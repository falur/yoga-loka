<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Configuration;

interface TypedConfig
{
    public static function configName(): string;
}
