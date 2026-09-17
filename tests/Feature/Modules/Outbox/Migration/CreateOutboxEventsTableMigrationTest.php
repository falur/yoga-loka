<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Outbox\Migration;

use Cycle\Database\Schema\AbstractIndex;
use Tests\DatabaseTestCase;
use Tests\Support\Migration\ReplaysMigration;

/**
 * Реальное исполнение уже применённой миграции `outbox_events`.
 *
 * Файл переехал волной G из `app/database/migrations` (вне области покрытия) в `app/src` модуля
 * Outbox (внутри неё): `migrate-test-databases.sh` применяет его один раз до PHPUnit/PCOV, поэтому
 * без прямого вызова down()/up() тело миграции остаётся непокрытым. Тест откатывает и повторно
 * накатывает таблицу внутри транзакции `DatabaseTestCase`, которая откатывается в tearDown — на
 * общей тестовой базе следа не остаётся. Редактировать содержимое уже применённой миграции нельзя
 * (`docs/rules.md`), поэтому тест проверяет её фактическим прогоном, а не по имени файла.
 */
final class CreateOutboxEventsTableMigrationTest extends DatabaseTestCase
{
    use ReplaysMigration;

    private const string MIGRATION_CLASS = 'Migration\\CreateOutboxEventsTable';
    private const string MIGRATION_FILE = '/app/src/Modules/Outbox/Infrastructure/Persistence/Cycle/Migration/20260525.153700_0_create_outbox_events_table.php';

    public function testDownDropsTableAndUpRecreatesItWithIndexes(): void
    {
        self::assertTrue($this->hasTable('outbox_events'));

        // Капсула кэширует схему таблицы на первом обращении, поэтому down() и up() берут каждый
        // свой свежий экземпляр миграции — иначе up() увидел бы колонки из кэша, снятого до drop().
        $this->migrationInstance(self::MIGRATION_CLASS, self::MIGRATION_FILE)->down();

        self::assertFalse($this->hasTable('outbox_events'));

        $this->migrationInstance(self::MIGRATION_CLASS, self::MIGRATION_FILE)->up();

        self::assertTrue($this->hasTable('outbox_events'));
        $schema = $this->migrationDatabase()->table('outbox_events')->getSchema();
        self::assertSame(['id'], $schema->getPrimaryKeys());
        self::assertTrue($schema->hasColumn('payload'));
        self::assertTrue($schema->hasColumn('status'));

        $statusIndex = $this->indexOn($schema->getIndexes(), ['status', 'available_at', 'id']);
        self::assertInstanceOf(AbstractIndex::class, $statusIndex);
    }

    /** @param list<AbstractIndex> $indexes */
    private function indexOn(iterable $indexes, array $columns): AbstractIndex|null
    {
        foreach ($indexes as $index) {
            if ($index->getColumns() === $columns) {
                return $index;
            }
        }

        return null;
    }
}
