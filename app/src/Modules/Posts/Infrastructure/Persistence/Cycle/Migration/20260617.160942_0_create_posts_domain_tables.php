<?php

declare(strict_types=1);

namespace Migration;

use Cycle\Migrations\Migration;

class CreatePostsDomainTables extends Migration
{
    protected const DATABASE = null;

    public function up(): void
    {
        $this->table('tags')
            ->addColumn('id', 'uuid', ['nullable' => false])
            // Ширина 64 — намеренный запас над доменным максимумом TagText (50 символов):
            // VO остаётся строже схемы, а небольшой запас оставляет место для будущего смягчения длины.
            ->addColumn('text', 'string', ['length' => 64, 'nullable' => false])
            ->addColumn('created_by_id', 'uuid', ['nullable' => false])
            ->addColumn('created_at', 'datetime', ['nullable' => false])
            ->addColumn('updated_at', 'datetime', ['nullable' => false])
            ->setPrimaryKeys(['id'])
            ->addIndex(['text'], ['unique' => true])
            ->addForeignKey(
                ['created_by_id'],
                'users',
                ['id'],
                ['delete' => 'RESTRICT', 'update' => 'CASCADE', 'indexCreate' => false],
            )
            ->create();

        $this->table('posts')
            ->addColumn('id', 'uuid', ['nullable' => false])
            ->addColumn('user_id', 'uuid', ['nullable' => false])
            ->addColumn('text', 'text', ['nullable' => true])
            ->addColumn('status', 'string', ['length' => 32, 'nullable' => false])
            ->addColumn('attachment_type', 'string', ['length' => 16, 'nullable' => false])
            ->addColumn('lesson_id', 'uuid', ['nullable' => true])
            ->addColumn('practice_id', 'uuid', ['nullable' => true])
            ->addColumn('parent_post_id', 'uuid', ['nullable' => true])
            ->addColumn('likes_count', 'integer', ['nullable' => false, 'default' => 0])
            ->addColumn('reposts_count', 'integer', ['nullable' => false, 'default' => 0])
            ->addColumn('comments_count', 'integer', ['nullable' => false, 'default' => 0])
            ->addColumn('deleted_at', 'datetime', ['nullable' => true])
            ->addColumn('created_at', 'datetime', ['nullable' => false])
            ->addColumn('updated_at', 'datetime', ['nullable' => false])
            ->setPrimaryKeys(['id'])
            ->addIndex(['user_id', 'id'])
            ->addIndex(['status'])
            ->addIndex(['parent_post_id'])
            ->addForeignKey(
                ['user_id'],
                'users',
                ['id'],
                ['delete' => 'RESTRICT', 'update' => 'CASCADE', 'indexCreate' => false],
            )
            ->create();

        // Self-FK добавляем отдельным ALTER после ->create(): индекс parent_post_id уже создан выше,
        // поэтому 'indexCreate' => false, иначе появился бы дублирующий индекс.
        $this->table('posts')
            ->addForeignKey(
                ['parent_post_id'],
                'posts',
                ['id'],
                ['delete' => 'SET NULL', 'update' => 'CASCADE', 'indexCreate' => false],
            )
            ->update();

        $this->table('post_media')
            ->addColumn('id', 'uuid', ['nullable' => false])
            ->addColumn('post_id', 'uuid', ['nullable' => false])
            ->addColumn('media_id', 'uuid', ['nullable' => false])
            ->addColumn('position', 'integer', ['nullable' => false])
            ->addColumn('created_at', 'datetime', ['nullable' => false])
            ->addColumn('updated_at', 'datetime', ['nullable' => false])
            ->setPrimaryKeys(['id'])
            ->addIndex(['post_id', 'media_id'], ['unique' => true])
            ->addIndex(['post_id', 'position'])
            ->addForeignKey(
                ['post_id'],
                'posts',
                ['id'],
                ['delete' => 'CASCADE', 'update' => 'CASCADE', 'indexCreate' => false],
            )
            ->addForeignKey(
                ['media_id'],
                'media',
                ['id'],
                ['delete' => 'RESTRICT', 'update' => 'CASCADE', 'indexCreate' => false],
            )
            ->create();

        $this->table('post_likes')
            ->addColumn('id', 'uuid', ['nullable' => false])
            ->addColumn('post_id', 'uuid', ['nullable' => false])
            ->addColumn('user_id', 'uuid', ['nullable' => false])
            ->addColumn('created_at', 'datetime', ['nullable' => false])
            ->addColumn('updated_at', 'datetime', ['nullable' => false])
            ->setPrimaryKeys(['id'])
            ->addIndex(['post_id', 'user_id'], ['unique' => true])
            ->addIndex(['user_id'])
            ->addForeignKey(
                ['post_id'],
                'posts',
                ['id'],
                ['delete' => 'CASCADE', 'update' => 'CASCADE', 'indexCreate' => false],
            )
            ->addForeignKey(
                ['user_id'],
                'users',
                ['id'],
                ['delete' => 'CASCADE', 'update' => 'CASCADE', 'indexCreate' => false],
            )
            ->create();

        $this->table('post_mentions')
            ->addColumn('id', 'uuid', ['nullable' => false])
            ->addColumn('post_id', 'uuid', ['nullable' => false])
            ->addColumn('user_id', 'uuid', ['nullable' => false])
            ->addColumn('created_at', 'datetime', ['nullable' => false])
            ->addColumn('updated_at', 'datetime', ['nullable' => false])
            ->setPrimaryKeys(['id'])
            ->addIndex(['post_id', 'user_id'], ['unique' => true])
            ->addIndex(['user_id'])
            ->addForeignKey(
                ['post_id'],
                'posts',
                ['id'],
                ['delete' => 'CASCADE', 'update' => 'CASCADE', 'indexCreate' => false],
            )
            ->addForeignKey(
                ['user_id'],
                'users',
                ['id'],
                ['delete' => 'CASCADE', 'update' => 'CASCADE', 'indexCreate' => false],
            )
            ->create();

        $this->table('post_tags')
            ->addColumn('id', 'uuid', ['nullable' => false])
            ->addColumn('post_id', 'uuid', ['nullable' => false])
            ->addColumn('tag_id', 'uuid', ['nullable' => false])
            ->addColumn('created_at', 'datetime', ['nullable' => false])
            ->addColumn('updated_at', 'datetime', ['nullable' => false])
            ->setPrimaryKeys(['id'])
            ->addIndex(['post_id', 'tag_id'], ['unique' => true])
            ->addIndex(['tag_id'])
            ->addForeignKey(
                ['post_id'],
                'posts',
                ['id'],
                ['delete' => 'CASCADE', 'update' => 'CASCADE', 'indexCreate' => false],
            )
            ->addForeignKey(
                ['tag_id'],
                'tags',
                ['id'],
                ['delete' => 'RESTRICT', 'update' => 'CASCADE', 'indexCreate' => false],
            )
            ->create();

        $this->table('post_blocks')
            ->addColumn('id', 'uuid', ['nullable' => false])
            ->addColumn('post_id', 'uuid', ['nullable' => false])
            ->addColumn('reason', 'string', ['length' => 500, 'nullable' => false])
            ->addColumn('blocked_by_id', 'uuid', ['nullable' => false])
            ->addColumn('unblocked_at', 'datetime', ['nullable' => true])
            ->addColumn('unblocked_by_id', 'uuid', ['nullable' => true])
            ->addColumn('unblocked_reason', 'string', ['length' => 500, 'nullable' => true])
            ->addColumn('created_at', 'datetime', ['nullable' => false])
            ->addColumn('updated_at', 'datetime', ['nullable' => false])
            ->setPrimaryKeys(['id'])
            ->addIndex(['post_id'])
            ->addForeignKey(
                ['post_id'],
                'posts',
                ['id'],
                ['delete' => 'CASCADE', 'update' => 'CASCADE', 'indexCreate' => false],
            )
            ->addForeignKey(
                ['blocked_by_id'],
                'users',
                ['id'],
                ['delete' => 'RESTRICT', 'update' => 'CASCADE', 'indexCreate' => false],
            )
            ->addForeignKey(
                ['unblocked_by_id'],
                'users',
                ['id'],
                ['delete' => 'SET NULL', 'update' => 'CASCADE', 'indexCreate' => false],
            )
            ->create();

        $this->table('comments')
            ->addColumn('id', 'uuid', ['nullable' => false])
            ->addColumn('post_id', 'uuid', ['nullable' => false])
            ->addColumn('user_id', 'uuid', ['nullable' => false])
            ->addColumn('text', 'text', ['nullable' => false])
            ->addColumn('parent_comment_id', 'uuid', ['nullable' => true])
            ->addColumn('likes_count', 'integer', ['nullable' => false, 'default' => 0])
            ->addColumn('replies_count', 'integer', ['nullable' => false, 'default' => 0])
            ->addColumn('deleted_at', 'datetime', ['nullable' => true])
            ->addColumn('deleted_by_id', 'uuid', ['nullable' => true])
            ->addColumn('deletion_reason', 'string', ['length' => 500, 'nullable' => true])
            ->addColumn('created_at', 'datetime', ['nullable' => false])
            ->addColumn('updated_at', 'datetime', ['nullable' => false])
            ->setPrimaryKeys(['id'])
            ->addIndex(['post_id', 'id'])
            ->addIndex(['parent_comment_id'])
            ->addIndex(['user_id'])
            ->addForeignKey(
                ['post_id'],
                'posts',
                ['id'],
                ['delete' => 'CASCADE', 'update' => 'CASCADE', 'indexCreate' => false],
            )
            ->addForeignKey(
                ['user_id'],
                'users',
                ['id'],
                ['delete' => 'RESTRICT', 'update' => 'CASCADE', 'indexCreate' => false],
            )
            ->addForeignKey(
                ['deleted_by_id'],
                'users',
                ['id'],
                ['delete' => 'SET NULL', 'update' => 'CASCADE', 'indexCreate' => false],
            )
            ->create();

        // Self-FK дерева комментариев — отдельным ALTER, индекс parent_comment_id уже создан выше.
        $this->table('comments')
            ->addForeignKey(
                ['parent_comment_id'],
                'comments',
                ['id'],
                ['delete' => 'SET NULL', 'update' => 'CASCADE', 'indexCreate' => false],
            )
            ->update();

        $this->table('comment_likes')
            ->addColumn('id', 'uuid', ['nullable' => false])
            ->addColumn('comment_id', 'uuid', ['nullable' => false])
            ->addColumn('user_id', 'uuid', ['nullable' => false])
            ->addColumn('created_at', 'datetime', ['nullable' => false])
            ->addColumn('updated_at', 'datetime', ['nullable' => false])
            ->setPrimaryKeys(['id'])
            ->addIndex(['comment_id', 'user_id'], ['unique' => true])
            ->addIndex(['user_id'])
            ->addForeignKey(
                ['comment_id'],
                'comments',
                ['id'],
                ['delete' => 'CASCADE', 'update' => 'CASCADE', 'indexCreate' => false],
            )
            ->addForeignKey(
                ['user_id'],
                'users',
                ['id'],
                ['delete' => 'CASCADE', 'update' => 'CASCADE', 'indexCreate' => false],
            )
            ->create();

        $this->table('comment_mentions')
            ->addColumn('id', 'uuid', ['nullable' => false])
            ->addColumn('comment_id', 'uuid', ['nullable' => false])
            ->addColumn('user_id', 'uuid', ['nullable' => false])
            ->addColumn('created_at', 'datetime', ['nullable' => false])
            ->addColumn('updated_at', 'datetime', ['nullable' => false])
            ->setPrimaryKeys(['id'])
            ->addIndex(['comment_id', 'user_id'], ['unique' => true])
            ->addIndex(['user_id'])
            ->addForeignKey(
                ['comment_id'],
                'comments',
                ['id'],
                ['delete' => 'CASCADE', 'update' => 'CASCADE', 'indexCreate' => false],
            )
            ->addForeignKey(
                ['user_id'],
                'users',
                ['id'],
                ['delete' => 'CASCADE', 'update' => 'CASCADE', 'indexCreate' => false],
            )
            ->create();
    }

    public function down(): void
    {
        $this->table('comment_mentions')->drop();
        $this->table('comment_likes')->drop();
        $this->table('comments')->drop();
        $this->table('post_blocks')->drop();
        $this->table('post_tags')->drop();
        $this->table('post_mentions')->drop();
        $this->table('post_likes')->drop();
        $this->table('post_media')->drop();
        $this->table('tags')->drop();
        $this->table('posts')->drop();
    }
}
