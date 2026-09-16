<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\Exception;

use App\Shared\Domain\Exception\DomainTranslatableException;

/**
 * Токен обновления недействителен, не является refresh-токеном или его срок истёк.
 */
final class InvalidRefreshTokenException extends DomainTranslatableException
{
    public function __construct()
    {
        parent::__construct(translationKey: 'app.auth.invalid_refresh');
    }

    #[\Override]
    protected function statusCode(): int
    {
        return 401;
    }
}
