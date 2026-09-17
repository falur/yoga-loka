<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Infrastructure\Persistence\Cycle\Columns;

final class NotificationDeviceTokenColumns
{
    public const string TABLE = 'notification_device_tokens';

    public const string ID = 'id';
    public const string USER_ID = 'user_id';
    public const string TOKEN = 'token';
    public const string PLATFORM = 'platform';

    private function __construct() {}
}
