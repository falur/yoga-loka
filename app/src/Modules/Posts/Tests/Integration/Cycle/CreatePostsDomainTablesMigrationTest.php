<?php

declare(strict_types=1);

namespace App\Modules\Posts\Tests\Integration\Cycle;

use Cycle\Database\Schema\AbstractForeignKey;
use Cycle\Database\Schema\AbstractTable;
use Tests\DatabaseTestCase;
use Tests\Support\Migration\ReplaysMigration;

/**
 * Реальное исполнение уже применённой миграции девяти таблиц Posts и одной — `tags`.
 *
 * Файл лежит в Posts (исторический артефакт, см. docblock `PostsBootloader`): девять из десяти
 * созданных им таблиц принадлежат Posts, редактировать уже применённое содержимое нельзя
 * (`docs/rules.md`). `migrate-test-databases.sh` применяет его один раз до PHPUnit/PCOV, поэтому без
 * прямого вызова down()/up() тело миграции остаётся непокрытым. Тест откатывает и повторно
 * накатывает таблицы внутри транзакции `DatabaseTestCase`, которая откатывается в tearDown —
 * временное воссоздание межмодульных внешних ключей на `users`/`media` (снятых отдельными
 * миграциями волны G, не затронутыми этим тестом) не оставляет следа на общей тестовой базе.
 */
final class CreatePostsDomainTablesMigrationTest extends DatabaseTestCase
{
    use ReplaysMigration;

    private const string MIGRATION_CLASS = 'Migration\\CreatePostsDomainTables';
    private const string MIGRATION_FILE = '/app/src/Modules/Posts/Infrastructure/Persistence/Cycle/Migration/20260617.160942_0_create_posts_domain_tables.php';

    private const array TABLES = [
        'tags',
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

    public function testDownDropsAllTenTablesAndUpRecreatesThemWithForeignKeys(): void
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

        $tags = $this->migrationDatabase()->table('tags')->getSchema();
        self::assertSame(['id'], $tags->getPrimaryKeys());
        self::assertNotNull($this->foreignKeyOn($tags, ['created_by_id']));

        $posts = $this->migrationDatabase()->table('posts')->getSchema();
        self::assertNotNull($this->foreignKeyOn($posts, ['user_id']));
        $selfForeignKey = $this->foreignKeyOn($posts, ['parent_post_id']);
        self::assertNotNull($selfForeignKey);
        self::assertSame('posts', $selfForeignKey->getForeignTable());

        $postTags = $this->migrationDatabase()->table('post_tags')->getSchema();
        $tagForeignKey = $this->foreignKeyOn($postTags, ['tag_id']);
        self::assertNotNull($tagForeignKey);
        self::assertSame('tags', $tagForeignKey->getForeignTable());

        $postBlocks = $this->migrationDatabase()->table('post_blocks')->getSchema();
        self::assertNotNull($this->foreignKeyOn($postBlocks, ['blocked_by_id']));
        $unblockedForeignKey = $this->foreignKeyOn($postBlocks, ['unblocked_by_id']);
        self::assertNotNull($unblockedForeignKey);
        self::assertSame('SET NULL', $unblockedForeignKey->getDeleteRule());

        $comments = $this->migrationDatabase()->table('comments')->getSchema();
        self::assertNotNull($this->foreignKeyOn($comments, ['user_id']));
        $parentCommentForeignKey = $this->foreignKeyOn($comments, ['parent_comment_id']);
        self::assertNotNull($parentCommentForeignKey);
        self::assertSame('comments', $parentCommentForeignKey->getForeignTable());

        self::assertNotNull($this->foreignKeyOn(
            $this->migrationDatabase()->table('comment_likes')->getSchema(),
            ['user_id'],
        ));
        self::assertNotNull($this->foreignKeyOn(
            $this->migrationDatabase()->table('comment_mentions')->getSchema(),
            ['user_id'],
        ));
        self::assertNotNull($this->foreignKeyOn(
            $this->migrationDatabase()->table('post_media')->getSchema(),
            ['media_id'],
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
