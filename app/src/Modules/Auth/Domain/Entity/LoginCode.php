<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\Entity;

use App\Modules\Auth\Domain\ValueObject\CodeAttempts;
use App\Modules\Auth\Domain\ValueObject\Consumption;
use App\Modules\Auth\Domain\ValueObject\EmailAddress;
use App\Modules\Auth\Domain\ValueObject\Expiration;
use App\Modules\Auth\Domain\ValueObject\LoginCodeId;
use App\Modules\Auth\Domain\ValueObject\SecretHash;
use App\Modules\Auth\Infrastructure\Persistence\Cycle\Typecast\ConsumptionTypecast;
use App\Modules\Auth\Infrastructure\Persistence\Cycle\Typecast\ExpirationTypecast;
use App\Modules\Auth\Infrastructure\Persistence\Cycle\Repository\CycleLoginCodeRepository;
use App\Shared\Infrastructure\Persistence\Cycle\HasTimestamps;
use App\Shared\Infrastructure\Persistence\Cycle\ValueObjectCast;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\ORM\Parser\Typecast;

#[Entity(
    role: 'auth_login_code',
    table: 'auth_login_codes',
    repository: CycleLoginCodeRepository::class,
    typecast: [Typecast::class, ValueObjectCast::class],
)]
final class LoginCode
{
    use HasTimestamps;

    #[Column(type: 'uuid', primary: true, typecast: LoginCodeId::class)]
    public private(set) LoginCodeId $id;

    #[Column(type: 'string(254)', typecast: EmailAddress::class)]
    public private(set) EmailAddress $email;

    #[Column(type: 'text', name: 'code_hash', typecast: SecretHash::class)]
    public private(set) SecretHash $codeHash;

    #[Column(type: 'datetime', name: 'expires_at', typecast: ExpirationTypecast::class)]
    public private(set) Expiration $expiration;

    #[Column(type: 'integer', typecast: CodeAttempts::class)]
    public private(set) CodeAttempts $attempts;

    #[Column(type: 'datetime', name: 'consumed_at', nullable: true, typecast: ConsumptionTypecast::class)]
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
