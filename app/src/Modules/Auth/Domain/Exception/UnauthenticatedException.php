<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\Exception;

use App\Shared\Domain\Exception\DomainTranslatableException;

/**
 * Маршрут требует аутентификации, а запрос пришёл без неё.
 */
final class UnauthenticatedException extends DomainTranslatableException
{
    public function __construct()
    {
        parent::__construct(translationKey: 'app.auth.unauthenticated');
    }

    #[\Override]
    protected function statusCode(): int
    {
        return 401;
    }
}
