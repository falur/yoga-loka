---
review: docs/reviews/2026-06-25_11-44_media-remove-original.md
date: 2026-06-25 13:42
status: done
---

# Фиксы по ревью: Удаление оригинала медиа с сохранением конверсий

Режим: `apply-optional`. В ревью все 9 пунктов помечены «на усмотрение автора», обязательных нет.
По каждому принято решение применить или отклонить с причиной.

## Применённые правки

| # | Замечание | Файлы | Тесты | Статус |
|---|-----------|-------|-------|--------|
| план-1 | Состав лог-контекста завершения не закреплён тестом | — | `tests/Feature/Modules/Media/Application/RemoveMediaOriginalHandlerTest.php` (новый кейс `testLogsCompletionContextWithoutRawValueObjects`, 1 ✓) | ✓ применено |
| 1 | Идемпотентный no-op не проверяет отсутствие записи в БД | — | `RemoveMediaOriginalHandlerTest.php` (`testIsIdempotentWhenOriginalAlreadyRemoved` — сверка `updatedAt`) | ✓ применено |
| 2 | Новый кейс `CheckMediaAttachableHandlerTest` не проверяет ключ исключения | — | `tests/Feature/Modules/Media/Application/CheckMediaAttachableHandlerTest.php` (`testRejectsReadyOriginalRemovedMedia` + `expectExceptionMessage`) | ✓ применено |
| 3 | Регрессионные/успешные кейсы проверяют факт вызова, но не пути | — | `RemoveMediaOriginalHandlerTest.php` (video/audio success: захват и сверка пути оригинала); `GetMediaUrlHandlerTest.php` (`testReturnsConversionUrlForReadyOriginalRemovedMedia` — сверка пути конверсии); `DeleteMediaHandlerTest.php` (`testDeletesReadyOriginalRemovedMediaConversionsAndIdempotentOriginal` — сверка путей конверсии и оригинала) | ✓ применено |
| 4 | `ProcessMediaHandlerTest` no-op не закрепляет неизменность полей ошибки | — | `tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php` (`testIsNoOpWhenMediaAlreadyOriginalRemoved` + ассерты `processingAttempts`/`processingError`) | ✓ применено |
| 5 | Дублирование `app.media.not_ready` в двух ветках `GetMediaUrlHandler` | — | — | ✗ отклонено (на усмотрение, причина ниже) |
| 6 | Дублирование тел `recordTemporaryProcessingError` и `recordPermanentProcessingError` | `app/src/Modules/Media/Domain/Entity/Media.php` (общий приватный `recordProcessingError`) | покрыто существующими доменными/handler-тестами (зелёные) | ✓ применено |
| 7 | `isFinalized()` собран через `||`, а не исчерпывающий `match` | — | — | ✗ отклонено (на усмотрение, причина ниже) |
| 8 | README: переход статусов описан линейной цепочкой | `app/src/Modules/Media/README.md` | — | ✓ применено |

## Решения по optional

- **Принято:**
  - **план-1** — дёшево, закрепляет описанный планом контракт `debug_precise` (ключи `mediaId/userId/storage/path`, camelCase, без сырых VO). Добавлен отдельный кейс с mock-логгером; helper `handler()` получил опциональный `LoggerInterface` (по умолчанию `NullLogger`).
  - **1** — дёшево, доказывает ранний `return` до `persist+run()`: после повторного вызова `updatedAt` не сдвинулся.
  - **2** — тривиальная правка, убирает асимметрию: новый кейс теперь проверяет конкретный ключ `app.media.not_ready` (подтверждён по коду handler-а).
  - **3** — повышает ценность покрытия: тесты теперь отличают «удалён/отдан правильный объект» от «не тот». Захват аргументов `deleteObject`/`publicUrl` и сверка путей.
  - **4** — прямо защищает инвариант, ради которого guard расширен до `isFinalized()`: при дубле `ProcessMedia` на `readyOriginalRemoved` поля ошибки (`processingAttempts`, `processingError`) остаются нетронутыми.
  - **6** — реальный DRY-риск: два побайтно идентичных тела. Вынесено в приватный `recordProcessingError`, публичные `recordTemporary/PermanentProcessingError` остались точками доменного контракта. Низкий риск, соответствует rules («короткие методы», «нет дублирования»).
  - **8** — тривиальная правка документации с нулевым риском: последний переход помечен как опциональный.
- **Отклонено** (чтобы следующие ревьюеры не открывали повторно без новых аргументов):
  - **5** (дублирование `app.media.not_ready` в `GetMediaUrlHandler`) — rules.md «Не плодить технические константы» прямо разрешает оставлять одноразовые технические строки рядом с использованием, пока их немного. Обе ветки (`conversionUrl()` для не-финализированного и ветка оригинала для не-ready) семантически разные и обе нужны по плану. Вынос в фабрику сейчас добавил бы абстракцию без реального драйвера переиспользования — расширение scope без пользы. Само ревью отмечает: «оставить как есть (соответствует rules)».
  - **7** (`isFinalized()` через `match` вместо `||`) — соседний предикат `isReady()` использует тот же стиль `===`. Мета-ревью (architecture-check) изначально пометило это «Править обязательно», но затем осознанно понизило до «на усмотрение» именно из-за сложившегося в файле стиля предикатов. Переписать один предикат на `match` сломало бы локальное единообразие, не устранив реального дефекта (новый `MediaStatus` — гипотетический, не входит в этот diff). Точечность важнее: меняем только то, что нужно.

## Финальная проверка

- **Тесты:** `make test` — ✓ (1270 тестов, 4102 ассерта, OK; полный pipeline с гейтом 100% покрытия прошёл)
- **Линтер/статанализ:** `make phpstan` — ✓ (No errors, PHPStan level max)
- **Заметки:** всё зелёное. Изменения не закоммичены.
