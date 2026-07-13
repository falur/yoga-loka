<?php

declare(strict_types=1);

namespace App\Modules\Auth\Presentation\Http\Resource;

use App\Modules\Auth\Application\View\SessionView;
use App\Shared\Presentation\Http\Resource\AbstractResource;

/**
 * Ресурс одной сессии пользователя. ip/device — nullable (неизвестное устройство отдаёт null), чтобы
 * OpenAPI пометил поля обнуляемыми (генератор читает nullable из типа свойства). current = это текущая
 * сессия запроса.
 */
final readonly class SessionResource extends AbstractResource
{
    public function __construct(
        public string $id,
        public \DateTimeImmutable $createdAt,
        public \DateTimeImmutable $expiresAt,
        public string|null $ip,
        public string|null $device,
        public bool $current,
    ) {}

    public static function fromView(SessionView $session): self
    {
        return new self(
            id: $session->id,
            createdAt: $session->createdAt,
            expiresAt: $session->expiresAt,
            ip: $session->ip,
            device: $session->device,
            current: $session->current,
        );
    }
}
