# Правила проекта

## Типовые контракты

В PHPDoc типовых контрактов запрещены:

- неявный `mixed`, который появляется из-за пропущенного generic-параметра, например `array` вместо `list<Foo>` или `array<int, Foo>`;
- вложенные массивы вроде `array<string, array<int, string>>`;
- tuple-типы;
- array shapes вроде `array{foo: string}`.

Явный `mixed` допустим, когда контракт действительно принимает или возвращает неизвестное значение.

Для generic-контейнеров используйте именованные DTO/value object, enum, коллекции, `list<T>` или `array<int|string, T>`, где `T` не является массивом, shape или tuple.

Локальная проверка: `composer phpstan`.

Все PHP-файлы, анализируемые PHPStan, должны начинаться с `declare(strict_types=1)`.
