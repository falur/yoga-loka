<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Posts\Repository;

use App\Modules\Posts\Domain\Collection\PostTagCollection;
use App\Modules\Posts\Domain\Entity\Post;
use App\Modules\Posts\Domain\Entity\PostTag;
use App\Modules\Posts\Domain\Enum\AttachmentType;
use App\Modules\Posts\Domain\Enum\PostStatus;
use App\Modules\Posts\Domain\ValueObject\PostLesson;
use App\Modules\Posts\Domain\ValueObject\PostOriginal;
use App\Modules\Posts\Domain\ValueObject\PostPractice;
use App\Modules\Posts\Domain\ValueObject\PostTagReference;
use App\Modules\Posts\Domain\ValueObject\PostText;
use App\Modules\Tags\Domain\Entity\Tag;
use App\Modules\Tags\Domain\ValueObject\TagText;
use App\Shared\Domain\ValueObject\UserId;
use Tests\Feature\Modules\Posts\PostsRepositoryTestCase;

final class PostTagRepositoryTest extends PostsRepositoryTestCase
{
    public function testPostTagLookups(): void
    {
        $user = $this->createUser();
        $this->persist($user);
        $post = $this->createPostFor($user->id);
        $tag = Tag::create(text: TagText::fromString('йога'), createdBy: $user->id);
        $this->persist($tag);
        $this->persist(PostTag::create(postId: $post->id, tagId: PostTagReference::fromString($tag->id->value())));
        $this->cleanOrmHeap();

        self::assertCount(1, $this->postTagRepository()->findByPostId($post->id));
        self::assertCount(1, $this->postTagRepository()->findByTagId(PostTagReference::fromString($tag->id->value())));
        self::assertCount(1, $this->postTagRepository()->findByPostIds($post->id));
        self::assertCount(0, $this->postTagRepository()->findByPostIds());
    }

    public function testFindByPostIdsBatchesAcrossPosts(): void
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

        $links = $this->postTagRepository()->findByPostIds($firstPost->id, $secondPost->id);

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
}
