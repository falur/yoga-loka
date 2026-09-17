<?php

declare(strict_types=1);

namespace App\Modules\Posts\Infrastructure\Persistence\Cycle\Columns;

final class PostColumns
{
    public const string TABLE = 'posts';

    public const string ID = 'id';
    public const string USER_ID = 'user_id';
    public const string TEXT = 'text';
    public const string STATUS = 'status';
    public const string ATTACHMENT_TYPE = 'attachment_type';
    public const string LESSON_ID = 'lesson_id';
    public const string PRACTICE_ID = 'practice_id';
    public const string PARENT_POST_ID = 'parent_post_id';
    public const string LIKES_COUNT = 'likes_count';
    public const string REPOSTS_COUNT = 'reposts_count';
    public const string COMMENTS_COUNT = 'comments_count';
    public const string DELETED_AT = 'deleted_at';

    private function __construct() {}
}
