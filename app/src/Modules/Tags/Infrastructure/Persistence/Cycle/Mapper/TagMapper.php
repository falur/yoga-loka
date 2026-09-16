<?php

declare(strict_types=1);

namespace App\Modules\Tags\Infrastructure\Persistence\Cycle\Mapper;

use App\Modules\Tags\Domain\Entity\Tag;
use App\Modules\Tags\Domain\ValueObject\TagId;
use App\Modules\Tags\Domain\ValueObject\TagText;
use App\Modules\Tags\Infrastructure\Persistence\Cycle\Entity\CycleTagEntity;
use App\Shared\Domain\ValueObject\UserId;

final readonly class TagMapper
{
    public function toDomain(CycleTagEntity $cycleEntity): Tag
    {
        return Tag::restore(
            id: TagId::fromString($cycleEntity->id),
            text: TagText::fromString($cycleEntity->text),
            createdById: UserId::fromString($cycleEntity->createdById),
            createdAt: $cycleEntity->createdAt,
            updatedAt: $cycleEntity->updatedAt,
        );
    }

    public function toCycleEntity(
        Tag $tag,
        CycleTagEntity|null $cycleEntity = null,
    ): CycleTagEntity {
        $cycleEntity ??= new CycleTagEntity();
        $cycleEntity->id = $tag->id->value();
        $cycleEntity->text = $tag->text->value();
        $cycleEntity->createdById = $tag->createdById->value();
        $cycleEntity->createdAt = $tag->createdAt;
        $cycleEntity->updatedAt = $tag->updatedAt;

        return $cycleEntity;
    }
}
