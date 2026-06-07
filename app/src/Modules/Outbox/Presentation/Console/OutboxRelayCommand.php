<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Presentation\Console;

use App\Modules\Outbox\Application\Command\RelayOutbox\RelayOutboxCommand;
use App\Modules\Outbox\Application\Command\RelayOutbox\RelayOutboxHandler;
use GianTiaga\SpiralCqrs\CommandBusInterface;
use Spiral\Console\Command;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

final class OutboxRelayCommand extends Command
{
    protected const SIGNATURE = <<<'CMD'
        outbox:relay
            {limit=100 : Размер одной пачки}
            {--loop : Запускать постоянно}
            {--sleep=1 : Пауза между пустыми пачками}
        CMD;

    protected const DESCRIPTION = 'Переложить pending outbox-события в очередь';

    public function perform(CommandBusInterface $commandBus, RelayOutboxHandler $relayOutboxHandler): int
    {
        // Команда только сужает mixed-ввод CLI до int (граница системы). Диапазон
        // значений валидируют доменные VO в RelayOutboxHandler.
        $publishedCount = $commandBus->dispatch(
            command: new RelayOutboxCommand(
                batchSize: $this->integerArgument('limit'),
                loop: (bool) $this->option('loop'),
                sleepSeconds: $this->integerOption('sleep'),
            ),
            handler: $relayOutboxHandler->handle(...),
        );

        $this->info(\sprintf('Outbox relay обработал событий: %d', $publishedCount));

        return SymfonyCommand::SUCCESS;
    }

    private function integerArgument(string $name): int
    {
        $value = $this->argument($name);
        $errorMessage = \sprintf('Аргумент %s должен быть целым числом.', $name);

        if (!\is_int($value) && !\is_string($value)) {
            throw new \InvalidArgumentException($errorMessage);
        }

        return $this->integerInputValue(
            value: $value,
            errorMessage: $errorMessage,
        );
    }

    private function integerOption(string $name): int
    {
        $value = $this->option($name);
        $errorMessage = \sprintf('Опция %s должна быть целым числом.', $name);

        if (!\is_int($value) && !\is_string($value)) {
            throw new \InvalidArgumentException($errorMessage);
        }

        return $this->integerInputValue(
            value: $value,
            errorMessage: $errorMessage,
        );
    }

    private function integerInputValue(int|string $value, string $errorMessage): int
    {
        if (\is_int($value)) {
            return $value;
        }

        if (\preg_match(pattern: '/^\d+$/', subject: $value) === 1) {
            return (int) $value;
        }

        throw new \InvalidArgumentException($errorMessage);
    }
}
