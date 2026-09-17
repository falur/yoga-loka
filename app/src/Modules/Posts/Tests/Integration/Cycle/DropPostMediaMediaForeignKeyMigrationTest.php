<?php

declare(strict_types=1);

namespace App\Modules\Posts\Tests\Integration\Cycle;

use Cycle\Database\DatabaseInterface;
use Cycle\Database\Schema\AbstractForeignKey;
use Cycle\Database\Schema\AbstractIndex;
use Cycle\Database\Schema\AbstractTable;
use Cycle\Migrations\Capsule;
use Cycle\Migrations\MigrationInterface;
use Tests\DatabaseTestCase;

/**
 * Схема post_media после снятия межмодульного внешнего ключа на media.
 *
 * Тест смотрит на настоящую схему тестовой базы: применённые миграции уже сняли ключ, а обе стороны
 * новой миграции проверяются прямым вызовом up() и down() на капсуле. Тест наследует транзакционный
 * DatabaseTestCase, поэтому изменения схемы откатываются вместе с транзакцией и не оставляют следа.
 */
final class DropPostMediaMediaForeignKeyMigrationTest extends DatabaseTestCase
{
    private const string TABLE = 'post_media';
    private const string MIGRATION_CLASS = 'Migration\\DropPostMediaMediaForeignKey';
    private const string MIGRATION_FILE = '/app/src/Modules/Posts/Infrastructure/Persistence/Cycle/Migration/20260915.234500_0_drop_post_media_media_foreign_key.php';

    public function testAppliedSchemaKeepsPostForeignKeyAndBothIndexesWithoutMediaForeignKey(): void
    {
        self::assertNull($this->foreignKeyOn(['media_id']));

        $postForeignKey = $this->foreignKeyOn(['post_id']);
        self::assertInstanceOf(AbstractForeignKey::class, $postForeignKey);
        self::assertSame('posts', $postForeignKey->getForeignTable());
        self::assertSame('CASCADE', $postForeignKey->getDeleteRule());

        $uniqueIndex = $this->indexOn(['post_id', 'media_id']);
        self::assertInstanceOf(AbstractIndex::class, $uniqueIndex);
        self::assertTrue($uniqueIndex->isUnique());

        $positionIndex = $this->indexOn(['post_id', 'position']);
        self::assertInstanceOf(AbstractIndex::class, $positionIndex);
        self::assertFalse($positionIndex->isUnique());
    }

    public function testRollbackRestoresMediaForeignKeyWithPreviousRules(): void
    {
        $this->migration()->down();

        $restored = $this->foreignKeyOn(['media_id']);

        self::assertInstanceOf(AbstractForeignKey::class, $restored);
        self::assertSame('media', $restored->getForeignTable());
        self::assertSame(['id'], $restored->getForeignKeys());
        self::assertSame('RESTRICT', $restored->getDeleteRule());
        self::assertSame('CASCADE', $restored->getUpdateRule());
    }

    public function testMigrationDropsRestoredMediaForeignKeyAndKeepsPostForeignKey(): void
    {
        $this->migration()->down();

        $this->migration()->up();

        self::assertNull($this->foreignKeyOn(['media_id']));
        self::assertInstanceOf(AbstractForeignKey::class, $this->foreignKeyOn(['post_id']));
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
