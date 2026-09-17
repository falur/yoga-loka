<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Command\RequestNotification;

use App\Modules\Notifications\Domain\ValueObject\NotificationAction;
use App\Modules\Notifications\Domain\ValueObject\NotificationActor;
use App\Modules\Notifications\Domain\ValueObject\NotificationBody;
use App\Modules\Notifications\Domain\ValueObject\NotificationTitle;
use App\Modules\Notifications\Domain\ValueObject\NotificationTypeCode;
use App\Shared\Domain\ValueObject\UserId;

/**
 * Запрос на отправку уведомления одному получателю. Примитивы публичного контракта в доменные
 * значения превращает входной адаптер (NotificationProvider), поэтому сценарий получает уже
 * проверенные значения: перехода нет -> NotificationAction::none(), автора нет ->
 * NotificationActor::none().
 */
final readonly class RequestNotificationCommand
{
    public function __construct(
        public UserId $recipient,
        public NotificationTypeCode $type,
        public NotificationTitle $title,
        public NotificationBody $body,
        public NotificationAction $action,
        public NotificationActor $actor,
    ) {}
}
