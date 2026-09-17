<?php

declare(strict_types=1);

namespace App\Modules\Tags\Tests\Integration\Spiral;

use App\Modules\Tags\Domain\Entity\Tag;
use App\Modules\Tags\Domain\ValueObject\TagId;
use App\Modules\Tags\Domain\ValueObject\TagText;
use App\Modules\Tags\Public\Contract\TagsContract;

/**
 * Публичный контракт Tags: обе операции раскладываются в существующие сценарии модуля, а внутренняя
 * карта «идентификатор -> текст» переводится в набор публичных меток с ключом-идентификатором.
 * Проверяется и мягкость чтения (несуществующая метка просто отсутствует в наборе).
 */
final class TagsProviderTest extends TagsApplicationTestCase
{
    public function testResolveReusesExistingTagAndCreatesMissingOne(): void
    {
        $user = $this->persistUser();
        $existing = Tag::create(text: TagText::fromString('yoga'), createdBy: $user->id);
        $this->persistTag($existing);
        $this->cleanOrmHeap();

        $resolved = $this->tagsContract()->resolve(
            texts: ['yoga', 'meditation', 'yoga'],
            creatorUserId: $user->id->value(),
        );

        // Порядок первого появления текста сохраняется, повтор схлопывается.
        self::assertCount(2, $resolved->tagIds);
        self::assertSame($existing->id->value(), $resolved->tagIds[0]);
        self::assertNotSame($existing->id->value(), $resolved->tagIds[1]);
    }

    public function testResolveOfEmptySetReturnsNoIdentifiers(): void
    {
        $user = $this->persistUser();
        $this->cleanOrmHeap();

        self::assertSame([], $this->tagsContract()->resolve(texts: [], creatorUserId: $user->id->value())->tagIds);
    }

    public function testTextsByIdsIsKeyedByTagIdAndOmitsMissing(): void
    {
        $user = $this->persistUser();
        $yoga = Tag::create(text: TagText::fromString('yoga'), createdBy: $user->id);
        $this->persistTag($yoga);
        $missing = TagId::generate()->value();
        $this->cleanOrmHeap();

        $tags = $this->tagsContract()->textsByIds([$yoga->id->value(), $missing]);

        self::assertCount(1, $tags);
        $tag = $tags->get($yoga->id->value());
        self::assertNotNull($tag);
        self::assertSame($yoga->id->value(), $tag->id);
        self::assertSame('yoga', $tag->text);
        // Несуществующая метка просто отсутствует в наборе: что с ней делать, решает потребитель.
        self::assertNull($tags->get($missing));
    }

    public function testTextsByIdsReturnsEmptyCollectionForEmptyBatch(): void
    {
        self::assertCount(0, $this->tagsContract()->textsByIds([]));
    }

    private function tagsContract(): TagsContract
    {
        return $this->getContainer()->get(TagsContract::class);
    }
}
