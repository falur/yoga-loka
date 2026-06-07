<?php

declare (strict_types=1);

namespace GianTiaga\SpiralOpenApi\Tests\Fixtures\Endpoint\Api\V1\Filter;

use Spiral\Filters\Attribute\Input\Query;

final class UserSearchFilter
{
    #[Query]
    public string $query;
    #[Query]
    public int $limit = 20;
}
