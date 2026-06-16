<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Contract;

interface SecretHasherContract
{
    public function hash(string $secret): string;

    public function verify(string $secret, string $hash): bool;
}
