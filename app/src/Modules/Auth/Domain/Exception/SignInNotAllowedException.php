<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\Exception;

use App\Shared\Domain\Exception\DomainTranslatableException;

/**
 * Вход в аккаунт запрещён: аккаунт удалён или заблокирован.
 */
final class SignInNotAllowedException extends DomainTranslatableException
{
    public function __construct()
    {
        parent::__construct(translationKey: 'app.auth.sign_in_not_allowed');
    }

    #[\Override]
    protected function statusCode(): int
    {
        return 401;
    }
}
