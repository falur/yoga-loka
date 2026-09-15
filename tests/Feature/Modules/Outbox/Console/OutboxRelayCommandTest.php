<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Outbox\Console;

use App\Modules\Outbox\Application\Contract\OutboxEventStoreContract;
use App\Modules\Outbox\Application\Contract\OutboxRelayWorkerContract;
use App\Modules\Outbox\Application\Message\OutboxDebugLogMessage;
use App\Modules\Outbox\Domain\ValueObject\OutboxRelayBatchSize;
use App\Modules\Outbox\Domain\ValueObject\OutboxRelaySleepSeconds;
use App\Modules\Outbox\Infrastructure\Spiral\Job\OutboxDebugLogJob;
use App\Shared\Domain\Exception\InvalidDomainValueException;
use Cycle\ORM\EntityManagerInterface;
use Tests\Feature\Modules\Outbox\CleansOutboxEvents;
use Tests\TestCase;

final class OutboxRelayCommandTest extends TestCase
{
    use CleansOutboxEvents;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->cleanOutboxEvents();
    }

    public function testCommandRunsRelayWithGivenLimit(): void
    {
        $fakeQueue = $this->fakeQueue()->getConnection();

        $this->getContainer()->get(OutboxEventStoreContract::class)->add(
            new OutboxDebugLogMessage(
                text: 'command relay check',
                createdAt: new \DateTimeImmutable('2026-05-25 16:07:00'),
            ),
        );
        $this->getContainer()->get(EntityManagerInterface::class)->run();

        $output = $this->runCommand(command: 'outbox:relay', args: ['limit' => '5']);

        self::assertStringContainsString('Outbox relay обработал событий: 1', $output);
        $fakeQueue->assertPushed(OutboxDebugLogJob::class);
    }

    public function testCommandDelegatesRelayRunToApplicationScenario(): void
    {
        $outboxRelayWorker = new RecordingOutboxRelayWorker();
        $this->getContainer()->removeBinding(OutboxRelayWorkerContract::class);
        $this->getContainer()->bindSingleton(OutboxRelayWorkerContract::class, $outboxRelayWorker);

        $output = $this->runCommand(command: 'outbox:relay', args: ['limit' => '7']);

        self::assertStringContainsString('Outbox relay обработал событий: 3', $output);
        self::assertNotNull($outboxRelayWorker->runOnceBatchSize);
        self::assertSame(7, $outboxRelayWorker->runOnceBatchSize->value());
    }

    public function testCommandDelegatesLoopOptionsToApplicationScenario(): void
    {
        $outboxRelayWorker = new RecordingOutboxRelayWorker();
        $this->getContainer()->removeBinding(OutboxRelayWorkerContract::class);
        $this->getContainer()->bindSingleton(OutboxRelayWorkerContract::class, $outboxRelayWorker);

        $this->expectException(LoopModeStartedException::class);

        try {
            $this->runCommand(command: 'outbox:relay', args: [
                'limit' => '9',
                '--loop' => true,
                '--sleep' => '4',
            ]);
        } finally {
            self::assertNotNull($outboxRelayWorker->runLoopBatchSize);
            self::assertSame(9, $outboxRelayWorker->runLoopBatchSize->value());
            self::assertNotNull($outboxRelayWorker->runLoopSleepSeconds);
            self::assertSame(4, $outboxRelayWorker->runLoopSleepSeconds->value());
        }
    }

    public function testCommandUsesDefaultLimitWhenArgumentOmitted(): void
    {
        $outboxRelayWorker = new RecordingOutboxRelayWorker();
        $this->getContainer()->removeBinding(OutboxRelayWorkerContract::class);
        $this->getContainer()->bindSingleton(OutboxRelayWorkerContract::class, $outboxRelayWorker);

        $this->runCommand(command: 'outbox:relay');

        self::assertNotNull($outboxRelayWorker->runOnceBatchSize);
        self::assertSame(100, $outboxRelayWorker->runOnceBatchSize->value());
    }

    public function testCommandRejectsOutOfRangeLimit(): void
    {
        $this->expectException(InvalidDomainValueException::class);
        $this->expectExceptionMessage('Размер пачки outbox relay должно быть от 1 до 1000.');

        $this->runCommand(command: 'outbox:relay', args: ['limit' => 'wrong']);
    }

    public function testCommandRejectsOutOfRangeSleepOption(): void
    {
        $this->expectException(InvalidDomainValueException::class);
        $this->expectExceptionMessage('Пауза outbox relay в секундах должно быть от 1 до 3600.');

        $this->runCommand(command: 'outbox:relay', args: [
            'limit' => '5',
            '--loop' => true,
            '--sleep' => 'wrong',
        ]);
    }
}

final class RecordingOutboxRelayWorker implements OutboxRelayWorkerContract
{
    public OutboxRelayBatchSize|null $runOnceBatchSize = null;
    public OutboxRelayBatchSize|null $runLoopBatchSize = null;
    public OutboxRelaySleepSeconds|null $runLoopSleepSeconds = null;

    #[\Override]
    public function runOnce(OutboxRelayBatchSize $outboxRelayBatchSize): int
    {
        $this->runOnceBatchSize = $outboxRelayBatchSize;

        return 3;
    }

    #[\Override]
    public function runLoop(OutboxRelayBatchSize $outboxRelayBatchSize, OutboxRelaySleepSeconds $outboxRelaySleepSeconds): never
    {
        $this->runLoopBatchSize = $outboxRelayBatchSize;
        $this->runLoopSleepSeconds = $outboxRelaySleepSeconds;

        throw new LoopModeStartedException();
    }
}

final class LoopModeStartedException extends \RuntimeException {}
