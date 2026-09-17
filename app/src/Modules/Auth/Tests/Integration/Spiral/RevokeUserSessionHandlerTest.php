<?php

declare(strict_types=1);

namespace App\Modules\Auth\Tests\Integration\Spiral;

use App\Modules\Auth\Application\Command\RevokeUserSession\RevokeUserSessionCommand;
use App\Modules\Auth\Application\Command\RevokeUserSession\RevokeUserSessionHandler;
use App\Modules\Auth\Domain\ValueObject\SessionDevice;
use App\Modules\Auth\Domain\ValueObject\SessionId;
use App\Modules\Auth\Domain\Exception\SessionNotFoundException;
use App\Shared\Domain\ValueObject\UserId;
use Psr\Log\NullLogger;
use Ramsey\Uuid\Uuid;
use Spiral\Auth\TokenInterface;

final class RevokeUserSessionHandlerTest extends AuthApplicationTestCase
{
    public function testRevokesOwnSessionTokens(): void
    {
        $userId = UserId::generate();
        $pair = $this->tokenStorage()->issuePair(userId: $userId, device: SessionDevice::unknown());
        $accessView = $this->spiralTokenStorage()->load($pair->accessToken);
        self::assertInstanceOf(TokenInterface::class, $accessView);

        $this->handler()->handle(new RevokeUserSessionCommand(
            userId: $userId->value(),
            sessionId: $accessView->getPayload()['sessionID'],
        ));

        self::assertNull($this->spiralTokenStorage()->load($pair->accessToken));
        self::assertNull($this->spiralTokenStorage()->load($pair->refreshToken));
    }

    public function testThrowsForForeignSession(): void
    {
        $pair = $this->tokenStorage()->issuePair(userId: UserId::generate(), device: SessionDevice::unknown());
        $accessView = $this->spiralTokenStorage()->load($pair->accessToken);
        self::assertInstanceOf(TokenInterface::class, $accessView);

        $this->expectException(SessionNotFoundException::class);

        $this->handler()->handle(new RevokeUserSessionCommand(
            userId: UserId::generate()->value(),
            sessionId: $accessView->getPayload()['sessionID'],
        ));
    }

    public function testThrowsForUnknownSession(): void
    {
        $this->expectException(SessionNotFoundException::class);

        $this->handler()->handle(new RevokeUserSessionCommand(
            userId: UserId::generate()->value(),
            sessionId: SessionId::generate()->value(),
        ));
    }

    public function testThrowsForNonUuidV7SessionWithoutServerError(): void
    {
        $this->expectException(SessionNotFoundException::class);

        $this->handler()->handle(new RevokeUserSessionCommand(
            userId: UserId::generate()->value(),
            sessionId: Uuid::uuid4()->toString(),
        ));
    }

    private function handler(): RevokeUserSessionHandler
    {
        return new RevokeUserSessionHandler(
            authTokenStorage: $this->tokenStorage(),
            logger: new NullLogger(),
        );
    }
}
