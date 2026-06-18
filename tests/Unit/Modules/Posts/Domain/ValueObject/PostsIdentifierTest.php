<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Posts\Domain\ValueObject;

use App\Modules\Posts\Domain\ValueObject\CommentId;
use App\Modules\Posts\Domain\ValueObject\CommentLikeId;
use App\Modules\Posts\Domain\ValueObject\CommentMentionId;
use App\Modules\Posts\Domain\ValueObject\PostBlockId;
use App\Modules\Posts\Domain\ValueObject\PostId;
use App\Modules\Posts\Domain\ValueObject\PostLikeId;
use App\Modules\Posts\Domain\ValueObject\PostMediaId;
use App\Modules\Posts\Domain\ValueObject\PostMediaReference;
use App\Modules\Posts\Domain\ValueObject\PostMentionId;
use App\Modules\Posts\Domain\ValueObject\PostTagId;
use App\Shared\Domain\Exception\InvalidDomainValueException;
use App\Shared\Domain\ValueObject\AbstractUuidV7Id;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class PostsIdentifierTest extends TestCase
{
    public function testIdentifiersGenerateValidateAndCompare(): void
    {
        /** @var list<class-string<AbstractUuidV7Id>> $idClasses */
        $idClasses = [
            PostId::class,
            CommentId::class,
            PostMediaId::class,
            PostLikeId::class,
            PostMentionId::class,
            PostTagId::class,
            PostBlockId::class,
            CommentLikeId::class,
            CommentMentionId::class,
            PostMediaReference::class,
        ];

        foreach ($idClasses as $idClass) {
            $generated = $idClass::generate();
            $restored = $idClass::fromString((string) $generated);

            self::assertTrue($generated->equals($restored));
            self::assertSame((string) $generated, $generated->jsonSerialize());
            self::assertSame((string) $generated, $generated->value());

            $rejectedV4 = false;

            try {
                $idClass::fromString(Uuid::uuid4()->toString());
            } catch (InvalidDomainValueException) {
                $rejectedV4 = true;
            }

            self::assertTrue($rejectedV4, \sprintf('%s должен отвергать UUID v4.', $idClass));
        }
    }

    public function testDifferentIdentifiersAreNotEqual(): void
    {
        self::assertFalse(PostId::generate()->equals(PostId::generate()));
    }
}
