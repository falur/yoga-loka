# Консольная команда

## Назначение

Консольная команда — входной адаптер CLI: она приводит ввод к типам, создаёт один Command или Query своего модуля и печатает итог.

## Когда применять

Применяй для регулярной или служебной операции, запускаемой из консоли: пересчёт, выгрузка, очистка, разовое обслуживание своих данных. Команды самого обмена (`outbox:relay` и `outbox:status`) даёт пакет `gian-tiaga/spiral-outbox`, и своих команд обмена проект не пишет.

```php
<?php

declare(strict_types=1);

namespace App\Modules\Media\Infrastructure\Spiral\Console;

use App\Modules\Media\Application\Command\PurgeExpiredUploads\PurgeExpiredUploadsCommand;
use App\Modules\Media\Application\Command\PurgeExpiredUploads\PurgeExpiredUploadsHandler;
use GianTiaga\SpiralCqrs\CommandBusInterface;
use Spiral\Console\Attribute\Argument;
use Spiral\Console\Attribute\AsCommand;
use Spiral\Console\Attribute\Option;
use Spiral\Console\Command;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

#[AsCommand(
    name: 'media:purge-expired',
    description: 'Удалить просроченные незавершённые загрузки',
)]
final class PurgeExpiredUploadsCommand extends Command
{
    #[Argument(description: 'Размер одной пачки')]
    public int $limit = 100;

    #[Option(name: 'dry-run', description: 'Только показать, ничего не удалять')]
    public bool $dryRun = false;

    public function perform(
        CommandBusInterface $commandBus,
        PurgeExpiredUploadsHandler $purgeExpiredUploadsHandler,
    ): int {
        // Команда — тонкая обёртка: Spiral приводит ввод к типам свойств,
        // а диапазон значений проверяют доменные типы внутри сценария.
        $purgedCount = $commandBus->dispatch(
            command: new PurgeExpiredUploadsCommand(
                batchSize: $this->limit,
                dryRun: $this->dryRun,
            ),
            handler: $purgeExpiredUploadsHandler->handle(...),
        );

        $this->info(\sprintf('Удалено просроченных загрузок: %d', $purgedCount));

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

Долгий режим остаётся флагом сценария, а не вторым классом команды. Команда, у которой нет разумного значения по умолчанию, объявляет свойство без значения и получает обязательный аргумент.
