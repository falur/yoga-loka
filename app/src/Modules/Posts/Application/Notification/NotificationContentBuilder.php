<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Notification;

use App\Modules\Notifications\Application\Dto\NotificationContent;
use App\Modules\Notifications\Domain\ValueObject\NotificationAction;
use App\Modules\Notifications\Domain\ValueObject\NotificationActor;
use App\Modules\Notifications\Domain\ValueObject\NotificationBody;
use App\Modules\Notifications\Domain\ValueObject\NotificationTitle;
use App\Shared\Domain\Enum\Locale;
use Spiral\Translator\TranslatorInterface;

/**
 * Сборка готового содержимого уведомления для модуля Posts. Текст (заголовок и тело) рендерится в
 * локали получателя по ключам `app.posts.notification.<вид>.{title|body}` домена `posts`; снимок
 * автора (actor) и переход (action) приходят готовыми объектами — билдер только переводит текст и
 * собирает NotificationContent.
 */
final readonly class NotificationContentBuilder
{
    private const string TRANSLATION_DOMAIN = 'posts';

    public function __construct(
        private TranslatorInterface $translator,
    ) {}

    public function build(
        PostNotificationType $type,
        NotificationActor $actor,
        NotificationAction $action,
        Locale $recipientLocale,
    ): NotificationContent {
        $parameters = ['actorName' => $actor->presentName()];

        return new NotificationContent(
            type: $type,
            title: NotificationTitle::fromString($this->translate(
                key: \sprintf('app.posts.notification.%s.title', $type->notificationKey()),
                parameters: $parameters,
                locale: $recipientLocale->value,
            )),
            body: NotificationBody::fromString($this->translate(
                key: \sprintf('app.posts.notification.%s.body', $type->notificationKey()),
                parameters: $parameters,
                locale: $recipientLocale->value,
            )),
            action: $action,
            actor: $actor,
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
