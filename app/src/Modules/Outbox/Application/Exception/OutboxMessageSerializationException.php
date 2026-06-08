<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Application\Exception;

final class OutboxMessageSerializationException extends \DomainException
{
    public static function unsupportedMessageType(string $type): self
    {
        return new self(\sprintf('Тип `%s` не реализует интерфейс `OutboxMessage`.', $type));
    }
}
