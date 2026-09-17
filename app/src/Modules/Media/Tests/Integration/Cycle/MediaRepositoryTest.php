<?php

declare(strict_types=1);

namespace App\Modules\Media\Tests\Integration\Cycle;

use App\Modules\Media\Domain\Collection\MediaAudioConversionCollection;
use App\Modules\Media\Domain\Collection\MediaImageConversionCollection;
use App\Modules\Media\Domain\Collection\MediaMultipartPartCollection;
use App\Modules\Media\Domain\Collection\MediaVideoConversionCollection;
use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Entity\MediaAudioConversion;
use App\Modules\Media\Domain\Entity\MediaImageConversion;
use App\Modules\Media\Domain\Entity\MediaMultipartUpload;
use App\Modules\Media\Domain\Entity\MediaVideoConversion;
use App\Modules\Media\Domain\Enum\MediaAudioConversionType;
use App\Modules\Media\Domain\Enum\MediaConversionStatus;
use App\Modules\Media\Domain\Enum\MediaImageConversionType;
use App\Modules\Media\Domain\Enum\MediaStatus;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\Enum\MediaVideoConversionType;
use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Modules\Media\Domain\Repository\MediaRepository;
use App\Modules\Media\Domain\ValueObject\MediaBitrate;
use App\Modules\Media\Domain\ValueObject\MediaDuration;
use App\Modules\Media\Domain\ValueObject\MediaExpiration;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPart;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPartETag;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPartNumber;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPartsCount;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPartSize;
use App\Modules\Media\Domain\ValueObject\MediaMultipartUploadIdValue;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaPixelDimension;
use App\Modules\Media\Domain\ValueObject\MediaSampleRate;
use App\Modules\Media\Domain\ValueObject\MediaStorageKey;
use App\Modules\Media\Domain\ValueObject\MediaWaveform;
use App\Modules\Media\Infrastructure\Persistence\Cycle\Columns\MediaAudioConversionColumns;
use App\Modules\Media\Infrastructure\Persistence\Cycle\Columns\MediaImageConversionColumns;
use App\Modules\Media\Infrastructure\Persistence\Cycle\Columns\MediaVideoConversionColumns;
use App\Modules\Media\Infrastructure\Persistence\Cycle\Mapper\MediaAudioConversionMapper;
use App\Modules\Media\Infrastructure\Persistence\Cycle\Mapper\MediaImageConversionMapper;
use App\Modules\Media\Infrastructure\Persistence\Cycle\Mapper\MediaMapper;
use App\Modules\Media\Infrastructure\Persistence\Cycle\Mapper\MediaMultipartUploadMapper;
use App\Modules\Media\Infrastructure\Persistence\Cycle\Mapper\MediaVideoConversionMapper;
use App\Shared\Domain\ValueObject\UserId;
use Cycle\Database\DatabaseInterface;
use Cycle\ORM\EntityManagerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\NullLogger;
use Tests\DatabaseTestCase;

final class MediaRepositoryTest extends DatabaseTestCase
{
    public function testStoresAndRestoresMediaWithValueObjects(): void
    {
        $media = $this->createMedia();

        $this->persistMedia($media);
        $this->entityManager()->run();

        $restoredMedia = $this->mediaRepository()->findById($media->id);

        self::assertInstanceOf(Media::class, $restoredMedia);
        self::assertTrue($media->id->equals($restoredMedia->id));
        self::assertTrue($media->storageKey->equals($restoredMedia->storageKey));
        self::assertSame(MediaStatus::WaitingUpload, $restoredMedia->status);
        self::assertSame(MediaStorage::Upload, $restoredMedia->storage);
        self::assertTrue($media->uploadedById->equals($restoredMedia->uploadedById));
        self::assertInstanceOf(Media::class, $this->mediaRepository()->findByStorageKey($media->storageKey));
    }

    public function testStoresAndRestoresConversionsAndMultipartUpload(): void
    {
        $media = $this->createMedia();
        $imageConversion = $this->createImageConversion($media);
        $videoConversion = $this->createVideoConversion($media);
        $audioConversion = $this->createAudioConversion($media);
        $multipartUpload = $this->createMultipartUpload($media);

        $this->persistMedia($media);
        $this->persistImageConversion($imageConversion);
        $this->persistVideoConversion($videoConversion);
        $this->persistAudioConversion($audioConversion);
        $this->persistMultipartUpload($multipartUpload);
        $this->entityManager()->run();

        $imageConversions = $this->mediaRepository()->findImageConversionsByMediaId($media->id);
        $videoConversions = $this->mediaRepository()->findVideoConversionsByMediaId($media->id);
        $audioConversions = $this->mediaRepository()->findAudioConversionsByMediaId($media->id);
        $restoredMultipartUpload = $this->mediaRepository()->findMultipartUploadByMediaId($media->id);

        self::assertInstanceOf(MediaImageConversionCollection::class, $imageConversions);
        self::assertInstanceOf(MediaVideoConversionCollection::class, $videoConversions);
        self::assertInstanceOf(MediaAudioConversionCollection::class, $audioConversions);
        self::assertCount(1, $imageConversions);
        self::assertCount(1, $videoConversions);
        self::assertCount(1, $audioConversions);
        self::assertInstanceOf(MediaAudioConversion::class, $audioConversions->first());
        self::assertSame([0, 64, 128, 255], $audioConversions->first()->waveform->peaks());
        self::assertSame(44_100, $audioConversions->first()->sampleRate->value());
        self::assertInstanceOf(MediaMultipartUpload::class, $restoredMultipartUpload);
        self::assertInstanceOf(MediaMultipartPartCollection::class, $restoredMultipartUpload->parts);
        self::assertSame(1, $restoredMultipartUpload->parts->first()->partNumber->value());
        self::assertSame('first', $restoredMultipartUpload->parts->first()->eTag->value());
    }

    /**
     * findById()/findOne() (в отличие от findByIdsWithConversions()) не грузят конверсии: домен
     * получает пустые коллекции, а не запускает три лишних SELECT ради поля, которое никто не
     * читает вне findByIdsWithConversions()-потребителя (MediaUrlService). До разделения Domain/Cycle
     * Entity доступ к $media->imageConversions лениво резолвился Cycle (LazyGhostMapper) при первом
     * обращении — после разделения Media больше не Cycle-сущность, и лениво резолвить нечего:
     * MediaMapper::toDomain() сознательно никогда не читает три relation-поля CycleMediaEntity (ни
     * напрямую, ни через ReflectionProperty::isInitialized() — эмпирически проверено на
     * php:8.4-cli, что сам вызов isInitialized() на нетронутом lazy-ghost relation-свойстве
     * запускает его initializer, то есть не «подсматривает» состояние, а форсирует загрузку).
     * assertCount(0, ...) сам по себе не отличил бы «не грузил» от «грузил, но конверсий нет»,
     * поэтому здесь дополнительно проверяется реальный SQL через RecordingQueryLogger, подключённый
     * к Driver на время вызова.
     */
    public function testFindByIdDoesNotQueryConversionTables(): void
    {
        $media = $this->createMedia();
        $imageConversion = $this->createImageConversion($media);
        $videoConversion = $this->createVideoConversion($media);
        $audioConversion = $this->createAudioConversion($media);

        $this->persistMedia($media);
        $this->persistImageConversion($imageConversion);
        $this->persistVideoConversion($videoConversion);
        $this->persistAudioConversion($audioConversion);
        $this->entityManager()->run();
        $this->cleanOrmHeap();

        $queryLogger = new RecordingQueryLogger();
        $driver = $this->loggableDriver();
        $driver->setLogger($queryLogger);

        try {
            $restoredMedia = $this->mediaRepository()->findById($media->id);
        } finally {
            $driver->setLogger(new NullLogger());
        }

        self::assertInstanceOf(Media::class, $restoredMedia);
        self::assertInstanceOf(MediaImageConversionCollection::class, $restoredMedia->imageConversions);
        self::assertCount(0, $restoredMedia->imageConversions);
        self::assertCount(0, $restoredMedia->videoConversions);
        self::assertCount(0, $restoredMedia->audioConversions);
        self::assertSame(
            [],
            $queryLogger->queriesTouching(MediaImageConversionColumns::TABLE),
            'findById() не должен обращаться к таблице конверсий изображения.',
        );
        self::assertSame(
            [],
            $queryLogger->queriesTouching(MediaVideoConversionColumns::TABLE),
            'findById() не должен обращаться к таблице конверсий видео.',
        );
        self::assertSame(
            [],
            $queryLogger->queriesTouching(MediaAudioConversionColumns::TABLE),
            'findById() не должен обращаться к таблице конверсий звука.',
        );
        self::assertCount(1, $this->mediaRepository()->findImageConversionsByMediaId($media->id));
    }

    /**
     * Обратная проверка к testFindByIdDoesNotQueryConversionTables(): подтверждает, что сам
     * механизм RecordingQueryLogger реально видит запросы (иначе отсутствие запросов в предыдущем
     * тесте могло бы означать не «findById() их не делает», а «логгер ничего не ловит»), и что
     * findByIdsWithConversions() по-прежнему грузит конверсии одним eager-запросом через .load().
     */
    public function testFindByIdsWithConversionsDoesQueryConversionTables(): void
    {
        $media = $this->createMedia();
        $imageConversion = $this->createImageConversion($media);

        $this->persistMedia($media);
        $this->persistImageConversion($imageConversion);
        $this->entityManager()->run();
        $this->cleanOrmHeap();

        $queryLogger = new RecordingQueryLogger();
        $driver = $this->loggableDriver();
        $driver->setLogger($queryLogger);

        try {
            $this->mediaRepository()->findByIdsWithConversions($media->id);
        } finally {
            $driver->setLogger(new NullLogger());
        }

        self::assertNotSame(
            [],
            $queryLogger->queriesTouching(MediaImageConversionColumns::TABLE),
            'findByIdsWithConversions() обязан загрузить конверсии изображения одним запросом.',
        );
    }

    public function testSavingMediaAfterFindByIdDoesNotTouchConversions(): void
    {
        $media = $this->createMedia();
        $imageConversion = $this->createImageConversion($media);

        $this->persistMedia($media);
        $this->persistImageConversion($imageConversion);
        $this->entityManager()->run();
        $mediaId = $media->id;

        $this->cleanOrmHeap();

        $restoredMedia = $this->mediaRepository()->findById($mediaId);

        self::assertInstanceOf(Media::class, $restoredMedia);
        $restoredMedia->markReady();
        $this->mediaRepository()->save($restoredMedia);
        $this->cleanOrmHeap();

        $savedMedia = $this->mediaRepository()->findById($mediaId);
        $savedImageConversion = $this->mediaRepository()->findImageConversionsByMediaId($mediaId)->first();

        self::assertInstanceOf(Media::class, $savedMedia);
        self::assertSame(MediaStatus::Ready, $savedMedia->status);
        self::assertInstanceOf(MediaImageConversion::class, $savedImageConversion);
        self::assertTrue($mediaId->equals($savedImageConversion->mediaId));
    }

    public function testExistsReadyForMediaIdReportsReadyConversionPresence(): void
    {
        $mediaWithConversions = $this->createMedia();
        $this->persistMedia($mediaWithConversions);
        $this->persistImageConversion($this->createImageConversion($mediaWithConversions));
        $this->persistVideoConversion($this->createVideoConversion($mediaWithConversions));
        $this->persistAudioConversion($this->createAudioConversion($mediaWithConversions));
        $this->entityManager()->run();

        $mediaWithoutConversions = $this->createMedia();
        $this->persistMedia($mediaWithoutConversions);
        $this->entityManager()->run();

        self::assertTrue($this->mediaRepository()->hasReadyImageConversion($mediaWithConversions->id));
        self::assertTrue($this->mediaRepository()->hasReadyVideoConversion($mediaWithConversions->id));
        self::assertTrue($this->mediaRepository()->hasReadyAudioConversion($mediaWithConversions->id));

        self::assertFalse($this->mediaRepository()->hasReadyImageConversion($mediaWithoutConversions->id));
        self::assertFalse($this->mediaRepository()->hasReadyVideoConversion($mediaWithoutConversions->id));
        self::assertFalse($this->mediaRepository()->hasReadyAudioConversion($mediaWithoutConversions->id));
    }

    public function testExistsReadyForMediaIdIgnoresNonReadyConversions(): void
    {
        // Есть только не-Ready конверсии (processing/processingFailed) -> готовой нет, метод даёт false.
        $media = $this->createMedia();
        $this->persistMedia($media);
        $this->persistImageConversion(
            $this->createImageConversion(media: $media, status: MediaConversionStatus::Processing),
        );
        $this->persistVideoConversion(
            $this->createVideoConversion(media: $media, status: MediaConversionStatus::ProcessingFailed),
        );
        $this->persistAudioConversion(
            $this->createAudioConversion(media: $media, status: MediaConversionStatus::Processing),
        );
        $this->entityManager()->run();

        self::assertFalse($this->mediaRepository()->hasReadyImageConversion($media->id));
        self::assertFalse($this->mediaRepository()->hasReadyVideoConversion($media->id));
        self::assertFalse($this->mediaRepository()->hasReadyAudioConversion($media->id));
    }

    public function testFindExpiredReturnsTypedCollection(): void
    {
        $media = $this->createMedia(
            expiration: MediaExpiration::temporaryUntil(new \DateTimeImmutable('-1 hour')),
        );

        $this->persistMedia($media);
        $this->entityManager()->run();

        $expiredMedia = $this->mediaRepository()->findExpired(new \DateTimeImmutable());

        self::assertTrue($expiredMedia->contains(static fn(Media $expired) => $expired->id->equals($media->id)));
    }

    public function testFindByIdsWithConversionsReturnsEmptyForEmptyInput(): void
    {
        self::assertCount(0, $this->mediaRepository()->findByIdsWithConversions());
    }

    public function testFindByIdsWithConversionsLoadsMediaWithConversions(): void
    {
        $first = $this->createMedia();
        $second = $this->createMedia();
        $this->persistMedia($first);
        $this->persistMedia($second);
        $this->persistImageConversion($this->createImageConversion($first));
        $this->persistVideoConversion($this->createVideoConversion($first));
        $this->entityManager()->run();
        $this->cleanOrmHeap();

        $media = $this->mediaRepository()->findByIdsWithConversions($first->id, $second->id);

        self::assertCount(2, $media);
        $restoredFirst = $media->first(static fn(Media $candidate): bool => $candidate->id->equals($first->id));
        self::assertInstanceOf(Media::class, $restoredFirst);
        self::assertCount(1, $restoredFirst->imageConversions);
        self::assertCount(1, $restoredFirst->videoConversions);
        self::assertInstanceOf(MediaImageConversion::class, $restoredFirst->imageConversions->first());
        self::assertInstanceOf(MediaVideoConversion::class, $restoredFirst->videoConversions->first());
    }

    public function testStorageKeyIsUnique(): void
    {
        $storageKey = MediaStorageKey::generate();

        $this->persistMedia($this->createMedia(storageKey: $storageKey));
        $this->persistMedia($this->createMedia(storageKey: $storageKey));

        $this->expectException(\Throwable::class);

        $this->entityManager()->run();
    }

    public function testCascadeDeletesRelatedRows(): void
    {
        $media = $this->createMedia();
        $imageConversion = $this->createImageConversion($media);
        $videoConversion = $this->createVideoConversion($media);
        $audioConversion = $this->createAudioConversion($media);
        $multipartUpload = $this->createMultipartUpload($media);

        $this->persistMedia($media);
        $this->persistImageConversion($imageConversion);
        $this->persistVideoConversion($videoConversion);
        $this->persistAudioConversion($audioConversion);
        $this->persistMultipartUpload($multipartUpload);
        $this->entityManager()->run();

        $this->mediaRepository()->delete($media);

        self::assertCount(0, $this->mediaRepository()->findImageConversionsByMediaId($media->id));
        self::assertCount(0, $this->mediaRepository()->findVideoConversionsByMediaId($media->id));
        self::assertCount(0, $this->mediaRepository()->findAudioConversionsByMediaId($media->id));
        self::assertNull($this->mediaRepository()->findMultipartUploadByMediaId($media->id));
    }

    /**
     * Медиа могло быть удалено параллельным сценарием (например повторной командой удаления)
     * между чтением и записью: домен больше не несёт разметку Cycle, поэтому репозиторий сам
     * ищет строку перед удалением и молча выходит, если её уже нет.
     */
    public function testDeleteIgnoresMediaMissingInDatabase(): void
    {
        $missingMedia = $this->createMedia();

        $this->mediaRepository()->delete($missingMedia);

        self::assertNull($this->mediaRepository()->findById($missingMedia->id));
    }

    private function createMedia(
        MediaStorageKey|null $storageKey = null,
        MediaExpiration|null $expiration = null,
    ): Media {
        $storageKey ??= MediaStorageKey::generate();

        return Media::create(
            storageKey: $storageKey,
            type: MediaType::Image,
            visibility: MediaVisibility::Private,
            path: MediaPath::originalUpload(storageKey: $storageKey, extension: 'jpg'),
            mimeType: MediaMimeType::fromString('image/jpeg'),
            size: MediaFileSize::fromInt(1024),
            uploadedById: UserId::generate(),
            expiration: $expiration ?? MediaExpiration::temporaryUntil(new \DateTimeImmutable('+1 hour')),
        );
    }

    private function createImageConversion(
        Media $media,
        MediaConversionStatus $status = MediaConversionStatus::Ready,
    ): MediaImageConversion {
        $storageKey = MediaStorageKey::generate();

        return MediaImageConversion::create(
            media: $media,
            type: MediaImageConversionType::Thumbnail,
            status: $status,
            storage: MediaStorage::Public,
            path: MediaPath::fromString(\sprintf('images/%s/%s/thumbnail.jpg', $storageKey->shard(), $storageKey)),
            mimeType: MediaMimeType::fromString('image/jpeg'),
            size: MediaFileSize::fromInt(512),
            width: MediaPixelDimension::fromInt(100),
            height: MediaPixelDimension::fromInt(100),
        );
    }

    private function createVideoConversion(
        Media $media,
        MediaConversionStatus $status = MediaConversionStatus::Ready,
    ): MediaVideoConversion {
        $storageKey = MediaStorageKey::generate();

        return MediaVideoConversion::create(
            media: $media,
            type: MediaVideoConversionType::NormalizedMp4H264,
            status: $status,
            storage: MediaStorage::Public,
            path: MediaPath::fromString(\sprintf('videos/%s/%s/normalized.mp4', $storageKey->shard(), $storageKey)),
            mimeType: MediaMimeType::fromString('video/mp4'),
            size: MediaFileSize::fromInt(2048),
            width: MediaPixelDimension::fromInt(1920),
            height: MediaPixelDimension::fromInt(1080),
            duration: MediaDuration::fromInt(1000),
            bitrate: MediaBitrate::fromInt(5000),
        );
    }

    private function createAudioConversion(
        Media $media,
        MediaConversionStatus $status = MediaConversionStatus::Ready,
    ): MediaAudioConversion {
        $storageKey = MediaStorageKey::generate();

        return MediaAudioConversion::create(
            media: $media,
            type: MediaAudioConversionType::NormalizedAacM4a,
            status: $status,
            storage: MediaStorage::Public,
            path: MediaPath::fromString(\sprintf('audios/%s/%s/normalizedAacM4a.m4a', $storageKey->shard(), $storageKey)),
            mimeType: MediaMimeType::fromString('audio/mp4'),
            size: MediaFileSize::fromInt(2048),
            duration: MediaDuration::fromInt(1000),
            bitrate: MediaBitrate::fromInt(128_000),
            sampleRate: MediaSampleRate::fromInt(44_100),
            waveform: MediaWaveform::fromPeaks([0, 64, 128, 255]),
        );
    }

    private function createMultipartUpload(Media $media): MediaMultipartUpload
    {
        $multipartUpload = MediaMultipartUpload::create(
            media: $media,
            uploadId: MediaMultipartUploadIdValue::fromString('upload-id'),
            partsCount: MediaMultipartPartsCount::fromInt(1),
            partSize: MediaMultipartPartSize::fromInt(5_242_880),
            fileSize: MediaFileSize::fromInt(5_242_880),
        );
        $multipartUpload->replaceParts(new MediaMultipartPartCollection([
            MediaMultipartPart::create(
                partNumber: MediaMultipartPartNumber::fromInt(1),
                eTag: MediaMultipartPartETag::fromString('first'),
            ),
        ]));

        return $multipartUpload;
    }

    private function entityManager(): EntityManagerInterface
    {
        return $this->getContainer()->get(EntityManagerInterface::class);
    }

    /**
     * Cycle\Database\Driver\DriverInterface не объявляет setLogger() в своём контракте (его несёт
     * конкретный Driver через Psr\Log\LoggerAwareInterface), поэтому проверяем это явно, а не
     * полагаемся на недекларированный метод интерфейса.
     */
    private function loggableDriver(): LoggerAwareInterface
    {
        $driver = $this->getContainer()->get(DatabaseInterface::class)->getDriver();

        if (!$driver instanceof LoggerAwareInterface) {
            self::fail('Driver БД не реализует LoggerAwareInterface — RecordingQueryLogger не может быть подключён.');
        }

        return $driver;
    }

    private function mediaRepository(): MediaRepository
    {
        return $this->getContainer()->get(MediaRepository::class);
    }

    /**
     * Media, MediaImageConversion, MediaVideoConversion, MediaAudioConversion и MediaMultipartUpload —
     * чистые доменные сущности без Cycle-разметки, поэтому в отличие от прежнего (Cycle-нативного)
     * состояния не могут быть сохранены через generic persist(): EntityManager не знает их роль.
     * Хелперы переводят их в Cycle Entity через Mapper перед постановкой в очередь EntityManager,
     * flush остаётся общим — как до разделения (приём фазы 2, см. AccessRepositoryTest).
     */
    private function persistMedia(Media $media): void
    {
        $this->entityManager()->persist($this->getContainer()->get(MediaMapper::class)->toCycleEntity($media));
    }

    private function persistImageConversion(MediaImageConversion $imageConversion): void
    {
        $this->entityManager()->persist(
            $this->getContainer()->get(MediaImageConversionMapper::class)->toCycleEntity($imageConversion),
        );
    }

    private function persistVideoConversion(MediaVideoConversion $videoConversion): void
    {
        $this->entityManager()->persist(
            $this->getContainer()->get(MediaVideoConversionMapper::class)->toCycleEntity($videoConversion),
        );
    }

    private function persistAudioConversion(MediaAudioConversion $audioConversion): void
    {
        $this->entityManager()->persist(
            $this->getContainer()->get(MediaAudioConversionMapper::class)->toCycleEntity($audioConversion),
        );
    }

    private function persistMultipartUpload(MediaMultipartUpload $multipartUpload): void
    {
        $this->entityManager()->persist(
            $this->getContainer()->get(MediaMultipartUploadMapper::class)->toCycleEntity($multipartUpload),
        );
    }
}
