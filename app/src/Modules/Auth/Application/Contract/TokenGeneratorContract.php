<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Contract;

interface TokenGeneratorContract
{
    public function generate(): string;
}
