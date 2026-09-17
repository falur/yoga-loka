<?php

declare(strict_types=1);

namespace App\Modules\Media\Infrastructure\Persistence\Cycle\Mapper;

use App\Modules\Media\Domain\Collection\MediaAudioConversionCollection;
use App\Modules\Media\Domain\Collection\MediaImageConversionCollection;
use App\Modules\Media\Domain\Collection\MediaVideoConversionCollection;
use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\ValueObject\MediaExpiration;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaProcessingAttempts;
use App\Modules\Media\Domain\ValueObject\MediaProcessingError;
use App\Modules\Media\Domain\ValueObject\MediaStorageKey;
use App\Modules\Media\Infrastructure\Persistence\Cycle\Entity\CycleMediaAudioConversionEntity;
use App\Modules\Media\Infrastructure\Persistence\Cycle\Entity\CycleMediaEntity;
use App\Modules\Media\Infrastructure\Persistence\Cycle\Entity\CycleMediaImageConversionEntity;
use App\Modules\Media\Infrastructure\Persistence\Cycle\Entity\CycleMediaVideoConversionEntity;
use App\Shared\Domain\ValueObject\UserId;

/**
 * imageConversions/videoConversions/audioConversions — реальные HasMany корня (см.
 * CycleMediaEntity), не eager-загруженные вне CycleMediaRepository::findByIdsWithConversions()
 * (.load()). У остального (findById() и т.п.) под свойством лежит lazy-ghost «обещание»
 * (Cycle\ORM\Reference\ReferenceInterface), которое штатный LazyGhostMapper проекта резолвит не
 * при явном чтении $cycleEntity->imageConversions, а при ЛЮБОМ обращении к ещё не инициализированному
 * lazy-ghost свойству — включая ReflectionProperty::isInitialized(). Проверено эмпирически
 * (`docker run php:8.4-cli`): вызов isInitialized() на нетронутом lazy-ghost свойстве сам запускает
 * initializer объекта, то есть не «подсматривает» состояние, а форсирует его. Поэтому toDomain()
 * НИКОГДА не читает эти три поля (ни напрямую, ни через isInitialized()) и всегда отдаёт пустые
 * коллекции — единственный путь получить домен с заполненными конверсиями — toDomainWithConversions(),
 * которым пользуется только findByIdsWithConversions(): там связи гарантированно eager-загружены
 * через .load(), Cycle кладёт в свойство уже готовые данные напрямую (не Reference), и чтение
 * безопасно.
 */
final readonly class MediaMapper
{
    public function __construct(
        private MediaImageConversionMapper $imageConversionMapper,
        private MediaVideoConversionMapper $videoConversionMapper,
        private MediaAudioConversionMapper $audioConversionMapper,
    ) {}

    public function toDomain(CycleMediaEntity $cycleEntity): Media
    {
        return $this->buildDomain(
            cycleEntity: $cycleEntity,
            imageConversions: new MediaImageConversionCollection(),
            videoConversions: new MediaVideoConversionCollection(),
            audioConversions: new MediaAudioConversionCollection(),
        );
    }

    /**
     * Вызывается только когда корень выбран с ->load('imageConversions')->load('videoConversions')
     * ->load('audioConversions') (CycleMediaRepository::findByIdsWithConversions()) — иначе чтение
     * связей ниже форсирует лишний SELECT на каждую из трёх (см. комментарий класса).
     */
    public function toDomainWithConversions(CycleMediaEntity $cycleEntity): Media
    {
        return $this->buildDomain(
            cycleEntity: $cycleEntity,
            imageConversions: $this->imageConversions($cycleEntity),
            videoConversions: $this->videoConversions($cycleEntity),
            audioConversions: $this->audioConversions($cycleEntity),
        );
    }

    public function toCycleEntity(
        Media $media,
        CycleMediaEntity|null $cycleEntity = null,
    ): CycleMediaEntity {
        $cycleEntity ??= new CycleMediaEntity();
        $cycleEntity->id = $media->id->value();
        $cycleEntity->storageKey = $media->storageKey->value();
        $cycleEntity->type = $media->type;
        $cycleEntity->status = $media->status;
        $cycleEntity->visibility = $media->visibility;
        $cycleEntity->storage = $media->storage;
        $cycleEntity->path = $media->path->value();
        $cycleEntity->mimeType = $media->mimeType->value();
        $cycleEntity->size = $media->size->value();
        $cycleEntity->uploadedById = $media->uploadedById->value();
        $cycleEntity->expiresAt = $media->expiration->value();
        $cycleEntity->processingAttempts = $media->processingAttempts->value();
        $cycleEntity->processingError = $media->processingError->value();
        $cycleEntity->createdAt = $media->createdAt;
        $cycleEntity->updatedAt = $media->updatedAt;

        return $cycleEntity;
    }

    private function buildDomain(
        CycleMediaEntity $cycleEntity,
        MediaImageConversionCollection $imageConversions,
        MediaVideoConversionCollection $videoConversions,
        MediaAudioConversionCollection $audioConversions,
    ): Media {
        return Media::restore(
            id: MediaId::fromString($cycleEntity->id),
            storageKey: MediaStorageKey::fromString($cycleEntity->storageKey),
            type: $cycleEntity->type,
            status: $cycleEntity->status,
            visibility: $cycleEntity->visibility,
            storage: $cycleEntity->storage,
            path: MediaPath::fromString($cycleEntity->path),
            mimeType: MediaMimeType::fromString($cycleEntity->mimeType),
            size: MediaFileSize::fromInt($cycleEntity->size),
            uploadedById: UserId::fromString($cycleEntity->uploadedById),
            expiration: $cycleEntity->expiresAt === null
                ? MediaExpiration::permanent()
                : MediaExpiration::temporaryUntil($cycleEntity->expiresAt),
            processingAttempts: MediaProcessingAttempts::fromInt($cycleEntity->processingAttempts),
            processingError: $cycleEntity->processingError === null
                ? MediaProcessingError::none()
                : MediaProcessingError::fromString($cycleEntity->processingError),
            imageConversions: $imageConversions,
            videoConversions: $videoConversions,
            audioConversions: $audioConversions,
            createdAt: $cycleEntity->createdAt,
            updatedAt: $cycleEntity->updatedAt,
        );
    }

    private function imageConversions(CycleMediaEntity $cycleEntity): MediaImageConversionCollection
    {
        $imageConversions = new MediaImageConversionCollection();

        // CycleMediaEntity::$imageConversions натив-типизировано доменным
        // MediaImageConversionCollection (collection: не меняется волной E, см. докблок класса) —
        // при eager-load Cycle кладёт туда CycleMediaImageConversionEntity, а не домен, поэтому
        // @var ниже точнее реального содержимого на момент eager-load, но конфликтует с native-типом
        // свойства для PHPStan.
        /** @var iterable<CycleMediaImageConversionEntity> $cycleImageConversions */
        // @phpstan-ignore varTag.nativeType
        $cycleImageConversions = $cycleEntity->imageConversions;

        foreach ($cycleImageConversions as $cycleImageConversion) {
            $imageConversions->push($this->imageConversionMapper->toDomain($cycleImageConversion));
        }

        return $imageConversions;
    }

    private function videoConversions(CycleMediaEntity $cycleEntity): MediaVideoConversionCollection
    {
        $videoConversions = new MediaVideoConversionCollection();

        // Тот же случай, что в imageConversions() выше: native-тип свойства — доменная
        // коллекция, реальное eager-load содержимое — Cycle Entity.
        /** @var iterable<CycleMediaVideoConversionEntity> $cycleVideoConversions */
        // @phpstan-ignore varTag.nativeType
        $cycleVideoConversions = $cycleEntity->videoConversions;

        foreach ($cycleVideoConversions as $cycleVideoConversion) {
            $videoConversions->push($this->videoConversionMapper->toDomain($cycleVideoConversion));
        }

        return $videoConversions;
    }

    private function audioConversions(CycleMediaEntity $cycleEntity): MediaAudioConversionCollection
    {
        $audioConversions = new MediaAudioConversionCollection();

        // Тот же случай, что в imageConversions() выше: native-тип свойства — доменная
        // коллекция, реальное eager-load содержимое — Cycle Entity.
        /** @var iterable<CycleMediaAudioConversionEntity> $cycleAudioConversions */
        // @phpstan-ignore varTag.nativeType
        $cycleAudioConversions = $cycleEntity->audioConversions;

        foreach ($cycleAudioConversions as $cycleAudioConversion) {
            $audioConversions->push($this->audioConversionMapper->toDomain($cycleAudioConversion));
        }

        return $audioConversions;
    }
}
