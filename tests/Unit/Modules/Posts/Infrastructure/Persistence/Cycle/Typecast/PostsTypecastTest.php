<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Posts\Infrastructure\Persistence\Cycle\Typecast;

use App\Modules\Posts\Domain\ValueObject\BlockUnblockedAt;
use App\Modules\Posts\Domain\ValueObject\BlockUnblockedBy;
use App\Modules\Posts\Domain\ValueObject\BlockUnblockedReason;
use App\Modules\Posts\Domain\ValueObject\CommentDeletedAt;
use App\Modules\Posts\Domain\ValueObject\CommentDeletedBy;
use App\Modules\Posts\Domain\ValueObject\CommentDeletionReason;
use App\Modules\Posts\Domain\ValueObject\CommentParent;
use App\Modules\Posts\Domain\ValueObject\PostDeletion;
use App\Modules\Posts\Domain\ValueObject\PostLesson;
use App\Modules\Posts\Domain\ValueObject\PostOriginal;
use App\Modules\Posts\Domain\ValueObject\PostPractice;
use App\Modules\Posts\Domain\ValueObject\PostText;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Typecast\BlockUnblockedAtTypecast;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Typecast\BlockUnblockedByTypecast;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Typecast\BlockUnblockedReasonTypecast;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Typecast\CommentDeletedAtTypecast;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Typecast\CommentDeletedByTypecast;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Typecast\CommentDeletionReasonTypecast;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Typecast\CommentParentTypecast;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Typecast\PostDeletionTypecast;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Typecast\PostLessonTypecast;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Typecast\PostOriginalTypecast;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Typecast\PostPracticeTypecast;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Typecast\PostTextTypecast;
use App\Shared\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class PostsTypecastTest extends TestCase
{
    public function testPostTextTypecastHandlesNullableString(): void
    {
        self::assertTrue(PostTextTypecast::castDatabaseValue(null)->isEmpty());
        self::assertSame('Привет', PostTextTypecast::castDatabaseValue('Привет')->value());
        self::assertNull(PostTextTypecast::uncastValue(null));
        self::assertNull(PostTextTypecast::uncastValue(PostText::none()));
        self::assertSame('Привет', PostTextTypecast::uncastValue(PostText::fromString('Привет')));
    }

    public function testCommentDeletionReasonTypecastHandlesNullableString(): void
    {
        self::assertTrue(CommentDeletionReasonTypecast::castDatabaseValue(null)->isEmpty());
        self::assertSame('Спам', CommentDeletionReasonTypecast::castDatabaseValue('Спам')->value());
        self::assertNull(CommentDeletionReasonTypecast::uncastValue(null));
        self::assertNull(CommentDeletionReasonTypecast::uncastValue(CommentDeletionReason::none()));
        self::assertSame('Спам', CommentDeletionReasonTypecast::uncastValue(CommentDeletionReason::of('Спам')));
    }

    public function testBlockUnblockedReasonTypecastHandlesNullableString(): void
    {
        self::assertTrue(BlockUnblockedReasonTypecast::castDatabaseValue(null)->isEmpty());
        self::assertSame('Ошибка', BlockUnblockedReasonTypecast::castDatabaseValue('Ошибка')->value());
        self::assertNull(BlockUnblockedReasonTypecast::uncastValue(null));
        self::assertNull(BlockUnblockedReasonTypecast::uncastValue(BlockUnblockedReason::none()));
        self::assertSame('Ошибка', BlockUnblockedReasonTypecast::uncastValue(BlockUnblockedReason::of('Ошибка')));
    }

    public function testPostLessonTypecastHandlesNullableUuid(): void
    {
        $uuid = Uuid::uuid7()->toString();

        self::assertTrue(PostLessonTypecast::castDatabaseValue(null)->isEmpty());
        self::assertSame($uuid, PostLessonTypecast::castDatabaseValue($uuid)->value());
        self::assertNull(PostLessonTypecast::uncastValue(null));
        self::assertNull(PostLessonTypecast::uncastValue(PostLesson::none()));
        self::assertSame($uuid, PostLessonTypecast::uncastValue(PostLesson::pointingTo($uuid)));
    }

    public function testPostPracticeTypecastHandlesNullableUuid(): void
    {
        $uuid = Uuid::uuid7()->toString();

        self::assertTrue(PostPracticeTypecast::castDatabaseValue(null)->isEmpty());
        self::assertSame($uuid, PostPracticeTypecast::castDatabaseValue($uuid)->value());
        self::assertNull(PostPracticeTypecast::uncastValue(null));
        self::assertNull(PostPracticeTypecast::uncastValue(PostPractice::none()));
        self::assertSame($uuid, PostPracticeTypecast::uncastValue(PostPractice::pointingTo($uuid)));
    }

    public function testPostOriginalTypecastHandlesNullableUuid(): void
    {
        $uuid = Uuid::uuid7()->toString();

        self::assertTrue(PostOriginalTypecast::castDatabaseValue(null)->isEmpty());
        self::assertSame($uuid, PostOriginalTypecast::castDatabaseValue($uuid)->value());
        self::assertNull(PostOriginalTypecast::uncastValue(null));
        self::assertNull(PostOriginalTypecast::uncastValue(PostOriginal::none()));
        self::assertSame($uuid, PostOriginalTypecast::uncastValue(PostOriginal::pointingTo($uuid)));
    }

    public function testCommentParentTypecastHandlesNullableUuid(): void
    {
        $uuid = Uuid::uuid7()->toString();

        self::assertTrue(CommentParentTypecast::castDatabaseValue(null)->isEmpty());
        self::assertSame($uuid, CommentParentTypecast::castDatabaseValue($uuid)->value());
        self::assertNull(CommentParentTypecast::uncastValue(null));
        self::assertNull(CommentParentTypecast::uncastValue(CommentParent::none()));
        self::assertSame($uuid, CommentParentTypecast::uncastValue(CommentParent::pointingTo($uuid)));
    }

    public function testCommentDeletedByTypecastHandlesNullableUuid(): void
    {
        $userId = UserId::generate();

        self::assertTrue(CommentDeletedByTypecast::castDatabaseValue(null)->isEmpty());
        self::assertSame($userId->value(), CommentDeletedByTypecast::castDatabaseValue($userId->value())->value());
        self::assertNull(CommentDeletedByTypecast::uncastValue(null));
        self::assertNull(CommentDeletedByTypecast::uncastValue(CommentDeletedBy::none()));
        self::assertSame($userId->value(), CommentDeletedByTypecast::uncastValue(CommentDeletedBy::by($userId)));
    }

    public function testBlockUnblockedByTypecastHandlesNullableUuid(): void
    {
        $userId = UserId::generate();

        self::assertTrue(BlockUnblockedByTypecast::castDatabaseValue(null)->isEmpty());
        self::assertSame($userId->value(), BlockUnblockedByTypecast::castDatabaseValue($userId->value())->value());
        self::assertNull(BlockUnblockedByTypecast::uncastValue(null));
        self::assertNull(BlockUnblockedByTypecast::uncastValue(BlockUnblockedBy::none()));
        self::assertSame($userId->value(), BlockUnblockedByTypecast::uncastValue(BlockUnblockedBy::by($userId)));
    }

    public function testPostDeletionTypecastHandlesNullableDate(): void
    {
        $deletedAt = new \DateTimeImmutable('2026-06-17 10:00:00');

        self::assertFalse(PostDeletionTypecast::castDatabaseValue(null)->isDeleted());
        self::assertSame($deletedAt, PostDeletionTypecast::castDatabaseValue($deletedAt)->value());
        self::assertSame(
            $deletedAt->format(\DateTimeInterface::ATOM),
            PostDeletionTypecast::castDatabaseValue($deletedAt->format(\DateTimeInterface::ATOM))->value()?->format(\DateTimeInterface::ATOM),
        );
        self::assertSame($deletedAt, PostDeletionTypecast::uncastValue(PostDeletion::deletedAt($deletedAt)));
        self::assertNull(PostDeletionTypecast::uncastValue(null));
    }

    public function testPostDeletionTypecastConvertsMutableDate(): void
    {
        $mutableDate = new \DateTime('2026-06-17 10:00:00');

        self::assertEquals(
            \DateTimeImmutable::createFromInterface($mutableDate),
            PostDeletionTypecast::castDatabaseValue($mutableDate)->value(),
        );
    }

    public function testCommentDeletedAtTypecastHandlesNullableDate(): void
    {
        $deletedAt = new \DateTimeImmutable('2026-06-17 11:00:00');
        $mutableDate = new \DateTime('2026-06-17 11:00:00');

        self::assertFalse(CommentDeletedAtTypecast::castDatabaseValue(null)->isDeleted());
        self::assertSame($deletedAt, CommentDeletedAtTypecast::castDatabaseValue($deletedAt)->value());
        self::assertEquals(
            \DateTimeImmutable::createFromInterface($mutableDate),
            CommentDeletedAtTypecast::castDatabaseValue($mutableDate)->value(),
        );
        self::assertSame(
            $deletedAt->format(\DateTimeInterface::ATOM),
            CommentDeletedAtTypecast::castDatabaseValue($deletedAt->format(\DateTimeInterface::ATOM))->value()?->format(\DateTimeInterface::ATOM),
        );
        self::assertSame($deletedAt, CommentDeletedAtTypecast::uncastValue(CommentDeletedAt::at($deletedAt)));
        self::assertNull(CommentDeletedAtTypecast::uncastValue(null));
    }

    public function testBlockUnblockedAtTypecastHandlesNullableDate(): void
    {
        $unblockedAt = new \DateTimeImmutable('2026-06-17 12:00:00');
        $mutableDate = new \DateTime('2026-06-17 12:00:00');

        self::assertFalse(BlockUnblockedAtTypecast::castDatabaseValue(null)->isUnblocked());
        self::assertSame($unblockedAt, BlockUnblockedAtTypecast::castDatabaseValue($unblockedAt)->value());
        self::assertEquals(
            \DateTimeImmutable::createFromInterface($mutableDate),
            BlockUnblockedAtTypecast::castDatabaseValue($mutableDate)->value(),
        );
        self::assertSame(
            $unblockedAt->format(\DateTimeInterface::ATOM),
            BlockUnblockedAtTypecast::castDatabaseValue($unblockedAt->format(\DateTimeInterface::ATOM))->value()?->format(\DateTimeInterface::ATOM),
        );
        self::assertSame($unblockedAt, BlockUnblockedAtTypecast::uncastValue(BlockUnblockedAt::at($unblockedAt)));
        self::assertNull(BlockUnblockedAtTypecast::uncastValue(null));
    }
}
