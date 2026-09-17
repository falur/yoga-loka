<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Infrastructure\Spiral\Http\Filter\DeviceToken;

use Spiral\Filters\Attribute\Input\Attribute;
use Spiral\Filters\Attribute\Input\Post;
use Spiral\Validation\Symfony\AttributesFilter;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Фильтр удаления push-токена. Удаление скоупится по текущему пользователю, чтобы нельзя было удалить
 * чужой токен; в пределах пользователя токен идентифицирует устройство по значению.
 */
final class RemoveNotificationDeviceTokenFilter extends AttributesFilter
{
    #[Attribute(key: 'authUserId')]
    #[Assert\NotBlank]
    public string $authUserId;

    #[Post]
    #[Assert\NotBlank]
    public string $token;
}
