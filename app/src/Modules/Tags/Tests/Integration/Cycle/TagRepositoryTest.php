<?php

declare(strict_types=1);

namespace App\Modules\Tags\Tests\Integration\Cycle;

use App\Modules\Tags\Domain\Entity\Tag;
use App\Modules\Tags\Domain\ValueObject\TagText;
use App\Modules\Tags\Infrastructure\Persistence\Cycle\Mapper\TagMapper;

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

    /**
     * Межмодульный внешний ключ tags.created_by_id -> users.id снят: удаление создавшего метку
     * пользователя больше не запрещено базой, метка остаётся с прежним идентификатором автора.
     */
    public function testDeletingUserReferencedByTagIsAllowedWithoutForeignKey(): void
    {
        $user = $this->createUser();
        $this->persist($user);
        $tag = Tag::create(text: TagText::fromString('йога'), createdBy: $user->id);
        $this->persistTag($tag);

        $this->deleteUser($user);
        $this->entityManager()->run();
        $this->cleanOrmHeap();

        $restored = $this->tagRepository()->findById($tag->id);

        self::assertInstanceOf(Tag::class, $restored);
        self::assertTrue($user->id->equals($restored->createdById));
    }
}
