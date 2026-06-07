<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Infrastructure;

final class OutboxJobRegistryException extends \DomainException
{
    public static function invalidMessageClass(string $outboxMessageClass): self
    {
        return new self(\sprintf('Класс %s не является outbox-сообщением.', $outboxMessageClass));
    }

    public static function invalidJobClass(string $outboxJobClass): self
    {
        return new self(\sprintf('Класс %s не является обработчиком задачи очереди.', $outboxJobClass));
    }

    public static function jobNotRegistered(string $outboxMessageClass): self
    {
        return new self(\sprintf('Для outbox-сообщения %s не зарегистрирован Job.', $outboxMessageClass));
    }
}
