---
review: docs/reviews/2026-06-07_15-10_outbox-module-draft-iter2.md
date: 2026-06-07 15:46
status: done
mode: apply-optional
---

# Фиксы по ревью: Модуль Outbox (итерация 2)

Режим `apply-optional`: применены и «править обязательно» (№4), и все «на усмотрение
автора» (№1, №2, №3). Пунктов «не править» в ревью нет.

## Применённые правки

| # | Замечание | Файлы | Тесты | Статус |
|---|-----------|-------|-------|--------|
| 1 | Дублирование критерия равенства момента времени (микросекунды) в двух VO | `app/src/Shared/Domain/Trait/ComparesDateTimeToMicroseconds.php` (новый трейт), `app/src/Modules/Outbox/Domain/ValueObject/OutboxAvailableAt.php`, `app/src/Modules/Outbox/Domain/ValueObject/KnownOutboxEventDate.php` | `tests/Unit/Modules/Outbox/Domain/OutboxValueObjectTest.php` (существующие equals-проверки проходят) | ✓ применено |
| 2 | Частично битый payload очереди тихо превращается в «не наша задача» без сигнала | `app/src/Modules/Outbox/Infrastructure/OutboxQueueStatusInterceptor.php` | `tests/Feature/Modules/Outbox/Infrastructure/OutboxQueueStatusInterceptorTest.php` (2 кейса дополнены проверкой WARN-лога) | ✓ применено |
| 3 | Пороги устойчивости relay-цикла зашиты константами | `app/config/outbox.php`, `.env.sample`, `app/src/Shared/Infrastructure/Configuration/Outbox/OutboxConfig.php`, `app/src/Modules/Outbox/Infrastructure/OutboxRelayWorker.php` | `tests/Unit/Modules/Outbox/Infrastructure/OutboxRelayWorkerTest.php` (+ новый тест на настраиваемый порог), `tests/Unit/Shared/Infrastructure/Configuration/SimpleConfigMapperTest.php`, `SimpleConfigBindingTest.php`, `tests/Feature/Modules/Outbox/Infrastructure/OutboxQueueStatusInterceptorFailureTest.php`, `OutboxRelayPublishTest.php`, `Fixture/OutboxRelayTestHelpers.php` (адаптация конструктора `OutboxConfig`) | ✓ применено |
| 4 | VO `OutboxAvailableAt` не реализует `\Stringable` (нарушение обязательного правила) | `app/src/Modules/Outbox/Domain/ValueObject/OutboxAvailableAt.php` | `tests/Unit/Modules/Outbox/Domain/OutboxValueObjectTest.php` (проверка `(string) $vo` и идентичности с `jsonSerialize()`) | ✓ применено |

## Как именно применено

### №4 — `OutboxAvailableAt implements \Stringable` (обязательно)
Класс теперь `implements \JsonSerializable, \Stringable`, добавлен метод
`__toString(): string`, возвращающий `format(\DateTimeInterface::ATOM)` — тот же формат,
что и `jsonSerialize()`, без новой семантики. В unit-тест добавлены проверки
`(string) $vo === jsonSerialize()`. Смежный «на усмотрение» пункт про иерархию
`OutboxEventDate`/`KnownOutboxEventDate` (нюанс null-объекта `EmptyOutboxEventDate`) в №4
не входит и не правился.

### №1 — общий критерий сравнения момента времени
Создан трейт `App\Shared\Domain\Trait\ComparesDateTimeToMicroseconds` с приватным
статическим хелпером `dateTimeEqualsToMicroseconds(value, other)` (равенство по секундам +
микросекундам). Оба VO (`OutboxAvailableAt`, `KnownOutboxEventDate`) подключают трейт и
вызывают хелпер из своих `equals()`. Доменный смысл VO остался разным — объединены только
критерий сравнения. Вызовы хелпера используют именованные аргументы (правило проекта,
проверяется PHPStan).

### №2 — заметность частично битого outbox-payload
В `outboxQueueEnvelopeFromParameters()` перед возвратом `null` добавлен WARN-лог для двух
случаев повреждённого входа: payload-массив без обоих ключей `OUTBOX_ID`/`OUTBOX_TYPE` и
payload-массив с нестроковыми значениями этих ключей. Легитимный не-outbox-вход
(`!is_array($payload)`) остался тихим возвратом `null` — это явно не-outbox-задача, ревью
исключает его из замечания. Поведение «задача не считается outbox» сохранено; добавлена
только видимость аномалии. Два существующих теста дополнены проверкой `hasRecord(warning, …)`
через `interceptorWithLogger(RecordingOutboxLogger)`.

### №3 — пороги устойчивости relay в конфиг
Три захардкоженные константы воркера вынесены в `OutboxConfig` с env-значениями по
умолчанию (как `maxAttempts`):
- `OUTBOX_MAX_CONSECUTIVE_RELAY_FAILURES=10`
- `OUTBOX_BASE_RELAY_RETRY_DELAY_SECONDS=1`
- `OUTBOX_MAX_RELAY_RETRY_DELAY_SECONDS=30`

`OutboxRelayWorker` теперь инжектит `OutboxConfig` и читает пороги из него (`final readonly`,
DI-биндинг — autowire, ничего в bootloader менять не пришлось). Хардкод
`CLAIM_TIMEOUT_SECONDS`/`PUBLISH_RETRY_DELAY_SECONDS` в `OutboxRelay` намеренно оставлен (вне
этого замечания). Конструктор `OutboxConfig` получил три новых поля — обновлены все места его
создания в тестах (через общий хелпер `outboxConfigWithMaxAttempts()` в
`OutboxRelayTestHelpers` и локальный хелпер в unit-тесте воркера). В `OutboxRelayWorkerTest`
добавлен тест, проверяющий остановку по настроенному порогу из конфига, а не по константе.

## Финальная проверка
- **php-cs-fixer:** `composer cs` (dry-run) — ✓ (0 файлов к правке); `composer cs:fix` — ✓ (изменений нет).
- **PHPStan:** `composer phpstan` — ✓ (No errors). Первый прогон поднял `project.namedArgumentsRequired`
  на вызовах нового хелпера — исправлено переходом на именованные аргументы, повторный прогон зелёный.
- **Тесты (без coverage):** `vendor/bin/phpunit --no-coverage tests/Unit/Modules/Outbox tests/Feature/Modules/Outbox tests/Unit/Shared/Infrastructure/Configuration`
  (в Docker test-runner с миграциями) — 171 тест, 170 ✓, **1 ✗**.

### Заметки
- Единственный падающий тест — `OutboxQueueStatusInterceptorTest::testInterceptorDoesNotRunJobWhenHeadersAndPayloadHaveDifferentOutboxIds`.
  Это **пре-существующая поломка в незакоммиченном коде Outbox-среза, не связанная ни с одним из
  четырёх применённых замечаний** и воспроизводится в изоляции. Тест не менялся в рамках фиксов;
  ни один из правленых файлов не влияет на его путь выполнения (тест отправляет полноценный
  `OutboxQueueEnvelope`-payload, ветку WARN-лога из №2 не задевает).
- Причина падения: `persistQueuedEvent()` делает DB-уровневый CAS `markQueuedIfPublishing()`, затем
  `findById()` (через `findByPK`), который возвращает закэшированную в ORM-heap сущность со статусом
  `Publishing` (строка в БД при этом `queued`). Ассерт `assertSame(Queued, $storedOutboxEvent->status)`
  падает на устаревшем in-memory статусе. Это вопрос рефреша сущности в тестовом helper'е/репозитории,
  а не предмет данного ревью. Покрытие тестами (coverage) по указанию не собиралось.
- Все тесты, добавленные/изменённые по фиксам №1–№4 (конфиг-маппинг, инъекция конфига в воркер,
  настраиваемый порог, `(string)` для `OutboxAvailableAt`, WARN-логи интерцептора, рефактор
  `OutboxRelayPublishTest`), проходят.

## Состояние git
Не закоммичено. Изменения по фиксам:
- Новый файл: `app/src/Shared/Domain/Trait/ComparesDateTimeToMicroseconds.php`.
- Изменены: `app/config/outbox.php`, `.env.sample`,
  `app/src/Shared/Infrastructure/Configuration/Outbox/OutboxConfig.php`,
  `app/src/Modules/Outbox/Domain/ValueObject/OutboxAvailableAt.php`,
  `app/src/Modules/Outbox/Domain/ValueObject/KnownOutboxEventDate.php`,
  `app/src/Modules/Outbox/Infrastructure/OutboxQueueStatusInterceptor.php`,
  `app/src/Modules/Outbox/Infrastructure/OutboxRelayWorker.php`,
  `tests/Unit/Modules/Outbox/Domain/OutboxValueObjectTest.php`,
  `tests/Unit/Modules/Outbox/Infrastructure/OutboxRelayWorkerTest.php`,
  `tests/Unit/Shared/Infrastructure/Configuration/SimpleConfigMapperTest.php`,
  `tests/Unit/Shared/Infrastructure/Configuration/SimpleConfigBindingTest.php`,
  `tests/Feature/Modules/Outbox/Infrastructure/OutboxQueueStatusInterceptorTest.php`,
  `tests/Feature/Modules/Outbox/Infrastructure/OutboxQueueStatusInterceptorFailureTest.php`,
  `tests/Feature/Modules/Outbox/Infrastructure/OutboxRelayPublishTest.php`,
  `tests/Feature/Modules/Outbox/Infrastructure/Fixture/OutboxRelayTestHelpers.php`.

Дальше: `eda-commit`.
