<?php

declare(strict_types=1);

namespace App\Modules\Posts\Tests\Unit\Domain\Collection;

use App\Modules\Posts\Domain\Collection\CommentCollection;
use App\Modules\Posts\Domain\Collection\CommentLikeCollection;
use App\Modules\Posts\Domain\Collection\CommentMentionCollection;
use App\Modules\Posts\Domain\Collection\PostBlockCollection;
use App\Modules\Posts\Domain\Collection\PostCollection;
use App\Modules\Posts\Domain\Collection\PostLikeCollection;
use App\Modules\Posts\Domain\Collection\PostMediaCollection;
use App\Modules\Posts\Domain\Collection\PostMentionCollection;
use App\Modules\Posts\Domain\Collection\PostTagCollection;
use App\Modules\Posts\Domain\Entity\Comment;
use App\Modules\Posts\Domain\Entity\Post;
use App\Modules\Posts\Domain\Enum\AttachmentType;
use App\Modules\Posts\Domain\Enum\PostStatus;
use App\Modules\Posts\Domain\ValueObject\CommentParent;
use App\Modules\Posts\Domain\ValueObject\CommentText;
use App\Modules\Posts\Domain\ValueObject\PostId;
use App\Modules\Posts\Domain\ValueObject\PostLesson;
use App\Modules\Posts\Domain\ValueObject\PostOriginal;
use App\Modules\Posts\Domain\ValueObject\PostPractice;
use App\Modules\Posts\Domain\ValueObject\PostText;
use App\Shared\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;

final class PostsCollectionTest extends TestCase
{
    public function testCollectionsAreEmptyOfTheirType(): void
    {
        self::assertCount(0, new PostCollection());
        self::assertCount(0, new CommentCollection());
        self::assertCount(0, new PostMediaCollection());
        self::assertCount(0, new PostTagCollection());
        self::assertCount(0, new PostLikeCollection());
        self::assertCount(0, new PostMentionCollection());
        self::assertCount(0, new PostBlockCollection());
        self::assertCount(0, new CommentLikeCollection());
        self::assertCount(0, new CommentMentionCollection());
    }

    public function testPostCollectionPreservesTypeOnMapAndFilter(): void
    {
        $posts = new PostCollection([$this->createPost()]);

        self::assertInstanceOf(PostCollection::class, $posts->map(static fn(Post $post): Post => $post));
        self::assertInstanceOf(
            PostCollection::class,
            $posts->filter(static fn(Post $post): bool => !$post->deletion->isDeleted()),
        );
    }

    public function testCommentCollectionPreservesTypeOnMapAndFilter(): void
    {
        $comments = new CommentCollection([$this->createComment()]);

        self::assertInstanceOf(CommentCollection::class, $comments->map(static fn(Comment $comment): Comment => $comment));
        self::assertInstanceOf(
            CommentCollection::class,
            $comments->filter(static fn(Comment $comment): bool => !$comment->isDeleted()),
        );
    }

    private function createPost(): Post
    {
        return Post::create(
            userId: UserId::generate(),
            text: PostText::none(),
            status: PostStatus::Draft,
            attachmentType: AttachmentType::None,
            lesson: PostLesson::none(),
            practice: PostPractice::none(),
            original: PostOriginal::none(),
        );
    }

    private function createComment(): Comment
    {
        return Comment::create(
            postId: PostId::generate(),
            userId: UserId::generate(),
            text: CommentText::fromString('Текст'),
            parent: CommentParent::none(),
        );
    }
}
