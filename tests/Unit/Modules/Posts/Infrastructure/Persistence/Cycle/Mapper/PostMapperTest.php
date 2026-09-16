<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Posts\Infrastructure\Persistence\Cycle\Mapper;

use App\Modules\Posts\Domain\Entity\Post;
use App\Modules\Posts\Domain\Enum\AttachmentType;
use App\Modules\Posts\Domain\Enum\PostStatus;
use App\Modules\Posts\Domain\ValueObject\PostLesson;
use App\Modules\Posts\Domain\ValueObject\PostOriginal;
use App\Modules\Posts\Domain\ValueObject\PostPractice;
use App\Modules\Posts\Domain\ValueObject\PostText;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Mapper\PostMapper;
use App\Shared\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

/**
 * Переносит проверки удалённых typecast-классов Post (PostTextTypecast, PostLessonTypecast,
 * PostPracticeTypecast, PostOriginalTypecast, PostDeletionTypecast) на Mapper, куда переехала их
 * логика null-bridging между nullable-колонкой и value object с сентинелом.
 */
final class PostMapperTest extends TestCase
{
    public function testMapsPostWithAllOptionalValuesFilled(): void
    {
        $mapper = new PostMapper();
        $lessonId = Uuid::uuid7()->toString();
        $originalId = Uuid::uuid7()->toString();
        $deletedAt = new \DateTimeImmutable('2026-06-17 10:00:00');

        $post = Post::create(
            userId: UserId::generate(),
            text: PostText::fromString('Привет'),
            status: PostStatus::Published,
            attachmentType: AttachmentType::Lesson,
            lesson: PostLesson::pointingTo($lessonId),
            practice: PostPractice::none(),
            original: PostOriginal::pointingTo($originalId),
        );
        $post->softDelete($deletedAt);

        $cycleEntity = $mapper->toCycleEntity($post);

        self::assertSame($post->id->value(), $cycleEntity->id);
        self::assertSame('Привет', $cycleEntity->text);
        self::assertSame($lessonId, $cycleEntity->lessonId);
        self::assertNull($cycleEntity->practiceId);
        self::assertSame($originalId, $cycleEntity->parentPostId);
        self::assertSame($deletedAt, $cycleEntity->deletedAt);

        $restoredPost = $mapper->toDomain($cycleEntity);

        self::assertTrue($post->id->equals($restoredPost->id));
        self::assertSame('Привет', $restoredPost->text->value());
        self::assertSame($lessonId, $restoredPost->lesson->value());
        self::assertTrue($restoredPost->practice->isEmpty());
        self::assertSame($originalId, $restoredPost->original->value());
        self::assertTrue($restoredPost->deletion->isDeleted());
        self::assertSame($deletedAt, $restoredPost->deletion->value());
        self::assertCount(0, $restoredPost->media);
        self::assertCount(0, $restoredPost->tags);
    }

    public function testMapsPostWithEmptyOptionalValuesToNullColumns(): void
    {
        $mapper = new PostMapper();
        $post = Post::create(
            userId: UserId::generate(),
            text: PostText::none(),
            status: PostStatus::Draft,
            attachmentType: AttachmentType::None,
            lesson: PostLesson::none(),
            practice: PostPractice::none(),
            original: PostOriginal::none(),
        );

        $cycleEntity = $mapper->toCycleEntity($post);

        self::assertNull($cycleEntity->text);
        self::assertNull($cycleEntity->lessonId);
        self::assertNull($cycleEntity->practiceId);
        self::assertNull($cycleEntity->parentPostId);
        self::assertNull($cycleEntity->deletedAt);

        $restoredPost = $mapper->toDomain($cycleEntity);

        self::assertTrue($restoredPost->text->isEmpty());
        self::assertTrue($restoredPost->lesson->isEmpty());
        self::assertTrue($restoredPost->practice->isEmpty());
        self::assertTrue($restoredPost->original->isEmpty());
        self::assertFalse($restoredPost->deletion->isDeleted());
    }

    public function testToCycleEntityUpdatesGivenInstanceInPlace(): void
    {
        $mapper = new PostMapper();
        $post = Post::create(
            userId: UserId::generate(),
            text: PostText::none(),
            status: PostStatus::Draft,
            attachmentType: AttachmentType::None,
            lesson: PostLesson::none(),
            practice: PostPractice::none(),
            original: PostOriginal::none(),
        );

        $cycleEntity = $mapper->toCycleEntity($post);
        $post->publish();
        $updatedCycleEntity = $mapper->toCycleEntity($post, $cycleEntity);

        self::assertSame($cycleEntity, $updatedCycleEntity);
        self::assertSame(PostStatus::Published, $updatedCycleEntity->status);
    }
}
