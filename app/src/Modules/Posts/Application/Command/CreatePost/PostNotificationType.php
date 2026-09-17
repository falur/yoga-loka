<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Command\CreatePost;

use App\Modules\Notifications\Public\Contract\NotificationTypeDefinition;
use App\Modules\Notifications\Public\Dto\NotificationChannelCollection;
use App\Modules\Notifications\Public\Enum\NotificationChannel;

/**
 * Виды уведомлений модуля Posts (action-сегмент кода вида и каналы по умолчанию). Один enum на все
 * виды модуля: коды и каналы лежат в одном файле и регистрируются одной строкой через cases() в
 * PostsBootloader. Enum реализует публичный контракт NotificationTypeDefinition модуля
 * Notifications, поэтому живёт в Application, а не в Domain: Domain не зависит от Public соседних
 * модулей (NotificationTypeDefinition, NotificationChannelCollection, NotificationChannel —
 * Notifications/Public). Лежит рядом с CreatePost — центральным сценарием, чьи PostContentComposer
 * и PostNotifier используют его вместе с остальными командами модуля (по факту широкой инъекции —
 * см. deviations фазы).
 */
enum PostNotificationType: string implements NotificationTypeDefinition
{
    case PostMention = 'posts.post_mention';
    case CommentMention = 'posts.comment_mention';
    case PostCommented = 'posts.post_commented';
    case CommentReply = 'posts.comment_reply';
    case PostLike = 'posts.post_like';
    case PostRepost = 'posts.post_repost';
    case CommentLike = 'posts.comment_like';

    #[\Override]
    public function code(): string
    {
        return $this->value;
    }

    #[\Override]
    public function defaultChannels(): NotificationChannelCollection
    {
        // Исчерпывающий match без default: новый case заставит статанализ указать его каналы.
        // Упоминания, комментарии и ответы — инбокс, push и realtime; лайки и репост — инбокс и push.
        return match ($this) {
            self::PostMention,
            self::CommentMention,
            self::PostCommented,
            self::CommentReply => NotificationChannelCollection::of(
                NotificationChannel::Database,
                NotificationChannel::Push,
                NotificationChannel::Realtime,
            ),
            self::PostLike,
            self::PostRepost,
            self::CommentLike => NotificationChannelCollection::of(
                NotificationChannel::Database,
                NotificationChannel::Push,
            ),
        };
    }

    /**
     * Action-сегмент кода вида (часть после `posts.`) — основа ключа перевода
     * `app.posts.notification.<action>.{title|body}`.
     */
    public function notificationKey(): string
    {
        return \explode(separator: '.', string: $this->value, limit: 2)[1];
    }
}
