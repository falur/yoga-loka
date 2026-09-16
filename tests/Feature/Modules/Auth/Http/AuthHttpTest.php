<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Auth\Http;

use App\Modules\Auth\Application\Contract\AuthTokenStorageContract;
use App\Modules\Auth\Application\Contract\SecretHasherContract;
use App\Modules\Auth\Application\Result\IssuedTokenPair;
use App\Modules\Auth\Domain\Collection\AuthTokenCollection;
use App\Modules\Auth\Domain\Entity\LoginCode;
use App\Modules\Auth\Domain\Entity\RegistrationTicket;
use App\Modules\Auth\Domain\ValueObject\EmailAddress;
use App\Modules\Auth\Domain\ValueObject\Expiration;
use App\Modules\Auth\Domain\ValueObject\LoginCodeId;
use App\Modules\Auth\Domain\ValueObject\RegistrationTicketId;
use App\Modules\Auth\Domain\ValueObject\SecretHash;
use App\Modules\Auth\Domain\ValueObject\SessionDevice;
use App\Modules\Auth\Domain\Repository\AuthTokenRepository;
use App\Modules\Auth\Domain\Repository\LoginCodeRepository;
use App\Modules\Auth\Infrastructure\Persistence\Cycle\Mapper\LoginCodeMapper;
use App\Modules\Auth\Infrastructure\Persistence\Cycle\Mapper\RegistrationTicketMapper;
use App\Modules\Auth\Infrastructure\Spiral\Auth\SpiralTokenStorage;
use App\Modules\User\Domain\Entity\User;
use App\Modules\User\Domain\ValueObject\Email;
use App\Modules\User\Domain\ValueObject\UserName;
use App\Modules\User\Domain\ValueObject\UserNickname;
use App\Modules\User\Infrastructure\Persistence\Cycle\Mapper\UserMapper;
use App\Modules\User\Domain\Repository\UserRepository;
use App\Shared\Domain\Enum\Locale;
use App\Shared\Domain\ValueObject\UserId;
use Cycle\Database\DatabaseInterface;
use Cycle\ORM\EntityManagerInterface;
use Spiral\Auth\TokenInterface;
use Spiral\Testing\Http\FakeHttp;
use Tests\NonTransactionalDatabaseTestCase;

final class AuthHttpTest extends NonTransactionalDatabaseTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $database = $this->getContainer()->get(DatabaseInterface::class);

        foreach ([
            'auth_tokens',
            'auth_registration_tickets',
            'auth_login_codes',
            'user_bans',
            'reserved_nicknames',
            'users',
            'outbox_events',
        ] as $table) {
            $database->delete($table)->run();
        }
    }

    public function testRequestCodeReturnsSentAndStoresLoginCode(): void
    {
        $response = $this->http()->postJson('/api/v1/auth/code/request', ['email' => 'request@example.com']);

        $response->assertNoContent();
        self::assertNotNull(
            $this->loginCodeRepository()->findActiveByEmail(EmailAddress::fromString('request@example.com')),
        );
    }

    public function testRequestCodeRejectsEmptyEmail(): void
    {
        $this->http()->postJson('/api/v1/auth/code/request', ['email' => ''])->assertUnprocessable();
    }

    public function testRequestCodeRejectsInvalidEmail(): void
    {
        $this->http()->postJson('/api/v1/auth/code/request', ['email' => 'not-email'])->assertUnprocessable();
    }

    public function testVerifyRejectsInvalidEmail(): void
    {
        $this->http()->postJson('/api/v1/auth/code/verify', [
            'email' => 'not-email',
            'code' => '123456',
        ])->assertUnprocessable();
    }

    public function testVerifyExistingUserReturnsTokens(): void
    {
        $this->seedActiveUser('verify-existing@example.com', 'verify.existing');
        $this->seedLoginCode('verify-existing@example.com', '123456');

        $response = $this->http()->postJson('/api/v1/auth/code/verify', [
            'email' => 'verify-existing@example.com',
            'code' => '123456',
        ]);

        $response->assertOk();
        $response->assertBodyContains('"needsProfile":false');
        $response->assertBodyContains('accessToken');
    }

    public function testVerifyNewEmailReturnsRegistrationTicket(): void
    {
        $this->seedLoginCode('verify-new@example.com', '123456');

        $response = $this->http()->postJson('/api/v1/auth/code/verify', [
            'email' => 'verify-new@example.com',
            'code' => '123456',
        ]);

        $response->assertOk();
        $response->assertBodyContains('"needsProfile":true');
        $response->assertBodyContains('registrationTicket');
    }

    public function testVerifyWrongCodeReturns401AndIncrementsAttempts(): void
    {
        $this->seedLoginCode('verify-wrong@example.com', '123456');

        $this->http()->postJson('/api/v1/auth/code/verify', [
            'email' => 'verify-wrong@example.com',
            'code' => '000000',
        ])->assertUnauthorized();

        $this->cleanOrmHeap();
        $loginCode = $this->loginCodeRepository()->findActiveByEmail(EmailAddress::fromString('verify-wrong@example.com'));
        self::assertInstanceOf(LoginCode::class, $loginCode);
        self::assertSame(1, $loginCode->attempts->value());
    }

    public function testVerifyExhaustedAttemptsReturns401(): void
    {
        $this->seedLoginCode('verify-exhausted@example.com', '123456', failedAttempts: 5);

        $this->http()->postJson('/api/v1/auth/code/verify', [
            'email' => 'verify-exhausted@example.com',
            'code' => '123456',
        ])->assertUnauthorized();
    }

    public function testRegisterCreatesActiveUserAndReturnsTokens(): void
    {
        $this->seedRegistrationTicket('register@example.com', 'ticket-http-1');

        $response = $this->http()->postJson('/api/v1/auth/register', [
            'ticket' => 'ticket-http-1',
            'name' => 'Имя Тест',
            'nickname' => 'register.user',
        ]);

        $response->assertOk();
        $response->assertBodyContains('accessToken');

        $this->cleanOrmHeap();
        $user = $this->userRepository()->findByEmail(Email::fromString('register@example.com'));
        self::assertInstanceOf(User::class, $user);
        self::assertTrue($user->isActive());
    }

    public function testRegisterRejectsTakenNicknameAndKeepsTicket(): void
    {
        $this->seedActiveUser('owner@example.com', 'occupied.nick');
        $this->seedRegistrationTicket('register-taken@example.com', 'ticket-http-2');

        $this->http()->postJson('/api/v1/auth/register', [
            'ticket' => 'ticket-http-2',
            'name' => 'Имя Тест',
            'nickname' => 'occupied.nick',
        ])->assertUnprocessable();

        $this->cleanOrmHeap();
        self::assertNotNull(
            $this->registrationTicketRepositoryFindActive('ticket-http-2'),
        );
    }

    public function testRegisterRejectsInvalidName(): void
    {
        $this->seedRegistrationTicket('register-bad-name@example.com', 'ticket-bad-name');

        $this->http()->postJson('/api/v1/auth/register', [
            'ticket' => 'ticket-bad-name',
            'name' => 'Имя123',
            'nickname' => 'valid.nick',
        ])->assertUnprocessable();
    }

    public function testRegisterRejectsInvalidNickname(): void
    {
        $this->seedRegistrationTicket('register-bad-nick@example.com', 'ticket-bad-nick');

        $this->http()->postJson('/api/v1/auth/register', [
            'ticket' => 'ticket-bad-nick',
            'name' => 'Имя Тест',
            'nickname' => 'a',
        ])->assertUnprocessable();
    }

    public function testRegisterRejectsUnknownTicket(): void
    {
        $this->http()->postJson('/api/v1/auth/register', [
            'ticket' => 'missing-ticket',
            'name' => 'Имя Тест',
            'nickname' => 'whoever.user',
        ])->assertUnauthorized();
    }

    public function testRefreshReturnsNewPairAndInvalidatesOldRefresh(): void
    {
        $pair = $this->issueTokenPair();

        $this->http()->postJson('/api/v1/auth/refresh', ['refreshToken' => $pair->refreshToken])->assertOk();
        $this->http()->postJson('/api/v1/auth/refresh', ['refreshToken' => $pair->refreshToken])->assertUnauthorized();
    }

    public function testRefreshRejectsAccessTokenAsRefresh(): void
    {
        $pair = $this->issueTokenPair();

        $this->http()->postJson('/api/v1/auth/refresh', ['refreshToken' => $pair->accessToken])->assertUnauthorized();
    }

    public function testLogoutRevokesSession(): void
    {
        $pair = $this->issueTokenPair();

        $this->http()
            ->withAuthorizationToken($pair->accessToken)
            ->postJson('/api/v1/auth/logout')
            ->assertNoContent();

        $this->http()
            ->withAuthorizationToken($pair->accessToken)
            ->postJson('/api/v1/auth/logout')
            ->assertUnauthorized();
    }

    public function testLogoutWithoutTokenReturns401(): void
    {
        $this->http()->postJson('/api/v1/auth/logout')->assertUnauthorized();
    }

    /**
     * Тело отказа собирает общий обработчик ошибок API из доменного исключения правила сессии
     * и остаётся прежним: перевод ключа `app.auth.unauthenticated` на языке запроса и код 401.
     */
    public function testLogoutWithoutTokenReturnsTranslatedUnauthenticatedBody(): void
    {
        $this->http()
            ->withHeader('Accept-Language', 'en')
            ->postJson('/api/v1/auth/logout')
            ->assertBodySame('{"message":"Authentication required.","code":401}');

        $this->http()
            ->withHeader('Accept-Language', 'ru')
            ->postJson('/api/v1/auth/logout')
            ->assertBodySame('{"message":"Требуется аутентификация.","code":401}');
    }

    /**
     * Публичный маршрут входа работает без сессии и после переезда установления личности
     * на всю группу `api`: переданный заголовок отсутствует, отказа нет.
     */
    public function testPublicRouteStaysAvailableWithoutSession(): void
    {
        $this->http()
            ->postJson('/api/v1/auth/code/request', ['email' => 'public-route@example.com'])
            ->assertNoContent();
    }

    public function testLogoutWithRefreshTokenAsBearerReturns401(): void
    {
        $pair = $this->issueTokenPair();

        $this->http()
            ->withAuthorizationToken($pair->refreshToken)
            ->postJson('/api/v1/auth/logout')
            ->assertUnauthorized();
    }

    public function testRateLimitReturns429AfterExceedingAttempts(): void
    {
        $http = $this->http();

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $http->postJson('/api/v1/auth/code/request', ['email' => 'rate-limit@example.com'])->assertNoContent();
        }

        $http->postJson('/api/v1/auth/code/request', ['email' => 'rate-limit@example.com'])->assertStatus(429);
    }

    public function testRateLimitReturns429OnVerifyAfterTenAttempts(): void
    {
        $this->seedLoginCode('verify-rate-limit@example.com', '123456');
        $http = $this->http();
        $payload = ['email' => 'verify-rate-limit@example.com', 'code' => '000000'];

        // Порог verify = 10 (отличается от code/request = 5): первые 10 запросов проходят
        // middleware (401 от неверного кода), 11-й упирается в rate limit (429).
        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $http->postJson('/api/v1/auth/code/verify', $payload)->assertUnauthorized();
        }

        $http->postJson('/api/v1/auth/code/verify', $payload)->assertStatus(429);
    }

    public function testSessionsListsOwnSessionsWithCurrentFlag(): void
    {
        $userId = UserId::generate();
        $currentPair = $this->issuePairFor(userId: $userId, ip: '203.0.113.10', userAgent: 'CurrentAgent');
        $this->issuePairFor(userId: $userId, ip: '203.0.113.20', userAgent: 'OtherAgent');

        $response = $this->http()
            ->withAuthorizationToken($currentPair->accessToken)
            ->getJson('/api/v1/auth/sessions');

        $response->assertOk();
        $response->assertBodyContains('"ip":"203.0.113.10","device":"CurrentAgent","current":true');
        $response->assertBodyContains('"ip":"203.0.113.20","device":"OtherAgent","current":false');
    }

    public function testSessionsListsSessionWithoutDeviceAsNull(): void
    {
        $pair = $this->issueTokenPair();

        $response = $this->http()
            ->withAuthorizationToken($pair->accessToken)
            ->getJson('/api/v1/auth/sessions');

        $response->assertOk();
        $response->assertBodyContains('"ip":null,"device":null');
    }

    public function testSessionsWithoutTokenReturns401(): void
    {
        $this->http()->getJson('/api/v1/auth/sessions')->assertUnauthorized();
    }

    public function testSessionsWithRefreshTokenAsBearerReturns401(): void
    {
        $pair = $this->issueTokenPair();

        $this->http()
            ->withAuthorizationToken($pair->refreshToken)
            ->getJson('/api/v1/auth/sessions')
            ->assertUnauthorized();
    }

    public function testRevokeSessionRevokesOwnSessionAndDropsItFromList(): void
    {
        $userId = UserId::generate();
        $currentPair = $this->issuePairFor(userId: $userId, ip: '203.0.113.10', userAgent: 'CurrentAgent');
        $targetPair = $this->issuePairFor(userId: $userId, ip: '203.0.113.20', userAgent: 'TargetAgent');
        $targetSessionId = $this->sessionIdOf($targetPair->accessToken);

        $this->http()
            ->withAuthorizationToken($currentPair->accessToken)
            ->deleteJson(\sprintf('/api/v1/auth/sessions/%s', $targetSessionId))
            ->assertNoContent();

        $this->http()
            ->withAuthorizationToken($targetPair->accessToken)
            ->getJson('/api/v1/auth/sessions')
            ->assertUnauthorized();

        $remainingSessions = $this->http()
            ->withAuthorizationToken($currentPair->accessToken)
            ->getJson('/api/v1/auth/sessions');
        $remainingSessions->assertOk();
        self::assertStringContainsString('203.0.113.10', (string) $remainingSessions);
        self::assertStringNotContainsString('203.0.113.20', (string) $remainingSessions);
    }

    public function testRevokeOwnCurrentSessionRevokesItAndInvalidatesToken(): void
    {
        $pair = $this->issueTokenPair();
        $currentSessionId = $this->sessionIdOf($pair->accessToken);

        $this->http()
            ->withAuthorizationToken($pair->accessToken)
            ->deleteJson(\sprintf('/api/v1/auth/sessions/%s', $currentSessionId))
            ->assertNoContent();

        $this->http()
            ->withAuthorizationToken($pair->accessToken)
            ->getJson('/api/v1/auth/sessions')
            ->assertUnauthorized();
    }

    public function testRevokeForeignSessionReturns404(): void
    {
        $ownerPair = $this->issuePairFor(userId: UserId::generate(), ip: '203.0.113.30', userAgent: 'OwnerAgent');
        $foreignSessionId = $this->sessionIdOf($ownerPair->accessToken);
        $attackerPair = $this->issueTokenPair();

        $this->http()
            ->withAuthorizationToken($attackerPair->accessToken)
            ->deleteJson(\sprintf('/api/v1/auth/sessions/%s', $foreignSessionId))
            ->assertStatus(404);
    }

    public function testRevokeWithInvalidSessionIdReturns404(): void
    {
        $pair = $this->issueTokenPair();

        $this->http()
            ->withAuthorizationToken($pair->accessToken)
            ->deleteJson('/api/v1/auth/sessions/not-a-uuid')
            ->assertStatus(404);
    }

    public function testRevokeWithoutTokenReturns401(): void
    {
        $pair = $this->issueTokenPair();
        $sessionId = $this->sessionIdOf($pair->accessToken);

        $this->http()
            ->deleteJson(\sprintf('/api/v1/auth/sessions/%s', $sessionId))
            ->assertUnauthorized();
    }

    public function testVerifyStoresClientDeviceOnIssuedTokens(): void
    {
        $this->seedActiveUser('device-verify@example.com', 'device.verify');
        $this->seedLoginCode('device-verify@example.com', '123456');

        $this->fakeHttp()
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.42'])
            ->withHeader('Accept-Language', 'ru')
            ->withHeader('User-Agent', 'IntegrationAgent/1.0')
            ->postJson('/api/v1/auth/code/verify', [
                'email' => 'device-verify@example.com',
                'code' => '123456',
            ])
            ->assertOk();

        $this->cleanOrmHeap();
        $deviceTokens = $this->activeTokensFor('device-verify@example.com');

        self::assertGreaterThan(0, $deviceTokens->count());
        foreach ($deviceTokens as $deviceToken) {
            self::assertSame('203.0.113.42', $deviceToken->ip->toNullableString());
            self::assertSame('IntegrationAgent/1.0', $deviceToken->userAgent->toNullableString());
        }
    }

    public function testRefreshStoresClientDeviceFromCurrentRequestOnNewPair(): void
    {
        $userId = UserId::generate();
        $initialPair = $this->issuePairFor(userId: $userId, ip: '198.51.100.1', userAgent: 'OldAgent/1.0');

        $this->fakeHttp()
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.55'])
            ->withHeader('Accept-Language', 'ru')
            ->withHeader('User-Agent', 'RefreshAgent/2.0')
            ->postJson('/api/v1/auth/refresh', ['refreshToken' => $initialPair->refreshToken])
            ->assertOk();

        $this->cleanOrmHeap();
        $rotatedTokens = $this->activeTokensForUser($userId);

        self::assertGreaterThan(0, $rotatedTokens->count());
        foreach ($rotatedTokens as $rotatedToken) {
            self::assertSame('203.0.113.55', $rotatedToken->ip->toNullableString());
            self::assertSame('RefreshAgent/2.0', $rotatedToken->userAgent->toNullableString());
        }
    }

    public function testSystemHealthRouteStillWorks(): void
    {
        $this->http()->getJson('/api/v1/health')->assertOk();
    }

    private function http(): FakeHttp
    {
        return $this->fakeHttp()
            ->withServerVariables(['REMOTE_ADDR' => $this->uniqueClientIp()])
            ->withHeader('Accept-Language', 'ru');
    }

    private function uniqueClientIp(): string
    {
        return \sprintf(
            '10.%d.%d.%d',
            \random_int(min: 0, max: 255),
            \random_int(min: 0, max: 255),
            \random_int(min: 1, max: 254),
        );
    }

    private function seedActiveUser(string $email, string $nickname): void
    {
        $user = User::create(
            name: UserName::fromString('Тест Пользователь'),
            email: Email::fromString($email),
            nickname: UserNickname::fromString($nickname),
            locale: Locale::Ru,
        );
        $user->confirmEmail();

        $this->entityManager()->persist((new UserMapper())->toCycleEntity($user));
        $this->entityManager()->run();
    }

    private function seedLoginCode(string $email, string $code, int $failedAttempts = 0): void
    {
        $now = new \DateTimeImmutable();
        $loginCode = LoginCode::issue(
            id: LoginCodeId::generate(),
            email: EmailAddress::fromString($email),
            codeHash: SecretHash::fromString($this->secretHasher()->hash($code)),
            expiration: Expiration::after(now: $now, seconds: 600),
            now: $now,
        );

        for ($attempt = 0; $attempt < $failedAttempts; $attempt++) {
            $loginCode->registerFailedAttempt($now);
        }

        $this->entityManager()->persist((new LoginCodeMapper())->toCycleEntity($loginCode));
        $this->entityManager()->run();
    }

    private function seedRegistrationTicket(string $email, string $ticketRaw): void
    {
        $now = new \DateTimeImmutable();
        $ticket = RegistrationTicket::issue(
            id: RegistrationTicketId::generate(),
            email: EmailAddress::fromString($email),
            ticketHash: SecretHash::fromString($this->secretHasher()->hash($ticketRaw)),
            expiration: Expiration::after(now: $now, seconds: 900),
            now: $now,
        );

        $this->entityManager()->persist((new RegistrationTicketMapper())->toCycleEntity($ticket));
        $this->entityManager()->run();
    }

    private function registrationTicketRepositoryFindActive(string $ticketRaw): RegistrationTicket|null
    {
        return $this->getContainer()
            ->get(\App\Modules\Auth\Domain\Repository\RegistrationTicketRepository::class)
            ->findActiveByHashForUpdate(SecretHash::fromString($this->secretHasher()->hash($ticketRaw)));
    }

    private function issueTokenPair(): IssuedTokenPair
    {
        return $this->getContainer()->get(AuthTokenStorageContract::class)
            ->issuePair(userId: UserId::generate(), device: SessionDevice::unknown());
    }

    private function issuePairFor(UserId $userId, string $ip, string $userAgent): IssuedTokenPair
    {
        return $this->getContainer()->get(AuthTokenStorageContract::class)
            ->issuePair(userId: $userId, device: SessionDevice::fromRequest(ip: $ip, userAgent: $userAgent));
    }

    private function sessionIdOf(string $accessToken): string
    {
        $token = $this->getContainer()->get(SpiralTokenStorage::class)->load($accessToken);
        self::assertInstanceOf(TokenInterface::class, $token);

        return $token->getPayload()['sessionID'];
    }

    private function activeTokensFor(string $email): AuthTokenCollection
    {
        $user = $this->userRepository()->findByEmail(Email::fromString($email));
        self::assertInstanceOf(User::class, $user);

        return $this->authTokenRepository()->findActiveByUserId($user->id, new \DateTimeImmutable());
    }

    private function activeTokensForUser(UserId $userId): AuthTokenCollection
    {
        return $this->authTokenRepository()->findActiveByUserId($userId, new \DateTimeImmutable());
    }

    private function authTokenRepository(): AuthTokenRepository
    {
        return $this->getContainer()->get(AuthTokenRepository::class);
    }

    private function secretHasher(): SecretHasherContract
    {
        return $this->getContainer()->get(SecretHasherContract::class);
    }

    private function entityManager(): EntityManagerInterface
    {
        return $this->getContainer()->get(EntityManagerInterface::class);
    }

    private function loginCodeRepository(): LoginCodeRepository
    {
        return $this->getContainer()->get(LoginCodeRepository::class);
    }

    private function userRepository(): UserRepository
    {
        return $this->getContainer()->get(UserRepository::class);
    }
}
