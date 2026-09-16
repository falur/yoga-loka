<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Posts\Reader;

use App\Modules\Posts\Application\Contract\CommentViewerReader;
use App\Modules\Posts\Domain\Entity\Comment;
use App\Modules\Posts\Domain\Entity\CommentLike;
use App\Modules\Posts\Domain\Entity\Post;
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

/**
 * CycleCommentViewerReader: флаг «оценил я» — по зрителю, не по автору комментария, батчем по
 * набору комментариев, и пустой набор идентификаторов не ходит в базу.
 */
final class CycleCommentViewerReaderTest extends PostsRepositoryTestCase
{
    public function testLikedByMeReflectsOnlyViewersOwnLikes(): void
    {
        $author = $this->createUser();
        $this->persist($author);
        $viewer = $this->createUser();
        $this->persist($viewer);
        $stranger = $this->createUser();
        $this->persist($stranger);

        $post = $this->persistPost($author->id);
        $likedComment = $this->persistComment(postId: $post->id, userId: $author->id);
        $otherComment = $this->persistComment(postId: $post->id, userId: $author->id);
        $this->persist(CommentLike::create(commentId: $likedComment->id, userId: $viewer->id));
        $this->persist(CommentLike::create(commentId: $otherComment->id, userId: $stranger->id));
        $this->cleanOrmHeap();

        $flags = $this->commentViewerReader()->likedByMe(
            commentIds: [$likedComment->id->value(), $otherComment->id->value()],
            viewerId: $viewer->id->value(),
        );

        self::assertTrue($flags->isLiked($likedComment->id->value()));
        self::assertFalse($flags->isLiked($otherComment->id->value()));
    }

    public function testEmptyCommentIdsReturnsEmptyFlagsWithoutQuery(): void
    {
        $viewer = $this->createUser();
        $this->persist($viewer);

        $flags = $this->commentViewerReader()->likedByMe(commentIds: [], viewerId: $viewer->id->value());

        self::assertFalse($flags->isLiked(UserId::generate()->value()));
    }

    private function commentViewerReader(): CommentViewerReader
    {
        return $this->getContainer()->get(CommentViewerReader::class);
    }

    private function persistPost(UserId $userId): Post
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
