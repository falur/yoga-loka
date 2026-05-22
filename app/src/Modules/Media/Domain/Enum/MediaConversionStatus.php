<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\Enum;

enum MediaConversionStatus: string
{
    case Processing = 'processing';
    case Ready = 'ready';
    case ProcessingFailed = 'processingFailed';
}
