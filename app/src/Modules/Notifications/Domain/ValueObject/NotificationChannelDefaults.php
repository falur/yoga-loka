<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Domain\ValueObject;

use App\Modules\Notifications\Domain\Enum\NotificationChannel;
use App\Shared\Domain\Exception\InvalidDomainValueException;

/**
 * Каналы, включённые по умолчанию для вида уведомления. Их задаёт определение вида
 * (NotificationTypeDefinition::defaultChannels()); рассылка берёт их, когда у пользователя нет
 * персональной настройки по каналу.
 */
final readonly class NotificationChannelDefaults implements \JsonSerializable
{
    /**
     * @param list<NotificationChannel> $enabledChannels
     */
    private function __construct(
        private array $enabledChannels,
    ) {}

    public static function of(NotificationChannel ...$enabledChannels): self
    {
        $unique = [];

        foreach ($enabledChannels as $channel) {
            if (\in_array(needle: $channel, haystack: $unique, strict: true)) {
                throw new InvalidDomainValueException('Канал по умолчанию указан повторно.');
            }

            $unique[] = $channel;
        }

        return new self(enabledChannels: $unique);
    }

    public function isEnabled(NotificationChannel $channel): bool
    {
        return \in_array(needle: $channel, haystack: $this->enabledChannels, strict: true);
    }

    public function equals(self $other): bool
    {
        // Набор каналов сравнивается без учёта порядка: сортируем локальные копии сериализаций
        // (jsonSerialize() возвращает новый массив, состояние VO не мутируется) и сверяем строго.
        $mine = $this->jsonSerialize();
        $theirs = $other->jsonSerialize();
        \sort($mine);
        \sort($theirs);

        return $mine === $theirs;
    }

    /**
     * @return list<string>
     */
    #[\Override]
    public function jsonSerialize(): array
    {
        return \array_map(
            callback: static fn(NotificationChannel $channel): string => $channel->value,
            array: $this->enabledChannels,
        );
    }
}
