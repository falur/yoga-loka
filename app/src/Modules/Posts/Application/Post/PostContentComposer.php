<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Post;

use App\Modules\Media\Application\Command\MakeMediaPermanent\MakeMediaPermanentCommand;
use App\Modules\Media\Application\Command\MakeMediaPermanent\MakeMediaPermanentHandler;
use App\Modules\Media\Application\Query\CheckMediaAttachable\CheckMediaAttachableHandler;
use App\Modules\Media\Application\Query\CheckMediaAttachable\CheckMediaAttachableQuery;
use App\Modules\Posts\Application\Notification\PostNotificationType;
use App\Modules\Posts\Application\Notification\PostNotifier;
use App\Modules\Posts\Domain\Entity\Post;
use App\Modules\Posts\Domain\Entity\PostMedia;
use App\Modules\Posts\Domain\Entity\PostMention;
use App\Modules\Posts\Domain\Entity\PostTag;
use App\Modules\Posts\Domain\Enum\PostStatus;
use App\Modules\Posts\Domain\ValueObject\MediaPosition;
use App\Modules\Posts\Domain\ValueObject\PostMediaReference;
use App\Modules\Posts\Repository\PostMentionRepository;
use App\Modules\Tags\Application\Command\ResolveTags\ResolveTagsCommand;
use App\Modules\Tags\Application\Command\ResolveTags\ResolveTagsHandler;
use App\Modules\User\Application\Dto\UserPublicProfileCollection;
use App\Shared\Domain\ValueObject\TagId;
use App\Shared\Domain\ValueObject\UserId;
use Cycle\ORM\EntityManagerInterface;
use GianTiaga\SpiralCqrs\CommandBusInterface;
use GianTiaga\SpiralCqrs\QueryBusInterface;

/**
 * Общая сборка содержимого записи для сценариев создания и репоста: разрешение тегов, вложение
 * медиа (проверка + перевод в permanent), теги и упоминания, а также стейджинг уведомлений
 * post_mention/post_repost. Все вызовы смежных модулей идут внутри той же транзакции Handler-а
 * (вложенный #[Transactional]-dispatch -> SAVEPOINT), persist выполняет этот сервис, а финальный
 * run() — вызывающий Handler.
 */
final readonly class PostContentComposer
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private QueryBusInterface $queryBus,
        private ResolveTagsHandler $resolveTagsHandler,
        private CheckMediaAttachableHandler $checkMediaAttachableHandler,
        private MakeMediaPermanentHandler $makeMediaPermanentHandler,
        private MentionRecipientResolver $mentionRecipientResolver,
        private PostNotifier $postNotifier,
        private PostMentionRepository $postMentionRepository,
        private EntityManagerInterface $entityManager,
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

        return $this->commandBus->dispatch(
            command: new ResolveTagsCommand(texts: $texts, creatorUserId: $creatorUserId),
            handler: $this->resolveTagsHandler->handle(...),
        )->tagIds;
    }

    /**
     * Проверяет каждое медиа (существование, владелец, готовность), переводит в permanent и
     * сохраняет строки вложения. Дубликаты в списке схлопываются (уникальный индекс
     * (post_id, media_id)). Порядок вложений — позиция по порядку в списке.
     *
     * @param list<string> $mediaIds
     */
    public function attachMedia(Post $post, array $mediaIds, string $ownerUserId): void
    {
        $position = 0;

        foreach (\array_values(\array_unique($mediaIds)) as $mediaId) {
            $this->queryBus->dispatch(
                query: new CheckMediaAttachableQuery(mediaId: $mediaId, ownerUserId: $ownerUserId),
                handler: $this->checkMediaAttachableHandler->handle(...),
            );
            $this->commandBus->dispatch(
                command: new MakeMediaPermanentCommand(userId: $ownerUserId, mediaId: $mediaId),
                handler: $this->makeMediaPermanentHandler->handle(...),
            );
            $this->entityManager->persist(PostMedia::create(
                post: $post,
                media: PostMediaReference::fromString($mediaId),
                position: MediaPosition::fromInt($position),
            ));
            $position++;
        }
    }

    /**
     * @param list<string> $tagIds
     */
    public function attachTags(Post $post, array $tagIds): void
    {
        foreach ($tagIds as $tagId) {
            $this->entityManager->persist(PostTag::create(postId: $post->id, tagId: TagId::fromString($tagId)));
        }
    }

    /**
     * Сохраняет упоминания записи и стейджит post_mention каждому упомянутому (кроме автора).
     * Дубликаты в списке схлопываются; несуществующий пользователь -> 422. Для черновика
     * упоминания только сохраняются, без рассылки уведомлений: чужой черновик невидим и deep-link
     * вёл бы в 404. Уведомления по сохранённым упоминаниям рассылает publish через notifyPostMentions.
     *
     * @param list<string> $mentionIds
     */
    public function attachPostMentions(Post $post, array $mentionIds, string $actorUserId): void
    {
        $uniqueIds = \array_values(\array_unique($mentionIds));

        if ($uniqueIds === []) {
            return;
        }

        // Для черновика рассылки нет, поэтому достаточно дешёвой проверки существования без сборки
        // профилей с разрешением ссылки на аватар. Уведомления по сохранённым упоминаниям рассылает
        // publish через notifyPostMentions, когда запись станет видимой.
        if ($post->status === PostStatus::Draft) {
            $this->mentionRecipientResolver->requireAllExist($uniqueIds);
            $this->persistMentions(post: $post, mentionIds: $uniqueIds);

            return;
        }

        // Опубликованная запись: строгое разрешение профилей (проверка полноты -> 422) и сразу
        // рассылка post_mention существующим получателям.
        $recipients = $this->mentionRecipientResolver->resolveRequired($uniqueIds);
        $this->persistMentions(post: $post, mentionIds: $uniqueIds);
        $this->notifyMentions(post: $post, recipients: $recipients, actorUserId: $actorUserId);
    }

    /**
     * @param list<string> $mentionIds
     */
    private function persistMentions(Post $post, array $mentionIds): void
    {
        foreach ($mentionIds as $mentionId) {
            $this->entityManager->persist(PostMention::create(postId: $post->id, userId: UserId::fromString($mentionId)));
        }
    }

    /**
     * Рассылает post_mention по уже сохранённым упоминаниям записи. Вызывается при публикации
     * черновика, чтобы упомянутые получили уведомление с рабочей ссылкой только после того, как
     * запись стала видимой.
     */
    public function notifyPostMentions(Post $post, string $actorUserId): void
    {
        /** @var list<string> $recipientIds */
        $recipientIds = $this->postMentionRepository->findByPostId($post->id)
            ->map(static fn(PostMention $postMention): string => $postMention->userId->value())
            ->values()
            ->all();

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

    private function notifyMentions(Post $post, UserPublicProfileCollection $recipients, string $actorUserId): void
    {
        $actor = $this->mentionRecipientResolver->profile($actorUserId);

        foreach ($recipients as $recipient) {
            $this->postNotifier->notify(
                type: PostNotificationType::PostMention,
                actor: $actor,
                recipient: $recipient,
                actionType: 'post',
                actionId: $post->id->value(),
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
            actionType: 'post',
            actionId: $postId,
        );
    }
}
