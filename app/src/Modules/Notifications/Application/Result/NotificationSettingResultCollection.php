<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Result;

use App\Modules\Notifications\Application\Contract\NotificationTypeDefinitionCollection;
use App\Modules\Notifications\Domain\Collection\NotificationSettingCollection;
use App\Modules\Notifications\Domain\Entity\NotificationSetting;
use App\Modules\Notifications\Domain\Enum\NotificationChannel;
use App\Modules\Notifications\Domain\ValueObject\NotificationTypeCode;
use App\Modules\Notifications\Public\Contract\NotificationTypeDefinition;
use App\Modules\Notifications\Public\Enum\NotificationChannel as PublicNotificationChannel;
use App\Shared\Domain\Collection\TypedCollection;

/**
 * @extends TypedCollection<int, NotificationSettingResult>
 */
final class NotificationSettingResultCollection extends TypedCollection
{
    /**
     * Строит матрицу настроек «вид × канал»: все зарегистрированные виды × все каналы, наложенные на
     * персональные строки настроек. Используется и чтением (GetNotificationSettings), и записью
     * (UpdateNotificationSettings) для единообразного ответа.
     */
    public static function build(
        NotificationTypeDefinitionCollection $definitions,
        NotificationSettingCollection $settings,
    ): self {
        $results = [];

        foreach ($definitions as $definition) {
            foreach (NotificationChannel::cases() as $channel) {
                $results[] = self::result(definition: $definition, channel: $channel, settings: $settings);
            }
        }

        return new self($results);
    }

    private static function result(
        NotificationTypeDefinition $definition,
        NotificationChannel $channel,
        NotificationSettingCollection $settings,
    ): NotificationSettingResult {
        // Публичное определение говорит о каналах публичными вариантами, доменные значения строим
        // здесь по строковому значению варианта.
        $type = NotificationTypeCode::fromString($definition->code());
        $default = $definition->defaultChannels()->includes(PublicNotificationChannel::from($channel->value));
        $setting = $settings->first(static fn(NotificationSetting $candidate): bool
            => $candidate->type->equals($type) && $candidate->channel === $channel);

        return new NotificationSettingResult(
            type: $type,
            channel: $channel,
            enabled: $setting !== null ? $setting->isEnabled() : $default,
            default: $default,
        );
    }
}
