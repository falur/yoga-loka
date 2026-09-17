<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Public\Contract;

use App\Modules\Notifications\Public\Dto\NotificationChannelCollection;

/**
 * Определение вида уведомления. Его реализует и регистрирует модуль-источник в своём бутлоадере:
 * код вида в формате `module.action` и каналы, включённые по умолчанию, когда у пользователя нет
 * персональной настройки по каналу. Ядро уведомлений вид не интерпретирует.
 */
interface NotificationTypeDefinition
{
    public function code(): string;

    public function defaultChannels(): NotificationChannelCollection;
}
