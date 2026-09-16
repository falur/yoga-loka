<?php

declare(strict_types=1);

namespace App\Modules\Access\Infrastructure\Persistence\Cycle\Columns;

final class RolePermissionColumns
{
    public const string TABLE = 'role_permissions';

    public const string ID = 'id';
    public const string ROLE_ID = 'role_id';
    public const string PERMISSION_ID = 'permission_id';

    private function __construct() {}
}
