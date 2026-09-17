<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Domain\Exception;

use App\Shared\Domain\Exception\DomainTranslatableException;

/**
 * Вида уведомления с таким кодом нет в реестре.
 */
final class UnknownNotificationTypeException extends DomainTranslatableException
{
    public function __construct()
    {
        parent::__construct(translationKey: 'app.notifications.unknown_type');
    }

    #[\Override]
    protected function statusCode(): int
    {
        return 422;
    }
}
