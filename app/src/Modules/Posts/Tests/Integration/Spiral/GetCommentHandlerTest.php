<?php

declare(strict_types=1);

namespace App\Modules\Posts\Tests\Integration\Spiral;

use App\Modules\Posts\Application\Contract\CommentViewerReader;
use App\Modules\Posts\Application\Query\GetComment\GetCommentHandler;
use App\Modules\Posts\Application\Query\GetComment\GetCommentQuery;
use App\Modules\Posts\Domain\Entity\Comment;
use App\Modules\Posts\Domain\Entity\CommentLike;
use App\Modules\Posts\Domain\Entity\Post;
use App\Modules\Posts\Domain\Enum\AttachmentType;
use App\Modules\Posts\Domain\Enum\PostStatus;
use App\Modules\Posts\Domain\Exception\CommentNotFoundException;
use App\Modules\Posts\Domain\Exception\PostNotFoundException;
use App\Modules\Posts\Domain\Service\PostVisibilityPolicy;
use App\Modules\Posts\Domain\ValueObject\CommentDeletedAt;
use App\Modules\Posts\Domain\ValueObject\CommentDeletedBy;
use App\Modules\Posts\Domain\ValueObject\CommentDeletionReason;
use App\Modules\Posts\Domain\ValueObject\CommentId;
use App\Modules\Posts\Domain\ValueObject\CommentParent;
use App\Modules\Posts\Domain\ValueObject\CommentText;
use App\Modules\Posts\Domain\ValueObject\PostId;
use App\Modules\Posts\Domain\ValueObject\PostLesson;
use App\Modules\Posts\Domain\ValueObject\PostOriginal;
use App\Modules\Posts\Domain\ValueObject\PostPractice;
use App\Modules\Posts\Domain\ValueObject\PostText;
use App\Modules\User\Public\Contract\UserContract;
use App\Shared\Domain\ValueObject\UserId;
use App\Modules\Posts\Tests\Integration\Cycle\PostsRepositoryTestCase;

/**
 * GetComment читает один комментарий для ответа Command handler-ов CommentPost/ReplyComment.
 * Проверяются все условия показа: видимый комментарий отдаётся с автором и флагом «оценил я»,
 * несуществующий и мягко удалённый — 404 комментария, комментарий у невидимой зрителю записи —
 * 404 записи (сам факт существования чужого черновика не раскрывается).
 */
final class GetCommentHandlerTest extends PostsRepositoryTestCase
{
    public function testReturnsCommentOfVisiblePostWithViewerFlag(): void
    {
        $author = $this->createUser();
        $this->persist($author);
        $viewer = $this->createUser();
        $this->persist($viewer);

        $post = $this->persistPost(userId: $author->id, status: PostStatus::Published);
        $comment = $this->persistComment(postId: $post->id, userId: $author->id);
        $this->persist(CommentLike::create(commentId: $comment->id, userId: $viewer->id));
        $this->cleanOrmHeap();

        $result = $this->handler()->handle(new GetCommentQuery(
            commentId: $comment->id->value(),
            authUserId: $viewer->id->value(),
        ));

        self::assertSame($comment->id->value(), $result->id);
        self::assertSame($post->id->value(), $result->postId);
        self::assertSame('Комментарий', $result->text);
        self::assertTrue($result->likedByMe);
        self::assertSame($author->id->value(), $result->author->userId);
    }

    public function testMissingCommentIsNotFound(): void
    {
        $viewer = $this->createUser();
        $this->persist($viewer);
        $this->cleanOrmHeap();

        $this->expectException(CommentNotFoundException::class);

        $this->handler()->handle(new GetCommentQuery(
            commentId: CommentId::generate()->value(),
            authUserId: $viewer->id->value(),
        ));
    }

    public function testDeletedCommentIsNotFound(): void
    {
        $author = $this->createUser();
        $this->persist($author);

        $post = $this->persistPost(userId: $author->id, status: PostStatus::Published);
        $comment = $this->persistComment(postId: $post->id, userId: $author->id);
        $comment->delete(
            deletedBy: CommentDeletedBy::by($author->id),
            deletedAt: CommentDeletedAt::at(new \DateTimeImmutable()),
            deletionReason: CommentDeletionReason::none(),
        );
        $this->persist($comment);
        $this->cleanOrmHeap();

        $this->expectException(CommentNotFoundException::class);

        $this->handler()->handle(new GetCommentQuery(
            commentId: $comment->id->value(),
            authUserId: $author->id->value(),
        ));
    }

    public function testCommentOfPostInvisibleToViewerIsNotFound(): void
    {
        $author = $this->createUser();
        $this->persist($author);
        $stranger = $this->createUser();
        $this->persist($stranger);

        $post = $this->persistPost(userId: $author->id, status: PostStatus::Draft);
        $comment = $this->persistComment(postId: $post->id, userId: $author->id);
        $this->cleanOrmHeap();

        $this->expectException(PostNotFoundException::class);

        $this->handler()->handle(new GetCommentQuery(
            commentId: $comment->id->value(),
            authUserId: $stranger->id->value(),
        ));
    }

    private function handler(): GetCommentHandler
    {
        return new GetCommentHandler(
            commentRepository: $this->commentRepository(),
            postRepository: $this->postRepository(),
            users: $this->getContainer()->get(UserContract::class),
            commentViewerReader: $this->getContainer()->get(CommentViewerReader::class),
            postVisibilityPolicy: new PostVisibilityPolicy(),
        );
    }

    private function persistPost(UserId $userId, PostStatus $status): Post
    {
        $post = Post::create(
            userId: $userId,
            text: PostText::fromString('Текст записи'),
            status: $status,
            attachmentType: AttachmentType::None,
            lesson: PostLesson::none(),
            practice: PostPractice::none(),
            original: PostOriginal::none(),
        );
        $this->persist($post);

        return $post;
    }

    private function persistComment(PostId $postId, UserId $userId): Comment
    {
        $comment = Comment::create(
            postId: $postId,
            userId: $userId,
            text: CommentText::fromString('Комментарий'),
            parent: CommentParent::none(),
        );
        $this->persist($comment);

        return $comment;
    }
}
