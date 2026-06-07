---
review: docs/reviews/2026-06-07_16-37_outbox-module-draft-iter3.md
date: 2026-06-07 16:50
status: done
---

# Фиксы по ревью: Модуль Outbox (итерация 3)

Режим: `apply-optional`. Обязательных пунктов в ревью нет, все три — «на усмотрение
автора», применены без интерактивных вопросов. Пунктов «не править» нет.

## Применённые правки

| # | Замечание | Файлы | Тесты | Статус |
|---|-----------|-------|-------|--------|
| 1 | Иерархия VO `OutboxEventDate` — единственное семейство VO модуля без `\Stringable`; привести к конвенции с учётом null-объекта `EmptyOutboxEventDate` | `app/src/Modules/Outbox/Domain/ValueObject/OutboxEventDate.php`, `KnownOutboxEventDate.php`, `EmptyOutboxEventDate.php`, тест-фикстура `tests/Unit/Modules/Outbox/Infrastructure/OutboxInfrastructureEdgeTest.php` | `tests/Unit/Modules/Outbox/Domain/OutboxValueObjectTest.php` (расширен) | ✓ применено |
| 2 | Relay-выборка сортирует по `id`, индекс `(status, available_at, id)` заточен под `available_at`; согласовать порядок выборки с индексом | `app/src/Modules/Outbox/Repository/OutboxEventRepository.php` | `OutboxRelayTest`, `OutboxRelayPublishTest`, `OutboxRelayCommandTest`, `OutboxEventRepositoryTest` (Feature, прогнаны) | ✓ применено |
| 3 | Незастейдженный «хвост» тест-хелпера (`cleanOrmState()` в рабочем дереве, старая версия в индексе) — привести индекс к рабочему дереву (`git add`), не коммитить | `tests/Feature/Modules/Outbox/Infrastructure/Fixture/OutboxQueueStatusInterceptorTestHelpers.php` | — | ✓ применено |

## Детали правок

### 1. Конвенция `\Stringable` у семейства «дат события»

Выбрана семантика «привести семейство к конвенции», а не «оставить без `\Stringable`
с комментарием»: «известная» дата уже имеет безопасное строковое представление
(`jsonSerialize()` → ATOM), а «пустая» дата — это null-объект, для которого пустая
строка является осмысленным строковым представлением (совпадает с её `jsonSerialize()`).

- `OutboxEventDate` (абстракция): `implements \JsonSerializable, \Stringable`.
- `KnownOutboxEventDate::__toString()` → `value->format(\DateTimeInterface::ATOM)`
  (зеркало `jsonSerialize()`).
- `EmptyOutboxEventDate::__toString()` → `''` (зеркало `jsonSerialize()`).

Так поведение полностью совпало с уже существующей JSON-сериализацией и с тем, как
ранее был доведён `OutboxAvailableAt` (`__toString()` == `jsonSerialize()`).

Побочное следствие добавления `\Stringable` на абстракцию: тест-фикстура
`UnknownOutboxEventDate` (subclass `OutboxEventDate` в `OutboxInfrastructureEdgeTest.php`,
используется для проверки, что typecast отвергает неизвестное состояние даты) тоже
обязана реализовать `__toString()`. Добавлен `__toString()` → `'unknown'` (зеркало её
`jsonSerialize()`), иначе класс не загружается. Других наследников `OutboxEventDate`
в проекте нет (проверено grep по `app/` и `tests/`).

Тест `OutboxValueObjectTest::testOutboxDateAndLastErrorHaveEmptyState()` расширен
проверками `(string) OutboxEventDate::none()` == `''`, `(string) fromDateTime($now)`
== ATOM и совпадения `(string)` с `jsonSerialize()` для обоих вариантов.

### 2. Порядок выборки relay согласован с индексом

Выбран намеренный порядок обработки «по времени доступности» (естественный для
relay). В обоих горячих запросах (`pendingForRelayEventsQuery` — ORM Select с
`forUpdate()`; `pendingForRelayRows` — Database Query Builder) `orderBy('id', 'ASC')`
заменён на составной порядок, совпадающий с последовательностью колонок индекса
`(status, available_at, id)`:

```php
->orderBy([
    'available_at' => 'ASC',
    'id' => 'ASC',
])
```

`id` (UUID v7, хронологический) оставлен как стабильный tie-breaker. Поскольку UUID v7
хронологически сортируем, итоговый порядок пачки практически совпадает с прежним
`ORDER BY id`, но теперь согласован с порядком колонок индекса горячего пути. Рядом
добавлен комментарий-обоснование на русском.

Нюанс из ревью учтён: при `status IN (...)` по нескольким значениям и диапазоне по
`available_at` PostgreSQL может не отдать полностью готовый порядок из одного прохода
индекса (возможна merge/частичная сортировка). `EXPLAIN` на представительном объёме
в рамках этого фикса не снимался — задача запрещает запуск QA/ручных проверок; правка
выравнивает `ORDER BY` с последовательностью колонок индекса, что является
необходимым условием для использования индекса как сортирующего. Реальный выигрыш
стоит подтвердить через `EXPLAIN` на боевом объёме отдельно. Корректность не
затронута: relay возвращает ту же пачку, существующие Feature-тесты выборки зелёные.

Форма `orderBy([...])` подтверждена по исходникам Cycle: и
`Cycle\Database\Query\SelectQuery::orderBy()`, и `Cycle\ORM\Select::orderBy()`
принимают `string|FragmentInterface|array`.

### 3. Стейджинг тест-хелпера

`git add tests/Feature/Modules/Outbox/Infrastructure/Fixture/OutboxQueueStatusInterceptorTestHelpers.php`
выполнен — фикс stale-heap (`cleanOrmState()` + его вызов в `persistQueuedEvent()`)
теперь полностью в индексе, статус файла в `git status` сменился с `AM` на `A`.
Коммит не делался.

## Финальная проверка

- **php-cs-fixer:** `composer cs` (dry-run, через `app-http`) — ✓ `Found 0 of 383 files that can be fixed`.
- **PHPStan:** `composer phpstan` (через `app-http`) — ✓ `[OK] No errors`.
- **Тесты Outbox Unit (без coverage):** `phpunit --testsuite Unit --filter Outbox` — ✓ `OK` (59 тестов, 177 assertions). 2 PHPUnit-deprecation — пред-существующие, не от этих правок.
- **Тесты Outbox Feature (без coverage):** `phpunit --testsuite Feature --filter Outbox` (после `php app.php migrate --force` на свежей test-схеме) — ✓ `OK` (60 тестов, 188 assertions).
- **Заметки:** QA / coverage / ручные тесты не запускались по ограничению задачи. `EXPLAIN` для пункта 2 не снимался (см. выше).

## Состояние git (без коммита)

- Пункт 3: тест-хелпер застейджен (`git add`), индекс приведён к рабочему дереву (`A`).
- Пункты 1–2: правки внесены в уже застейдженные как новые (`A`) файлы, поэтому в
  `git status` они числятся как `AM` (новый файл в индексе + свежие изменения в рабочем
  дереве). Изменения 1–2 дополнительно в индекс не добавлялись.
- Файлы с правками: `OutboxEventDate.php`, `KnownOutboxEventDate.php`,
  `EmptyOutboxEventDate.php`, `OutboxEventRepository.php`, `OutboxValueObjectTest.php`,
  `OutboxInfrastructureEdgeTest.php` (все `AM`);
  `OutboxQueueStatusInterceptorTestHelpers.php` (`A`).
- Коммит и push не выполнялись.
