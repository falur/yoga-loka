<?php

declare(strict_types=1);

namespace Migration;

use Cycle\Migrations\Migration;

class CreateAccessDomainTables extends Migration
{
    protected const DATABASE = null;

    public function up(): void
    {
        $this->table('roles')
            ->addColumn('id', 'uuid', ['nullable' => false])
            ->addColumn('slug', 'string', ['length' => 64, 'nullable' => false])
            ->addColumn('created_at', 'datetime', ['nullable' => false])
            ->addColumn('updated_at', 'datetime', ['nullable' => false])
            ->setPrimaryKeys(['id'])
            ->addIndex(['slug'], ['unique' => true])
            ->create();

        $this->table('permissions')
            ->addColumn('id', 'uuid', ['nullable' => false])
            ->addColumn('slug', 'string', ['length' => 64, 'nullable' => false])
            ->addColumn('created_at', 'datetime', ['nullable' => false])
            ->addColumn('updated_at', 'datetime', ['nullable' => false])
            ->setPrimaryKeys(['id'])
            ->addIndex(['slug'], ['unique' => true])
            ->create();

        $this->table('role_permissions')
            ->addColumn('id', 'uuid', ['nullable' => false])
            ->addColumn('role_id', 'uuid', ['nullable' => false])
            ->addColumn('permission_id', 'uuid', ['nullable' => false])
            ->setPrimaryKeys(['id'])
            ->addForeignKey(
                ['role_id'],
                'roles',
                ['id'],
                ['delete' => 'CASCADE', 'update' => 'CASCADE', 'indexCreate' => false],
            )
            ->addForeignKey(
                ['permission_id'],
                'permissions',
                ['id'],
                ['delete' => 'CASCADE', 'update' => 'CASCADE', 'indexCreate' => false],
            )
            ->addIndex(['role_id', 'permission_id'], ['unique' => true])
            ->create();

        $this->table('user_roles')
            ->addColumn('id', 'uuid', ['nullable' => false])
            ->addColumn('user_id', 'uuid', ['nullable' => false])
            ->addColumn('role_id', 'uuid', ['nullable' => false])
            ->setPrimaryKeys(['id'])
            ->addForeignKey(
                ['user_id'],
                'users',
                ['id'],
                ['delete' => 'CASCADE', 'update' => 'CASCADE', 'indexCreate' => false],
            )
            ->addForeignKey(
                ['role_id'],
                'roles',
                ['id'],
                ['delete' => 'CASCADE', 'update' => 'CASCADE', 'indexCreate' => false],
            )
            ->addIndex(['user_id', 'role_id'], ['unique' => true])
            ->create();
    }

    public function down(): void
    {
        $this->table('user_roles')->drop();
        $this->table('role_permissions')->drop();
        $this->table('permissions')->drop();
        $this->table('roles')->drop();
    }
}
