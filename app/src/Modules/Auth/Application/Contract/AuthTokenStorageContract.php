<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Contract;

use App\Modules\Auth\Application\Result\IssuedTokenPair;
use App\Modules\Auth\Domain\ValueObject\SessionDevice;
use App\Modules\Auth\Domain\ValueObject\SessionId;
use App\Shared\Domain\ValueObject\UserId;

/**
 * Доменная граница хранилища токенов для Application-сценариев. Реализуется адаптером
 * AuthTokenIssuer, а интерфейс хранения токенов фреймворка Spiral — отдельным адаптером
 * SpiralTokenStorage; оба делят общий приём выпуска токена AuthTokenIssuing. Хендлеры зависят
 * только от этого контракта, а auth-middleware — от фреймворк-интерфейса.
 */
interface AuthTokenStorageContract
{
    public function issuePair(UserId $userId, SessionDevice $device): IssuedTokenPair;

    public function rotate(string $refreshRaw, SessionDevice $device): IssuedTokenPair;

    public function revokeSession(SessionId $sessionId): void;

    public function revokeUserSession(UserId $userId, SessionId $sessionId): void;
}
