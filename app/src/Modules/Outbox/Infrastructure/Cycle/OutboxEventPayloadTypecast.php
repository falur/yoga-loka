<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Infrastructure\Cycle;

use App\Modules\Outbox\Domain\ValueObject\OutboxEventPayload;
use App\Shared\Infrastructure\Cycle\ColumnValueTypecast;

final class OutboxEventPayloadTypecast implements ColumnValueTypecast
{
    public static function castDatabaseValue(
        string $value,
    ): OutboxEventPayload {
        return OutboxEventPayload::fromJson($value);
    }

    public static function uncastValue(
        OutboxEventPayload $value,
    ): string {
        return $value->value();
    }
}
