<?php

declare(strict_types=1);

namespace App\Modules\User\Infrastructure\Persistence\Cycle\Repository;

use App\Modules\User\Domain\Entity\ReservedNickname;
use App\Modules\User\Domain\Repository\ReservedNicknameRepository;
use App\Modules\User\Domain\ValueObject\UserNickname;
use App\Shared\Infrastructure\Persistence\Cycle\AbstractRepository;

/**
 * @extends AbstractRepository<ReservedNickname>
 */
final class CycleReservedNicknameRepository extends AbstractRepository implements ReservedNicknameRepository
{
    #[\Override]
    public function findByNickname(UserNickname $nickname): ReservedNickname|null
    {
        return $this->findOne(['nickname' => $nickname->value()]);
    }

    #[\Override]
    public function isReserved(UserNickname $nickname): bool
    {
        return $this->findByNickname($nickname) !== null;
    }
}
