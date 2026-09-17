<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Tags\Migration;

use Cycle\Database\DatabaseInterface;
use Cycle\Database\Schema\AbstractForeignKey;
use Cycle\Database\Schema\AbstractIndex;
use Cycle\Database\Schema\AbstractTable;
use Cycle\Migrations\Capsule;
use Cycle\Migrations\MigrationInterface;
use Tests\DatabaseTestCase;

/**
 * Схема tags после снятия межмодульного внешнего ключа на users.
 *
 * Тест смотрит на настоящую схему тестовой базы: применённые миграции уже сняли ключ, а обе стороны
 * новой миграции проверяются прямым вызовом up() и down() на капсуле. Тест наследует транзакционный
 * DatabaseTestCase, поэтому изменения схемы откатываются вместе с транзакцией и не оставляют следа.
 */
final class DropTagsCreatedByForeignKeyMigrationTest extends DatabaseTestCase
{
    private const string TABLE = 'tags';
    private const string MIGRATION_CLASS = 'App\\Modules\\Tags\\Infrastructure\\Persistence\\Cycle\\Migration\\DropTagsCreatedByForeignKey';
    private const string MIGRATION_FILE = '/app/src/Modules/Tags/Infrastructure/Persistence/Cycle/Migration/20260916.090200_0_drop_tags_created_by_foreign_key.php';

    public function testAppliedSchemaKeepsUniqueTextIndexWithoutCreatedByForeignKey(): void
    {
        self::assertNull($this->foreignKeyOn(['created_by_id']));

        $textIndex = $this->indexOn(['text']);
        self::assertInstanceOf(AbstractIndex::class, $textIndex);
        self::assertTrue($textIndex->isUnique());
    }

    public function testRollbackRestoresCreatedByForeignKeyWithPreviousRules(): void
    {
        $this->migration()->down();

        $restored = $this->foreignKeyOn(['created_by_id']);

        self::assertInstanceOf(AbstractForeignKey::class, $restored);
        self::assertSame('users', $restored->getForeignTable());
        self::assertSame(['id'], $restored->getForeignKeys());
        self::assertSame('RESTRICT', $restored->getDeleteRule());
        self::assertSame('CASCADE', $restored->getUpdateRule());
    }

    public function testMigrationDropsRestoredCreatedByForeignKeyAgain(): void
    {
        $this->migration()->down();

        $this->migration()->up();

        self::assertNull($this->foreignKeyOn(['created_by_id']));
    }

    /**
     * Класс миграции лежит в каталоге миграций и не автозагружается Composer: механизм миграций
     * подключает такие файлы сам, поэтому тест делает это так же явно и один раз на процесс.
     */
    private function migration(): MigrationInterface
    {
        if (!\class_exists(self::MIGRATION_CLASS, autoload: false)) {
            require_once $this->rootDirectory() . self::MIGRATION_FILE;
        }

        $migrationClass = self::MIGRATION_CLASS;
        $migration = new $migrationClass();

        self::assertInstanceOf(MigrationInterface::class, $migration);

        return $migration->withCapsule(new Capsule($this->database()));
    }

    /** @param list<string> $columns */
    private function foreignKeyOn(array $columns): AbstractForeignKey|null
    {
        foreach ($this->tableSchema()->getForeignKeys() as $foreignKey) {
            if ($foreignKey->getColumns() === $columns) {
                return $foreignKey;
            }
        }

        return null;
    }

    /** @param list<string> $columns */
    private function indexOn(array $columns): AbstractIndex|null
    {
        foreach ($this->tableSchema()->getIndexes() as $index) {
            if ($index->getColumns() === $columns) {
                return $index;
            }
        }

        return null;
    }

    private function tableSchema(): AbstractTable
    {
        return $this->database()->table(self::TABLE)->getSchema();
    }

    private function database(): DatabaseInterface
    {
        return $this->getContainer()->get(DatabaseInterface::class);
    }
}
