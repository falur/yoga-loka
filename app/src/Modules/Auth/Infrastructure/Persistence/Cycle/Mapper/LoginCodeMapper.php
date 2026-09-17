<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Persistence\Cycle\Mapper;

use App\Modules\Auth\Domain\Entity\LoginCode;
use App\Modules\Auth\Domain\ValueObject\CodeAttempts;
use App\Modules\Auth\Domain\ValueObject\Consumption;
use App\Modules\Auth\Domain\ValueObject\EmailAddress;
use App\Modules\Auth\Domain\ValueObject\Expiration;
use App\Modules\Auth\Domain\ValueObject\LoginCodeId;
use App\Modules\Auth\Domain\ValueObject\SecretHash;
use App\Modules\Auth\Infrastructure\Persistence\Cycle\Entity\CycleLoginCodeEntity;

final readonly class LoginCodeMapper
{
    public function toDomain(CycleLoginCodeEntity $cycleEntity): LoginCode
    {
        return LoginCode::restore(
            id: LoginCodeId::fromString($cycleEntity->id),
            email: EmailAddress::fromString($cycleEntity->email),
            codeHash: SecretHash::fromString($cycleEntity->codeHash),
            expiration: Expiration::fromDateTime($cycleEntity->expiresAt),
            attempts: CodeAttempts::fromInt($cycleEntity->attempts),
            consumption: $cycleEntity->consumedAt === null
                ? Consumption::notConsumed()
                : Consumption::at($cycleEntity->consumedAt),
            createdAt: $cycleEntity->createdAt,
            updatedAt: $cycleEntity->updatedAt,
        );
    }

    public function toCycleEntity(
        LoginCode $loginCode,
        CycleLoginCodeEntity|null $cycleEntity = null,
    ): CycleLoginCodeEntity {
        $cycleEntity ??= new CycleLoginCodeEntity();
        $cycleEntity->id = $loginCode->id->value();
        $cycleEntity->email = $loginCode->email->value();
        $cycleEntity->codeHash = $loginCode->codeHash->value();
        $cycleEntity->expiresAt = $loginCode->expiration->value();
        $cycleEntity->attempts = $loginCode->attempts->value();
        $cycleEntity->consumedAt = $loginCode->consumption->value();
        $cycleEntity->createdAt = $loginCode->createdAt;
        $cycleEntity->updatedAt = $loginCode->updatedAt;

        return $cycleEntity;
    }
}
