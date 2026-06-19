<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Command\UnlikeComment;

use App\Modules\Posts\Domain\ValueObject\CommentId;
use App\Modules\Posts\Repository\CommentLikeRepository;
use App\Modules\Posts\Repository\CommentRepository;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\ValueObject\UserId;
use Cycle\ORM\EntityManagerInterface;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;
use GianTiaga\SpiralCqrs\Attribute\Transactional;
use Psr\Log\LoggerInterface;

/**
 * Снятие лайка с комментария. Несуществующий комментарий -> 404. Идемпотентно: снятие
 * отсутствующего лайка — no-op; счётчик не уходит ниже нуля.
 */
final readonly class UnlikeCommentHandler
{
    public function __construct(
        private CommentRepository $commentRepository,
        private CommentLikeRepository $commentLikeRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}

    #[Transactional]
    #[LogOperation]
    public function handle(UnlikeCommentCommand $command): void
    {
        $userId = UserId::fromString($command->authUserId);

        $comment = $this->commentRepository->findById(CommentId::fromString($command->commentId))
            ?? throw new NotFoundException('app.posts.comment_not_found');

        $like = $this->commentLikeRepository->findByCommentAndUser(commentId: $comment->id, userId: $userId);

        if ($like === null) {
            $this->logger->debug(message: 'Снятие отсутствующего лайка комментария — no-op.', context: ['commentId' => $comment->id->value()]);

            return;
        }

        $this->entityManager->delete($like);

        if ($comment->likesCount->value() > 0) {
            $comment->decrementLikes();
            $this->entityManager->persist($comment);
        }

        $this->entityManager->run();
    }
}
