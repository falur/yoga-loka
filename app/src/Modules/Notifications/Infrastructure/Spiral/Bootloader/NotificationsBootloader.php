<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Infrastructure\Spiral\Bootloader;

use App\Modules\Notifications\Application\Contract\CentrifugoServiceContract;
use App\Modules\Notifications\Application\Contract\FcmPushSenderContract;
use App\Modules\Notifications\Application\Contract\MarkAllNotificationsReadContract;
use App\Modules\Notifications\Application\Contract\NotificationTypeCatalogContract;
use App\Modules\Notifications\Application\Contract\OnlinePresenceContract;
use App\Modules\Notifications\Public\Event\NotificationPushRequestedEvent;
use App\Modules\Notifications\Public\Event\NotificationRealtimeRequestedEvent;
use App\Modules\Notifications\Public\Contract\NotificationContract;
use App\Modules\Notifications\Public\Contract\NotificationTypeRegistryContract;
use App\Modules\Notifications\Public\Event\NotificationRequestedEvent;
use App\Modules\Notifications\Infrastructure\Client\CentrifugoClient;
use App\Modules\Notifications\Infrastructure\Client\CentrifugoOnlinePresence;
use App\Modules\Notifications\Infrastructure\Client\CentrifugoService;
use App\Modules\Notifications\Domain\Repository\NotificationDeviceTokenRepository;
use App\Modules\Notifications\Domain\Repository\NotificationRepository;
use App\Modules\Notifications\Domain\Repository\NotificationSettingRepository;
use App\Modules\Notifications\Infrastructure\Persistence\Cycle\CycleMarkAllNotificationsRead;
use App\Modules\Notifications\Infrastructure\Persistence\Cycle\Repository\CycleNotificationDeviceTokenRepository;
use App\Modules\Notifications\Infrastructure\Persistence\Cycle\Repository\CycleNotificationRepository;
use App\Modules\Notifications\Infrastructure\Persistence\Cycle\Repository\CycleNotificationSettingRepository;
use App\Modules\Notifications\Infrastructure\Client\KreaitFcmPushSender;
use App\Modules\Notifications\Infrastructure\Spiral\PublicApi\NotificationProvider;
use App\Modules\Notifications\Infrastructure\Spiral\PublicApi\NotificationTypeRegistryProvider;
use App\Modules\Notifications\Infrastructure\Spiral\Registry\NotificationTypeRegistry;
use App\Modules\Notifications\Infrastructure\Spiral\Job\DispatchNotificationJob;
use App\Modules\Notifications\Infrastructure\Spiral\Job\PublishRealtimeNotificationJob;
use App\Modules\Notifications\Infrastructure\Spiral\Job\SendPushNotificationJob;
use App\Modules\Notifications\Infrastructure\Spiral\Configuration\CentrifugoConfig;
use App\Modules\Notifications\Infrastructure\Spiral\Configuration\PushConfig;
use App\Modules\Outbox\Public\Contract\IntegrationEventRoutingContract;
use App\Shared\Infrastructure\Spiral\Bootloader\ConfigBootloader;
use App\Shared\Infrastructure\Spiral\Configuration\ConfigArrayFile;
use Cycle\Migrations\Config\MigrationConfig;
use GuzzleHttp\Client;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Factory;
use Spiral\Boot\Bootloader\Bootloader;
use Spiral\Bootloader\I18nBootloader;
use Spiral\Config\ConfiguratorInterface;
use Spiral\Config\Patch\Append;

/**
 * Бутлоадер модуля Notifications. Доменные интерфейсы хранения трёх независимых корней агрегатов
 * связаны со своими Cycle-реализациями, а массовая отметка уведомлений получателя прочитанными
 * остаётся отдельным портом Application/Contract со своей set-based реализацией. Публичные
 * контракты отправки и регистрации видов связаны с адаптерами из Infrastructure/Spiral/PublicApi.
 * Каталог видов — синглтон (stateful, накапливает регистрации модулей-источников). PSR-18
 * HTTP-клиент Centrifugo и FCM Messaging создаются лениво (фабрики ниже): реальный
 * service-account FCM нужен только при фактической отправке, не на старте/в тестах
 * с дублёрами. PSR-18 клиент собирается под Centrifugo внутри фабрики centrifugoClient(), а не
 * биндится на глобальный ClientInterface — модуль не занимает общий интерфейс и не навязывает свою
 * конфигурацию Guzzle другим модулям. Регистрируется в Kernel после Outbox-бутлоадеров, т.к. boot()
 * (пары событие -> Job) использует IntegrationEventRoutingContract из OutboxBootloader.
 */
final class NotificationsBootloader extends Bootloader
{
    private const string MIGRATION_VENDOR_DIRECTORIES = 'vendorDirectories';

    protected const BINDINGS = [
        NotificationRepository::class => CycleNotificationRepository::class,
        NotificationSettingRepository::class => CycleNotificationSettingRepository::class,
        NotificationDeviceTokenRepository::class => CycleNotificationDeviceTokenRepository::class,
        NotificationContract::class => NotificationProvider::class,
        NotificationTypeRegistryContract::class => NotificationTypeRegistryProvider::class,
        MarkAllNotificationsReadContract::class => CycleMarkAllNotificationsRead::class,
        CentrifugoServiceContract::class => CentrifugoService::class,
        FcmPushSenderContract::class => KreaitFcmPushSender::class,
        OnlinePresenceContract::class => CentrifugoOnlinePresence::class,
        CentrifugoClient::class => [self::class, 'centrifugoClient'],
    ];

    protected const SINGLETONS = [
        NotificationTypeCatalogContract::class => NotificationTypeRegistry::class,
        Messaging::class => [self::class, 'fcmMessaging'],
    ];

    /** @param ConfiguratorInterface<object> $config */
    public function init(
        ConfiguratorInterface $config,
        I18nBootloader $i18n,
        ConfigBootloader $configBootloader,
    ): void {
        // Переводы модуля лежат внутри модуля: удаление модуля не оставляет переводов в чужих папках.
        $i18n->addDirectory(
            directory: \sprintf('%s/Infrastructure/Spiral/Resources/locale', \dirname(path: __DIR__, levels: 3)),
        );

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

        // Типизированные конфиги модуля (push, centrifugo) лежат внутри модуля: удаление модуля
        // не оставляет конфигурации в чужих папках.
        $configBootloader->addConfigurationDirectory(
            directory: \sprintf('%s/Infrastructure/Spiral/Configuration', \dirname(path: __DIR__, levels: 3)),
        );
        $config->setDefaults(
            section: 'push',
            data: ConfigArrayFile::read(path: \sprintf(
                '%s/Infrastructure/Spiral/Configuration/push.php',
                \dirname(path: __DIR__, levels: 3),
            )),
        );
        $config->setDefaults(
            section: 'centrifugo',
            data: ConfigArrayFile::read(path: \sprintf(
                '%s/Infrastructure/Spiral/Configuration/centrifugo.php',
                \dirname(path: __DIR__, levels: 3),
            )),
        );
    }

    public function boot(IntegrationEventRoutingContract $integrationEventRouting): void
    {
        $integrationEventRouting->register(
            integrationEventClass: NotificationRequestedEvent::class,
            jobClass: DispatchNotificationJob::class,
        );
        $integrationEventRouting->register(
            integrationEventClass: NotificationPushRequestedEvent::class,
            jobClass: SendPushNotificationJob::class,
        );
        $integrationEventRouting->register(
            integrationEventClass: NotificationRealtimeRequestedEvent::class,
            jobClass: PublishRealtimeNotificationJob::class,
        );
    }

    public function centrifugoClient(CentrifugoConfig $centrifugoConfig): CentrifugoClient
    {
        return new CentrifugoClient(httpClient: new Client(), config: $centrifugoConfig);
    }

    public function fcmMessaging(PushConfig $pushConfig): Messaging
    {
        return new Factory()
            ->withServiceAccount($pushConfig->credentialsFile)
            ->createMessaging();
    }
}
