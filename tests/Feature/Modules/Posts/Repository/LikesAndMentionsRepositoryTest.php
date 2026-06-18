<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Posts\Repository;

use App\Modules\Posts\Domain\Entity\Comment;
use App\Modules\Posts\Domain\Entity\CommentLike;
use App\Modules\Posts\Domain\Entity\CommentMention;
use App\Modules\Posts\Domain\Entity\Post;
use App\Modules\Posts\Domain\Entity\PostLike;
use App\Modules\Posts\Domain\Entity\PostMention;
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
use Tests\Feature\Modules\Posts\PostsRepositoryTestCase;

final class LikesAndMentionsRepositoryTest extends PostsRepositoryTestCase
{
    public function testPostLikeLookupsAndExistence(): void
    {
        $author = $this->createUser();
        $this->persist($author);
        $liker = $this->createUser();
        $this->persist($liker);
        $post = $this->createPostFor($author->id);

        $this->persist(PostLike::create(postId: $post->id, userId: $liker->id));
        $this->cleanOrmHeap();

        self::assertInstanceOf(PostLike::class, $this->postLikeRepository()->findByPostAndUser($post->id, $liker->id));
        self::assertTrue($this->postLikeRepository()->existsByPostAndUser($post->id, $liker->id));
        self::assertFalse($this->postLikeRepository()->existsByPostAndUser($post->id, $author->id));
        self::assertCount(1, $this->postLikeRepository()->findByUserId($liker->id));
        self::assertCount(0, $this->postLikeRepository()->findByUserId($author->id));
    }

    public function testPostLikeIsUniquePerPostAndUser(): void
    {
        $user = $this->createUser();
        $this->persist($user);
        $post = $this->createPostFor($user->id);

        $this->entityManager()->persist(PostLike::create(postId: $post->id, userId: $user->id));
        $this->entityManager()->persist(PostLike::create(postId: $post->id, userId: $user->id));

        $this->expectException(\Throwable::class);

        $this->entityManager()->run();
    }

    public function testPostMentionLookups(): void
    {
        $author = $this->createUser();
        $this->persist($author);
        $mentioned = $this->createUser();
        $this->persist($mentioned);
        $post = $this->createPostFor($author->id);

        $this->persist(PostMention::create(postId: $post->id, userId: $mentioned->id));
        $this->cleanOrmHeap();

        self::assertCount(1, $this->postMentionRepository()->findByPostId($post->id));
        self::assertCount(1, $this->postMentionRepository()->findByUserId($mentioned->id));
        self::assertCount(0, $this->postMentionRepository()->findByUserId($author->id));
    }

    public function testPostMentionIsUniquePerPostAndUser(): void
    {
        $user = $this->createUser();
        $this->persist($user);
        $post = $this->createPostFor($user->id);

        $this->entityManager()->persist(PostMention::create(postId: $post->id, userId: $user->id));
        $this->entityManager()->persist(PostMention::create(postId: $post->id, userId: $user->id));

        $this->expectException(\Throwable::class);

        $this->entityManager()->run();
    }

    public function testCommentLikeLookupsAndExistence(): void
    {
        $author = $this->createUser();
        $this->persist($author);
        $liker = $this->createUser();
        $this->persist($liker);
        $comment = $this->createCommentFor($author->id);

        $this->persist(CommentLike::create(commentId: $comment->id, userId: $liker->id));
        $this->cleanOrmHeap();

        self::assertInstanceOf(
            CommentLike::class,
            $this->commentLikeRepository()->findByCommentAndUser($comment->id, $liker->id),
        );
        self::assertTrue($this->commentLikeRepository()->existsByCommentAndUser($comment->id, $liker->id));
        self::assertFalse($this->commentLikeRepository()->existsByCommentAndUser($comment->id, $author->id));
    }

    public function testCommentLikeIsUniquePerCommentAndUser(): void
    {
        $user = $this->createUser();
        $this->persist($user);
        $comment = $this->createCommentFor($user->id);

        $this->entityManager()->persist(CommentLike::create(commentId: $comment->id, userId: $user->id));
        $this->entityManager()->persist(CommentLike::create(commentId: $comment->id, userId: $user->id));

        $this->expectException(\Throwable::class);

        $this->entityManager()->run();
    }

    public function testCommentMentionLookups(): void
    {
        $author = $this->createUser();
        $this->persist($author);
        $mentioned = $this->createUser();
        $this->persist($mentioned);
        $comment = $this->createCommentFor($author->id);

        $this->persist(CommentMention::create(commentId: $comment->id, userId: $mentioned->id));
        $this->cleanOrmHeap();

        self::assertCount(1, $this->commentMentionRepository()->findByCommentId($comment->id));
    }

    public function testCommentMentionIsUniquePerCommentAndUser(): void
    {
        $user = $this->createUser();
        $this->persist($user);
        $comment = $this->createCommentFor($user->id);

        $this->entityManager()->persist(CommentMention::create(commentId: $comment->id, userId: $user->id));
        $this->entityManager()->persist(CommentMention::create(commentId: $comment->id, userId: $user->id));

        $this->expectException(\Throwable::class);

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

    private function createCommentFor(UserId $userId): Comment
    {
        $post = $this->createPostFor($userId);
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
