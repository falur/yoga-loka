<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Domain\ValueObject;

use App\Shared\Domain\ValueObject\AbstractUuidV7Id;

/**
 * Идентификатор доставки NotificationRequestedEvent, по которому рассылка обеспечивает
 * идемпотентность (колонка notifications.outbox_id + unique): доставка имеет семантику
 * at-least-once, и повтор той же строки не создаёт второе уведомление. Значение приходит из
 * полезной нагрузки Job, поэтому это тоже UUID v7.
 */
final readonly class NotificationOutboxId extends AbstractUuidV7Id {}
