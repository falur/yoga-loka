<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\View;

/**
 * Переход уведомления (deep-link) в read-model: тип цели и её идентификатор. Присутствует только когда
 * у уведомления есть переход (иначе NotificationView.action = null).
 */
final readonly class NotificationActionView
{
    public function __construct(
        public string $actionType,
        public string $actionId,
    ) {}
}
