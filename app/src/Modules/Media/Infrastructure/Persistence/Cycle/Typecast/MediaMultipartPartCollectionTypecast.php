<?php

declare(strict_types=1);

namespace App\Modules\Media\Infrastructure\Persistence\Cycle\Typecast;

use App\Modules\Media\Domain\Collection\MediaMultipartPartCollection;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPart;
use App\Shared\Infrastructure\Persistence\Cycle\ColumnValueTypecast;

final class MediaMultipartPartCollectionTypecast implements ColumnValueTypecast
{
    public static function castDatabaseValue(
        string $value,
    ): MediaMultipartPartCollection {
        $payload = \json_decode(
            json: $value,
            associative: true,
            flags: \JSON_THROW_ON_ERROR | \JSON_INVALID_UTF8_SUBSTITUTE,
        );

        if (!\is_array($payload)) {
            throw new \InvalidArgumentException('Список частей загрузки должен быть JSON-массивом.');
        }

        $parts = [];
        foreach ($payload as $partPayload) {
            if (!\is_array($partPayload)) {
                throw new \InvalidArgumentException('Часть загрузки имеет неверный формат.');
            }

            $partNumber = $partPayload['partNumber'] ?? null;
            $eTag = $partPayload['eTag'] ?? null;

            if (!\is_int($partNumber) || !\is_string($eTag)) {
                throw new \InvalidArgumentException('Часть загрузки имеет неверный формат.');
            }

            $parts[] = MediaMultipartPart::fromValues(partNumber: $partNumber, eTag: $eTag);
        }

        return new MediaMultipartPartCollection($parts);
    }

    public static function uncastValue(
        MediaMultipartPartCollection $value,
    ): string {
        return \json_encode(
            value: $value->jsonSerialize(),
            flags: \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR | \JSON_INVALID_UTF8_SUBSTITUTE,
        );
    }
}
