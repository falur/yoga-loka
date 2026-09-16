<?php

declare(strict_types=1);

namespace App\Modules\Posts\Infrastructure\Persistence\Cycle\Columns;

final class PostTagColumns
{
    public const string TABLE = 'post_tags';

    public const string ID = 'id';
    public const string POST_ID = 'post_id';
    public const string TAG_ID = 'tag_id';

    private function __construct() {}
}
