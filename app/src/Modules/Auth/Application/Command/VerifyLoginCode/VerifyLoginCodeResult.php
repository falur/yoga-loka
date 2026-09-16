<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Command\VerifyLoginCode;

use App\Modules\Auth\Application\Result\IssuedTokenPair;

/**
 * Результат проверки кода: существующему пользователю — пара токенов (needsProfile=false),
 * новому email — сырой талон регистрации (needsProfile=true).
 */
final readonly class VerifyLoginCodeResult
{
    public function __construct(
        public bool $needsProfile,
        public IssuedTokenPair|null $tokens,
        public string|null $registrationTicket,
    ) {}
}
