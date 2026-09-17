<?php

declare(strict_types=1);

namespace App\Modules\Posts\Tests\Integration\Cycle;

use Cycle\Database\Schema\AbstractForeignKey;
use Cycle\Database\Schema\AbstractTable;
use Tests\DatabaseTestCase;
use Tests\Support\Migration\ReplaysMigration;

/**
 * Реальное исполнение уже применённой миграции девяти таблиц Posts.
 *
 * `migrate-test-databases.sh` применяет миграцию один раз до PHPUnit/PCOV, поэтому без прямого
 * вызова down()/up() тело миграции остаётся непокрытым. Тест откатывает и повторно накатывает
 * таблицы внутри транзакции `DatabaseTestCase`, которая откатывается в tearDown. Миграция не
 * создаёт ни одного межмодульного внешнего ключа: ссылки на `users` и `tags` — обычные колонки без
 * ограничения (docs/arch.md, «Владение данными»); внутримодульные ключи (на `posts`/`comments`
 * этого же модуля) остаются как есть.
 */
final class CreatePostsDomainTablesMigrationTest extends DatabaseTestCase
{
    use ReplaysMigration;

    private const string MIGRATION_CLASS = 'Migration\\CreatePostsDomainTables';
    private const string MIGRATION_FILE = '/app/src/Modules/Posts/Infrastructure/Persistence/Cycle/Migration/20260617.160942_0_create_posts_domain_tables.php';

    private const array TABLES = [
        'posts',
        'post_media',
        'post_likes',
        'post_mentions',
        'post_tags',
        'post_blocks',
        'comments',
        'comment_likes',
        'comment_mentions',
    ];

    public function testDownDropsAllNineTablesAndUpRecreatesThemWithForeignKeys(): void
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

        $posts = $this->migrationDatabase()->table('posts')->getSchema();
        self::assertNull($this->foreignKeyOn($posts, ['user_id']));
        $selfForeignKey = $this->foreignKeyOn($posts, ['parent_post_id']);
        self::assertNotNull($selfForeignKey);
        self::assertSame('posts', $selfForeignKey->getForeignTable());

        self::assertNull($this->foreignKeyOn(
            $this->migrationDatabase()->table('post_media')->getSchema(),
            ['media_id'],
        ));

        $postTags = $this->migrationDatabase()->table('post_tags')->getSchema();
        self::assertNull($this->foreignKeyOn($postTags, ['tag_id']));
        self::assertNotNull($this->foreignKeyOn($postTags, ['post_id']));

        $postBlocks = $this->migrationDatabase()->table('post_blocks')->getSchema();
        self::assertNull($this->foreignKeyOn($postBlocks, ['blocked_by_id']));
        self::assertNull($this->foreignKeyOn($postBlocks, ['unblocked_by_id']));

        $comments = $this->migrationDatabase()->table('comments')->getSchema();
        self::assertNull($this->foreignKeyOn($comments, ['user_id']));
        self::assertNull($this->foreignKeyOn($comments, ['deleted_by_id']));
        $parentCommentForeignKey = $this->foreignKeyOn($comments, ['parent_comment_id']);
        self::assertNotNull($parentCommentForeignKey);
        self::assertSame('comments', $parentCommentForeignKey->getForeignTable());

        self::assertNull($this->foreignKeyOn(
            $this->migrationDatabase()->table('comment_likes')->getSchema(),
            ['user_id'],
        ));
        self::assertNull($this->foreignKeyOn(
            $this->migrationDatabase()->table('comment_mentions')->getSchema(),
            ['user_id'],
        ));
    }

    /** @param list<string> $columns */
    private function foreignKeyOn(AbstractTable $schema, array $columns): AbstractForeignKey|null
    {
        foreach ($schema->getForeignKeys() as $foreignKey) {
            if ($foreignKey->getColumns() === $columns) {
                return $foreignKey;
            }
        }

        return null;
    }
}
