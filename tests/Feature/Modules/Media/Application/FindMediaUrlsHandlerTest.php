<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Media\Application;

use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Media\Application\Dto\MediaConversionUrl;
use App\Modules\Media\Application\Dto\MediaConversionUrlCollection;
use App\Modules\Media\Application\Dto\MediaUrlsResult;
use App\Modules\Media\Application\Query\FindMediaUrls\FindMediaUrlsHandler;
use App\Modules\Media\Application\Query\FindMediaUrls\FindMediaUrlsQuery;
use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Enum\MediaAudioConversionType;
use App\Modules\Media\Domain\Enum\MediaConversionKind;
use App\Modules\Media\Domain\Enum\MediaConversionStatus;
use App\Modules\Media\Domain\Enum\MediaImageConversionType;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\Enum\MediaVideoConversionType;
use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Infrastructure\Storage\MediaUrlService;
use App\Shared\Domain\Exception\InvalidDomainValueException;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Spiral\Configuration\Media\MediaConfig;

/**
 * Пакетное разрешение URL нескольких медиа: результат ключуется по id медиа, недоступные (не
 * финализированы, не найдены) в набор не попадают. Здесь же проверяются ветки построения ссылок
 * (public/presigned, срок жизни presigned-ссылки, набор конверсий) — единственная дверь к ним из
 * сценариев модуля.
 */
final class FindMediaUrlsHandlerTest extends MediaApplicationTestCase
{
    public function testReturnsEmptyCollectionForEmptyInput(): void
    {
        $result = $this->handler($this->createStub(MediaFileServiceContract::class))->handle(
            new FindMediaUrlsQuery(mediaIds: []),
        );

        self::assertCount(0, $result);
    }

    public function testResolvesAvailableMediaKeyedByIdAndSkipsUnavailable(): void
    {
        $first = $this->readyMedia(MediaVisibility::Public);
        $second = $this->readyMedia(MediaVisibility::Public);
        $notReady = $this->createMedia(userId: UserId::generate(), visibility: MediaVisibility::Public);
        $this->persist($first, $second, $notReady);
        $this->cleanOrmHeap();

        $fileService = $this->createStub(MediaFileServiceContract::class);
        $fileService->method('publicUrl')->willReturn('https://cdn.example/x.jpg');

        $result = $this->handler($fileService)->handle(new FindMediaUrlsQuery(
            mediaIds: [
                $first->id->value(),
                $second->id->value(),
                $notReady->id->value(),
                UserId::generate()->value(),
            ],
        ));

        // Доступные медиа лежат под своим id; нефинализированное и несуществующее в набор не попадают.
        self::assertCount(2, $result);
        $firstUrls = $result->get($first->id->value());
        self::assertNotNull($firstUrls);
        self::assertNotNull($firstUrls->original);
        self::assertSame('https://cdn.example/x.jpg', $firstUrls->original->url);
        self::assertNotNull($result->get($second->id->value()));
        self::assertNull($result->get($notReady->id->value()));
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

        $urls = $this->urlsOf($fileService, $media, presignedTtlSeconds: 300);

        // Оригинал — прямой публичный URL без срока.
        self::assertNotNull($urls->original);
        self::assertSame('http://minio/media-public/' . $media->path->value(), $urls->original->url);
        self::assertNull($urls->original->expiresAt);

        // Все три конверсии присутствуют, каждая со своим видом, типом и URL по своему пути.
        self::assertCount(3, $urls->conversions);

        $thumbnailUrl = $this->conversionByType($urls->conversions, MediaImageConversionType::Thumbnail);
        self::assertSame(MediaConversionKind::Image, $thumbnailUrl->kind);
        self::assertSame('http://minio/media-public/' . $thumbnail->path->value(), $thumbnailUrl->url);
        self::assertNull($thumbnailUrl->expiresAt);

        $videoUrl = $this->conversionByType($urls->conversions, MediaVideoConversionType::NormalizedMp4H264);
        self::assertSame(MediaConversionKind::Video, $videoUrl->kind);
        self::assertSame('http://minio/media-public/' . $video->path->value(), $videoUrl->url);

        $audioUrl = $this->conversionByType($urls->conversions, MediaAudioConversionType::NormalizedAacM4a);
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

        $urls = $this->urlsOf($fileService, $media, presignedTtlSeconds: 300);

        // Оригинал — presigned-ссылка со сроком.
        self::assertNotNull($urls->original);
        self::assertSame('http://minio/signed/' . $media->path->value(), $urls->original->url);
        self::assertNotNull($urls->original->expiresAt);
        self::assertEqualsWithDelta(
            new \DateTimeImmutable('+300 seconds')->getTimestamp(),
            $urls->original->expiresAt->getTimestamp(),
            5,
        );

        // Конверсия private тоже presigned со сроком.
        self::assertCount(1, $urls->conversions);
        $conversion = $this->conversionByType($urls->conversions, MediaImageConversionType::Thumbnail);
        self::assertSame('http://minio/signed/' . $thumbnail->path->value(), $conversion->url);
        self::assertNotNull($conversion->expiresAt);
    }

    public function testOmitsOriginalButKeepsConversionsForReadyOriginalRemovedMedia(): void
    {
        // Ключевое поведение: после удаления оригинала медиа остаётся в наборе — original = null,
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

        $urls = $this->urlsOf($fileService, $media, presignedTtlSeconds: 300);

        self::assertNull($urls->original);
        self::assertCount(1, $urls->conversions);
        self::assertSame(
            'http://minio/media-public/' . $thumbnail->path->value(),
            $this->conversionByType($urls->conversions, MediaImageConversionType::Thumbnail)->url,
        );
    }

    public function testReturnsEmptyConversionsForReadyMediaWithoutConversions(): void
    {
        $media = $this->readyMedia(MediaVisibility::Public);
        $this->persist($media);
        $this->cleanOrmHeap();

        $fileService = $this->createStub(MediaFileServiceContract::class);
        $fileService->method('publicUrl')->willReturn('http://minio/media-public/object');

        $urls = $this->urlsOf($fileService, $media, presignedTtlSeconds: 300);

        self::assertNotNull($urls->original);
        self::assertSame('http://minio/media-public/object', $urls->original->url);
        self::assertTrue($urls->conversions->isEmpty());
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

        $urls = $this->urlsOf($fileService, $media, presignedTtlSeconds: 300);

        self::assertCount(1, $urls->conversions);
        self::assertNotNull($urls->conversions->ofType(MediaImageConversionType::Thumbnail));
        self::assertNull($urls->conversions->ofType(MediaVideoConversionType::NormalizedMp4H264));
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

        $urls = $this->urlsOf($fileService, $media, presignedTtlSeconds: 300);

        // Осталась только готовая конверсия, и она на нулевом ключе — список без разрыва.
        self::assertCount(1, $urls->conversions);
        self::assertSame([0], $urls->conversions->keys()->all());
        self::assertNotNull($urls->conversions->ofType(MediaImageConversionType::Preview));

        // Главное: набор сериализуется как JSON-массив, а не как объект с разрывом ключей ({"1": ...}).
        $encoded = \json_encode($urls->conversions);
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

        $urls = $this->urlsOf($fileService, $media, presignedTtlSeconds: 0);

        self::assertNotNull($urls->original);
        self::assertSame('http://minio/media-public/object', $urls->original->url);
        self::assertNull($urls->original->expiresAt);
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

        $urls = $this->urlsOf($fileService, $media);

        self::assertNotNull($urls->original);
        self::assertNotNull($urls->original->expiresAt);
        self::assertEqualsWithDelta(
            new \DateTimeImmutable('+3600 seconds')->getTimestamp(),
            $urls->original->expiresAt->getTimestamp(),
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
            new FindMediaUrlsQuery(mediaIds: [$media->id->value()], presignedTtlSeconds: 0),
        );
    }

    /**
     * Разрешает ссылки одного медиа через пакетный сценарий и проверяет, что оно попало в набор.
     */
    private function urlsOf(
        MediaFileServiceContract $fileService,
        Media $media,
        int|null $presignedTtlSeconds = null,
    ): MediaUrlsResult {
        $result = $this->handler($fileService)->handle(new FindMediaUrlsQuery(
            mediaIds: [$media->id->value()],
            presignedTtlSeconds: $presignedTtlSeconds,
        ));

        $urls = $result->get($media->id->value());
        self::assertNotNull($urls);

        return $urls;
    }

    private function handler(MediaFileServiceContract $fileService): FindMediaUrlsHandler
    {
        return new FindMediaUrlsHandler(
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
