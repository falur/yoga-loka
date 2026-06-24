<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Domain\Pagination;

use App\Shared\Domain\Collection\TypedCollection;
use App\Shared\Domain\Pagination\CursorSlice;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

/**
 * @extends TypedCollection<int, string>
 */
final class CursorSliceItemCollection extends TypedCollection {}

final class CursorSliceTest extends TestCase
{
    public function testOverfetchedPageExposesNextCursorOfLastVisible(): void
    {
        $slice = CursorSlice::fromOverfetched(
            overfetched: new Collection(['a', 'b', 'c']),
            limit: 2,
            cursorOf: static fn(string $value): string => $value,
        );

        self::assertSame(['a', 'b'], $slice->items->all());
        self::assertSame('b', $slice->nextCursor);
    }

    public function testExactlyLimitHasNoNextCursor(): void
    {
        $slice = CursorSlice::fromOverfetched(
            overfetched: new Collection(['a', 'b']),
            limit: 2,
            cursorOf: static fn(string $value): string => $value,
        );

        self::assertSame(['a', 'b'], $slice->items->all());
        self::assertNull($slice->nextCursor);
    }

    public function testFewerThanLimitHasNoNextCursor(): void
    {
        $slice = CursorSlice::fromOverfetched(
            overfetched: new Collection(['a']),
            limit: 2,
            cursorOf: static fn(string $value): string => $value,
        );

        self::assertSame(['a'], $slice->items->all());
        self::assertNull($slice->nextCursor);
    }

    public function testEmptyPageHasNoNextCursor(): void
    {
        $slice = CursorSlice::fromOverfetched(
            overfetched: new Collection([]),
            limit: 2,
            cursorOf: static fn(string $value): string => $value,
        );

        self::assertSame([], $slice->items->all());
        self::assertNull($slice->nextCursor);
    }

    public function testPreservesConcreteCollectionTypeAfterTake(): void
    {
        $slice = CursorSlice::fromOverfetched(
            overfetched: new CursorSliceItemCollection(['a', 'b', 'c']),
            limit: 2,
            cursorOf: static fn(string $value): string => $value,
        );

        self::assertInstanceOf(CursorSliceItemCollection::class, $slice->items);
        self::assertSame(['a', 'b'], $slice->items->all());
    }
}
