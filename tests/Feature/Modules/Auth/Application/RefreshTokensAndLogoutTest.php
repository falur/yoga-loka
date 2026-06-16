<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Auth\Application;

use App\Modules\Auth\Application\Command\Logout\LogoutCommand;
use App\Modules\Auth\Application\Command\Logout\LogoutHandler;
use App\Modules\Auth\Application\Command\RefreshTokens\RefreshTokensCommand;
use App\Modules\Auth\Application\Command\RefreshTokens\RefreshTokensHandler;
use App\Shared\Domain\Exception\AuthenticationException;
use App\Shared\Domain\ValueObject\UserId;
use Psr\Log\NullLogger;
use Spiral\Auth\TokenInterface;

final class RefreshTokensAndLogoutTest extends AuthApplicationTestCase
{
    public function testRefreshIssuesNewPairAndInvalidatesOldRefresh(): void
    {
        $pair = $this->tokenStorage()->issuePair(UserId::generate());

        $newPair = $this->refreshHandler()->handle(new RefreshTokensCommand(refreshToken: $pair->refreshToken));

        self::assertNotSame($pair->refreshToken, $newPair->refreshToken);
        self::assertNotSame($pair->accessToken, $newPair->accessToken);
        self::assertNull($this->tokenStorage()->load($pair->refreshToken));
        self::assertInstanceOf(TokenInterface::class, $this->tokenStorage()->load($newPair->accessToken));
    }

    public function testRefreshRejectsInvalidToken(): void
    {
        $this->expectException(AuthenticationException::class);

        $this->refreshHandler()->handle(new RefreshTokensCommand(refreshToken: 'invalid-token'));
    }

    public function testLogoutRevokesWholeSession(): void
    {
        $pair = $this->tokenStorage()->issuePair(UserId::generate());
        $accessView = $this->tokenStorage()->load($pair->accessToken);
        self::assertInstanceOf(TokenInterface::class, $accessView);

        $this->logoutHandler()->handle(new LogoutCommand(authSessionId: $accessView->getPayload()['sessionID']));

        self::assertNull($this->tokenStorage()->load($pair->accessToken));
        self::assertNull($this->tokenStorage()->load($pair->refreshToken));
    }

    private function refreshHandler(): RefreshTokensHandler
    {
        return new RefreshTokensHandler(authTokenStorage: $this->tokenStorage(), logger: new NullLogger());
    }

    private function logoutHandler(): LogoutHandler
    {
        return new LogoutHandler(authTokenStorage: $this->tokenStorage(), logger: new NullLogger());
    }
}
