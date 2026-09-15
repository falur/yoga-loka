<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Auth\Infrastructure\Spiral\Http\Middleware;

use App\Modules\Auth\Domain\Enum\AuthTokenType;
use App\Modules\Auth\Domain\ValueObject\SessionId;
use App\Modules\Auth\Infrastructure\Spiral\Auth\AuthTokenView;
use App\Modules\Auth\Infrastructure\Spiral\Http\Middleware\AuthContextAttributeMiddleware;
use App\Shared\Domain\ValueObject\UserId;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Spiral\Auth\AuthContextInterface;
use Spiral\Auth\TokenInterface;

final class AuthContextAttributeMiddlewareTest extends TestCase
{
    public function testSetsAttributesForAccessToken(): void
    {
        $userId = UserId::generate();
        $sessionId = SessionId::generate();
        $request = $this->process(new AuthTokenView(
            id: 'raw',
            userId: $userId,
            type: AuthTokenType::Access,
            sessionId: $sessionId,
            expiresAt: null,
        ));

        self::assertSame($userId->value(), $request->getAttribute('authUserId'));
        self::assertSame($sessionId->value(), $request->getAttribute('authSessionId'));
    }

    public function testSkipsWhenNoToken(): void
    {
        self::assertNull($this->process(null)->getAttribute('authUserId'));
    }

    public function testSkipsRefreshToken(): void
    {
        $request = $this->process(new AuthTokenView(
            id: 'raw',
            userId: UserId::generate(),
            type: AuthTokenType::Refresh,
            sessionId: SessionId::generate(),
            expiresAt: null,
        ));

        self::assertNull($request->getAttribute('authUserId'));
    }

    public function testSkipsAccessTokenWithoutUserId(): void
    {
        // AuthTokenView всегда несёт userID, поэтому payload без userID приходит только от чужой
        // реализации TokenInterface — на ней и проверяется defensive-гард middleware.
        $token = $this->createStub(TokenInterface::class);
        $token->method('getPayload')->willReturn(['type' => AuthTokenType::Access->value, 'sessionID' => 'session-1']);

        $request = $this->process($token);

        self::assertNull($request->getAttribute('authUserId'));
        self::assertNull($request->getAttribute('authSessionId'));
    }

    private function process(TokenInterface|null $token): ServerRequestInterface
    {
        $authContext = new class ($token) implements AuthContextInterface {
            public function __construct(private readonly TokenInterface|null $token) {}

            public function start(TokenInterface $token, string|null $transport = null): void {}

            public function getToken(): TokenInterface|null
            {
                return $this->token;
            }

            public function getTransport(): string|null
            {
                return null;
            }

            public function getActor(): object|null
            {
                return null;
            }

            public function close(): void {}

            public function isClosed(): bool
            {
                return false;
            }
        };

        $captureHandler = new class implements RequestHandlerInterface {
            public ServerRequestInterface|null $request = null;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->request = $request;

                return new Response(status: 200);
            }
        };

        new AuthContextAttributeMiddleware(authContext: $authContext)
            ->process(new ServerRequest(method: 'POST', uri: '/api/v1/auth/logout'), $captureHandler);

        return $captureHandler->request ?? throw new \RuntimeException('Обработчик не был вызван.');
    }
}
