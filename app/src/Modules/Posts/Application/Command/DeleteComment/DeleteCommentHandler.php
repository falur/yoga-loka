<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Command\DeleteComment;

use App\Modules\Posts\Domain\Entity\Comment;
use App\Modules\Posts\Domain\ValueObject\CommentDeletedAt;
use App\Modules\Posts\Domain\ValueObject\CommentDeletedBy;
use App\Modules\Posts\Domain\ValueObject\CommentDeletionReason;
use App\Modules\Posts\Domain\ValueObject\CommentId;
use App\Modules\Posts\Domain\ValueObject\PostId;
use App\Modules\Posts\Domain\Repository\CommentRepository;
use App\Modules\Posts\Domain\Repository\PostRepository;
use App\Modules\Posts\Domain\Exception\CommentNotFoundException;
use App\Modules\Posts\Domain\Exception\NotAuthorException;
use App\Shared\Domain\ValueObject\UserId;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;
use GianTiaga\SpiralCqrs\Attribute\Transactional;
use Psr\Log\LoggerInterface;

/**
 * Мягкое удаление своего комментария. Чужой -> 403, несуществующий -> 404, повторное удаление ->
 * идемпотентный no-op. Зеркалит счётчик: комментарий верхнего уровня уменьшает счётчик записи,
 * ответ — счётчик ответов родителя. Счётчики не уходят ниже нуля.
 */
final readonly class DeleteCommentHandler
{
    public function __construct(
        private CommentRepository $commentRepository,
        private PostRepository $postRepository,
        private LoggerInterface $logger,
    ) {}

    #[Transactional]
    #[LogOperation]
    public function handle(DeleteCommentCommand $command): void
    {
        $comment = $this->commentRepository->findById(CommentId::fromString($command->commentId))
            ?? throw new CommentNotFoundException();

        if (!$comment->userId->equals(UserId::fromString($command->authUserId))) {
            throw new NotAuthorException();
        }

        if ($comment->isDeleted()) {
            $this->logger->debug(message: 'Повторное удаление комментария — no-op.', context: ['commentId' => $comment->id->value()]);

            return;
        }

        $now = new \DateTimeImmutable();
        $comment->delete(
            deletedBy: CommentDeletedBy::by(UserId::fromString($command->authUserId)),
            deletedAt: CommentDeletedAt::at($now),
            deletionReason: CommentDeletionReason::none(),
        );

        $this->mirrorCounter(comment: $comment, postId: $comment->postId->value(), parentId: $comment->parent->value());

        $this->logger->debug(message: 'Комментарий удалён.', context: ['commentId' => $comment->id->value()]);
    }

    /**
     * Один прогон EntityManager на сценарий: счётчик второго корня (записи для комментария
     * верхнего уровня, родителя для ответа) только ставится в очередь через add(), а флашит всё
     * разом save() самого удаляемого комментария — оба репозитория используют общий
     * shared-singleton EntityManager запроса, как `LoginCodeRepository`/`RegistrationTicketRepository`.
     */
    private function mirrorCounter(Comment $comment, string $postId, string|null $parentId): void
    {
        if ($parentId === null) {
            $post = $this->postRepository->findById(PostId::fromString($postId));

            if ($post !== null) {
                if ($post->commentsCount->value() > 0) {
                    $post->decrementComments();
                }

                $this->postRepository->add($post);
            }
        } else {
            $parent = $this->commentRepository->findById(CommentId::fromString($parentId));

            if ($parent !== null) {
                if ($parent->repliesCount->value() > 0) {
                    $parent->decrementReplies();
                }

                $this->commentRepository->add($parent);
            }
        }

        $this->commentRepository->save($comment);
    }
}
