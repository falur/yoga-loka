<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Tests\Unit\Application\Fixture;

use App\Modules\Notifications\Public\Contract\NotificationTypeDefinition;
use App\Modules\Notifications\Public\Dto\NotificationChannelCollection;
use App\Modules\Notifications\Public\Enum\NotificationChannel;

/**
 * Тестовое определение вида: поток отправки/рассылки/настроек тестируется через эту фикстуру
 * независимо от видов, которые регистрируют реальные модули-источники.
 */
final readonly class FixtureNotificationTypeDefinition implements NotificationTypeDefinition
{
    private function __construct(
        private string $code,
        private NotificationChannelCollection $defaultChannels,
    ) {}

    public static function withDefaultChannels(
        string $code = 'chat.message_received',
        NotificationChannel ...$channels,
    ): self {
        return new self(
            code: $code,
            defaultChannels: NotificationChannelCollection::of(...$channels),
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

    #[\Override]
    public function code(): string
    {
        return $this->code;
    }

    #[\Override]
    public function defaultChannels(): NotificationChannelCollection
    {
        return $this->defaultChannels;
    }
}
