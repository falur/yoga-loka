<?php

declare(strict_types=1);

namespace App\Modules\User\Tests\Integration\Cycle;

use Cycle\Database\DatabaseInterface;
use Cycle\Database\Schema\AbstractForeignKey;
use Cycle\Database\Schema\AbstractIndex;
use Cycle\Database\Schema\AbstractTable;
use Cycle\Migrations\Capsule;
use Cycle\Migrations\MigrationInterface;
use Tests\DatabaseTestCase;

/**
 * Схема users после снятия межмодульного внешнего ключа на media.
 *
 * Тест смотрит на настоящую схему тестовой базы: применённые миграции уже сняли ключ, а обе стороны
 * новой миграции проверяются прямым вызовом up() и down() на капсуле. Тест наследует транзакционный
 * DatabaseTestCase, поэтому изменения схемы откатываются вместе с транзакцией и не оставляют следа.
 */
final class DropUserAvatarMediaForeignKeyMigrationTest extends DatabaseTestCase
{
    private const string TABLE = 'users';
    private const string MIGRATION_CLASS = 'App\\Modules\\User\\Infrastructure\\Persistence\\Cycle\\Migration\\DropUserAvatarMediaForeignKey';
    private const string MIGRATION_FILE = '/app/src/Modules/User/Infrastructure/Persistence/Cycle/Migration/20260916.090000_0_drop_users_avatar_media_foreign_key.php';

    public function testAppliedSchemaKeepsIndexesWithoutAvatarMediaForeignKey(): void
    {
        self::assertNull($this->foreignKeyOn(['avatar_media_id']));

        $emailIndex = $this->indexOn(['email']);
        self::assertInstanceOf(AbstractIndex::class, $emailIndex);
        self::assertTrue($emailIndex->isUnique());

        $nicknameIndex = $this->indexOn(['nickname']);
        self::assertInstanceOf(AbstractIndex::class, $nicknameIndex);
        self::assertTrue($nicknameIndex->isUnique());

        $statusIndex = $this->indexOn(['status']);
        self::assertInstanceOf(AbstractIndex::class, $statusIndex);
        self::assertFalse($statusIndex->isUnique());
    }

    public function testRollbackRestoresAvatarMediaForeignKeyWithPreviousRules(): void
    {
        $this->migration()->down();

        $restored = $this->foreignKeyOn(['avatar_media_id']);

        self::assertInstanceOf(AbstractForeignKey::class, $restored);
        self::assertSame('media', $restored->getForeignTable());
        self::assertSame(['id'], $restored->getForeignKeys());
        self::assertSame('RESTRICT', $restored->getDeleteRule());
        self::assertSame('CASCADE', $restored->getUpdateRule());
    }

    public function testMigrationDropsRestoredAvatarMediaForeignKeyAgain(): void
    {
        $this->migration()->down();

        $this->migration()->up();

        self::assertNull($this->foreignKeyOn(['avatar_media_id']));
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
