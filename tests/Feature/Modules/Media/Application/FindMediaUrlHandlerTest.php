<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Media\Application;

use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Media\Application\Dto\MediaConversionUrl;
use App\Modules\Media\Application\Dto\MediaConversionUrlCollection;
use App\Modules\Media\Application\Query\FindMediaUrl\FindMediaUrlHandler;
use App\Modules\Media\Application\Query\FindMediaUrl\FindMediaUrlQuery;
use App\Modules\Media\Domain\Enum\MediaAudioConversionType;
use App\Modules\Media\Domain\Enum\MediaConversionKind;
use App\Modules\Media\Domain\Enum\MediaConversionStatus;
use App\Modules\Media\Domain\Enum\MediaImageConversionType;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\Enum\MediaVideoConversionType;
use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Infrastructure\FileService\MediaUrlService;
use App\Shared\Domain\Exception\InvalidDomainValueException;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Configuration\Media\MediaConfig;

final class FindMediaUrlHandlerTest extends MediaApplicationTestCase
{
    public function testReturnsNullForMissingMedia(): void
    {
        $result = $this->handler($this->createStub(MediaFileServiceContract::class))->handle(
            new FindMediaUrlQuery(mediaId: UserId::generate()->value(), presignedTtlSeconds: 300),
        );

        self::assertNull($result);
    }

    public function testReturnsNullForMediaThatIsNotFinalized(): void
    {
        // Ещё не ready и не readyOriginalRemoved -> best-effort null, чтобы вызывающий подставил дефолт.
        $media = $this->createMedia(userId: UserId::generate());
        $this->persist($media);
        $this->cleanOrmHeap();

        $result = $this->handler($this->createStub(MediaFileServiceContract::class))->handle(
            new FindMediaUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 300),
        );

        self::assertNull($result);
    }

    public function testReturnsOriginalAndAllConversionsForReadyPublicMedia(): void
    {
        $media = $this->readyMedia(MediaVisibility::Public);
        $thumbnail = $this->thumbnailConversion($media);
        $video = $this->videoConversion($media);
        $audio = $this->audioConversion($media);
        $this->persist($media, $thumbnail, $video, $audio);
        $this->cleanOrmHeap();

        $fileService = $this->createMock(MediaFileServiceContract::class);
        $fileService->expects(self::atLeastOnce())->method('publicUrl')->willReturnCallback(
            static fn(MediaStorage $storage, MediaPath $path): string => 'http://minio/media-public/' . $path->value(),
        );
        $fileService->expects(self::never())->method('presignGet');

        $result = $this->handler($fileService)->handle(
            new FindMediaUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 300),
        );

        self::assertNotNull($result);

        // Оригинал — прямой публичный URL без срока.
        self::assertNotNull($result->original);
        self::assertSame('http://minio/media-public/' . $media->path->value(), $result->original->url);
        self::assertNull($result->original->expiresAt);

        // Все три конверсии присутствуют, каждая со своим видом, типом и URL по своему пути.
        self::assertCount(3, $result->conversions);

        $thumbnailUrl = $this->conversionByType($result->conversions, MediaImageConversionType::Thumbnail);
        self::assertSame(MediaConversionKind::Image, $thumbnailUrl->kind);
        self::assertSame('http://minio/media-public/' . $thumbnail->path->value(), $thumbnailUrl->url);
        self::assertNull($thumbnailUrl->expiresAt);

        $videoUrl = $this->conversionByType($result->conversions, MediaVideoConversionType::NormalizedMp4H264);
        self::assertSame(MediaConversionKind::Video, $videoUrl->kind);
        self::assertSame('http://minio/media-public/' . $video->path->value(), $videoUrl->url);

        $audioUrl = $this->conversionByType($result->conversions, MediaAudioConversionType::NormalizedAacM4a);
        self::assertSame(MediaConversionKind::Audio, $audioUrl->kind);
        self::assertSame('http://minio/media-public/' . $audio->path->value(), $audioUrl->url);
    }

    public function testReturnsPresignedOriginalAndConversionForReadyPrivateMedia(): void
    {
        $media = $this->readyMedia(MediaVisibility::Private);
        $thumbnail = $this->thumbnailConversion($media);
        $this->persist($media, $thumbnail);
        $this->cleanOrmHeap();

        $fileService = $this->createMock(MediaFileServiceContract::class);
        $fileService->expects(self::never())->method('publicUrl');
        $fileService->expects(self::atLeastOnce())->method('presignGet')->willReturnCallback(
            static fn(MediaStorage $storage, MediaPath $path, \DateTimeImmutable $expiresAt): string
                => 'http://minio/signed/' . $path->value(),
        );

        $result = $this->handler($fileService)->handle(
            new FindMediaUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 300),
        );

        self::assertNotNull($result);

        // Оригинал — presigned-ссылка со сроком.
        self::assertNotNull($result->original);
        self::assertSame('http://minio/signed/' . $media->path->value(), $result->original->url);
        self::assertNotNull($result->original->expiresAt);
        self::assertEqualsWithDelta(
            new \DateTimeImmutable('+300 seconds')->getTimestamp(),
            $result->original->expiresAt->getTimestamp(),
            5,
        );

        // Конверсия private тоже presigned со сроком.
        self::assertCount(1, $result->conversions);
        $conversion = $this->conversionByType($result->conversions, MediaImageConversionType::Thumbnail);
        self::assertSame('http://minio/signed/' . $thumbnail->path->value(), $conversion->url);
        self::assertNotNull($conversion->expiresAt);
    }

    public function testOmitsOriginalButKeepsConversionsForReadyOriginalRemovedMedia(): void
    {
        // Ключевое поведение: после удаления оригинала результат не null — original = null,
        // а конверсии продолжают резолвиться. Никакого 404.
        $media = $this->readyMedia(MediaVisibility::Public);
        $thumbnail = $this->thumbnailConversion($media);
        $media->markReadyOriginalRemoved();
        $this->persist($media, $thumbnail);
        $this->cleanOrmHeap();

        $fileService = $this->createStub(MediaFileServiceContract::class);
        $fileService->method('publicUrl')->willReturnCallback(
            static fn(MediaStorage $storage, MediaPath $path): string => 'http://minio/media-public/' . $path->value(),
        );

        $result = $this->handler($fileService)->handle(
            new FindMediaUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 300),
        );

        self::assertNotNull($result);
        self::assertNull($result->original);
        self::assertCount(1, $result->conversions);
        self::assertSame(
            'http://minio/media-public/' . $thumbnail->path->value(),
            $this->conversionByType($result->conversions, MediaImageConversionType::Thumbnail)->url,
        );
    }

    public function testReturnsEmptyConversionsForReadyMediaWithoutConversions(): void
    {
        $media = $this->readyMedia(MediaVisibility::Public);
        $this->persist($media);
        $this->cleanOrmHeap();

        $fileService = $this->createStub(MediaFileServiceContract::class);
        $fileService->method('publicUrl')->willReturn('http://minio/media-public/object');

        $result = $this->handler($fileService)->handle(
            new FindMediaUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 300),
        );

        self::assertNotNull($result);
        self::assertNotNull($result->original);
        self::assertSame('http://minio/media-public/object', $result->original->url);
        self::assertTrue($result->conversions->isEmpty());
    }

    public function testExcludesNonReadyConversionsFromUrls(): void
    {
        // Среди связей есть готовая (Ready) image-конверсия и не-Ready (processing) video-конверсия.
        // В набор URL попадает только готовая: не-Ready не отдаём, чтобы не дать ссылку на не готовый объект.
        $media = $this->readyMedia(MediaVisibility::Public);
        $thumbnail = $this->thumbnailConversion($media);
        $processingVideo = $this->videoConversion(media: $media, status: MediaConversionStatus::Processing);
        $this->persist($media, $thumbnail, $processingVideo);
        $this->cleanOrmHeap();

        $fileService = $this->createStub(MediaFileServiceContract::class);
        $fileService->method('publicUrl')->willReturnCallback(
            static fn(MediaStorage $storage, MediaPath $path): string => 'http://minio/media-public/' . $path->value(),
        );

        $result = $this->handler($fileService)->handle(
            new FindMediaUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 300),
        );

        self::assertNotNull($result);
        self::assertCount(1, $result->conversions);
        self::assertNotNull($result->conversions->ofType(MediaImageConversionType::Thumbnail));
        self::assertNull($result->conversions->ofType(MediaVideoConversionType::NormalizedMp4H264));
    }

    public function testReindexesConversionsWhenNonReadyPrecedesReadyInSameKind(): void
    {
        // В рамках одного вида (image) первой по порядку (orderBy id ASC) идёт не-Ready (processing)
        // конверсия, затем Ready. filter() отсеивает первую и без переиндексации оставил бы готовую на
        // ключе 1 — коллекция стала бы картой с разрывом и сериализовалась как JSON-объект {"1": ...}.
        // Проверяем, что итог переиндексирован в список и сериализуется как массив.
        $media = $this->readyMedia(MediaVisibility::Public);
        $processingThumbnail = $this->imageConversion(
            media: $media,
            type: MediaImageConversionType::Thumbnail,
            status: MediaConversionStatus::Processing,
        );
        $readyPreview = $this->imageConversion(
            media: $media,
            type: MediaImageConversionType::Preview,
            status: MediaConversionStatus::Ready,
        );
        $this->persist($media, $processingThumbnail, $readyPreview);
        $this->cleanOrmHeap();

        $fileService = $this->createStub(MediaFileServiceContract::class);
        $fileService->method('publicUrl')->willReturnCallback(
            static fn(MediaStorage $storage, MediaPath $path): string => 'http://minio/media-public/' . $path->value(),
        );

        $result = $this->handler($fileService)->handle(
            new FindMediaUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 300),
        );

        self::assertNotNull($result);

        // Осталась только готовая конверсия, и она на нулевом ключе — список без разрыва.
        self::assertCount(1, $result->conversions);
        self::assertSame([0], $result->conversions->keys()->all());
        self::assertNotNull($result->conversions->ofType(MediaImageConversionType::Preview));

        // Главное: набор сериализуется как JSON-массив, а не как объект с разрывом ключей ({"1": ...}).
        $encoded = \json_encode($result->conversions);
        self::assertNotFalse($encoded);
        self::assertStringStartsWith('[', $encoded);
    }

    public function testIgnoresInvalidTtlForPublicMedia(): void
    {
        // Для public-медиа ветка резолвера short-circuit'ит до построения MediaPresignedTtl, поэтому
        // заведомо невалидный TTL (явный 0) проходит как успех без presignGet — причина именно в
        // public short-circuit, а не в самом значении 0 (для private такой 0 бросил бы исключение).
        $media = $this->readyMedia(MediaVisibility::Public);
        $this->persist($media);
        $this->cleanOrmHeap();

        $fileService = $this->createMock(MediaFileServiceContract::class);
        $fileService->expects(self::once())->method('publicUrl')->willReturn('http://minio/media-public/object');
        $fileService->expects(self::never())->method('presignGet');

        $result = $this->handler($fileService)->handle(
            new FindMediaUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 0),
        );

        self::assertNotNull($result);
        self::assertNotNull($result->original);
        self::assertSame('http://minio/media-public/object', $result->original->url);
        self::assertNull($result->original->expiresAt);
    }

    public function testUsesDefaultTtlForPrivateMediaWithoutOverride(): void
    {
        // Без presignedTtlSeconds в запросе берётся значение по умолчанию сервиса (3600), а expiresAt
        // считается на каждый вызов (singleton) — now + 3600.
        $media = $this->readyMedia(MediaVisibility::Private);
        $this->persist($media);
        $this->cleanOrmHeap();

        $fileService = $this->createStub(MediaFileServiceContract::class);
        $fileService->method('presignGet')->willReturnCallback(
            static fn(MediaStorage $storage, MediaPath $path, \DateTimeImmutable $expiresAt): string
                => 'http://minio/signed/' . $path->value(),
        );

        $result = $this->handler($fileService)->handle(
            new FindMediaUrlQuery(mediaId: $media->id->value()),
        );

        self::assertNotNull($result);
        self::assertNotNull($result->original);
        self::assertNotNull($result->original->expiresAt);
        self::assertEqualsWithDelta(
            new \DateTimeImmutable('+3600 seconds')->getTimestamp(),
            $result->original->expiresAt->getTimestamp(),
            5,
        );
    }

    public function testRejectsExplicitZeroTtlForPrivateMedia(): void
    {
        // Строгая семантика !== null: явный 0 для private не уходит в значение по умолчанию, а строит
        // MediaPresignedTtl::fromInt(0) -> вне диапазона (MIN=1) -> InvalidDomainValueException.
        $media = $this->readyMedia(MediaVisibility::Private);
        $this->persist($media);
        $this->cleanOrmHeap();

        $this->expectException(InvalidDomainValueException::class);

        $this->handler($this->createStub(MediaFileServiceContract::class))->handle(
            new FindMediaUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 0),
        );
    }

    private function handler(MediaFileServiceContract $fileService): FindMediaUrlHandler
    {
        return new FindMediaUrlHandler(
            mediaRepository: $this->mediaRepository(),
            mediaUrlService: new MediaUrlService(
                mediaFileService: $fileService,
                mediaConfig: $this->getContainer()->get(MediaConfig::class),
            ),
        );
    }

    private function conversionByType(
        MediaConversionUrlCollection $conversions,
        MediaImageConversionType|MediaVideoConversionType|MediaAudioConversionType $type,
    ): MediaConversionUrl {
        $conversion = $conversions->ofType($type);
        self::assertNotNull($conversion, \sprintf('Конверсия типа %s не найдена в результате.', $type->value));

        return $conversion;
    }
}
