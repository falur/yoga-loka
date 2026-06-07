<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Domain\ValueObject;

use App\Shared\Domain\ValueObject\AbstractIntegerValue;

final readonly class OutboxMaxAttempts extends AbstractIntegerValue
{
    protected const int MIN = 1;
    protected const int MAX = \PHP_INT_MAX;
    protected const string NAME = 'Лимит попыток outbox';
}
