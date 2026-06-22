<?php

declare(strict_types=1);

namespace Migration;

use Cycle\Migrations\Migration;

class CreateMediaAudioConversionsTable extends Migration
{
    protected const DATABASE = null;

    public function up(): void
    {
        $this->table('media_audio_conversions')
            ->addColumn('id', 'uuid', ['nullable' => false])
            ->addColumn('media_id', 'uuid', ['nullable' => false])
            ->addColumn('type', 'string', ['length' => 64, 'nullable' => false])
            ->addColumn('status', 'string', ['length' => 32, 'nullable' => false])
            ->addColumn('storage', 'string', ['length' => 64, 'nullable' => false])
            ->addColumn('path', 'string', ['length' => 1024, 'nullable' => false])
            ->addColumn('mime_type', 'string', ['length' => 255, 'nullable' => false])
            ->addColumn('size', 'bigInteger', ['nullable' => false])
            ->addColumn('duration_ms', 'bigInteger', ['nullable' => false])
            ->addColumn('bitrate', 'integer', ['nullable' => false])
            ->addColumn('sample_rate', 'integer', ['nullable' => false])
            ->addColumn('waveform', 'json', ['nullable' => false])
            ->addColumn('created_at', 'datetime', ['nullable' => false])
            ->addColumn('updated_at', 'datetime', ['nullable' => false])
            ->setPrimaryKeys(['id'])
            ->addForeignKey(
                ['media_id'],
                'media',
                ['id'],
                ['delete' => 'CASCADE', 'update' => 'CASCADE', 'indexCreate' => false],
            )
            ->addIndex(['media_id', 'type'], ['unique' => true])
            ->addIndex(['media_id'])
            ->addIndex(['status'])
            ->create();
    }

    public function down(): void
    {
        $this->table('media_audio_conversions')->drop();
    }
}
