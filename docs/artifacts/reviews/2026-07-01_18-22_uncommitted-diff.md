---
title: Рефакторинг Media URL-сервисов, RemoveMediaOriginal и config-independence (4-й круг)
date: 2026-07-01 18:22
target: git diff HEAD (staged + unstaged + новые файлы)
plan: none
mode: strict
score: 94
status: final
meta_reviewers: [architecture-check, rules-check, quality-check, codex]
---

# Ревью: Рефакторинг Media URL-сервисов, RemoveMediaOriginal и config-independence (4-й круг)

## Оценка

**94/100.** Четвёртый круг доводки того же крупного дифа. Все обязательные замечания прошлых кругов
устранены и на месте: исчерпывающий `match` без `default` в `MediaUrlService::resolverFor`, докблоки
про инвариант FK RESTRICT у `PostMedia::$media` и про отсутствие runtime-потребителя у `FindMediaUrl`,
симметрия happy-path-ассертов `MediaResult` для image/video/audio, консолидация билдеров конверсий в
`MediaApplicationTestCase`, тест отклонения явного нулевого TTL для private в `FindMediaOriginalUrl`.

Отдельно проверено то, о чём просил запрос — не сломала ли и не создала ли лишнюю связность
консолидация билдеров конверсий в базовый тест-кейс: не сломала. Билдеры `imageConversion`/
`videoConversion`/`audioConversion` в базовом кейсе используют `storage: $media->storage`, а у всех
трёх потребителей (`FindMediaUrlHandlerTest`, `RemoveMediaOriginalHandlerTest`,
`GetAudioWaveformHandlerTest`) исходное медиа приходит в `MediaStorage::Public` — то же значение, что
раньше хардкодили удалённые локальные копии, поэтому поведение и смысл ассертов не изменились. Единая
точка для фикстур конверсий — намеренная и уже одобренная в 3-м круге консолидация; новой вредной
связности она не вносит.

Обязательных новых проблем в этом круге не найдено. Единственное необязательное наблюдение — минорное
дублирование `existsReadyForMediaId` в трёх репозиториях конверсий (см. «Рекомендации»), которое
зеркалит уже принятую тройную копию `findByMediaId` и на оценку не влияет. Ранее отклонённые и
осознанно отложенные пункты (дублирование предела presigned-TTL,
eager-load конверсий в ленте, N+1 разрешения аватаров в листинге профилей, порядок «удалить объект →
зафиксировать статус» в `RemoveMediaOriginal`) повторно не поднимаю: новых содержательных аргументов
против прежних решений нет. По порядку в `RemoveMediaOriginal` фиксирую только неизменный статус:
команда по-прежнему не подключена ни к одному входу, поэтому предусловие «перед подключением к
триггеру перевести `deleteObject` в after-commit outbox-шаг» остаётся открытым, а не новым замечанием.

## Проблемы сверки с планом

Проверка плана пропущена: план не указан и не найден.

## Замечания

Явных новых проблем в коде не найдено.

Кратко о том, что проверено и признано корректным (не замечания, а обоснование пустого списка —
чтобы следующий круг не искал это заново):

- **Консолидация билдеров в `MediaApplicationTestCase` безопасна.** `readyAudioMedia()` в
  `GetAudioWaveformHandlerTest` отдаёт `storage: Public`, поэтому общий `audioConversion()` через
  `$media->storage` строит ту же конверсию, что прежняя локальная копия с `storage: MediaStorage::Public`.
  Волна `[0, 64, 128, 255]` и ассерт в `GetAudioWaveform` теперь берутся из общего билдера — это
  ожидаемая цена DRY-фикстур, а не дефект; `make qa` (3-й круг) с этим билдером зелёный. Никакого
  скрытого расхождения между видео-кейсом в `RemoveMediaOriginalHandlerTest` и логикой сценария нет:
  билдер `readyVideoMediaWithConversion()` создаёт медиа `MediaType::Video` с video- и
  image-Poster-конверсиями, а `RemoveMediaOriginal` не ветвится по типу медиа — ему важны только
  `ready` и наличие ≥1 готовой конверсии. (Сценарий «медиа Image с video/audio-конверсиями» относится
  к `FindMediaUrlHandlerTest`, а не к удалению оригинала.)
- **Новый продуктовый код** (`MediaUrlService` + резолверы, `MediaUploadPlanner`,
  `MediaConversionsChecker`, enum `MediaConversionKind`, `RemoveMediaOriginalHandler`,
  `FindMediaOriginalUrlHandler`, доменные `isFinalized`/`isOriginalRemoved`/`markReadyOriginalRemoved`
  и приватный `recordProcessingError`, `LocaleResolver`, фабрики `UserBootloader`/`AppBootloader`)
  правилам и архитектуре не противоречит: config-independence Application выдержана (ни один
  Handler/контракт не импортирует `*Config`), foundational-исключение `Media` соблюдено и
  задокументировано, guard-clause-переходы идемпотентны, коллекционные пайплайны идиоматичны
  (`filter+map+values`, `toBase()`, variadic-spread), `existsReadyForMediaId` через `count()` держит
  репозитории read-only, `declare(strict_types=1)`, именованные аргументы, trailing commas и
  типизированные исключения на месте.
- **Оба потребителя URL-слоя используют разные механизмы осознанно.** `PostViewAssembler` держит
  сущность `Media` уже загруженной через relation и зовёт `MediaUrlServiceContract::getOriginalUrl`
  напрямую; `UserPublicProfileAssembler` имеет только идентификатор аватара и идёт через `QueryBus` +
  `FindMediaOriginalUrl`. Асимметрия объясняется разной формой входа (сущность против id) и
  задокументирована в докблоках; PHPStan level max (по отчётам прошлых кругов) сохраняет `null` в
  выводе типов через шину — контракт «медиа недоступно → null → значение по умолчанию» не потерян.
- **Синхронизация конфига/документации полная**: `presignedTtlSeconds` добавлен в `app/config/media.php`,
  `MediaConfig` (+ валидация верхней границы при старте), `.env.sample`, `phpunit.xml`, README и
  arch.md; все тест-конструкторы `MediaConfig` обновлены новым полем.

## Рекомендации

- **Править обязательно:** —
- **На усмотрение автора:**
  - **Тройное дублирование `existsReadyForMediaId` в репозиториях конверсий** (`quality`).
    Метод байт-в-байт повторяется в `MediaImageConversionRepository`, `MediaVideoConversionRepository`,
    `MediaAudioConversionRepository` (тело `->where('media_id')->where('status', Ready)->count() > 0`,
    различается лишь слово image/video/audio в докблоке). Риск низкий: зеркалит уже принятую тройную
    копию `findByMediaId` в тех же классах, соответствует локальному стилю и на оценку не влияет.
    Вынесение общего булевого метода в `AbstractRepository` было бы улучшением, но не обязательно.

## Изменения после мета-ревью

Режим `strict`. Запущены `architecture-check`, `rules-check`, `quality-check` (одним batch) и кросс-CLI
`codex`. `plan-check` не запускался: план не указан в ревью (`plan: none`).

### После architecture-check / rules-check / quality-check
- **+ Добавлено:** один optional «на усмотрение автора» — тройное дублирование `existsReadyForMediaId`
  в трёх репозиториях конверсий (`quality`, низкий риск, зеркалит принятую копию `findByMediaId`, на
  оценку не влияет). Поднято `quality-check`, проверено по коду.
- **~ Изменено:** исправлена фактическая неточность в обосновании пустого списка — видео-медиа в
  `RemoveMediaOriginalHandlerTest` имеет тип `MediaType::Video` (с video- и image-Poster-конверсиями),
  а не Image; ложная деталь «тип у него Image» убрана, верный вывод «`RemoveMediaOriginal` не ветвится
  по типу медиа» сохранён. Ошибку независимо нашли `quality-check` и `architecture-check`, подтверждена
  чтением билдера `readyVideoMediaWithConversion()`.
- **− Убрано:** ничего.
- **Отклонено:** косметическая лишняя пустая строка перед `}` в `FindMediaOriginalUrlHandlerTest`
  (`rules-check`) — уровень php-cs-fixer, не проблема ревью. Новых обязательных нарушений правил и
  архитектуры не найдено — вывод ревью «явных новых проблем не найдено» подтверждён.

### После соседнего CLI (codex)
- **+ Добавлено:** ничего — codex не нашёл пропущенных существенных проблем (проверил целевые файлы,
  новые `??`-файлы, отсутствие висячих ссылок на удалённый `GetMediaUrl`, границы модулей, URL для
  `ready`/`readyOriginalRemoved`, TTL, биндинги, тесты; `php -l` и `git diff --check HEAD` без ошибок).
- **− Убрано:** ничего.
- **Отклонено:** codex подтвердил корректность вывода ревью; переформулировок по сути не предложил.
- **?** Зелёный `make test`/`make phpstan`/100% покрытие в этом круге прямо не подтверждены (read-only
  окружение у codex; субагенты по инструкции сьют не запускали) — ревью корректно ссылается на них как
  на отчёты прошлых кругов.

Итог: оценка `94/100` без изменений (код чист, добавлен лишь один минорный optional, не влияющий на
оценку); `status: final`.

## Применённые фиксы
Отчёт: `docs/review-fixes/2026-07-01_18-40_uncommitted-diff.md`

Отклонённые optional-решения:
- **Тройное дублирование `existsReadyForMediaId` в репозиториях конверсий** — отклонено. Чистой и
  консистентной консолидации нет: буквальный вынос в `AbstractRepository` (кросс-модульный `Shared`)
  нарушил бы направление зависимостей (Media-типы `MediaId`/`MediaConversionStatus` в `Shared`,
  Media-метод на 20 репозиториях чужих модулей); устранять только `existsReadyForMediaId`, оставив
  параллельный дубль `findByMediaId`, — неконсистентно; а консолидация обоих потребовала бы либо
  ослабить конкретный тип возврата `findByMediaId` до `TypedCollection` (нарушает rules.md), либо
  завести новый базовый класс с фабрикой-шаблонным методом без прецедента и без реального устранения
  параллельных публичных методов. Пункт `quality`, низкий риск, на оценку не влияет. Следующим
  ревьюерам: не поднимать повторно без нового архитектурно чистого способа консолидации ОБОИХ
  дублей (`existsReadyForMediaId` + `findByMediaId`) сразу.
