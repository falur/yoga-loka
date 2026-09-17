<?php

declare(strict_types=1);

return [
    'app.auth.code_email_subject' => 'Код для входа в YogaLoka',
    'app.auth.code_email_body' => 'Ваш код для входа: {code}. Он действует 10 минут. Если вы не запрашивали вход, просто проигнорируйте это письмо.',
    'app.auth.invalid_code' => 'Неверный или просроченный код.',
    'app.auth.sign_in_not_allowed' => 'Вход в этот аккаунт недоступен.',
    'app.auth.invalid_ticket' => 'Талон регистрации недействителен или его срок истёк.',
    'app.auth.invalid_refresh' => 'Токен обновления недействителен или его срок истёк.',
    'app.auth.unauthenticated' => 'Требуется аутентификация.',
    'app.auth.session_not_found' => 'Сессия не найдена.',
];
