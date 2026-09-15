<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Infrastructure\Spiral\Bootloader;

use App\Modules\Notifications\Application\Contract\CentrifugoServiceContract;
use App\Modules\Notifications\Application\Contract\FcmPushSenderContract;
use App\Modules\Notifications\Application\Contract\NotificationBulkWriterContract;
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
use App\Modules\Notifications\Infrastructure\Persistence\Cycle\NotificationBulkWriter;
use App\Modules\Notifications\Infrastructure\Client\KreaitFcmPushSender;
use App\Modules\Notifications\Infrastructure\Spiral\PublicApi\NotificationProvider;
use App\Modules\Notifications\Infrastructure\Spiral\PublicApi\NotificationTypeRegistryProvider;
use App\Modules\Notifications\Infrastructure\Spiral\Registry\NotificationTypeRegistry;
use App\Modules\Notifications\Infrastructure\Spiral\Job\DispatchNotificationJob;
use App\Modules\Notifications\Infrastructure\Spiral\Job\PublishRealtimeNotificationJob;
use App\Modules\Notifications\Infrastructure\Spiral\Job\SendPushNotificationJob;
use App\Modules\Outbox\Public\Contract\IntegrationEventRoutingContract;
use App\Shared\Infrastructure\Spiral\Configuration\Centrifugo\CentrifugoConfig;
use App\Shared\Infrastructure\Spiral\Configuration\Push\PushConfig;
use GuzzleHttp\Client;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Factory;
use Spiral\Boot\Bootloader\Bootloader;

/**
 * Бутлоадер модуля Notifications. Публичные контракты отправки и регистрации видов связаны с
 * адаптерами из Infrastructure/Spiral/PublicApi. Каталог видов — синглтон (stateful, накапливает
 * регистрации модулей-источников). PSR-18 HTTP-клиент Centrifugo и FCM Messaging создаются лениво (фабрики
 * ниже): реальный service-account FCM нужен только при фактической отправке, не на старте/в тестах
 * с дублёрами. PSR-18 клиент собирается под Centrifugo внутри фабрики centrifugoClient(), а не
 * биндится на глобальный ClientInterface — модуль не занимает общий интерфейс и не навязывает свою
 * конфигурацию Guzzle другим модулям. Регистрируется в Kernel после Outbox-бутлоадеров, т.к. boot()
 * (пары событие -> Job) использует IntegrationEventRoutingContract из OutboxBootloader.
 */
final class NotificationsBootloader extends Bootloader
{
    protected const BINDINGS = [
        NotificationContract::class => NotificationProvider::class,
        NotificationTypeRegistryContract::class => NotificationTypeRegistryProvider::class,
        NotificationBulkWriterContract::class => NotificationBulkWriter::class,
        CentrifugoServiceContract::class => CentrifugoService::class,
        FcmPushSenderContract::class => KreaitFcmPushSender::class,
        OnlinePresenceContract::class => CentrifugoOnlinePresence::class,
        CentrifugoClient::class => [self::class, 'centrifugoClient'],
    ];

    protected const SINGLETONS = [
        NotificationTypeCatalogContract::class => NotificationTypeRegistry::class,
        Messaging::class => [self::class, 'fcmMessaging'],
    ];

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
