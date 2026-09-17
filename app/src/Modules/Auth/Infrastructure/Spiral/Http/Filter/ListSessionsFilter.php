<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Spiral\Http\Filter;

use Spiral\Filters\Attribute\Input\Attribute;
use Spiral\Validation\Symfony\AttributesFilter;

/**
 * Читает authUserId/authSessionId из request-атрибутов, выставленных
 * AuthContextAttributeMiddleware (наличие гарантирует объявление доступа маршрута), без
 * обращения к ServerRequestInterface. authSessionId нужен, чтобы пометить текущую сессию.
 */
final class ListSessionsFilter extends AttributesFilter
{
    #[Attribute(key: 'authUserId')]
    public string $authUserId;

    #[Attribute(key: 'authSessionId')]
    public string $authSessionId;
}
