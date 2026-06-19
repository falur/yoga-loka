<?php

declare(strict_types=1);

return [
    // Доменные ошибки (4xx), переводятся на границе по ключу (домен = posts).
    'app.posts.not_found' => 'Запись не найдена.',
    'app.posts.comment_not_found' => 'Комментарий не найден.',
    'app.posts.forbidden' => 'Недостаточно прав для этого действия.',
    'app.posts.mention_user_not_found' => 'Упомянутый пользователь не найден.',

    // Тексты уведомлений на действия. Подставляется имя автора-инициатора через {actorName}.
    'app.posts.notification.post_mention.title' => 'Вас упомянули в записи',
    'app.posts.notification.post_mention.body' => '{actorName} упомянул вас в записи.',
    'app.posts.notification.comment_mention.title' => 'Вас упомянули в комментарии',
    'app.posts.notification.comment_mention.body' => '{actorName} упомянул вас в комментарии.',
    'app.posts.notification.post_commented.title' => 'Новый комментарий',
    'app.posts.notification.post_commented.body' => '{actorName} прокомментировал вашу запись.',
    'app.posts.notification.comment_reply.title' => 'Новый ответ',
    'app.posts.notification.comment_reply.body' => '{actorName} ответил на ваш комментарий.',
    'app.posts.notification.post_like.title' => 'Новая отметка «нравится»',
    'app.posts.notification.post_like.body' => '{actorName} оценил вашу запись.',
    'app.posts.notification.post_repost.title' => 'Новый репост',
    'app.posts.notification.post_repost.body' => '{actorName} поделился вашей записью.',
    'app.posts.notification.comment_like.title' => 'Новая отметка «нравится»',
    'app.posts.notification.comment_like.body' => '{actorName} оценил ваш комментарий.',
];
