<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Domain\Exception;

use App\Shared\Domain\Exception\DomainTranslatableException;

/**
 * Уведомление получателя не найдено.
 */
final class NotificationNotFoundException extends DomainTranslatableException
{
    public function __construct()
    {
        parent::__construct(translationKey: 'app.notifications.not_found');
    }

    #[\Override]
    protected function statusCode(): int
    {
        return 404;
    }
}
