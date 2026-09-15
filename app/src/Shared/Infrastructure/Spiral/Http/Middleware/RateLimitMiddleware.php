<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Spiral\Http\Middleware;

use GianTiaga\SpiralOpenApi\Response\Enum\HttpStatus;
use GianTiaga\SpiralOpenApi\Response\ErrorResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Clock\ClockInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\SimpleCache\CacheInterface;
use Spiral\Cache\CacheStorageProviderInterface;
use Spiral\Translator\TranslatorInterface;

/**
 * Переиспользуемое ограничение частоты по IP + маршрут. Навешивается per-route через Autowire
 * с аргументами maxAttempts/perSeconds. Счётчик в именованном Redis-хранилище (общий между
 * процессами middleware; дефолтный кэш в dev — local, поэтому берём redis явно). Превышение →
 * 429 JSON напрямую (middleware вне цепочки controller-интерсепторов, исключение тут не
 * сконвертируется). Незначительный перерасчёт при гонке get/set допустим.
 *
 * Фиксированное окно: момент истечения окна вычисляется один раз на первом запросе и хранится
 * под отдельным ключом с TTL=perSeconds, а счётчик записывается с оставшимся до истечения окна
 * TTL. Так окно истекает ровно через perSeconds после первого запроса и не продлевается
 * последующими запросами в его пределах.
 */
final readonly class RateLimitMiddleware implements MiddlewareInterface
{
    private const string STORAGE = 'redis';

    public function __construct(
        private int $maxAttempts,
        private int $perSeconds,
        private CacheStorageProviderInterface $cacheStorageProvider,
        private TranslatorInterface $translator,
        private ClockInterface $clock,
    ) {}

    #[\Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $cache = $this->cacheStorageProvider->storage(self::STORAGE);
        $key = $this->counterKey($request);
        $storedAttempts = $cache->get($key);
        $currentAttempts = \is_int($storedAttempts) ? $storedAttempts : 0;

        if ($currentAttempts >= $this->maxAttempts) {
            return $this->tooManyRequestsResponse();
        }

        $windowTtl = $this->windowTtl(cache: $cache, key: $key);
        $cache->set(key: $key, value: $currentAttempts + 1, ttl: $windowTtl);

        return $handler->handle($request);
    }

    /**
     * Возвращает TTL счётчика до конца текущего окна. На первом запросе фиксирует момент
     * истечения окна, далее переиспользует его, не продлевая окно.
     */
    private function windowTtl(CacheInterface $cache, string $key): int
    {
        $resetKey = $key . ':reset';
        $now = $this->clock->now()->getTimestamp();
        $storedResetAt = $cache->get($resetKey);
        $resetAt = \is_int($storedResetAt) && $storedResetAt > $now ? $storedResetAt : 0;

        if ($resetAt === 0) {
            $resetAt = $now + $this->perSeconds;
            $cache->set(key: $resetKey, value: $resetAt, ttl: $this->perSeconds);
        }

        return \max(1, $resetAt - $now);
    }

    private function counterKey(ServerRequestInterface $request): string
    {
        $remoteAddress = $request->getServerParams()['REMOTE_ADDR'] ?? '';
        $clientIp = \is_string($remoteAddress) ? $remoteAddress : '';

        return \hash(algo: 'sha256', data: $clientIp . '|' . $request->getUri()->getPath());
    }

    private function tooManyRequestsResponse(): ResponseInterface
    {
        $errorResponse = new ErrorResponse(
            message: $this->translator->trans(id: 'app.shared.rate_limit_exceeded', parameters: [], domain: 'shared'),
            code: HttpStatus::TooManyRequests->value,
        );

        return $errorResponse->withStatus(HttpStatus::TooManyRequests)->toResponse();
    }
}
