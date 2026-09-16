<?php

declare(strict_types=1);

namespace App\Modules\Posts\Infrastructure\Persistence\Cycle\Columns;

final class PostMediaColumns
{
    public const string TABLE = 'post_media';

    public const string ID = 'id';
    public const string POST_ID = 'post_id';
    public const string MEDIA_ID = 'media_id';
    public const string POSITION = 'position';

    private function __construct() {}
}
