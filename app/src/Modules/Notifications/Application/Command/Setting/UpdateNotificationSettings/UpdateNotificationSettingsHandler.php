<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Command\Setting\UpdateNotificationSettings;

use App\Modules\Notifications\Application\Contract\NotificationTypeDefinition;
use App\Modules\Notifications\Application\Contract\NotificationTypeRegistryContract;
use App\Modules\Notifications\Application\Dto\NotificationSettingViewCollection;
use App\Modules\Notifications\Application\Service\NotificationSettingsViewFactory;
use App\Modules\Notifications\Domain\Entity\NotificationSetting;
use App\Modules\Notifications\Domain\Enum\NotificationChannel;
use App\Modules\Notifications\Domain\Enum\NotificationSettingStatus;
use App\Modules\Notifications\Domain\ValueObject\NotificationTypeCode;
use App\Modules\Notifications\Repository\NotificationSettingRepository;
use App\Shared\Domain\Exception\ValidationException;
use App\Shared\Domain\ValueObject\UserId;
use Cycle\ORM\EntityManagerInterface;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;
use GianTiaga\SpiralCqrs\Attribute\Transactional;
use Psr\Log\LoggerInterface;

/**
 * Upsert настроек «вид × канал» одним flush. Неизвестный вид (нет в реестре) или неизвестный канал
 * -> ValidationException (422). Возвращает полную матрицу настроек после обновления.
 */
final readonly class UpdateNotificationSettingsHandler
{
    public function __construct(
        private NotificationSettingRepository $notificationSettingRepository,
        private NotificationTypeRegistryContract $typeRegistry,
        private NotificationSettingsViewFactory $settingsViewFactory,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}

    #[Transactional]
    #[LogOperation]
    public function handle(UpdateNotificationSettingsCommand $command): NotificationSettingViewCollection
    {
        $userId = UserId::fromString($command->userId);

        foreach ($command->updates as $update) {
            $this->applyUpdate(userId: $userId, update: $update);
        }

        $this->entityManager->run();

        $this->logger->debug(message: 'Настройки уведомлений обновлены.', context: [
            'userId' => $command->userId,
            'count' => \count($command->updates),
        ]);

        return $this->settingsViewFactory->build($userId);
    }

    private function applyUpdate(UserId $userId, NotificationSettingUpdate $update): void
    {
        $type = $this->resolveType($update->type);
        $channel = NotificationChannel::tryFrom($update->channel)
            ?? throw new ValidationException('app.notifications.unknown_channel');

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

        $this->entityManager->persist($setting);
    }

    private function resolveType(string $type): NotificationTypeCode
    {
        $definition = $this->typeRegistry->all()->first(
            static fn(NotificationTypeDefinition $candidate): bool => $candidate->code()->value() === $type,
        ) ?? throw new ValidationException('app.notifications.unknown_type');

        return $definition->code();
    }

    private function status(bool $enabled): NotificationSettingStatus
    {
        return $enabled ? NotificationSettingStatus::Enabled : NotificationSettingStatus::Disabled;
    }
}
