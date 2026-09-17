# Технический порт Application

## Назначение

Технический порт — интерфейс в `Application/Contract`, которым сценарий описывает нужную ему техническую возможность: часы, хеширование, токены, файлы, почта, внешний клиент. Реализация живёт в Infrastructure и подставляется контейнером.

## Когда применять

Применяй, когда сценарию нужна техническая зависимость, а не доменное правило и не данные соседнего модуля. Для чтения своих таблиц порт называется `{Name}Reader` — см. карточку [Reader](reader.md). Для данных соседа порта не заводят: их берут через `{OtherModule}/Public`.

```php
<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Contract;

/** Часы сценария: время берётся отсюда, а не из `new \DateTimeImmutable()` внутри домена. */
interface ClockContract
{
    public function now(): \DateTimeImmutable;
}
```

```php
<?php

declare(strict_types=1);

namespace App\Modules\Posts\Infrastructure\Spiral\Clock;

use App\Modules\Posts\Application\Contract\ClockContract;

final readonly class SystemClock implements ClockContract
{
    #[\Override]
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable();
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Modules\Posts\Infrastructure\Spiral\Bootloader;

use App\Modules\Posts\Application\Contract\ClockContract;
use App\Modules\Posts\Infrastructure\Spiral\Clock\SystemClock;
use Spiral\Boot\Bootloader\Bootloader;

final class PostsBootloader extends Bootloader
{
    protected const BINDINGS = [
        ClockContract::class => SystemClock::class,
    ];
}
```

## Что повторять

- Интерфейс лежит в `Application/Contract` своего модуля и заканчивается на `Contract`.
- Сигнатура выражена доменными типами и скалярами; классов Cycle, Spiral и внешних библиотек в ней нет.
- Реализация лежит в Infrastructure, в явно названной границе своей технологии: `Spiral`, `Persistence`, `Cache`, `Client`, `Storage`.
- Связь «интерфейс — реализация» объявляет bootloader своего модуля.
- Класс, реализующий одновременно контракт Cycle и Spiral, разделяется на два адаптера.
- В тесте такой порт заменяется дублёром: `createStub()`, если факт вызова не проверяется.

## Допустимые варианты

Один порт может содержать несколько тесно связанных операций. Для пакетной работы добавляется метод, принимающий набор, а не цикл одиночных вызовов. Порт без владельца среди модулей остаётся у того модуля, чей сценарий им пользуется: `Shared` бизнес-портов не держит.
