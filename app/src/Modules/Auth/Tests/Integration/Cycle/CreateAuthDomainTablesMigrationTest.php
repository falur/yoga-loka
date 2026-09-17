<?php

declare(strict_types=1);

namespace App\Modules\Auth\Tests\Integration\Cycle;

use Tests\DatabaseTestCase;
use Tests\Support\Migration\ReplaysMigration;

/**
 * Реальное исполнение уже применённой миграции трёх таблиц Auth.
 *
 * Файл переехал волной G из `app/database/migrations` (вне области покрытия) в `app/src` модуля
 * Auth: `migrate-test-databases.sh` применяет его один раз до PHPUnit/PCOV, поэтому без прямого
 * вызова down()/up() тело миграции остаётся непокрытым. Тест откатывает и повторно накатывает
 * таблицы внутри транзакции `DatabaseTestCase`, которая откатывается в tearDown. down() этого файла
 * удаляет `auth_tokens` целиком вместе с колонками `ip`/`user_agent`, добавленными отдельной более
 * поздней миграцией `AddDeviceToAuthTokens` (не затронутой этим тестом) — up() восстанавливает
 * таблицу в её исходной, ещё без этих колонок, форме, что соответствует историческому содержимому
 * файла; откат транзакции убирает временное состояние без следа на общей тестовой базе.
 */
final class CreateAuthDomainTablesMigrationTest extends DatabaseTestCase
{
    use ReplaysMigration;

    private const string MIGRATION_CLASS = 'Migration\\CreateAuthDomainTables';
    private const string MIGRATION_FILE = '/app/src/Modules/Auth/Infrastructure/Persistence/Cycle/Migration/20260615.141700_0_create_auth_domain_tables.php';

    private const array TABLES = ['auth_login_codes', 'auth_registration_tickets', 'auth_tokens'];

    public function testDownDropsAllTablesAndUpRecreatesThem(): void
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

        $tokens = $this->migrationDatabase()->table('auth_tokens')->getSchema();
        self::assertSame(['id'], $tokens->getPrimaryKeys());
        self::assertTrue($tokens->hasColumn('token_hash'));
        self::assertFalse($tokens->hasColumn('ip'));
    }
}
