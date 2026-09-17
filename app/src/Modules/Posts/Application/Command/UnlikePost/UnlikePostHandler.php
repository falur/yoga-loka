<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Command\UnlikePost;

use App\Modules\Posts\Domain\ValueObject\PostId;
use App\Modules\Posts\Domain\Repository\PostRepository;
use App\Modules\Posts\Domain\Exception\PostNotFoundException;
use App\Shared\Domain\ValueObject\UserId;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;
use GianTiaga\SpiralCqrs\Attribute\Transactional;
use Psr\Log\LoggerInterface;

/**
 * Снятие лайка с записи. Несуществующая запись -> 404. Идемпотентно: снятие отсутствующего лайка —
 * no-op; счётчик не уходит ниже нуля.
 */
final readonly class UnlikePostHandler
{
    public function __construct(
        private PostRepository $postRepository,
        private LoggerInterface $logger,
    ) {}

    #[Transactional]
    #[LogOperation]
    public function handle(UnlikePostCommand $command): void
    {
        $userId = UserId::fromString($command->authUserId);

        $post = $this->postRepository->findById(PostId::fromString($command->postId))
            ?? throw new PostNotFoundException();

        $like = $this->postRepository->findLikeByPostAndUser(postId: $post->id, userId: $userId);

        if ($like === null) {
            $this->logger->debug(message: 'Снятие отсутствующего лайка записи — no-op.', context: ['postId' => $post->id->value()]);

            return;
        }

        if ($post->likesCount->value() > 0) {
            $post->decrementLikes();
        }

        $this->postRepository->removeLike(like: $like, post: $post);
    }
}
