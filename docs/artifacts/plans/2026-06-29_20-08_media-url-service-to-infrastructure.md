---
title: MediaUrlService → Infrastructure за контрактом MediaUrlServiceContract
date: 2026-06-29 20:08
mode: normal
plan_size: small
decision_mode: recommend_and_ask
status: ready
test_strategy: after_each_phase
logging_strategy: debug_precise
sources:
  rules: docs/rules.md
  arch: docs/arch.md
  prev_plan: docs/plans/2026-06-29_16-38_application-config-independence.md
---

# План реализации

## Задача

Перевести построение URL медиа на паттерн `arch.md` «технический сервис с поведением → `Application/Contract`
+ реализация в `Infrastructure`». Сейчас `MediaUrlService` лежит в `Application/Service` и получает срок
presigned по умолчанию обёрткой `MediaPresignedTtl` через фабрику бутлоадера. Нужно:

- ввести контракт `MediaUrlServiceContract` в `Application/Contract`;
- перенести реализацию в `Infrastructure/FileService` (рядом с `S3MediaFileService`), которая инжектит
  `MediaConfig` напрямую и читает `presignedTtlSeconds`;
- убрать обёртку `MediaPresignedTtl $defaultPresignedTtl` из конструктора сервиса и фабрику
  `mediaUrlService` из `MediaBootloader` (биндинг — обычный `const BINDINGS`);
- перенести резолверы (`MediaUrlResolver`, `PublicMediaUrlResolver`, `PresignedMediaUrlResolver`) в
  `Infrastructure/FileService` как деталь реализации;
- потребители (`FindMediaUrlHandler`, `PostViewAssembler`) зависят от `MediaUrlServiceContract`.

**Охват — только `MediaUrlService`.** `MediaUploadSettings` (загрузка), аватарная строка
(`UserPublicProfileAssembler`), `LocaleResolver` (локаль) — **не трогаем**.

Готово, когда: `FindMediaUrlHandler` и `PostViewAssembler` зависят от контракта; реализация в
`Infrastructure/FileService` читает `MediaConfig`; обёртка `MediaPresignedTtl` и фабрика бутлоадера убраны;
старые файлы из `Application/Service` удалены; `make qa` зелёный (100% покрытие).

## Контекст

- Текущее состояние — результат предыдущей задачи (план `2026-06-29_16-38`). Там сервис намеренно оставлен
  в `Application/Service` и получает `MediaPresignedTtl` через фабрику `MediaBootloader::mediaUrlService()`.
  Пользователь решил переделать: технический сервис → в Infrastructure за контрактом, реализация читает
  обычный `MediaConfig`, без обёрток.
- `arch.md` уже поощряет этот паттерн: «Если Application нужен именно технический сервис с поведением (S3,
  процессор, внешний клиент) — он по-прежнему идёт через `Application/Contract` + реализацию в
  `Infrastructure`». `MediaUrlService` — ровно такой (строит URL поверх `MediaFileServiceContract`).
- Правило «Domain/Application не импортируют `*Config`» **остаётся верным**: Application зависит от
  контракта, конфиг читает Infrastructure-реализация. Поэтому `arch.md` и `rules.md` **не меняются**.
- foundational-Media: `PostViewAssembler` (модуль Posts) зовёт сервис напрямую — теперь через контракт
  `MediaUrlServiceContract` (`Media/Application/Contract`), что разрешено (модули обращаются к `Application`
  другого модуля; контракт — часть Application).
- Конфиг `presignedTtlSeconds` уже есть и **не меняется**: `app/config/media.php`
  (`\max(1, (int) \env('MEDIA_PRESIGNED_TTL_SECONDS', 3600))`), `MediaConfig->presignedTtlSeconds: int`,
  `.env.sample`, `phpunit.xml` (`value="3600"`). Valinor маппит по имени ключа, не по позиции.

## Принятые решения

- **Контракт:** `MediaUrlServiceContract::getUrls(Media $media, int|null $presignedTtlSeconds = null):
  MediaUrlsResult|null`. Принимает Domain Entity `Media`, возвращает Application DTO `MediaUrlsResult` —
  допустимо для контракта в `Application/Contract`.
- **Реализация:** `App\Modules\Media\Infrastructure\FileService\MediaUrlService implements
  MediaUrlServiceContract`, конструктор `(MediaFileServiceContract $mediaFileService, MediaConfig
  $mediaConfig)`. Срок по умолчанию читается из `$this->mediaConfig->presignedTtlSeconds` (обычный конфиг,
  прямая инъекция). Это не нарушает «Application без конфига» — класс теперь Infrastructure.
- **Резолверы** (`MediaUrlResolver` interface, `PublicMediaUrlResolver`, `PresignedMediaUrlResolver`)
  переезжают в `Infrastructure/FileService` — это деталь реализации построения URL.
- **Биндинг:** в `MediaBootloader` через `const BINDINGS` (как `MediaFileServiceContract`):
  `MediaUrlServiceContract::class => MediaUrlService::class`. Из `defineSingletons()` убрать запись и фабрику
  `mediaUrlService`, а также импорты `MediaUrlService` (Application) и `MediaPresignedTtl`. Фабрика и запись
  `mediaUploadSettings` **остаются**.
- **Строгая семантика TTL сохраняется:** `MediaPresignedTtl::fromInt($presignedTtlSeconds ??
  $this->mediaConfig->presignedTtlSeconds)`. `??` ловит только `null`; явный `0` остаётся `0` и `fromInt(0)`
  бросает `InvalidDomainValueException` (MIN=1). public-ветка short-circuit'ит до TTL. `expiresAt` считается
  в `resolverFor` на каждый private-вызов (сервис stateless `final readonly`).
- **DTO остаются в Application:** `MediaUrlsResult`, `MediaUrlResult`, `MediaConversionUrl`,
  `MediaConversionUrlCollection` — возвращаемые типы контракта, место не меняют.
- **Конфиг не трогаем:** `app/config/media.php`, `MediaConfig`, `.env.sample`, `phpunit.xml`.
- **`arch.md` / `rules.md` не трогаем** — правило про Application/Domain без `*Config` остаётся верным.
- **VO `MediaPresignedTtl`** остаётся в `Domain/ValueObject` (используется в загрузке `MediaUploadSpec` и в
  новой реализации `resolverFor`).

## Целевой алгоритм

```text
Сборка контейнера:
  MediaBootloader.BINDINGS:  MediaUrlServiceContract -> Infrastructure\FileService\MediaUrlService
                             (авто-вайр: MediaFileServiceContract + MediaConfig)

Рантайм:
  FindMediaUrlHandler / PostViewAssembler -> MediaUrlServiceContract.getUrls(media, ?ttl)
    реализация (Infrastructure):
      if !finalized                 -> null
      resolverFor(visibility, ?ttl):
        public                      -> PublicMediaUrlResolver (без TTL)
        private                     -> ttl = MediaPresignedTtl::fromInt(?ttl ?? mediaConfig.presignedTtlSeconds)
                                       expiresAt = now + ttl (на каждый вызов) -> PresignedMediaUrlResolver
```

## Контракты реализации

### Данные и БД
Не затрагивается.

### API и внешние контракты
HTTP-маршруты, Filter, Response не меняются. Меняются внутренние типы:

```text
НОВОЕ:
  App\Modules\Media\Application\Contract\MediaUrlServiceContract
    getUrls(Media $media, int|null $presignedTtlSeconds = null): MediaUrlsResult|null

ПЕРЕЕЗД (Application\Service -> Infrastructure\FileService):
  MediaUrlService            (implements MediaUrlServiceContract; конструктор (MediaFileServiceContract, MediaConfig))
  MediaUrlResolver           (interface)
  PublicMediaUrlResolver
  PresignedMediaUrlResolver

УДАЛЯЕТСЯ (старое расположение):
  App\Modules\Media\Application\Service\MediaUrlService.php
  App\Modules\Media\Application\Service\MediaUrlResolver.php
  App\Modules\Media\Application\Service\PublicMediaUrlResolver.php
  App\Modules\Media\Application\Service\PresignedMediaUrlResolver.php

ИЗМЕНЁННЫЕ КОНСТРУКТОРЫ:
  FindMediaUrlHandler(... , MediaUrlServiceContract $mediaUrlService)   // было MediaUrlService
  PostViewAssembler(... , MediaUrlServiceContract $mediaUrlService)     // было MediaUrlService

БИНДИНГ:
  MediaBootloader.BINDINGS += MediaUrlServiceContract::class => MediaUrlService::class
  MediaBootloader.defineSingletons(): убрать mediaUrlService (фабрику и запись); mediaUploadSettings оставить
```

Контракт и реализация (ключевой код):

```php
// Application/Contract/MediaUrlServiceContract.php
interface MediaUrlServiceContract
{
    public function getUrls(Media $media, int|null $presignedTtlSeconds = null): MediaUrlsResult|null;
}

// Infrastructure/FileService/MediaUrlService.php
final readonly class MediaUrlService implements MediaUrlServiceContract
{
    public function __construct(
        private MediaFileServiceContract $mediaFileService,
        private MediaConfig $mediaConfig,
    ) {}

    public function getUrls(Media $media, int|null $presignedTtlSeconds = null): MediaUrlsResult|null
    {
        if (!$media->isFinalized()) {
            return null;
        }
        $resolver = $this->resolverFor(visibility: $media->visibility, presignedTtlSeconds: $presignedTtlSeconds);

        return new MediaUrlsResult(
            original: $media->isReady() ? $resolver->resolve(storage: $media->storage, path: $media->path) : null,
            conversions: $this->conversionUrls(media: $media, resolver: $resolver),
        );
    }

    private function resolverFor(MediaVisibility $visibility, int|null $presignedTtlSeconds): MediaUrlResolver
    {
        if ($visibility === MediaVisibility::Public) {
            return new PublicMediaUrlResolver($this->mediaFileService);
        }
        $ttl = MediaPresignedTtl::fromInt($presignedTtlSeconds ?? $this->mediaConfig->presignedTtlSeconds);
        $expiresAt = new \DateTimeImmutable()->add(new \DateInterval(\sprintf('PT%dS', $ttl->value())));

        return new PresignedMediaUrlResolver(mediaFileService: $this->mediaFileService, expiresAt: $expiresAt);
    }

    // conversionUrls() / conversionUrl() — переносятся как есть
}

// MediaBootloader
protected const BINDINGS = [
    MediaFileServiceContract::class => S3MediaFileService::class,
    MediaUrlServiceContract::class  => MediaUrlService::class,   // новый, из Infrastructure\FileService
    // ...процессоры, S3ClientProvider...
];
```

## Фазы выполнения

### 1. Контракт + перенос реализации в Infrastructure/FileService + биндинг + потребители

Что сделать (атомарный рефактор — суть согласована, разбивать дальше нет смысла):
- Создать `Application/Contract/MediaUrlServiceContract`.
- Создать `Infrastructure/FileService/MediaUrlService` (implements контракт; конструктор
  `(MediaFileServiceContract, MediaConfig)`; `resolverFor` читает `$this->mediaConfig->presignedTtlSeconds`;
  `getUrls`/`conversionUrls`/`conversionUrl` переносятся). Импорты `MediaVisibility`, `MediaPresignedTtl`,
  `MediaConfig`, DTO, резолверы.
- Перенести `MediaUrlResolver` (interface), `PublicMediaUrlResolver`, `PresignedMediaUrlResolver` в
  `Infrastructure/FileService` (сменить namespace). Поправить докблок `MediaUrlResolver` (ссылается на
  `MediaUrlService::resolverFor()`).
- Удалить старые `Application/Service/{MediaUrlService,MediaUrlResolver,PublicMediaUrlResolver,
  PresignedMediaUrlResolver}.php`.
- `MediaBootloader`: добавить в `BINDINGS` `MediaUrlServiceContract::class => MediaUrlService::class`
  (импорт из `Infrastructure\FileService`); из `defineSingletons()` убрать запись `MediaUrlService` и метод
  `mediaUrlService`; убрать импорты `Application\Service\MediaUrlService` и `MediaPresignedTtl`. Оставить
  `mediaUploadSettings`.
- `FindMediaUrlHandler`: тип зависимости `MediaUrlService` → `MediaUrlServiceContract` (импорт на контракт).
- `PostViewAssembler`: то же.

Проверка: `make phpstan` зелёный.

### 2. Тесты

Что сделать:
- `tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php`: хелпер `handler()` строит
  `new MediaUrlService(mediaFileService: $fileService, mediaConfig:
  $this->getContainer()->get(MediaConfig::class))` (импорт реализации из `Infrastructure\FileService`,
  `MediaConfig`; убрать импорт `MediaPresignedTtl`, если больше не нужен). Тесты «default» (private без
  override → now + 3600) и «explicit 0 → InvalidDomainValueException» остаются валидны.
- `tests/Feature/Modules/User/Application/UserApplicationTestCase.php`: то же изменение конструктора
  `MediaUrlService` (импорт реализации + `MediaConfig`, убрать `MediaPresignedTtl`).
- `tests/Kernel/Modules/Media/MediaBootloaderTest.php`: тест биндинга резолвит
  `MediaUrlServiceContract::class` и проверяет `assertInstanceOf(MediaUrlService::class, ...)` (реализация из
  `Infrastructure\FileService`). Тест `MediaUploadSettings` не трогаем.
- Проверить, нет ли прямых юнит-тестов на перенесённые резолверы (грепом
  `Public/PresignedMediaUrlResolver`); если есть — поправить namespace-импорт.

Проверка: `make test` зелёный.

### 3. Документация + финальная проверка

Что сделать:
- `app/src/Modules/Media/README.md`: «URL строит `MediaUrlService`» → «за контрактом
  `MediaUrlServiceContract`, реализация в `Infrastructure/FileService` читает `MediaConfig` напрямую». Убрать
  абзац про «`MediaBootloader` собирает `MediaPresignedTtl` и отдаёт в Application `MediaUrlService`»
  (заменить на «MediaUrlService читает presignedTtlSeconds сам»).
- Докблок `MediaFileServiceContract` (строка ~22): фразу «значение по умолчанию приходит при сборке
  сервиса» поправить на нейтральную (срок по умолчанию читает реализация `MediaUrlService` из конфига).
- Докблок-упоминания `MediaUrlService::getUrls` в `MediaRepository` и `PostMedia` — оставить (метод тот же),
  при желании уточнить, что вызов идёт через контракт.
- `arch.md` / `rules.md` — не трогаем.

Проверка:
- Грепы: `App\Modules\Media\Application\Service\MediaUrlService` нигде не используется; нет ссылок на старые
  namespace резолверов; `MediaPresignedTtl` в `MediaBootloader` отсутствует.
- `make qa` зелёный (100%-гейт покрытия + phpstan + стиль).

## Тесты

Стратегия `after_each_phase`: фаза 1 → `make phpstan`; фаза 2 → `make test`; фаза 3 → `make qa`. Новых
поведенческих тестов не добавляем (поведение не меняется, только размещение и источник дефолта); существующие
тесты `FindMediaUrlHandlerTest` (default + 0→исключение) и Kernel-биндинг сохраняют покрытие реализации и
резолверов. Покрытие 100% обязано остаться.

## Логирование

`debug_precise`. Новых логов нет — рефактор переносит класс и меняет источник дефолта, новых рантайм-ветвей
нет. Ошибочная конфигурация по-прежнему проявляется как `InvalidDomainValueException` из
`MediaPresignedTtl::fromInt` (теперь при первом private-вызове без override, а не на сборке сервиса).

## Документация и эксплуатация

- `README.md` Media и докблок `MediaFileServiceContract` приведены к новому размещению (фаза 3).
- Конфиг, env, миграции, API не меняются — особых шагов релиза нет.

## Прогресс выполнения
Журнал: `docs/executions/2026-06-29_21-42_media-url-service-to-infrastructure.md`

- [x] Фаза 1: контракт + перенос реализации в Infrastructure/FileService + биндинг + потребители (`make phpstan`)
- [x] Фаза 2: тесты (`make test`)
- [x] Фаза 3: документация + финальная проверка (`make qa`)
