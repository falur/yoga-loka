<?php

declare(strict_types=1);

namespace App\Modules\User\Domain\Repository;

use App\Modules\User\Domain\Entity\ReservedNickname;
use App\Modules\User\Domain\ValueObject\UserNickname;

/**
 * Хранение броней псевдонимов. Бронь переживает удаление аккаунта, поэтому это отдельный
 * корень агрегата.
 */
interface ReservedNicknameRepository
{
    public function findByNickname(UserNickname $nickname): ReservedNickname|null;

    public function isReserved(UserNickname $nickname): bool;
}
