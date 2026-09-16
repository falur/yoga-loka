<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Domain\Exception;

use App\Shared\Domain\Exception\DomainTranslatableException;

/**
 * Платформы устройства с таким кодом нет.
 */
final class UnknownDevicePlatformException extends DomainTranslatableException
{
    public function __construct()
    {
        parent::__construct(translationKey: 'app.notifications.unknown_platform');
    }

    #[\Override]
    protected function statusCode(): int
    {
        return 422;
    }
}
