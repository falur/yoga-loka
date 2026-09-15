# Domain Event

## Назначение

Доменное событие — факт внутри одного модуля. Оно остаётся в `Domain/Event`, соседям не видно и в очередь не уходит.

## Когда применять

Применяй, когда доменный переход нужно зафиксировать как факт для самого модуля: журнал, пересчёт своей проекции, ветвление сценария. Наружу факт выходит только как интеграционное событие в `Public/Event` — см. карточку [Интеграционное событие](integration-event.md); решает это Application, а не Domain.

```php
<?php

declare(strict_types=1);

namespace App\Modules\Posts\Domain\Event;

use App\Modules\Posts\Domain\ValueObject\PostId;
use App\Shared\Domain\ValueObject\UserId;

/** Запись опубликована: факт внутри модуля, доменными типами и в прошедшем времени. */
final readonly class PostPublished
{
    public function __construct(
        public PostId $postId,
        public UserId $authorId,
        public \DateTimeImmutable $publishedAt,
    ) {}
}
```

```php
<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Command\PublishPost;

use App\Modules\Posts\Domain\Event\PostPublished;
use App\Modules\Posts\Public\Event\PostPublishedEvent;
use GianTiaga\SpiralCqrs\Attribute\Transactional;

final readonly class PublishPostHandler
{
    #[Transactional]
    public function handle(PublishPostCommand $command): PublishPostResult
    {
        // ... загрузка агрегата, доменный переход и сохранение

        $postPublished = new PostPublished(
            postId: $post->id,
            authorId: $post->authorId,
            publishedAt: $publishedAt,
        );

        $this->postPublishedLog->record($postPublished);

        // Application решает, какой доменный факт становится интеграционным событием:
        // доменное событие остаётся внутри, наружу уходит его публичная форма.
        $this->outboxEventStore->add(new PostPublishedEvent(
            postId: $postPublished->postId->value(),
            authorUserId: $postPublished->authorId->value(),
            publishedAt: $postPublished->publishedAt,
        ));

        return new PublishPostResult(postId: $post->id->value());
    }
}
```

## Что повторять

- Класс `final readonly`, лежит в `Domain/Event` и назван свершившимся фактом в прошедшем времени: `PostPublished`.
- Поля — доменные типы своего модуля и `DateTimeImmutable`; примитивы хранения и Cycle-модели в событие не попадают.
- Доменное событие не покидает модуль: в `Public`, в outbox и в очередь уходит только `Public/Event`.
- Классов Spiral, Cycle и контейнера в событии нет.
- Преобразование доменного события в интеграционное выполняет Application.

## Допустимые варианты

Если у модуля нет внутреннего потребителя факта, доменное событие не заводится: Command handler сразу создаёт `Public/Event`. Доменное событие и интеграционное событие — два разных класса даже при совпадающем наборе полей: у них разные границы совместимости.
