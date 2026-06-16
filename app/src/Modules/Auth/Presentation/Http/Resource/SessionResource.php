<?php

declare(strict_types=1);

namespace App\Modules\Auth\Presentation\Http\Resource;

use App\Modules\Auth\Application\Query\GetUserSessions\AuthSession;
use App\Shared\Presentation\Http\Resource\AbstractResource;

/**
 * Ресурс одной сессии пользователя. ip/device — nullable (Unknown* отдаёт null), чтобы OpenAPI
 * пометил поля обнуляемыми (генератор читает nullable из типа свойства). current = это текущая
 * сессия запроса.
 */
final readonly class SessionResource extends AbstractResource
{
    public function __construct(
        public string $id,
        public string $createdAt,
        public string $expiresAt,
        public string|null $ip,
        public string|null $device,
        public bool $current,
    ) {}

    public static function fromSession(AuthSession $session, string $currentSessionId): self
    {
        return new self(
            id: $session->sessionId->value(),
            createdAt: $session->createdAt->format('c'),
            expiresAt: $session->expiresAt->value()->format('c'),
            ip: $session->ip->toNullableString(),
            device: $session->userAgent->toNullableString(),
            current: $session->sessionId->value() === $currentSessionId,
        );
    }
}
