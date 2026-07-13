---
title: Удаление оригинала медиа с сохранением конверсий
date: 2026-06-24 23:26
mode: strict
plan_size: normal
decision_mode: recommend_and_ask
status: reviewed
reviewer: codex
meta_reviewers: [haiku, sonnet, opus]
test_strategy: after_each_phase
logging_strategy: debug_precise
sources:
  rules: docs/rules.md
  arch: docs/arch.md
  research: —
---

# План реализации

## Задача
Добавить в модуль `Media` сценарий удаления оригинального файла из хранилища при сохранении
конверсий. Готово, когда:

- потребитель может явной командой удалить оригинал у `ready`-медиа;
- статус медиа переходит в уже существующий `MediaStatus::ReadyOriginalRemoved`;
- объект оригинала физически удаляется из целевого бакета;
- конверсии остаются доступны (URL конверсий через `GetMediaUrl`, волна аудио через
  `GetAudioWaveform`), а оригинал — нет;
- покрытие тестами 100% — финальный гейт `make qa` (стиль + PHPStan + один PCOV-coverage-run)
  зелёный; пофазно для быстрого цикла достаточно `make test` + `make phpstan`.

## Контекст
Факты из кода и правил, на которые опирается план.

- Статус `MediaStatus::ReadyOriginalRemoved` (`Domain/Enum/MediaStatus.php`) и доменный метод
  `Media::markReadyOriginalRemoved()` (`Domain/Entity/Media.php`) **уже существуют** и
  зарезервированы (исторический контекст, не `sources.research` этого плана:
  `docs/researches/2026-05-20_18-42_media-domain.md` и `docs/plans/2026-05-21_17-59_...`). Сейчас
  они не используются ни одним сценарием — переиспользуем их, ничего нового в доменную модель
  статусов не вводим.
- В таблице `media` колонка `status` — `string(64)` без CHECK-ограничения и без enum-типа БД
  (`app/database/migrations/20260521.184100_0_create_media_domain_tables.php`). Значение
  `readyOriginalRemoved` уже валидно на уровне хранения, поэтому **миграция не нужна**.
- `Media::isReady()` возвращает `true` только для `MediaStatus::Ready`. На нём завязаны пять мест:
  `ProcessMediaHandler` (идемпотентность обработки), `CheckMediaAttachableHandler`,
  `FindMediaUrlHandler`, `GetMediaUrlHandler`, `GetAudioWaveformHandler`.
- Модуль `Posts` (`Application/View/PostViewAssembler::mediaItem`) резолвит **оригинал** медиа через
  `FindMediaUrl` и при недоступности исключает вложение из ответа. Тест
  `tests/Feature/Modules/Posts/Http/GetPostHttpTest::testUnavailableAttachedMediaIsExcludedWithoutError`
  ставит `markReadyOriginalRemoved()` и ожидает, что вложение исчезает. Это поведение «оригинал
  удалён → вложение пропадает» нужно сохранить без изменений.
- Внешних потребителей `GetMediaUrl`/`GetAudioWaveform` нет (проверено grep), поэтому ослабление их
  гардов безопасно. `FindMediaUrl` (поведение которого НЕ меняем) потребляют двое: Posts
  (`PostViewAssembler`) и User (`UserPublicProfileAssembler` — аватары). Для обоих removed-original
  единообразно даёт `null` → потребитель подставляет значение по умолчанию (вложение исчезает /
  аватар по умолчанию). Это ожидаемое и согласованное поведение, отдельной семантики для аватаров
  не вводим.
- Строки конверсий (`media_*_conversions`) на практике записываются только со статусом
  `MediaConversionStatus::Ready`: `ProcessMediaHandler` создаёт их разом в финальном атомарном
  `persist`+`run()` уже готовыми, промежуточных не-ready строк в БД не появляется. Поэтому гард
  «есть ≥1 конверсия» (любая строка) сейчас эквивалентен «есть ≥1 готовая конверсия» — фильтр по
  статусу конверсии не вводим (преждевременно), но фиксируем это допущение явно.
- `DeleteMediaHandler` удаляет объекты конверсий и текущий оригинал через
  `deleteObject` (404 идемпотентно игнорируется сервисом). Для `ReadyOriginalRemoved`-медиа
  `media.storage`/`media.path` остаются исторической ссылкой на уже удалённый оригинал, поэтому
  `DeleteMedia` продолжит работать без изменений (повторный `deleteObject` 404 → no-op).
- Образцы: `DeleteMediaHandler`, `MakeMediaPermanentHandler` — owner-guarded команды без
  `#[Transactional]`, делают S3-операции, затем один `persist`+`run()`. `MakeMediaPermanent`
  держит статус-guard на Application-границе (домен без guard) — следуем тому же паттерну.
- Команды/обработчики резолвятся контейнером по constructor injection; регистрация в
  `MediaBootloader` не нужна (как у `DeleteMedia`/`MakeMediaPermanent`). У модуля нет HTTP →
  OpenAPI/маршруты не затрагиваются.

## Принятые решения
Все существенные развилки подтверждены пользователем в текущем сообщении (`decision_mode:
recommend_and_ask`).

1. **Триггер — явная команда** `RemoveMediaOriginal(userId, mediaId): MediaResult`. Потребитель сам
   вызывает её после того, как медиа стало `ready`. Формат outbox-сообщения `MediaUploaded` и логику
   конверсий **не трогаем** — это исключает breaking change формата очереди (README предупреждает о
   несовместимости при смене содержимого `MediaUploaded`) и сохраняет возможность переобработки из
   оригинала до его явного удаления. Единственная правка `ProcessMediaHandler` — расширение early-skip
   guard на финализированное состояние (idempotency hardening, см. фазу 1), без изменения контракта
   очереди или пайплайна конверсий. *(Подтверждено пользователем; правка guard — autonomous по
   замечанию кросс-CLI ревью.)*
2. **Гард ≥1 конверсии.** Удалять оригинал можно только если у медиа есть хотя бы одна конверсия;
   иначе 422 `app.media.no_conversions_to_keep`. Гарантирует, что после удаления оригинала всегда
   остаётся доступный контент. Следствие: у `Document` и у медиа с пустым планом конверсий
   оригинал удалить нельзя. *(Подтверждено пользователем.)*
3. **Семантика запросов после удаления оригинала.** Конверсионные запросы (`GetMediaUrl` с
   `conversionType`, `GetAudioWaveform`) обслуживают `Ready` и `ReadyOriginalRemoved`.
   Оригинал-запросы остаются строгими: `GetMediaUrl` без `conversionType` → 404 при
   `ReadyOriginalRemoved`; `FindMediaUrl` → `null` (поведение Posts сохранено).
   `CheckMediaAttachable` осознанно остаётся строгим (`Ready` only) — изменение attach-семантики
   вне scope этой задачи. *(Вытекает из запроса «оставить только конверсии»; зафиксировано
   `decision_mode: autonomous` для сужения — менять Posts/attach не требуется.)*
4. **Без `#[Transactional]`, порядок «S3 → БД».** Сначала `deleteObject` оригинала, затем
   `markReadyOriginalRemoved()` + `persist`+`run()`. На сбое после удаления объекта статус остаётся
   `Ready`, повтор команды довыполнит переход (S3 `deleteObject` идемпотентен). Если же статус уже
   `ReadyOriginalRemoved` — команда сразу no-op (идемпотентность для дублей). **Принятый риск:** в
   узком окне между `deleteObject` и провалом до `persist` статус остаётся `Ready`, поэтому
   `GetMediaUrl(original)`/`FindMediaUrl` могут отдать ссылку на уже удалённый объект (скачивание →
   S3 404); повтор команды чинит статус. Это осознанный компромисс, а не утверждение «рассогласования
   нет». *(autonomous: по образцу `DeleteMediaHandler`, обоснование — корректное восстановление после
   частичного сбоя.)*
5. **Размер — normal**, `test_strategy: after_each_phase`, `logging_strategy: debug_precise`.
   *(Подтверждено пользователем / из `docs/settings.yaml`.)*

Ожидаемый объём (normal): 3 фазы. Новый код — `RemoveMediaOriginalCommand` + `RemoveMediaOriginalHandler`.
Правки: домен `Media` (предикат `isFinalized()` + guard-ы `recordTemporary/PermanentProcessingError`),
`ProcessMediaHandler` (early-skip guard), 2 query-обработчика (`GetMediaUrl`, `GetAudioWaveform`),
локали ru/en (3 ключа), README. `FindMediaUrl`/`CheckMediaAttachable` — без изменений. Основной объём — тесты.

## Целевой алгоритм
Сквозное поведение `RemoveMediaOriginalHandler::handle(RemoveMediaOriginalCommand)`:

```text
вход: userId, mediaId
1. media = MediaRepository.findById(MediaId::fromString(mediaId))
     null -> NotFoundException('app.media.not_found')                       (404)
2. media.uploadedById.equals(UserId::fromString(userId)) == false
     -> ForbiddenException('app.media.access_denied')                       (403)
3. media.status == ReadyOriginalRemoved
     -> debug-лог «пропущено: оригинал уже удалён»; СРАЗУ return MediaResult.fromEntity(media)
        без перехода к шагам 6-7 (deleteObject НЕ вызывается)               (идемпотентность)
4. media.status != Ready
     -> ValidationException('app.media.original_not_removable')             (422)
5. конверсий нет: image, video и audio findByMediaId(media.id) — все пусты
     (ленивая проверка image->isNotEmpty() || video->... || audio->..., ранний выход на первой непустой)
     -> ValidationException('app.media.no_conversions_to_keep')            (422)
6. mediaFileService.deleteObject(storage: media.storage, path: media.path)  (текущий ОРИГИНАЛ в
     целевом бакете — после markReadyMovedTo это target, не staging; S3 404 идемпотентно)
7. media.markReadyOriginalRemoved()
   entityManager.persist(media); entityManager.run()                        (один flush)
8. debug-лог «Оригинал медиа удалён», контекст camelCase: mediaId=media.id.value(),
     userId=command.userId, storage=media.storage->value, path=media.path->value() (без сырых VO)
   return MediaResult.fromEntity(media)
```

Поведение query-обработчиков после внедрения:

```text
GetMediaUrl(mediaId, ttl, conversionType):
  media отсутствует            -> 404 app.media.not_found
  conversionType != null:
     media.isFinalized() == false -> 404 app.media.not_ready
     конверсия не найдена                 -> 404 app.media.conversion_not_found
     иначе                                -> URL конверсии (public direct / private presigned)
  conversionType == null (оригинал):
     media.status == ReadyOriginalRemoved -> 404 app.media.original_removed
     media.isReady() == false             -> 404 app.media.not_ready
     иначе                                -> URL оригинала

GetAudioWaveform(mediaId):
  media.isFinalized() == false -> 404 app.media.not_ready
  media.type != Audio                  -> 404 app.media.conversion_not_found
  аудио-конверсии нет                  -> 404 app.media.conversion_not_found
  иначе                                -> MediaWaveform

FindMediaUrl(mediaId, ttl):           (оригинал, best-effort, без изменений)
  media == null || !media.isReady()    -> null     (ReadyOriginalRemoved -> null)
  иначе                                -> MediaUrlResult оригинала

CheckMediaAttachable(mediaId, ownerUserId):   (без изменений, строго Ready)
  !media.isReady()                     -> 422 app.media.not_ready
```

## Контракты реализации

### Данные и БД
Не затрагивается. Колонка `media.status` — `string(64)` без CHECK-ограничения, значение
`readyOriginalRemoved` уже валидно. Миграции, backfill, rollback не требуются.

### API и внешние контракты
HTTP/OpenAPI не затрагивается (у модуля `Media` нет своего HTTP-слоя). Меняется **публичный
Application-API модуля** (контракт для модулей-потребителей):

- **Новый сценарий** `RemoveMediaOriginal` (Command):
  - DTO: `RemoveMediaOriginalCommand{ string $userId, string $mediaId }`.
  - Handler: `RemoveMediaOriginalHandler::handle(): MediaResult` (`#[LogOperation]`, без
    `#[Transactional]`).
  - Результат: `MediaResult{ string mediaId, MediaStatus status, MediaVisibility visibility }`
    (существующий DTO, `status` отдаёт `readyOriginalRemoved`).
  - Ошибки: 404 `app.media.not_found`; 403 `app.media.access_denied`; 422
    `app.media.original_not_removable`; 422 `app.media.no_conversions_to_keep`.
- **Изменение поведения существующих Query** (без смены сигнатур DTO):
  - `GetMediaUrl`: запрос конверсии (`conversionType != null`) теперь обслуживается и при
    `ReadyOriginalRemoved`; запрос оригинала (`conversionType == null`) при `ReadyOriginalRemoved`
    отдаёт 404 `app.media.original_removed` (новый ключ).
  - `GetAudioWaveform`: обслуживается и при `ReadyOriginalRemoved`.
  - `FindMediaUrl`, `CheckMediaAttachable`: без изменений.
- **Новые ключи локалей** (ровно три; `app.media.not_ready` уже существует и переиспользуется
  ветками `isFinalized() == false`, новым не является) в `app/locale/ru/media.php` и
  `app/locale/en/media.php`:
  - `app.media.original_not_removable` (422) — ru: «Удалить оригинал можно только у готового
    медиа.» / en: "The original can only be removed from ready media."
  - `app.media.no_conversions_to_keep` (422) — ru: «Нельзя удалить оригинал: у медиа нет ни одного
    преобразования.» / en: "Cannot remove the original: the media has no conversions." (ru-текст
    использует «преобразование» — стиль существующих переводов media, ср. `conversion_not_found`;
    «конверсия» в пользовательском тексте не используется, только в технических идентификаторах.)
  - `app.media.original_removed` (404) — ru: «Оригинал медиа удалён.» / en: "The media original
    has been removed."

Внешние сервисы/очереди/webhooks/события не затрагиваются: outbox-сообщение `MediaUploaded`,
`ProcessMediaJob`, регистрация в `MediaBootloader` остаются без изменений.

## Фазы выполнения

### 1. Команда RemoveMediaOriginal, доменный предикат и локали
Цель: появляется рабочий Application-сценарий удаления оригинала с владелец/статус/конверсии
гардами и идемпотентностью; конверсии в БД сохраняются.

Что сделать:
- В `Media` добавить чистый predicate-метод
  `isFinalized(): bool` → `status === MediaStatus::Ready || status ===
  MediaStatus::ReadyOriginalRemoved` (терминальное «готовое» состояние: обработка завершена,
  конверсии обслуживаются). `isReady()` и `markReadyOriginalRemoved()` не менять.
- **Защита финализированного состояния от поздней переобработки** (иначе дубль/повтор старого
  `ProcessMediaCommand` после удаления оригинала прочитал бы удалённый объект и через запись ошибки
  перевёл бы `ReadyOriginalRemoved → ProcessingFailed`):
  - `ProcessMediaHandler::handle`: заменить ранний `if ($media->isReady())` на
    `if ($media->isFinalized())` — уже финализированное медиа (в т.ч. `ReadyOriginalRemoved`) не
    переобрабатывается (no-op, без чтения S3);
  - `Media::recordTemporaryProcessingError()` и `Media::recordPermanentProcessingError()`: расширить
    существующий guard `if ($this->status === MediaStatus::Ready) return;` до
    `if ($this->isFinalized()) return;`, чтобы поздний сбой фоновой обработки не понижал
    `ReadyOriginalRemoved`. Обновить докблоки этих методов (инвариант «финализированное медиа не
    ломается задним числом» теперь покрывает и removed-original).
- Создать `App\Modules\Media\Application\Command\RemoveMediaOriginal\RemoveMediaOriginalCommand`
  (`final readonly`, поля `string $userId`, `string $mediaId`).
- Создать `RemoveMediaOriginalHandler` (`final readonly`, `#[LogOperation]` — по образцу
  `DeleteMediaHandler`, который этот атрибут несёт; `MakeMediaPermanentHandler` его НЕ имеет, на него
  в части логирования не опираемся; без `#[Transactional]`):
  зависимости — `MediaRepository`, `MediaImageConversionRepository`,
  `MediaVideoConversionRepository`, `MediaAudioConversionRepository`,
  `MediaFileServiceContract`, `EntityManagerInterface`, `LoggerInterface`. Реализовать алгоритм из
  раздела «Целевой алгоритм»: ранние возвраты (guard clauses) без вложенности 2+, именованные
  аргументы, конкретные имена переменных (`$media`, не `$result`/`$conversions`). Проверка наличия
  конверсий — ленивая через `||` тремя раздельными `findByMediaId(...)->isNotEmpty()` с ранним
  выходом на первой непустой коллекции (агрегирующего метода в репозиториях нет — это осознанный
  выбор, до трёх запросов на вызов команды). Удаление S3-объекта строго ДО доменного перехода и
  flush; идемпотентный пропуск (`ReadyOriginalRemoved`) — ранний `return` ДО `deleteObject`. Один
  `persist`+`run()`. Возврат `MediaResult::fromEntity`. Без try-catch (ошибки всплывают к
  `ApiExceptionInterceptor`).
- Добавить три ключа локалей в `app/locale/ru/media.php` и `app/locale/en/media.php`.

Результат: команда удаляет оригинал у `ready`-медиа с конверсиями, корректно отбивает 404/403/422,
идемпотентна на `ReadyOriginalRemoved`; строки конверсий в БД не трогаются.

Сценарии тестирования:
- `tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php` (unit, без БД): `isFinalized()`
  → `true` для `Ready` и `ReadyOriginalRemoved`; `false` для ВСЕХ остальных статусов
  (`WaitingUpload`, `CompletingMultipartUpload`, `MultipartCompletionFailedCanRetry`,
  `MultipartCompletionFailedNeedReupload`, `Uploaded`, `Processing`, `ProcessingFailed`). Переход
  `markReadyOriginalRemoved()` уже покрыт существующим кейсом этого файла — заново не добавляем.
- Новый `RemoveMediaOriginalHandlerTest extends MediaApplicationTestCase` (handler конструируется
  напрямую, как `DeleteMediaHandlerTest`; контейнерная регистрация не нужна):
  - успех для image-медиа: захватить аргументы `deleteObject` (по образцу `DeleteMediaHandlerTest`)
    и проверить, что удалён ИМЕННО текущий оригинал (`media.storage`/`media.path` целевого бакета,
    не staging), вызван один раз; статус стал `ReadyOriginalRemoved`; image-конверсия осталась в БД;
    результат `MediaResult` со статусом `ReadyOriginalRemoved`;
  - аналогичный успех для video-медиа (видео-конверсия + постер сохранены) и audio-медиа
    (аудио-конверсия сохранена) — три раздельных кейса доказывают, что гард проверяет все три
    репозитория (у audio нет image-конверсии);
  - 404 на отсутствующем медиа;
  - 403 на чужом владельце;
  - 422 `original_not_removable` на не-ready статусе — представительный `Uploaded` (ветка `!= Ready`
    одна, покрывается одним кейсом);
  - 422 `no_conversions_to_keep` — ДВА раздельных кейса: ready-`Document` без конверсий и ready-`Image`
    с пустым планом конверсий (оба статуса достижимы: `ProcessMediaHandler` для `Document` отдаёт `[]`);
  - идемпотентность: медиа переведено в `ReadyOriginalRemoved` доменным методом и сохранено, затем
    команда → возвращает `MediaResult`, `deleteObject` НЕ вызывается (`createMock` +
    `expects(self::never())`, ранний return до S3).
- Все негативные кейсы `RemoveMediaOriginalHandlerTest` проверяют не только класс исключения, но и
  сообщение-ключ (`expectExceptionMessage('app.media.original_not_removable')` и
  `'app.media.no_conversions_to_keep'`), чтобы зафиксировать конкретный контракт ошибки.
- Регрессия в `DeleteMediaHandlerTest`: удаление медиа в статусе `ReadyOriginalRemoved` —
  конверсии удаляются, `deleteObject` оригинала идемпотентен (404 → no-op), запись удалена.
  Подтверждает заявление Контекста, что `DeleteMedia` работает на removed-original без изменений.
- Защита финализированного состояния (в `MediaEntityTest` и `ProcessMediaHandlerTest`):
  - `recordTemporaryProcessingError()`/`recordPermanentProcessingError()` на медиа в
    `ReadyOriginalRemoved` — no-op (статус, attempts, error не меняются), симметрично существующему
    кейсу для `Ready`;
  - `ProcessMediaHandler` на медиа в `ReadyOriginalRemoved` — ранний no-op: НЕ читает S3, НЕ меняет
    статус, НЕ пишет ошибку (по образцу существующего теста «обработка пропущена: уже ready»).

Проверка:
- `make test` (suite зелёный, включая новые тесты), `make phpstan` (level max без ошибок).

### 2. Семантика конверсионных и оригинал-запросов
Цель: после удаления оригинала конверсии остаются доступны, а оригинал — нет; поведение Posts
(исключение вложения) сохранено.

Что сделать:
- `GetMediaUrlHandler::handle`: убрать единый ранний `if (!$media->isReady())` и развести гарды по
  ветке `conversionType`, СОХРАНЯЯ плоскую структуру (guard-then-return, без вложенности 2+,
  `handle()` ≤ ~40 строк, существующие приватные `findConversion()`/`buildUrl()` переиспользуются):
  - `conversionType !== null`: `if (!$media->isFinalized()) throw
    NotFoundException('app.media.not_ready')`, дальше как сейчас (поиск конверсии → URL);
  - `conversionType === null`: сначала
    `if ($media->status === MediaStatus::ReadyOriginalRemoved) throw
    NotFoundException('app.media.original_removed')`, затем `if (!$media->isReady()) throw
    NotFoundException('app.media.not_ready')`, дальше URL оригинала.
  Логику резолва URL (public/private, поиск конверсии) не менять.
- `GetAudioWaveformHandler::handle`: заменить `if (!$media->isReady())` на
  `if (!$media->isFinalized())` (волна — это конверсия, переживает удаление оригинала);
  порядок остальных проверок (`type !== Audio`, наличие конверсии) сохранить.
- `FindMediaUrlHandler` и `CheckMediaAttachableHandler` — оставить без изменений (строгий
  `isReady()`); зафиксировать это сценариями-регрессиями.

Результат: `GetMediaUrl(conversion)` и `GetAudioWaveform` работают на `ReadyOriginalRemoved`;
`GetMediaUrl(original)` отдаёт 404 `original_removed`; `FindMediaUrl` → `null`;
`CheckMediaAttachable` → 422.

Сценарии тестирования:
- `GetMediaUrlHandlerTest`:
  - на `ReadyOriginalRemoved`-медиа запрос конверсии (`conversionType != null`) возвращает URL;
  - на `ReadyOriginalRemoved`-медиа запрос оригинала (`conversionType = null`) бросает
    `NotFoundException` с проверкой сообщения `expectExceptionMessage('app.media.original_removed')`
    (а не только класса — фиксируем новый контракт ошибки, отличный от `not_ready`);
  - на `Processing` (не-ready) медиа запрос оригинала по-прежнему бросает `app.media.not_ready` —
    закрывает вторую 404-ветку оригинала; существующий тест `testRejectsMediaThatIsNotReady`
    оставить (он покрывает эту ветку), при рефакторе НЕ удалять;
  - на `Processing` (не-ready) медиа запрос конверсии бросает `app.media.not_ready` — фиксирует,
    что `isFinalized()` не ослабил гард для не-готовых.
- `GetAudioWaveformHandlerTest`: на `ReadyOriginalRemoved` аудио-медиа волна возвращается;
  существующий негативный кейс «не-ready медиа → 404» оставить как регрессию (`isFinalized()`
  не должен пускать `WaitingUpload`/`Processing`).
- `FindMediaUrlHandlerTest`: на `ReadyOriginalRemoved`-медиа результат `null` (регрессия Posts).
- `CheckMediaAttachableHandlerTest`: на `ReadyOriginalRemoved`-медиа — `ValidationException`
  (строгость attach сохранена).

Проверка:
- `make test`, `make phpstan`. Особо: тест Posts
  `GetPostHttpTest::testUnavailableAttachedMediaIsExcludedWithoutError` остаётся зелёным.

### 3. Документация модуля
Цель: README отражает новый сценарий и семантику статуса `readyOriginalRemoved`.

Что сделать:
- В `app/src/Modules/Media/README.md`:
  - в таблицу публичного Application-API добавить строку
    `RemoveMediaOriginal(userId, mediaId)` → `MediaResult` (Command);
  - в раздел «Статусы и обработка ошибок» добавить переход `ready → readyOriginalRemoved` и
    смысл: оригинал физически удалён, `storage`/`path` — историческая ссылка, конверсии живут;
  - кратко описать семантику запросов после удаления оригинала (конверсии и волна доступны;
    оригинальный URL — 404; `FindMediaUrl` → null; `CheckMediaAttachable` строго ready);
  - синхронизировать существующие строки таблицы API про `GetMediaUrl` и `GetAudioWaveform`
    (текущие описания около строк 25-26 README): отметить, что они обслуживают и
    `readyOriginalRemoved` (конверсия/волна), тогда как оригинальный `GetMediaUrl` — нет;
  - в заметке про `DeleteMedia` отметить, что он корректно отрабатывает и на
    `readyOriginalRemoved` (повторный `deleteObject` оригинала 404 → no-op);
  - отметить, что гард требует ≥1 конверсии (Document/пустой план удалить оригинал не дают).

Результат: README соответствует реализации; новых env/конфигов/раннеров нет.

Сценарии тестирования:
- Документная фаза без своих автотестов и без своего gate: PHPStan/тесты README не проверяют, а
  основной gate уже зелёный после фазы 2. Критерий — отсутствие регрессий в подтверждающем прогоне.

Проверка:
- Финальный гейт `make qa` зелёный (стиль + PHPStan + 100% PCOV-покрытие) — это и есть проверка
  100%-покрытия для всей задачи; README-правки своего gate не добавляют.

## Тесты
Стратегия `after_each_phase`: каждая из фаз 1 и 2 завершается своим набором тестов и быстрым прогоном
`make test` + `make phpstan`; финальный гейт 100%-покрытия — `make qa` (покрытие проверяет именно он
через PCOV, не `make test`), запускается в конце фазы 3. Покрытие — 100% (правило проекта): новый
Handler, доменный предикат `isFinalized()` и расширенные guard-ы `recordTemporary/PermanentProcessingError`
покрываются в фазе 1, изменённые ветки query-обработчиков и `ProcessMediaHandler` — в фазах 1–2. Дублёры файлового сервиса — `createMock`
там, где проверяется факт/число вызовов `deleteObject`, и `createStub` там, где вызов не
проверяется (правило «Стаб вместо expects() для дублёров без проверки вызова»).

## Логирование
Стратегия `debug_precise`. `RemoveMediaOriginalHandler` помечен `#[LogOperation]` (debug-лог
старта и времени операции) и пишет точечные debug-логи: при идемпотентном пропуске
(«Удаление оригинала медиа пропущено: оригинал уже удалён», контекст `mediaId`) и по завершении
(«Оригинал медиа удалён», контекст `mediaId`, `userId`, `storage`, `path`). Уровни по правилу
«DEBUG по умолчанию»: это нормальный flow владельца, не WARN/ERROR. Сообщения — на русском, без
англицизмов; ключи контекста — camelCase. Query-обработчики свой лог не расширяют
(`FindMediaUrl` уже под `#[LogOperation]`).

## Документация и эксплуатация
- Обновляется `app/src/Modules/Media/README.md` (фаза 3). `docs/arch.md`/`docs/rules.md` не
  требуют правок: задача укладывается в существующую архитектуру (новый Application-сценарий +
  изменение поведения Query внутри модуля).
- Env/конфиги не меняются; новые раннеры/процессы не вводятся; миграции не нужны.
- Релизных предусловий нет: формат `MediaUploaded` и очередь не затронуты, слив очереди не
  требуется. Откат — обратный revert кода (схема БД не менялась).
- Эксплуатационный момент: оригинал удаляется явно и до доменного перехода, конверсии остаются и
  продолжают резолвиться; `DeleteMedia` затем штатно чистит конверсии и (идемпотентно) уже удалённый
  оригинал. Принятый риск окна сбоя: если процесс упадёт между `deleteObject` и `persist`, статус
  останется `Ready`, и до повторного запуска команды `GetMediaUrl(original)`/`FindMediaUrl` отдадут
  ссылку на уже удалённый объект (скачивание → S3 404). Повтор команды чинит статус; «висячая
  ссылка» в этом узком окне — осознанный компромисс, а не инвариант «рассогласования нет».

## Изменения после мета-ревью

### После моделей
- **+ Добавлено:** раздельные тест-кейсы `no_conversions_to_keep` для ready-`Document` и
  ready-`Image` (оба достижимы); парные негативные кейсы `GetMediaUrl` для оригинала (`Processing →
  not_ready`) и для конверсии (`Processing → not_ready`), закрывающие обе 404-ветки на 100%;
  регрессия `DeleteMediaHandlerTest` на медиа в `ReadyOriginalRemoved`; полный список не-ready
  статусов в тесте предиката `isFinalized()`.
- **~ Изменено:** уточнена структура `GetMediaUrlHandler` после рефактора (плоские guard-ветки,
  переиспользование `findConversion()`/`buildUrl()`, `handle()` ≤ ~40 строк); зафиксирован путь и
  тип теста предиката (`tests/Unit/...MediaEntityTest`, unit); проверка наличия конверсий описана как
  ленивая `||` тремя раздельными `findByMediaId(...)->isNotEmpty()`; `#[LogOperation]` берётся по
  образцу `DeleteMediaHandler` (не `MakeMediaPermanent`); конкретизированы значения и camelCase-ключи
  лог-контекста; уточнено, что `app.media.not_ready` переиспользуется, новых ключей ровно три; в
  алгоритме явно отмечен ранний `return` идемпотентного пропуска без `deleteObject` и удаление именно
  целевого (не staging) оригинала.
- **− Убрано:** категоричное «осиротевшие объекты/рассогласования не появляются» — заменено на явно
  описанный принятый риск «висячей ссылки» в окне сбоя между `deleteObject` и `persist`.
- **Отклонено:** замечание haiku, что `Document` не достигает `Ready` (опровергнуто кодом
  `ProcessMediaHandler` ветка `MediaType::Document => []` + `markReadyMovedTo` и тестом
  `GetMediaUrlHandlerTest::testReturnsUrlForReadyDocument`) — вместо этого добавлен отдельный
  Document-кейс; предложение sonnet добавить тест перехода `markReadyOriginalRemoved()` в Entity
  (уже покрыт существующим кейсом `MediaEntityTest`); предложение «тест на все три типа конверсий
  одновременно» (недостижимое доменное состояние: у медиа один `type`; покрытие достигается тремя
  раздельными per-type success-кейсами).

## Реакция на ревью

Strict кросс-CLI ревью (`codex`, gpt-5.5, итог 78/100; полный список —
`2026-06-24_23-26_media-remove-original_review.md`). Внесено в план:

- **Защита финализированного состояния от поздней переобработки** (главное замечание): повторный/дубль
  `ProcessMediaCommand` после удаления оригинала прочитал бы удалённый объект и через запись ошибки
  перевёл бы `ReadyOriginalRemoved → ProcessingFailed`. Добавлены: переход `ProcessMediaHandler` на
  `isFinalized()` в early-skip; расширение guard-ов `recordTemporary/PermanentProcessingError` на
  `isFinalized()`; тесты «ProcessMedia на ReadyOriginalRemoved — no-op» и «record-error — no-op».
- **Гейт покрытия — `make qa`**, а не `make test` (PCOV-coverage гоняет именно `make qa`). Обновлены
  «Задача», «Тесты», проверка фазы 3.
- **Локаль `no_conversions_to_keep`**: ru-текст переведён на «преобразование» под стиль существующих
  переводов media (а не «конверсия»).
- **Проверка сообщений-ключей** в негативных тестах (`expectExceptionMessage` на новые ключи), а не
  только класса исключения.
- **`FindMediaUrl` потребляют Posts и User (аватары)** — зафиксировано как ожидаемое единообразное
  поведение (removed-original → `null` → значение по умолчанию), отдельная семантика для аватаров не вводится.
- **Допущение про статус конверсий**: в БД пишутся только `Ready`-конверсии (атомарный flush), поэтому
  гард «≥1 конверсия» = «≥1 готовая»; фильтр по статусу конверсии не вводим (зафиксировано как допущение, а не код).
- **Ссылка на research** в Контексте помечена как исторический контекст, а не `sources.research`.

Отклонённых замечаний нет: все пункты codex приняты (часть — как явная фиксация допущения/риска в
тексте, а не как новый код). Спорных, требующих решения пользователя, среди замечаний не было —
все укладываются в подтверждённый ранее scope и его корректную реализацию.

## Прогресс выполнения
Журнал: `docs/executions/2026-06-25_11-18_media-remove-original.md`

- [x] Фаза 1: Команда RemoveMediaOriginal, доменный предикат и локали
- [x] Фаза 2: Семантика конверсионных и оригинал-запросов
- [x] Фаза 3: Документация модуля
