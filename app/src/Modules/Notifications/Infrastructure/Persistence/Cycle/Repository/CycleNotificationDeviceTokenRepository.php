<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Infrastructure\Persistence\Cycle\Repository;

use App\Modules\Notifications\Domain\Collection\NotificationDeviceTokenCollection;
use App\Modules\Notifications\Domain\Entity\NotificationDeviceToken;
use App\Modules\Notifications\Domain\Repository\NotificationDeviceTokenRepository;
use App\Modules\Notifications\Domain\ValueObject\DeviceToken;
use App\Modules\Notifications\Infrastructure\Persistence\Cycle\Columns\NotificationDeviceTokenColumns;
use App\Modules\Notifications\Infrastructure\Persistence\Cycle\Entity\CycleNotificationDeviceTokenEntity;
use App\Modules\Notifications\Infrastructure\Persistence\Cycle\Mapper\NotificationDeviceTokenMapper;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Persistence\Cycle\AbstractRepository;
use Cycle\ORM\EntityManagerInterface;
use Cycle\ORM\ORM;
use Cycle\ORM\Select;

/**
 * @extends AbstractRepository<CycleNotificationDeviceTokenEntity>
 */
final class CycleNotificationDeviceTokenRepository extends AbstractRepository implements NotificationDeviceTokenRepository
{
    /**
     * @param Select<CycleNotificationDeviceTokenEntity> $select
     */
    public function __construct(
        Select $select,
        ORM $orm,
        string $role,
        private NotificationDeviceTokenMapper $notificationDeviceTokenMapper,
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct(select: $select, orm: $orm, role: $role);
    }

    /**
     * id DESC = новые токены сверху (UUID v7 хронологичен).
     */
    #[\Override]
    public function findAllForUser(UserId $userId): NotificationDeviceTokenCollection
    {
        $notificationDeviceTokenCollection = new NotificationDeviceTokenCollection();

        /** @var iterable<CycleNotificationDeviceTokenEntity> $cycleEntities */
        $cycleEntities = $this->select()
            ->where(NotificationDeviceTokenColumns::USER_ID, $userId->value())
            ->orderBy(expression: NotificationDeviceTokenColumns::ID, direction: 'DESC')
            ->fetchAll();

        foreach ($cycleEntities as $cycleEntity) {
            $notificationDeviceTokenCollection->push($this->notificationDeviceTokenMapper->toDomain($cycleEntity));
        }

        return $notificationDeviceTokenCollection;
    }

    #[\Override]
    public function findByToken(DeviceToken $token): NotificationDeviceToken|null
    {
        /** @var CycleNotificationDeviceTokenEntity|null $cycleEntity */
        $cycleEntity = $this->findOne([NotificationDeviceTokenColumns::TOKEN => $token->value()]);

        return $cycleEntity === null ? null : $this->notificationDeviceTokenMapper->toDomain($cycleEntity);
    }

    #[\Override]
    public function findByTokenForUser(DeviceToken $token, UserId $userId): NotificationDeviceToken|null
    {
        /** @var CycleNotificationDeviceTokenEntity|null $cycleEntity */
        $cycleEntity = $this->findOne([
            NotificationDeviceTokenColumns::TOKEN => $token->value(),
            NotificationDeviceTokenColumns::USER_ID => $userId->value(),
        ]);

        return $cycleEntity === null ? null : $this->notificationDeviceTokenMapper->toDomain($cycleEntity);
    }

    #[\Override]
    public function save(NotificationDeviceToken $notificationDeviceToken): void
    {
        /** @var CycleNotificationDeviceTokenEntity|null $cycleEntity */
        $cycleEntity = $this->findOne([NotificationDeviceTokenColumns::ID => $notificationDeviceToken->id->value()]);

        $this->entityManager
            ->persist($this->notificationDeviceTokenMapper->toCycleEntity(
                notificationDeviceToken: $notificationDeviceToken,
                cycleEntity: $cycleEntity,
            ))
            ->run();
    }

    #[\Override]
    public function delete(NotificationDeviceToken $notificationDeviceToken): void
    {
        /** @var CycleNotificationDeviceTokenEntity|null $cycleEntity */
        $cycleEntity = $this->findOne([NotificationDeviceTokenColumns::ID => $notificationDeviceToken->id->value()]);

        if ($cycleEntity === null) {
            return;
        }

        $this->entityManager
            ->delete($cycleEntity)
            ->run();
    }

    #[\Override]
    public function deleteAll(NotificationDeviceTokenCollection $notificationDeviceTokens): void
    {
        foreach ($notificationDeviceTokens as $notificationDeviceToken) {
            /** @var CycleNotificationDeviceTokenEntity|null $cycleEntity */
            $cycleEntity = $this->findOne([NotificationDeviceTokenColumns::ID => $notificationDeviceToken->id->value()]);

            if ($cycleEntity === null) {
                continue;
            }

            $this->entityManager->delete($cycleEntity);
        }

        $this->entityManager->run();
    }
}
