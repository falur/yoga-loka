<?php

declare(strict_types=1);

namespace App\Modules\Posts\Infrastructure\Spiral\Bootloader;

use App\Modules\Notifications\Public\Contract\NotificationTypeRegistryContract;
use App\Modules\Posts\Application\Notification\PostNotificationType;
use App\Modules\Posts\Domain\Repository\CommentRepository;
use App\Modules\Posts\Domain\Repository\PostBlockRepository;
use App\Modules\Posts\Domain\Repository\PostRepository;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Repository\CycleCommentRepository;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Repository\CyclePostBlockRepository;
use App\Modules\Posts\Infrastructure\Persistence\Cycle\Repository\CyclePostRepository;
use Spiral\Boot\Bootloader\Bootloader;

/**
 * Бутлоадер модуля Posts. Связывает три доменных интерфейса хранения (Post, Comment, PostBlock —
 * три агрегата модуля) с их Cycle-реализациями и регистрирует виды уведомлений модуля через
 * публичный контракт Notifications: реестр — синглтон, накапливающий регистрации модулей-источников,
 * поэтому регистрация делается в boot() (после поднятия NotificationsBootloader в Kernel). Все виды
 * модуля — один enum PostNotificationType, регистрируются разом через cases().
 */
final class PostsBootloader extends Bootloader
{
    protected const BINDINGS = [
        PostRepository::class => CyclePostRepository::class,
        CommentRepository::class => CycleCommentRepository::class,
        PostBlockRepository::class => CyclePostBlockRepository::class,
    ];

    public function boot(NotificationTypeRegistryContract $typeRegistry): void
    {
        $typeRegistry->register(...PostNotificationType::cases());
    }
}
