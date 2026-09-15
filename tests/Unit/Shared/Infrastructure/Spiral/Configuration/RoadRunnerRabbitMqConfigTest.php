<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Spiral\Configuration;

use PHPUnit\Framework\TestCase;

final class RoadRunnerRabbitMqConfigTest extends TestCase
{
    public function testRabbitMqPipelineUsesEnvironmentPlaceholders(): void
    {
        $roadRunnerConfig = \file_get_contents(__DIR__ . '/../../../../../../docker/rr/http-jobs.yaml');

        self::assertIsString($roadRunnerConfig);
        self::assertStringContainsString('prefetch: ${RABBITMQ_QUEUE_PREFETCH:-100}', $roadRunnerConfig);
        self::assertStringContainsString('queue: ${RABBITMQ_QUEUE_NAME:-yoga_loka_jobs}', $roadRunnerConfig);
        self::assertStringContainsString('exchange: ${RABBITMQ_EXCHANGE_NAME:-yoga_loka_jobs}', $roadRunnerConfig);
        self::assertStringContainsString('exchange_durable: ${RABBITMQ_EXCHANGE_DURABLE:-true}', $roadRunnerConfig);
        self::assertStringContainsString('exchange_type: ${RABBITMQ_EXCHANGE_TYPE:-direct}', $roadRunnerConfig);
        self::assertStringContainsString('routing_key: ${RABBITMQ_ROUTING_KEY:-yoga_loka_jobs}', $roadRunnerConfig);
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
    }
}
