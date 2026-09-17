<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\Exception;

use App\Shared\Domain\Exception\DomainTranslatableException;

/**
 * Сессия с таким идентификатором у пользователя не найдена.
 */
final class SessionNotFoundException extends DomainTranslatableException
{
    public function __construct()
    {
        parent::__construct(translationKey: 'app.auth.session_not_found');
    }

    #[\Override]
    protected function statusCode(): int
    {
        return 404;
    }
}
