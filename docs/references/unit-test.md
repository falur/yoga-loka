# Unit-тест доменной логики

## Назначение

Unit-тест проверяет чистое правило домена или сценария без Spiral, базы данных и сети. Такой набор запускается быстро и первым показывает сломанное правило.

## Когда применять

Применяй для каждого доменного правила: инвариант ValueObject, переход состояния Entity, решение `Domain/Service`, ветвление handler-а на дублёрах. Для маршрута нужен интеграционный тест — см. карточку [Интеграционный тест](integration-test.md).

```php
<?php

declare(strict_types=1);

namespace App\Modules\Posts\Tests\Unit\Domain;

use App\Modules\Posts\Domain\Entity\Post;
use App\Modules\Posts\Domain\Enum\PostStatus;
use App\Modules\Posts\Domain\Exception\PostAlreadyPublishedException;
use App\Modules\Posts\Domain\ValueObject\PostId;
use App\Shared\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;

final class PostPublishTest extends TestCase
{
    public function testPublishMakesDraftPublished(): void
    {
        $post = $this->draft();

        $post->publish(new \DateTimeImmutable('2026-09-15 12:00:00'));

        self::assertSame(PostStatus::Published, $post->status);
    }

    public function testPublishTwiceIsRejected(): void
    {
        $post = $this->draft();
        $post->publish(new \DateTimeImmutable('2026-09-15 12:00:00'));

        $this->expectException(PostAlreadyPublishedException::class);

        $post->publish(new \DateTimeImmutable('2026-09-15 12:00:01'));
    }

    public function testPublishKeepsFirstPublicationTime(): void
    {
        $publishedAt = new \DateTimeImmutable('2026-09-15 12:00:00');
        $post = $this->draft();

        $post->publish($publishedAt);

        self::assertEquals($publishedAt, $post->publishedAt);
    }

    private function draft(): Post
    {
        return Post::draft(
            id: PostId::fromString('01996e0f-6c4a-7a6f-9f0e-2f5f9a3c1d20'),
            authorId: UserId::fromString('01996e0f-6c4a-7a6f-9f0e-2f5f9a3c1d21'),
        );
    }
}
```

## Что повторять

- Файл лежит в `Modules/{Module}/Tests/Unit/{Domain,Application}`, namespace повторяет путь.
- Тест наследует `PHPUnit\Framework\TestCase` и не поднимает Spiral, базу данных, Redis, MinIO, очередь и сеть.
- Имя теста называет проверяемое правило, а не метод: `testPublishTwiceIsRejected()`.
- Новая ветвь поведения покрывается положительным, отрицательным и граничным сценарием.
- Исправление ошибки начинается с теста, который её воспроизводит.
- Объекты собираются доменными фабриками, а не через рефлексию и не подстановкой полей.
- Для дублёра, у которого не проверяется факт вызова, берётся `createStub()`; mock — только когда тест проверяет взаимодействие.
- Набор запускается командой `make test-unit`, полный прогон — `make test`.

## Допустимые варианты

Тест сценария Application тоже относится к unit-набору, если все его зависимости заменены дублёрами: Repository, Reader и технические порты объявлены интерфейсами именно ради этого. Общая подготовка данных выносится в приватный метод-фабрику того же теста, а не в общий базовый класс.
