<?php

declare(strict_types=1);

namespace App\Infrastructure\Configuration\Queue;

use App\Infrastructure\Configuration\TypedConfig;
use Spiral\Core\Container\Autowire;
use Spiral\Serializer\SerializerInterface;

final readonly class QueueConfig implements TypedConfig
{
    public static function configName(): string
    {
        return 'queue';
    }

    /**
     * @param array<string, string> $aliases
     * @param array<string, QueueConnectionConfig> $connections
     * @param array<string, class-string> $driverAliases
     * @param array<string, QueuePipelineConfig> $pipelines
     * @param Autowire<SerializerInterface>|SerializerInterface|string|null $defaultSerializer
     */
    public function __construct(
        public string $default,
        public array $aliases,
        public array $connections,
        public QueueRegistryConfig $registry,
        public array $driverAliases,
        public QueueInterceptorsConfig $interceptors,
        public array $pipelines,
        public SerializerInterface|string|Autowire|null $defaultSerializer,
    ) {}
}
