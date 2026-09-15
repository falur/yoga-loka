<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Spiral;

enum DirectoryAlias: string
{
    case Root = 'root';
    case Runtime = 'runtime';
    case Cache = 'cache';
}
