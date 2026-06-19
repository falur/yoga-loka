<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\User\Application;

use App\Modules\User\Application\Query\GetUserPublicProfile\GetUserPublicProfileHandler;
use App\Modules\User\Application\Query\GetUserPublicProfile\GetUserPublicProfileQuery;
use App\Modules\User\Domain\ValueObject\UserAvatar;
use App\Shared\Domain\Enum\Locale;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\ValueObject\UserId;

final class GetUserPublicProfileHandlerTest extends UserApplicationTestCase
{
    public function testReturnsDefaultAvatarAndLocaleWhenUserHasNoAvatar(): void
    {
        $user = $this->persistUser(locale: Locale::En);

        $view = $this->handler()->handle(new GetUserPublicProfileQuery($user->id->value()));

        self::assertSame($user->id->value(), $view->userId);
        self::assertSame('Йога Тест', $view->name);
        self::assertSame($this->defaultAvatarUrl(), $view->avatarUrl);
        self::assertSame('en', $view->locale);
    }

    public function testReturnsRealAvatarUrlWhenMediaReady(): void
    {
        $media = $this->persistReadyPublicMedia();
        $user = $this->persistUser(avatar: UserAvatar::pointingTo($media->id->value()));

        $view = $this->handler()->handle(new GetUserPublicProfileQuery($user->id->value()));

        self::assertSame(self::STUBBED_AVATAR_URL, $view->avatarUrl);
        self::assertSame('ru', $view->locale);
    }

    public function testFallsBackToDefaultWhenAvatarMediaNotReady(): void
    {
        $media = $this->persistNotReadyMedia();
        $user = $this->persistUser(avatar: UserAvatar::pointingTo($media->id->value()));

        $view = $this->handler()->handle(new GetUserPublicProfileQuery($user->id->value()));

        self::assertSame($this->defaultAvatarUrl(), $view->avatarUrl);
    }

    public function testThrowsWhenUserNotFound(): void
    {
        $this->expectException(NotFoundException::class);

        $this->handler()->handle(new GetUserPublicProfileQuery(UserId::generate()->value()));
    }

    private function handler(): GetUserPublicProfileHandler
    {
        return new GetUserPublicProfileHandler(
            userRepository: $this->userRepository(),
            assembler: $this->profileHandlerAssembler(),
        );
    }
}
