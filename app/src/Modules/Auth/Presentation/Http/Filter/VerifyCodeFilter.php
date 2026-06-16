<?php

declare(strict_types=1);

namespace App\Modules\Auth\Presentation\Http\Filter;

use Spiral\Filters\Attribute\Input\Post;
use Spiral\Validation\Symfony\AttributesFilter;
use Symfony\Component\Validator\Constraints as Assert;

final class VerifyCodeFilter extends AttributesFilter
{
    #[Post]
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 254)]
    public string $email;

    #[Post]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^\d{6}$/')]
    public string $code;
}
