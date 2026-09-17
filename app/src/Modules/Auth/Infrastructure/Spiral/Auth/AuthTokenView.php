<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Spiral\Auth;

use App\Modules\Auth\Domain\Enum\AuthTokenType;
use App\Modules\Auth\Domain\ValueObject\SessionId;
use App\Shared\Domain\ValueObject\UserId;
use Spiral\Auth\TokenInterface;

/**
 * Адаптер AuthToken к Spiral\Auth\TokenInterface. getID() возвращает ИСХОДНЫЙ raw-токен из
 * аргумента load()/create() (в БД его нет — только хэш), иначе фреймворковый commitToken
 * испортил бы заголовок ответа. Доменные значения хранятся типизированно; ассоциативный
 * payload собирается только в getPayload() — на границе с Spiral\Auth\TokenInterface.
 */
final readonly class AuthTokenView implements TokenInterface
{
    public function __construct(
        private string $id,
        private UserId $userId,
        private AuthTokenType $type,
        private SessionId $sessionId,
        private \DateTimeImmutable|null $expiresAt,
    ) {}

    #[\Override]
    public function getID(): string
    {
        return $this->id;
    }

    #[\Override]
    public function getExpiresAt(): \DateTimeInterface|null
    {
        return $this->expiresAt;
    }

    /**
     * @return array<string, string>
     */
    #[\Override]
    public function getPayload(): array
    {
        return [
            'userID' => $this->userId->value(),
            'type' => $this->type->value,
            'sessionID' => $this->sessionId->value(),
        ];
    }
}
