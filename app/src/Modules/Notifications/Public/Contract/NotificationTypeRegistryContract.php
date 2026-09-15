<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Public\Contract;

/**
 * Регистрация видов уведомлений: модуль-источник объявляет свои определения в boot() своего
 * бутлоадера. Реестр — синглтон, накапливающий регистрации всех модулей за время жизни приложения;
 * повторная регистрация того же кода вида — ошибка, поэтому код вида уникален по приложению.
 *
 * Чтение реестра соседям не публикуется: виды читает только само ядро уведомлений.
 */
interface NotificationTypeRegistryContract
{
    public function register(NotificationTypeDefinition ...$definitions): void;
}
