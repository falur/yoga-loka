<?php

declare(strict_types=1);

namespace App\Modules\Auth\Presentation\Http\Filter;

use Spiral\Filters\Attribute\Input\Post;
use Spiral\Validation\Symfony\AttributesFilter;
use Symfony\Component\Validator\Constraints as Assert;

final class RefreshFilter extends AttributesFilter
{
    #[Post]
    #[Assert\NotBlank]
    public string $refreshToken;
}
