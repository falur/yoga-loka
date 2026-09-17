<?php

declare(strict_types=1);

namespace App\Modules\Posts\Tests\Unit\Domain\ValueObject;

use App\Modules\Posts\Domain\ValueObject\BlockReason;
use App\Modules\Posts\Domain\ValueObject\BlockUnblockedReason;
use App\Modules\Posts\Domain\ValueObject\CommentDeletionReason;
use App\Modules\Posts\Domain\ValueObject\CommentText;
use App\Modules\Posts\Domain\ValueObject\PostText;
use App\Shared\Domain\Exception\InvalidDomainValueException;
use PHPUnit\Framework\TestCase;

final class PostsTextValueObjectTest extends TestCase
{
    public function testPostTextNoneIsEmpty(): void
    {
        $text = PostText::none();

        self::assertTrue($text->isEmpty());
        self::assertNull($text->value());
        self::assertSame('', (string) $text);
        self::assertNull($text->jsonSerialize());
    }

    public function testPostTextTrimsAndKeepsValue(): void
    {
        $text = PostText::fromString('  Привет мир  ');

        self::assertFalse($text->isEmpty());
        self::assertSame('Привет мир', $text->value());
        self::assertSame('Привет мир', (string) $text);
        self::assertSame('Привет мир', $text->jsonSerialize());
        self::assertTrue($text->equals(PostText::fromString('Привет мир')));
        self::assertFalse($text->equals(PostText::none()));
    }

    public function testPostTextAcceptsNewlineAndMaxLength(): void
    {
        self::assertSame("первая\nвторая", PostText::fromString("первая\nвторая")->value());
        self::assertSame(5000, \mb_strlen((string) PostText::fromString(\str_repeat('я', 5000))));
    }

    public function testPostTextRejectsEmptyString(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        PostText::fromString('   ');
    }

    public function testPostTextRejectsTooLong(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        PostText::fromString(\str_repeat('я', 5001));
    }

    public function testPostTextRejectsControlCharacters(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        PostText::fromString("текст\twith tab");
    }

    public function testCommentTextRequiresValue(): void
    {
        $text = CommentText::fromString('  Хороший пост  ');

        self::assertSame('Хороший пост', $text->value());
        self::assertSame('Хороший пост', (string) $text);
        self::assertSame('Хороший пост', $text->jsonSerialize());
        self::assertTrue($text->equals(CommentText::fromString('Хороший пост')));
        self::assertFalse($text->equals(CommentText::fromString('Другой')));
    }

    public function testCommentTextRejectsEmpty(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        CommentText::fromString('');
    }

    public function testCommentTextRejectsTooLong(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        CommentText::fromString(\str_repeat('я', 2001));
    }

    public function testCommentTextRejectsControlCharacters(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        CommentText::fromString("текст\tтаб");
    }

    public function testBlockReasonRequiresValue(): void
    {
        $reason = BlockReason::fromString('  Нарушение правил  ');

        self::assertSame('Нарушение правил', $reason->value());
        self::assertSame('Нарушение правил', (string) $reason);
        self::assertSame('Нарушение правил', $reason->jsonSerialize());
        self::assertTrue($reason->equals(BlockReason::fromString('Нарушение правил')));
        self::assertFalse($reason->equals(BlockReason::fromString('Другое')));
    }

    public function testBlockReasonRejectsEmpty(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        BlockReason::fromString('');
    }

    public function testBlockReasonRejectsTooLong(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        BlockReason::fromString(\str_repeat('я', 501));
    }

    public function testBlockUnblockedReasonNullObject(): void
    {
        $none = BlockUnblockedReason::none();

        self::assertTrue($none->isEmpty());
        self::assertNull($none->value());
        self::assertSame('', (string) $none);
        self::assertNull($none->jsonSerialize());

        $reason = BlockUnblockedReason::of('  Ошибочная блокировка  ');

        self::assertFalse($reason->isEmpty());
        self::assertSame('Ошибочная блокировка', $reason->value());
        self::assertSame('Ошибочная блокировка', (string) $reason);
        self::assertSame('Ошибочная блокировка', $reason->jsonSerialize());
        self::assertTrue($reason->equals(BlockUnblockedReason::of('Ошибочная блокировка')));
        self::assertFalse($reason->equals($none));
    }

    public function testBlockUnblockedReasonRejectsEmpty(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        BlockUnblockedReason::of('');
    }

    public function testBlockUnblockedReasonRejectsTooLong(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        BlockUnblockedReason::of(\str_repeat('я', 501));
    }

    public function testCommentDeletionReasonNullObject(): void
    {
        $none = CommentDeletionReason::none();

        self::assertTrue($none->isEmpty());
        self::assertNull($none->value());
        self::assertSame('', (string) $none);
        self::assertNull($none->jsonSerialize());

        $reason = CommentDeletionReason::of('  Спам  ');

        self::assertFalse($reason->isEmpty());
        self::assertSame('Спам', $reason->value());
        self::assertSame('Спам', (string) $reason);
        self::assertSame('Спам', $reason->jsonSerialize());
        self::assertTrue($reason->equals(CommentDeletionReason::of('Спам')));
        self::assertFalse($reason->equals($none));
    }

    public function testCommentDeletionReasonRejectsEmpty(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        CommentDeletionReason::of('');
    }

    public function testCommentDeletionReasonRejectsTooLong(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        CommentDeletionReason::of(\str_repeat('я', 501));
    }
}
