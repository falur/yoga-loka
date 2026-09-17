# Консольная команда

## Назначение

Консольная команда — входной адаптер CLI: она приводит ввод к типам, создаёт один Command или Query своего модуля и печатает итог.

## Когда применять

Применяй для регулярной или служебной операции, запускаемой из консоли: relay, пересчёт, выгрузка, разовое обслуживание своих данных.

```php
<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Infrastructure\Spiral\Console;

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
        // Команда — тонкая обёртка: Spiral приводит ввод к типам свойств,
        // а диапазон значений проверяют доменные типы внутри сценария.
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
```

## Что повторять

- Класс называется `{Action}Command`, наследует `Spiral\Console\Command` и лежит в `Infrastructure/Spiral/Console` своего модуля.
- Имя и описание задаёт `#[AsCommand]`; имя имеет вид `{область}:{действие}`, описание — на русском языке.
- Аргументы и опции объявлены типизированными свойствами с `#[Argument]` и `#[Option]`, а не разбором строки.
- `perform()` создаёт один Command или Query и вызывает шину; бизнес-правил, запросов к БД и `try-catch` в нём нет.
- Результат печатается методами вывода команды, возвращается код завершения Symfony.
- Команда регистрируется bootloader-ом своего модуля.

## Допустимые варианты

Долгий режим (`--loop`) остаётся флагом сценария, а не вторым классом команды. Команда, у которой нет разумного значения по умолчанию, объявляет свойство без значения и получает обязательный аргумент.
