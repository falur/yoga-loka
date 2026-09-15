<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Spiral\Configuration;

interface TypedConfig
{
    public static function configName(): string;
}
