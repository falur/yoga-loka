<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\Exception;

use App\Shared\Domain\Exception\DomainTranslatableException;

/**
 * Код входа не подошёл: его нет, он просрочен, исчерпан или введён неверно.
 */
final class InvalidLoginCodeException extends DomainTranslatableException
{
    public function __construct()
    {
        parent::__construct(translationKey: 'app.auth.invalid_code');
    }

    #[\Override]
    protected function statusCode(): int
    {
        return 401;
    }
}
