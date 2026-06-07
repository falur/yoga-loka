<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Outbox\Presentation\Job;

use App\Modules\Outbox\Application\Command\ProcessDebugLogMessage\ProcessOutboxDebugLogMessageHandler;
use App\Modules\Outbox\Application\Contract\OutboxEventStoreContract;
use App\Modules\Outbox\Application\Contract\OutboxMessageLoaderContract;
use App\Modules\Outbox\Application\Message\OutboxDebugLogMessage;
use App\Modules\Outbox\Application\Message\OutboxQueueEnvelope;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventId;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventType;
use App\Modules\Outbox\Presentation\Job\OutboxDebugLogJob;
use Cycle\ORM\EntityManagerInterface;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Tests\Feature\Modules\Outbox\CleansOutboxEvents;
use Tests\TestCase;
use Tools\Cqrs\CommandBusInterface;

final class OutboxDebugLogJobTest extends TestCase
{
    use CleansOutboxEvents;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanOutboxEvents();
    }

    public function testJobDelegatesDebugMessageToApplicationHandler(): void
    {
        $outboxEventId = $this->addOutboxMessage(new OutboxDebugLogMessage(
            text: 'job log check',
            createdAt: new \DateTimeImmutable('2026-05-25T16:06:00+00:00'),
        ));
        $payload = new OutboxQueueEnvelope(
            outboxEventId: $outboxEventId,
            outboxEventType: OutboxEventType::fromString(OutboxDebugLogMessage::class),
        );
        $logger = new RecordingLogger();

        $this->getContainer()->get(OutboxDebugLogJob::class)->invoke(
            payload: $payload,
            id: 'job-id',
            outboxMessageLoader: $this->getContainer()->get(OutboxMessageLoaderContract::class),
            commandBus: $this->getContainer()->get(CommandBusInterface::class),
            processOutboxDebugLogMessageHandler: new ProcessOutboxDebugLogMessageHandler($logger),
        );

        self::assertTrue($logger->hasDebugContextValue('jobId', 'job-id'));
        self::assertTrue($logger->hasDebugContextValue('debugText', 'job log check'));
        self::assertTrue($logger->hasDebugContextValue('createdAt', '2026-05-25T16:06:00+00:00'));
    }

    private function addOutboxMessage(OutboxDebugLogMessage $outboxMessage): OutboxEventId
    {
        $storedOutboxEventId = $this->getContainer()->get(OutboxEventStoreContract::class)->add($outboxMessage);
        $this->entityManager()->run();

        return OutboxEventId::fromString($storedOutboxEventId->value());
    }

    private function entityManager(): EntityManagerInterface
    {
        return $this->getContainer()->get(EntityManagerInterface::class);
    }
}

final class RecordingLogger extends AbstractLogger implements LoggerInterface
{
    /**
     * @var list<array<string, mixed>>
     */
    private array $records = [];

    /**
     * @param array<string, mixed> $context
     */
    #[\Override]
    public function log($level, \Stringable|string $message, array $context = []): void
    {
        $this->records[] = [
            'level' => (string) $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }

    public function hasDebugContextValue(string $key, string $value): bool
    {
        foreach ($this->records as $record) {
            if ($record['level'] !== 'debug' || !\is_array($record['context'])) {
                continue;
            }

            if (($record['context'][$key] ?? null) === $value) {
                return true;
            }
        }

        return false;
    }
}
