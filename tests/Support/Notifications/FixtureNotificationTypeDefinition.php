<?php

declare(strict_types=1);

namespace Tests\Support\Notifications;

use App\Modules\Notifications\Application\Contract\NotificationTypeDefinition;
use App\Modules\Notifications\Domain\Enum\NotificationChannel;
use App\Modules\Notifications\Domain\ValueObject\NotificationChannelDefaults;
use App\Modules\Notifications\Domain\ValueObject\NotificationTypeCode;

/**
 * Тестовое определение вида: конкретных видов в проде ещё нет (их дают модули-источники),
 * поэтому поток отправки/рассылки/настроек тестируется через эту фикстуру.
 */
final readonly class FixtureNotificationTypeDefinition implements NotificationTypeDefinition
{
    private function __construct(
        private NotificationTypeCode $code,
        private NotificationChannelDefaults $defaultChannels,
    ) {}

    public static function withDefaultChannels(
        string $code = 'chat.message_received',
        NotificationChannel ...$channels,
    ): self {
        return new self(
            code: NotificationTypeCode::fromString($code),
            defaultChannels: NotificationChannelDefaults::of(...$channels),
        );
    }

    public static function allChannels(string $code = 'chat.message_received'): self
    {
        return self::withDefaultChannels(
            $code,
            NotificationChannel::Database,
            NotificationChannel::Push,
            NotificationChannel::Realtime,
        );
    }

    public function code(): NotificationTypeCode
    {
        return $this->code;
    }

    public function defaultChannels(): NotificationChannelDefaults
    {
        return $this->defaultChannels;
    }
}
