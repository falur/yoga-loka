# Bootloader модуля

## Назначение

Bootloader связывает интерфейсы модуля с инфраструктурными реализациями и самостоятельно подключает модуль к Spiral.

## Когда применять

Применяй для регистрации Repository, технических портов, публичных контрактов, конфигурации, миграций, переводов и адаптеров модуля.

```php
<?php

declare(strict_types=1);

namespace App\Modules\User\Infrastructure\Bootloader;

use App\Modules\User\Domain\Repository\UserRepository;
use App\Modules\User\Infrastructure\Persistence\Cycle\Repository\CycleUserRepository;
use App\Modules\User\Infrastructure\PublicApi\UserSummaryProvider;
use App\Modules\User\Public\Contract\UserSummaryContract;
use Spiral\Boot\Bootloader\Bootloader;

final class UserBootloader extends Bootloader
{
    protected const BINDINGS = [
        UserRepository::class => CycleUserRepository::class,
        UserSummaryContract::class => UserSummaryProvider::class,
    ];
}
```

## Что повторять

- Интерфейс указывает на реализацию своего модуля.
- Публичный интерфейс связан с адаптером из `Infrastructure/PublicApi`.
- Пути к конфигурации, миграциям и переводам берутся из папки этого модуля.
- Соседние модули не регистрируются и не раскрываются напрямую.
- Bootloader не содержит бизнес-логику.

## Допустимые варианты

Для создания объекта из typed config используется фабричный метод bootloader-а. Stateful-зависимость не регистрируется синглтоном.
