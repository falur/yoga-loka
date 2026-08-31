---
review: docs/reviews/2026-06-10_02-20_media-upload-pipeline-uncommitted-draft.md
date: 2026-06-10 12:50
status: done
mode: apply-optional
---

# Фиксы по ревью: Media upload pipeline — незакоммиченный diff (третий цикл)

Режим `apply-optional`. Обязательных пунктов («править обязательно») в ревью нет. Все 3
замечания — «на усмотрение автора». По указанию пользователя и по существу все три конкретны и
применимы: №1 — реальный функциональный пробел (утечка хранилища), №2 и №3 — пробелы защиты от
регрессии для осознанно расширенного guard'а. Все три **применены**.

## Применённые правки
| # | Замечание | Файлы | Тесты | Статус |
|---|-----------|-------|-------|--------|
| 1 | `DeleteMedia` оставляет объекты конверсий осиротевшими в S3 | `app/src/Modules/Media/Application/Command/Media/DeleteMedia/DeleteMediaHandler.php`; `app/src/Modules/Media/README.md` | `tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php` (+1 `testDeletesReadyMediaConversionObjectsFromStorage` ✓); `MediaApplicationTestCase` (+ `videoConversionRepository()`) | ✓ применено (реальный фикс) |
| 2 | Нет интеграционного теста восстановления: транзиентный сбой → ретрай → `ready` | — (только тест) | `tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php` (+1 `testProcessingRecoversFromProcessingFailedToReady` ✓) | ✓ применено |
| 3 | Нет Handler-теста `ProcessMedia` для неверного статуса (`WaitingUpload`) | — (только тест) | `tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php` (+1 `testRejectsMediaInWaitingUploadStatus` ✓) | ✓ применено |

## Решения по optional

### Принято (все три)

- **№1 (реальный фикс + тест + README):** В `DeleteMediaHandler` добавлены зависимости
  `MediaImageConversionRepository` и `MediaVideoConversionRepository`. До `entityManager->delete($media)`
  Handler вызывает приватный `deleteConversionObjects($media)`: грузит конверсии через
  `findByMediaId` и для каждой выполняет `deleteObject(storage, path)` по её собственным
  `storage`/`path` (404 идемпотентно игнорируется сервисом). Это закрывает утечку: раньше FK
  `ON DELETE CASCADE` убирал строки конверсий из БД, а файлы оставались осиротевшими в постоянном
  бакете (`media-public`/`media-private`) без expiry. Видео-конверсии включены превентивно (тот же
  контракт `storage`/`path` + CASCADE); в проде сейчас генерируются только image-конверсии, поэтому
  видео-цикл просто холостой до их появления. `handle()` остаётся коротким (guard-clauses + 1 вызов
  приватного метода); `foreach` оправдан побочным эффектом `deleteObject` (rules.md:19). README
  дополнен явной записью про удаление объектов конверсий в разделе «Транзакционная дисциплина S3».
  Новый feature-тест `testDeletesReadyMediaConversionObjectsFromStorage` создаёт `ready`-медиа с
  персистнутой image-конверсией и проверяет, что `deleteObject` вызван и для пути конверсии, и для
  оригинала, а после удаления конверсий в БД не остаётся. В базовый `MediaApplicationTestCase`
  добавлен accessor `videoConversionRepository()`.

- **№2 (интеграционный тест восстановления):** В `MediaProcessingFlowTest` добавлен
  `testProcessingRecoversFromProcessingFailedToReady` — сквозной кейс восстановления: валидный
  JPEG-оригинал заливается в upload-бакет, медиа переводится в `ProcessingFailed` через
  `recordTemporaryProcessingError`, затем реальный `ProcessMediaHandler` (из контейнера, с настоящими
  Imagick/S3) обрабатывает медиа с конверсией. Проверяется `status === Ready`, очистка
  `processingError`, наличие 1 конверсии, перекладка оригинала в целевой бакет (`path == readyPath`,
  `headObject` подтверждает оригинал и конверсию). Это закрывает пробел: единственная причина
  расширения guard'а `markReadyMovedTo` до `ProcessingFailed` теперь под защитой от регрессии (если
  guard снова сузят до `uploaded`/`processing`, этот тест упадёт). Объекты S3 регистрируются в
  `track()` и чистятся в `tearDown`.

- **№3 (Handler-тест неверного статуса):** В `ProcessMediaHandlerTest` добавлен
  `testRejectsMediaInWaitingUploadStatus` — медиа в `WaitingUpload` (загрузка не подтверждена)
  передаётся в `ProcessMediaHandler::handle`, ожидается `InvalidDomainValueException` от доменного
  `markReadyMovedTo`. Это требование плана фазы 4 («покрыть неверный статус для каждого Handler») и
  фиксирует связку Handler → guard на границе Application, а не только изолированный domain-переход.
  Выбран вариант ревью «ожидать `InvalidDomainValueException`», без раннего отклонения в Handler'е —
  поведение уже корректно (500 для неподтверждённого статуса), вводить отдельную раннюю проверку с
  `ValidationException` не требуется и расширило бы scope.

### Отклонено

Нет. Все три optional-пункта применены.

## Финальная проверка
- **Стиль (cs) + PHPStan (level max):** `make phpstan` — ✓ No errors. `make qa` (cs → phpstan →
  test → coverage): cs и phpstan зелёные.
- **Тесты:** `make test` / `make qa` — ✓ 317/317 (было 314, +3 новых), все Media-тесты зелёные.
  Точечный прогон трёх затронутых файлов: ✓ 14/14, 52 assertions. Deprecations (1 + 29 PHPUnit) —
  пред-существующие, не связаны с задачей.
- **Покрытие:** `make qa` шаг `test-coverage` — ✗ глобальный gate < 100% (пред-существующий долг
  не-media классов: `LazyGhost*`, `ValueObjectCast`, `ConfigMappingException`,
  `ExceptionHandlerBootloader` и т.п. — сверка с планом №1, осознанное отклонение). Новый/затронутый
  media-код покрыт на 100%: новая ветка `deleteConversionObjects` исполняется обоими циклами
  (image — заполненный, video — холостой), оба покрыты; ветки `ProcessMedia` и flow-восстановления
  покрыты новыми тестами. Регресса покрытия media-кода нет.
- **Заметки:** красным остаётся только глобальный coverage-gate — пред-существующее осознанное
  отклонение плана, не регресс этой задачи. cs/phpstan/test зелёные.
