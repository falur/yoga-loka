<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\ValueObject;

use App\Shared\Domain\ValueObject\AbstractIntegerValue;

final readonly class MediaMultipartPartNumber extends AbstractIntegerValue
{
    protected const int MIN = 1;
    protected const int MAX = 10_000;
    protected const string NAME = 'Номер части загрузки';
}
