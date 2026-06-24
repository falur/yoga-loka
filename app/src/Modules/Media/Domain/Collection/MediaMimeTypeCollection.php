<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\Collection;

use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Shared\Domain\Collection\TypedCollection;

/**
 * @extends TypedCollection<int, MediaMimeType>
 */
final class MediaMimeTypeCollection extends TypedCollection
{
    /**
     * Сравнение по базовому MIME без параметров (`baseValue()`) — нормализация умышленно общая для
     * всех типов, а не только документов: для документов с charset-параметром (Markdown, CSV) она
     * обязательна, и тот же базовый MIME проходит спецификацию для image/video/audio с параметром.
     * Это осознанный выбор: спецификацию `allowedMimeTypes` задаёт потребитель, а хранимое значение
     * (`MediaMimeType::value()`) остаётся исходным.
     */
    public function containsMimeType(MediaMimeType $mimeType): bool
    {
        return $this->contains(
            static fn(MediaMimeType $allowed): bool => $allowed->baseValue() === $mimeType->baseValue(),
        );
    }
}
