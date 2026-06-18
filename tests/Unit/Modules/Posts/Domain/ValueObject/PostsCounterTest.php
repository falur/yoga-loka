<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Posts\Domain\ValueObject;

use App\Modules\Posts\Domain\ValueObject\CommentsCount;
use App\Modules\Posts\Domain\ValueObject\LikesCount;
use App\Modules\Posts\Domain\ValueObject\MediaPosition;
use App\Modules\Posts\Domain\ValueObject\RepliesCount;
use App\Modules\Posts\Domain\ValueObject\RepostsCount;
use App\Shared\Domain\Exception\InvalidDomainValueException;
use PHPUnit\Framework\TestCase;

final class PostsCounterTest extends TestCase
{
    public function testLikesCountIncrementsAndDecrements(): void
    {
        $count = LikesCount::zero();

        self::assertSame(0, $count->value());
        self::assertSame('0', (string) $count);
        self::assertSame(2, $count->increment()->increment()->value());
        self::assertSame(0, $count->increment()->decrement()->value());
        self::assertSame(1, $count->increment()->jsonSerialize());
    }

    public function testRepostsCountIncrementsAndDecrements(): void
    {
        self::assertSame(1, RepostsCount::zero()->increment()->value());
        self::assertSame(0, RepostsCount::zero()->increment()->decrement()->value());
    }

    public function testCommentsCountIncrementsAndDecrements(): void
    {
        self::assertSame(1, CommentsCount::zero()->increment()->value());
        self::assertSame(0, CommentsCount::zero()->increment()->decrement()->value());
    }

    public function testRepliesCountIncrementsAndDecrements(): void
    {
        self::assertSame(1, RepliesCount::zero()->increment()->value());
        self::assertSame(0, RepliesCount::zero()->increment()->decrement()->value());
    }

    public function testCountersEquality(): void
    {
        self::assertTrue(LikesCount::fromInt(3)->equals(LikesCount::fromInt(3)));
        self::assertFalse(LikesCount::fromInt(3)->equals(LikesCount::fromInt(4)));
        self::assertTrue(LikesCount::supports(5));
        self::assertFalse(LikesCount::supports(-1));
    }

    public function testLikesCountDecrementBelowZeroFails(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        LikesCount::zero()->decrement();
    }

    public function testRepostsCountDecrementBelowZeroFails(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        RepostsCount::zero()->decrement();
    }

    public function testCommentsCountDecrementBelowZeroFails(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        CommentsCount::zero()->decrement();
    }

    public function testRepliesCountDecrementBelowZeroFails(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        RepliesCount::zero()->decrement();
    }

    public function testLikesCountIncrementAtUpperBoundFails(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        LikesCount::fromInt(PHP_INT_MAX)->increment();
    }

    public function testRepostsCountIncrementAtUpperBoundFails(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        RepostsCount::fromInt(PHP_INT_MAX)->increment();
    }

    public function testCommentsCountIncrementAtUpperBoundFails(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        CommentsCount::fromInt(PHP_INT_MAX)->increment();
    }

    public function testRepliesCountIncrementAtUpperBoundFails(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        RepliesCount::fromInt(PHP_INT_MAX)->increment();
    }

    public function testMediaPositionAllowsZeroAndPositive(): void
    {
        self::assertSame(0, MediaPosition::fromInt(0)->value());
        self::assertSame(5, MediaPosition::fromInt(5)->value());
        self::assertTrue(MediaPosition::fromInt(2)->equals(MediaPosition::fromInt(2)));
        self::assertSame(3, MediaPosition::fromInt(3)->jsonSerialize());
        self::assertSame('3', (string) MediaPosition::fromInt(3));
    }

    public function testMediaPositionRejectsNegative(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        MediaPosition::fromInt(-1);
    }
}
