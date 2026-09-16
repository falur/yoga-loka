<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Posts;

use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Modules\Media\Domain\ValueObject\MediaExpiration;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaStorageKey;
use App\Modules\Posts\Domain\Repository\CommentRepository;
use App\Modules\Posts\Domain\Repository\PostBlockRepository;
use App\Modules\Posts\Domain\Repository\PostRepository;
use App\Modules\User\Domain\Entity\User;
use App\Modules\User\Domain\ValueObject\Email;
use App\Modules\User\Domain\ValueObject\UserName;
use App\Modules\User\Domain\ValueObject\UserNickname;
use App\Shared\Domain\Enum\Locale;
use App\Shared\Domain\ValueObject\UserId;
use Cycle\ORM\EntityManagerInterface;
use Tests\DatabaseTestCase;

/**
 * Общая основа feature-тестов модуля Posts: реальные строки User и Media для FK,
 * persist-helper с немедленным flush в нужном порядке и доступ к репозиториям модуля.
 */
abstract class PostsRepositoryTestCase extends DatabaseTestCase
{
    private int $userCounter = 0;

    protected function entityManager(): EntityManagerInterface
    {
        return $this->getContainer()->get(EntityManagerInterface::class);
    }

    /**
     * Сохраняет одну сущность и сразу делает flush, чтобы кросс-табличные FK
     * (без Cycle relation) выполнялись в правильном порядке вставок.
     */
    protected function persist(object $entity): void
    {
        $this->entityManager()->persist($entity);
        $this->entityManager()->run();
    }

    protected function createUser(): User
    {
        $this->userCounter++;

        return User::create(
            name: UserName::fromString('Йога Тест'),
            email: Email::fromString(\sprintf('post.user%d@example.com', $this->userCounter)),
            nickname: UserNickname::fromString(\sprintf('yoga.user%d', $this->userCounter)),
            locale: Locale::Ru,
        );
    }

    protected function createMedia(UserId $uploadedBy): Media
    {
        $storageKey = MediaStorageKey::generate();

        return Media::create(
            storageKey: $storageKey,
            type: MediaType::Image,
            visibility: MediaVisibility::Private,
            path: MediaPath::originalUpload(storageKey: $storageKey, extension: 'jpg'),
            mimeType: MediaMimeType::fromString('image/jpeg'),
            size: MediaFileSize::fromInt(1024),
            uploadedById: $uploadedBy,
            expiration: MediaExpiration::temporaryUntil(new \DateTimeImmutable('+1 hour')),
        );
    }

    protected function postRepository(): PostRepository
    {
        return $this->getContainer()->get(PostRepository::class);
    }

    protected function commentRepository(): CommentRepository
    {
        return $this->getContainer()->get(CommentRepository::class);
    }

    protected function postBlockRepository(): PostBlockRepository
    {
        return $this->getContainer()->get(PostBlockRepository::class);
    }
}
