<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Posts\Reader;

use App\Modules\Posts\Application\Contract\PostReader;
use App\Modules\Posts\Application\Data\PostData;
use App\Modules\Posts\Application\Data\PostDataCollection;
use App\Modules\Posts\Domain\Entity\Post;
use App\Modules\Posts\Domain\Entity\PostMedia;
use App\Modules\Posts\Domain\Entity\PostTag;
use App\Modules\Posts\Domain\Enum\AttachmentType;
use App\Modules\Posts\Domain\Enum\PostStatus;
use App\Modules\Posts\Domain\ValueObject\MediaPosition;
use App\Modules\Posts\Domain\ValueObject\PostLesson;
use App\Modules\Posts\Domain\ValueObject\PostMediaReference;
use App\Modules\Posts\Domain\ValueObject\PostOriginal;
use App\Modules\Posts\Domain\ValueObject\PostPractice;
use App\Modules\Posts\Domain\ValueObject\PostTagReference;
use App\Modules\Posts\Domain\ValueObject\PostText;
use App\Modules\Tags\Domain\Entity\Tag;
use App\Modules\Tags\Domain\ValueObject\TagText;
use App\Shared\Domain\ValueObject\UserId;
use Tests\Feature\Modules\Posts\PostsRepositoryTestCase;

/**
 * CyclePostReader: страница ленты (своя/чужая), вложения и метки в PostData, курсор страницы,
 * пустой набор идентификаторов не порождает лишних запросов к таблицам вложений/меток.
 */
final class CyclePostReaderTest extends PostsRepositoryTestCase
{
    public function testMyFeedIncludesAllStatusesExceptBlocked(): void
    {
        $user = $this->createUser();
        $this->persist($user);

        $published = $this->persistPost($user->id, PostStatus::Published);
        $draft = $this->persistPost($user->id, PostStatus::Draft);
        $this->persistPost($user->id, PostStatus::Blocked);
        $this->cleanOrmHeap();

        $page = $this->postReader()->myFeed(ownerUserId: $user->id->value(), cursor: null, limit: 10);

        self::assertSame(
            $this->idsDesc([$published, $draft]),
            $this->postDataIds($page->posts),
        );
    }

    public function testMyFeedExcludesSoftDeletedPosts(): void
    {
        $user = $this->createUser();
        $this->persist($user);

        $published = $this->persistPost($user->id, PostStatus::Published);
        $softDeleted = $this->persistPost($user->id, PostStatus::Published);
        $softDeleted->softDelete(new \DateTimeImmutable());
        $this->persist($softDeleted);
        $this->cleanOrmHeap();

        $page = $this->postReader()->myFeed(ownerUserId: $user->id->value(), cursor: null, limit: 10);

        self::assertSame([$published->id->value()], $this->postDataIds($page->posts));
    }

    public function testUserFeedIncludesOnlyPublished(): void
    {
        $user = $this->createUser();
        $this->persist($user);

        $published = $this->persistPost($user->id, PostStatus::Published);
        $this->persistPost($user->id, PostStatus::Draft);
        $this->persistPost($user->id, PostStatus::Blocked);
        $this->cleanOrmHeap();

        $page = $this->postReader()->userFeed(ownerUserId: $user->id->value(), cursor: null, limit: 10);

        self::assertSame([$published->id->value()], $this->postDataIds($page->posts));
    }

    public function testPageHasNextCursorWhenMoreRowsExist(): void
    {
        $user = $this->createUser();
        $this->persist($user);

        for ($index = 0; $index < 3; $index++) {
            $this->persistPost($user->id, PostStatus::Published);
        }
        $this->cleanOrmHeap();

        $firstPage = $this->postReader()->myFeed(ownerUserId: $user->id->value(), cursor: null, limit: 2);
        self::assertCount(2, $firstPage->posts);
        self::assertNotNull($firstPage->nextCursor);

        $secondPage = $this->postReader()->myFeed(ownerUserId: $user->id->value(), cursor: $firstPage->nextCursor, limit: 2);
        self::assertCount(1, $secondPage->posts);
        self::assertNull($secondPage->nextCursor);
    }

    public function testEmptyFeedReturnsEmptyPageWithNullCursor(): void
    {
        $user = $this->createUser();
        $this->persist($user);
        $this->cleanOrmHeap();

        $page = $this->postReader()->myFeed(ownerUserId: $user->id->value(), cursor: null, limit: 10);

        self::assertTrue($page->posts->isEmpty());
        self::assertNull($page->nextCursor);
    }

    public function testMediaIdsAreOrderedByPositionAndTagIdsAreIncluded(): void
    {
        $user = $this->createUser();
        $this->persist($user);

        $post = $this->persistPost($user->id, PostStatus::Published, AttachmentType::Media);
        $first = $this->persistMedia($post, 0);
        $second = $this->persistMedia($post, 1);
        $tag = Tag::create(text: TagText::fromString('йога'), createdBy: $user->id);
        $this->persist($tag);
        $this->persist(PostTag::create(postId: $post->id, tagId: PostTagReference::fromString($tag->id->value())));
        $this->cleanOrmHeap();

        $page = $this->postReader()->myFeed(ownerUserId: $user->id->value(), cursor: null, limit: 10);

        $postData = $page->posts->first();
        self::assertInstanceOf(PostData::class, $postData);
        self::assertSame([$first, $second], $postData->mediaIds);
        self::assertSame([$tag->id->value()], $postData->tagIds);
    }

    public function testPostWithoutMediaOrTagsHasEmptyLists(): void
    {
        $user = $this->createUser();
        $this->persist($user);
        $this->persistPost($user->id, PostStatus::Published);
        $this->cleanOrmHeap();

        $page = $this->postReader()->myFeed(ownerUserId: $user->id->value(), cursor: null, limit: 10);

        $postData = $page->posts->first();
        self::assertInstanceOf(PostData::class, $postData);
        self::assertSame([], $postData->mediaIds);
        self::assertSame([], $postData->tagIds);
    }

    private function postReader(): PostReader
    {
        return $this->getContainer()->get(PostReader::class);
    }

    private function persistPost(UserId $userId, PostStatus $status, AttachmentType $attachmentType = AttachmentType::None): Post
    {
        $post = Post::create(
            userId: $userId,
            text: PostText::none(),
            status: $status,
            attachmentType: $attachmentType,
            lesson: PostLesson::none(),
            practice: PostPractice::none(),
            original: PostOriginal::none(),
        );
        $this->persist($post);

        return $post;
    }

    private function persistMedia(Post $post, int $position): string
    {
        $mediaId = UserId::generate()->value();
        $this->persist(PostMedia::create(
            post: $post,
            mediaId: PostMediaReference::fromString($mediaId),
            position: MediaPosition::fromInt($position),
        ));

        return $mediaId;
    }

    /**
     * @param list<Post> $posts
     *
     * @return list<string>
     */
    private function idsDesc(array $posts): array
    {
        $ids = \array_map(static fn(Post $post): string => $post->id->value(), $posts);
        \rsort($ids);

        return $ids;
    }

    /**
     * @return list<string>
     */
    private function postDataIds(PostDataCollection $posts): array
    {
        return $posts->mapToList(static fn(PostData $postData): string => $postData->id);
    }
}
