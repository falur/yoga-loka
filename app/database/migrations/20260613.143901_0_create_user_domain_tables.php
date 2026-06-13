<?php

declare(strict_types=1);

namespace Migration;

use Cycle\Migrations\Migration;

class CreateUserDomainTables extends Migration
{
    protected const DATABASE = null;

    public function up(): void
    {
        $this->table('users')
            ->addColumn('id', 'uuid', ['nullable' => false])
            ->addColumn('name', 'string', ['length' => 100, 'nullable' => false])
            ->addColumn('spiritual_name', 'string', ['length' => 100, 'nullable' => true])
            ->addColumn('bio', 'text', ['nullable' => true])
            ->addColumn('location', 'string', ['length' => 100, 'nullable' => true])
            ->addColumn('email', 'string', ['length' => 254, 'nullable' => false])
            ->addColumn('nickname', 'string', ['length' => 30, 'nullable' => false])
            ->addColumn('avatar_media_id', 'uuid', ['nullable' => true])
            ->addColumn('verification', 'string', ['length' => 32, 'nullable' => false])
            ->addColumn('status', 'string', ['length' => 32, 'nullable' => false])
            ->addColumn('locale', 'string', ['length' => 8, 'nullable' => false])
            ->addColumn('deleted_at', 'datetime', ['nullable' => true])
            ->addColumn('created_at', 'datetime', ['nullable' => false])
            ->addColumn('updated_at', 'datetime', ['nullable' => false])
            ->setPrimaryKeys(['id'])
            ->addIndex(['email'], ['unique' => true])
            ->addIndex(['nickname'], ['unique' => true])
            ->addIndex(['status'])
            ->addForeignKey(
                ['avatar_media_id'],
                'media',
                ['id'],
                ['delete' => 'RESTRICT', 'update' => 'CASCADE', 'indexCreate' => false],
            )
            ->create();

        $this->table('user_bans')
            ->addColumn('id', 'uuid', ['nullable' => false])
            ->addColumn('user_id', 'uuid', ['nullable' => false])
            ->addColumn('banned_by_id', 'uuid', ['nullable' => false])
            ->addColumn('reason', 'string', ['length' => 500, 'nullable' => false])
            ->addColumn('expires_at', 'datetime', ['nullable' => true])
            ->addColumn('unbanned_at', 'datetime', ['nullable' => true])
            ->addColumn('unbanned_by_id', 'uuid', ['nullable' => true])
            ->addColumn('unbanned_reason', 'string', ['length' => 500, 'nullable' => true])
            ->addColumn('created_at', 'datetime', ['nullable' => false])
            ->addColumn('updated_at', 'datetime', ['nullable' => false])
            ->setPrimaryKeys(['id'])
            ->addForeignKey(
                ['user_id'],
                'users',
                ['id'],
                ['delete' => 'CASCADE', 'update' => 'CASCADE', 'indexCreate' => false],
            )
            ->addIndex(['user_id'])
            ->create();

        $this->table('reserved_nicknames')
            ->addColumn('id', 'uuid', ['nullable' => false])
            ->addColumn('nickname', 'string', ['length' => 30, 'nullable' => false])
            ->addColumn('assigned_user_id', 'uuid', ['nullable' => true])
            ->addColumn('created_at', 'datetime', ['nullable' => false])
            ->addColumn('updated_at', 'datetime', ['nullable' => false])
            ->setPrimaryKeys(['id'])
            ->addIndex(['nickname'], ['unique' => true])
            ->addForeignKey(
                ['assigned_user_id'],
                'users',
                ['id'],
                ['delete' => 'SET NULL', 'update' => 'CASCADE', 'indexCreate' => false],
            )
            ->addIndex(['assigned_user_id'])
            ->create();
    }

    public function down(): void
    {
        $this->table('reserved_nicknames')->drop();
        $this->table('user_bans')->drop();
        $this->table('users')->drop();
    }
}
