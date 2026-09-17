<?php

declare(strict_types=1);

namespace App\Modules\Media\Infrastructure\Persistence\Cycle\Typecast;

use App\Modules\Media\Domain\ValueObject\MediaWaveform;
use App\Shared\Infrastructure\Persistence\Cycle\ColumnValueTypecast;

final class MediaWaveformTypecast implements ColumnValueTypecast
{
    public static function castDatabaseValue(string $value): MediaWaveform
    {
        $payload = \json_decode(
            json: $value,
            associative: true,
            flags: \JSON_THROW_ON_ERROR | \JSON_INVALID_UTF8_SUBSTITUTE,
        );

        if (!\is_array($payload)) {
            throw new \InvalidArgumentException('Волна аудио должна быть JSON-массивом.');
        }

        $peaks = [];
        foreach ($payload as $peak) {
            if (!\is_int($peak)) {
                throw new \InvalidArgumentException('Амплитуда волны должна быть целым числом.');
            }

            $peaks[] = $peak;
        }

        return MediaWaveform::fromPeaks($peaks);
    }

    public static function uncastValue(MediaWaveform $value): string
    {
        return \json_encode(
            value: $value->jsonSerialize(),
            flags: \JSON_THROW_ON_ERROR | \JSON_INVALID_UTF8_SUBSTITUTE,
        );
    }
}
