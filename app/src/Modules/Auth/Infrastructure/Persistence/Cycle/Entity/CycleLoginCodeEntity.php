<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Persistence\Cycle\Entity;

use App\Modules\Auth\Infrastructure\Persistence\Cycle\Columns\LoginCodeColumns;
use App\Modules\Auth\Infrastructure\Persistence\Cycle\Repository\CycleLoginCodeRepository;
use App\Shared\Infrastructure\Persistence\Cycle\HasTimestamps;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\ORM\Parser\Typecast;

#[Entity(
    role: 'auth_login_code',
    table: LoginCodeColumns::TABLE,
    repository: CycleLoginCodeRepository::class,
    typecast: [Typecast::class],
)]
final class CycleLoginCodeEntity
{
    use HasTimestamps;

    #[Column(type: 'uuid', name: LoginCodeColumns::ID, primary: true)]
    public string $id;

    #[Column(type: 'string(254)', name: LoginCodeColumns::EMAIL)]
    public string $email;

    #[Column(type: 'text', name: LoginCodeColumns::CODE_HASH)]
    public string $codeHash;

    #[Column(type: 'datetime', name: LoginCodeColumns::EXPIRES_AT, typecast: 'datetime')]
    public \DateTimeImmutable $expiresAt;

    #[Column(type: 'integer', name: LoginCodeColumns::ATTEMPTS)]
    public int $attempts;

    #[Column(type: 'datetime', name: LoginCodeColumns::CONSUMED_AT, nullable: true, typecast: 'datetime')]
    public \DateTimeImmutable|null $consumedAt;
}
