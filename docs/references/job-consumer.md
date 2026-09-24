# Job-потребитель

## Назначение

Job — входной адаптер очереди: он принимает доставку интеграционного события, восстанавливает событие и передаёт его в Command или Query своего модуля.

## Когда применять

Применяй для каждого интеграционного события, на которое модуль реагирует асинхронно: отправка письма, push, обработка медиа, пересчёт по факту соседнего модуля.

```php
<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Infrastructure\Spiral\Job;

use App\Modules\Notifications\Application\Command\NotifyPostPublished\NotifyPostPublishedCommand;
use App\Modules\Notifications\Application\Command\NotifyPostPublished\NotifyPostPublishedHandler;
use App\Modules\Posts\Public\Event\PostPublishedEvent;
use GianTiaga\SpiralCqrs\CommandBusInterface;
use GianTiaga\SpiralOutbox\OutboxMessageLoaderContract;
use Spiral\Queue\JobHandler;

/**
 * Потребитель события о публикации записи. Доставка имеет семантику at-least-once, поэтому
 * идентификатор доставки уходит в сценарий: повтор той же доставки не создаёт вторую рассылку.
 */
final class NotifyPostPublishedJob extends JobHandler
{
    public function invoke(
        string $outboxDeliveryId,
        OutboxMessageLoaderContract $outboxMessageLoader,
        CommandBusInterface $commandBus,
        NotifyPostPublishedHandler $notifyPostPublishedHandler,
    ): void {
        $event = $outboxMessageLoader->load(
            outboxDeliveryId: $outboxDeliveryId,
            expectedMessageClass: PostPublishedEvent::class,
        );

        $commandBus->dispatch(
            command: new NotifyPostPublishedCommand(
                deliveryId: $outboxDeliveryId,
                postId: $event->postId,
                authorUserId: $event->authorUserId,
                publishedAt: $event->publishedAt,
            ),
            handler: $notifyPostPublishedHandler->handle(...),
        );
    }
}
```

```php
<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Command\NotifyPostPublished;

use App\Modules\Notifications\Domain\Entity\PostPublishedNotification;
use App\Modules\Notifications\Domain\Repository\PostPublishedNotificationRepository;
use App\Modules\Notifications\Domain\ValueObject\DeliveryId;
use GianTiaga\SpiralCqrs\Attribute\Transactional;

final readonly class NotifyPostPublishedHandler
{
    public function __construct(
        private PostPublishedNotificationRepository $notificationRepository,
    ) {}

    /**
     * Идемпотентность по идентификатору доставки: уникальный ключ на нём отсекает повтор,
     * а не проверка «похоже, уже отправляли».
     */
    #[Transactional]
    public function handle(NotifyPostPublishedCommand $command): void
    {
        $deliveryId = DeliveryId::fromString($command->deliveryId);

        if ($this->notificationRepository->existsByDeliveryId($deliveryId)) {
            return;
        }

        $this->notificationRepository->save(PostPublishedNotification::schedule(
            deliveryId: $deliveryId,
            postId: $command->postId,
            authorUserId: $command->authorUserId,
            publishedAt: $command->publishedAt,
        ));
    }
}
```

## Что повторять

- Класс называется `{Action}Job`, наследует `JobHandler` и лежит в `Infrastructure/Spiral/Job` модуля-потребителя.
- Публичный `invoke()` принимает обязательный `string $outboxDeliveryId`; остальные параметры — объектные зависимости, их подставляет контейнер.
- Событие восстанавливает `OutboxMessageLoaderContract::load()` с явным ожидаемым классом: чужое событие в этом Job не пройдёт.
- Job преобразует событие в один Command или Query и вызывает шину: бизнес-правил, запросов к БД и `try-catch` в нём нет.
- Повторами владеет только outbox: их число задаёт список пауз маршрута, собственного счётчика попыток у Job нет. Доставка повторяется, поэтому идемпотентность обязательна и реализуется в сценарии по идентификатору доставки, а не в Job.
- Потребитель не импортирует Domain, Application и Repository модуля-издателя: только его `Public/Event`.
- Пара «событие — Job» объявляется маршрутом в секции конфигурации `outbox`: bootloader модуля-потребителя патчит её списком маршрутов своего события, где маршрут называет Job, очередь, список пауз повторов в секундах и таймаут доставки; пустой список пауз означает единственную попытку без повтора. Если событие и Job — разные модули, объявление делает bootloader модуля-потребителя (импортирует свой Job и чужой `Public/Event`), а не издателя: издатель не имеет доступа к `Infrastructure/Spiral` потребителя.

## Допустимые варианты

Одно событие может иметь несколько маршрутов и несколько Job в разных модулях. Тяжёлая работа выносится в сценарий, а не в `invoke()`. Если повтор безопасен сам по себе (операция естественно идемпотентна), проверка по идентификатору доставки не нужна, и причина записывается комментарием рядом со сценарием.
