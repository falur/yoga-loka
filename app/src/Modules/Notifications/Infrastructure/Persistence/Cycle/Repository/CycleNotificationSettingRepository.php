<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Infrastructure\Persistence\Cycle\Repository;

use App\Modules\Notifications\Domain\Collection\NotificationSettingCollection;
use App\Modules\Notifications\Domain\Entity\NotificationSetting;
use App\Modules\Notifications\Domain\Enum\NotificationChannel;
use App\Modules\Notifications\Domain\Repository\NotificationSettingRepository;
use App\Modules\Notifications\Domain\ValueObject\NotificationTypeCode;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Persistence\Cycle\AbstractRepository;
use Cycle\ORM\EntityManagerInterface;
use Cycle\ORM\ORM;
use Cycle\ORM\Select;

/**
 * @extends AbstractRepository<NotificationSetting>
 */
final class CycleNotificationSettingRepository extends AbstractRepository implements NotificationSettingRepository
{
    /**
     * @param Select<NotificationSetting> $select
     */
    public function __construct(
        Select $select,
        ORM $orm,
        string $role,
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct(select: $select, orm: $orm, role: $role);
    }

    #[\Override]
    public function findForUserAndType(UserId $userId, NotificationTypeCode $type): NotificationSettingCollection
    {
        return new NotificationSettingCollection(
            $this->select()
                ->where('user_id', $userId->value())
                ->where('type', $type->value())
                ->fetchAll(),
        );
    }

    #[\Override]
    public function findForUser(UserId $userId): NotificationSettingCollection
    {
        return new NotificationSettingCollection(
            $this->select()
                ->where('user_id', $userId->value())
                ->fetchAll(),
        );
    }

    #[\Override]
    public function findOneForUserTypeChannel(
        UserId $userId,
        NotificationTypeCode $type,
        NotificationChannel $channel,
    ): NotificationSetting|null {
        return $this->findOne([
            'user_id' => $userId->value(),
            'type' => $type->value(),
            'channel' => $channel->value,
        ]);
    }

    #[\Override]
    public function saveAll(NotificationSettingCollection $notificationSettings): void
    {
        foreach ($notificationSettings as $notificationSetting) {
            $this->entityManager->persist($notificationSetting);
        }

        $this->entityManager->run();
    }
}
