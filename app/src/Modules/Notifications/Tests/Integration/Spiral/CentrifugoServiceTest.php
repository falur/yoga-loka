<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Tests\Integration\Spiral;

use App\Modules\Notifications\Application\Contract\RealtimeNotificationPayload;
use App\Modules\Notifications\Infrastructure\Client\CentrifugoClient;
use App\Modules\Notifications\Infrastructure\Client\CentrifugoService;
use App\Modules\Notifications\Infrastructure\Spiral\Configuration\CentrifugoConfig;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use PHPUnit\Framework\TestCase;

final class CentrifugoServiceTest extends TestCase
{
    public function testPublishDelegatesToClient(): void
    {
        $psr17 = new Psr17Factory();
        $capturedRequest = null;

        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects(self::once())
            ->method('sendRequest')
            ->willReturnCallback(function (RequestInterface $request) use (&$capturedRequest, $psr17): ResponseInterface {
                $capturedRequest = $request;

                return $psr17->createResponse(200);
            });

        $centrifugoClient = new CentrifugoClient(
            httpClient: $httpClient,
            config: new CentrifugoConfig(apiUrl: 'http://centrifugo:8000/api', apiKey: 'dev-key'),
        );

        new CentrifugoService($centrifugoClient)->publish(
            'personal:#user_1',
            new RealtimeNotificationPayload(
                type: 'chat.message_received',
                title: 'Новое сообщение',
                body: 'Вам пришло сообщение',
                action: null,
                actor: null,
                createdAt: '2026-06-13T10:00:00+00:00',
            ),
        );

        self::assertInstanceOf(RequestInterface::class, $capturedRequest);
        self::assertSame('http://centrifugo:8000/api/publish', (string) $capturedRequest->getUri());
    }
}
