---
title: Рефакторинг Media URL-сервисов, RemoveMediaOriginal и config-independence (3-й круг)
date: 2026-07-01 17:38
target: git diff HEAD (staged + unstaged + новые файлы)
plan: none
mode: strict
score: 92
status: final
meta_reviewers: [architecture-check (sonnet), rules-check (sonnet), quality-check (sonnet), codex (gpt-5.5)]
---

# Ревью: Рефакторинг Media URL-сервисов, RemoveMediaOriginal и config-independence (3-й круг)

## Оценка

**92/100.** Третий круг доводки того же крупного дифа. Обязательные замечания предыдущих кругов
устранены и на месте: исчерпывающий `match` в `MediaUrlService::resolverFor`, докблоки про инвариант
FK RESTRICT у `PostMedia::$media` и про отсутствие runtime-потребителя у `FindMediaUrl`. Код,
внесённый дифом (URL-резолверы за интерфейсом, `MediaUploadPlanner`/`MediaUrlService` за контрактами,
`MediaConversionsChecker`, enum `MediaConversionKind`, доменные переходы `markReadyOriginalRemoved`/
`isFinalized`, `LocaleResolver`, фабрики бутлоадеров), правилам и архитектуре не противоречит:
config-independence Application выдержана, foundational-исключение `Media` задокументировано в
`arch.md` и соблюдено, guard-clause-переходы идемпотентны, коллекционные пайплайны идиоматичны,
`declare(strict_types=1)`, именованные аргументы и trailing commas на месте, документация
(`arch.md`/`rules.md`/README/локали/`.env.sample`) синхронизирована с кодом.

Обязательных нарушений в этом круге не найдено (подтверждено мета-ревью: architecture-check,
rules-check, quality-check, кросс-CLI codex). Все три свежих замечания — `на усмотрение автора` и
касаются только тест-кода: асимметрия в утверждениях happy-path удаления оригинала (аудио-ветка),
дублирование билдеров конверсий между тремя тест-классами при наличии общего базового кейса и
неполная фиксация контракта TTL у нового суита `FindMediaOriginalUrl` относительно соседнего
`FindMediaUrl`. Ни одно не создаёт бага и не является пробелом покрытия строк. Ранее отклонённые с обоснованием
пункты (№2 дублирование предела presigned-TTL, №5 eager-load конверсий в ленте, №4 порядок «удалить
объект → зафиксировать статус» в `RemoveMediaOriginal`, N+1 разрешения аватаров в листинге профилей)
повторно не поднимаю: новых содержательных аргументов против прежних решений нет. По №4 фиксирую
только неизменный статус: команда по-прежнему не подключена ни к одному входу, поэтому предусловие
«перед подключением к триггеру перевести `deleteObject` в after-commit outbox-шаг» остаётся открытым,
а не новым замечанием.

## Проблемы сверки с планом

Проверка плана не выполнялась: план не указан (`plan: none`), поэтому `plan-check` не запускался и
выполнение плана не сверялось.

## Замечания

### 1. Happy-path удаления оригинала для аудио не проверяет возвращённый результат — асимметрия с image/video

Сценарий удаления оригинала возвращает вызывающему объект-результат со статусом медиа и его
идентификатором. Три позитивных теста (image, video, audio) по смыслу симметричны — все три проверяют
успешное удаление, — но проверяют разное. Для image и video тест захватывает возвращённый результат и
утверждает его поля (статус стал «оригинал удалён», идентификатор совпадает), а для audio тест
вызывает сценарий без захвата результата и проверяет только состояние самой сущности и число
конверсий.

Это ровно тот же класс проблемы, что уже исправлялся в первом круге (там результат не проверял
видео-тест, его дочинили). Сейчас пробел «переехал» на аудио-тест. Бага это не создаёт, но ослабляет
фиксацию контракта возврата именно для аудио-ветки и снова делает набор тестов несогласованным: при
регрессе в сборке результата для аудио тест промолчит, тогда как для image/video поймает.

Технические детали:

- **Тип:** `tests`
- **Рекомендация:** `на усмотрение автора`
- **Где:** `tests/Feature/Modules/Media/Application/RemoveMediaOriginalHandlerTest.php` —
  `testRemovesOriginalOfAudioMediaKeepingConversion` вызывает `$this->handler($fileService)->handle(...)`
  без присвоения `$result`; ср. `testRemovesOriginalOfImageMediaKeepingConversion` и
  `testRemovesOriginalOfVideoMediaKeepingConversionAndPoster`, где есть
  `assertSame(MediaStatus::ReadyOriginalRemoved, $result->status)` и
  `assertSame($media->id->value(), $result->mediaId)`.
- **Что подтверждает проблему:** аудио-тест не содержит ни одного утверждения по возвращённому
  `MediaResult`, тогда как соседние два позитивных теста их содержат; это прямое расхождение внутри
  одной тройки симметричных сценариев.
- **Как исправить:** захватить результат в аудио-тесте и добавить те же два утверждения по
  `status`/`mediaId`, что и в image/video.
- **Тесты:** это и есть правка теста.

### 2. Билдеры конверсий продублированы между тремя тест-классами при наличии общего базового кейса

Тест-классы модуля Media наследуются от одного базового кейса `MediaApplicationTestCase`. В этот
базовый кейс в дифе вынесли общие помощники (`readyMedia`, `thumbnailConversion`, сброс identity map),
но построение остальных конверсий разошлось по классам: тест полного набора ссылок объявляет
собственные билдеры image/video/audio-конверсий, тест удаления оригинала строит свои «готовое медиа с
конверсией» отдельными приватными методами со структурно тем же телом `create(...)`, а тест волны
аудио держит ещё один локальный `audioConversion()`. В итоге создание одинаковых по сути тестовых
конверсий описано в нескольких местах.

Дублирование не байт-в-байт по всем видам, но реальное: точный дубль — image-thumbnail
(`readyImageMediaWithConversion()` в тесте удаления повторяет тело базового `thumbnailConversion()`);
video/audio-билдеры в тест-классах структурно близки, но параметризованы по-разному (вариант в
`RemoveMediaOriginalHandlerTest` не принимает `status`), а `GetAudioWaveformHandlerTest::audioConversion()`
почти совпадает с `FindMediaUrlHandlerTest::audioConversion()` (отличается лишь фиксированным
`storage: MediaStorage::Public`). Дифф не только оставил это расхождение, но и добавил в
`GetAudioWaveformHandlerTest` нового потребителя локального билдера
(`testReturnsWaveformForReadyOriginalRemovedAudioMedia`).

На поведение и покрытие это не влияет и багом не является, но это дублирование тест-кода: при
изменении сигнатуры доменного `create(...)` конверсии (новое обязательное поле, переименование VO)
править придётся в трёх файлах, и легко забыть один. Часть уже вынесена в общий кейс, поэтому разумно
довести до конца и держать билдеры конверсий в одном месте.

Технические детали:

- **Тип:** `quality`
- **Рекомендация:** `на усмотрение автора`
- **Где:** `tests/Feature/Modules/Media/Application/MediaApplicationTestCase.php` — уже содержит
  `thumbnailConversion()`; `tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php` —
  приватные `imageConversion()`/`videoConversion()`/`audioConversion()`;
  `tests/Feature/Modules/Media/Application/RemoveMediaOriginalHandlerTest.php` —
  `readyImageMediaWithConversion()`/`readyVideoMediaWithConversion()`/`readyAudioMediaWithConversion()`
  с инлайновыми `MediaImageConversion::create(...)`/`MediaVideoConversion::create(...)`/
  `MediaAudioConversion::create(...)`; `tests/Feature/Modules/Media/Application/GetAudioWaveformHandlerTest.php` —
  собственный приватный `audioConversion()`.
- **Что подтверждает проблему:** тело image-thumbnail-конверсии в базовом кейсе и в
  `readyImageMediaWithConversion()` совпадает; `audioConversion()` определён и в `FindMediaUrlHandlerTest`,
  и в `GetAudioWaveformHandlerTest` с почти идентичным телом, а также заново реализован инлайн в
  `RemoveMediaOriginalHandlerTest` — один и тот же вид конверсии собирается в трёх местах.
- **Как исправить:** поднять билдеры конверсий (image по типу/статусу, video, audio) в
  `MediaApplicationTestCase` рядом с уже вынесенным `thumbnailConversion()` и переиспользовать их из всех
  трёх тест-классов; в тесте удаления собирать «готовое медиа + конверсия» поверх этих общих билдеров.
  Если `GetAudioWaveform` нужен фиксированный `storage: Public` — параметризовать общий билдер, а не
  держать отдельную копию.
- **Тесты:** правка только тест-кода; после консолидации весь набор Media-тестов должен остаться
  зелёным без изменения смысла ассертов.

### 3. Новый суит `FindMediaOriginalUrl` не фиксирует отклонение явного/невалидного TTL — асимметрия с `FindMediaUrl`

Докблок `FindMediaOriginalUrlHandler` и README заявляют, что срок presigned-ссылки работает так же, как
у `FindMediaUrl`. Оба сценария резолвят TTL через один и тот же приватный путь
`MediaUrlService::presignedResolverFor()` → `MediaPresignedTtl::fromInt($ttl ?? default)`, где явный `0`
или значение вне диапазона обязаны бросить исключение (комментарий в коде прямо предупреждает: нельзя
писать `?:`, иначе явный 0 тихо ушёл бы в значение по умолчанию). Соседний суит
`FindMediaUrlHandlerTest` фиксирует эту ветку двумя тестами (`testRejectsExplicitZeroTtlForPrivateMedia`,
`testIgnoresInvalidTtlForPublicMedia`), а новый `FindMediaOriginalUrlHandlerTest` проверяет только
happy-path и значение по умолчанию — негативную TTL-ветку не пинит вовсе.

Пробелом покрытия строк это не является: путь `MediaPresignedTtl::fromInt` общий и уже покрыт через
`FindMediaUrl`, живого бага нет. Но это ровно тот же класс проблемы, что и замечание №1: параллельный
суит недо-фиксирует одну ветку задокументированного контракта. При регрессе, специфичном для
`getOriginalUrl` (например если однажды путь оригинала перестанет валидировать TTL), выделенный суит
промолчит, тогда как `FindMediaUrl` поймает.

Технические детали:

- **Тип:** `tests`
- **Рекомендация:** `на усмотрение автора`
- **Где:** `tests/Feature/Modules/Media/Application/FindMediaOriginalUrlHandlerTest.php` — среди 6
  тестов нет проверки явного `presignedTtlSeconds: 0` (или значения вне диапазона) для private-медиа;
  ср. `tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php` —
  `testRejectsExplicitZeroTtlForPrivateMedia` и `testIgnoresInvalidTtlForPublicMedia`.
- **Что подтверждает проблему:** отклонение явного 0/вне-диапазона живёт в общем
  `MediaUrlService::presignedResolverFor()`, который вызывают и `getUrls`, и `getOriginalUrl`; контракт
  явно задокументирован как единый, но новый суит его для ветки оригинала не проверяет.
- **Как исправить:** добавить в `FindMediaOriginalUrlHandlerTest` тест, что явный `presignedTtlSeconds: 0`
  на private-медиа приводит к тому же исключению `MediaPresignedTtl`, что и в `FindMediaUrl`.
- **Тесты:** это и есть правка теста.

## Рекомендации

- **Править обязательно:** —
- **На усмотрение автора:** 1, 2, 3

## Изменения после мета-ревью

Режим `strict`. Запущены (одним batch, sonnet): `architecture-check`, `rules-check`, `quality-check`.
`plan-check` не запускался: план не указан в ревью (`plan: none`). Кросс-CLI: `codex` (`gpt-5.5`;
запрошенный `gpt-5.3-codex` недоступен для ChatGPT-аккаунта, взята ближайшая доступная code-capable
модель).

### После architecture-check
- **+ Добавлено:** —
- **~ Изменено:** —
- **− Убрано:** —
- **Итог:** архитектурных нарушений в дифе не найдено; оба исходных замечания корректны. Подтверждены
  config-independence Application (ни один Handler/контракт не импортирует `*Config`), foundational-исключение
  `Media` (`cascade:false`/`fkCreate:false`), размещение `LocaleResolver` (Shared/Domain/Locale),
  `MediaConversionsChecker` (Application/Service), `MediaUrlService`/`MediaUploadPlanner` за контрактами в
  Infrastructure, разделение `FindMediaUrl` (с конверсиями) и `FindMediaOriginalUrl` (без). Внутренний
  интерфейс `MediaUrlResolver` в `Infrastructure/FileService` — инфраструктурный шов, не зависимость
  Application, нарушением не является.

### После rules-check
- **~ Изменено:** уточнена формулировка замечания №2 — прямой дубль только для image-thumbnail; video/audio
  в тест-классах структурно близки, но не идентичны (в `RemoveMediaOriginalHandlerTest` нет параметра
  `status`).
- **+ Добавлено:** —
- **− Убрано:** —
- **Отклонено:** предложение вынести отдельным пунктом использование запрещённого имени `$result` в новых
  тест-методах. Причина: `$result` — сквозная предсуществующая конвенция всего тест-сьюта (в неизменённых
  `FindMediaUrlHandlerTest`/`CheckMediaAttachableHandlerTest` тоже), не проверяется PHPStan, и прямо
  отражает возвращаемый `*Result`-DTO; выделять только новые файлы означало бы рассинхрон со стилем всего
  сьюта. Тот же принцип, по которому ранее отклонён «pre-existing» N+1 аватаров.
- **Итог:** обязательных нарушений правил нет; `declare(strict_types=1)`, исчерпывающий `match` без
  `default`, именованные аргументы, trailing commas, guard-clause-порядок, типизированные исключения,
  `->filter()->map()->values()` — соблюдены.

### После quality-check
- **~ Изменено:** замечание №2 расширено — дублирование аудио-билдера конверсии не двух-, а
  трёхстороннее: `GetAudioWaveformHandlerTest::audioConversion()` почти совпадает с
  `FindMediaUrlHandlerTest::audioConversion()`, а дифф добавил в тот класс нового потребителя локального
  билдера. Важность прежняя (`на усмотрение автора`).
- **+ Добавлено:** —
- **− Убрано:** —
- **Итог:** оба исходных замечания фактически точны; в новом продуктовом коде (`MediaUrlService`,
  `MediaUploadPlanner`, резолверы, `MediaConversionsChecker`, `RemoveMediaOriginalHandler`,
  `FindMediaUrlHandler`, `PostViewAssembler`, `Media`) пропущенных существенных quality-проблем нет.

### После соседнего CLI (codex)
- **+ Добавлено:** замечание №3 — новый суит `FindMediaOriginalUrlHandlerTest` не фиксирует отклонение
  явного/невалидного TTL для private-медиа, которое соседний `FindMediaUrlHandlerTest` пинит
  (`testRejectsExplicitZeroTtlForPrivateMedia`); путь общий (`MediaUrlService::presignedResolverFor` →
  `MediaPresignedTtl::fromInt`), поэтому это симметрия задокументированного контракта, а не пробел
  покрытия строк. Тип `tests`, `на усмотрение автора`.
- **~ Изменено:** уточнена формулировка раздела сверки с планом (`plan: none`, проверка не выполнялась).
- **− Убрано:** —
- **Отклонено:** предложение вынести отдельным пунктом изменение `docs/settings.yaml` (`plan_size`,
  `decision_mode`). Причина: это намеренная настройка eda-тулчейна владельцем репозитория (в том же файле
  `strict: true`), а не дефект кода/архитектуры/правил/тестов; гигиена состава коммита — зона `eda-commit`,
  а не код-ревью. Ревью остаётся ревью по проблемам кода.
- **Итог:** codex подтвердил, что оба исходных замечания фактически точны и убирать их не нужно; ложных
  утверждений в ревью не найдено.

### Ранее отклонённые / отложенные пункты (не поднимаю повторно без новых аргументов)

- **№2 (quality, дублирование предела presigned-TTL в `MediaConfig` и `MediaPresignedTtl::MAX`)** —
  отклонён в 1-м круге: единый источник требует сделать `MediaPresignedTtl::MAX` публичной и завести
  зависимость `Shared/Infrastructure/Configuration → Modules/Media/Domain`, что противоречит намеренной
  отвязке общего config-DTO от домена модуля. Синхронизация закреплена комментарием и тестом
  `MediaConfigTest`. Новых аргументов нет.
- **№5 (performance, eager-load конверсий в ленте)** — отклонён: eager-load намеренный и
  задокументирован в `arch.md` как задел под показ превью; убирать можно только синхронно с правкой
  `arch.md`, отдельной задачей. Новых аргументов нет.
- **№4 (architecture, порядок «удалить объект → зафиксировать статус» в `RemoveMediaOriginal`)** —
  отложен: команда не подключена ни к одному входу, живого бага нет; перед подключением к реальному
  триггеру `deleteObject` обязательно перевести в after-commit outbox-шаг. Статус неизменен, новым
  замечанием не является.
- **N+1 разрешения аватаров в листинге профилей (`UserPublicProfileAssembler`)** — отклонён во 2-м
  круге как pre-existing (относительно HEAD число запросов на аватар не меняется), рекомендованный фикс
  (пакетный сценарий «URL оригиналов по набору id») — отдельная задача. Новых аргументов нет.

## Применённые фиксы
Отчёт: `docs/review-fixes/2026-07-01_18-13_uncommitted-diff.md`

Режим `apply-optional`: все три optional-замечания (№1, №2, №3) применены; отклонённых нет.
`make qa` зелёный (стиль, PHPStan `level max`, `OK (1293 tests, 4182 assertions)`, покрытие 100%).
