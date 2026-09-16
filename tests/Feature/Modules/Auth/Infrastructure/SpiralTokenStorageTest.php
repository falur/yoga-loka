<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Auth\Infrastructure;

use App\Modules\Auth\Application\Contract\TokenGeneratorContract;
use App\Modules\Auth\Domain\Enum\AuthTokenType;
use App\Modules\Auth\Domain\Repository\AuthTokenRepository;
use App\Modules\Auth\Domain\ValueObject\SessionDevice;
use App\Modules\Auth\Domain\ValueObject\SessionId;
use App\Modules\Auth\Infrastructure\Spiral\Auth\AuthTokenIssuer;
use App\Modules\Auth\Infrastructure\Spiral\Auth\AuthTokenIssuing;
use App\Modules\Auth\Infrastructure\Spiral\Auth\AuthTokenView;
use App\Modules\Auth\Infrastructure\Spiral\Auth\RandomTokenGenerator;
use App\Modules\Auth\Infrastructure\Spiral\Auth\SpiralTokenStorage;
use App\Shared\Domain\Exception\InvalidDomainValueException;
use App\Shared\Domain\ValueObject\UserId;
use Spiral\Auth\TokenInterface;
use Tests\DatabaseTestCase;

/**
 * Vendor-граница Spiral\Auth\TokenStorageInterface: load()/create()/delete(). Выпуск токена в
 * этих сценариях идёт через issuer() (AuthTokenStorageContract), как это делает и реальный
 * AuthTransportWithStorageMiddleware для чтения уже выданных токенов.
 */
final class SpiralTokenStorageTest extends DatabaseTestCase
{
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
        $pair = $this->issuer()->issuePair(userId: UserId::generate(), device: SessionDevice::unknown());
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

    private function storage(): SpiralTokenStorage
    {
        return new SpiralTokenStorage(
            authTokenRepository: $this->authTokenRepository(),
            authTokenIssuing: $this->authTokenIssuing(),
        );
    }

    private function issuer(): AuthTokenIssuer
    {
        return new AuthTokenIssuer(
            authTokenRepository: $this->authTokenRepository(),
            authTokenIssuing: $this->authTokenIssuing(),
        );
    }

    private function authTokenIssuing(): AuthTokenIssuing
    {
        return new AuthTokenIssuing(
            authTokenRepository: $this->authTokenRepository(),
            tokenGenerator: $this->tokenGenerator(),
        );
    }

    private function tokenGenerator(): TokenGeneratorContract
    {
        return new RandomTokenGenerator();
    }

    private function authTokenRepository(): AuthTokenRepository
    {
        return $this->getContainer()->get(AuthTokenRepository::class);
    }
}
