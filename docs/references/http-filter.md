# HTTP Filter

## Назначение

Filter читает HTTP-вход, задаёт точные типы и возвращает клиенту ожидаемые ошибки проверки.

## Когда применять

Применяй для тела, query-параметров и request attributes маршрута.

```php
<?php

declare(strict_types=1);

namespace App\Modules\User\Presentation\Http\Filter;

use Spiral\Filters\Attribute\Input\Attribute;
use Spiral\Filters\Attribute\Input\Post;
use Spiral\Validation\Symfony\AttributesFilter;
use Symfony\Component\Validator\Constraints as Assert;

final class RenameUserFilter extends AttributesFilter
{
    #[Attribute(key: 'authUserId')]
    #[Assert\NotBlank]
    #[Assert\Uuid]
    public string $authUserId;

    #[Post]
    #[Assert\NotBlank]
    #[Assert\Length(max: 30)]
    public string $name;
}
```

## Что повторять

- Обязательное поле не имеет значения по умолчанию и не nullable.
- Имя `Post`-поля совпадает с JSON-ключом, поэтому `key` не указан.
- Request attribute имеет явный ключ.
- Проверка формата происходит до создания доменного типа.

## Допустимые варианты

Для опционального поля допустим nullable или явное значение по умолчанию. Вложенный объект выражается отдельным Filter или DTO, а не ассоциативным массивом.
