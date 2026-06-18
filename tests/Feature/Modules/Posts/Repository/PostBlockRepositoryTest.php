<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Posts\Repository;

use App\Modules\Posts\Domain\Entity\Post;
use App\Modules\Posts\Domain\Entity\PostBlock;
use App\Modules\Posts\Domain\Enum\AttachmentType;
use App\Modules\Posts\Domain\Enum\PostStatus;
use App\Modules\Posts\Domain\ValueObject\BlockReason;
use App\Modules\Posts\Domain\ValueObject\BlockUnblockedAt;
use App\Modules\Posts\Domain\ValueObject\BlockUnblockedBy;
use App\Modules\Posts\Domain\ValueObject\BlockUnblockedReason;
use App\Modules\Posts\Domain\ValueObject\PostLesson;
use App\Modules\Posts\Domain\ValueObject\PostOriginal;
use App\Modules\Posts\Domain\ValueObject\PostPractice;
use App\Modules\Posts\Domain\ValueObject\PostText;
use App\Shared\Domain\ValueObject\UserId;
use Tests\Feature\Modules\Posts\PostsRepositoryTestCase;

final class PostBlockRepositoryTest extends PostsRepositoryTestCase
{
    public function testStoresActiveBlockAndFindsIt(): void
    {
        $author = $this->createUser();
        $this->persist($author);
        $blocker = $this->createUser();
        $this->persist($blocker);
        $post = $this->createPostFor($author->id);

        $block = PostBlock::create(
            postId: $post->id,
            reason: BlockReason::fromString('Нарушение правил'),
            blockedBy: $blocker->id,
        );
        $this->persist($block);
        $this->cleanOrmHeap();

        $byId = $this->postBlockRepository()->findById($block->id);
        self::assertInstanceOf(PostBlock::class, $byId);
        self::assertSame('Нарушение правил', $byId->reason->value());
        self::assertTrue($byId->isActive());

        $active = $this->postBlockRepository()->findActiveByPostId($post->id);
        self::assertInstanceOf(PostBlock::class, $active);
        self::assertTrue($block->id->equals($active->id));
    }

    public function testFindActiveReturnsNullWhenUnblocked(): void
    {
        $author = $this->createUser();
        $this->persist($author);
        $blocker = $this->createUser();
        $this->persist($blocker);
        $unblocker = $this->createUser();
        $this->persist($unblocker);
        $post = $this->createPostFor($author->id);

        $block = PostBlock::create(
            postId: $post->id,
            reason: BlockReason::fromString('Нарушение правил'),
            blockedBy: $blocker->id,
        );
        $block->markUnblocked(
            unblockedBy: BlockUnblockedBy::by($unblocker->id),
            unblockedAt: BlockUnblockedAt::at(new \DateTimeImmutable('2026-06-17 12:00:00')),
            unblockedReason: BlockUnblockedReason::of('Ошибочно'),
        );
        $this->persist($block);
        $this->cleanOrmHeap();

        self::assertNull($this->postBlockRepository()->findActiveByPostId($post->id));

        $restored = $this->postBlockRepository()->findById($block->id);
        self::assertInstanceOf(PostBlock::class, $restored);
        self::assertFalse($restored->isActive());
        self::assertSame($unblocker->id->value(), $restored->unblockedBy->value());
        self::assertSame('Ошибочно', $restored->unblockedReason->value());
    }

    public function testUnblockedByIsSetNullWhenUnblockerDeleted(): void
    {
        $author = $this->createUser();
        $this->persist($author);
        $blocker = $this->createUser();
        $this->persist($blocker);
        $unblocker = $this->createUser();
        $this->persist($unblocker);
        $post = $this->createPostFor($author->id);

        $block = PostBlock::create(
            postId: $post->id,
            reason: BlockReason::fromString('Нарушение правил'),
            blockedBy: $blocker->id,
        );
        $block->markUnblocked(
            unblockedBy: BlockUnblockedBy::by($unblocker->id),
            unblockedAt: BlockUnblockedAt::at(new \DateTimeImmutable('2026-06-17 12:00:00')),
            unblockedReason: BlockUnblockedReason::of('Ошибочно'),
        );
        $this->persist($block);

        $this->entityManager()->delete($unblocker);
        $this->entityManager()->run();
        $this->cleanOrmHeap();

        $restored = $this->postBlockRepository()->findById($block->id);
        self::assertInstanceOf(PostBlock::class, $restored);
        self::assertTrue($restored->unblockedBy->isEmpty());
        self::assertTrue($restored->unblockedAt->isUnblocked());
    }

    public function testCannotDeleteBlockerReferencedByBlock(): void
    {
        $author = $this->createUser();
        $this->persist($author);
        $blocker = $this->createUser();
        $this->persist($blocker);
        $post = $this->createPostFor($author->id);

        $this->persist(PostBlock::create(
            postId: $post->id,
            reason: BlockReason::fromString('Нарушение правил'),
            blockedBy: $blocker->id,
        ));

        $this->expectException(\Throwable::class);

        $this->entityManager()->delete($blocker);
        $this->entityManager()->run();
    }

    private function createPostFor(UserId $userId): Post
    {
        $post = Post::create(
            userId: $userId,
            text: PostText::none(),
            status: PostStatus::Blocked,
            attachmentType: AttachmentType::None,
            lesson: PostLesson::none(),
            practice: PostPractice::none(),
            original: PostOriginal::none(),
        );
        $this->persist($post);

        return $post;
    }
}
