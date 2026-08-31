---
review: docs/reviews/2026-06-30_20-54_uncommitted-diff.md
date: 2026-06-30 21:26
status: done
---

# Фиксы по ревью: Незакоммиченный diff — шестой круг (доводка до 95/100)

Режим: `apply-optional`. Обязательных замечаний нет — 4 пункта «на усмотрение».
Все 4 закрыты конструктивно (реальной правкой). Регрессий нет: `make test` и
`make phpstan` зелёные.

## Применённые правки

| # | Замечание | Файлы | Тесты | Статус |
|---|-----------|-------|-------|--------|
| 1 | Каталоги `Application/Service` и `Shared/Domain/Locale` не описаны в дереве каталогов | `docs/arch.md` | — (только документация) | ✓ применено |
| 2 | `PostMedia::create()` не инициализирует non-nullable связь `$media` | `app/src/Modules/Posts/Domain/Entity/PostMedia.php` | — (вариант А, только докблок) | ✓ применено (вариант А) |
| 3 | Статус конверсии не учитывается в чекере наличия и при сборке URL | `app/src/Modules/Media/Repository/Media{Image,Video,Audio}ConversionRepository.php`, `app/src/Modules/Media/Application/Service/MediaConversionsChecker.php`, `app/src/Modules/Media/Infrastructure/FileService/MediaUrlService.php` | `MediaRepositoryTest` (+1 тест, обновлён 1), `RemoveMediaOriginalHandlerTest` (+1), `FindMediaUrlHandlerTest` (+1) | ✓ применено (вариант А) |
| 4 | Докблок `UserPublicProfileAssembler` неточно ссылается на `PostViewAssembler` | `app/src/Modules/User/Application/Profile/UserPublicProfileAssembler.php` | — (только докблок) | ✓ применено |

## Что сделано по каждому пункту

### §1 (docs) — применено
Одной правкой `docs/arch.md` зафиксировал каталоги в дереве «Структура каталогов»:
- под `Application/` добавлена строка `Service/` с краткой конвенцией: внутримодульные
  stateless-помощники Application (резолверы/чекеры), инкапсулирующие репозитории своего
  модуля под один use-case-вопрос. Подтверждено двумя существующими сервисами
  (`MediaTypeResolver`, `MediaConversionsChecker`; ещё один — `NotificationSettingsViewFactory`
  в `Notifications/Application/Service`).
- под `Shared/Domain/` добавлены строки `Locale/` (доменный сервис разбора локали
  `LocaleResolver`), а заодно ранее не описанные `Collection/` (`TypedCollection`),
  `Enum/` (`Locale`) и `Pagination/` (`CursorSlice`) — тот же накопленный документационный
  долг. Код не трогал.

### §2 (quality) — применено, вариант А (обоснование)
Сначала посмотрел вызывающий контекст. Единственный production-вызов —
`PostContentComposer::attachMedia()`: он держит только строковый идентификатор медиа
(валидирует его `CheckMediaAttachableQuery`, которая не возвращает сущность `Media`), а FK
вложения пишет через колонку `mediaId` (`PostMediaReference`). Сущности `Media` на руках нет.
Связь `$media` объявлена `cascade: false, fkCreate: false` и нужна только для eager-load при
чтении. Вариант Б (передавать `Media` в `create()`) потребовал бы дополнительного
межмодульного запроса в `Media/Application`, который текущий поток сознательно избегает, и
исказил бы модель записи (FK уже пишется колонкой). Поэтому выбран **вариант А**: в докблок
свойства `$media` добавлено явное предупреждение, что `create()` связь не инициализирует
(в отличие от `$post`), сущность `Media` в сценарии создания недоступна, и `$media` безопасен
только после ORM-гидрации; обращение до гидрации бросит `Error`.

### §3 (quality) — применено, вариант А (конструктивно)
Выбран **вариант А** (фильтрация по `MediaConversionStatus::Ready`), как предпочитал ревьюер.
Вариант Б (закрепить инвариант тестом) оставляет read-путь молча эквивалентным для всех
статусов; вариант А делает семантику явной и устойчивой к будущему частичному/асинхронному
конвейеру, не меняя текущее поведение (сейчас все конверсии пишутся `Ready`). Сделано:
- три репозитория конверсий: `existsForMediaId()` → `existsReadyForMediaId()` с дополнительным
  `->where('status', MediaConversionStatus::Ready->value)`; обновлены докблоки.
- `MediaConversionsChecker::hasAnyConversion()` теперь зовёт `existsReadyForMediaId()`; докблок
  уточнён («хотя бы одна готовая (Ready) конверсия»).
- `MediaUrlService::conversionUrlsOf()` фильтрует связи по `status === Ready` до сборки URL;
  докблоки класса и метода уточнены.
- Тесты-ветки «есть только не-Ready конверсия»:
  - репозитории: новый `testExistsReadyForMediaIdIgnoresNonReadyConversions` (+ обновлён
    существующий `testExistsReadyForMediaIdReportsReadyConversionPresence`); helper-методы
    создания конверсий получили опциональный `status` (по умолчанию `Ready`).
  - чекер (через хендлер): новый `RemoveMediaOriginalHandlerTest::testRejectsReadyImageWithOnlyNonReadyConversion`
    — ready-медиа с единственной `processing`-конверсией → `app.media.no_conversions_to_keep`.
  - сборка URL: новый `FindMediaUrlHandlerTest::testExcludesNonReadyConversionsFromUrls`
    — в набор попадает только готовая конверсия, `processing`-видео отсутствует.

### §4 (docs/quality) — применено
Из докблока `UserPublicProfileAssembler` убрана неточная отсылка «(как в PostViewAssembler)»
для медиа-случая (там теперь прямой вызов `MediaUrlServiceContract::getOriginalUrl()`).
Оставлен общий тезис про nullable-результат шины без привязки к конкретному образцу.

## Решения по optional
- **Принято (конструктивно):** §1, §2 (вариант А — обоснован недоступностью `Media` в
  вызывающем контексте), §3 (вариант А — фильтр по `Ready`), §4.
- **Отклонено:** нет.

## Финальная проверка
- **Тесты:** `make test` — ✓ (1291 тестов, 4171 проверка, OK).
- **Линтер/статанализ:** `make phpstan` — ✓ (No errors).
- **Заметки:** новые ветки кода (`existsReadyForMediaId`, фильтр Ready при сборке URL)
  покрыты добавленными тестами. Не коммичено, индекс не сброшен.
