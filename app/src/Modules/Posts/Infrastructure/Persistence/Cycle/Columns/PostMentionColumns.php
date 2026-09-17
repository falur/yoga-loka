<?php

declare(strict_types=1);

namespace App\Modules\Posts\Infrastructure\Persistence\Cycle\Columns;

final class PostMentionColumns
{
    public const string TABLE = 'post_mentions';

    public const string ID = 'id';
    public const string POST_ID = 'post_id';
    public const string USER_ID = 'user_id';

    private function __construct() {}
}
