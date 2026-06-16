<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Contract;

use App\Modules\Notifications\Application\Dto\NotificationContent;
use App\Shared\Domain\ValueObject\UserId;

/**
 * Публичная точка отправки уведомления для других модулей. Вызывается из #[Transactional]-Handler
 * модуля-источника на каждого получателя: стейджит ровно один NotificationRequested в outbox,
 * собственный EntityManager::run() НЕ вызывает — flush делает источник своим run().
 */
interface NotificationSenderContract
{
    public function send(UserId $recipient, NotificationContent $content): void;
}
