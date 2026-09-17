<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Command\CommentPost;

use App\Modules\Posts\Application\Command\CreatePost\MentionRecipientResolver;
use App\Modules\Posts\Application\Command\CreatePost\PostNotificationType;
use App\Modules\Posts\Application\Command\CreatePost\PostNotifier;
use App\Modules\Posts\Domain\Collection\CommentMentionCollection;
use App\Modules\Posts\Domain\Entity\Comment;
use App\Modules\Posts\Domain\Entity\CommentMention;
use App\Modules\Posts\Domain\Enum\PostNotificationActionTarget;
use App\Modules\Posts\Domain\ValueObject\PostNotificationAction;
use App\Shared\Domain\ValueObject\UserId;

/**
 * Сборка содержимого комментария: сборка упоминаний и стейджинг уведомлений с дедупликацией по
 * получателю. На одного получателя уходит ровно одно уведомление с наибольшим приоритетом
 * (mention > comment_reply > post_commented), self-получатель исключается. Все deep-link
 * комментария ведут на сам комментарий. Упоминания только собираются в коллекцию — сохраняет их
 * вместе с комментарием методом своего интерфейса вызывающий Handler.
 *
 * Лежит рядом с CommentPost, но используется также ReplyComment/LikeComment (по факту инъекции в
 * их конструкторы) — общая сборка содержимого и уведомлений комментария не привязана к одной
 * команде жизненного цикла комментария.
 */
final readonly class CommentComposer
{
    public function __construct(
        private MentionRecipientResolver $mentionRecipientResolver,
        private PostNotifier $postNotifier,
    ) {}

    /**
     * Собирает упоминания комментария и стейджит уведомления. primaryRecipient — автор записи
     * (для post_commented у комментария верхнего уровня) либо автор родителя (для comment_reply у
     * ответа); его вид задаёт primaryType. Упоминание перебивает primary при совпадении получателя.
     *
     * @param list<string> $mentionIds
     */
    public function attachMentionsAndNotify(
        Comment $comment,
        array $mentionIds,
        string $actorUserId,
        string $primaryRecipientUserId,
        PostNotificationType $primaryType,
    ): CommentMentionCollection {
        $uniqueMentions = \array_values(\array_unique($mentionIds));

        $mentions = new CommentMentionCollection();

        foreach ($uniqueMentions as $mentionId) {
            $mentions->push(
                CommentMention::create(
                    commentId: $comment->id,
                    userId: UserId::fromString($mentionId),
                ),
            );
        }

        $mentionProfiles = $this->mentionRecipientResolver->resolveRequired($uniqueMentions);

        $actor = $this->mentionRecipientResolver->profile($actorUserId);

        /** @var array<string, CommentNotificationTarget> $targets */
        $targets = [];

        foreach ($mentionProfiles as $mentionProfile) {
            $targets[$mentionProfile->userId] = new CommentNotificationTarget(
                type: PostNotificationType::CommentMention,
                recipient: $mentionProfile,
            );
        }

        // Упоминание (приоритет выше) перебивает primary: добавляем primary только если получатель
        // ещё не охвачен упоминанием.
        if (!isset($targets[$primaryRecipientUserId])) {
            $targets[$primaryRecipientUserId] = new CommentNotificationTarget(
                type: $primaryType,
                recipient: $this->mentionRecipientResolver->profile($primaryRecipientUserId),
            );
        }

        foreach ($targets as $target) {
            $this->postNotifier->notify(
                type: $target->type,
                actor: $actor,
                recipient: $target->recipient,
                action: new PostNotificationAction(
                    target: PostNotificationActionTarget::Comment,
                    id: $comment->id->value(),
                ),
            );
        }

        return $mentions;
    }

    /**
     * Стейджит уведомление автору комментария о реакции на него (comment_like). Самодействие не
     * уведомляет.
     */
    public function notifyCommentAuthor(
        PostNotificationType $type,
        UserId $commentAuthor,
        string $actorUserId,
        string $commentId,
    ): void {
        if ($commentAuthor->value() === $actorUserId) {
            return;
        }

        $this->postNotifier->notify(
            type: $type,
            actor: $this->mentionRecipientResolver->profile($actorUserId),
            recipient: $this->mentionRecipientResolver->profile($commentAuthor->value()),
            action: new PostNotificationAction(
                target: PostNotificationActionTarget::Comment,
                id: $commentId,
            ),
        );
    }
}
