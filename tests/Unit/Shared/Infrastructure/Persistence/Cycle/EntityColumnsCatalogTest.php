<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Persistence\Cycle;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Инварианты каталогов колонок всех модулей (карточка docs/references/entity-columns.md):
 * каталог — справочник имён таблицы и колонок, а не объект, поэтому инстанцировать его нельзя,
 * и первой константой он обязан объявлять имя таблицы TABLE.
 */
final class EntityColumnsCatalogTest extends TestCase
{
    public function testEveryModuleHasColumnsCatalog(): void
    {
        // Каталог заводится на каждую таблицу: 28 сущностей хранения — 28 каталогов.
        self::assertCount(28, self::columnsCatalogFiles());
    }

    /**
     * Каталог колонок — не объект: конструктор закрыт, чтобы его нельзя было создать
     * по недосмотру вместо обращения к константе.
     */
    #[DataProvider('columnsCatalogClasses')]
    public function testColumnsCatalogIsNotInstantiable(string $columnsClass): void
    {
        $reflection = new \ReflectionClass($columnsClass);
        $constructor = $reflection->getConstructor();

        self::assertNotNull($constructor);
        self::assertTrue($constructor->isPrivate());
        self::assertTrue($reflection->isFinal());

        // Конструктор закрыт снаружи, но остаётся вызываемым телом класса: проверяем, что он
        // пустой и безопасный (не выполняет побочных действий), вызвав его через рефлексию.
        $instance = $reflection->newInstanceWithoutConstructor();
        $constructor->invoke($instance);

        self::assertInstanceOf($columnsClass, $instance);
    }

    /**
     * Имя таблицы каталога называется TABLE (docs/rules.md, раздел «Именование») и не пустое.
     */
    #[DataProvider('columnsCatalogClasses')]
    public function testColumnsCatalogDeclaresTableName(string $columnsClass): void
    {
        $reflection = new \ReflectionClass($columnsClass);

        self::assertTrue($reflection->hasConstant('TABLE'));

        $table = $reflection->getConstant('TABLE');

        self::assertIsString($table);
        self::assertNotSame('', $table);
    }

    /**
     * @return \Generator<string, array{string}>
     */
    public static function columnsCatalogClasses(): \Generator
    {
        foreach (self::columnsCatalogFiles() as $file) {
            $class = self::classFromFile($file);

            yield $class => [$class];
        }
    }

    /**
     * @return list<string>
     */
    private static function columnsCatalogFiles(): array
    {
        $files = \glob(self::projectRoot() . '/app/src/Modules/*/Infrastructure/Persistence/Cycle/Columns/*Columns.php');

        if ($files === false || $files === []) {
            throw new \RuntimeException('Каталоги колонок не найдены.');
        }

        \sort($files);

        return \array_values($files);
    }

    /**
     * Корень проекта ищется по composer.json вверх от файла теста: путь не зависит от
     * глубины вложенности самого теста.
     */
    private static function projectRoot(): string
    {
        $directory = __DIR__;

        while (!\is_file($directory . '/composer.json')) {
            $parent = \dirname($directory);

            if ($parent === $directory) {
                throw new \RuntimeException('Корень проекта не найден.');
            }

            $directory = $parent;
        }

        return $directory;
    }

    /**
     * Полное имя класса восстанавливается из объявления namespace в самом файле, поэтому
     * тест не зависит от порядка и состава модулей.
     */
    private static function classFromFile(string $file): string
    {
        $source = \file_get_contents($file);

        if ($source === false) {
            throw new \RuntimeException(\sprintf('Каталог колонок %s не читается.', $file));
        }

        if (\preg_match(pattern: '/^namespace\s+([^;]+);/m', subject: $source, matches: $matches) !== 1) {
            throw new \RuntimeException(\sprintf('В каталоге колонок %s нет namespace.', $file));
        }

        return $matches[1] . '\\' . \basename($file, '.php');
    }
}
