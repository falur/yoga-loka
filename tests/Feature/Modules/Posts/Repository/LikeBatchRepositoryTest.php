<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Posts\Repository;

use App\Modules\Posts\Domain\Entity\Comment;
use App\Modules\Posts\Domain\Entity\CommentLike;
use App\Modules\Posts\Domain\Entity\Post;
use App\Modules\Posts\Domain\Entity\PostLike;
use App\Modules\Posts\Domain\Enum\AttachmentType;
use App\Modules\Posts\Domain\Enum\PostStatus;
use App\Modules\Posts\Domain\ValueObject\CommentParent;
use App\Modules\Posts\Domain\ValueObject\CommentText;
use App\Modules\Posts\Domain\ValueObject\PostLesson;
use App\Modules\Posts\Domain\ValueObject\PostOriginal;
use App\Modules\Posts\Domain\ValueObject\PostPractice;
use App\Modules\Posts\Domain\ValueObject\PostText;
use App\Shared\Domain\ValueObject\UserId;
use Tests\Feature\Modules\Posts\PostsRepositoryTestCase;

final class LikeBatchRepositoryTest extends PostsRepositoryTestCase
{
    public function testFindByUserAndPostIdsReturnsOnlyLikedPosts(): void
    {
        $user = $this->createUser();
        $this->persist($user);
        $likedPost = $this->createPostFor($user->id);
        $otherPost = $this->createPostFor($user->id);
        $this->persist(PostLike::create(postId: $likedPost->id, userId: $user->id));
        $this->cleanOrmHeap();

        $likes = $this->postLikeRepository()->findByUserAndPostIds($user->id, $likedPost->id, $otherPost->id);

        self::assertCount(1, $likes);
        self::assertTrue($likes->first()?->postId->equals($likedPost->id));
    }

    public function testFindByUserAndPostIdsReturnsEmptyForEmptyInput(): void
    {
        $user = $this->createUser();
        $this->persist($user);

        self::assertCount(0, $this->postLikeRepository()->findByUserAndPostIds($user->id));
    }

    public function testFindByUserAndCommentIdsReturnsOnlyLikedComments(): void
    {
        $user = $this->createUser();
        $this->persist($user);
        $post = $this->createPostFor($user->id);
        $likedComment = $this->createCommentFor($post, $user->id);
        $otherComment = $this->createCommentFor($post, $user->id);
        $this->persist(CommentLike::create(commentId: $likedComment->id, userId: $user->id));
        $this->cleanOrmHeap();

        $likes = $this->commentLikeRepository()->findByUserAndCommentIds($user->id, $likedComment->id, $otherComment->id);

        self::assertCount(1, $likes);
        self::assertTrue($likes->first()?->commentId->equals($likedComment->id));
    }

    public function testFindByUserAndCommentIdsReturnsEmptyForEmptyInput(): void
    {
        $user = $this->createUser();
        $this->persist($user);

        self::assertCount(0, $this->commentLikeRepository()->findByUserAndCommentIds($user->id));
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

    private function createCommentFor(Post $post, UserId $userId): Comment
    {
        $comment = Comment::create(
            postId: $post->id,
            userId: $userId,
            text: CommentText::fromString('Комментарий'),
            parent: CommentParent::none(),
        );
        $this->persist($comment);

        return $comment;
    }
}
