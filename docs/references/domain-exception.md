# Доменное исключение

## Назначение

Ожидаемая ошибка выражается отдельным типом исключения. Клиент получает безопасный перевод, а технические подробности наружу не выходят.

## Когда применять

Применяй, когда доменная операция может быть отклонена по понятной бизнес-причине.

```php
<?php

declare(strict_types=1);

namespace App\Modules\User\Domain\Exception;

use App\Shared\Domain\Exception\DomainTranslatableException;

final class UserBlockedException extends DomainTranslatableException
{
    public function __construct()
    {
        parent::__construct(translationKey: 'app.user.blocked');
    }

    #[\Override]
    protected function statusCode(): int
    {
        return 403;
    }
}
```

## Что повторять

- Имя заканчивается на `Exception` и описывает причину отказа.
- Исключение содержит ключ перевода, а не готовый текст ответа.
- В сообщении и параметрах нет секретов и чужих персональных данных.
- Неожиданная техническая ошибка не маскируется под доменное исключение.

## Допустимые варианты

Общие типы `NotFoundException`, `ForbiddenException`, `ValidationException` и другие нейтральные ошибки остаются в `Shared/Domain/Exception`. Ошибка внешнего сервиса принадлежит Application или Infrastructure, а не Domain.
