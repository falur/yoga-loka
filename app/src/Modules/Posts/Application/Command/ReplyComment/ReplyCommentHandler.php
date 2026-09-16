<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Command\ReplyComment;

use App\Modules\Posts\Application\Command\CommentPost\CommentComposer;
use App\Modules\Posts\Application\Command\CreatePost\PostNotificationType;
use App\Modules\Posts\Domain\Entity\Comment;
use App\Modules\Posts\Domain\ValueObject\CommentId;
use App\Modules\Posts\Domain\ValueObject\CommentParent;
use App\Modules\Posts\Domain\ValueObject\CommentText;
use App\Modules\Posts\Domain\Repository\CommentRepository;
use App\Modules\Posts\Domain\Repository\PostRepository;
use App\Modules\Posts\Domain\Service\PostVisibilityPolicy;
use App\Modules\Posts\Domain\Exception\CommentNotFoundException;
use App\Modules\Posts\Domain\Exception\PostNotFoundException;
use App\Shared\Domain\ValueObject\UserId;
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
    ) {}

    #[Transactional]
    #[LogOperation]
    public function handle(ReplyCommentCommand $command): ReplyCommentResult
    {
        $authUserId = UserId::fromString($command->authUserId);

        $parent = $this->commentRepository->findById(CommentId::fromString($command->commentId))
            ?? throw new CommentNotFoundException();

        if ($parent->isDeleted()) {
            throw new CommentNotFoundException();
        }

        // Запись существующего комментария всегда есть (FK), но проверку null оставляем в одном
        // условии с доступностью: ответ допустим только на доступную (опубликованную) запись.
        $post = $this->postRepository->findById($parent->postId);

        if ($post === null || !PostVisibilityPolicy::isActionable($post)) {
            throw new PostNotFoundException();
        }

        $comment = Comment::create(
            postId: $parent->postId,
            userId: $authUserId,
            text: CommentText::fromString($command->text),
            parent: CommentParent::pointingTo($parent->id->value()),
        );

        $mentions = $this->composer->attachMentionsAndNotify(
            comment: $comment,
            mentionIds: $command->mentions,
            actorUserId: $command->authUserId,
            primaryRecipientUserId: $parent->userId->value(),
            primaryType: PostNotificationType::CommentReply,
        );

        // Один прогон EntityManager на сценарий: ответ с упоминаниями только ставятся в очередь,
        // а родитель — второй экземпляр того же корня Comment — флашит всё разом своим save().
        $this->commentRepository->addWithMentions(comment: $comment, mentions: $mentions);

        $parent->incrementReplies();
        $this->commentRepository->save($parent);

        return new ReplyCommentResult(commentId: $comment->id->value());
    }
}
