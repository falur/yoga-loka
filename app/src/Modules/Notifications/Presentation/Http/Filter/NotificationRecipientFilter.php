<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Presentation\Http\Filter;

use Spiral\Filters\Attribute\Input\Attribute;
use Spiral\Validation\Symfony\AttributesFilter;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Фильтр для эндпоинтов, которым нужен только аутентифицированный пользователь (счётчик,
 * отметка прочитанного, чтение настроек). authUserId приходит из request attribute (JWT middleware
 * появится с модулем Auth).
 */
final class NotificationRecipientFilter extends AttributesFilter
{
    #[Attribute(key: 'authUserId')]
    #[Assert\NotBlank]
    public string $authUserId;
}
