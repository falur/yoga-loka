# Интеграционный тест endpoint

## Назначение

Интеграционный тест проверяет маршрут через настоящий Spiral kernel, middleware, Filter, шину и хранилище.

## Когда применять

Применяй для каждого HTTP-маршрута и каждой значимой ветви его договора.

```php
<?php

declare(strict_types=1);

namespace App\Modules\Auth\Tests\Feature\Spiral;

use App\Modules\Auth\Domain\Repository\LoginCodeRepository;
use App\Modules\Auth\Domain\ValueObject\EmailAddress;
use Tests\NonTransactionalDatabaseTestCase;

final class RequestLoginCodeHttpTest extends NonTransactionalDatabaseTestCase
{
    public function testRequestStoresLoginCode(): void
    {
        $response = $this->http()->postJson(
            '/api/v1/auth/code/request',
            ['email' => 'user@example.com'],
        );

        $response->assertNoContent();
        self::assertNotNull(
            $this->getContainer()
                ->get(LoginCodeRepository::class)
                ->findActiveByEmail(EmailAddress::fromString('user@example.com')),
        );
    }
}
```

## Что повторять

- Файл теста находится в `Modules/Auth/Tests/Feature/Spiral`, namespace повторяет путь: `App\Modules\Auth\Tests\Feature\Spiral`. Удаление модуля уносит и его тесты.
- Общий базовый TestCase остаётся в корневом `tests/`: это часть без владельца среди модулей, как `app/config` и `app/locale` (`arch.md`, «Самодостаточность модуля»). Модуль наследует его, но своих файлов там не оставляет.
- Корневой `tests/` остаётся для сквозных и межмодульных проверок.
- Тест проходит через HTTP, а не вызывает Controller или Handler напрямую.
- Авторизация соответствует договору маршрута.
- Проверяется ответ и сохранённое состояние.
- Данные теста принадлежат отдельной тестовой базе.

## Допустимые варианты

Для маршрута чтения проверяется точное JSON-содержимое и отсутствие приватных полей. Отдельные тесты покрывают неавторизованный доступ, неверный ввод, чужой ресурс и граничные значения.
