Reading additional input from stdin...
OpenAI Codex v0.141.0
--------
workdir: /Users/gian_tiaga/Code/yoga-loka-spiral-2
model: gpt-5.5
provider: openai
approval: never
sandbox: workspace-write [workdir, /tmp, $TMPDIR]
reasoning effort: high
reasoning summaries: none
session id: 019eefb7-4f43-77e1-9715-6650ff0d0b55
--------
user
Прочитай docs/researches/2026-06-22_17-22_media-document-support.md, docs/rules.md, docs/arch.md и ключевые файлы модуля Media (app/src/Modules/Media/Application/Service/MediaTypeResolver.php, app/src/Modules/Media/Domain/ValueObject/MediaPath.php, app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php, app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php, app/src/Modules/Media/Domain/Enum/MediaType.php). Это исследование по добавлению поддержки документов в модуль Media. Проверь: проблема описана конкретно; архитектура не выдумана и соответствует коду; все развилки решены; риски закрыты рядом с решением; MIME-типы документов указаны верно; вывод не является пошаговым планом и не абстрактен. Критическое ревью на русском, только по реальным проблемам. Формат каждой строки: '- [тип] описание — что исправить'.
codex
Прочитаю исследование, правила, архитектуру и указанные файлы модуля Media, затем сверю выводы исследования с фактическим кодом. Код менять не буду, только проверю реальные несостыковки.
exec
/bin/zsh -lc "sed -n '1,260p' docs/arch.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
# Архитектура: модульный монолит на Spiral + CQRS + тактический DDD

## Обзор

YogaLoka - API-first backend для мобильного приложения на PHP 8.5, Spiral
Framework, RoadRunner и Cycle ORM.

Приложение работает как модульный монолит: один runtime, один deploy и один код
приложения. Код группируется в модули по предметной области.

## Модули

Проект устроен как модульный монолит с прагматичным DDD-подходом.

Бизнес-логика живёт в `Domain`. Важные значения оформляются как
`ValueObject`, сущности содержат поведение, а технические детали вынесены в
`Infrastructure`.

DDD-паттерны не вводятся автоматически. Aggregate, Domain Service, Domain Event,
отдельная read model или anti-corruption layer добавляются только когда они
решают реальную проблему в коде.

Модуль - это отдельная область приложения: `Media`, `User`, `Feed`, `Auth`.

Структура нового модуля:

```text
Modules/
  Media/
    Domain/
    Application/
      Contract/
    Repository/
    Infrastructure/
      Cycle/
      FileService/
    Presentation/
```

Назначение слоёв:

```text
Domain         - бизнес-логика модуля.
Application    - сценарии модуля и контракты для технических зависимостей.
Repository     - доступ к БД своего модуля через доменные методы.
Infrastructure - технические реализации контрактов, Cycle typecast, S3, внешние сервисы.
Presentation   - HTTP-контроллеры, фильтры запросов и другие входы.
```

Исключения каждого слоя лежат в папке `Exception/` своего слоя:
`Application/Exception`, `Infrastructure/Exception`, `Presentation/Exception`, а
общие доменные — в `Shared/Domain/Exception`. Слой исключения определяется
контрактом, к которому оно относится, а не местом выброса: например
`OutboxMessageLoadingException` лежит в `Application/Exception`, хотя бросается в
инфраструктурной реализации загрузчика сообщений.

Правила связей:

```text
Domain не зависит от других слоёв.

Application может использовать:
- свой Domain;
- свой Repository;
- свои Contract;
- Application других модулей.

Infrastructure реализует Contract своего модуля.

Presentation вызывает Application своего модуля.
```

Контракты лежат в:

```text
Modules/{Module}/Application/Contract
```

Имена контрактов заканчиваются на `Contract`.

Примеры:

```text
MediaFileServiceContract
```

Репозитории лежат в:

```text
Modules/{Module}/Repository
```

Пример:

```text
MediaRepository
```

Репозитории не являются публичным API модуля. `Application` своего модуля может
использовать свои репозитории напрямую, но другие модули не обращаются к ним.

Технические реализации контрактов лежат в `Infrastructure`:

```text
Infrastructure/
  Cycle/        # typecast и другие классы для Cycle ORM
  FileService/  # техническая работа с файлами
```

Примеры:

```text
S3MediaFileService
```

Общий доменный код, который не принадлежит одному модулю, лежит в `Shared`.

Пример:

```text
Shared/
  Domain/
    Exception/
    Trait/
    ValueObject/
  Presentation/
    Http/
      Resource/
```

Другие модули могут обращаться только к `Application`.

Хорошо:

```text
User/Application -> Media/Application
```

Плохо:

```text
User/Application -> Media/Infrastructure
User/Infrastructure -> Media/Infrastructure
User -> media_files table
User -> MediaRepository
```

Пример:

```text
User/Application/SetAvatar
  -> Media/Application/CheckMediaIsImage
  -> User/Domain/User::setAvatar
```

`Media` не должен знать про аватар. Аватар - это часть `User`.

`Media` должен давать только свои сценарии:

```text
CreateMedia
DeleteMedia
CheckMediaExists
CheckMediaIsImage
GetMediaUrl
```

## Локальный Docker-runtime

Локальная разработка и проверки выполняются через Docker Compose из
`docker/docker-compose.dev.yml`. Приложение запускается в двух RoadRunner runtime:

- `app-http`: RoadRunner слушает `0.0.0.0:8080` только внутри контейнера.
  Наружу compose публикует сервис как `127.0.0.1:60080 -> 8080`. RoadRunner
  jobs consumer для RabbitMQ работает в том же процессе.
- `temporal-worker`: отдельный Temporal worker на task queue `default`.

Dev runtime использует RabbitMQ как queue connection по умолчанию. Memory
pipeline остаётся запасным вариантом для локальных экспериментов и обратной
совместимости, но не является основной очередью dev-стенда. Redis в локальном
стенде используется для cache/session и RoadRunner KV, но не является брокером
очереди.

Локальная инфраструктура: PostgreSQL, Redis, RabbitMQ, MinIO, Mailpit, Temporal,
Temporal UI и Centrifugo. Dev storage по умолчанию использует MinIO bucket
`yoga-loka`, тесты используют отдельные `yoga_loka_test` и `yoga-loka-test`.

## Структура каталогов

```
app/
  config/                         # Spiral config-файлы; env() допустим только здесь
  database/
    migrations/                   # Cycle ORM миграции
  locale/                         # Переводы
  src/
    Modules/
      Media/
        Domain/                   # Entity, ValueObject, Enum, доменные коллекции
        Application/              # Command/Query сценарии модуля
          Contract/               # Контракты технических сервисов, *Contract
        Repository/               # Cycle repositories с доменными методами
        Infrastructure/
          Cycle/                  # Typecast и другие классы Cycle ORM
          FileService/            # Реализации файловых сервисов
        Presentation/             # HTTP, console, queue, Temporal входы модуля

      Outbox/
        Domain/                   # Outbox-события, статусы, value object
        Application/
          Command/                # Сценарии relay и обработки outbox-сообщений
          Contract/               # OutboxEventStoreContract и сериализация сообщений
          Exception/              # Исключения слоя Application (загрузка, сериализация сообщений)
          Message/                # DTO сообщений outbox
        Repository/               # Доступ к outbox_events
        Infrastructure/
          Bootloader/             # OutboxBootloader, OutboxConsoleBootloader
          Exception/              # Исключения слоя Infrastructure (registry, relay)
          Relay/                  # Relay, worker, loop control, sleeper
          Queue/                  # Publisher, serializer, headers, status interceptor
          Message/                # Event store, message loader, message serializer
          Registry/               # Реестр пары message -> Job
          Cycle/                  # Typecast outbox-полей
        Presentation/
          Console/                # outbox:relay
          Job/                    # Технические Job outbox

      System/
        Infrastructure/
          Bootloader/             # SystemBootloader: регистрация namespace `system` для view
        Presentation/
          Http/                   # Health, Swagger UI, OpenAPI YAML route
          Console/                # openapi:* команды
          Exception/              # Исключения слоя Presentation (публикация ассетов OpenAPI)
          Temporal/               # технические workflow
          views/                  # twig-шаблоны модуля (swagger/index), namespace `system`

    Shared/
      Domain/
        Exception/                # Общие доменные исключения
        Trait/                    # Общие доменные трейты
        ValueObject/              # Общие базовые VO и общие идентификаторы
      Presentation/
        Http/
          Resource/               # Общие базовые API-ресурсы
      Infrastructure/
        Cache/
        Configuration/            # Типизированные config DTO и ConfigMapper
        Cycle/                    # Общие typecast-классы
        Exception/                # Общие инфраструктурные исключения (config mapping)
        Framework/                # Spiral Kernel, bootloaders, routes
```

## Правила зависимостей

Внешние слои могут зависеть от внутренних, внутренние не зависят от внешних.

```text
Presentation   -> Application своего модуля, Response/Resource, framework attributes
Application    -> Domain, Repository своего модуля, Contract своего модуля

exec
/bin/zsh -lc "sed -n '1,240p' docs/rules.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc "sed -n '1,220p' app/src/Modules/Media/Application/Service/MediaTypeResolver.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Service;

use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Shared\Domain\Exception\ValidationException;

/**
 * Резолвер MIME -> MediaType. Намеренно сужающий: поддержаны image/*, video/* и audio/*;
 * прочие типы (документы и т.п.) вне области пайплайна и отклоняются как ожидаемая клиентская
 * ошибка 422 (MediaPath допускает только префиксы uploads|images|videos|audios).
 */
final readonly class MediaTypeResolver
{
    public function resolve(MediaMimeType $mimeType): MediaType
    {
        $value = $mimeType->value();

        if (\str_starts_with(haystack: $value, needle: 'image/')) {
            return MediaType::Image;
        }

        if (\str_starts_with(haystack: $value, needle: 'video/')) {
            return MediaType::Video;
        }

        if (\str_starts_with(haystack: $value, needle: 'audio/')) {
            return MediaType::Audio;
        }

        throw new ValidationException(
            translationKey: 'app.media.unsupported_file_type',
            translationParameters: ['type' => $value],
        );
    }
}

 succeeded in 0ms:
# Правила проекта

## Язык

- Отвечай всегда на русском
- Исключения пишутся на русском языке.
- Логи пишутся на русском языке.
- Комментарии к коду пишутся на русском языке.
- Ошибки, которые отдаются пользователю, пишутся на языке пользователя.
- **Без англицизмов**: в русском тексте — комментариях, докстрингах, сообщениях логов и исключений, документации — не использовать кальки с английского, если есть обычное русское слово. Например: «временный» вместо «транзиентный», «рассылка»/«отправка» вместо «диспатч», «по умолчанию» вместо «дефолтный», «полезные данные» вместо «payload». Имена классов, методов, переменных, типов и прочие технические идентификаторы остаются на английском (`DispatchNotificationJob`, `isTransient()`, `outbox`) — переводу подлежит только человекочитаемый русский текст.
- **Коммиты на русском**: сообщения коммитов (subject, body) пишутся на русском языке. Тип и scope остаются на английском по Conventional Commits (`feat`, `fix`, `refactor` и т.д.), но описание — на русском. Пример: `feat(tenant): добавить управление тенантами`.

## Качество кода

- **Ранний возврат**: guard clauses (`throw`/`return`) в начале метода. Инвертировать условие, выбросить исключение первым, happy path без вложенности. Вложенность 2+ уровней `if/else` — красный флаг.
- **Короткие методы**: `handle()` в Handler — не более ~40 строк. Длиннее — выносить в приватные методы.
- **`match` вместо `switch`**: `switch` не используется. Всегда `match`-выражение. Enum — исчерпывающий `match` без `default`.
- **Именованные аргументы**: обязательны при 2+ обычных параметрах и при любом необязательном/булевом параметре. Позиционные — для 1–2 очевидных параметров. Variadic-вызовы и вызовы с unpack (`...$args`) допускают позиционные аргументы, потому что именование ломает читаемость таких API (`sprintf`, `implode` и т.д.). (Проверяется PHPStan)
- **`sprintf()` для строк**: для пользовательских сообщений и строк ошибок. Интерполяция `"{$a}-{$b}"` — только для компактных ключей/идентификаторов. Конкатенация `.` — избегать.
- **Collection-пайплайны**: `->map()`, `->filter()`, `->groupBy()` для чистых трансформаций. `foreach` — только при побочных эффектах.
- **Не подменять методы коллекции array-функциями**: если значение уже `Illuminate\Support\Collection` (в т.ч. типизированная доменная коллекция), для выборок и трансформаций вызывай её методы (`->filter()`, `->map()`, `->first()`, `->contains()`, `->reject()`, `->keyBy()`), а не разворачивай в массив через `->all()` ради `array_filter`/`array_map`. Антипаттерн: `array_filter($collection->all(), $fn)` вместо `$collection->filter($fn)`. `->all()` уместен только когда действительно нужен `array`/`list<T>`: передать в конструктор другой типизированной коллекции, вернуть наружу или применить позиционную операцию без удобного аналога у коллекции (например `array_slice` для курсора).
- **Типизированные Laravel Collections вместо массивов и Doctrine Collections**: в Entity и Repository не использовать массивы для наборов сущностей или value object. Для каждой доменной коллекции создавать именованный класс на базе `Illuminate\Support\Collection` с generic-типом элемента: например, связь `User -> Role` хранится и возвращается как `RoleCollection`, а результат репозитория со списком пользователей — как `UserCollection`. `Doctrine\Common\Collections\ArrayCollection` запрещена. Все связи Cycle ORM и методы репозиториев, возвращающие несколько элементов, должны возвращать конкретную типизированную коллекцию, а не `array` и не голый `Illuminate\Support\Collection`.
- **Явные типы вместо `null`**: `null` в доменной модели не протаскивается через границы — тип свойства Entity, параметры `create()`, аргументы и возвраты доменных методов не должны быть `?T` для доменных данных. Отсутствие, неизвестность или особое состояние выражается отдельным value object, enum или доменным типом, а не `?string`/`?Ip`. Форма null-object VO выбирается по содержанию состояний:
  - **Одно опциональное значение, поведение сводится к «есть/нет»** — один `readonly`-VO с приватным nullable внутри и фабриками присутствия/отсутствия (образец `MediaExpiration` — `permanent()`/`temporaryUntil()`; `MediaProcessingError` — `none()`). Такой инкапсулированный nullable правилу не противоречит: наружу `?T` не виден.
  - **Несколько состояний с разными данными или разным поведением**, где полиморфизм убирает `if`/`instanceof` у вызывающих — абстракция + реализации (образец `Ip` → `KnownIp`/`UnknownIp`).

  По умолчанию для одного опционального значения берётся форма «один VO»; абстракция с наследниками заводится только когда варианты реально несут разные данные или поведение.
- **Строгая типизация**: все PHP-файлы, анализируемые PHPStan, должны начинаться с `declare(strict_types=1)`.
- **Строгие сравнения**: значения сравниваются только через `===` и `!==`; loose comparisons `==`, `!=` и `<>` запрещены.
- **Trailing commas**: обязательны в каждом многострочном списке (аргументы, массивы, `match`, параметры).
- **Нет мёртвого кода**: неиспользуемые переменные, закомментированные блоки, недостижимые ветки — удалять.
- **Инлайн одноразовых переменных**: если переменная используется один раз и получается просто (обращение к свойству, вызов метода, enum-значение), не заводить для неё отдельную переменную — подставлять выражение напрямую. `$slug = $role->slug->value; ... 'slug' => $slug` → `'slug' => $role->slug->value`.
- **Не дублировать конструктор фабриками без смысла**: статическая фабрика (`create()`, `fromParts()`, `fromItems()` и т.д.) добавляется только если она выражает отдельный доменный сценарий, преобразует внешний формат, делает дополнительную валидацию/нормализацию или скрывает сложное создание. Если метод принимает те же данные, что и конструктор, и просто вызывает `new self(...)` без нового смысла — он запрещён; используй конструктор напрямую.
- **Исключения, а не коды ошибок**: при ошибке — типизированное исключение, не `null`/`false`/код.
- **Не плодить технические константы**: одноразовые технические строки, шаблоны и сообщения оставлять рядом с использованием. Константу, enum или value object использовать, когда значение переиспользуется, является публичным контрактом или закрытым набором вариантов.
- **Enum вместо строк**: если значение имеет ограниченный набор вариантов (статус, тип, роль, категория) — использовать `enum`. Строковые литералы `'active'`, `'super_user'`, `'pending'` в бизнес-логике — красный флаг, должен быть enum.
- **UUID v7 для всех идентификаторов**: все первичные ключи и идентификаторы сущностей — UUID версии 7 (`Ramsey\Uuid\Uuid::uuid7()`). UUID v4 и автоинкремент запрещены. UUID v7 обеспечивает хронологическую сортируемость и лучшую производительность индексов в PostgreSQL.
- **Cursor-пагинация по UUID v7 `id`**: для cursor-based пагинации используется `id` (UUID v7) вместо `createdAt`. UUID v7 содержит timestamp и хронологически сортируем, поэтому `ORDER BY id DESC` эквивалентен `ORDER BY createdAt DESC`, но использует PK-индекс напрямую — без дополнительного индекса и без проблем с дубликатами timestamp.
- **Конкретные имена переменных**: запрещены абстрактные имена вроде `$handler`, `$service`, `$manager`, `$data`, `$result`, `$item`. Имя должно отражать суть: `$loginSuperUser` вместо `$handler`, `$tenantRepository` вместо `$repository`, `$activeUsers` вместо `$result`. Это касается и параметров контроллеров: `$updateUserProfileHandler` вместо `$updateHandler`, `$getUserProfileHandler` вместо `$profileHandler`, `$loginFilter` вместо `$filter`. Исключение — лямбды с очевидным контекстом (`fn(Role $role) => $role->slug`).
- **Property hooks вместо геттеров/сеттеров**: в Entity использовать PHP 8.4 property hooks (`get`/`set`) вместо методов `getX()`/`setX()`. Вычисляемые значения — через `get` hook, валидация при записи — через `set` hook.
- **Entity: фабричный метод `create()` вместо конструктора**: Entity не объявляет конструктор, если он пустой. Создание новой сущности — через статический метод `create(...)`, который принимает обязательные бизнес-параметры, инициализирует все поля и возвращает готовый объект. Это чётко разделяет «создание нового» от «восстановления из БД».
- **Entity без примитивов**: Entity не хранит и не принимает `string`, `int`, `float`, `bool`, `array` или голый `Collection` для доменных данных. Свойства Entity, аргументы `create()`, аргументы доменных методов и значения, которые Entity возвращает наружу, должны быть VO, enum, другой доменной моделью или именованной типизированной коллекцией. Исключение: `bool` разрешён только как return type у чистых predicate-методов без побочных эффектов (`isActive()`, `canLogin()`), если метод отвечает на вопрос и не протаскивает состояние наружу.
- **ValueObject для доменных примитивов**: email, пароль, slug, subdomain, имена, идентификаторы, счётчики, флаги состояния и другие одиночные значения внутри Entity — не голые примитивы, а `readonly class` в `Modules/{Module}/Domain/ValueObject` или enum для закрытого набора вариантов. Общие базовые VO и общие идентификаторы, которые не принадлежат одному модулю, лежат в `Shared/Domain/ValueObject`. Простой скалярный VO: `readonly`, `Stringable`, `JsonSerializable`, приватный конструктор, фабричный метод с валидацией, метод для чтения значения (`value()`, `toString()` или другой явный доменный метод) и `equals()`. Составной VO для JSON-значения может не быть `Stringable`, если у него нет естественного безопасного строкового представления; при этом он всё равно должен быть `readonly`, `JsonSerializable`, создаваться через фабрику, валидировать входные значения и иметь `equals()`. Вспомогательные payload/DTO для JSON не кладём в `Domain/ValueObject`, если они сами не являются полноценными VO. VO не реализует инфраструктурные интерфейсы и не зависит от Cycle ORM. Гидрация и запись VO в БД настраиваются в инфраструктурном typecast-слое: для простых VO общий `ValueObjectCast` может использовать публичные методы VO по соглашению, для сложных VO создаётся отдельный typecast-класс в `Infrastructure`. Entity хранит VO-свойства нативно (`public private(set) Email $email`) с `#[Column(typecast: Email::class)]` или с отдельным typecast-классом, без промежуточных raw-полей. CQRS Command принимает примитивы на внешней границе, Handler создаёт VO до вызова `Entity::create()` или доменных методов Entity.
- **Не заменять типизацию ручными проверками**: если ожидаемый тип известен, указывай его в сигнатуре метода. Не принимай `object`, `mixed` или слишком широкий тип с последующей проверкой `instanceof`. Исключение — границы системы и места, где входные данные действительно имеют неизвестный тип.
- **Валидация значений — только в ValueObject**: Entity не содержит методов валидации (`validateX()`, `mb_strlen()`, `trim()` и т.д.) для доменных значений. Вся валидация (длина, формат, пустота, диапазон, нормализация) инкапсулирована в соответствующем VO. Entity принимает готовый VO в `create()` и доменных методах, без голых примитивов и локальной валидации скаляров.
- **Исключения из ValueObject — это внутренние доменные ошибки (500)**: VO и доменные коллекции бросают `InvalidDomainValueException` (код 500), не `ValidationException` и не `\InvalidArgumentException`. `ValidationException` используется только на HTTP/API-границе, когда нужно вернуть пользователю ожидаемую ошибку 422. `GianTiaga\SpiralApiErrors\Interceptor\ApiExceptionInterceptor` возвращает для `InvalidDomainValueException` обычную 500-ошибку без исходного сообщения исключения в ответе.
- **Типизированные доменные исключения вместо `RuntimeException`**: запрещено использовать голый `\RuntimeException` с HTTP-кодом. Для ожидаемых HTTP-сценариев — свой класс: `NotFoundException` (404), `AuthenticationException` (401), `ForbiddenException` (403), `ValidationException` (422). Такие HTTP-исключения не логируются, а только превращаются в ответ пользователю. Для невалидного доменного значения — `InvalidDomainValueException` (500). Код зашит в конструкторе, передаётся только сообщение.
- **Исключения слоя — в папке `Exception/` этого слоя**: классы-исключения не лежат рядом с логикой, которая их бросает, а собираются в папке `Exception/` своего слоя — `App\Modules\{Module}\{Layer}\Exception` (`Application/Exception`, `Infrastructure/Exception`, `Presentation/Exception`) и `App\Shared\{Layer}\Exception`. Слой исключения определяется контрактом, к которому оно относится, а не местом выброса: `OutboxMessageLoadingException` — часть контракта загрузчика сообщений, поэтому лежит в `Application/Exception`, хотя бросается в `Infrastructure`. Это распространяет на все слои и модули паттерн `Shared/Domain/Exception`.
- **Запрет `assert()`**: `\assert()` запрещён во всём коде проекта. Вместо assert — явная проверка условия с выбросом типизированного исключения. Причина: assert может быть отключён в production через `zend.assertions=-1`, что молча пропускает невалидные данные. В ValueObject публичные фабрики валидируют пользовательский ввод, а восстановление значений из БД выполняет инфраструктурный typecast-слой.

## Архитектура

- **Запрет try-catch в контроллерах, Handler-ах и бизнес-логике**: контроллеры и Handler-ы не ловят исключения — ошибки всплывают до `GianTiaga\SpiralApiErrors\Interceptor\ApiExceptionInterceptor`, который конвертирует их в `ErrorResponse` с корректным HTTP-кодом. `try-catch` допустим только в инфраструктурном коде (interceptor-ы, middleware, Job-обработчики) — там, где исключение ловится на границе системы. В Handler-ах вместо try-catch на `ConstrainException` использовать проверку перед записью (check-then-act) — дублирование при конкурентных запросах допустимо как 500, которую `ApiExceptionInterceptor` обработает. Ожидаемые клиентские ошибки бросать типизированными доменными исключениями (`AuthenticationException`, `ForbiddenException` и т.д.) с 4xx-кодом в `$code`; внутренние нарушения доменных значений — через `InvalidDomainValueException`. Даже в инфраструктурном коде запрещён try-catch, который ловит широкий `\Throwable` и лишь перебрасывает его как другой тип исключения, не добавляя реальной обработки (логирование, fallback, retry, изменение состояния, освобождение ресурса) — пусть исходное исключение всплывает к обработчику на границе. Оборачивать чужое исключение в доменный тип оправдано только тогда, когда это добавляет существенный контекст, нужный потребителю, или требуется контрактом границы; перетипизация без пользы — лишний код. Если же ошибку код обнаруживает сам (guard-clause, проверка предусловия), он сразу бросает конкретное типизированное доменное исключение в источнике, а не generic-заглушку вроде `\UnexpectedValueException`/`\RuntimeException`.
- **Консольные команды — тонкие обёртки**: консольная команда только собирает ввод (аргументы, опции) и делегирует в соответствующий CQRS Command+Handler. Вся бизнес-логика — в Handler, консольная команда не содержит логики кроме маппинга ввода в Command DTO и вывода результата. Формат атрибута `#[AsCommand]` см. в примере консольной команды в `docs/code-examples.md`.
- **`env()` только в конфигах**: вызов `env()` допустим исключительно в файлах `app/config/*.php`. Сервисы, Handler-ы и любой другой код получают значения через типизированные конфиг-объекты (Spiral Config) или через DI. Прямое обращение к `env()` в бизнес-коде — нарушение.
- **Typed config для каждого config-файла**: при добавлении нового `app/config/*.php` сразу создаётся корневой DTO в `App\Shared\Infrastructure\Configuration\<Section>\<Section>Config`, который реализует `TypedConfig`. `configName()` должен совпадать с именем файла без `.php`. Вложенные секции описываются отдельными DTO рядом с корневым классом, а маппинг покрывается тестом через `ConfigMapper`.
- EnvironmentInterface - запрещён нужно использовать конфиги
- **Реквесты через Filter**: входящие данные принимаются через Spiral Filter-классы (`Spiral\Filter\Dto\FilterInterface`). Ассоциативные массивы `array $input` в контроллерах — запрещены. Enum-ы (`TenantStatus`, `TenantPlan` и т.д.) типизируются прямо в Filter-е — Spiral автоматически кастит строку в BackedEnum. Контроллер не делает `::from()`/`::tryFrom()` вручную.
- **Запрет `ServerRequestInterface` в контроллерах**: контроллеры не инжектят `Psr\Http\Message\ServerRequestInterface`. Для тела запроса — Spiral Filter (`#[Post]`, `#[Query]`). Для аутентифицированного пользователя — `#[Attribute(key: 'authUserId')]`, читающий request attribute из JWT middleware. Ключ `authUserId` (не `userId`) во избежание коллизий с параметрами роута. `ServerRequestInterface` допустим только в middleware и interceptor-ах.
- **Filter-ы полностью объектно-ориентированные**: свойства Filter-а — только скалярные типы, Enum-ы и вложенные Filter-ы/DTO. Ассоциативные массивы (`array<string, mixed>`) в Filter-е — запрещены. Для вложенных JSON-объектов создавать отдельный вложенный Filter или DTO (`#[NestedFilter]` / отдельный `readonly class`). Spiral поддерживает вложенность фильтров — использовать её вместо сырых массивов. Плоские типизированные списки (`list<string>`, `list<int>`, `list<UuidString>`) — допустимы, в том числе nullable (`?array` с PHPDoc `@var list<string>|null`).
- **Filter: не указывать `key` если совпадает с именем свойства**: `#[Post]` без `key` — Spiral автоматически берёт имя PHP-свойства. `key` указывать только когда имя в JSON отличается от имени свойства. Поскольку API использует camelCase и PHP-свойства тоже camelCase — `key` практически никогда не нужен.
- **Filter: обязательные свойства без значения по умолчанию**: если свойство Filter-а обязательное — оно объявляется без значения по умолчанию и без nullable. `public string $email;` — не `public string $email = '';`. Spiral заполняет свойства из запроса, валидация через `#[Assert\NotBlank]` гарантирует наличие. Дефолт `= ''` маскирует отсутствие значения. Nullable (`?string $name = null`) и дефолты (`int $limit = 20`) — только для реально опциональных полей. Для файлов: `public UploadedFileInterface $file;` — не nullable.
- **Респонс — типизированный класс**: каждый ответ API — объект с типизированными полями, не ассоциативный массив. Запрещено возвращать `['key' => $value]` напрямую.
- **Запрет ассоциативного массива как структуры (record)**: ассоциативный массив с фиксированным, заранее известным набором именованных ключей, где значения по ключам имеют разный смысл (`['userId' => .., 'type' => .., 'sessionId' => ..]`), — это скрытый DTO и запрещён везде в коде проекта. Заменять на DTO/VO/Response-класс или enum. Признак нарушения (проверяется на ревью): ключи массива записаны строковыми литералами в коде, либо к массиву обращаются по константному ключу (`$x['userId']`). PHPDoc-тип `array<string, string>` (как и любой `array<string, …>`) такой массив НЕ легализует — он лишь маскирует структуру под «карту», поэтому «значения одного типа» сами по себе массив не оправдывают. Разрешён ассоциативный массив только как однородная карта (map): ключ — это данные времени выполнения (идентификатор, ключ кеша, код локали), набор ключей заранее не известен и не ограничен, а все значения имеют один тип и один смысл; типизируется `array<string, T>`, где `T` — не массив, shape или tuple (см. «Именованные типы для сложных данных»). Когда карта становится самостоятельным доменным понятием, которое передаётся между слоями или несёт поведение/инварианты, — оборачивать в именованную типизированную коллекцию; для разовой инфраструктурной карты raw `array<string, T>` допустим. Исключения, где ассоциативный массив неизбежен и нарушением не считается: контекст логгера (`['userId' => $id]`), `jsonSerialize()` и другая сериализация в JSON, конфиги фреймворка (`app/config/*.php` и их config-DTO), а также payload/headers/параметры на самой границе с чужим контрактом — vendor-интерфейсом, Cycle ORM, Spiral Queue, translator, PSR-интерфейсами. На такой границе массив навязан библиотекой; внутрь нашего кода он должен сразу превращаться в VO/DTO/enum.
- **Базовые Response-классы**: для API-ответов использовать `DataResponse<T>` (одиночный объект), `PaginationResponse<T>` (постраничный список), `CollectionResponse<T>` (коллекция без пагинации). Response-классы параметризованы дженерик-типом `T` возвращаемого ресурса.
- **Ресурсы наследуют `AbstractResource`**: все API-ресурсы — `final readonly class` extends `App\Shared\Presentation\Http\Resource\AbstractResource`. Определяют только свойства и `fromEntity()`. Сериализация автоматическая через рефлексию, `jsonSerialize()` вручную не определяется.
- **camelCase везде кроме БД**: все ключи в любых сериализуемых данных — camelCase. Это касается: HTTP request/response JSON, payload очередей (queue jobs), WebSocket-сообщений (Centrifugo), event payload, логгер-контекста, PSR-7 request attributes. Единственное исключение — имена таблиц и колонок в БД (PostgreSQL convention: snake_case), Cycle ORM аннотации для колонок, переменные окружения и ключи конфигов фреймворка. Если ключ попадает в JSON, очередь, лог или передаётся между слоями — он camelCase.
- **Контроллеры возвращают Response напрямую**: методы контроллеров возвращают базовые Response-классы (`DataResponse`, `CollectionResponse`, `PaginationResponse`, `ErrorResponse`) напрямую, без промежуточных обёрток. Return type метода — конкретный Response-класс.
- **PHPDoc `@return` с дженериком на каждом методе контроллера**: если метод возвращает базовый Response-класс, обязателен `@return DataResponse<SuperUserResource>` (или аналогичный) в PHPDoc. Это даёт PHPStan полную информацию о типе ресурса внутри ответа.
- **OpenAPI metadata не обязательна**: `#[OpenApi(id: ..., description: ...)]` используется только для ручного уточнения `operationId` и описания. Если атрибута нет, генератор строит операцию из route name, controller method, PHPDoc, Filter DTO, Response/Resource и enum-ов. Перед релизом запускать `php app.php openapi:generate` и проверять обновление `public/openapi/openapi.yml`.
- **Контроллеры — тонкие обёртки**: контроллер не содержит бизнес-логики и логики выборки. Контроллер только: принимает Filter, вызывает Command Handler (для записи) или Query Handler (для чтения), маппит результат в Resource и оборачивает в Response-класс. Никаких `WHERE`-условий, `if-else`, подсчётов, ручной пагинации — вся логика выборки в Query Handler.
- **CQRS: Command + Query**: запись через Command, чтение через Query; каждая операция — DTO + Handler. Выборку пишем явно в репозиториях, без Data Grid.
- **Репозитории — отдельный слой модуля**: Repository лежит в `Modules/{Module}/Repository`. `Application` своего модуля может использовать свои Repository напрямую через constructor injection. Репозиторий другого модуля использовать нельзя: для связи между модулями обращаться только к `Application` другого модуля.
- **В папке `Repository` — только репозитории, работающие с сущностями**: `Modules/{Module}/Repository` содержит исключительно `*Repository`-классы. Репозиторий принимает критерии запроса и возвращает доменные сущности (`Entity`) и их коллекции; скаляр или enum допустим только как результат запроса состояния (`count`, `exists`, статус). DTO, read-model, типизированные представления сырой строки БД, value object-ы и прочие вспомогательные классы в папке `Repository` запрещены — им место в `Domain`/`Application`/`Infrastructure`. Если строки нужно прочитать в обход ORM-гидрации (например, обработка повреждённых данных), такой код и его типы живут в `Infrastructure`, а не в репозитории.
- **Репозитории без обязательных контрактов**: `RepositoryContract` не создаётся автоматически. Контракт для репозитория добавляется только при реальной причине: несколько реализаций, сложная подмена в тестах или необходимость полностью отвязать сценарий от конкретной ORM.
- **Контракты технических сервисов**: если `Application` нужен технический сервис, контракт лежит в `Modules/{Module}/Application/Contract` и заканчивается на `Contract`. Реализация лежит в `Infrastructure` своего модуля. Пример: `MediaFileServiceContract` и `S3MediaFileService`.
- **Доменные методы в репозиториях**: запрещены generic-вызовы `findByPK()`, `findOne()`, `select()` в Handler-ах и другом бизнес-коде. Репозиторий предоставляет конкретные доменные методы: `findByEmail()`, `findByTokenHash()`, `findLatestByUserId()`. Базовые методы Repository (`findOne`, `findByPK`) используются только внутри самого Repository для реализации доменных методов.
- **Без лишнего `instanceof` в репозиториях**: если репозиторий наследуется от `Cycle\ORM\Select\Repository` с PHPDoc `@extends Repository<Entity>`, методы Cycle (`findByPK()`, `findOne()`, `findAll()`, `select()->fetchOne()`) уже типизируются как эта Entity. Не писать `return $entity instanceof Entity ? $entity : null;` после таких вызовов — возвращать результат напрямую.
- **Параметры методов Repository только про запрос**: методы Repository принимают только критерии выборки, сортировки, пагинации или блокировки, относящиеся к конкретному запросу. Нельзя передавать в методы Repository технические зависимости вроде `DatabaseInterface`, `Select`, `EntityManagerInterface`, config, logger или service-объекты. Такие зависимости передаются через constructor injection и остаются внутренней деталью Repository.
- **Выборки в Repository через ORM Select**: внутри Repository для чтения сущностей использовать `$this->select()` с доменными методами Repository. Не строить выборку сущностей через `$database->select()->from('table_name')`, если то же можно выразить через ORM Select. Query Builder допустим только для операций, которые ORM Select не умеет выразить, и причина должна быть понятна из кода.
- **Репозиторий — только чтение (read-only)**: Repository только читает данные — через ORM `$this->select()` и базовые `findByPK()`/`findOne()`/`findAll()`. Репозиторий не сохраняет и не изменяет данные: запрещены методы-мутаторы `save()`, `persist()`, `store()`, `create()`, `update()`, `delete()`, а также инъекция `EntityManagerInterface` в репозиторий. Прямые `$database->update()`, `$database->insert()`, `$database->delete()` тоже запрещены — даже атомарный CAS-переход по статусу. Любое изменение состояния выражается доменным методом Entity (`markQueued()`, `markFailed()` и т.д.) и сохраняется вызовом `$this->entityManager->persist($entity)` + `$this->entityManager->run()` в Handler-е или инфраструктурном orchestrator-е, который вызывает репозиторий только для выборки. Правило rules.md «Запрет SQL текстом» отвечает на вопрос *как* писать запрос (query builder, не сырая строка), но не разрешает репозиторию модифицировать данные. Санкционированное исключение для массовой записи без доменных инвариантов — отдельный класс с маркером `App\Shared\Infrastructure\Database\SetBasedWrite` (см. правило «Set-based запись — только через маркер `SetBasedWrite`»); сам репозиторий остаётся read-only в любом случае.
- **Set-based запись — только через маркер `SetBasedWrite` и только при доказанной необходимости**: прямая массовая запись (`update()`/`insert()`/`delete()` на `Cycle\Database\DatabaseInterface`) по умолчанию запрещена и механически блокируется правилом `DisallowSetBasedWriteRule` — обычное изменение состояния идёт через доменный метод Entity + `EntityManager`. Исключение разрешается, только когда выполнены **оба** блока условий.
  - **Необходимость (хотя бы один пункт):** (1) число затрагиваемых строк не ограничено доменом сверху и растёт с данными или активностью пользователя (mark-all-read, удаление/архивация старых записей, сброс по типу) — загрузка всего набора как Entity даёт неограниченную память и время; (2) операция на горячем пути и вызывается так часто, что стоимость гидрации сущностей значима на масштабе; (3) это чистый «штамп» одной колонки/статуса по набору (флаг, таймстемп, счётчик), где загруженные сущности всё равно не используются. Числовой порог намеренно не задаётся: критерий — «набор не ограничен сверху», а не «больше N».
  - **Безопасность (все пункты обязательны):** у строки нет доменного инварианта, валидации или ветвления, которые должны отработать при записи; переход идемпотентный и тривиальный; затронутые строки не читаются и не используются как живые Entity в этом же запросе.
  - **Запрещено, даже если соблазнительно:** одиночная сущность; набор, заведомо ограниченный доменом небольшим числом; наличие per-row инварианта/проверки/вычисляемых полей (тогда — доменный путь, при необходимости порциями); чтение затронутых строк обратно в том же запросе; мотив «так меньше кода / быстрее писать».
  - Каждый метод set-based writer-а — именованная доменная операция (`markAllReadForRecipient`), а не generic `update(table, set, where)`; writer-классы держатся маленькими и узкими, каждый метод покрыт тестом. Маркер реализуется на классе-реализации в `Infrastructure`, а Application обращается к нему через `*Contract`. Блок «безопасность» PHPStan не проверяет — это зона ревью и докблока.
- **Не обходить ORM ради «свежего» чтения**: все изменения проходят через Entity и общий identity map ORM, поэтому Entity в памяти и есть актуальное состояние. Запрещено добавлять сырые `$database->select()` «чтобы перечитать свежий статус мимо identity map» — читать саму Entity или доменный метод репозитория на ORM Select. Если в sync-сценарии другой обработчик меняет состояние в том же процессе, он меняет ту же Entity, и она уже актуальна. Потребность в read мимо гидрации почти всегда сигнал, что изменение должно было пройти через Entity.
- **Запрет транзакций внутри Repository**: Repository не открывает, не коммитит и не откатывает транзакции (`transaction()`, `begin()`, `commit()`, `rollback()`). Граница транзакции находится в Handler-е, Application-сценарии или инфраструктурном orchestrator-е, который вызывает Repository. Repository отвечает только за конкретные запросы (чтение).
- **Доступ к БД только через репозитории**: прямые запросы к базе данных (`$database->query()`, `$database->table()`, `$orm->getRepository()`, инлайн `Select`, raw SQL) запрещены в Handler-ах, контроллерах, сервисах и любом бизнес-коде. Весь доступ к данным — исключительно через методы Repository-классов своего модуля. Это обеспечивает единую точку доступа к данным и предотвращает дублирование запросов. Исключение — миграции и инфраструктурный код (console-команды для обслуживания БД).
- **Запрет SQL текстом**: ручные SQL-строки в `$database->query()` и `$database->execute()` запрещены в коде приложения и тестах. Для выборки, вставки, обновления и удаления всегда использовать Cycle ORM Select или Cycle Database Query Builder (`select()`, `insert()`, `update()`, `delete()`). Исключение — миграции, если это невозможно выразить через schema/query builder и причина явно обоснована рядом с кодом.
- **Формат даты для БД через общий контракт**: если дату нужно явно форматировать для query builder или другого запроса к БД, формат нельзя писать строкой прямо в вызове `format()` и нельзя заводить локальную константу в отдельном репозитории. Используй общий `App\Shared\Infrastructure\Database\DatabaseDateTimeFormat`, чтобы формат был единым контрактом хранения.
- **Typecast VO: конвенция или отдельный класс**: для non-nullable VO, отображаемого на скаляр (id, тип, счётчик, enum), достаточно общего `App\Shared\Infrastructure\Cycle\ValueObjectCast` по соглашению — cast из БД через `fromString(string)`/`fromInt(int)`/`BackedEnum::from()`, запись через `value()` (скаляр или `DateTimeInterface`). Отдельный `ColumnValueTypecast`-класс в `Modules/{Module}/Infrastructure/Cycle` (статические `castDatabaseValue()`/`uncastValue()`) нужен только для случаев, которые конвенция не выражает: nullable-колонка при non-null VO-свойстве (null-object `none()`/`permanent()` — иначе `NULL` превратится в `null` и сломает типизированное свойство), дата/время (нужна фабрика `fromDateTime`), JSON или коллекция (`fromJson`/`json_encode` вместо `fromString`/`value()`).
- **Без pass-through typecast-обёрток**: не создавать per-module typecast-класс, который реализует `Castable`/`UncastableInterface` и только делегирует в `ValueObjectCast` без своей логики. Entity ссылается на общий движок напрямую: `typecast: [Typecast::class, ValueObjectCast::class]`. Отдельный класс заводится только при наличии собственной логики преобразования (см. предыдущее правило).
- **Stateful typecast — не синглтон**: `ValueObjectCast` хранит правила (`$rules`) для конкретной роли Entity, поэтому stateful; Cycle создаёт по одному обработчику на роль через `factory->make()`. Его (и любой stateful typecast) нельзя помечать `#[Singleton]` или биндить общим синглтоном в контейнере — иначе правила разных сущностей смешаются.
- **Логирование: DEBUG по умолчанию**: бизнес-логика логирует на уровне DEBUG. INFO — только для ключевых бизнес-событий (успешная регистрация, успешный сброс пароля). WARN — реальные проблемы инфраструктуры. ERROR — сбои, нарушения инвариантов. Неверный пароль, истёкший код, невалидный токен — это нормальный flow пользователя → DEBUG, не WARNING.
- **Centrifugo через свой HTTP-клиент**: для взаимодействия с Centrifugo используется клиент в `Modules/{Module}/Infrastructure/Centrifugo` или в `Shared/Infrastructure`, если он нужен нескольким модулям (прямые curl-запросы к HTTP API). Пакет `centrifugal/phpcent` удалён (deprecated `curl_close()` на PHP 8.5). Сервис `CentrifugoService` — обёртка над `CentrifugoClient`.
- **Внешние события и отложенные шаги через outbox**: события, которые вызывают внешние побочные эффекты (Centrifugo, email, push, webhooks), сохраняются как DTO в transactional outbox в той же транзакции, что и бизнес-изменение. Handler-ы и Spiral event listener-ы не вызывают такие интеграции напрямую. Публикация выполняется отдельным relay-процессом после commit-а с повторами и статусами доставки. Тот же механизм применяется и к внутренним отложенным шагам, которые должны гарантированно выполниться после commit-а бизнес-транзакции, даже если их побочный эффект остаётся внутри системы (например, асинхронная обработка загруженного медиа: `MediaUploaded` кладётся в outbox в одной транзакции с переходом медиа в `uploaded`, а relay запускает обработку). Критерий — нужна надёжная доставка «после commit», а не природа эффекта (внешний или внутренний).
- **Outbox relay запускается в одном экземпляре**: текущий relay использует `FOR UPDATE` без `SKIP LOCKED`, потому что ручной SQL запрещён, а Cycle ORM Select не даёт отдельного API для `SKIP LOCKED`. До отдельного решения по безопасному `SKIP LOCKED` нельзя запускать несколько постоянных `outbox:relay --loop` одновременно.
- **Запрет yii-error-handler-bridge**: пакет `spiral-packages/yii-error-handler-bridge` удалён — HTML/XML рендеры ошибок не нужны в API-only проекте. Ошибки обрабатываются через `GianTiaga\SpiralApiErrors\Interceptor\ApiExceptionInterceptor`.
- **`entityManager->run()` вызывается в Handler-е**: каждый Handler сам вызывает `$this->entityManager->persist()` / `$this->entityManager->delete()` и затем `$this->entityManager->run()` для flush. Транзакцию вокруг dispatch включает только `#[Transactional]` на `Handler::handle()`. Если атрибута нет, dispatch выполняется без транзакции. `#[NonTransactional]` не используется. `run()` остаётся внутри Handler-а, чтобы сценарий явно управлял моментом flush. Исключение — технический `outbox:relay`: это инфраструктурный orchestrator после commit-а бизнес-транзакции, поэтому он сам фиксирует claim, publish-failure и queue-status переходы.
- **Queue push по FQCN класса**: задачи пушатся в очередь по имени класса Job (`SendEmailVerificationCodeJob::class`). Промежуточные enum-ы для имён задач — лишняя прослойка. Spiral Queue маршрутизирует по имени класса напрямую.
- **Filter-ы живут в модуле**: Filter-классы — часть HTTP-слоя (парсинг HTTP-запросов), не доменной логики. Размещать в `Modules/{Module}/Presentation/Http/Filter/{Area}`. Например: `App\Modules\Auth\Presentation\Http\Filter\LoginFilter`, `App\Modules\User\Presentation\Http\Filter\UpdateUserProfileFilter`. Папка `App\Filter` запрещена.
- **View-шаблоны живут в модуле**: шаблоны представления модуля (twig: письма, Swagger UI, любые рендеримые view) — часть Presentation-слоя и лежат в `Modules/{Module}/Presentation/views/`. Каждый модуль регистрирует свою папку шаблонов как namespace = имя модуля в нижнем регистре через `ViewsBootloader::addDirectory(...)` в bootloader-е модуля (путь к папке строится от `__DIR__`), а ссылка на шаблон использует namespace-форму `{module}:<имя>` — например `auth:login-code`, `system:swagger/index`. Общая папка `app/views` (default namespace) для шаблонов модулей не используется. Это контраст с двумя сущностями, которые осознанно остаются глобальными: config-файлы (`app/config/*.php` + typed DTO в `Shared/Infrastructure/Configuration`, см. «Typed config для каждого config-файла» и `arch.md`) и миграции (`app/database/migrations` — единая линейная история схемы, часто кросс-модульная). Шаблон — живой ассет модуля, рендерится в рантайме и переезжает вместе с модулем; конфиг и миграция — нет.
- **CQRS: группировка по действию**: внутри `Modules/{Module}/Application/Command/` и `Modules/{Module}/Application/Query/` каждое действие выносится в отдельную подпапку. Папка называется по действию (без суффикса Command/Query). Пока в модуле одна область (Area), подпапка действия лежит прямо в корне `Command/`/`Query/`; уровень области `{Area}` добавляется только когда областей становится несколько. Пример: `Modules/Auth/Application/Command/Login/LoginCommand.php`, `LoginHandler.php`, `LoginResult.php`. Это даёт чёткую изоляцию: все файлы одного use-case лежат рядом. Namespace соответствует: `App\Modules\Auth\Application\Command\Login`.
- **CQRS: полное имя действия в имени класса**: имя Command/Query/Handler/Filter должно содержать полный контекст действия, включая доменную сущность, даже если namespace уже содержит домен. Пример: `UpdateUserProfileCommand` (не `UpdateProfileCommand`), `GetUserProfileQuery` (не `GetProfileQuery`), `UpdateUserProfileFilter` (не `UpdateProfileFilter`). Папка действия совпадает: `Application/Command/UpdateUserProfile/`, `Application/Query/GetUserProfile/`. Это делает класс самодокументируемым — по имени сразу понятно, что он делает, без заглядывания в namespace.


## Типовые контракты

- **Без неявного `mixed`**: generic-параметры указываются явно. Нельзя писать `array`, если контракт на самом деле ожидает `list<Foo>` или `array<int, Foo>`.
- **Без сложных массивов в PHPDoc**: вложенные массивы, tuple-типы и array shapes запрещены, например `array<string, array<int, string>>` и `array{foo: string}`.
- **Без явного `mixed`**: `mixed` не используется в проектных контрактах, включая параметры, return type, PHPDoc generic-и и callbacks. Неизвестное значение нужно сузить на границе системы и дальше передавать именованный DTO/VO/enum, конкретный union type или типизированную коллекцию.
- **Именованные типы для сложных данных**: вместо сложных generic-контейнеров используйте DTO/value object, enum или коллекции. Допустимы простые `list<T>` и `array<int|string, T>`, где `T` не является массивом, shape или tuple.

## Тестирование
- 100 процентное покрытие тестами
- Каждый роут должен быть покрыт интеграционным тестом
- **Стаб вместо `expects()` для дублёров без проверки вызова**: если дублёр нужен только чтобы вернуть данные (не для проверки факта/числа вызовов), используй `createStub()` + `willReturn*()`, а не `createMock()` с `->method()` без `expects()` и не `->expects($this->any())`. В PHPUnit 13 `with()`/`method()` без `expects()` деприкейтнут, а `expects($this->any())` тоже деприкейтнут (удаляется в PHPUnit 14). Для подбора возврата по аргументу — `willReturnMap([[ $arg, $value ]])`. Важный нюанс: при несовпадении аргумента `willReturnMap` возвращает дефолт по типу возврата метода (для `: array` это `[]`, не исключение), поэтому стаб сам по себе не проверяет аргумент. Для дублёров конфигуратора (`getConfig(): array`) это допустимо, пока у целевого DTO есть обязательные поля — маппинг пустого `[]` падает и косвенно ловит «не ту секцию». Если добавляешь конфиг, у которого все поля опциональны/с дефолтами, заведи отдельный позитивный тест на «ожидаемая секция запрошена» (например `willReturnCallback`, бросающий при несовпадении), иначе неверная секция пройдёт молча.

## Проверки

- **Тесты приложения**: локальная проверка запускается командой `make test`.
- **PHPStan приложения**: локальная проверка запускается командой `make phpstan`.
- **PHPStan tooling**: живёт отдельным Composer-пакетом в `packages/phpstan-strict-rules`. Его тесты и fixtures должны оставаться внутри этого пакета, а не в корневом `tests/`.
- **Packages-пакеты изолированы**: каждый пакет в `packages/*` является отдельным Composer-пакетом со своим `composer.json`, локальным `vendor/`, package-local `bootstrap.php`, `phpunit.xml` и `phpstan.neon`. Bootstrap и Composer scripts внутри `packages/*` не должны ссылаться на корневой `../../vendor` или `../../vendor/bin`. Для проверки пакета используется `composer -d packages/<package> install`, затем `composer -d packages/<package> test` и `composer -d packages/<package> phpstan`.

exec
/bin/zsh -lc "sed -n '1,260p' app/src/Modules/Media/Domain/ValueObject/MediaPath.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\ValueObject;

use App\Modules\Media\Domain\Enum\MediaAudioConversionType;
use App\Modules\Media\Domain\Enum\MediaImageConversionType;
use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\Enum\MediaVideoConversionType;
use App\Shared\Domain\Exception\InvalidDomainValueException;
use Ramsey\Uuid\Uuid;

final readonly class MediaPath implements \Stringable, \JsonSerializable
{
    private const int MAX_LENGTH = 1024;

    private function __construct(
        private string $value,
    ) {}

    public static function originalUpload(MediaStorageKey $storageKey, string $extension): self
    {
        return self::fromString(
            \sprintf('uploads/%s/%s/source.%s', $storageKey->shard(), $storageKey, self::sanitizeExtension($extension)),
        );
    }

    public static function imageConversion(
        MediaStorageKey $storageKey,
        MediaImageConversionType $type,
        string $extension,
    ): self {
        return self::fromString(
            \sprintf(
                'images/%s/%s/%s.%s',
                $storageKey->shard(),
                $storageKey,
                $type->value,
                self::sanitizeExtension($extension),
            ),
        );
    }

    public static function videoConversion(
        MediaStorageKey $storageKey,
        MediaVideoConversionType $type,
        string $extension,
    ): self {
        return self::fromString(
            \sprintf(
                'videos/%s/%s/%s.%s',
                $storageKey->shard(),
                $storageKey,
                $type->value,
                self::sanitizeExtension($extension),
            ),
        );
    }

    public static function audioConversion(
        MediaStorageKey $storageKey,
        MediaAudioConversionType $type,
        string $extension,
    ): self {
        return self::fromString(
            \sprintf(
                'audios/%s/%s/%s.%s',
                $storageKey->shard(),
                $storageKey,
                $type->value,
                self::sanitizeExtension($extension),
            ),
        );
    }

    public static function originalReady(MediaStorageKey $storageKey, MediaType $type, string $extension): self
    {
        $prefix = match ($type) {
            MediaType::Image => 'images',
            MediaType::Video => 'videos',
            MediaType::Audio => 'audios',
            MediaType::Document => throw new InvalidDomainValueException(
                'Перекладка готового оригинала для документов не поддержана.',
            ),
        };

        return self::fromString(
            \sprintf('%s/%s/%s/source.%s', $prefix, $storageKey->shard(), $storageKey, self::sanitizeExtension($extension)),
        );
    }

    public static function fromString(string $value): self
    {
        $value = \trim($value);
        self::assertValid($value);

        return new self(value: $value);
    }

    public function value(): string
    {
        return $this->value;
    }

    /**
     * Расширение файла из последнего сегмента пути (без точки). Используется для построения
     * путей конверсий и готового оригинала с тем же форматом, что у загруженного файла.
     */
    public function extension(): string
    {
        $segments = \explode(separator: '/', string: $this->value);
        $fileName = (string) \end($segments);
        $dotPosition = \strrpos(haystack: $fileName, needle: '.');

        return $dotPosition === false ? '' : \substr(string: $fileName, offset: $dotPosition + 1);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->value;
    }

    #[\Override]
    public function jsonSerialize(): string
    {
        return $this->value;
    }

    private static function sanitizeExtension(string $extension): string
    {
        $safeExtension = \strtolower(\trim($extension));

        if ($safeExtension === '' || \preg_match(pattern: '/^[a-z0-9]+$/', subject: $safeExtension) !== 1) {
            throw new InvalidDomainValueException('Расширение файла имеет неверный формат.');
        }

        return $safeExtension;
    }

    private static function assertValid(string $value): void
    {
        if (\strlen($value) > self::MAX_LENGTH) {
            throw new InvalidDomainValueException('Путь файла слишком длинный.');
        }

        $matches = [];
        if (\preg_match(
            pattern: '/^(uploads|images|videos|audios)\/([0-9a-f]{2})\/([0-9a-f-]{36})\/([^\/]+)$/',
            subject: $value,
            matches: $matches,
        ) !== 1) {
            throw new InvalidDomainValueException('Путь файла имеет неверный формат.');
        }

        if (!Uuid::isValid($matches[3]) || $matches[2] !== \substr(string: $matches[3], offset: 0, length: 2)) {
            throw new InvalidDomainValueException('Путь файла имеет неверный раздел.');
        }
    }
}

exec
/bin/zsh -lc "sed -n '1,240p' docs/researches/2026-06-22_17-22_media-document-support.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
---
title: Поддержка документов в модуле Media
date: 2026-06-22 17:22
mode: strict
decision_mode: recommend_and_ask
status: draft
reviewer: pending
sources:
  rules: docs/rules.md
  arch: docs/arch.md
---

# Поддержка документов в модуле Media

## Суть

В модуле `Media` нужно включить загрузку и выдачу документов (PDF, DOC, DOCX, DJVU,
TXT и другие популярные форматы). Сейчас `MediaType::Document` объявлен в enum, но это
заглушка: документы сознательно отклоняются во всех точках пайплайна. Задача — снять эту
заглушку так, чтобы документ можно было загрузить, переложить в постоянное хранилище и
отдать по URL, не ломая уже работающий поток image/video/audio.

Что именно отклоняется сейчас (точки проверены по коду):

```text
MediaTypeResolver::resolve()           app/.../Application/Service/MediaTypeResolver.php:22-37
  — распознаёт только image/*, video/*, audio/*; остальное → 422 unsupported_file_type
MediaPath::assertValid() (regex)       app/.../Domain/ValueObject/MediaPath.php:154
  — путь допускает лишь префиксы uploads|images|videos|audios
MediaPath::originalReady()             app/.../Domain/ValueObject/MediaPath.php:83
  — MediaType::Document => throw InvalidDomainValueException
ProcessMediaHandler::handle()          app/.../Application/Command/ProcessMedia/ProcessMediaHandler.php:90
  — MediaType::Document => throw «Обработка документов не поддержана»
CompleteMediaUploadHandler             app/.../Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php:123
  — MediaType::Document => throw ValidationException в assertPlanValid()
```

## Решение

### Что выбрано

- **Поведение**: документ только хранится. После подтверждения загрузки оригинал
  перекладывается в постоянный бакет по `visibility` и отдаётся через `GetMediaUrl`.
  Конверсий, превью первой страницы и извлечения метаданных нет.
- **Распознавание типа**: явный whitelist MIME-типов документов в `MediaTypeResolver`.
  Неизвестный `application/*` по-прежнему отклоняется как 422.
- **Набор форматов**: все четыре согласованные категории (полный список ниже).

Документ в этой модели — это «image без конверсий»: тот же путь
`waitingUpload → uploaded → перекладка оригинала → ready`, но `MediaConversionPlan`
полностью пустой, и обработчик ничего не транскодирует.

### Почему так, а не иначе

| Развилка | Выбор | Почему |
|---|---|---|
| Глубина обработки | Только хранение | Совпадает с природой Media («храним и отдаём URL»). Превью потребовало бы Ghostscript + LibreOffice headless в образе, новую таблицу конверсий, новый процессор и контракт — отдельная большая задача. Превью можно добавить позже по образцу видео-постера. |
| Распознавание | Явный whitelist MIME | Сохраняет «намеренно сужающую» философию резолвера: модуль принимает только то, что знает. Предсказуемо и тестируемо. Альтернатива «всё, что не image/video/audio — документ» приняла бы любой `application/octet-stream`. |
| Набор форматов | Все 4 категории | Покрывает названные пользователем PDF/DOC/DOCX/DJVU/TXT и ходовые офисные/книжные форматы. |

### Архитектура: какие слои затронуты

Доменная и архитектурная модель НЕ меняется — нет новых Entity, контрактов, репозиториев,
зависимостей, миграций, конфигов, очередей или outbox-сообщений. Меняются ровно те 5 точек,
где `MediaType::Document` сейчас бросает исключение. Поток данных остаётся прежним:

```text
RequestMediaUpload  -> presigned PUT, media = waitingUpload   (тип резолвится в Document)
   клиент PUT-ит байты в staging-бакет
CompleteMediaUpload -> headObject, media = uploaded, MediaUploaded в outbox  (план пустой)
outbox:relay -> ProcessMediaJob -> ProcessMedia
   match(type): Document -> конверсий нет ([]) -> перекладка оригинала -> ready
GetMediaUrl -> public: прямой URL; private: presignGet
```

Точки правок:

| Точка | Сейчас | Станет |
|---|---|---|
| `MediaTypeResolver::resolve()` | 3 префиксных правила, остальное 422 | whitelist MIME → `Document` проверяется **первым**, затем префиксы image/video/audio, затем 422 |
| `MediaPath::assertValid()` regex | `(uploads\|images\|videos\|audios)` | добавить префикс `documents` |
| `MediaPath::originalReady()` | `Document => throw` | `Document => 'documents'` |
| `ProcessMediaHandler::handle()` match | `Document => throw` | `Document => []` (пустой набор конверсий, дальше штатная перекладка `persistReady`) |
| `CompleteMediaUploadHandler::assertPlanValid()` | `Document => throw` | `Document =>` проверка, что `image`/`video`/`audio` пусты; иначе 422 `conversion_plan_type_mismatch` |

`originalUpload()` не трогаем: staging-путь `uploads/...` от типа не зависит.
`MediaConversionPlan` и `MediaUploaded` не трогаем: для документа все три списка пусты.
Перевод `app.media.conversion_plan_type_mismatch` уже есть (`app/locale/ru/media.php:18`) —
подходит для непустого плана у документа; `unsupported_file_type` остаётся для реально
неизвестных MIME.

### Whitelist MIME → расширение (формат)

`MediaPath::sanitizeExtension()` допускает `[a-z0-9]+` — все расширения ниже проходят.

```text
Базовые текстовые
  application/pdf                                                              -> pdf
  application/msword                                                           -> doc
  application/vnd.openxmlformats-officedocument.wordprocessingml.document      -> docx
  text/plain                                                                   -> txt
  application/rtf, text/rtf                                                    -> rtf   (встречаются оба MIME)
OpenDocument
  application/vnd.oasis.opendocument.text                                      -> odt
  application/vnd.oasis.opendocument.spreadsheet                               -> ods
  application/vnd.oasis.opendocument.presentation                              -> odp
MS Office таблицы/презентации
  application/vnd.ms-excel                                                     -> xls
  application/vnd.openxmlformats-officedocument.spreadsheetml.sheet            -> xlsx
  application/vnd.ms-powerpoint                                                -> ppt
  application/vnd.openxmlformats-officedocument.presentationml.presentation    -> pptx
Книги и сканы
  application/epub+zip                                                         -> epub
  application/x-fictionbook+xml                                                -> fb2   (MIME не стандартизирован IANA)
  image/vnd.djvu                                                               -> djvu  (внимание: префикс image/)
  text/csv                                                                     -> csv
  text/markdown                                                                -> md
```

### Риски — закрытие рядом с решением

- **DJVU ломает порядок проверок в резолвере.** MIME DJVU — `image/vnd.djvu`, он начинается
  с `image/`. Текущий резолвер проверяет `str_starts_with('image/')` первым
  (`MediaTypeResolver.php:22`), поэтому без изменений DJVU стал бы `Image` и пайплайн отправил
  бы его в Imagick-ресайз. **Снимаем**: whitelist документов (точное сравнение MIME)
  проверяется ДО префиксных правил. Это обязательное требование к реализации резолвера.
- **Модуль доверяет заявленному MIME и не проверяет содержимое.** Клиент PUT-ит байты прямо в
  S3, MIME приходит из `MediaFileMeta`. Это свойство существующей архитектуры (так же для
  image/video/audio). Для документов добавляется поверхность (вредоносный PDF/макросы в
  docx), но Media документ не парсит и не исполняет — только хранит и отдаёт.
  **Принимаем** в рамках текущего дизайна; антивирус/проверка magic bytes — отдельная задача
  вне этих рамок. Потребитель дополнительно сужает набор через `MediaUploadSpec.allowedMimeTypes`.
- **MIME FB2 не стандартизирован.** `application/x-fictionbook+xml` — самый частый, но
  встречаются `application/fb2` и просто XML. **Снимаем выбором**: в whitelist берём
  `application/x-fictionbook+xml`; если потребитель присылает другой MIME для FB2 — это его
  ответственность через `allowedMimeTypes`. Дополнительные варианты можно добавить точечно.
- **CSV/RTF имеют несколько MIME.** CSV часто `text/csv`, но Excel помечает его иначе; RTF —
  `application/rtf` и `text/rtf`. **Снимаем**: для RTF включаем оба; для CSV берём `text/csv`
  как канонический, прочие — на усмотрение потребителя.
- **Осиротевшие staging-объекты** — без изменений: документ, как и другие типы, чистится
  staging-expiry задачей; терминальные сироты — на будущий storage-sweep.

### Версии ПО

Проверка актуальных версий пакетов/ПО **не требуется**: выбран вариант «только хранение»,
он не вводит новых зависимостей (Composer-пакетов, бинарей, системных пакетов). Imagick и
ffmpeg для документов не задействуются; Ghostscript/LibreOffice не нужны, потому что превью
отклонено. Используются только уже имеющиеся S3-операции (`copyObject`, `headObject`,
`presignPut`, `presignGet`).

## Ответы на вопросы

| Вопрос (развилка) | Ответ пользователя |
|---|---|
| Что Media делает с документом после загрузки? | **Только хранить оригинал** (без превью и без метаданных) |
| Как определять, что файл — документ? | **Явный список MIME-типов** (whitelist) |
| Какие форматы включить? | **Все четыре категории**: базовые текстовые (PDF, DOC, DOCX, TXT, RTF), OpenDocument (ODT, ODS, ODP), MS Office таблицы/презентации (XLS, XLSX, PPT, PPTX), книги и сканы (EPUB, FB2, DJVU, CSV, MD) |

## Итог

Подход: снять заглушку `MediaType::Document` в 5 уже найденных точках, не вводя новых
сущностей, зависимостей, миграций и конфигов. Документ обрабатывается как «медиа без
конверсий»: резолвер классифицирует его по whitelist MIME (с приоритетом над префиксом
`image/` ради DJVU), `MediaPath` получает префикс `documents`, `ProcessMediaHandler`
возвращает пустой набор конверсий и делает штатную перекладку оригинала в ready, а
`CompleteMediaUpload` требует, чтобы план конверсий для документа был пустым. Полный whitelist
MIME → расширение зафиксирован выше. Тесты: дополнить `MediaTypeResolverTest` (включая
DJVU → Document, а не Image), `MediaValueObjectTest` (путь `documents`), `ProcessMediaHandlerTest`
(перекладка без конверсий), `CompleteMediaUploadHandlerTest` (пустой план для документа),
`RequestMediaUploadHandlerTest` (запрос загрузки документа) — требуется 100% покрытие.
Следующий шаг — `eda-plan` для пошаговой реализации.
</content>
</invoke>

codex
Первое чтение подтверждает общую картину: документы действительно сейчас объявлены, но несколько мест их отклоняют. Дочитываю обработчики и enum, потому что там важны детали про пустой план и порядок переходов состояния.
exec
/bin/zsh -lc "sed -n '1,280p' app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc "sed -n '1,260p' app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Command\CompleteMediaUpload;

use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Media\Application\Dto\MediaConversionPlan;
use App\Modules\Media\Application\Dto\MediaImageConversionSpec;
use App\Modules\Media\Application\Dto\MediaResult;
use App\Modules\Media\Application\Message\MediaUploaded;
use App\Modules\Media\Domain\Collection\MediaMultipartPartCollection;
use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Enum\MediaStatus;
use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\ValueObject\MediaBitrate;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Modules\Media\Domain\ValueObject\MediaPixelDimension;
use App\Modules\Media\Domain\ValueObject\MediaSampleRate;
use App\Modules\Media\Domain\ValueObject\MediaWaveformPeakCount;
use App\Modules\Media\Repository\MediaMultipartUploadRepository;
use App\Modules\Media\Repository\MediaRepository;
use App\Modules\Outbox\Application\Contract\OutboxEventStoreContract;
use App\Shared\Domain\Exception\ForbiddenException;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\Exception\ValidationException;
use App\Shared\Domain\ValueObject\UserId;
use Cycle\ORM\EntityManagerInterface;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;
use GianTiaga\SpiralCqrs\Attribute\Transactional;
use Illuminate\Support\Collection;
use Psr\Log\LoggerInterface;

final readonly class CompleteMediaUploadHandler
{
    public function __construct(
        private MediaRepository $mediaRepository,
        private MediaMultipartUploadRepository $mediaMultipartUploadRepository,
        private MediaFileServiceContract $mediaFileService,
        private OutboxEventStoreContract $outboxEventStore,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}

    #[Transactional]
    #[LogOperation]
    public function handle(CompleteMediaUploadCommand $command): MediaResult
    {
        $media = $this->mediaRepository->findById(MediaId::fromString($command->mediaId))
            ?? throw new NotFoundException('app.media.not_found');

        if (!$media->uploadedById->equals(UserId::fromString($command->userId))) {
            throw new ForbiddenException('app.media.access_denied');
        }

        if ($media->status !== MediaStatus::WaitingUpload) {
            throw new ValidationException('app.media.upload_not_pending');
        }

        $this->assertPlanValid(plan: $command->plan, type: $media->type);

        if ($command->parts !== null) {
            $this->completeMultipartUpload(media: $media, parts: $command->parts);
        }

        $this->assertObjectUploaded($media);

        $media->markUploaded();
        $this->outboxEventStore->add(new MediaUploaded(
            mediaId: $media->id->value(),
            plan: $command->plan,
        ));
        $this->entityManager->persist($media);
        $this->entityManager->run();

        $this->logger->debug(message: 'Загрузка медиа подтверждена.', context: [
            'mediaId' => $media->id->value(),
            'userId' => $command->userId,
            'imageConversions' => \count($command->plan->image),
            'videoConversions' => \count($command->plan->video),
            'audioConversions' => \count($command->plan->audio),
        ]);

        return MediaResult::fromEntity($media);
    }

    private function completeMultipartUpload(Media $media, MediaMultipartPartCollection $parts): void
    {
        $multipartUpload = $this->mediaMultipartUploadRepository->findByMediaId($media->id)
            ?? throw new ValidationException('app.media.multipart_upload_not_found');

        $multipartUpload->replaceParts($parts);
        $this->entityManager->persist($multipartUpload);
        $this->mediaFileService->completeMultipartUpload(
            storage: $media->storage,
            path: $media->path,
            uploadId: $multipartUpload->uploadId,
            parts: $parts,
        );
    }

    private function assertObjectUploaded(Media $media): void
    {
        $objectHead = $this->mediaFileService->headObject(storage: $media->storage, path: $media->path);

        if ($objectHead === null || $objectHead->contentLength->value() !== $media->size->value()) {
            throw new ValidationException('app.media.uploaded_object_mismatch');
        }
    }

    /**
     * Валидирует ТОЛЬКО список, относящийся к типу медиа; список «не своего» типа должен быть
     * пустым (кросс-тип → 422). Диапазоны проверяются через контракт VO ::supports() на
     * Application-границе, чтобы невалидная спека не прошла подтверждение и не упала асинхронно в
     * ProcessMedia (после S3-записей и unique-конфликта).
     */
    private function assertPlanValid(MediaConversionPlan $plan, MediaType $type): void
    {
        match ($type) {
            MediaType::Image => $this->assertImagePlan($plan),
            MediaType::Video => $this->assertVideoPlan($plan),
            MediaType::Audio => $this->assertAudioPlan($plan),
            MediaType::Document => throw new ValidationException(
                translationKey: 'app.media.unsupported_file_type',
                translationParameters: ['type' => $type->value],
            ),
        };
    }

    private function assertImagePlan(MediaConversionPlan $plan): void
    {
        if ($plan->video !== [] || $plan->audio !== []) {
            throw new ValidationException('app.media.conversion_plan_type_mismatch');
        }

        $types = Collection::make($plan->image)->map(
            static fn(MediaImageConversionSpec $spec): string => $spec->type->value,
        );
        if ($types->count() !== $types->unique()->count()) {
            throw new ValidationException('app.media.conversion_duplicate_type');
        }

        foreach ($plan->image as $spec) {
            if (!MediaPixelDimension::supports($spec->width) || !MediaPixelDimension::supports($spec->height)) {
                throw new ValidationException('app.media.conversion_dimensions_out_of_range');
            }
        }
    }

    private function assertVideoPlan(MediaConversionPlan $plan): void
    {
        if ($plan->image !== [] || $plan->audio !== []) {
            throw new ValidationException('app.media.conversion_plan_type_mismatch');
        }

        // Ровно один профиль: проверка дубликатов типа (как в assertImagePlan) тут не нужна —
        // список из двух элементов отвергается раньше, поэтому дубль физически невозможен.
        if (\count($plan->video) !== 1) {
            throw new ValidationException('app.media.video_conversion_profile_required');
        }

        $spec = $plan->video[0];
        if (!MediaPixelDimension::supports($spec->width) || !MediaPixelDimension::supports($spec->height)) {
            throw new ValidationException('app.media.conversion_dimensions_out_of_range');
        }

        if (!MediaBitrate::supports($spec->videoBitrate) || !MediaBitrate::supports($spec->audioBitrate)) {
            throw new ValidationException('app.media.conversion_bitrate_out_of_range');
        }
    }

    private function assertAudioPlan(MediaConversionPlan $plan): void
    {
        if ($plan->image !== [] || $plan->video !== []) {
            throw new ValidationException('app.media.conversion_plan_type_mismatch');
        }

        // Ровно один профиль: проверка дубликатов типа (как в assertImagePlan) тут не нужна —
        // список из двух элементов отвергается раньше, поэтому дубль физически невозможен.
        if (\count($plan->audio) !== 1) {
            throw new ValidationException('app.media.audio_conversion_profile_required');
        }

        $spec = $plan->audio[0];
        if (!MediaBitrate::supports($spec->bitrate)) {
            throw new ValidationException('app.media.conversion_bitrate_out_of_range');
        }

        if (!MediaSampleRate::supports($spec->sampleRate)) {
            throw new ValidationException('app.media.conversion_sample_rate_out_of_range');
        }

        if (!MediaWaveformPeakCount::supports($spec->waveformPeaks)) {
            throw new ValidationException('app.media.conversion_waveform_peaks_out_of_range');
        }
    }
}

 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Command\ProcessMedia;

use App\Modules\Media\Application\Contract\MediaAudioProcessorContract;
use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Media\Application\Contract\MediaImageProcessorContract;
use App\Modules\Media\Application\Contract\MediaVideoProcessorContract;
use App\Modules\Media\Application\Dto\MediaConversionPlan;
use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Entity\MediaAudioConversion;
use App\Modules\Media\Domain\Entity\MediaImageConversion;
use App\Modules\Media\Domain\Entity\MediaVideoConversion;
use App\Modules\Media\Domain\Enum\MediaConversionStatus;
use App\Modules\Media\Domain\Enum\MediaImageConversionType;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaPixelDimension;
use App\Modules\Media\Repository\MediaRepository;
use App\Shared\Domain\Exception\InvalidDomainValueException;
use App\Shared\Domain\Exception\NotFoundException;
use Cycle\ORM\EntityManagerInterface;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;
use Illuminate\Support\Collection;
use Psr\Log\LoggerInterface;

/**
 * Без #[Transactional]: все S3/ffmpeg/Imagick-операции выполняются вне транзакции, затем один
 * атомарный persist+run() с переходом в ready. Идемпотентен: на ready — no-op; конверсии
 * создаются только в финальном flush, поэтому частичного состояния не бывает и повтор
 * пересоздаёт их без конфликта по unique (media_id, type) (процессоры перезаписывают объекты
 * по детерминированным путям).
 */
final readonly class ProcessMediaHandler
{
    public function __construct(
        private MediaRepository $mediaRepository,
        private MediaFileServiceContract $mediaFileService,
        private MediaImageProcessorContract $mediaImageProcessor,
        private MediaVideoProcessorContract $mediaVideoProcessor,
        private MediaAudioProcessorContract $mediaAudioProcessor,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}

    #[LogOperation]
    public function handle(ProcessMediaCommand $command): void
    {
        $media = $this->mediaRepository->findById(MediaId::fromString($command->mediaId))
            ?? throw new NotFoundException('app.media.not_found');

        if ($media->isReady()) {
            $this->logger->debug(message: 'Обработка медиа пропущена: уже ready.', context: [
                'mediaId' => $media->id->value(),
            ]);

            return;
        }

        $this->logger->debug(message: 'Начата обработка медиа.', context: [
            'mediaId' => $media->id->value(),
            'type' => $media->type->value,
        ]);

        $targetStorage = $this->targetStorage($media->visibility);
        $extension = $media->path->extension();

        $conversions = match ($media->type) {
            MediaType::Image => $this->buildImageConversions(
                media: $media,
                plan: $command->plan,
                targetStorage: $targetStorage,
                extension: $extension,
            ),
            MediaType::Video => $this->buildVideoConversions(
                media: $media,
                plan: $command->plan,
                targetStorage: $targetStorage,
            ),
            MediaType::Audio => $this->buildAudioConversions(
                media: $media,
                plan: $command->plan,
                targetStorage: $targetStorage,
            ),
            MediaType::Document => throw new InvalidDomainValueException('Обработка документов не поддержана.'),
        };

        $this->persistReady(
            media: $media,
            conversions: $conversions,
            targetStorage: $targetStorage,
            extension: $extension,
        );
    }

    /**
     * @param list<MediaImageConversion|MediaVideoConversion|MediaAudioConversion> $conversions
     */
    private function persistReady(
        Media $media,
        array $conversions,
        MediaStorage $targetStorage,
        string $extension,
    ): void {
        $targetPath = MediaPath::originalReady(storageKey: $media->storageKey, type: $media->type, extension: $extension);
        $this->mediaFileService->copyObject(
            fromStorage: $media->storage,
            fromPath: $media->path,
            toStorage: $targetStorage,
            toPath: $targetPath,
        );
        $media->markReadyMovedTo(storage: $targetStorage, path: $targetPath);

        $this->entityManager->persist($media);
        foreach ($conversions as $conversion) {
            $this->entityManager->persist($conversion);
        }
        $this->entityManager->run();

        $this->logger->debug(message: 'Медиа готово.', context: [
            'mediaId' => $media->id->value(),
            'targetStorage' => $targetStorage->value,
            'imageConversions' => $this->countByClass(conversions: $conversions, conversionClass: MediaImageConversion::class),
            'videoConversions' => $this->countByClass(conversions: $conversions, conversionClass: MediaVideoConversion::class),
            'audioConversions' => $this->countByClass(conversions: $conversions, conversionClass: MediaAudioConversion::class),
        ]);
    }

    /**
     * @param list<MediaImageConversion|MediaVideoConversion|MediaAudioConversion> $conversions
     * @param class-string $conversionClass
     */
    private function countByClass(array $conversions, string $conversionClass): int
    {
        return Collection::make($conversions)
            ->filter(static fn(MediaImageConversion|MediaVideoConversion|MediaAudioConversion $conversion): bool
                => $conversion instanceof $conversionClass)
            ->count();
    }

    /**
     * @return list<MediaImageConversion>
     */
    private function buildImageConversions(
        Media $media,
        MediaConversionPlan $plan,
        MediaStorage $targetStorage,
        string $extension,
    ): array {
        if ($plan->image === []) {
            return [];
        }

        $originalContents = $this->mediaFileService->getObjectContents(storage: $media->storage, path: $media->path);

        $conversions = [];

        foreach ($plan->image as $spec) {
            $conversionResult = $this->mediaImageProcessor->resize(
                originalContents: $originalContents,
                width: MediaPixelDimension::fromInt($spec->width),
                height: MediaPixelDimension::fromInt($spec->height),
                targetMimeType: $media->mimeType,
            );
            $conversionPath = MediaPath::imageConversion(
                storageKey: $media->storageKey,
                type: $spec->type,
                extension: $extension,
            );
            $this->mediaFileService->putObject(
                storage: $targetStorage,
                path: $conversionPath,
                contents: $conversionResult->contents,
                mimeType: $conversionResult->mimeType,
            );

            $this->logger->debug(message: 'Создана конверсия изображения.', context: [
                'type' => $spec->type->value,
                'path' => $conversionPath->value(),
                'size' => $conversionResult->size->value(),
            ]);

            // width/height берём из результата процессора (размер реально записанного объекта),
            // а не из spec — устойчиво к смене режима ресайза. См. README.
            $conversions[] = MediaImageConversion::create(
                media: $media,
                type: $spec->type,
                status: MediaConversionStatus::Ready,
                storage: $targetStorage,
                path: $conversionPath,
                mimeType: $conversionResult->mimeType,
                size: $conversionResult->size,
                width: $conversionResult->width,
                height: $conversionResult->height,
            );
        }

        return $conversions;
    }

    /**
     * @return list<MediaVideoConversion|MediaImageConversion>
     */
    private function buildVideoConversions(
        Media $media,
        MediaConversionPlan $plan,
        MediaStorage $targetStorage,
    ): array {
        $conversions = [];

        foreach ($plan->video as $spec) {
            $normalizedPath = MediaPath::videoConversion(storageKey: $media->storageKey, type: $spec->type, extension: 'mp4');
            $posterPath = MediaPath::imageConversion(
                storageKey: $media->storageKey,
                type: MediaImageConversionType::Poster,
                extension: 'jpg',
            );

            $result = $this->mediaVideoProcessor->process(
                sourceStorage: $media->storage,
                sourcePath: $media->path,
                spec: $spec,
                targetStorage: $targetStorage,
                normalizedPath: $normalizedPath,
                posterPath: $posterPath,
            );

            $conversions[] = MediaVideoConversion::create(
                media: $media,
                type: $spec->type,
                status: MediaConversionStatus::Ready,
                storage: $targetStorage,
                path: $normalizedPath,
                mimeType: $result->normalizedMimeType,
                size: $result->normalizedSize,
                width: $result->width,
                height: $result->height,
                duration: $result->duration,
                bitrate: $result->bitrate,
            );
            // Постер видео — MediaImageConversion type=Poster размером транскода (width/height).
            $conversions[] = MediaImageConversion::create(
                media: $media,
                type: MediaImageConversionType::Poster,
                status: MediaConversionStatus::Ready,
                storage: $targetStorage,
                path: $posterPath,
                mimeType: $result->posterMimeType,
                size: $result->posterSize,
                width: $result->width,
                height: $result->height,
            );

            $this->logger->debug(message: 'Создана конверсия видео и постер.', context: [
                'type' => $spec->type->value,

exec
/bin/zsh -lc "sed -n '1,220p' docs/code-examples.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
# Примеры кода

## DTO команды

```php
<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Command\Login;

final readonly class LoginCommand
{
    public function __construct(
        public string $email,
        public string $password,
    ) {}
}
```

## Обработчик команды

```php
<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Command\Login;

use App\Shared\Domain\Exception\AuthenticationException;
use App\Modules\User\Domain\ValueObject\Email;
use App\Modules\User\Repository\UserRepository;

final readonly class LoginHandler
{
    public function __construct(
        private UserRepository $userRepository,
    ) {}

    public function handle(LoginCommand $command): LoginResult
    {
        $user = $this->userRepository->findByEmail(Email::from($command->email))
            ?? throw new AuthenticationException('Неверный email или пароль');

        if (!$user->passwordHash->verify($command->password)) {
            throw new AuthenticationException('Неверный email или пароль');
        }

        return new LoginResult(
            accessToken: $user->issueAccessToken(),
            refreshToken: $user->issueRefreshToken(),
        );
    }
}
```

## DTO запроса

```php
<?php

declare(strict_types=1);

namespace App\Modules\User\Application\Query\GetUserProfile;

final readonly class GetUserProfileQuery
{
    public function __construct(
        public string $userId,
    ) {}
}
```

## Typed config

```php
<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Configuration\Payment;

use App\Shared\Infrastructure\Configuration\TypedConfig;

final readonly class PaymentConfig implements TypedConfig
{
    public static function configName(): string
    {
        return 'payment';
    }

    /**
     * @param array<string, PaymentProviderConfig> $providers
     */
    public function __construct(
        public string $default,
        public array $providers,
    ) {}
}
```

```php
<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Configuration\Payment;

final readonly class PaymentProviderConfig
{
    public function __construct(
        public string $dsn,
        public bool $sandbox,
    ) {}
}
```

Тест на `Tests\TestCase` поднимает Spiral kernel, поэтому живёт в suite `Kernel`
(`tests/Kernel`), а не в лёгком `Unit`:

```php
<?php

declare(strict_types=1);

namespace Tests\Kernel\Shared\Infrastructure\Configuration;

use App\Shared\Infrastructure\Configuration\Mapping\ConfigMapper;
use App\Shared\Infrastructure\Configuration\Payment\PaymentConfig;
use Tests\TestCase;

final class PaymentConfigTest extends TestCase
{
    public function testPaymentConfigMapsFromConfigurator(): void
    {
        $config = $this->getContainer()
            ->get(ConfigMapper::class)
            ->map(
                section: PaymentConfig::configName(),
                targetClass: PaymentConfig::class,
            );

        self::assertSame('stripe', $config->default);
        self::assertArrayHasKey('stripe', $config->providers);
    }
}
```

## Обработчик запроса

```php
<?php

declare(strict_types=1);

namespace App\Modules\User\Application\Query\GetUserProfile;

use App\Modules\User\Domain\Entity\User;
use App\Shared\Domain\Exception\NotFoundException;
use App\Modules\User\Repository\UserRepository;

final readonly class GetUserProfileHandler
{
    public function __construct(
        private UserRepository $userRepository,
    ) {}

    public function handle(GetUserProfileQuery $query): User
    {
        return $this->userRepository->findById($query->userId)
            ?? throw new NotFoundException('Пользователь не найден');
    }
}
```

## Контроллер

```php
<?php

declare(strict_types=1);

namespace App\Modules\User\Presentation\Http\Controller;

use App\Modules\User\Application\Query\GetUserProfile\GetUserProfileHandler;
use App\Modules\User\Application\Query\GetUserProfile\GetUserProfileQuery;
use App\Modules\User\Presentation\Http\Resource\UserResource;
use GianTiaga\SpiralOpenApi\Response\DataResponse;
use GianTiaga\SpiralCqrs\QueryBusInterface;
use Spiral\Router\Annotation\Route;

final readonly class UserController
{
    /**
     * @return DataResponse<UserResource>
     */
    #[Route(route: '/api/v1/users/<id>', name: 'api.v1.user.show', methods: ['GET'])]
    public function show(
        string $id,
        GetUserProfileHandler $getUserProfileHandler,
        QueryBusInterface $queryBus,
    ): DataResponse {
        $query = new GetUserProfileQuery(
            userId: $id,
        );

        $user = $queryBus->dispatch(
            query: $query,
            handler: $getUserProfileHandler->handle(...),
        );

        return new DataResponse(UserResource::fromEntity($user));
    }
}
```

## Сущность

```php
<?php

exec
/bin/zsh -lc "sed -n '1,160p' app/src/Modules/Media/Domain/Enum/MediaType.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\Enum;

enum MediaType: string
{
    case Image = 'image';
    case Video = 'video';
    case Audio = 'audio';
    case Document = 'document';
}

exec
/bin/zsh -lc 'rg -n "Document|documents|unsupported_file_type|conversion_plan_type_mismatch|MediaConversionPlan|allowedMimeTypes|mime" app/src/Modules/Media app/locale/ru/media.php tests -S' in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
app/locale/ru/media.php:18:    'app.media.conversion_plan_type_mismatch' => 'Профили преобразования не соответствуют типу медиа.',
app/locale/ru/media.php:24:    'app.media.mime_not_allowed' => 'MIME-тип «{mimeType}» не разрешён спецификацией загрузки.',
app/locale/ru/media.php:25:    'app.media.unsupported_file_type' => 'Тип файла «{type}» не поддерживается для загрузки.',
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:12:use App\Modules\Media\Application\Dto\MediaConversionPlan;
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:234:        string $mimeType = 'image/jpeg',
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:242:            mimeType: $mimeType,
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:250:            mimeType: $media->mimeType,
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:257:    private function completeUpload(Media $media, MediaConversionPlan $plan): OutboxEventId
app/src/Modules/Media/Domain/Enum/MediaType.php:12:    case Document = 'document';
tests/App/Modules/System/Http/ApiErrorTestController.php:30:            translationKey: 'app.media.unsupported_file_type',
app/src/Modules/Media/Domain/Entity/Media.php:65:    #[Column(type: 'string(255)', name: 'mime_type', typecast: MediaMimeType::class)]
app/src/Modules/Media/Domain/Entity/Media.php:66:    public private(set) MediaMimeType $mimeType;
app/src/Modules/Media/Domain/Entity/Media.php:115:        MediaMimeType $mimeType,
app/src/Modules/Media/Domain/Entity/Media.php:128:        $media->mimeType = $mimeType;
app/src/Modules/Media/Domain/Entity/MediaImageConversion.php:52:    #[Column(type: 'string(255)', name: 'mime_type', typecast: MediaMimeType::class)]
app/src/Modules/Media/Domain/Entity/MediaImageConversion.php:53:    public private(set) MediaMimeType $mimeType;
app/src/Modules/Media/Domain/Entity/MediaImageConversion.php:73:        MediaMimeType $mimeType,
app/src/Modules/Media/Domain/Entity/MediaImageConversion.php:86:        $conversion->mimeType = $mimeType;
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:378:    private function putSource(MediaPath $path, string $bytes, string $mime): void
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:384:            mimeType: MediaMimeType::fromString($mime),
app/src/Modules/Media/Domain/Entity/MediaVideoConversion.php:54:    #[Column(type: 'string(255)', name: 'mime_type', typecast: MediaMimeType::class)]
app/src/Modules/Media/Domain/Entity/MediaVideoConversion.php:55:    public private(set) MediaMimeType $mimeType;
app/src/Modules/Media/Domain/Entity/MediaVideoConversion.php:81:        MediaMimeType $mimeType,
app/src/Modules/Media/Domain/Entity/MediaVideoConversion.php:96:        $conversion->mimeType = $mimeType;
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:42:            mimeType: MediaMimeType::fromString(self::MIME),
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:67:            mimeType: MediaMimeType::fromString(self::MIME),
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:112:            mimeType: MediaMimeType::fromString(self::MIME),
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:141:            mimeType: MediaMimeType::fromString(self::MIME),
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:161:            mimeType: MediaMimeType::fromString(self::MIME),
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:184:            mimeType: MediaMimeType::fromString(self::MIME),
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:208:            mimeType: MediaMimeType::fromString(self::MIME),
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:220:            mimeType: MediaMimeType::fromString(self::MIME),
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:235:            mimeType: MediaMimeType::fromString(self::MIME),
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:253:            mimeType: MediaMimeType::fromString(self::MIME),
app/src/Modules/Media/Domain/Entity/MediaAudioConversion.php:56:    #[Column(type: 'string(255)', name: 'mime_type', typecast: MediaMimeType::class)]
app/src/Modules/Media/Domain/Entity/MediaAudioConversion.php:57:    public private(set) MediaMimeType $mimeType;
app/src/Modules/Media/Domain/Entity/MediaAudioConversion.php:83:        MediaMimeType $mimeType,
app/src/Modules/Media/Domain/Entity/MediaAudioConversion.php:98:        $conversion->mimeType = $mimeType;
tests/Kernel/Shared/Infrastructure/Cycle/LazyGhostMapperExtractTest.php:35:            mimeType: MediaMimeType::fromString('image/jpeg'),
app/src/Modules/Media/Domain/Collection/MediaMimeTypeCollection.php:15:    public function containsMimeType(MediaMimeType $mimeType): bool
app/src/Modules/Media/Domain/Collection/MediaMimeTypeCollection.php:17:        return $this->contains(static fn(MediaMimeType $allowed): bool => $allowed->equals($mimeType));
tests/Feature/Modules/User/Application/UserApplicationTestCase.php:127:            mimeType: MediaMimeType::fromString('image/jpeg'),
tests/Kernel/Shared/Infrastructure/Cycle/LazyGhostEntityFactoryTest.php:108:            mimeType: MediaMimeType::fromString('image/jpeg'),
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:10:use App\Modules\Media\Application\Dto\MediaConversionPlan;
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:64:        self::assertInstanceOf(MediaConversionPlan::class, $captured->plan);
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:97:            mimeType: 'video/mp4',
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:121:            mimeType: 'audio/mpeg',
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:172:        $media = $this->createMedia(userId: $userId, type: MediaType::Video, extension: 'mp4', mimeType: 'video/mp4');
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:188:        $media = $this->createMedia(userId: $userId, type: MediaType::Video, extension: 'mp4', mimeType: 'video/mp4');
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:204:        $media = $this->createMedia(userId: $userId, type: MediaType::Video, extension: 'mp4', mimeType: 'video/mp4');
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:220:        $media = $this->createMedia(userId: $userId, type: MediaType::Audio, extension: 'mp3', mimeType: 'audio/mpeg');
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:236:        $media = $this->createMedia(userId: $userId, type: MediaType::Audio, extension: 'mp3', mimeType: 'audio/mpeg');
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:252:        $media = $this->createMedia(userId: $userId, type: MediaType::Video, extension: 'mp4', mimeType: 'video/mp4');
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:268:        $media = $this->createMedia(userId: $userId, type: MediaType::Video, extension: 'mp4', mimeType: 'video/mp4');
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:284:        $media = $this->createMedia(userId: $userId, type: MediaType::Audio, extension: 'mp3', mimeType: 'audio/mpeg');
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:300:        $media = $this->createMedia(userId: $userId, type: MediaType::Audio, extension: 'mp3', mimeType: 'audio/mpeg');
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:316:        $media = $this->createMedia(userId: $userId, type: MediaType::Audio, extension: 'mp3', mimeType: 'audio/mpeg');
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:332:        $media = $this->createMedia(userId: $userId, type: MediaType::Audio, extension: 'mp3', mimeType: 'audio/mpeg');
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:345:    public function testRejectsDocumentMedia(): void
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:348:        $media = $this->createMedia(userId: $userId, type: MediaType::Document, extension: 'pdf', mimeType: 'application/pdf');
tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php:180:            mimeType: MediaMimeType::fromString('audio/mp4'),
tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php:215:            mimeType: MediaMimeType::fromString('image/jpeg'),
tests/Feature/Modules/Media/Application/GetAudioWaveformHandlerTest.php:40:        $media = $this->createMedia(userId: UserId::generate(), type: MediaType::Audio, extension: 'mp3', mimeType: 'audio/mpeg');
tests/Feature/Modules/Media/Application/GetAudioWaveformHandlerTest.php:95:            mimeType: 'audio/mpeg',
tests/Feature/Modules/Media/Application/GetAudioWaveformHandlerTest.php:118:            mimeType: MediaMimeType::fromString('audio/mp4'),
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:271:            mimeType: MediaMimeType::fromString('video/mp4'),
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:292:            mimeType: MediaMimeType::fromString('audio/mp4'),
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:313:            mimeType: MediaMimeType::fromString('image/jpeg'),
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:138:            type: MediaType::Document,
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:253:    public function testRejectsDocumentType(): void
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:257:            type: MediaType::Document,
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:259:            mimeType: 'application/pdf',
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:305:            mimeType: MediaMimeType::fromString('image/jpeg'),
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:337:            mimeType: 'video/mp4',
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:351:            mimeType: 'audio/mpeg',
tests/Unit/Modules/Media/Infrastructure/S3MediaFileServiceErrorTest.php:86:        $fileService->createMultipartUpload(MediaStorage::Upload, $this->path(), $this->mime());
tests/Unit/Modules/Media/Infrastructure/S3MediaFileServiceErrorTest.php:175:        $fileService->createMultipartUpload(MediaStorage::Upload, $this->path(), $this->mime());
tests/Unit/Modules/Media/Infrastructure/S3MediaFileServiceErrorTest.php:186:        $fileService->putObject(MediaStorage::Upload, $this->path(), 'contents', $this->mime());
tests/Unit/Modules/Media/Infrastructure/S3MediaFileServiceErrorTest.php:234:        $fileService->uploadFromFile(MediaStorage::Upload, $this->path(), '/tmp/nonexistent-source', $this->mime());
tests/Unit/Modules/Media/Infrastructure/S3MediaFileServiceErrorTest.php:422:    private function mime(): MediaMimeType
tests/Feature/Modules/Media/Application/MediaApplicationTestCase.php:8:use App\Modules\Media\Application\Dto\MediaConversionPlan;
tests/Feature/Modules/Media/Application/MediaApplicationTestCase.php:39:        string $mimeType = 'image/jpeg',
tests/Feature/Modules/Media/Application/MediaApplicationTestCase.php:48:            mimeType: MediaMimeType::fromString($mimeType),
tests/Feature/Modules/Media/Application/MediaApplicationTestCase.php:102:    protected function emptyPlan(): MediaConversionPlan
tests/Feature/Modules/Media/Application/MediaApplicationTestCase.php:104:        return new MediaConversionPlan(image: [], video: [], audio: []);
tests/Feature/Modules/Media/Application/MediaApplicationTestCase.php:107:    protected function imagePlan(MediaImageConversionSpec ...$specs): MediaConversionPlan
tests/Feature/Modules/Media/Application/MediaApplicationTestCase.php:109:        return new MediaConversionPlan(image: \array_values($specs), video: [], audio: []);
tests/Feature/Modules/Media/Application/MediaApplicationTestCase.php:112:    protected function videoPlan(MediaVideoConversionSpec ...$specs): MediaConversionPlan
tests/Feature/Modules/Media/Application/MediaApplicationTestCase.php:114:        return new MediaConversionPlan(image: [], video: \array_values($specs), audio: []);
tests/Feature/Modules/Media/Application/MediaApplicationTestCase.php:117:    protected function audioPlan(MediaAudioConversionSpec ...$specs): MediaConversionPlan
tests/Feature/Modules/Media/Application/MediaApplicationTestCase.php:119:        return new MediaConversionPlan(image: [], video: [], audio: \array_values($specs));
tests/Unit/Modules/Media/Infrastructure/ImagickMediaImageProcessorTest.php:29:        self::assertSame('image/jpeg', $result->mimeType->value());
tests/Unit/Modules/Media/Infrastructure/ImagickMediaImageProcessorTest.php:45:        self::assertSame('image/png', $result->mimeType->value());
tests/Feature/Modules/User/Repository/UserRepositoryTest.php:249:            mimeType: MediaMimeType::fromString('image/jpeg'),
app/src/Modules/Media/Application/Service/MediaTypeResolver.php:18:    public function resolve(MediaMimeType $mimeType): MediaType
app/src/Modules/Media/Application/Service/MediaTypeResolver.php:20:        $value = $mimeType->value();
app/src/Modules/Media/Application/Service/MediaTypeResolver.php:35:            translationKey: 'app.media.unsupported_file_type',
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:28:        $media = $this->createMedia(userId: UserId::generate(), type: MediaType::Video, extension: 'mp4', mimeType: 'video/mp4');
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:44:                MediaMimeType $mimeType,
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:126:            fileMeta: $this->fileMeta(mimeType: 'audio/mpeg'),
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:137:            fileMeta: $this->fileMeta(mimeType: 'image/png'),
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:190:            allowedMimeTypes: new MediaMimeTypeCollection([MediaMimeType::fromString('image/jpeg')]),
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:199:        string $mimeType = 'image/jpeg',
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:204:            mimeType: MediaMimeType::fromString($mimeType),
tests/Feature/Modules/Posts/Http/PostsHttpTestCase.php:286:            mimeType: MediaMimeType::fromString('image/jpeg'),
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:229:            mimeType: MediaMimeType::fromString('image/jpeg'),
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:240:        $media = $this->createMedia(userId: $userId, type: MediaType::Video, extension: 'mp4', mimeType: 'video/mp4');
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:256:            mimeType: MediaMimeType::fromString('video/mp4'),
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:269:        $media = $this->createMedia(userId: $userId, type: MediaType::Audio, extension: 'mp3', mimeType: 'audio/mpeg');
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:287:            mimeType: MediaMimeType::fromString('audio/mp4'),
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:267:            mimeType: MediaMimeType::fromString('image/jpeg'),
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:284:            mimeType: MediaMimeType::fromString('image/jpeg'),
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:301:            mimeType: MediaMimeType::fromString('video/mp4'),
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:320:            mimeType: MediaMimeType::fromString('audio/mp4'),
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php:8:use App\Modules\Media\Application\Dto\MediaConversionPlan;
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php:117:    private function assertPlanValid(MediaConversionPlan $plan, MediaType $type): void
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php:123:            MediaType::Document => throw new ValidationException(
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php:124:                translationKey: 'app.media.unsupported_file_type',
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php:130:    private function assertImagePlan(MediaConversionPlan $plan): void
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php:133:            throw new ValidationException('app.media.conversion_plan_type_mismatch');
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php:150:    private function assertVideoPlan(MediaConversionPlan $plan): void
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php:153:            throw new ValidationException('app.media.conversion_plan_type_mismatch');
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php:172:    private function assertAudioPlan(MediaConversionPlan $plan): void
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php:175:            throw new ValidationException('app.media.conversion_plan_type_mismatch');
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadCommand.php:7:use App\Modules\Media\Application\Dto\MediaConversionPlan;
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadCommand.php:15:        public MediaConversionPlan $plan,
app/src/Modules/Media/Domain/ValueObject/MediaPath.php:83:            MediaType::Document => throw new InvalidDomainValueException(
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php:40:        $mediaType = $this->mediaTypeResolver->resolve($command->fileMeta->mimeType);
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php:49:            mimeType: $command->fileMeta->mimeType,
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php:67:            'mimeType' => $command->fileMeta->mimeType->value(),
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php:79:            mimeType: $media->mimeType,
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php:98:            mimeType: $media->mimeType,
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php:127:        if (!$spec->allowedMimeTypes->containsMimeType($fileMeta->mimeType)) {
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php:129:                translationKey: 'app.media.mime_not_allowed',
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php:130:                translationParameters: ['mimeType' => $fileMeta->mimeType->value()],
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaCommand.php:7:use App\Modules\Media\Application\Dto\MediaConversionPlan;
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaCommand.php:13:        public MediaConversionPlan $plan,
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:11:use App\Modules\Media\Application\Dto\MediaConversionPlan;
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:90:            MediaType::Document => throw new InvalidDomainValueException('Обработка документов не поддержана.'),
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:151:        MediaConversionPlan $plan,
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:168:                targetMimeType: $media->mimeType,
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:179:                mimeType: $conversionResult->mimeType,
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:196:                mimeType: $conversionResult->mimeType,
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:211:        MediaConversionPlan $plan,
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:239:                mimeType: $result->normalizedMimeType,
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:253:                mimeType: $result->posterMimeType,
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:274:        MediaConversionPlan $plan,
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:296:                mimeType: $result->normalizedMimeType,
app/src/Modules/Media/Infrastructure/FileService/ImagickMediaImageProcessor.php:47:            mimeType: MediaMimeType::fromString($encoded->mimetype()),
app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaAudioProcessor.php:58:                mimeType: $result->normalizedMimeType,
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:52:        MediaMimeType $mimeType,
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:59:            'ContentType' => $mimeType->value(),
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:69:        MediaMimeType $mimeType,
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:75:                'ContentType' => $mimeType->value(),
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:246:        MediaMimeType $mimeType,
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:253:                'ContentType' => $mimeType->value(),
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:265:        MediaMimeType $mimeType,
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:272:                'ContentType' => $mimeType->value(),
app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaVideoProcessor.php:61:                mimeType: $result->normalizedMimeType,
app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaVideoProcessor.php:67:                mimeType: $result->posterMimeType,
app/src/Modules/Media/Application/Message/MediaUploaded.php:7:use App\Modules\Media\Application\Dto\MediaConversionPlan;
app/src/Modules/Media/Application/Message/MediaUploaded.php:12: * примитивы/enum (mediaId — строка, plan — MediaConversionPlan из публичных readonly-DTO с
app/src/Modules/Media/Application/Message/MediaUploaded.php:20:        public MediaConversionPlan $plan,
tests/Unit/Modules/Media/Application/MediaUploadedSerializationTest.php:8:use App\Modules\Media\Application\Dto\MediaConversionPlan;
tests/Unit/Modules/Media/Application/MediaUploadedSerializationTest.php:26:            plan: new MediaConversionPlan(
tests/Unit/Modules/Media/Application/MediaTypeResolverTest.php:27:    public function testRejectsUnsupportedMimeTypes(string $mimeType): void
tests/Unit/Modules/Media/Application/MediaTypeResolverTest.php:31:        new MediaTypeResolver()->resolve(MediaMimeType::fromString($mimeType));
app/src/Modules/Media/Application/Contract/MediaImageProcessorContract.php:15: * mimeType/size/width/height, которые требует MediaImageConversion::create().
app/src/Modules/Media/Application/Contract/MediaFileServiceContract.php:31:        MediaMimeType $mimeType,
app/src/Modules/Media/Application/Contract/MediaFileServiceContract.php:41:        MediaMimeType $mimeType,
app/src/Modules/Media/Application/Contract/MediaFileServiceContract.php:102:        MediaMimeType $mimeType,
app/src/Modules/Media/Application/Contract/MediaFileServiceContract.php:112:        MediaMimeType $mimeType,
app/src/Modules/Media/Application/Dto/MediaUploadSpec.php:19:        public MediaMimeTypeCollection $allowedMimeTypes,
app/src/Modules/Media/Application/Dto/MediaFileMeta.php:12: * и не хранится; mimeType и size — доменные VO.
app/src/Modules/Media/Application/Dto/MediaFileMeta.php:18:        public MediaMimeType $mimeType,
app/src/Modules/Media/Application/Dto/MediaConversionPlan.php:14:final readonly class MediaConversionPlan
tests/Feature/Modules/Posts/PostsRepositoryTestCase.php:77:            mimeType: MediaMimeType::fromString('image/jpeg'),
app/src/Modules/Media/Application/Dto/MediaImageConversionSpec.php:11: * конструктором: переиспользуется в outbox-сообщении MediaUploaded (через MediaConversionPlan),
app/src/Modules/Media/Application/Dto/MediaConversionResult.php:13: * фактические mimeType/size/width/height — они нужны для MediaImageConversion::create()
app/src/Modules/Media/Application/Dto/MediaConversionResult.php:20:        public MediaMimeType $mimeType,
app/src/Modules/Media/Application/Dto/MediaAudioConversionSpec.php:11: * конструктором: переиспользуется в outbox-сообщении MediaUploaded (через MediaConversionPlan),
app/src/Modules/Media/Application/Dto/MediaVideoConversionSpec.php:11: * конструктором: переиспользуется в outbox-сообщении MediaUploaded (через MediaConversionPlan),
app/src/Modules/Media/README.md:20:| `CompleteMediaUpload(userId, mediaId, MediaConversionPlan, parts?)` | Command `#[Transactional]` | `MediaResult` |
app/src/Modules/Media/README.md:21:| `ProcessMedia(mediaId, MediaConversionPlan)` | Command (через Job) | `void` |
app/src/Modules/Media/README.md:38:- `MediaUploadSpec{ allowedMimeTypes: MediaMimeTypeCollection, maxSize: MediaFileSize, visibility, presignedTtl: MediaPresignedTtl }`
app/src/Modules/Media/README.md:41:- `MediaFileMeta{ fileName, mimeType, size }` — `fileName` используется только для извлечения
app/src/Modules/Media/README.md:44:  объединены в `MediaConversionPlan{ image: list<MediaImageConversionSpec>, video: list<...>, audio: list<...> }`:
app/src/Modules/Media/README.md:139:(`string mediaId`, вложенный `MediaConversionPlan` из `list<...Spec>`), потому что
tests/Unit/Shared/Domain/Exception/ApiDomainExceptionTest.php:20:            translationKey: 'app.media.unsupported_file_type',
tests/Unit/Shared/Domain/Exception/ApiDomainExceptionTest.php:27:        self::assertSame('app.media.unsupported_file_type', $exception->translationKey());
tests/Unit/Shared/Domain/Exception/ApiDomainExceptionTest.php:28:        self::assertSame('app.media.unsupported_file_type', $exception->getMessage());
tests/Feature/Modules/Outbox/Application/OutboxEventStoreTransactionTest.php:111:            mimeType: MediaMimeType::fromString('image/jpeg'),
tests/Unit/Shared/Infrastructure/Configuration/RoadRunnerRabbitMqConfigTest.php:28:    public function testRabbitMqVhostNameAndUrlPathAreDocumentedSeparately(): void

exec
/bin/zsh -lc "sed -n '1,180p' app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Command\RequestMediaUpload;

use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Media\Application\Dto\MediaFileMeta;
use App\Modules\Media\Application\Dto\RequestMediaUploadResult;
use App\Modules\Media\Application\Dto\MediaUploadSpec;
use App\Modules\Media\Application\Service\MediaTypeResolver;
use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Entity\MediaMultipartUpload;
use App\Modules\Media\Domain\ValueObject\MediaExpiration;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPartSize;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPartsCount;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaStorageKey;
use App\Shared\Domain\Exception\ValidationException;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Configuration\Media\MediaConfig;
use Cycle\ORM\EntityManagerInterface;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;
use Psr\Log\LoggerInterface;

final readonly class RequestMediaUploadHandler
{
    public function __construct(
        private MediaFileServiceContract $mediaFileService,
        private MediaTypeResolver $mediaTypeResolver,
        private MediaConfig $mediaConfig,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}

    #[LogOperation]
    public function handle(RequestMediaUploadCommand $command): RequestMediaUploadResult
    {
        $mediaType = $this->mediaTypeResolver->resolve($command->fileMeta->mimeType);
        $this->assertUploadAllowed(spec: $command->spec, fileMeta: $command->fileMeta);

        $storageKey = MediaStorageKey::generate();
        $media = Media::create(
            storageKey: $storageKey,
            type: $mediaType,
            visibility: $command->spec->visibility,
            path: MediaPath::originalUpload(storageKey: $storageKey, extension: $this->extension($command->fileMeta)),
            mimeType: $command->fileMeta->mimeType,
            size: $command->fileMeta->size,
            uploadedById: UserId::fromString($command->userId),
            expiration: MediaExpiration::temporaryUntil($this->expiresIn($this->mediaConfig->stagingTtlSeconds)),
        );

        $presignedExpiresAt = $this->expiresIn($command->spec->presignedTtl->value());
        $preparedUpload = $this->isMultipart($command->fileMeta->size)
            ? $this->prepareMultipartUpload(media: $media, presignedExpiresAt: $presignedExpiresAt)
            : $this->prepareSingleUpload(media: $media, presignedExpiresAt: $presignedExpiresAt);

        $this->entityManager->run();

        $this->logger->debug(message: 'Запрошена загрузка медиа.', context: [
            'mediaId' => $media->id->value(),
            'userId' => $command->userId,
            'uploadMode' => $preparedUpload->uploadMode->value,
            'size' => $command->fileMeta->size->value(),
            'mimeType' => $command->fileMeta->mimeType->value(),
            'presignedTtlSeconds' => $command->spec->presignedTtl->value(),
        ]);

        return $preparedUpload;
    }

    private function prepareSingleUpload(Media $media, \DateTimeImmutable $presignedExpiresAt): RequestMediaUploadResult
    {
        $putUrl = $this->mediaFileService->presignPut(
            storage: $media->storage,
            path: $media->path,
            mimeType: $media->mimeType,
            expiresAt: $presignedExpiresAt,
        );
        $this->entityManager->persist($media);

        return RequestMediaUploadResult::single(
            mediaId: $media->id->value(),
            putUrl: $putUrl,
            expiresAt: $presignedExpiresAt,
        );
    }

    private function prepareMultipartUpload(Media $media, \DateTimeImmutable $presignedExpiresAt): RequestMediaUploadResult
    {
        $partSize = MediaMultipartPartSize::fromInt($this->mediaConfig->multipartPartSizeBytes);
        $partsCount = MediaMultipartPartsCount::fromInt($this->partsCount(size: $media->size, partSize: $partSize));
        $uploadId = $this->mediaFileService->createMultipartUpload(
            storage: $media->storage,
            path: $media->path,
            mimeType: $media->mimeType,
        );
        $parts = $this->mediaFileService->presignUploadParts(
            storage: $media->storage,
            path: $media->path,
            uploadId: $uploadId,
            partsCount: $partsCount,
            expiresAt: $presignedExpiresAt,
        );

        $this->entityManager->persist($media);
        $this->entityManager->persist(MediaMultipartUpload::create(
            media: $media,
            uploadId: $uploadId,
            partsCount: $partsCount,
            partSize: $partSize,
            fileSize: $media->size,
        ));

        return RequestMediaUploadResult::multipart(
            mediaId: $media->id->value(),
            uploadId: $uploadId->value(),
            parts: $parts,
            expiresAt: $presignedExpiresAt,
        );
    }

    private function assertUploadAllowed(MediaUploadSpec $spec, MediaFileMeta $fileMeta): void
    {
        if (!$spec->allowedMimeTypes->containsMimeType($fileMeta->mimeType)) {
            throw new ValidationException(
                translationKey: 'app.media.mime_not_allowed',
                translationParameters: ['mimeType' => $fileMeta->mimeType->value()],
            );
        }

        if ($fileMeta->size->value() > $spec->maxSize->value()) {
            throw new ValidationException('app.media.file_size_exceeded');
        }
    }

    private function extension(MediaFileMeta $fileMeta): string
    {
        $extension = \pathinfo(path: $fileMeta->fileName, flags: \PATHINFO_EXTENSION);

        if ($extension === '') {
            throw new ValidationException('app.media.file_name_without_extension');
        }

        return $extension;
    }

    private function isMultipart(MediaFileSize $size): bool
    {
        return $size->value() >= $this->mediaConfig->multipartThresholdBytes;
    }

    private function partsCount(MediaFileSize $size, MediaMultipartPartSize $partSize): int
    {
        return (int) \ceil($size->value() / $partSize->value());
    }

    private function expiresIn(int $seconds): \DateTimeImmutable
    {
        return new \DateTimeImmutable()->add(new \DateInterval(\sprintf('PT%dS', $seconds)));
    }
}

exec
/bin/zsh -lc "sed -n '1,220p' tests/Unit/Modules/Media/Application/MediaTypeResolverTest.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Media\Application;

use App\Modules\Media\Application\Service\MediaTypeResolver;
use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Shared\Domain\Exception\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MediaTypeResolverTest extends TestCase
{
    public function testResolvesImageVideoAndAudio(): void
    {
        $resolver = new MediaTypeResolver();

        self::assertSame(MediaType::Image, $resolver->resolve(MediaMimeType::fromString('image/jpeg')));
        self::assertSame(MediaType::Video, $resolver->resolve(MediaMimeType::fromString('video/mp4')));
        self::assertSame(MediaType::Audio, $resolver->resolve(MediaMimeType::fromString('audio/mpeg')));
        self::assertSame(MediaType::Audio, $resolver->resolve(MediaMimeType::fromString('audio/mp4')));
    }

    #[DataProvider('unsupportedMimeTypeProvider')]
    public function testRejectsUnsupportedMimeTypes(string $mimeType): void
    {
        $this->expectException(ValidationException::class);

        new MediaTypeResolver()->resolve(MediaMimeType::fromString($mimeType));
    }

    /**
     * @return list<array{string}>
     */
    public static function unsupportedMimeTypeProvider(): array
    {
        return [['application/pdf'], ['text/plain'], ['font/woff2']];
    }
}

exec
/bin/zsh -lc "sed -n '1,220p' app/src/Modules/Media/Application/Dto/MediaUploadSpec.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Dto;

use App\Modules\Media\Domain\Collection\MediaMimeTypeCollection;
use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaPresignedTtl;

/**
 * Спецификация ограничений загрузки, которую потребитель передаёт в RequestMediaUpload.
 * In-process DTO: держит политику загрузки на стороне серверного модуля-потребителя.
 */
final readonly class MediaUploadSpec
{
    public function __construct(
        public MediaMimeTypeCollection $allowedMimeTypes,
        public MediaFileSize $maxSize,
        public MediaVisibility $visibility,
        public MediaPresignedTtl $presignedTtl,
    ) {}
}

exec
/bin/zsh -lc "sed -n '1,220p' app/src/Modules/Media/Domain/ValueObject/MediaMimeType.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidDomainValueException;

final readonly class MediaMimeType implements \Stringable, \JsonSerializable
{
    private const int MAX_LENGTH = 255;

    private function __construct(
        private string $value,
    ) {}

    public static function fromString(string $value): self
    {
        $value = \trim($value);

        if ($value === '' || \strlen($value) > self::MAX_LENGTH) {
            throw new InvalidDomainValueException('MIME type файла имеет неверную длину.');
        }

        return new self(value: $value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->value;
    }

    #[\Override]
    public function jsonSerialize(): string
    {
        return $this->value;
    }
}

exec
/bin/zsh -lc "sed -n '120,170p' tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
            MediaPath::fromString('audios/ab/ab111111-1111-4111-8111-111111111111/source.m4a')->value(),
        );
    }

    public function testMediaPathExtensionReadsLastSegment(): void
    {
        $storageKey = MediaStorageKey::fromString('ab111111-1111-4111-8111-111111111111');

        self::assertSame('jpg', MediaPath::originalUpload(storageKey: $storageKey, extension: 'jpg')->extension());
        self::assertSame('', MediaPath::fromString('uploads/ab/ab111111-1111-4111-8111-111111111111/source')->extension());
    }

    public function testOriginalReadyRejectsUnsupportedMediaType(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        MediaPath::originalReady(
            storageKey: MediaStorageKey::generate(),
            type: MediaType::Document,
            extension: 'pdf',
        );
    }

    public function testWaveformValidatesPeaksAndSerializes(): void
    {
        $waveform = MediaWaveform::fromPeaks([0, 128, 255]);

        self::assertSame([0, 128, 255], $waveform->peaks());
        self::assertSame([0, 128, 255], $waveform->jsonSerialize());
        self::assertTrue($waveform->equals(MediaWaveform::fromPeaks([0, 128, 255])));
        self::assertFalse($waveform->equals(MediaWaveform::fromPeaks([0, 128, 254])));
    }

    public function testWaveformRejectsEmptyPeaks(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        MediaWaveform::fromPeaks([]);
    }

    public function testWaveformRejectsPeakOutOfRange(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        MediaWaveform::fromPeaks([0, 256]);
    }

    public function testMediaPathFactoryRejectsInvalidExtension(): void
    {
        $this->expectException(InvalidDomainValueException::class);


codex
Дополнительно проверил `RequestMediaUploadHandler`: это важно для тезиса про `allowedMimeTypes`. Там тип файла определяется до проверки разрешённых MIME, поэтому потребитель не может сам «добавить» MIME, которого нет в общем распознавателе.
web search: 
web search: IANA media types application rtf text rtf image vnd.djvu text markdown application epub+zip application x-fictionbook+xml
web search: 
web search: https://www.iana.org/assignments/media-types/application/rtf
exec
/bin/zsh -lc "sed -n '1,140p' app/src/Modules/Media/Presentation/Job/ProcessMediaJob.php 2>/dev/null || true" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc "sed -n '1,120p' app/src/Modules/Media/Application/Message/MediaUploaded.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Media\Presentation\Job;

use App\Modules\Media\Application\Command\ProcessMedia\ProcessMediaCommand;
use App\Modules\Media\Application\Command\ProcessMedia\ProcessMediaHandler;
use App\Modules\Media\Application\Command\RecordMediaProcessingFailure\RecordMediaProcessingFailureCommand;
use App\Modules\Media\Application\Command\RecordMediaProcessingFailure\RecordMediaProcessingFailureHandler;
use App\Modules\Media\Application\Exception\MediaFileServiceFailedException;
use App\Modules\Media\Application\Exception\MediaProcessorFailedException;
use App\Modules\Media\Application\Message\MediaUploaded;
use App\Modules\Outbox\Application\Contract\OutboxMessageLoaderContract;
use App\Modules\Outbox\Application\Message\OutboxQueueEnvelope;
use GianTiaga\SpiralCqrs\CommandBusInterface;
use Psr\Log\LoggerInterface;
use Spiral\Queue\Exception\RetryException;
use Spiral\Queue\JobHandler;

/**
 * Инфраструктурный Job обработки медиа. Грузит MediaUploaded из outbox и запускает
 * ProcessMediaCommand. Ошибки обработки ловятся здесь (Job — граница системы, try-catch
 * разрешён): фиксируем безопасную ошибку на Media и классифицируем — временную просим
 * повторить (RetryException, его читает OutboxQueueStatusInterceptor), постоянную пробрасываем
 * терминально (outbox -> failed). Статусы outbox Job сам не трогает.
 */
final class ProcessMediaJob extends JobHandler
{
    private const string STORAGE_FAILURE_MESSAGE = 'Ошибка хранилища при обработке медиа.';
    private const string PROCESSING_RETRY_MESSAGE = 'Повторная обработка медиа запланирована.';
    private const string PROCESSING_FAILURE_MESSAGE = 'Не удалось обработать медиа.';

    public function invoke(
        OutboxQueueEnvelope $payload,
        string $id,
        OutboxMessageLoaderContract $outboxMessageLoader,
        CommandBusInterface $commandBus,
        ProcessMediaHandler $processMediaHandler,
        RecordMediaProcessingFailureHandler $recordMediaProcessingFailureHandler,
        LoggerInterface $logger,
    ): void {
        $mediaUploaded = $outboxMessageLoader->load(
            outboxEventId: $payload->outboxEventId,
            expectedMessageClass: MediaUploaded::class,
        );

        try {
            $commandBus->dispatch(
                command: new ProcessMediaCommand(
                    mediaId: $mediaUploaded->mediaId,
                    plan: $mediaUploaded->plan,
                ),
                handler: $processMediaHandler->handle(...),
            );
        } catch (\Throwable $exception) {
            // Временность приходит контрактным сигналом isTransient(): классификацию делает
            // Infrastructure (AWS — файловый сервис, ffmpeg — процессоры), Presentation не знает про
            // конкретные реализации хранилища и обработки.
            $isTransient = ($exception instanceof MediaFileServiceFailedException && $exception->isTransient())
                || ($exception instanceof MediaProcessorFailedException && $exception->isTransient());

            // Запись ошибки на Media — отдельный сбойный путь: медиа могли конкурентно удалить
            // (NotFoundException) или короткий сбой БД. Защищаем только этот вызов локальным guard,
            // чтобы вторичный сбой записи не подменил исходную причину и решение повтор/терминальный исход:
            // логируем его как вторичный сбой (ERROR — реальная инфра/инвариант-проблема, rules.md:84)
            // и продолжаем классифицировать по исходному $exception.
            try {
                $commandBus->dispatch(
                    command: new RecordMediaProcessingFailureCommand(
                        mediaId: $mediaUploaded->mediaId,
                        error: $this->safeMessage($exception),
                        isTransient: $isTransient,
                    ),
                    handler: $recordMediaProcessingFailureHandler->handle(...),
                );
            } catch (\Throwable $recordFailure) {
                $logger->error(message: 'Не удалось записать ошибку обработки на медиа.', context: [
                    'mediaId' => $mediaUploaded->mediaId,
                    'jobId' => $id,
                    'recordErrorClass' => $recordFailure::class,
                    'originalErrorClass' => $exception::class,
                    'errorMessage' => $recordFailure->getMessage(),
                ]);
            }

            $logContext = [
                'mediaId' => $mediaUploaded->mediaId,
                'jobId' => $id,
                'isTransient' => $isTransient,
                'errorClass' => $exception::class,
            ];

            if ($isTransient) {
                // Временный повторяемый сбой инфраструктуры -> WARN (rules.md:84), повтор ожидаем.
                $logger->warning(
                    message: 'Временная ошибка обработки медиа, запланирован повтор.',
                    context: $logContext,
                );

                // Причина повтора по типу сбоя: ошибка процессора != ошибка хранилища.
                $retryReason = $exception instanceof MediaProcessorFailedException
                    ? self::PROCESSING_RETRY_MESSAGE
                    : self::STORAGE_FAILURE_MESSAGE;

                throw new RetryException(reason: $retryReason);
            }

            // Постоянный сбой (битый файл/нарушение инварианта) -> ERROR, повтора не будет.
            $logger->error(message: 'Ошибка обработки медиа.', context: $logContext);

            throw $exception;
        }
    }

    private function safeMessage(\Throwable $exception): string
    {
        return $exception instanceof MediaFileServiceFailedException
            ? self::STORAGE_FAILURE_MESSAGE
            : self::PROCESSING_FAILURE_MESSAGE;
    }
}

 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Message;

use App\Modules\Media\Application\Dto\MediaConversionPlan;
use App\Modules\Outbox\Application\Message\OutboxMessage;

/**
 * Outbox-сообщение: загрузка подтверждена, нужна асинхронная обработка. Полезные данные — только
 * примитивы/enum (mediaId — строка, plan — MediaConversionPlan из публичных readonly-DTO с
 * точными list<...Spec>), чтобы ValinorOutboxMessageSerializer восстановил его без кастомных
 * конструкторов VO.
 */
final readonly class MediaUploaded implements OutboxMessage
{
    public function __construct(
        public string $mediaId,
        public MediaConversionPlan $plan,
    ) {}
}

exec
/bin/zsh -lc "sed -n '1,90p' app/src/Modules/Media/Application/Dto/MediaConversionPlan.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Dto;

/**
 * План конверсий на тип медиа: отдельный список профилей для каждого MediaType. Потребитель
 * задаёт списки на тип, валидируется только список, относящийся к типу медиа.
 *
 * Все три списка несут точные PHPDoc-типы list<...Spec> — это условие восстановления вложенного
 * DTO через ValinorOutboxMessageSerializer (план кладётся в outbox-сообщение MediaUploaded).
 */
final readonly class MediaConversionPlan
{
    /**
     * @param list<MediaImageConversionSpec> $image
     * @param list<MediaVideoConversionSpec> $video
     * @param list<MediaAudioConversionSpec> $audio
     */
    public function __construct(
        public array $image,
        public array $video,
        public array $audio,
    ) {}
}

exec
/bin/zsh -lc "rg -n \"new ProcessMediaCommand|ProcessMediaCommand\\(\" app tests -S" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
app/src/Modules/Media/Presentation/Job/ProcessMediaJob.php:50:                command: new ProcessMediaCommand(
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:84:        $this->getContainer()->get(ProcessMediaHandler::class)->handle(new ProcessMediaCommand(
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:104:        $this->getContainer()->get(ProcessMediaHandler::class)->handle(new ProcessMediaCommand(
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:47:        $this->handler($fileService)->handle(new ProcessMediaCommand(
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:69:        $handler->handle(new ProcessMediaCommand(
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:89:        $this->handler($fileService)->handle(new ProcessMediaCommand(
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:120:        $this->handler(fileService: $fileService, videoProcessor: $videoProcessor)->handle(new ProcessMediaCommand(
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:157:        $this->handler(fileService: $fileService, audioProcessor: $audioProcessor)->handle(new ProcessMediaCommand(
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:191:        $this->handler(fileService: $fileService, videoProcessor: $videoProcessor)->handle(new ProcessMediaCommand(
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:219:        $this->handler(fileService: $fileService, audioProcessor: $audioProcessor)->handle(new ProcessMediaCommand(
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:245:        $this->handler($fileService)->handle(new ProcessMediaCommand(
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:266:        $this->handler($this->createStub(MediaFileServiceContract::class))->handle(new ProcessMediaCommand(
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:276:        $this->handler($this->createStub(MediaFileServiceContract::class))->handle(new ProcessMediaCommand(
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:289:        $this->handler($this->createStub(MediaFileServiceContract::class))->handle(new ProcessMediaCommand(

codex
Сейчас сверяю не только перечисленные пять точек, но и ближайшие контракты вокруг них. Это нужно, чтобы не принять за решение то, что в реальном потоке недостижимо или проверяется в другом порядке.
exec
/bin/zsh -lc "sed -n '1,220p' app/src/Modules/Media/Application/Query/GetMediaUrl/GetMediaUrlHandler.php 2>/dev/null || true" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Query\GetMediaUrl;

use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Media\Application\Dto\MediaUrlResult;
use App\Modules\Media\Domain\Entity\MediaAudioConversion;
use App\Modules\Media\Domain\Entity\MediaImageConversion;
use App\Modules\Media\Domain\Entity\MediaVideoConversion;
use App\Modules\Media\Domain\Enum\MediaAudioConversionType;
use App\Modules\Media\Domain\Enum\MediaImageConversionType;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\Enum\MediaVideoConversionType;
use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Modules\Media\Domain\ValueObject\MediaId;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaPresignedTtl;
use App\Modules\Media\Repository\MediaAudioConversionRepository;
use App\Modules\Media\Repository\MediaImageConversionRepository;
use App\Modules\Media\Repository\MediaRepository;
use App\Modules\Media\Repository\MediaVideoConversionRepository;
use App\Shared\Domain\Exception\NotFoundException;

final readonly class GetMediaUrlHandler
{
    public function __construct(
        private MediaRepository $mediaRepository,
        private MediaImageConversionRepository $mediaImageConversionRepository,
        private MediaVideoConversionRepository $mediaVideoConversionRepository,
        private MediaAudioConversionRepository $mediaAudioConversionRepository,
        private MediaFileServiceContract $mediaFileService,
    ) {}

    public function handle(GetMediaUrlQuery $query): MediaUrlResult
    {
        $media = $this->mediaRepository->findById(MediaId::fromString($query->mediaId))
            ?? throw new NotFoundException('app.media.not_found');

        if (!$media->isReady()) {
            throw new NotFoundException('app.media.not_ready');
        }

        if ($query->conversionType !== null) {
            $conversion = $this->findConversion(mediaId: $media->id, type: $query->conversionType)
                ?? throw new NotFoundException('app.media.conversion_not_found');

            return $this->buildUrl(
                visibility: $media->visibility,
                storage: $conversion->storage,
                path: $conversion->path,
                presignedTtlSeconds: $query->presignedTtlSeconds,
            );
        }

        return $this->buildUrl(
            visibility: $media->visibility,
            storage: $media->storage,
            path: $media->path,
            presignedTtlSeconds: $query->presignedTtlSeconds,
        );
    }

    private function findConversion(
        MediaId $mediaId,
        MediaImageConversionType|MediaVideoConversionType|MediaAudioConversionType $type,
    ): MediaImageConversion|MediaVideoConversion|MediaAudioConversion|null {
        // У изображений несколько типов — выбираем нужный по type. У видео и аудио каталог типов
        // содержит ровно один профиль и на медиа не больше одной такой конверсии, поэтому берём
        // первую (фильтр по единственному типу был бы всегда истинным).
        return match (true) {
            $type instanceof MediaImageConversionType => $this->mediaImageConversionRepository->findByMediaId($mediaId)->first(
                static fn(MediaImageConversion $conversion): bool => $conversion->type === $type,
            ),
            $type instanceof MediaVideoConversionType => $this->mediaVideoConversionRepository->findByMediaId($mediaId)->first(),
            $type instanceof MediaAudioConversionType => $this->mediaAudioConversionRepository->findByMediaId($mediaId)->first(),
        };
    }

    private function buildUrl(
        MediaVisibility $visibility,
        MediaStorage $storage,
        MediaPath $path,
        int $presignedTtlSeconds,
    ): MediaUrlResult {
        if ($visibility === MediaVisibility::Public) {
            return new MediaUrlResult(
                url: $this->mediaFileService->publicUrl(storage: $storage, path: $path),
                expiresAt: null,
            );
        }

        $expiresAt = new \DateTimeImmutable()->add(
            new \DateInterval(\sprintf('PT%dS', MediaPresignedTtl::fromInt($presignedTtlSeconds)->value())),
        );

        return new MediaUrlResult(
            url: $this->mediaFileService->presignGet(storage: $storage, path: $path, expiresAt: $expiresAt),
            expiresAt: $expiresAt,
        );
    }
}

exec
/bin/zsh -lc "sed -n '1,220p' app/src/Modules/Media/Application/Contract/MediaFileServiceContract.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Contract;

use App\Modules\Media\Application\Dto\MediaObjectHead;
use App\Modules\Media\Application\Dto\MediaPresignedPartCollection;
use App\Modules\Media\Domain\Collection\MediaMultipartPartCollection;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Modules\Media\Domain\ValueObject\MediaMultipartPartsCount;
use App\Modules\Media\Domain\ValueObject\MediaMultipartUploadIdValue;
use App\Modules\Media\Domain\ValueObject\MediaPath;

/**
 * Контракт серверных файловых операций модуля Media поверх S3-совместимого хранилища.
 *
 * Реальное имя бакета и prefix реализация резолвит из StorageConfig по алиасу MediaStorage,
 * а не из значения enum напрямую. Срок действия presigned-ссылок задаёт потребитель — через
 * MediaUploadSpec (загрузка) и GetMediaUrlQuery (скачивание), поэтому presign-методы принимают expiresAt.
 */
interface MediaFileServiceContract
{
    /**
     * Presigned PUT-ссылка для прямой одиночной загрузки клиентом.
     */
    public function presignPut(
        MediaStorage $storage,
        MediaPath $path,
        MediaMimeType $mimeType,
        \DateTimeImmutable $expiresAt,
    ): string;

    /**
     * Инициирует multipart-загрузку и возвращает её uploadId.
     */
    public function createMultipartUpload(
        MediaStorage $storage,
        MediaPath $path,
        MediaMimeType $mimeType,
    ): MediaMultipartUploadIdValue;

    /**
     * Presigned-ссылки на загрузку каждой части (1..partsCount).
     */
    public function presignUploadParts(
        MediaStorage $storage,
        MediaPath $path,
        MediaMultipartUploadIdValue $uploadId,
        MediaMultipartPartsCount $partsCount,
        \DateTimeImmutable $expiresAt,
    ): MediaPresignedPartCollection;

    /**
     * Собирает multipart-объект из загруженных частей.
     *
     * На повторе после частичного сбоя S3 может вернуть NoSuchUpload — реализация трактует
     * это как успех, если headObject подтверждает собранный объект.
     */
    public function completeMultipartUpload(
        MediaStorage $storage,
        MediaPath $path,
        MediaMultipartUploadIdValue $uploadId,
        MediaMultipartPartCollection $parts,
    ): void;

    /**
     * Отменяет незавершённую multipart-загрузку. 404/NoSuchUpload игнорируется.
     */
    public function abortMultipartUpload(
        MediaStorage $storage,
        MediaPath $path,
        MediaMultipartUploadIdValue $uploadId,
    ): void;

    /**
     * Метаданные объекта. null — объекта нет.
     */
    public function headObject(MediaStorage $storage, MediaPath $path): MediaObjectHead|null;

    /**
     * Содержимое объекта (для чтения оригинала перед конверсией).
     */
    public function getObjectContents(MediaStorage $storage, MediaPath $path): string;

    /**
     * Скачивает объект в локальный временный файл и возвращает его путь. Стримовое чтение без
     * полного буфера в памяти (для ffmpeg-обработки видео/аудио). Владелец файла — вызыватель:
     * он обязан удалить его после использования (процессор делает это в finally).
     */
    public function downloadToFile(MediaStorage $storage, MediaPath $path): string;

    /**
     * Заливает локальный файл (результат ffmpeg-обработки) в хранилище стримом, без полного
     * буфера в памяти. Локальный файл не удаляется — его владелец вызыватель.
     */
    public function uploadFromFile(
        MediaStorage $storage,
        MediaPath $path,
        string $localFile,
        MediaMimeType $mimeType,
    ): void;

    /**
     * Заливает объект (результат конверсии) в хранилище.
     */
    public function putObject(
        MediaStorage $storage,
        MediaPath $path,
        string $contents,
        MediaMimeType $mimeType,
    ): void;

    /**
     * Копирует объект между хранилищами/путями (перекладка оригинала upload -> target).
     */
    public function copyObject(
        MediaStorage $fromStorage,
        MediaPath $fromPath,
        MediaStorage $toStorage,
        MediaPath $toPath,
    ): void;

    /**
     * Удаляет объект. 404 игнорируется (идемпотентное удаление).
     */
    public function deleteObject(MediaStorage $storage, MediaPath $path): void;

    /**
     * Presigned GET-ссылка с TTL (для private-медиа).
     */
    public function presignGet(MediaStorage $storage, MediaPath $path, \DateTimeImmutable $expiresAt): string;

    /**
     * Прямой публичный URL (для public-медиа, бакет с anonymous-read policy).
     */
    public function publicUrl(MediaStorage $storage, MediaPath $path): string;
}

exec
/bin/zsh -lc 'rg -n "class GetMediaUrl|presignGet|directUrl|public" app/src/Modules/Media -S' in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
app/src/Modules/Media/Infrastructure/FileService/NativeAacAudioFormat.php:20:    public function __construct(
app/src/Modules/Media/Infrastructure/FileService/NativeAacAudioFormat.php:30:    public function getExtraParams(): array
app/src/Modules/Media/Infrastructure/FileService/NativeAacAudioFormat.php:39:    public function getAvailableAudioCodecs(): array
app/src/Modules/Media/Application/Exception/MediaFileServiceFailedException.php:29:    public static function transient(string $message, \Throwable $previous): self
app/src/Modules/Media/Application/Exception/MediaFileServiceFailedException.php:37:    public static function permanent(string $message, \Throwable|null $previous = null): self
app/src/Modules/Media/Application/Exception/MediaFileServiceFailedException.php:42:    public function isTransient(): bool
app/src/Modules/Media/Repository/MediaAudioConversionRepository.php:17:    public function findByMediaId(MediaId $mediaId): MediaAudioConversionCollection
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php:36:    public function __construct(
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php:47:    public function handle(CompleteMediaUploadCommand $command): MediaResult
app/src/Modules/Media/Infrastructure/FileService/ImagickMediaImageProcessor.php:27:    public function __construct(MediaConfig $mediaConfig)
app/src/Modules/Media/Infrastructure/FileService/ImagickMediaImageProcessor.php:33:    public function resize(
app/src/Modules/Media/Application/Exception/MediaProcessorFailedException.php:30:    public static function transient(string $message, \Throwable $previous): self
app/src/Modules/Media/Application/Exception/MediaProcessorFailedException.php:38:    public static function permanent(string $message, \Throwable|null $previous = null): self
app/src/Modules/Media/Application/Exception/MediaProcessorFailedException.php:43:    public function isTransient(): bool
app/src/Modules/Media/Repository/MediaImageConversionRepository.php:17:    public function findByMediaId(MediaId $mediaId): MediaImageConversionCollection
app/src/Modules/Media/Domain/Enum/MediaStorage.php:11:    case Public = 'media-public';
app/src/Modules/Media/Repository/MediaVideoConversionRepository.php:17:    public function findByMediaId(MediaId $mediaId): MediaVideoConversionCollection
app/src/Modules/Media/Repository/MediaMultipartUploadRepository.php:16:    public function findByMediaId(MediaId $mediaId): MediaMultipartUpload|null
app/src/Modules/Media/Infrastructure/FileService/ConfiguredS3ClientProvider.php:16:    public function forServer(StorageServerConfig $serverConfig): S3Client
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadCommand.php:12:    public function __construct(
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadCommand.php:13:        public string $userId,
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadCommand.php:14:        public string $mediaId,
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadCommand.php:15:        public MediaConversionPlan $plan,
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadCommand.php:16:        public MediaMultipartPartCollection|null $parts,
app/src/Modules/Media/Repository/MediaRepository.php:18:    public function findById(MediaId $mediaId): Media|null
app/src/Modules/Media/Repository/MediaRepository.php:23:    public function findByStorageKey(MediaStorageKey $storageKey): Media|null
app/src/Modules/Media/Repository/MediaRepository.php:28:    public function findExpired(\DateTimeImmutable $now): MediaCollection
app/src/Modules/Media/Infrastructure/FileService/AbstractFfmpegMediaProcessor.php:25:    public function __construct(
app/src/Modules/Media/Application/Query/CheckMediaIsImage/CheckMediaIsImageHandler.php:13:    public function __construct(
app/src/Modules/Media/Application/Query/CheckMediaIsImage/CheckMediaIsImageHandler.php:17:    public function handle(CheckMediaIsImageQuery $query): bool
app/src/Modules/Media/Application/Command/DeleteMedia/DeleteMediaHandler.php:25:    public function __construct(
app/src/Modules/Media/Application/Command/DeleteMedia/DeleteMediaHandler.php:37:    public function handle(DeleteMediaCommand $command): void
app/src/Modules/Media/Domain/Enum/MediaVisibility.php:10:    case Public = 'public';
app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaAudioProcessor.php:42:    public function process(
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadCommand.php:12:    public function __construct(
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadCommand.php:13:        public string $userId,
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadCommand.php:14:        public MediaUploadSpec $spec,
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadCommand.php:15:        public MediaFileMeta $fileMeta,
app/src/Modules/Media/Application/Contract/MediaImageProcessorContract.php:19:    public function resize(
app/src/Modules/Media/Application/Query/CheckMediaIsImage/CheckMediaIsImageQuery.php:9:    public function __construct(
app/src/Modules/Media/Application/Query/CheckMediaIsImage/CheckMediaIsImageQuery.php:10:        public string $mediaId,
app/src/Modules/Media/Application/Command/DeleteMedia/DeleteMediaCommand.php:9:    public function __construct(
app/src/Modules/Media/Application/Command/DeleteMedia/DeleteMediaCommand.php:10:        public string $userId,
app/src/Modules/Media/Application/Command/DeleteMedia/DeleteMediaCommand.php:11:        public string $mediaId,
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php:29:    public function __construct(
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php:38:    public function handle(RequestMediaUploadCommand $command): RequestMediaUploadResult
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:43:    public function __construct(
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:49:    public function presignPut(
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:66:    public function createMultipartUpload(
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:89:    public function presignUploadParts(
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:119:    public function completeMultipartUpload(
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:153:    public function abortMultipartUpload(
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:174:    public function headObject(MediaStorage $storage, MediaPath $path): MediaObjectHead|null
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:197:    public function getObjectContents(MediaStorage $storage, MediaPath $path): string
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:216:    public function downloadToFile(MediaStorage $storage, MediaPath $path): string
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:242:    public function uploadFromFile(
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:261:    public function putObject(
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:280:    public function copyObject(
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:300:    public function deleteObject(MediaStorage $storage, MediaPath $path): void
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:317:    public function presignGet(MediaStorage $storage, MediaPath $path, \DateTimeImmutable $expiresAt): string
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:329:    public function publicUrl(MediaStorage $storage, MediaPath $path): string
app/src/Modules/Media/Application/Contract/MediaVideoProcessorContract.php:22:    public function process(
app/src/Modules/Media/Domain/Entity/MediaMultipartUpload.php:34:    public private(set) MediaMultipartUploadId $id;
app/src/Modules/Media/Domain/Entity/MediaMultipartUpload.php:37:    public private(set) MediaId $mediaId;
app/src/Modules/Media/Domain/Entity/MediaMultipartUpload.php:40:    public private(set) MediaMultipartUploadIdValue $uploadId;
app/src/Modules/Media/Domain/Entity/MediaMultipartUpload.php:43:    public private(set) MediaMultipartPartsCount $partsCount;
app/src/Modules/Media/Domain/Entity/MediaMultipartUpload.php:46:    public private(set) MediaMultipartPartSize $partSize;
app/src/Modules/Media/Domain/Entity/MediaMultipartUpload.php:49:    public private(set) MediaFileSize $fileSize;
app/src/Modules/Media/Domain/Entity/MediaMultipartUpload.php:52:    public private(set) MediaMultipartPartCollection $parts;
app/src/Modules/Media/Domain/Entity/MediaMultipartUpload.php:55:    public private(set) Media $media;
app/src/Modules/Media/Domain/Entity/MediaMultipartUpload.php:57:    public static function create(
app/src/Modules/Media/Domain/Entity/MediaMultipartUpload.php:78:    public function replaceParts(MediaMultipartPartCollection $parts): void
app/src/Modules/Media/Presentation/Job/ProcessMediaJob.php:34:    public function invoke(
app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaVideoProcessor.php:38:    public function process(
app/src/Modules/Media/Application/Query/GetAudioWaveform/GetAudioWaveformHandler.php:20:    public function __construct(
app/src/Modules/Media/Application/Query/GetAudioWaveform/GetAudioWaveformHandler.php:25:    public function handle(GetAudioWaveformQuery $query): MediaWaveform
app/src/Modules/Media/Application/Contract/MediaAudioProcessorContract.php:22:    public function process(
app/src/Modules/Media/README.md:25:| `GetMediaUrl(mediaId, presignedTtlSeconds, conversionType?)` | Query | `MediaUrlResult` (`conversionType` — union image/video/audio-enum или null; для public-медиа `presignedTtlSeconds` игнорируется и не валидируется — прямой URL без срока; срок применяется только для private) |
app/src/Modules/Media/README.md:68:6. GetMediaUrl: public -> прямой URL (media-public, anonymous read); private -> presignGet
app/src/Modules/Media/README.md:118:- Оригинал И конверсии живут в одном бакете по `visibility` (`media-public`/`media-private`),
app/src/Modules/Media/README.md:195:- `media-public` должен иметь anonymous-read policy (в dev ставится `ensure-buckets.sh`; в
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaCommand.php:11:    public function __construct(
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaCommand.php:12:        public string $mediaId,
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaCommand.php:13:        public MediaConversionPlan $plan,
app/src/Modules/Media/Domain/Entity/Media.php:45:    public private(set) MediaId $id;
app/src/Modules/Media/Domain/Entity/Media.php:48:    public private(set) MediaStorageKey $storageKey;
app/src/Modules/Media/Domain/Entity/Media.php:51:    public private(set) MediaType $type;
app/src/Modules/Media/Domain/Entity/Media.php:54:    public private(set) MediaStatus $status;
app/src/Modules/Media/Domain/Entity/Media.php:57:    public private(set) MediaVisibility $visibility;
app/src/Modules/Media/Domain/Entity/Media.php:60:    public private(set) MediaStorage $storage;
app/src/Modules/Media/Domain/Entity/Media.php:63:    public private(set) MediaPath $path;
app/src/Modules/Media/Domain/Entity/Media.php:66:    public private(set) MediaMimeType $mimeType;
app/src/Modules/Media/Domain/Entity/Media.php:69:    public private(set) MediaFileSize $size;
app/src/Modules/Media/Domain/Entity/Media.php:72:    public private(set) UserId $uploadedById;
app/src/Modules/Media/Domain/Entity/Media.php:75:    public private(set) MediaExpiration $expiration;
app/src/Modules/Media/Domain/Entity/Media.php:78:    public private(set) MediaProcessingAttempts $processingAttempts;
app/src/Modules/Media/Domain/Entity/Media.php:81:    public private(set) MediaProcessingError $processingError;
app/src/Modules/Media/Domain/Entity/Media.php:90:    public private(set) MediaImageConversionCollection $imageConversions;
app/src/Modules/Media/Domain/Entity/Media.php:99:    public private(set) MediaVideoConversionCollection $videoConversions;
app/src/Modules/Media/Domain/Entity/Media.php:108:    public private(set) MediaAudioConversionCollection $audioConversions;
app/src/Modules/Media/Domain/Entity/Media.php:110:    public static function create(
app/src/Modules/Media/Domain/Entity/Media.php:142:    public function startCompletingMultipartUpload(): void
app/src/Modules/Media/Domain/Entity/Media.php:148:    public function markMultipartCompletionFailedCanRetry(MediaProcessingError $processingError): void
app/src/Modules/Media/Domain/Entity/Media.php:155:    public function markMultipartCompletionFailedNeedReupload(MediaProcessingError $processingError): void
app/src/Modules/Media/Domain/Entity/Media.php:162:    public function markUploaded(): void
app/src/Modules/Media/Domain/Entity/Media.php:169:    public function startProcessing(): void
app/src/Modules/Media/Domain/Entity/Media.php:181:    public function recordTemporaryProcessingError(MediaProcessingError $processingError): void
app/src/Modules/Media/Domain/Entity/Media.php:197:    public function recordPermanentProcessingError(MediaProcessingError $processingError): void
app/src/Modules/Media/Domain/Entity/Media.php:209:    public function markReady(): void
app/src/Modules/Media/Domain/Entity/Media.php:221:    public function markReadyMovedTo(MediaStorage $storage, MediaPath $path): void
app/src/Modules/Media/Domain/Entity/Media.php:244:    public function isReady(): bool
app/src/Modules/Media/Domain/Entity/Media.php:249:    public function markReadyOriginalRemoved(): void
app/src/Modules/Media/Domain/Entity/Media.php:256:    public function makePermanent(): void
app/src/Modules/Media/Infrastructure/FileService/S3ClientProvider.php:17:    public function forServer(StorageServerConfig $serverConfig): S3Client;
app/src/Modules/Media/Application/Query/GetAudioWaveform/GetAudioWaveformQuery.php:9:    public function __construct(
app/src/Modules/Media/Application/Query/GetAudioWaveform/GetAudioWaveformQuery.php:10:        public string $mediaId,
app/src/Modules/Media/Application/Contract/MediaFileServiceContract.php:28:    public function presignPut(
app/src/Modules/Media/Application/Contract/MediaFileServiceContract.php:38:    public function createMultipartUpload(
app/src/Modules/Media/Application/Contract/MediaFileServiceContract.php:47:    public function presignUploadParts(
app/src/Modules/Media/Application/Contract/MediaFileServiceContract.php:61:    public function completeMultipartUpload(
app/src/Modules/Media/Application/Contract/MediaFileServiceContract.php:71:    public function abortMultipartUpload(
app/src/Modules/Media/Application/Contract/MediaFileServiceContract.php:80:    public function headObject(MediaStorage $storage, MediaPath $path): MediaObjectHead|null;
app/src/Modules/Media/Application/Contract/MediaFileServiceContract.php:85:    public function getObjectContents(MediaStorage $storage, MediaPath $path): string;
app/src/Modules/Media/Application/Contract/MediaFileServiceContract.php:92:    public function downloadToFile(MediaStorage $storage, MediaPath $path): string;
app/src/Modules/Media/Application/Contract/MediaFileServiceContract.php:98:    public function uploadFromFile(
app/src/Modules/Media/Application/Contract/MediaFileServiceContract.php:108:    public function putObject(
app/src/Modules/Media/Application/Contract/MediaFileServiceContract.php:118:    public function copyObject(
app/src/Modules/Media/Application/Contract/MediaFileServiceContract.php:128:    public function deleteObject(MediaStorage $storage, MediaPath $path): void;
app/src/Modules/Media/Application/Contract/MediaFileServiceContract.php:133:    public function presignGet(MediaStorage $storage, MediaPath $path, \DateTimeImmutable $expiresAt): string;
app/src/Modules/Media/Application/Contract/MediaFileServiceContract.php:136:     * Прямой публичный URL (для public-медиа, бакет с anonymous-read policy).
app/src/Modules/Media/Application/Contract/MediaFileServiceContract.php:138:    public function publicUrl(MediaStorage $storage, MediaPath $path): string;
app/src/Modules/Media/Application/Message/MediaUploaded.php:18:    public function __construct(
app/src/Modules/Media/Application/Message/MediaUploaded.php:19:        public string $mediaId,
app/src/Modules/Media/Application/Message/MediaUploaded.php:20:        public MediaConversionPlan $plan,
app/src/Modules/Media/Domain/Entity/MediaImageConversion.php:35:    public private(set) MediaImageConversionId $id;
app/src/Modules/Media/Domain/Entity/MediaImageConversion.php:38:    public private(set) MediaId $mediaId;
app/src/Modules/Media/Domain/Entity/MediaImageConversion.php:41:    public private(set) MediaImageConversionType $type;
app/src/Modules/Media/Domain/Entity/MediaImageConversion.php:44:    public private(set) MediaConversionStatus $status;
app/src/Modules/Media/Domain/Entity/MediaImageConversion.php:47:    public private(set) MediaStorage $storage;
app/src/Modules/Media/Domain/Entity/MediaImageConversion.php:50:    public private(set) MediaPath $path;
app/src/Modules/Media/Domain/Entity/MediaImageConversion.php:53:    public private(set) MediaMimeType $mimeType;
app/src/Modules/Media/Domain/Entity/MediaImageConversion.php:56:    public private(set) MediaFileSize $size;
app/src/Modules/Media/Domain/Entity/MediaImageConversion.php:59:    public private(set) MediaPixelDimension $width;
app/src/Modules/Media/Domain/Entity/MediaImageConversion.php:62:    public private(set) MediaPixelDimension $height;
app/src/Modules/Media/Domain/Entity/MediaImageConversion.php:65:    public private(set) Media $media;
app/src/Modules/Media/Domain/Entity/MediaImageConversion.php:67:    public static function create(
app/src/Modules/Media/Infrastructure/Exception/MediaImageProcessorException.php:9:    public static function unsupportedDriver(string $driver): self
app/src/Modules/Media/Application/Query/CheckMediaExists/CheckMediaExistsHandler.php:12:    public function __construct(
app/src/Modules/Media/Application/Query/CheckMediaExists/CheckMediaExistsHandler.php:16:    public function handle(CheckMediaExistsQuery $query): bool
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:41:    public function __construct(
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:52:    public function handle(ProcessMediaCommand $command): void
app/src/Modules/Media/Domain/Entity/MediaVideoConversion.php:37:    public private(set) MediaVideoConversionId $id;
app/src/Modules/Media/Domain/Entity/MediaVideoConversion.php:40:    public private(set) MediaId $mediaId;
app/src/Modules/Media/Domain/Entity/MediaVideoConversion.php:43:    public private(set) MediaVideoConversionType $type;
app/src/Modules/Media/Domain/Entity/MediaVideoConversion.php:46:    public private(set) MediaConversionStatus $status;
app/src/Modules/Media/Domain/Entity/MediaVideoConversion.php:49:    public private(set) MediaStorage $storage;
app/src/Modules/Media/Domain/Entity/MediaVideoConversion.php:52:    public private(set) MediaPath $path;
app/src/Modules/Media/Domain/Entity/MediaVideoConversion.php:55:    public private(set) MediaMimeType $mimeType;
app/src/Modules/Media/Domain/Entity/MediaVideoConversion.php:58:    public private(set) MediaFileSize $size;
app/src/Modules/Media/Domain/Entity/MediaVideoConversion.php:61:    public private(set) MediaPixelDimension $width;
app/src/Modules/Media/Domain/Entity/MediaVideoConversion.php:64:    public private(set) MediaPixelDimension $height;
app/src/Modules/Media/Domain/Entity/MediaVideoConversion.php:67:    public private(set) MediaDuration $duration;
app/src/Modules/Media/Domain/Entity/MediaVideoConversion.php:70:    public private(set) MediaBitrate $bitrate;
app/src/Modules/Media/Domain/Entity/MediaVideoConversion.php:73:    public private(set) Media $media;
app/src/Modules/Media/Domain/Entity/MediaVideoConversion.php:75:    public static function create(
app/src/Modules/Media/Application/Command/MakeMediaPermanent/MakeMediaPermanentHandler.php:20:    public function __construct(
app/src/Modules/Media/Application/Command/MakeMediaPermanent/MakeMediaPermanentHandler.php:26:    public function handle(MakeMediaPermanentCommand $command): MediaResult
app/src/Modules/Media/Infrastructure/Exception/MediaStorageNotConfiguredException.php:11:    public static function bucketAliasMissing(MediaStorage $storage): self
app/src/Modules/Media/Infrastructure/Exception/MediaStorageNotConfiguredException.php:16:    public static function bucketNameMissing(MediaStorage $storage): self
app/src/Modules/Media/Infrastructure/Exception/MediaStorageNotConfiguredException.php:21:    public static function serverMissing(MediaStorage $storage, string $server): self
app/src/Modules/Media/Application/Dto/MediaVideoProcessingResult.php:21:    public function __construct(
app/src/Modules/Media/Application/Dto/MediaVideoProcessingResult.php:22:        public MediaMimeType $normalizedMimeType,
app/src/Modules/Media/Application/Dto/MediaVideoProcessingResult.php:23:        public MediaFileSize $normalizedSize,
app/src/Modules/Media/Application/Dto/MediaVideoProcessingResult.php:24:        public MediaPixelDimension $width,
app/src/Modules/Media/Application/Dto/MediaVideoProcessingResult.php:25:        public MediaPixelDimension $height,
app/src/Modules/Media/Application/Dto/MediaVideoProcessingResult.php:26:        public MediaDuration $duration,
app/src/Modules/Media/Application/Dto/MediaVideoProcessingResult.php:27:        public MediaBitrate $bitrate,
app/src/Modules/Media/Application/Dto/MediaVideoProcessingResult.php:28:        public MediaMimeType $posterMimeType,
app/src/Modules/Media/Application/Dto/MediaVideoProcessingResult.php:29:        public MediaFileSize $posterSize,
app/src/Modules/Media/Application/Query/GetMediaUrl/GetMediaUrlQuery.php:11:final readonly class GetMediaUrlQuery
app/src/Modules/Media/Application/Query/GetMediaUrl/GetMediaUrlQuery.php:13:    public function __construct(
app/src/Modules/Media/Application/Query/GetMediaUrl/GetMediaUrlQuery.php:14:        public string $mediaId,
app/src/Modules/Media/Application/Query/GetMediaUrl/GetMediaUrlQuery.php:15:        public int $presignedTtlSeconds,
app/src/Modules/Media/Application/Query/GetMediaUrl/GetMediaUrlQuery.php:16:        public MediaImageConversionType|MediaVideoConversionType|MediaAudioConversionType|null $conversionType = null,
app/src/Modules/Media/Application/Query/CheckMediaExists/CheckMediaExistsQuery.php:9:    public function __construct(
app/src/Modules/Media/Application/Query/CheckMediaExists/CheckMediaExistsQuery.php:10:        public string $mediaId,
app/src/Modules/Media/Domain/Entity/MediaAudioConversion.php:39:    public private(set) MediaAudioConversionId $id;
app/src/Modules/Media/Domain/Entity/MediaAudioConversion.php:42:    public private(set) MediaId $mediaId;
app/src/Modules/Media/Domain/Entity/MediaAudioConversion.php:45:    public private(set) MediaAudioConversionType $type;
app/src/Modules/Media/Domain/Entity/MediaAudioConversion.php:48:    public private(set) MediaConversionStatus $status;
app/src/Modules/Media/Domain/Entity/MediaAudioConversion.php:51:    public private(set) MediaStorage $storage;
app/src/Modules/Media/Domain/Entity/MediaAudioConversion.php:54:    public private(set) MediaPath $path;
app/src/Modules/Media/Domain/Entity/MediaAudioConversion.php:57:    public private(set) MediaMimeType $mimeType;
app/src/Modules/Media/Domain/Entity/MediaAudioConversion.php:60:    public private(set) MediaFileSize $size;
app/src/Modules/Media/Domain/Entity/MediaAudioConversion.php:63:    public private(set) MediaDuration $duration;
app/src/Modules/Media/Domain/Entity/MediaAudioConversion.php:66:    public private(set) MediaBitrate $bitrate;
app/src/Modules/Media/Domain/Entity/MediaAudioConversion.php:69:    public private(set) MediaSampleRate $sampleRate;
app/src/Modules/Media/Domain/Entity/MediaAudioConversion.php:72:    public private(set) MediaWaveform $waveform;
app/src/Modules/Media/Domain/Entity/MediaAudioConversion.php:75:    public private(set) Media $media;
app/src/Modules/Media/Domain/Entity/MediaAudioConversion.php:77:    public static function create(
app/src/Modules/Media/Application/Command/MakeMediaPermanent/MakeMediaPermanentCommand.php:9:    public function __construct(
app/src/Modules/Media/Application/Command/MakeMediaPermanent/MakeMediaPermanentCommand.php:10:        public string $userId,
app/src/Modules/Media/Application/Command/MakeMediaPermanent/MakeMediaPermanentCommand.php:11:        public string $mediaId,
app/src/Modules/Media/Application/Dto/MediaUploadSpec.php:18:    public function __construct(
app/src/Modules/Media/Application/Dto/MediaUploadSpec.php:19:        public MediaMimeTypeCollection $allowedMimeTypes,
app/src/Modules/Media/Application/Dto/MediaUploadSpec.php:20:        public MediaFileSize $maxSize,
app/src/Modules/Media/Application/Dto/MediaUploadSpec.php:21:        public MediaVisibility $visibility,
app/src/Modules/Media/Application/Dto/MediaUploadSpec.php:22:        public MediaPresignedTtl $presignedTtl,
app/src/Modules/Media/Application/Query/GetMediaUrl/GetMediaUrlHandler.php:26:final readonly class GetMediaUrlHandler
app/src/Modules/Media/Application/Query/GetMediaUrl/GetMediaUrlHandler.php:28:    public function __construct(
app/src/Modules/Media/Application/Query/GetMediaUrl/GetMediaUrlHandler.php:36:    public function handle(GetMediaUrlQuery $query): MediaUrlResult
app/src/Modules/Media/Application/Query/GetMediaUrl/GetMediaUrlHandler.php:89:                url: $this->mediaFileService->publicUrl(storage: $storage, path: $path),
app/src/Modules/Media/Application/Query/GetMediaUrl/GetMediaUrlHandler.php:99:            url: $this->mediaFileService->presignGet(storage: $storage, path: $path, expiresAt: $expiresAt),
app/src/Modules/Media/Infrastructure/Cycle/MediaMultipartPartCollectionTypecast.php:13:    public static function castDatabaseValue(
app/src/Modules/Media/Infrastructure/Cycle/MediaMultipartPartCollectionTypecast.php:45:    public static function uncastValue(
app/src/Modules/Media/Application/Dto/MediaPresignedPart.php:12:    public function __construct(
app/src/Modules/Media/Application/Dto/MediaPresignedPart.php:13:        public int $partNumber,
app/src/Modules/Media/Application/Dto/MediaPresignedPart.php:14:        public string $url,
app/src/Modules/Media/Domain/Collection/MediaMimeTypeCollection.php:15:    public function containsMimeType(MediaMimeType $mimeType): bool
app/src/Modules/Media/Application/Command/RecordMediaProcessingFailure/RecordMediaProcessingFailureHandler.php:22:    public function __construct(
app/src/Modules/Media/Application/Command/RecordMediaProcessingFailure/RecordMediaProcessingFailureHandler.php:28:    public function handle(RecordMediaProcessingFailureCommand $command): void
app/src/Modules/Media/Infrastructure/Cycle/MediaWaveformTypecast.php:12:    public static function castDatabaseValue(string $value): MediaWaveform
app/src/Modules/Media/Infrastructure/Cycle/MediaWaveformTypecast.php:36:    public static function uncastValue(MediaWaveform $value): string
app/src/Modules/Media/Application/Dto/MediaObjectHead.php:15:    public function __construct(
app/src/Modules/Media/Application/Dto/MediaObjectHead.php:16:        public MediaFileSize $contentLength,
app/src/Modules/Media/Domain/ValueObject/MediaProcessingError.php:17:    public static function none(): self
app/src/Modules/Media/Domain/ValueObject/MediaProcessingError.php:22:    public static function fromString(string $value): self
app/src/Modules/Media/Domain/ValueObject/MediaProcessingError.php:43:    public function value(): string|null
app/src/Modules/Media/Domain/ValueObject/MediaProcessingError.php:48:    public function isEmpty(): bool
app/src/Modules/Media/Domain/ValueObject/MediaProcessingError.php:53:    public function equals(self $other): bool
app/src/Modules/Media/Domain/ValueObject/MediaProcessingError.php:59:    public function __toString(): string
app/src/Modules/Media/Domain/ValueObject/MediaProcessingError.php:65:    public function jsonSerialize(): string
app/src/Modules/Media/Domain/Collection/MediaMultipartPartCollection.php:19:    public function __construct(iterable $parts = [])
app/src/Modules/Media/Domain/Collection/MediaMultipartPartCollection.php:27:    public function jsonSerialize(): array
app/src/Modules/Media/Application/Command/RecordMediaProcessingFailure/RecordMediaProcessingFailureCommand.php:9:    public function __construct(
app/src/Modules/Media/Application/Command/RecordMediaProcessingFailure/RecordMediaProcessingFailureCommand.php:10:        public string $mediaId,
app/src/Modules/Media/Application/Command/RecordMediaProcessingFailure/RecordMediaProcessingFailureCommand.php:11:        public string $error,
app/src/Modules/Media/Application/Command/RecordMediaProcessingFailure/RecordMediaProcessingFailureCommand.php:12:        public bool $isTransient,
app/src/Modules/Media/Infrastructure/Cycle/MediaProcessingErrorTypecast.php:12:    public static function castDatabaseValue(
app/src/Modules/Media/Infrastructure/Cycle/MediaProcessingErrorTypecast.php:22:    public static function uncastValue(
app/src/Modules/Media/Infrastructure/Cycle/MediaExpirationTypecast.php:12:    public static function castDatabaseValue(
app/src/Modules/Media/Infrastructure/Cycle/MediaExpirationTypecast.php:30:    public static function uncastValue(
app/src/Modules/Media/Application/Dto/MediaUrlResult.php:8: * URL к медиа или его конверсии. expiresAt = null для прямого публичного URL (media-public),
app/src/Modules/Media/Application/Dto/MediaUrlResult.php:13:    public function __construct(
app/src/Modules/Media/Application/Dto/MediaUrlResult.php:14:        public string $url,
app/src/Modules/Media/Application/Dto/MediaUrlResult.php:15:        public \DateTimeImmutable|null $expiresAt,
app/src/Modules/Media/Domain/ValueObject/MediaMultipartPart.php:10:        public MediaMultipartPartNumber $partNumber,
app/src/Modules/Media/Domain/ValueObject/MediaMultipartPart.php:11:        public MediaMultipartPartETag $eTag,
app/src/Modules/Media/Domain/ValueObject/MediaMultipartPart.php:14:    public static function create(MediaMultipartPartNumber $partNumber, MediaMultipartPartETag $eTag): self
app/src/Modules/Media/Domain/ValueObject/MediaMultipartPart.php:19:    public static function fromValues(int $partNumber, string $eTag): self
app/src/Modules/Media/Domain/ValueObject/MediaMultipartPart.php:27:    public function equals(self $other): bool
app/src/Modules/Media/Domain/ValueObject/MediaMultipartPart.php:37:    public function jsonSerialize(): array
app/src/Modules/Media/Application/Dto/MediaVideoConversionSpec.php:17:    public function __construct(
app/src/Modules/Media/Application/Dto/MediaVideoConversionSpec.php:18:        public MediaVideoConversionType $type,
app/src/Modules/Media/Application/Dto/MediaVideoConversionSpec.php:19:        public int $width,
app/src/Modules/Media/Application/Dto/MediaVideoConversionSpec.php:20:        public int $height,
app/src/Modules/Media/Application/Dto/MediaVideoConversionSpec.php:21:        public int $videoBitrate,
app/src/Modules/Media/Application/Dto/MediaVideoConversionSpec.php:22:        public int $audioBitrate,
app/src/Modules/Media/Application/Query/CheckMediaAttachable/CheckMediaAttachableHandler.php:22:    public function __construct(
app/src/Modules/Media/Application/Query/CheckMediaAttachable/CheckMediaAttachableHandler.php:27:    public function handle(CheckMediaAttachableQuery $query): MediaAttachableResult
app/src/Modules/Media/Application/Dto/MediaConversionResult.php:18:    public function __construct(
app/src/Modules/Media/Application/Dto/MediaConversionResult.php:19:        public string $contents,
app/src/Modules/Media/Application/Dto/MediaConversionResult.php:20:        public MediaMimeType $mimeType,
app/src/Modules/Media/Application/Dto/MediaConversionResult.php:21:        public MediaFileSize $size,
app/src/Modules/Media/Application/Dto/MediaConversionResult.php:22:        public MediaPixelDimension $width,
app/src/Modules/Media/Application/Dto/MediaConversionResult.php:23:        public MediaPixelDimension $height,
app/src/Modules/Media/Application/Dto/MediaFileMeta.php:16:    public function __construct(
app/src/Modules/Media/Application/Dto/MediaFileMeta.php:17:        public string $fileName,
app/src/Modules/Media/Application/Dto/MediaFileMeta.php:18:        public MediaMimeType $mimeType,
app/src/Modules/Media/Application/Dto/MediaFileMeta.php:19:        public MediaFileSize $size,
app/src/Modules/Media/Infrastructure/Bootloader/MediaBootloader.php:32:    public function boot(OutboxJobRegistryContract $outboxJobRegistry): void
app/src/Modules/Media/Application/Dto/MediaResult.php:16:    public function __construct(
app/src/Modules/Media/Application/Dto/MediaResult.php:17:        public string $mediaId,
app/src/Modules/Media/Application/Dto/MediaResult.php:18:        public MediaStatus $status,
app/src/Modules/Media/Application/Dto/MediaResult.php:19:        public MediaVisibility $visibility,
app/src/Modules/Media/Application/Dto/MediaResult.php:22:    public static function fromEntity(Media $media): self
app/src/Modules/Media/Application/Dto/MediaImageConversionSpec.php:17:    public function __construct(
app/src/Modules/Media/Application/Dto/MediaImageConversionSpec.php:18:        public MediaImageConversionType $type,
app/src/Modules/Media/Application/Dto/MediaImageConversionSpec.php:19:        public int $width,
app/src/Modules/Media/Application/Dto/MediaImageConversionSpec.php:20:        public int $height,
app/src/Modules/Media/Domain/ValueObject/MediaStorageKey.php:16:    public static function generate(): self
app/src/Modules/Media/Domain/ValueObject/MediaStorageKey.php:21:    public static function fromString(string $value): self
app/src/Modules/Media/Domain/ValueObject/MediaStorageKey.php:30:    public function value(): string
app/src/Modules/Media/Domain/ValueObject/MediaStorageKey.php:35:    public function shard(): string
app/src/Modules/Media/Domain/ValueObject/MediaStorageKey.php:40:    public function equals(self $other): bool
app/src/Modules/Media/Domain/ValueObject/MediaStorageKey.php:46:    public function __toString(): string
app/src/Modules/Media/Domain/ValueObject/MediaStorageKey.php:52:    public function jsonSerialize(): string
app/src/Modules/Media/Application/Dto/RequestMediaUploadResult.php:13:    public function __construct(
app/src/Modules/Media/Application/Dto/RequestMediaUploadResult.php:14:        public string $mediaId,
app/src/Modules/Media/Application/Dto/RequestMediaUploadResult.php:15:        public MediaUploadMode $uploadMode,
app/src/Modules/Media/Application/Dto/RequestMediaUploadResult.php:16:        public \DateTimeImmutable $expiresAt,
app/src/Modules/Media/Application/Dto/RequestMediaUploadResult.php:17:        public string|null $putUrl,
app/src/Modules/Media/Application/Dto/RequestMediaUploadResult.php:18:        public string|null $uploadId,
app/src/Modules/Media/Application/Dto/RequestMediaUploadResult.php:19:        public MediaPresignedPartCollection|null $parts,
app/src/Modules/Media/Application/Dto/RequestMediaUploadResult.php:22:    public static function single(string $mediaId, string $putUrl, \DateTimeImmutable $expiresAt): self
app/src/Modules/Media/Application/Dto/RequestMediaUploadResult.php:34:    public static function multipart(
app/src/Modules/Media/Application/Service/MediaTypeResolver.php:18:    public function resolve(MediaMimeType $mimeType): MediaType
app/src/Modules/Media/Application/Dto/MediaAudioConversionSpec.php:17:    public function __construct(
app/src/Modules/Media/Application/Dto/MediaAudioConversionSpec.php:18:        public MediaAudioConversionType $type,
app/src/Modules/Media/Application/Dto/MediaAudioConversionSpec.php:19:        public int $bitrate,
app/src/Modules/Media/Application/Dto/MediaAudioConversionSpec.php:20:        public int $sampleRate,
app/src/Modules/Media/Application/Dto/MediaAudioConversionSpec.php:21:        public int $waveformPeaks,
app/src/Modules/Media/Application/Query/CheckMediaAttachable/CheckMediaAttachableQuery.php:9:    public function __construct(
app/src/Modules/Media/Application/Query/CheckMediaAttachable/CheckMediaAttachableQuery.php:10:        public string $mediaId,
app/src/Modules/Media/Application/Query/CheckMediaAttachable/CheckMediaAttachableQuery.php:11:        public string $ownerUserId,
app/src/Modules/Media/Application/Query/CheckMediaAttachable/MediaAttachableResult.php:12:    public function __construct(
app/src/Modules/Media/Application/Query/CheckMediaAttachable/MediaAttachableResult.php:13:        public string $mediaId,
app/src/Modules/Media/Application/Dto/MediaAudioProcessingResult.php:21:    public function __construct(
app/src/Modules/Media/Application/Dto/MediaAudioProcessingResult.php:22:        public MediaMimeType $normalizedMimeType,
app/src/Modules/Media/Application/Dto/MediaAudioProcessingResult.php:23:        public MediaFileSize $normalizedSize,
app/src/Modules/Media/Application/Dto/MediaAudioProcessingResult.php:24:        public MediaDuration $duration,
app/src/Modules/Media/Application/Dto/MediaAudioProcessingResult.php:25:        public MediaBitrate $bitrate,
app/src/Modules/Media/Application/Dto/MediaAudioProcessingResult.php:26:        public MediaSampleRate $sampleRate,
app/src/Modules/Media/Application/Dto/MediaAudioProcessingResult.php:27:        public MediaWaveform $waveform,
app/src/Modules/Media/Application/Dto/MediaConversionPlan.php:21:    public function __construct(
app/src/Modules/Media/Application/Dto/MediaConversionPlan.php:22:        public array $image,
app/src/Modules/Media/Application/Dto/MediaConversionPlan.php:23:        public array $video,
app/src/Modules/Media/Application/Dto/MediaConversionPlan.php:24:        public array $audio,
app/src/Modules/Media/Application/Query/FindMediaUrl/FindMediaUrlHandler.php:22:    public function __construct(
app/src/Modules/Media/Application/Query/FindMediaUrl/FindMediaUrlHandler.php:28:    public function handle(FindMediaUrlQuery $query): MediaUrlResult|null
app/src/Modules/Media/Application/Query/FindMediaUrl/FindMediaUrlHandler.php:38:                url: $this->mediaFileService->publicUrl(storage: $media->storage, path: $media->path),
app/src/Modules/Media/Application/Query/FindMediaUrl/FindMediaUrlHandler.php:48:            url: $this->mediaFileService->presignGet(storage: $media->storage, path: $media->path, expiresAt: $expiresAt),
app/src/Modules/Media/Domain/ValueObject/MediaMimeType.php:17:    public static function fromString(string $value): self
app/src/Modules/Media/Domain/ValueObject/MediaMimeType.php:28:    public function value(): string
app/src/Modules/Media/Domain/ValueObject/MediaMimeType.php:33:    public function equals(self $other): bool
app/src/Modules/Media/Domain/ValueObject/MediaMimeType.php:39:    public function __toString(): string
app/src/Modules/Media/Domain/ValueObject/MediaMimeType.php:45:    public function jsonSerialize(): string
app/src/Modules/Media/Application/Query/FindMediaUrl/FindMediaUrlQuery.php:9:    public function __construct(
app/src/Modules/Media/Application/Query/FindMediaUrl/FindMediaUrlQuery.php:10:        public string $mediaId,
app/src/Modules/Media/Application/Query/FindMediaUrl/FindMediaUrlQuery.php:11:        public int $presignedTtlSeconds,
app/src/Modules/Media/Domain/ValueObject/MediaWaveform.php:31:    public static function fromPeaks(array $peaks): self
app/src/Modules/Media/Domain/ValueObject/MediaWaveform.php:51:    public function peaks(): array
app/src/Modules/Media/Domain/ValueObject/MediaWaveform.php:56:    public function equals(self $other): bool
app/src/Modules/Media/Domain/ValueObject/MediaWaveform.php:65:    public function jsonSerialize(): array
app/src/Modules/Media/Domain/ValueObject/MediaMultipartPartETag.php:17:    public static function fromString(string $value): self
app/src/Modules/Media/Domain/ValueObject/MediaMultipartPartETag.php:28:    public function value(): string
app/src/Modules/Media/Domain/ValueObject/MediaMultipartPartETag.php:33:    public function equals(self $other): bool
app/src/Modules/Media/Domain/ValueObject/MediaMultipartPartETag.php:39:    public function __toString(): string
app/src/Modules/Media/Domain/ValueObject/MediaMultipartPartETag.php:45:    public function jsonSerialize(): string
app/src/Modules/Media/Domain/ValueObject/MediaPath.php:22:    public static function originalUpload(MediaStorageKey $storageKey, string $extension): self
app/src/Modules/Media/Domain/ValueObject/MediaPath.php:29:    public static function imageConversion(
app/src/Modules/Media/Domain/ValueObject/MediaPath.php:45:    public static function videoConversion(
app/src/Modules/Media/Domain/ValueObject/MediaPath.php:61:    public static function audioConversion(
app/src/Modules/Media/Domain/ValueObject/MediaPath.php:77:    public static function originalReady(MediaStorageKey $storageKey, MediaType $type, string $extension): self
app/src/Modules/Media/Domain/ValueObject/MediaPath.php:93:    public static function fromString(string $value): self
app/src/Modules/Media/Domain/ValueObject/MediaPath.php:101:    public function value(): string
app/src/Modules/Media/Domain/ValueObject/MediaPath.php:110:    public function extension(): string
app/src/Modules/Media/Domain/ValueObject/MediaPath.php:119:    public function equals(self $other): bool
app/src/Modules/Media/Domain/ValueObject/MediaPath.php:125:    public function __toString(): string
app/src/Modules/Media/Domain/ValueObject/MediaPath.php:131:    public function jsonSerialize(): string
app/src/Modules/Media/Domain/ValueObject/MediaExpiration.php:15:    public static function permanent(): self
app/src/Modules/Media/Domain/ValueObject/MediaExpiration.php:20:    public static function temporaryUntil(\DateTimeImmutable $expiresAt): self
app/src/Modules/Media/Domain/ValueObject/MediaExpiration.php:25:    public function value(): \DateTimeImmutable|null
app/src/Modules/Media/Domain/ValueObject/MediaExpiration.php:30:    public function isPermanent(): bool
app/src/Modules/Media/Domain/ValueObject/MediaExpiration.php:35:    public function isTemporary(): bool
app/src/Modules/Media/Domain/ValueObject/MediaExpiration.php:40:    public function expiresAtOrFail(): \DateTimeImmutable
app/src/Modules/Media/Domain/ValueObject/MediaExpiration.php:49:    public function equals(self $other): bool
app/src/Modules/Media/Domain/ValueObject/MediaExpiration.php:55:    public function __toString(): string
app/src/Modules/Media/Domain/ValueObject/MediaExpiration.php:61:    public function jsonSerialize(): string|null
app/src/Modules/Media/Domain/ValueObject/MediaMultipartUploadIdValue.php:17:    public static function fromString(string $value): self
app/src/Modules/Media/Domain/ValueObject/MediaMultipartUploadIdValue.php:28:    public function value(): string
app/src/Modules/Media/Domain/ValueObject/MediaMultipartUploadIdValue.php:33:    public function equals(self $other): bool
app/src/Modules/Media/Domain/ValueObject/MediaMultipartUploadIdValue.php:39:    public function __toString(): string
app/src/Modules/Media/Domain/ValueObject/MediaMultipartUploadIdValue.php:45:    public function jsonSerialize(): string
app/src/Modules/Media/Domain/ValueObject/MediaProcessingAttempts.php:16:    public static function zero(): self
app/src/Modules/Media/Domain/ValueObject/MediaProcessingAttempts.php:21:    public function increment(): self

exec
/bin/zsh -lc "sed -n '1,180p' app/src/Modules/Media/README.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
# Модуль Media

`Media` — переиспользуемый Application-API загрузки и обработки файлов. У модуля **нет
собственного HTTP**: точку входа с настройками держит модуль-потребитель (например, будущий
`User` для аватара), а `Media` предоставляет только свои сценарии через
`Application`-слой. Ограничения каждой загрузки потребитель передаёт спецификацией прямо в
вызов — они не зашиты в общий конфиг и не отдаются клиенту.

> `Media` не знает про аватар или конкретное применение. Он умеет только: запросить загрузку,
> подтвердить её, асинхронно обработать, отдать URL, проверить и удалить.

## Публичный Application-API

Другие модули обращаются только к `Application` (Command/Query), не к `Repository`,
`Infrastructure` или таблицам.

| Сценарий | Тип | Результат |
|---|---|---|
| `RequestMediaUpload(userId, MediaUploadSpec, MediaFileMeta)` | Command | `RequestMediaUploadResult` |
| `CompleteMediaUpload(userId, mediaId, MediaConversionPlan, parts?)` | Command `#[Transactional]` | `MediaResult` |
| `ProcessMedia(mediaId, MediaConversionPlan)` | Command (через Job) | `void` |
| `RecordMediaProcessingFailure(mediaId, error, isTransient)` | Command | `void` |
| `DeleteMedia(userId, mediaId)` | Command | `void` |
| `MakeMediaPermanent(userId, mediaId)` | Command | `MediaResult` |
| `GetMediaUrl(mediaId, presignedTtlSeconds, conversionType?)` | Query | `MediaUrlResult` (`conversionType` — union image/video/audio-enum или null; для public-медиа `presignedTtlSeconds` игнорируется и не валидируется — прямой URL без срока; срок применяется только для private) |
| `GetAudioWaveform(mediaId)` | Query | `MediaWaveform` (числа амплитуд аудио-конверсии; не через URL; не ready/не аудио/нет конверсии → 404) |
| `CheckMediaIsImage(mediaId)` | Query | `bool` |
| `CheckMediaExists(mediaId)` | Query | `bool` |

`userId` потребитель передаёт параметром (строкой). Проверка владельца —
в Handler-е (`CompleteMediaUpload`/`DeleteMedia`/`MakeMediaPermanent`): несовпадение →
`ForbiddenException` (403). Ошибки — типизированные доменные исключения
(`ValidationException` 422, `NotFoundException` 404, `ForbiddenException` 403), которые на
границе потребителя превращает в ответ `ApiExceptionInterceptor`.

### Спецификация и DTO (`Application/Dto`)

- `MediaUploadSpec{ allowedMimeTypes: MediaMimeTypeCollection, maxSize: MediaFileSize, visibility, presignedTtl: MediaPresignedTtl }`
  — политика загрузки от потребителя. Конверсий здесь нет. `presignedTtl` — срок жизни
  presigned-ссылок загрузки (PUT/части), задаёт потребитель под контекст, а не общий конфиг.
- `MediaFileMeta{ fileName, mimeType, size }` — `fileName` используется только для извлечения
  расширения (не хранится).
- Профили конверсии на тип — **публичные** примитив-дружественные DTO (идут в полезных данных outbox),
  объединены в `MediaConversionPlan{ image: list<MediaImageConversionSpec>, video: list<...>, audio: list<...> }`:
  - `MediaImageConversionSpec{ type, width, height }`
  - `MediaVideoConversionSpec{ type, width, height, videoBitrate, audioBitrate }`
  - `MediaAudioConversionSpec{ type, bitrate, sampleRate, waveformPeaks }`

  Валидируется только список, относящийся к `media.type`; список «не своего» типа должен быть
  пустым (кросс-тип → 422). Для video и audio нужен ровно один профиль; дубли типов в списке image → 422.
- `RequestMediaUploadResult` — `single`: `putUrl`; `multipart`: `uploadId` + коллекция
  presigned-ссылок частей; всегда `mediaId`, `uploadMode`, `expiresAt`.
- `MediaResult{ mediaId, status, visibility }`, `MediaUrlResult{ url, expiresAt? }` — наружу не
  отдаётся доменная Entity.

## Поток загрузки

```text
1. RequestMediaUpload  -> presigned PUT (single) или multipart-ссылки; media = waitingUpload
2. Клиент PUT-ит байты напрямую в MinIO (бакет media-upload)
3. CompleteMediaUpload  -> headObject подтверждает объект и размер; media = uploaded;
                           MediaUploaded кладётся в outbox в той же транзакции   #[Transactional]
4. outbox:relay -> ProcessMediaJob -> ProcessMediaCommand
5. ProcessMedia (без #[Transactional]): S3/ffmpeg/Imagick вне транзакции. Исчерпывающий match по
   media.type: image -> ресайз (Imagick); video -> транскод mp4/H.264+AAC + кадр-постер (ffmpeg);
   audio -> транскод m4a/AAC + волна амплитуд (ffmpeg). Затем перекладка оригинала в целевой бакет
   по visibility и один атомарный persist+run() -> media = ready
6. GetMediaUrl: public -> прямой URL (media-public, anonymous read); private -> presignGet
   (TTL из presignedTtlSeconds запроса). Волна аудио — отдельным GetAudioWaveform (числа, не URL)
```

`uploadMode` (single/multipart) выбирается по порогу `MediaConfig.multipartThresholdBytes`;
размер части — `MediaConfig.multipartPartSizeBytes` (S3 требует ≥ 5 MiB на часть, кроме последней).

## Статусы и обработка ошибок

`MediaStatus`: `waitingUpload → uploaded → ready`. Промежуточный `processing` намеренно не
используется (атомарный переход `uploaded → ready` одним flush). При ошибке обработки
`ProcessMediaJob` фиксирует `ProcessingFailed` через `RecordMediaProcessingFailure` и выбирает
стратегию повтора по **контрактному сигналу** временной ошибки, а не по типу хранилища:
`MediaFileServiceFailedException::isTransient()` → временная (сетевые/5xx/ограничение скорости) даёт
`RetryException` (событие остаётся на повтор) и лог уровня WARN; постоянная (Imagick/битый файл,
нештатный ответ S3) → терминальный проброс (outbox → `failed`) и лог уровня ERROR. Ошибки ffmpeg
классифицируются так же контрактным `MediaProcessorFailedException::isTransient()` (таймаут
транскодирования → временная/`RetryException`; битый/неподдерживаемый вход, нештатный код выхода →
постоянная); причина повтора в `RetryException` выбирается по типу (ошибка процессора ≠ ошибка
хранилища). Классификацию сырого `AwsException`/исключений php-ffmpeg делает Infrastructure
(`S3MediaFileService` / ffmpeg-процессоры → контрактные исключения), поэтому `Presentation/Job` не
импортирует `Aws\*`/`FFMpeg\*` и не знает про реализации хранилища и обработки. Текст ошибки —
из предопределённого набора безопасных сообщений; сырой текст AWS не прокидывается (VO
`MediaProcessingError` отклоняет пути и слово `etag`). Сама запись ошибки на Media обёрнута
локальным guard: если она падает (медиа конкурентно удалили → `NotFoundException`, короткий сбой
БД), вторичный сбой логируется уровнем ERROR и не подменяет исходную причину — Job всё равно
выбирает повтор/терминальный исход по исходному исключению (временное → `RetryException`).

## Транзакционная дисциплина S3

- `CompleteMediaUpload` — `#[Transactional]`: переход `uploaded` и запись `MediaUploaded` в
  outbox происходят в одной транзакции. Компромисс: для multipart-ветки внешние S3-операции
  (`completeMultipartUpload`, `headObject`) выполняются под открытой транзакцией БД. Это
  осознанный компромисс — повтор `complete` идемпотентен (`NoSuchUpload` → подтверждение через
  `headObject`), а транзакция короткая. Если появятся проблемы с длительными транзакциями (блокировки,
  рассинхрон БД↔S3 при таймауте), сборку multipart и HEAD стоит вынести за пределы транзакции
  по образцу `ProcessMediaHandler`.
- `ProcessMediaHandler` — **без** `#[Transactional]`: сначала все S3/Imagick-операции вне
  транзакции (чтобы не держать БД и блокировку строки), затем один атомарный
  `persist(media + conversions); run()` с `markReadyMovedTo`. Операции идемпотентны
  (детерминированные ключи от `storageKey`): на повторе до коммита конверсий нет → создаём
  заново без конфликта по unique `(media_id, type)`; если медиа уже `ready` — no-op. Для image
  оригинал читается в память только при непустом наборе конверсий; video/audio скачиваются
  процессором в локальный файл (потоково, не в память); перекладка оригинала в целевой бакет —
  server-side `copyObject` для всех типов.
- Набор `conversions` фиксируется один раз в `CompleteMediaUpload` и доезжает до обработки в
  outbox-сообщении, поэтому на практике он стабилен. Повторная обработка перезаписывает объекты
  по детерминированным ключам и не размножает их; но если бы тот же media обработали повторно с
  другим набором конверсий, ранее залитые и больше не запрашиваемые конверсии в S3 не удаляются
  (осиротевшие объекты). В текущем контракте такого повтора не возникает.
- Оригинал И конверсии живут в одном бакете по `visibility` (`media-public`/`media-private`),
  чтобы `GetMediaUrl` резолвил их единообразно и не было утечки private-медиа.
- `MediaImageConversion` хранит `width`/`height` **из результата процессора**
  (`conversionResult->width`/`height`), а не запрошенные `spec.width`/`spec.height`: в БД должен
  лежать размер реально записанного объекта. Текущий `ImagickMediaImageProcessor` использует
  `cover()` и всегда выдаёт точные spec-размеры, поэтому фактически значения совпадают со spec; чтение
  из результата выбрано потому, что оно устойчиво к будущей смене режима ресайза (`contain`/`scale`),
  где фактический размер мог бы отличаться от запрошенного. Это осознанное отклонение от буквы плана
  (шаг 6 предписывал `MediaPixelDimension из spec.width/spec.height`).
- `DeleteMedia` удаляет из S3 не только оригинал, но и объекты конверсий: до `delete($media)`
  Handler грузит конверсии (`MediaImageConversionRepository`/`MediaVideoConversionRepository`/
  `MediaAudioConversionRepository`) и удаляет каждый объект по `storage`/`path` (404 идемпотентно
  игнорируется). Постер видео — это `MediaImageConversion` (Poster), он уже в image-цикле, отдельно
  не чистится. Без этого FK `ON DELETE CASCADE` убрал бы строки конверсий, оставив файлы
  осиротевшими в постоянном бакете.
- Staging-оригинал из `media-upload` синхронно не удаляется — чистится по expiry (отдельная
  задача).

## Outbox-сообщение

`Application/Message/MediaUploaded implements OutboxMessage` — только примитивы/enum
(`string mediaId`, вложенный `MediaConversionPlan` из `list<...Spec>`), потому что
`ValinorOutboxMessageSerializer` не регистрирует кастомные конструкторы доменных VO; точные
PHPDoc-типы `list<...Spec>` обязательны для восстановления вложенного DTO Valinor-ом. Пара `MediaUploaded → ProcessMediaJob`
регистрируется в `MediaBootloader`; Job — в `app/config/queue.php`
(`registry.handlers` + `registry.serializers = OutboxQueueSerializer`).

## Инфраструктура и конфиг

- `app/config/media.php` + `MediaConfig` (`Shared/Infrastructure/Configuration/Media`): staging-TTL,
  порог и размер части multipart, драйвер обработки изображений, ffmpeg-настройки
  (`ffmpegBinaryPath`, `ffprobeBinaryPath`, `ffmpegTimeoutSeconds`, `ffmpegThreads` —
  env `MEDIA_FFMPEG_BINARY`/`MEDIA_FFPROBE_BINARY`/`MEDIA_FFMPEG_TIMEOUT_SECONDS`/`MEDIA_FFMPEG_THREADS`).
  TTL presigned-ссылок в конфиге **нет** — его
  задаёт потребитель под контекст: `MediaUploadSpec.presignedTtl` (загрузка) и
  `GetMediaUrlQuery.presignedTtlSeconds` (скачивание). Ключи `MEDIA_*` — в `.env.sample` и
  `phpunit.xml`. `MediaConfig` инжектится напрямую в `RequestMediaUploadHandler` — осознанное
  исключение из правила зависимостей слоёв: `TypedConfig` живёт в `Shared/Infrastructure/Configuration`
  по конвенции размещения всех config-DTO, и оборачивать его в Application-`*Contract` было бы
  запрещённой pass-through-обёрткой (см. `docs/arch.md`, «Правила зависимостей»).
- presigned/multipart и серверные S3-операции — `S3MediaFileService` поверх `Aws\S3\S3Client`
  (построение клиента вынесено в `S3ClientProvider` для тестируемости). Имя бакета и `prefix`
  резолвятся из `StorageConfig->buckets[alias]`, где alias — значение enum `MediaStorage`.
- Обработка изображений — `ImagickMediaImageProcessor` на Intervention Image v4 (драйвер
  imagick по умолчанию, gd как фолбэк; см. `docker/README.md`).
- Обработка видео/аудио — `FfmpegMediaVideoProcessor`/`FfmpegMediaAudioProcessor` на
  `php-ffmpeg/php-ffmpeg` (бинарь ffmpeg в образе). Процессор владеет временными файлами:
  `downloadToFile` оригинала → ffmpeg (probe/транскод/постер/волна) → `uploadFromFile` результатов
  → удаление всех временных файлов в `finally` при любом исходе. Application-handler строит целевые
  пути доменными фабриками и передаёт готовыми; Infrastructure доменные пути не строит. AAC берётся
  нативным кодером ffmpeg (`NativeAacAudioFormat`), т.к. `libfdk_aac` в сборку не входит. Волну
  амплитуд снимаем сырым PCM (s16le, моно) прямым вызовом ffmpeg и нормализуем в 0..255.
- Контракт выхода видео — всегда mp4/**H.264+AAC**. php-ffmpeg добавляет AAC только при наличии
  аудио на входе, поэтому немому видео (`video/*` без звука) процессор подмешивает тихую AAC-дорожку
  (`anullsrc`, обрезается по длине видео через `-shortest`) — выход остаётся H.264+AAC.
- Аудио с обложкой альбома (`audio/*` со встроенным потоком attached_pic — mjpeg/png) — штатный вход:
  «есть видеопоток» не равно «не аудио». Процессор различает реальное видео и аудио-с-обложкой по
  факту настоящего аудиопотока и отсутствия не-обложечной видеодорожки (ffprobe), а при транскоде
  выкидывает обложку (`-vn`), отдавая чистый m4a/AAC.

## Эксплуатация

- Миграция `*_create_media_audio_conversions_table` добавляет таблицу `media_audio_conversions`

web search: 
web search: https://www.iana.org/assignments/media-types/text/csv
web search: 
web search: Microsoft file formats docm docx macros official
web search: 
web search: https://www.iana.org/assignments/media-types/application/vnd.ms-word.document.macroEnabled.12
codex
- [архитектура] В исследовании сказано, что другой MIME для FB2 — ответственность потребителя через `allowedMimeTypes`, но в коде сначала вызывается `MediaTypeResolver::resolve()`, и только потом проверяется `allowedMimeTypes`; такой MIME будет отклонён раньше — исправить: дополнительные FB2 MIME надо добавлять в общий whitelist или явно оставить неподдержанными

- [MIME] Whitelist описан как точное сравнение строк, но для текстовых типов бывают параметры: у `text/markdown` параметр `charset` обязателен по IANA, у `text/csv` допустимы `charset` и `header`; текущий `MediaMimeType` параметры не отделяет — решить и описать: нормализовать MIME до базового типа или явно не поддерживать параметры, плюс добавить тесты

- [риск] Риск «макросы в docx» описан неточно: macro-enabled Office-форматы имеют отдельные MIME для `docm`, `xlsm`, `pptm`, а в whitelist их нет; при этом старый `doc` включён и сам рискованнее — исправить формулировку риска и явно решить, поддерживать ли macro-enabled форматы или оставить их запрещёнными
tokens used
96 741
- [архитектура] В исследовании сказано, что другой MIME для FB2 — ответственность потребителя через `allowedMimeTypes`, но в коде сначала вызывается `MediaTypeResolver::resolve()`, и только потом проверяется `allowedMimeTypes`; такой MIME будет отклонён раньше — исправить: дополнительные FB2 MIME надо добавлять в общий whitelist или явно оставить неподдержанными

- [MIME] Whitelist описан как точное сравнение строк, но для текстовых типов бывают параметры: у `text/markdown` параметр `charset` обязателен по IANA, у `text/csv` допустимы `charset` и `header`; текущий `MediaMimeType` параметры не отделяет — решить и описать: нормализовать MIME до базового типа или явно не поддерживать параметры, плюс добавить тесты

- [риск] Риск «макросы в docx» описан неточно: macro-enabled Office-форматы имеют отдельные MIME для `docm`, `xlsm`, `pptm`, а в whitelist их нет; при этом старый `doc` включён и сам рискованнее — исправить формулировку риска и явно решить, поддерживать ли macro-enabled форматы или оставить их запрещёнными
