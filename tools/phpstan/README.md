# Yoga Loka PHPStan Rules

Локальный пакет с проектными PHPStan-правилами. Подключается через `extension.neon`; правила применяются к приложению корневой командой:

```bash
composer phpstan
```

Проверки самого пакета:

```bash
composer tools:phpstan:qa
```

## Подключение

В корневом `phpstan.neon`:

```neon
includes:
    - vendor/yoga-loka/phpstan-rules/extension.neon
```

Для path repository пакет подключён через корневой `composer.json`. При изменении правил их исходники должны входить в `parameters.paths` корневого `phpstan.neon`, иначе PHPStan предупреждает о stale result cache.

## Правила

### `project.missingStrictTypes`

Класс: `Tools\PHPStan\Rules\RequireStrictTypesRule`

Что делает:

- Проверяет каждый анализируемый PHP-файл.
- Требует, чтобы первым AST-узлом файла был `declare(strict_types=1)`.
- Сообщает ошибку на первой строке файла.

Что запрещено:

```php
<?php

namespace App\Example;
```

```php
<?php

declare(strict_types=0);
```

Что разрешено:

```php
<?php

declare(strict_types=1);

namespace App\Example;
```

Исключения:

- Исключений по namespace или папкам внутри правила нет.
- Правило применяется ко всем файлам, которые попали в `paths` текущего PHPStan-конфига.

Нюансы:

- `declare(strict_types=1)` должен идти до `namespace`.
- Если файл не анализируется PHPStan, правило его не увидит.

### `project.namedArgumentsRequired`

Класс: `Tools\PHPStan\Rules\RequireNamedArgumentsRule`

Что делает:

- Проверяет вызовы функций, методов, статических методов, конструкторов и attributes.
- Если в вызове два или больше обычных аргумента, каждый обычный аргумент должен быть именованным.
- Variadic unpack (`...$args`) не считается обычным аргументом.

Что запрещено:

```php
$user = new User('email@example.com', 'Иван');
$logger->info('message', ['userId' => $userId]);
#[Route('/api/v1/health', 'api.v1.health')]
```

Что разрешено:

```php
$user = new User(email: 'email@example.com', name: 'Иван');
$logger->info(message: 'message', context: ['userId' => $userId]);
#[Route(route: '/api/v1/health', name: 'api.v1.health')]
```

Исключения:

- Один обычный позиционный аргумент разрешён.
- Unpack-аргументы не учитываются при подсчёте.
- First-class callable syntax не проверяется как обычный вызов.

Нюансы:

- Правило не оценивает семантику аргументов, оно смотрит только на форму вызова.
- Если в вызове есть два обычных аргумента и один из них не именован, ошибка будет на неименованном аргументе.
- Attributes проверяются тем же правилом, потому что в проекте они тоже являются публичным контрактом.

### `project.looseEqualForbidden`

Класс: `Tools\PHPStan\Rules\DisallowLooseComparisonRule`

Что делает:

- Запрещает loose comparison через `==`.
- Требует использовать строгое сравнение `===`.

Что запрещено:

```php
if ($status == 'active') {
}
```

Что разрешено:

```php
if ($status === UserStatus::Active) {
}
```

Исключения:

- Исключений нет.

Нюансы:

- Правило ловит только оператор `==`.
- Для `!=` используется отдельный identifier `project.looseNotEqualForbidden`.

### `project.looseNotEqualForbidden`

Класс: `Tools\PHPStan\Rules\DisallowLooseComparisonRule`

Что делает:

- Запрещает loose not-equal comparison через `!=`.
- Требует использовать строгое сравнение `!==`.

Что запрещено:

```php
if ($status != 'active') {
}
```

Что разрешено:

```php
if ($status !== UserStatus::Active) {
}
```

Исключения:

- Исключений нет.

Нюансы:

- PHP parser нормализует `<>` в тот же AST-тип, что и `!=`, поэтому правило закрывает оба варианта.

### `project.noImplicitMixedType`

Классы:

- `Tools\PHPStan\Rules\TypeContractRule`
- `Tools\PHPStan\TypeContracts\TypeContractInspector`
- `Tools\PHPStan\TypeContracts\PhpDocContractTypeCollector`

Что делает:

- Проверяет PHPDoc type contracts.
- Запрещает неявный `mixed`, который появляется из неполного типа вроде `array`, `callable`, `iterable` без generic-деталей.
- Проверяет `@var`, `@param`, `@param-out`, `@return`, `@throws`, `@template`, `@phpstan-type`, `@property`, `@method`.

Что запрещено:

```php
/**
 * @param array $items
 * @return array
 */
public function normalize($items): array
{
    return $items;
}
```

Что разрешено:

```php
/**
 * @param array<int, string> $items
 * @return list<string>
 */
public function normalize(array $items): array
{
    return array_values($items);
}
```

Исключения:

- Явный `mixed` разрешён: `mixed`, `array<string, mixed>`, `callable(mixed): void`.
- Template type не считается нарушением.

Нюансы:

- Правило работает по PHPDoc-контрактам, а не по всем native type hints.
- Native `mixed` не запрещается этим rule set.
- Ошибка означает, что контракт недостаточно точный для PHPStan и IDE.

### `project.noNestedArrayType`

Классы:

- `Tools\PHPStan\Rules\TypeContractRule`
- `Tools\PHPStan\TypeContracts\TypeContractInspector`

Что делает:

- Запрещает вложенные массивы в PHPDoc type contracts.
- Подталкивает к DTO, value object, enum или именованным коллекциям вместо сложных контейнеров.

Что запрещено:

```php
/**
 * @param array<string, array<int, string>> $items
 */
public function handle(array $items): void
{
}
```

```php
/** @var list<array<string, int>> $items */
$items = [];
```

Что разрешено:

```php
/**
 * @param array<int, string> $items
 * @return list<string>
 */
public function normalize(array $items): array
{
    return array_values($items);
}
```

Исключения:

- Простые `list<T>` и `array<int|string, T>` разрешены, если `T` не является массивом или shape.
- Именованные DTO/VO/коллекции вместо вложенного массива разрешены и предпочтительны.

Нюансы:

- Нарушение может прийти из `@phpstan-type`, `@property`, `@method`, `@param`, `@return` и локального `@var`.
- Если нужно передать сложную структуру, сначала создаётся именованный тип, а не вложенный array contract.

### `project.noArrayShapeType`

Классы:

- `Tools\PHPStan\Rules\TypeContractRule`
- `Tools\PHPStan\TypeContracts\TypeContractInspector`

Что делает:

- Запрещает array shapes и tuple-типы в PHPDoc type contracts.
- Требует заменить shape на именованный DTO/value object/response/filter.

Что запрещено:

```php
/**
 * @return array{message: string, code: int}
 */
public function error(): array
{
    return ['message' => 'Ошибка', 'code' => 422];
}
```

```php
/**
 * @method array{0: string, 1: int} makeTuple()
 */
final class Example
{
}
```

Что разрешено:

```php
final readonly class ErrorPayload
{
    public function __construct(
        public string $message,
        public int $code,
    ) {}
}
```

Исключения:

- Исключений для shapes нет.

Нюансы:

- Tuple вроде `array{0: string, 1: int}` считается тем же нарушением.
- Правило проверяет PHPDoc-контракты. Runtime-массив без PHPDoc shape ловится другими архитектурными правилами и ревью.

## Как добавлять новое правило

1. Добавить rule-класс в `src/Rules`.
2. Зарегистрировать его в `extension.neon` с тегом `phpstan.rules.rule`.
3. Добавить unit-тест в `tests/Unit/PHPStan`.
4. Добавить fixtures с разрешёнными и запрещёнными кейсами.
5. Описать правило в этом README: что делает, исключения, нюансы.
6. Запустить `composer tools:phpstan:qa` и корневой `composer phpstan`.
