<?php

declare(strict_types=1);

namespace App\Modules\User\Domain\Exception;

use App\Shared\Domain\Exception\DomainTranslatableException;

/**
 * Адрес электронной почты уже занят другим аккаунтом.
 */
final class EmailAlreadyTakenException extends DomainTranslatableException
{
    public function __construct()
    {
        parent::__construct(translationKey: 'app.user.email_taken');
    }

    #[\Override]
    protected function statusCode(): int
    {
        return 422;
    }
}
