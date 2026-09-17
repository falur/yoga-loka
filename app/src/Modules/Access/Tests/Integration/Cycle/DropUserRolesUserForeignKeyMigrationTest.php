<?php

declare(strict_types=1);

namespace App\Modules\Access\Tests\Integration\Cycle;

use Cycle\Database\DatabaseInterface;
use Cycle\Database\Schema\AbstractForeignKey;
use Cycle\Database\Schema\AbstractIndex;
use Cycle\Database\Schema\AbstractTable;
use Cycle\Migrations\Capsule;
use Cycle\Migrations\MigrationInterface;
use Tests\DatabaseTestCase;

/**
 * Схема user_roles после снятия межмодульного внешнего ключа на users.
 *
 * Тест смотрит на настоящую схему тестовой базы: применённые миграции уже сняли ключ, а обе стороны
 * новой миграции проверяются прямым вызовом up() и down() на капсуле. Тест наследует транзакционный
 * DatabaseTestCase, поэтому изменения схемы откатываются вместе с транзакцией и не оставляют следа.
 */
final class DropUserRolesUserForeignKeyMigrationTest extends DatabaseTestCase
{
    private const string TABLE = 'user_roles';
    private const string MIGRATION_CLASS = 'App\\Modules\\Access\\Infrastructure\\Persistence\\Cycle\\Migration\\DropUserRolesUserForeignKey';
    private const string MIGRATION_FILE = '/app/src/Modules/Access/Infrastructure/Persistence/Cycle/Migration/20260916.090100_0_drop_user_roles_user_foreign_key.php';

    public function testAppliedSchemaKeepsRoleForeignKeyAndUniqueIndexWithoutUserForeignKey(): void
    {
        self::assertNull($this->foreignKeyOn(['user_id']));

        $roleForeignKey = $this->foreignKeyOn(['role_id']);
        self::assertInstanceOf(AbstractForeignKey::class, $roleForeignKey);
        self::assertSame('roles', $roleForeignKey->getForeignTable());
        self::assertSame('CASCADE', $roleForeignKey->getDeleteRule());

        $uniqueIndex = $this->indexOn(['user_id', 'role_id']);
        self::assertInstanceOf(AbstractIndex::class, $uniqueIndex);
        self::assertTrue($uniqueIndex->isUnique());
    }

    public function testRollbackRestoresUserForeignKeyWithPreviousRules(): void
    {
        $this->migration()->down();

        $restored = $this->foreignKeyOn(['user_id']);

        self::assertInstanceOf(AbstractForeignKey::class, $restored);
        self::assertSame('users', $restored->getForeignTable());
        self::assertSame(['id'], $restored->getForeignKeys());
        self::assertSame('CASCADE', $restored->getDeleteRule());
        self::assertSame('CASCADE', $restored->getUpdateRule());
    }

    public function testMigrationDropsRestoredUserForeignKeyAgain(): void
    {
        $this->migration()->down();

        $this->migration()->up();

        self::assertNull($this->foreignKeyOn(['user_id']));
        self::assertInstanceOf(AbstractForeignKey::class, $this->foreignKeyOn(['role_id']));
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
