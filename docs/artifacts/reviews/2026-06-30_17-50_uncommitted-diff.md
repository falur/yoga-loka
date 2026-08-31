---
title: Незакоммиченный diff — второй круг (Media URL-сервис, RemoveMediaOriginal, LocaleResolver, конфиг-независимость)
date: 2026-06-30 17:50
target: git diff HEAD
plan: none
mode: strict
score: 84
status: final
meta_reviewers: [architecture-check, rules-check, quality-check, sonnet, codex]
---

# Ревью: Незакоммиченный diff — второй круг

## Оценка

**84/100.** Это второй круг по тому же changeset: обязательное замечание прошлого ревью (аватар грузил
все конверсии ради одной ссылки) закрыто — появился лёгкий путь `FindMediaOriginalUrl` /
`MediaUrlService::getOriginalUrl`, который не трогает связи-конверсии, а профиль и лента переключены на
него. База архитектуры аккуратная: технические сервисы за `*Contract` в `Infrastructure`, доменный
`LocaleResolver`, устранена зависимость Application → `*Config`, верхняя граница presigned-TTL теперь
проверяется на старте.

Но мета-ревью (специализированные субагенты + кросс-CLI Codex) сняло прежний вывод «явных нарушений
нет» и нашло два пункта «править обязательно». Первое: changeset в том виде, в каком его описывает
целевой `git diff HEAD`, **неполный** — новый сценарий `FindMediaOriginalUrl` (handler + query) и его
тест лежат как untracked-файлы и в diff не входят, хотя уже отслеживаемые `UserPublicProfileAssembler`
и `UserBootloader` на них опираются; такой набор изменений сам по себе не соберётся. Второе:
задокументированное нарушение правил тестов — три дублёра в `FindMediaUrlHandlerTest` вызывают
`method()` без `expects()` (деприкейтнутый в PHPUnit 13 паттерн). Дополнительно: архитектурная
несогласованность — `UserPublicProfileAssembler` обходит `QueryBus` и зовёт обработчик напрямую по
неверному обоснованию, из-за чего `#[LogOperation]` обработчика мёртв; а также рассинхрон
«документация против кода» не только в комментариях кода, но и в `app/config/media.php` и в самом
`docs/arch.md`, который этот же changeset правит. Прежние два пункта (устаревшие комментарии и холостой
eager-load конверсий) подтверждены, один уточнён.

## Проблемы сверки с планом

Проверка плана пропущена: план не указан и не найден.

## Замечания

### 1. Комментарии описывают прежний способ получения ссылок на медиа, которого в коде уже нет

После рефактора лента берёт ссылку на вложение прямо из уже загруженной сущности медиа и больше не
обращается к отдельному сценарию с запросом в базу. Но текстовые пояснения рядом с этим кодом всё ещё
рассказывают про старый механизм: будто URL каждого вложения разрешается поштучно через отдельный
запрос и поэтому число обращений растёт линейно с числом вложений. Сейчас это не так — ссылка строится
в памяти из заранее загруженной записи, отдельного запроса на вложение нет (для приватного медиа
остаётся только подпись ссылки, не запрос в базу).

Риск тут не в поведении, а в сопровождении: следующий человек поверит комментарию и будет
оптимизировать «N+1 на разрешении URL», которого уже нет, или наоборот побоится трогать код,
считая его узким местом. Расхождение «комментарий против кода» само по себе дефект — комментарий
должен описывать то, что есть, а не то, что было до правки.

Технические детали:

- **Тип:** `docs`
- **Рекомендация:** `на усмотрение автора`
- **Где:** `app/src/Modules/Posts/Application/View/PostViewAssembler.php:37-41` (классовый docblock: «mediaItem -> FindMediaUrl», «пакетного контракта разрешения URL в Media сейчас нет», «число вызовов растёт линейно с числом вложений»). Сопутствующе: `app/src/Modules/Posts/Domain/Entity/PostMedia.php:50` называет потребителем eager-load связи `MediaUrlService::getUrls`, тогда как лента вызывает `getOriginalUrl`. Та же категория расхождения — в файловом docblock `app/config/media.php:11`: «вызывающий может переопределить его в `FindMediaUrlQuery`», хотя срок переопределяет и добавленный в этом changeset `FindMediaOriginalUrlQuery` (тоже принимает `presignedTtlSeconds`).
- **Что подтверждает проблему:** `mediaItem()` (`:277`) теперь зовёт `$this->mediaUrlService->getOriginalUrl(media: $postMedia->media)` по уже eager-загруженной сущности; ни `FindMediaUrl`, ни per-element-запроса в базу в этом пути нет (`grep FindMediaUrl app/src` даёт только сам сценарий и комментарии). То есть классовый docblock описывает удалённый механизм.
- **Как исправить:** обновить классовый docblock `PostViewAssembler` под фактический поток: вложения и их медиа грузятся пакетно в `PostMediaRepository` (eager `media.*`), URL оригинала строится из загруженной сущности через `MediaUrlService::getOriginalUrl` без запроса на вложение; для приватного медиа остаётся подпись presigned-ссылки на оригинал. В docblock `PostMedia` уточнить, что для ленты используется `getOriginalUrl` (оригинал), а `getUrls` — общий путь полного набора. В `app/config/media.php:11` упомянуть оба Query (`FindMediaUrlQuery` и `FindMediaOriginalUrlQuery`) либо обобщить формулировку.
- **Тесты:** не требуются — правка только текста комментариев.
- **Поправка после мета-ревью:** из списка убрана ссылка на `MediaRepository.php:26` — она была ошибочной: docblock `findByIdWithConversions` («полный набор ссылок, `MediaUrlService::getUrls`») верен, этот метод обслуживает `FindMediaUrlHandler`, который и правда вызывает `getUrls`, а не ленту.

### 2. Лента по-прежнему eager-грузит конверсии, которые её текущий код не использует

При сборке ленты репозиторий вложений дополнительно подтягивает из базы все варианты-превью каждого
медиа (картиночные, видео, аудио). Но текущий код ленты строит ссылку только на оригинал и к этим
превью не обращается вообще — они загружаются и тут же отбрасываются. На каждую выборку ленты/записи
это три дополнительных запроса за данными, которые сейчас никто не читает.

Это сознательное состояние, а не случайность: `docs/arch.md` прямо предписывает грузить медиа вместе с
конверсиями при чтении ленты, а комментарий в `mediaItem()` отмечает, что показ конверсий
(постер видео, превью) в ленте — «отдельная задача». То есть eager-load оставлен «на вырост». Отмечаю
только чтобы зафиксировать текущую цену: пока лента не начнёт рендерить конверсии, эти три relation-load
на страницу — холостые.

Технические детали:

- **Тип:** `performance`
- **Рекомендация:** `на усмотрение автора`
- **Где:** `app/src/Modules/Posts/Repository/PostMediaRepository.php:23-25` и `:47-49` (`->load('media.imageConversions')->load('media.videoConversions')->load('media.audioConversions')` в `findByPostId` и в пакетном методе); потребитель — `app/src/Modules/Posts/Application/View/PostViewAssembler.php:277` (`getOriginalUrl`, конверсии не читает).
- **Что подтверждает проблему:** `getUrls` (единственный метод, читающий `media->imageConversions/...`) в `app/src` вызывается только из `FindMediaUrlHandler`, а тот в ленте не используется; `getOriginalUrl` к связям-конверсиям не обращается вовсе. Значит eager-загруженные конверсии в пути ленты гарантированно не читаются. Прежняя мотивировка eager-load («чтобы `getUrls` строил полный набор без N+1») к ленте сейчас не применима, потому что лента ушла на `getOriginalUrl`.
- **Как исправить:** **только вариант (а) — оставить eager-load как есть и явно зафиксировать в комментарии `PostMediaRepository`/`mediaItem()`, что он держится под планируемый показ превью в ленте.** Это согласовано с `docs/arch.md:165-169`, который прямо предписывает грузить конверсии вместе с лентой; правок кода нет. Убирать `->load('media.*Conversions')` нельзя как обычную правку — это противоречило бы действующему архитектурному решению, поэтому такой шаг возможен только как осознанное изменение `arch.md` (и тогда — отдельной задачей, синхронно с правкой документа и с учётом предупреждения docblock `PostMedia` про ленивую подгрузку). См. также п.6: сам `arch.md:168` сейчас называет потребителем `getUrls`, что тоже надо привести к `getOriginalUrl`.
- **Тесты:** при варианте (а) тесты не нужны.

### 3. Сценарий `FindMediaOriginalUrl` и его тест — untracked, вне целевого `git diff HEAD`: changeset неполный

Целевой diff ревью — `git diff HEAD`. Туда не попадают неотслеживаемые (untracked) файлы. А новый
Query-сценарий `FindMediaOriginalUrl` (`FindMediaOriginalUrlHandler` + `FindMediaOriginalUrlQuery`) и его
feature-тест существуют в рабочем дереве как untracked. При этом уже отслеживаемые и изменённые
`UserPublicProfileAssembler` (импорт и инъекция `FindMediaOriginalUrlHandler`) и `UserBootloader`
(биндинг и фабрика с этим обработчиком) на этот сценарий прямо ссылаются. Значит набор изменений,
который описывает целевой diff, сам по себе не соберётся: tracked-код зависит от классов, чьи файлы в
diff отсутствуют. Если автор закоммитит только текущее проиндексированное/отслеживаемое состояние,
сборка и тесты сломаются. Прежняя Оценка ревью опиралась на `FindMediaOriginalUrl` как на часть
проверенного changeset-а, хотя строго по `git diff HEAD` его там нет.

Технические детали:

- **Тип:** `process`
- **Рекомендация:** `править обязательно`
- **Где:** untracked: `app/src/Modules/Media/Application/Query/FindMediaOriginalUrl/FindMediaOriginalUrlHandler.php`, `.../FindMediaOriginalUrlQuery.php`, `tests/Feature/Modules/Media/Application/FindMediaOriginalUrlHandlerTest.php`. Зависят от них (в diff): `app/src/Modules/User/Application/Profile/UserPublicProfileAssembler.php:7-8,29,51-52`, `app/src/Modules/User/Infrastructure/Bootloader/UserBootloader.php:7,28`.
- **Что подтверждает проблему:** `git status --porcelain` показывает `?? app/src/Modules/Media/Application/Query/FindMediaOriginalUrl/` и `?? tests/Feature/Modules/Media/Application/FindMediaOriginalUrlHandlerTest.php`; `git ls-files` их не знает. `grep FindMediaOriginalUrl app/src` находит использование в отслеживаемых файлах модуля User.
- **Как исправить:** проиндексировать untracked-файлы (`git add app/src/Modules/Media/Application/Query/FindMediaOriginalUrl/ tests/Feature/Modules/Media/Application/FindMediaOriginalUrlHandlerTest.php`), чтобы они вошли в один changeset с зависящим от них кодом; затем убедиться, что полный набор собирается и `make test` / `make phpstan` зелёные на цельном дереве. Закоммитить новый сценарий и его потребителей одним коммитом.
- **Тесты:** тест `FindMediaOriginalUrlHandlerTest` уже написан (но untracked) — его нужно закоммитить вместе со сценарием.

### 4. PHPUnit 13: дублёры `createMock()` вызывают `method()` без `expects()` — деприкейтнутый паттерн

В `FindMediaUrlHandlerTest` тройка тестов создаёт дублёр `MediaFileServiceContract` через
`createMock()` и на нём же настраивает заглушку метода через `->method(...)->willReturn*(...)` без
`expects()`. По правилам проекта (раздел «Тестирование») это запрещено: в PHPUnit 13 `with()`/`method()`
без `expects()` деприкейтнуты и удаляются в PHPUnit 14. Дублёр здесь именно `createMock()` (а не
`createStub()`), потому что на соседнем методе стоит `expects(self::never())`, поэтому верный путь —
добавить ожидание вызова и заглушаемому методу.

Технические детали:

- **Тип:** `tests`
- **Рекомендация:** `править обязательно`
- **Где:** `tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:71` (`->method('publicUrl')->willReturnCallback(...)`), `:113` (`->method('presignGet')->willReturnCallback(...)`), `:198` (`->method('publicUrl')->willReturn(...)`). Во всех трёх дублёр получен `createMock(...)` (строки `:70`, `:111`, `:197`), а на сиблинг-методе стоит `expects(self::never())`.
- **Что подтверждает проблему:** правило rules.md: «В PHPUnit 13 `with()`/`method()` без `expects()` деприкейтнут, а `expects($this->any())` тоже деприкейтнут (удаляется в PHPUnit 14)».
- **Как исправить:** перед заглушаемым `->method(...)` добавить `->expects(self::atLeastOnce())` (для `:71` и `:113`, где метод заведомо вызывается) или `->expects(self::once())` (для `:198`). Менять `createMock` на `createStub` нельзя, пока на том же дублёре есть `expects(self::never())` на втором методе.
- **Тесты:** правка касается самих тестов; после неё прогнать `make test`, чтобы деприкейт-варнинги ушли.
- **Замечание по охвату:** проверена тройка в `FindMediaUrlHandlerTest`; при правке стоит просмотреть аналогичные дублёры в соседних новых тестах модуля Media на тот же паттерн.

### 5. `UserPublicProfileAssembler` обходит `QueryBus` и зовёт обработчик напрямую — `#[LogOperation]` мёртв, обоснование неверно

`UserPublicProfileAssembler` инъектит `FindMediaOriginalUrlHandler` и вызывает `->handle(...)`
напрямую, а не через `QueryBusInterface::dispatch(query, $handler->handle(...))`, как это делает
`PostViewAssembler` для всех межмодульных Query (`GetUserPublicProfiles`, `GetTags`). Прямой вызов
обходит шину, а вместе с ней — bus-middleware. У `FindMediaOriginalUrlHandler::handle()` стоит
`#[LogOperation]`; при обходе шины этот атрибут не срабатывает никогда, и debug-лог операции молча
теряется (единственный продакшен-вызов обработчика — как раз этот, через ассемблер). Обоснование в
docblock ассемблера («обёртка шины теряет null из вывода типов») неверно: `QueryBusInterface::dispatch()`
объявлен как `@template TResult ... @return TResult`, тип берётся из `callable(TQuery): TResult`, то есть
для `handle(): MediaUrlResult|null` шина вернёт ровно `MediaUrlResult|null` — null не теряется, и
подавление статанализа не нужно.

Технические детали:

- **Тип:** `architecture`
- **Рекомендация:** `на усмотрение автора`
- **Где:** `app/src/Modules/User/Application/Profile/UserPublicProfileAssembler.php:22-24` (docblock-обоснование), `:29` (инъекция обработчика), `:51-53` (прямой `->handle(...)`). Атрибут: `app/src/Modules/Media/Application/Query/FindMediaOriginalUrl/FindMediaOriginalUrlHandler.php:30` (`#[LogOperation]`). Контракт шины: `packages/spiral-cqrs/src/QueryBusInterface.php` (`@return TResult`). Эталон бус-паттерна: `PostViewAssembler.php:143-147,162-166,308-311`.
- **Что подтверждает проблему:** `QueryBusInterface::dispatch()` — generic с `@return TResult`; `PostViewAssembler` успешно гоняет через шину обработчики с разными (в т.ч. nullable) типами. Значит причина обхода, заявленная в docblock, не подтверждается.
- **Как исправить:** маршрутизировать вызов через шину — `$this->queryBus->dispatch(query: new FindMediaOriginalUrlQuery(...), handler: $this->findMediaOriginalUrlHandler->handle(...))`; это вернёт `#[LogOperation]` к работе и приведёт ассемблер к единому паттерну, а ошибочный абзац про «потерю null» из docblock убрать. Если прямой вызов выбран осознанно (например, чтобы не тащить `QueryBusInterface` в ассемблер) — тогда снять с `FindMediaOriginalUrlHandler::handle()` мёртвый `#[LogOperation]` и поправить вводящее в заблуждение обоснование в docblock.
- **Тесты:** существующий feature-тест обработчика покрывает его поведение; при переводе на шину дополнительных тестов не требуется.

### 6. `docs/arch.md` (правится этим же changeset) рассинхронен с кодом

Changeset правит `docs/arch.md`, и правка внесла/оставила два расхождения с фактическим кодом — та же
категория «документация против кода», что и в п.1, но уже на уровне архитектурного документа, который
задаёт норму. Во-первых, в разделе про foundational-модуль Media сказано, что лента грузит конверсии,
«после чего `MediaUrlService::getUrls(Media $media)` строит URL без обращений в БД», тогда как лента
фактически вызывает `getOriginalUrl()`. Во-вторых, нормативный список «Media должен давать только свои
сценарии» в этом же diff пополнился строкой `FindMediaUrl`, но два других новых публичных сценария
changeset-а — Command `RemoveMediaOriginal` и Query `FindMediaOriginalUrl` — в список не добавлены.

Технические детали:

- **Тип:** `docs`
- **Рекомендация:** `на усмотрение автора`
- **Где:** `docs/arch.md:168` («`MediaUrlService::getUrls(Media $media)` строит URL без обращений в БД» — для пути ленты должно быть `getOriginalUrl`); `docs/arch.md:179-184` (список сценариев Media — без `RemoveMediaOriginal` и `FindMediaOriginalUrl`).
- **Что подтверждает проблему:** `git diff HEAD -- docs/arch.md` показывает добавленную строку с `getUrls` и добавленную в список строку `+FindMediaUrl`; `mediaItem()` (`PostViewAssembler.php:277`) использует `getOriginalUrl`; в `app/src/Modules/Media/Application/...` присутствуют `RemoveMediaOriginal` и `FindMediaOriginalUrl`.
- **Как исправить:** в `docs/arch.md:168` заменить упоминание потребителя ленты на `MediaUrlService::getOriginalUrl` (а `getUrls` оставить как путь полного набора, например для `FindMediaUrl`); в список сценариев Media добавить `RemoveMediaOriginal` и `FindMediaOriginalUrl`.
- **Тесты:** не требуются — правка документации.

### 7. `MediaUploadPlannerContract` не фиксирует инвариант согласованности `partSize()` и `partsCount()`

Контракт `MediaUploadPlannerContract` отдаёт `partSize()` и `partsCount(MediaFileSize)` как два
независимых метода, а `RequestMediaUploadHandler` вызывает их по отдельности (число частей — отдельно,
размер части — отдельно при сборке `MediaMultipartUpload`). Между ними есть неявный инвариант: число
частей должно считаться делением размера файла на то же значение, которое возвращает `partSize()`.
Текущая реализация `MediaUploadPlanner` его держит (и это описано в её docblock), но сам интерфейс-
контракт инвариант не фиксирует. Альтернативная реализация, где `partsCount()` делит на другое значение,
молча соберёт `MediaMultipartUpload` с несогласованной парой (`partsCount`, `partSize`). Риск латентный,
проявится только при расширении.

Технические детали:

- **Тип:** `quality`
- **Рекомендация:** `на усмотрение автора`
- **Где:** `app/src/Modules/Media/Application/Contract/MediaUploadPlannerContract.php` (методы `partSize()` и `partsCount()` без описанного инварианта); потребитель — `app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php:89,108` (независимые вызовы).
- **Что подтверждает проблему:** в контракте нет ни докблока про связь методов, ни общего возврата; согласованность держится только реализацией.
- **Как исправить:** один из путей. (а) Добавить в docblock `MediaUploadPlannerContract` явный инвариант: реализация обязана вычислять `partsCount` делением на то же значение, которое возвращает `partSize()`. (б) Объединить в один метод, возвращающий result-DTO `{partsCount, partSize}`, чтобы рассогласование стало невозможным конструктивно.
- **Тесты:** при варианте (б) — поправить тесты планировщика под новый возврат; при (а) тесты не нужны.

## Рекомендации

- **Править обязательно:** 3, 4
- **На усмотрение автора:** 1, 2, 5, 6, 7

## Изменения после мета-ревью

Режим: `strict`. Запущены `architecture-check`, `rules-check`, `quality-check` (модель `sonnet`) одним
batch, затем кросс-CLI `codex`. `plan-check` не запускался: план не указан в ревью (`plan: none`).

### После architecture-check / rules-check / quality-check (sonnet)

- **+ Добавлено:** п.3 (untracked `FindMediaOriginalUrl` вне `git diff HEAD` — независимо отмечен и rules-check как «?» по покрытию); п.4 (rules-check: `method()` без `expects()` в `FindMediaUrlHandlerTest`, три места — деприкейт PHPUnit 13); п.5 (architecture-check: `UserPublicProfileAssembler` обходит `QueryBus`, мёртвый `#[LogOperation]`, неверное обоснование про потерю null); п.6 (architecture-check: неполный список сценариев Media в `arch.md`); п.7 (quality-check: неописанный инвариант `partSize()`/`partsCount()` в `MediaUploadPlannerContract`).
- **~ Изменено:** п.1 — убрана ошибочная ссылка на `MediaRepository.php:26` (docblock `findByIdWithConversions`→`getUrls` верен), добавлен `app/config/media.php:11` (упомянут только `FindMediaUrlQuery`); п.2 — вариант «убрать eager-load» переформулирован как недопустимая обычная правка (противоречит `arch.md`), оставлен только вариант «оставить и задокументировать»; Оценка переписана, снята фраза «явных нарушений не нашёл»; `score` 93 → 84; `status` draft → meta-reviewed.
- **− Убрано:** из п.1 — ссылка на `MediaRepository.php:26` как на стейл-место (подтверждено независимой проверкой: комментарий метода точен).
- **Отклонено:** «?» quality-check о пробеле покрытия `RemoveMediaOriginal`/`FindMediaOriginalUrl` — отклонено как пробел: тесты существуют (`RemoveMediaOriginalHandlerTest` в diff; `FindMediaOriginalUrlHandlerTest` — untracked, его статус вынесен в п.3). Стилистические «можно вынести в подпункт» от architecture-check/quality-check — не применял, формулировки достаточно ясны.

### После соседнего CLI (codex)

- **+ Добавлено:** п.3 (codex: untracked `FindMediaOriginalUrl` не входит в `git diff HEAD`, целевой diff без него не соберётся); п.6 (codex: устаревший `arch.md:165-169` про `getUrls` вместо `getOriginalUrl`).
- **~ Изменено:** п.2 (codex: вариант «убрать `->load(...)`» нельзя предлагать без правки `arch.md`, который eager-load предписывает); Оценка (codex: фраза «явных багов/нарушений не нашёл» неверна).
- **− Убрано:** предложение codex полностью удалить упоминание `FindMediaOriginalUrl` из Оценки применено как переформулировка, а не удаление — путь реально есть в рабочем дереве, а его untracked-статус зафиксирован отдельным пунктом 3.
- **Отклонено:** codex «+» про `@return list<array{int, bool}>` в `MediaUploadPlannerTest.php:41` как нарушение «без сложных массивов в PHPDoc» — отклонено: `array{...}`-shape в data-провайдерах PHPUnit является устоявшейся практикой проекта (21 вхождение в уже закоммиченных тестах: `AuthValueObjectTest`, `ProcessMediaJobTest`, `S3MediaFileServiceErrorTest` и др.), `make phpstan` на ней зелёный; флаг противоречил бы существующему коду и был бы шумом.

`status` meta-reviewed → final.

## Применённые фиксы
Отчёт: `docs/review-fixes/2026-06-30_18-29_uncommitted-diff.md`

Обязательные (3, 4) исправлены. Все optional (1, 2, 5, 6, 7) применены (режим `apply-optional`):
п.2 и п.7 — по варианту «а» (комментарий / docblock-инвариант), без правки кода поведения; п.5 — вызов
переведён на `QueryBus`, `#[LogOperation]` снова работает, неверный абзац про «потерю null» убран.

### Отклонённые optional-решения
- **п.7, вариант «б»** (объединить `partSize()`/`partsCount()` в один result-DTO `{partsCount, partSize}`):
  отклонён в пользу варианта «а». Причина: вариант «а» (docblock-инвариант) закрывает латентный риск
  рассогласования дёшево; вариант «б» расширяет scope (новый DTO + правка `RequestMediaUploadHandler` +
  тесты планировщика) и несёт регрессионный риск, не оправданный на текущем числе реализаций контракта.
  Не переоткрывать без новой реализации `MediaUploadPlannerContract`, которая реально считает `partsCount`
  иначе.
