<?php

declare(strict_types=1);

namespace App\Modules\Posts\Tests\Unit\Domain\Entity;

use App\Modules\Posts\Domain\Entity\Comment;
use App\Modules\Posts\Domain\ValueObject\CommentDeletedAt;
use App\Modules\Posts\Domain\ValueObject\CommentDeletedBy;
use App\Modules\Posts\Domain\ValueObject\CommentDeletionReason;
use App\Modules\Posts\Domain\ValueObject\CommentParent;
use App\Modules\Posts\Domain\ValueObject\CommentText;
use App\Modules\Posts\Domain\ValueObject\PostId;
use App\Shared\Domain\Exception\InvalidDomainValueException;
use App\Shared\Domain\ValueObject\AbstractUuidV7Id;
use App\Shared\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;

final class CommentEntityTest extends TestCase
{
    public function testCreateInitializesDefaults(): void
    {
        $comment = $this->createComment();

        self::assertTrue(AbstractUuidV7Id::isUuidV7($comment->id->value()));
        self::assertSame('Хороший пост', $comment->text->value());
        self::assertTrue($comment->parent->isEmpty());
        self::assertSame(0, $comment->likesCount->value());
        self::assertSame(0, $comment->repliesCount->value());
        self::assertFalse($comment->isDeleted());
        self::assertTrue($comment->deletedBy->isEmpty());
        self::assertTrue($comment->deletionReason->isEmpty());
        self::assertEquals($comment->createdAt, $comment->updatedAt);
    }

    public function testLikeAndReplyCounters(): void
    {
        $comment = $this->createComment();

        $comment->incrementLikes();
        self::assertSame(1, $comment->likesCount->value());
        $comment->decrementLikes();
        self::assertSame(0, $comment->likesCount->value());

        $comment->incrementReplies();
        $comment->incrementReplies();
        self::assertSame(2, $comment->repliesCount->value());
        $comment->decrementReplies();
        self::assertSame(1, $comment->repliesCount->value());
    }

    public function testDeleteAndRestore(): void
    {
        $comment = $this->createComment();
        $deletedBy = UserId::generate();
        $deletedAt = new \DateTimeImmutable('2026-06-17 10:00:00');

        $comment->delete(
            deletedBy: CommentDeletedBy::by($deletedBy),
            deletedAt: CommentDeletedAt::at($deletedAt),
            deletionReason: CommentDeletionReason::of('Спам'),
        );

        self::assertTrue($comment->isDeleted());
        self::assertSame($deletedBy->value(), $comment->deletedBy->value());
        self::assertSame($deletedAt, $comment->deletedAt->value());
        self::assertSame('Спам', $comment->deletionReason->value());

        $comment->undelete();

        self::assertFalse($comment->isDeleted());
        self::assertTrue($comment->deletedBy->isEmpty());
        self::assertTrue($comment->deletionReason->isEmpty());
    }

    public function testDeleteRejectsEmptyDate(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        $this->createComment()->delete(
            deletedBy: CommentDeletedBy::by(UserId::generate()),
            deletedAt: CommentDeletedAt::notDeleted(),
            deletionReason: CommentDeletionReason::none(),
        );
    }

    public function testDeleteRejectsEmptyUser(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        $this->createComment()->delete(
            deletedBy: CommentDeletedBy::none(),
            deletedAt: CommentDeletedAt::at(new \DateTimeImmutable('2026-06-17 10:00:00')),
            deletionReason: CommentDeletionReason::none(),
        );
    }

    private function createComment(): Comment
    {
        return Comment::create(
            postId: PostId::generate(),
            userId: UserId::generate(),
            text: CommentText::fromString('Хороший пост'),
            parent: CommentParent::none(),
        );
    }
}
