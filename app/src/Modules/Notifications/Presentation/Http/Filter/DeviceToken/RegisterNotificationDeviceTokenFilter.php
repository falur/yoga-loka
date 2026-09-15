<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Presentation\Http\Filter\DeviceToken;

use Spiral\Filters\Attribute\Input\Attribute;
use Spiral\Filters\Attribute\Input\Post;
use Spiral\Validation\Symfony\AttributesFilter;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Фильтр регистрации push-токена. platform принимается строкой; допустимость значения проверяет
 * Handler (неизвестная платформа -> 422). Enum нельзя типизировать прямо в Filter: генератор OpenAPI
 * (gian-tiaga/spiral-openapi) пока не строит схему для enum-свойства фильтра и падает на openapi:generate.
 */
final class RegisterNotificationDeviceTokenFilter extends AttributesFilter
{
    #[Attribute(key: 'authUserId')]
    #[Assert\NotBlank]
    public string $authUserId;

    #[Post]
    #[Assert\NotBlank]
    public string $token;

    #[Post]
    #[Assert\NotBlank]
    public string $platform;
}
