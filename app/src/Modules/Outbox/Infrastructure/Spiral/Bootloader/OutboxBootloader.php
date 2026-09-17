<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Infrastructure\Spiral\Bootloader;

use App\Modules\Outbox\Application\Contract\OutboxJobRegistryContract;
use App\Modules\Outbox\Application\Contract\OutboxMessageSerializerContract;
use App\Modules\Outbox\Application\Contract\OutboxRelayContract;
use App\Modules\Outbox\Application\Contract\OutboxRelayLoopControlContract;
use App\Modules\Outbox\Application\Contract\OutboxRelaySleeperContract;
use App\Modules\Outbox\Application\Contract\OutboxRelayWorkerContract;
use App\Modules\Outbox\Domain\Repository\StoredOutboxEventRepository;
use App\Modules\Outbox\Infrastructure\Persistence\Cycle\Repository\CycleStoredOutboxEventRepository;
use App\Modules\Outbox\Public\Event\OutboxDebugLogRequestedEvent;
use App\Modules\Outbox\Infrastructure\Serializer\ValinorOutboxMessageSerializer;
use App\Modules\Outbox\Infrastructure\Spiral\PublicApi\IntegrationEventLoaderProvider;
use App\Modules\Outbox\Infrastructure\Spiral\PublicApi\IntegrationEventRoutingProvider;
use App\Modules\Outbox\Infrastructure\Spiral\PublicApi\IntegrationEventStoreProvider;
use App\Modules\Outbox\Infrastructure\Spiral\Registry\OutboxJobRegistry;
use App\Modules\Outbox\Infrastructure\Relay\InfiniteOutboxRelayLoopControl;
use App\Modules\Outbox\Infrastructure\Relay\OutboxRelay;
use App\Modules\Outbox\Infrastructure\Relay\OutboxRelayWorker;
use App\Modules\Outbox\Infrastructure\Relay\SystemOutboxRelaySleeper;
use App\Modules\Outbox\Infrastructure\Spiral\Console\OutboxRelayCommand;
use App\Modules\Outbox\Infrastructure\Spiral\Job\OutboxDebugLogJob;
use App\Modules\Outbox\Public\Contract\IntegrationEventLoaderContract;
use App\Modules\Outbox\Public\Contract\IntegrationEventRoutingContract;
use App\Modules\Outbox\Public\Contract\IntegrationEventStoreContract;
use App\Shared\Infrastructure\Spiral\Bootloader\ConfigBootloader;
use App\Shared\Infrastructure\Spiral\Configuration\ConfigArrayFile;
use Cycle\Migrations\Config\MigrationConfig;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Config\ConfiguratorInterface;
use Spiral\Config\Patch\Append;
use Spiral\Console\Bootloader\ConsoleBootloader;

/**
 * Точка подключения модуля Outbox к приложению: доменный интерфейс хранения единственного корня
 * агрегата связан со своей Cycle-реализацией, публичные контракты и технические порты — со своими
 * реализациями.
 */
final class OutboxBootloader extends Bootloader
{
    private const string MIGRATION_VENDOR_DIRECTORIES = 'vendorDirectories';

    protected const BINDINGS = [
        StoredOutboxEventRepository::class => CycleStoredOutboxEventRepository::class,
        IntegrationEventStoreContract::class => IntegrationEventStoreProvider::class,
        IntegrationEventLoaderContract::class => IntegrationEventLoaderProvider::class,
        IntegrationEventRoutingContract::class => IntegrationEventRoutingProvider::class,
        OutboxRelayContract::class => OutboxRelay::class,
        OutboxRelayLoopControlContract::class => InfiniteOutboxRelayLoopControl::class,
        OutboxRelaySleeperContract::class => SystemOutboxRelaySleeper::class,
        OutboxMessageSerializerContract::class => ValinorOutboxMessageSerializer::class,
        OutboxRelayWorkerContract::class => OutboxRelayWorker::class,
    ];

    protected const SINGLETONS = [
        OutboxJobRegistryContract::class => OutboxJobRegistry::class,
    ];

    /**
     * @return array<int, class-string>
     */
    public function defineDependencies(): array
    {
        return [ConsoleBootloader::class];
    }

    /** @param ConfiguratorInterface<object> $config */
    public function init(
        ConsoleBootloader $console,
        ConfiguratorInterface $config,
        ConfigBootloader $configBootloader,
    ): void {
        $console->addCommand(OutboxRelayCommand::class);

        // Каталог миграций модуля дописывается в общий механизм: файлы остаются внутри модуля,
        // а удаление модуля не оставляет миграций в чужих папках.
        $config->modify(
            section: MigrationConfig::CONFIG,
            patch: new Append(
                position: self::MIGRATION_VENDOR_DIRECTORIES,
                key: null,
                value: \sprintf(
                    '%s/Infrastructure/Persistence/Cycle/Migration',
                    \dirname(path: __DIR__, levels: 3),
                ),
            ),
        );

        // Типизированный конфиг модуля лежит внутри модуля: удаление модуля не оставляет
        // конфигурации в чужих папках.
        $configBootloader->addConfigurationDirectory(
            directory: \sprintf('%s/Infrastructure/Spiral/Configuration', \dirname(path: __DIR__, levels: 3)),
        );
        $config->setDefaults(
            section: 'outbox',
            data: ConfigArrayFile::read(path: \sprintf(
                '%s/Infrastructure/Spiral/Configuration/outbox.php',
                \dirname(path: __DIR__, levels: 3),
            )),
        );
    }

    public function boot(IntegrationEventRoutingContract $integrationEventRouting): void
    {
        $integrationEventRouting->register(
            integrationEventClass: OutboxDebugLogRequestedEvent::class,
            jobClass: OutboxDebugLogJob::class,
        );
    }
}
