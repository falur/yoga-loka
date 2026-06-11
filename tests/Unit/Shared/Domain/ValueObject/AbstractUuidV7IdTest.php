<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Domain\ValueObject;

use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Shared\Domain\Exception\InvalidDomainValueException;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class AbstractUuidV7IdTest extends TestCase
{
    public function testJsonSerializeReturnsValueOfConcreteId(): void
    {
        $mediaId = MediaId::generate();
        $restored = MediaId::fromString((string) $mediaId);

        self::assertSame($mediaId->value(), $mediaId->jsonSerialize());
        self::assertTrue($mediaId->equals($restored));
    }

    public function testFromStringRejectsNonUuidV7(): void
    {
        $this->expectException(InvalidDomainValueException::class);
        $this->expectExceptionMessage('Идентификатор должен быть UUID v7.');

        MediaId::fromString(Uuid::uuid4()->toString());
    }
}
