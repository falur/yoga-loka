<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Tags\Domain\Entity;

use App\Modules\Tags\Domain\Entity\Tag;
use App\Modules\Tags\Domain\ValueObject\TagText;
use App\Shared\Domain\ValueObject\AbstractUuidV7Id;
use App\Shared\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;

final class TagEntityTest extends TestCase
{
    public function testTagCreate(): void
    {
        $createdBy = UserId::generate();
        $tag = Tag::create(text: TagText::fromString('йога'), createdBy: $createdBy);

        self::assertTrue(AbstractUuidV7Id::isUuidV7($tag->id->value()));
        self::assertSame('йога', $tag->text->value());
        self::assertTrue($createdBy->equals($tag->createdById));
    }
}
