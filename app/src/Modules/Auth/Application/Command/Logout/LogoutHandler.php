<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Command\Logout;

use App\Modules\Auth\Application\Contract\AuthTokenStorageContract;
use App\Modules\Auth\Domain\ValueObject\SessionId;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;
use GianTiaga\SpiralCqrs\Attribute\Transactional;
use Psr\Log\LoggerInterface;

/**
 * Выход: отзыв всех токенов текущей сессии (access + refresh) по sessionId из payload токена.
 */
final readonly class LogoutHandler
{
    public function __construct(
        private AuthTokenStorageContract $authTokenStorage,
        private LoggerInterface $logger,
    ) {}

    #[Transactional]
    #[LogOperation]
    public function handle(LogoutCommand $command): void
    {
        $this->authTokenStorage->revokeSession(SessionId::fromString($command->authSessionId));

        $this->logger->debug(message: 'Сессия отозвана.', context: [
            'sessionId' => $command->authSessionId,
        ]);
    }
}
