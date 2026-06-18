<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Posts\Domain\Entity;

use App\Modules\Posts\Domain\Collection\PostMediaCollection;
use App\Modules\Posts\Domain\Collection\PostTagCollection;
use App\Modules\Posts\Domain\Entity\Post;
use App\Modules\Posts\Domain\Enum\AttachmentType;
use App\Modules\Posts\Domain\Enum\PostStatus;
use App\Modules\Posts\Domain\ValueObject\PostLesson;
use App\Modules\Posts\Domain\ValueObject\PostOriginal;
use App\Modules\Posts\Domain\ValueObject\PostPractice;
use App\Modules\Posts\Domain\ValueObject\PostText;
use App\Shared\Domain\Exception\InvalidDomainValueException;
use App\Shared\Domain\ValueObject\AbstractUuidV7Id;
use App\Shared\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class PostEntityTest extends TestCase
{
    public function testCreateInitializesDefaults(): void
    {
        $post = $this->createPost();

        self::assertTrue(AbstractUuidV7Id::isUuidV7($post->id->value()));
        self::assertSame(PostStatus::Draft, $post->status);
        self::assertSame(AttachmentType::None, $post->attachmentType);
        self::assertSame(0, $post->likesCount->value());
        self::assertSame(0, $post->repostsCount->value());
        self::assertSame(0, $post->commentsCount->value());
        self::assertFalse($post->deletion->isDeleted());
        self::assertTrue($post->lesson->isEmpty());
        self::assertTrue($post->practice->isEmpty());
        self::assertTrue($post->original->isEmpty());
        self::assertInstanceOf(PostMediaCollection::class, $post->media);
        self::assertInstanceOf(PostTagCollection::class, $post->tags);
        self::assertCount(0, $post->media);
        self::assertCount(0, $post->tags);
        self::assertEquals($post->createdAt, $post->updatedAt);
    }

    public function testStatusTransitions(): void
    {
        $post = $this->createPost();

        $post->publish();
        self::assertSame(PostStatus::Published, $post->status);

        $post->block();
        self::assertSame(PostStatus::Blocked, $post->status);

        $post->unblock();
        self::assertSame(PostStatus::Published, $post->status);
    }

    public function testSoftDeleteAndRestoreTouchUpdatedAt(): void
    {
        $post = $this->createPost();
        $deletedAt = new \DateTimeImmutable('2020-01-01 00:00:00');

        $post->softDelete($deletedAt);

        self::assertTrue($post->deletion->isDeleted());
        self::assertSame($deletedAt, $post->deletion->value());
        self::assertSame($deletedAt, $post->updatedAt);

        $post->restore();
        self::assertFalse($post->deletion->isDeleted());
    }

    public function testCounters(): void
    {
        $post = $this->createPost();

        $post->incrementLikes();
        $post->incrementLikes();
        $post->decrementLikes();
        self::assertSame(1, $post->likesCount->value());

        $post->incrementReposts();
        self::assertSame(1, $post->repostsCount->value());
        $post->decrementReposts();
        self::assertSame(0, $post->repostsCount->value());

        $post->incrementComments();
        self::assertSame(1, $post->commentsCount->value());
        $post->decrementComments();
        self::assertSame(0, $post->commentsCount->value());
    }

    public function testAttachmentIsMutuallyExclusive(): void
    {
        $post = $this->createPost();
        $lessonUuid = Uuid::uuid7()->toString();
        $practiceUuid = Uuid::uuid7()->toString();

        $post->setLesson(PostLesson::pointingTo($lessonUuid));
        self::assertSame(AttachmentType::Lesson, $post->attachmentType);
        self::assertSame($lessonUuid, $post->lesson->value());
        self::assertTrue($post->practice->isEmpty());

        $post->setPractice(PostPractice::pointingTo($practiceUuid));
        self::assertSame(AttachmentType::Practice, $post->attachmentType);
        self::assertSame($practiceUuid, $post->practice->value());
        self::assertTrue($post->lesson->isEmpty());

        $post->setMediaAttachment();
        self::assertSame(AttachmentType::Media, $post->attachmentType);
        self::assertTrue($post->lesson->isEmpty());
        self::assertTrue($post->practice->isEmpty());

        $post->clearAttachment();
        self::assertSame(AttachmentType::None, $post->attachmentType);
        self::assertTrue($post->lesson->isEmpty());
        self::assertTrue($post->practice->isEmpty());
    }

    public function testSetLessonRejectsEmptyReference(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        $this->createPost()->setLesson(PostLesson::none());
    }

    public function testSetPracticeRejectsEmptyReference(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        $this->createPost()->setPractice(PostPractice::none());
    }

    public function testCreateRejectsLessonTypeWithoutLessonReference(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        Post::create(
            userId: UserId::generate(),
            text: PostText::none(),
            status: PostStatus::Draft,
            attachmentType: AttachmentType::Lesson,
            lesson: PostLesson::none(),
            practice: PostPractice::none(),
            original: PostOriginal::none(),
        );
    }

    public function testCreateRejectsPracticeTypeWithoutPracticeReference(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        Post::create(
            userId: UserId::generate(),
            text: PostText::none(),
            status: PostStatus::Draft,
            attachmentType: AttachmentType::Practice,
            lesson: PostLesson::none(),
            practice: PostPractice::none(),
            original: PostOriginal::none(),
        );
    }

    public function testCreateRejectsNoneTypeWithFilledLessonReference(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        Post::create(
            userId: UserId::generate(),
            text: PostText::none(),
            status: PostStatus::Draft,
            attachmentType: AttachmentType::None,
            lesson: PostLesson::pointingTo(Uuid::uuid7()->toString()),
            practice: PostPractice::none(),
            original: PostOriginal::none(),
        );
    }

    public function testCreateRejectsMediaTypeWithFilledPracticeReference(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        Post::create(
            userId: UserId::generate(),
            text: PostText::none(),
            status: PostStatus::Draft,
            attachmentType: AttachmentType::Media,
            lesson: PostLesson::none(),
            practice: PostPractice::pointingTo(Uuid::uuid7()->toString()),
            original: PostOriginal::none(),
        );
    }

    public function testCreateRejectsBothLessonAndPracticeReferences(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        Post::create(
            userId: UserId::generate(),
            text: PostText::none(),
            status: PostStatus::Draft,
            attachmentType: AttachmentType::Lesson,
            lesson: PostLesson::pointingTo(Uuid::uuid7()->toString()),
            practice: PostPractice::pointingTo(Uuid::uuid7()->toString()),
            original: PostOriginal::none(),
        );
    }

    public function testCreateAcceptsLessonTypeWithLessonReference(): void
    {
        $lessonUuid = Uuid::uuid7()->toString();

        $post = Post::create(
            userId: UserId::generate(),
            text: PostText::none(),
            status: PostStatus::Draft,
            attachmentType: AttachmentType::Lesson,
            lesson: PostLesson::pointingTo($lessonUuid),
            practice: PostPractice::none(),
            original: PostOriginal::none(),
        );

        self::assertSame(AttachmentType::Lesson, $post->attachmentType);
        self::assertSame($lessonUuid, $post->lesson->value());
        self::assertTrue($post->practice->isEmpty());
    }

    public function testCreateAcceptsPracticeTypeWithPracticeReference(): void
    {
        $practiceUuid = Uuid::uuid7()->toString();

        $post = Post::create(
            userId: UserId::generate(),
            text: PostText::none(),
            status: PostStatus::Draft,
            attachmentType: AttachmentType::Practice,
            lesson: PostLesson::none(),
            practice: PostPractice::pointingTo($practiceUuid),
            original: PostOriginal::none(),
        );

        self::assertSame(AttachmentType::Practice, $post->attachmentType);
        self::assertSame($practiceUuid, $post->practice->value());
        self::assertTrue($post->lesson->isEmpty());
    }

    public function testCreateAcceptsMediaTypeWithoutReferences(): void
    {
        $post = Post::create(
            userId: UserId::generate(),
            text: PostText::none(),
            status: PostStatus::Draft,
            attachmentType: AttachmentType::Media,
            lesson: PostLesson::none(),
            practice: PostPractice::none(),
            original: PostOriginal::none(),
        );

        self::assertSame(AttachmentType::Media, $post->attachmentType);
        self::assertTrue($post->lesson->isEmpty());
        self::assertTrue($post->practice->isEmpty());
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
