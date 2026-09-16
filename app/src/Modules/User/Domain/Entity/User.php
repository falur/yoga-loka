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
use App\Shared\Domain\Enum\Locale;
use App\Shared\Domain\Trait\HasTimestamps;
use App\Shared\Domain\ValueObject\UserId;

final class User
{
    use HasTimestamps;

    public private(set) UserId $id;

    public private(set) UserName $name;

    public private(set) UserSpiritualName $spiritualName;

    public private(set) UserBio $bio;

    public private(set) UserLocation $location;

    public private(set) Email $email;

    public private(set) UserNickname $nickname;

    public private(set) UserAvatar $avatar;

    public private(set) UserVerification $verification;

    public private(set) UserStatus $status;

    public private(set) Locale $locale;

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

    public static function restore(
        UserId $id,
        UserName $name,
        UserSpiritualName $spiritualName,
        UserBio $bio,
        UserLocation $location,
        Email $email,
        UserNickname $nickname,
        UserAvatar $avatar,
        UserVerification $verification,
        UserStatus $status,
        Locale $locale,
        UserDeletion $deletion,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        $user = new self();
        $user->id = $id;
        $user->name = $name;
        $user->spiritualName = $spiritualName;
        $user->bio = $bio;
        $user->location = $location;
        $user->email = $email;
        $user->nickname = $nickname;
        $user->avatar = $avatar;
        $user->verification = $verification;
        $user->status = $status;
        $user->locale = $locale;
        $user->deletion = $deletion;
        $user->createdAt = $createdAt;
        $user->updatedAt = $updatedAt;

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
