<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Posts\Infrastructure\Persistence\Cycle\Mapper;

use App\Modules\Posts\Domain\Entity\Comment;
use App\Modules\Posts\Domain\ValueObject\CommentDeletedAt;
use App\Modules\Posts\Domain\ValueObject\CommentDeletedBy;
use App\Modules\Posts\Domain\ValueObject\CommentDeletionReason;
use App\Modules\Posts\Domain\ValueObject\CommentParent;
use App\Modules\Posts\Domain\ValueObject\CommentText;
use App\Modules\Posts\Domain\ValueObject\PostId;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Mapper\CommentMapper;
use App\Shared\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

/**
 * Переносит проверки удалённых typecast-классов Comment (CommentParentTypecast,
 * CommentDeletedAtTypecast, CommentDeletedByTypecast, CommentDeletionReasonTypecast) на Mapper,
 * куда переехала их логика null-bridging между nullable-колонкой и value object с сентинелом.
 */
final class CommentMapperTest extends TestCase
{
    public function testMapsCommentWithAllOptionalValuesFilled(): void
    {
        $mapper = new CommentMapper();
        $parentId = Uuid::uuid7()->toString();
        $deletedBy = UserId::generate();
        $deletedAt = new \DateTimeImmutable('2026-06-17 11:00:00');

        $comment = Comment::create(
            postId: PostId::generate(),
            userId: UserId::generate(),
            text: CommentText::fromString('Привет'),
            parent: CommentParent::pointingTo($parentId),
        );
        $comment->delete(
            deletedBy: CommentDeletedBy::by($deletedBy),
            deletedAt: CommentDeletedAt::at($deletedAt),
            deletionReason: CommentDeletionReason::of('Спам'),
        );

        $cycleEntity = $mapper->toCycleEntity($comment);

        self::assertSame($comment->id->value(), $cycleEntity->id);
        self::assertSame($parentId, $cycleEntity->parentCommentId);
        self::assertSame($deletedAt, $cycleEntity->deletedAt);
        self::assertSame($deletedBy->value(), $cycleEntity->deletedById);
        self::assertSame('Спам', $cycleEntity->deletionReason);

        $restoredComment = $mapper->toDomain($cycleEntity);

        self::assertTrue($comment->id->equals($restoredComment->id));
        self::assertSame($parentId, $restoredComment->parent->value());
        self::assertTrue($restoredComment->isDeleted());
        self::assertSame($deletedAt, $restoredComment->deletedAt->value());
        self::assertSame($deletedBy->value(), $restoredComment->deletedBy->value());
        self::assertSame('Спам', $restoredComment->deletionReason->value());
    }

    public function testMapsCommentWithEmptyOptionalValuesToNullColumns(): void
    {
        $mapper = new CommentMapper();
        $comment = Comment::create(
            postId: PostId::generate(),
            userId: UserId::generate(),
            text: CommentText::fromString('Привет'),
            parent: CommentParent::none(),
        );

        $cycleEntity = $mapper->toCycleEntity($comment);

        self::assertNull($cycleEntity->parentCommentId);
        self::assertNull($cycleEntity->deletedAt);
        self::assertNull($cycleEntity->deletedById);
        self::assertNull($cycleEntity->deletionReason);

        $restoredComment = $mapper->toDomain($cycleEntity);

        self::assertTrue($restoredComment->parent->isEmpty());
        self::assertFalse($restoredComment->isDeleted());
        self::assertTrue($restoredComment->deletedBy->isEmpty());
        self::assertTrue($restoredComment->deletionReason->isEmpty());
    }

    public function testToCycleEntityUpdatesGivenInstanceInPlace(): void
    {
        $mapper = new CommentMapper();
        $comment = Comment::create(
            postId: PostId::generate(),
            userId: UserId::generate(),
            text: CommentText::fromString('Привет'),
            parent: CommentParent::none(),
        );

        $cycleEntity = $mapper->toCycleEntity($comment);
        $comment->incrementLikes();
        $updatedCycleEntity = $mapper->toCycleEntity($comment, $cycleEntity);

        self::assertSame($cycleEntity, $updatedCycleEntity);
        self::assertSame(1, $updatedCycleEntity->likesCount);
    }
}
