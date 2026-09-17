<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Spiral\Auth;

use App\Modules\Auth\Application\Contract\TokenGeneratorContract;
use App\Modules\Auth\Domain\Entity\AuthToken;
use App\Modules\Auth\Domain\Enum\AuthTokenType;
use App\Modules\Auth\Domain\Repository\AuthTokenRepository;
use App\Modules\Auth\Domain\ValueObject\AuthTokenId;
use App\Modules\Auth\Domain\ValueObject\Expiration;
use App\Modules\Auth\Domain\ValueObject\SessionDevice;
use App\Modules\Auth\Domain\ValueObject\SessionId;
use App\Modules\Auth\Domain\ValueObject\TokenHash;
use App\Shared\Domain\ValueObject\UserId;

/**
 * Общий приём выпуска токена для двух адаптеров границы Auth: SpiralTokenStorage (vendor-граница
 * Spiral\Auth\TokenStorageInterface) и AuthTokenIssuer (доменная граница AuthTokenStorageContract).
 * Не реализует ни один из этих интерфейсов сама — не оркеструет их обязательства, лишь удерживает
 * приём выпуска токена в одном месте, чтобы он не дублировался в обоих адаптерах.
 */
final readonly class AuthTokenIssuing
{
    public function __construct(
        private AuthTokenRepository $authTokenRepository,
        private TokenGeneratorContract $tokenGenerator,
    ) {}

    /**
     * Единая типизированная фабрика токена для домена (issuePair/rotate) и vendor-границы
     * (create): создаёт AuthToken, фиксирует его и возвращает view. Ассоциативные
     * payload-массивы сюда не попадают.
     */
    public function issue(
        UserId $userId,
        AuthTokenType $type,
        SessionId $sessionId,
        \DateTimeInterface $expiresAt,
        SessionDevice $device,
    ): AuthTokenView {
        $rawToken = $this->tokenGenerator->generate();
        $expiration = Expiration::fromDateTime(\DateTimeImmutable::createFromInterface($expiresAt));
        $authToken = AuthToken::issue(
            id: AuthTokenId::generate(),
            userId: $userId,
            sessionId: $sessionId,
            type: $type,
            tokenHash: TokenHash::fromRawToken($rawToken),
            expiration: $expiration,
            device: $device,
            now: new \DateTimeImmutable(),
        );

        $this->authTokenRepository->save($authToken);

        return new AuthTokenView(
            id: $rawToken,
            userId: $userId,
            type: $type,
            sessionId: $sessionId,
            expiresAt: $expiration->value(),
        );
    }

    public function view(string $rawToken, AuthToken $authToken): AuthTokenView
    {
        return new AuthTokenView(
            id: $rawToken,
            userId: $authToken->userId,
            type: $authToken->type,
            sessionId: $authToken->sessionId,
            expiresAt: $authToken->expiration->value(),
        );
    }
}
