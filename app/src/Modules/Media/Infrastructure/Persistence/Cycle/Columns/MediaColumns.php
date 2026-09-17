<?php

declare(strict_types=1);

namespace App\Modules\Media\Infrastructure\Persistence\Cycle\Columns;

final class MediaColumns
{
    public const string TABLE = 'media';

    public const string ID = 'id';
    public const string STORAGE_KEY = 'storage_key';
    public const string TYPE = 'type';
    public const string STATUS = 'status';
    public const string VISIBILITY = 'visibility';
    public const string STORAGE = 'storage';
    public const string PATH = 'path';
    public const string MIME_TYPE = 'mime_type';
    public const string SIZE = 'size';
    public const string UPLOADED_BY_ID = 'uploaded_by_id';
    public const string EXPIRES_AT = 'expires_at';
    public const string PROCESSING_ATTEMPTS = 'processing_attempts';
    public const string PROCESSING_ERROR = 'processing_error';

    private function __construct() {}
}
