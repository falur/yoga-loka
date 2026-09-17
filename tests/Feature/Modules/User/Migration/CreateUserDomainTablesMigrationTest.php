<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\User\Migration;

use Cycle\Database\Schema\AbstractForeignKey;
use Tests\DatabaseTestCase;
use Tests\Support\Migration\ReplaysMigration;

/**
 * Реальное исполнение уже применённой миграции трёх таблиц User.
 *
 * Файл переехал волной G из `app/database/migrations` (вне области покрытия) в `app/src` модуля
 * User: `migrate-test-databases.sh` применяет его один раз до PHPUnit/PCOV, поэтому без прямого
 * вызова down()/up() тело миграции остаётся непокрытым. Тест откатывает и повторно накатывает
 * таблицы внутри транзакции `DatabaseTestCase`, которая откатывается в tearDown. up() этого
 * исторического файла воссоздаёт `users.avatar_media_id -> media.id` — ключ, снятый отдельной
 * миграцией `DropUsersAvatarMediaForeignKey` (не затронутой этим тестом): откат транзакции убирает
 * и это временное воссоздание, на общей тестовой базе следа не остаётся.
 */
final class CreateUserDomainTablesMigrationTest extends DatabaseTestCase
{
    use ReplaysMigration;

    private const string MIGRATION_CLASS = 'Migration\\CreateUserDomainTables';
    private const string MIGRATION_FILE = '/app/src/Modules/User/Infrastructure/Persistence/Cycle/Migration/20260613.143901_0_create_user_domain_tables.php';

    private const array TABLES = ['users', 'user_bans', 'reserved_nicknames'];

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

        $users = $this->migrationDatabase()->table('users')->getSchema();
        $avatarForeignKey = $this->foreignKeyOn($users->getForeignKeys(), ['avatar_media_id']);
        self::assertInstanceOf(AbstractForeignKey::class, $avatarForeignKey);
        self::assertSame('media', $avatarForeignKey->getForeignTable());
        self::assertSame('RESTRICT', $avatarForeignKey->getDeleteRule());

        $userBans = $this->migrationDatabase()->table('user_bans')->getSchema();
        $userForeignKey = $this->foreignKeyOn($userBans->getForeignKeys(), ['user_id']);
        self::assertInstanceOf(AbstractForeignKey::class, $userForeignKey);
        self::assertSame('users', $userForeignKey->getForeignTable());

        $reservedNicknames = $this->migrationDatabase()->table('reserved_nicknames')->getSchema();
        $assignedForeignKey = $this->foreignKeyOn($reservedNicknames->getForeignKeys(), ['assigned_user_id']);
        self::assertInstanceOf(AbstractForeignKey::class, $assignedForeignKey);
        self::assertSame('SET NULL', $assignedForeignKey->getDeleteRule());
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
