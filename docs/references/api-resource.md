# API Resource

## Назначение

Resource задаёт форму JSON-ответа и OpenAPI-схемы.

## Когда применять

Применяй для преобразования Application Result в публичный HTTP-ответ.

```php
<?php

declare(strict_types=1);

namespace App\Modules\User\Infrastructure\Spiral\Http\Resource;

use App\Modules\User\Application\Query\GetUser\GetUserResult;
use App\Shared\Infrastructure\Spiral\Http\Resource\AbstractResource;

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

- Класс `final readonly` наследует общий `AbstractResource` и лежит в `Infrastructure/Spiral/Http/Resource`.
- Поля точно отражают JSON-контракт.
- Фабрика только преобразует данные и не делает запросов.
- `jsonSerialize()` вручную не объявляется.

## Допустимые варианты

Resource строится только из Result своего сценария: доменная Entity и Cycle Entity до HTTP не доходят, иначе в JSON попадёт любое поле, добавленное в агрегат позже. Переиспользуемую часть ответа Resource берёт из соответствующего класса `Application/Result`. Дата остаётся `DateTimeImmutable` до общей сериализации.
