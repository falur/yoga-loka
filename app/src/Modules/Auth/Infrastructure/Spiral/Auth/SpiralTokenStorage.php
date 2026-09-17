<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Spiral\Auth;

use App\Modules\Auth\Domain\Enum\AuthTokenType;
use App\Modules\Auth\Domain\Repository\AuthTokenRepository;
use App\Modules\Auth\Domain\ValueObject\SessionDevice;
use App\Modules\Auth\Domain\ValueObject\SessionId;
use App\Modules\Auth\Domain\ValueObject\TokenHash;
use App\Shared\Domain\Exception\InvalidDomainValueException;
use App\Shared\Domain\ValueObject\UserId;
use Spiral\Auth\TokenInterface;
use Spiral\Auth\TokenStorageInterface;

/**
 * Адаптер vendor-границы Spiral\Auth\TokenStorageInterface: load()/create()/delete(). Запись и
 * удаление ведёт методами AuthTokenRepository, которые фиксируют изменение своим прогоном —
 * auth-middleware зовёт create()/delete() напрямую и ждёт реального flush. Выпуск токена в
 * create() делегируется общему приёму AuthTokenIssuing, которым пользуется и AuthTokenIssuer.
 */
final readonly class SpiralTokenStorage implements TokenStorageInterface
{
    public function __construct(
        private AuthTokenRepository $authTokenRepository,
        private AuthTokenIssuing $authTokenIssuing,
    ) {}

    /**
     * Обычное чтение по хэшу БЕЗ блокировки — вызывается на каждом запросе с токеном.
     */
    #[\Override]
    public function load(string $id): TokenInterface|null
    {
        if ($id === '') {
            return null;
        }

        $authToken = $this->authTokenRepository->findByHash(TokenHash::fromRawToken($id));

        if ($authToken === null || $authToken->isExpired(new \DateTimeImmutable())) {
            return null;
        }

        return $this->authTokenIssuing->view(rawToken: $id, authToken: $authToken);
    }

    /**
     * Граница с Spiral\Auth\TokenStorageInterface: ассоциативный payload разбирается здесь в
     * доменные значения, дальше внутри работаем только типизированно (см. AuthTokenIssuing::issue).
     *
     * @param array<string, string> $payload
     */
    #[\Override]
    public function create(array $payload, \DateTimeInterface|null $expiresAt = null): TokenInterface
    {
        if ($expiresAt === null) {
            throw new InvalidDomainValueException('Срок жизни токена обязателен.');
        }

        $userId = $payload['userID'] ?? null;
        $type = $payload['type'] ?? null;
        $sessionId = $payload['sessionID'] ?? null;

        if ($userId === null || $type === null || $sessionId === null) {
            throw new InvalidDomainValueException('Некорректный payload токена.');
        }

        return $this->authTokenIssuing->issue(
            userId: UserId::fromString($userId),
            type: AuthTokenType::from($type),
            sessionId: SessionId::fromString($sessionId),
            expiresAt: $expiresAt,
            device: SessionDevice::unknown(),
        );
    }

    #[\Override]
    public function delete(TokenInterface $token): void
    {
        $authToken = $this->authTokenRepository->findByHashForUpdate(TokenHash::fromRawToken($token->getID()));

        if ($authToken === null) {
            return;
        }

        $this->authTokenRepository->delete($authToken);
    }
}
