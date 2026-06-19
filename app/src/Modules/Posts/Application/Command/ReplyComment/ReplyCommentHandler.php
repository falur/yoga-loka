<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Command\ReplyComment;

use App\Modules\Posts\Application\Notification\PostNotificationType;
use App\Modules\Posts\Application\Post\CommentComposer;
use App\Modules\Posts\Application\Post\PostVisibilityPolicy;
use App\Modules\Posts\Application\View\CommentView;
use App\Modules\Posts\Application\View\CommentViewAssembler;
use App\Modules\Posts\Domain\Entity\Comment;
use App\Modules\Posts\Domain\ValueObject\CommentId;
use App\Modules\Posts\Domain\ValueObject\CommentParent;
use App\Modules\Posts\Domain\ValueObject\CommentText;
use App\Modules\Posts\Repository\CommentRepository;
use App\Modules\Posts\Repository\PostRepository;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\ValueObject\UserId;
use Cycle\ORM\EntityManagerInterface;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;
use GianTiaga\SpiralCqrs\Attribute\Transactional;

/**
 * Ответ на комментарий. Удалённый родитель/недоступная запись -> 404. Увеличивает счётчик ответов
 * родителя; автору родителя (кроме self) стейджится comment_reply, упомянутым — comment_mention
 * (с дедупом). Автор записи post_commented на ответ НЕ получает (решение 14).
 */
final readonly class ReplyCommentHandler
{
    public function __construct(
        private CommentRepository $commentRepository,
        private PostRepository $postRepository,
        private CommentComposer $composer,
        private CommentViewAssembler $commentViewAssembler,
        private EntityManagerInterface $entityManager,
    ) {}

    #[Transactional]
    #[LogOperation]
    public function handle(ReplyCommentCommand $command): CommentView
    {
        $authUserId = UserId::fromString($command->authUserId);

        $parent = $this->commentRepository->findById(CommentId::fromString($command->commentId))
            ?? throw new NotFoundException('app.posts.comment_not_found');

        if ($parent->isDeleted()) {
            throw new NotFoundException('app.posts.comment_not_found');
        }

        // Запись существующего комментария всегда есть (FK), но проверку null оставляем в одном
        // условии с доступностью: ответ допустим только на доступную (опубликованную) запись.
        $post = $this->postRepository->findById($parent->postId);

        if ($post === null || !PostVisibilityPolicy::isActionable($post)) {
            throw new NotFoundException('app.posts.not_found');
        }

        $comment = Comment::create(
            postId: $parent->postId,
            userId: $authUserId,
            text: CommentText::fromString($command->text),
            parent: CommentParent::pointingTo($parent->id->value()),
        );
        $this->entityManager->persist($comment);

        $parent->incrementReplies();
        $this->entityManager->persist($parent);

        $this->composer->attachMentionsAndNotify(
            comment: $comment,
            mentionIds: $command->mentions,
            actorUserId: $command->authUserId,
            primaryRecipientUserId: $parent->userId->value(),
            primaryType: PostNotificationType::CommentReply,
        );

        $this->entityManager->run();

        return $this->commentViewAssembler->fromComment(comment: $comment, viewer: $authUserId);
    }
}
