<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Domain\ValueObject;

use App\Shared\Domain\ValueObject\AbstractRangedIntegerValue;

final readonly class OutboxRelayBatchSize extends AbstractRangedIntegerValue
{
    protected const int MIN = 1;
    protected const int MAX = 1000;
    protected const string NAME = 'Размер пачки outbox relay';
}
