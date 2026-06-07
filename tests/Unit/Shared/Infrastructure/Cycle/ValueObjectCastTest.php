<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Cycle;

use App\Shared\Infrastructure\Cycle\ColumnValueTypecast;
use App\Shared\Infrastructure\Cycle\ValueObjectCast;
use Cycle\ORM\Exception\TypecastException;
use PHPUnit\Framework\TestCase;

final class ValueObjectCastTest extends TestCase
{
    public function testRestoresValueObjectFromStringFactory(): void
    {
        $cast = new ValueObjectCast();
        $cast->setRules(['name' => ValueObjectCastStringProbe::class]);

        $data = $cast->cast(['name' => 'media']);

        self::assertInstanceOf(ValueObjectCastStringProbe::class, $data['name']);
        self::assertSame('media', $data['name']->value());
    }

    public function testRestoresValueObjectFromIntFactory(): void
    {
        $cast = new ValueObjectCast();
        $cast->setRules(['size' => ValueObjectCastIntProbe::class]);

        $data = $cast->cast(['size' => '42']);

        self::assertInstanceOf(ValueObjectCastIntProbe::class, $data['size']);
        self::assertSame(42, $data['size']->value());
    }

    public function testPreparesValueObjectForDatabase(): void
    {
        $cast = new ValueObjectCast();
        $cast->setRules(['name' => ValueObjectCastStringProbe::class]);

        $data = $cast->uncast(['name' => ValueObjectCastStringProbe::fromString('media')]);

        self::assertSame(['name' => 'media'], $data);
    }

    public function testRestoresAndStoresStringBackedEnum(): void
    {
        $cast = new ValueObjectCast();
        $cast->setRules(['status' => ValueObjectCastStatusProbe::class]);

        $data = $cast->cast(['status' => 'ready']);
        $uncastedData = $cast->uncast($data);

        self::assertSame(ValueObjectCastStatusProbe::Ready, $data['status']);
        self::assertSame(['status' => 'ready'], $uncastedData);
    }

    public function testUsesColumnTypecastRule(): void
    {
        $cast = new ValueObjectCast();
        $cast->setRules(['payload' => ValueObjectCastColumnProbe::class]);

        $data = $cast->cast(['payload' => 'ok']);
        $uncastedData = $cast->uncast($data);

        self::assertInstanceOf(ValueObjectCastStringProbe::class, $data['payload']);
        self::assertSame(['payload' => 'ok'], $uncastedData);
    }

    public function testUnsupportedObjectFailsWithFieldName(): void
    {
        $cast = new ValueObjectCast();

        $this->expectException(TypecastException::class);
        $this->expectExceptionMessage('field');

        $cast->uncast(['field' => new \stdClass()]);
    }
}

final readonly class ValueObjectCastStringProbe implements \Stringable, \JsonSerializable
{
    private function __construct(
        private string $value,
    ) {}

    public static function fromString(string $value): self
    {
        return new self(value: $value);
    }

    public function value(): string
    {
        return $this->value;
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->value;
    }

    #[\Override]
    public function jsonSerialize(): string
    {
        return $this->value;
    }
}

final readonly class ValueObjectCastIntProbe
{
    private function __construct(
        private int $value,
    ) {}

    public static function fromInt(int $value): self
    {
        return new self(value: $value);
    }

    public function value(): int
    {
        return $this->value;
    }
}

enum ValueObjectCastStatusProbe: string
{
    case Ready = 'ready';
}

final class ValueObjectCastColumnProbe implements ColumnValueTypecast
{
    public static function castDatabaseValue(
        string $value,
    ): ValueObjectCastStringProbe {
        return ValueObjectCastStringProbe::fromString($value);
    }

    public static function uncastValue(
        ValueObjectCastStringProbe $value,
    ): string|null {
        return $value->value();
    }
}
