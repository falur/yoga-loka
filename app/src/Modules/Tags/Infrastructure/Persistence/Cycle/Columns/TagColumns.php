<?php

declare(strict_types=1);

namespace App\Modules\Tags\Infrastructure\Persistence\Cycle\Columns;

final class TagColumns
{
    public const string TABLE = 'tags';

    public const string ID = 'id';
    public const string TEXT = 'text';
    public const string CREATED_BY_ID = 'created_by_id';

    private function __construct() {}
}
