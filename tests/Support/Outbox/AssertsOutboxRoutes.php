<?php

declare(strict_types=1);

namespace Tests\Support\Outbox;

use App\Shared\Infrastructure\Spiral\Queue\QueueName;
use GianTiaga\SpiralOutbox\Config\OutboxConfig;
use GianTiaga\SpiralOutbox\IntegrationEventContract;
use Spiral\Queue\JobHandler;

/**
 * Проверка маршрута события в собранной секции конфигурации `outbox`: карту наполняют bootloader-ы
 * модулей-потребителей, поэтому читается она из готового объекта настройки пакета.
 *
 * @mixin \Tests\TestCase
 */
trait AssertsOutboxRoutes
{
    /**
     * @param class-string<IntegrationEventContract> $eventClass
     * @param class-string<JobHandler> $jobClass
     * @param list<int> $retryDelaysSeconds
     */
    protected function assertOutboxRoute(
        string $eventClass,
        string $jobClass,
        QueueName $queueName,
        array $retryDelaysSeconds,
        int $deliveryTimeoutSeconds,
    ): void {
        $routes = $this->getContainer()->get(OutboxConfig::class)->routes->forEvent(eventType: $eventClass);

        self::assertNotNull($routes, \sprintf('У события %s нет маршрутов.', $eventClass));

        $declaredRoutes = $routes->all();
        self::assertCount(1, $declaredRoutes);

        $route = $declaredRoutes[0];
        self::assertSame($jobClass, $route->job);
        self::assertSame($queueName->value, $route->queue);
        self::assertSame($retryDelaysSeconds, $route->retryDelaysSeconds);
        self::assertSame($deliveryTimeoutSeconds, $route->deliveryTimeoutSeconds);
    }
}
