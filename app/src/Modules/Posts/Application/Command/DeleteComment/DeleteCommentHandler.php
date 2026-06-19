<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Command\DeleteComment;

use App\Modules\Posts\Domain\ValueObject\CommentDeletedAt;
use App\Modules\Posts\Domain\ValueObject\CommentDeletedBy;
use App\Modules\Posts\Domain\ValueObject\CommentDeletionReason;
use App\Modules\Posts\Domain\ValueObject\CommentId;
use App\Modules\Posts\Domain\ValueObject\PostId;
use App\Modules\Posts\Repository\CommentRepository;
use App\Modules\Posts\Repository\PostRepository;
use App\Shared\Domain\Exception\ForbiddenException;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\ValueObject\UserId;
use Cycle\ORM\EntityManagerInterface;
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
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}

    #[Transactional]
    #[LogOperation]
    public function handle(DeleteCommentCommand $command): void
    {
        $comment = $this->commentRepository->findById(CommentId::fromString($command->commentId))
            ?? throw new NotFoundException('app.posts.comment_not_found');

        if (!$comment->userId->equals(UserId::fromString($command->authUserId))) {
            throw new ForbiddenException('app.posts.forbidden');
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
        $this->entityManager->persist($comment);

        $this->mirrorCounter(postId: $comment->postId->value(), parentId: $comment->parent->value());

        $this->entityManager->run();

        $this->logger->debug(message: 'Комментарий удалён.', context: ['commentId' => $comment->id->value()]);
    }

    private function mirrorCounter(string $postId, string|null $parentId): void
    {
        if ($parentId === null) {
            $post = $this->postRepository->findById(PostId::fromString($postId));

            if ($post !== null && $post->commentsCount->value() > 0) {
                $post->decrementComments();
                $this->entityManager->persist($post);
            }

            return;
        }

        $parent = $this->commentRepository->findById(CommentId::fromString($parentId));

        if ($parent !== null && $parent->repliesCount->value() > 0) {
            $parent->decrementReplies();
            $this->entityManager->persist($parent);
        }
    }
}
