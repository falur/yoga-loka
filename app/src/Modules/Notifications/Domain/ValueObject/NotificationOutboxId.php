<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Domain\ValueObject;

use App\Shared\Domain\ValueObject\AbstractUuidV7Id;

/**
 * Идентификатор outbox-события NotificationRequested, по которому рассылка обеспечивает
 * идемпотентность (колонка notifications.outbox_id + unique). Значение совпадает с
 * OutboxEventId источника, поэтому это тоже UUID v7.
 */
final readonly class NotificationOutboxId extends AbstractUuidV7Id {}
