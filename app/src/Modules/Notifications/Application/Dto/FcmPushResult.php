<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Dto;

/**
 * Результат отправки push: список значений токенов, которые FCM признал невалидными
 * (UNREGISTERED/INVALID_ARGUMENT) — их нужно удалить.
 */
final readonly class FcmPushResult
{
    /**
     * @param list<string> $invalidTokens
     */
    public function __construct(
        public array $invalidTokens,
    ) {}
}
