<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Persistence\Cycle\Mapper;

use App\Modules\Auth\Domain\Entity\AuthToken;
use App\Modules\Auth\Domain\ValueObject\AuthTokenId;
use App\Modules\Auth\Domain\ValueObject\Expiration;
use App\Modules\Auth\Domain\ValueObject\Ip;
use App\Modules\Auth\Domain\ValueObject\SessionId;
use App\Modules\Auth\Domain\ValueObject\TokenHash;
use App\Modules\Auth\Domain\ValueObject\UserAgent;
use App\Modules\Auth\Infrastructure\Persistence\Cycle\Entity\CycleAuthTokenEntity;
use App\Shared\Domain\ValueObject\UserId;

final readonly class AuthTokenMapper
{
    public function toDomain(CycleAuthTokenEntity $cycleEntity): AuthToken
    {
        return AuthToken::restore(
            id: AuthTokenId::fromString($cycleEntity->id),
            userId: UserId::fromString($cycleEntity->userId),
            sessionId: SessionId::fromString($cycleEntity->sessionId),
            type: $cycleEntity->type,
            tokenHash: TokenHash::fromString($cycleEntity->tokenHash),
            expiration: Expiration::fromDateTime($cycleEntity->expiresAt),
            ip: Ip::fromNullable($cycleEntity->ip),
            userAgent: UserAgent::fromNullable($cycleEntity->userAgent),
            createdAt: $cycleEntity->createdAt,
            updatedAt: $cycleEntity->updatedAt,
        );
    }

    public function toCycleEntity(
        AuthToken $authToken,
        CycleAuthTokenEntity|null $cycleEntity = null,
    ): CycleAuthTokenEntity {
        $cycleEntity ??= new CycleAuthTokenEntity();
        $cycleEntity->id = $authToken->id->value();
        $cycleEntity->userId = $authToken->userId->value();
        $cycleEntity->sessionId = $authToken->sessionId->value();
        $cycleEntity->type = $authToken->type;
        $cycleEntity->tokenHash = $authToken->tokenHash->value();
        $cycleEntity->expiresAt = $authToken->expiration->value();
        $cycleEntity->ip = $authToken->ip->toNullableString();
        $cycleEntity->userAgent = $authToken->userAgent->toNullableString();
        $cycleEntity->createdAt = $authToken->createdAt;
        $cycleEntity->updatedAt = $authToken->updatedAt;

        return $cycleEntity;
    }
}
