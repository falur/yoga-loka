<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\ValueObject;

use App\Shared\Domain\ValueObject\AbstractRangedIntegerValue;

final readonly class MediaSampleRate extends AbstractRangedIntegerValue
{
    protected const int MIN = 8000;
    protected const int MAX = 192_000;
    protected const string NAME = 'Частота дискретизации';
}
