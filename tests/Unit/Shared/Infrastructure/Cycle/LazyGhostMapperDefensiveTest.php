<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Cycle;

use App\Shared\Infrastructure\Cycle\LazyGhostMapper;
use Cycle\ORM\SchemaInterface;
use PHPUnit\Framework\TestCase;

/**
 * Защитные ветки разбора схемы в LazyGhostMapper. На реальной схеме недостижимы:
 * генератор TableInheritance отключён (app/config/cycle.php), наследования/discriminator нет,
 * а children/resolvedEntityClass валидируются в конструкторе (class_exists), поэтому
 * подставить «битый» класс через конструктор нельзя. Тест строит mapper без конструктора
 * и подменяет схему/состояние напрямую, чтобы покрыть guard'ы без @codeCoverageIgnore.
 */
final class LazyGhostMapperDefensiveTest extends TestCase
{
    public function testEntityClassRejectsInvalidSchemaEntity(): void
    {
        $mapper = $this->mapperWithSchema(
            $this->schemaReturning(static fn(string $role, int $property): int => 123),
        );

        $this->expectException(\UnexpectedValueException::class);

        (new \ReflectionMethod(LazyGhostMapper::class, 'entityClass'))->invoke($mapper, 'media');
    }

    public function testChildrenRejectsNonArraySchema(): void
    {
        $mapper = $this->mapperWithSchema(
            $this->schemaReturning(
                static fn(string $role, int $property): string|null => $property === SchemaInterface::CHILDREN
                    ? 'not-an-array'
                    : null,
            ),
        );

        $this->expectException(\UnexpectedValueException::class);

        (new \ReflectionMethod(LazyGhostMapper::class, 'children'))->invoke($mapper, 'media');
    }

    public function testChildrenRejectsInvalidChildClass(): void
    {
        $mapper = $this->mapperWithSchema(
            $this->schemaReturningChildren(['kind' => 'Totally\\Missing\\Child']),
        );

        $this->expectException(\UnexpectedValueException::class);

        (new \ReflectionMethod(LazyGhostMapper::class, 'children'))->invoke($mapper, 'media');
    }

    public function testChildrenMapsValidChildClasses(): void
    {
        $mapper = $this->mapperWithSchema(
            $this->schemaReturningChildren(['kind' => \stdClass::class]),
        );

        self::assertSame(
            ['kind' => \stdClass::class],
            (new \ReflectionMethod(LazyGhostMapper::class, 'children'))->invoke($mapper, 'media'),
        );
    }

    public function testDiscriminatorRejectsNonStringSchema(): void
    {
        $mapper = $this->mapperWithSchema(
            $this->schemaReturning(
                static fn(string $role, int $property): int|string|null => $property === SchemaInterface::DISCRIMINATOR
                    ? 123
                    : null,
            ),
        );

        $this->expectException(\UnexpectedValueException::class);

        (new \ReflectionMethod(LazyGhostMapper::class, 'discriminator'))->invoke($mapper, 'media');
    }

    public function testDiscriminatorReturnsSchemaValue(): void
    {
        $mapper = $this->mapperWithSchema(
            $this->schemaReturning(
                static fn(string $role, int $property): string|null => $property === SchemaInterface::DISCRIMINATOR
                    ? '_kind'
                    : null,
            ),
        );

        self::assertSame(
            '_kind',
            (new \ReflectionMethod(LazyGhostMapper::class, 'discriminator'))->invoke($mapper, 'media'),
        );
    }

    public function testResolvedEntityClassRejectsMissingClass(): void
    {
        $mapper = (new \ReflectionClass(LazyGhostMapper::class))->newInstanceWithoutConstructor();
        $this->setProperty($mapper, 'role', 'media');
        $this->setProperty($mapper, 'entity', \stdClass::class);
        $this->setProperty($mapper, 'children', ['kind' => 'Totally\\Missing\\Resolved']);
        $this->setProperty($mapper, 'discriminator', '_type');

        $this->expectException(\UnexpectedValueException::class);

        (new \ReflectionMethod(LazyGhostMapper::class, 'resolvedEntityClass'))->invoke($mapper, ['_type' => 'kind'], null);
    }

    private function mapperWithSchema(SchemaInterface $schema): LazyGhostMapper
    {
        $mapper = (new \ReflectionClass(LazyGhostMapper::class))->newInstanceWithoutConstructor();
        $this->setProperty($mapper, 'schema', $schema);

        return $mapper;
    }

    /**
     * @param callable(string, int): (int|string|null) $define
     */
    private function schemaReturning(callable $define): SchemaInterface
    {
        $schema = $this->createStub(SchemaInterface::class);
        $schema->method('define')->willReturnCallback($define);

        return $schema;
    }

    /**
     * @param array<string, string> $children
     */
    private function schemaReturningChildren(array $children): SchemaInterface
    {
        $schema = $this->createStub(SchemaInterface::class);
        $schema->method('define')->willReturnCallback(
            static fn(string $role, int $property): array|null => $property === SchemaInterface::CHILDREN ? $children : null,
        );

        return $schema;
    }

    /**
     * @param string|object|array<string, string> $value
     */
    private function setProperty(LazyGhostMapper $mapper, string $name, string|object|array $value): void
    {
        // Привязка к приватным свойствам mapper'а, часть которых унаследована от базового
        // Cycle ORM Mapper (entity/children/discriminator/schema/role) — чинить при апгрейде Cycle.
        (new \ReflectionProperty(LazyGhostMapper::class, $name))->setValue($mapper, $value);
    }
}
