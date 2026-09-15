<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Infrastructure\Persistence\Cycle\Typecast;

use App\Modules\Notifications\Domain\ValueObject\NotificationActor;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Persistence\Cycle\ColumnValueTypecast;

/**
 * Гидрация nullable json-колонки actor в null-object NotificationActor: NULL -> none(),
 * JSON-объект {id, name, avatarMediaId} -> of(UserId, name, avatarMediaId). avatarMediaId в JSON может
 * быть null (у автора нет аватара). Снимок автора хранит id медиа-аватара, а не готовую ссылку: полный
 * MediaView собирается на чтении через модуль Media. Образец — MediaMultipartPartCollectionTypecast.
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
        $avatarMediaId = $payload['avatarMediaId'] ?? null;

        // avatarMediaId опционален: допускаем null (у автора нет аватара), но не другой тип.
        if (!\is_string($id) || !\is_string($name) || ($avatarMediaId !== null && !\is_string($avatarMediaId))) {
            throw new \InvalidArgumentException('Снимок автора уведомления имеет неверный формат.');
        }

        return NotificationActor::of(userId: UserId::fromString($id), name: $name, avatarMediaId: $avatarMediaId);
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
