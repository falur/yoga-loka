<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\Entity;

use App\Modules\Auth\Domain\ValueObject\CodeAttempts;
use App\Modules\Auth\Domain\ValueObject\Consumption;
use App\Modules\Auth\Domain\ValueObject\EmailAddress;
use App\Modules\Auth\Domain\ValueObject\Expiration;
use App\Modules\Auth\Domain\ValueObject\LoginCodeId;
use App\Modules\Auth\Domain\ValueObject\SecretHash;
use App\Shared\Domain\Trait\HasTimestamps;

final class LoginCode
{
    use HasTimestamps;

    public private(set) LoginCodeId $id;

    public private(set) EmailAddress $email;

    public private(set) SecretHash $codeHash;

    public private(set) Expiration $expiration;

    public private(set) CodeAttempts $attempts;

    public private(set) Consumption $consumption;

    public static function issue(
        LoginCodeId $id,
        EmailAddress $email,
        SecretHash $codeHash,
        Expiration $expiration,
        \DateTimeImmutable $now,
    ): self {
        $loginCode = new self();
        $loginCode->id = $id;
        $loginCode->email = $email;
        $loginCode->codeHash = $codeHash;
        $loginCode->expiration = $expiration;
        $loginCode->attempts = CodeAttempts::initial();
        $loginCode->consumption = Consumption::notConsumed();
        $loginCode->initializeTimestamps($now);

        return $loginCode;
    }

    public static function restore(
        LoginCodeId $id,
        EmailAddress $email,
        SecretHash $codeHash,
        Expiration $expiration,
        CodeAttempts $attempts,
        Consumption $consumption,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        $loginCode = new self();
        $loginCode->id = $id;
        $loginCode->email = $email;
        $loginCode->codeHash = $codeHash;
        $loginCode->expiration = $expiration;
        $loginCode->attempts = $attempts;
        $loginCode->consumption = $consumption;
        $loginCode->createdAt = $createdAt;
        $loginCode->updatedAt = $updatedAt;

        return $loginCode;
    }

    public function registerFailedAttempt(\DateTimeImmutable $now): void
    {
        $this->attempts = $this->attempts->increment();
        $this->touch($now);
    }

    public function consume(\DateTimeImmutable $now): void
    {
        $this->consumption = Consumption::at($now);
        $this->touch($now);
    }

    public function isExpired(\DateTimeImmutable $now): bool
    {
        return $this->expiration->isExpired($now);
    }

    public function attemptsExhausted(): bool
    {
        return $this->attempts->isExhausted();
    }

    public function isConsumed(): bool
    {
        return $this->consumption->isConsumed();
    }
}
