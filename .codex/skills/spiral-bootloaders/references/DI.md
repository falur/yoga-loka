# DI-контейнер — подробный справочник

## Регистрация биндингов через BinderInterface

```php
use Spiral\Core\BinderInterface;

public function boot(BinderInterface $binder): void
{
    // Singleton — один экземпляр на приложение
    $binder->bindSingleton(
        ClientInterface::class,
        static fn(GithubConfig $config) => new Client($config->getAccessToken()),
    );

    // Factory — новый экземпляр каждый раз
    $binder->bind(RequestInterface::class, static fn() => new Request());
}
```

## Атрибуты биндингов

### #[SingletonMethod]
- `alias` (string|null) — привязать к alias вместо return type
- `aliasesFromReturnType` (bool) — также привязать к return type при указанном alias

### #[BindMethod]
Аналогично SingletonMethod, но новый экземпляр каждый раз.

### #[InjectorMethod]
Кастомный инжектор для управления разрешением типа:

```php
#[InjectorMethod(LoggerInterface::class)]
public function createLogger(string $channel = 'default'): LoggerInterface
{
    return new Logger($channel);
}
```

### #[BindAlias]
Добавляет дополнительные alias-ы. Можно применять несколько раз:

```php
#[SingletonMethod]
#[BindAlias(LoggerInterface::class, PsrLoggerInterface::class)]
#[BindAlias(MonologLoggerInterface::class)]
public function createLogger(): Logger
{
    return new Logger();
}
```

### #[BindScope]
Привязывает сервис к конкретному scope контейнера:

```php
#[SingletonMethod]
#[BindScope('http')]
public function createHttpClient(): HttpClientInterface
{
    return new HttpClient();
}
```

Поведение scope:
- Без `#[BindScope]` — глобальный (доступен везде)
- С `#[BindScope]` — только в указанном scope
- Разрешение за пределами scope бросает исключение

## IoC Scopes

Критически важны для long-living приложений (RoadRunner). Предотвращают обработку запросов как глобальных синглтонов.

## ResolverInterface

Разрешает аргументы метода динамически:
- Union types — передаётся первый доступный
- Variadic — массивы по имени параметра
- Default object values — `stdClass $std = new \stdClass()`

## Замена контейнера

```php
$container = new Container();
$container->bind(...);

$app = Kernel::create(
    directories: ['root' => __DIR__],
    container: $container,
);
```
