<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Infrastructure\Cycle;

use App\Modules\Notifications\Domain\ValueObject\NotificationActor;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Cycle\ColumnValueTypecast;

/**
 * Гидрация nullable json-колонки actor в null-object NotificationActor: NULL -> none(),
 * JSON-объект {id, name, avatarUrl} -> of(UserId, name, avatarUrl). Снимок автора хранится целиком,
 * чтобы клиент показал аватар без запроса к профилю. Образец — MediaMultipartPartCollectionTypecast.
 */
final class NotificationActorTypecast implements ColumnValueTypecast
{
    public static function castDatabaseValue(
        string|null $value,
    ): NotificationActor {
        if ($value === null) {
            return NotificationActor::none();
        }

        $payload = \json_decode(
            json: $value,
            associative: true,
            flags: \JSON_THROW_ON_ERROR | \JSON_INVALID_UTF8_SUBSTITUTE,
        );

        if (!\is_array($payload)) {
            throw new \InvalidArgumentException('Снимок автора уведомления должен быть JSON-объектом.');
        }

        $id = $payload['id'] ?? null;
        $name = $payload['name'] ?? null;
        $avatarUrl = $payload['avatarUrl'] ?? null;

        if (!\is_string($id) || !\is_string($name) || !\is_string($avatarUrl)) {
            throw new \InvalidArgumentException('Снимок автора уведомления имеет неверный формат.');
        }

        return NotificationActor::of(userId: UserId::fromString($id), name: $name, avatarUrl: $avatarUrl);
    }

    public static function uncastValue(
        NotificationActor|null $value,
    ): string|null {
        if ($value === null || !$value->isPresent()) {
            return null;
        }

        return \json_encode(
            value: $value->jsonSerialize(),
            flags: \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR | \JSON_INVALID_UTF8_SUBSTITUTE,
        );
    }
}
