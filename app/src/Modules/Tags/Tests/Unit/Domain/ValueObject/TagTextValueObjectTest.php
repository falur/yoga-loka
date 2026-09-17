<?php

declare(strict_types=1);

namespace App\Modules\Tags\Tests\Unit\Domain\ValueObject;

use App\Modules\Tags\Domain\ValueObject\TagText;
use App\Shared\Domain\Exception\InvalidDomainValueException;
use PHPUnit\Framework\TestCase;

final class TagTextValueObjectTest extends TestCase
{
    public function testTagTextNormalizesToLowerCase(): void
    {
        self::assertSame('йога', TagText::fromString('ЙОГА')->value());
        self::assertSame('yoga_2-0', TagText::fromString('Yoga_2-0')->value());
        self::assertSame('йога', (string) TagText::fromString('Йога'));
        self::assertSame('йога', TagText::fromString('Йога')->jsonSerialize());
        self::assertTrue(TagText::fromString('йога')->equals(TagText::fromString('ЙОГА')));
        self::assertFalse(TagText::fromString('йога')->equals(TagText::fromString('медитация')));
    }

    public function testTagTextRejectsSpace(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        TagText::fromString('йога утром');
    }

    public function testTagTextRejectsSpecialCharacter(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        TagText::fromString('йога!');
    }

    public function testTagTextRejectsEmpty(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        TagText::fromString('   ');
    }

    public function testTagTextRejectsTooLong(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        TagText::fromString(\str_repeat('я', 51));
    }
}
