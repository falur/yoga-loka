<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Command\CommentPost;

use App\Modules\Posts\Application\Notification\PostNotificationType;
use App\Modules\Posts\Application\Post\CommentComposer;
use App\Modules\Posts\Application\Post\PostVisibilityPolicy;
use App\Modules\Posts\Application\View\CommentView;
use App\Modules\Posts\Application\View\CommentViewAssembler;
use App\Modules\Posts\Domain\Entity\Comment;
use App\Modules\Posts\Domain\ValueObject\CommentParent;
use App\Modules\Posts\Domain\ValueObject\CommentText;
use App\Modules\Posts\Domain\ValueObject\PostId;
use App\Modules\Posts\Repository\PostRepository;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\ValueObject\UserId;
use Cycle\ORM\EntityManagerInterface;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;
use GianTiaga\SpiralCqrs\Attribute\Transactional;

/**
 * Комментарий верхнего уровня к записи. Невидимая/недоступная запись -> 404. Увеличивает счётчик
 * комментариев записи; автору записи (кроме self) стейджится post_commented, упомянутым —
 * comment_mention, с дедупом по получателю (mention перебивает post_commented).
 */
final readonly class CommentPostHandler
{
    public function __construct(
        private PostRepository $postRepository,
        private CommentComposer $composer,
        private CommentViewAssembler $commentViewAssembler,
        private EntityManagerInterface $entityManager,
    ) {}

    #[Transactional]
    #[LogOperation]
    public function handle(CommentPostCommand $command): CommentView
    {
        $authUserId = UserId::fromString($command->authUserId);

        $post = $this->postRepository->findById(PostId::fromString($command->postId))
            ?? throw new NotFoundException('app.posts.not_found');

        if (!PostVisibilityPolicy::isActionable($post)) {
            throw new NotFoundException('app.posts.not_found');
        }

        $comment = Comment::create(
            postId: $post->id,
            userId: $authUserId,
            text: CommentText::fromString($command->text),
            parent: CommentParent::none(),
        );
        $this->entityManager->persist($comment);

        $post->incrementComments();
        $this->entityManager->persist($post);

        $this->composer->attachMentionsAndNotify(
            comment: $comment,
            mentionIds: $command->mentions,
            actorUserId: $command->authUserId,
            primaryRecipientUserId: $post->userId->value(),
            primaryType: PostNotificationType::PostCommented,
        );

        $this->entityManager->run();

        return $this->commentViewAssembler->fromComment(comment: $comment, viewer: $authUserId);
    }
}
