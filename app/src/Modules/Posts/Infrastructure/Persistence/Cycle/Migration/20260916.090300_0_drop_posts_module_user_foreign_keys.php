<?php

declare(strict_types=1);

namespace App\Modules\Posts\Infrastructure\Persistence\Cycle\Migration;

use Cycle\Migrations\Migration;

/**
 * Снимает десять межмодульных внешних ключей таблиц модуля Posts на users.id и один — на tags.id.
 *
 * Таблицы users и tags принадлежат модулям User и Tags соответственно, все изменённые здесь таблицы —
 * модулю Posts, а межмодульные внешние ключи не используются как основа согласованности (docs/arch.md,
 * «Владение данными»). В кодовой базе нет ни одного сценария, который жёстко удаляет строку users или
 * tags (`UserRepository` не имеет метода `delete`, в модуле Tags нет ни одной команды удаления), поэтому
 * правила RESTRICT/CASCADE/SET NULL этих ключей на практике никогда не срабатывали.
 *
 * Снимаемые ключи:
 * - post_tags.tag_id -> tags.id (RESTRICT/CASCADE)
 * - posts.user_id -> users.id (RESTRICT/CASCADE)
 * - post_likes.user_id -> users.id (CASCADE/CASCADE)
 * - post_mentions.user_id -> users.id (CASCADE/CASCADE)
 * - post_blocks.blocked_by_id -> users.id (RESTRICT/CASCADE)
 * - post_blocks.unblocked_by_id -> users.id (SET NULL/CASCADE)
 * - comments.user_id -> users.id (RESTRICT/CASCADE)
 * - comments.deleted_by_id -> users.id (SET NULL/CASCADE)
 * - comment_likes.user_id -> users.id (CASCADE/CASCADE)
 * - comment_mentions.user_id -> users.id (CASCADE/CASCADE)
 *
 * Данные не меняются: снимаются только ограничения. Остальные колонки, внутримодульные внешние ключи
 * (post_tags.post_id, posts.parent_post_id, post_likes.post_id, post_mentions.post_id,
 * post_blocks.post_id, comments.post_id, comments.parent_comment_id, comment_likes.comment_id,
 * comment_mentions.comment_id) и все индексы остаются как есть. Откат возвращает ключи с прежними
 * правилами.
 */
class DropPostsUserForeignKeys extends Migration
{
    protected const DATABASE = null;

    public function up(): void
    {
        $this->table('post_tags')
            ->dropForeignKey(columns: ['tag_id'])
            ->update();

        $this->table('posts')
            ->dropForeignKey(columns: ['user_id'])
            ->update();

        $this->table('post_likes')
            ->dropForeignKey(columns: ['user_id'])
            ->update();

        $this->table('post_mentions')
            ->dropForeignKey(columns: ['user_id'])
            ->update();

        $this->table('post_blocks')
            ->dropForeignKey(columns: ['blocked_by_id'])
            ->dropForeignKey(columns: ['unblocked_by_id'])
            ->update();

        $this->table('comments')
            ->dropForeignKey(columns: ['user_id'])
            ->dropForeignKey(columns: ['deleted_by_id'])
            ->update();

        $this->table('comment_likes')
            ->dropForeignKey(columns: ['user_id'])
            ->update();

        $this->table('comment_mentions')
            ->dropForeignKey(columns: ['user_id'])
            ->update();
    }

    public function down(): void
    {
        // indexCreate: false во всех вызовах ниже — как и в исходной миграции: отдельные индексы под
        // эти колонки ключом не заводились, кроме comments.user_id, где отдельный индекс уже существует.
        $this->table('comment_mentions')
            ->addForeignKey(
                columns: ['user_id'],
                foreignTable: 'users',
                foreignKeys: ['id'],
                options: ['delete' => 'CASCADE', 'update' => 'CASCADE', 'indexCreate' => false],
            )
            ->update();

        $this->table('comment_likes')
            ->addForeignKey(
                columns: ['user_id'],
                foreignTable: 'users',
                foreignKeys: ['id'],
                options: ['delete' => 'CASCADE', 'update' => 'CASCADE', 'indexCreate' => false],
            )
            ->update();

        $this->table('comments')
            ->addForeignKey(
                columns: ['deleted_by_id'],
                foreignTable: 'users',
                foreignKeys: ['id'],
                options: ['delete' => 'SET NULL', 'update' => 'CASCADE', 'indexCreate' => false],
            )
            ->addForeignKey(
                columns: ['user_id'],
                foreignTable: 'users',
                foreignKeys: ['id'],
                options: ['delete' => 'RESTRICT', 'update' => 'CASCADE', 'indexCreate' => false],
            )
            ->update();

        $this->table('post_blocks')
            ->addForeignKey(
                columns: ['unblocked_by_id'],
                foreignTable: 'users',
                foreignKeys: ['id'],
                options: ['delete' => 'SET NULL', 'update' => 'CASCADE', 'indexCreate' => false],
            )
            ->addForeignKey(
                columns: ['blocked_by_id'],
                foreignTable: 'users',
                foreignKeys: ['id'],
                options: ['delete' => 'RESTRICT', 'update' => 'CASCADE', 'indexCreate' => false],
            )
            ->update();

        $this->table('post_mentions')
            ->addForeignKey(
                columns: ['user_id'],
                foreignTable: 'users',
                foreignKeys: ['id'],
                options: ['delete' => 'CASCADE', 'update' => 'CASCADE', 'indexCreate' => false],
            )
            ->update();

        $this->table('post_likes')
            ->addForeignKey(
                columns: ['user_id'],
                foreignTable: 'users',
                foreignKeys: ['id'],
                options: ['delete' => 'CASCADE', 'update' => 'CASCADE', 'indexCreate' => false],
            )
            ->update();

        $this->table('posts')
            ->addForeignKey(
                columns: ['user_id'],
                foreignTable: 'users',
                foreignKeys: ['id'],
                options: ['delete' => 'RESTRICT', 'update' => 'CASCADE', 'indexCreate' => false],
            )
            ->update();

        $this->table('post_tags')
            ->addForeignKey(
                columns: ['tag_id'],
                foreignTable: 'tags',
                foreignKeys: ['id'],
                options: ['delete' => 'RESTRICT', 'update' => 'CASCADE', 'indexCreate' => false],
            )
            ->update();
    }
}
