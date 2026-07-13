# Примеры кода

## DTO команды

```php
<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Command\Login;

final readonly class LoginCommand
{
    public function __construct(
        public string $email,
        public string $password,
    ) {}
}
```

## Обработчик команды

```php
<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Command\Login;

use App\Shared\Domain\Exception\AuthenticationException;
use App\Modules\User\Domain\ValueObject\Email;
use App\Modules\User\Repository\UserRepository;

final readonly class LoginHandler
{
    public function __construct(
        private UserRepository $userRepository,
    ) {}

    public function handle(LoginCommand $command): LoginResult
    {
        $user = $this->userRepository->findByEmail(Email::from($command->email))
            ?? throw new AuthenticationException('Неверный email или пароль');

        if (!$user->passwordHash->verify($command->password)) {
            throw new AuthenticationException('Неверный email или пароль');
        }

        return new LoginResult(
            accessToken: $user->issueAccessToken(),
            refreshToken: $user->issueRefreshToken(),
        );
    }
}
```

## DTO запроса

```php
<?php

declare(strict_types=1);

namespace App\Modules\User\Application\Query\GetUserProfile;

final readonly class GetUserProfileQuery
{
    public function __construct(
        public string $userId,
    ) {}
}
```

## Typed config

```php
<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Configuration\Payment;

use App\Shared\Infrastructure\Configuration\TypedConfig;

final readonly class PaymentConfig implements TypedConfig
{
    public static function configName(): string
    {
        return 'payment';
    }

    /**
     * @param array<string, PaymentProviderConfig> $providers
     */
    public function __construct(
        public string $default,
        public array $providers,
    ) {}
}
```

```php
<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Configuration\Payment;

final readonly class PaymentProviderConfig
{
    public function __construct(
        public string $dsn,
        public bool $sandbox,
    ) {}
}
```

Тест на `Tests\TestCase` поднимает Spiral kernel, поэтому живёт в suite `Kernel`
(`tests/Kernel`), а не в лёгком `Unit`:

```php
<?php

declare(strict_types=1);

namespace Tests\Kernel\Shared\Infrastructure\Configuration;

use App\Shared\Infrastructure\Configuration\Mapping\ConfigMapper;
use App\Shared\Infrastructure\Configuration\Payment\PaymentConfig;
use Tests\TestCase;

final class PaymentConfigTest extends TestCase
{
    public function testPaymentConfigMapsFromConfigurator(): void
    {
        $config = $this->getContainer()
            ->get(ConfigMapper::class)
            ->map(
                section: PaymentConfig::configName(),
                targetClass: PaymentConfig::class,
            );

        self::assertSame('stripe', $config->default);
        self::assertArrayHasKey('stripe', $config->providers);
    }
}
```

## Обработчик запроса

```php
<?php

declare(strict_types=1);

namespace App\Modules\User\Application\Query\GetUserProfile;

use App\Modules\User\Domain\Entity\User;
use App\Shared\Domain\Exception\NotFoundException;
use App\Modules\User\Repository\UserRepository;

final readonly class GetUserProfileHandler
{
    public function __construct(
        private UserRepository $userRepository,
    ) {}

    public function handle(GetUserProfileQuery $query): User
    {
        return $this->userRepository->findById($query->userId)
            ?? throw new NotFoundException('Пользователь не найден');
    }
}
```

## Контроллер

```php
<?php

declare(strict_types=1);

namespace App\Modules\User\Presentation\Http\Controller;

use App\Modules\User\Application\Query\GetUserProfile\GetUserProfileHandler;
use App\Modules\User\Application\Query\GetUserProfile\GetUserProfileQuery;
use App\Modules\User\Presentation\Http\Resource\UserResource;
use GianTiaga\SpiralOpenApi\Response\DataResponse;
use GianTiaga\SpiralCqrs\QueryBusInterface;
use Spiral\Router\Annotation\Route;

final readonly class UserController
{
    /**
     * @return DataResponse<UserResource>
     */
    #[Route(route: '/api/v1/users/<id>', name: 'api.v1.user.show', methods: ['GET'])]
    public function show(
        string $id,
        GetUserProfileHandler $getUserProfileHandler,
        QueryBusInterface $queryBus,
    ): DataResponse {
        $query = new GetUserProfileQuery(
            userId: $id,
        );

        $user = $queryBus->dispatch(
            query: $query,
            handler: $getUserProfileHandler->handle(...),
        );

        return new DataResponse(UserResource::fromEntity($user));
    }
}
```

## Сущность

```php
<?php

declare(strict_types=1);

namespace App\Modules\User\Domain\Entity;

use App\Shared\Domain\Trait\HasTimestamps;
use App\Shared\Domain\ValueObject\UserId;
use App\Modules\User\Domain\ValueObject\DisplayName;
use App\Modules\User\Domain\ValueObject\Email;
use App\Modules\User\Domain\ValueObject\PasswordHash;
use App\Modules\User\Domain\ValueObject\Username;
use App\Modules\User\Infrastructure\Cycle\UserValueObjectTypecast;
use App\Modules\User\Repository\UserRepository;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\ORM\Parser\Typecast;

#[Entity(
    role: 'user',
    table: 'users',
    repository: UserRepository::class,
    typecast: [Typecast::class, UserValueObjectTypecast::class],
)]
class User
{
    use HasTimestamps;

    #[Column(type: 'uuid', primary: true, typecast: UserId::class)]
    public private(set) UserId $id;

    #[Column(type: 'string', typecast: Email::class)]
    public private(set) Email $email;

    #[Column(type: 'string', typecast: PasswordHash::class)]
    public private(set) PasswordHash $passwordHash;

    #[Column(type: 'string', typecast: Username::class)]
    public private(set) Username $username;

    #[Column(type: 'string', typecast: DisplayName::class)]
    public private(set) DisplayName $name;

    public static function create(
        Email $email,
        PasswordHash $passwordHash,
        Username $username,
        DisplayName $name,
    ): self {
        $user = new self();
        $user->id = UserId::generate();
        $user->email = $email;
        $user->passwordHash = $passwordHash;
        $user->username = $username;
        $user->name = $name;
        $user->initializeTimestamps();

        return $user;
    }

    public function rename(DisplayName $name): void
    {
        $this->name = $name;
        $this->touch();
    }
}
```

## Объект-значение

```php
<?php

declare(strict_types=1);

namespace App\Modules\User\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidDomainValueException;

final readonly class Email implements \Stringable, \JsonSerializable
{
    private function __construct(
        private string $value,
    ) {}

    public static function from(string $value): self
    {
        $email = \mb_strtolower(\trim($value));

        if (!\filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidDomainValueException('Некорректный email');
        }

        return new self($email);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $email): bool
    {
        return $this->value === $email->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function jsonSerialize(): string
    {
        return $this->value;
    }
}
```

## Репозиторий

```php
<?php

declare(strict_types=1);

namespace App\Modules\User\Repository;

use App\Modules\User\Domain\Entity\User;
use App\Modules\User\Domain\ValueObject\Email;
use App\Modules\User\Domain\ValueObject\Username;
use App\Shared\Infrastructure\Cycle\AbstractRepository;

/**
 * @extends AbstractRepository<User>
 */
final class UserRepository extends AbstractRepository
{
    public function findByEmail(Email $email): ?User
    {
        return $this->findOne(['email' => $email->value()]);
    }

    public function findByUsername(Username $username): ?User
    {
        return $this->findOne(['username' => $username->value()]);
    }

    public function existsByUsername(Username $username): bool
    {
        return $this->findByUsername($username) !== null;
    }
}
```

## Ресурс

```php
<?php

declare(strict_types=1);

namespace App\Modules\User\Presentation\Http\Resource;

use App\Modules\User\Domain\Entity\User;
use App\Shared\Presentation\Http\Resource\AbstractResource;

final readonly class UserResource extends AbstractResource
{
    public function __construct(
        public string $id,
        public string $email,
        public string $username,
        public string $name,
        public \DateTimeImmutable $createdAt,
    ) {}

    public static function fromEntity(User $user): self
    {
        return new self(
            id: (string) $user->id,
            email: (string) $user->email,
            username: (string) $user->username,
            name: (string) $user->name,
            createdAt: $user->createdAt,
        );
    }
}
```

## Консольная команда

```php
<?php

declare(strict_types=1);

namespace App\Modules\User\Presentation\Console;

use App\Modules\User\Application\Command\CreateUser\CreateUserCommand;
use App\Modules\User\Application\Command\CreateUser\CreateUserHandler;
use GianTiaga\SpiralCqrs\CommandBusInterface;
use Spiral\Console\Attribute\Argument;
use Spiral\Console\Attribute\AsCommand;
use Spiral\Console\Command;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

#[AsCommand(
    name: 'user:create',
    description: 'Создать пользователя',
)]
final class CreateUserConsoleCommand extends Command
{
    #[Argument(description: 'Email пользователя')]
    public string $email;

    #[Argument(description: 'Имя пользователя')]
    public string $username;

    public function perform(
        CommandBusInterface $commandBus,
        CreateUserHandler $createUserHandler,
    ): int {
        $result = $commandBus->dispatch(
            command: new CreateUserCommand(
                email: $this->email,
                username: $this->username,
            ),
            handler: $createUserHandler->handle(...),
        );

        $this->info(\sprintf('Пользователь создан: %s', $result->userId));

        return SymfonyCommand::SUCCESS;
    }
}
```
