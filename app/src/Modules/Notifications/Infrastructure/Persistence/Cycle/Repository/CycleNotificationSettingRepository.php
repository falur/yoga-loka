<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Infrastructure\Persistence\Cycle\Repository;

use App\Modules\Notifications\Domain\Collection\NotificationSettingCollection;
use App\Modules\Notifications\Domain\Entity\NotificationSetting;
use App\Modules\Notifications\Domain\Enum\NotificationChannel;
use App\Modules\Notifications\Domain\Repository\NotificationSettingRepository;
use App\Modules\Notifications\Domain\ValueObject\NotificationTypeCode;
use App\Modules\Notifications\Infrastructure\Persistence\Cycle\Columns\NotificationSettingColumns;
use App\Modules\Notifications\Infrastructure\Persistence\Cycle\Entity\CycleNotificationSettingEntity;
use App\Modules\Notifications\Infrastructure\Persistence\Cycle\Mapper\NotificationSettingMapper;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Persistence\Cycle\AbstractRepository;
use Cycle\ORM\EntityManagerInterface;
use Cycle\ORM\ORM;
use Cycle\ORM\Select;

/**
 * @extends AbstractRepository<CycleNotificationSettingEntity>
 */
final class CycleNotificationSettingRepository extends AbstractRepository implements NotificationSettingRepository
{
    /**
     * @param Select<CycleNotificationSettingEntity> $select
     */
    public function __construct(
        Select $select,
        ORM $orm,
        string $role,
        private NotificationSettingMapper $notificationSettingMapper,
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct(select: $select, orm: $orm, role: $role);
    }

    #[\Override]
    public function findForUserAndType(UserId $userId, NotificationTypeCode $type): NotificationSettingCollection
    {
        $notificationSettingCollection = new NotificationSettingCollection();

        /** @var iterable<CycleNotificationSettingEntity> $cycleEntities */
        $cycleEntities = $this->select()
            ->where(NotificationSettingColumns::USER_ID, $userId->value())
            ->where(NotificationSettingColumns::TYPE, $type->value())
            ->fetchAll();

        foreach ($cycleEntities as $cycleEntity) {
            $notificationSettingCollection->push($this->notificationSettingMapper->toDomain($cycleEntity));
        }

        return $notificationSettingCollection;
    }

    #[\Override]
    public function findForUser(UserId $userId): NotificationSettingCollection
    {
        $notificationSettingCollection = new NotificationSettingCollection();

        /** @var iterable<CycleNotificationSettingEntity> $cycleEntities */
        $cycleEntities = $this->select()
            ->where(NotificationSettingColumns::USER_ID, $userId->value())
            ->fetchAll();

        foreach ($cycleEntities as $cycleEntity) {
            $notificationSettingCollection->push($this->notificationSettingMapper->toDomain($cycleEntity));
        }

        return $notificationSettingCollection;
    }

    #[\Override]
    public function findOneForUserTypeChannel(
        UserId $userId,
        NotificationTypeCode $type,
        NotificationChannel $channel,
    ): NotificationSetting|null {
        /** @var CycleNotificationSettingEntity|null $cycleEntity */
        $cycleEntity = $this->findOne([
            NotificationSettingColumns::USER_ID => $userId->value(),
            NotificationSettingColumns::TYPE => $type->value(),
            NotificationSettingColumns::CHANNEL => $channel->value,
        ]);

        return $cycleEntity === null ? null : $this->notificationSettingMapper->toDomain($cycleEntity);
    }

    #[\Override]
    public function saveAll(NotificationSettingCollection $notificationSettings): void
    {
        foreach ($notificationSettings as $notificationSetting) {
            /** @var CycleNotificationSettingEntity|null $cycleEntity */
            $cycleEntity = $this->findOne([NotificationSettingColumns::ID => $notificationSetting->id->value()]);

            $this->entityManager->persist($this->notificationSettingMapper->toCycleEntity(
                notificationSetting: $notificationSetting,
                cycleEntity: $cycleEntity,
            ));
        }

        $this->entityManager->run();
    }
}
