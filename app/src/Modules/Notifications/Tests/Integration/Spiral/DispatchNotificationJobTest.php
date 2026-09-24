<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Tests\Integration\Spiral;

use App\Modules\Notifications\Application\Command\Notification\DispatchNotification\DispatchNotificationHandler;
use App\Modules\Notifications\Public\Contract\NotificationTypeRegistryContract;
use App\Modules\Notifications\Application\Exception\NotificationTypeRegistryException;
use App\Modules\Notifications\Public\Event\NotificationRequestedEvent;
use App\Modules\Notifications\Domain\Entity\Notification;
use App\Modules\Notifications\Domain\ValueObject\NotificationOutboxId;
use App\Modules\Notifications\Infrastructure\Spiral\Registry\NotificationTypeRegistry;
use App\Modules\Notifications\Infrastructure\Spiral\Job\DispatchNotificationJob;
use App\Modules\Notifications\Domain\Repository\NotificationRepository;
use App\Modules\Notifications\Domain\Repository\NotificationSettingRepository;
use App\Shared\Domain\ValueObject\UserId;
use Cycle\ORM\EntityManagerInterface;
use GianTiaga\SpiralCqrs\CommandBusInterface;
use GianTiaga\SpiralOutbox\Exception\RetryableOutboxException;
use GianTiaga\SpiralOutbox\OutboxEventStoreContract;
use GianTiaga\SpiralOutbox\OutboxMessageLoaderContract;
use Psr\Log\NullLogger;
use Tests\DatabaseTestCase;
use App\Modules\Notifications\Tests\Unit\Application\Fixture\FixtureNotificationTypeDefinition;
use Tests\Support\Notifications\RecordingOutboxEventStore;
use Tests\Support\Outbox\CleansOutboxEvents;
use Tests\Support\Outbox\RunsOutboxRelay;

final class DispatchNotificationJobTest extends DatabaseTestCase
{
    use CleansOutboxEvents;
    use RunsOutboxRelay;

    private const string TYPE = 'chat.message_received';
    /** Доставки с таким идентификатором нет: сценарий проверяет только классификацию сбоя. */
    private const string MISSING_DELIVERY_ID = '0190f3b1-0000-7000-8000-0000000000de';

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        // Доставка ищется по классу Job, поэтому тест начинает с пустых таблиц обмена: чужая
        // строка того же Job от соседа по worker-у сделала бы выбор неоднозначным.
        $this->cleanOutboxEvents();
    }

    public function testDelegatesToDispatchHandlerAndCreatesInbox(): void
    {
        $this->getContainer()->get(NotificationTypeRegistryContract::class)
            ->register(FixtureNotificationTypeDefinition::allChannels(self::TYPE));

        $deliveryId = $this->stageNotificationRequested(UserId::generate());

        $this->getContainer()->get(DispatchNotificationJob::class)->invoke(
            outboxDeliveryId: $deliveryId,
            outboxMessageLoader: $this->getContainer()->get(OutboxMessageLoaderContract::class),
            commandBus: $this->getContainer()->get(CommandBusInterface::class),
            dispatchNotificationHandler: $this->getContainer()->get(DispatchNotificationHandler::class),
            logger: new NullLogger(),
        );

        $inbox = $this->notificationRepository()->findByOutboxId(NotificationOutboxId::fromString($deliveryId));
        self::assertInstanceOf(Notification::class, $inbox);
    }

    public function testRethrowsTerminalDomainErrorForUnknownType(): void
    {
        $deliveryId = $this->stageNotificationRequested(UserId::generate());

        $this->expectException(NotificationTypeRegistryException::class);

        $this->getContainer()->get(DispatchNotificationJob::class)->invoke(
            outboxDeliveryId: $deliveryId,
            outboxMessageLoader: $this->getContainer()->get(OutboxMessageLoaderContract::class),
            commandBus: $this->getContainer()->get(CommandBusInterface::class),
            dispatchNotificationHandler: $this->getContainer()->get(DispatchNotificationHandler::class),
            logger: new NullLogger(),
        );
    }

    public function testConvertsInfrastructureFailureToRetry(): void
    {
        $registry = new NotificationTypeRegistry();
        $registry->register(FixtureNotificationTypeDefinition::allChannels(self::TYPE));

        // Отказ базы на записи сценария: прогон делает репозиторий, поэтому падение имитирует он.
        $failingNotificationRepository = $this->createStub(NotificationRepository::class);
        $failingNotificationRepository->method('saveAll')->willThrowException(new \RuntimeException('БД недоступна.'));

        $handler = new DispatchNotificationHandler(
            notificationRepository: $failingNotificationRepository,
            notificationSettingRepository: $this->getContainer()->get(NotificationSettingRepository::class),
            typeCatalog: $registry,
            outboxEventStore: new RecordingOutboxEventStore(),
            logger: new NullLogger(),
        );

        $loader = $this->createStub(OutboxMessageLoaderContract::class);
        $loader->method('load')->willReturn(new NotificationRequestedEvent(
            userId: UserId::generate()->value(),
            type: self::TYPE,
            title: 'Новое сообщение',
            body: 'Вам пришло сообщение',
            action: null,
            actor: null,
            createdAt: '2026-06-13T10:00:00+00:00',
        ));

        $this->expectException(RetryableOutboxException::class);

        $this->getContainer()->get(DispatchNotificationJob::class)->invoke(
            outboxDeliveryId: self::MISSING_DELIVERY_ID,
            outboxMessageLoader: $loader,
            commandBus: $this->getContainer()->get(CommandBusInterface::class),
            dispatchNotificationHandler: $handler,
            logger: new NullLogger(),
        );
    }

    /**
     * Кладёт событие и выполняет первый этап relay: по маршруту появляется строка доставки, её
     * идентификатор и есть ключ идемпотентности рассылки.
     */
    private function stageNotificationRequested(UserId $userId): string
    {
        $this->getContainer()->get(OutboxEventStoreContract::class)->add(new NotificationRequestedEvent(
            userId: $userId->value(),
            type: self::TYPE,
            title: 'Новое сообщение',
            body: 'Вам пришло сообщение',
            action: null,
            actor: null,
            createdAt: '2026-06-13T10:00:00+00:00',
        ));
        $this->getContainer()->get(EntityManagerInterface::class)->run();
        $this->routeOutboxEvents();

        return $this->outboxDeliveryOf(DispatchNotificationJob::class)->outboxDeliveryId;
    }

    private function notificationRepository(): NotificationRepository
    {
        return $this->getContainer()->get(NotificationRepository::class);
    }
}
