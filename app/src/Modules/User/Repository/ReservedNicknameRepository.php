<?php

declare(strict_types=1);

namespace App\Modules\User\Repository;

use App\Modules\User\Domain\Entity\ReservedNickname;
use App\Modules\User\Domain\ValueObject\UserNickname;
use Cycle\ORM\Select\Repository;

/**
 * @extends Repository<ReservedNickname>
 */
final class ReservedNicknameRepository extends Repository
{
    public function findByNickname(UserNickname $nickname): ReservedNickname|null
    {
        return $this->findOne(['nickname' => $nickname->value()]);
    }

    public function isReserved(UserNickname $nickname): bool
    {
        return $this->findByNickname($nickname) !== null;
    }
}
