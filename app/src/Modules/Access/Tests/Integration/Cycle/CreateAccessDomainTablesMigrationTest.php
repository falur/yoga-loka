<?php

declare(strict_types=1);

namespace App\Modules\Access\Tests\Integration\Cycle;

use Cycle\Database\Schema\AbstractForeignKey;
use Tests\DatabaseTestCase;
use Tests\Support\Migration\ReplaysMigration;

/**
 * Реальное исполнение уже применённой миграции четырёх таблиц Access.
 *
 * Файл переехал волной G из `app/database/migrations` (вне области покрытия) в `app/src` модуля
 * Access: `migrate-test-databases.sh` применяет его один раз до PHPUnit/PCOV, поэтому без прямого
 * вызова down()/up() тело миграции остаётся непокрытым. Тест откатывает и повторно накатывает
 * таблицы внутри транзакции `DatabaseTestCase`, которая откатывается в tearDown. up() этого
 * исторического файла воссоздаёт `user_roles.user_id -> users.id` — ключ, снятый отдельной
 * миграцией `DropUserRolesUserForeignKey` (не затронутой этим тестом): откат транзакции убирает и
 * это временное воссоздание, на общей тестовой базе следа не остаётся.
 */
final class CreateAccessDomainTablesMigrationTest extends DatabaseTestCase
{
    use ReplaysMigration;

    private const string MIGRATION_CLASS = 'Migration\\CreateAccessDomainTables';
    private const string MIGRATION_FILE = '/app/src/Modules/Access/Infrastructure/Persistence/Cycle/Migration/20260613.143902_0_create_access_domain_tables.php';

    private const array TABLES = ['roles', 'permissions', 'role_permissions', 'user_roles'];

    public function testDownDropsAllTablesAndUpRecreatesThemWithForeignKeys(): void
    {
        foreach (self::TABLES as $table) {
            self::assertTrue($this->hasTable($table));
        }

        $this->migrationInstance(self::MIGRATION_CLASS, self::MIGRATION_FILE)->down();

        foreach (self::TABLES as $table) {
            self::assertFalse($this->hasTable($table));
        }

        $this->migrationInstance(self::MIGRATION_CLASS, self::MIGRATION_FILE)->up();

        foreach (self::TABLES as $table) {
            self::assertTrue($this->hasTable($table));
        }

        $rolePermissions = $this->migrationDatabase()->table('role_permissions')->getSchema();
        $roleForeignKey = $this->foreignKeyOn($rolePermissions->getForeignKeys(), ['role_id']);
        self::assertInstanceOf(AbstractForeignKey::class, $roleForeignKey);
        self::assertSame('roles', $roleForeignKey->getForeignTable());

        $userRoles = $this->migrationDatabase()->table('user_roles')->getSchema();
        $userForeignKey = $this->foreignKeyOn($userRoles->getForeignKeys(), ['user_id']);
        self::assertInstanceOf(AbstractForeignKey::class, $userForeignKey);
        self::assertSame('users', $userForeignKey->getForeignTable());
    }

    /** @param list<string> $columns */
    private function foreignKeyOn(iterable $foreignKeys, array $columns): AbstractForeignKey|null
    {
        foreach ($foreignKeys as $foreignKey) {
            if ($foreignKey->getColumns() === $columns) {
                return $foreignKey;
            }
        }

        return null;
    }
}
