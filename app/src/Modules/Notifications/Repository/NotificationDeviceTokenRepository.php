<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Repository;

use App\Modules\Notifications\Domain\Collection\NotificationDeviceTokenCollection;
use App\Modules\Notifications\Domain\Entity\NotificationDeviceToken;
use App\Modules\Notifications\Domain\ValueObject\DeviceToken;
use App\Shared\Domain\ValueObject\UserId;
use Cycle\ORM\Select\Repository;

/**
 * @extends Repository<NotificationDeviceToken>
 */
final class NotificationDeviceTokenRepository extends Repository
{
    public function findAllForUser(UserId $userId): NotificationDeviceTokenCollection
    {
        return new NotificationDeviceTokenCollection(
            $this->select()
                ->where('user_id', $userId->value())
                ->orderBy(expression: 'id', direction: 'DESC')
                ->fetchAll(),
        );
    }

    public function findByToken(DeviceToken $token): NotificationDeviceToken|null
    {
        return $this->findOne(['token' => $token->value()]);
    }

    public function findByTokenForUser(DeviceToken $token, UserId $userId): NotificationDeviceToken|null
    {
        return $this->findOne(['token' => $token->value(), 'user_id' => $userId->value()]);
    }
}
