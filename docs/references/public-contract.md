# Публичный контракт модуля

## Назначение

Публичный контракт — единственная синхронная дверь в модуль для соседних модулей. Он описывает поддерживаемую возможность и возвращает собственный публичный DTO.

## Когда применять

Применяй, когда результат другого модуля нужен в текущем сценарии или оба изменения должны войти в одну локальную транзакцию.

```php
<?php

declare(strict_types=1);

namespace App\Modules\User\Public\Contract;

use App\Modules\User\Public\Dto\UserSummaryDto;

interface UserSummaryContract
{
    public function userSummary(string $userId): UserSummaryDto;
}
```

```php
<?php

declare(strict_types=1);

namespace App\Modules\User\Public\Dto;

final readonly class UserSummaryDto
{
    public function __construct(
        public string $id,
        public string $name,
    ) {}
}
```

```php
<?php

declare(strict_types=1);

namespace App\Modules\User\Infrastructure\PublicApi;

use App\Modules\User\Application\Query\GetUser\GetUserHandler;
use App\Modules\User\Application\Query\GetUser\GetUserQuery;
use App\Modules\User\Public\Contract\UserSummaryContract;
use App\Modules\User\Public\Dto\UserSummaryDto;
use GianTiaga\SpiralCqrs\QueryBusInterface;

final readonly class UserSummaryProvider implements UserSummaryContract
{
    public function __construct(
        private GetUserHandler $getUserHandler,
        private QueryBusInterface $queryBus,
    ) {}

    #[\Override]
    public function userSummary(string $userId): UserSummaryDto
    {
        $result = $this->queryBus->dispatch(
            query: new GetUserQuery(userId: $userId),
            handler: $this->getUserHandler->handle(...),
        );

        return new UserSummaryDto(
            id: $result->id,
            name: $result->name,
        );
    }
}
```

## Что повторять

- Интерфейс находится в `Public/Contract`.
- Интерфейс называется по возможности модуля и заканчивается на `Contract`.
- Реализация заканчивается на `Provider` и находится в `Infrastructure/PublicApi`.
- Сигнатура использует только скаляры и типы из `Public`.
- DTO из `Public` не импортирует Domain или Application; преобразование выполняет Provider.
- Доменная сущность, Repository, Handler и HTTP Resource наружу не выходят.
- Реализация скрыта внутри модуля и подставляется контейнером.

## Допустимые варианты

Один контракт может содержать несколько тесно связанных операций. Для пакетного чтения добавляется один метод, принимающий список идентификаторов; цикл одиночных вызовов с N+1 не является вариантом.
