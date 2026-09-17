<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\Repository;

use App\Modules\Auth\Domain\Entity\LoginCode;
use App\Modules\Auth\Domain\ValueObject\EmailAddress;

/**
 * Хранение кодов входа. Корень агрегата — LoginCode, внутренних сущностей у него нет.
 */
interface LoginCodeRepository
{
    /**
     * Последний непогашенный код по email с блокировкой строки. Срок не фильтруется в запросе:
     * различение «истёк» и «кода нет» делает доменный сценарий.
     */
    public function findActiveByEmailForUpdate(EmailAddress $email): LoginCode|null;

    /**
     * Последний непогашенный код по email без блокировки — для троттлинга повторной отправки.
     */
    public function findActiveByEmail(EmailAddress $email): LoginCode|null;

    /**
     * Ставит код в текущую запись без прогона: он уйдёт в базу тем прогоном, который
     * завершает запись сценария.
     */
    public function add(LoginCode $loginCode): void;

    /**
     * Сохраняет код своим прогоном: вместе с ним в базу уходит всё, что уже поставлено
     * в текущую запись.
     */
    public function save(LoginCode $loginCode): void;
}
