<?php

declare(strict_types=1);

namespace App\Modules\Posts\Tests\Unit\Domain\ValueObject;

use App\Modules\Posts\Domain\ValueObject\BlockUnblockedAt;
use App\Modules\Posts\Domain\ValueObject\BlockUnblockedBy;
use App\Modules\Posts\Domain\ValueObject\CommentDeletedAt;
use App\Modules\Posts\Domain\ValueObject\CommentDeletedBy;
use App\Modules\Posts\Domain\ValueObject\PostDeletion;
use App\Shared\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;

final class PostsNullObjectValueObjectTest extends TestCase
{
    public function testPostDeletionNullObject(): void
    {
        $at = new \DateTimeImmutable('2026-06-17 10:00:00');
        $notDeleted = PostDeletion::notDeleted();
        $deleted = PostDeletion::deletedAt($at);

        self::assertFalse($notDeleted->isDeleted());
        self::assertNull($notDeleted->value());
        self::assertSame('', (string) $notDeleted);
        self::assertNull($notDeleted->jsonSerialize());

        self::assertTrue($deleted->isDeleted());
        self::assertSame($at, $deleted->value());
        self::assertSame($at->format(\DateTimeInterface::ATOM), (string) $deleted);
        self::assertSame($at->format(\DateTimeInterface::ATOM), $deleted->jsonSerialize());

        self::assertTrue($notDeleted->equals(PostDeletion::notDeleted()));
        self::assertTrue($deleted->equals(PostDeletion::deletedAt($at)));
        self::assertFalse($deleted->equals($notDeleted));
        self::assertFalse($deleted->equals(PostDeletion::deletedAt(new \DateTimeImmutable('2026-06-18 10:00:00'))));
    }

    public function testCommentDeletedAtNullObject(): void
    {
        $at = new \DateTimeImmutable('2026-06-17 11:00:00');
        $notDeleted = CommentDeletedAt::notDeleted();
        $deleted = CommentDeletedAt::at($at);

        self::assertFalse($notDeleted->isDeleted());
        self::assertNull($notDeleted->value());
        self::assertSame('', (string) $notDeleted);
        self::assertNull($notDeleted->jsonSerialize());

        self::assertTrue($deleted->isDeleted());
        self::assertSame($at, $deleted->value());
        self::assertSame($at->format(\DateTimeInterface::ATOM), (string) $deleted);
        self::assertSame($at->format(\DateTimeInterface::ATOM), $deleted->jsonSerialize());

        self::assertTrue($notDeleted->equals(CommentDeletedAt::notDeleted()));
        self::assertFalse($deleted->equals($notDeleted));
        self::assertFalse($deleted->equals(CommentDeletedAt::at(new \DateTimeImmutable('2026-06-18 11:00:00'))));
    }

    public function testBlockUnblockedAtNullObject(): void
    {
        $at = new \DateTimeImmutable('2026-06-17 12:00:00');
        $active = BlockUnblockedAt::notUnblocked();
        $unblocked = BlockUnblockedAt::at($at);

        self::assertFalse($active->isUnblocked());
        self::assertNull($active->value());
        self::assertSame('', (string) $active);
        self::assertNull($active->jsonSerialize());

        self::assertTrue($unblocked->isUnblocked());
        self::assertSame($at, $unblocked->value());
        self::assertSame($at->format(\DateTimeInterface::ATOM), (string) $unblocked);
        self::assertSame($at->format(\DateTimeInterface::ATOM), $unblocked->jsonSerialize());

        self::assertTrue($active->equals(BlockUnblockedAt::notUnblocked()));
        self::assertFalse($unblocked->equals($active));
        self::assertFalse($unblocked->equals(BlockUnblockedAt::at(new \DateTimeImmutable('2026-06-18 12:00:00'))));
    }

    public function testCommentDeletedByNullObject(): void
    {
        $userId = UserId::generate();
        $none = CommentDeletedBy::none();
        $by = CommentDeletedBy::by($userId);

        self::assertTrue($none->isEmpty());
        self::assertNull($none->value());
        self::assertSame('', (string) $none);
        self::assertNull($none->jsonSerialize());

        self::assertFalse($by->isEmpty());
        self::assertSame($userId->value(), $by->value());
        self::assertSame($userId->value(), (string) $by);
        self::assertSame($userId->value(), $by->jsonSerialize());

        self::assertTrue($by->equals(CommentDeletedBy::by($userId)));
        self::assertFalse($by->equals($none));
    }

    public function testBlockUnblockedByNullObject(): void
    {
        $userId = UserId::generate();
        $none = BlockUnblockedBy::none();
        $by = BlockUnblockedBy::by($userId);

        self::assertTrue($none->isEmpty());
        self::assertNull($none->value());
        self::assertSame('', (string) $none);
        self::assertNull($none->jsonSerialize());

        self::assertFalse($by->isEmpty());
        self::assertSame($userId->value(), $by->value());
        self::assertSame($userId->value(), (string) $by);
        self::assertSame($userId->value(), $by->jsonSerialize());

        self::assertTrue($by->equals(BlockUnblockedBy::by($userId)));
        self::assertFalse($by->equals($none));
    }
}
