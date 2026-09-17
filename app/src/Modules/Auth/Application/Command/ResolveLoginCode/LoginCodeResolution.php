<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Command\ResolveLoginCode;

use App\Modules\Auth\Application\Result\IssuedTokenPair;

/**
 * Результат транзакционного разбора кода: исход плюс выданная пара токенов (Verified) или
 * сырой талон регистрации (NeedsProfile). Для остальных исходов оба поля пустые.
 */
final readonly class LoginCodeResolution
{
    private function __construct(
        public LoginCodeOutcome $outcome,
        public IssuedTokenPair|null $tokens,
        public string|null $registrationTicket,
    ) {}

    public static function failed(LoginCodeOutcome $outcome): self
    {
        return new self(outcome: $outcome, tokens: null, registrationTicket: null);
    }

    public static function verified(IssuedTokenPair $tokens): self
    {
        return new self(outcome: LoginCodeOutcome::Verified, tokens: $tokens, registrationTicket: null);
    }

    public static function needsProfile(string $registrationTicket): self
    {
        return new self(
            outcome: LoginCodeOutcome::NeedsProfile,
            tokens: null,
            registrationTicket: $registrationTicket,
        );
    }
}
