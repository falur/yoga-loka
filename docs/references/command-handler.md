# Command handler

## Назначение

Обработчик команды координирует один сценарий изменения состояния.

## Когда применять

Применяй для создания, изменения или удаления бизнес-состояния.

```php
<?php

declare(strict_types=1);

namespace App\Modules\User\Application\Command\RenameUser;

use App\Modules\User\Domain\Repository\UserRepository;
use App\Modules\User\Domain\ValueObject\UserDisplayName;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\ValueObject\UserId;
use GianTiaga\SpiralCqrs\Attribute\Transactional;

final readonly class RenameUserHandler
{
    public function __construct(
        private UserRepository $userRepository,
    ) {}

    #[Transactional]
    public function handle(RenameUserCommand $command): void
    {
        $user = $this->userRepository->findById(UserId::fromString($command->userId))
            ?? throw new NotFoundException('app.user.not_found');

        $user->rename(UserDisplayName::fromString($command->name));

        $this->userRepository->save($user);
    }
}
```

## Что повторять

- Handler отвечает за один сценарий и получает зависимости конструктором.
- Внешние примитивы преобразуются в доменные типы до вызова сущности.
- Бизнес-переход выполняет доменная сущность.
- Транзакция охватывает загрузку, изменение, сохранение и outbox-событие.

## Допустимые варианты

Команда может вернуть минимальный Result с идентификатором или токеном. Обогащённое представление дочитывается отдельным Query.
