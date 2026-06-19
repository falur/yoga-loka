<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Command\UnlikePost;

use App\Modules\Posts\Domain\ValueObject\PostId;
use App\Modules\Posts\Repository\PostLikeRepository;
use App\Modules\Posts\Repository\PostRepository;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\ValueObject\UserId;
use Cycle\ORM\EntityManagerInterface;
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
        private PostLikeRepository $postLikeRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}

    #[Transactional]
    #[LogOperation]
    public function handle(UnlikePostCommand $command): void
    {
        $userId = UserId::fromString($command->authUserId);

        $post = $this->postRepository->findById(PostId::fromString($command->postId))
            ?? throw new NotFoundException('app.posts.not_found');

        $like = $this->postLikeRepository->findByPostAndUser(postId: $post->id, userId: $userId);

        if ($like === null) {
            $this->logger->debug(message: 'Снятие отсутствующего лайка записи — no-op.', context: ['postId' => $post->id->value()]);

            return;
        }

        $this->entityManager->delete($like);

        if ($post->likesCount->value() > 0) {
            $post->decrementLikes();
            $this->entityManager->persist($post);
        }

        $this->entityManager->run();
    }
}
