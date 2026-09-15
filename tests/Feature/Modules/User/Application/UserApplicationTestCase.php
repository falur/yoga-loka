<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\User\Application;

use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Media\Application\Command\MakeMediaPermanent\MakeMediaPermanentHandler;
use App\Modules\Media\Application\Query\CheckMediaAttachable\CheckMediaAttachableHandler;
use App\Modules\Media\Application\Query\FindMediaUrls\FindMediaUrlsHandler;
use App\Modules\Media\Infrastructure\Spiral\PublicApi\MediaProvider;
use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Entity\MediaImageConversion;
use App\Modules\Media\Domain\Enum\MediaConversionStatus;
use App\Modules\Media\Domain\Enum\MediaImageConversionType;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Modules\Media\Domain\ValueObject\MediaExpiration;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaPixelDimension;
use App\Modules\Media\Domain\ValueObject\MediaStorageKey;
use App\Modules\Media\Infrastructure\Storage\MediaUrlService;
use App\Modules\Media\Repository\MediaRepository;
use App\Modules\User\Application\Command\CreateUser\CreateUserHandler;
use App\Modules\User\Application\Profile\UserPublicProfileAssembler;
use App\Modules\User\Application\Query\CheckUsersExist\CheckUsersExistHandler;
use App\Modules\User\Application\Query\FindUserForAuth\FindUserForAuthHandler;
use App\Modules\User\Application\Query\GetUserPublicProfile\GetUserPublicProfileHandler;
use App\Modules\User\Application\Query\GetUserPublicProfiles\GetUserPublicProfilesHandler;
use App\Modules\User\Domain\Entity\User;
use App\Modules\User\Domain\ValueObject\Email;
use App\Modules\User\Domain\ValueObject\UserAvatar;
use App\Modules\User\Domain\ValueObject\UserName;
use App\Modules\User\Domain\ValueObject\UserNickname;
use App\Modules\User\Infrastructure\Spiral\PublicApi\UserProvider;
use App\Modules\User\Repository\ReservedNicknameRepository;
use App\Modules\User\Repository\UserRepository;
use App\Shared\Domain\Enum\Locale;
use App\Shared\Domain\Locale\LocaleResolver;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Spiral\Configuration\Media\MediaConfig;
use Cycle\ORM\EntityManagerInterface;
use GianTiaga\SpiralCqrs\CommandBusInterface;
use GianTiaga\SpiralCqrs\QueryBusInterface;
use Psr\Log\NullLogger;
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

    protected function persistReadyOriginalRemovedMedia(): Media
    {
        $media = $this->createMedia(MediaVisibility::Public);
        $media->markUploaded();
        $media->markReadyMovedTo(
            MediaStorage::Public,
            MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Image, extension: 'jpg'),
        );
        $media->markReadyOriginalRemoved();
        $this->persist($media);

        return $media;
    }

    protected function persistThumbnailConversion(Media $media): MediaImageConversion
    {
        $conversion = MediaImageConversion::create(
            media: $media,
            type: MediaImageConversionType::Thumbnail,
            status: MediaConversionStatus::Ready,
            storage: $media->storage,
            path: MediaPath::imageConversion(
                storageKey: $media->storageKey,
                type: MediaImageConversionType::Thumbnail,
                extension: 'jpg',
            ),
            mimeType: MediaMimeType::fromString('image/jpeg'),
            size: MediaFileSize::fromInt(256),
            width: MediaPixelDimension::fromInt(100),
            height: MediaPixelDimension::fromInt(100),
        );
        $this->persist($conversion);

        return $conversion;
    }

    protected function profileHandlerAssembler(): UserPublicProfileAssembler
    {
        $fileService = $this->createStub(MediaFileServiceContract::class);
        $fileService->method('publicUrl')->willReturn(self::STUBBED_AVATAR_URL);

        return new UserPublicProfileAssembler(
            media: new MediaProvider(
                commandBus: $this->getContainer()->get(CommandBusInterface::class),
                queryBus: $this->getContainer()->get(QueryBusInterface::class),
                findMediaUrlsHandler: new FindMediaUrlsHandler(
                    mediaRepository: $this->getContainer()->get(MediaRepository::class),
                    mediaUrlService: new MediaUrlService(
                        mediaFileService: $fileService,
                        mediaConfig: $this->getContainer()->get(MediaConfig::class),
                    ),
                ),
                checkMediaAttachableHandler: $this->getContainer()->get(CheckMediaAttachableHandler::class),
                makeMediaPermanentHandler: $this->getContainer()->get(MakeMediaPermanentHandler::class),
            ),
        );
    }

    /**
     * Публичный контракт модуля поверх тех же сценариев, что и одиночные тесты: аватар разрешается
     * через стаб файлового сервиса, поэтому ссылка предсказуема и к S3 обращения нет.
     */
    protected function userProvider(): UserProvider
    {
        $assembler = $this->profileHandlerAssembler();

        return new UserProvider(
            commandBus: $this->getContainer()->get(CommandBusInterface::class),
            queryBus: $this->getContainer()->get(QueryBusInterface::class),
            createUserHandler: new CreateUserHandler(
                userRepository: $this->userRepository(),
                reservedNicknameRepository: $this->getContainer()->get(ReservedNicknameRepository::class),
                entityManager: $this->entityManager(),
                logger: new NullLogger(),
                localeResolver: $this->getContainer()->get(LocaleResolver::class),
            ),
            findUserForAuthHandler: new FindUserForAuthHandler(userRepository: $this->userRepository()),
            checkUsersExistHandler: new CheckUsersExistHandler(userRepository: $this->userRepository()),
            getUserPublicProfileHandler: new GetUserPublicProfileHandler(
                userRepository: $this->userRepository(),
                assembler: $assembler,
            ),
            getUserPublicProfilesHandler: new GetUserPublicProfilesHandler(
                userRepository: $this->userRepository(),
                assembler: $assembler,
            ),
        );
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
