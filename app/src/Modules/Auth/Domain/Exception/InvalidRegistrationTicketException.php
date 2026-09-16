<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\Exception;

use App\Shared\Domain\Exception\DomainTranslatableException;

/**
 * Талон регистрации недействителен или его срок истёк.
 */
final class InvalidRegistrationTicketException extends DomainTranslatableException
{
    public function __construct()
    {
        parent::__construct(translationKey: 'app.auth.invalid_ticket');
    }

    #[\Override]
    protected function statusCode(): int
    {
        return 401;
    }
}
