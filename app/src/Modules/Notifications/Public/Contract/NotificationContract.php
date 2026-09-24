<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Public\Contract;

use App\Modules\Notifications\Public\Dto\NotificationContentDto;

/**
 * Публичный контракт модуля Notifications: единственная синхронная дверь соседей к отправке
 * уведомления.
 *
 * Вызывается на каждого получателя из #[Transactional]-Handler-а модуля-источника: пишет ровно
 * одно событие уведомления в outbox сразу, в уже открытую транзакцию источника, поэтому
 * бизнес-данные и событие коммитятся одной транзакцией. Вызов вне транзакции источника не упадёт:
 * у сценария отправки свой #[Transactional], и событие закрепится его собственной транзакцией —
 * отдельно от бизнес-данных вызывающего, то есть наружу уйдёт факт, которого в данных может и не
 * оказаться. Поэтому вызывать контракт нужно именно внутри транзакции источника.
 *
 * Вид уведомления должен быть заранее зарегистрирован через NotificationTypeRegistryContract:
 * незарегистрированный код вида — ошибка до постановки события (fail-fast).
 */
interface NotificationContract
{
    public function send(string $recipientUserId, NotificationContentDto $content): void;
}
