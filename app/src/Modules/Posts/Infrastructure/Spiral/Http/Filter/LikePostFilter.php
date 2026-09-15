<?php

declare(strict_types=1);

namespace App\Modules\Posts\Infrastructure\Spiral\Http\Filter;

use Spiral\Filters\Attribute\Input\Attribute;
use Spiral\Filters\Attribute\Input\Route;
use Spiral\Validation\Symfony\AttributesFilter;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Лайк записи: authUserId из request-атрибута (auth-middleware) и <id> записи из пути (uuid, иначе 422).
 */
final class LikePostFilter extends AttributesFilter
{
    #[Attribute(key: 'authUserId')]
    #[Assert\NotBlank]
    public string $authUserId;

    #[Route(key: 'id')]
    #[Assert\NotBlank]
    #[Assert\Uuid]
    public string $id;
}
