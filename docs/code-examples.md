# Примеры кода

## DTO команды

```php
<?php

declare(strict_types=1);

namespace App\Application\Command\Auth\Login;

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

namespace App\Application\Command\Auth\Login;

use App\Domain\Exception\AuthenticationException;
use App\Domain\ValueObject\Email;
use App\Repository\UserRepository;

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

namespace App\Application\Query\User\GetUserProfile;

final readonly class GetUserProfileQuery
{
    public function __construct(
        public string $userId,
    ) {}
}
```

## Обработчик запроса

```php
<?php

declare(strict_types=1);

namespace App\Application\Query\User\GetUserProfile;

use App\Domain\Entity\User;
use App\Domain\Exception\NotFoundException;
use App\Repository\UserRepository;

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

namespace App\Endpoint\Api\V1\Controller;

use App\Application\Query\User\GetUserProfile\GetUserProfileHandler;
use App\Application\Query\User\GetUserProfile\GetUserProfileQuery;
use App\Endpoint\Api\V1\Resource\UserResource;
use App\Endpoint\Api\V1\Response\DataResponse;
use App\Infrastructure\Bus\QueryBusInterface;
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
        $user = $queryBus->dispatch(
            static fn() => $getUserProfileHandler->handle(
                new GetUserProfileQuery(
                    userId: $id,
                ),
            ),
        );

        return new DataResponse(UserResource::fromEntity($user));
    }
}
```

## Сущность

```php
<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use App\Domain\Trait\HasTimestamps;
use App\Domain\Trait\HasUuid;
use App\Domain\ValueObject\DisplayName;
use App\Domain\ValueObject\Email;
use App\Domain\ValueObject\PasswordHash;
use App\Domain\ValueObject\Username;
use App\Infrastructure\Cycle\ValueObjectCast;
use App\Repository\UserRepository;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\ORM\Parser\Typecast;

#[Entity(
    role: 'user',
    table: 'users',
    repository: UserRepository::class,
    typecast: [Typecast::class, ValueObjectCast::class],
)]
class User
{
    use HasUuid;
    use HasTimestamps;

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
        $user->initUuid();
        $user->initTimestamps();
        $user->email = $email;
        $user->passwordHash = $passwordHash;
        $user->username = $username;
        $user->name = $name;

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

namespace App\Domain\ValueObject;

use App\Domain\Exception\ValidationException;

final readonly class Email implements \Stringable, \JsonSerializable
{
    private function __construct(
        private string $value,
    ) {}

    public static function from(string $value): self
    {
        $email = \mb_strtolower(\trim($value));

        if (!\filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException('Некорректный email');
        }

        return new self($email);
    }

    public static function fromDatabase(string $value): self
    {
        return new self($value);
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

namespace App\Repository;

use App\Domain\Entity\User;
use App\Domain\ValueObject\Email;
use App\Domain\ValueObject\Username;
use Cycle\ORM\Select\Repository;

/**
 * @extends Repository<User>
 */
final class UserRepository extends Repository
{
    public function findByEmail(Email $email): ?User
    {
        return $this->findOne(['email' => (string) $email]);
    }

    public function findByUsername(Username $username): ?User
    {
        return $this->findOne(['username' => $username]);
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

namespace App\Endpoint\Api\V1\Resource;

use App\Domain\Entity\User;

final readonly class UserResource extends AbstractResource
{
    public function __construct(
        public string $id,
        public string $email,
        public string $username,
        public string $name,
        public string $createdAt,
    ) {}

    public static function fromEntity(User $user): self
    {
        return new self(
            id: (string) $user->id,
            email: (string) $user->email,
            username: (string) $user->username,
            name: (string) $user->name,
            createdAt: $user->createdAt->format('c'),
        );
    }
}
```
