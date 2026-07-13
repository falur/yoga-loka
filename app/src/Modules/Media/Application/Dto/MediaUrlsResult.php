<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Dto;

use App\Shared\Application\View\MediaConversionView;
use App\Shared\Application\View\MediaOriginalView;
use App\Shared\Application\View\MediaView;

/**
 * Полный набор URL медиа: оригинал и все его конверсии. original = null, если оригинал удалён
 * (readyOriginalRemoved) — при этом конверсии продолжают резолвиться. Вызывающий получает всё
 * сразу и сам выбирает нужное по типу, не запрашивая конверсии по отдельности.
 */
final readonly class MediaUrlsResult
{
    public function __construct(
        public MediaUrlResult|null $original,
        public MediaConversionUrlCollection $conversions,
    ) {}

    /**
     * Проекция результата сценария в общий read-model MediaView (Shared/Application/View) — единая
     * точка перекладки для ассемблеров-потребителей (Posts, User), чтобы каждый не повторял маппинг.
     * id и position задаёт вызывающий: у вложения записи это mediaId и позиция, у аватара — id медиа
     * без позиции. id всегда задан — проекция строится только за реальной сущностью медиа.
     */
    public function toView(string $id, int|null $position): MediaView
    {
        return new MediaView(
            id: $id,
            position: $position,
            original: $this->original === null
                ? null
                : new MediaOriginalView(url: $this->original->url, expiresAt: $this->original->expiresAt),
            conversions: $this->conversions->mapToList(
                static fn(MediaConversionUrl $conversion): MediaConversionView => new MediaConversionView(
                    kind: $conversion->kind,
                    type: $conversion->type,
                    url: $conversion->url,
                    expiresAt: $conversion->expiresAt,
                ),
            ),
        );
    }
}
