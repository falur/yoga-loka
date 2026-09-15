---
name: spiral-http
description: >-
  Справочник по HTTP-слою Spiral Framework: routing, controllers, middleware, interceptors.
  Используй при создании маршрутов, контроллеров, middleware и interceptor-ов.
user-invocable: false
---

# Spiral Framework: HTTP (Routing, Middleware, Interceptors)

## Routing через атрибуты

```php
use Spiral\Router\Annotation\Route;

final class UserController
{
    #[Route(route: '/api/v1/users/<id>', name: 'user.show', methods: ['GET'])]
    public function show(string $id): DataResponse
    {
        // ...
    }

    #[Route(route: '/api/v1/users', name: 'user.store', methods: ['POST'])]
    public function store(CreateUserFilter $filter): DataResponse
    {
        // ...
    }
}
```

### Параметры #[Route]

| Параметр | Тип | Описание |
|----------|-----|---------|
| `route` | string | URL-паттерн (обязателен) |
| `name` | string | Идентификатор маршрута |
| `methods` | array/string | HTTP-методы: GET, POST, PUT, DELETE |
| `defaults` | array | Значения по умолчанию |
| `group` | string | Группа маршрута |
| `middleware` | array | Классы middleware |
| `priority` | int | Приоритет (меньше = важнее) |

### Паттерны параметров

```php
// Числовой ID
#[Route(route: '/user/<id:\d+>', ...)]

// Предопределённые значения
#[Route(route: '/do/<action:login|logout>', ...)]

// Опциональный сегмент
#[Route(route: '/users[/<page>]', ...)]

// UUID паттерн (через RoutePatternRegistryInterface)
#[Route(route: '/post/<post:uuid>', ...)]
```

### Регистрация паттернов

```php
public function boot(RoutePatternRegistryInterface $patternRegistry): void
{
    $patternRegistry->register(
        'uuid',
        '[0-9a-fA-F]{8}\b-[0-9a-fA-F]{4}\b-[0-9a-fA-F]{4}\b-[0-9a-fA-F]{4}\b-[0-9a-fA-F]{12}',
    );
}
```

## Routing через RoutingConfigurator

```php
final class RoutesBootloader extends BaseRoutesBootloader
{
    protected function defineRoutes(RoutingConfigurator $routes): void
    {
        $routes->add(name: 'news.show', pattern: '/news/<id:int>')
            ->group('api')
            ->methods(['GET'])
            ->action(NewsController::class, 'show');
    }

    protected function configureRouteGroups(GroupRegistry $groups): void
    {
        $groups->getGroup('api')
            ->setNamePrefix('api.')
            ->setPrefix('/api/v1');
    }
}
```

### Импорт маршрутов из файлов

```php
$routes->import($this->dirs->get('app') . '/routes/api.php')
    ->prefix('/api')
    ->group('api');
```

## Middleware

### PSR-15 Middleware

```php
use Psr\Http\Server\MiddlewareInterface;

class MyMiddleware implements MiddlewareInterface
{
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        return $handler->handle($request)
            ->withAddedHeader('My-Header', 'value');
    }
}
```

### Глобальные middleware

```php
final class RoutesBootloader extends BaseRoutesBootloader
{
    protected function globalMiddleware(): array
    {
        return [
            ErrorHandlerMiddleware::class,
            JsonPayloadMiddleware::class,
        ];
    }
}
```

### Middleware-группы

```php
protected function middlewareGroups(): array
{
    return [
        'web' => [
            CookiesMiddleware::class,
            SessionMiddleware::class,
        ],
        'api' => [
            JsonPayloadMiddleware::class,
        ],
    ];
}
```

### Middleware на маршрут

```php
// Через атрибут
#[Route(route: '/', middleware: [AuthMiddleware::class])]

// Через конфигуратор
$routes->add(...)->middleware(['middleware:api', MyMiddleware::class]);
```

### IoC Scope в Middleware

```php
class AuthMiddleware implements MiddlewareInterface
{
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        // Добавление в request attributes (рекомендуемый способ)
        return $handler->handle(
            $request->withAttribute('userId', $decodedToken->userId),
        );
    }
}
```

Чтение через Filter:
```php
#[Attribute(key: 'userId')]
public string $userId;
```

## Interceptors

### Создание interceptor-а

```php
use Spiral\Core\CoreInterceptorInterface;

class CustomInterceptor implements CoreInterceptorInterface
{
    public function intercept(CallContextInterface $context, HandlerInterface $handler): mixed
    {
        // До обработки
        $result = $handler->handle($context);
        // После обработки
        return $result;
    }
}
```

### Регистрация через DomainBootloader

```php
class AppBootloader extends DomainBootloader
{
    protected const SINGLETONS = [
        CoreInterface::class => [self::class, 'domainCore'],
    ];

    protected const INTERCEPTORS = [
        HandleExceptionsInterceptor::class,
        JsonPayloadResponseInterceptor::class,
        CustomInterceptor::class,
    ];
}
```

### CycleInterceptor — автоматическое разрешение Entity

```php
// В interceptors:
CycleInterceptor::class

// В контроллере — entity подставляется автоматически по route параметру
#[Route(route: '/users/<id>')]
public function show(User $user): DataResponse
{
    // $user уже загружен из БД по id
}
```

### Pipeline — кастомные interceptor-ы на endpoint

```php
#[Pipeline(pipeline: [CycleInterceptor::class, GuardInterceptor::class], skipNext: true)]
public function email(User $user, Email $email): string
{
    // ...
}
```

## URL Generation

```php
public function index(RouterInterface $router): void
{
    $uri = $router->uri('user.show', ['id' => 123]);
    // /api/v1/users/123

    $uri = $router->uri('user.list', ['page' => 2]);
    // /api/v1/users?page=2
}
```

## События маршрутизации

| Событие | Описание |
|---------|---------|
| `Routing` | До matching маршрута |
| `RouteMatched` | Маршрут найден |
| `RouteNotFound` | Маршрут не найден |

Подробнее: [references/ROUTING.md](references/ROUTING.md), [references/INTERCEPTORS.md](references/INTERCEPTORS.md)
