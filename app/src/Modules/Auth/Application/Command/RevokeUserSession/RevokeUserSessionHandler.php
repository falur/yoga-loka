<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Command\RevokeUserSession;

use App\Modules\Auth\Application\Contract\AuthTokenStorageContract;
use App\Modules\Auth\Domain\ValueObject\SessionId;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\ValueObject\AbstractUuidV7Id;
use App\Shared\Domain\ValueObject\UserId;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;
use GianTiaga\SpiralCqrs\Attribute\Transactional;
use Psr\Log\LoggerInterface;

/**
 * Отзыв конкретной сессии пользователя по её id. Некорректный (не UUID v7) sessionId сразу даёт
 * 404, чтобы не раскрывать формат чужих идентификаторов и не падать 500 на SessionId::fromString.
 * Чужая/несуществующая сессия → 404 из хранилища (проверка владельца).
 */
final readonly class RevokeUserSessionHandler
{
    public function __construct(
        private AuthTokenStorageContract $authTokenStorage,
        private LoggerInterface $logger,
    ) {}

    #[Transactional]
    #[LogOperation]
    public function handle(RevokeUserSessionCommand $command): void
    {
        if (!AbstractUuidV7Id::isUuidV7($command->sessionId)) {
            throw new NotFoundException('app.auth.session_not_found');
        }

        $this->authTokenStorage->revokeUserSession(
            userId: UserId::fromString($command->userId),
            sessionId: SessionId::fromString($command->sessionId),
        );

        $this->logger->debug(message: 'Сессия пользователя отозвана.', context: [
            'userId' => $command->userId,
            'sessionId' => $command->sessionId,
        ]);
    }
}
