<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Tests\Integration\Spiral;

use App\Modules\Notifications\Application\Command\RequestNotification\RequestNotificationCommand;
use App\Modules\Notifications\Application\Command\RequestNotification\RequestNotificationHandler;
use App\Modules\Notifications\Application\Contract\CentrifugoServiceContract;
use App\Modules\Notifications\Application\Exception\CentrifugoPublishException;
use App\Modules\Notifications\Domain\ValueObject\NotificationAction;
use App\Modules\Notifications\Domain\ValueObject\NotificationActor;
use App\Modules\Notifications\Domain\ValueObject\NotificationBody;
use App\Modules\Notifications\Domain\ValueObject\NotificationOutboxId;
use App\Modules\Notifications\Domain\ValueObject\NotificationTitle;
use App\Modules\Notifications\Domain\ValueObject\NotificationTypeCode;
use App\Modules\Notifications\Domain\Repository\NotificationRepository;
use App\Modules\Notifications\Infrastructure\Spiral\Job\DispatchNotificationJob;
use App\Modules\Notifications\Infrastructure\Spiral\Job\PublishRealtimeNotificationJob;
use App\Modules\Notifications\Public\Contract\NotificationTypeRegistryContract;
use App\Modules\Notifications\Public\Enum\NotificationChannel;
use App\Modules\Notifications\Tests\Unit\Application\Fixture\FixtureNotificationTypeDefinition;
use App\Shared\Domain\ValueObject\UserId;
use GianTiaga\SpiralCqrs\CommandBusInterface;
use GianTiaga\SpiralOutbox\OutboxDeliveryStatus;
use Tests\DatabaseTestCase;
use Tests\Support\Outbox\CleansOutboxEvents;
use Tests\Support\Outbox\RunsOutboxRelay;

/**
 * Сквозной путь уведомления: сценарий-триггер кладёт событие, первый проход relay доводит рассылку
 * до инбокса и стейджит запрос realtime-доставки, второй проход выполняет её. У маршрута realtime
 * пуст список пауз, поэтому первая же ошибка канала закрывает доставку окончательно.
 */
final class NotificationOutboxFlowTest extends DatabaseTestCase
{
    use CleansOutboxEvents;
    use RunsOutboxRelay;

    private const string TYPE = 'chat.message_received';

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        // Счёт событий здесь абсолютный, поэтому тест начинает с пустых таблиц обмена независимо
        // от того, что оставил сосед по worker-у.
        $this->cleanOutboxEvents();
        $this->getContainer()->get(NotificationTypeRegistryContract::class)->register(
            FixtureNotificationTypeDefinition::withDefaultChannels(
                self::TYPE,
                NotificationChannel::Database,
                NotificationChannel::Realtime,
            ),
        );
    }

    public function testRequestedNotificationReachesRealtimeChannelThroughTwoRelayPasses(): void
    {
        $this->bindCentrifugoService($this->createStub(CentrifugoServiceContract::class));
        $userId = UserId::generate();

        $this->requestNotification($userId);
        self::assertSame(1, $this->outboxEventCount());

        $this->runOutboxRelayPass();

        $dispatchDelivery = $this->outboxDeliveryOf(DispatchNotificationJob::class);
        self::assertSame(OutboxDeliveryStatus::Completed, $dispatchDelivery->status);
        self::assertNotNull($this->getContainer()->get(NotificationRepository::class)->findByOutboxId(
            NotificationOutboxId::fromString($dispatchDelivery->outboxDeliveryId),
        ));

        // Рассылка застейджила запрос realtime-доставки: его маршрут отрабатывает следующий проход.
        self::assertSame(2, $this->outboxEventCount());

        $this->runOutboxRelayPass();

        self::assertSame(
            OutboxDeliveryStatus::Completed,
            $this->outboxDeliveryOf(PublishRealtimeNotificationJob::class)->status,
        );
    }

    public function testRealtimeDeliveryFailsOnFirstErrorBecauseRetryListIsEmpty(): void
    {
        $failingCentrifugoService = $this->createStub(CentrifugoServiceContract::class);
        $failingCentrifugoService->method('publish')
            ->willThrowException(CentrifugoPublishException::serverError(503));
        $this->bindCentrifugoService($failingCentrifugoService);

        $this->requestNotification(UserId::generate());
        $this->runOutboxRelayPass();
        $this->runOutboxRelayPass();

        // Повторяемый отказ канала, но пауз у маршрута нет: доставка сразу становится окончательной,
        // а соседняя доставка рассылки остаётся закрытой успехом.
        self::assertSame(
            OutboxDeliveryStatus::Failed,
            $this->outboxDeliveryOf(PublishRealtimeNotificationJob::class)->status,
        );
        self::assertSame(
            OutboxDeliveryStatus::Completed,
            $this->outboxDeliveryOf(DispatchNotificationJob::class)->status,
        );
    }

    public function testFailedTriggerStagesNoEventAndLeavesEarlierDeliveryUntouched(): void
    {
        $this->bindCentrifugoService($this->createStub(CentrifugoServiceContract::class));

        $this->requestNotification(UserId::generate());
        $this->runOutboxRelayPass();
        $earlierDelivery = $this->outboxDeliveryOf(DispatchNotificationJob::class);
        $eventCountBeforeFailure = $this->outboxEventCount();

        // Незарегистрированный вид — отказ сценария до записи события.
        try {
            $this->requestNotification(userId: UserId::generate(), type: 'chat.unknown');
            self::fail('Ожидался отказ триггера уведомления.');
        } catch (\Throwable) {
            // Отказ ожидаем: проверяется его след в обмене, а не тип исключения.
        }

        self::assertSame($eventCountBeforeFailure, $this->outboxEventCount());

        $delivery = $this->outboxDeliveryOf(DispatchNotificationJob::class);
        self::assertSame($earlierDelivery->outboxDeliveryId, $delivery->outboxDeliveryId);
        self::assertSame(OutboxDeliveryStatus::Completed, $delivery->status);
    }

    private function bindCentrifugoService(CentrifugoServiceContract $centrifugoService): void
    {
        $this->getContainer()->bindSingleton(CentrifugoServiceContract::class, $centrifugoService);
    }

    private function requestNotification(UserId $userId, string $type = self::TYPE): void
    {
        $this->getContainer()->get(CommandBusInterface::class)->dispatch(
            command: new RequestNotificationCommand(
                recipient: $userId,
                type: NotificationTypeCode::fromString($type),
                title: NotificationTitle::fromString('Новое сообщение'),
                body: NotificationBody::fromString('Вам пришло сообщение'),
                action: NotificationAction::none(),
                actor: NotificationActor::none(),
            ),
            handler: $this->getContainer()->get(RequestNotificationHandler::class)->handle(...),
        );
    }
}
