---
review: docs/reviews/2026-06-06_17-45_outbox-module-draft-iter4.md
date: 2026-06-06 22:29
status: done
mode: apply-optional
---

# Фиксы по ревью: модуль Outbox (итерация 4)

Режим `apply-optional`: применены и «править обязательно», и «на усмотрение автора»
без интерактивных вопросов. Пунктов «не править» в ревью нет.

## Применённые правки

| # | Замечание | Файлы | Тесты | Статус |
|---|-----------|-------|-------|--------|
| 2 (StoredOutboxEventId) | Фабрика `fromString()` дублировала публичный конструктор без нового смысла — прямой запрет правила «Не дублировать конструктор фабриками без смысла» | `app/src/Modules/Outbox/Application/Message/StoredOutboxEventId.php` | существующий `OutboxMessageSerializerTest::testStoredOutboxEventIdRejectsEmptyValue` (через `fromString`) ✓ | ✓ применено (обязательно) |
| 2 (KnownOutboxEventDate) | Конкретный конструктор был публичным, хотя единственный санкционированный вход — абстрактная фабрика `OutboxEventDate::fromDateTime()` | `app/src/Modules/Outbox/Domain/ValueObject/KnownOutboxEventDate.php` | существующие unit-тесты VO ✓ | ✓ применено (на усмотрение) |
| 1 (RABBITMQ_VHOST) | Нестандартный vhost без ведущего слэша даёт битый AMQP-адрес; контракт нигде не зафиксирован | `.env.sample`, `app/src/Modules/Outbox/README.md` | инфраструктура не покрывается юнит-тестами | ✓ применено (на усмотрение, вариант «зафиксировать контракт документацией») |
| 3 (ValidOutboxPendingRow) | Мёртвые поля `createdAt`/`updatedAt` — не читаются ни в коде, ни в тестах (правило «Нет мёртвого кода») | `app/src/Modules/Outbox/Repository/ValidOutboxPendingRow.php`, `OutboxPendingRow.php`, `OutboxEventRepository.php`, `tests/Unit/Modules/Outbox/Repository/OutboxPendingRowTest.php` | `OutboxPendingRowTest` (10 ✓), feature repository-тесты ✓ | ✓ применено (на усмотрение, дефолт — удалить) |

## Детали по правкам

### Замечание 2 — `StoredOutboxEventId` (обязательно)
Конструктор сделан `private`, `fromString()` остаётся единственным входом — как у
`OutboxLastError`/`OutboxAvailableAt` с приватным конструктором. Это сохраняет
существующие точки создания (`OutboxEventStore.php:34`, тест сериализатора) без
изменений и закрывает запрет правила. Прямого `new StoredOutboxEventId(...)` в
коде/тестах нет (проверено grep).

### Замечание 2 — `KnownOutboxEventDate` (на усмотрение, консистентность)
Конкретный конструктор понижен до `protected`: единственный `new
KnownOutboxEventDate(...)` находится внутри родительской фабрики
`OutboxEventDate::fromDateTime()` (та же иерархия классов → `protected` доступен).
Снаружи прямого создания нет.

### Замечание 1 — `RABBITMQ_VHOST` (на усмотрение)
Выбран безопасный вариант из ревью — зафиксировать контракт документацией, не меняя
семантику переменной. Причина: `RABBITMQ_VHOST` используется в двух местах с разным
требуемым форматом — в `docker/rr/http-jobs.yaml:40` как path-сегмент AMQP-адреса
(нужен ведущий слэш) и в `docker/docker-compose.dev.yml:173` как
`RABBITMQ_DEFAULT_VHOST` (имя vhost RabbitMQ, где `/` — корректное литеральное
значение по умолчанию). Переписывание шаблона под «имя без слэша» затронуло бы общий
docker-compose контракт за пределами модуля и рисковало сломать рабочий дефолт.
Добавлен явный комментарий рядом с `RABBITMQ_VHOST` в `.env.sample` и абзац в README
модуля Outbox: значение обязано начинаться со слэша, иначе jobs-консьюмер не
поднимется, а события копятся в pending.

### Замечание 3 — `ValidOutboxPendingRow` (на усмотрение, дефолт — удалить)
Убраны `createdAt`/`updatedAt` из DTO (`ValidOutboxPendingRow`), из парсинга
(`OutboxPendingRow::fromDatabaseRow()`) и из выборки `pendingForRelayRows()`
(`OutboxEventRepository`). Из фикстуры `validRow()` в unit-тесте убраны
неиспользуемые ключи `created_at`/`updated_at`. Хелпер `dateField()` остаётся — он
по-прежнему используется для `available_at`. Колонки `updated_at` в UPDATE-запросах
(`markRowFailedById`, `markQueuedIfPublishing`) не затронуты: это запись timestamp, а
не чтение мёртвых полей.

## Финальная проверка
- **php-cs-fixer:** `composer cs` (dry-run) — ✓ (0 из 381 файлов требуют правок)
- **PHPStan:** `composer phpstan` — ✓ (No errors)
- **Тесты:** `make test` (`composer test` = phpunit, без coverage) — ✓ (240 тестов, 868 ассертов, OK)
- **Coverage:** не запускался по явному указанию пользователя.
- **Заметки:** PHPUnit показал 28 deprecation-нотисов — они предшествующие, не
  падения и не связаны с этими правками.
