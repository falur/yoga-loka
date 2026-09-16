<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Infrastructure\Persistence\Cycle\Columns;

final class NotificationSettingColumns
{
    public const string TABLE = 'notification_settings';

    public const string ID = 'id';
    public const string USER_ID = 'user_id';
    public const string TYPE = 'type';
    public const string CHANNEL = 'channel';
    public const string ENABLED = 'enabled';

    private function __construct() {}
}
