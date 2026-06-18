<?php

declare(strict_types=1);

namespace App\Modules\Posts\Domain\Enum;

enum PostStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Blocked = 'blocked';
}
