<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\ValueObject;

use App\Shared\Domain\ValueObject\AbstractIntegerValue;

final readonly class MediaWaveformPeakCount extends AbstractIntegerValue
{
    protected const int MIN = 1;
    protected const int MAX = 4096;
    protected const string NAME = 'Количество пиков волны';
}
