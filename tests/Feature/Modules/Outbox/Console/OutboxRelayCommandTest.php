<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Outbox\Console;

use App\Modules\Outbox\Application\Contract\OutboxEventStoreContract;
use App\Modules\Outbox\Application\Contract\OutboxRelayWorkerContract;
use App\Modules\Outbox\Application\Message\OutboxDebugLogMessage;
use App\Modules\Outbox\Domain\ValueObject\OutboxRelayBatchSize;
use App\Modules\Outbox\Domain\ValueObject\OutboxRelaySleepSeconds;
use App\Modules\Outbox\Presentation\Console\OutboxRelayCommand;
use App\Modules\Outbox\Presentation\Job\OutboxDebugLogJob;
use Cycle\ORM\EntityManagerInterface;
use Tests\Feature\Modules\Outbox\CleansOutboxEvents;
use Tests\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;

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

    public function testCommandRejectsInvalidLimit(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Аргумент limit должен быть целым числом.');

        $this->runCommand(command: 'outbox:relay', args: ['limit' => 'wrong']);
    }

    public function testCommandRejectsInvalidSleepOption(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Опция sleep должна быть целым числом.');

        $this->runCommand(command: 'outbox:relay', args: [
            'limit' => '5',
            '--loop' => true,
            '--sleep' => 'wrong',
        ]);
    }

    public function testCommandAcceptsAlreadyIntegerInputValue(): void
    {
        $integerInputValue = new \ReflectionMethod(OutboxRelayCommand::class, 'integerInputValue');

        self::assertSame(
            5,
            $integerInputValue->invoke(
                $this->getContainer()->get(OutboxRelayCommand::class),
                5,
                'Не используется.',
            ),
        );
    }

    public function testCommandRejectsNonScalarArgumentValue(): void
    {
        $integerArgument = new \ReflectionMethod(OutboxRelayCommand::class, 'integerArgument');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Аргумент limit должен быть целым числом.');

        $integerArgument->invoke(
            $this->commandWithInput(new NonScalarArgumentInput()),
            'limit',
        );
    }

    public function testCommandRejectsNonScalarOptionValue(): void
    {
        $integerOption = new \ReflectionMethod(OutboxRelayCommand::class, 'integerOption');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Опция sleep должна быть целым числом.');

        $integerOption->invoke(
            $this->commandWithInput(new NonScalarOptionInput()),
            'sleep',
        );
    }

    private function commandWithInput(InputInterface $input): OutboxRelayCommand
    {
        $outboxRelayCommand = $this->getContainer()->get(OutboxRelayCommand::class);
        $inputProperty = new \ReflectionProperty(OutboxRelayCommand::class, 'input');
        $inputProperty->setValue($outboxRelayCommand, $input);

        return $outboxRelayCommand;
    }
}

final class NonScalarArgumentInput extends ArrayInput
{
    public function __construct()
    {
        parent::__construct([]);
    }

    #[\Override]
    public function getArgument(string $name): mixed
    {
        return ['bad'];
    }
}

final class NonScalarOptionInput extends ArrayInput
{
    public function __construct()
    {
        parent::__construct([]);
    }

    #[\Override]
    public function getOption(string $name): mixed
    {
        return ['bad'];
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
