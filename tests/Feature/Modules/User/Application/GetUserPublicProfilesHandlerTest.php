<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\User\Application;

use App\Modules\User\Application\Query\GetUserPublicProfiles\GetUserPublicProfilesHandler;
use App\Modules\User\Application\Query\GetUserPublicProfiles\GetUserPublicProfilesQuery;
use App\Modules\User\Application\Result\UserProfileResult;
use App\Modules\User\Domain\ValueObject\UserAvatar;
use App\Shared\Domain\ValueObject\UserId;

final class GetUserPublicProfilesHandlerTest extends UserApplicationTestCase
{
    public function testReturnsProfilesForFoundUsersExcludingMissing(): void
    {
        $first = $this->persistUser();
        $second = $this->persistUser();

        $profiles = $this->handler()->handle(new GetUserPublicProfilesQuery(
            userIds: [$first->id->value(), UserId::generate()->value(), $second->id->value()],
        ));

        self::assertCount(2, $profiles);

        $userIds = $profiles
            ->map(static fn(UserProfileResult $profile): string => $profile->userId)
            ->all();

        self::assertContains($first->id->value(), $userIds);
        self::assertContains($second->id->value(), $userIds);
    }

    /**
     * Аватары набора читаются одним пакетным обращением к Media: у пользователя с аватаром в ответе
     * стоит ссылка, у пользователя без аватара — null, а вызов к соседу всё равно один.
     */
    public function testResolvesAvatarsOfBatchInSingleMediaCall(): void
    {
        $media = $this->persistReadyPublicMedia();
        $withAvatar = $this->persistUser(avatar: UserAvatar::pointingTo($media->id->value()));
        $withoutAvatar = $this->persistUser();

        $profiles = $this->handler()->handle(new GetUserPublicProfilesQuery(
            userIds: [$withAvatar->id->value(), $withoutAvatar->id->value()],
        ));

        $byUserId = [];
        foreach ($profiles as $profile) {
            $byUserId[$profile->userId] = $profile;
        }

        self::assertSame(self::STUBBED_AVATAR_URL, $byUserId[$withAvatar->id->value()]->avatar->original->url);
        self::assertNull($byUserId[$withoutAvatar->id->value()]->avatar);
    }

    public function testReturnsEmptyCollectionForEmptyInput(): void
    {
        $profiles = $this->handler()->handle(new GetUserPublicProfilesQuery(userIds: []));

        self::assertCount(0, $profiles);
    }

    private function handler(): GetUserPublicProfilesHandler
    {
        return new GetUserPublicProfilesHandler(
            userRepository: $this->userRepository(),
            media: $this->mediaContract(),
        );
    }
}
