<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Command\RefreshTokens;

use App\Modules\Auth\Application\Contract\AuthTokenStorageContract;
use App\Modules\Auth\Application\Dto\IssuedTokenPair;
use App\Modules\Auth\Domain\ValueObject\SessionDevice;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;
use GianTiaga\SpiralCqrs\Attribute\Transactional;
use Psr\Log\LoggerInterface;

/**
 * Обновление пары токенов: ротация refresh-токена (удаление старой пары сессии и выдача новой).
 * Невалидный/истёкший/не-refresh токен → AuthenticationException (401) из хранилища.
 */
final readonly class RefreshTokensHandler
{
    public function __construct(
        private AuthTokenStorageContract $authTokenStorage,
        private LoggerInterface $logger,
    ) {}

    #[Transactional]
    #[LogOperation]
    public function handle(RefreshTokensCommand $command): IssuedTokenPair
    {
        $tokens = $this->authTokenStorage->rotate(
            refreshRaw: $command->refreshToken,
            device: SessionDevice::fromRequest(ip: $command->ip, userAgent: $command->userAgent),
        );

        $this->logger->debug(message: 'Пара токенов ротирована.');

        return $tokens;
    }
}
