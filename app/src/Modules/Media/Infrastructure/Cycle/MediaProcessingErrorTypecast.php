<?php

declare(strict_types=1);

namespace App\Modules\Media\Infrastructure\Cycle;

use App\Modules\Media\Domain\ValueObject\MediaProcessingError;
use App\Shared\Infrastructure\Cycle\ColumnValueTypecast;

final class MediaProcessingErrorTypecast implements ColumnValueTypecast
{
    public static function castDatabaseValue(
        string|null $value,
    ): MediaProcessingError {
        if ($value === null) {
            return MediaProcessingError::none();
        }

        return MediaProcessingError::fromString($value);
    }

    public static function uncastValue(
        MediaProcessingError|null $value,
    ): string|null {
        if ($value === null) {
            return null;
        }

        return $value->value();
    }
}
