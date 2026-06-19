<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Command\PublishPost;

use App\Modules\Posts\Application\Post\PostContentComposer;
use App\Modules\Posts\Application\View\PostView;
use App\Modules\Posts\Application\View\PostViewAssembler;
use App\Modules\Posts\Domain\Enum\PostStatus;
use App\Modules\Posts\Domain\ValueObject\PostId;
use App\Modules\Posts\Repository\PostRepository;
use App\Shared\Domain\Exception\ForbiddenException;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\ValueObject\UserId;
use Cycle\ORM\EntityManagerInterface;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;
use GianTiaga\SpiralCqrs\Attribute\Transactional;
use Psr\Log\LoggerInterface;

/**
 * Публикация черновика владельцем. Удалённая/заблокированная/несуществующая запись -> 404, чужая
 * -> 403, уже опубликованная -> идемпотентный успешный no-op.
 */
final readonly class PublishPostHandler
{
    public function __construct(
        private PostRepository $postRepository,
        private PostContentComposer $composer,
        private PostViewAssembler $postViewAssembler,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}

    #[Transactional]
    #[LogOperation]
    public function handle(PublishPostCommand $command): PostView
    {
        $post = $this->postRepository->findById(PostId::fromString($command->postId))
            ?? throw new NotFoundException('app.posts.not_found');

        if ($post->deletion->isDeleted() || $post->status === PostStatus::Blocked) {
            throw new NotFoundException('app.posts.not_found');
        }

        if (!$post->userId->equals(UserId::fromString($command->authUserId))) {
            throw new ForbiddenException('app.posts.forbidden');
        }

        if ($post->status === PostStatus::Draft) {
            $post->publish();
            $this->entityManager->persist($post);
            $this->composer->notifyPostMentions(post: $post, actorUserId: $command->authUserId);
            $this->entityManager->run();

            $this->logger->debug(message: 'Запись опубликована.', context: ['postId' => $post->id->value()]);
        }

        return $this->postViewAssembler->fromPost(post: $post, viewer: UserId::fromString($command->authUserId));
    }
}
