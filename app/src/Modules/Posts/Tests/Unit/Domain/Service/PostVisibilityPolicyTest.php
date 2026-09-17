<?php

declare(strict_types=1);

namespace App\Modules\Posts\Tests\Unit\Domain\Service;

use App\Modules\Posts\Domain\Entity\Post;
use App\Modules\Posts\Domain\Enum\AttachmentType;
use App\Modules\Posts\Domain\Enum\PostStatus;
use App\Modules\Posts\Domain\Service\PostVisibilityPolicy;
use App\Modules\Posts\Domain\ValueObject\PostLesson;
use App\Modules\Posts\Domain\ValueObject\PostOriginal;
use App\Modules\Posts\Domain\ValueObject\PostPractice;
use App\Modules\Posts\Domain\ValueObject\PostText;
use App\Shared\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;

/**
 * Правило видимости и доступности записи проверяется на экземпляре политики: сценарий получает
 * её через конструктор, статических вызовов у правила нет.
 */
final class PostVisibilityPolicyTest extends TestCase
{
    public function testPublishedPostIsVisibleToStranger(): void
    {
        $post = $this->post(userId: UserId::generate(), status: PostStatus::Published);

        self::assertTrue(new PostVisibilityPolicy()->isVisibleTo(post: $post, viewer: UserId::generate()));
    }

    public function testDraftIsVisibleToItsOwner(): void
    {
        $owner = UserId::generate();
        $post = $this->post(userId: $owner, status: PostStatus::Draft);

        self::assertTrue(new PostVisibilityPolicy()->isVisibleTo(post: $post, viewer: $owner));
    }

    public function testDraftIsNotVisibleToStranger(): void
    {
        $post = $this->post(userId: UserId::generate(), status: PostStatus::Draft);

        self::assertFalse(new PostVisibilityPolicy()->isVisibleTo(post: $post, viewer: UserId::generate()));
    }

    public function testBlockedPostIsNotVisibleEvenToItsOwner(): void
    {
        $owner = UserId::generate();
        $post = $this->post(userId: $owner, status: PostStatus::Blocked);

        self::assertFalse(new PostVisibilityPolicy()->isVisibleTo(post: $post, viewer: $owner));
    }

    public function testDeletedPublishedPostIsNotVisibleEvenToItsOwner(): void
    {
        $owner = UserId::generate();
        $post = $this->post(userId: $owner, status: PostStatus::Published);
        $post->softDelete(new \DateTimeImmutable('2026-09-17 15:00:00'));

        self::assertFalse(new PostVisibilityPolicy()->isVisibleTo(post: $post, viewer: $owner));
    }

    public function testPublishedPostIsActionable(): void
    {
        $post = $this->post(userId: UserId::generate(), status: PostStatus::Published);

        self::assertTrue(new PostVisibilityPolicy()->isActionable($post));
    }

    public function testDraftIsNotActionable(): void
    {
        $post = $this->post(userId: UserId::generate(), status: PostStatus::Draft);

        self::assertFalse(new PostVisibilityPolicy()->isActionable($post));
    }

    public function testBlockedPostIsNotActionable(): void
    {
        $post = $this->post(userId: UserId::generate(), status: PostStatus::Blocked);

        self::assertFalse(new PostVisibilityPolicy()->isActionable($post));
    }

    public function testDeletedPublishedPostIsNotActionable(): void
    {
        $post = $this->post(userId: UserId::generate(), status: PostStatus::Published);
        $post->softDelete(new \DateTimeImmutable('2026-09-17 15:00:00'));

        self::assertFalse(new PostVisibilityPolicy()->isActionable($post));
    }

    private function post(UserId $userId, PostStatus $status): Post
    {
        return Post::create(
            userId: $userId,
            text: PostText::fromString('Текст записи'),
            status: $status,
            attachmentType: AttachmentType::None,
            lesson: PostLesson::none(),
            practice: PostPractice::none(),
            original: PostOriginal::none(),
        );
    }
}
