# CQRS Tools

`yoga-loka/cqrs-tools` — маленький пакет с CQRS-шиной для Spiral-приложений.

Пакет даёт:

- `CommandBusInterface` для команд;
- `QueryBusInterface` для запросов;
- атрибуты `#[Transactional]` и `#[LogOperation]`;
- общий механизм handler middleware через атрибуты;
- PHPStan-правило, которое проверяет правильный вызов Handler-а.

Пакет не содержит прикладные Command, Query, Handler, Entity, Repository или
доменные типы. Всё это остаётся в приложении.

## Установка

Пакет подключается как локальный Composer path repository:

```json
{
    "require": {
        "yoga-loka/cqrs-tools": "dev-main"
    },
    "repositories": [
        {
            "type": "path",
            "url": "tools/cqrs",
            "options": {
                "symlink": true
            }
        }
    ]
}
```

Bootloader пакета регистрируется в приложении:

```php
use Tools\Cqrs\Bootloader\CqrsBootloader;

protected const LOAD = [
    CqrsBootloader::class,
];
```

`CqrsBootloader` регистрирует:

- `Tools\Cqrs\CommandBusInterface`;
- `Tools\Cqrs\QueryBusInterface`.

Bus регистрируются как обычные bindings, не как singleton.

## Быстрый пример

Контроллер создаёт DTO и передаёт в bus сам DTO вместе с
`$handler->handle(...)`.

```php
use Tools\Cqrs\CommandBusInterface;

final readonly class ProfileController
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private UpdateProfileHandler $updateProfileHandler,
    ) {}

    public function update(UpdateProfileRequest $request): void
    {
        $this->commandBus->dispatch(
            command: new UpdateProfileCommand(
                userId: $request->userId,
                displayName: $request->displayName,
            ),
            handler: $this->updateProfileHandler->handle(...),
        );
    }
}
```

Handler принимает один DTO и возвращает результат сценария, если он нужен.

```php
final readonly class UpdateProfileHandler
{
    public function __construct(
        private ProfileRepository $profiles,
    ) {}

    public function handle(UpdateProfileCommand $command): void
    {
        $profile = $this->profiles->get($command->userId);
        $profile->rename($command->displayName);

        $this->profiles->save($profile);
    }
}
```

PHPStan-правило пакета требует именно first-class callable:

```php
$this->commandBus->dispatch(
    command: $command,
    handler: $this->handler->handle(...),
);
```

Closure, array-callable и передача самого объекта Handler-а запрещены:

```php
$this->commandBus->dispatch(command: $command, handler: fn() => null); // нельзя
$this->commandBus->dispatch(command: $command, handler: [$handler, 'handle']); // нельзя
$this->commandBus->dispatch(command: $command, handler: $handler); // нельзя
```

## Command

Command — это DTO, который меняет состояние приложения.

```php
final readonly class CreatePostCommand
{
    public function __construct(
        public string $authorId,
        public string $title,
        public string $body,
    ) {}
}
```

Command Handler выполняет сценарий.

```php
use Tools\Cqrs\Attribute\LogOperation;
use Tools\Cqrs\Attribute\Transactional;

final readonly class CreatePostHandler
{
    public function __construct(
        private PostRepository $posts,
        private EntityManagerInterface $entityManager,
    ) {}

    #[Transactional]
    #[LogOperation(name: 'post.create')]
    public function handle(CreatePostCommand $command): string
    {
        $post = Post::create(
            authorId: $command->authorId,
            title: $command->title,
            body: $command->body,
        );

        $this->posts->add($post);
        $this->entityManager->persist($post);
        $this->entityManager->run();

        return $post->id();
    }
}
```

Вызов из контроллера или другого application-сервиса:

```php
$postId = $this->commandBus->dispatch(
    command: new CreatePostCommand(
        authorId: $authorId,
        title: $title,
        body: $body,
    ),
    handler: $this->createPostHandler->handle(...),
);
```

## Query

Query — это DTO, который читает данные и не меняет состояние приложения.

```php
final readonly class GetPostQuery
{
    public function __construct(
        public string $postId,
    ) {}
}
```

Query Handler возвращает результат чтения.

```php
use Tools\Cqrs\Attribute\LogOperation;

final readonly class GetPostHandler
{
    public function __construct(
        private PostReadRepository $posts,
    ) {}

    #[LogOperation(name: 'post.get')]
    public function handle(GetPostQuery $query): PostView
    {
        return $this->posts->getView($query->postId);
    }
}
```

Вызов:

```php
$post = $this->queryBus->dispatch(
    query: new GetPostQuery(postId: $postId),
    handler: $this->getPostHandler->handle(...),
);
```

`#[Transactional]` на Query Handler-е запрещён. Query не должен открывать
транзакцию и не должен менять состояние приложения.

## Встроенные атрибуты

### `#[Transactional]`

`#[Transactional]` ставится на `Command Handler::handle()` и оборачивает выполнение
Handler-а в транзакцию `Cycle\Database\DatabaseInterface`.

```php
use Tools\Cqrs\Attribute\Transactional;

final readonly class PublishPostHandler
{
    #[Transactional]
    public function handle(PublishPostCommand $command): void
    {
        // Запись в базу.
    }
}
```

Если атрибута нет, Command выполняется без транзакции.

### `#[LogOperation]`

`#[LogOperation]` пишет debug-лог перед запуском Handler-а и debug-лог с временем
выполнения после завершения.

```php
use Tools\Cqrs\Attribute\LogOperation;

final readonly class PublishPostHandler
{
    #[LogOperation(name: 'post.publish')]
    public function handle(PublishPostCommand $command): void
    {
        // Сценарий публикации.
    }
}
```

Если `name` не задан, имя операции берётся из имени класса Handler-а.

```php
#[LogOperation]
public function handle(PublishPostCommand $command): void
{
    // В лог попадёт имя Handler-а.
}
```

## Как работают middleware

Bus не знает про конкретные атрибуты вроде `Transactional` или `LogOperation`.

При dispatch он:

1. Берёт атрибуты с `Handler::handle()`.
2. Оставляет только атрибуты, которые наследуются от
   `Tools\Cqrs\Attribute\HandlerMiddlewareAttribute`.
3. У каждого атрибута спрашивает middleware-класс через `middleware()`.
4. Берёт middleware из DI-контейнера.
5. Запускает цепочку middleware и в конце вызывает Handler.

Поэтому зависимости не передаются через executor. Если middleware нужен логер,
база, клиент внешнего сервиса или другой сервис, middleware получает это сам
через constructor injection.

## Как написать свой middleware

Нужно создать два класса:

- атрибут, который хранит настройки и возвращает middleware-класс;
- middleware, который выполняет действие вокруг Handler-а.

Пример: audit-лог для важных операций.

### 1. Атрибут

```php
namespace App\Shared\Application\Cqrs\Attribute;

use App\Shared\Application\Cqrs\Middleware\AuditOperationMiddleware;
use Tools\Cqrs\Attribute\HandlerMiddlewareAttribute;

#[\Attribute(\Attribute::TARGET_METHOD)]
final readonly class AuditOperation extends HandlerMiddlewareAttribute
{
    public function __construct(
        public string $event,
    ) {}

    #[\Override]
    public function middleware(): string
    {
        return AuditOperationMiddleware::class;
    }
}
```

### 2. Middleware

```php
namespace App\Shared\Application\Cqrs\Middleware;

use App\Shared\Application\Audit\AuditLogger;
use App\Shared\Application\Cqrs\Attribute\AuditOperation;
use Tools\Cqrs\Attribute\HandlerMiddlewareAttribute;
use Tools\Cqrs\HandlerContext;
use Tools\Cqrs\HandlerMiddlewareInterface;

final readonly class AuditOperationMiddleware implements HandlerMiddlewareInterface
{
    public function __construct(
        private AuditLogger $auditLogger,
    ) {}

    /**
     * @template TInput of object
     * @template TResult
     * @param TInput $input
     * @param callable(TInput): TResult $next
     * @return TResult
     */
    #[\Override]
    public function handle(
        object $input,
        HandlerMiddlewareAttribute $attribute,
        callable $next,
        HandlerContext $context,
    ) {
        if (!$attribute instanceof AuditOperation) {
            throw new \LogicException(message: 'Некорректный атрибут для audit middleware.');
        }

        try {
            return $next($input);
        } finally {
            $this->auditLogger->write(
                event: $attribute->event,
                operation: $context->operationName(name: null),
            );
        }
    }
}
```

### 3. Использование на Handler-е

```php
use App\Shared\Application\Cqrs\Attribute\AuditOperation;
use Tools\Cqrs\Attribute\Transactional;

final readonly class DeletePostHandler
{
    #[Transactional]
    #[AuditOperation(event: 'post.deleted')]
    public function handle(DeletePostCommand $command): void
    {
        // Удаление поста.
    }
}
```

### 4. Зависимости middleware

Middleware создаётся через контейнер. Если его зависимости уже доступны в Spiral
DI, ничего дополнительно делать не нужно.

Если зависимость нужно связать вручную, добавь binding в bootloader приложения:

```php
use App\Shared\Application\Audit\AuditLogger;
use App\Shared\Infrastructure\Audit\DatabaseAuditLogger;

final class AuditBootloader extends Bootloader
{
    protected const BINDINGS = [
        AuditLogger::class => DatabaseAuditLogger::class,
    ];
}
```

## Контекст Handler-а

В `HandlerMiddlewareInterface::handle()` приходит `HandlerContext`.

Через него middleware может получить имя операции:

```php
$operationName = $context->operationName(name: null);
```

Для Command используется `CommandHandlerContext`. Это позволяет middleware
отличать Command от Query:

```php
use Tools\Cqrs\CommandHandlerContext;

if (!$context instanceof CommandHandlerContext) {
    throw new \LogicException(message: 'Этот middleware работает только с Command.');
}
```

Так работает встроенный `#[Transactional]`: он разрешён только для Command.

## Рекомендации

- Command меняет состояние, Query только читает.
- Handler принимает один DTO.
- В `dispatch()` передаётся только `$handler->handle(...)`.
- Транзакция включается только атрибутом `#[Transactional]`.
- Логирование операции включается только атрибутом `#[LogOperation]`.
- Новое поведение вокруг Handler-а добавляется через свой
  `HandlerMiddlewareAttribute` и свой `HandlerMiddlewareInterface`.
- Middleware сам получает свои зависимости из контейнера.

## Проверки

Проверки пакета:

```bash
composer -d tools/cqrs install
composer -d tools/cqrs test
composer -d tools/cqrs phpstan
```

Проверки приложения запускаются из корня проекта:

```bash
make test
make phpstan
```
