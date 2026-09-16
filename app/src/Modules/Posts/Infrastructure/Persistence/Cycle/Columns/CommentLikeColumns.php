<?php

declare(strict_types=1);

namespace App\Modules\Posts\Infrastructure\Persistence\Cycle\Columns;

final class CommentLikeColumns
{
    public const string TABLE = 'comment_likes';

    public const string ID = 'id';
    public const string COMMENT_ID = 'comment_id';
    public const string USER_ID = 'user_id';

    private function __construct() {}
}
