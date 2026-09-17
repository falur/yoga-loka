<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Media\Migration;

use Tests\DatabaseTestCase;
use Tests\Support\Migration\ReplaysMigration;

/**
 * Реальное исполнение уже применённой миграции четырёх таблиц Media.
 *
 * Файл переехал волной G из `app/database/migrations` (вне области покрытия) в `app/src` модуля
 * Media: `migrate-test-databases.sh` применяет его один раз до PHPUnit/PCOV, поэтому без прямого
 * вызова down()/up() тело миграции остаётся непокрытым. Тест откатывает и повторно накатывает
 * таблицы внутри транзакции `DatabaseTestCase`, которая откатывается в tearDown.
 *
 * `media_audio_conversions` (отдельная более поздняя миграция `CreateMediaAudioConversionsTable`,
 * не затронутая этим тестом) ссылается на `media`, поэтому перед down() этого файла её строка
 * временно снимается тем же приёмом (down() соседней миграции), а после up() восстанавливается —
 * иначе Postgres не даст удалить `media` из-за внешнего ключа. Откат транзакции убирает оба
 * временных состояния без следа на общей тестовой базе.
 */
final class CreateMediaDomainTablesMigrationTest extends DatabaseTestCase
{
    use ReplaysMigration;

    private const string MIGRATION_CLASS = 'Migration\\CreateMediaDomainTables';
    private const string MIGRATION_FILE = '/app/src/Modules/Media/Infrastructure/Persistence/Cycle/Migration/20260521.184100_0_create_media_domain_tables.php';

    private const string AUDIO_MIGRATION_CLASS = 'Migration\\CreateMediaAudioConversionsTable';
    private const string AUDIO_MIGRATION_FILE = '/app/src/Modules/Media/Infrastructure/Persistence/Cycle/Migration/20260620.222100_0_create_media_audio_conversions_table.php';

    private const array TABLES = ['media', 'media_image_conversions', 'media_video_conversions', 'media_multipart_uploads'];

    public function testDownDropsAllTablesAndUpRecreatesThemWithForeignKeys(): void
    {
        foreach (self::TABLES as $table) {
            self::assertTrue($this->hasTable($table));
        }
        self::assertTrue($this->hasTable('media_audio_conversions'));

        $this->migrationInstance(self::AUDIO_MIGRATION_CLASS, self::AUDIO_MIGRATION_FILE)->down();
        $this->migrationInstance(self::MIGRATION_CLASS, self::MIGRATION_FILE)->down();

        foreach (self::TABLES as $table) {
            self::assertFalse($this->hasTable($table));
        }

        $this->migrationInstance(self::MIGRATION_CLASS, self::MIGRATION_FILE)->up();
        $this->migrationInstance(self::AUDIO_MIGRATION_CLASS, self::AUDIO_MIGRATION_FILE)->up();

        foreach (self::TABLES as $table) {
            self::assertTrue($this->hasTable($table));
        }
        self::assertTrue($this->hasTable('media_audio_conversions'));

        $media = $this->migrationDatabase()->table('media')->getSchema();
        self::assertSame(['id'], $media->getPrimaryKeys());
        self::assertTrue($media->hasColumn('storage_key'));

        $imageConversions = $this->migrationDatabase()->table('media_image_conversions')->getSchema();
        $foreignKey = null;
        foreach ($imageConversions->getForeignKeys() as $candidate) {
            if ($candidate->getColumns() === ['media_id']) {
                $foreignKey = $candidate;
            }
        }
        self::assertNotNull($foreignKey);
        self::assertSame('media', $foreignKey->getForeignTable());
    }
}
