<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Infrastructure\Spiral\Http\Resource;

use App\Modules\Notifications\Domain\Entity\NotificationDeviceToken;
use App\Modules\Notifications\Domain\Enum\DevicePlatform;
use App\Shared\Infrastructure\Spiral\Http\Resource\AbstractResource;

/**
 * Ресурс push-токена. Само значение токена в ответе не отдаём (rules.md:84 — без значений токенов).
 * Платформа — enum-ом (закрытый набор ios/android).
 */
final readonly class NotificationDeviceTokenResource extends AbstractResource
{
    public function __construct(
        public string $id,
        public DevicePlatform $platform,
        public \DateTimeImmutable $createdAt,
    ) {}

    public static function fromEntity(NotificationDeviceToken $deviceToken): self
    {
        return new self(
            id: $deviceToken->id->value(),
            platform: $deviceToken->platform,
            createdAt: $deviceToken->createdAt,
        );
    }
}
