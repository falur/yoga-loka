<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\User\Application;

use App\Modules\User\Application\Dto\UserPublicProfileView;
use App\Modules\User\Application\Query\GetUserPublicProfiles\GetUserPublicProfilesHandler;
use App\Modules\User\Application\Query\GetUserPublicProfiles\GetUserPublicProfilesQuery;
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
            ->map(static fn(UserPublicProfileView $profile): string => $profile->userId)
            ->all();

        self::assertContains($first->id->value(), $userIds);
        self::assertContains($second->id->value(), $userIds);
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
            assembler: $this->profileHandlerAssembler(),
        );
    }
}
