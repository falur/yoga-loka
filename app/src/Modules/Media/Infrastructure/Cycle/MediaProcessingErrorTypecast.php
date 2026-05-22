<?php

declare(strict_types=1);

namespace App\Modules\Media\Infrastructure\Cycle;

use App\Modules\Media\Domain\ValueObject\MediaProcessingError;
use App\Shared\Infrastructure\Cycle\ColumnValueTypecast;

final class MediaProcessingErrorTypecast implements ColumnValueTypecast
{
    #[\Override]
    public static function castDatabaseValue(
        bool|int|float|string|\DateTimeInterface|null $value,
    ): MediaProcessingError {
        if ($value === null) {
            return MediaProcessingError::none();
        }

        if (!\is_string($value)) {
            throw new \InvalidArgumentException('Ошибка обработки должна быть строкой.');
        }

        return MediaProcessingError::fromString($value);
    }

    #[\Override]
    public static function uncastValue(
        object|null $value,
    ): ?string {
        if ($value === null) {
            return null;
        }

        if (!$value instanceof MediaProcessingError) {
            throw new \InvalidArgumentException('Значение должно быть ошибкой обработки.');
        }

        return $value->value();
    }
}
