<?php

declare(strict_types=1);

namespace App\Modules\Posts\Infrastructure\Persistence\Cycle\Columns;

final class PostBlockColumns
{
    public const string TABLE = 'post_blocks';

    public const string ID = 'id';
    public const string POST_ID = 'post_id';
    public const string REASON = 'reason';
    public const string BLOCKED_BY_ID = 'blocked_by_id';
    public const string UNBLOCKED_AT = 'unblocked_at';
    public const string UNBLOCKED_BY_ID = 'unblocked_by_id';
    public const string UNBLOCKED_REASON = 'unblocked_reason';

    private function __construct() {}
}
