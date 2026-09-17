<?php

declare(strict_types=1);

namespace App\Modules\Tags\Tests\Integration\Cycle;

use Cycle\Database\Schema\AbstractIndex;
use Tests\DatabaseTestCase;
use Tests\Support\Migration\ReplaysMigration;

/**
 * Реальное исполнение уже применённой миграции таблицы `tags`.
 *
 * `migrate-test-databases.sh` применяет миграцию один раз до PHPUnit/PCOV, поэтому без прямого
 * вызова down()/up() тело миграции остаётся непокрытым. Тест откатывает и повторно накатывает
 * таблицу внутри транзакции `DatabaseTestCase`, которая откатывается в tearDown. Миграция не
 * создаёт межмодульный внешний ключ `tags.created_by_id -> users.id`: колонка хранит ссылку без
 * ограничения (docs/arch.md, «Владение данными»).
 */
final class CreateTagsTableMigrationTest extends DatabaseTestCase
{
    use ReplaysMigration;

    private const string MIGRATION_CLASS = 'App\\Modules\\Tags\\Infrastructure\\Persistence\\Cycle\\Migration\\CreateTagsTable';
    private const string MIGRATION_FILE = '/app/src/Modules/Tags/Infrastructure/Persistence/Cycle/Migration/20260917.090000_0_create_tags_table.php';

    public function testDownDropsTableAndUpRecreatesItWithoutForeignKey(): void
    {
        self::assertTrue($this->hasTable('tags'));

        $this->migrationInstance(self::MIGRATION_CLASS, self::MIGRATION_FILE)->down();

        self::assertFalse($this->hasTable('tags'));

        $this->migrationInstance(self::MIGRATION_CLASS, self::MIGRATION_FILE)->up();

        self::assertTrue($this->hasTable('tags'));

        $tags = $this->migrationDatabase()->table('tags')->getSchema();
        self::assertSame(['id'], $tags->getPrimaryKeys());
        self::assertSame([], $tags->getForeignKeys());

        $textIndex = $this->indexOn(['text']);
        self::assertInstanceOf(AbstractIndex::class, $textIndex);
        self::assertTrue($textIndex->isUnique());
    }

    /** @param list<string> $columns */
    private function indexOn(array $columns): AbstractIndex|null
    {
        foreach ($this->migrationDatabase()->table('tags')->getSchema()->getIndexes() as $index) {
            if ($index->getColumns() === $columns) {
                return $index;
            }
        }

        return null;
    }
}
