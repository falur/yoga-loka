<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Service;

use App\Modules\Notifications\Application\Contract\NotificationTypeCatalogContract;
use App\Modules\Notifications\Application\Dto\NotificationSettingView;
use App\Modules\Notifications\Application\Dto\NotificationSettingViewCollection;
use App\Modules\Notifications\Domain\Collection\NotificationSettingCollection;
use App\Modules\Notifications\Domain\Entity\NotificationSetting;
use App\Modules\Notifications\Domain\Enum\NotificationChannel;
use App\Modules\Notifications\Domain\ValueObject\NotificationTypeCode;
use App\Modules\Notifications\Public\Contract\NotificationTypeDefinition;
use App\Modules\Notifications\Public\Enum\NotificationChannel as PublicNotificationChannel;
use App\Modules\Notifications\Domain\Repository\NotificationSettingRepository;
use App\Shared\Domain\ValueObject\UserId;

/**
 * Строит матрицу настроек «вид × канал» для пользователя: все зарегистрированные виды × все каналы,
 * наложенные на персональные строки настроек. Используется и чтением (GetNotificationSettings),
 * и записью (UpdateNotificationSettings) для единообразного ответа.
 */
final readonly class NotificationSettingsViewFactory
{
    public function __construct(
        private NotificationTypeCatalogContract $typeCatalog,
        private NotificationSettingRepository $notificationSettingRepository,
    ) {}

    public function build(UserId $userId): NotificationSettingViewCollection
    {
        $settings = $this->notificationSettingRepository->findForUser($userId);
        $views = [];

        foreach ($this->typeCatalog->all() as $definition) {
            foreach (NotificationChannel::cases() as $channel) {
                $views[] = $this->view(definition: $definition, channel: $channel, settings: $settings);
            }
        }

        return new NotificationSettingViewCollection($views);
    }

    private function view(
        NotificationTypeDefinition $definition,
        NotificationChannel $channel,
        NotificationSettingCollection $settings,
    ): NotificationSettingView {
        // Публичное определение говорит о каналах публичными вариантами, доменные значения строим
        // здесь по строковому значению варианта.
        $type = NotificationTypeCode::fromString($definition->code());
        $default = $definition->defaultChannels()->includes(PublicNotificationChannel::from($channel->value));
        $setting = $settings->first(static fn(NotificationSetting $candidate): bool
            => $candidate->type->equals($type) && $candidate->channel === $channel);

        return new NotificationSettingView(
            type: $type,
            channel: $channel,
            enabled: $setting !== null ? $setting->isEnabled() : $default,
            default: $default,
        );
    }
}
