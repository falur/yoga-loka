<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Command\LikePost;

use App\Modules\Posts\Application\Notification\PostNotificationType;
use App\Modules\Posts\Application\Post\PostContentComposer;
use App\Modules\Posts\Application\Post\PostVisibilityPolicy;
use App\Modules\Posts\Domain\Entity\PostLike;
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
 * Лайк записи. Невидимая/недоступная запись (черновик/удалена/заблокирована) -> 404. Идемпотентно:
 * повторный лайк — no-op без второго инкремента и уведомления. Автору записи (кроме self)
 * стейджится post_like.
 */
final readonly class LikePostHandler
{
    public function __construct(
        private PostRepository $postRepository,
        private PostLikeRepository $postLikeRepository,
        private PostContentComposer $composer,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}

    #[Transactional]
    #[LogOperation]
    public function handle(LikePostCommand $command): void
    {
        $userId = UserId::fromString($command->authUserId);

        $post = $this->postRepository->findById(PostId::fromString($command->postId))
            ?? throw new NotFoundException('app.posts.not_found');

        if (!PostVisibilityPolicy::isActionable($post)) {
            throw new NotFoundException('app.posts.not_found');
        }

        if ($this->postLikeRepository->existsByPostAndUser(postId: $post->id, userId: $userId)) {
            $this->logger->debug(message: 'Повторный лайк записи — no-op.', context: ['postId' => $post->id->value()]);

            return;
        }

        $this->entityManager->persist(PostLike::create(postId: $post->id, userId: $userId));
        $post->incrementLikes();
        $this->entityManager->persist($post);

        $this->composer->notifyPostAuthor(
            type: PostNotificationType::PostLike,
            postAuthor: $post->userId,
            actorUserId: $command->authUserId,
            postId: $post->id->value(),
        );

        $this->entityManager->run();
    }
}
