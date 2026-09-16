<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\Repository;

use App\Modules\Auth\Domain\Entity\RegistrationTicket;
use App\Modules\Auth\Domain\ValueObject\SecretHash;

/**
 * Хранение талонов регистрации. Корень агрегата — RegistrationTicket, внутренних сущностей
 * у него нет.
 */
interface RegistrationTicketRepository
{
    /**
     * Непогашенный талон по хэшу с блокировкой строки. Срок проверяет доменный сценарий.
     */
    public function findActiveByHashForUpdate(SecretHash $ticketHash): RegistrationTicket|null;

    /**
     * Ставит талон в текущую запись без прогона: он уйдёт в базу тем прогоном, который
     * завершает запись сценария.
     */
    public function add(RegistrationTicket $registrationTicket): void;

    /**
     * Сохраняет талон своим прогоном: вместе с ним в базу уходит всё, что уже поставлено
     * в текущую запись.
     */
    public function save(RegistrationTicket $registrationTicket): void;
}
