<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Contract;

use App\Shared\Domain\ValueObject\UserId;

interface NotificationBulkWriterContract
{
    /**
     * Массово отмечает все непрочитанные уведомления получателя прочитанными одним UPDATE.
     * Возвращает число затронутых строк.
     */
    public function markAllReadForRecipient(UserId $userId, \DateTimeImmutable $readAt): int;
}
