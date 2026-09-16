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
use App\Modules\Auth\Infrastructure\Persistence\Cycle\Typecast\ExpirationTypecast;
use App\Modules\Auth\Infrastructure\Persistence\Cycle\Typecast\IpTypecast;
use App\Modules\Auth\Infrastructure\Persistence\Cycle\Typecast\UserAgentTypecast;
use App\Modules\Auth\Infrastructure\Persistence\Cycle\Repository\CycleAuthTokenRepository;
use App\Shared\Infrastructure\Persistence\Cycle\HasTimestamps;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Persistence\Cycle\ValueObjectCast;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\ORM\Parser\Typecast;

#[Entity(
    role: 'auth_token',
    table: 'auth_tokens',
    repository: CycleAuthTokenRepository::class,
    typecast: [Typecast::class, ValueObjectCast::class],
)]
final class AuthToken
{
    use HasTimestamps;

    #[Column(type: 'uuid', primary: true, typecast: AuthTokenId::class)]
    public private(set) AuthTokenId $id;

    #[Column(type: 'uuid', name: 'user_id', typecast: UserId::class)]
    public private(set) UserId $userId;

    #[Column(type: 'uuid', name: 'session_id', typecast: SessionId::class)]
    public private(set) SessionId $sessionId;

    #[Column(type: 'string(16)', typecast: AuthTokenType::class)]
    public private(set) AuthTokenType $type;

    #[Column(type: 'text', name: 'token_hash', typecast: TokenHash::class)]
    public private(set) TokenHash $tokenHash;

    #[Column(type: 'datetime', name: 'expires_at', typecast: ExpirationTypecast::class)]
    public private(set) Expiration $expiration;

    #[Column(type: 'string(45)', name: 'ip', nullable: true, typecast: IpTypecast::class)]
    public private(set) Ip $ip;

    #[Column(type: 'text', name: 'user_agent', nullable: true, typecast: UserAgentTypecast::class)]
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
