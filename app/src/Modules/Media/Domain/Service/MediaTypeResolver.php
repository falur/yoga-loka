<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\Service;

use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\Exception\MediaUnsupportedFileTypeException;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;

/**
 * Резолвер MIME -> MediaType. Поддержан список разрешённых MIME-типов документов (он
 * проверяется первым) и префиксы image/*, video/* и audio/*; прочие MIME вне области пайплайна
 * и отклоняются как ожидаемая клиентская ошибка 422. MediaPath допускает префиксы
 * uploads|images|videos|audios|documents.
 */
final readonly class MediaTypeResolver
{
    /**
     * Форматы Office с макросами (MIME с суффиксом …macroEnabled.12) намеренно не включены
     * из-за активного содержимого.
     *
     * @var list<string>
     */
    private const array DOCUMENT_MIME_TYPES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'text/plain',
        'application/rtf',
        'text/rtf',
        'application/vnd.oasis.opendocument.text',
        'application/vnd.oasis.opendocument.spreadsheet',
        'application/vnd.oasis.opendocument.presentation',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/epub+zip',
        'application/x-fictionbook+xml',
        'image/vnd.djvu',
        'text/csv',
        'text/markdown',
    ];

    public function resolve(MediaMimeType $mimeType): MediaType
    {
        if (\in_array(needle: $mimeType->baseValue(), haystack: self::DOCUMENT_MIME_TYPES, strict: true)) {
            return MediaType::Document;
        }

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

        throw new MediaUnsupportedFileTypeException($value);
    }
}
