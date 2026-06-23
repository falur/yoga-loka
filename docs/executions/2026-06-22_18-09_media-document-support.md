---
plan: docs/plans/2026-06-22_17-35_media-document-support.md
started: 2026-06-22 18:09
finished: 2026-06-22 18:34
status: done
---

# Журнал: Поддержка документов в модуле Media

## Шаги
| # | Шаг | Файлы | Тесты | Статус |
|---|-----|-------|-------|--------|
| 1 | Нормализация MIME и классификация документа: `baseValue()`, whitelist в резолвере (проверяется первым), сравнение по `baseValue()` в `containsMimeType()`, обновлён докблок резолвера | `MediaMimeType.php`, `MediaMimeTypeCollection.php`, `MediaTypeResolver.php` | `MediaTypeResolverTest`, `MediaValueObjectTest` (baseValue), новый `MediaMimeTypeCollectionTest` | `make test-unit` OK (572), `make phpstan` OK |
| 2 | Префикс `documents`: расширена regex в `assertValid()`, ветка `Document => 'documents'` в `originalReady()` | `MediaPath.php` | `MediaValueObjectTest` (originalReady Document, fromString documents/ позитив+негатив по shard) | `make test-unit` OK (573), `make phpstan` OK |
| 3 | `ProcessMediaHandler`: `Document => []` (пустой набор конверсий), удалён неиспользуемый импорт `InvalidDomainValueException` | `ProcessMediaHandler.php` | `ProcessMediaHandlerTest` (перевёрнут негатив в `testProcessesDocumentByMovingOriginalWithoutConversions`) | точечный `ProcessMediaHandlerTest` OK (11), `make phpstan` OK |
| 4 | `CompleteMediaUploadHandler`: `Document => assertDocumentPlan()` (пустой план обязателен, иначе `conversion_plan_type_mismatch`) + end-to-end загрузка документа | `CompleteMediaUploadHandler.php` | `CompleteMediaUploadHandlerTest` (перевёрнут негатив + data-provider непустого плана), `RequestMediaUploadHandlerTest` (документ, charset-MIME, негатив `mime_not_allowed`), `MediaProcessingFlowTest` (end-to-end documents/ через MinIO), `GetMediaUrlHandlerTest` (документ public/private), `CheckMediaQueriesTest` (документ не image) | точечный `tests/Feature/Modules/Media` OK (140), `make phpstan` OK |

## Заметки

- Фаза 1 проверена быстрым `make test-unit` (все её тесты в Unit-сьюте) + `make phpstan`. Полный `make test` запланирован в финальной проверке.
- Фазы 3–4 (feature-тесты) проверены точечным прогоном `tests/Feature/Modules/Media` (TEST_TOKEN пуст → базовая БД `yoga_loka_test`/bucket `yoga-loka-test`), чтобы не гонять весь Feature-сьют дважды. Полный gate — в финальной проверке.
- В `ProcessMediaHandler` после замены `Document => throw` на `Document => []` импорт `InvalidDomainValueException` стал неиспользуемым — удалён (правило «Нет мёртвого кода»).
- Резолвер: новый докблок убирает ложные утверждения «документы отклоняются» и «только uploads|images|videos|audios», добавляет упоминание списка разрешённых MIME-типов и префикса `documents` (критерий готовности докблока выполнен).
- Изменений в `docs/rules.md`/`docs/arch.md` не потребовалось: план сознательно без новых сущностей, контрактов, миграций и архитектурных решений.

## Финальная проверка

- `make phpstan` — OK (No errors), прогнан после всех правок Фазы 4.
- `make test` — первый полный прогон дал 1224/1225 и 1 ошибку в `OutboxRelayPublishTest::testRelayReclaimsStuckPublishingEventAndMarksFailedWhenAttemptsExhausted` (модуль Outbox, мной не затронут; «строка без статуса»). Тест проходит в изоляции (12/12) и при повторном полном прогоне — 1225/1225, 4006 assertions, OK. Вывод: транзиентный флаки в Outbox при параллельном выполнении, не связан с задачей документов. Свой код не подавлялся.

## Изменения в docs

## Изменения в docs
