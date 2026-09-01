# Query handler

## Назначение

Обработчик запроса читает данные и не меняет состояние.

## Когда применять

Применяй для получения сущности, списка, Result или View.

```php
<?php

declare(strict_types=1);

namespace App\Modules\User\Application\Query\GetUser;

use App\Modules\User\Domain\Repository\UserRepository;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\ValueObject\UserId;

final readonly class GetUserHandler
{
    public function __construct(
        private UserRepository $userRepository,
    ) {}

    public function handle(GetUserQuery $query): GetUserResult
    {
        $user = $this->userRepository->findById(UserId::fromString($query->userId))
            ?? throw new NotFoundException('app.user.not_found');

        return new GetUserResult(
            id: $user->id->value(),
            name: $user->name->value(),
        );
    }
}
```

## Что повторять

- На `handle()` нет транзакции записи.
- Handler не меняет сущность и не сохраняет её.
- Результат имеет точный именованный тип.
- Межмодульное обогащение выполняется через `Public` соседнего модуля пакетно.

## Допустимые варианты

Для простой внутренней выборки Query может вернуть доменную сущность. Для HTTP и межмодульной границы предпочтителен Result, View или публичный DTO соответствующей границы.
