<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Spiral\Http\Filter;

use Spiral\Filters\Attribute\Input\Header;
use Spiral\Filters\Attribute\Input\Post;
use Spiral\Filters\Attribute\Input\RemoteAddress;
use Spiral\Validation\Symfony\AttributesFilter;
use Symfony\Component\Validator\Constraints as Assert;

final class RefreshFilter extends AttributesFilter
{
    #[Post]
    #[Assert\NotBlank]
    public string $refreshToken;

    // Захватываются из соединения и заголовка автоматически (клиент ничего не присылает) для
    // метаданных сессии. SOURCE_NONE — в OpenAPI-контракт тела запроса не попадают.
    #[RemoteAddress]
    public string|null $clientIp = null;

    #[Header(key: 'User-Agent')]
    public string|null $userAgent = null;
}
