<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Infrastructure\Persistence\Cycle\Repository;

use App\Modules\Notifications\Domain\Collection\NotificationDeviceTokenCollection;
use App\Modules\Notifications\Domain\Entity\NotificationDeviceToken;
use App\Modules\Notifications\Domain\Repository\NotificationDeviceTokenRepository;
use App\Modules\Notifications\Domain\ValueObject\DeviceToken;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Persistence\Cycle\AbstractRepository;
use Cycle\ORM\EntityManagerInterface;
use Cycle\ORM\ORM;
use Cycle\ORM\Select;

/**
 * @extends AbstractRepository<NotificationDeviceToken>
 */
final class CycleNotificationDeviceTokenRepository extends AbstractRepository implements NotificationDeviceTokenRepository
{
    /**
     * @param Select<NotificationDeviceToken> $select
     */
    public function __construct(
        Select $select,
        ORM $orm,
        string $role,
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
        return new NotificationDeviceTokenCollection(
            $this->select()
                ->where('user_id', $userId->value())
                ->orderBy(expression: 'id', direction: 'DESC')
                ->fetchAll(),
        );
    }

    #[\Override]
    public function findByToken(DeviceToken $token): NotificationDeviceToken|null
    {
        return $this->findOne(['token' => $token->value()]);
    }

    #[\Override]
    public function findByTokenForUser(DeviceToken $token, UserId $userId): NotificationDeviceToken|null
    {
        return $this->findOne(['token' => $token->value(), 'user_id' => $userId->value()]);
    }

    #[\Override]
    public function save(NotificationDeviceToken $notificationDeviceToken): void
    {
        $this->entityManager
            ->persist($notificationDeviceToken)
            ->run();
    }

    #[\Override]
    public function delete(NotificationDeviceToken $notificationDeviceToken): void
    {
        $this->entityManager
            ->delete($notificationDeviceToken)
            ->run();
    }

    #[\Override]
    public function deleteAll(NotificationDeviceTokenCollection $notificationDeviceTokens): void
    {
        foreach ($notificationDeviceTokens as $notificationDeviceToken) {
            $this->entityManager->delete($notificationDeviceToken);
        }

        $this->entityManager->run();
    }
}
