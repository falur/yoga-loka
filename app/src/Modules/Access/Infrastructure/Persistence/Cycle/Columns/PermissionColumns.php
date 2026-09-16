<?php

declare(strict_types=1);

namespace App\Modules\Access\Infrastructure\Persistence\Cycle\Columns;

final class PermissionColumns
{
    public const string TABLE = 'permissions';

    public const string ID = 'id';
    public const string SLUG = 'slug';

    private function __construct() {}
}
