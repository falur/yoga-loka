<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Domain\ValueObject;

use App\Shared\Domain\ValueObject\AbstractUuidV7Id;

final readonly class OutboxEventId extends AbstractUuidV7Id {}
