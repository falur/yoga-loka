<?php

declare(strict_types=1);

namespace App\Infrastructure\Configuration;

interface TypedConfig
{
    public static function configName(): string;
}
