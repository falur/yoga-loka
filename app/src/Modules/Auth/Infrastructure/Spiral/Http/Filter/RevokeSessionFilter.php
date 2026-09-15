<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Spiral\Http\Filter;

use Spiral\Filters\Attribute\Input\Attribute;
use Spiral\Validation\Symfony\AttributesFilter;

/**
 * Читает authUserId из request-атрибута, выставленного AuthContextAttributeMiddleware (наличие
 * гарантирует RequireAuthenticatedMiddleware), без обращения к ServerRequestInterface. sessionId
 * приходит как параметр маршрута и читается аргументом метода контроллера.
 */
final class RevokeSessionFilter extends AttributesFilter
{
    #[Attribute(key: 'authUserId')]
    public string $authUserId;
}
