<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\ValueObject;

use App\Shared\Domain\ValueObject\AbstractRangedIntegerValue;

final readonly class MediaBitrate extends AbstractRangedIntegerValue
{
    protected const int MIN = 1;
    protected const int MAX = 1_000_000_000;
    protected const string NAME = 'Битрейт';
}
