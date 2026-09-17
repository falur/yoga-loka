<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Command\DeletePost;

use App\Modules\Posts\Domain\ValueObject\PostId;
use App\Modules\Posts\Domain\Repository\PostRepository;
use App\Modules\Posts\Domain\Exception\NotAuthorException;
use App\Modules\Posts\Domain\Exception\PostNotFoundException;
use App\Shared\Domain\ValueObject\UserId;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;
use GianTiaga\SpiralCqrs\Attribute\Transactional;
use Psr\Log\LoggerInterface;

/**
 * Мягкое удаление своей записи. Чужая -> 403, несуществующая -> 404, повторное удаление ->
 * идемпотентный no-op (счётчик репостов оригинала не уводится в минус повторно). Если удаляемая
 * запись — репост, у оригинала уменьшается счётчик репостов.
 */
final readonly class DeletePostHandler
{
    public function __construct(
        private PostRepository $postRepository,
        private LoggerInterface $logger,
    ) {}

    #[Transactional]
    #[LogOperation]
    public function handle(DeletePostCommand $command): void
    {
        $post = $this->postRepository->findById(PostId::fromString($command->postId))
            ?? throw new PostNotFoundException();

        if (!$post->userId->equals(UserId::fromString($command->authUserId))) {
            throw new NotAuthorException();
        }

        if ($post->deletion->isDeleted()) {
            $this->logger->debug(message: 'Повторное удаление записи — no-op.', context: ['postId' => $post->id->value()]);

            return;
        }

        $post->softDelete(new \DateTimeImmutable());

        $originalId = $post->original->value();
        $original = $originalId !== null ? $this->postRepository->findById(PostId::fromString($originalId)) : null;

        if ($original !== null) {
            if ($original->repostsCount->value() > 0) {
                $original->decrementReposts();
            }

            $this->postRepository->saveWithOriginal(post: $post, original: $original);
        } else {
            $this->postRepository->save($post);
        }

        $this->logger->debug(message: 'Запись удалена.', context: ['postId' => $post->id->value()]);
    }
}
