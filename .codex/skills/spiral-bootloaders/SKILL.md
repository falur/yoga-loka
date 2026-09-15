---
name: spiral-bootloaders
description: >-
  Справочник по Bootloaders, DI-контейнеру и Config-объектам в Spiral Framework.
  Используй при создании или модификации bootloader-ов, регистрации сервисов,
  настройке DI-биндингов и работе с конфигурацией приложения.
user-invocable: false
---

# Spiral Framework: Bootloaders, DI и Config

## Bootloaders

Bootloaders — классы, настраивающие приложение при старте. Выполняются один раз при bootstrap.

### Регистрация в Kernel

```php
class Kernel extends \Spiral\Framework\Kernel
{
    public function defineBootloaders(): array
    {
        return [
            RoutesBootloader::class,
        ];
    }

    public function defineAppBootloaders(): array
    {
        return [
            LoggingBootloader::class,
            MyBootloader::class,
        ];
    }
}
```

`defineAppBootloaders()` выполняется ПОСЛЕ `defineBootloaders()`.

### Методы инициализации

**Порядок выполнения:**
1. `#[InitMethod(priority: 10)]` — высший приоритет init
2. `#[InitMethod]` — приоритет по умолчанию
3. `init()` — традиционный метод
4. `#[BootMethod(priority: 10)]` — высший приоритет boot
5. `#[BootMethod]` — приоритет по умолчанию
6. `boot()` — традиционный метод

**init()** — выполняется первым, ДО `boot()` любого bootloader-а:
```php
final class GithubClientBootloader extends Bootloader
{
    public function __construct(
        private readonly ConfiguratorInterface $config,
    ) {}

    public function init(): void
    {
        // Defaults берутся из конфиг-файла app/config/github.php,
        // где допустим env(). EnvironmentInterface ЗАПРЕЩЁН.
        $this->config->setDefaults(GithubConfig::CONFIG, [
            'access_token' => '',
            'timeout' => 30,
        ]);
    }
}
```

> **ПРАВИЛО ПРОЕКТА:** `EnvironmentInterface` запрещён везде. `env()` допустим ТОЛЬКО
> в файлах `app/config/*.php`. Bootloader читает значения через Config-объекты.

**boot()** — выполняется после ВСЕХ `init()`:
```php
public function boot(GithubConfig $config, HttpBootloader $http): void
{
    $http->addMiddleware(GithubAuthMiddleware::class);
}
```

### Декларативные биндинги

```php
final class ServicesBootloader extends Bootloader
{
    public function defineBindings(): array
    {
        return [
            RequestInterface::class => Request::class,
        ];
    }

    public function defineSingletons(): array
    {
        return [
            CacheInterface::class => RedisCache::class,
            LoggerInterface::class => static fn(Config $config) =>
                new Logger($config->get('logging.channel')),
        ];
    }
}
```

### Биндинги через атрибуты

```php
use Spiral\Boot\Attribute\SingletonMethod;
use Spiral\Boot\Attribute\BindMethod;
use Spiral\Boot\Attribute\BindAlias;
use Spiral\Boot\Attribute\BindScope;

final class ServicesBootloader extends Bootloader
{
    #[SingletonMethod]
    public function createHttpClient(GithubConfig $config): HttpClientInterface
    {
        return new Client($config->getAccessToken());
    }

    #[BindMethod]
    public function createRequest(): RequestInterface
    {
        return new Request();
    }

    #[SingletonMethod]
    #[BindAlias(LoggerInterface::class, PsrLoggerInterface::class)]
    public function createLogger(): Logger
    {
        return new Logger();
    }

    #[SingletonMethod]
    #[BindScope('http')]
    public function createSessionManager(): SessionManagerInterface
    {
        return new SessionManager();
    }
}
```

### Зависимости между bootloader-ами

**Метод 1 — инъекция (когда нужны методы зависимости):**
```php
public function boot(HttpBootloader $http): void
{
    $http->addMiddleware(ApiAuthMiddleware::class);
}
```

**Метод 2 — defineDependencies (только загрузка):**
```php
public function defineDependencies(): array
{
    return [
        HttpBootloader::class,
        AuthBootloader::class,
    ];
}
```

### Условная загрузка (BootloadConfig)

```php
use Spiral\Boot\Attribute\BootloadConfig;

// В Kernel:
PrototypeBootloader::class => new BootloadConfig(
    allowEnv: ['APP_ENV' => ['local', 'dev']],
),

DebugBootloader::class => new BootloadConfig(
    denyEnv: ['APP_ENV' => 'production'],
),
```

Через атрибут:
```php
#[BootloadConfig(allowEnv: ['APP_ENV' => ['local', 'development']])]
final class DevToolsBootloader extends Bootloader {}
```

### Динамическая загрузка

```php
public function boot(BootloadManagerInterface $bootloadManager, AppConfig $appConfig): void
{
    if ($appConfig->isDebug()) {
        $bootloadManager->bootload([DebugBootloader::class]);
    }
}
```

> `EnvironmentInterface` запрещён — используй типизированный Config-объект.

> Динамически загруженные bootloader-ы НЕ МОГУТ иметь `init()` или `#[InitMethod]`.

## DI-контейнер

Spiral реализует PSR-11 Container. Поддерживает constructor и method injection.

### Constructor Injection

```php
final class UserController
{
    public function __construct(
        private readonly UserRepository $users,
    ) {}
}
```

### Method Injection

```php
final class UserController
{
    public function show(UserRepository $users, string $id): void
    {
        $user = $users->findOrFail($id);
    }
}
```

### FactoryInterface — создание с параметрами

```php
public function make(FactoryInterface $factory): MyClass
{
    return $factory->make(UserService::class, ['table' => 'users']);
}
```

### InvokerInterface — вызов с автоматическим DI

```php
$result = $this->invoker->invoke([$this, 'doSomething'], $params);
```

## Config Objects

### Создание типизированного конфига

```php
namespace App\Application\Config;

use Spiral\Core\InjectableConfig;

class GithubConfig extends InjectableConfig
{
    public const CONFIG = 'github';

    protected array $config = [
        'access_token' => '',
    ];

    public function getAccessToken(): string
    {
        return $this->config['access_token'];
    }
}
```

Файл конфига: `app/config/github.php`:
```php
return [
    'access_token' => env('GITHUB_ACCESS_TOKEN'),
];
```

### Установка defaults в bootloader

```php
public function init(ConfiguratorInterface $configurator): void
{
    // Defaults задают структуру конфига. Реальные значения
    // приходят из файла app/config/github.php через env().
    $configurator->setDefaults(GithubConfig::CONFIG, [
        'access_token' => '',
    ]);
}
```

### Модификация конфигурации

```php
use Spiral\Config\Patch\Set;

public function setAccessToken(string $token): void
{
    $this->configurator->modify(GithubConfig::CONFIG, new Set('access_token', $token));
}
```

### Жизненный цикл конфигурации

- Устанавливать значения — в `init()`
- Запрашивать конфигурацию — в `boot()`
- После первого запроса конфигурация "замораживается" — изменения вызовут `ConfigDeliveredException`

## Ключевые правила

1. `env()` допустим ТОЛЬКО в файлах `app/config/*.php`
2. В сервисах и handler-ах — только через типизированные Config-классы
3. `EnvironmentInterface` ЗАПРЕЩЁН ВЕЗДЕ (включая bootloader-ы) — используй конфиги
4. Bootloader — это ТОЛЬКО конфигурация, не бизнес-логика
5. `init()` — для установки defaults, `boot()` — для использования конфигов

Подробнее: [references/DI.md](references/DI.md)
