<?php

declare(strict_types=1);

namespace App\Modules\Posts\Domain\ValueObject;

use App\Shared\Domain\ValueObject\AbstractIntegerValue;

final readonly class MediaPosition extends AbstractIntegerValue
{
    protected const int MIN = 0;
    protected const int MAX = PHP_INT_MAX;
    protected const string NAME = 'Позиция вложения';
}
