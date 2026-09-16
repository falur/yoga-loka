<?php

declare(strict_types=1);

namespace App\Modules\Access\Infrastructure\Persistence\Cycle\Columns;

final class RoleColumns
{
    public const string TABLE = 'roles';

    public const string ID = 'id';
    public const string SLUG = 'slug';

    private function __construct() {}
}
