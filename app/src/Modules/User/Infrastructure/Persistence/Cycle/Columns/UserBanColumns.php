<?php

declare(strict_types=1);

namespace App\Modules\User\Infrastructure\Persistence\Cycle\Columns;

final class UserBanColumns
{
    public const string TABLE = 'user_bans';

    public const string ID = 'id';
    public const string USER_ID = 'user_id';
    public const string BANNED_BY_ID = 'banned_by_id';
    public const string REASON = 'reason';
    public const string EXPIRES_AT = 'expires_at';
    public const string UNBANNED_AT = 'unbanned_at';
    public const string UNBANNED_BY_ID = 'unbanned_by_id';
    public const string UNBANNED_REASON = 'unbanned_reason';

    private function __construct() {}
}
