---
plan: docs/plans/2026-06-08_22-56_media-upload-pipeline.md
started: 2026-06-09 12:46
finished: 2026-06-09 15:40
status: done
---

# Журнал: модуль Media — переиспользуемый Application-API загрузки

Выполняется в текущей ветке `main` (решение пользователя). Конфликтов плана с
`docs/rules.md`/`docs/arch.md` нет; единственное расхождение (UUID v4 в
`MediaStorageKey`) план явно выносит за scope и не трогает.

## Шаги
| # | Шаг | Файлы | Тесты | Статус |
|---|-----|-------|-------|--------|
| 1 | Зависимости и Docker (Imagick + Intervention + aws-sdk) | `composer.json`/`composer.lock`, `docker/Dockerfile` | `make test` 222/222, `make phpstan` OK, smoke Intervention (imagick+gd) | done |
| 2 | Конфиг, Application-DTO, контракты, S3-сервис | `app/config/media.php`, `MediaConfig`, `Media/Application/Dto/*`, `MediaMimeTypeCollection`, `MediaFileServiceContract`, `MediaImageProcessorContract`, `S3MediaFileService`+`S3ClientProvider`/`ConfiguredS3ClientProvider`, infra-exceptions, `ensure-buckets.sh`, `.env.sample`, `phpunit.xml`, `ConfigShapeTest` | `make test` 242/242, `make phpstan` OK; `MediaConfigTest`, `S3MediaFileServiceTest` (MinIO), `S3MediaFileServiceErrorTest` (защитные ветки) | done |
| 3 | Процессор изображений + доменные методы/пути | `ImagickMediaImageProcessor`, `MediaImageProcessorException`, `Media::markReadyMovedTo`/`isReady`, `MediaPath::imageConversion`/`originalReady`/`sanitizeExtension` | `make test` 253/253, `make phpstan` OK; unit процессора (imagick+gd, JPEG/PNG, неизв. драйвер), `MediaPath`-фабрики, `markReadyMovedTo`/`isReady` | done |
| 4 | CQRS-сценарии + outbox-сообщение | 6 Command + 3 Query (`Application/Command|Query/Media/*`), `MediaUploaded`, `MediaTypeResolver`, `MediaPath::extension`, `run-tests.sh` (таймаут) | `make test` 298/298, `make phpstan` OK; feature-тесты каждого Handler (happy + ошибки), `MediaTypeResolverTest` | done |
| 5 | Проводка outbox: Job, bootloader, очередь, Kernel | `ProcessMediaJob`, `MediaBootloader`, `app/config/queue.php`, `Kernel` | `make test` 300/300, `make phpstan` OK, app-kernel грузится; сквозной flow-тест (complete→relay→ready + конверсии + копия оригинала; путь ошибки → ProcessingFailed + outbox Failed; идемпотентность) | done |
| 6 | Документация + согласование outbox | `docs/rules.md`, `docs/arch.md`, `Modules/Media/README.md`, `docker/README.md`, `run-qa.sh` (таймаут), доп. тесты покрытия (`ProcessMediaJobTest`, `MediaPath::extension`, S3 защитные ветки) | `make test` 310/310, `make phpstan` OK, `composer cs` OK; финальная проверка ниже | done |

## Заметки

- **Фаза 1.** `php8.5-imagick` доступен apt-пакетом (3.8.0) в `ubuntu:26.04 resolute/universe` — выбран apt-маршрут вместо PECL (надёжнее). Добавлены `php8.5-imagick` и `php8.5-gd` (GD-фолбэк-драйвер). В Dockerfile добавлены проверки `php -m | grep imagick|gd`.
- `composer require intervention/image:^4 aws/aws-sdk-php` → `intervention/image 4.1.3`, `intervention/gif 5.0.1`, `aws/aws-sdk-php 3.384.5`. Попутно `composer update` поднял dev-main пакеты gian-tiaga (api-errors, openapi) — это штатное поведение dev-зависимостей; baseline-тесты остались зелёными.
- **API Intervention v4.1.3** (важно для фазы 3): `new ImageManager(new ImagickDriver())`, `->createImage($w,$h)` / `->decodeBinary($bytes)` / `->decodeStream($stream)`; ресайз `->scaleDown(width:..)` / `->cover($w,$h)`; кодирование `->encodeUsingFileExtension('jpg')` или `encodeUsingMediaType()`, возвращает `EncodedImageInterface` (`(string)$enc` = байты, `->mimetype()`, `->mediaType()`). Методов `read()/create()/toJpeg()` в этом релизе нет.
- **Фаза 2.** `MediaConfig` лежит в `Shared/Infrastructure/Configuration/Media` и авто-регистрируется `ConfigBootloader` (скан `*Config.php`); поля — примитивы (как у других TypedConfig). Драйвер обработки — `string` в конфиге, чтобы Shared не зависел от модульного enum.
- S3-клиент построен через seam `S3ClientProvider` (prod — `ConfiguredS3ClientProvider`): нужно, чтобы покрыть защитные ветки `S3MediaFileService` (нештатный ответ S3, проброс ошибок), недостижимые против реального MinIO. В тестах — `FakeS3Client extends S3Client` (передаёт `'service' => 's3'`, иначе SDK выводит сервис из имени класса) + `FakeS3ClientProvider`. 100% покрытие = построчное (`assert-coverage.php` сравнивает `coveredstatements/statements`), поэтому каждую throw-ветку покрываю явно.
- phpstan считает `StorageServerConfig->options` non-null (id `nullsafe.neverNull`) → в `ConfiguredS3ClientProvider` используется `->`, не `?->`.
- Контракт `MediaFileServiceContract` принимает `\DateTimeImmutable $expiresAt` (presign-методы), единый источник TTL — `MediaConfig` в Handler-е (фаза 4). Добавлены `getObjectContents`/`putObject` (нужны для конверсий в фазе 4/6 алгоритма).
- Тест-изоляция S3: в `phpunit.xml` выставлен `MEDIA_*_STORAGE_PREFIX=test`; тесты чистят созданные ключи в `tearDown`. `media-public` получил anonymous download policy в `ensure-buckets.sh` (применяется в режиме `ensure`, не в `reset-test`).
- **Фаза 3 (решение, см. таблицу вопросов).** Guard `markReadyMovedTo` расширен до `uploaded`/`processing`/`processingFailed` → `ready` (план писал «только uploaded/processing», но его же retry-поток приходит из `processingFailed`; со строгим guard ретрай вечно падал бы в 500). На `ready` — no-op.
- `MediaPath::imageConversion` → `images/<shard>/<key>/<type>.<ext>`; `originalReady` → префикс по `MediaType` (`images`/`videos`), `Audio`/`Document` → `InvalidDomainValueException` (вне scope, mime-резолвер отсекает их раньше 422). Санитизация расширения вынесена в общий `sanitizeExtension` (DRY с `originalUpload`).
- Процессор использует `cover(w,h)` → точные целевые размеры; результат отдаёт фактические `width`/`height` (== spec при cover), `mimeType`/`size` из закодированных байт. Имя класса `ImagickMediaImageProcessor` по плану, хотя поддерживает и gd-драйвер.
- **Фаза 4.** Расширение оригинала берётся из `MediaFileMeta.fileName` (pathinfo) при RequestUpload, хранится в пути и переиспользуется в `ProcessMedia` для конверсий и ready-оригинала через `MediaPath::extension()`; конверсии сохраняют формат оригинала (target mime = `media.mimeType`). Дименсии конверсии в БД = фактические из результата процессора (== spec при cover).
- `RecordMediaProcessingFailure` вызывает один доменный метод (`recordTemporaryProcessingError`); `isTransient` не влияет на Entity (методы идентичны) — только на решение Job о ретрае и идёт в контекст лога.
- Handler-тесты — **feature-стиля**: репозитории `final`, мокать нельзя, поэтому реальные репозитории из контейнера + замоканные контракты (`MediaFileServiceContract`/`MediaImageProcessorContract`/`OutboxEventStoreContract`). `#[Transactional]`/`#[LogOperation]` при прямом вызове Handler не активны (их ставит CQRS-bus) — транзакционность сквозного потока проверяется в фазе 5.
- **PHPUnit 13 nuance:** `createMock()` без `->expects()`, у которого настроенный stub-метод ни разу не вызван, даёт «PHPUnit Notice» (совет использовать stub). Идиом: `createStub()` для stub-only дублей, `createMock()` только с `->expects()`. Все notice устранены.
- `run-tests.sh`: `COMPOSER_PROCESS_TIMEOUT=900` для `composer test` (suite вырос до 298 тестов, ~6 мин; раньше упирался в дефолтные 300с composer). Зеркалит приём из `run-qa.sh`.
- **Фаза 5.** `ProcessMediaJob`: try-catch вокруг dispatch разрешён (Job — граница, и реально обрабатывает: пишет ошибку на Media через `RecordMediaProcessingFailure`, логирует ERROR, решает retry/terminal). Классификация `isTransient`: только `AwsException` с `isConnectionError()`/5xx/throttling-кодами → транзиентная (`RetryException`); Imagick/прочее → постоянная (терминальный проброс → outbox failed). Текст ошибки — из 2 предопределённых безопасных сообщений (без сырого AWS-текста, иначе `MediaProcessingError` отклонил бы).
- `MediaBootloader` (после `OutboxBootloader` в Kernel) биндит `MediaFileServiceContract`/`MediaImageProcessorContract`/`S3ClientProvider` и регистрирует пару `MediaUploaded → ProcessMediaJob`. `queue.php`: `ProcessMediaJob` в `registry.handlers`+`registry.serializers` (`OutboxQueueSerializer`); порядок `interceptors.consume` не менялся.
- Сквозной тест гоняет реальный MinIO+Imagick+outbox на `QUEUE_CONNECTION=sync`: `relay()` исполняет Job inline; проверены ready+конверсии+копия оригинала, путь ошибки (битый файл → ProcessingFailed + outbox Failed) и идемпотентность (повтор ProcessMedia на ready — no-op).

## Изменения в docs

- **Фаза 6 (выполнено).** `docs/rules.md` — правило «Внешние события только через outbox» переформулировано в «Внешние события и отложенные шаги через outbox» (критерий — надёжная доставка после commit, не природа эффекта). `docs/arch.md` раздел «События и outbox» — добавлен абзац про внутренние отложенные шаги (пример: `MediaUploaded`). Создан `app/src/Modules/Media/README.md` (публичный API, спецификации, поток, статусы, outbox, транзакционная дисциплина S3, точки входа в потребителях). `docker/README.md` — imagick (apt `php8.5-imagick`) + GD-фолбэк-драйвер. Ключи `media.php` в `.env.sample`/`phpunit.xml` добавлены в фазе 2.

## Финальная проверка

- `composer cs` (php-cs-fixer): **OK** — 0 файлов к правке (445 файлов).
- `make phpstan` (level max): **OK** — No errors.
- `make test` (полный сьют): **OK** — 310/310 тестов, 1041 assertion. Только пред-существующие deprecation (1 + 29 PHPUnit), не связанные с задачей.
- `make qa` (= cs + phpstan + test + test-coverage 100%): **частично** — cs/phpstan/test зелёные; шаг покрытия падает: **92.52% < 100%**.

### Про покрытие 100% (важно)

Глобальный gate `make qa` требует 100% построчного покрытия по всему `app/src`. Он
**не достижим в рамках этого плана из-за пред-существующего долга покрытия**, который был
ниже 100% и до этой работы:

- Проверено: CI/workflows в репозитории нет; `@codeCoverageIgnore` нет; низкопокрытые файлы
  (`Shared/Infrastructure/Cycle/ValueObjectCast` 78%, `LazyGhostMapper` 75%,
  `LazyGhostEntityFactory` 88%, `ConfigMapper` 89%, `ExceptionHandlerBootloader` 75%;
  `Outbox` `OutboxRelay` 91%, `StoredOutboxEvent` 84%; существующие Media-VO `MediaExpiration`
  75%, `MediaMimeType` 89%, `MediaProcessingError` 88%, `MediaStorageKey` 90% и типкасты)
  **не изменялись этой задачей** (git status чист по ним) и покрываются <100% существующими
  тестами. Мои правки могут только добавлять покрытие, поэтому baseline был <100%.
- **Что покрыто на 100% этой задачей:** все новые публичные Application-сценарии Media
  (6 Command + 3 Query handlers, DTO, `MediaUploaded`, `MediaTypeResolver`), `ProcessMediaJob`
  (все ветки классификации ошибок), `ImagickMediaImageProcessor`, `S3MediaFileService`
  (126/126 строк, 18/18 методов), `S3ClientProvider`/`ConfiguredS3ClientProvider`,
  `MediaConfig`, `MediaBootloader`, новые исключения. Это покрывает критерий плана
  «публичные сценарии Media … покрыты тестами на 100%».
- **Остаточные пробелы — пред-существующие и вне scope:** Shared-инфраструктура
  (`ValueObjectCast`, `LazyGhost*`, `ConfigMapper*`), Outbox-внутренности, существующие
  Media-VO/типкасты и зарезервированные (намеренно неиспользуемые) доменные методы
  `Media` (`recordPermanentProcessingError`, `startCompletingMultipartUpload` и т.п.),
  плюс ветки `MediaPath::assertValid` (длина/формат). Их покрытие — отдельная задача по всему
  репозиторию, не входит в этот план и не должна делаться молчком в рамках media-фичи.

Решение (автономно, по просьбе пользователя): новый код задачи доведён до 100%; глобальный
долг покрытия зафиксирован как пред-существующий блокер `make qa`, чужие/несвязанные файлы не
трогались (правило eda-execute про «чужие поломки»). cs, phpstan и `make test` зелёные.
</content>
</invoke>
