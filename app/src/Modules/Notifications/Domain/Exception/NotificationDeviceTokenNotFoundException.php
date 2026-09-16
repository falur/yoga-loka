<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Domain\Exception;

use App\Shared\Domain\Exception\DomainTranslatableException;

/**
 * Токен устройства у пользователя не найден.
 */
final class NotificationDeviceTokenNotFoundException extends DomainTranslatableException
{
    public function __construct()
    {
        parent::__construct(translationKey: 'app.notifications.device_token_not_found');
    }

    #[\Override]
    protected function statusCode(): int
    {
        return 404;
    }
}
