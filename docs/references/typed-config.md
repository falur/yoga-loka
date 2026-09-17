# Typed config

## Назначение

Типизированный конфиг превращает секцию Spiral в проверяемый объект Infrastructure.

## Когда применять

Применяй для каждой новой секции конфигурации. Конфигурация предметной области и её тест находятся внутри модуля-владельца. Только общая настройка runtime остаётся в `app/config` и `Shared/Infrastructure/Spiral/Configuration`.

```php
<?php

declare(strict_types=1);

namespace App\Modules\User\Infrastructure\Spiral\Configuration;

use App\Shared\Infrastructure\Spiral\Configuration\TypedConfig;

final readonly class UserConfig implements TypedConfig
{
    public function __construct(
        public string $defaultAvatarUrl,
        public int $nicknameChangeDays,
    ) {}

    #[\Override]
    public static function configName(): string
    {
        return 'user';
    }
}
```

## Что повторять

- Корневой класс `final readonly` реализует `TypedConfig`.
- `configName()` совпадает с именем config-файла.
- Каждое поле имеет точный тип.
- Конфиг модуля лежит в его `Infrastructure/Spiral/Configuration`, тест преобразования — в его `Tests/Integration/Spiral`: проверка поднимает конфигурацию Spiral, поэтому в unit-набор она не входит.
- Domain и Application не импортируют config DTO.

## Допустимые варианты

Вложенная секция описывается отдельным `final readonly` DTO рядом с корневым. Однородная карта допустима только с точным generic-типом. Конфигурация без владельца среди бизнес-модулей может находиться в `Shared/Infrastructure/Spiral/Configuration`.
