<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Posts\Application;

use App\Modules\Media\Public\Contract\MediaContract;
use App\Modules\Posts\Application\Contract\PostReader;
use App\Modules\Posts\Application\Contract\PostViewerReader;
use App\Modules\Posts\Application\Query\GetMyFeed\GetMyFeedHandler;
use App\Modules\Posts\Application\Query\GetMyFeed\GetMyFeedQuery;
use App\Modules\Posts\Application\Query\GetPost\GetPostHandler;
use App\Modules\Posts\Application\Query\GetPost\GetPostQuery;
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
use App\Modules\Tags\Public\Contract\TagsContract;
use App\Modules\Tags\Public\Dto\TagDtoCollection;
use App\Modules\User\Domain\Entity\User;
use App\Modules\User\Public\Contract\UserContract;
use Tests\Feature\Modules\Posts\PostsRepositoryTestCase;

/**
 * Метки записи собираются по публичному контракту Tags: метка берётся из батча по идентификатору —
 * по связям записи (Entity-путь GetPost) или по набору идентификаторов из PostData (Reader-путь
 * ленты). Проверяются случаи набора: метка есть, метки у соседа нет (связь пропускается, 500 не
 * возникает — оба пути) и связей нет вовсе (к соседу не ходим).
 */
final class PostTagViewTest extends PostsRepositoryTestCase
{
    public function testTagOfPostIsTakenFromContractBatchById(): void
    {
        $user = $this->createUser();
        $this->persist($user);
        $post = $this->persistPost($user);
        $tag = Tag::create(text: TagText::fromString('йога'), createdBy: $user->id);
        $this->persist($tag);
        $this->persist(PostTag::create(
            postId: $post->id,
            tagId: PostTagReference::fromString($tag->id->value()),
        ));
        $this->cleanOrmHeap();

        $result = $this->getPostHandler($this->getContainer()->get(TagsContract::class))
            ->handle(new GetPostQuery(postId: $post->id->value(), authUserId: $user->id->value()));

        self::assertCount(1, $result->tags);
        self::assertSame($tag->id->value(), $result->tags[0]->id);
        self::assertSame('йога', $result->tags[0]->text);
    }

    public function testTagMissingFromContractBatchIsOmittedFromView(): void
    {
        $user = $this->createUser();
        $this->persist($user);
        $post = $this->persistPost($user);
        $tag = Tag::create(text: TagText::fromString('йога'), createdBy: $user->id);
        $this->persist($tag);
        $this->persist(PostTag::create(
            postId: $post->id,
            tagId: PostTagReference::fromString($tag->id->value()),
        ));
        $this->cleanOrmHeap();

        $tags = $this->createStub(TagsContract::class);
        $tags->method('textsByIds')->willReturn(new TagDtoCollection());

        $result = $this->getPostHandler($tags)
            ->handle(new GetPostQuery(postId: $post->id->value(), authUserId: $user->id->value()));

        self::assertSame([], $result->tags);
    }

    public function testPostWithoutTagsDoesNotCallTagsContract(): void
    {
        $user = $this->createUser();
        $this->persist($user);
        $post = $this->persistPost($user);
        $this->cleanOrmHeap();

        $tags = $this->createMock(TagsContract::class);
        $tags->expects(self::never())->method('textsByIds');

        $result = $this->getPostHandler($tags)
            ->handle(new GetPostQuery(postId: $post->id->value(), authUserId: $user->id->value()));

        self::assertSame([], $result->tags);
    }

    /**
     * Лента читает метки по набору идентификаторов из PostData (Reader-путь), а не по связям
     * записи, поэтому пропуск отсутствующей у соседа метки проверяется отдельно от GetPost.
     */
    public function testTagMissingFromContractBatchIsOmittedFromFeed(): void
    {
        $user = $this->createUser();
        $this->persist($user);
        $post = $this->persistPost($user);
        $tag = Tag::create(text: TagText::fromString('йога'), createdBy: $user->id);
        $this->persist($tag);
        $this->persist(PostTag::create(
            postId: $post->id,
            tagId: PostTagReference::fromString($tag->id->value()),
        ));
        $this->cleanOrmHeap();

        $tags = $this->createStub(TagsContract::class);
        $tags->method('textsByIds')->willReturn(new TagDtoCollection());

        $result = $this->getMyFeedHandler($tags)
            ->handle(new GetMyFeedQuery(authUserId: $user->id->value(), cursor: null, limit: 10));

        self::assertCount(1, $result->posts);
        self::assertSame([], $result->posts->first()?->tags);
    }

    private function persistPost(User $user): Post
    {
        $post = Post::create(
            userId: $user->id,
            text: PostText::fromString('Текст записи'),
            status: PostStatus::Published,
            attachmentType: AttachmentType::None,
            lesson: PostLesson::none(),
            practice: PostPractice::none(),
            original: PostOriginal::none(),
        );
        $this->persist($post);

        return $post;
    }

    private function getMyFeedHandler(TagsContract $tags): GetMyFeedHandler
    {
        return new GetMyFeedHandler(
            postReader: $this->getContainer()->get(PostReader::class),
            postRepository: $this->postRepository(),
            users: $this->getContainer()->get(UserContract::class),
            media: $this->getContainer()->get(MediaContract::class),
            tags: $tags,
            postViewerReader: $this->getContainer()->get(PostViewerReader::class),
        );
    }

    private function getPostHandler(TagsContract $tags): GetPostHandler
    {
        return new GetPostHandler(
            postRepository: $this->postRepository(),
            users: $this->getContainer()->get(UserContract::class),
            media: $this->getContainer()->get(MediaContract::class),
            tags: $tags,
            postViewerReader: $this->getContainer()->get(PostViewerReader::class),
        );
    }
}
