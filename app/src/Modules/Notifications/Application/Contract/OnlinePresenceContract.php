<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Contract;

use App\Shared\Domain\ValueObject\UserId;

/**
 * Проверка, онлайн ли пользователь (подключён к своему персональному каналу). Реализация — в
 * Infrastructure (Centrifugo). Метод не бросает исключений: любую неопределённость (сбой проверки,
 * недоступность) реализация трактует как «не онлайн» (fail-open — доставка важнее подавления).
 */
interface OnlinePresenceContract
{
    public function isOnline(UserId $userId): bool;
}
