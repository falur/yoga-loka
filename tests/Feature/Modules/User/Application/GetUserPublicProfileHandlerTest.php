<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\User\Application;

use App\Modules\Media\Public\Enum\MediaConversionKind;
use App\Modules\Media\Public\Enum\MediaImageConversionType;
use App\Modules\User\Application\Query\GetUserPublicProfile\GetUserPublicProfileHandler;
use App\Modules\User\Application\Query\GetUserPublicProfile\GetUserPublicProfileQuery;
use App\Modules\User\Domain\ValueObject\UserAvatar;
use App\Shared\Domain\Enum\Locale;
use App\Modules\User\Domain\Exception\UserNotFoundException;
use App\Shared\Domain\ValueObject\UserId;

final class GetUserPublicProfileHandlerTest extends UserApplicationTestCase
{
    public function testReturnsNullAvatarAndLocaleWhenUserHasNoAvatar(): void
    {
        $user = $this->persistUser(locale: Locale::En);

        $view = $this->handler()->handle(new GetUserPublicProfileQuery($user->id->value()));

        self::assertSame($user->id->value(), $view->userId);
        self::assertSame('Йога Тест', $view->name);
        // Аватара нет: сервер не выдумывает заглушку, дефолт ставит клиент.
        self::assertNull($view->avatar);
        self::assertSame(Locale::En, $view->locale);
    }

    public function testReturnsRealAvatarUrlWhenMediaReady(): void
    {
        $media = $this->persistReadyPublicMedia();
        $user = $this->persistUser(avatar: UserAvatar::pointingTo($media->id->value()));

        $view = $this->handler()->handle(new GetUserPublicProfileQuery($user->id->value()));

        self::assertSame(self::STUBBED_AVATAR_URL, $view->avatar->original->url);
        self::assertCount(0, $view->avatar->conversions);
        self::assertSame(Locale::Ru, $view->locale);
    }

    public function testReturnsAvatarConversionsSoClientChoosesWhatToShow(): void
    {
        $media = $this->persistReadyPublicMedia();
        $this->persistThumbnailConversion($media);
        $user = $this->persistUser(avatar: UserAvatar::pointingTo($media->id->value()));
        $this->cleanOrmHeap();

        $view = $this->handler()->handle(new GetUserPublicProfileQuery($user->id->value()));

        // Оригинал по-прежнему одиночной ссылкой, а конверсии — отдельным набором для выбора на клиенте.
        self::assertSame(self::STUBBED_AVATAR_URL, $view->avatar->original->url);
        self::assertCount(1, $view->avatar->conversions);

        $conversion = $view->avatar->conversions[0];
        self::assertSame(MediaConversionKind::Image, $conversion->kind);
        self::assertSame(MediaImageConversionType::Thumbnail, $conversion->type);
        self::assertSame(self::STUBBED_AVATAR_URL, $conversion->url);
        self::assertNull($conversion->expiresAt);
    }

    public function testReturnsNullAvatarWhenAvatarMediaNotReady(): void
    {
        $media = $this->persistNotReadyMedia();
        $user = $this->persistUser(avatar: UserAvatar::pointingTo($media->id->value()));

        $view = $this->handler()->handle(new GetUserPublicProfileQuery($user->id->value()));

        self::assertNull($view->avatar);
    }

    public function testReturnsNullAvatarWhenAvatarOriginalRemoved(): void
    {
        // Смысловой центр changeset на границе аватара: оригинал удалён -> getUrls отдаёт набор с
        // original = null -> ассемблер отдаёт null. Регресс-гард на случай, если оригинал снова начнёт
        // отдавать (устаревшую) ссылку для readyOriginalRemoved. Конверсия у медиа есть, но отказ —
        // всё или ничего: конверсии удалённого оригинала не подмешиваются в отдельный аватар.
        $media = $this->persistReadyOriginalRemovedMedia();
        $this->persistThumbnailConversion($media);
        $user = $this->persistUser(avatar: UserAvatar::pointingTo($media->id->value()));
        $this->cleanOrmHeap();

        $view = $this->handler()->handle(new GetUserPublicProfileQuery($user->id->value()));

        self::assertNull($view->avatar);
    }

    public function testThrowsWhenUserNotFound(): void
    {
        $this->expectException(UserNotFoundException::class);

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
