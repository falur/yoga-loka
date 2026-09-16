<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Result;

final readonly class IssuedTokenPair
{
    public function __construct(
        public string $accessToken,
        public string $refreshToken,
        public int $expiresIn,
    ) {}
}
