<?php

declare(strict_types=1);

namespace App\Modules\Auth\Tests\Integration\Spiral;

use App\Modules\Auth\Infrastructure\Spiral\Job\SendLoginCodeJob;
use App\Modules\Auth\Public\Event\LoginCodeRequestedEvent;
use App\Shared\Infrastructure\Spiral\Queue\QueueName;
use Tests\Support\Outbox\AssertsOutboxRoutes;
use Tests\TestCase;

/**
 * Маршрут события модуля объявляет его bootloader патчем секции `outbox`: проверяем собранную
 * карту, а не сам вызов патча.
 */
final class AuthOutboxRoutesTest extends TestCase
{
    use AssertsOutboxRoutes;

    public function testLoginCodeRequestedGoesToMailQueueWithTwoRetries(): void
    {
        $this->assertOutboxRoute(
            eventClass: LoginCodeRequestedEvent::class,
            jobClass: SendLoginCodeJob::class,
            queueName: QueueName::Mail,
            retryDelaysSeconds: [30, 120],
            deliveryTimeoutSeconds: 300,
        );
    }
}
