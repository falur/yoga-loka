<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Auth\Infrastructure;

use App\Modules\Auth\Domain\Enum\AuthTokenType;
use App\Modules\Auth\Domain\ValueObject\KnownIp;
use App\Modules\Auth\Domain\ValueObject\KnownUserAgent;
use App\Modules\Auth\Domain\ValueObject\SessionDevice;
use App\Modules\Auth\Domain\ValueObject\SessionId;
use App\Modules\Auth\Infrastructure\Spiral\Auth\AuthTokenView;
use App\Modules\Auth\Infrastructure\Spiral\Auth\CycleTokenStorage;
use App\Modules\Auth\Infrastructure\Spiral\Auth\RandomTokenGenerator;
use App\Modules\Auth\Domain\Repository\AuthTokenRepository;
use App\Modules\Auth\Domain\Exception\InvalidRefreshTokenException;
use App\Shared\Domain\Exception\InvalidDomainValueException;
use App\Modules\Auth\Domain\Exception\SessionNotFoundException;
use App\Shared\Domain\ValueObject\UserId;
use Spiral\Auth\TokenInterface;
use Tests\DatabaseTestCase;

final class CycleTokenStorageTest extends DatabaseTestCase
{
    public function testIssuePairCreatesLoadableAccessAndRefresh(): void
    {
        $userId = UserId::generate();
        $pair = $this->storage()->issuePair(userId: $userId, device: SessionDevice::unknown());

        self::assertSame(3600, $pair->expiresIn);
        self::assertNotSame($pair->accessToken, $pair->refreshToken);

        $accessView = $this->storage()->load($pair->accessToken);

        self::assertInstanceOf(TokenInterface::class, $accessView);
        self::assertSame($pair->accessToken, $accessView->getID());
        self::assertSame(AuthTokenType::Access->value, $accessView->getPayload()['type']);
        self::assertSame($userId->value(), $accessView->getPayload()['userID']);
        self::assertNotNull($accessView->getExpiresAt());

        $sessionId = SessionId::fromString($accessView->getPayload()['sessionID']);
        self::assertCount(2, $this->authTokenRepository()->findBySessionIdForUpdate($sessionId));
    }

    public function testLoadReturnsNullForEmptyUnknownAndExpiredTokens(): void
    {
        self::assertNull($this->storage()->load(''));
        self::assertNull($this->storage()->load('unknown-token'));

        $expiredView = $this->storage()->create(
            payload: $this->payload(AuthTokenType::Access),
            expiresAt: new \DateTimeImmutable('-1 hour'),
        );

        self::assertNull($this->storage()->load($expiredView->getID()));
    }

    public function testCreateRejectsNullExpiry(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        $this->storage()->create(payload: $this->payload(AuthTokenType::Access), expiresAt: null);
    }

    public function testCreateRejectsIncompletePayload(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        $this->storage()->create(
            payload: ['type' => AuthTokenType::Access->value, 'sessionID' => SessionId::generate()->value()],
            expiresAt: new \DateTimeImmutable('+1 hour'),
        );
    }

    public function testDeleteRemovesToken(): void
    {
        $pair = $this->storage()->issuePair(userId: UserId::generate(), device: SessionDevice::unknown());
        $accessView = $this->storage()->load($pair->accessToken);

        self::assertInstanceOf(TokenInterface::class, $accessView);

        $this->storage()->delete($accessView);

        self::assertNull($this->storage()->load($pair->accessToken));
    }

    public function testDeleteIgnoresUnknownToken(): void
    {
        $this->storage()->delete(new AuthTokenView(
            id: 'unknown-token',
            userId: UserId::generate(),
            type: AuthTokenType::Access,
            sessionId: SessionId::generate(),
            expiresAt: null,
        ));

        self::assertNull($this->storage()->load('unknown-token'));
    }

    public function testRotateIssuesNewPairAndRevokesOld(): void
    {
        $pair = $this->storage()->issuePair(userId: UserId::generate(), device: SessionDevice::unknown());

        $rotatedPair = $this->storage()->rotate(refreshRaw: $pair->refreshToken, device: SessionDevice::unknown());

        self::assertNotSame($pair->accessToken, $rotatedPair->accessToken);
        self::assertNotSame($pair->refreshToken, $rotatedPair->refreshToken);
        self::assertNull($this->storage()->load($pair->refreshToken));
        self::assertNull($this->storage()->load($pair->accessToken));
        self::assertInstanceOf(TokenInterface::class, $this->storage()->load($rotatedPair->accessToken));
    }

    public function testRotateRejectsUnknownRefresh(): void
    {
        $this->expectException(InvalidRefreshTokenException::class);

        $this->storage()->rotate(refreshRaw: 'unknown-token', device: SessionDevice::unknown());
    }

    public function testRotateRejectsAccessTokenAsRefresh(): void
    {
        $pair = $this->storage()->issuePair(userId: UserId::generate(), device: SessionDevice::unknown());

        $this->expectException(InvalidRefreshTokenException::class);

        $this->storage()->rotate(refreshRaw: $pair->accessToken, device: SessionDevice::unknown());
    }

    public function testRotateRejectsExpiredRefresh(): void
    {
        $expiredRefresh = $this->storage()->create(
            payload: $this->payload(AuthTokenType::Refresh),
            expiresAt: new \DateTimeImmutable('-1 hour'),
        );

        $this->expectException(InvalidRefreshTokenException::class);

        $this->storage()->rotate(refreshRaw: $expiredRefresh->getID(), device: SessionDevice::unknown());
    }

    public function testRevokeSessionDeletesAllSessionTokens(): void
    {
        $pair = $this->storage()->issuePair(userId: UserId::generate(), device: SessionDevice::unknown());
        $accessView = $this->storage()->load($pair->accessToken);

        self::assertInstanceOf(TokenInterface::class, $accessView);

        $this->storage()->revokeSession(SessionId::fromString($accessView->getPayload()['sessionID']));

        self::assertNull($this->storage()->load($pair->accessToken));
        self::assertNull($this->storage()->load($pair->refreshToken));
    }

    public function testIssuePairStoresDeviceOnBothSessionTokens(): void
    {
        $pair = $this->storage()->issuePair(
            userId: UserId::generate(),
            device: SessionDevice::fromRequest(ip: '198.51.100.10', userAgent: 'Device/1.0'),
        );
        $accessView = $this->storage()->load($pair->accessToken);
        self::assertInstanceOf(TokenInterface::class, $accessView);
        $sessionId = SessionId::fromString($accessView->getPayload()['sessionID']);

        $this->cleanOrmHeap();
        $sessionTokens = $this->authTokenRepository()->findBySessionIdForUpdate($sessionId);

        self::assertCount(2, $sessionTokens);
        foreach ($sessionTokens as $sessionToken) {
            self::assertInstanceOf(KnownIp::class, $sessionToken->ip);
            self::assertSame('198.51.100.10', $sessionToken->ip->toNullableString());
            self::assertInstanceOf(KnownUserAgent::class, $sessionToken->userAgent);
            self::assertSame('Device/1.0', $sessionToken->userAgent->toNullableString());
        }
    }

    public function testRotateRecordsDeviceFromCurrentRequestNotOldToken(): void
    {
        $pair = $this->storage()->issuePair(
            userId: UserId::generate(),
            device: SessionDevice::fromRequest(ip: '203.0.113.1', userAgent: 'Old/1.0'),
        );

        $rotatedPair = $this->storage()->rotate(
            refreshRaw: $pair->refreshToken,
            device: SessionDevice::fromRequest(ip: '203.0.113.2', userAgent: 'New/2.0'),
        );
        $accessView = $this->storage()->load($rotatedPair->accessToken);
        self::assertInstanceOf(TokenInterface::class, $accessView);
        $sessionId = SessionId::fromString($accessView->getPayload()['sessionID']);

        $this->cleanOrmHeap();
        $sessionTokens = $this->authTokenRepository()->findBySessionIdForUpdate($sessionId);

        self::assertCount(2, $sessionTokens);
        foreach ($sessionTokens as $sessionToken) {
            self::assertSame('203.0.113.2', $sessionToken->ip->toNullableString());
            self::assertSame('New/2.0', $sessionToken->userAgent->toNullableString());
        }
    }

    public function testRevokeUserSessionDeletesOwnSessionTokens(): void
    {
        $userId = UserId::generate();
        $pair = $this->storage()->issuePair(userId: $userId, device: SessionDevice::unknown());
        $accessView = $this->storage()->load($pair->accessToken);
        self::assertInstanceOf(TokenInterface::class, $accessView);

        $this->storage()->revokeUserSession(
            userId: $userId,
            sessionId: SessionId::fromString($accessView->getPayload()['sessionID']),
        );

        self::assertNull($this->storage()->load($pair->accessToken));
        self::assertNull($this->storage()->load($pair->refreshToken));
    }

    public function testRevokeUserSessionThrowsForForeignUser(): void
    {
        $pair = $this->storage()->issuePair(userId: UserId::generate(), device: SessionDevice::unknown());
        $accessView = $this->storage()->load($pair->accessToken);
        self::assertInstanceOf(TokenInterface::class, $accessView);

        $this->expectException(SessionNotFoundException::class);

        $this->storage()->revokeUserSession(
            userId: UserId::generate(),
            sessionId: SessionId::fromString($accessView->getPayload()['sessionID']),
        );
    }

    public function testRevokeUserSessionThrowsForUnknownSession(): void
    {
        $this->expectException(SessionNotFoundException::class);

        $this->storage()->revokeUserSession(userId: UserId::generate(), sessionId: SessionId::generate());
    }

    /**
     * @return array<string, string>
     */
    private function payload(AuthTokenType $type): array
    {
        return [
            'userID' => UserId::generate()->value(),
            'type' => $type->value,
            'sessionID' => SessionId::generate()->value(),
        ];
    }

    private function storage(): CycleTokenStorage
    {
        return new CycleTokenStorage(
            authTokenRepository: $this->authTokenRepository(),
            tokenGenerator: new RandomTokenGenerator(),
        );
    }

    private function authTokenRepository(): AuthTokenRepository
    {
        return $this->getContainer()->get(AuthTokenRepository::class);
    }
}
