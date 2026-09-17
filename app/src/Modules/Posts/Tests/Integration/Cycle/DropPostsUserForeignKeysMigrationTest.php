<?php

declare(strict_types=1);

namespace App\Modules\Posts\Tests\Integration\Cycle;

use Cycle\Database\DatabaseInterface;
use Cycle\Database\Schema\AbstractForeignKey;
use Cycle\Database\Schema\AbstractIndex;
use Cycle\Database\Schema\AbstractTable;
use Cycle\Migrations\Capsule;
use Cycle\Migrations\MigrationInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\DatabaseTestCase;

/**
 * Схема таблиц Posts после снятия десяти межмодульных внешних ключей на users.id и tags.id.
 *
 * Тест смотрит на настоящую схему тестовой базы: применённые миграции уже сняли ключи, а обе стороны
 * новой миграции проверяются прямым вызовом up() и down() на капсуле. Тест наследует транзакционный
 * DatabaseTestCase, поэтому изменения схемы откатываются вместе с транзакцией и не оставляют следа.
 */
final class DropPostsUserForeignKeysMigrationTest extends DatabaseTestCase
{
    private const string MIGRATION_CLASS = 'App\\Modules\\Posts\\Infrastructure\\Persistence\\Cycle\\Migration\\DropPostsUserForeignKeys';
    private const string MIGRATION_FILE = '/app/src/Modules/Posts/Infrastructure/Persistence/Cycle/Migration/20260916.090300_0_drop_posts_module_user_foreign_keys.php';

    /**
     * @return iterable<string, array{string, list<string>, string, string, string}>
     */
    public static function droppedForeignKeyProvider(): iterable
    {
        yield 'post_tags.tag_id -> tags' => ['post_tags', ['tag_id'], 'tags', 'RESTRICT', 'CASCADE'];
        yield 'posts.user_id -> users' => ['posts', ['user_id'], 'users', 'RESTRICT', 'CASCADE'];
        yield 'post_likes.user_id -> users' => ['post_likes', ['user_id'], 'users', 'CASCADE', 'CASCADE'];
        yield 'post_mentions.user_id -> users' => ['post_mentions', ['user_id'], 'users', 'CASCADE', 'CASCADE'];
        yield 'post_blocks.blocked_by_id -> users' => ['post_blocks', ['blocked_by_id'], 'users', 'RESTRICT', 'CASCADE'];
        yield 'post_blocks.unblocked_by_id -> users' => ['post_blocks', ['unblocked_by_id'], 'users', 'SET NULL', 'CASCADE'];
        yield 'comments.user_id -> users' => ['comments', ['user_id'], 'users', 'RESTRICT', 'CASCADE'];
        yield 'comments.deleted_by_id -> users' => ['comments', ['deleted_by_id'], 'users', 'SET NULL', 'CASCADE'];
        yield 'comment_likes.user_id -> users' => ['comment_likes', ['user_id'], 'users', 'CASCADE', 'CASCADE'];
        yield 'comment_mentions.user_id -> users' => ['comment_mentions', ['user_id'], 'users', 'CASCADE', 'CASCADE'];
    }

    /**
     * Те же снятые ключи без правил удаления/обновления: PHPUnit требует, чтобы набор данных
     * совпадал по числу аргументов с сигнатурой теста.
     *
     * @return iterable<string, array{string, list<string>}>
     */
    public static function droppedForeignKeyColumnsProvider(): iterable
    {
        foreach (self::droppedForeignKeyProvider() as $name => [$table, $columns]) {
            yield $name => [$table, $columns];
        }
    }

    /** @param list<string> $columns */
    #[DataProvider('droppedForeignKeyColumnsProvider')]
    public function testAppliedSchemaHasNoForeignKeyOnDroppedColumn(string $table, array $columns): void
    {
        self::assertNull($this->foreignKeyOn($table, $columns));
    }

    /** @param list<string> $columns */
    #[DataProvider('droppedForeignKeyProvider')]
    public function testRollbackRestoresForeignKeyWithPreviousRules(
        string $table,
        array $columns,
        string $foreignTable,
        string $deleteRule,
        string $updateRule,
    ): void {
        $this->migration()->down();

        $restored = $this->foreignKeyOn($table, $columns);

        self::assertInstanceOf(AbstractForeignKey::class, $restored);
        self::assertSame($foreignTable, $restored->getForeignTable());
        self::assertSame(['id'], $restored->getForeignKeys());
        self::assertSame($deleteRule, $restored->getDeleteRule());
        self::assertSame($updateRule, $restored->getUpdateRule());
    }

    /** @param list<string> $columns */
    #[DataProvider('droppedForeignKeyColumnsProvider')]
    public function testMigrationDropsRestoredForeignKeyAgain(string $table, array $columns): void
    {
        $this->migration()->down();

        $this->migration()->up();

        self::assertNull($this->foreignKeyOn($table, $columns));
    }

    /**
     * Внутримодульные внешние ключи тех же таблиц, которые миграция не трогает.
     *
     * @return iterable<string, array{string, list<string>, string}>
     */
    public static function neighborForeignKeyProvider(): iterable
    {
        yield 'post_tags.post_id -> posts' => ['post_tags', ['post_id'], 'posts'];
        yield 'posts.parent_post_id -> posts' => ['posts', ['parent_post_id'], 'posts'];
        yield 'post_likes.post_id -> posts' => ['post_likes', ['post_id'], 'posts'];
        yield 'post_mentions.post_id -> posts' => ['post_mentions', ['post_id'], 'posts'];
        yield 'post_blocks.post_id -> posts' => ['post_blocks', ['post_id'], 'posts'];
        yield 'comments.post_id -> posts' => ['comments', ['post_id'], 'posts'];
        yield 'comments.parent_comment_id -> comments' => ['comments', ['parent_comment_id'], 'comments'];
        yield 'comment_likes.comment_id -> comments' => ['comment_likes', ['comment_id'], 'comments'];
        yield 'comment_mentions.comment_id -> comments' => ['comment_mentions', ['comment_id'], 'comments'];
    }

    /** @param list<string> $columns */
    #[DataProvider('neighborForeignKeyProvider')]
    public function testAppliedSchemaKeepsNeighborForeignKeyIntact(string $table, array $columns, string $foreignTable): void
    {
        $neighbor = $this->foreignKeyOn($table, $columns);

        self::assertInstanceOf(AbstractForeignKey::class, $neighbor);
        self::assertSame($foreignTable, $neighbor->getForeignTable());
    }

    /**
     * Индексы тех же таблиц, которые миграция не трогает.
     *
     * @return iterable<string, array{string, list<string>, bool}>
     */
    public static function neighborIndexProvider(): iterable
    {
        yield 'post_tags unique (post_id, tag_id)' => ['post_tags', ['post_id', 'tag_id'], true];
        yield 'post_tags index (tag_id)' => ['post_tags', ['tag_id'], false];
        yield 'posts index (user_id, id)' => ['posts', ['user_id', 'id'], false];
        yield 'post_likes unique (post_id, user_id)' => ['post_likes', ['post_id', 'user_id'], true];
        yield 'post_mentions unique (post_id, user_id)' => ['post_mentions', ['post_id', 'user_id'], true];
        yield 'post_blocks index (post_id)' => ['post_blocks', ['post_id'], false];
        yield 'comments index (user_id)' => ['comments', ['user_id'], false];
        yield 'comment_likes unique (comment_id, user_id)' => ['comment_likes', ['comment_id', 'user_id'], true];
        yield 'comment_mentions unique (comment_id, user_id)' => ['comment_mentions', ['comment_id', 'user_id'], true];
    }

    /** @param list<string> $columns */
    #[DataProvider('neighborIndexProvider')]
    public function testAppliedSchemaKeepsNeighborIndexIntact(string $table, array $columns, bool $unique): void
    {
        $index = $this->indexOn($table, $columns);

        self::assertInstanceOf(AbstractIndex::class, $index);
        self::assertSame($unique, $index->isUnique());
    }

    /**
     * Класс миграции лежит в каталоге миграций и не автозагружается Composer: механизм миграций
     * подключает такие файлы сам, поэтому тест делает это так же явно и один раз на процесс.
     */
    private function migration(): MigrationInterface
    {
        if (!\class_exists(self::MIGRATION_CLASS, autoload: false)) {
            require_once $this->rootDirectory() . self::MIGRATION_FILE;
        }

        $migrationClass = self::MIGRATION_CLASS;
        $migration = new $migrationClass();

        self::assertInstanceOf(MigrationInterface::class, $migration);

        return $migration->withCapsule(new Capsule($this->database()));
    }

    /** @param list<string> $columns */
    private function foreignKeyOn(string $table, array $columns): AbstractForeignKey|null
    {
        foreach ($this->tableSchema($table)->getForeignKeys() as $foreignKey) {
            if ($foreignKey->getColumns() === $columns) {
                return $foreignKey;
            }
        }

        return null;
    }

    /** @param list<string> $columns */
    private function indexOn(string $table, array $columns): AbstractIndex|null
    {
        foreach ($this->tableSchema($table)->getIndexes() as $index) {
            if ($index->getColumns() === $columns) {
                return $index;
            }
        }

        return null;
    }

    private function tableSchema(string $table): AbstractTable
    {
        return $this->database()->table($table)->getSchema();
    }

    private function database(): DatabaseInterface
    {
        return $this->getContainer()->get(DatabaseInterface::class);
    }
}
