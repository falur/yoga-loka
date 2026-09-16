<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Outbox\Infrastructure\Fixture;

use App\Modules\Outbox\Public\Contract\IntegrationEventStoreContract;
use App\Modules\Outbox\Public\Contract\IntegrationEventRoutingContract;
use App\Modules\Outbox\Public\Contract\IntegrationEvent;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventId;
use App\Modules\Outbox\Domain\Repository\StoredOutboxEventRepository;
use App\Shared\Infrastructure\Spiral\Configuration\Outbox\OutboxConfig;
use Cycle\Database\DatabaseInterface;
use Cycle\ORM\EntityManagerInterface;
use Spiral\Queue\Config\QueueConfig;
use Spiral\Queue\HandlerInterface;
use Spiral\Queue\QueueRegistry;

/**
 * @mixin \Tests\TestCase
 */
trait OutboxRelayTestHelpers
{
    private function entityManager(): EntityManagerInterface
    {
        return $this->getContainer()->get(EntityManagerInterface::class);
    }

    private function database(): DatabaseInterface
    {
        return $this->getContainer()->get(DatabaseInterface::class);
    }

    private function storedOutboxEventRepository(): StoredOutboxEventRepository
    {
        return $this->getContainer()->get(StoredOutboxEventRepository::class);
    }

    private function outboxEventStore(): IntegrationEventStoreContract
    {
        return $this->getContainer()->get(IntegrationEventStoreContract::class);
    }

    private function useQueueConnection(string $connection): void
    {
        $queueConfig = $this->getContainer()->get(QueueConfig::class)->toArray();

        $this->getContainer()->removeBinding(QueueConfig::class);
        $this->getContainer()->bindSingleton(
            QueueConfig::class,
            new QueueConfig(['default' => $connection] + $queueConfig),
        );
    }

    private function addOutboxMessage(IntegrationEvent $outboxMessage): OutboxEventId
    {
        return OutboxEventId::fromString($this->outboxEventStore()->add($outboxMessage));
    }

    private function outboxConfigWithMaxAttempts(int $maxAttempts): OutboxConfig
    {
        return new OutboxConfig(
            maxAttempts: $maxAttempts,
            maxConsecutiveRelayFailures: 10,
            baseRelayRetryDelaySeconds: 1,
            maxRelayRetryDelaySeconds: 30,
            claimTimeoutSeconds: 60,
            publishRetryDelaySeconds: 60,
        );
    }

    /**
     * @param class-string<IntegrationEvent> $integrationEventClass
     * @param class-string<HandlerInterface> $jobClass
     */
    private function registerOutboxJob(string $integrationEventClass, string $jobClass): void
    {
        $this->getContainer()->get(IntegrationEventRoutingContract::class)->register(
            integrationEventClass: $integrationEventClass,
            jobClass: $jobClass,
        );
        $this->getContainer()->get(QueueRegistry::class)->setHandler(
            jobType: $jobClass,
            handler: $jobClass,
        );
    }

    private function outboxStatusInDatabase(OutboxEventId $outboxEventId): string
    {
        $row = $this->database()
            ->select('status')
            ->from('outbox_events')
            ->where('id', $outboxEventId->value())
            ->limit(1)
            ->fetchAll()[0] ?? null;

        if (!\is_array($row) || !\is_string($row['status'] ?? null)) {
            throw new \UnexpectedValueException('Тестовый запрос outbox-события вернул строку без статуса.');
        }

        return $row['status'];
    }

    private function outboxLastErrorIsFilledInDatabase(OutboxEventId $outboxEventId): bool
    {
        $row = $this->database()
            ->select('last_error')
            ->from('outbox_events')
            ->where('id', $outboxEventId->value())
            ->limit(1)
            ->fetchAll()[0] ?? null;

        if (!\is_array($row) || !\array_key_exists(key: 'last_error', array: $row)) {
            throw new \UnexpectedValueException('Тестовый запрос outbox-события вернул строку без last_error.');
        }

        return $row['last_error'] !== null && $row['last_error'] !== '';
    }

    private function outboxQueuedAtIsFilledInDatabase(OutboxEventId $outboxEventId): bool
    {
        $row = $this->database()
            ->select('queued_at')
            ->from('outbox_events')
            ->where('id', $outboxEventId->value())
            ->limit(1)
            ->fetchAll()[0] ?? null;

        if (!\is_array($row) || !\array_key_exists(key: 'queued_at', array: $row)) {
            throw new \UnexpectedValueException('Тестовый запрос outbox-события вернул строку без queued_at.');
        }

        return $row['queued_at'] !== null;
    }
}
