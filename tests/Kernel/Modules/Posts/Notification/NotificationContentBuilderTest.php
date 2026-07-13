<?php

declare(strict_types=1);

namespace Tests\Kernel\Modules\Posts\Notification;

use App\Modules\Notifications\Domain\ValueObject\NotificationAction;
use App\Modules\Notifications\Domain\ValueObject\NotificationActor;
use App\Modules\Posts\Application\Notification\NotificationContentBuilder;
use App\Modules\Posts\Application\Notification\PostNotificationType;
use App\Shared\Domain\Enum\Locale;
use App\Shared\Domain\ValueObject\UserId;
use Tests\TestCase;

/**
 * Сборщик содержимого уведомлений Posts рендерит непустые заголовок и тело в локали получателя
 * (ru и en дают разный текст) и прокладывает готовые снимок автора (actor) и переход (action) в
 * NotificationContent без изменений.
 */
final class NotificationContentBuilderTest extends TestCase
{
    public function testBuildsNonEmptyTranslatedContentForEveryTypeInBothLocales(): void
    {
        $builder = $this->builder();
        $actor = NotificationActor::of(userId: UserId::generate(), name: 'Иван', avatarMediaId: UserId::generate()->value());
        $action = NotificationAction::linkTo(actionType: 'post', actionId: UserId::generate()->value());

        foreach (PostNotificationType::cases() as $type) {
            $russian = $builder->build(
                type: $type,
                actor: $actor,
                action: $action,
                recipientLocale: Locale::Ru,
            );
            $english = $builder->build(
                type: $type,
                actor: $actor,
                action: $action,
                recipientLocale: Locale::En,
            );

            self::assertNotSame('', $russian->title->value());
            self::assertNotSame('', $russian->body->value());
            self::assertNotSame(
                $russian->body->value(),
                $english->body->value(),
                \sprintf('Тело уведомления %s не переведено в локали получателя.', $type->value),
            );
            self::assertStringContainsString('Иван', $russian->body->value());
        }
    }

    public function testPassesActionAndActorThrough(): void
    {
        $actorId = UserId::generate();
        $commentId = UserId::generate()->value();
        $avatarMediaId = UserId::generate()->value();

        $content = $this->builder()->build(
            type: PostNotificationType::CommentReply,
            actor: NotificationActor::of(userId: $actorId, name: 'Мария', avatarMediaId: $avatarMediaId),
            action: NotificationAction::linkTo(actionType: 'comment', actionId: $commentId),
            recipientLocale: Locale::Ru,
        );

        self::assertSame(
            ['actionType' => 'comment', 'actionId' => $commentId],
            $content->action->jsonSerialize(),
        );
        self::assertSame(
            ['id' => $actorId->value(), 'name' => 'Мария', 'avatarMediaId' => $avatarMediaId],
            $content->actor->jsonSerialize(),
        );
    }

    private function builder(): NotificationContentBuilder
    {
        return $this->getContainer()->get(NotificationContentBuilder::class);
    }
}
