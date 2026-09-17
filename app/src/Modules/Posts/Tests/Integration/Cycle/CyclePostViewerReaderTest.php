<?php

declare(strict_types=1);

namespace App\Modules\Posts\Tests\Integration\Cycle;

use App\Modules\Posts\Application\Contract\PostViewerReader;
use App\Modules\Posts\Domain\Entity\Post;
use App\Modules\Posts\Domain\Entity\PostLike;
use App\Modules\Posts\Domain\Enum\AttachmentType;
use App\Modules\Posts\Domain\Enum\PostStatus;
use App\Modules\Posts\Domain\ValueObject\PostLesson;
use App\Modules\Posts\Domain\ValueObject\PostOriginal;
use App\Modules\Posts\Domain\ValueObject\PostPractice;
use App\Modules\Posts\Domain\ValueObject\PostText;
use App\Shared\Domain\ValueObject\UserId;

/**
 * CyclePostViewerReader: флаг «оценил я» — по зрителю, не по автору записи, батчем по набору
 * записей, и пустой набор идентификаторов не ходит в базу.
 */
final class CyclePostViewerReaderTest extends PostsRepositoryTestCase
{
    public function testLikedByMeReflectsOnlyViewersOwnLikes(): void
    {
        $author = $this->createUser();
        $this->persist($author);
        $viewer = $this->createUser();
        $this->persist($viewer);
        $stranger = $this->createUser();
        $this->persist($stranger);

        $likedPost = $this->persistPost($author->id);
        $otherPost = $this->persistPost($author->id);
        $this->persist(PostLike::create(postId: $likedPost->id, userId: $viewer->id));
        // Лайк постороннего зрителя на тот же otherPost не должен влиять на флаг $viewer.
        $this->persist(PostLike::create(postId: $otherPost->id, userId: $stranger->id));
        $this->cleanOrmHeap();

        $flags = $this->postViewerReader()->likedByMe(
            postIds: [$likedPost->id->value(), $otherPost->id->value()],
            viewerId: $viewer->id->value(),
        );

        self::assertTrue($flags->isLiked($likedPost->id->value()));
        self::assertFalse($flags->isLiked($otherPost->id->value()));
    }

    public function testEmptyPostIdsReturnsEmptyFlagsWithoutQuery(): void
    {
        $viewer = $this->createUser();
        $this->persist($viewer);

        $flags = $this->postViewerReader()->likedByMe(postIds: [], viewerId: $viewer->id->value());

        self::assertFalse($flags->isLiked(UserId::generate()->value()));
    }

    private function postViewerReader(): PostViewerReader
    {
        return $this->getContainer()->get(PostViewerReader::class);
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
}
