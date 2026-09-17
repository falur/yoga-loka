<?php

declare(strict_types=1);

namespace App\Modules\Media\Infrastructure\Persistence\Cycle\Columns;

final class MediaMultipartUploadColumns
{
    public const string TABLE = 'media_multipart_uploads';

    public const string ID = 'id';
    public const string MEDIA_ID = 'media_id';
    public const string UPLOAD_ID = 'upload_id';
    public const string PARTS_COUNT = 'parts_count';
    public const string PART_SIZE = 'part_size';
    public const string FILE_SIZE = 'file_size';
    public const string PARTS = 'parts';

    private function __construct() {}
}
