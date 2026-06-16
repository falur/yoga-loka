<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Auth;

use App\Modules\Auth\Application\Contract\AuthTokenStorageContract;
use App\Modules\Auth\Application\Contract\TokenGeneratorContract;
use App\Modules\Auth\Application\Dto\IssuedTokenPair;
use App\Modules\Auth\Domain\Entity\AuthToken;
use App\Modules\Auth\Domain\Enum\AuthTokenType;
use App\Modules\Auth\Domain\ValueObject\AuthTokenId;
use App\Modules\Auth\Domain\ValueObject\Expiration;
use App\Modules\Auth\Domain\ValueObject\SessionDevice;
use App\Modules\Auth\Domain\ValueObject\SessionId;
use App\Modules\Auth\Domain\ValueObject\TokenHash;
use App\Modules\Auth\Repository\AuthTokenRepository;
use App\Shared\Domain\Exception\AuthenticationException;
use App\Shared\Domain\Exception\InvalidDomainValueException;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\ValueObject\UserId;
use Cycle\ORM\EntityManagerInterface;
use Spiral\Auth\TokenInterface;
use Spiral\Auth\TokenStorageInterface;

/**
 * Единый адаптер хранилища токенов: реализует и Spiral\Auth\TokenStorageInterface (load для
 * auth-middleware), и доменный AuthTokenStorageContract (issuePair/rotate/revokeSession для
 * хендлеров). Инфраструктурный orchestrator: persist/run делает сам (vendor-middleware зовёт
 * create/delete напрямую и ждёт реального flush; вызовы доменных методов идут внутри
 * #[Transactional]-сценария, несколько run() в одной транзакции безопасны).
 */
final readonly class CycleTokenStorage implements TokenStorageInterface, AuthTokenStorageContract
{
    private const int ACCESS_TTL_SECONDS = 3600;
    private const int REFRESH_TTL_SECONDS = 5_184_000;

    public function __construct(
        private AuthTokenRepository $authTokenRepository,
        private TokenGeneratorContract $tokenGenerator,
        private EntityManagerInterface $entityManager,
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

        return $this->view(rawToken: $id, authToken: $authToken);
    }

    /**
     * Граница с Spiral\Auth\TokenStorageInterface: ассоциативный payload разбирается здесь в
     * доменные значения, дальше внутри работаем только типизированно (см. issueToken).
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

        return $this->issueToken(
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

        $this->entityManager->delete($authToken);
        $this->entityManager->run();
    }

    #[\Override]
    public function issuePair(UserId $userId, SessionDevice $device): IssuedTokenPair
    {
        $sessionId = SessionId::generate();
        $now = new \DateTimeImmutable();

        $accessToken = $this->issueToken(
            userId: $userId,
            type: AuthTokenType::Access,
            sessionId: $sessionId,
            expiresAt: $now->add(new \DateInterval(\sprintf('PT%dS', self::ACCESS_TTL_SECONDS))),
            device: $device,
        );
        $refreshToken = $this->issueToken(
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
            ?? throw new AuthenticationException('app.auth.invalid_refresh');

        if (!$authToken->isRefresh() || $authToken->isExpired(new \DateTimeImmutable())) {
            throw new AuthenticationException('app.auth.invalid_refresh');
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

        foreach ($sessionTokens as $sessionToken) {
            $this->entityManager->delete($sessionToken);
        }

        $this->entityManager->run();
    }

    #[\Override]
    public function revokeUserSession(UserId $userId, SessionId $sessionId): void
    {
        $sessionTokens = $this->authTokenRepository->findByUserAndSessionForUpdate(
            userId: $userId,
            sessionId: $sessionId,
        );

        if ($sessionTokens->isEmpty()) {
            throw new NotFoundException('app.auth.session_not_found');
        }

        foreach ($sessionTokens as $sessionToken) {
            $this->entityManager->delete($sessionToken);
        }

        $this->entityManager->run();
    }

    /**
     * Единая типизированная фабрика токена для домена (issuePair) и vendor-границы (create):
     * создаёт AuthToken, фиксирует его и возвращает view. Ассоциативные payload-массивы сюда
     * не попадают.
     */
    private function issueToken(
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

        $this->entityManager->persist($authToken);
        $this->entityManager->run();

        return new AuthTokenView(
            id: $rawToken,
            userId: $userId,
            type: $type,
            sessionId: $sessionId,
            expiresAt: $expiration->value(),
        );
    }

    private function view(string $rawToken, AuthToken $authToken): AuthTokenView
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
