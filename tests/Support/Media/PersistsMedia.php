<?php

declare(strict_types=1);

namespace Tests\Support\Media;

use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Media\Application\Command\MakeMediaPermanent\MakeMediaPermanentHandler;
use App\Modules\Media\Application\Query\CheckMediaAttachable\CheckMediaAttachableHandler;
use App\Modules\Media\Application\Query\FindMediaUrls\FindMediaUrlsHandler;
use App\Modules\Media\Infrastructure\Spiral\PublicApi\MediaProvider;
use App\Modules\Media\Public\Contract\MediaContract;
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
use App\Modules\Media\Infrastructure\Persistence\Cycle\Mapper\MediaImageConversionMapper;
use App\Modules\Media\Infrastructure\Persistence\Cycle\Mapper\MediaMapper;
use App\Modules\Media\Infrastructure\Storage\MediaUrlService;
use App\Modules\Media\Domain\Repository\MediaRepository;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Spiral\Configuration\Media\MediaConfig;
use Cycle\ORM\EntityManagerInterface;
use GianTiaga\SpiralCqrs\CommandBusInterface;
use GianTiaga\SpiralCqrs\QueryBusInterface;

/**
 * Сидинг медиа и сборка публичного контракта Media со стабом файлового сервиса (URL предсказуем, без
 * S3) — общая основа feature-тестов, которым нужно резолвить URL медиа (например, аватары авторов
 * уведомлений). Требует DatabaseTestCase-контекста ($this->getContainer(), $this->createStub()).
 */
trait PersistsMedia
{
    protected const string STUBBED_MEDIA_URL = 'https://cdn.example/real-media.jpg';

    protected function persistReadyPublicMedia(): Media
    {
        $media = $this->createReadyPublicMedia();
        $this->persistMedia($media);

        return $media;
    }

    protected function persistNotReadyMedia(): Media
    {
        $media = $this->createMedia();
        $this->persistMedia($media);

        return $media;
    }

    protected function persistReadyOriginalRemovedMedia(): Media
    {
        $media = $this->createReadyPublicMedia();
        $media->markReadyOriginalRemoved();
        $this->persistMedia($media);

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
        $this->persistImageConversion($conversion);

        return $conversion;
    }

    /**
     * Публичный контракт Media поверх реального пакетного сценария с реальным репозиторием из
     * контейнера и стабом файлового сервиса: public URL предсказуем (STUBBED_MEDIA_URL), обращения к
     * S3 нет.
     */
    protected function stubbedMediaContract(): MediaContract
    {
        return new MediaProvider(
            commandBus: $this->getContainer()->get(CommandBusInterface::class),
            queryBus: $this->getContainer()->get(QueryBusInterface::class),
            findMediaUrlsHandler: new FindMediaUrlsHandler(
                mediaRepository: $this->getContainer()->get(MediaRepository::class),
                mediaUrlService: $this->stubbedMediaUrlService(),
            ),
            checkMediaAttachableHandler: $this->getContainer()->get(CheckMediaAttachableHandler::class),
            makeMediaPermanentHandler: $this->getContainer()->get(MakeMediaPermanentHandler::class),
        );
    }

    private function stubbedMediaUrlService(): MediaUrlService
    {
        $fileService = $this->createStub(MediaFileServiceContract::class);
        $fileService->method('publicUrl')->willReturn(self::STUBBED_MEDIA_URL);

        return new MediaUrlService(
            mediaFileService: $fileService,
            mediaConfig: $this->getContainer()->get(MediaConfig::class),
        );
    }

    private function createReadyPublicMedia(): Media
    {
        $media = $this->createMedia();
        $media->markUploaded();
        $media->markReadyMovedTo(
            MediaStorage::Public,
            MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Image, extension: 'jpg'),
        );

        return $media;
    }

    private function createMedia(): Media
    {
        $storageKey = MediaStorageKey::generate();

        return Media::create(
            storageKey: $storageKey,
            type: MediaType::Image,
            visibility: MediaVisibility::Public,
            path: MediaPath::originalUpload(storageKey: $storageKey, extension: 'jpg'),
            mimeType: MediaMimeType::fromString('image/jpeg'),
            size: MediaFileSize::fromInt(1024),
            uploadedById: UserId::generate(),
            expiration: MediaExpiration::temporaryUntil(new \DateTimeImmutable('+1 hour')),
        );
    }

    /**
     * Media и MediaImageConversion — чистые доменные сущности без Cycle-разметки, поэтому в отличие
     * от прежнего (Cycle-нативного) состояния не могут быть сохранены через generic persist():
     * EntityManager не знает их роль. Хелперы переводят их в Cycle Entity через Mapper перед
     * постановкой в очередь EntityManager (приём фазы 2, см. AccessRepositoryTest).
     */
    private function persistMedia(Media $media): void
    {
        $entityManager = $this->getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($this->getContainer()->get(MediaMapper::class)->toCycleEntity($media));
        $entityManager->run();
    }

    private function persistImageConversion(MediaImageConversion $imageConversion): void
    {
        $entityManager = $this->getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist(
            $this->getContainer()->get(MediaImageConversionMapper::class)->toCycleEntity($imageConversion),
        );
        $entityManager->run();
    }
}
