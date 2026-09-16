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
use App\Modules\Posts\Domain\Repository\CommentRepository;
use App\Modules\Posts\Domain\Repository\PostRepository;
use App\Modules\Posts\Domain\Exception\PostNotFoundException;
use App\Shared\Domain\ValueObject\UserId;
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
        private CommentRepository $commentRepository,
        private CommentComposer $composer,
        private CommentViewAssembler $commentViewAssembler,
    ) {}

    #[Transactional]
    #[LogOperation]
    public function handle(CommentPostCommand $command): CommentView
    {
        $authUserId = UserId::fromString($command->authUserId);

        $post = $this->postRepository->findById(PostId::fromString($command->postId))
            ?? throw new PostNotFoundException();

        if (!PostVisibilityPolicy::isActionable($post)) {
            throw new PostNotFoundException();
        }

        $comment = Comment::create(
            postId: $post->id,
            userId: $authUserId,
            text: CommentText::fromString($command->text),
            parent: CommentParent::none(),
        );

        $post->incrementComments();

        $mentions = $this->composer->attachMentionsAndNotify(
            comment: $comment,
            mentionIds: $command->mentions,
            actorUserId: $command->authUserId,
            primaryRecipientUserId: $post->userId->value(),
            primaryType: PostNotificationType::PostCommented,
        );

        // Один прогон EntityManager на сценарий: комментарий с упоминаниями только ставятся в
        // очередь, а запись флашит всё разом своим save() — оба репозитория используют общий
        // shared-singleton EntityManager запроса, как `LoginCodeRepository`/`StoredOutboxEventRepository`.
        $this->commentRepository->addWithMentions(comment: $comment, mentions: $mentions);
        $this->postRepository->save($post);

        return $this->commentViewAssembler->fromComment(comment: $comment, viewer: $authUserId);
    }
}
