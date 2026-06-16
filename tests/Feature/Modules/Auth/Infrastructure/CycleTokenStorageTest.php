<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Auth\Infrastructure;

use App\Modules\Auth\Domain\Enum\AuthTokenType;
use App\Modules\Auth\Domain\ValueObject\SessionId;
use App\Modules\Auth\Infrastructure\Auth\AuthTokenView;
use App\Modules\Auth\Infrastructure\Auth\CycleTokenStorage;
use App\Modules\Auth\Infrastructure\Auth\RandomTokenGenerator;
use App\Modules\Auth\Repository\AuthTokenRepository;
use App\Shared\Domain\Exception\AuthenticationException;
use App\Shared\Domain\Exception\InvalidDomainValueException;
use App\Shared\Domain\ValueObject\UserId;
use Cycle\ORM\EntityManagerInterface;
use Spiral\Auth\TokenInterface;
use Tests\DatabaseTestCase;

final class CycleTokenStorageTest extends DatabaseTestCase
{
    public function testIssuePairCreatesLoadableAccessAndRefresh(): void
    {
        $userId = UserId::generate();
        $pair = $this->storage()->issuePair($userId);

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
        $pair = $this->storage()->issuePair(UserId::generate());
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
        $pair = $this->storage()->issuePair(UserId::generate());

        $rotatedPair = $this->storage()->rotate($pair->refreshToken);

        self::assertNotSame($pair->accessToken, $rotatedPair->accessToken);
        self::assertNotSame($pair->refreshToken, $rotatedPair->refreshToken);
        self::assertNull($this->storage()->load($pair->refreshToken));
        self::assertNull($this->storage()->load($pair->accessToken));
        self::assertInstanceOf(TokenInterface::class, $this->storage()->load($rotatedPair->accessToken));
    }

    public function testRotateRejectsUnknownRefresh(): void
    {
        $this->expectException(AuthenticationException::class);

        $this->storage()->rotate('unknown-token');
    }

    public function testRotateRejectsAccessTokenAsRefresh(): void
    {
        $pair = $this->storage()->issuePair(UserId::generate());

        $this->expectException(AuthenticationException::class);

        $this->storage()->rotate($pair->accessToken);
    }

    public function testRotateRejectsExpiredRefresh(): void
    {
        $expiredRefresh = $this->storage()->create(
            payload: $this->payload(AuthTokenType::Refresh),
            expiresAt: new \DateTimeImmutable('-1 hour'),
        );

        $this->expectException(AuthenticationException::class);

        $this->storage()->rotate($expiredRefresh->getID());
    }

    public function testRevokeSessionDeletesAllSessionTokens(): void
    {
        $pair = $this->storage()->issuePair(UserId::generate());
        $accessView = $this->storage()->load($pair->accessToken);

        self::assertInstanceOf(TokenInterface::class, $accessView);

        $this->storage()->revokeSession(SessionId::fromString($accessView->getPayload()['sessionID']));

        self::assertNull($this->storage()->load($pair->accessToken));
        self::assertNull($this->storage()->load($pair->refreshToken));
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
            entityManager: $this->getContainer()->get(EntityManagerInterface::class),
        );
    }

    private function authTokenRepository(): AuthTokenRepository
    {
        return $this->getContainer()->get(AuthTokenRepository::class);
    }
}
