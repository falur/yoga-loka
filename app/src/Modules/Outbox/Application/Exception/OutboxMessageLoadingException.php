<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Application\Exception;

use App\Modules\Outbox\Application\Message\OutboxMessage;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventId;

final class OutboxMessageLoadingException extends \DomainException
{
    /**
     * @param class-string<OutboxMessage> $expectedMessageClass
     */
    public static function eventNotFound(OutboxEventId $outboxEventId, string $expectedMessageClass): self
    {
        return new self(\sprintf(
            'Outbox-событие `%s` для сообщения `%s` не найдено.',
            $outboxEventId->value(),
            $expectedMessageClass,
        ));
    }

    /**
     * @param class-string<OutboxMessage> $expectedMessageClass
     */
    public static function storedTypeMismatch(string $storedMessageClass, string $expectedMessageClass): self
    {
        return new self(\sprintf(
            'Outbox-событие содержит тип `%s`, ожидался `%s`.',
            $storedMessageClass,
            $expectedMessageClass,
        ));
    }

    /**
     * @param class-string<OutboxMessage> $expectedMessageClass
     */
    public static function restoredTypeMismatch(OutboxMessage $outboxMessage, string $expectedMessageClass): self
    {
        return new self(\sprintf(
            'Outbox-сообщение восстановлено как `%s`, ожидался `%s`.',
            $outboxMessage::class,
            $expectedMessageClass,
        ));
    }
}
