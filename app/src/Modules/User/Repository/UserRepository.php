<?php

declare(strict_types=1);

namespace App\Modules\User\Repository;

use App\Modules\User\Domain\Entity\User;
use App\Modules\User\Domain\ValueObject\Email;
use App\Modules\User\Domain\ValueObject\UserNickname;
use App\Shared\Domain\ValueObject\UserId;
use Cycle\ORM\Select\Repository;

/**
 * @extends Repository<User>
 */
final class UserRepository extends Repository
{
    public function findById(UserId $userId): User|null
    {
        return $this->findByPK($userId->value());
    }

    public function findByEmail(Email $email): User|null
    {
        return $this->findOne(['email' => $email->value()]);
    }

    public function findByNickname(UserNickname $nickname): User|null
    {
        return $this->findOne(['nickname' => $nickname->value()]);
    }

    public function existsByEmail(Email $email): bool
    {
        return $this->findByEmail($email) !== null;
    }

    public function existsByNickname(UserNickname $nickname): bool
    {
        return $this->findByNickname($nickname) !== null;
    }
}
