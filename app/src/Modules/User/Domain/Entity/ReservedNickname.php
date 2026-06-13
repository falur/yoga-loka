<?php

declare(strict_types=1);

namespace App\Modules\User\Domain\Entity;

use App\Modules\User\Domain\ValueObject\ReservedNicknameHolder;
use App\Modules\User\Domain\ValueObject\ReservedNicknameId;
use App\Modules\User\Domain\ValueObject\UserNickname;
use App\Modules\User\Infrastructure\Cycle\ReservedNicknameHolderTypecast;
use App\Modules\User\Repository\ReservedNicknameRepository;
use App\Shared\Domain\Trait\HasTimestamps;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Cycle\ValueObjectCast;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\ORM\Parser\Typecast;

#[Entity(
    role: 'reserved_nickname',
    table: 'reserved_nicknames',
    repository: ReservedNicknameRepository::class,
    typecast: [Typecast::class, ValueObjectCast::class],
)]
final class ReservedNickname
{
    use HasTimestamps;

    #[Column(type: 'uuid', primary: true, typecast: ReservedNicknameId::class)]
    public private(set) ReservedNicknameId $id;

    #[Column(type: 'string(30)', typecast: UserNickname::class)]
    public private(set) UserNickname $nickname;

    #[Column(type: 'uuid', name: 'assigned_user_id', nullable: true, typecast: ReservedNicknameHolderTypecast::class)]
    public private(set) ReservedNicknameHolder $holder;

    public static function create(UserNickname $nickname): self
    {
        $reservedNickname = new self();
        $reservedNickname->id = ReservedNicknameId::generate();
        $reservedNickname->nickname = $nickname;
        $reservedNickname->holder = ReservedNicknameHolder::unassigned();
        $reservedNickname->initializeTimestamps();

        return $reservedNickname;
    }

    public function assignTo(UserId $userId): void
    {
        $this->holder = ReservedNicknameHolder::assignedTo($userId);
        $this->touch();
    }

    public function isAssignedTo(UserId $userId): bool
    {
        return $this->holder->isAssignedTo($userId);
    }
}
