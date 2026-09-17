<?php

declare(strict_types=1);

namespace App\Modules\Posts\Infrastructure\Spiral\Http\Resource;

use App\Modules\Media\Public\Dto\MediaConversionDto;
use App\Modules\Media\Public\Dto\MediaDto;
use App\Modules\Posts\Application\Result\PostMediaResult;
use App\Shared\Infrastructure\Spiral\Http\Resource\AbstractResource;

/**
 * Медиа с преобразованиями в ответе API: оригинал (ссылка + срок) и все его конверсии одним
 * объектом, чтобы клиент сам выбрал, что показать. id всегда задан — объект есть только за реальным
 * медиа. Неприменимое в контексте поле — null: position вне упорядоченного набора вложений (у
 * аватара её нет, потому что позиция принадлежит записи, а не медиа), original — когда оригинал
 * удалён (конверсии остаются).
 *
 * Собственная копия ресурса медиа: общий ресурс жил бы в чужом модуле и нарушал бы его границу, а
 * имя схемы OpenAPI строится по короткому имени класса, поэтому имя и поля повторяются дословно.
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

    /**
     * Вложение записи: позицию в наборе вложений даёт результат Posts, ссылки — публичные DTO Media.
     */
    public static function fromResult(PostMediaResult $media): self
    {
        return new self(
            id: $media->id,
            position: $media->position,
            original: $media->original === null ? null : MediaOriginalResource::fromDto($media->original),
            conversions: self::conversions($media->conversions),
        );
    }

    /**
     * Аватар автора: он вне упорядоченного набора вложений, поэтому position = null.
     */
    public static function fromDto(MediaDto $media): self
    {
        return new self(
            id: $media->id,
            position: null,
            original: $media->original === null ? null : MediaOriginalResource::fromDto($media->original),
            conversions: self::conversions($media->conversions),
        );
    }

    /**
     * @param list<MediaConversionDto> $conversions
     *
     * @return list<MediaConversionResource>
     */
    private static function conversions(array $conversions): array
    {
        return \array_map(
            static fn(MediaConversionDto $conversion): MediaConversionResource
                => MediaConversionResource::fromDto($conversion),
            $conversions,
        );
    }
}
