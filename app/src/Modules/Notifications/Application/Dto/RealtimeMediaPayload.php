<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Dto;

use App\Modules\Media\Public\Dto\MediaConversionDto;
use App\Modules\Media\Public\Dto\MediaDto;

/**
 * Аватар автора в realtime-payload (Centrifugo): оригинал и все конверсии одним объектом — та же
 * форма, что в HTTP-ответе инбокса (MediaResource), чтобы клиент показывал одинаковый аватар. id
 * всегда задан (объект есть только за реальным медиа); position у аватара — null, потому что позиция
 * принадлежит записи, а не медиа. Вложенные объекты (original, conversions) сериализуются рекурсивно
 * самим json_encode.
 */
final readonly class RealtimeMediaPayload
{
    /**
     * @param list<RealtimeMediaConversionPayload> $conversions
     */
    public function __construct(
        public string $id,
        public int|null $position,
        public RealtimeMediaOriginalPayload|null $original,
        public array $conversions,
    ) {}

    public static function fromDto(MediaDto $media): self
    {
        return new self(
            id: $media->id,
            position: null,
            original: $media->original === null ? null : RealtimeMediaOriginalPayload::fromDto($media->original),
            conversions: \array_map(
                static fn(MediaConversionDto $conversion): RealtimeMediaConversionPayload
                    => RealtimeMediaConversionPayload::fromDto($conversion),
                $media->conversions,
            ),
        );
    }
}
