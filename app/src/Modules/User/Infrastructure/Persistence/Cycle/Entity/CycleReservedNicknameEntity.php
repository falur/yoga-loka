<?php

declare(strict_types=1);

namespace App\Modules\User\Infrastructure\Persistence\Cycle\Entity;

use App\Modules\User\Infrastructure\Persistence\Cycle\Columns\ReservedNicknameColumns;
use App\Modules\User\Infrastructure\Persistence\Cycle\Repository\CycleReservedNicknameRepository;
use App\Shared\Infrastructure\Persistence\Cycle\HasTimestamps;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\ORM\Parser\Typecast;

#[Entity(
    role: 'reserved_nickname',
    table: ReservedNicknameColumns::TABLE,
    repository: CycleReservedNicknameRepository::class,
    typecast: [Typecast::class],
)]
final class CycleReservedNicknameEntity
{
    use HasTimestamps;

    #[Column(type: 'uuid', name: ReservedNicknameColumns::ID, primary: true)]
    public string $id;

    #[Column(type: 'string(30)', name: ReservedNicknameColumns::NICKNAME)]
    public string $nickname;

    #[Column(type: 'uuid', name: ReservedNicknameColumns::ASSIGNED_USER_ID, nullable: true)]
    public string|null $assignedUserId;
}
