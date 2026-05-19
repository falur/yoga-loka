<?php

declare(strict_types=1);

namespace App\Infrastructure\Framework;

enum DirectoryAlias: string
{
    case Root = 'root';
    case Runtime = 'runtime';
}
