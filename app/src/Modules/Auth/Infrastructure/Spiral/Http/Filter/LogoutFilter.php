<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Spiral\Http\Filter;

use Spiral\Filters\Attribute\Input\Attribute;
use Spiral\Validation\Symfony\AttributesFilter;

/**
 * Читает sessionId из request-атрибута, выставленного AuthContextAttributeMiddleware
 * (наличие гарантирует RequireAuthenticatedMiddleware), без обращения к ServerRequestInterface.
 */
final class LogoutFilter extends AttributesFilter
{
    #[Attribute(key: 'authSessionId')]
    public string $authSessionId;
}
