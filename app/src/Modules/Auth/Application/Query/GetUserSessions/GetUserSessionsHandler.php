<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Query\GetUserSessions;

use App\Modules\Auth\Application\Result\SessionResult;
use App\Modules\Auth\Application\Result\SessionResultCollection;
use App\Modules\Auth\Domain\Collection\AuthTokenCollection;
use App\Modules\Auth\Domain\Entity\AuthToken;
use App\Modules\Auth\Domain\Repository\AuthTokenRepository;
use App\Shared\Domain\Exception\InvalidDomainValueException;
use App\Shared\Domain\ValueObject\UserId;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;
use Illuminate\Support\Collection;
use Psr\Log\LoggerInterface;

/**
 * Чтение активных сессий пользователя: берёт не истёкшие токены и группирует их по sessionId
 * (access + refresh одной сессии), сворачивая каждую группу в SessionResult. Группировка идёт
 * через базовый Collection, чтобы дженерики AuthTokenCollection не ломали PHPStan на вложенном
 * результате groupBy. createdAt = самый ранний выпуск, expiresAt = самый поздний срок (refresh,
 * ~60 дней). ip/userAgent берём из первого токена: оба токена сессии выпущены одним issuePair,
 * поэтому устройство идентично. Без транзакции (Query).
 */
final readonly class GetUserSessionsHandler
{
    public function __construct(
        private AuthTokenRepository $authTokenRepository,
        private LoggerInterface $logger,
    ) {}

    #[LogOperation]
    public function handle(GetUserSessionsQuery $query): SessionResultCollection
    {
        $tokens = $this->authTokenRepository->findActiveByUserId(
            userId: UserId::fromString($query->userId),
            now: new \DateTimeImmutable(),
        );

        $sessions = $this->fromActiveTokens(activeTokens: $tokens, currentSessionId: $query->currentSessionId);

        $this->logger->debug(message: 'Список сессий пользователя получен.', context: [
            'userId' => $query->userId,
            'sessionCount' => $sessions->count(),
        ]);

        return $sessions;
    }

    private function fromActiveTokens(AuthTokenCollection $activeTokens, string $currentSessionId): SessionResultCollection
    {
        return new SessionResultCollection(
            $activeTokens
                ->toBase()
                ->groupBy(static fn(AuthToken $token): string => $token->sessionId->value())
                ->map(fn(Collection $sessionTokens): SessionResult => $this->fromSessionTokens(
                    sessionTokens: new AuthTokenCollection($sessionTokens),
                    currentSessionId: $currentSessionId,
                ))
                ->values(),
        );
    }

    private function fromSessionTokens(AuthTokenCollection $sessionTokens, string $currentSessionId): SessionResult
    {
        $firstToken = $sessionTokens->first();

        if ($firstToken === null) {
            throw new InvalidDomainValueException('Сессия должна содержать хотя бы один токен.');
        }

        // Collection::min()/max() возвращают mixed (не проходят PHPStan strict), поэтому границы
        // считаем типизированным reduce по DateTimeImmutable.
        $createdAt = $sessionTokens->reduce(
            callback: static fn(\DateTimeImmutable $earliest, AuthToken $token): \DateTimeImmutable
                => $token->createdAt < $earliest ? $token->createdAt : $earliest,
            initial: $firstToken->createdAt,
        );
        $expiresAt = $sessionTokens->reduce(
            callback: static fn(\DateTimeImmutable $latest, AuthToken $token): \DateTimeImmutable
                => $token->expiration->value() > $latest ? $token->expiration->value() : $latest,
            initial: $firstToken->expiration->value(),
        );

        return new SessionResult(
            id: $firstToken->sessionId->value(),
            createdAt: $createdAt,
            expiresAt: $expiresAt,
            ip: $firstToken->ip->toNullableString(),
            device: $firstToken->userAgent->toNullableString(),
            current: $firstToken->sessionId->value() === $currentSessionId,
        );
    }
}
