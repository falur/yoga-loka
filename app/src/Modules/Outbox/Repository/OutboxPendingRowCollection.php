<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Repository;

use Illuminate\Support\Collection;

/**
 * @extends Collection<int, OutboxPendingRow>
 */
final class OutboxPendingRowCollection extends Collection {}
