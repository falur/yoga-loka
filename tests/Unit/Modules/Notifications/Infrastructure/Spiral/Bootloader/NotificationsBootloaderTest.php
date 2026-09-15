<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Notifications\Infrastructure\Spiral\Bootloader;

use App\Modules\Notifications\Application\Contract\OnlinePresenceContract;
use App\Modules\Notifications\Infrastructure\Spiral\Bootloader\NotificationsBootloader;
use App\Modules\Notifications\Infrastructure\Client\CentrifugoClient;
use App\Modules\Notifications\Infrastructure\Client\CentrifugoOnlinePresence;
use App\Shared\Infrastructure\Spiral\Configuration\Centrifugo\CentrifugoConfig;
use App\Shared\Infrastructure\Spiral\Configuration\Push\PushConfig;
use Kreait\Firebase\Contract\Messaging;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Spiral\Core\Container;

final class NotificationsBootloaderTest extends TestCase
{
    public function testCentrifugoClientBuildsWithDedicatedHttpClient(): void
    {
        $centrifugoClient = new NotificationsBootloader()->centrifugoClient(
            new CentrifugoConfig(apiUrl: 'http://centrifugo:8000/api', apiKey: 'dev-key'),
        );

        self::assertInstanceOf(CentrifugoClient::class, $centrifugoClient);
    }

    public function testFcmMessagingBuildsFromServiceAccountFile(): void
    {
        $credentialsFile = $this->writeServiceAccountFile();

        try {
            $messaging = new NotificationsBootloader()->fcmMessaging(
                new PushConfig(projectId: 'test-project', credentialsFile: $credentialsFile),
            );

            self::assertInstanceOf(Messaging::class, $messaging);
        } finally {
            @\unlink($credentialsFile);
        }
    }

    public function testOnlinePresenceContractResolvesToCentrifugoImplementation(): void
    {
        $container = new Container();
        $container->bindSingleton(
            CentrifugoConfig::class,
            new CentrifugoConfig(apiUrl: 'http://centrifugo:8000/api', apiKey: 'dev-key'),
        );
        $container->bindSingleton(LoggerInterface::class, new NullLogger());

        foreach (new NotificationsBootloader()->defineBindings() as $contract => $concrete) {
            $container->bind($contract, $concrete);
        }

        self::assertInstanceOf(
            CentrifugoOnlinePresence::class,
            $container->get(OnlinePresenceContract::class),
        );
    }

    private function writeServiceAccountFile(): string
    {
        $key = \openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => \OPENSSL_KEYTYPE_RSA]);
        self::assertNotFalse($key);
        \openssl_pkey_export($key, $privateKey);

        $serviceAccount = \json_encode([
            'type' => 'service_account',
            'project_id' => 'test-project',
            'private_key_id' => 'test-key-id',
            'private_key' => $privateKey,
            'client_email' => 'test@test-project.iam.gserviceaccount.com',
            'client_id' => '1234567890',
            'auth_uri' => 'https://accounts.google.com/o/oauth2/auth',
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ], \JSON_THROW_ON_ERROR);

        $file = \tempnam(\sys_get_temp_dir(), 'fcm') . '.json';
        \file_put_contents($file, $serviceAccount);

        return $file;
    }
}
