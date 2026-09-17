<?php

declare(strict_types=1);

namespace Tests\Support\Migration;

use Cycle\Database\DatabaseInterface;
use Cycle\Migrations\Capsule;
use Cycle\Migrations\MigrationInterface;

/**
 * Прогоняет уже применённую миграцию заново (down()/up()) внутри транзакционного теста, чтобы
 * реально исполнить её код, а не полагаться на однократное применение вне PCOV в
 * `migrate-test-databases.sh`. Файл миграции не автозагружается Composer (namespace `Migration` не
 * PSR-4), поэтому подключается явно тем же приёмом, что `CreatePostsDomainTablesMigrationTest`.
 * Требует `Tests\DatabaseTestCase`-контекста ($this->getContainer(), $this->rootDirectory()).
 */
trait ReplaysMigration
{
    private function migrationInstance(string $migrationClass, string $migrationFile): MigrationInterface
    {
        if (!\class_exists($migrationClass, autoload: false)) {
            require_once $this->rootDirectory() . $migrationFile;
        }

        $migration = new $migrationClass();

        self::assertInstanceOf(MigrationInterface::class, $migration);

        return $migration->withCapsule(new Capsule($this->migrationDatabase()));
    }

    private function hasTable(string $table): bool
    {
        return $this->migrationDatabase()->hasTable($table);
    }

    private function migrationDatabase(): DatabaseInterface
    {
        return $this->getContainer()->get(DatabaseInterface::class);
    }
}
