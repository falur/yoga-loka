<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Posts\Application;

use App\Modules\Media\Public\Contract\MediaContract;
use App\Modules\Posts\Application\View\CommentViewAssembler;
use App\Modules\Posts\Application\View\PostViewAssembler;
use App\Modules\Posts\Domain\Collection\CommentCollection;
use App\Modules\Posts\Domain\Collection\PostCollection;
use App\Modules\Posts\Domain\Entity\Comment;
use App\Modules\Posts\Domain\Entity\Post;
use App\Modules\Posts\Domain\Enum\AttachmentType;
use App\Modules\Posts\Domain\Enum\PostStatus;
use App\Modules\Posts\Domain\ValueObject\CommentParent;
use App\Modules\Posts\Domain\ValueObject\CommentText;
use App\Modules\Posts\Domain\ValueObject\PostLesson;
use App\Modules\Posts\Domain\ValueObject\PostOriginal;
use App\Modules\Posts\Domain\ValueObject\PostPractice;
use App\Modules\Posts\Domain\ValueObject\PostText;
use App\Modules\Tags\Public\Contract\TagsContract;
use App\Modules\User\Public\Contract\UserContract;
use App\Modules\User\Public\Dto\UserProfileDtoCollection;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\ValueObject\UserId;
use Tests\Feature\Modules\Posts\PostsRepositoryTestCase;

/**
 * Пакетное чтение профилей мягкое: пропавший автор просто отсутствует в карте. Posts обязан
 * превратить это в ту же 404, что и одиночный путь, а не в undefined array key -> 500, и бросает
 * её своим ключом перевода (текст совпадает с текстом владельца, тело ответа не меняется).
 */
final class MissingAuthorTest extends PostsRepositoryTestCase
{
    public function testFeedFailsWithOwnKeyWhenPostAuthorIsMissingFromProfileBatch(): void
    {
        $user = $this->createUser();
        $this->persist($user);

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
        $this->cleanOrmHeap();

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('app.posts.author_not_found');

        $this->postViewAssembler()->fromPosts(
            posts: new PostCollection([$post]),
            viewer: $user->id,
        );
    }

    public function testCommentListFailsWithOwnKeyWhenAuthorIsMissingFromProfileBatch(): void
    {
        $user = $this->createUser();
        $this->persist($user);

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

        $comment = Comment::create(
            postId: $post->id,
            userId: $user->id,
            text: CommentText::fromString('Текст комментария'),
            parent: CommentParent::none(),
        );
        $this->persist($comment);
        $this->cleanOrmHeap();

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('app.posts.author_not_found');

        $this->commentViewAssembler()->fromComments(
            comments: new CommentCollection([$comment]),
            viewer: $user->id,
        );
    }

    private function postViewAssembler(): PostViewAssembler
    {
        return new PostViewAssembler(
            users: $this->usersWithoutProfiles(),
            media: $this->getContainer()->get(MediaContract::class),
            tags: $this->getContainer()->get(TagsContract::class),
            postRepository: $this->postRepository(),
            postMediaRepository: $this->postMediaRepository(),
            postTagRepository: $this->postTagRepository(),
            postLikeRepository: $this->postLikeRepository(),
        );
    }

    private function commentViewAssembler(): CommentViewAssembler
    {
        return new CommentViewAssembler(
            users: $this->usersWithoutProfiles(),
            commentLikeRepository: $this->commentLikeRepository(),
        );
    }

    /**
     * Сосед, у которого запрошенных профилей не нашлось: пакетное чтение отдаёт пустой набор.
     */
    private function usersWithoutProfiles(): UserContract
    {
        $users = $this->createStub(UserContract::class);
        $users->method('profilesByIds')->willReturn(new UserProfileDtoCollection());

        return $users;
    }
}
