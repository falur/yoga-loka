# Spiral CQRS

`gian-tiaga/spiral-cqrs` добавляет в Spiral-приложение простые шины команд и запросов.

Пакет даёт:

- `CommandBusInterface` для сценариев, которые меняют состояние;
- `QueryBusInterface` для сценариев чтения;
- атрибут `#[Transactional]` для транзакций команд;
- атрибут `#[LogOperation]` для debug-логов выполнения;
- PHPStan-правило, которое требует передавать handler как `$handler->handle(...)`.

## Установка

```bash
composer require gian-tiaga/spiral-cqrs
```

Подключите bootloader в приложении:

```php
use GianTiaga\SpiralCqrs\Bootloader\CqrsBootloader;

protected const LOAD = [
    CqrsBootloader::class,
];
```

Bootloader регистрирует `CommandBusInterface` и `QueryBusInterface`.

## Command

Command — это объект с входными данными сценария. Handler выполняет сценарий.

```php
use GianTiaga\SpiralCqrs\CommandBusInterface;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;
use GianTiaga\SpiralCqrs\Attribute\Transactional;

final readonly class CreatePostCommand
{
    public function __construct(
        public string $authorId,
        public string $title,
        public string $body,
    ) {}
}

final readonly class CreatePostHandler
{
    public function __construct(
        private PostRepository $postRepository,
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

        $this->postRepository->add($post);

        return $post->id();
    }
}

$postId = $commandBus->dispatch(
    command: new CreatePostCommand(
        authorId: $authorId,
        title: $title,
        body: $body,
    ),
    handler: $createPostHandler->handle(...),
);
```

`#[Transactional]` работает только для command handler. Если поставить его на query handler, package PHPStan-правило сообщит об ошибке.

## Query

Query читает данные и не должен менять состояние приложения.

```php
use GianTiaga\SpiralCqrs\QueryBusInterface;

final readonly class GetPostQuery
{
    public function __construct(
        public string $postId,
    ) {}
}

final readonly class GetPostHandler
{
    public function __construct(
        private PostReadRepository $postReadRepository,
    ) {}

    public function handle(GetPostQuery $query): PostView
    {
        return $this->postReadRepository->getView($query->postId);
    }
}

$post = $queryBus->dispatch(
    query: new GetPostQuery(postId: $postId),
    handler: $getPostHandler->handle(...),
);
```

## Логи и исключения

`#[LogOperation]` пишет debug-лог перед запуском handler и после завершения. Сообщения логов и служебные исключения пакета написаны на русском языке.

## PHPStan

Чтобы проверять вызовы `dispatch()`, подключите extension:

```neon
includes:
    - vendor/gian-tiaga/spiral-cqrs/extension.neon
```

Разрешено:

```php
$commandBus->dispatch(
    command: $command,
    handler: $handler->handle(...),
);
```

Запрещено:

```php
$commandBus->dispatch(command: $command, handler: fn() => null);
$commandBus->dispatch(command: $command, handler: [$handler, 'handle']);
$commandBus->dispatch(command: $command, handler: $handler);
```

Идентификаторы ошибок:

- `gianTiaga.spiralCqrs.handlerCallableRequired`;
- `gianTiaga.spiralCqrs.transactionalQueryHandler`.

## Локальная разработка

Если пакет разрабатывается рядом с приложением, можно подключить path repository:

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "packages/spiral-cqrs",
      "options": {
        "symlink": true
      }
    }
  ]
}
```

Проверки пакета:

```bash
composer -d packages/spiral-cqrs install
composer -d packages/spiral-cqrs test
composer -d packages/spiral-cqrs phpstan
```
