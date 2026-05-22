<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\ValueObject;

use App\Shared\Domain\ValueObject\AbstractIntegerValue;

final readonly class MediaMultipartPartSize extends AbstractIntegerValue
{
    protected const int MIN = 5_242_880;
    protected const int MAX = 5_497_558_138_880;
    protected const string NAME = 'Размер части загрузки';
}
