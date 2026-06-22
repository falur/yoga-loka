<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Service;

use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Shared\Domain\Exception\ValidationException;

/**
 * Резолвер MIME -> MediaType. Намеренно сужающий: поддержаны image/*, video/* и audio/*;
 * прочие типы (документы и т.п.) вне области пайплайна и отклоняются как ожидаемая клиентская
 * ошибка 422 (MediaPath допускает только префиксы uploads|images|videos|audios).
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

        if (\str_starts_with(haystack: $value, needle: 'audio/')) {
            return MediaType::Audio;
        }

        throw new ValidationException(
            translationKey: 'app.media.unsupported_file_type',
            translationParameters: ['type' => $value],
        );
    }
}
