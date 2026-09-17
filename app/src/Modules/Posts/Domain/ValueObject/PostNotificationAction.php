<?php

declare(strict_types=1);

namespace App\Modules\Posts\Domain\ValueObject;

use App\Modules\Posts\Domain\Enum\PostNotificationActionTarget;

/**
 * Deep-link уведомления модуля Posts одним значением: цель перехода (запись/комментарий) и её
 * идентификатор. Передаётся в PostNotifier вместо пары плоских строк.
 */
final readonly class PostNotificationAction
{
    public function __construct(
        public PostNotificationActionTarget $target,
        public string $id,
    ) {}
}
