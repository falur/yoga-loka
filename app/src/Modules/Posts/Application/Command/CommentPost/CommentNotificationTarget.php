<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Command\CommentPost;

use App\Modules\Posts\Application\Command\CreatePost\PostNotificationType;
use App\Modules\User\Public\Dto\UserProfileDto;

/**
 * Получатель уведомления о комментарии после дедупликации: вид с наибольшим приоритетом для этого
 * получателя и его профиль. Используется, чтобы на одного получателя ушло одно уведомление
 * (mention > comment_reply > post_commented).
 *
 * Живёт в Application, а не в Domain: несёт публичный профиль пользователя (User/Public), которым
 * Domain не пользуется.
 */
final readonly class CommentNotificationTarget
{
    public function __construct(
        public PostNotificationType $type,
        public UserProfileDto $recipient,
    ) {}
}
