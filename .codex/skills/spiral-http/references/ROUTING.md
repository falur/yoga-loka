# Routing — подробный справочник

## Route Targets

### Action Target — конкретный метод контроллера

```php
$routes->add(name: 'user.show', pattern: '/users/<id>')
    ->methods(['GET'])
    ->action(UserController::class, 'show');
```

### Controller Target — все методы контроллера

```php
$routes->add(name: 'user', pattern: '/user/<action>')
    ->controller(UserController::class);
```

### Namespaced Target — все контроллеры в namespace

```php
$routes->add(name: 'admin', pattern: '/admin/<controller>/<action>')
    ->namespaced(namespace: 'App\Endpoint\Api\V1');
```

### Callable Target

```php
$routes->add(name: 'health', pattern: '/health')
    ->callable(fn() => 'OK');
```

## Route Groups

```php
protected function configureRouteGroups(GroupRegistry $groups): void
{
    $groups->getGroup('api')
        ->setNamePrefix('api.')
        ->setPrefix('/api/v1');

    $groups->getGroup('web')
        ->addMiddleware(SessionMiddleware::class);

    $groups->setDefaultGroup('api');
}
```

## RESTful Routing

```php
class UserController
{
    public function getUser($id): string { return "get {$id}"; }
    public function postUser($id): string { return "post {$id}"; }
    public function deleteUser($id): string { return "delete {$id}"; }
}

// Маршрут с RESTFUL — HTTP-метод определяет метод контроллера
$router->setRoute('user', new Route(
    '/user/<id:\d+>',
    new Controller(UserController::class, Controller::RESTFUL),
    ['action' => 'user'],
));
```

## Immutability маршрутов

Маршруты иммутабельны — методы возвращают новую копию:
```php
$route = new Route('/[<action>]', $handler);
$router->setRoute('home', $route->withDefaults(['action' => 'default']));
```

## Fallback Route

```php
$routes->default('/<path:.*>')
    ->callable(fn() => 'Страница не найдена');
```

## URL Generation с не-латинскими символами

```php
// Глобальная настройка в bootloader
return (new UriHandler($uriFactory))->withPathSegmentEncoder(
    static fn(string $segment): string => \rawurlencode($segment),
);
```
