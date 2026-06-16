<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Dto;

use App\Modules\Notifications\Application\Contract\NotificationTypeDefinition;
use App\Modules\Notifications\Domain\ValueObject\NotificationAction;
use App\Modules\Notifications\Domain\ValueObject\NotificationActor;
use App\Modules\Notifications\Domain\ValueObject\NotificationBody;
use App\Modules\Notifications\Domain\ValueObject\NotificationTitle;

/**
 * Готовое содержимое уведомления, которое модуль-источник передаёт в NotificationSenderContract::send().
 * type — это само определение вида (зарегистрированное NotificationTypeDefinition), а не строковый код:
 * так на месте отправки нет магической строки, а код вида ядро берёт из определения само. Текст
 * (title/body) уже на языке получателя — Notifications его не переводит. action — переход (deep-link)
 * либо его отсутствие. actor — автор-инициатор (например, тот, кто подписался) либо его отсутствие;
 * по нему клиент покажет аватар автора.
 */
final readonly class NotificationContent
{
    public function __construct(
        public NotificationTypeDefinition $type,
        public NotificationTitle $title,
        public NotificationBody $body,
        public NotificationAction $action,
        public NotificationActor $actor,
    ) {}
}
