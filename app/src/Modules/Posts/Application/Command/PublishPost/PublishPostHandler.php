<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Command\PublishPost;

use App\Modules\Posts\Application\Command\CreatePost\PostContentComposer;
use App\Modules\Posts\Domain\Enum\PostStatus;
use App\Modules\Posts\Domain\ValueObject\PostId;
use App\Modules\Posts\Domain\Repository\PostRepository;
use App\Modules\Posts\Domain\Exception\NotAuthorException;
use App\Modules\Posts\Domain\Exception\PostNotFoundException;
use App\Shared\Domain\ValueObject\UserId;
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
        private LoggerInterface $logger,
    ) {}

    #[Transactional]
    #[LogOperation]
    public function handle(PublishPostCommand $command): PublishPostResult
    {
        $post = $this->postRepository->findById(PostId::fromString($command->postId))
            ?? throw new PostNotFoundException();

        if ($post->deletion->isDeleted() || $post->status === PostStatus::Blocked) {
            throw new PostNotFoundException();
        }

        if (!$post->userId->equals(UserId::fromString($command->authUserId))) {
            throw new NotAuthorException();
        }

        if ($post->status === PostStatus::Draft) {
            $post->publish();
            $this->postRepository->save($post);
            $this->composer->notifyPostMentions(post: $post, actorUserId: $command->authUserId);

            $this->logger->debug(message: 'Запись опубликована.', context: ['postId' => $post->id->value()]);
        }

        return new PublishPostResult(postId: $post->id->value());
    }
}
