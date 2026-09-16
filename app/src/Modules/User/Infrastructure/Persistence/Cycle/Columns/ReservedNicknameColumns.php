<?php

declare(strict_types=1);

namespace App\Modules\User\Infrastructure\Persistence\Cycle\Columns;

final class ReservedNicknameColumns
{
    public const string TABLE = 'reserved_nicknames';

    public const string ID = 'id';
    public const string NICKNAME = 'nickname';
    public const string ASSIGNED_USER_ID = 'assigned_user_id';

    private function __construct() {}
}
