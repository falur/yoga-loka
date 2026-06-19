<?php

declare(strict_types=1);

/**
 * Конфигурация модуля User.
 *
 * defaultAvatarUrl — ссылка на аватар по умолчанию, которую публичный профиль отдаёт, когда у
 * пользователя нет аватара или его медиа недоступно. Значение зависит от окружения/CDN; не может
 * быть пустым, иначе снимок автора уведомления (NotificationActor) упал бы с ошибкой.
 */
return [
    'defaultAvatarUrl' => (string) \env('USER_DEFAULT_AVATAR_URL', 'https://cdn.yogaloka.app/avatars/default.png'),
];
