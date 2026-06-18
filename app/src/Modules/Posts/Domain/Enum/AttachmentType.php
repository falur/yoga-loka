<?php

declare(strict_types=1);

namespace App\Modules\Posts\Domain\Enum;

enum AttachmentType: string
{
    case None = 'none';
    case Media = 'media';
    case Lesson = 'lesson';
    case Practice = 'practice';
}
