<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Auth;

use App\Modules\Auth\Application\Contract\TokenGeneratorContract;

/**
 * Высокоэнтропийный токен: 32 случайных байта в base64url без паддинга.
 */
final readonly class RandomTokenGenerator implements TokenGeneratorContract
{
    private const int TOKEN_BYTES = 32;

    #[\Override]
    public function generate(): string
    {
        return \rtrim(
            string: \strtr(string: \base64_encode(\random_bytes(self::TOKEN_BYTES)), from: '+/', to: '-_'),
            characters: '=',
        );
    }
}
