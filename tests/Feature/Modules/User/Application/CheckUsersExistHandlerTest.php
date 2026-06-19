<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\User\Application;

use App\Modules\User\Application\Query\CheckUsersExist\CheckUsersExistHandler;
use App\Modules\User\Application\Query\CheckUsersExist\CheckUsersExistQuery;
use App\Shared\Domain\ValueObject\UserId;

final class CheckUsersExistHandlerTest extends UserApplicationTestCase
{
    public function testReturnsTrueWhenAllRequestedUsersExist(): void
    {
        $first = $this->persistUser();
        $second = $this->persistUser();

        $allExist = $this->handler()->handle(new CheckUsersExistQuery(
            userIds: [$first->id->value(), $second->id->value(), $first->id->value()],
        ));

        self::assertTrue($allExist);
    }

    public function testReturnsFalseWhenAnyRequestedUserIsMissing(): void
    {
        $existing = $this->persistUser();

        $allExist = $this->handler()->handle(new CheckUsersExistQuery(
            userIds: [$existing->id->value(), UserId::generate()->value()],
        ));

        self::assertFalse($allExist);
    }

    public function testReturnsTrueForEmptyInput(): void
    {
        $allExist = $this->handler()->handle(new CheckUsersExistQuery(userIds: []));

        self::assertTrue($allExist);
    }

    private function handler(): CheckUsersExistHandler
    {
        return new CheckUsersExistHandler(userRepository: $this->userRepository());
    }
}
