<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Contract;

use App\Modules\Notifications\Domain\ValueObject\NotificationChannelDefaults;
use App\Modules\Notifications\Domain\ValueObject\NotificationTypeCode;

/**
 * Определение вида уведомления. Регистрируется модулем-источником в его бутлоадере: код вида
 * (`module.action`) и каналы, включённые по умолчанию, когда у пользователя нет персональной
 * настройки по каналу. Ядро уведомлений вид не интерпретирует.
 */
interface NotificationTypeDefinition
{
    public function code(): NotificationTypeCode;

    public function defaultChannels(): NotificationChannelDefaults;
}
