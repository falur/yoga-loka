<?php

declare(strict_types=1);

namespace App\Modules\Media\Infrastructure\Spiral\PublicApi;

use App\Modules\Media\Application\Command\MakeMediaPermanent\MakeMediaPermanentCommand;
use App\Modules\Media\Application\Command\MakeMediaPermanent\MakeMediaPermanentHandler;
use App\Modules\Media\Application\Dto\MediaConversionUrl;
use App\Modules\Media\Application\Dto\MediaUrlsResult;
use App\Modules\Media\Application\Query\CheckMediaAttachable\CheckMediaAttachableHandler;
use App\Modules\Media\Application\Query\CheckMediaAttachable\CheckMediaAttachableQuery;
use App\Modules\Media\Application\Query\FindMediaUrls\FindMediaUrlsHandler;
use App\Modules\Media\Application\Query\FindMediaUrls\FindMediaUrlsQuery;
use App\Modules\Media\Public\Contract\MediaContract;
use App\Modules\Media\Public\Dto\MediaConversionDto;
use App\Modules\Media\Public\Dto\MediaDto;
use App\Modules\Media\Public\Dto\MediaDtoCollection;
use App\Modules\Media\Public\Dto\MediaOriginalDto;
use App\Modules\Media\Public\Enum\MediaAudioConversionType;
use App\Modules\Media\Public\Enum\MediaConversionKind;
use App\Modules\Media\Public\Enum\MediaImageConversionType;
use App\Modules\Media\Public\Enum\MediaVideoConversionType;
use GianTiaga\SpiralCqrs\CommandBusInterface;
use GianTiaga\SpiralCqrs\QueryBusInterface;

/**
 * Входной адаптер публичного контракта Media: раскладывает вызовы соседей в пакетные сценарии модуля
 * и переводит их Result в публичные DTO. Правил доступности и пригодности медиа здесь нет — они
 * остаются в сценариях: недоступное медиа не попадает в Result разрешения ссылок, а непригодное к
 * вложению даёт исключение сценария проверки.
 */
final readonly class MediaProvider implements MediaContract
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private QueryBusInterface $queryBus,
        private FindMediaUrlsHandler $findMediaUrlsHandler,
        private CheckMediaAttachableHandler $checkMediaAttachableHandler,
        private MakeMediaPermanentHandler $makeMediaPermanentHandler,
    ) {}

    /**
     * @param list<string> $mediaIds
     */
    #[\Override]
    public function urlsByIds(array $mediaIds): MediaDtoCollection
    {
        $urls = $this->queryBus->dispatch(
            query: new FindMediaUrlsQuery(mediaIds: $mediaIds),
            handler: $this->findMediaUrlsHandler->handle(...),
        );

        return new MediaDtoCollection(
            $urls->toBase()->map(
                fn(MediaUrlsResult $urlsResult, string $mediaId): MediaDto => $this->mediaDto(
                    mediaId: $mediaId,
                    urlsResult: $urlsResult,
                ),
            ),
        );
    }

    /**
     * @param list<string> $mediaIds
     */
    #[\Override]
    public function ensureAttachable(array $mediaIds, string $ownerUserId): void
    {
        $this->queryBus->dispatch(
            query: new CheckMediaAttachableQuery(mediaIds: $mediaIds, ownerUserId: $ownerUserId),
            handler: $this->checkMediaAttachableHandler->handle(...),
        );
    }

    /**
     * @param list<string> $mediaIds
     */
    #[\Override]
    public function makePermanent(array $mediaIds, string $ownerUserId): void
    {
        $this->commandBus->dispatch(
            command: new MakeMediaPermanentCommand(userId: $ownerUserId, mediaIds: $mediaIds),
            handler: $this->makeMediaPermanentHandler->handle(...),
        );
    }

    private function mediaDto(string $mediaId, MediaUrlsResult $urlsResult): MediaDto
    {
        return new MediaDto(
            id: $mediaId,
            original: $urlsResult->original === null
                ? null
                : new MediaOriginalDto(url: $urlsResult->original->url, expiresAt: $urlsResult->original->expiresAt),
            conversions: $urlsResult->conversions->mapToList(
                fn(MediaConversionUrl $conversion): MediaConversionDto => $this->conversionDto($conversion),
            ),
        );
    }

    private function conversionDto(MediaConversionUrl $conversion): MediaConversionDto
    {
        $kind = MediaConversionKind::from($conversion->kind->value);

        return new MediaConversionDto(
            kind: $kind,
            type: $this->conversionType(kind: $kind, typeValue: $conversion->type->value),
            url: $conversion->url,
            expiresAt: $conversion->expiresAt,
        );
    }

    /**
     * Доменный вариант переводится в публичный по строковому значению — набор вариантов и значений
     * держит в синхронном состоянии unit-проверка совпадения. Вид конверсии однозначно называет
     * enum профиля (постер видео — это конверсия вида image), поэтому класс профиля выбирается
     * исчерпывающим match по виду, а не разбором вариантов профиля.
     */
    private function conversionType(
        MediaConversionKind $kind,
        string $typeValue,
    ): MediaImageConversionType|MediaVideoConversionType|MediaAudioConversionType {
        return match ($kind) {
            MediaConversionKind::Image => MediaImageConversionType::from($typeValue),
            MediaConversionKind::Video => MediaVideoConversionType::from($typeValue),
            MediaConversionKind::Audio => MediaAudioConversionType::from($typeValue),
        };
    }
}
