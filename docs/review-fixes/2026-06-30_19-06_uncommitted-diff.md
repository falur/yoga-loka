---
review: docs/reviews/2026-06-30_18-37_uncommitted-diff.md
date: 2026-06-30 19:06
status: done
---

# Фиксы по ревью: Незакоммиченный diff — третий круг (Media URL-сервис, RemoveMediaOriginal, UploadPlanner, LocaleResolver)

Режим: `apply-optional`. Один обязательный пункт (3) исправлен. По каждому необязательному пункту
(1, 2, 4, 5) решение принято самостоятельно: 1, 4, 5 применены полностью, 2 применён частично
(только документирование компромисса), переписывание на outbox отклонено.

## Применённые правки

| # | Замечание | Файлы | Тесты | Статус |
|---|-----------|-------|-------|--------|
| 3 | Англицизмы «задиспатчат»/«транзиентной» в комментариях | `app/src/Modules/Media/Domain/Entity/Media.php` (докблок: «задиспатчат» → «запустят»), `tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php` (комментарий: «транзиентной» → «временной») | — (текст комментариев) | ✓ применено (обязательное) |
| 1 | `markReadyOriginalRemoved()` не отстаивает инвариант источника | `app/src/Modules/Media/Domain/Entity/Media.php` (ранний guard: no-op на уже `readyOriginalRemoved`, иначе разрешён только из `ready`, иначе `InvalidDomainValueException`) | `tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php` (+2: отказ из не-ready, идемпотентность) | ✓ применено (optional) |
| 2 | Окно битой ссылки: удаление оригинала до фиксации статуса | `app/src/Modules/Media/Application/Command/RemoveMediaOriginal/RemoveMediaOriginalHandler.php` (докблок: явно зафиксирован компромисс и что команда пока не для прямого user-trigger) | — (поведение не менялось) | ◐ применён вариант «оставить + задокументировать»; переписывание на outbox ✗ отклонено |
| 4 | На границе аватара не закреплён случай `readyOriginalRemoved` | `tests/Feature/Modules/User/Application/GetUserPublicProfileHandlerTest.php` (+1 кейс), `tests/Feature/Modules/User/Application/UserApplicationTestCase.php` (хелпер `persistReadyOriginalRemovedMedia()`) | feature-тест `testFallsBackToDefaultWhenAvatarOriginalRemoved` | ✓ применено (optional) |
| 5 | Дублирование тест-хелперов `readyMedia()`/`thumbnailConversion()` | `tests/Feature/Modules/Media/Application/MediaApplicationTestCase.php` (хелперы подняты в базовый класс), `FindMediaUrlHandlerTest.php` / `FindMediaOriginalUrlHandlerTest.php` (копии и ставшие лишними импорты убраны), `CheckMediaAttachableHandlerTest.php` (локальный `readyMedia(UserId)` переименован в `readyMediaOwnedBy()` из-за коллизии имён) | существующие тесты обоих классов остаются зелёными | ✓ применено (optional) |

## Решения по optional

- **Принято:**
  - **1** — guard в `markReadyOriginalRemoved()`. Дёшево, риск регрессии минимальный (единственный
    вызывающий — обработчик — уже проверяет `isReady()`), а домен начинает отстаивать заявленный в
    этом же классе инвариант (симметрично `markReadyMovedTo()` и guard'у `isFinalized` в
    `recordProcessingError`). Покрыто двумя unit-тестами.
  - **2 (частично)** — применён только лёгкий вариант «оставить порядок как есть + задокументировать
    компромисс» (ревью прямо требует зафиксировать его в README/докблоке). Дёшево, нулевой риск.
  - **4** — добавлен feature-тест на границе аватара. Дёшево, закрывает пробел по классам
    эквивалентности и служит регресс-гардом к замечанию 2 в смысловом центре changeset.
  - **5** — подъём идентичных хелперов в базовый класс. Убирает дублирование, единый источник
    правды; корректность подтверждена полным прогоном тестов. Всплывшая коллизия с третьим
    классом (`CheckMediaAttachableHandlerTest`, у которого `readyMedia(UserId)` — другой по смыслу
    метод) устранена точечным переименованием его приватного хелпера в `readyMediaOwnedBy()`.
- **Отклонено:**
  - **2 (вариант с outbox)** — перенос `deleteObject` в outbox-шаг после commit-а перехода. Причина:
    расширяет scope и архитектуру, повышает риск регрессии и требует продуктового/архитектурного
    решения. У команды пока нет HTTP-входа и прямого user-trigger, для шага не предусмотрен outbox —
    ревью само помечает пункт «на усмотрение». Компромисс зафиксирован в докблоке; вариант с outbox
    пересмотреть, когда команду будут подключать к реальному триггеру. Следующим ревьюерам не
    повторять как открытое замечание без новых аргументов.

## Финальная проверка

- **Тесты:** `make test` (Docker) — ✓ OK (1287 тестов, 4158 assertions).
- **PHPStan:** `make phpstan` (Docker) — ✓ No errors.
- **Заметки:** При подъёме хелперов в базовый класс ParaTest сначала упал фатально из-за коллизии
  имени `readyMedia` с приватным методом другого по смыслу в `CheckMediaAttachableHandlerTest`
  (нельзя сузить видимость до `private` при `protected` в родителе). Исправлено переименованием
  локального хелпера в `readyMediaOwnedBy()`; повторный прогон зелёный. Не закоммичено.
