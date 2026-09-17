<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Command\LikeComment;

use App\Modules\Posts\Application\Command\CommentPost\CommentComposer;
use App\Modules\Posts\Application\Command\CreatePost\PostNotificationType;
use App\Modules\Posts\Domain\Entity\CommentLike;
use App\Modules\Posts\Domain\ValueObject\CommentId;
use App\Modules\Posts\Domain\Repository\CommentRepository;
use App\Modules\Posts\Domain\Exception\CommentNotFoundException;
use App\Shared\Domain\ValueObject\UserId;
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
        private CommentComposer $composer,
        private LoggerInterface $logger,
    ) {}

    #[Transactional]
    #[LogOperation]
    public function handle(LikeCommentCommand $command): void
    {
        $userId = UserId::fromString($command->authUserId);

        $comment = $this->commentRepository->findById(CommentId::fromString($command->commentId))
            ?? throw new CommentNotFoundException();

        if ($comment->isDeleted()) {
            throw new CommentNotFoundException();
        }

        if ($this->commentRepository->existsLikeByCommentAndUser(commentId: $comment->id, userId: $userId)) {
            $this->logger->debug(message: 'Повторный лайк комментария — no-op.', context: ['commentId' => $comment->id->value()]);

            return;
        }

        $like = CommentLike::create(commentId: $comment->id, userId: $userId);
        $comment->incrementLikes();
        $this->commentRepository->saveWithLike(comment: $comment, like: $like);

        $this->composer->notifyCommentAuthor(
            type: PostNotificationType::CommentLike,
            commentAuthor: $comment->userId,
            actorUserId: $command->authUserId,
            commentId: $comment->id->value(),
        );
    }
}
