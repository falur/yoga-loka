<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Posts\Repository;

use App\Modules\Posts\Domain\Collection\CommentLikeCollection;
use App\Modules\Posts\Domain\Collection\PostMediaCollection;
use App\Modules\Posts\Domain\Collection\PostTagCollection;
use App\Modules\Posts\Domain\Entity\Comment;
use App\Modules\Posts\Domain\Entity\CommentLike;
use App\Modules\Posts\Domain\Entity\CommentMention;
use App\Modules\Posts\Domain\Entity\Post;
use App\Modules\Posts\Domain\Entity\PostBlock;
use App\Modules\Posts\Domain\Entity\PostLike;
use App\Modules\Posts\Domain\Entity\PostMedia;
use App\Modules\Posts\Domain\Entity\PostMention;
use App\Modules\Posts\Domain\Entity\PostTag;
use App\Modules\Posts\Domain\Enum\AttachmentType;
use App\Modules\Posts\Domain\Enum\PostStatus;
use App\Modules\Posts\Domain\ValueObject\BlockReason;
use App\Modules\Posts\Domain\ValueObject\BlockUnblockedAt;
use App\Modules\Posts\Domain\ValueObject\BlockUnblockedBy;
use App\Modules\Posts\Domain\ValueObject\BlockUnblockedReason;
use App\Modules\Posts\Domain\ValueObject\CommentDeletedAt;
use App\Modules\Posts\Domain\ValueObject\CommentDeletedBy;
use App\Modules\Posts\Domain\ValueObject\CommentDeletionReason;
use App\Modules\Posts\Domain\ValueObject\CommentParent;
use App\Modules\Posts\Domain\ValueObject\CommentText;
use App\Modules\Posts\Domain\ValueObject\MediaPosition;
use App\Modules\Posts\Domain\ValueObject\PostId;
use App\Modules\Posts\Domain\ValueObject\PostLesson;
use App\Modules\Posts\Domain\ValueObject\PostMediaReference;
use App\Modules\Posts\Domain\ValueObject\PostOriginal;
use App\Modules\Posts\Domain\ValueObject\PostPractice;
use App\Modules\Posts\Domain\ValueObject\PostTagReference;
use App\Modules\Posts\Domain\ValueObject\PostText;
use App\Modules\Tags\Domain\Entity\Tag;
use App\Modules\Tags\Domain\ValueObject\TagText;
use App\Shared\Domain\Exception\InvalidDomainValueException;
use App\Shared\Domain\ValueObject\UserId;
use Tests\Feature\Modules\Posts\PostsRepositoryTestCase;

/**
 * Репозитории трёх агрегатов Posts: PostRepository (запись вместе с вложением, меткой, упоминанием
 * и лайком), CommentRepository (комментарий вместе с лайком и упоминанием) и PostBlockRepository
 * (независимая блокировка модерации). Проверяет каждый метод чтения нового интерфейса и каскадное
 * удаление внутренних сущностей вместе с корнем.
 */
final class PostsRepositoryTest extends PostsRepositoryTestCase
{
    // --- Post: корень ---

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

    public function testDeletingPostCascadesAllInnerEntities(): void
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
        $this->persist(PostTag::create(postId: $post->id, tagId: PostTagReference::fromString($tag->id->value())));
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

        self::assertCount(0, $this->postRepository()->findMediaByPostId($post->id));
        self::assertFalse($this->postRepository()->existsLikeByPostAndUser($post->id, $user->id));
        self::assertCount(0, $this->postRepository()->findMentionsByPostId($post->id));
        self::assertCount(0, $this->postRepository()->findTagsByPostId($post->id));
        self::assertNull($this->postBlockRepository()->findById($block->id));
        self::assertCount(0, $this->commentRepository()->findByPostId($post->id, null, 10));
    }

    // --- Post: вложения (внутренняя сущность) ---

    public function testStoresAndRestoresMediaOrderedByPosition(): void
    {
        $user = $this->createUser();
        $this->persist($user);
        $firstMedia = $this->createMedia($user->id);
        $this->persist($firstMedia);
        $secondMedia = $this->createMedia($user->id);
        $this->persist($secondMedia);

        $post = $this->newPost($user->id, PostStatus::Published, attachmentType: AttachmentType::Media);
        $this->persist($post);

        // Намеренно вставляем во втором/первом порядке, чтобы проверить orderBy position ASC.
        $this->persist(PostMedia::create(
            post: $post,
            mediaId: PostMediaReference::fromString($secondMedia->id->value()),
            position: MediaPosition::fromInt(1),
        ));
        $this->persist(PostMedia::create(
            post: $post,
            mediaId: PostMediaReference::fromString($firstMedia->id->value()),
            position: MediaPosition::fromInt(0),
        ));
        $this->cleanOrmHeap();

        $attachments = $this->postRepository()->findMediaByPostId($post->id);

        self::assertInstanceOf(PostMediaCollection::class, $attachments);
        self::assertCount(2, $attachments);
        self::assertSame(0, $attachments->first()?->position->value());
        self::assertSame($firstMedia->id->value(), $attachments->first()?->mediaId->value());
        self::assertSame(
            [$firstMedia->id->value(), $secondMedia->id->value()],
            $attachments->map(static fn(PostMedia $item): string => $item->mediaId->value())->all(),
        );

        $restoredPost = $this->postRepository()->findById($post->id);
        self::assertInstanceOf(Post::class, $restoredPost);
        self::assertCount(2, $restoredPost->media);
        self::assertSame(0, $restoredPost->media->first()?->position->value());
    }

    public function testFindMediaByPostIdsBatchesAcrossPostsOrderedByPostAndPosition(): void
    {
        $user = $this->createUser();
        $this->persist($user);
        $firstMedia = $this->createMedia($user->id);
        $this->persist($firstMedia);
        $secondMedia = $this->createMedia($user->id);
        $this->persist($secondMedia);

        $firstPost = $this->newPost($user->id, attachmentType: AttachmentType::Media);
        $this->persist($firstPost);
        $secondPost = $this->newPost($user->id, attachmentType: AttachmentType::Media);
        $this->persist($secondPost);

        $this->persist(PostMedia::create(
            post: $firstPost,
            mediaId: PostMediaReference::fromString($firstMedia->id->value()),
            position: MediaPosition::fromInt(0),
        ));
        $this->persist(PostMedia::create(
            post: $secondPost,
            mediaId: PostMediaReference::fromString($secondMedia->id->value()),
            position: MediaPosition::fromInt(0),
        ));
        $this->cleanOrmHeap();

        $attachments = $this->postRepository()->findMediaByPostIds($firstPost->id, $secondPost->id);

        self::assertInstanceOf(PostMediaCollection::class, $attachments);
        self::assertCount(2, $attachments);
        $postIds = $attachments->map(static fn(PostMedia $postMedia): string => $postMedia->postId->value())->all();
        self::assertContains($firstPost->id->value(), $postIds);
        self::assertContains($secondPost->id->value(), $postIds);
        self::assertCount(0, $this->postRepository()->findMediaByPostIds());
    }

    public function testPostMediaBelongsToLazyLoadsPost(): void
    {
        $user = $this->createUser();
        $this->persist($user);
        $media = $this->createMedia($user->id);
        $this->persist($media);
        $post = $this->newPost($user->id, attachmentType: AttachmentType::Media);
        $this->persist($post);
        $this->persist(PostMedia::create(
            post: $post,
            mediaId: PostMediaReference::fromString($media->id->value()),
            position: MediaPosition::fromInt(0),
        ));
        $this->cleanOrmHeap();

        $restored = $this->postRepository()->findMediaByPostId($post->id)->first();

        self::assertInstanceOf(PostMedia::class, $restored);
        self::assertInstanceOf(Post::class, $restored->post);
        self::assertTrue($post->id->equals($restored->post->id));
    }

    public function testPostMediaIsUniquePerPostAndMedia(): void
    {
        $user = $this->createUser();
        $this->persist($user);
        $media = $this->createMedia($user->id);
        $this->persist($media);
        $post = $this->newPost($user->id, attachmentType: AttachmentType::Media);
        $this->persist($post);

        $this->entityManager()->persist(PostMedia::create(
            post: $post,
            mediaId: PostMediaReference::fromString($media->id->value()),
            position: MediaPosition::fromInt(0),
        ));
        $this->entityManager()->persist(PostMedia::create(
            post: $post,
            mediaId: PostMediaReference::fromString($media->id->value()),
            position: MediaPosition::fromInt(1),
        ));

        $this->expectException(\Throwable::class);

        $this->entityManager()->run();
    }

    /**
     * Межмодульного внешнего ключа post_media.media_id -> media.id больше нет: удаление медиа
     * соседним модулем не блокируется вложением и не удаляет его строку. Недоступное медиа мягко
     * исключается из ответа сборкой ответа, а не ограничением базы.
     */
    public function testDeletingReferencedMediaKeepsAttachmentRow(): void
    {
        $user = $this->createUser();
        $this->persist($user);
        $media = $this->createMedia($user->id);
        $this->persist($media);
        $post = $this->newPost($user->id, attachmentType: AttachmentType::Media);
        $this->persist($post);
        $this->persist(PostMedia::create(
            post: $post,
            mediaId: PostMediaReference::fromString($media->id->value()),
            position: MediaPosition::fromInt(0),
        ));

        $this->entityManager()->delete($media);
        $this->entityManager()->run();
        $this->cleanOrmHeap();

        $attachments = $this->postRepository()->findMediaByPostId($post->id);

        self::assertCount(1, $attachments);
        self::assertSame($media->id->value(), $attachments->first()?->mediaId->value());
    }

    // --- Post: метки (внутренняя сущность) ---

    public function testPostTagLookups(): void
    {
        $user = $this->createUser();
        $this->persist($user);
        $post = $this->createPostFor($user->id);
        $tag = Tag::create(text: TagText::fromString('йога'), createdBy: $user->id);
        $this->persist($tag);
        $this->persist(PostTag::create(postId: $post->id, tagId: PostTagReference::fromString($tag->id->value())));
        $this->cleanOrmHeap();

        self::assertCount(1, $this->postRepository()->findTagsByPostId($post->id));
        self::assertCount(1, $this->postRepository()->findTagsByTagId(PostTagReference::fromString($tag->id->value())));
        self::assertCount(1, $this->postRepository()->findTagsByPostIds($post->id));
        self::assertCount(0, $this->postRepository()->findTagsByPostIds());
    }

    public function testFindTagsByPostIdsBatchesAcrossPosts(): void
    {
        $user = $this->createUser();
        $this->persist($user);
        $firstPost = $this->createPostFor($user->id);
        $secondPost = $this->createPostFor($user->id);
        $tag = Tag::create(text: TagText::fromString('йога'), createdBy: $user->id);
        $this->persist($tag);
        $this->persist(PostTag::create(postId: $firstPost->id, tagId: PostTagReference::fromString($tag->id->value())));
        $this->persist(PostTag::create(postId: $secondPost->id, tagId: PostTagReference::fromString($tag->id->value())));
        $this->cleanOrmHeap();

        $links = $this->postRepository()->findTagsByPostIds($firstPost->id, $secondPost->id);

        self::assertInstanceOf(PostTagCollection::class, $links);
        self::assertCount(2, $links);
    }

    public function testPostHasManyTagsHydratesFromDatabase(): void
    {
        $user = $this->createUser();
        $this->persist($user);
        $post = $this->createPostFor($user->id);

        $firstTag = Tag::create(text: TagText::fromString('йога'), createdBy: $user->id);
        $this->persist($firstTag);
        $secondTag = Tag::create(text: TagText::fromString('медитация'), createdBy: $user->id);
        $this->persist($secondTag);
        $this->persist(PostTag::create(postId: $post->id, tagId: PostTagReference::fromString($firstTag->id->value())));
        $this->persist(PostTag::create(postId: $post->id, tagId: PostTagReference::fromString($secondTag->id->value())));
        $this->cleanOrmHeap();

        $restoredPost = $this->postRepository()->findById($post->id);

        self::assertInstanceOf(Post::class, $restoredPost);
        self::assertInstanceOf(PostTagCollection::class, $restoredPost->tags);
        self::assertCount(2, $restoredPost->tags);
    }

    public function testPostTagIsUniquePerPostAndTag(): void
    {
        $user = $this->createUser();
        $this->persist($user);
        $post = $this->createPostFor($user->id);
        $tag = Tag::create(text: TagText::fromString('йога'), createdBy: $user->id);
        $this->persist($tag);

        $this->entityManager()->persist(PostTag::create(postId: $post->id, tagId: PostTagReference::fromString($tag->id->value())));
        $this->entityManager()->persist(PostTag::create(postId: $post->id, tagId: PostTagReference::fromString($tag->id->value())));

        $this->expectException(\Throwable::class);

        $this->entityManager()->run();
    }

    public function testCannotDeleteTagReferencedByPostTag(): void
    {
        $user = $this->createUser();
        $this->persist($user);
        $post = $this->createPostFor($user->id);
        $tag = Tag::create(text: TagText::fromString('йога'), createdBy: $user->id);
        $this->persist($tag);
        $this->persist(PostTag::create(postId: $post->id, tagId: PostTagReference::fromString($tag->id->value())));

        $this->expectException(\Throwable::class);

        $this->entityManager()->delete($tag);
        $this->entityManager()->run();
    }

    // --- Post: лайки и упоминания (внутренние сущности) ---

    public function testPostLikeLookupsAndExistence(): void
    {
        $author = $this->createUser();
        $this->persist($author);
        $liker = $this->createUser();
        $this->persist($liker);
        $post = $this->createPostFor($author->id);

        $this->persist(PostLike::create(postId: $post->id, userId: $liker->id));
        $this->cleanOrmHeap();

        self::assertInstanceOf(PostLike::class, $this->postRepository()->findLikeByPostAndUser($post->id, $liker->id));
        self::assertTrue($this->postRepository()->existsLikeByPostAndUser($post->id, $liker->id));
        self::assertFalse($this->postRepository()->existsLikeByPostAndUser($post->id, $author->id));
        self::assertCount(1, $this->postRepository()->findLikesByUserId($liker->id));
        self::assertCount(0, $this->postRepository()->findLikesByUserId($author->id));
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

    public function testFindLikesByUserAndPostIdsReturnsOnlyLikedPosts(): void
    {
        $user = $this->createUser();
        $this->persist($user);
        $likedPost = $this->createPostFor($user->id);
        $otherPost = $this->createPostFor($user->id);
        $this->persist(PostLike::create(postId: $likedPost->id, userId: $user->id));
        $this->cleanOrmHeap();

        $likes = $this->postRepository()->findLikesByUserAndPostIds($user->id, $likedPost->id, $otherPost->id);

        self::assertCount(1, $likes);
        self::assertTrue($likes->first()?->postId->equals($likedPost->id));
        self::assertCount(0, $this->postRepository()->findLikesByUserAndPostIds($user->id));
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

        self::assertCount(1, $this->postRepository()->findMentionsByPostId($post->id));
        self::assertCount(1, $this->postRepository()->findMentionsByUserId($mentioned->id));
        self::assertCount(0, $this->postRepository()->findMentionsByUserId($author->id));
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

    // --- Comment: корень ---

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

        $idsDesc = $this->commentIdsDesc($created);

        $firstPage = $this->commentRepository()->findByPostId($post->id, null, 2);
        self::assertSame(\array_slice($idsDesc, 0, 2), $this->commentIds($firstPage->all()));

        $secondPage = $this->commentRepository()->findByPostId($post->id, $firstPage->last()?->id, 2);
        self::assertSame(\array_slice($idsDesc, 2), $this->commentIds($secondPage->all()));
    }

    public function testFindTopLevelByPostIdHidesRepliesAndDeleted(): void
    {
        $user = $this->createUser();
        $this->persist($user);
        $post = $this->createPostFor($user->id);

        $topLevel = $this->newComment($post->id, $user->id);
        $this->persist($topLevel);

        $reply = Comment::create(
            postId: $post->id,
            userId: $user->id,
            text: CommentText::fromString('Ответ'),
            parent: CommentParent::pointingTo($topLevel->id->value()),
        );
        $this->persist($reply);

        $deletedTopLevel = $this->newComment($post->id, $user->id);
        $deletedTopLevel->delete(
            deletedBy: CommentDeletedBy::by($user->id),
            deletedAt: CommentDeletedAt::at(new \DateTimeImmutable()),
            deletionReason: CommentDeletionReason::none(),
        );
        $this->persist($deletedTopLevel);
        $this->cleanOrmHeap();

        $topLevelComments = $this->commentRepository()->findTopLevelByPostId($post->id, null, 10);

        self::assertCount(1, $topLevelComments);
        self::assertTrue($topLevelComments->first()?->id->equals($topLevel->id));
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

        $idsDesc = $this->commentIdsDesc($created);

        $firstPage = $this->commentRepository()->findReplies($parent->id, null, 2);
        self::assertSame(\array_slice($idsDesc, 0, 2), $this->commentIds($firstPage->all()));

        $secondPage = $this->commentRepository()->findReplies($parent->id, $firstPage->last()?->id, 2);
        self::assertSame(\array_slice($idsDesc, 2), $this->commentIds($secondPage->all()));
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

        self::assertFalse($this->commentRepository()->existsLikeByCommentAndUser($comment->id, $user->id));
        self::assertCount(0, $this->commentRepository()->findMentionsByCommentId($comment->id));
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

    // --- Comment: лайки и упоминания (внутренние сущности) ---

    public function testCommentLikeLookupsAndExistence(): void
    {
        $author = $this->createUser();
        $this->persist($author);
        $liker = $this->createUser();
        $this->persist($liker);
        $post = $this->createPostFor($author->id);
        $comment = $this->newComment($post->id, $author->id);
        $this->persist($comment);

        $this->persist(CommentLike::create(commentId: $comment->id, userId: $liker->id));
        $this->cleanOrmHeap();

        self::assertInstanceOf(
            CommentLike::class,
            $this->commentRepository()->findLikeByCommentAndUser($comment->id, $liker->id),
        );
        self::assertTrue($this->commentRepository()->existsLikeByCommentAndUser($comment->id, $liker->id));
        self::assertFalse($this->commentRepository()->existsLikeByCommentAndUser($comment->id, $author->id));
    }

    public function testCommentLikeIsUniquePerCommentAndUser(): void
    {
        $user = $this->createUser();
        $this->persist($user);
        $post = $this->createPostFor($user->id);
        $comment = $this->newComment($post->id, $user->id);
        $this->persist($comment);

        $this->entityManager()->persist(CommentLike::create(commentId: $comment->id, userId: $user->id));
        $this->entityManager()->persist(CommentLike::create(commentId: $comment->id, userId: $user->id));

        $this->expectException(\Throwable::class);

        $this->entityManager()->run();
    }

    public function testFindLikesByUserAndCommentIdsReturnsOnlyLikedComments(): void
    {
        $user = $this->createUser();
        $this->persist($user);
        $post = $this->createPostFor($user->id);
        $likedComment = $this->newComment($post->id, $user->id);
        $this->persist($likedComment);
        $otherComment = $this->newComment($post->id, $user->id);
        $this->persist($otherComment);
        $this->persist(CommentLike::create(commentId: $likedComment->id, userId: $user->id));
        $this->cleanOrmHeap();

        $likes = $this->commentRepository()->findLikesByUserAndCommentIds($user->id, $likedComment->id, $otherComment->id);

        self::assertInstanceOf(CommentLikeCollection::class, $likes);
        self::assertCount(1, $likes);
        self::assertTrue($likes->first()?->commentId->equals($likedComment->id));
        self::assertCount(0, $this->commentRepository()->findLikesByUserAndCommentIds($user->id));
    }

    public function testCommentMentionLookups(): void
    {
        $author = $this->createUser();
        $this->persist($author);
        $mentioned = $this->createUser();
        $this->persist($mentioned);
        $post = $this->createPostFor($author->id);
        $comment = $this->newComment($post->id, $author->id);
        $this->persist($comment);

        $this->persist(CommentMention::create(commentId: $comment->id, userId: $mentioned->id));
        $this->cleanOrmHeap();

        self::assertCount(1, $this->commentRepository()->findMentionsByCommentId($comment->id));
    }

    public function testCommentMentionIsUniquePerCommentAndUser(): void
    {
        $user = $this->createUser();
        $this->persist($user);
        $post = $this->createPostFor($user->id);
        $comment = $this->newComment($post->id, $user->id);
        $this->persist($comment);

        $this->entityManager()->persist(CommentMention::create(commentId: $comment->id, userId: $user->id));
        $this->entityManager()->persist(CommentMention::create(commentId: $comment->id, userId: $user->id));

        $this->expectException(\Throwable::class);

        $this->entityManager()->run();
    }

    // --- PostBlock: независимый корень ---

    public function testStoresActiveBlockAndFindsIt(): void
    {
        $author = $this->createUser();
        $this->persist($author);
        $blocker = $this->createUser();
        $this->persist($blocker);
        $post = $this->newPost($author->id, PostStatus::Blocked);
        $this->persist($post);

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
        $post = $this->newPost($author->id, PostStatus::Blocked);
        $this->persist($post);

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
        $post = $this->newPost($author->id, PostStatus::Blocked);
        $this->persist($post);

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
        $post = $this->newPost($author->id, PostStatus::Blocked);
        $this->persist($post);

        $this->persist(PostBlock::create(
            postId: $post->id,
            reason: BlockReason::fromString('Нарушение правил'),
            blockedBy: $blocker->id,
        ));

        $this->expectException(\Throwable::class);

        $this->entityManager()->delete($blocker);
        $this->entityManager()->run();
    }

    // --- Хелперы ---

    private function newPost(
        UserId $userId,
        PostStatus $status = PostStatus::Draft,
        PostText|null $text = null,
        AttachmentType $attachmentType = AttachmentType::None,
    ): Post {
        return Post::create(
            userId: $userId,
            text: $text ?? PostText::none(),
            status: $status,
            attachmentType: $attachmentType,
            lesson: PostLesson::none(),
            practice: PostPractice::none(),
            original: PostOriginal::none(),
        );
    }

    private function createPostFor(UserId $userId): Post
    {
        $post = $this->newPost($userId, PostStatus::Published);
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

    /**
     * @param list<Comment> $comments
     *
     * @return list<string>
     */
    private function commentIdsDesc(array $comments): array
    {
        $ids = $this->commentIds($comments);
        \rsort($ids);

        return $ids;
    }

    /**
     * @param list<Comment> $comments
     *
     * @return list<string>
     */
    private function commentIds(array $comments): array
    {
        return \array_map(static fn(Comment $comment): string => $comment->id->value(), $comments);
    }
}
