<?php

declare(strict_types=1);

namespace App\Modules\User\Domain\Exception;

use App\Shared\Domain\Exception\DomainTranslatableException;

/**
 * Псевдоним уже занят другим аккаунтом или забронирован.
 */
final class NicknameAlreadyTakenException extends DomainTranslatableException
{
    public function __construct()
    {
        parent::__construct(translationKey: 'app.user.nickname_taken');
    }

    #[\Override]
    protected function statusCode(): int
    {
        return 422;
    }
}
