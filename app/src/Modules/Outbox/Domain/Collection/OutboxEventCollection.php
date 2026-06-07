<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Domain\Collection;

use App\Modules\Outbox\Domain\Entity\StoredOutboxEvent;
use Illuminate\Support\Collection;

/**
 * @extends Collection<int, StoredOutboxEvent>
 */
final class OutboxEventCollection extends Collection {}
