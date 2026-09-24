<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Spiral\Configuration;

use App\Shared\Infrastructure\Spiral\Queue\QueueName;
use PHPUnit\Framework\TestCase;

final class RoadRunnerRabbitMqConfigTest extends TestCase
{
    public function testEveryApplicationQueueIsDeclaredAndConsumedByRoadRunner(): void
    {
        $roadRunnerConfig = \file_get_contents(__DIR__ . '/../../../../../../docker/rr/http-jobs.yaml');

        self::assertIsString($roadRunnerConfig);
        self::assertCount(3, QueueName::cases());

        foreach (QueueName::cases() as $queueName) {
            $resourceName = '${RABBITMQ_QUEUE_PREFIX:-yoga_loka}_' . $queueName->value;

            self::assertStringContainsString("    {$queueName->value}:\n      driver: amqp", $roadRunnerConfig);
            self::assertStringContainsString('queue: ' . $resourceName, $roadRunnerConfig);
            self::assertStringContainsString('exchange: ' . $resourceName, $roadRunnerConfig);
            self::assertStringContainsString('routing_key: ' . $resourceName, $roadRunnerConfig);
        }

        self::assertStringContainsString("  consume:\n    - mail\n    - media\n    - notifications\n", $roadRunnerConfig);
        self::assertStringNotContainsString('RABBITMQ_QUEUE_NAME', $roadRunnerConfig);
        self::assertStringNotContainsString('RABBITMQ_EXCHANGE_NAME', $roadRunnerConfig);
        self::assertStringNotContainsString('RABBITMQ_ROUTING_KEY', $roadRunnerConfig);
    }

    public function testRabbitMqPipelinesUseEnvironmentPlaceholders(): void
    {
        $roadRunnerConfig = \file_get_contents(__DIR__ . '/../../../../../../docker/rr/http-jobs.yaml');

        self::assertIsString($roadRunnerConfig);
        self::assertStringContainsString('prefetch: ${RABBITMQ_QUEUE_PREFETCH:-100}', $roadRunnerConfig);
        self::assertStringContainsString('exchange_durable: ${RABBITMQ_EXCHANGE_DURABLE:-true}', $roadRunnerConfig);
        self::assertStringContainsString('exchange_type: ${RABBITMQ_EXCHANGE_TYPE:-direct}', $roadRunnerConfig);
        self::assertStringContainsString('requeue_on_fail: ${RABBITMQ_REQUEUE_ON_FAIL:-false}', $roadRunnerConfig);
        self::assertStringContainsString('durable: ${RABBITMQ_QUEUE_DURABLE:-true}', $roadRunnerConfig);
        self::assertStringContainsString('${RABBITMQ_PORT:-5672}${RABBITMQ_URL_VHOST:-/}', $roadRunnerConfig);
        self::assertStringNotContainsString('${RABBITMQ_PORT:-5672}${RABBITMQ_VHOST:-/}', $roadRunnerConfig);
    }

    public function testRabbitMqVhostNameAndUrlPathAreDocumentedSeparately(): void
    {
        $environmentSample = \file_get_contents(__DIR__ . '/../../../../../../.env.sample');
        $dockerComposeConfig = \file_get_contents(__DIR__ . '/../../../../../../docker/docker-compose.dev.yml');

        self::assertIsString($environmentSample);
        self::assertIsString($dockerComposeConfig);
        self::assertStringContainsString('RABBITMQ_VHOST=/', $environmentSample);
        self::assertStringContainsString('RABBITMQ_URL_VHOST=/', $environmentSample);
        self::assertStringContainsString('RABBITMQ_DEFAULT_VHOST: ${RABBITMQ_VHOST:-/}', $dockerComposeConfig);
        self::assertStringContainsString('Для нестандартного vhost используй `staging`', $environmentSample);
        self::assertStringContainsString('Для того же `staging` используй `/staging`', $environmentSample);
        self::assertStringContainsString('RABBITMQ_QUEUE_PREFIX=yoga_loka', $environmentSample);
        self::assertStringNotContainsString('RABBITMQ_QUEUE_NAME=', $environmentSample);
        self::assertStringNotContainsString('RABBITMQ_EXCHANGE_NAME=', $environmentSample);
        self::assertStringNotContainsString('RABBITMQ_ROUTING_KEY=', $environmentSample);
    }
}
