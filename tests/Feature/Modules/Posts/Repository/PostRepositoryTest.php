<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Posts\Repository;

use App\Modules\Posts\Domain\Entity\Comment;
use App\Modules\Posts\Domain\Entity\Post;
use App\Modules\Posts\Domain\Entity\PostBlock;
use App\Modules\Posts\Domain\Entity\PostLike;
use App\Modules\Posts\Domain\Entity\PostMedia;
use App\Modules\Posts\Domain\Entity\PostMention;
use App\Modules\Posts\Domain\Entity\PostTag;
use App\Modules\Posts\Domain\Enum\AttachmentType;
use App\Modules\Posts\Domain\Enum\PostStatus;
use App\Modules\Posts\Domain\ValueObject\BlockReason;
use App\Modules\Posts\Domain\ValueObject\CommentParent;
use App\Modules\Posts\Domain\ValueObject\CommentText;
use App\Modules\Posts\Domain\ValueObject\MediaPosition;
use App\Modules\Posts\Domain\ValueObject\PostId;
use App\Modules\Posts\Domain\ValueObject\PostLesson;
use App\Modules\Posts\Domain\ValueObject\PostMediaReference;
use App\Modules\Posts\Domain\ValueObject\PostOriginal;
use App\Modules\Posts\Domain\ValueObject\PostPractice;
use App\Modules\Posts\Domain\ValueObject\PostText;
use App\Modules\Tags\Domain\Entity\Tag;
use App\Modules\Tags\Domain\ValueObject\TagText;
use App\Shared\Domain\Exception\InvalidDomainValueException;
use App\Shared\Domain\ValueObject\UserId;
use Tests\Feature\Modules\Posts\PostsRepositoryTestCase;

final class PostRepositoryTest extends PostsRepositoryTestCase
{
    public function testStoresAndRestoresPostWithValueObjects(): void
    {
        $user = $this->createUser();
        $this->persist($user);

        $lessonId = (string) PostId::generate();
        $post = $this->newPost($user->id, PostStatus::Published, PostText::fromString('Текст записи'));
        $post->setLesson(PostLesson::pointingTo($lessonId));
        $post->incrementLikes();
        $post->incrementReposts();
        $post->incrementComments();
        $this->persist($post);
        $this->cleanOrmHeap();

        $restored = $this->postRepository()->findById($post->id);

        self::assertInstanceOf(Post::class, $restored);
        self::assertTrue($post->id->equals($restored->id));
        self::assertTrue($user->id->equals($restored->userId));
        self::assertSame('Текст записи', $restored->text->value());
        self::assertSame(PostStatus::Published, $restored->status);
        self::assertSame(AttachmentType::Lesson, $restored->attachmentType);
        self::assertSame($lessonId, $restored->lesson->value());
        self::assertTrue($restored->practice->isEmpty());
        self::assertSame(1, $restored->likesCount->value());
        self::assertSame(1, $restored->repostsCount->value());
        self::assertSame(1, $restored->commentsCount->value());
        self::assertFalse($restored->deletion->isDeleted());
    }

    public function testStoresAndRestoresPostWithEmptyNullObjects(): void
    {
        $user = $this->createUser();
        $this->persist($user);

        $post = $this->newPost($user->id, PostStatus::Draft, PostText::none());
        $this->persist($post);
        $this->cleanOrmHeap();

        $restored = $this->postRepository()->findById($post->id);

        self::assertInstanceOf(Post::class, $restored);
        self::assertTrue($restored->text->isEmpty());
        self::assertTrue($restored->lesson->isEmpty());
        self::assertTrue($restored->practice->isEmpty());
        self::assertTrue($restored->original->isEmpty());
        self::assertFalse($restored->deletion->isDeleted());
        self::assertSame(PostStatus::Draft, $restored->status);
        self::assertSame(AttachmentType::None, $restored->attachmentType);
    }

    public function testCounterRoundTripWithNonZeroValue(): void
    {
        $user = $this->createUser();
        $this->persist($user);

        $post = $this->newPost($user->id);
        $post->incrementLikes();
        $post->incrementLikes();
        $post->incrementLikes();
        $this->persist($post);
        $this->cleanOrmHeap();

        $restored = $this->postRepository()->findById($post->id);

        self::assertInstanceOf(Post::class, $restored);
        self::assertSame(3, $restored->likesCount->value());
    }

    public function testDecrementLikesBelowZeroThrows(): void
    {
        $post = $this->newPost(UserId::generate());

        $this->expectException(InvalidDomainValueException::class);

        $post->decrementLikes();
    }

    public function testSoftDeleteRoundTrip(): void
    {
        $user = $this->createUser();
        $this->persist($user);

        $post = $this->newPost($user->id);
        $post->softDelete(new \DateTimeImmutable('2026-06-17 10:00:00'));
        $this->persist($post);
        $this->cleanOrmHeap();

        $restored = $this->postRepository()->findById($post->id);

        self::assertInstanceOf(Post::class, $restored);
        self::assertTrue($restored->deletion->isDeleted());
        self::assertEquals(new \DateTimeImmutable('2026-06-17 10:00:00'), $restored->deletion->value());
    }

    public function testFindByUserIdFiltersStatusAndPaginates(): void
    {
        $user = $this->createUser();
        $this->persist($user);
        $other = $this->createUser();
        $this->persist($other);

        $created = [];
        for ($index = 0; $index < 3; $index++) {
            $post = $this->newPost($user->id, PostStatus::Published);
            $this->persist($post);
            $created[] = $post;
        }

        $blocked = $this->newPost($user->id, PostStatus::Blocked);
        $this->persist($blocked);

        // Чужая запись не попадает в выборку получателя.
        $this->persist($this->newPost($other->id, PostStatus::Published));
        $this->cleanOrmHeap();

        $publishedIdsDesc = $this->idsDesc($created);

        $firstPage = $this->postRepository()->findByUserId($user->id, PostStatus::Published, null, 2);
        self::assertSame(\array_slice($publishedIdsDesc, 0, 2), $this->ids($firstPage->all()));

        $lastOnFirstPage = $firstPage->last();
        self::assertInstanceOf(Post::class, $lastOnFirstPage);

        $secondPage = $this->postRepository()->findByUserId($user->id, PostStatus::Published, $lastOnFirstPage->id, 2);
        self::assertSame(\array_slice($publishedIdsDesc, 2), $this->ids($secondPage->all()));

        $emptyPage = $this->postRepository()->findByUserId($user->id, PostStatus::Published, $secondPage->last()?->id, 2);
        self::assertCount(0, $emptyPage);

        // Без фильтра статуса возвращаются и Published, и Blocked.
        self::assertCount(4, $this->postRepository()->findByUserId($user->id, null, null, 10));
        self::assertCount(1, $this->postRepository()->findByUserId($user->id, PostStatus::Blocked, null, 10));
    }

    public function testFindVisibleByUserIdExcludesBlockedAndSoftDeleted(): void
    {
        $user = $this->createUser();
        $this->persist($user);

        $published = $this->newPost($user->id, PostStatus::Published);
        $this->persist($published);
        $draft = $this->newPost($user->id, PostStatus::Draft);
        $this->persist($draft);
        $this->persist($this->newPost($user->id, PostStatus::Blocked));
        $softDeleted = $this->newPost($user->id, PostStatus::Published);
        $softDeleted->softDelete(new \DateTimeImmutable());
        $this->persist($softDeleted);
        $this->cleanOrmHeap();

        // Лента владельца: все статусы, кроме Blocked, и без мягко удалённых.
        $ownerFeed = $this->postRepository()->findVisibleByUserId(
            userId: $user->id,
            status: null,
            excludeStatus: PostStatus::Blocked,
            cursor: null,
            limit: 10,
        );
        self::assertSame(
            $this->idsDesc([$published, $draft]),
            $this->ids($ownerFeed->all()),
        );

        // Чужая лента: только Published.
        $strangerFeed = $this->postRepository()->findVisibleByUserId(
            userId: $user->id,
            status: PostStatus::Published,
            excludeStatus: null,
            cursor: null,
            limit: 10,
        );
        self::assertSame([$published->id->value()], $this->ids($strangerFeed->all()));
    }

    public function testFindByIdsReturnsRequestedPosts(): void
    {
        $user = $this->createUser();
        $this->persist($user);

        $first = $this->newPost($user->id, PostStatus::Published);
        $this->persist($first);
        $second = $this->newPost($user->id, PostStatus::Published);
        $this->persist($second);
        $this->persist($this->newPost($user->id, PostStatus::Published));
        $this->cleanOrmHeap();

        $found = $this->postRepository()->findByIds($first->id, $second->id);

        self::assertCount(2, $found);
        self::assertSame(
            $this->idsDesc([$first, $second]),
            $this->idsDesc($found->all()),
        );
        self::assertCount(0, $this->postRepository()->findByIds());
    }

    public function testFindRepostsOf(): void
    {
        $user = $this->createUser();
        $this->persist($user);

        $original = $this->newPost($user->id, PostStatus::Published);
        $this->persist($original);

        $repost = Post::create(
            userId: $user->id,
            text: PostText::none(),
            status: PostStatus::Published,
            attachmentType: AttachmentType::None,
            lesson: PostLesson::none(),
            practice: PostPractice::none(),
            original: PostOriginal::pointingTo($original->id->value()),
        );
        $this->persist($repost);
        $this->cleanOrmHeap();

        $reposts = $this->postRepository()->findRepostsOf($original->id);

        self::assertCount(1, $reposts);
        self::assertTrue($reposts->contains(static fn(Post $post): bool => $post->id->equals($repost->id)));
    }

    public function testParentPostIsSetNullWhenOriginalDeleted(): void
    {
        $user = $this->createUser();
        $this->persist($user);

        $original = $this->newPost($user->id, PostStatus::Published);
        $this->persist($original);

        $repost = Post::create(
            userId: $user->id,
            text: PostText::none(),
            status: PostStatus::Published,
            attachmentType: AttachmentType::None,
            lesson: PostLesson::none(),
            practice: PostPractice::none(),
            original: PostOriginal::pointingTo($original->id->value()),
        );
        $this->persist($repost);

        $this->entityManager()->delete($original);
        $this->entityManager()->run();
        $this->cleanOrmHeap();

        $restoredRepost = $this->postRepository()->findById($repost->id);

        self::assertInstanceOf(Post::class, $restoredRepost);
        self::assertTrue($restoredRepost->original->isEmpty());
        self::assertNull($this->postRepository()->findById($original->id));
    }

    public function testCannotDeleteUserReferencedByPost(): void
    {
        $user = $this->createUser();
        $this->persist($user);
        $this->persist($this->newPost($user->id));

        $this->expectException(\Throwable::class);

        $this->entityManager()->delete($user);
        $this->entityManager()->run();
    }

    public function testDeletingPostCascadesAllChildren(): void
    {
        $user = $this->createUser();
        $this->persist($user);
        $media = $this->createMedia($user->id);
        $this->persist($media);

        $post = $this->newPost($user->id, PostStatus::Published);
        $this->persist($post);

        $tag = Tag::create(text: TagText::fromString('йога'), createdBy: $user->id);
        $this->persist($tag);

        $this->persist(PostMedia::create(
            post: $post,
            mediaId: PostMediaReference::fromString($media->id->value()),
            position: MediaPosition::fromInt(0),
        ));
        $this->persist(PostLike::create(postId: $post->id, userId: $user->id));
        $this->persist(PostMention::create(postId: $post->id, userId: $user->id));
        $this->persist(PostTag::create(postId: $post->id, tagId: $tag->id));
        $block = PostBlock::create(postId: $post->id, reason: BlockReason::fromString('Нарушение'), blockedBy: $user->id);
        $this->persist($block);
        $this->persist(Comment::create(
            postId: $post->id,
            userId: $user->id,
            text: CommentText::fromString('Комментарий'),
            parent: CommentParent::none(),
        ));

        $this->entityManager()->delete($post);
        $this->entityManager()->run();
        $this->cleanOrmHeap();

        self::assertCount(0, $this->postMediaRepository()->findByPostId($post->id));
        self::assertFalse($this->postLikeRepository()->existsByPostAndUser($post->id, $user->id));
        self::assertCount(0, $this->postMentionRepository()->findByPostId($post->id));
        self::assertCount(0, $this->postTagRepository()->findByPostId($post->id));
        self::assertNull($this->postBlockRepository()->findById($block->id));
        self::assertCount(0, $this->commentRepository()->findByPostId($post->id, null, 10));
    }

    private function newPost(
        UserId $userId,
        PostStatus $status = PostStatus::Draft,
        PostText|null $text = null,
    ): Post {
        return Post::create(
            userId: $userId,
            text: $text ?? PostText::none(),
            status: $status,
            attachmentType: AttachmentType::None,
            lesson: PostLesson::none(),
            practice: PostPractice::none(),
            original: PostOriginal::none(),
        );
    }

    /**
     * @param list<Post> $posts
     *
     * @return list<string>
     */
    private function idsDesc(array $posts): array
    {
        $ids = $this->ids($posts);
        \rsort($ids);

        return $ids;
    }

    /**
     * @param list<Post> $posts
     *
     * @return list<string>
     */
    private function ids(array $posts): array
    {
        return \array_map(static fn(Post $post): string => $post->id->value(), $posts);
    }
}
