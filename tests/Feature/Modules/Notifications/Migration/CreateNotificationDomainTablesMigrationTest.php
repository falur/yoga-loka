<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Notifications\Migration;

use Tests\DatabaseTestCase;
use Tests\Support\Migration\ReplaysMigration;

/**
 * Реальное исполнение уже применённой миграции трёх таблиц Notifications.
 *
 * Файл переехал волной G из `app/database/migrations` (вне области покрытия) в `app/src` модуля
 * Notifications: `migrate-test-databases.sh` применяет его один раз до PHPUnit/PCOV, поэтому без
 * прямого вызова down()/up() тело миграции остаётся непокрытым. Тест откатывает и повторно
 * накатывает таблицы внутри транзакции `DatabaseTestCase`, которая откатывается в tearDown.
 */
final class CreateNotificationDomainTablesMigrationTest extends DatabaseTestCase
{
    use ReplaysMigration;

    private const string MIGRATION_CLASS = 'Migration\\CreateNotificationDomainTables';
    private const string MIGRATION_FILE = '/app/src/Modules/Notifications/Infrastructure/Persistence/Cycle/Migration/20260613.130000_0_create_notification_domain_tables.php';

    private const array TABLES = ['notifications', 'notification_settings', 'notification_device_tokens'];

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

        $notifications = $this->migrationDatabase()->table('notifications')->getSchema();
        self::assertSame(['id'], $notifications->getPrimaryKeys());
        self::assertTrue($notifications->hasColumn('outbox_id'));

        $settings = $this->migrationDatabase()->table('notification_settings')->getSchema();
        self::assertTrue($settings->hasColumn('channel'));

        $tokens = $this->migrationDatabase()->table('notification_device_tokens')->getSchema();
        self::assertTrue($tokens->hasColumn('token'));
    }
}
