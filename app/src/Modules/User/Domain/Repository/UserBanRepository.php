<?php

declare(strict_types=1);

namespace App\Modules\User\Domain\Repository;

use App\Modules\User\Domain\Entity\UserBan;
use App\Modules\User\Domain\ValueObject\UserBanId;
use App\Shared\Domain\ValueObject\UserId;

/**
 * Хранение банов. Бан переживает удаление аккаунта, поэтому это отдельный корень агрегата.
 */
interface UserBanRepository
{
    public function findById(UserBanId $userBanId): UserBan|null;

    public function findActiveByUserId(UserId $userId, \DateTimeImmutable $now): UserBan|null;
}
