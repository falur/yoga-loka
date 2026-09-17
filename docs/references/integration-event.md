# Интеграционное событие

## Назначение

Интеграционное событие — стабильное сообщение модуля соседям о свершившемся факте. Оно лежит в `Public/Event`, записывается в outbox в той же транзакции, что и изменение агрегата, и доставляется потребителю асинхронно с семантикой at-least-once.

## Когда применять

Применяй, когда действие после commit не должно задерживать ответ и может повторяться: почта, push, Centrifugo, обработка медиа, реакция соседнего модуля. Если результат нужен текущему ответу или изменения обязаны войти в одну транзакцию, используй публичный контракт соседа.

```php
<?php

declare(strict_types=1);

namespace App\Modules\Posts\Public\Event;

use GianTiaga\SpiralOutbox\IntegrationEventContract;

/**
 * Запись опубликована. Только минимальные стабильные данные: по ним потребитель
 * дочитает всё остальное через `Public` модуля-владельца.
 */
final readonly class PostPublishedEvent implements IntegrationEventContract
{
    public function __construct(
        public string $postId,
        public string $authorUserId,
        public \DateTimeImmutable $publishedAt,
    ) {}
}
```

```php
<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Command\PublishPost;

use App\Modules\Posts\Application\Contract\ClockContract;
use App\Modules\Posts\Domain\Repository\PostRepository;
use App\Modules\Posts\Domain\ValueObject\PostId;
use App\Modules\Posts\Public\Event\PostPublishedEvent;
use App\Shared\Domain\Exception\NotFoundException;
use GianTiaga\SpiralCqrs\Attribute\Transactional;
use GianTiaga\SpiralOutbox\OutboxEventStoreContract;

final readonly class PublishPostHandler
{
    public function __construct(
        private PostRepository $postRepository,
        private OutboxEventStoreContract $outboxEventStore,
        private ClockContract $clock,
    ) {}

    /**
     * Одна транзакция: загрузка агрегата, доменный переход, сохранение и запись события.
     * Откат уносит и событие, поэтому наружу не уйдёт факт, которого не случилось.
     */
    #[Transactional]
    public function handle(PublishPostCommand $command): PublishPostResult
    {
        $postId = PostId::fromString($command->postId);

        $post = $this->postRepository->findById($postId)
            ?? throw new NotFoundException('app.post.not_found');

        $publishedAt = $this->clock->now();
        $post->publish($publishedAt);

        $this->postRepository->save($post);

        $this->outboxEventStore->add(new PostPublishedEvent(
            postId: $post->id->value(),
            authorUserId: $post->authorId->value(),
            publishedAt: $publishedAt,
        ));

        return new PublishPostResult(postId: $post->id->value());
    }
}
```

## Что повторять

- Класс события лежит в `Public/Event`, имя заканчивается на `Event` и называет свершившийся факт в прошедшем времени.
- Событие `final readonly`, реализует `IntegrationEventContract` и состоит только из скаляров, backed enum, `DateTimeImmutable` и типизированных списков; у каждого свойства есть одноимённый параметр конструктора, иначе событие не собрать обратно.
- В событии нет Entity, ValueObject, Cycle-моделей, приватных полей, секретов и сырых ответов внешних сервисов.
- Событие создаёт Command handler и записывает его через `OutboxEventStoreContract::add()` внутри своей `#[Transactional]`-границы; своей транзакции контракт не открывает.
- Запись события идёт после сохранения агрегата и до выхода из handler: транзакция охватывает загрузку, изменение, сохранение и outbox.
- Внутреннее доменное событие в `Public/Event` не выносится и остаётся в `Domain/Event`.
- Маршрут события на Job-потребителя объявляется в `boot()` через `IntegrationEventRoutingContract`. Когда Job лежит в том же модуле, что и событие, регистрация естественно оказывается в его bootloader; когда потребитель — другой модуль, регистрацию делает bootloader модуля-потребителя (он же импортирует свой Job), а не издателя: `Infrastructure/Spiral` одного модуля не видит `Infrastructure/Spiral` другого (deptrac), тогда как `Public/Event` соседа открыт всем — см. карточку [Job-потребитель](job-consumer.md).

## Допустимые варианты

Одно действие может породить несколько событий, если это разные факты для разных потребителей. Возвращённый `add()` идентификатор берут только сценарии, которым нужно связать свою строку с событием в той же транзакции; остальные его игнорируют.
