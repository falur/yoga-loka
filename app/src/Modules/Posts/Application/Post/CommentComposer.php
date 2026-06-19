<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Post;

use App\Modules\Posts\Application\Notification\CommentNotificationTarget;
use App\Modules\Posts\Application\Notification\PostNotificationType;
use App\Modules\Posts\Application\Notification\PostNotifier;
use App\Modules\Posts\Domain\Entity\Comment;
use App\Modules\Posts\Domain\Entity\CommentMention;
use App\Shared\Domain\ValueObject\UserId;
use Cycle\ORM\EntityManagerInterface;

/**
 * Сборка содержимого комментария: сохранение упоминаний и стейджинг уведомлений с дедупликацией по
 * получателю. На одного получателя уходит ровно одно уведомление с наибольшим приоритетом
 * (mention > comment_reply > post_commented), self-получатель исключается. Все deep-link
 * комментария ведут на сам комментарий.
 */
final readonly class CommentComposer
{
    public function __construct(
        private MentionRecipientResolver $mentionRecipientResolver,
        private PostNotifier $postNotifier,
        private EntityManagerInterface $entityManager,
    ) {}

    /**
     * Сохраняет упоминания комментария и стейджит уведомления. primaryRecipient — автор записи
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
    ): void {
        $uniqueMentions = \array_values(\array_unique($mentionIds));

        foreach ($uniqueMentions as $mentionId) {
            $this->entityManager->persist(CommentMention::create(commentId: $comment->id, userId: UserId::fromString($mentionId)));
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
                actionType: 'comment',
                actionId: $comment->id->value(),
            );
        }
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
            actionType: 'comment',
            actionId: $commentId,
        );
    }
}
