<?php

declare(strict_types=1);

namespace App\Modules\Media\Infrastructure\Spiral\Http\Resource;

use App\Shared\Infrastructure\Spiral\Http\Resource\AbstractResource;
use App\Modules\Media\Application\View\MediaConversionView;
use App\Modules\Media\Application\View\MediaView;

/**
 * Медиа с преобразованиями в ответе API: оригинал (ссылка + срок) и все его конверсии одним
 * объектом, чтобы клиент сам выбрал, что показать. Одна форма для вложений записи и аватара автора.
 * id всегда задан — объект есть только за реальной сущностью медиа. Неприменимое в контексте поле —
 * null: position — вне упорядоченного набора вложений, original — когда оригинал удалён (конверсии
 * остаются).
 */
final readonly class MediaResource extends AbstractResource
{
    /**
     * @param list<MediaConversionResource> $conversions
     */
    public function __construct(
        public string $id,
        public int|null $position,
        public MediaOriginalResource|null $original,
        public array $conversions,
    ) {}

    public static function fromView(MediaView $media): self
    {
        return new self(
            id: $media->id,
            position: $media->position,
            original: $media->original === null ? null : MediaOriginalResource::fromView($media->original),
            conversions: \array_map(
                static fn(MediaConversionView $conversion): MediaConversionResource => MediaConversionResource::fromView($conversion),
                $media->conversions,
            ),
        );
    }
}
