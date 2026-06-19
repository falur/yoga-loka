<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Notification;

use App\Modules\Notifications\Application\Contract\NotificationTypeDefinition;
use App\Modules\Notifications\Domain\Enum\NotificationChannel;
use App\Modules\Notifications\Domain\ValueObject\NotificationChannelDefaults;
use App\Modules\Notifications\Domain\ValueObject\NotificationTypeCode;

/**
 * Виды уведомлений модуля Posts (action-сегмент кода вида и каналы по умолчанию). Один enum на все
 * виды модуля: коды и каналы лежат в одном файле и регистрируются одной строкой через cases() в
 * PostsBootloader. Enum реализует контракт NotificationTypeDefinition, поэтому живёт в Application
 * (его использует и Handler сборки контента, Application не должен зависеть от Infrastructure).
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
    public function code(): NotificationTypeCode
    {
        return NotificationTypeCode::fromString($this->value);
    }

    #[\Override]
    public function defaultChannels(): NotificationChannelDefaults
    {
        // Исчерпывающий match без default: новый case заставит статанализ указать его каналы.
        // Упоминания, комментарии и ответы — инбокс, push и realtime; лайки и репост — инбокс и push.
        return match ($this) {
            self::PostMention,
            self::CommentMention,
            self::PostCommented,
            self::CommentReply => NotificationChannelDefaults::of(
                NotificationChannel::Database,
                NotificationChannel::Push,
                NotificationChannel::Realtime,
            ),
            self::PostLike,
            self::PostRepost,
            self::CommentLike => NotificationChannelDefaults::of(
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
