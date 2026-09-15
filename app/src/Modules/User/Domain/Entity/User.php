<?php

declare(strict_types=1);

namespace App\Modules\User\Domain\Entity;

use App\Modules\User\Domain\Enum\UserStatus;
use App\Modules\User\Domain\Enum\UserVerification;
use App\Modules\User\Domain\ValueObject\Email;
use App\Modules\User\Domain\ValueObject\UserAvatar;
use App\Modules\User\Domain\ValueObject\UserBio;
use App\Modules\User\Domain\ValueObject\UserDeletion;
use App\Modules\User\Domain\ValueObject\UserLocation;
use App\Modules\User\Domain\ValueObject\UserName;
use App\Modules\User\Domain\ValueObject\UserNickname;
use App\Modules\User\Domain\ValueObject\UserSpiritualName;
use App\Modules\User\Infrastructure\Persistence\Cycle\Typecast\UserAvatarTypecast;
use App\Modules\User\Infrastructure\Persistence\Cycle\Typecast\UserBioTypecast;
use App\Modules\User\Infrastructure\Persistence\Cycle\Typecast\UserDeletionTypecast;
use App\Modules\User\Infrastructure\Persistence\Cycle\Typecast\UserLocationTypecast;
use App\Modules\User\Infrastructure\Persistence\Cycle\Typecast\UserSpiritualNameTypecast;
use App\Modules\User\Repository\UserRepository;
use App\Shared\Domain\Enum\Locale;
use App\Shared\Infrastructure\Persistence\Cycle\HasTimestamps;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Persistence\Cycle\ValueObjectCast;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\ORM\Parser\Typecast;

#[Entity(
    role: 'user',
    table: 'users',
    repository: UserRepository::class,
    typecast: [Typecast::class, ValueObjectCast::class],
)]
final class User
{
    use HasTimestamps;

    #[Column(type: 'uuid', primary: true, typecast: UserId::class)]
    public private(set) UserId $id;

    #[Column(type: 'string(100)', typecast: UserName::class)]
    public private(set) UserName $name;

    #[Column(type: 'string(100)', name: 'spiritual_name', nullable: true, typecast: UserSpiritualNameTypecast::class)]
    public private(set) UserSpiritualName $spiritualName;

    #[Column(type: 'text', nullable: true, typecast: UserBioTypecast::class)]
    public private(set) UserBio $bio;

    #[Column(type: 'string(100)', nullable: true, typecast: UserLocationTypecast::class)]
    public private(set) UserLocation $location;

    #[Column(type: 'string(254)', typecast: Email::class)]
    public private(set) Email $email;

    #[Column(type: 'string(30)', typecast: UserNickname::class)]
    public private(set) UserNickname $nickname;

    #[Column(type: 'uuid', name: 'avatar_media_id', nullable: true, typecast: UserAvatarTypecast::class)]
    public private(set) UserAvatar $avatar;

    #[Column(type: 'string(32)', typecast: UserVerification::class)]
    public private(set) UserVerification $verification;

    #[Column(type: 'string(32)', typecast: UserStatus::class)]
    public private(set) UserStatus $status;

    #[Column(type: 'string(8)', typecast: Locale::class)]
    public private(set) Locale $locale;

    #[Column(type: 'datetime', name: 'deleted_at', nullable: true, typecast: UserDeletionTypecast::class)]
    public private(set) UserDeletion $deletion;

    public static function create(
        UserName $name,
        Email $email,
        UserNickname $nickname,
        Locale $locale,
    ): self {
        $user = new self();
        $user->id = UserId::generate();
        $user->name = $name;
        $user->spiritualName = UserSpiritualName::none();
        $user->bio = UserBio::none();
        $user->location = UserLocation::none();
        $user->email = $email;
        $user->nickname = $nickname;
        $user->avatar = UserAvatar::none();
        $user->verification = UserVerification::Unverified;
        $user->status = UserStatus::WaitingEmailConfirmation;
        $user->locale = $locale;
        $user->deletion = UserDeletion::active();
        $user->initializeTimestamps();

        return $user;
    }

    public function confirmEmail(): void
    {
        $this->status = UserStatus::Active;
        $this->touch();
    }

    public function changeEmail(Email $email): void
    {
        $this->email = $email;
        $this->status = UserStatus::WaitingEmailConfirmation;
        $this->touch();
    }

    public function changeNickname(UserNickname $nickname): void
    {
        $this->nickname = $nickname;
        $this->touch();
    }

    public function rename(UserName $name): void
    {
        $this->name = $name;
        $this->touch();
    }

    public function changeSpiritualName(UserSpiritualName $spiritualName): void
    {
        $this->spiritualName = $spiritualName;
        $this->touch();
    }

    public function changeBio(UserBio $bio): void
    {
        $this->bio = $bio;
        $this->touch();
    }

    public function changeLocation(UserLocation $location): void
    {
        $this->location = $location;
        $this->touch();
    }

    public function changeLocale(Locale $locale): void
    {
        $this->locale = $locale;
        $this->touch();
    }

    public function setAvatar(UserAvatar $avatar): void
    {
        $this->avatar = $avatar;
        $this->touch();
    }

    public function removeAvatar(): void
    {
        $this->avatar = UserAvatar::none();
        $this->touch();
    }

    public function verify(): void
    {
        $this->verification = UserVerification::Verified;
        $this->touch();
    }

    public function unverify(): void
    {
        $this->verification = UserVerification::Unverified;
        $this->touch();
    }

    public function ban(): void
    {
        $this->status = UserStatus::Banned;
        $this->touch();
    }

    public function unban(): void
    {
        $this->status = UserStatus::Active;
        $this->touch();
    }

    public function markDeleted(\DateTimeImmutable $now): void
    {
        $this->deletion = UserDeletion::at($now);
        $this->status = UserStatus::Deleted;
        $this->touch(now: $now);
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    public function isVerified(): bool
    {
        return $this->verification === UserVerification::Verified;
    }

    public function isBanned(): bool
    {
        return $this->status === UserStatus::Banned;
    }

    public function isDeleted(): bool
    {
        return $this->status === UserStatus::Deleted || $this->deletion->isDeleted();
    }
}
