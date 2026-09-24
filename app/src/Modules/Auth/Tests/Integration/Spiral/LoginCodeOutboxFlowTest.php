<?php

declare(strict_types=1);

namespace App\Modules\Auth\Tests\Integration\Spiral;

use App\Modules\Auth\Application\Command\RequestLoginCode\RequestLoginCodeCommand;
use App\Modules\Auth\Application\Command\RequestLoginCode\RequestLoginCodeHandler;
use App\Modules\Auth\Application\Contract\LoginCodeMailerContract;
use App\Modules\Auth\Infrastructure\Spiral\Job\SendLoginCodeJob;
use GianTiaga\SpiralCqrs\CommandBusInterface;
use GianTiaga\SpiralOutbox\OutboxDeliveryStatus;
use Tests\DatabaseTestCase;
use Tests\Support\Outbox\CleansOutboxEvents;
use Tests\Support\Outbox\RunsOutboxRelay;

/**
 * Сквозной путь письма с кодом входа: Command handler в транзакции кладёт событие, проход relay
 * создаёт доставку по маршруту и выполняет Job, доставка закрывается успехом. Подключение очереди
 * в тестах — `sync`, поэтому исход доставки известен сразу после прохода.
 */
final class LoginCodeOutboxFlowTest extends DatabaseTestCase
{
    use CleansOutboxEvents;
    use RunsOutboxRelay;

    private RecordingLoginCodeMailer $loginCodeMailer;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        // Счёт событий и доставок здесь абсолютный, поэтому тест начинает с пустых таблиц обмена
        // независимо от того, что оставил сосед по worker-у.
        $this->cleanOutboxEvents();
        $this->loginCodeMailer = new RecordingLoginCodeMailer();
        $this->getContainer()->bindSingleton(LoginCodeMailerContract::class, $this->loginCodeMailer);
    }

    public function testRequestedCodeReachesMailerThroughRelayAndCompletesDelivery(): void
    {
        $this->requestLoginCode();

        self::assertSame(1, $this->outboxEventCount());

        $this->runOutboxRelayPass();

        $delivery = $this->outboxDeliveryOf(SendLoginCodeJob::class);
        self::assertSame(OutboxDeliveryStatus::Completed, $delivery->status);
        self::assertCount(1, $this->loginCodeMailer->sentEmails);
    }

    public function testThrottledRepeatStagesNoEventAndLeavesEarlierDeliveryUntouched(): void
    {
        $this->requestLoginCode();
        $this->runOutboxRelayPass();
        $earlierDelivery = $this->outboxDeliveryOf(SendLoginCodeJob::class);

        // Повторный запрос в окне троттлинга — отказ сценария отправить письмо второй раз:
        // нового события нет, соседняя закрытая доставка не меняется.
        $this->requestLoginCode();

        self::assertSame(1, $this->outboxEventCount());
        self::assertCount(1, $this->outboxDeliveries());

        $delivery = $this->outboxDeliveryOf(SendLoginCodeJob::class);
        self::assertSame($earlierDelivery->outboxDeliveryId, $delivery->outboxDeliveryId);
        self::assertSame(OutboxDeliveryStatus::Completed, $delivery->status);
        self::assertSame(0, $delivery->attempts);
    }

    public function testRepeatedRelayPassDoesNotDeliverClosedDeliveryTwice(): void
    {
        $this->requestLoginCode();
        $this->runOutboxRelayPass();

        // Доставка закрыта, поэтому второй проход её не берёт: письмо остаётся одно.
        $this->runOutboxRelayPass();

        self::assertCount(1, $this->outboxDeliveries());
        self::assertSame(
            OutboxDeliveryStatus::Completed,
            $this->outboxDeliveryOf(SendLoginCodeJob::class)->status,
        );
        self::assertCount(1, $this->loginCodeMailer->sentEmails);
    }

    private function requestLoginCode(): void
    {
        $this->getContainer()->get(CommandBusInterface::class)->dispatch(
            command: new RequestLoginCodeCommand(email: 'user@example.com', requestLocale: 'ru'),
            handler: $this->getContainer()->get(RequestLoginCodeHandler::class)->handle(...),
        );
    }
}
