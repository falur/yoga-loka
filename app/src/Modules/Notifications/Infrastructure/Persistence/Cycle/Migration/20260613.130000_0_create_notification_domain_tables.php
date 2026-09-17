<?php

declare(strict_types=1);

namespace Migration;

use Cycle\Migrations\Migration;

class CreateNotificationDomainTables extends Migration
{
    protected const DATABASE = null;

    public function up(): void
    {
        $this->table('notifications')
            ->addColumn('id', 'uuid', ['nullable' => false])
            ->addColumn('outbox_id', 'uuid', ['nullable' => false])
            ->addColumn('user_id', 'uuid', ['nullable' => false])
            ->addColumn('type', 'string', ['length' => 255, 'nullable' => false])
            ->addColumn('title', 'string', ['length' => 255, 'nullable' => false])
            ->addColumn('body', 'text', ['nullable' => false])
            ->addColumn('action_type', 'string', ['length' => 255, 'nullable' => true])
            ->addColumn('action_id', 'string', ['length' => 255, 'nullable' => true])
            ->addColumn('actor', 'json', ['nullable' => true])
            ->addColumn('read_at', 'datetime', ['nullable' => true])
            ->addColumn('created_at', 'datetime', ['nullable' => false])
            ->addColumn('updated_at', 'datetime', ['nullable' => false])
            ->setPrimaryKeys(['id'])
            ->addIndex(['outbox_id'], ['unique' => true])
            ->addIndex(['user_id', 'id'])
            ->addIndex(['user_id', 'read_at'])
            ->create();

        $this->table('notification_settings')
            ->addColumn('id', 'uuid', ['nullable' => false])
            ->addColumn('user_id', 'uuid', ['nullable' => false])
            ->addColumn('type', 'string', ['length' => 255, 'nullable' => false])
            ->addColumn('channel', 'string', ['length' => 32, 'nullable' => false])
            ->addColumn('enabled', 'boolean', ['nullable' => false])
            ->addColumn('created_at', 'datetime', ['nullable' => false])
            ->addColumn('updated_at', 'datetime', ['nullable' => false])
            ->setPrimaryKeys(['id'])
            ->addIndex(['user_id', 'type', 'channel'], ['unique' => true])
            ->create();

        $this->table('notification_device_tokens')
            ->addColumn('id', 'uuid', ['nullable' => false])
            ->addColumn('user_id', 'uuid', ['nullable' => false])
            ->addColumn('token', 'string', ['length' => 1024, 'nullable' => false])
            ->addColumn('platform', 'string', ['length' => 32, 'nullable' => false])
            ->addColumn('created_at', 'datetime', ['nullable' => false])
            ->addColumn('updated_at', 'datetime', ['nullable' => false])
            ->setPrimaryKeys(['id'])
            ->addIndex(['token'], ['unique' => true])
            ->addIndex(['user_id'])
            ->create();
    }

    public function down(): void
    {
        $this->table('notification_device_tokens')->drop();
        $this->table('notification_settings')->drop();
        $this->table('notifications')->drop();
    }
}
