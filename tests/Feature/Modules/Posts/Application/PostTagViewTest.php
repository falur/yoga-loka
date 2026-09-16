<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Posts\Application;

use App\Modules\Media\Public\Contract\MediaContract;
use App\Modules\Posts\Application\View\PostViewAssembler;
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
 * Метки записи собираются по публичному контракту Tags: перебираются связи самой записи, а метка
 * берётся из батча по идентификатору. Проверяются три случая набора: метка есть, метки у соседа нет
 * (связь пропускается, 500 не возникает) и связей нет вовсе (к соседу не ходим).
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

        $view = $this->postViewAssembler($this->getContainer()->get(TagsContract::class))
            ->fromPost(post: $post, viewer: $user->id);

        self::assertCount(1, $view->tags);
        self::assertSame($tag->id->value(), $view->tags[0]->id);
        self::assertSame('йога', $view->tags[0]->text);
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

        $view = $this->postViewAssembler($tags)->fromPost(post: $post, viewer: $user->id);

        self::assertSame([], $view->tags);
    }

    public function testPostWithoutTagsDoesNotCallTagsContract(): void
    {
        $user = $this->createUser();
        $this->persist($user);
        $post = $this->persistPost($user);
        $this->cleanOrmHeap();

        $tags = $this->createMock(TagsContract::class);
        $tags->expects(self::never())->method('textsByIds');

        $view = $this->postViewAssembler($tags)->fromPost(post: $post, viewer: $user->id);

        self::assertSame([], $view->tags);
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

    private function postViewAssembler(TagsContract $tags): PostViewAssembler
    {
        return new PostViewAssembler(
            users: $this->getContainer()->get(UserContract::class),
            media: $this->getContainer()->get(MediaContract::class),
            tags: $tags,
            postRepository: $this->postRepository(),
        );
    }
}
