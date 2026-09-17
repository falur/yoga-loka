<?php

declare(strict_types=1);

namespace App\Modules\Media\Tests\Integration\Spiral;

use App\Modules\Media\Public\Dto\MediaAudioConversionSpecDto;
use App\Modules\Media\Public\Dto\MediaConversionPlanDto;
use App\Modules\Media\Public\Dto\MediaImageConversionSpecDto;
use App\Modules\Media\Public\Dto\MediaVideoConversionSpecDto;
use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Entity\MediaAudioConversion;
use App\Modules\Media\Domain\Entity\MediaImageConversion;
use App\Modules\Media\Domain\Entity\MediaMultipartUpload;
use App\Modules\Media\Domain\Entity\MediaVideoConversion;
use App\Modules\Media\Domain\Enum\MediaAudioConversionType;
use App\Modules\Media\Domain\Enum\MediaConversionStatus;
use App\Modules\Media\Domain\Enum\MediaImageConversionType;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\Enum\MediaVideoConversionType;
use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Modules\Media\Public\Enum\MediaAudioConversionType as PublicMediaAudioConversionType;
use App\Modules\Media\Public\Enum\MediaImageConversionType as PublicMediaImageConversionType;
use App\Modules\Media\Public\Enum\MediaVideoConversionType as PublicMediaVideoConversionType;
use App\Modules\Media\Domain\ValueObject\MediaBitrate;
use App\Modules\Media\Domain\ValueObject\MediaDuration;
use App\Modules\Media\Domain\ValueObject\MediaExpiration;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaPixelDimension;
use App\Modules\Media\Domain\ValueObject\MediaSampleRate;
use App\Modules\Media\Domain\ValueObject\MediaStorageKey;
use App\Modules\Media\Domain\ValueObject\MediaWaveform;
use App\Modules\Media\Domain\Repository\MediaRepository;
use App\Modules\Media\Infrastructure\Persistence\Cycle\Entity\CycleMediaAudioConversionEntity;
use App\Modules\Media\Infrastructure\Persistence\Cycle\Entity\CycleMediaEntity;
use App\Modules\Media\Infrastructure\Persistence\Cycle\Entity\CycleMediaImageConversionEntity;
use App\Modules\Media\Infrastructure\Persistence\Cycle\Entity\CycleMediaMultipartUploadEntity;
use App\Modules\Media\Infrastructure\Persistence\Cycle\Entity\CycleMediaVideoConversionEntity;
use App\Modules\Media\Infrastructure\Persistence\Cycle\Mapper\MediaAudioConversionMapper;
use App\Modules\Media\Infrastructure\Persistence\Cycle\Mapper\MediaImageConversionMapper;
use App\Modules\Media\Infrastructure\Persistence\Cycle\Mapper\MediaMapper;
use App\Modules\Media\Infrastructure\Persistence\Cycle\Mapper\MediaMultipartUploadMapper;
use App\Modules\Media\Infrastructure\Persistence\Cycle\Mapper\MediaVideoConversionMapper;
use App\Shared\Domain\ValueObject\UserId;
use Cycle\ORM\EntityManagerInterface;
use Cycle\ORM\ORMInterface;
use Cycle\ORM\Select;
use Tests\TestCase;

abstract class MediaApplicationTestCase extends TestCase
{
    protected function createMedia(
        UserId|null $userId = null,
        MediaVisibility $visibility = MediaVisibility::Private,
        MediaType $type = MediaType::Image,
        MediaFileSize|null $size = null,
        string $extension = 'jpg',
        string $mimeType = 'image/jpeg',
    ): Media {
        $storageKey = MediaStorageKey::generate();

        return Media::create(
            storageKey: $storageKey,
            type: $type,
            visibility: $visibility,
            path: MediaPath::originalUpload(storageKey: $storageKey, extension: $extension),
            mimeType: MediaMimeType::fromString($mimeType),
            size: $size ?? MediaFileSize::fromInt(1024),
            uploadedById: $userId ?? UserId::generate(),
            expiration: MediaExpiration::temporaryUntil(new \DateTimeImmutable('+1 hour')),
        );
    }

    protected function readyMedia(MediaVisibility $visibility): Media
    {
        $media = $this->createMedia(userId: UserId::generate(), visibility: $visibility);
        $media->markUploaded();
        $targetStorage = $visibility === MediaVisibility::Public ? MediaStorage::Public : MediaStorage::Private;
        $media->markReadyMovedTo(
            $targetStorage,
            MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Image, extension: 'jpg'),
        );

        return $media;
    }

    protected function imageConversion(
        Media $media,
        MediaImageConversionType $type,
        MediaConversionStatus $status,
    ): MediaImageConversion {
        return MediaImageConversion::create(
            media: $media,
            type: $type,
            status: $status,
            storage: $media->storage,
            path: MediaPath::imageConversion(
                storageKey: $media->storageKey,
                type: $type,
                extension: 'jpg',
            ),
            mimeType: MediaMimeType::fromString('image/jpeg'),
            size: MediaFileSize::fromInt(256),
            width: MediaPixelDimension::fromInt(100),
            height: MediaPixelDimension::fromInt(100),
        );
    }

    protected function thumbnailConversion(Media $media): MediaImageConversion
    {
        return $this->imageConversion(
            media: $media,
            type: MediaImageConversionType::Thumbnail,
            status: MediaConversionStatus::Ready,
        );
    }

    protected function videoConversion(
        Media $media,
        MediaConversionStatus $status = MediaConversionStatus::Ready,
    ): MediaVideoConversion {
        return MediaVideoConversion::create(
            media: $media,
            type: MediaVideoConversionType::NormalizedMp4H264,
            status: $status,
            storage: $media->storage,
            path: MediaPath::videoConversion(
                storageKey: $media->storageKey,
                type: MediaVideoConversionType::NormalizedMp4H264,
                extension: 'mp4',
            ),
            mimeType: MediaMimeType::fromString('video/mp4'),
            size: MediaFileSize::fromInt(4096),
            width: MediaPixelDimension::fromInt(1280),
            height: MediaPixelDimension::fromInt(720),
            duration: MediaDuration::fromInt(2000),
            bitrate: MediaBitrate::fromInt(900_000),
        );
    }

    protected function audioConversion(Media $media): MediaAudioConversion
    {
        return MediaAudioConversion::create(
            media: $media,
            type: MediaAudioConversionType::NormalizedAacM4a,
            status: MediaConversionStatus::Ready,
            storage: $media->storage,
            path: MediaPath::audioConversion(
                storageKey: $media->storageKey,
                type: MediaAudioConversionType::NormalizedAacM4a,
                extension: 'm4a',
            ),
            mimeType: MediaMimeType::fromString('audio/mp4'),
            size: MediaFileSize::fromInt(2048),
            duration: MediaDuration::fromInt(3000),
            bitrate: MediaBitrate::fromInt(128_000),
            sampleRate: MediaSampleRate::fromInt(44_100),
            waveform: MediaWaveform::fromPeaks([0, 64, 128, 255]),
        );
    }

    /**
     * Media и её внутренние сущности (конверсии, multipart-загрузка) — чистые доменные сущности
     * без Cycle-разметки, поэтому в отличие от прежнего (Cycle-нативного) состояния не могут быть
     * сохранены через generic persist(): EntityManager не знает их роль. Хелпер переводит каждую
     * сущность в Cycle Entity через соответствующий Mapper перед постановкой в очередь
     * EntityManager (приём фазы 2, см. AccessRepositoryTest) — сигнатура persist() не меняется,
     * поэтому ни один из вызывающих тестов не правится.
     *
     * toCycleEntity() ищет существующую строку по PK перед вызовом Mapper (по образцу
     * PostsRepositoryTestCase::toCycleEntity()): без этого повторный persist() уже сохранённой
     * (например, изменённой handler-ом и затем перечитанной тестом) сущности создавал бы новый
     * CycleEntity с тем же PK и падал на дублирующемся первичном ключе вместо UPDATE — родная
     * identity map Cycle доступна только внутри одного findById(), домен её больше не наследует.
     */
    protected function persist(object ...$entities): void
    {
        foreach ($entities as $entity) {
            $this->entityManager()->persist($this->toCycleEntity($entity));
        }

        $this->entityManager()->run();
    }

    private function toCycleEntity(object $entity): object
    {
        return match (true) {
            $entity instanceof Media => $this->getContainer()->get(MediaMapper::class)->toCycleEntity(
                media: $entity,
                cycleEntity: $this->findCycleEntityByClass(CycleMediaEntity::class, $entity->id->value()),
            ),
            $entity instanceof MediaImageConversion => $this->getContainer()
                ->get(MediaImageConversionMapper::class)
                ->toCycleEntity(
                    imageConversion: $entity,
                    cycleEntity: $this->findCycleEntityByClass(CycleMediaImageConversionEntity::class, $entity->id->value()),
                ),
            $entity instanceof MediaVideoConversion => $this->getContainer()
                ->get(MediaVideoConversionMapper::class)
                ->toCycleEntity(
                    videoConversion: $entity,
                    cycleEntity: $this->findCycleEntityByClass(CycleMediaVideoConversionEntity::class, $entity->id->value()),
                ),
            $entity instanceof MediaAudioConversion => $this->getContainer()
                ->get(MediaAudioConversionMapper::class)
                ->toCycleEntity(
                    audioConversion: $entity,
                    cycleEntity: $this->findCycleEntityByClass(CycleMediaAudioConversionEntity::class, $entity->id->value()),
                ),
            $entity instanceof MediaMultipartUpload => $this->getContainer()
                ->get(MediaMultipartUploadMapper::class)
                ->toCycleEntity(
                    multipartUpload: $entity,
                    cycleEntity: $this->findCycleEntityByClass(CycleMediaMultipartUploadEntity::class, $entity->id->value()),
                ),
            default => throw new \InvalidArgumentException(\sprintf(
                'persist() не знает Mapper для сущности %s.',
                $entity::class,
            )),
        };
    }

    /**
     * @template TCycleEntity of object
     *
     * @param class-string<TCycleEntity> $cycleClass
     *
     * @return TCycleEntity|null
     */
    private function findCycleEntityByClass(string $cycleClass, string $id): object|null
    {
        /** @var ORMInterface $orm */
        $orm = $this->getContainer()->get(ORMInterface::class);

        /** @var Select<TCycleEntity> $select */
        $select = new Select($orm, $cycleClass);

        return $select->wherePK($id)->fetchOne();
    }

    protected function imageConversionSpec(
        PublicMediaImageConversionType $type = PublicMediaImageConversionType::Thumbnail,
        int $width = 100,
        int $height = 100,
    ): MediaImageConversionSpecDto {
        return new MediaImageConversionSpecDto(type: $type, width: $width, height: $height);
    }

    protected function videoConversionSpec(
        PublicMediaVideoConversionType $type = PublicMediaVideoConversionType::NormalizedMp4H264,
        int $width = 1280,
        int $height = 720,
        int $videoBitrate = 1_000_000,
        int $audioBitrate = 128_000,
    ): MediaVideoConversionSpecDto {
        return new MediaVideoConversionSpecDto(
            type: $type,
            width: $width,
            height: $height,
            videoBitrate: $videoBitrate,
            audioBitrate: $audioBitrate,
        );
    }

    protected function audioConversionSpec(
        PublicMediaAudioConversionType $type = PublicMediaAudioConversionType::NormalizedAacM4a,
        int $bitrate = 128_000,
        int $sampleRate = 44_100,
        int $waveformPeaks = 64,
    ): MediaAudioConversionSpecDto {
        return new MediaAudioConversionSpecDto(
            type: $type,
            bitrate: $bitrate,
            sampleRate: $sampleRate,
            waveformPeaks: $waveformPeaks,
        );
    }

    protected function emptyPlan(): MediaConversionPlanDto
    {
        return new MediaConversionPlanDto(image: [], video: [], audio: []);
    }

    protected function imagePlan(MediaImageConversionSpecDto ...$specs): MediaConversionPlanDto
    {
        return new MediaConversionPlanDto(image: \array_values($specs), video: [], audio: []);
    }

    protected function videoPlan(MediaVideoConversionSpecDto ...$specs): MediaConversionPlanDto
    {
        return new MediaConversionPlanDto(image: [], video: \array_values($specs), audio: []);
    }

    protected function audioPlan(MediaAudioConversionSpecDto ...$specs): MediaConversionPlanDto
    {
        return new MediaConversionPlanDto(image: [], video: [], audio: \array_values($specs));
    }

    protected function entityManager(): EntityManagerInterface
    {
        return $this->getContainer()->get(EntityManagerInterface::class);
    }

    /**
     * Сбрасывает ORM identity map, чтобы последующая выборка читала сущности из БД заново (с eager-load
     * связей), а не возвращала закэшированный после persist экземпляр с незагруженными связями.
     */
    protected function cleanOrmHeap(): void
    {
        $this->getContainer()->get(EntityManagerInterface::class)->clean();
        $this->getContainer()->get(ORMInterface::class)->getHeap()->clean();
    }

    protected function mediaRepository(): MediaRepository
    {
        return $this->getContainer()->get(MediaRepository::class);
    }
}
