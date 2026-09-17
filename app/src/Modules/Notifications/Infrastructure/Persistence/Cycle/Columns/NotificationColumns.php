<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Infrastructure\Persistence\Cycle\Columns;

final class NotificationColumns
{
    public const string TABLE = 'notifications';

    public const string ID = 'id';
    public const string OUTBOX_ID = 'outbox_id';
    public const string USER_ID = 'user_id';
    public const string TYPE = 'type';
    public const string TITLE = 'title';
    public const string BODY = 'body';
    public const string ACTION_TYPE = 'action_type';
    public const string ACTION_ID = 'action_id';
    public const string ACTOR = 'actor';
    public const string READ_AT = 'read_at';

    private function __construct() {}
}
