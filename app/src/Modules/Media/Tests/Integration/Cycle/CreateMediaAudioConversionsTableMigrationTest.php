<?php

declare(strict_types=1);

namespace App\Modules\Media\Tests\Integration\Cycle;

use Tests\DatabaseTestCase;
use Tests\Support\Migration\ReplaysMigration;

/**
 * Реальное исполнение уже применённой миграции `media_audio_conversions`.
 *
 * Файл переехал волной G из `app/database/migrations` (вне области покрытия) в `app/src` модуля
 * Media: `migrate-test-databases.sh` применяет его один раз до PHPUnit/PCOV, поэтому без прямого
 * вызова down()/up() тело миграции остаётся непокрытым. Тест откатывает и повторно накатывает
 * таблицу внутри транзакции `DatabaseTestCase`, которая откатывается в tearDown.
 */
final class CreateMediaAudioConversionsTableMigrationTest extends DatabaseTestCase
{
    use ReplaysMigration;

    private const string MIGRATION_CLASS = 'Migration\\CreateMediaAudioConversionsTable';
    private const string MIGRATION_FILE = '/app/src/Modules/Media/Infrastructure/Persistence/Cycle/Migration/20260620.222100_0_create_media_audio_conversions_table.php';

    public function testDownDropsTableAndUpRecreatesItWithForeignKey(): void
    {
        self::assertTrue($this->hasTable('media_audio_conversions'));

        $this->migrationInstance(self::MIGRATION_CLASS, self::MIGRATION_FILE)->down();

        self::assertFalse($this->hasTable('media_audio_conversions'));

        $this->migrationInstance(self::MIGRATION_CLASS, self::MIGRATION_FILE)->up();

        self::assertTrue($this->hasTable('media_audio_conversions'));
        $schema = $this->migrationDatabase()->table('media_audio_conversions')->getSchema();
        self::assertSame(['id'], $schema->getPrimaryKeys());
        self::assertTrue($schema->hasColumn('waveform'));

        $foreignKey = null;
        foreach ($schema->getForeignKeys() as $candidate) {
            if ($candidate->getColumns() === ['media_id']) {
                $foreignKey = $candidate;
            }
        }
        self::assertNotNull($foreignKey);
        self::assertSame('media', $foreignKey->getForeignTable());
    }
}
