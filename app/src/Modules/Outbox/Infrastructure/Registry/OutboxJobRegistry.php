<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Infrastructure\Registry;

use App\Modules\Outbox\Application\Contract\OutboxJobRegistryContract;
use App\Modules\Outbox\Application\Message\OutboxMessage;
use Spiral\Queue\HandlerInterface;

final class OutboxJobRegistry implements OutboxJobRegistryContract
{
    /**
     * @var array<class-string<OutboxMessage>, class-string<HandlerInterface>>
     */
    private array $jobsByMessageClass = [];

    /**
     * @param class-string $outboxMessageClass
     * @param class-string $outboxJobClass
     */
    #[\Override]
    public function register(string $outboxMessageClass, string $outboxJobClass): void
    {
        if (!\is_subclass_of(object_or_class: $outboxMessageClass, class: OutboxMessage::class)) {
            throw OutboxJobRegistryException::invalidMessageClass($outboxMessageClass);
        }

        if (!\is_subclass_of(object_or_class: $outboxJobClass, class: HandlerInterface::class)) {
            throw OutboxJobRegistryException::invalidJobClass($outboxJobClass);
        }

        $this->jobsByMessageClass[$outboxMessageClass] = $outboxJobClass;
    }

    /**
     * @return class-string<HandlerInterface>
     */
    #[\Override]
    public function jobFor(OutboxMessage $outboxMessage): string
    {
        $outboxMessageClass = $outboxMessage::class;

        return $this->jobsByMessageClass[$outboxMessageClass]
            ?? throw OutboxJobRegistryException::jobNotRegistered($outboxMessageClass);
    }
}
