<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Public\Contract;

use App\Modules\Notifications\Public\Dto\NotificationContentDto;

/**
 * Публичный контракт модуля Notifications: единственная синхронная дверь соседей к отправке
 * уведомления.
 *
 * Вызывается на каждого получателя из #[Transactional]-Handler-а модуля-источника: стейджит ровно
 * одно событие уведомления в outbox источника и своего flush не делает — его выполняет источник
 * своим run(), поэтому бизнес-данные и событие коммитятся одной транзакцией.
 *
 * Вид уведомления должен быть заранее зарегистрирован через NotificationTypeRegistryContract:
 * незарегистрированный код вида — ошибка до постановки события (fail-fast).
 */
interface NotificationContract
{
    public function send(string $recipientUserId, NotificationContentDto $content): void;
}
