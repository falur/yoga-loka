<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Auth\Infrastructure\Auth;

use App\Modules\Auth\Domain\Enum\AuthTokenType;
use App\Modules\Auth\Domain\ValueObject\SessionId;
use App\Modules\Auth\Infrastructure\Auth\AuthTokenView;
use App\Shared\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;

final class AuthTokenViewTest extends TestCase
{
    public function testExposesRawIdPayloadAndExpiry(): void
    {
        $expiresAt = new \DateTimeImmutable('2026-06-15 13:00:00');
        $userId = UserId::generate();
        $sessionId = SessionId::generate();
        $view = new AuthTokenView(
            id: 'raw-token',
            userId: $userId,
            type: AuthTokenType::Access,
            sessionId: $sessionId,
            expiresAt: $expiresAt,
        );

        self::assertSame('raw-token', $view->getID());
        self::assertSame(
            [
                'userID' => $userId->value(),
                'type' => AuthTokenType::Access->value,
                'sessionID' => $sessionId->value(),
            ],
            $view->getPayload(),
        );
        self::assertSame($expiresAt, $view->getExpiresAt());
    }

    public function testAllowsNullExpiry(): void
    {
        $view = new AuthTokenView(
            id: 'raw-token',
            userId: UserId::generate(),
            type: AuthTokenType::Access,
            sessionId: SessionId::generate(),
            expiresAt: null,
        );

        self::assertNull($view->getExpiresAt());
    }
}
