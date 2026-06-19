<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\User\Application;

use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Media\Application\Query\FindMediaUrl\FindMediaUrlHandler;
use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Modules\Media\Domain\ValueObject\MediaExpiration;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaStorageKey;
use App\Modules\Media\Repository\MediaRepository;
use App\Modules\User\Application\Profile\UserPublicProfileAssembler;
use App\Modules\User\Domain\Entity\User;
use App\Modules\User\Domain\ValueObject\Email;
use App\Modules\User\Domain\ValueObject\UserAvatar;
use App\Modules\User\Domain\ValueObject\UserName;
use App\Modules\User\Domain\ValueObject\UserNickname;
use App\Modules\User\Repository\UserRepository;
use App\Shared\Domain\Enum\Locale;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Configuration\User\UserConfig;
use Cycle\ORM\EntityManagerInterface;
use Tests\DatabaseTestCase;

/**
 * Общая основа feature-тестов публичного профиля: создаёт пользователей и медиа, собирает
 * UserPublicProfileAssembler со стабом файлового сервиса (URL предсказуем, без обращения к S3).
 */
abstract class UserApplicationTestCase extends DatabaseTestCase
{
    protected const string STUBBED_AVATAR_URL = 'https://cdn.example/real-avatar.jpg';

    private int $userCounter = 0;

    protected function persistUser(UserAvatar|null $avatar = null, Locale $locale = Locale::Ru): User
    {
        $this->userCounter++;

        $user = User::create(
            name: UserName::fromString('Йога Тест'),
            email: Email::fromString(\sprintf('profile.user%d@example.com', $this->userCounter)),
            nickname: UserNickname::fromString(\sprintf('profile.user%d', $this->userCounter)),
            locale: $locale,
        );

        if ($avatar !== null && !$avatar->isEmpty()) {
            $user->setAvatar($avatar);
        }

        $this->persist($user);

        return $user;
    }

    protected function persistReadyPublicMedia(): Media
    {
        $media = $this->createMedia(MediaVisibility::Public);
        $media->markUploaded();
        $media->markReadyMovedTo(
            MediaStorage::Public,
            MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Image, extension: 'jpg'),
        );
        $this->persist($media);

        return $media;
    }

    protected function persistNotReadyMedia(): Media
    {
        $media = $this->createMedia(MediaVisibility::Public);
        $this->persist($media);

        return $media;
    }

    protected function profileHandlerAssembler(): UserPublicProfileAssembler
    {
        $fileService = $this->createStub(MediaFileServiceContract::class);
        $fileService->method('publicUrl')->willReturn(self::STUBBED_AVATAR_URL);

        return new UserPublicProfileAssembler(
            findMediaUrlHandler: new FindMediaUrlHandler(
                mediaRepository: $this->getContainer()->get(MediaRepository::class),
                mediaFileService: $fileService,
            ),
            userConfig: $this->getContainer()->get(UserConfig::class),
        );
    }

    protected function defaultAvatarUrl(): string
    {
        return $this->getContainer()->get(UserConfig::class)->defaultAvatarUrl;
    }

    protected function userRepository(): UserRepository
    {
        return $this->getContainer()->get(UserRepository::class);
    }

    protected function persist(object $entity): void
    {
        $this->entityManager()->persist($entity);
        $this->entityManager()->run();
    }

    protected function entityManager(): EntityManagerInterface
    {
        return $this->getContainer()->get(EntityManagerInterface::class);
    }

    private function createMedia(MediaVisibility $visibility): Media
    {
        $storageKey = MediaStorageKey::generate();

        return Media::create(
            storageKey: $storageKey,
            type: MediaType::Image,
            visibility: $visibility,
            path: MediaPath::originalUpload(storageKey: $storageKey, extension: 'jpg'),
            mimeType: MediaMimeType::fromString('image/jpeg'),
            size: MediaFileSize::fromInt(1024),
            uploadedById: UserId::generate(),
            expiration: MediaExpiration::temporaryUntil(new \DateTimeImmutable('+1 hour')),
        );
    }
}
