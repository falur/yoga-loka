<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Application\Exception;

use App\Modules\Outbox\Domain\ValueObject\OutboxEventId;
use App\Modules\Outbox\Public\Contract\IntegrationEvent;

final class OutboxMessageLoadingException extends \DomainException
{
    /**
     * @param class-string<IntegrationEvent> $expectedMessageClass
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
     * @param class-string<IntegrationEvent> $expectedMessageClass
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
     * @param class-string<IntegrationEvent> $expectedMessageClass
     */
    public static function restoredTypeMismatch(IntegrationEvent $outboxMessage, string $expectedMessageClass): self
    {
        return new self(\sprintf(
            'Outbox-сообщение восстановлено как `%s`, ожидался `%s`.',
            $outboxMessage::class,
            $expectedMessageClass,
        ));
    }
}
