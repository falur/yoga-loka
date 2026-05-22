<?php

declare(strict_types=1);

namespace App\Modules\Media\Infrastructure\Cycle;

use App\Modules\Media\Domain\Collection\MediaMultipartPartCollection;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPart;
use App\Shared\Infrastructure\Cycle\ColumnValueTypecast;

final class MediaMultipartPartCollectionTypecast implements ColumnValueTypecast
{
    #[\Override]
    public static function castDatabaseValue(
        bool|int|float|string|\DateTimeInterface|null $value,
    ): MediaMultipartPartCollection {
        if (!\is_string($value)) {
            throw new \InvalidArgumentException('Список частей загрузки должен быть JSON-строкой.');
        }

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

    #[\Override]
    public static function uncastValue(
        object|null $value,
    ): string {
        if (!$value instanceof MediaMultipartPartCollection) {
            throw new \InvalidArgumentException('Значение должно быть списком частей загрузки.');
        }

        return \json_encode(
            value: $value->jsonSerialize(),
            flags: \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR | \JSON_INVALID_UTF8_SUBSTITUTE,
        );
    }
}
