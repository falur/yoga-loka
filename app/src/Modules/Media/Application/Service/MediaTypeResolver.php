<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Service;

use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Shared\Domain\Exception\ValidationException;

/**
 * Резолвер MIME -> MediaType. Намеренно сужающий: поддержаны только image/* и video/*;
 * прочие типы (audio, документы и т.п.) вне scope пайплайна и отклоняются как ожидаемая
 * клиентская ошибка 422 (MediaPath допускает только префиксы uploads|images|videos).
 */
final readonly class MediaTypeResolver
{
    public function resolve(MediaMimeType $mimeType): MediaType
    {
        $value = $mimeType->value();

        if (\str_starts_with(haystack: $value, needle: 'image/')) {
            return MediaType::Image;
        }

        if (\str_starts_with(haystack: $value, needle: 'video/')) {
            return MediaType::Video;
        }

        throw new ValidationException(
            translationKey: 'app.media.unsupported_file_type',
            translationParameters: ['type' => $value],
        );
    }
}
