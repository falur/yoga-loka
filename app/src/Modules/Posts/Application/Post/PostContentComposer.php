<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Post;

use App\Modules\Media\Public\Contract\MediaContract;
use App\Modules\Posts\Application\Notification\PostNotificationAction;
use App\Modules\Posts\Application\Notification\PostNotificationActionTarget;
use App\Modules\Posts\Application\Notification\PostNotificationType;
use App\Modules\Posts\Application\Notification\PostNotifier;
use App\Modules\Posts\Domain\Collection\PostMediaCollection;
use App\Modules\Posts\Domain\Collection\PostMentionCollection;
use App\Modules\Posts\Domain\Collection\PostTagCollection;
use App\Modules\Posts\Domain\Entity\Post;
use App\Modules\Posts\Domain\Entity\PostMedia;
use App\Modules\Posts\Domain\Entity\PostMention;
use App\Modules\Posts\Domain\Entity\PostTag;
use App\Modules\Posts\Domain\Enum\PostStatus;
use App\Modules\Posts\Domain\Repository\PostRepository;
use App\Modules\Posts\Domain\ValueObject\MediaPosition;
use App\Modules\Posts\Domain\ValueObject\PostMediaReference;
use App\Modules\Posts\Domain\ValueObject\PostTagReference;
use App\Modules\Tags\Public\Contract\TagsContract;
use App\Modules\User\Public\Dto\UserProfileDtoCollection;
use App\Shared\Domain\ValueObject\UserId;

/**
 * Общая сборка содержимого записи для сценариев создания и репоста: разрешение тегов, вложение
 * медиа (проверка + перевод в permanent), теги и упоминания, а также стейджинг уведомлений
 * post_mention/post_repost. Все вызовы смежных модулей идут внутри той же транзакции Handler-а
 * (вложенный #[Transactional]-dispatch -> SAVEPOINT). Вложения, теги и упоминания только собираются
 * в коллекции — сохраняет их вместе с записью методом своего интерфейса вызывающий Handler.
 */
final readonly class PostContentComposer
{
    public function __construct(
        private TagsContract $tags,
        private MediaContract $mediaContract,
        private MentionRecipientResolver $mentionRecipientResolver,
        private PostNotifier $postNotifier,
        private PostRepository $postRepository,
    ) {}

    /**
     * @param list<string> $texts
     *
     * @return list<string>
     */
    public function resolveTags(array $texts, string $creatorUserId): array
    {
        if ($texts === []) {
            return [];
        }

        return $this->tags->resolve(texts: $texts, creatorUserId: $creatorUserId)->tagIds;
    }

    /**
     * Проверяет весь набор медиа (существование, владелец, готовность), переводит его в permanent и
     * сохраняет строки вложения. К Media идут ровно два вызова на запись, а не по два на вложение;
     * набор передаётся в порядке вложений, поэтому непригодное медиа даёт ту же ошибку, что и раньше.
     * Дубликаты в списке схлопываются (уникальный индекс (post_id, media_id)). Порядок вложений —
     * позиция по порядку в списке. Пустой набор к соседу не ходит.
     *
     * @param list<string> $mediaIds
     */
    public function attachMedia(Post $post, array $mediaIds, string $ownerUserId): PostMediaCollection
    {
        $uniqueIds = \array_values(\array_unique($mediaIds));

        $media = new PostMediaCollection();

        if ($uniqueIds === []) {
            return $media;
        }

        $this->mediaContract->ensureAttachable(mediaIds: $uniqueIds, ownerUserId: $ownerUserId);
        $this->mediaContract->makePermanent(mediaIds: $uniqueIds, ownerUserId: $ownerUserId);

        $position = 0;

        foreach ($uniqueIds as $mediaId) {
            $media->push(PostMedia::create(
                post: $post,
                mediaId: PostMediaReference::fromString($mediaId),
                position: MediaPosition::fromInt($position),
            ));
            $position++;
        }

        return $media;
    }

    /**
     * @param list<string> $tagIds
     */
    public function attachTags(Post $post, array $tagIds): PostTagCollection
    {
        $tags = new PostTagCollection();

        foreach ($tagIds as $tagId) {
            $tags->push(PostTag::create(
                postId: $post->id,
                tagId: PostTagReference::fromString($tagId),
            ));
        }

        return $tags;
    }

    /**
     * Собирает упоминания записи и стейджит post_mention каждому упомянутому (кроме автора).
     * Дубликаты в списке схлопываются; несуществующий пользователь -> 422. Для черновика
     * упоминания только собираются, без рассылки уведомлений: чужой черновик невидим и deep-link
     * вёл бы в 404. Уведомления по сохранённым упоминаниям рассылает publish через notifyPostMentions.
     *
     * @param list<string> $mentionIds
     */
    public function attachPostMentions(Post $post, array $mentionIds, string $actorUserId): PostMentionCollection
    {
        $uniqueIds = \array_values(\array_unique($mentionIds));

        if ($uniqueIds === []) {
            return new PostMentionCollection();
        }

        // Для черновика рассылки нет, поэтому достаточно дешёвой проверки существования без сборки
        // профилей с разрешением ссылки на аватар. Уведомления по сохранённым упоминаниям рассылает
        // publish через notifyPostMentions, когда запись станет видимой.
        if ($post->status === PostStatus::Draft) {
            $this->mentionRecipientResolver->requireAllExist($uniqueIds);

            return $this->buildMentions(post: $post, mentionIds: $uniqueIds);
        }

        // Опубликованная запись: строгое разрешение профилей (проверка полноты -> 422) и сразу
        // рассылка post_mention существующим получателям.
        $recipients = $this->mentionRecipientResolver->resolveRequired($uniqueIds);
        $mentions = $this->buildMentions(post: $post, mentionIds: $uniqueIds);
        $this->notifyMentions(post: $post, recipients: $recipients, actorUserId: $actorUserId);

        return $mentions;
    }

    /**
     * @param list<string> $mentionIds
     */
    private function buildMentions(Post $post, array $mentionIds): PostMentionCollection
    {
        $mentions = new PostMentionCollection();

        foreach ($mentionIds as $mentionId) {
            $mentions->push(PostMention::create(postId: $post->id, userId: UserId::fromString($mentionId)));
        }

        return $mentions;
    }

    /**
     * Рассылает post_mention по уже сохранённым упоминаниям записи. Вызывается при публикации
     * черновика, чтобы упомянутые получили уведомление с рабочей ссылкой только после того, как
     * запись стала видимой.
     */
    public function notifyPostMentions(Post $post, string $actorUserId): void
    {
        $recipientIds = $this->postRepository->findMentionsByPostId($post->id)
            ->mapToList(static fn(PostMention $postMention): string => $postMention->userId->value());

        if ($recipientIds === []) {
            return;
        }

        // Мягкий путь: упоминания уже отвалидированы при создании черновика, к моменту публикации
        // кого-то могло не стать -> недоступные тихо пропускаются, без строгой проверки полноты.
        $this->notifyMentions(
            post: $post,
            recipients: $this->mentionRecipientResolver->resolveExisting($recipientIds),
            actorUserId: $actorUserId,
        );
    }

    private function notifyMentions(Post $post, UserProfileDtoCollection $recipients, string $actorUserId): void
    {
        $actor = $this->mentionRecipientResolver->profile($actorUserId);

        foreach ($recipients as $recipient) {
            $this->postNotifier->notify(
                type: PostNotificationType::PostMention,
                actor: $actor,
                recipient: $recipient,
                action: new PostNotificationAction(target: PostNotificationActionTarget::Post, id: $post->id->value()),
            );
        }
    }

    /**
     * Стейджит уведомление автору записи о действии над ней (репост, лайк). Самодействие
     * (автор == инициатор) не уведомляет.
     */
    public function notifyPostAuthor(
        PostNotificationType $type,
        UserId $postAuthor,
        string $actorUserId,
        string $postId,
    ): void {
        if ($postAuthor->value() === $actorUserId) {
            return;
        }

        $this->postNotifier->notify(
            type: $type,
            actor: $this->mentionRecipientResolver->profile($actorUserId),
            recipient: $this->mentionRecipientResolver->profile($postAuthor->value()),
            action: new PostNotificationAction(target: PostNotificationActionTarget::Post, id: $postId),
        );
    }
}
