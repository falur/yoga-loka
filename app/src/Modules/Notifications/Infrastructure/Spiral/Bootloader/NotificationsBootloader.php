<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Infrastructure\Spiral\Bootloader;

use App\Modules\Notifications\Application\Contract\CentrifugoServiceContract;
use App\Modules\Notifications\Application\Contract\FcmPushSenderContract;
use App\Modules\Notifications\Application\Contract\NotificationBulkWriterContract;
use App\Modules\Notifications\Application\Contract\NotificationSenderContract;
use App\Modules\Notifications\Application\Contract\NotificationTypeRegistryContract;
use App\Modules\Notifications\Application\Contract\OnlinePresenceContract;
use App\Modules\Notifications\Application\Message\NotificationPushRequested;
use App\Modules\Notifications\Application\Message\NotificationRealtimeRequested;
use App\Modules\Notifications\Application\Message\NotificationRequested;
use App\Modules\Notifications\Application\NotificationSender;
use App\Modules\Notifications\Infrastructure\Client\CentrifugoClient;
use App\Modules\Notifications\Infrastructure\Client\CentrifugoOnlinePresence;
use App\Modules\Notifications\Infrastructure\Client\CentrifugoService;
use App\Modules\Notifications\Infrastructure\Persistence\Cycle\NotificationBulkWriter;
use App\Modules\Notifications\Infrastructure\Client\KreaitFcmPushSender;
use App\Modules\Notifications\Infrastructure\Spiral\Registry\NotificationTypeRegistry;
use App\Modules\Notifications\Infrastructure\Spiral\Job\DispatchNotificationJob;
use App\Modules\Notifications\Infrastructure\Spiral\Job\PublishRealtimeNotificationJob;
use App\Modules\Notifications\Infrastructure\Spiral\Job\SendPushNotificationJob;
use App\Modules\Outbox\Application\Contract\OutboxJobRegistryContract;
use App\Shared\Infrastructure\Spiral\Configuration\Centrifugo\CentrifugoConfig;
use App\Shared\Infrastructure\Spiral\Configuration\Push\PushConfig;
use GuzzleHttp\Client;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Factory;
use Spiral\Boot\Bootloader\Bootloader;

/**
 * Бутлоадер модуля Notifications. Реестр видов — синглтон (stateful, накапливает регистрации
 * модулей-источников). PSR-18 HTTP-клиент Centrifugo и FCM Messaging создаются лениво (фабрики
 * ниже): реальный service-account FCM нужен только при фактической отправке, не на старте/в тестах
 * с дублёрами. PSR-18 клиент собирается под Centrifugo внутри фабрики centrifugoClient(), а не
 * биндится на глобальный ClientInterface — модуль не занимает общий интерфейс и не навязывает свою
 * конфигурацию Guzzle другим модулям. Регистрируется в Kernel после Outbox-бутлоадеров, т.к. boot()
 * (пары message -> Job) использует OutboxJobRegistryContract из OutboxBootloader.
 */
final class NotificationsBootloader extends Bootloader
{
    protected const BINDINGS = [
        NotificationSenderContract::class => NotificationSender::class,
        NotificationBulkWriterContract::class => NotificationBulkWriter::class,
        CentrifugoServiceContract::class => CentrifugoService::class,
        FcmPushSenderContract::class => KreaitFcmPushSender::class,
        OnlinePresenceContract::class => CentrifugoOnlinePresence::class,
        CentrifugoClient::class => [self::class, 'centrifugoClient'],
    ];

    protected const SINGLETONS = [
        NotificationTypeRegistryContract::class => NotificationTypeRegistry::class,
        Messaging::class => [self::class, 'fcmMessaging'],
    ];

    public function boot(OutboxJobRegistryContract $outboxJobRegistry): void
    {
        $outboxJobRegistry->register(
            outboxMessageClass: NotificationRequested::class,
            outboxJobClass: DispatchNotificationJob::class,
        );
        $outboxJobRegistry->register(
            outboxMessageClass: NotificationPushRequested::class,
            outboxJobClass: SendPushNotificationJob::class,
        );
        $outboxJobRegistry->register(
            outboxMessageClass: NotificationRealtimeRequested::class,
            outboxJobClass: PublishRealtimeNotificationJob::class,
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
