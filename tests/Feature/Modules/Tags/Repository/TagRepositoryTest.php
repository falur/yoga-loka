<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tags\Repository;

use App\Modules\Tags\Domain\Entity\Tag;
use App\Modules\Tags\Domain\ValueObject\TagText;
use App\Modules\Tags\Infrastructure\Persistence\Cycle\Mapper\TagMapper;
use Tests\Feature\Modules\Tags\TagsRepositoryTestCase;

final class TagRepositoryTest extends TagsRepositoryTestCase
{
    public function testStoresAndRestoresTag(): void
    {
        $user = $this->createUser();
        $this->persist($user);

        $tag = Tag::create(text: TagText::fromString('Йога'), createdBy: $user->id);
        $this->persistTag($tag);
        $this->cleanOrmHeap();

        $byId = $this->tagRepository()->findById($tag->id);
        self::assertInstanceOf(Tag::class, $byId);
        self::assertSame('йога', $byId->text->value());
        self::assertTrue($user->id->equals($byId->createdById));

        self::assertInstanceOf(Tag::class, $this->tagRepository()->findByText(TagText::fromString('йога')));
        self::assertNull($this->tagRepository()->findByText(TagText::fromString('медитация')));
    }

    public function testFindByTexts(): void
    {
        $user = $this->createUser();
        $this->persist($user);
        $this->persistTag(Tag::create(text: TagText::fromString('йога'), createdBy: $user->id));
        $this->persistTag(Tag::create(text: TagText::fromString('медитация'), createdBy: $user->id));
        $this->cleanOrmHeap();

        $found = $this->tagRepository()->findByTexts(
            TagText::fromString('йога'),
            TagText::fromString('медитация'),
        );
        self::assertCount(2, $found);

        self::assertCount(0, $this->tagRepository()->findByTexts(TagText::fromString('пранаяма')));
    }

    public function testFindByTextsWithoutArgumentsReturnsEmptyCollection(): void
    {
        self::assertCount(0, $this->tagRepository()->findByTexts());
    }

    public function testTagTextIsUnique(): void
    {
        $user = $this->createUser();
        $this->persist($user);

        $tagMapper = new TagMapper();
        $this->entityManager()->persist($tagMapper->toCycleEntity(
            Tag::create(text: TagText::fromString('йога'), createdBy: $user->id),
        ));
        $this->entityManager()->persist($tagMapper->toCycleEntity(
            Tag::create(text: TagText::fromString('йога'), createdBy: $user->id),
        ));

        $this->expectException(\Throwable::class);

        $this->entityManager()->run();
    }

    public function testCannotDeleteUserReferencedByTag(): void
    {
        $user = $this->createUser();
        $this->persist($user);
        $this->persistTag(Tag::create(text: TagText::fromString('йога'), createdBy: $user->id));

        $this->expectException(\Throwable::class);

        $this->deleteUser($user);
        $this->entityManager()->run();
    }
}
