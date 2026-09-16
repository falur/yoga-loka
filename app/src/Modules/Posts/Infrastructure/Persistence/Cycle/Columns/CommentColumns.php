<?php

declare(strict_types=1);

namespace App\Modules\Posts\Infrastructure\Persistence\Cycle\Columns;

final class CommentColumns
{
    public const string TABLE = 'comments';

    public const string ID = 'id';
    public const string POST_ID = 'post_id';
    public const string USER_ID = 'user_id';
    public const string TEXT = 'text';
    public const string PARENT_COMMENT_ID = 'parent_comment_id';
    public const string LIKES_COUNT = 'likes_count';
    public const string REPLIES_COUNT = 'replies_count';
    public const string DELETED_AT = 'deleted_at';
    public const string DELETED_BY_ID = 'deleted_by_id';
    public const string DELETION_REASON = 'deletion_reason';

    private function __construct() {}
}
