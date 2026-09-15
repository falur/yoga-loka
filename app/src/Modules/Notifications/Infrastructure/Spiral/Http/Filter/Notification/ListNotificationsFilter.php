<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Infrastructure\Spiral\Http\Filter\Notification;

use Spiral\Filters\Attribute\Input\Attribute;
use Spiral\Filters\Attribute\Input\Query;
use Spiral\Validation\Symfony\AttributesFilter;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Фильтр списка уведомлений: cursor (UUID v7 id последней отданной строки) и limit (1..100).
 */
final class ListNotificationsFilter extends AttributesFilter
{
    #[Attribute(key: 'authUserId')]
    #[Assert\NotBlank]
    public string $authUserId;

    #[Query]
    #[Assert\Uuid]
    public string|null $cursor = null;

    #[Query]
    #[Assert\Positive]
    #[Assert\LessThanOrEqual(100)]
    public int $limit = 20;
}
