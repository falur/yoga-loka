<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Command\ResolveLoginCode;

final readonly class ResolveLoginCodeCommand
{
    public function __construct(
        public string $email,
        public string $code,
    ) {}
}
