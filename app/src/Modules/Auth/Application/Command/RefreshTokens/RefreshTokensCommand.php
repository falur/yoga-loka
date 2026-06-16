<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Command\RefreshTokens;

final readonly class RefreshTokensCommand
{
    public function __construct(
        public string $refreshToken,
        public string|null $ip = null,
        public string|null $userAgent = null,
    ) {}
}
