# PHPStan Strict Rules

`gian-tiaga/phpstan-strict-rules` — набор строгих PHPStan-правил для PHP-кода.

Сообщения правил остаются на английском языке. Это developer-инструмент, а не пользовательский интерфейс приложения.

## Установка

```bash
composer require --dev gian-tiaga/phpstan-strict-rules
```

Подключите extension в `phpstan.neon`:

```neon
includes:
    - vendor/gian-tiaga/phpstan-strict-rules/extension.neon
```

## Правила

### `declare(strict_types=1)`

Каждый анализируемый PHP-файл должен начинаться с `declare(strict_types=1)`.

Идентификатор:

- `gianTiaga.phpstanStrictRules.missingStrictTypes`.

Запрещено:

```php
<?php

namespace App\Example;
```

Разрешено:

```php
<?php

declare(strict_types=1);

namespace App\Example;
```

### Именованные аргументы

Если в вызове два или больше обычных аргумента, они должны быть именованными.

Идентификатор:

- `gianTiaga.phpstanStrictRules.namedArgumentsRequired`.

Запрещено:

```php
$user = new User('email@example.com', 'Иван');
$logger->info('message', ['userId' => $userId]);
```

Разрешено:

```php
$user = new User(email: 'email@example.com', name: 'Иван');
$logger->info(message: 'message', context: ['userId' => $userId]);
```

Один позиционный аргумент разрешён. Вызовы PHPUnit assertions не проверяются этим правилом, потому что PHPUnit запрещает named arguments для своих assert-методов.

### Строгие сравнения

Loose comparison запрещён.

Идентификаторы:

- `gianTiaga.phpstanStrictRules.looseEqualForbidden`;
- `gianTiaga.phpstanStrictRules.looseNotEqualForbidden`.

Запрещено:

```php
if ($status == 'active') {
}

if ($status != 'active') {
}
```

Разрешено:

```php
if ($status === UserStatus::Active) {
}

if ($status !== UserStatus::Active) {
}
```

### Точные PHPDoc-типы

PHPDoc-контракты не должны содержать неявный `mixed`, вложенные массивы и array shape.

Идентификаторы:

- `gianTiaga.phpstanStrictRules.noImplicitMixedType`;
- `gianTiaga.phpstanStrictRules.noNestedArrayType`;
- `gianTiaga.phpstanStrictRules.noArrayShapeType`.

Запрещено:

```php
/**
 * @param array $items
 * @return array
 */
public function names(array $items): array
{
    return $items;
}
```

Разрешено:

```php
/**
 * @param list<UserName> $items
 * @return list<string>
 */
public function names(array $items): array
{
    return array_map(
        callback: static fn(UserName $name): string => $name->value,
        array: $items,
    );
}
```

Если данные сложные, лучше вынести их в DTO или именованную коллекцию, а не описывать многоуровневым массивом.

## Локальная разработка

```bash
composer -d packages/phpstan-strict-rules install
composer -d packages/phpstan-strict-rules test
composer -d packages/phpstan-strict-rules phpstan
```

Если пакет подключён через path repository и вы меняете сами правила, иногда нужно очистить кеш PHPStan:

```bash
vendor/bin/phpstan clear-result-cache
```
