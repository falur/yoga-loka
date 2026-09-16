<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Query\GetUserSessions;

use App\Modules\Auth\Application\View\SessionViewAssembler;
use App\Modules\Auth\Application\View\SessionViewCollection;
use App\Modules\Auth\Domain\Repository\AuthTokenRepository;
use App\Shared\Domain\ValueObject\UserId;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;
use Psr\Log\LoggerInterface;

/**
 * Чтение активных сессий пользователя: берёт не истёкшие токены и передаёт их SessionViewAssembler,
 * который группирует их по sessionId и собирает по одной SessionView на сессию. Без транзакции (Query).
 */
final readonly class GetUserSessionsHandler
{
    public function __construct(
        private AuthTokenRepository $authTokenRepository,
        private SessionViewAssembler $sessionViewAssembler,
        private LoggerInterface $logger,
    ) {}

    #[LogOperation]
    public function handle(GetUserSessionsQuery $query): SessionViewCollection
    {
        $tokens = $this->authTokenRepository->findActiveByUserId(
            userId: UserId::fromString($query->userId),
            now: new \DateTimeImmutable(),
        );

        $sessions = $this->sessionViewAssembler->fromActiveTokens(
            activeTokens: $tokens,
            currentSessionId: $query->currentSessionId,
        );

        $this->logger->debug(message: 'Список сессий пользователя получен.', context: [
            'userId' => $query->userId,
            'sessionCount' => $sessions->count(),
        ]);

        return $sessions;
    }
}
