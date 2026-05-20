<?php

declare(strict_types=1);

namespace Tools\ApiError\Tests\Middleware;

use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;
use Spiral\Router\Exception\RouteNotFoundException;
use Tools\ApiError\Middleware\RouteNotFoundMiddleware;
use Tools\ApiError\Tests\Support\FakeTranslator;
use Tools\OpenApi\Response\Enum\ContentType;
use Tools\OpenApi\Response\Enum\HttpHeader;
use Tools\OpenApi\Response\Enum\HttpStatus;

final class RouteNotFoundMiddlewareTest extends TestCase
{
    public function testRouteNotFoundExceptionReturnsEnglishJson404(): void
    {
        $this->assertRouteNotFoundResponse(
            translator: self::englishTranslator(),
            expectedBody: '{"message":"Route not found.","code":404}',
        );
    }

    public function testRouteNotFoundExceptionReturnsRussianJson404(): void
    {
        $this->assertRouteNotFoundResponse(
            translator: new FakeTranslator(
                locale: 'ru',
                messages: [
                    'yoga_loka.api_error.route_not_found' => 'Маршрут не найден.',
                ],
            ),
            expectedBody: '{"message":"Маршрут не найден.","code":404}',
        );
    }

    private function assertRouteNotFoundResponse(FakeTranslator $translator, string $expectedBody): void
    {
        $request = new ServerRequest(method: 'GET', uri: '/missing');

        $response = (new RouteNotFoundMiddleware(
            logger: new NullLogger(),
            translator: $translator,
        ))->process(
            request: $request,
            handler: new RouteNotFoundMiddlewareFixtureHandler(
                response: new Response(),
                exception: new RouteNotFoundException(uri: $request->getUri()),
            ),
        );

        self::assertSame(HttpStatus::NotFound->value, $response->getStatusCode());
        self::assertSame(ContentType::Json->value, $response->getHeaderLine(HttpHeader::ContentType->value));
        self::assertSame($expectedBody, (string) $response->getBody());
    }

    public function testSuccessfulResponsePassesThrough(): void
    {
        $expectedResponse = new Response(status: HttpStatus::Accepted->value, body: 'ok');

        $response = (new RouteNotFoundMiddleware(
            logger: new NullLogger(),
            translator: self::englishTranslator(),
        ))->process(
            request: new ServerRequest(method: 'GET', uri: '/exists'),
            handler: new RouteNotFoundMiddlewareFixtureHandler(response: $expectedResponse),
        );

        self::assertSame($expectedResponse, $response);
    }

    public function testOtherThrowableIsNotCaught(): void
    {
        $middleware = new RouteNotFoundMiddleware(
            logger: new NullLogger(),
            translator: self::englishTranslator(),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Сервис недоступен');

        $middleware->process(
            request: new ServerRequest(method: 'GET', uri: '/broken'),
            handler: new RouteNotFoundMiddlewareFixtureHandler(
                response: new Response(),
                exception: new \RuntimeException(message: 'Сервис недоступен'),
            ),
        );
    }

    public function testDebugLogDoesNotContainRequestValues(): void
    {
        $logger = new RouteNotFoundMiddlewareRecordingLogger();
        $request = new ServerRequest(
            method: 'POST',
            uri: '/hidden?token=query-secret',
            headers: [
                'Authorization' => 'Bearer header-secret',
                'Cookie' => 'session=cookie-secret',
            ],
            body: 'body-secret',
        );

        (new RouteNotFoundMiddleware(
            logger: $logger,
            translator: self::englishTranslator(),
        ))->process(
            request: $request,
            handler: new RouteNotFoundMiddlewareFixtureHandler(
                response: new Response(),
                exception: new RouteNotFoundException(uri: $request->getUri()),
            ),
        );

        self::assertCount(1, $logger->records());

        $record = $logger->records()[0];

        self::assertSame(LogLevel::DEBUG, $record->level);
        self::assertSame('HTTP-маршрут не найден.', $record->message);
        self::assertSame([
            'method' => 'POST',
            'path' => '/hidden',
            'status' => HttpStatus::NotFound->value,
            'exceptionClass' => RouteNotFoundException::class,
        ], $record->context);

        $logContext = \json_encode(value: $record->context, flags: JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        self::assertStringNotContainsString('query-secret', $logContext);
        self::assertStringNotContainsString('body-secret', $logContext);
        self::assertStringNotContainsString('cookie-secret', $logContext);
        self::assertStringNotContainsString('header-secret', $logContext);
    }

    private static function englishTranslator(): FakeTranslator
    {
        return new FakeTranslator(
            locale: 'en',
            messages: [
                'yoga_loka.api_error.route_not_found' => 'Route not found.',
            ],
        );
    }
}

final readonly class RouteNotFoundMiddlewareFixtureHandler implements RequestHandlerInterface
{
    public function __construct(
        private ResponseInterface $response,
        private ?\Throwable $exception = null,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->exception !== null) {
            throw $this->exception;
        }

        return $this->response;
    }
}

final class RouteNotFoundMiddlewareRecordingLogger extends AbstractLogger
{
    /** @var list<RouteNotFoundMiddlewareLogRecord> */
    private array $records = [];

    /**
     * @param mixed $level
     * @param array<string, mixed> $context
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        if (!\is_string($level) && !$level instanceof \Stringable) {
            throw new \InvalidArgumentException(message: 'Некорректный уровень лога.');
        }

        $this->records[] = new RouteNotFoundMiddlewareLogRecord(
            level: (string) $level,
            message: (string) $message,
            context: $this->normalizeContext(context: $context),
        );
    }

    /**
     * @return list<RouteNotFoundMiddlewareLogRecord>
     */
    public function records(): array
    {
        return $this->records;
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, int|string>
     */
    private function normalizeContext(array $context): array
    {
        $normalizedContext = [];

        foreach ($context as $key => $value) {
            if (!\is_int($value) && !\is_string($value)) {
                continue;
            }

            $normalizedContext[$key] = $value;
        }

        return $normalizedContext;
    }
}

final readonly class RouteNotFoundMiddlewareLogRecord
{
    /**
     * @param array<string, int|string> $context
     */
    public function __construct(
        public string $level,
        public string $message,
        public array $context,
    ) {}
}
