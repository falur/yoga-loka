<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Result;

/**
 * Переход уведомления (deep-link) в ответе API: тип цели и её идентификатор. Присутствует только когда
 * у уведомления есть переход (иначе NotificationResult::$action = null).
 */
final readonly class NotificationActionResult
{
    public function __construct(
        public string $actionType,
        public string $actionId,
    ) {}
}
