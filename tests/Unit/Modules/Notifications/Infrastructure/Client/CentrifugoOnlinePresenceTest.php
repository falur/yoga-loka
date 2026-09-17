<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Notifications\Infrastructure\Client;

use App\Modules\Notifications\Infrastructure\Client\CentrifugoClient;
use App\Modules\Notifications\Infrastructure\Client\CentrifugoOnlinePresence;
use App\Shared\Domain\ValueObject\UserId;
use App\Modules\Notifications\Infrastructure\Spiral\Configuration\CentrifugoConfig;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class CentrifugoOnlinePresenceTest extends TestCase
{
    public function testReturnsTrueAndQueriesPersonalChannelWhenClientsPresent(): void
    {
        $userId = UserId::generate();
        $psr17 = new Psr17Factory();
        $capturedRequest = null;

        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects(self::once())
            ->method('sendRequest')
            ->willReturnCallback(function (RequestInterface $request) use (&$capturedRequest, $psr17): ResponseInterface {
                $capturedRequest = $request;

                return $psr17->createResponse(200)
                    ->withBody($psr17->createStream('{"result":{"num_clients":1,"num_users":1}}'));
            });

        $online = $this->presence($httpClient, new NullLogger())->isOnline($userId);

        self::assertTrue($online);
        self::assertInstanceOf(RequestInterface::class, $capturedRequest);

        $body = \json_decode((string) $capturedRequest->getBody(), associative: true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame(\sprintf('personal:#user_%s', $userId->value()), $body['channel']);
    }

    public function testReturnsFalseWhenNoClientsPresent(): void
    {
        $online = $this->presence($this->respondingWith('{"result":{"num_clients":0,"num_users":0}}'), new NullLogger())
            ->isOnline(UserId::generate());

        self::assertFalse($online);
    }

    public function testReturnsFalseAndLogsWarningWhenPresenceFails(): void
    {
        $httpClient = $this->createStub(ClientInterface::class);
        $httpClient->method('sendRequest')->willThrowException(
            new class ('boom') extends \RuntimeException implements ClientExceptionInterface {},
        );

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');

        self::assertFalse($this->presence($httpClient, $logger)->isOnline(UserId::generate()));
    }

    private function presence(ClientInterface $httpClient, LoggerInterface $logger): CentrifugoOnlinePresence
    {
        return new CentrifugoOnlinePresence(
            centrifugoClient: new CentrifugoClient(
                httpClient: $httpClient,
                config: new CentrifugoConfig(apiUrl: 'http://centrifugo:8000/api', apiKey: 'dev-key'),
            ),
            logger: $logger,
        );
    }

    private function respondingWith(string $body): ClientInterface
    {
        $psr17 = new Psr17Factory();
        $httpClient = $this->createStub(ClientInterface::class);
        $httpClient->method('sendRequest')->willReturn(
            $psr17->createResponse(200)->withBody($psr17->createStream($body)),
        );

        return $httpClient;
    }
}
