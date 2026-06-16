<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Query\GetUserSessions;

use App\Modules\Auth\Domain\Collection\AuthTokenCollection;
use App\Modules\Auth\Domain\Entity\AuthToken;
use App\Modules\Auth\Repository\AuthTokenRepository;
use App\Shared\Domain\ValueObject\UserId;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;
use Illuminate\Support\Collection;
use Psr\Log\LoggerInterface;

/**
 * Чтение активных сессий пользователя: группирует не истёкшие токены по sessionId и собирает
 * по одной AuthSession на группу. Группировка идёт через базовый Collection, чтобы дженерики
 * AuthTokenCollection не ломали PHPStan на вложенном результате groupBy. Без транзакции (Query).
 */
final readonly class GetUserSessionsHandler
{
    public function __construct(
        private AuthTokenRepository $authTokenRepository,
        private LoggerInterface $logger,
    ) {}

    #[LogOperation]
    public function handle(GetUserSessionsQuery $query): AuthSessionCollection
    {
        $tokens = $this->authTokenRepository->findActiveByUserId(
            userId: UserId::fromString($query->userId),
            now: new \DateTimeImmutable(),
        );

        $sessions = (new Collection($tokens->all()))
            ->groupBy(static fn(AuthToken $token): string => $token->sessionId->value())
            ->map(static fn(Collection $sessionTokens): AuthSession
                => AuthSession::fromTokens(new AuthTokenCollection($sessionTokens->all())))
            ->values()
            ->all();

        $userSessions = new AuthSessionCollection($sessions);

        $this->logger->debug(message: 'Список сессий пользователя получен.', context: [
            'userId' => $query->userId,
            'sessionCount' => $userSessions->count(),
        ]);

        return $userSessions;
    }
}
