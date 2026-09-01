# API Resource

## Назначение

Resource задаёт форму JSON-ответа и OpenAPI-схемы.

## Когда применять

Применяй для преобразования Result, View или разрешённой простой сущности в публичный HTTP-ответ.

```php
<?php

declare(strict_types=1);

namespace App\Modules\User\Presentation\Http\Resource;

use App\Modules\User\Application\Query\GetUser\GetUserResult;
use App\Shared\Presentation\Http\Resource\AbstractResource;

final readonly class UserResource extends AbstractResource
{
    public function __construct(
        public string $id,
        public string $name,
    ) {}

    public static function fromResult(GetUserResult $result): self
    {
        return new self(
            id: $result->id,
            name: $result->name,
        );
    }
}
```

## Что повторять

- Класс `final readonly` наследует общий `AbstractResource`.
- Поля точно отражают JSON-контракт.
- Фабрика только преобразует данные и не делает запросов.
- `jsonSerialize()` вручную не объявляется.

## Допустимые варианты

Resource может строиться из View или Entity, если обогащение не требуется. Дата остаётся `DateTimeImmutable` до общей сериализации.
