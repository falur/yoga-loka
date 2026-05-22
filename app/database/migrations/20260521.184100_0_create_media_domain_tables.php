<?php

declare(strict_types=1);

namespace Migration;

use Cycle\Migrations\Migration;

class CreateMediaDomainTables extends Migration
{
    protected const DATABASE = null;

    public function up(): void
    {
        $this->table('media')
            ->addColumn('id', 'uuid', ['nullable' => false])
            ->addColumn('storage_key', 'uuid', ['nullable' => false])
            ->addColumn('type', 'string', ['length' => 32, 'nullable' => false])
            ->addColumn('status', 'string', ['length' => 64, 'nullable' => false])
            ->addColumn('visibility', 'string', ['length' => 16, 'nullable' => false])
            ->addColumn('storage', 'string', ['length' => 64, 'nullable' => false])
            ->addColumn('path', 'string', ['length' => 1024, 'nullable' => false])
            ->addColumn('mime_type', 'string', ['length' => 255, 'nullable' => false])
            ->addColumn('size', 'bigInteger', ['nullable' => false])
            ->addColumn('uploaded_by_id', 'uuid', ['nullable' => false])
            ->addColumn('expires_at', 'datetime', ['nullable' => true])
            ->addColumn('processing_attempts', 'integer', ['nullable' => false, 'default' => 0])
            ->addColumn('processing_error', 'text', ['nullable' => true])
            ->addColumn('created_at', 'datetime', ['nullable' => false])
            ->addColumn('updated_at', 'datetime', ['nullable' => false])
            ->setPrimaryKeys(['id'])
            ->addIndex(['storage_key'], ['unique' => true])
            ->addIndex(['uploaded_by_id'])
            ->addIndex(['status'])
            ->addIndex(['expires_at'])
            ->create();

        $this->table('media_image_conversions')
            ->addColumn('id', 'uuid', ['nullable' => false])
            ->addColumn('media_id', 'uuid', ['nullable' => false])
            ->addColumn('type', 'string', ['length' => 64, 'nullable' => false])
            ->addColumn('status', 'string', ['length' => 32, 'nullable' => false])
            ->addColumn('storage', 'string', ['length' => 64, 'nullable' => false])
            ->addColumn('path', 'string', ['length' => 1024, 'nullable' => false])
            ->addColumn('mime_type', 'string', ['length' => 255, 'nullable' => false])
            ->addColumn('size', 'bigInteger', ['nullable' => false])
            ->addColumn('width', 'integer', ['nullable' => false])
            ->addColumn('height', 'integer', ['nullable' => false])
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

        $this->table('media_video_conversions')
            ->addColumn('id', 'uuid', ['nullable' => false])
            ->addColumn('media_id', 'uuid', ['nullable' => false])
            ->addColumn('type', 'string', ['length' => 64, 'nullable' => false])
            ->addColumn('status', 'string', ['length' => 32, 'nullable' => false])
            ->addColumn('storage', 'string', ['length' => 64, 'nullable' => false])
            ->addColumn('path', 'string', ['length' => 1024, 'nullable' => false])
            ->addColumn('mime_type', 'string', ['length' => 255, 'nullable' => false])
            ->addColumn('size', 'bigInteger', ['nullable' => false])
            ->addColumn('width', 'integer', ['nullable' => false])
            ->addColumn('height', 'integer', ['nullable' => false])
            ->addColumn('duration_ms', 'bigInteger', ['nullable' => false])
            ->addColumn('bitrate', 'integer', ['nullable' => false])
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

        $this->table('media_multipart_uploads')
            ->addColumn('id', 'uuid', ['nullable' => false])
            ->addColumn('media_id', 'uuid', ['nullable' => false])
            ->addColumn('upload_id', 'string', ['length' => 1024, 'nullable' => false])
            ->addColumn('parts_count', 'integer', ['nullable' => false])
            ->addColumn('part_size', 'bigInteger', ['nullable' => false])
            ->addColumn('file_size', 'bigInteger', ['nullable' => false])
            ->addColumn('parts', 'json', ['nullable' => false])
            ->addColumn('created_at', 'datetime', ['nullable' => false])
            ->addColumn('updated_at', 'datetime', ['nullable' => false])
            ->setPrimaryKeys(['id'])
            ->addForeignKey(
                ['media_id'],
                'media',
                ['id'],
                ['delete' => 'CASCADE', 'update' => 'CASCADE', 'indexCreate' => false],
            )
            ->addIndex(['media_id'], ['unique' => true])
            ->addIndex(['upload_id'])
            ->create();
    }

    public function down(): void
    {
        $this->table('media_multipart_uploads')->drop();
        $this->table('media_video_conversions')->drop();
        $this->table('media_image_conversions')->drop();
        $this->table('media')->drop();
    }
}
