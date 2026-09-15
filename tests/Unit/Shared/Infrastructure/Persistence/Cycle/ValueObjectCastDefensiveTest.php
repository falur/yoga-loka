<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Persistence\Cycle;

use App\Shared\Infrastructure\Persistence\Cycle\ColumnValueTypecast;
use App\Shared\Infrastructure\Persistence\Cycle\ValueObjectCast;
use PHPUnit\Framework\TestCase;

/**
 * Защитные ветки общего typecast-движка: матрица «правило × тип значения».
 * Фикстуры-правила ниже — тестовые заглушки, к ним не применяется доменное
 * правило «фабрика только при своей логике».
 */
final class ValueObjectCastDefensiveTest extends TestCase
{
    public function testSetRulesSkipsInvalidAndUnsupportedRules(): void
    {
        $valueObjectCast = new ValueObjectCast();

        $leftover = $valueObjectCast->setRules([
            'missing' => 'Totally\\Missing\\Klass12345',
            'unsupported' => \stdClass::class,
            'supported' => StubStringValueObject::class,
        ]);

        self::assertArrayHasKey('missing', $leftover);
        self::assertArrayHasKey('unsupported', $leftover);
        self::assertArrayNotHasKey('supported', $leftover);
    }

    public function testCastConvertsNullableRuleAndDateColumnValue(): void
    {
        $valueObjectCast = new ValueObjectCast();
        $valueObjectCast->setRules([
            'nullable' => StubStringValueObject::class,
            'column' => StubColumnTypecast::class,
        ]);

        $cast = $valueObjectCast->cast([
            'nullable' => null,
            'column' => new \DateTimeImmutable('2026-06-11 00:00:00'),
        ]);

        self::assertNull($cast['nullable']);
        self::assertInstanceOf(\stdClass::class, $cast['column']);
    }

    public function testCastRejectsNonScalarEnumValue(): void
    {
        $valueObjectCast = new ValueObjectCast();
        $valueObjectCast->setRules(['flag' => StubBackedEnum::class]);

        $this->expectException(\InvalidArgumentException::class);

        $valueObjectCast->cast(['flag' => 1.5]);
    }

    public function testCastRejectsNonStringForStringFactory(): void
    {
        $valueObjectCast = new ValueObjectCast();
        $valueObjectCast->setRules(['text' => StubStringValueObject::class]);

        $this->expectException(\InvalidArgumentException::class);

        $valueObjectCast->cast(['text' => 123]);
    }

    public function testCastRejectsNonNumericForIntFactory(): void
    {
        $valueObjectCast = new ValueObjectCast();
        $valueObjectCast->setRules(['number' => StubIntValueObject::class]);

        $this->expectException(\InvalidArgumentException::class);

        $valueObjectCast->cast(['number' => 1.5]);
    }

    public function testCastRejectsFactoryReturningNonObject(): void
    {
        $valueObjectCast = new ValueObjectCast();
        $valueObjectCast->setRules(['broken' => StubStringFactoryReturningScalar::class]);

        $this->expectException(\InvalidArgumentException::class);

        $valueObjectCast->cast(['broken' => 'value']);
    }

    public function testCastRejectsColumnCastReturningNonObject(): void
    {
        $valueObjectCast = new ValueObjectCast();
        $valueObjectCast->setRules(['broken' => StubColumnCastReturningScalar::class]);

        $this->expectException(\InvalidArgumentException::class);

        $valueObjectCast->cast(['broken' => 'value']);
    }

    public function testCastRejectsUnsupportedDatabaseValueType(): void
    {
        $valueObjectCast = new ValueObjectCast();
        $valueObjectCast->setRules(['column' => StubColumnTypecast::class]);

        $this->expectException(\InvalidArgumentException::class);

        $valueObjectCast->cast(['column' => new \stdClass()]);
    }

    public function testUncastConvertsSupportedRulesAndPlainValues(): void
    {
        $valueObjectCast = new ValueObjectCast();
        $valueObjectCast->setRules([
            'column' => StubColumnTypecast::class,
            'dateRule' => StubStringValueObject::class,
            'stringRule' => StubStringValueObject::class,
            'voNull' => StubStringValueObject::class,
            'voDate' => StubStringValueObject::class,
        ]);

        $now = new \DateTimeImmutable('2026-06-11 00:00:00');
        $uncast = $valueObjectCast->uncast([
            'plain' => 'no-rule-scalar',
            'column' => 'already-scalar',
            'dateRule' => $now,
            'stringRule' => 'plain-scalar',
            'voNull' => new StubValueObject(null),
            'voDate' => new StubValueObject($now),
        ]);

        self::assertSame('no-rule-scalar', $uncast['plain']);
        self::assertSame('already-scalar', $uncast['column']);
        self::assertSame($now, $uncast['dateRule']);
        self::assertSame('plain-scalar', $uncast['stringRule']);
        self::assertNull($uncast['voNull']);
        self::assertSame($now, $uncast['voDate']);
    }

    public function testUncastRejectsObjectWithoutValueMethod(): void
    {
        $valueObjectCast = new ValueObjectCast();
        $valueObjectCast->setRules(['field' => StubStringValueObject::class]);

        $this->expectException(\InvalidArgumentException::class);

        $valueObjectCast->uncast(['field' => new \stdClass()]);
    }

    public function testUncastRejectsColumnUncastReturningUnsupportedObject(): void
    {
        $valueObjectCast = new ValueObjectCast();
        $valueObjectCast->setRules(['column' => StubColumnUncastReturningObject::class]);

        $this->expectException(\InvalidArgumentException::class);

        $valueObjectCast->uncast(['column' => new \stdClass()]);
    }

    public function testUncastRejectsValueObjectReturningUnsupportedType(): void
    {
        $valueObjectCast = new ValueObjectCast();
        $valueObjectCast->setRules(['field' => StubStringValueObject::class]);

        $this->expectException(\InvalidArgumentException::class);

        $valueObjectCast->uncast(['field' => new StubValueObject(new \stdClass())]);
    }
}

enum StubBackedEnum: string
{
    case Foo = 'foo';
}

final class StubStringValueObject
{
    private function __construct(
        private readonly string $value,
    ) {}

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }
}

final class StubIntValueObject
{
    private function __construct(
        private readonly int $value,
    ) {}

    public static function fromInt(int $value): self
    {
        return new self($value);
    }

    public function value(): int
    {
        return $this->value;
    }
}

final class StubStringFactoryReturningScalar
{
    public static function fromString(string $value): string
    {
        return $value;
    }
}

final class StubValueObject
{
    public function __construct(
        private readonly object|null $stored,
    ) {}

    public function value(): object|null
    {
        return $this->stored;
    }
}

final class StubColumnTypecast implements ColumnValueTypecast
{
    public static function castDatabaseValue(mixed $value): object
    {
        return new \stdClass();
    }

    public static function uncastValue(mixed $value): string
    {
        return 'stored';
    }
}

final class StubColumnCastReturningScalar implements ColumnValueTypecast
{
    public static function castDatabaseValue(mixed $value): string
    {
        return 'not-an-object';
    }

    public static function uncastValue(mixed $value): string
    {
        return 'stored';
    }
}

final class StubColumnUncastReturningObject implements ColumnValueTypecast
{
    public static function castDatabaseValue(mixed $value): object
    {
        return new \stdClass();
    }

    public static function uncastValue(mixed $value): object
    {
        return new \stdClass();
    }
}
