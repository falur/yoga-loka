<?php

declare(strict_types=1);

namespace App\Modules\User\Domain\Exception;

use App\Shared\Domain\Exception\DomainTranslatableException;

/**
 * Пользователь не найден.
 */
final class UserNotFoundException extends DomainTranslatableException
{
    public function __construct()
    {
        parent::__construct(translationKey: 'app.user.not_found');
    }

    #[\Override]
    protected function statusCode(): int
    {
        return 404;
    }
}
