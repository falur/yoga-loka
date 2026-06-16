<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Auth\Infrastructure\Auth;

use App\Modules\Auth\Domain\Enum\AuthTokenType;
use App\Modules\Auth\Domain\ValueObject\SessionId;
use App\Modules\Auth\Infrastructure\Auth\AuthenticatedUser;
use App\Modules\Auth\Infrastructure\Auth\AuthTokenView;
use App\Modules\Auth\Infrastructure\Auth\UserActorProvider;
use App\Shared\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;
use Spiral\Auth\TokenInterface;

final class UserActorProviderTest extends TestCase
{
    public function testReturnsActorForAccessToken(): void
    {
        $userId = UserId::generate();
        $token = new AuthTokenView(
            id: 'raw',
            userId: $userId,
            type: AuthTokenType::Access,
            sessionId: SessionId::generate(),
            expiresAt: null,
        );

        $actor = (new UserActorProvider())->getActor($token);

        self::assertInstanceOf(AuthenticatedUser::class, $actor);
        self::assertTrue($actor->userId->equals($userId));
    }

    public function testRejectsRefreshToken(): void
    {
        $token = new AuthTokenView(
            id: 'raw',
            userId: UserId::generate(),
            type: AuthTokenType::Refresh,
            sessionId: SessionId::generate(),
            expiresAt: null,
        );

        self::assertNull((new UserActorProvider())->getActor($token));
    }

    public function testRejectsAccessTokenWithoutUserId(): void
    {
        // AuthTokenView всегда несёт userID, поэтому payload без userID приходит только от чужой
        // реализации TokenInterface — на ней и проверяется defensive-гард провайдера.
        $token = $this->createStub(TokenInterface::class);
        $token->method('getPayload')->willReturn(['type' => AuthTokenType::Access->value]);

        self::assertNull((new UserActorProvider())->getActor($token));
    }
}
