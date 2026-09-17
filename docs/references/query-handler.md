# Query handler

## Назначение

Обработчик запроса читает данные и не меняет состояние.

## Когда применять

Применяй для чтения одной записи, списка или страницы. Handler берёт Entity от Repository, когда ответу хватает полей агрегата, и Data от Reader, когда ответу нужен признак, которого в агрегате нет.

```php
<?php

declare(strict_types=1);

namespace App\Modules\User\Application\Query\GetUser;

use App\Modules\User\Domain\Exception\UserNotFoundException;
use App\Modules\User\Domain\Repository\UserRepository;
use App\Shared\Domain\ValueObject\UserId;

final readonly class GetUserHandler
{
    public function __construct(
        private UserRepository $userRepository,
    ) {}

    public function handle(GetUserQuery $query): GetUserResult
    {
        $user = $this->userRepository->findById(UserId::fromString($query->userId))
            ?? throw new UserNotFoundException();

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
- Ожидаемый отказ выражен собственным типом модуля-владельца: ключ перевода и статус лежат внутри
  типа, а не в месте броска — см. карточку [Доменное исключение](domain-exception.md).
- Результат имеет точный именованный тип `{Action}Result`; Entity и Data за пределы handler не выходят.
- Межмодульное обогащение выполняется через `Public` соседнего модуля пакетно.

## Допустимые варианты

Переиспользуемая часть ответа выносится в `Application/Result` и вкладывается в Result сценария. На межмодульной границе Result преобразует в публичный DTO провайдер из `Infrastructure/Spiral/PublicApi`, на HTTP-границе — Resource.
