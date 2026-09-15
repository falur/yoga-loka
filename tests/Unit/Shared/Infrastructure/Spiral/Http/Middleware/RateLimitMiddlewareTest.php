<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Spiral\Http\Middleware;

use App\Shared\Infrastructure\Spiral\Http\Middleware\RateLimitMiddleware;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\SimpleCache\CacheInterface;
use Spiral\Cache\CacheStorageProviderInterface;
use Symfony\Component\Clock\MockClock;
use Spiral\Translator\TranslatorInterface;

final class RateLimitMiddlewareTest extends TestCase
{
    public function testAllowsRequestsWithinLimitAndBlocksOverLimit(): void
    {
        $clock = new MockClock();
        $middleware = $this->middleware(maxAttempts: 2, cache: new RecordingCache($clock), clock: $clock);
        $request = $this->request(clientIp: '10.0.0.1', path: '/api/v1/auth/code/request');

        self::assertSame(200, $middleware->process($request, $this->handler())->getStatusCode());
        self::assertSame(200, $middleware->process($request, $this->handler())->getStatusCode());

        $blocked = $middleware->process($request, $this->handler());

        self::assertSame(429, $blocked->getStatusCode());
        $body = (string) $blocked->getBody();
        self::assertStringContainsString('"code":429', $body);
        self::assertStringContainsString('Слишком много запросов', $body);
    }

    public function testSeparatesCountersByClientIp(): void
    {
        $clock = new MockClock();
        $middleware = $this->middleware(maxAttempts: 1, cache: new RecordingCache($clock), clock: $clock);

        self::assertSame(200, $middleware->process($this->request('1.1.1.1', '/p'), $this->handler())->getStatusCode());
        self::assertSame(429, $middleware->process($this->request('1.1.1.1', '/p'), $this->handler())->getStatusCode());
        self::assertSame(200, $middleware->process($this->request('2.2.2.2', '/p'), $this->handler())->getStatusCode());
    }

    public function testSeparatesCountersByRoute(): void
    {
        $clock = new MockClock();
        $middleware = $this->middleware(maxAttempts: 1, cache: new RecordingCache($clock), clock: $clock);

        self::assertSame(200, $middleware->process($this->request('9.9.9.9', '/route-a'), $this->handler())->getStatusCode());
        self::assertSame(429, $middleware->process($this->request('9.9.9.9', '/route-a'), $this->handler())->getStatusCode());
        self::assertSame(200, $middleware->process($this->request('9.9.9.9', '/route-b'), $this->handler())->getStatusCode());
    }

    public function testWindowDoesNotExtendOnEachRequest(): void
    {
        $clock = new MockClock();
        $cache = new RecordingCache($clock);
        $middleware = $this->middleware(maxAttempts: 5, cache: $cache, clock: $clock);
        $request = $this->request('3.3.3.3', '/api/v1/auth/code/request');

        $middleware->process($request, $this->handler());
        $clock->sleep(20);
        $middleware->process($request, $this->handler());
        $clock->sleep(20);
        $middleware->process($request, $this->handler());

        // Фиксированное окно: TTL счётчика уменьшается к концу окна (60, затем 40, затем 20),
        // а не сбрасывается на perSeconds=60 при каждом запросе.
        self::assertSame([60, 40, 20], $cache->counterTtls());
    }

    public function testWindowResetsAfterExpiryUnderLimit(): void
    {
        $clock = new MockClock();
        $cache = new RecordingCache($clock);
        $middleware = $this->middleware(maxAttempts: 1, cache: $cache, clock: $clock);
        $request = $this->request('4.4.4.4', '/api/v1/auth/code/request');

        // Клиент держится под лимитом, обращаясь реже окна: после истечения окна не блокируется.
        self::assertSame(200, $middleware->process($request, $this->handler())->getStatusCode());

        $clock->sleep(61);

        self::assertSame(200, $middleware->process($request, $this->handler())->getStatusCode());
    }

    private function middleware(int $maxAttempts, CacheInterface $cache, ClockInterface $clock): RateLimitMiddleware
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturn('Слишком много запросов. Попробуйте позже.');

        $cacheStorageProvider = new class ($cache) implements CacheStorageProviderInterface {
            public function __construct(private readonly CacheInterface $cache) {}

            #[\Override]
            public function storage(string|null $name = null): CacheInterface
            {
                return $this->cache;
            }
        };

        return new RateLimitMiddleware(
            maxAttempts: $maxAttempts,
            perSeconds: 60,
            cacheStorageProvider: $cacheStorageProvider,
            translator: $translator,
            clock: $clock,
        );
    }

    private function handler(): RequestHandlerInterface
    {
        return new class implements RequestHandlerInterface {
            #[\Override]
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(status: 200, headers: [], body: 'ok');
            }
        };
    }

    private function request(string $clientIp, string $path): ServerRequestInterface
    {
        return new ServerRequest(method: 'POST', uri: $path, serverParams: ['REMOTE_ADDR' => $clientIp]);
    }
}
