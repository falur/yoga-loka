<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\User\PublicApi;

use App\Modules\User\Domain\ValueObject\UserAvatar;
use App\Shared\Domain\Enum\Locale;
use App\Modules\User\Domain\Exception\EmailAlreadyTakenException;
use App\Modules\User\Domain\Exception\UserNotFoundException;
use App\Shared\Domain\ValueObject\UserId;
use Tests\Feature\Modules\User\Application\UserApplicationTestCase;

/**
 * Публичный контракт User: все пять операций раскладываются в существующие сценарии модуля, а их
 * внутренние формы ответа переводятся в публичные DTO. Проверяется и мягкость пакетного чтения
 * (пропавший просто отсутствует в наборе), и строгость одиночного (404).
 */
final class UserProviderTest extends UserApplicationTestCase
{
    public function testCreateUserReturnsIdentifierOfCreatedUser(): void
    {
        $created = $this->userProvider()->createUser(
            email: 'public.contract@example.com',
            name: 'Йога Тест',
            nickname: 'public.contract',
            locale: 'en',
        );

        $this->cleanOrmHeap();
        $profile = $this->userProvider()->profile($created->userId);

        self::assertSame($created->userId, $profile->userId);
        self::assertSame('Йога Тест', $profile->name);
        self::assertSame(Locale::En, $profile->locale);
    }

    public function testCreateUserRejectsTakenEmail(): void
    {
        $existing = $this->persistUser();
        $this->cleanOrmHeap();

        $this->expectException(EmailAlreadyTakenException::class);
        $this->expectExceptionMessage('app.user.email_taken');

        $this->userProvider()->createUser(
            email: $existing->email->value(),
            name: 'Йога Тест',
            nickname: 'another.nickname',
            locale: 'ru',
        );
    }

    public function testFindForSignInReturnsIdentifierAndSignInRight(): void
    {
        $user = $this->persistUser(confirmed: true);
        $this->cleanOrmHeap();

        $signIn = $this->userProvider()->findForSignIn($user->email->value());

        self::assertNotNull($signIn);
        self::assertSame($user->id->value(), $signIn->userId);
        self::assertTrue($signIn->canSignIn);
    }

    public function testFindForSignInReturnsNullForUnknownEmail(): void
    {
        self::assertNull($this->userProvider()->findForSignIn('nobody.here@example.com'));
    }

    public function testExistsAllIsTrueOnlyWhenEveryRequestedUserExists(): void
    {
        $first = $this->persistUser();
        $second = $this->persistUser();
        $this->cleanOrmHeap();

        $provider = $this->userProvider();

        self::assertTrue($provider->existsAll([$first->id->value(), $second->id->value()]));
        self::assertFalse($provider->existsAll([$first->id->value(), UserId::generate()->value()]));
        // Пустой набор существует: к пользователям обращаться незачем.
        self::assertTrue($provider->existsAll([]));
    }

    public function testProfileCarriesAvatarAndLocale(): void
    {
        $media = $this->persistReadyPublicMedia();
        $user = $this->persistUser(avatar: UserAvatar::pointingTo($media->id->value()), locale: Locale::En);
        $this->cleanOrmHeap();

        $profile = $this->userProvider()->profile($user->id->value());

        self::assertSame($user->id->value(), $profile->userId);
        self::assertNotNull($profile->avatar);
        self::assertSame($media->id->value(), $profile->avatar->id);
        self::assertNotNull($profile->avatar->original);
        self::assertSame(self::STUBBED_AVATAR_URL, $profile->avatar->original->url);
        self::assertSame(Locale::En, $profile->locale);
    }

    public function testProfileThrowsNotFoundForUnknownUser(): void
    {
        $this->expectException(UserNotFoundException::class);
        $this->expectExceptionMessage('app.user.not_found');

        $this->userProvider()->profile(UserId::generate()->value());
    }

    public function testProfilesByIdsIsKeyedByUserIdAndOmitsMissing(): void
    {
        $first = $this->persistUser();
        $second = $this->persistUser();
        $missing = UserId::generate()->value();
        $this->cleanOrmHeap();

        $profiles = $this->userProvider()->profilesByIds([
            $first->id->value(),
            $missing,
            $second->id->value(),
        ]);

        self::assertCount(2, $profiles);
        self::assertNotNull($profiles->get($first->id->value()));
        self::assertNotNull($profiles->get($second->id->value()));
        // Пропавший просто отсутствует в наборе: решение, что с ним делать, принимает потребитель.
        self::assertNull($profiles->get($missing));
        self::assertNull($profiles->get($first->id->value())->avatar);
    }

    public function testProfilesByIdsReturnsEmptyCollectionForEmptyBatch(): void
    {
        self::assertCount(0, $this->userProvider()->profilesByIds([]));
    }
}
