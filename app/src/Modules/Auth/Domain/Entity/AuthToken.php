<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\Entity;

use App\Modules\Auth\Domain\Enum\AuthTokenType;
use App\Modules\Auth\Domain\ValueObject\AuthTokenId;
use App\Modules\Auth\Domain\ValueObject\Expiration;
use App\Modules\Auth\Domain\ValueObject\Ip;
use App\Modules\Auth\Domain\ValueObject\SessionDevice;
use App\Modules\Auth\Domain\ValueObject\SessionId;
use App\Modules\Auth\Domain\ValueObject\TokenHash;
use App\Modules\Auth\Domain\ValueObject\UserAgent;
use App\Shared\Domain\Trait\HasTimestamps;
use App\Shared\Domain\ValueObject\UserId;

final class AuthToken
{
    use HasTimestamps;

    public private(set) AuthTokenId $id;

    public private(set) UserId $userId;

    public private(set) SessionId $sessionId;

    public private(set) AuthTokenType $type;

    public private(set) TokenHash $tokenHash;

    public private(set) Expiration $expiration;

    public private(set) Ip $ip;

    public private(set) UserAgent $userAgent;

    public static function issue(
        AuthTokenId $id,
        UserId $userId,
        SessionId $sessionId,
        AuthTokenType $type,
        TokenHash $tokenHash,
        Expiration $expiration,
        SessionDevice $device,
        \DateTimeImmutable $now,
    ): self {
        $authToken = new self();
        $authToken->id = $id;
        $authToken->userId = $userId;
        $authToken->sessionId = $sessionId;
        $authToken->type = $type;
        $authToken->tokenHash = $tokenHash;
        $authToken->expiration = $expiration;
        $authToken->ip = $device->ip;
        $authToken->userAgent = $device->userAgent;
        $authToken->initializeTimestamps($now);

        return $authToken;
    }

    public static function restore(
        AuthTokenId $id,
        UserId $userId,
        SessionId $sessionId,
        AuthTokenType $type,
        TokenHash $tokenHash,
        Expiration $expiration,
        Ip $ip,
        UserAgent $userAgent,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        $authToken = new self();
        $authToken->id = $id;
        $authToken->userId = $userId;
        $authToken->sessionId = $sessionId;
        $authToken->type = $type;
        $authToken->tokenHash = $tokenHash;
        $authToken->expiration = $expiration;
        $authToken->ip = $ip;
        $authToken->userAgent = $userAgent;
        $authToken->createdAt = $createdAt;
        $authToken->updatedAt = $updatedAt;

        return $authToken;
    }

    public function isExpired(\DateTimeImmutable $now): bool
    {
        return $this->expiration->isExpired($now);
    }

    public function isAccess(): bool
    {
        return $this->type === AuthTokenType::Access;
    }

    public function isRefresh(): bool
    {
        return $this->type === AuthTokenType::Refresh;
    }
}
