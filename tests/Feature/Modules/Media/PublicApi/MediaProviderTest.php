<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Media\PublicApi;

use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Media\Application\Command\MakeMediaPermanent\MakeMediaPermanentHandler;
use App\Modules\Media\Application\Query\CheckMediaAttachable\CheckMediaAttachableHandler;
use App\Modules\Media\Application\Query\FindMediaUrls\FindMediaUrlsHandler;
use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Infrastructure\Spiral\PublicApi\MediaProvider;
use App\Modules\Media\Infrastructure\Storage\MediaUrlService;
use App\Modules\Media\Public\Dto\MediaConversionDto;
use App\Modules\Media\Public\Enum\MediaAudioConversionType;
use App\Modules\Media\Public\Enum\MediaConversionKind;
use App\Modules\Media\Public\Enum\MediaImageConversionType;
use App\Modules\Media\Public\Enum\MediaVideoConversionType;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Spiral\Configuration\Media\MediaConfig;
use GianTiaga\SpiralCqrs\CommandBusInterface;
use GianTiaga\SpiralCqrs\QueryBusInterface;
use Tests\Feature\Modules\Media\Application\MediaApplicationTestCase;

/**
 * Публичный контракт Media: пакетное разрешение ссылок на набор идентификаторов и перевод результата
 * сценария в публичные DTO. Доменные варианты вида и типа конверсии переводятся в публичные по
 * строковому значению, поэтому проверяются все три вида (image/video/audio) — расхождение дубликатов
 * сломало бы перевод в рантайме.
 */
final class MediaProviderTest extends MediaApplicationTestCase
{
    public function testReturnsPublicDtoForEveryAvailableMediaOfTheBatch(): void
    {
        $media = $this->readyMedia(MediaVisibility::Public);
        $thumbnail = $this->thumbnailConversion($media);
        $video = $this->videoConversion($media);
        $audio = $this->audioConversion($media);
        $second = $this->readyMedia(MediaVisibility::Public);
        $this->persist($media, $thumbnail, $video, $audio, $second);
        $this->cleanOrmHeap();

        $result = $this->provider()->urlsByIds([$media->id->value(), $second->id->value()]);

        self::assertCount(2, $result);

        $first = $result->get($media->id->value());
        self::assertNotNull($first);
        self::assertSame($media->id->value(), $first->id);
        self::assertNotNull($first->original);
        self::assertSame('http://minio/media-public/' . $media->path->value(), $first->original->url);
        self::assertNull($first->original->expiresAt);
        self::assertCount(3, $first->conversions);

        $thumbnailDto = $this->conversionOfKind(conversions: $first->conversions, kind: MediaConversionKind::Image);
        self::assertSame(MediaImageConversionType::Thumbnail, $thumbnailDto->type);
        self::assertSame('http://minio/media-public/' . $thumbnail->path->value(), $thumbnailDto->url);

        $videoDto = $this->conversionOfKind(conversions: $first->conversions, kind: MediaConversionKind::Video);
        self::assertSame(MediaVideoConversionType::NormalizedMp4H264, $videoDto->type);
        self::assertSame('http://minio/media-public/' . $video->path->value(), $videoDto->url);

        $audioDto = $this->conversionOfKind(conversions: $first->conversions, kind: MediaConversionKind::Audio);
        self::assertSame(MediaAudioConversionType::NormalizedAacM4a, $audioDto->type);
        self::assertSame('http://minio/media-public/' . $audio->path->value(), $audioDto->url);

        $secondDto = $result->get($second->id->value());
        self::assertNotNull($secondDto);
        self::assertSame([], $secondDto->conversions);
    }

    public function testOmitsUnavailableMediaOfTheBatch(): void
    {
        // Недоступное медиа (нет в базе или не финализировано) просто отсутствует в наборе: сосед
        // трактует отсутствие идентификатора как «медиа недоступно» и ставит своё значение по умолчанию.
        $ready = $this->readyMedia(MediaVisibility::Public);
        $notFinalized = $this->createMedia(userId: UserId::generate());
        $this->persist($ready, $notFinalized);
        $this->cleanOrmHeap();

        $result = $this->provider()->urlsByIds([
            $notFinalized->id->value(),
            $ready->id->value(),
            UserId::generate()->value(),
        ]);

        self::assertCount(1, $result);
        self::assertNotNull($result->get($ready->id->value()));
        self::assertNull($result->get($notFinalized->id->value()));
    }

    public function testKeepsConversionsWhenOriginalIsRemoved(): void
    {
        $media = $this->readyMedia(MediaVisibility::Public);
        $thumbnail = $this->thumbnailConversion($media);
        $media->markReadyOriginalRemoved();
        $this->persist($media, $thumbnail);
        $this->cleanOrmHeap();

        $result = $this->provider()->urlsByIds([$media->id->value()]);

        $mediaDto = $result->get($media->id->value());
        self::assertNotNull($mediaDto);
        self::assertNull($mediaDto->original);
        self::assertCount(1, $mediaDto->conversions);
    }

    public function testReturnsEmptyCollectionForEmptyBatch(): void
    {
        $result = $this->provider()->urlsByIds([]);

        self::assertCount(0, $result);
    }

    /**
     * @param list<MediaConversionDto> $conversions
     */
    private function conversionOfKind(array $conversions, MediaConversionKind $kind): MediaConversionDto
    {
        foreach ($conversions as $conversion) {
            if ($conversion->kind === $kind) {
                return $conversion;
            }
        }

        self::fail(\sprintf('В наборе нет конверсии вида %s.', $kind->value));
    }

    private function provider(): MediaProvider
    {
        $fileService = $this->createStub(MediaFileServiceContract::class);
        $fileService->method('publicUrl')->willReturnCallback(
            static fn(MediaStorage $storage, MediaPath $path): string => 'http://minio/media-public/' . $path->value(),
        );

        return new MediaProvider(
            commandBus: $this->getContainer()->get(CommandBusInterface::class),
            queryBus: $this->getContainer()->get(QueryBusInterface::class),
            findMediaUrlsHandler: new FindMediaUrlsHandler(
                mediaRepository: $this->mediaRepository(),
                mediaUrlService: new MediaUrlService(
                    mediaFileService: $fileService,
                    mediaConfig: $this->getContainer()->get(MediaConfig::class),
                ),
            ),
            checkMediaAttachableHandler: $this->getContainer()->get(CheckMediaAttachableHandler::class),
            makeMediaPermanentHandler: $this->getContainer()->get(MakeMediaPermanentHandler::class),
        );
    }
}
