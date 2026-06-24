<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Domain\Collection;

use App\Shared\Domain\Collection\TypedCollection;
use PHPUnit\Framework\TestCase;

/**
 * Конкретная коллекция для проверки базового TypedCollection: TypedCollection abstract,
 * инстанцируются только final-наследники.
 *
 * @extends TypedCollection<array-key, string>
 */
final class TypedCollectionTestDouble extends TypedCollection {}

final class TypedCollectionTest extends TestCase
{
    public function testMapToListTransformsElementsAndKeepsOrder(): void
    {
        $collection = new TypedCollectionTestDouble(['yoga', 'loka', 'spiral']);

        $result = $collection->mapToList(static fn(string $value): string => \mb_strtoupper($value));

        self::assertSame(['YOGA', 'LOKA', 'SPIRAL'], $result);
    }

    public function testMapToListResetsStringKeysToList(): void
    {
        // Кейс TagTextCollection (<string, string>): строковые ключи сбрасываются в 0,1,2.
        $collection = new TypedCollectionTestDouble(['t1' => 'yoga', 't2' => 'zen']);

        $result = $collection->mapToList(static fn(string $text): int => \mb_strlen($text));

        self::assertSame([4, 3], $result);
    }

    public function testMapToListReindexesHoleyIntKeys(): void
    {
        $collection = new TypedCollectionTestDouble([5 => 'a', 9 => 'b', 12 => 'c']);

        $result = $collection->mapToList(static fn(string $value): string => $value);

        self::assertSame(['a', 'b', 'c'], $result);
    }

    public function testMapToListOnEmptyCollectionReturnsEmptyList(): void
    {
        $collection = new TypedCollectionTestDouble();

        $result = $collection->mapToList(static fn(string $value): string => $value);

        self::assertSame([], $result);
    }
}
