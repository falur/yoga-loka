<?php

declare(strict_types=1);

namespace App\Modules\Auth\Presentation\Http\Filter;

use Spiral\Filters\Attribute\Input\Header;
use Spiral\Filters\Attribute\Input\Post;
use Spiral\Filters\Attribute\Input\RemoteAddress;
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

    // Захватываются из соединения и заголовка автоматически (клиент ничего не присылает) для
    // метаданных сессии. SOURCE_NONE — в OpenAPI-контракт тела запроса не попадают.
    #[RemoteAddress]
    public string|null $clientIp = null;

    #[Header(key: 'User-Agent')]
    public string|null $userAgent = null;
}
