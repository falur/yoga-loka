<?php

declare(strict_types=1);

namespace App\Modules\Tags\Domain\Entity;

use App\Modules\Tags\Domain\ValueObject\TagText;
use App\Modules\Tags\Repository\TagRepository;
use App\Shared\Domain\Trait\HasTimestamps;
use App\Shared\Domain\ValueObject\TagId;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Cycle\ValueObjectCast;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\ORM\Parser\Typecast;

#[Entity(
    role: 'tag',
    table: 'tags',
    repository: TagRepository::class,
    typecast: [Typecast::class, ValueObjectCast::class],
)]
final class Tag
{
    use HasTimestamps;

    #[Column(type: 'uuid', primary: true, typecast: TagId::class)]
    public private(set) TagId $id;

    // Ширина 64 — намеренный запас над доменным максимумом TagText (50 символов): VO остаётся строже схемы.
    #[Column(type: 'string(64)', typecast: TagText::class)]
    public private(set) TagText $text;

    #[Column(type: 'uuid', name: 'created_by_id', typecast: UserId::class)]
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
}
