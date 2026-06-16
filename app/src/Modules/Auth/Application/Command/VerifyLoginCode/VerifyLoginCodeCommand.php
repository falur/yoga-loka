<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Command\VerifyLoginCode;

final readonly class VerifyLoginCodeCommand
{
    public function __construct(
        public string $email,
        public string $code,
    ) {}
}
