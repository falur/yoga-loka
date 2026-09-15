---
name: spiral-testing
description: >-
  Справочник по тестированию в Spiral Framework.
  Используй при написании unit и feature тестов, HTTP-тестов,
  тестировании очередей, storage, mail и консольных команд.
user-invocable: false
---

# Spiral Framework: Testing

PHPUnit с двумя типами тестов:
- **Unit** — `PHPUnit\Framework\TestCase`, без загрузки приложения
- **Feature** — `Tests\TestCase`, полная загрузка приложения

## Конфигурация phpunit.xml

```xml
<env name="APP_ENV" value="testing"/>
<env name="QUEUE_CONNECTION" value="sync"/>
<env name="CACHE_STORAGE" value="array"/>
<env name="TOKENIZER_CACHE_TARGETS" value="true"/>
<env name="CYCLE_SCHEMA_CACHE" value="true"/>
```

## Feature TestCase

### Переменные окружения

```php
use Spiral\Testing\Attribute\Env;

#[Env('APP_DEBUG', 'true')]
#[Env('QUEUE_CONNECTION', 'sync')]
class MyTest extends TestCase
{
    // Или через константу
    const ENV = [
        'APP_DEBUG' => 'true',
    ];
}
```

### Конфигурация

```php
use Spiral\Testing\Attribute\Config;

#[Config('database.default', 'sqlite')]
public function testSomething(): void {}

// Assertions
$this->assertConfigMatches('database', ['default' => 'sqlite']);
$this->assertConfigHasFragments('database', ['default' => 'sqlite']);
$config = $this->getConfig('database');
```

### Container assertions

```php
$this->assertContainerBound(UserRepository::class);
$this->assertContainerBoundAsSingleton(UserRepository::class);
$this->assertContainerInstantiable(UserService::class);
$this->assertContainerMissed(SomeInterface::class);
```

### Мокирование

```php
$this->mockContainer(UserRepository::class, function () {
    $mock = \Mockery::mock(UserRepository::class);
    $mock->shouldReceive('findByEmail')->andReturn($user);
    return $mock;
});
```

### Bootloader assertions

```php
$this->assertBootloaderLoaded(AppBootloader::class);
$this->assertBootloaderMissed(DebugBootloader::class);
```

### Console assertions

```php
$this->assertCommandRegistered('app:create:user');

$output = $this->runCommand('app:create:user', [
    'email' => 'test@example.com',
    '--admin' => true,
]);

$this->assertConsoleCommandOutputContainsStrings('app:create:user', [], [
    'Пользователь создан',
]);
```

## HTTP Testing

### Создание FakeHttp

```php
$http = $this->fakeHttp();
```

### Методы запросов

```php
// GET
$response = $http->get('/users', query: ['sort' => 'desc']);
$response = $http->getJson('/users');

// POST
$response = $http->post('/users', data: ['name' => 'John']);
$response = $http->postJson('/users', data: ['name' => 'John']);

// PUT
$response = $http->put('/users/1', data: ['name' => 'John']);
$response = $http->putJson('/users/1', data: ['name' => 'John']);

// DELETE
$response = $http->delete('/users/1');
$response = $http->deleteJson('/users/1');
```

### Заголовки и авторизация

```php
// Установка заголовков
$http->withHeaders(['Content-type' => 'application/json']);
$http->withHeader('Accept', 'application/json');

// Bearer-токен
$http->withAuthorizationToken('xxx-xxxx', type: 'Bearer');

// Cookies
$http->withCookies(['theme' => 'dark']);
$http->withCookie('session', 'abc');

// Сессия
$http->withSession(data: ['user_id' => 1], lifetime: 3600);

// Аутентификация
$http->withActor($user);
```

### Response Assertions

```php
// Статус
$response->assertStatus(200);
$response->assertOk();           // 200
$response->assertCreated();      // 201
$response->assertAccepted();     // 202
$response->assertNoContent();    // 204
$response->assertNotFound();     // 404
$response->assertForbidden();    // 403
$response->assertUnauthorized(); // 401
$response->assertUnprocessable();// 422

// Тело
$response->assertBodySame('{"status":"ok"}');
$response->assertBodyContains('"email"');
$response->assertBodyNotSame('error');

// Заголовки
$response->assertHasHeader('Content-type');
$response->assertHasHeader('Content-type', 'application/json');
$response->assertHeaderMissing('X-Debug');

// Cookies
$response->assertCookieExists('theme');
$response->assertCookieSame('theme', 'dark');
$response->assertCookieMissed('old_cookie');
```

### Тестирование загрузки файлов

```php
$image = $http->getFileFactory()->createImage(
    filename: 'avatar.jpg',
    width: 640,
    height: 480,
);

$response = $http->post('/upload', files: ['avatar' => $image]);

// Произвольный файл
$file = $http->getFileFactory()->createFile(
    filename: 'data.csv',
    kilobytes: 100,
    mimeType: 'text/csv',
);

// Файл с содержимым
$file = $http->getFileFactory()->createFileWithContent(
    filename: 'test.json',
    content: '{"key": "value"}',
    mimeType: 'application/json',
);
```

## Queue Testing

```php
$queue = $this->fakeQueue();

// Выполнить действие, которое пушит job
$this->runCommand('app:process');

// Проверить, что job был отправлен
$queue->assertPushed(SendEmailJob::class);
$queue->assertPushed(SendEmailJob::class, function (array $payload) {
    return $payload['email'] === 'test@example.com';
});
$queue->assertNotPushed(SomeOtherJob::class);
$queue->assertPushedTimes(SendEmailJob::class, 2);
```

## Mail Testing

```php
$mailer = $this->fakeMailer();

// Выполнить действие
// ...

$mailer->assertSent(WelcomeEmail::class);
$mailer->assertNotSent(AdminNotification::class);
```

## Storage Testing

```php
$storage = $this->fakeStorage();

// Выполнить действие
// ...

$storage->assertExists('bucket://file.txt');
$storage->assertMissing('bucket://other.txt');
```

## Events Testing

```php
$events = $this->fakeEventDispatcher();

// Выполнить действие
// ...

$events->assertDispatched(UserCreated::class);
$events->assertNotDispatched(UserDeleted::class);
```

## Ключевые правила

1. Unit-тесты — для каждого Handler (Command и Query)
2. Feature/HTTP-тесты — для каждого endpoint
3. Тесты для ValueObject — валидация, фабричные методы, equals(), граничные случаи
4. PHPStan, CS Fixer и тесты — прогонять после каждой задачи
