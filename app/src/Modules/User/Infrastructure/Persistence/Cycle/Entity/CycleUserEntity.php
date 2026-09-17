<?php

declare(strict_types=1);

namespace App\Modules\User\Infrastructure\Persistence\Cycle\Entity;

use App\Modules\User\Domain\Enum\UserStatus;
use App\Modules\User\Domain\Enum\UserVerification;
use App\Modules\User\Infrastructure\Persistence\Cycle\Columns\UserColumns;
use App\Modules\User\Infrastructure\Persistence\Cycle\Repository\CycleUserRepository;
use App\Shared\Domain\Enum\Locale;
use App\Shared\Infrastructure\Persistence\Cycle\HasTimestamps;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\ORM\Parser\Typecast;

#[Entity(
    role: 'user',
    table: UserColumns::TABLE,
    repository: CycleUserRepository::class,
    typecast: [Typecast::class],
)]
final class CycleUserEntity
{
    use HasTimestamps;

    #[Column(type: 'uuid', name: UserColumns::ID, primary: true)]
    public string $id;

    #[Column(type: 'string(100)', name: UserColumns::NAME)]
    public string $name;

    #[Column(type: 'string(100)', name: UserColumns::SPIRITUAL_NAME, nullable: true)]
    public string|null $spiritualName;

    #[Column(type: 'text', name: UserColumns::BIO, nullable: true)]
    public string|null $bio;

    #[Column(type: 'string(100)', name: UserColumns::LOCATION, nullable: true)]
    public string|null $location;

    #[Column(type: 'string(254)', name: UserColumns::EMAIL)]
    public string $email;

    #[Column(type: 'string(30)', name: UserColumns::NICKNAME)]
    public string $nickname;

    #[Column(type: 'uuid', name: UserColumns::AVATAR_MEDIA_ID, nullable: true)]
    public string|null $avatarMediaId;

    #[Column(type: 'string(32)', name: UserColumns::VERIFICATION, typecast: UserVerification::class)]
    public UserVerification $verification;

    #[Column(type: 'string(32)', name: UserColumns::STATUS, typecast: UserStatus::class)]
    public UserStatus $status;

    #[Column(type: 'string(8)', name: UserColumns::LOCALE, typecast: Locale::class)]
    public Locale $locale;

    #[Column(type: 'datetime', name: UserColumns::DELETED_AT, nullable: true, typecast: 'datetime')]
    public \DateTimeImmutable|null $deletedAt;
}
