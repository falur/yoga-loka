<?php

declare(strict_types=1);

namespace App\Modules\Access\Infrastructure\Persistence\Cycle\Columns;

final class UserRoleColumns
{
    public const string TABLE = 'user_roles';

    public const string ID = 'id';
    public const string USER_ID = 'user_id';
    public const string ROLE_ID = 'role_id';

    private function __construct() {}
}
