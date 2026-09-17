<?php

declare(strict_types=1);

namespace App\Modules\Posts\Infrastructure\Spiral\Http\Filter;

use Spiral\Filters\Attribute\Input\Attribute;
use Spiral\Filters\Attribute\Input\Query;
use Spiral\Filters\Attribute\Input\Route;
use Spiral\Validation\Symfony\AttributesFilter;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Комментарии записи: <id> записи из пути (uuid), cursor (uuid) и limit (1..100).
 */
final class GetPostCommentsFilter extends AttributesFilter
{
    #[Attribute(key: 'authUserId')]
    #[Assert\NotBlank]
    public string $authUserId;

    #[Route(key: 'id')]
    #[Assert\NotBlank]
    #[Assert\Uuid]
    public string $id;

    #[Query]
    #[Assert\Uuid]
    public string|null $cursor = null;

    #[Query]
    #[Assert\Positive]
    #[Assert\LessThanOrEqual(100)]
    public int $limit = 20;
}
