<?php

declare(strict_types=1);

namespace App\Modules\Posts\Infrastructure\Spiral\Bootloader;

use App\Modules\Notifications\Public\Contract\NotificationTypeRegistryContract;
use App\Modules\Posts\Application\Notification\PostNotificationType;
use Spiral\Boot\Bootloader\Bootloader;

/**
 * Бутлоадер модуля Posts. Регистрирует виды уведомлений модуля через публичный контракт
 * Notifications: реестр — синглтон, накапливающий регистрации модулей-источников, поэтому
 * регистрация делается в boot() (после поднятия NotificationsBootloader в Kernel). Все виды
 * модуля — один enum PostNotificationType, регистрируются разом через cases().
 */
final class PostsBootloader extends Bootloader
{
    public function boot(NotificationTypeRegistryContract $typeRegistry): void
    {
        $typeRegistry->register(...PostNotificationType::cases());
    }
}
