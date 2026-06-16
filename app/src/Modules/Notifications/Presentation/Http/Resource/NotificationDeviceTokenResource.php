<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Presentation\Http\Resource;

use App\Modules\Notifications\Domain\Entity\NotificationDeviceToken;
use App\Shared\Presentation\Http\Resource\AbstractResource;

/**
 * Ресурс push-токена. Само значение токена в ответе не отдаём (rules.md:84 — без значений токенов).
 */
final readonly class NotificationDeviceTokenResource extends AbstractResource
{
    public function __construct(
        public string $id,
        public string $platform,
        public string $createdAt,
    ) {}

    public static function fromEntity(NotificationDeviceToken $deviceToken): self
    {
        return new self(
            id: $deviceToken->id->value(),
            platform: $deviceToken->platform->value,
            createdAt: $deviceToken->createdAt->format(\DateTimeInterface::ATOM),
        );
    }
}
