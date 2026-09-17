<?php

declare(strict_types=1);

namespace App\Modules\User\Domain\Repository;

use App\Modules\User\Domain\Collection\UserCollection;
use App\Modules\User\Domain\Entity\User;
use App\Modules\User\Domain\ValueObject\Email;
use App\Modules\User\Domain\ValueObject\UserNickname;
use App\Shared\Domain\ValueObject\UserId;

/**
 * Хранение аккаунтов. Корень агрегата — User, внутренних сущностей у него нет.
 */
interface UserRepository
{
    public function findById(UserId $userId): User|null;

    public function findByIds(UserId ...$userIds): UserCollection;

    public function countByIds(UserId ...$userIds): int;

    public function findByEmail(Email $email): User|null;

    public function findByNickname(UserNickname $nickname): User|null;

    public function existsByEmail(Email $email): bool;

    public function existsByNickname(UserNickname $nickname): bool;

    public function save(User $user): void;
}
