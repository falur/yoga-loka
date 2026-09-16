<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Posts\Domain\Entity;

use App\Modules\Posts\Domain\Entity\CommentLike;
use App\Modules\Posts\Domain\Entity\CommentMention;
use App\Modules\Posts\Domain\Entity\Post;
use App\Modules\Posts\Domain\Entity\PostLike;
use App\Modules\Posts\Domain\Entity\PostMedia;
use App\Modules\Posts\Domain\Entity\PostMention;
use App\Modules\Posts\Domain\Entity\PostTag;
use App\Modules\Posts\Domain\Enum\AttachmentType;
use App\Modules\Posts\Domain\Enum\PostStatus;
use App\Modules\Posts\Domain\ValueObject\CommentId;
use App\Modules\Posts\Domain\ValueObject\MediaPosition;
use App\Modules\Posts\Domain\ValueObject\PostId;
use App\Modules\Posts\Domain\ValueObject\PostLesson;
use App\Modules\Posts\Domain\ValueObject\PostMediaReference;
use App\Modules\Posts\Domain\ValueObject\PostOriginal;
use App\Modules\Posts\Domain\ValueObject\PostPractice;
use App\Modules\Posts\Domain\ValueObject\PostTagReference;
use App\Modules\Posts\Domain\ValueObject\PostText;
use App\Shared\Domain\ValueObject\AbstractUuidV7Id;
use App\Shared\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class JoinEntityTest extends TestCase
{
    public function testPostMediaCreateLinksToPost(): void
    {
        $post = $this->createPost();
        $mediaUuid = Uuid::uuid7()->toString();
        $postMedia = PostMedia::create(
            post: $post,
            mediaId: PostMediaReference::fromString($mediaUuid),
            position: MediaPosition::fromInt(0),
        );

        self::assertTrue(AbstractUuidV7Id::isUuidV7($postMedia->id->value()));
        self::assertTrue($post->id->equals($postMedia->postId));
        self::assertSame($mediaUuid, $postMedia->mediaId->value());
        self::assertSame(0, $postMedia->position->value());
    }

    public function testPostLikeCreate(): void
    {
        $postId = PostId::generate();
        $userId = UserId::generate();
        $like = PostLike::create(postId: $postId, userId: $userId);

        self::assertTrue($postId->equals($like->postId));
        self::assertTrue($userId->equals($like->userId));
    }

    public function testPostMentionCreate(): void
    {
        $postId = PostId::generate();
        $userId = UserId::generate();
        $mention = PostMention::create(postId: $postId, userId: $userId);

        self::assertTrue($postId->equals($mention->postId));
        self::assertTrue($userId->equals($mention->userId));
    }

    public function testPostTagCreate(): void
    {
        $postId = PostId::generate();
        $tagId = PostTagReference::generate();
        $postTag = PostTag::create(postId: $postId, tagId: $tagId);

        self::assertTrue($postId->equals($postTag->postId));
        self::assertTrue($tagId->equals($postTag->tagId));
    }

    public function testCommentLikeCreate(): void
    {
        $commentId = CommentId::generate();
        $userId = UserId::generate();
        $like = CommentLike::create(commentId: $commentId, userId: $userId);

        self::assertTrue($commentId->equals($like->commentId));
        self::assertTrue($userId->equals($like->userId));
    }

    public function testCommentMentionCreate(): void
    {
        $commentId = CommentId::generate();
        $userId = UserId::generate();
        $mention = CommentMention::create(commentId: $commentId, userId: $userId);

        self::assertTrue($commentId->equals($mention->commentId));
        self::assertTrue($userId->equals($mention->userId));
    }

    private function createPost(): Post
    {
        return Post::create(
            userId: UserId::generate(),
            text: PostText::none(),
            status: PostStatus::Draft,
            attachmentType: AttachmentType::None,
            lesson: PostLesson::none(),
            practice: PostPractice::none(),
            original: PostOriginal::none(),
        );
    }
}
