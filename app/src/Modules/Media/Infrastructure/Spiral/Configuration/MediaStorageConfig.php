<?php

declare(strict_types=1);

namespace App\Modules\Media\Infrastructure\Spiral\Configuration;

use App\Shared\Infrastructure\Spiral\Configuration\Storage\StorageBucketConfig;
use App\Shared\Infrastructure\Spiral\Configuration\Storage\StorageServerConfig;
use App\Shared\Infrastructure\Spiral\Configuration\TypedConfig;

/**
 * Типизированный вид общей секции Spiral 'storage' со стороны Media.
 *
 * Читает ту же секцию, что и {@see \App\Shared\Infrastructure\Spiral\Configuration\Storage\StorageConfig}
 * (маппер конфигурации строгий: `CuyZ\Valinor\MapperBuilder` без `allowSuperfluousKeys()` требует
 * поле под каждый ключ раздела, поэтому повторяет полную форму раздела), но модуль обращается только
 * к своим трём бакетам (`buckets[MediaStorage::value]`) — общий `default`-бакет приложения и серверы
 * читает из `StorageConfig`.
 */
final readonly class MediaStorageConfig implements TypedConfig
{
    public static function configName(): string
    {
        return 'storage';
    }

    /**
     * @param array<string, StorageServerConfig> $servers
     * @param array<string, StorageBucketConfig> $buckets
     */
    public function __construct(
        public string $default,
        public array $servers,
        public array $buckets,
    ) {}
}
