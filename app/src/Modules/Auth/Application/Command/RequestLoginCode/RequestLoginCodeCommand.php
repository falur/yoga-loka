<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Command\RequestLoginCode;

final readonly class RequestLoginCodeCommand
{
    public function __construct(
        public string $email,
        public string $requestLocale,
    ) {}
}
