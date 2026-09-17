<?php

declare(strict_types=1);

namespace Migration;

use Cycle\Migrations\Migration;

class CreateAuthDomainTables extends Migration
{
    protected const DATABASE = null;

    public function up(): void
    {
        $this->table('auth_login_codes')
            ->addColumn('id', 'uuid', ['nullable' => false])
            ->addColumn('email', 'string', ['length' => 254, 'nullable' => false])
            ->addColumn('code_hash', 'text', ['nullable' => false])
            ->addColumn('expires_at', 'datetime', ['nullable' => false])
            ->addColumn('attempts', 'integer', ['nullable' => false, 'default' => 0])
            ->addColumn('consumed_at', 'datetime', ['nullable' => true])
            ->addColumn('created_at', 'datetime', ['nullable' => false])
            ->addColumn('updated_at', 'datetime', ['nullable' => false])
            ->setPrimaryKeys(['id'])
            ->addIndex(['email'])
            ->create();

        $this->table('auth_registration_tickets')
            ->addColumn('id', 'uuid', ['nullable' => false])
            ->addColumn('email', 'string', ['length' => 254, 'nullable' => false])
            ->addColumn('ticket_hash', 'text', ['nullable' => false])
            ->addColumn('expires_at', 'datetime', ['nullable' => false])
            ->addColumn('consumed_at', 'datetime', ['nullable' => true])
            ->addColumn('created_at', 'datetime', ['nullable' => false])
            ->addColumn('updated_at', 'datetime', ['nullable' => false])
            ->setPrimaryKeys(['id'])
            ->addIndex(['email'])
            ->create();

        $this->table('auth_tokens')
            ->addColumn('id', 'uuid', ['nullable' => false])
            ->addColumn('user_id', 'uuid', ['nullable' => false])
            ->addColumn('session_id', 'uuid', ['nullable' => false])
            ->addColumn('type', 'string', ['length' => 16, 'nullable' => false])
            ->addColumn('token_hash', 'text', ['nullable' => false])
            ->addColumn('expires_at', 'datetime', ['nullable' => false])
            ->addColumn('created_at', 'datetime', ['nullable' => false])
            ->addColumn('updated_at', 'datetime', ['nullable' => false])
            ->setPrimaryKeys(['id'])
            ->addIndex(['token_hash'], ['unique' => true])
            ->addIndex(['user_id'])
            ->addIndex(['session_id'])
            ->create();
    }

    public function down(): void
    {
        $this->table('auth_tokens')->drop();
        $this->table('auth_registration_tickets')->drop();
        $this->table('auth_login_codes')->drop();
    }
}
