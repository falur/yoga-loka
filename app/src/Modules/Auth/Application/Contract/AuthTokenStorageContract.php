<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Contract;

use App\Modules\Auth\Application\Dto\IssuedTokenPair;
use App\Modules\Auth\Domain\ValueObject\SessionId;
use App\Shared\Domain\ValueObject\UserId;

/**
 * Доменная граница хранилища токенов для Application-сценариев. Реализуется тем же адаптером,
 * что и Spiral\Auth\TokenStorageInterface (CycleTokenStorage), но хендлеры зависят только от
 * этого контракта, а auth-middleware — от фреймворк-интерфейса.
 */
interface AuthTokenStorageContract
{
    public function issuePair(UserId $userId): IssuedTokenPair;

    public function rotate(string $refreshRaw): IssuedTokenPair;

    public function revokeSession(SessionId $sessionId): void;
}
