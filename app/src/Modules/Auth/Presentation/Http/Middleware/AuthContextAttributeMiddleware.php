<?php

declare(strict_types=1);

namespace App\Modules\Auth\Presentation\Http\Middleware;

use App\Modules\Auth\Domain\Enum\AuthTokenType;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Spiral\Auth\AuthContextInterface;

/**
 * Кладёт authUserId/authSessionId из payload access-токена в request-атрибуты, чтобы контроллер
 * читал их через Filter #[Attribute], а не ServerRequestInterface. При отсутствии/битом payload
 * или для refresh-токена атрибуты не ставятся (и middleware не падает).
 */
final readonly class AuthContextAttributeMiddleware implements MiddlewareInterface
{
    public const string ATTRIBUTE_USER_ID = 'authUserId';
    public const string ATTRIBUTE_SESSION_ID = 'authSessionId';

    public function __construct(
        private AuthContextInterface $authContext,
    ) {}

    #[\Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $token = $this->authContext->getToken();

        if ($token === null) {
            return $handler->handle($request);
        }

        $payload = $token->getPayload();

        if (($payload['type'] ?? null) !== AuthTokenType::Access->value) {
            return $handler->handle($request);
        }

        $userId = $payload['userID'] ?? null;
        $sessionId = $payload['sessionID'] ?? null;

        if (\is_string($userId) && \is_string($sessionId)) {
            // PSR-7 ServerRequestInterface::withAttribute объявляет первый параметр как $name,
            // но конкретные реализации (Nyholm — $attribute) расходятся в имени, поэтому named
            // arguments невозможны и используются позиционные (правило погашено в phpstan.neon).
            $request = $request
                ->withAttribute(self::ATTRIBUTE_USER_ID, $userId)
                ->withAttribute(self::ATTRIBUTE_SESSION_ID, $sessionId);
        }

        return $handler->handle($request);
    }
}
