<?php

declare(strict_types=1);

namespace App\Modules\Tags\Infrastructure\Persistence\Cycle\Entity;

use App\Modules\Tags\Infrastructure\Persistence\Cycle\Columns\TagColumns;
use App\Modules\Tags\Infrastructure\Persistence\Cycle\Repository\CycleTagRepository;
use App\Shared\Infrastructure\Persistence\Cycle\HasTimestamps;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\ORM\Parser\Typecast;

#[Entity(
    role: 'tag',
    table: TagColumns::TABLE,
    repository: CycleTagRepository::class,
    typecast: [Typecast::class],
)]
final class CycleTagEntity
{
    use HasTimestamps;

    #[Column(type: 'uuid', name: TagColumns::ID, primary: true)]
    public string $id;

    // Ширина 64 — намеренный запас над доменным максимумом TagText (50 символов): VO остаётся строже схемы.
    #[Column(type: 'string(64)', name: TagColumns::TEXT)]
    public string $text;

    #[Column(type: 'uuid', name: TagColumns::CREATED_BY_ID)]
    public string $createdById;
}
