<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\ValueObject;

use App\Shared\Domain\ValueObject\AbstractIntegerValue;

final readonly class MediaPresignedTtl extends AbstractIntegerValue
{
    protected const int MIN = 1;
    protected const int MAX = 604_800;
    protected const string NAME = 'TTL presigned-ссылки';
}
