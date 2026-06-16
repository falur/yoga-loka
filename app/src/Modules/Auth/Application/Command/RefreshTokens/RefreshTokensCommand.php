<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Command\RefreshTokens;

final readonly class RefreshTokensCommand
{
    public function __construct(
        public string $refreshToken,
    ) {}
}
