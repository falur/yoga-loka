<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Spiral\Auth;

use App\Modules\Auth\Application\Contract\AuthTokenStorageContract;
use App\Modules\Auth\Application\Result\IssuedTokenPair;
use App\Modules\Auth\Domain\Enum\AuthTokenType;
use App\Modules\Auth\Domain\Exception\InvalidRefreshTokenException;
use App\Modules\Auth\Domain\Exception\SessionNotFoundException;
use App\Modules\Auth\Domain\Repository\AuthTokenRepository;
use App\Modules\Auth\Domain\ValueObject\SessionDevice;
use App\Modules\Auth\Domain\ValueObject\SessionId;
use App\Modules\Auth\Domain\ValueObject\TokenHash;
use App\Shared\Domain\ValueObject\UserId;

/**
 * Адаптер доменной границы AuthTokenStorageContract: issuePair()/rotate()/revokeSession()/
 * revokeUserSession() для Application-хендлеров. Выпуск токена в issuePair()/rotate()
 * делегируется общему приёму AuthTokenIssuing, которым пользуется и SpiralTokenStorage.
 */
final readonly class AuthTokenIssuer implements AuthTokenStorageContract
{
    private const int ACCESS_TTL_SECONDS = 3600;
    private const int REFRESH_TTL_SECONDS = 5_184_000;

    public function __construct(
        private AuthTokenRepository $authTokenRepository,
        private AuthTokenIssuing $authTokenIssuing,
    ) {}

    #[\Override]
    public function issuePair(UserId $userId, SessionDevice $device): IssuedTokenPair
    {
        $sessionId = SessionId::generate();
        $now = new \DateTimeImmutable();

        $accessToken = $this->authTokenIssuing->issue(
            userId: $userId,
            type: AuthTokenType::Access,
            sessionId: $sessionId,
            expiresAt: $now->add(new \DateInterval(\sprintf('PT%dS', self::ACCESS_TTL_SECONDS))),
            device: $device,
        );
        $refreshToken = $this->authTokenIssuing->issue(
            userId: $userId,
            type: AuthTokenType::Refresh,
            sessionId: $sessionId,
            expiresAt: $now->add(new \DateInterval(\sprintf('PT%dS', self::REFRESH_TTL_SECONDS))),
            device: $device,
        );

        return new IssuedTokenPair(
            accessToken: $accessToken->getID(),
            refreshToken: $refreshToken->getID(),
            expiresIn: self::ACCESS_TTL_SECONDS,
        );
    }

    #[\Override]
    public function rotate(string $refreshRaw, SessionDevice $device): IssuedTokenPair
    {
        $authToken = $this->authTokenRepository->findByHashForUpdate(TokenHash::fromRawToken($refreshRaw))
            ?? throw new InvalidRefreshTokenException();

        if (!$authToken->isRefresh() || $authToken->isExpired(new \DateTimeImmutable())) {
            throw new InvalidRefreshTokenException();
        }

        $userId = $authToken->userId;
        $this->revokeSession($authToken->sessionId);

        // Device берётся из текущего запроса (нового refresh): IP/устройство сессии обновляются
        // на актуальные, значения старого отзываемого токена отбрасываются.
        return $this->issuePair(userId: $userId, device: $device);
    }

    #[\Override]
    public function revokeSession(SessionId $sessionId): void
    {
        $sessionTokens = $this->authTokenRepository->findBySessionIdForUpdate($sessionId);

        $this->authTokenRepository->deleteAll($sessionTokens);
    }

    #[\Override]
    public function revokeUserSession(UserId $userId, SessionId $sessionId): void
    {
        $sessionTokens = $this->authTokenRepository->findByUserAndSessionForUpdate(
            userId: $userId,
            sessionId: $sessionId,
        );

        if ($sessionTokens->isEmpty()) {
            throw new SessionNotFoundException();
        }

        $this->authTokenRepository->deleteAll($sessionTokens);
    }
}
