<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Command\CompleteRegistration;

final readonly class CompleteRegistrationCommand
{
    public function __construct(
        public string $ticket,
        public string $name,
        public string $nickname,
        public string $requestLocale,
        public string|null $ip = null,
        public string|null $userAgent = null,
    ) {}
}
