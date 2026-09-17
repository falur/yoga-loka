<?php

declare(strict_types=1);

namespace Migration;

use Cycle\Migrations\Migration;

class CreateOutboxEventsTable extends Migration
{
    protected const DATABASE = null;

    public function up(): void
    {
        $this->table('outbox_events')
            ->addColumn('id', 'uuid', ['nullable' => false])
            ->addColumn('type', 'string', ['length' => 255, 'nullable' => false])
            ->addColumn('payload', 'jsonb', ['nullable' => false])
            ->addColumn('status', 'string', ['length' => 32, 'nullable' => false, 'default' => 'pending'])
            ->addColumn('attempts', 'integer', ['nullable' => false, 'default' => 0])
            ->addColumn('available_at', 'datetime', ['nullable' => false])
            ->addColumn('queued_at', 'datetime', ['nullable' => true])
            ->addColumn('handled_at', 'datetime', ['nullable' => true])
            ->addColumn('failed_at', 'datetime', ['nullable' => true])
            ->addColumn('last_error', 'text', ['nullable' => true])
            ->addColumn('created_at', 'datetime', ['nullable' => false])
            ->addColumn('updated_at', 'datetime', ['nullable' => false])
            ->setPrimaryKeys(['id'])
            ->addIndex(['status', 'available_at', 'id'])
            ->addIndex(['type'])
            ->addIndex(['queued_at'])
            ->addIndex(['failed_at'])
            ->create();
    }

    public function down(): void
    {
        $this->table('outbox_events')->drop();
    }
}
