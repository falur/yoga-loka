<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Domain\Collection;

use App\Modules\Outbox\Domain\Entity\StoredOutboxEvent;
use App\Shared\Domain\Collection\TypedCollection;

/**
 * @extends TypedCollection<int, StoredOutboxEvent>
 */
final class OutboxEventCollection extends TypedCollection {}
