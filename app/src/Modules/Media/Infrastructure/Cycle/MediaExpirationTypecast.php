<?php

declare(strict_types=1);

namespace App\Modules\Media\Infrastructure\Cycle;

use App\Modules\Media\Domain\ValueObject\MediaExpiration;
use App\Shared\Infrastructure\Cycle\ColumnValueTypecast;

final class MediaExpirationTypecast implements ColumnValueTypecast
{
    #[\Override]
    public static function castDatabaseValue(
        bool|int|float|string|\DateTimeInterface|null $value,
    ): MediaExpiration {
        if ($value === null) {
            return MediaExpiration::permanent();
        }

        if ($value instanceof \DateTimeImmutable) {
            return MediaExpiration::temporaryUntil($value);
        }

        if ($value instanceof \DateTimeInterface) {
            return MediaExpiration::temporaryUntil(\DateTimeImmutable::createFromInterface($value));
        }

        if (\is_string($value)) {
            return MediaExpiration::temporaryUntil(new \DateTimeImmutable($value));
        }

        throw new \InvalidArgumentException('Дата удаления файла имеет неверный формат.');
    }

    #[\Override]
    public static function uncastValue(
        object|null $value,
    ): ?\DateTimeImmutable {
        if ($value === null) {
            return null;
        }

        if (!$value instanceof MediaExpiration) {
            throw new \InvalidArgumentException('Значение должно быть датой удаления файла.');
        }

        return $value->value();
    }
}
