<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Posts\Repository;

use App\Modules\Posts\Domain\Entity\Comment;
use App\Modules\Posts\Domain\Entity\CommentLike;
use App\Modules\Posts\Domain\Entity\CommentMention;
use App\Modules\Posts\Domain\Entity\Post;
use App\Modules\Posts\Domain\Enum\AttachmentType;
use App\Modules\Posts\Domain\Enum\PostStatus;
use App\Modules\Posts\Domain\ValueObject\CommentDeletedAt;
use App\Modules\Posts\Domain\ValueObject\CommentDeletedBy;
use App\Modules\Posts\Domain\ValueObject\CommentDeletionReason;
use App\Modules\Posts\Domain\ValueObject\CommentId;
use App\Modules\Posts\Domain\ValueObject\CommentParent;
use App\Modules\Posts\Domain\ValueObject\CommentText;
use App\Modules\Posts\Domain\ValueObject\PostId;
use App\Modules\Posts\Domain\ValueObject\PostLesson;
use App\Modules\Posts\Domain\ValueObject\PostOriginal;
use App\Modules\Posts\Domain\ValueObject\PostPractice;
use App\Modules\Posts\Domain\ValueObject\PostText;
use App\Shared\Domain\ValueObject\UserId;
use Tests\Feature\Modules\Posts\PostsRepositoryTestCase;

final class CommentRepositoryTest extends PostsRepositoryTestCase
{
    public function testStoresAndRestoresComment(): void
    {
        $user = $this->createUser();
        $this->persist($user);
        $post = $this->createPostFor($user->id);

        $comment = $this->newComment($post->id, $user->id);
        $this->persist($comment);
        $this->cleanOrmHeap();

        $restored = $this->commentRepository()->findById($comment->id);

        self::assertInstanceOf(Comment::class, $restored);
        self::assertTrue($comment->id->equals($restored->id));
        self::assertSame('Комментарий', $restored->text->value());
        self::assertTrue($restored->parent->isEmpty());
        self::assertSame(0, $restored->likesCount->value());
        self::assertSame(0, $restored->repliesCount->value());
        self::assertFalse($restored->isDeleted());
    }

    public function testSoftDeleteRestoresAllFields(): void
    {
        $user = $this->createUser();
        $this->persist($user);
        $post = $this->createPostFor($user->id);
        $deletedAt = new \DateTimeImmutable('2026-06-17 10:00:00');

        $comment = $this->newComment($post->id, $user->id);
        $comment->delete(
            deletedBy: CommentDeletedBy::by($user->id),
            deletedAt: CommentDeletedAt::at($deletedAt),
            deletionReason: CommentDeletionReason::of('Спам'),
        );
        $this->persist($comment);
        $this->cleanOrmHeap();

        $restored = $this->commentRepository()->findById($comment->id);

        self::assertInstanceOf(Comment::class, $restored);
        self::assertTrue($restored->isDeleted());
        self::assertSame($user->id->value(), $restored->deletedBy->value());
        self::assertEquals($deletedAt, $restored->deletedAt->value());
        self::assertSame('Спам', $restored->deletionReason->value());
        // findByPostId не прячет мягко удалённые — это решает вызывающий.
        self::assertCount(1, $this->commentRepository()->findByPostId($post->id, null, 10));
    }

    public function testReplyTreeAndRepliesCounter(): void
    {
        $user = $this->createUser();
        $this->persist($user);
        $post = $this->createPostFor($user->id);

        $parent = $this->newComment($post->id, $user->id);
        $this->persist($parent);

        $reply = Comment::create(
            postId: $post->id,
            userId: $user->id,
            text: CommentText::fromString('Ответ'),
            parent: CommentParent::pointingTo($parent->id->value()),
        );
        $this->persist($reply);
        $parent->incrementReplies();
        $this->persist($parent);
        $this->cleanOrmHeap();

        $replies = $this->commentRepository()->findReplies($parent->id, null, 10);
        self::assertCount(1, $replies);
        self::assertTrue($replies->first()?->id->equals($reply->id));

        $restoredParent = $this->commentRepository()->findById($parent->id);
        self::assertInstanceOf(Comment::class, $restoredParent);
        self::assertSame(1, $restoredParent->repliesCount->value());

        $restoredParent->decrementReplies();
        $this->persist($restoredParent);
        $this->cleanOrmHeap();

        $afterDecrement = $this->commentRepository()->findById($parent->id);
        self::assertInstanceOf(Comment::class, $afterDecrement);
        self::assertSame(0, $afterDecrement->repliesCount->value());
    }

    public function testFindByPostIdPaginatesByIdDesc(): void
    {
        $user = $this->createUser();
        $this->persist($user);
        $post = $this->createPostFor($user->id);

        $created = [];
        for ($index = 0; $index < 3; $index++) {
            $comment = $this->newComment($post->id, $user->id);
            $this->persist($comment);
            $created[] = $comment;
        }
        $this->cleanOrmHeap();

        $idsDesc = $this->idsDesc($created);

        $firstPage = $this->commentRepository()->findByPostId($post->id, null, 2);
        self::assertSame(\array_slice($idsDesc, 0, 2), $this->ids($firstPage->all()));

        $secondPage = $this->commentRepository()->findByPostId($post->id, $firstPage->last()?->id, 2);
        self::assertSame(\array_slice($idsDesc, 2), $this->ids($secondPage->all()));
    }

    public function testFindRepliesPaginatesByIdDesc(): void
    {
        $user = $this->createUser();
        $this->persist($user);
        $post = $this->createPostFor($user->id);
        $parent = $this->newComment($post->id, $user->id);
        $this->persist($parent);

        $created = [];
        for ($index = 0; $index < 3; $index++) {
            $reply = Comment::create(
                postId: $post->id,
                userId: $user->id,
                text: CommentText::fromString('Ответ'),
                parent: CommentParent::pointingTo($parent->id->value()),
            );
            $this->persist($reply);
            $created[] = $reply;
        }
        $this->cleanOrmHeap();

        $idsDesc = $this->idsDesc($created);

        $firstPage = $this->commentRepository()->findReplies($parent->id, null, 2);
        self::assertSame(\array_slice($idsDesc, 0, 2), $this->ids($firstPage->all()));

        $secondPage = $this->commentRepository()->findReplies($parent->id, $firstPage->last()?->id, 2);
        self::assertSame(\array_slice($idsDesc, 2), $this->ids($secondPage->all()));
    }

    public function testParentCommentIsSetNullWhenParentDeleted(): void
    {
        $user = $this->createUser();
        $this->persist($user);
        $post = $this->createPostFor($user->id);

        $parent = $this->newComment($post->id, $user->id);
        $this->persist($parent);
        $reply = Comment::create(
            postId: $post->id,
            userId: $user->id,
            text: CommentText::fromString('Ответ'),
            parent: CommentParent::pointingTo($parent->id->value()),
        );
        $this->persist($reply);

        $this->entityManager()->delete($parent);
        $this->entityManager()->run();
        $this->cleanOrmHeap();

        $restoredReply = $this->commentRepository()->findById($reply->id);

        self::assertInstanceOf(Comment::class, $restoredReply);
        self::assertTrue($restoredReply->parent->isEmpty());
        self::assertNull($this->commentRepository()->findById($parent->id));
    }

    public function testDeletingCommentCascadesLikesAndMentions(): void
    {
        $user = $this->createUser();
        $this->persist($user);
        $post = $this->createPostFor($user->id);
        $comment = $this->newComment($post->id, $user->id);
        $this->persist($comment);
        $this->persist(CommentLike::create(commentId: $comment->id, userId: $user->id));
        $this->persist(CommentMention::create(commentId: $comment->id, userId: $user->id));

        $this->entityManager()->delete($comment);
        $this->entityManager()->run();
        $this->cleanOrmHeap();

        self::assertFalse($this->commentLikeRepository()->existsByCommentAndUser($comment->id, $user->id));
        self::assertCount(0, $this->commentMentionRepository()->findByCommentId($comment->id));
    }

    public function testCannotDeleteUserReferencedByComment(): void
    {
        $author = $this->createUser();
        $this->persist($author);
        $post = $this->createPostFor($author->id);

        $commenter = $this->createUser();
        $this->persist($commenter);
        $this->persist($this->newComment($post->id, $commenter->id));

        $this->expectException(\Throwable::class);

        $this->entityManager()->delete($commenter);
        $this->entityManager()->run();
    }

    private function createPostFor(UserId $userId): Post
    {
        $post = Post::create(
            userId: $userId,
            text: PostText::none(),
            status: PostStatus::Published,
            attachmentType: AttachmentType::None,
            lesson: PostLesson::none(),
            practice: PostPractice::none(),
            original: PostOriginal::none(),
        );
        $this->persist($post);

        return $post;
    }

    private function newComment(PostId $postId, UserId $userId): Comment
    {
        return Comment::create(
            postId: $postId,
            userId: $userId,
            text: CommentText::fromString('Комментарий'),
            parent: CommentParent::none(),
        );
    }

    /**
     * @param list<Comment> $comments
     *
     * @return list<string>
     */
    private function idsDesc(array $comments): array
    {
        $ids = $this->ids($comments);
        \rsort($ids);

        return $ids;
    }

    /**
     * @param list<Comment> $comments
     *
     * @return list<string>
     */
    private function ids(array $comments): array
    {
        return \array_map(static fn(Comment $comment): string => $comment->id->value(), $comments);
    }
}
