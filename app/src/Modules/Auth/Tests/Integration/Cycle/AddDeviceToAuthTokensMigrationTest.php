<?php

declare(strict_types=1);

namespace App\Modules\Auth\Tests\Integration\Cycle;

use Tests\DatabaseTestCase;
use Tests\Support\Migration\ReplaysMigration;

/**
 * Реальное исполнение уже применённой миграции, добавляющей `ip`/`user_agent` в `auth_tokens`.
 *
 * Файл переехал волной G из `app/database/migrations` (вне области покрытия) в `app/src` модуля
 * Auth: `migrate-test-databases.sh` применяет его один раз до PHPUnit/PCOV, поэтому без прямого
 * вызова down()/up() тело миграции остаётся непокрытым. Тест откатывает и повторно накатывает
 * колонки внутри транзакции `DatabaseTestCase`, которая откатывается в tearDown.
 */
final class AddDeviceToAuthTokensMigrationTest extends DatabaseTestCase
{
    use ReplaysMigration;

    private const string MIGRATION_CLASS = 'Migration\\AddDeviceToAuthTokens';
    private const string MIGRATION_FILE = '/app/src/Modules/Auth/Infrastructure/Persistence/Cycle/Migration/20260616.180010_0_add_device_to_auth_tokens.php';

    public function testDownDropsColumnsAndUpRecreatesThem(): void
    {
        $schema = $this->migrationDatabase()->table('auth_tokens')->getSchema();
        self::assertTrue($schema->hasColumn('ip'));
        self::assertTrue($schema->hasColumn('user_agent'));

        $this->migrationInstance(self::MIGRATION_CLASS, self::MIGRATION_FILE)->down();

        $afterDown = $this->migrationDatabase()->table('auth_tokens')->getSchema();
        self::assertFalse($afterDown->hasColumn('ip'));
        self::assertFalse($afterDown->hasColumn('user_agent'));

        $this->migrationInstance(self::MIGRATION_CLASS, self::MIGRATION_FILE)->up();

        $afterUp = $this->migrationDatabase()->table('auth_tokens')->getSchema();
        self::assertTrue($afterUp->hasColumn('ip'));
        self::assertTrue($afterUp->hasColumn('user_agent'));
    }
}
