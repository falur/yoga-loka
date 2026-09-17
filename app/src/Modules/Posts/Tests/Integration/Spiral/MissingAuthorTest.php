<?php

declare(strict_types=1);

namespace App\Modules\Posts\Tests\Integration\Spiral;

use App\Modules\Media\Public\Contract\MediaContract;
use App\Modules\Posts\Application\Contract\CommentViewerReader;
use App\Modules\Posts\Application\Contract\PostReader;
use App\Modules\Posts\Application\Contract\PostViewerReader;
use App\Modules\Posts\Application\Query\GetMyFeed\GetMyFeedHandler;
use App\Modules\Posts\Application\Query\GetMyFeed\GetMyFeedQuery;
use App\Modules\Posts\Application\Query\GetPostComments\GetPostCommentsHandler;
use App\Modules\Posts\Application\Query\GetPostComments\GetPostCommentsQuery;
use App\Modules\Posts\Domain\Entity\Comment;
use App\Modules\Posts\Domain\Entity\Post;
use App\Modules\Posts\Domain\Enum\AttachmentType;
use App\Modules\Posts\Domain\Enum\PostStatus;
use App\Modules\Posts\Domain\Service\PostVisibilityPolicy;
use App\Modules\Posts\Domain\ValueObject\CommentParent;
use App\Modules\Posts\Domain\ValueObject\CommentText;
use App\Modules\Posts\Domain\ValueObject\PostLesson;
use App\Modules\Posts\Domain\ValueObject\PostOriginal;
use App\Modules\Posts\Domain\ValueObject\PostPractice;
use App\Modules\Posts\Domain\ValueObject\PostText;
use App\Modules\Tags\Public\Contract\TagsContract;
use App\Modules\User\Public\Contract\UserContract;
use App\Modules\User\Public\Dto\UserProfileDtoCollection;
use App\Modules\Posts\Domain\Exception\PostAuthorNotFoundException;
use App\Modules\Posts\Tests\Integration\Cycle\PostsRepositoryTestCase;

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

        $this->expectException(PostAuthorNotFoundException::class);
        $this->expectExceptionMessage('app.posts.author_not_found');

        $this->getMyFeedHandler()->handle(new GetMyFeedQuery(
            authUserId: $user->id->value(),
            cursor: null,
            limit: 20,
        ));
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

        $this->expectException(PostAuthorNotFoundException::class);
        $this->expectExceptionMessage('app.posts.author_not_found');

        $this->getPostCommentsHandler()->handle(new GetPostCommentsQuery(
            postId: $post->id->value(),
            authUserId: $user->id->value(),
            cursor: null,
            limit: 20,
        ));
    }

    private function getMyFeedHandler(): GetMyFeedHandler
    {
        return new GetMyFeedHandler(
            postReader: $this->getContainer()->get(PostReader::class),
            postRepository: $this->postRepository(),
            users: $this->usersWithoutProfiles(),
            media: $this->getContainer()->get(MediaContract::class),
            tags: $this->getContainer()->get(TagsContract::class),
            postViewerReader: $this->getContainer()->get(PostViewerReader::class),
            postVisibilityPolicy: new PostVisibilityPolicy(),
        );
    }

    private function getPostCommentsHandler(): GetPostCommentsHandler
    {
        return new GetPostCommentsHandler(
            postRepository: $this->postRepository(),
            commentRepository: $this->commentRepository(),
            users: $this->usersWithoutProfiles(),
            commentViewerReader: $this->getContainer()->get(CommentViewerReader::class),
            postVisibilityPolicy: new PostVisibilityPolicy(),
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
