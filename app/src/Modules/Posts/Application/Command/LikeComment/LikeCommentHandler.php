<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Command\LikeComment;

use App\Modules\Posts\Application\Notification\PostNotificationType;
use App\Modules\Posts\Application\Post\CommentComposer;
use App\Modules\Posts\Domain\Entity\CommentLike;
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
 * Лайк комментария. Удалённый/несуществующий комментарий -> 404. Идемпотентно: повторный лайк —
 * no-op. Автору комментария (кроме self) стейджится comment_like.
 */
final readonly class LikeCommentHandler
{
    public function __construct(
        private CommentRepository $commentRepository,
        private CommentLikeRepository $commentLikeRepository,
        private CommentComposer $composer,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}

    #[Transactional]
    #[LogOperation]
    public function handle(LikeCommentCommand $command): void
    {
        $userId = UserId::fromString($command->authUserId);

        $comment = $this->commentRepository->findById(CommentId::fromString($command->commentId))
            ?? throw new NotFoundException('app.posts.comment_not_found');

        if ($comment->isDeleted()) {
            throw new NotFoundException('app.posts.comment_not_found');
        }

        if ($this->commentLikeRepository->existsByCommentAndUser(commentId: $comment->id, userId: $userId)) {
            $this->logger->debug(message: 'Повторный лайк комментария — no-op.', context: ['commentId' => $comment->id->value()]);

            return;
        }

        $this->entityManager->persist(CommentLike::create(commentId: $comment->id, userId: $userId));
        $comment->incrementLikes();
        $this->entityManager->persist($comment);

        $this->composer->notifyCommentAuthor(
            type: PostNotificationType::CommentLike,
            commentAuthor: $comment->userId,
            actorUserId: $command->authUserId,
            commentId: $comment->id->value(),
        );

        $this->entityManager->run();
    }
}
