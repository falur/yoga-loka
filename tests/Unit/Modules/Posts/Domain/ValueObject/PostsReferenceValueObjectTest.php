<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Posts\Domain\ValueObject;

use App\Modules\Posts\Domain\ValueObject\CommentParent;
use App\Modules\Posts\Domain\ValueObject\PostLesson;
use App\Modules\Posts\Domain\ValueObject\PostOriginal;
use App\Modules\Posts\Domain\ValueObject\PostPractice;
use App\Shared\Domain\Exception\InvalidDomainValueException;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class PostsReferenceValueObjectTest extends TestCase
{
    public function testPostLessonNullObjectAndPointer(): void
    {
        $uuid = Uuid::uuid7()->toString();
        $none = PostLesson::none();
        $pointing = PostLesson::pointingTo($uuid);

        self::assertTrue($none->isEmpty());
        self::assertNull($none->value());
        self::assertSame('', (string) $none);
        self::assertNull($none->jsonSerialize());

        self::assertFalse($pointing->isEmpty());
        self::assertSame($uuid, $pointing->value());
        self::assertSame($uuid, (string) $pointing);
        self::assertSame($uuid, $pointing->jsonSerialize());
        self::assertTrue($pointing->equals(PostLesson::pointingTo($uuid)));
        self::assertFalse($pointing->equals($none));
    }

    public function testPostLessonRejectsNonUuidV7(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        PostLesson::pointingTo(Uuid::uuid4()->toString());
    }

    public function testPostPracticeNullObjectAndPointer(): void
    {
        $uuid = Uuid::uuid7()->toString();
        $pointing = PostPractice::pointingTo($uuid);

        self::assertTrue(PostPractice::none()->isEmpty());
        self::assertSame($uuid, $pointing->value());
        self::assertSame($uuid, (string) $pointing);
        self::assertSame($uuid, $pointing->jsonSerialize());
        self::assertFalse($pointing->isEmpty());
        self::assertTrue($pointing->equals(PostPractice::pointingTo($uuid)));
        self::assertFalse($pointing->equals(PostPractice::none()));
    }

    public function testPostPracticeRejectsNonUuidV7(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        PostPractice::pointingTo('not-a-uuid');
    }

    public function testPostOriginalNullObjectAndPointer(): void
    {
        $uuid = Uuid::uuid7()->toString();
        $pointing = PostOriginal::pointingTo($uuid);

        self::assertTrue(PostOriginal::none()->isEmpty());
        self::assertSame('', (string) PostOriginal::none());
        self::assertNull(PostOriginal::none()->jsonSerialize());
        self::assertSame($uuid, $pointing->value());
        self::assertSame($uuid, (string) $pointing);
        self::assertSame($uuid, $pointing->jsonSerialize());
        self::assertFalse($pointing->isEmpty());
        self::assertTrue($pointing->equals(PostOriginal::pointingTo($uuid)));
        self::assertFalse($pointing->equals(PostOriginal::none()));
    }

    public function testPostOriginalRejectsNonUuidV7(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        PostOriginal::pointingTo(Uuid::uuid4()->toString());
    }

    public function testCommentParentNullObjectAndPointer(): void
    {
        $uuid = Uuid::uuid7()->toString();
        $pointing = CommentParent::pointingTo($uuid);

        self::assertTrue(CommentParent::none()->isEmpty());
        self::assertSame('', (string) CommentParent::none());
        self::assertNull(CommentParent::none()->jsonSerialize());
        self::assertSame($uuid, $pointing->value());
        self::assertSame($uuid, (string) $pointing);
        self::assertSame($uuid, $pointing->jsonSerialize());
        self::assertFalse($pointing->isEmpty());
        self::assertTrue($pointing->equals(CommentParent::pointingTo($uuid)));
        self::assertFalse($pointing->equals(CommentParent::none()));
    }

    public function testCommentParentRejectsNonUuidV7(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        CommentParent::pointingTo(Uuid::uuid4()->toString());
    }
}
