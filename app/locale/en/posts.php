<?php

declare(strict_types=1);

return [
    // Доменные ошибки (4xx), переводятся на границе по ключу (домен = posts).
    'app.posts.not_found' => 'Post not found.',
    'app.posts.comment_not_found' => 'Comment not found.',
    'app.posts.forbidden' => 'You are not allowed to perform this action.',
    'app.posts.mention_user_not_found' => 'Mentioned user not found.',

    // Тексты уведомлений на действия. Подставляется имя автора-инициатора через {actorName}.
    'app.posts.notification.post_mention.title' => 'You were mentioned in a post',
    'app.posts.notification.post_mention.body' => '{actorName} mentioned you in a post.',
    'app.posts.notification.comment_mention.title' => 'You were mentioned in a comment',
    'app.posts.notification.comment_mention.body' => '{actorName} mentioned you in a comment.',
    'app.posts.notification.post_commented.title' => 'New comment',
    'app.posts.notification.post_commented.body' => '{actorName} commented on your post.',
    'app.posts.notification.comment_reply.title' => 'New reply',
    'app.posts.notification.comment_reply.body' => '{actorName} replied to your comment.',
    'app.posts.notification.post_like.title' => 'New like',
    'app.posts.notification.post_like.body' => '{actorName} liked your post.',
    'app.posts.notification.post_repost.title' => 'New repost',
    'app.posts.notification.post_repost.body' => '{actorName} reposted your post.',
    'app.posts.notification.comment_like.title' => 'New like',
    'app.posts.notification.comment_like.body' => '{actorName} liked your comment.',
];
