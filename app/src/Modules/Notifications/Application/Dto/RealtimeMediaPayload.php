<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Dto;

use App\Modules\Media\Application\View\MediaConversionView;
use App\Modules\Media\Application\View\MediaView;

/**
 * Аватар автора в realtime-payload (Centrifugo): оригинал и все конверсии одним объектом — та же форма
 * MediaView, что в HTTP-ответе инбокса (MediaResource), чтобы клиент показывал одинаковый аватар. id
 * всегда задан (объект есть только за реальной сущностью медиа); position у аватара — null. Вложенные
 * объекты (original, conversions) сериализуются рекурсивно самим json_encode.
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

    public static function fromView(MediaView $media): self
    {
        return new self(
            id: $media->id,
            position: $media->position,
            original: $media->original === null ? null : RealtimeMediaOriginalPayload::fromView($media->original),
            conversions: \array_map(
                static fn(MediaConversionView $conversion): RealtimeMediaConversionPayload
                    => RealtimeMediaConversionPayload::fromView($conversion),
                $media->conversions,
            ),
        );
    }
}
