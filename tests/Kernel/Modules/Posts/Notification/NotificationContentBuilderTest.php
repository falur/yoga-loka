<?php

declare(strict_types=1);

namespace Tests\Kernel\Modules\Posts\Notification;

use App\Modules\Notifications\Public\Dto\NotificationActionDto;
use App\Modules\Notifications\Public\Dto\NotificationActorDto;
use App\Modules\Posts\Application\Notification\NotificationContentBuilder;
use App\Modules\Posts\Application\Notification\PostNotificationType;
use App\Shared\Domain\Enum\Locale;
use App\Shared\Domain\ValueObject\UserId;
use Tests\TestCase;

/**
 * Сборщик содержимого уведомлений Posts рендерит непустые заголовок и тело в локали получателя
 * (ru и en дают разный текст) и прокладывает готовые снимок автора (actor) и переход (action) в
 * NotificationContentDto без изменений.
 */
final class NotificationContentBuilderTest extends TestCase
{
    public function testBuildsNonEmptyTranslatedContentForEveryTypeInBothLocales(): void
    {
        $builder = $this->builder();
        $actor = new NotificationActorDto(
            id: UserId::generate()->value(),
            name: 'Иван',
            avatarMediaId: UserId::generate()->value(),
        );
        $action = new NotificationActionDto(actionType: 'post', actionId: UserId::generate()->value());

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

            self::assertNotSame('', $russian->title);
            self::assertNotSame('', $russian->body);
            self::assertNotSame(
                $russian->body,
                $english->body,
                \sprintf('Тело уведомления %s не переведено в локали получателя.', $type->value),
            );
            self::assertStringContainsString('Иван', $russian->body);
        }
    }

    public function testPassesActionAndActorThrough(): void
    {
        $actorId = UserId::generate();
        $commentId = UserId::generate()->value();
        $avatarMediaId = UserId::generate()->value();

        $content = $this->builder()->build(
            type: PostNotificationType::CommentReply,
            actor: new NotificationActorDto(id: $actorId->value(), name: 'Мария', avatarMediaId: $avatarMediaId),
            action: new NotificationActionDto(actionType: 'comment', actionId: $commentId),
            recipientLocale: Locale::Ru,
        );

        self::assertSame(PostNotificationType::CommentReply->code(), $content->typeCode);
        self::assertNotNull($content->action);
        self::assertSame('comment', $content->action->actionType);
        self::assertSame($commentId, $content->action->actionId);
        self::assertNotNull($content->actor);
        self::assertSame($actorId->value(), $content->actor->id);
        self::assertSame('Мария', $content->actor->name);
        self::assertSame($avatarMediaId, $content->actor->avatarMediaId);
    }

    private function builder(): NotificationContentBuilder
    {
        return $this->getContainer()->get(NotificationContentBuilder::class);
    }
}
