<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Dto;

/**
 * Полный набор URL медиа: оригинал и все его конверсии. original = null, если оригинал удалён
 * (readyOriginalRemoved) — при этом конверсии продолжают резолвиться. Вызывающий получает всё
 * сразу и сам выбирает нужное по типу, не запрашивая конверсии по отдельности.
 *
 * Это форма ответа сценария Media. Соседям она не отдаётся: на межмодульной границе её переводит в
 * публичные DTO провайдер контракта (Infrastructure/Spiral/PublicApi).
 */
final readonly class MediaUrlsResult
{
    public function __construct(
        public MediaUrlResult|null $original,
        public MediaConversionUrlCollection $conversions,
    ) {}
}
