<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Domain\Exception;

use App\Shared\Domain\Exception\DomainTranslatableException;

/**
 * Канала уведомления с таким кодом нет.
 */
final class UnknownNotificationChannelException extends DomainTranslatableException
{
    public function __construct()
    {
        parent::__construct(translationKey: 'app.notifications.unknown_channel');
    }

    #[\Override]
    protected function statusCode(): int
    {
        return 422;
    }
}
