# Domain Service

## Назначение

Domain Service — доменная операция без естественного владельца: правило касается нескольких сущностей сразу и ни одной из них не принадлежит.

## Когда применять

Применяй, когда правило нельзя честно положить ни в Entity, ни в ValueObject. Если правило работает с состоянием одной сущности, его место — метод этой сущности, а не сервис.

```php
<?php

declare(strict_types=1);

namespace App\Modules\Posts\Domain\Service;

use App\Modules\Posts\Domain\Entity\Post;
use App\Modules\Posts\Domain\Enum\PostStatus;
use App\Shared\Domain\ValueObject\UserId;

/**
 * Видимость записи зрителю: правило связывает запись, статус и зрителя,
 * поэтому не принадлежит ни записи, ни пользователю.
 */
final readonly class PostVisibilityPolicy
{
    public function isVisibleTo(Post $post, UserId $viewer): bool
    {
        if ($post->isDeleted()) {
            return false;
        }

        return match ($post->status) {
            PostStatus::Published => true,
            PostStatus::Draft => $post->authorId->equals($viewer),
            PostStatus::Blocked => false,
        };
    }
}
```

## Что повторять

- Класс `final readonly`, лежит в `Domain/Service` и называет правило, а не действие сценария: `PostVisibilityPolicy`, а не `PostService`.
- Метод принимает доменные типы и возвращает доменный тип, `bool` или новое значение.
- Зависимостей нет: ни Repository, ни Reader, ни шины, ни конфигурации, ни `Public` соседей.
- Ветвление по enum — исчерпывающий `match` без `default`: новый вариант enum сразу ломает сборку.
- Сервис ничего не сохраняет: загрузку и запись ведёт сценарий через Repository.
- Правило покрывается unit-тестом без Spiral и базы данных.

## Допустимые варианты

Сервис может принимать типизированную коллекцию и возвращать отфильтрованную коллекцию того же типа. Если правилу нужны данные из хранилища, их читает сценарий и передаёт готовыми: порт чтения внутрь домена не попадает.
