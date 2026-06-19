<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Notification;

use App\Modules\User\Application\Dto\UserPublicProfileView;

/**
 * Получатель уведомления о комментарии после дедупликации: вид с наибольшим приоритетом для этого
 * получателя и его профиль. Используется, чтобы на одного получателя ушло одно уведомление
 * (mention > comment_reply > post_commented).
 */
final readonly class CommentNotificationTarget
{
    public function __construct(
        public PostNotificationType $type,
        public UserPublicProfileView $recipient,
    ) {}
}
