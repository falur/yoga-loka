<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Notification;

use App\Modules\Notifications\Application\Dto\NotificationContent;
use App\Modules\Notifications\Domain\ValueObject\NotificationAction;
use App\Modules\Notifications\Domain\ValueObject\NotificationActor;
use App\Modules\Notifications\Domain\ValueObject\NotificationBody;
use App\Modules\Notifications\Domain\ValueObject\NotificationTitle;
use App\Shared\Domain\ValueObject\UserId;
use Spiral\Translator\TranslatorInterface;

/**
 * Сборка готового содержимого уведомления для модуля Posts. Текст (заголовок и тело) рендерится в
 * локали получателя по ключам `app.posts.notification.<вид>.{title|body}` домена `posts`; автор
 * (actor) — снимок инициатора действия с непустыми именем и ссылкой на аватар (гарантирует
 * вызывающий через профиль), action — deep-link на запись или комментарий.
 */
final readonly class NotificationContentBuilder
{
    private const string TRANSLATION_DOMAIN = 'posts';

    public function __construct(
        private TranslatorInterface $translator,
    ) {}

    public function build(
        PostNotificationType $type,
        UserId $actorUserId,
        string $actorName,
        string $actorAvatarUrl,
        string $recipientLocale,
        string $actionType,
        string $actionId,
    ): NotificationContent {
        $parameters = ['actorName' => $actorName];

        return new NotificationContent(
            type: $type,
            title: NotificationTitle::fromString($this->translate(
                key: \sprintf('app.posts.notification.%s.title', $type->notificationKey()),
                parameters: $parameters,
                locale: $recipientLocale,
            )),
            body: NotificationBody::fromString($this->translate(
                key: \sprintf('app.posts.notification.%s.body', $type->notificationKey()),
                parameters: $parameters,
                locale: $recipientLocale,
            )),
            action: NotificationAction::linkTo(actionType: $actionType, actionId: $actionId),
            actor: NotificationActor::of(userId: $actorUserId, name: $actorName, avatarUrl: $actorAvatarUrl),
        );
    }

    /**
     * @param array<string, string> $parameters
     */
    private function translate(string $key, array $parameters, string $locale): string
    {
        return $this->translator->trans(
            id: $key,
            parameters: $parameters,
            domain: self::TRANSLATION_DOMAIN,
            locale: $locale,
        );
    }
}
