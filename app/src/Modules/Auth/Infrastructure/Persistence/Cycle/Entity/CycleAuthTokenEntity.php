<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Persistence\Cycle\Entity;

use App\Modules\Auth\Domain\Enum\AuthTokenType;
use App\Modules\Auth\Infrastructure\Persistence\Cycle\Columns\AuthTokenColumns;
use App\Modules\Auth\Infrastructure\Persistence\Cycle\Repository\CycleAuthTokenRepository;
use App\Shared\Infrastructure\Persistence\Cycle\HasTimestamps;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\ORM\Parser\Typecast;

#[Entity(
    role: 'auth_token',
    table: AuthTokenColumns::TABLE,
    repository: CycleAuthTokenRepository::class,
    typecast: [Typecast::class],
)]
final class CycleAuthTokenEntity
{
    use HasTimestamps;

    #[Column(type: 'uuid', name: AuthTokenColumns::ID, primary: true)]
    public string $id;

    #[Column(type: 'uuid', name: AuthTokenColumns::USER_ID)]
    public string $userId;

    #[Column(type: 'uuid', name: AuthTokenColumns::SESSION_ID)]
    public string $sessionId;

    #[Column(type: 'string(16)', name: AuthTokenColumns::TYPE, typecast: AuthTokenType::class)]
    public AuthTokenType $type;

    #[Column(type: 'text', name: AuthTokenColumns::TOKEN_HASH)]
    public string $tokenHash;

    #[Column(type: 'datetime', name: AuthTokenColumns::EXPIRES_AT, typecast: 'datetime')]
    public \DateTimeImmutable $expiresAt;

    #[Column(type: 'string(45)', name: AuthTokenColumns::IP, nullable: true)]
    public string|null $ip;

    #[Column(type: 'text', name: AuthTokenColumns::USER_AGENT, nullable: true)]
    public string|null $userAgent;
}
