<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Command\SendLoginCode;

final readonly class SendLoginCodeCommand
{
    public function __construct(
        public string $email,
        public string $code,
        public string $locale,
    ) {}
}
