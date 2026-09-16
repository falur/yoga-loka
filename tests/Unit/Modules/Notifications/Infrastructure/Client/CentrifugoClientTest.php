<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Notifications\Infrastructure\Client;

use App\Modules\Notifications\Public\Dto\NotificationActionDto;
use App\Modules\Notifications\Application\Contract\RealtimeActorPayload;
use App\Modules\Notifications\Application\Contract\RealtimeMediaOriginalPayload;
use App\Modules\Notifications\Application\Contract\RealtimeMediaPayload;
use App\Modules\Notifications\Application\Contract\RealtimeNotificationPayload;
use App\Modules\Notifications\Application\Exception\CentrifugoPublishException;
use App\Modules\Notifications\Infrastructure\Client\CentrifugoClient;
use App\Modules\Notifications\Infrastructure\Client\CentrifugoPresenceException;
use App\Shared\Infrastructure\Spiral\Configuration\Centrifugo\CentrifugoConfig;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use PHPUnit\Framework\TestCase;

final class CentrifugoClientTest extends TestCase
{
    public function testPublishSendsSignedRequestToPublishEndpoint(): void
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

        $this->client($httpClient)->publish(
            'personal:#user_1',
            new RealtimeNotificationPayload(
                type: 'chat.message_received',
                title: 'Новое сообщение',
                body: 'Вам пришло сообщение',
                action: new NotificationActionDto(actionType: 'chat', actionId: '42'),
                actor: new RealtimeActorPayload(
                    id: 'actor-1',
                    name: 'Иван',
                    avatar: new RealtimeMediaPayload(
                        id: 'media-1',
                        position: null,
                        original: new RealtimeMediaOriginalPayload(url: 'https://cdn/a.jpg', expiresAt: null),
                        conversions: [],
                    ),
                ),
                createdAt: '2026-06-13T10:00:00+00:00',
            ),
        );

        self::assertInstanceOf(RequestInterface::class, $capturedRequest);
        self::assertSame('POST', $capturedRequest->getMethod());
        self::assertSame('http://centrifugo:8000/api/publish', (string) $capturedRequest->getUri());
        self::assertSame('apikey dev-key', $capturedRequest->getHeaderLine('Authorization'));

        $body = \json_decode((string) $capturedRequest->getBody(), associative: true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('personal:#user_1', $body['channel']);
        self::assertSame('chat.message_received', $body['data']['type']);
        self::assertSame('chat', $body['data']['action']['actionType']);
        self::assertSame('42', $body['data']['action']['actionId']);
        self::assertSame('actor-1', $body['data']['actor']['id']);
        self::assertSame('Иван', $body['data']['actor']['name']);
        // Аватар автора едет полным медиа (та же форма, что в HTTP-ответе инбокса), а не одной ссылкой.
        self::assertSame('media-1', $body['data']['actor']['avatar']['id']);
        self::assertSame('https://cdn/a.jpg', $body['data']['actor']['avatar']['original']['url']);
        self::assertNull($body['data']['actor']['avatar']['original']['expiresAt']);
        self::assertSame([], $body['data']['actor']['avatar']['conversions']);
    }

    public function testTransportFailureIsTransient(): void
    {
        $httpClient = $this->createStub(ClientInterface::class);
        $httpClient->method('sendRequest')->willThrowException(
            new class ('boom') extends \RuntimeException implements ClientExceptionInterface {},
        );

        $exception = $this->capture($httpClient);
        self::assertTrue($exception->isTransient());
    }

    public function testServerErrorIsTransient(): void
    {
        $exception = $this->capture($this->respondingWith(503));
        self::assertTrue($exception->isTransient());
    }

    public function testClientErrorIsTerminal(): void
    {
        $exception = $this->capture($this->respondingWith(400));
        self::assertFalse($exception->isTransient());
    }

    public function testApiErrorInBodyOnStatus200IsTerminal(): void
    {
        $exception = $this->capture(
            $this->respondingWith(200, '{"error":{"code":102,"message":"unknown channel"}}'),
        );

        self::assertFalse($exception->isTransient());
        self::assertStringContainsString('102', $exception->getMessage());
        self::assertStringContainsString('unknown channel', $exception->getMessage());
    }

    public function testInternalApiErrorInBodyOnStatus200IsTransient(): void
    {
        $exception = $this->capture(
            $this->respondingWith(200, '{"error":{"code":100,"message":"internal server error"}}'),
        );

        self::assertTrue($exception->isTransient());
    }

    public function testSuccessfulResultBodyDoesNotThrow(): void
    {
        $this->client($this->respondingWith(200, '{"result":{"offset":1,"epoch":"xyz"}}'))
            ->publish('personal:#user_1', $this->payload());

        $this->addToAssertionCount(1);
    }

    public function testPresenceStatsSendsRequestToPresenceEndpoint(): void
    {
        $psr17 = new Psr17Factory();
        $capturedRequest = null;

        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects(self::once())
            ->method('sendRequest')
            ->willReturnCallback(function (RequestInterface $request) use (&$capturedRequest, $psr17): ResponseInterface {
                $capturedRequest = $request;

                return $psr17->createResponse(200)
                    ->withBody($psr17->createStream('{"result":{"num_clients":2,"num_users":1}}'));
            });

        $stats = $this->client($httpClient)->presenceStats('personal:#user_1');

        self::assertSame(2, $stats->numClients);
        self::assertInstanceOf(RequestInterface::class, $capturedRequest);
        self::assertSame('POST', $capturedRequest->getMethod());
        self::assertSame('http://centrifugo:8000/api/presence_stats', (string) $capturedRequest->getUri());
        self::assertSame('apikey dev-key', $capturedRequest->getHeaderLine('Authorization'));

        $body = \json_decode((string) $capturedRequest->getBody(), associative: true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('personal:#user_1', $body['channel']);
    }

    public function testPresenceTransportFailureIsWrapped(): void
    {
        $httpClient = $this->createStub(ClientInterface::class);
        $httpClient->method('sendRequest')->willThrowException(
            new class ('boom') extends \RuntimeException implements ClientExceptionInterface {},
        );

        self::assertStringContainsString('недоступен', $this->capturePresence($httpClient)->getMessage());
    }

    public function testPresenceServerErrorIsWrapped(): void
    {
        self::assertStringContainsString('503', $this->capturePresence($this->respondingWith(503))->getMessage());
    }

    public function testPresenceRequestRejectedIsWrapped(): void
    {
        self::assertStringContainsString('400', $this->capturePresence($this->respondingWith(400))->getMessage());
    }

    public function testPresenceApiErrorInBodyIsWrapped(): void
    {
        $exception = $this->capturePresence(
            $this->respondingWith(200, '{"error":{"code":102,"message":"unknown channel"}}'),
        );

        self::assertStringContainsString('102', $exception->getMessage());
        self::assertStringContainsString('unknown channel', $exception->getMessage());
    }

    public function testPresenceMalformedStructureIsWrapped(): void
    {
        $exception = $this->capturePresence($this->respondingWith(200, '{"result":{}}'));

        self::assertStringContainsString('некорректный', $exception->getMessage());
    }

    public function testPresenceBrokenJsonIsWrapped(): void
    {
        $exception = $this->capturePresence($this->respondingWith(200, '{not json'));

        self::assertStringContainsString('некорректный', $exception->getMessage());
    }

    public function testPresenceNonArrayJsonIsWrapped(): void
    {
        $exception = $this->capturePresence($this->respondingWith(200, '42'));

        self::assertStringContainsString('некорректный', $exception->getMessage());
    }

    private function capturePresence(ClientInterface $httpClient): CentrifugoPresenceException
    {
        try {
            $this->client($httpClient)->presenceStats('personal:#user_1');
        } catch (CentrifugoPresenceException $exception) {
            return $exception;
        }

        self::fail('Ожидалось CentrifugoPresenceException.');
    }

    private function respondingWith(int $statusCode, string $body = ''): ClientInterface
    {
        $psr17 = new Psr17Factory();
        $httpClient = $this->createStub(ClientInterface::class);
        $httpClient->method('sendRequest')->willReturn(
            $psr17->createResponse($statusCode)->withBody($psr17->createStream($body)),
        );

        return $httpClient;
    }

    private function capture(ClientInterface $httpClient): CentrifugoPublishException
    {
        try {
            $this->client($httpClient)->publish('personal:#user_1', $this->payload());
        } catch (CentrifugoPublishException $exception) {
            return $exception;
        }

        self::fail('Ожидалось CentrifugoPublishException.');
    }

    private function client(ClientInterface $httpClient): CentrifugoClient
    {
        return new CentrifugoClient(
            httpClient: $httpClient,
            config: new CentrifugoConfig(apiUrl: 'http://centrifugo:8000/api', apiKey: 'dev-key'),
        );
    }

    private function payload(): RealtimeNotificationPayload
    {
        return new RealtimeNotificationPayload(
            type: 'chat.message_received',
            title: 'Новое сообщение',
            body: 'Вам пришло сообщение',
            action: null,
            actor: null,
            createdAt: '2026-06-13T10:00:00+00:00',
        );
    }
}
