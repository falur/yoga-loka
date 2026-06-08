<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Presentation\Console;

use App\Modules\Outbox\Application\Command\RelayOutbox\RelayOutboxCommand;
use App\Modules\Outbox\Application\Command\RelayOutbox\RelayOutboxHandler;
use GianTiaga\SpiralCqrs\CommandBusInterface;
use Spiral\Console\Attribute\Argument;
use Spiral\Console\Attribute\AsCommand;
use Spiral\Console\Attribute\Option;
use Spiral\Console\Command;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

#[AsCommand(
    name: 'outbox:relay',
    description: 'Переложить pending outbox-события в очередь',
)]
final class OutboxRelayCommand extends Command
{
    #[Argument(description: 'Размер одной пачки')]
    public int $limit = 100;

    #[Option(description: 'Запускать постоянно')]
    public bool $loop = false;

    #[Option(name: 'sleep', description: 'Пауза между пустыми пачками')]
    public int $sleepSeconds = 1;

    public function perform(
        CommandBusInterface $commandBus,
        RelayOutboxHandler $relayOutboxHandler,
    ): int {
        // Команда — тонкая обёртка: Spiral приводит CLI-ввод к типам свойств,
        // а диапазон значений валидируют доменные VO внутри RelayOutboxHandler.
        $publishedCount = $commandBus->dispatch(
            command: new RelayOutboxCommand(
                batchSize: $this->limit,
                loop: $this->loop,
                sleepSeconds: $this->sleepSeconds,
            ),
            handler: $relayOutboxHandler->handle(...),
        );

        $this->info(\sprintf('Outbox relay обработал событий: %d', $publishedCount));

        return SymfonyCommand::SUCCESS;
    }
}
