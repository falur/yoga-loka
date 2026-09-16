<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Command\Setting\UpdateNotificationSettings;

use App\Modules\Notifications\Application\Contract\NotificationTypeCatalogContract;
use App\Modules\Notifications\Application\Dto\NotificationSettingViewCollection;
use App\Modules\Notifications\Application\Service\NotificationSettingsViewFactory;
use App\Modules\Notifications\Domain\Collection\NotificationSettingCollection;
use App\Modules\Notifications\Domain\Entity\NotificationSetting;
use App\Modules\Notifications\Domain\Enum\NotificationChannel;
use App\Modules\Notifications\Domain\Enum\NotificationSettingStatus;
use App\Modules\Notifications\Domain\ValueObject\NotificationTypeCode;
use App\Modules\Notifications\Public\Contract\NotificationTypeDefinition;
use App\Modules\Notifications\Domain\Repository\NotificationSettingRepository;
use App\Modules\Notifications\Domain\Exception\UnknownNotificationChannelException;
use App\Modules\Notifications\Domain\Exception\UnknownNotificationTypeException;
use App\Shared\Domain\ValueObject\UserId;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;
use GianTiaga\SpiralCqrs\Attribute\Transactional;
use Psr\Log\LoggerInterface;

/**
 * Upsert настроек «вид × канал» одним прогоном записи. Неизвестный вид (нет в реестре) или
 * неизвестный канал -> UnknownNotificationTypeException или UnknownNotificationChannelException
 * (422). Возвращает полную матрицу настроек после обновления.
 */
final readonly class UpdateNotificationSettingsHandler
{
    public function __construct(
        private NotificationSettingRepository $notificationSettingRepository,
        private NotificationTypeCatalogContract $typeCatalog,
        private NotificationSettingsViewFactory $settingsViewFactory,
        private LoggerInterface $logger,
    ) {}

    #[Transactional]
    #[LogOperation]
    public function handle(UpdateNotificationSettingsCommand $command): NotificationSettingViewCollection
    {
        $userId = UserId::fromString($command->userId);
        $notificationSettings = new NotificationSettingCollection();

        foreach ($command->updates as $update) {
            $notificationSettings->push($this->applyUpdate(userId: $userId, update: $update));
        }

        // Единственный прогон сценария: правки всех пар «вид × канал» уходят в базу вместе.
        $this->notificationSettingRepository->saveAll($notificationSettings);

        $this->logger->debug(message: 'Настройки уведомлений обновлены.', context: [
            'userId' => $command->userId,
            'count' => \count($command->updates),
        ]);

        return $this->settingsViewFactory->build($userId);
    }

    private function applyUpdate(UserId $userId, NotificationSettingUpdate $update): NotificationSetting
    {
        $type = $this->resolveType($update->type);
        $channel = NotificationChannel::tryFrom($update->channel)
            ?? throw new UnknownNotificationChannelException();

        $setting = $this->notificationSettingRepository->findOneForUserTypeChannel(
            userId: $userId,
            type: $type,
            channel: $channel,
        );

        if ($setting === null) {
            $setting = NotificationSetting::create(
                userId: $userId,
                type: $type,
                channel: $channel,
                status: $this->status($update->enabled),
            );
        } elseif ($update->enabled) {
            $setting->enable();
        } else {
            $setting->disable();
        }

        return $setting;
    }

    private function resolveType(string $type): NotificationTypeCode
    {
        $definition = $this->typeCatalog->all()->first(
            static fn(NotificationTypeDefinition $candidate): bool => $candidate->code() === $type,
        ) ?? throw new UnknownNotificationTypeException();

        return NotificationTypeCode::fromString($definition->code());
    }

    private function status(bool $enabled): NotificationSettingStatus
    {
        return $enabled ? NotificationSettingStatus::Enabled : NotificationSettingStatus::Disabled;
    }
}
