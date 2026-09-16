<?php

declare(strict_types=1);

namespace App\Modules\Tags\Domain\Entity;

use App\Modules\Tags\Domain\ValueObject\TagId;
use App\Modules\Tags\Domain\ValueObject\TagText;
use App\Shared\Domain\Trait\HasTimestamps;
use App\Shared\Domain\ValueObject\UserId;

final class Tag
{
    use HasTimestamps;

    public private(set) TagId $id;

    public private(set) TagText $text;

    public private(set) UserId $createdById;

    public static function create(TagText $text, UserId $createdBy): self
    {
        $tag = new self();
        $tag->id = TagId::generate();
        $tag->text = $text;
        $tag->createdById = $createdBy;
        $tag->initializeTimestamps();

        return $tag;
    }

    public static function restore(
        TagId $id,
        TagText $text,
        UserId $createdById,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        $tag = new self();
        $tag->id = $id;
        $tag->text = $text;
        $tag->createdById = $createdById;
        $tag->createdAt = $createdAt;
        $tag->updatedAt = $updatedAt;

        return $tag;
    }
}
