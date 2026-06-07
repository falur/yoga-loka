<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Application\Message;

final class OutboxMessageSerializationException extends \DomainException
{
    public static function serializationFailed(\Throwable $exception): self
    {
        return new self(
            message: \sprintf('Не удалось сериализовать outbox-сообщение: %s', $exception->getMessage()),
            previous: $exception,
        );
    }

    public static function deserializationFailed(\Throwable $exception): self
    {
        return new self(
            message: \sprintf('Не удалось восстановить outbox-сообщение: %s', $exception->getMessage()),
            previous: $exception,
        );
    }

}
