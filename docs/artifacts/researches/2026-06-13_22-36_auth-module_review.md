Reading additional input from stdin...
OpenAI Codex v0.139.0
--------
workdir: /Users/gian_tiaga/Code/yoga-loka-spiral-2
model: gpt-5.5
provider: openai
approval: never
sandbox: workspace-write [workdir, /tmp, $TMPDIR]
reasoning effort: high
reasoning summaries: none
session id: 019ec27f-83e9-78d2-be10-9d29d2d04ca4
--------
user
Прочитай docs/researches/2026-06-13_22-36_auth-module.md, docs/rules.md, docs/arch.md при наличии и все источники, на которые ссылается исследование (включая docs/researches/2026-06-13_12-33_user-domain.md и код модулей User/Access, app/src/Modules). Проверь, что проблема описана конкретно, архитектура не выдумана, все развилки решены, риски закрыты, версии пакетов/ПО проверены по актуальным источникам, а вариант решения не является планом или абстрактным рассуждением. Особое внимание: корректно ли описан spiral/auth (TokenStorageInterface, ActorProviderInterface, AuthMiddleware, header-транспорт) для Spiral 3.16; реально ли реализуемо своё CycleTokenStorage; нет ли нарушения границ модулей (Auth -> User только через Application); верны ли ссылки file:line. Критическое ревью на русском: фактические ошибки, пропущенные развилки, незакрытые риски, слабые источники, неверные версии, неконкретный итог. Формат: '- [тип] описание — что исправить'.
codex
Использую `eda-review`, потому что запрос именно на критическое ревью файла и связанных источников. Сначала прочитаю инструкции скилла и обязательные документы проекта, затем проверю ссылки и факты по коду и актуальной документации Spiral.
exec
/bin/zsh -lc "sed -n '1,240p' docs/arch.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
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
        Presentation/
          Http/                   # Health, Swagger UI, OpenAPI YAML route
          Console/                # openapi:* команды
          Exception/              # Исключения слоя Presentation (публикация ассетов OpenAPI)
          Temporal/               # технические workflow

    Shared/
      Domain/
        Exception/                # Общие доменные исключения
        Trait/                    # Общие доменные трейты
        ValueObject/              # Общие базовые VO и общие идентификаторы
      Presentation/

exec
/bin/zsh -lc "sed -n '1,240p' docs/code-examples.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
# Примеры кода

## DTO команды

```php
<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Command\Auth\Login;

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

namespace App\Modules\Auth\Application\Command\Auth\Login;

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

namespace App\Modules\User\Application\Query\User\GetUserProfile;

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

namespace App\Modules\User\Application\Query\User\GetUserProfile;

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

use App\Modules\User\Application\Query\User\GetUserProfile\GetUserProfileHandler;
use App\Modules\User\Application\Query\User\GetUserProfile\GetUserProfileQuery;
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

declare(strict_types=1);

namespace App\Modules\User\Domain\Entity;

use App\Shared\Domain\Trait\HasTimestamps;
use App\Shared\Domain\ValueObject\UserId;
use App\Modules\User\Domain\ValueObject\DisplayName;
use App\Modules\User\Domain\ValueObject\Email;
use App\Modules\User\Domain\ValueObject\PasswordHash;
use App\Modules\User\Domain\ValueObject\Username;
use App\Modules\User\Infrastructure\Cycle\UserValueObjectTypecast;
use App\Modules\User\Repository\UserRepository;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\ORM\Parser\Typecast;

#[Entity(
    role: 'user',
    table: 'users',

exec
/bin/zsh -lc "sed -n '1,240p' /Users/gian_tiaga/Code/yoga-loka-spiral-2/.codex/skills/eda-review/SKILL.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
---
name: eda-review
description: 'Ревью изменений (незакоммиченный diff, ветка, PR, папка/файл) с оценкой и сверкой с планом работ из `docs/plans/`, когда план указан или однозначно найден. Если план не указан и не найден однозначно, ревьюит без сверки с планом и не спрашивает про него. Без аргументов ревьюит незакоммиченный `git diff HEAD`. Перед ревью читает `docs/rules.md` и `docs/arch.md`. Формирует ревью только по проблемам, сохраняет в `docs/reviews/` и в обычном режиме передаёт файл в `eda-review-check` для мета-проверки субагентами. Флаг `draft` сохраняет только черновое ревью без субагентов. Кросс-CLI включается флагом `strict` или настройкой `defaults.strict: true`. Не правит код — это `eda-fix-by-review`.'
---

# Скил: Ревьюер (eda-review)

Делаешь ревью изменений: сверяешь diff с планом работ, правилами и архитектурой, пишешь только найденные проблемы, ставишь оценку, сохраняешь файл. Если скилл вызван без аргументов, ревьюишь незакоммиченный `git diff HEAD`. План сверяешь, только если он указан или однозначно найден; иначе ревьюишь без сверки с планом и про план не спрашиваешь. Каждый пункт начинаешь с короткого человеческого объяснения риска, без технической перегрузки; файлы, строки, детали кода и способ исправления даёшь ниже в блоке «Технические детали». Не перечисляешь, что выполнено хорошо или без замечаний. Затем в обычном режиме запускаешь `eda-review-check`, который проверяет ревью параллельными специализированными агентами; в `strict` он добавляет соседний CLI. В `draft` останавливаешься сразу после сохранения черновика.

## Режим запуска

Прочитай `docs/settings.yaml`, если файл есть. Прямое указание пользователя в текущем сообщении важнее настроек.

Дефолты: `defaults.strict: false`, `review.include_code_quality: true`.

| Настройка | Значения | Аргументы в сообщении |
|---|---|---|
| `defaults.strict` | `true`, `false` | `strict`; выключение: `normal`, `без strict`, `без строгого режима` |
| `review.include_code_quality` | `true`, `false` | «проверь качество кода»; выключение: «без quality», «без проверки качества» |

`strict` добавляет кросс-CLI соседним агентом. `review.include_code_quality: true` добавляет `quality-check` в обычное мета-ревью.

Флаг `draft` отключает весь этап `eda-review-check`: сохрани ревью со `status: draft`, `meta_reviewers: []`, покажи путь и оценку, затем остановись. `draft` важнее `strict`: если пользователь написал оба режима, задай `AskUserQuestion`, потому что требования противоречат друг другу.

## Вход из сообщения пользователя

Текст рядом с вызовом скилла в текущем сообщении пользователя — главный вход. Сначала разбери именно его: тему, задачу, путь к файлу, режим, ограничения и прямые указания.

Если этого входа достаточно, продолжай без вопроса. `AskUserQuestion` задавай только если входа нет, он противоречивый, найдено несколько равных вариантов или есть риск выполнить не ту задачу. Старое обсуждение в истории не считай входом, если пользователь не связал его с текущим вызовом явно.

## Главные правила

1. **Перед ревью** прочитай `docs/rules.md` и `docs/arch.md`, если есть, и строго следуй им. Замечания должны опираться на правила и архитектуру проекта, а не на твои общие представления.
2. **Интерактивные вопросы** — по разделу ниже. Если контекст понятен, не спрашивай.
3. **Не правишь код.** Только ревью. Запись только в `docs/reviews/`.
4. **Сверка с планом — только если план есть.** Используй план, если пользователь указал путь к нему или его можно однозначно определить из diff/ссылок/существующего review-файла. Если плана нет или вариантов несколько — не спрашивай: делай ревью без сверки с планом и отметь это в ревью. Не подставляй «последний план» наугад.
5. **Мета-ревью специализированными агентами — обязательно в normal/strict**, но выполняется отдельным скиллом `eda-review-check`. В `draft` мета-ревью не запускается.
6. **Главный ревьюер — ты.** Мета-ревьюеры дают подсказки; решение — добавить, убрать или оставить пункт — за тобой. Не вставляй чужие пункты механически.
7. **Простой язык** в формулировках замечаний и рекомендаций. Технические термины — только если без них нельзя, и сразу с пояснением.
8. **Ревью содержит только проблемы.** Не пиши отчёт о выполненной работе, список успешно сделанных пунктов, похвалу или нейтральное перечисление изменений. В файл попадают только ошибки, недоделки, неточности, спорные решения, нарушения правил/архитектуры, риски и недостающие проверки. Если проблем нет, напиши коротко: «Явных проблем не найдено», без пересказа diff.
9. **Формат файла ревью — Markdown-блоки по шаблону из этапа 2.** Проблемы сверки с планом, каждое замечание и рекомендации пиши отдельными блоками с подзаголовками. Внутри каждого пункта сначала идёт основная часть простым языком, затем блок `Технические детали`.
10. **Основная часть замечания — главный ответ.** Пиши 1–3 коротких абзаца, и в эти же абзацы включай весь человеческий верх пункта: короткий контекст, понятный без чтения плана, diff и истории задачи; что происходит или что не подтверждено; почему это важно, какой практический риск и когда он проявится. Не начинай с имён классов, методов, строк, SQL, DTO, конфигов и внутренних деталей.
11. **Технические детали — ниже основной части.** Там укажи тип, рекомендацию, файлы/строки, что в коде подтверждает проблему, общий подход к исправлению и конкретные шаги. Если деталь не помогает понять или исправить проблему — не пиши её.

## Стиль замечаний

Пиши так, чтобы первый экран пункта можно было прочитать без глубокого знания кода и без знания плана, diff или истории задачи: в 1–3 коротких абзацах объясни контекст, ситуацию, риск и когда он проявится; затем дай `Технические детали`. Не растягивай пункт, не пересказывай весь diff и не начинай с имён классов, методов, строк, SQL, DTO, конфигов.

## Интерактивные вопросы

Если цель ревью понятна из запроса или текущего состояния репозитория, **не задавай вопрос о цели ревью**. Вопрос о плане не задавай никогда: если план не указан и не найден однозначно, ревьюй без сверки с планом. Примеры понятного контекста:
- пользователь указал файл, папку, PR, ветку или тип diff;
- в запросе есть «ревью незакоммиченных изменений», «ревью текущей ветки», «проверь PR #N»;
- в рабочем дереве есть незакоммиченные изменения и пользователь просит «сделай ревью» без других целей.

Когда инструкция говорит `AskUserQuestion`, это означает блокирующий интерактивный вопрос.
- Claude Code: используй `AskUserQuestion`.
- Codex interactive: если доступен `request_user_input`, используй его. Если tool недоступен, задай один короткий вопрос в чат, дай варианты 1–3, напиши «Ответь номером или своим вариантом. Я продолжу только после ответа.» и остановись.
- Codex exec / неинтерактивный запуск: не задавай вопросы и не пытайся читать stdin. Если без ответа нельзя безопасно продолжать, заверши работу со статусом `blocked: нужен ответ пользователя`, перечисли вопросы и варианты, не выполняй рискованные действия.
- Не запускай команды, которые ждут интерактивного ввода в терминале; перед такими командами спроси в хост-интерфейсе или остановись с `blocked`.

## Этапы

### 1. Выбрать вход и прочитать контекст
Сначала попробуй определить цель ревью без вопроса:
- если пользователь указал файл, папку, PR, ветку или тип diff — используй это;
- если пользователь просит ревью без уточнений и есть незакоммиченные изменения — используй `git diff HEAD`;
- если незакоммиченных изменений нет, но текущая ветка не `main`/`master` и отличается от базовой ветки — используй `git diff <base>...HEAD`, где `<base>` выбери из существующих `main`, `master`, `origin/main`, `origin/master`;
- если цель всё ещё неоднозначна или diff пустой — только тогда через `AskUserQuestion` спроси, что ревьюим: незакоммиченный diff (`git diff HEAD`) / diff ветки от main (`git diff main...HEAD`) / конкретный файл или папка / номер PR.

Получи diff/исходник в `$TARGET`. Прочитай `docs/rules.md`, `docs/arch.md` и строго следуй им.

Определи `$PLAN_FILE`, не задавая вопросов о плане:
- если пользователь указал путь к плану — используй его;
- если в diff, описании задачи или уже существующем review-файле есть однозначная ссылка на `docs/plans/...` — используй её;
- иначе (план не указан, не найден или вариантов несколько) — установи `$PLAN_FILE=none` и ревьюй без сверки с планом. Не подставляй «последний план» наугад.

Если в diff видно отсылки к исследованию из `docs/researches/` — прочитай его тоже.

### 2. Сделать первичное ревью и сохранить
Пройдись по изменениям. Если `$PLAN_FILE` указывает на файл, сначала проверь, выполнен ли план, но в ревью выноси только проблемы: что сделано частично, что не сделано, какие отклонения создают риск, какие проверки отсутствуют. Если `$PLAN_FILE=none`, не делай проверку плана и не пытайся восстановить план из последних файлов. Не перечисляй выполненные пункты плана. Затем проверь нарушения правил, архитектуры, баги, тестовые пробелы, безопасность, производительность и документацию. Если `review.include_code_quality: true` или пользователь явно попросил качество кода, отдельно проверь качество реализации: читаемость, сложность, дублирование, имена, границы ответственности, связность модулей, поддерживаемость и соответствие стилю уже существующего кода.

Каждое замечание должно быть самостоятельным блоком:
- заголовок с номером и коротким смыслом;
- основная часть простым языком: 1–3 коротких абзаца, которые включают контекст, проблему и риск без привязки к конкретному коду;
- `Технические детали`: тип, рекомендация, конкретные файлы/строки, что именно в коде подтверждает проблему, как исправить и какие тесты добавить или обновить.

Поставь оценку 0–100 (100 — идеально, 0 — переделывать).

Сохрани в `docs/reviews/{YYYY-MM-DD}_{HH-MM}_{slug}.md`. Соблюдай этот формат буквально: каждый пункт сначала читается как короткий человеческий ответ, а технические детали идут ниже отдельным блоком.

```markdown
---
title: <заголовок>
date: <YYYY-MM-DD HH:MM>
target: <git diff HEAD | main...HEAD | path | PR#>
plan: <docs/plans/... | none>
mode: <draft | normal | strict>
score: <0..100>
status: draft
meta_reviewers: []
---

# Ревью: <заголовок>

## Оценка

**<N>/100.** <одно-два предложения почему>

## Проблемы сверки с планом

<Если `$PLAN_FILE=none`: «Проверка плана пропущена: план не указан и не найден.» Если проблем по плану нет: «Явных проблем по плану не найдено.»>

### 1. <недоделка, отклонение или непроверенный пункт>

<1–3 коротких абзаца: короткий контекст, понятный без плана, diff и истории задачи; что требовал план; что не сделано или не подтверждено; почему это важно, какой риск и когда он проявится. Без имён файлов, строк и внутренних деталей в начале пункта.>

Технические детали:

- **Статус:** <частично | не выполнено | не проверялось | отклонение>
- **Где:** `<path/file.ts:42>` — <что смотреть>
- **Что подтверждает проблему:** <конкретная деталь по коду, diff или плану>
- **Как исправить:** <общий подход и конкретные шаги по коду/тестам>

## Замечания

<Если замечаний нет: «Явных проблем в коде не найдено.»>

### 1. <короткий заголовок проблемы>

<1–3 коротких абзаца: короткий контекст, понятный без плана, diff и истории задачи; что происходит; почему это проблема; какой практический риск и когда он проявится. Без технической перегрузки и без пересказа diff.>

Технические детали:

- **Тип:** `bug | security | performance | architecture | rules | tests | quality | docs`
- **Рекомендация:** `править обязательно | на усмотрение автора`
- **Где:** `<path/file.py:42>` — <что смотреть>
- **Что подтверждает проблему:** <конкретное объяснение по коду>
- **Как исправить:** <общий подход, затем конкретные шаги по файлам>
- **Тесты:** <какой тест добавить или обновить; если тест не нужен, почему>

## Рекомендации

- **Править обязательно:** <номера>
- **На усмотрение автора:** <номера>

## Изменения после мета-ревью

<заполняется на этапах 3 и 4>
```

Покажи путь.

Если режим `draft` — не запускай субагентов и не вызывай `eda-review-check`; сразу переходи к финалу draft.

### 3. Проверить ревью через `eda-review-check`
В normal/strict запусти `eda-review-check` для `$REVIEW_FILE` и передай выбранный режим (`normal` или `strict`). Именно `eda-review-check` запускает `architecture-check`, `rules-check`, а если `plan` в front matter указывает на файл — ещё и `plan-check`; при включённой настройке `quality-check` тоже запускается. Затем `eda-review-check` обновляет `status`, `meta_reviewers`, `score` и раздел `## Изменения после мета-ревью`.

Требования к средам остаются частью контракта ревью: Codex interactive: если доступен инструмент субагентов (`spawn_agent` или аналог), используй его; не используй отдельные `codex exec` для обычного мета-ревью, когда субагенты доступны. Codex exec / неинтерактивный fallback допустим только как fallback и описан в `eda-review-check`. Кросс-CLI выполняет только `eda-review-check strict`.

### 4. Финал
Короткое сообщение: режим (`draft`, `normal` или `strict`), путь к файлу ревью, итоговая оценка, сколько пунктов «править обязательно» / «на усмотрение». Не пересказывай выполненную работу. Если режим был `draft` — добавь строку «мета-ревью не запускалось; для проверки — `eda-review-check <review>`». Если режим был обычный — добавь строку «кросс-CLI не запускался; для проверки соседним агентом — `eda-review-check strict <review>`». Что предлагаешь дальше — `eda-fix-by-review` для применения или `eda-commit` если оценка приемлема.

## Чего НЕ делать
- Править код. Это `eda-fix-by-review`.
- Пропускать `eda-review-check` в normal/strict. Исключение — явный режим `draft`.
- Запускать мета-ревьюеров частями: сначала 1–2 агента, потом остальных. Все обязательные проверки стартуют сразу.
- Пропускать кросс-CLI молча в `strict` (если соседней CLI нет — спроси через `AskUserQuestion`).
- Вставлять чужие предложения механически — каждое решение принимай ты.
- Задавать вопросы без блокировки и продолжать работу до ответа.
- Сохранять ревью куда-либо кроме `docs/reviews/`.
- Оформлять замечания без контекста перед конкретикой.
- Перечислять выполненные пункты плана или давать общий отчёт о проделанной работе.

exec
/bin/zsh -lc "sed -n '1,240p' docs/rules.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
# Правила проекта

## Язык

- Отвечай всегда на русском
- Исключения пишутся на русском языке.
- Логи пишутся на русском языке.
- Комментарии к коду пишутся на русском языке.
- Ошибки, которые отдаются пользователю, пишутся на языке пользователя.
- **Коммиты на русском**: сообщения коммитов (subject, body) пишутся на русском языке. Тип и scope остаются на английском по Conventional Commits (`feat`, `fix`, `refactor` и т.д.), но описание — на русском. Пример: `feat(tenant): добавить управление тенантами`.

## Качество кода

- **Ранний возврат**: guard clauses (`throw`/`return`) в начале метода. Инвертировать условие, выбросить исключение первым, happy path без вложенности. Вложенность 2+ уровней `if/else` — красный флаг.
- **Короткие методы**: `handle()` в Handler — не более ~40 строк. Длиннее — выносить в приватные методы.
- **`match` вместо `switch`**: `switch` не используется. Всегда `match`-выражение. Enum — исчерпывающий `match` без `default`.
- **Именованные аргументы**: обязательны при 2+ обычных параметрах и при любом необязательном/булевом параметре. Позиционные — для 1–2 очевидных параметров. Variadic-вызовы и вызовы с unpack (`...$args`) допускают позиционные аргументы, потому что именование ломает читаемость таких API (`sprintf`, `implode` и т.д.). (Проверяется PHPStan)
- **`sprintf()` для строк**: для пользовательских сообщений и строк ошибок. Интерполяция `"{$a}-{$b}"` — только для компактных ключей/идентификаторов. Конкатенация `.` — избегать.
- **Collection-пайплайны**: `->map()`, `->filter()`, `->groupBy()` для чистых трансформаций. `foreach` — только при побочных эффектах.
- **Типизированные Laravel Collections вместо массивов и Doctrine Collections**: в Entity и Repository не использовать массивы для наборов сущностей или value object. Для каждой доменной коллекции создавать именованный класс на базе `Illuminate\Support\Collection` с generic-типом элемента: например, связь `User -> Role` хранится и возвращается как `RoleCollection`, а результат репозитория со списком пользователей — как `UserCollection`. `Doctrine\Common\Collections\ArrayCollection` запрещена. Все связи Cycle ORM и методы репозиториев, возвращающие несколько элементов, должны возвращать конкретную типизированную коллекцию, а не `array` и не голый `Illuminate\Support\Collection`.
- **Явные типы вместо `null`**: `null` в доменной модели избегается. Отсутствие, неизвестность или особое состояние выражать отдельным типом/value object/enum, например `KnownIp` и `UnknownIp` как реализации абстракции `Ip`, а не `?string`/`?Ip`.
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
- **Запрет ассоциативных массивов**: для передачи данных используем ООП-подход — DTO, Filter, Response-классы. `array<string, mixed>` в публичных контрактах (параметры, return type) — нарушение.
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
- **Репозиторий — только чтение (read-only)**: Repository только читает данные — через ORM `$this->select()` и базовые `findByPK()`/`findOne()`/`findAll()`. Репозиторий не сохраняет и не изменяет данные: запрещены методы-мутаторы `save()`, `persist()`, `store()`, `create()`, `update()`, `delete()`, а также инъекция `EntityManagerInterface` в репозиторий. Прямые `$database->update()`, `$database->insert()`, `$database->delete()` тоже запрещены — даже атомарный CAS-переход по статусу. Любое изменение состояния выражается доменным методом Entity (`markQueued()`, `markFailed()` и т.д.) и сохраняется вызовом `$this->entityManager->persist($entity)` + `$this->entityManager->run()` в Handler-е или инфраструктурном orchestrator-е, который вызывает репозиторий только для выборки. Правило rules.md «Запрет SQL текстом» отвечает на вопрос *как* писать запрос (query builder, не сырая строка), но не разрешает репозиторию модифицировать данные.
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
- **CQRS: группировка по действию**: внутри `Modules/{Module}/Application/Command/{Domain}/` и `Modules/{Module}/Application/Query/{Domain}/` каждое действие выносится в отдельную подпапку. Папка называется по действию (без суффикса Command/Query). Пример: `Modules/Auth/Application/Command/Auth/Login/LoginCommand.php`, `LoginHandler.php`, `LoginResult.php`. Это даёт чёткую изоляцию: все файлы одного use-case лежат рядом. Namespace соответствует: `App\Modules\Auth\Application\Command\Auth\Login`.
- **CQRS: полное имя действия в имени класса**: имя Command/Query/Handler/Filter должно содержать полный контекст действия, включая доменную сущность, даже если namespace уже содержит домен. Пример: `UpdateUserProfileCommand` (не `UpdateProfileCommand`), `GetUserProfileQuery` (не `GetProfileQuery`), `UpdateUserProfileFilter` (не `UpdateProfileFilter`). Папка действия совпадает: `Application/Command/User/UpdateUserProfile/`, `Application/Query/User/GetUserProfile/`. Это делает класс самодокументируемым — по имени сразу понятно, что он делает, без заглядывания в namespace.


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

codex
Контекст проекта прочитан. Теперь разбираю само исследование и связанные файлы, чтобы проверить не только текст, но и реальные классы, границы модулей и номера строк.
exec
/bin/zsh -lc "sed -n '1,260p' docs/researches/2026-06-13_22-36_auth-module.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc "sed -n '1,260p' docs/researches/2026-06-13_12-33_user-domain.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
---
title: Модуль Auth — беспарольный вход по email-коду
date: 2026-06-13 22:36
mode: strict
decision_mode: ask_each_time
status: draft
reviewer: pending
sources:
  rules: docs/rules.md
  arch: docs/arch.md
---

# Модуль Auth — беспарольный вход по email-коду

## Суть

Проектируем модуль `Auth` — аутентификацию для API мобильного приложения YogaLoka.
Домен и данные `User` и `Access` уже реализованы (`User.php:77`, модуль `Access`), но
аутентификацию там осознанно не делали (`docs/researches/2026-06-13_12-33_user-domain.md:248`):
у `User` нет поля пароля вообще, есть только статус `WaitingEmailConfirmation` и метод
`confirmEmail()` (`User.php:101`).

Решаем: как пользователь входит и регистрируется, чем выдаём и проверяем токены, где
хранить коды и токены, как защититься от перебора, и как всё это лечь в модульный
монолит на Spiral + Cycle + CQRS (`docs/arch.md:5`), не нарушая границы модулей и не
переписывая готовый домен `User`.

Рамка: модуль `Auth` уже назван в архитектуре отдельной областью (`docs/arch.md:23`).
Идентификаторы — UUID v7, доменные значения — VO/enum, доступ к БД — через репозитории,
внешние эффекты (письма) — через outbox (`docs/arch.md:514`).

## Решение

Беспарольная аутентификация: пользователь вводит email, получает на почту 6-значный код,
вводит код — и входит. Один поток = и вход, и регистрация. Пароля нет, поэтому сброса и
смены пароля тоже нет. Токены — непрозрачные (случайная строка, данные на сервере), пара
access + refresh, хранятся в Cycle, refresh ротируется. Защита от перебора — переиспользуемый
атрибут `#[RateLimited]` + Spiral-интерцептор в `Shared`.

### Два потока входа

```text
СУЩЕСТВУЮЩИЙ email
  POST /api/auth/code/request {email}     -> код на почту (через outbox)
  POST /api/auth/code/verify  {email,code}-> сервер узнал юзера
                                             -> {access, refresh, expiresIn}

НОВЫЙ email (вход = регистрация)
  POST /api/auth/code/request {email}     -> код на почту
  POST /api/auth/code/verify  {email,code}-> юзера ещё нет
                                             -> {registrationTicket, needsProfile:true}
  POST /api/auth/register {ticket,name,nickname}
                                          -> создаём User (сразу Active) + {access, refresh}

ОБНОВЛЕНИЕ И ВЫХОД
  POST /api/auth/refresh {refreshToken}   -> ротация -> новые {access, refresh}
  POST /api/auth/logout  (Bearer access)  -> отзыв токена(ов)
```

«Талон регистрации» — короткоживущая (~10 мин) одноразовая запись, привязанная к email,
а не к пользователю (пользователя ещё нет). Он переносит доказательство «email только что
подтверждён кодом» с шага проверки кода на шаг заполнения профиля. Без него запрос
`register` не имел бы доказательства, что почта подтверждена, и любой мог бы
зарегистрировать чужой email. Талон годится только для завершения регистрации, это не токен
доступа.

Почему так, а не «авто-ник» или «всё сразу»: `User::create()` требует обязательные `UserName`
и уникальный `UserNickname` (`User.php:77-99`), а email-вход даёт только email. Вариант
«код → талон → профиль» не трогает готовый домен `User`, не плодит пустые аккаунты и не
занимает ники на неподтверждённые email. Пользователь рождается сразу `Active` (код доказал
владение почтой), поэтому `WaitingEmailConfirmation` при регистрации не используется — этот
статус остаётся для смены email существующим пользователем (`changeEmail()` → код →
`confirmEmail()`, `User.php:107`).

### Карта модуля Auth

```text
Modules/Auth/
  Domain/
    Entity/        LoginCode, RegistrationTicket, AuthToken
    ValueObject/   LoginCodeId, RegistrationTicketId, AuthTokenId,
                   EmailFingerprint, SecretHash, CodeAttempts,
                   CodeExpiration, TicketExpiration, TokenExpiration
    Enum/          AuthTokenType (Access | Refresh)
  Application/
    Command/Auth/  RequestLoginCode, VerifyLoginCode, CompleteRegistration,
                   RefreshTokens, Logout
    Contract/      SecretHasherContract, TokenGeneratorContract
    Message/       LoginCodeRequested            # outbox-сообщение (письмо)
    Exception/     # ошибки слоя Application, если нужны сверх общих
  Repository/      LoginCodeRepository, RegistrationTicketRepository, AuthTokenRepository
  Infrastructure/
    Cycle/         # typecast VO
    Auth/          CycleTokenStorage, UserActorProvider
    Hash/          HmacSecretHasher                # реализация SecretHasherContract
    Bootloader/    AuthBootloader                  # биндинги storage/actor provider
  Presentation/
    Http/Controller/ AuthController
    Http/Filter/     RequestCodeFilter, VerifyCodeFilter, RegisterFilter, RefreshFilter
    Http/Resource/   TokenPairResource, VerifyResultResource
    Job/             SendLoginCodeJob              # потребитель outbox-задачи

Shared/                                            # переиспользуемый rate limit
  Presentation/Http/Attribute/ RateLimited
  Infrastructure/Framework/Interceptor/ RateLimitInterceptor
```

Auth ссылается на пользователя через общий `UserId` из `Shared/Domain/ValueObject`
(уже есть), как это делает `Access`. В таблицу `users` и в `UserRepository` Auth напрямую
не лезет — только через `User/Application` (`docs/arch.md:131`, `docs/arch.md:141-146`).

### Таблицы (владелец — модуль Auth)

```text
auth_login_codes
  id              UUID v7 PK
  email           citext            -- по нему ищем; индекс
  code_hash       text              -- HMAC-SHA256(code, app secret); сам код не храним
  expires_at      datetime          -- now + ~5 мин
  attempts        int               -- счётчик неверных вводов, лимит -> код сгорает
  consumed_at     datetime null     -- одноразовость
  created_at, updated_at

auth_registration_tickets
  id              UUID v7 PK
  email           citext
  ticket_hash     text              -- HMAC-SHA256(ticket, app secret)
  expires_at      datetime          -- now + ~10 мин
  consumed_at     datetime null
  created_at, updated_at

auth_tokens                          -- наше Cycle-хранилище токенов
  id              UUID v7 PK
  user_id         UUID              -- UserId; индекс (для отзыва по пользователю)
  type            enum              -- access | refresh
  token_hash      text              -- SHA-256(token); сам токен у клиента
  expires_at      datetime
  created_at, updated_at
```

### Токены: своё Cycle-хранилище поверх spiral/auth

`spiral/auth` (входит в `spiral/framework` 3.16.2) даёт `TokenStorageInterface`,
`ActorProviderInterface`, `AuthMiddleware` и транспорт `header` (Bearer) — подтверждено по
документации Spiral 3.16. Берём этот механизм, но `TokenStorageInterface` реализуем **сами**
(`Auth/Infrastructure/Auth/CycleTokenStorage`) поверх своей сущности `AuthToken`, а не
готовое generic-хранилище `cycle-bridge` (`AUTH_TOKEN_STORAGE=cycle`).

Причина: generic-хранилище кладёт payload в JSON и не индексирует по пользователю — а нам
нужно: ротация refresh, отзыв при выходе, мульти-девайс и отзыв всех токенов при бане. Своя
таблица с колонкой `user_id` это решает и ложится в паттерн проекта (Repository + Cycle). Это
по-прежнему «хранилище Cycle», как и выбрано.

```text
выдача (verify/register): create access (TTL ~1ч) + create refresh (TTL ~30-60д)
                          -> клиенту отдаём сырые строки токенов
запрос к API:             AuthMiddleware (header) -> CycleTokenStorage.load(access)
                          -> UserActorProvider.getActor -> actor = AuthenticatedUser(UserId)
refresh:                  читаем refreshToken из тела -> load -> проверка type=refresh и срок
                          -> delete старого refresh (ротация) + create новой пары access+refresh
logout:                   delete текущего access (и refresh, если передан)
```

`UserActorProvider::getActor(token)` достаёт `userID` из payload и возвращает лёгкий объект
актора с `UserId`. Тяжёлой проверки в БД на каждом запросе не делаем: бан отзывает токены
(будущий `BanUser` дёргает `Auth/Application` для удаления токенов по `user_id`), а access
короткоживущий. Привязка актора к ролям/правам `Access` (GuardInterceptor) — вне рамок этого
захода.

Токены — 256-бит случайная строка (high-entropy), в БД только `SHA-256`. Коды и талоны —
низкоэнтропийные (6 цифр / короткая строка), поэтому хэшируем `HMAC-SHA256` с серверным
секретом (app key из `EncrypterBootloader`): при утечке БД без ключа перебор offline
невозможен. Сравнение — по хэшу (constant-time). Это `SecretHasherContract` с реализацией в
`Infrastructure/Hash`.

### Письмо с кодом — через outbox

Отправка письма — внешний эффект, поэтому идёт через transactional outbox (`docs/arch.md:514`),
а не напрямую из Handler-а:

```text
RequestLoginCode Handler (#[Transactional])
  -> сгенерировать код, сохранить LoginCode (хэш) через LoginCodeRepository
  -> OutboxEventStoreContract::add( LoginCodeRequested{email, code, locale} )
  -> EntityManager::run()                       # код и outbox в одной транзакции
outbox relay -> RabbitMQ -> SendLoginCodeJob -> Mailer (Mailpit в dev) шлёт письмо
```

Код в payload outbox идёт открытым текстом (иначе письмо не отправить) — это короткоживущая
запись в служебной таблице, не видна пользователю, риск принимаем. Локаль письма кладём в
сообщение: для нового email — из `Accept-Language` запроса (`LocaleMiddleware`), для
существующего — из `User.preferredLocale`; в очереди per-request локали нет (`docs/arch.md:382`),
поэтому язык переносим в сообщение.

### Rate limit — атрибут + интерцептор в Shared

```text
Shared/Presentation/Http/Attribute/RateLimited(maxAttempts, perSeconds)
Shared/Infrastructure/Framework/Interceptor/RateLimitInterceptor
  - общий, ничего не знает про email
  - ключ = client IP + действие (контроллер::метод), счётчики в Redis (уже есть)
  - превышение -> 429 (через packages/spiral-api-errors)
```

Вешаем `#[RateLimited]` на действия `AuthController` (request/verify/register/refresh).
Лимит «не чаще одного кода в минуту на email» и «N неверных вводов кода» — это доменное
правило, живёт внутри сценариев Auth по таблице `auth_login_codes` (поля `attempts`,
`expires_at`), а не в общем интерцепторе. Так общий механизм остаётся универсальным для
любого эндпоинта, а email-специфика — там, где есть смысл email.

### Сценарии (CQRS)

```text
RequestLoginCode(email)            Command #[Transactional]
  валидирует email (Filter -> 422), генерит код, гасит/обновляет прошлый код для email,
  пишет LoginCode (hash), кладёт LoginCodeRequested в outbox. Ответ всегда 200
  (не раскрываем, есть ли такой пользователь).

VerifyLoginCode(email, code)       Command #[Transactional]
  грузит активный LoginCode по email, сверяет hash, проверяет срок и attempts.
  Неверно -> attempts++, AuthenticationException (401). Верно -> consumed.
    - User по email существует (через User/Application) и не Banned/Deleted -> выдать пару токенов
      (если был WaitingEmailConfirmation -> confirmEmail()).
    - User не существует -> создать RegistrationTicket, вернуть его + needsProfile.
    - User Banned/Deleted -> отказ (Forbidden/Authentication).

CompleteRegistration(ticket,name,nickname)  Command #[Transactional]
  валидирует и гасит ticket, вызывает User/Application CreateUser(email,name,nickname,locale)
  (он же отвечает за уникальность ника и ReservedNickname), выдаёт пару токенов.

RefreshTokens(refreshToken)        Command #[Transactional]
  load -> type=refresh + срок -> delete (ротация) -> create access+refresh.

Logout(token)                      Command #[Transactional]
  delete токена(ов) текущей сессии.
```

### Зависимость от User/Application (важно)

Auth не может ходить в `UserRepository` напрямую (`docs/arch.md:141-146`), а у модуля `User`
сейчас **нет** слоя Application — только Domain + Repository + Infrastructure. Значит для Auth
нужно, чтобы в `User/Application` появились минимум:

```text
CreateUser(email, name, nickname, locale)   -> создаёт User (Active), держит уникальность ника
FindUserForAuth(email)                       -> есть ли юзер + его UserId + статус (active/banned/deleted)
```

`User-domain` research уже ставит создание `User/Application` (CreateUser и т.д.) следующим
шагом (`docs/researches/2026-06-13_12-33_user-domain.md:267`). Поэтому план Auth должен либо
включать эти две User-команды, либо идти после плана User. Это единственная жёсткая внешняя
зависимость Auth.

### Границы зависимостей

```text
Auth/Domain        -> Shared/Domain (UserId), свой Domain
Auth/Application    -> Auth/Domain, Auth/Repository, Auth/Contract, User/Application, Outbox/Application
Auth/Infrastructure -> spiral/auth (TokenStorage, ActorProvider), свой Contract, Redis, Cycle
Auth/Presentation   -> Auth/Application, Shared RateLimited

 succeeded in 0ms:
---
title: Проектирование домена пользователя и авторизации (User + Access)
date: 2026-06-13 12:33
mode: normal
decision_mode: recommend_and_ask
status: draft
reviewer: none
sources:
  rules: docs/rules.md
  arch: docs/arch.md
---

# Проектирование домена пользователя и авторизации (User + Access)

## Суть

Проектируем доменный слой и Value Object-ы модуля пользователя для YogaLoka. Аутентификацию (пароли, токены, login/email-verification flow) сейчас НЕ проектируем — только домен: сущности, VO, enum, статусы, связи и границы модулей.

Вход — черновая структура таблиц от пользователя: профиль (имя, духовное имя, bio, место, email, псевдоним, аватар, флаг «подтверждён», статус, таймстампы), роли, права, связи `role_permissions` и `user_roles`. Дополнительно нужно предложить, что упущено.

Рамка проекта: модульный монолит на Spiral + Cycle ORM, тактический DDD, CQRS (`docs/arch.md:5`, `docs/arch.md:13`). Идентификаторы — UUID v7 (`docs/rules.md:31`). Доменные примитивы — VO/enum, Entity без примитивов, валидация только в VO, доступ к данным через репозитории (`docs/rules.md:36`, `docs/rules.md:37`, `docs/rules.md:39`). Есть эталонный модуль `Media` и общие базовые VO (`UserId`, `AbstractUuidV7Id`, `AbstractIntegerValue`) в `Shared/Domain/ValueObject`.

## Решение

Разносим на два модуля: **`User`** (профиль, статус, верификация, баны) и **`Access`** (роли, права, проверки доступа). Граница между ними и `Media` идёт только через `Application` (`docs/arch.md:131`).

### Карта модулей

```text
Modules/
  User/
    Domain/
      Entity/        User, UserBan
      ValueObject/   Email, UserNickname, UserName, UserSpiritualName,
                     UserBio, UserLocation, UserTimezone, UserAvatar,
                     UserDeletion, BanReason, BanExpiration, UserBanId
      Enum/          UserStatus, UserVerification
    Application/     Command/Query (CreateUser, UpdateUserProfile, SetUserAvatar,
                     BanUser, UnbanUser, VerifyUser, DeleteUser, GetUserProfile ...)
    Repository/      UserRepository, UserBanRepository
    Infrastructure/  Cycle/ (typecast nullable VO)
    Presentation/    Http/ (Controller, Filter, Resource)

  Access/
    Domain/
      Entity/        Role, Permission
      ValueObject/   RoleId, RoleSlug, PermissionId, PermissionSlug
      Enum/          RoleName, PermissionName   # канон ролей/прав в коде
    Application/     AssignRoleToUser, RevokeRoleFromUser,
                     CheckUserHasPermission, GetUserRoles, GetUserPermissions
    Repository/      RoleRepository, PermissionRepository, UserRoleRepository
    Infrastructure/  Bootloader (сидер ролей/прав из enum)

Shared/Domain/
  Enum/Locale         # ru/en, общая сквозная концепция (LocaleConfig.supported)
  ValueObject/UserId  # уже есть; используется и User, и Access, и Media
```

`UserId` уже лежит в `Shared/Domain/ValueObject/UserId.php:7` — поэтому `Access` ссылается на пользователя через общий `UserId`, не зная Entity `User`. Цикла зависимостей `User ↔ Access` нет.

### Сущности

| Сущность | Таблица | Назначение |
|---|---|---|
| `User` | `users` | Профиль, email, псевдоним, статус, верификация, аватар, локаль, мягкое удаление |
| `UserBan` | `user_bans` | Журнал банов/разбанов (история модерации) |
| `Role` | `roles` | Роль (справочник, сид из кода) |
| `Permission` | `permissions` | Право (справочник, сид из кода) |
| `RolePermission` | `role_permissions` | Связь роль↔право (many-to-many) |
| `UserRole` | `user_roles` | Связь пользователь↔роль (many-to-many) |

### Entity `User`

| Поле | Доменный тип | Колонка БД | Null | Правила |
|---|---|---|---|---|
| `id` | `UserId` (Shared, UUID v7) | `id` uuid PK | нет | генерируется при `create()` |
| `name` | `UserName` | `name` | нет | буквы/пробел/дефис/апостроф, без эмодзи и спецсимволов, 1–100 |
| `spiritualName` | `UserSpiritualName` (null-object) | `spiritual_name` | да | те же правила, опционально |
| `bio` | `UserBio` (null-object) | `bio` text | да | свободный текст, эмодзи разрешены, без управляющих символов, ≤500 |
| `location` | `UserLocation` (null-object) | `location` | да | буквы/цифры/пробел/пунктуация, без эмодзи, ≤100 |
| `email` | `Email` | `email` citext UNIQUE | нет | RFC, нормализация в lower, ≤254, уникален |
| `nickname` | `UserNickname` | `nickname` citext UNIQUE | нет | `[a-z0-9._-]`, 3–30, не начинается/кончается точкой, без `..`, lower, уникален |
| `avatar` | `UserAvatar` (null-object) | `avatar_media_id` uuid | да | ссылка на `Media.id`, аватар один; FK RESTRICT |
| `verification` | `UserVerification` (enum) | `verification` | нет | `Unverified` по умолчанию |
| `status` | `UserStatus` (enum) | `status` | нет | `WaitingEmailConfirmation` по умолчанию |
| `preferredLocale` | `Locale` (enum, Shared) | `preferred_locale` | нет | `ru`/`en` |
| `timezone` | `UserTimezone` | `timezone` | нет | IANA-идентификатор (валидируется списком), дефолт задаёт сценарий |
| `deletion` | `UserDeletion` (null-object) | `deleted_at` datetime | да | soft-delete |
| `createdAt` | `\DateTimeImmutable` (`HasTimestamps`) | `created_at` | нет | |
| `updatedAt` | `\DateTimeImmutable` (`HasTimestamps`) | `updated_at` | нет | |

`User::create()` принимает обязательные VO (`UserName`, `Email`, `UserNickname`, `Locale`, `UserTimezone`), ставит `status = WaitingEmailConfirmation`, `verification = Unverified`, опциональные поля — в null-object `none()`, и инициализирует таймстампы. Доменные методы (примеры): `rename()`, `changeSpiritualName()`, `changeBio()`, `changeLocation()`, `changeEmail()` (сбрасывает в `WaitingEmailConfirmation`), `confirmEmail()` (→ `Active`), `setAvatar(UserAvatar)`, `removeAvatar()`, `verify()`/`unverify()`, `ban()`/`unban()`, `markDeleted()`, `isActive()`/`isVerified()`/`isBanned()` (чистые predicate, единственный разрешённый `bool` — `docs/rules.md:36`).

`name`/`spiritualName`/`bio`/`location` — это VO, вся валидация и нормализация внутри них; Entity их не проверяет (`docs/rules.md:39`).

### Enum-ы User

```text
UserStatus: string
  WaitingEmailConfirmation   # создан, ждёт подтверждения почты
  Active                     # подтверждён, активен
  Banned                     # забанен (детали в user_bans)
  Deleted                    # мягко удалён (deleted_at заполнен)

UserVerification: string     # «галочка», в будущем платная услуга
  Unverified
  Verified
```

`status` и `verification` — две независимые оси: статус = жизненный цикл аккаунта, verification = бейдж доверия. Закрытые наборы → enum (`docs/rules.md:30`), исчерпывающий `match` без `default` (`docs/rules.md:16`).

### Value Object-ы и правила валидации

Все простые VO следуют паттерну проекта: `final readonly class`, `Stringable`, `JsonSerializable`, приватный конструктор, фабрика `fromString()` с валидацией, `value()`, `equals()`; бросают `InvalidDomainValueException` (500), не `ValidationException` (`docs/rules.md:37`, `docs/rules.md:40`). На HTTP-границе Filter/Handler проверяет вход и отдаёт 422 до построения VO (как `AbstractIntegerValue::supports()` в `app/src/Shared/Domain/ValueObject/AbstractIntegerValue.php:36`).

| VO | Модуль | Тип | Валидация |
|---|---|---|---|
| `Email` | User/Domain | строка | trim+lower, формат RFC (`filter_var`), ≤254, не пусто |
| `UserNickname` | User/Domain | строка | lower, `^[a-z0-9](?:[a-z0-9._-]{1,28})[a-z0-9]$`, без `..`, 3–30 |
| `UserName` | User/Domain | строка, NFC | whitelist `\p{L}\p{M}` + пробел/дефис/апостроф, 1–100, схлопывание пробелов |
| `UserSpiritualName` | User/Domain | null-object | как `UserName`, `none()` для пустого |
| `UserBio` | User/Domain | null-object | без `\p{Cc}` (кроме `\n`), эмодзи разрешены, ≤500, `none()` |
| `UserLocation` | User/Domain | null-object | whitelist `\p{L}\p{N}` + пробел/`.,-`, без эмодзи (`\p{So}\p{Cs}` запрещены), ≤100, `none()` |
| `UserTimezone` | User/Domain | строка | `in_array($tz, \DateTimeZone::listIdentifiers(), true)` |
| `UserAvatar` | User/Domain | null-object | `none()` / `pointingTo(string mediaId)` (UUID v7); `value(): ?string` |
| `UserDeletion` | User/Domain | null-object | `active()` / `at(\DateTimeImmutable)`; predicate `isDeleted()` |
| `BanReason` | User/Domain | строка | trim, не пусто, ≤500 |
| `BanExpiration` | User/Domain | null-object | `permanent()` / `until(\DateTimeImmutable)` |
| `UserBanId` | User/Domain | UUID v7 | `extends AbstractUuidV7Id` |
| `RoleId`/`PermissionId` | Access/Domain | UUID v7 | `extends AbstractUuidV7Id` |
| `RoleSlug`/`PermissionSlug` | Access/Domain | строка | `^[a-z][a-z0-9.]*[a-z0-9]$` (напр. `admin`, `user.ban`) |

Запрет эмодзи/спецсимволов делаем **whitelist-подходом** (разрешаем только нужные категории Unicode через PCRE `\p{...}`), а не blacklist-ом эмодзи — так автоматически отсекаются и эмодзи, и неожиданные служебные символы. PCRE `\p{L}`/`\p{N}`/`\p{So}` встроены в PHP, внешних пакетов не требуется. NFC-нормализация имён желательна, но `ext-intl` в зависимостях нет (`composer.json:12-16`, есть только `ext-mbstring`); на старте нормализуем через `mbstring` (trim, схлопывание пробелов) — риск: визуально одинаковые имена в разных Unicode-формах будут считаться разными. Снятие риска (опционально, решение плана): добавить `ext-intl` и `Normalizer::normalize(..., FORM_C)` в фабриках текстовых VO.

### Аватар: связь с `Media` через `Application`, без нарушения границ

Аватар — часть `User`, `Media` про аватар не знает (`docs/arch.md:156`). Публичный API `Media/Application` принимает примитивы (`CheckMediaIsImageQuery:9` — `string $mediaId`; `MakeMediaPermanentCommand:9-11` — `string $userId, string $mediaId`), поэтому `User` хранит ссылку собственным VO `UserAvatar` и передаёт `value()` на границу, **не импортируя `Media\Domain`**. `MediaId` переносить в `Shared` не нужно.

Поток (ровно пример из `docs/arch.md:150-154`):

```text
User/Application/SetUserAvatar
  -> Media/Application CheckMediaIsImage(mediaId)    # существует и это картинка
  -> Media/Application MakeMediaPermanent(userId, mediaId)  # снять expiresAt, иначе cleanup удалит аватар
  -> User/Domain User::setAvatar(UserAvatar::pointingTo(mediaId))
  -> persist
```

`MakeMediaPermanent` обязателен: загруженное медиа временное (`expiresAt = now + 1 day`, `docs/researches/2026-05-20_18-42_media-domain.md:455-463`); без перевода в permanent аватар вычистит фоновая очистка. На уровне БД — `users.avatar_media_id` FK → `media(id)` `ON DELETE RESTRICT` (БД запрещает удалить медиа, пока оно чей-то аватар — согласуется с принципом media-домена «Media нельзя удалить, пока есть связь», `docs/researches/2026-05-20_18-42_media-domain.md:425-433`). Смена/снятие аватара — отдельный сценарий, который при необходимости просит `Media/Application` удалить старый объект.

Отвергнутая альтернатива: перенос `MediaId` в `Shared` и хранение `MediaId` в `User`. Не нужен, потому что граница `Media` уже принимает строки; перенос дал бы лишний рефактор `Media` без выгоды.

### Мягкое удаление

Удаление аккаунта — soft-delete: `deleted_at` + `status = Deleted`, физически строка не удаляется. Состояние в домене выражается null-object `UserDeletion` (`active()`/`at(dt)`), а не `?datetime` (`docs/rules.md:21`). Это отличается от решения по `Media` (там hard-delete, `docs/researches/2026-05-20_18-42_media-domain.md:72`) — осознанно: у пользователя есть юридические/UX-причины сохранять аккаунт (история, восстановление, аудит модерации).

Открытый продуктовый момент, вынесенный в план: освобождение `email`/`nickname` удалённого пользователя. На старте — НЕ освобождаем (уникальный индекс держит занятость), потому что это проще и безопаснее для восстановления. Если потребуется переиспользование — отдельный сценарий анонимизации (перезапись `email`/`nickname` на синтетические при удалении). Риск молчаливого «занятого» псевдонима принимаем на старте, фиксируем здесь как известное ограничение.

### Баны: отдельная таблица-журнал

`user_bans` — журнал (история), а не одно поле в `users` (выбор пользователя). Текущее состояние денормализовано в `users.status = Banned` для быстрых проверок.

```text
UserBan (user_bans)
  id            UserBanId (UUID v7)
  userId        UserId            -> users.id  (FK CASCADE)
  bannedById    UserId            кто забанил (админ)
  reason        BanReason
  expiresAt     BanExpiration     null-object: permanent / until(dt)
  liftedAt      null-object       снят ли досрочно (когда)
  liftedById    UserId | none     кто снял
  createdAt / updatedAt
```

Активный бан — запись, где `liftedAt` пуст и (`expiresAt` permanent или в будущем). `BanUser`: создаёт `UserBan` + `User::ban()` (`status = Banned`) в одной транзакции (`#[Transactional]`). `UnbanUser`: проставляет `liftedAt`/`liftedById` + `User::unban()` (`status = Active`). FK `user_bans.user_id` → `users.id` `ON DELETE CASCADE` (журнал живёт ровно столько, сколько строка пользователя; при soft-delete строка остаётся).

### Модуль `Access`: роли, права, RBAC

Полные таблицы-справочники (выбор пользователя), но **источник истины — код**: канон ролей и прав определён enum `RoleName`/`PermissionName`, а инфраструктурный сидер (`Access/Infrastructure/Bootloader`) создаёт/обновляет строки `roles`/`permissions`/`role_permissions` из этого enum при деплое. Так совмещаем требование «таблицы-справочники» с правилами «enum вместо строк» и «создаются только из кода» (`docs/rules.md:30`).

```text
roles            id (RoleId), slug (RoleSlug)                 UNIQUE(slug)
permissions      id (PermissionId), slug (PermissionSlug)     UNIQUE(slug)
role_permissions role_id -> roles.id, permission_id -> permissions.id   (PK составной, FK CASCADE)
user_roles       user_id (UserId), role_id -> roles.id        (PK составной, FK CASCADE)
```

Канон (стартовый, расширяется в плане):

```text
RoleName: admin
PermissionName: user.ban, user.verify, user.role.assign, ...   # уточняется при реализации сценариев
admin -> все права
```

Проверки доступа — Query в `Access/Application`: `CheckUserHasPermission(userId, PermissionName)`, `GetUserRoles(userId)`, `GetUserPermissions(userId)`. Они грузят роли пользователя через `UserRoleRepository`, разворачивают в права через `role_permissions` и сравнивают с `PermissionName`. Назначение роли — `AssignRoleToUser(userId, RoleName)`.

По умолчанию пользователь **без роли** (нет строки в `user_roles`) — отсутствие роли = нет прав. Роль `admin` обязательна и создаётся сидером. `User/Application` при необходимости назначить роль вызывает `Access/Application/AssignRoleToUser` (кросс-модульно через Application, `docs/arch.md:131`).

Связь `user_roles` хранит `UserId` без FK на `users` в строгом ORM-смысле кросс-модульно: на уровне БД допустим FK `user_roles.user_id` → `users.id` `ON DELETE CASCADE` как инвариант целостности (одна БД монолита), но `Access` в коде не обращается к таблице/репозиторию `User` (`docs/arch.md:141-146`).

### Уникальность и индексы

`email` и `nickname` уникальны без учёта регистра. Решение: тип PostgreSQL `citext` + `UNIQUE` (либо `text` + уникальный функциональный индекс по `lower(...)`). VO дополнительно нормализуют значение в нижний регистр до записи, поэтому конфликт регистра невозможен и на уровне домена. Проверку занятости делаем check-then-act в Handler-е (`UserRepository::findByEmail()`/`findByNickname()`), при гонке дубликат ловит уникальный индекс как 500 (`docs/rules.md:47`).

### Что было упущено в исходной структуре (предложения, принятые в домен)

```text
preferredLocale   язык интерфейса/писем (ru/en) — проект многоязычный (LocaleMiddleware, docs/arch.md:382)
timezone          для расписаний/времени уведомлений
status = Deleted  + deleted_at — мягкое удаление
user_bans         журнал банов: кто, кого, почему, до когда, когда снят
verification      выделено в отдельную ось от status (бейдж ≠ жизненный цикл)
canon enum        RoleName/PermissionName как источник истины для сидера
citext/lower      явная стратегия регистронезависимой уникальности email/nickname
```

### Границы (итоговая схема зависимостей)

```text
User/Domain        -> Shared/Domain (UserId, Locale), свой Domain
User/Application    -> User/Domain, User/Repository, Media/Application, Access/Application
Access/Domain       -> Shared/Domain (UserId), свой Domain
Access/Application  -> Access/Domain, Access/Repository
Media               про аватар/пользователя-как-профиль не знает; принимает примитивы на границе
```

## Ответы на вопросы

```text
Как моделировать роли и права?
  Полные таблицы-справочники: roles, permissions, role_permissions, user_roles.
  Источник истины — enum RoleName/PermissionName в коде; сид из кода при деплое.

Где разместить роли, права и проверки доступа?
  Отдельный модуль Access (рекомендация принята). User обращается к Access/Application.
  Плюсы/минусы разобраны: чистая граница и переиспользование будущим Auth против чуть большей
  структуры на старте. UserId уже в Shared, поэтому разделение бесшовно.

Как обрабатывать удаление аккаунта?
  Soft-delete: deleted_at + status = Deleted. Физически строку не удаляем
  (в отличие от hard-delete в Media). email/nickname на старте не освобождаем.

Какие поля добавить сверх списка?
  Язык/локаль (preferredLocale), таймзона (timezone),
  метаданные бана — отдельной таблицей user_bans (журнал: кто/кого/почему/срок/снятие).

Аутентификация?
  Не проектируем сейчас. Статус WaitingEmailConfirmation и переход confirmEmail()
  заложены в домен, но сам verification-flow (коды/токены) — вне рамок.

Verified vs статус подтверждения почты?
  Это разные оси: UserVerification (бейдж, будущая платная услуга) и
  UserStatus.WaitingEmailConfirmation (жизненный цикл). Разделены.

Аватар — поле или таблица связи?
  Поле avatar_media_id (1:1, «аватар один»). FK -> media RESTRICT.
  Поток через Media/Application (CheckMediaIsImage + MakeMediaPermanent).
```


exec
/bin/zsh -lc 'rg --files app/src/Modules/User app/src/Modules/Access app/src/Modules/Auth app/src/Modules 2>/dev/null | sort' in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
app/src/Modules/Access/Domain/Collection/PermissionCollection.php
app/src/Modules/Access/Domain/Collection/PermissionCollection.php
app/src/Modules/Access/Domain/Collection/RoleCollection.php
app/src/Modules/Access/Domain/Collection/RoleCollection.php
app/src/Modules/Access/Domain/Collection/RolePermissionCollection.php
app/src/Modules/Access/Domain/Collection/RolePermissionCollection.php
app/src/Modules/Access/Domain/Collection/UserRoleCollection.php
app/src/Modules/Access/Domain/Collection/UserRoleCollection.php
app/src/Modules/Access/Domain/Entity/Permission.php
app/src/Modules/Access/Domain/Entity/Permission.php
app/src/Modules/Access/Domain/Entity/Role.php
app/src/Modules/Access/Domain/Entity/Role.php
app/src/Modules/Access/Domain/Entity/RolePermission.php
app/src/Modules/Access/Domain/Entity/RolePermission.php
app/src/Modules/Access/Domain/Entity/UserRole.php
app/src/Modules/Access/Domain/Entity/UserRole.php
app/src/Modules/Access/Domain/Enum/PermissionName.php
app/src/Modules/Access/Domain/Enum/PermissionName.php
app/src/Modules/Access/Domain/Enum/RoleName.php
app/src/Modules/Access/Domain/Enum/RoleName.php
app/src/Modules/Access/Domain/ValueObject/PermissionId.php
app/src/Modules/Access/Domain/ValueObject/PermissionId.php
app/src/Modules/Access/Domain/ValueObject/PermissionSlug.php
app/src/Modules/Access/Domain/ValueObject/PermissionSlug.php
app/src/Modules/Access/Domain/ValueObject/RoleId.php
app/src/Modules/Access/Domain/ValueObject/RoleId.php
app/src/Modules/Access/Domain/ValueObject/RolePermissionId.php
app/src/Modules/Access/Domain/ValueObject/RolePermissionId.php
app/src/Modules/Access/Domain/ValueObject/RoleSlug.php
app/src/Modules/Access/Domain/ValueObject/RoleSlug.php
app/src/Modules/Access/Domain/ValueObject/UserRoleId.php
app/src/Modules/Access/Domain/ValueObject/UserRoleId.php
app/src/Modules/Access/Repository/PermissionRepository.php
app/src/Modules/Access/Repository/PermissionRepository.php
app/src/Modules/Access/Repository/RolePermissionRepository.php
app/src/Modules/Access/Repository/RolePermissionRepository.php
app/src/Modules/Access/Repository/RoleRepository.php
app/src/Modules/Access/Repository/RoleRepository.php
app/src/Modules/Access/Repository/UserRoleRepository.php
app/src/Modules/Access/Repository/UserRoleRepository.php
app/src/Modules/Media/Application/Command/Media/CompleteMediaUpload/CompleteMediaUploadCommand.php
app/src/Modules/Media/Application/Command/Media/CompleteMediaUpload/CompleteMediaUploadHandler.php
app/src/Modules/Media/Application/Command/Media/DeleteMedia/DeleteMediaCommand.php
app/src/Modules/Media/Application/Command/Media/DeleteMedia/DeleteMediaHandler.php
app/src/Modules/Media/Application/Command/Media/MakeMediaPermanent/MakeMediaPermanentCommand.php
app/src/Modules/Media/Application/Command/Media/MakeMediaPermanent/MakeMediaPermanentHandler.php
app/src/Modules/Media/Application/Command/Media/ProcessMedia/ProcessMediaCommand.php
app/src/Modules/Media/Application/Command/Media/ProcessMedia/ProcessMediaHandler.php
app/src/Modules/Media/Application/Command/Media/RecordMediaProcessingFailure/RecordMediaProcessingFailureCommand.php
app/src/Modules/Media/Application/Command/Media/RecordMediaProcessingFailure/RecordMediaProcessingFailureHandler.php
app/src/Modules/Media/Application/Command/Media/RequestMediaUpload/RequestMediaUploadCommand.php
app/src/Modules/Media/Application/Command/Media/RequestMediaUpload/RequestMediaUploadHandler.php
app/src/Modules/Media/Application/Contract/MediaFileServiceContract.php
app/src/Modules/Media/Application/Contract/MediaImageProcessorContract.php
app/src/Modules/Media/Application/Dto/MediaConversionResult.php
app/src/Modules/Media/Application/Dto/MediaConversionSpec.php
app/src/Modules/Media/Application/Dto/MediaFileMeta.php
app/src/Modules/Media/Application/Dto/MediaObjectHead.php
app/src/Modules/Media/Application/Dto/MediaPresignedPart.php
app/src/Modules/Media/Application/Dto/MediaPresignedPartCollection.php
app/src/Modules/Media/Application/Dto/MediaResult.php
app/src/Modules/Media/Application/Dto/MediaUploadMode.php
app/src/Modules/Media/Application/Dto/MediaUploadSpec.php
app/src/Modules/Media/Application/Dto/MediaUrlResult.php
app/src/Modules/Media/Application/Dto/RequestMediaUploadResult.php
app/src/Modules/Media/Application/Exception/MediaFileServiceFailedException.php
app/src/Modules/Media/Application/Message/MediaUploaded.php
app/src/Modules/Media/Application/Query/Media/CheckMediaExists/CheckMediaExistsHandler.php
app/src/Modules/Media/Application/Query/Media/CheckMediaExists/CheckMediaExistsQuery.php
app/src/Modules/Media/Application/Query/Media/CheckMediaIsImage/CheckMediaIsImageHandler.php
app/src/Modules/Media/Application/Query/Media/CheckMediaIsImage/CheckMediaIsImageQuery.php
app/src/Modules/Media/Application/Query/Media/GetMediaUrl/GetMediaUrlHandler.php
app/src/Modules/Media/Application/Query/Media/GetMediaUrl/GetMediaUrlQuery.php
app/src/Modules/Media/Application/Service/MediaTypeResolver.php
app/src/Modules/Media/Domain/Collection/MediaCollection.php
app/src/Modules/Media/Domain/Collection/MediaImageConversionCollection.php
app/src/Modules/Media/Domain/Collection/MediaMimeTypeCollection.php
app/src/Modules/Media/Domain/Collection/MediaMultipartPartCollection.php
app/src/Modules/Media/Domain/Collection/MediaVideoConversionCollection.php
app/src/Modules/Media/Domain/Entity/Media.php
app/src/Modules/Media/Domain/Entity/MediaImageConversion.php
app/src/Modules/Media/Domain/Entity/MediaMultipartUpload.php
app/src/Modules/Media/Domain/Entity/MediaVideoConversion.php
app/src/Modules/Media/Domain/Enum/MediaConversionStatus.php
app/src/Modules/Media/Domain/Enum/MediaImageConversionType.php
app/src/Modules/Media/Domain/Enum/MediaStatus.php
app/src/Modules/Media/Domain/Enum/MediaStorage.php
app/src/Modules/Media/Domain/Enum/MediaType.php
app/src/Modules/Media/Domain/Enum/MediaVideoConversionType.php
app/src/Modules/Media/Domain/Enum/MediaVisibility.php
app/src/Modules/Media/Domain/ValueObject/MediaBitrate.php
app/src/Modules/Media/Domain/ValueObject/MediaDuration.php
app/src/Modules/Media/Domain/ValueObject/MediaExpiration.php
app/src/Modules/Media/Domain/ValueObject/MediaFileSize.php
app/src/Modules/Media/Domain/ValueObject/MediaId.php
app/src/Modules/Media/Domain/ValueObject/MediaImageConversionId.php
app/src/Modules/Media/Domain/ValueObject/MediaMimeType.php
app/src/Modules/Media/Domain/ValueObject/MediaMultipartPart.php
app/src/Modules/Media/Domain/ValueObject/MediaMultipartPartETag.php
app/src/Modules/Media/Domain/ValueObject/MediaMultipartPartNumber.php
app/src/Modules/Media/Domain/ValueObject/MediaMultipartPartSize.php
app/src/Modules/Media/Domain/ValueObject/MediaMultipartPartsCount.php
app/src/Modules/Media/Domain/ValueObject/MediaMultipartUploadId.php
app/src/Modules/Media/Domain/ValueObject/MediaMultipartUploadIdValue.php
app/src/Modules/Media/Domain/ValueObject/MediaPath.php
app/src/Modules/Media/Domain/ValueObject/MediaPixelDimension.php
app/src/Modules/Media/Domain/ValueObject/MediaPresignedTtl.php
app/src/Modules/Media/Domain/ValueObject/MediaProcessingAttempts.php
app/src/Modules/Media/Domain/ValueObject/MediaProcessingError.php
app/src/Modules/Media/Domain/ValueObject/MediaStorageKey.php
app/src/Modules/Media/Domain/ValueObject/MediaVideoConversionId.php
app/src/Modules/Media/Infrastructure/Bootloader/MediaBootloader.php
app/src/Modules/Media/Infrastructure/Cycle/MediaExpirationTypecast.php
app/src/Modules/Media/Infrastructure/Cycle/MediaMultipartPartCollectionTypecast.php
app/src/Modules/Media/Infrastructure/Cycle/MediaProcessingErrorTypecast.php
app/src/Modules/Media/Infrastructure/Exception/MediaImageProcessorException.php
app/src/Modules/Media/Infrastructure/Exception/MediaStorageNotConfiguredException.php
app/src/Modules/Media/Infrastructure/FileService/ConfiguredS3ClientProvider.php
app/src/Modules/Media/Infrastructure/FileService/ImagickMediaImageProcessor.php
app/src/Modules/Media/Infrastructure/FileService/S3ClientProvider.php
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php
app/src/Modules/Media/Presentation/Job/ProcessMediaJob.php
app/src/Modules/Media/README.md
app/src/Modules/Media/Repository/MediaImageConversionRepository.php
app/src/Modules/Media/Repository/MediaMultipartUploadRepository.php
app/src/Modules/Media/Repository/MediaRepository.php
app/src/Modules/Media/Repository/MediaVideoConversionRepository.php
app/src/Modules/Outbox/Application/Command/ProcessDebugLogMessage/ProcessOutboxDebugLogMessageCommand.php
app/src/Modules/Outbox/Application/Command/ProcessDebugLogMessage/ProcessOutboxDebugLogMessageHandler.php
app/src/Modules/Outbox/Application/Command/RelayOutbox/RelayOutboxCommand.php
app/src/Modules/Outbox/Application/Command/RelayOutbox/RelayOutboxHandler.php
app/src/Modules/Outbox/Application/Contract/OutboxEventStoreContract.php
app/src/Modules/Outbox/Application/Contract/OutboxJobRegistryContract.php
app/src/Modules/Outbox/Application/Contract/OutboxMessageLoaderContract.php
app/src/Modules/Outbox/Application/Contract/OutboxMessageSerializerContract.php
app/src/Modules/Outbox/Application/Contract/OutboxRelayContract.php
app/src/Modules/Outbox/Application/Contract/OutboxRelayLoopControlContract.php
app/src/Modules/Outbox/Application/Contract/OutboxRelaySleeperContract.php
app/src/Modules/Outbox/Application/Contract/OutboxRelayWorkerContract.php
app/src/Modules/Outbox/Application/Exception/OutboxMessageLoadingException.php
app/src/Modules/Outbox/Application/Exception/OutboxMessageSerializationException.php
app/src/Modules/Outbox/Application/Message/OutboxDebugLogMessage.php
app/src/Modules/Outbox/Application/Message/OutboxMessage.php
app/src/Modules/Outbox/Application/Message/OutboxQueueEnvelope.php
app/src/Modules/Outbox/Application/Message/SerializedOutboxMessage.php
app/src/Modules/Outbox/Application/Message/StoredOutboxEventId.php
app/src/Modules/Outbox/Domain/Collection/OutboxEventCollection.php
app/src/Modules/Outbox/Domain/Entity/StoredOutboxEvent.php
app/src/Modules/Outbox/Domain/Enum/OutboxEventStatus.php
app/src/Modules/Outbox/Domain/ValueObject/EmptyOutboxEventDate.php
app/src/Modules/Outbox/Domain/ValueObject/KnownOutboxEventDate.php
app/src/Modules/Outbox/Domain/ValueObject/OutboxAttempts.php
app/src/Modules/Outbox/Domain/ValueObject/OutboxAvailableAt.php
app/src/Modules/Outbox/Domain/ValueObject/OutboxEventDate.php
app/src/Modules/Outbox/Domain/ValueObject/OutboxEventId.php
app/src/Modules/Outbox/Domain/ValueObject/OutboxEventPayload.php
app/src/Modules/Outbox/Domain/ValueObject/OutboxEventType.php
app/src/Modules/Outbox/Domain/ValueObject/OutboxLastError.php
app/src/Modules/Outbox/Domain/ValueObject/OutboxMaxAttempts.php
app/src/Modules/Outbox/Domain/ValueObject/OutboxRelayBatchSize.php
app/src/Modules/Outbox/Domain/ValueObject/OutboxRelaySleepSeconds.php
app/src/Modules/Outbox/Infrastructure/Bootloader/OutboxBootloader.php
app/src/Modules/Outbox/Infrastructure/Bootloader/OutboxConsoleBootloader.php
app/src/Modules/Outbox/Infrastructure/Cycle/OutboxAvailableAtTypecast.php
app/src/Modules/Outbox/Infrastructure/Cycle/OutboxEventDateTypecast.php
app/src/Modules/Outbox/Infrastructure/Cycle/OutboxEventPayloadTypecast.php
app/src/Modules/Outbox/Infrastructure/Cycle/OutboxLastErrorTypecast.php
app/src/Modules/Outbox/Infrastructure/Exception/OutboxJobRegistryException.php
app/src/Modules/Outbox/Infrastructure/Exception/OutboxRelayStoppedException.php
app/src/Modules/Outbox/Infrastructure/Message/OutboxEventStore.php
app/src/Modules/Outbox/Infrastructure/Message/OutboxMessageLoader.php
app/src/Modules/Outbox/Infrastructure/Message/ValinorOutboxMessageSerializer.php
app/src/Modules/Outbox/Infrastructure/Queue/OutboxQueueHeaders.php
app/src/Modules/Outbox/Infrastructure/Queue/OutboxQueuePublisher.php
app/src/Modules/Outbox/Infrastructure/Queue/OutboxQueueSerializer.php
app/src/Modules/Outbox/Infrastructure/Queue/OutboxQueueStatusInterceptor.php
app/src/Modules/Outbox/Infrastructure/Registry/OutboxJobRegistry.php
app/src/Modules/Outbox/Infrastructure/Relay/InfiniteOutboxRelayLoopControl.php
app/src/Modules/Outbox/Infrastructure/Relay/OutboxRelay.php
app/src/Modules/Outbox/Infrastructure/Relay/OutboxRelayWorker.php
app/src/Modules/Outbox/Infrastructure/Relay/SystemOutboxRelaySleeper.php
app/src/Modules/Outbox/Presentation/Console/OutboxRelayCommand.php
app/src/Modules/Outbox/Presentation/Job/OutboxDebugLogJob.php
app/src/Modules/Outbox/README.md
app/src/Modules/Outbox/Repository/OutboxEventRepository.php
app/src/Modules/System/Presentation/Console/OpenApiGenerateCommand.php
app/src/Modules/System/Presentation/Console/OpenApiPublishAssetsCommand.php
app/src/Modules/System/Presentation/Exception/OpenApiAssetsPublicationException.php
app/src/Modules/System/Presentation/Http/Controller/HealthController.php
app/src/Modules/System/Presentation/Http/Controller/SwaggerController.php
app/src/Modules/System/Presentation/Http/Enum/HealthStatus.php
app/src/Modules/System/Presentation/Http/Resource/HealthResource.php
app/src/Modules/System/Presentation/Http/View/SwaggerView.php
app/src/Modules/System/Presentation/Temporal/Ping.php
app/src/Modules/User/Domain/Entity/ReservedNickname.php
app/src/Modules/User/Domain/Entity/ReservedNickname.php
app/src/Modules/User/Domain/Entity/User.php
app/src/Modules/User/Domain/Entity/User.php
app/src/Modules/User/Domain/Entity/UserBan.php
app/src/Modules/User/Domain/Entity/UserBan.php
app/src/Modules/User/Domain/Enum/UserStatus.php
app/src/Modules/User/Domain/Enum/UserStatus.php
app/src/Modules/User/Domain/Enum/UserVerification.php
app/src/Modules/User/Domain/Enum/UserVerification.php
app/src/Modules/User/Domain/ValueObject/BanExpiration.php
app/src/Modules/User/Domain/ValueObject/BanExpiration.php
app/src/Modules/User/Domain/ValueObject/BanReason.php
app/src/Modules/User/Domain/ValueObject/BanReason.php
app/src/Modules/User/Domain/ValueObject/BanUnbannedAt.php
app/src/Modules/User/Domain/ValueObject/BanUnbannedAt.php
app/src/Modules/User/Domain/ValueObject/BanUnbannedBy.php
app/src/Modules/User/Domain/ValueObject/BanUnbannedBy.php
app/src/Modules/User/Domain/ValueObject/BanUnbannedReason.php
app/src/Modules/User/Domain/ValueObject/BanUnbannedReason.php
app/src/Modules/User/Domain/ValueObject/Email.php
app/src/Modules/User/Domain/ValueObject/Email.php
app/src/Modules/User/Domain/ValueObject/ReservedNicknameHolder.php
app/src/Modules/User/Domain/ValueObject/ReservedNicknameHolder.php
app/src/Modules/User/Domain/ValueObject/ReservedNicknameId.php
app/src/Modules/User/Domain/ValueObject/ReservedNicknameId.php
app/src/Modules/User/Domain/ValueObject/UserAvatar.php
app/src/Modules/User/Domain/ValueObject/UserAvatar.php
app/src/Modules/User/Domain/ValueObject/UserBanId.php
app/src/Modules/User/Domain/ValueObject/UserBanId.php
app/src/Modules/User/Domain/ValueObject/UserBio.php
app/src/Modules/User/Domain/ValueObject/UserBio.php
app/src/Modules/User/Domain/ValueObject/UserDeletion.php
app/src/Modules/User/Domain/ValueObject/UserDeletion.php
app/src/Modules/User/Domain/ValueObject/UserLocation.php
app/src/Modules/User/Domain/ValueObject/UserLocation.php
app/src/Modules/User/Domain/ValueObject/UserName.php
app/src/Modules/User/Domain/ValueObject/UserName.php
app/src/Modules/User/Domain/ValueObject/UserNickname.php
app/src/Modules/User/Domain/ValueObject/UserNickname.php
app/src/Modules/User/Domain/ValueObject/UserSpiritualName.php
app/src/Modules/User/Domain/ValueObject/UserSpiritualName.php
app/src/Modules/User/Infrastructure/Cycle/BanExpirationTypecast.php
app/src/Modules/User/Infrastructure/Cycle/BanExpirationTypecast.php
app/src/Modules/User/Infrastructure/Cycle/BanUnbannedAtTypecast.php
app/src/Modules/User/Infrastructure/Cycle/BanUnbannedAtTypecast.php
app/src/Modules/User/Infrastructure/Cycle/BanUnbannedByTypecast.php
app/src/Modules/User/Infrastructure/Cycle/BanUnbannedByTypecast.php
app/src/Modules/User/Infrastructure/Cycle/BanUnbannedReasonTypecast.php
app/src/Modules/User/Infrastructure/Cycle/BanUnbannedReasonTypecast.php
app/src/Modules/User/Infrastructure/Cycle/ReservedNicknameHolderTypecast.php
app/src/Modules/User/Infrastructure/Cycle/ReservedNicknameHolderTypecast.php
app/src/Modules/User/Infrastructure/Cycle/UserAvatarTypecast.php
app/src/Modules/User/Infrastructure/Cycle/UserAvatarTypecast.php
app/src/Modules/User/Infrastructure/Cycle/UserBioTypecast.php
app/src/Modules/User/Infrastructure/Cycle/UserBioTypecast.php
app/src/Modules/User/Infrastructure/Cycle/UserDeletionTypecast.php
app/src/Modules/User/Infrastructure/Cycle/UserDeletionTypecast.php
app/src/Modules/User/Infrastructure/Cycle/UserLocationTypecast.php
app/src/Modules/User/Infrastructure/Cycle/UserLocationTypecast.php
app/src/Modules/User/Infrastructure/Cycle/UserSpiritualNameTypecast.php
app/src/Modules/User/Infrastructure/Cycle/UserSpiritualNameTypecast.php
app/src/Modules/User/Repository/ReservedNicknameRepository.php
app/src/Modules/User/Repository/ReservedNicknameRepository.php
app/src/Modules/User/Repository/UserBanRepository.php
app/src/Modules/User/Repository/UserBanRepository.php
app/src/Modules/User/Repository/UserRepository.php
app/src/Modules/User/Repository/UserRepository.php

exec
/bin/zsh -lc 'rg -n "spiral/auth|AuthMiddleware|TokenStorageInterface|ActorProviderInterface|CycleTokenStorage|Header|Bearer|access|Access|Auth|UserRepository|findByEmail|Application" docs/researches/2026-06-13_22-36_auth-module.md docs/researches/2026-06-13_12-33_user-domain.md app/src app/config composer.json composer.lock 2>/dev/null' in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
docs/researches/2026-06-13_12-33_user-domain.md:2:title: Проектирование домена пользователя и авторизации (User + Access)
docs/researches/2026-06-13_12-33_user-domain.md:13:# Проектирование домена пользователя и авторизации (User + Access)
docs/researches/2026-06-13_12-33_user-domain.md:25:Разносим на два модуля: **`User`** (профиль, статус, верификация, баны) и **`Access`** (роли, права, проверки доступа). Граница между ними и `Media` идёт только через `Application` (`docs/arch.md:131`).
docs/researches/2026-06-13_12-33_user-domain.md:38:    Application/     Command/Query (CreateUser, UpdateUserProfile, SetUserAvatar,
docs/researches/2026-06-13_12-33_user-domain.md:40:    Repository/      UserRepository, UserBanRepository
docs/researches/2026-06-13_12-33_user-domain.md:44:  Access/
docs/researches/2026-06-13_12-33_user-domain.md:49:    Application/     AssignRoleToUser, RevokeRoleFromUser,
docs/researches/2026-06-13_12-33_user-domain.md:56:  ValueObject/UserId  # уже есть; используется и User, и Access, и Media
docs/researches/2026-06-13_12-33_user-domain.md:59:`UserId` уже лежит в `Shared/Domain/ValueObject/UserId.php:7` — поэтому `Access` ссылается на пользователя через общий `UserId`, не зная Entity `User`. Цикла зависимостей `User ↔ Access` нет.
docs/researches/2026-06-13_12-33_user-domain.md:130:| `RoleId`/`PermissionId` | Access/Domain | UUID v7 | `extends AbstractUuidV7Id` |
docs/researches/2026-06-13_12-33_user-domain.md:131:| `RoleSlug`/`PermissionSlug` | Access/Domain | строка | `^[a-z][a-z0-9.]*[a-z0-9]$` (напр. `admin`, `user.ban`) |
docs/researches/2026-06-13_12-33_user-domain.md:135:### Аватар: связь с `Media` через `Application`, без нарушения границ
docs/researches/2026-06-13_12-33_user-domain.md:137:Аватар — часть `User`, `Media` про аватар не знает (`docs/arch.md:156`). Публичный API `Media/Application` принимает примитивы (`CheckMediaIsImageQuery:9` — `string $mediaId`; `MakeMediaPermanentCommand:9-11` — `string $userId, string $mediaId`), поэтому `User` хранит ссылку собственным VO `UserAvatar` и передаёт `value()` на границу, **не импортируя `Media\Domain`**. `MediaId` переносить в `Shared` не нужно.
docs/researches/2026-06-13_12-33_user-domain.md:142:User/Application/SetUserAvatar
docs/researches/2026-06-13_12-33_user-domain.md:143:  -> Media/Application CheckMediaIsImage(mediaId)    # существует и это картинка
docs/researches/2026-06-13_12-33_user-domain.md:144:  -> Media/Application MakeMediaPermanent(userId, mediaId)  # снять expiresAt, иначе cleanup удалит аватар
docs/researches/2026-06-13_12-33_user-domain.md:149:`MakeMediaPermanent` обязателен: загруженное медиа временное (`expiresAt = now + 1 day`, `docs/researches/2026-05-20_18-42_media-domain.md:455-463`); без перевода в permanent аватар вычистит фоновая очистка. На уровне БД — `users.avatar_media_id` FK → `media(id)` `ON DELETE RESTRICT` (БД запрещает удалить медиа, пока оно чей-то аватар — согласуется с принципом media-домена «Media нельзя удалить, пока есть связь», `docs/researches/2026-05-20_18-42_media-domain.md:425-433`). Смена/снятие аватара — отдельный сценарий, который при необходимости просит `Media/Application` удалить старый объект.
docs/researches/2026-06-13_12-33_user-domain.md:177:### Модуль `Access`: роли, права, RBAC
docs/researches/2026-06-13_12-33_user-domain.md:179:Полные таблицы-справочники (выбор пользователя), но **источник истины — код**: канон ролей и прав определён enum `RoleName`/`PermissionName`, а инфраструктурный сидер (`Access/Infrastructure/Bootloader`) создаёт/обновляет строки `roles`/`permissions`/`role_permissions` из этого enum при деплое. Так совмещаем требование «таблицы-справочники» с правилами «enum вместо строк» и «создаются только из кода» (`docs/rules.md:30`).
docs/researches/2026-06-13_12-33_user-domain.md:196:Проверки доступа — Query в `Access/Application`: `CheckUserHasPermission(userId, PermissionName)`, `GetUserRoles(userId)`, `GetUserPermissions(userId)`. Они грузят роли пользователя через `UserRoleRepository`, разворачивают в права через `role_permissions` и сравнивают с `PermissionName`. Назначение роли — `AssignRoleToUser(userId, RoleName)`.
docs/researches/2026-06-13_12-33_user-domain.md:198:По умолчанию пользователь **без роли** (нет строки в `user_roles`) — отсутствие роли = нет прав. Роль `admin` обязательна и создаётся сидером. `User/Application` при необходимости назначить роль вызывает `Access/Application/AssignRoleToUser` (кросс-модульно через Application, `docs/arch.md:131`).
docs/researches/2026-06-13_12-33_user-domain.md:200:Связь `user_roles` хранит `UserId` без FK на `users` в строгом ORM-смысле кросс-модульно: на уровне БД допустим FK `user_roles.user_id` → `users.id` `ON DELETE CASCADE` как инвариант целостности (одна БД монолита), но `Access` в коде не обращается к таблице/репозиторию `User` (`docs/arch.md:141-146`).
docs/researches/2026-06-13_12-33_user-domain.md:204:`email` и `nickname` уникальны без учёта регистра. Решение: тип PostgreSQL `citext` + `UNIQUE` (либо `text` + уникальный функциональный индекс по `lower(...)`). VO дополнительно нормализуют значение в нижний регистр до записи, поэтому конфликт регистра невозможен и на уровне домена. Проверку занятости делаем check-then-act в Handler-е (`UserRepository::findByEmail()`/`findByNickname()`), при гонке дубликат ловит уникальный индекс как 500 (`docs/rules.md:47`).
docs/researches/2026-06-13_12-33_user-domain.md:222:User/Application    -> User/Domain, User/Repository, Media/Application, Access/Application
docs/researches/2026-06-13_12-33_user-domain.md:223:Access/Domain       -> Shared/Domain (UserId), свой Domain
docs/researches/2026-06-13_12-33_user-domain.md:224:Access/Application  -> Access/Domain, Access/Repository
docs/researches/2026-06-13_12-33_user-domain.md:236:  Отдельный модуль Access (рекомендация принята). User обращается к Access/Application.
docs/researches/2026-06-13_12-33_user-domain.md:237:  Плюсы/минусы разобраны: чистая граница и переиспользование будущим Auth против чуть большей
docs/researches/2026-06-13_12-33_user-domain.md:258:  Поток через Media/Application (CheckMediaIsImage + MakeMediaPermanent).
docs/researches/2026-06-13_12-33_user-domain.md:263:Делаем два модуля. **`User`**: Entity `User` (профиль, `email`/`nickname` через citext-уникальность, статус `UserStatus`, бейдж `UserVerification`, локаль/таймзона, аватар как null-object VO `UserAvatar` со ссылкой на `Media` через примитив на границе, soft-delete через `UserDeletion`), плюс Entity `UserBan` как журнал модерации. **`Access`**: справочники `roles`/`permissions`/`role_permissions`/`user_roles` с источником истины в enum `RoleName`/`PermissionName` и сидером из кода; проверки доступа — Query в `Access/Application`; пользователь по умолчанию без роли, роль `admin` сидится.
docs/researches/2026-06-13_12-33_user-domain.md:267:Следующий шаг — `eda-plan` на реализацию модуля `User` и модуля `Access` по этому исследованию: миграции, Entity + VO + typecast, репозитории, базовые Command/Query (создание/обновление профиля, установка аватара, бан/разбан, назначение роли, проверка прав) и сидер ролей/прав. Конкретные HTTP-сценарии и интеграционные тесты роутов проектируются в плане.
composer.json:5:    "description": "Spiral Application installer",
docs/researches/2026-06-13_22-36_auth-module.md:2:title: Модуль Auth — беспарольный вход по email-коду
docs/researches/2026-06-13_22-36_auth-module.md:13:# Модуль Auth — беспарольный вход по email-коду
docs/researches/2026-06-13_22-36_auth-module.md:17:Проектируем модуль `Auth` — аутентификацию для API мобильного приложения YogaLoka.
docs/researches/2026-06-13_22-36_auth-module.md:18:Домен и данные `User` и `Access` уже реализованы (`User.php:77`, модуль `Access`), но
docs/researches/2026-06-13_22-36_auth-module.md:28:Рамка: модуль `Auth` уже назван в архитектуре отдельной областью (`docs/arch.md:23`).
docs/researches/2026-06-13_22-36_auth-module.md:37:access + refresh, хранятся в Cycle, refresh ротируется. Защита от перебора — переиспользуемый
docs/researches/2026-06-13_22-36_auth-module.md:46:                                             -> {access, refresh, expiresIn}
docs/researches/2026-06-13_22-36_auth-module.md:53:                                          -> создаём User (сразу Active) + {access, refresh}
docs/researches/2026-06-13_22-36_auth-module.md:56:  POST /api/auth/refresh {refreshToken}   -> ротация -> новые {access, refresh}
docs/researches/2026-06-13_22-36_auth-module.md:57:  POST /api/auth/logout  (Bearer access)  -> отзыв токена(ов)
docs/researches/2026-06-13_22-36_auth-module.md:75:### Карта модуля Auth
docs/researches/2026-06-13_22-36_auth-module.md:78:Modules/Auth/
docs/researches/2026-06-13_22-36_auth-module.md:80:    Entity/        LoginCode, RegistrationTicket, AuthToken
docs/researches/2026-06-13_22-36_auth-module.md:81:    ValueObject/   LoginCodeId, RegistrationTicketId, AuthTokenId,
docs/researches/2026-06-13_22-36_auth-module.md:84:    Enum/          AuthTokenType (Access | Refresh)
docs/researches/2026-06-13_22-36_auth-module.md:85:  Application/
docs/researches/2026-06-13_22-36_auth-module.md:86:    Command/Auth/  RequestLoginCode, VerifyLoginCode, CompleteRegistration,
docs/researches/2026-06-13_22-36_auth-module.md:90:    Exception/     # ошибки слоя Application, если нужны сверх общих
docs/researches/2026-06-13_22-36_auth-module.md:91:  Repository/      LoginCodeRepository, RegistrationTicketRepository, AuthTokenRepository
docs/researches/2026-06-13_22-36_auth-module.md:94:    Auth/          CycleTokenStorage, UserActorProvider
docs/researches/2026-06-13_22-36_auth-module.md:96:    Bootloader/    AuthBootloader                  # биндинги storage/actor provider
docs/researches/2026-06-13_22-36_auth-module.md:98:    Http/Controller/ AuthController
docs/researches/2026-06-13_22-36_auth-module.md:108:Auth ссылается на пользователя через общий `UserId` из `Shared/Domain/ValueObject`
docs/researches/2026-06-13_22-36_auth-module.md:109:(уже есть), как это делает `Access`. В таблицу `users` и в `UserRepository` Auth напрямую
docs/researches/2026-06-13_22-36_auth-module.md:110:не лезет — только через `User/Application` (`docs/arch.md:131`, `docs/arch.md:141-146`).
docs/researches/2026-06-13_22-36_auth-module.md:112:### Таблицы (владелец — модуль Auth)
docs/researches/2026-06-13_22-36_auth-module.md:135:  type            enum              -- access | refresh
docs/researches/2026-06-13_22-36_auth-module.md:141:### Токены: своё Cycle-хранилище поверх spiral/auth
docs/researches/2026-06-13_22-36_auth-module.md:143:`spiral/auth` (входит в `spiral/framework` 3.16.2) даёт `TokenStorageInterface`,
docs/researches/2026-06-13_22-36_auth-module.md:144:`ActorProviderInterface`, `AuthMiddleware` и транспорт `header` (Bearer) — подтверждено по
docs/researches/2026-06-13_22-36_auth-module.md:145:документации Spiral 3.16. Берём этот механизм, но `TokenStorageInterface` реализуем **сами**
docs/researches/2026-06-13_22-36_auth-module.md:146:(`Auth/Infrastructure/Auth/CycleTokenStorage`) поверх своей сущности `AuthToken`, а не
docs/researches/2026-06-13_22-36_auth-module.md:155:выдача (verify/register): create access (TTL ~1ч) + create refresh (TTL ~30-60д)
docs/researches/2026-06-13_22-36_auth-module.md:157:запрос к API:             AuthMiddleware (header) -> CycleTokenStorage.load(access)
docs/researches/2026-06-13_22-36_auth-module.md:158:                          -> UserActorProvider.getActor -> actor = AuthenticatedUser(UserId)
docs/researches/2026-06-13_22-36_auth-module.md:160:                          -> delete старого refresh (ротация) + create новой пары access+refresh
docs/researches/2026-06-13_22-36_auth-module.md:161:logout:                   delete текущего access (и refresh, если передан)
docs/researches/2026-06-13_22-36_auth-module.md:166:(будущий `BanUser` дёргает `Auth/Application` для удаления токенов по `user_id`), а access
docs/researches/2026-06-13_22-36_auth-module.md:167:короткоживущий. Привязка актора к ролям/правам `Access` (GuardInterceptor) — вне рамок этого
docs/researches/2026-06-13_22-36_auth-module.md:205:Вешаем `#[RateLimited]` на действия `AuthController` (request/verify/register/refresh).
docs/researches/2026-06-13_22-36_auth-module.md:207:правило, живёт внутри сценариев Auth по таблице `auth_login_codes` (поля `attempts`,
docs/researches/2026-06-13_22-36_auth-module.md:221:  Неверно -> attempts++, AuthenticationException (401). Верно -> consumed.
docs/researches/2026-06-13_22-36_auth-module.md:222:    - User по email существует (через User/Application) и не Banned/Deleted -> выдать пару токенов
docs/researches/2026-06-13_22-36_auth-module.md:225:    - User Banned/Deleted -> отказ (Forbidden/Authentication).
docs/researches/2026-06-13_22-36_auth-module.md:228:  валидирует и гасит ticket, вызывает User/Application CreateUser(email,name,nickname,locale)
docs/researches/2026-06-13_22-36_auth-module.md:232:  load -> type=refresh + срок -> delete (ротация) -> create access+refresh.
docs/researches/2026-06-13_22-36_auth-module.md:238:### Зависимость от User/Application (важно)
docs/researches/2026-06-13_22-36_auth-module.md:240:Auth не может ходить в `UserRepository` напрямую (`docs/arch.md:141-146`), а у модуля `User`
docs/researches/2026-06-13_22-36_auth-module.md:241:сейчас **нет** слоя Application — только Domain + Repository + Infrastructure. Значит для Auth
docs/researches/2026-06-13_22-36_auth-module.md:242:нужно, чтобы в `User/Application` появились минимум:
docs/researches/2026-06-13_22-36_auth-module.md:246:FindUserForAuth(email)                       -> есть ли юзер + его UserId + статус (active/banned/deleted)
docs/researches/2026-06-13_22-36_auth-module.md:249:`User-domain` research уже ставит создание `User/Application` (CreateUser и т.д.) следующим
docs/researches/2026-06-13_22-36_auth-module.md:250:шагом (`docs/researches/2026-06-13_12-33_user-domain.md:267`). Поэтому план Auth должен либо
docs/researches/2026-06-13_22-36_auth-module.md:252:зависимость Auth.
docs/researches/2026-06-13_22-36_auth-module.md:257:Auth/Domain        -> Shared/Domain (UserId), свой Domain
docs/researches/2026-06-13_22-36_auth-module.md:258:Auth/Application    -> Auth/Domain, Auth/Repository, Auth/Contract, User/Application, Outbox/Application
docs/researches/2026-06-13_22-36_auth-module.md:259:Auth/Infrastructure -> spiral/auth (TokenStorage, ActorProvider), свой Contract, Redis, Cycle
docs/researches/2026-06-13_22-36_auth-module.md:260:Auth/Presentation   -> Auth/Application, Shared RateLimited
docs/researches/2026-06-13_22-36_auth-module.md:261:Shared              -> RateLimited + RateLimitInterceptor (общие, не про Auth)
docs/researches/2026-06-13_22-36_auth-module.md:262:User                -> про токены/коды не знает; даёт CreateUser/FindUserForAuth на границе
docs/researches/2026-06-13_22-36_auth-module.md:268:(включает `spiral/auth`, `spiral/auth-http`, `spiral/security`), `spiral/cycle-bridge` ^2.11,
docs/researches/2026-06-13_22-36_auth-module.md:269:`ext-redis`, `MailerBootloader` + Mailpit, outbox-модуль. JWT-библиотека и OAuth-провайдеры
docs/researches/2026-06-13_22-36_auth-module.md:280:  Отложено: вход через Apple/Google, привязка ролей Access к актору (Guard).
docs/researches/2026-06-13_22-36_auth-module.md:283:  Непрозрачные access + refresh, хранилище на Cycle (своя таблица auth_tokens),
docs/researches/2026-06-13_22-36_auth-module.md:284:  refresh ротируется, Bearer-транспорт. JWT и сессии-куки отклонены
docs/researches/2026-06-13_22-36_auth-module.md:288:  Отдельный модуль Auth со своими таблицами (auth_login_codes,
docs/researches/2026-06-13_22-36_auth-module.md:301:  Таблица PostgreSQL в модуле Auth, только хэш (HMAC с серверным секретом),
docs/researches/2026-06-13_22-36_auth-module.md:307:  (код в минуту, попытки) — правило сценария Auth по таблице кодов.
docs/researches/2026-06-13_22-36_auth-module.md:316:Делаем модуль `Auth` с беспарольным входом по email-коду (вход = регистрация). Поток:
docs/researches/2026-06-13_22-36_auth-module.md:319:и `logout` (отзыв). Токены непрозрачные access+refresh в собственной Cycle-таблице
docs/researches/2026-06-13_22-36_auth-module.md:320:`auth_tokens` через свою реализацию `spiral/auth TokenStorageInterface`; коды и талоны — в
docs/researches/2026-06-13_22-36_auth-module.md:321:своих таблицах Auth, хэшированные HMAC с серверным секретом, со сроком и счётчиком попыток.
docs/researches/2026-06-13_22-36_auth-module.md:323:интерцептор в `Shared` (IP+действие), email-троттлинг — в сценарии Auth.
docs/researches/2026-06-13_22-36_auth-module.md:325:Следующий шаг — `eda-plan` на реализацию модуля `Auth` по этому исследованию: миграции трёх
docs/researches/2026-06-13_22-36_auth-module.md:326:таблиц, Entity+VO+typecast, репозитории, `CycleTokenStorage` и `UserActorProvider`,
docs/researches/2026-06-13_22-36_auth-module.md:330:AuthMiddleware (header) в API-группе и AuthBootloader. План обязан включить (или идти после)
docs/researches/2026-06-13_22-36_auth-module.md:331:`User/Application`: `CreateUser` и `FindUserForAuth` — без них Auth не собрать. Языки писем,
composer.lock:6022:                "spiral/auth": "^3.15",
composer.lock:6255:                "spiral/auth": "self.version",
composer.lock:6256:                "spiral/auth-http": "self.version",
composer.lock:6339:                    "Spiral\\Auth\\": [
composer.lock:6340:                        "src/Auth/src",
composer.lock:6341:                        "src/AuthHttp/src"
composer.lock:8810:                "symfony/property-access": "^7.4|^8.0",
composer.lock:10397:                "symfony/property-access": "^6.4|^7.0|^8.0",
composer.lock:10471:                "symfony/property-access": "^7.4|^8.0",
composer.lock:11420:                    "role": "Author"
composer.lock:13342:                "spiral/auth": "^3.16",
composer.lock:13343:                "spiral/auth-http": "^3.16",
app/src/Modules/Media/Presentation/Job/ProcessMediaJob.php:7:use App\Modules\Media\Application\Command\Media\ProcessMedia\ProcessMediaCommand;
app/src/Modules/Media/Presentation/Job/ProcessMediaJob.php:8:use App\Modules\Media\Application\Command\Media\ProcessMedia\ProcessMediaHandler;
app/src/Modules/Media/Presentation/Job/ProcessMediaJob.php:9:use App\Modules\Media\Application\Command\Media\RecordMediaProcessingFailure\RecordMediaProcessingFailureCommand;
app/src/Modules/Media/Presentation/Job/ProcessMediaJob.php:10:use App\Modules\Media\Application\Command\Media\RecordMediaProcessingFailure\RecordMediaProcessingFailureHandler;
app/src/Modules/Media/Presentation/Job/ProcessMediaJob.php:11:use App\Modules\Media\Application\Exception\MediaFileServiceFailedException;
app/src/Modules/Media/Presentation/Job/ProcessMediaJob.php:12:use App\Modules\Media\Application\Message\MediaUploaded;
app/src/Modules/Media/Presentation/Job/ProcessMediaJob.php:13:use App\Modules\Outbox\Application\Contract\OutboxMessageLoaderContract;
app/src/Modules/Media/Presentation/Job/ProcessMediaJob.php:14:use App\Modules\Outbox\Application\Message\OutboxQueueEnvelope;
app/src/Modules/Media/README.md:3:`Media` — переиспользуемый Application-API загрузки и обработки файлов. У модуля **нет
app/src/Modules/Media/README.md:6:`Application`-слой. Ограничения каждой загрузки потребитель передаёт спецификацией прямо в
app/src/Modules/Media/README.md:12:## Публичный Application-API
app/src/Modules/Media/README.md:14:Другие модули обращаются только к `Application` (Command/Query), не к `Repository`,
app/src/Modules/Media/README.md:35:### Спецификация и DTO (`Application/Dto`)
app/src/Modules/Media/README.md:122:`Application/Message/MediaUploaded implements OutboxMessage` — только примитивы/enum
app/src/Modules/Media/README.md:136:  по конвенции размещения всех config-DTO, и оборачивать его в Application-`*Contract` было бы
app/src/Modules/Media/README.md:151:  входа с настройками и вызовет этот Application-API.
app/src/Modules/Media/Application/Exception/MediaFileServiceFailedException.php:5:namespace App\Modules\Media\Application\Exception;
app/src/Modules/Media/Application/Exception/MediaFileServiceFailedException.php:12: * Слой исключения определяется контрактом, к которому оно относится (Application/Contract),
app/src/Modules/Media/Application/Message/MediaUploaded.php:5:namespace App\Modules\Media\Application\Message;
app/src/Modules/Media/Application/Message/MediaUploaded.php:7:use App\Modules\Media\Application\Dto\MediaConversionSpec;
app/src/Modules/Media/Application/Message/MediaUploaded.php:8:use App\Modules\Outbox\Application\Message\OutboxMessage;
app/src/Modules/Media/Application/Query/Media/CheckMediaIsImage/CheckMediaIsImageHandler.php:5:namespace App\Modules\Media\Application\Query\Media\CheckMediaIsImage;
app/src/Modules/Media/Application/Contract/MediaImageProcessorContract.php:5:namespace App\Modules\Media\Application\Contract;
app/src/Modules/Media/Application/Contract/MediaImageProcessorContract.php:7:use App\Modules\Media\Application\Dto\MediaConversionResult;
app/src/Modules/Media/Application/Query/Media/CheckMediaIsImage/CheckMediaIsImageQuery.php:5:namespace App\Modules\Media\Application\Query\Media\CheckMediaIsImage;
app/src/Modules/Media/Application/Query/Media/GetMediaUrl/GetMediaUrlQuery.php:5:namespace App\Modules\Media\Application\Query\Media\GetMediaUrl;
app/src/Modules/Media/Application/Contract/MediaFileServiceContract.php:5:namespace App\Modules\Media\Application\Contract;
app/src/Modules/Media/Application/Contract/MediaFileServiceContract.php:7:use App\Modules\Media\Application\Dto\MediaObjectHead;
app/src/Modules/Media/Application/Contract/MediaFileServiceContract.php:8:use App\Modules\Media\Application\Dto\MediaPresignedPartCollection;
app/src/Modules/Media/Application/Service/MediaTypeResolver.php:5:namespace App\Modules\Media\Application\Service;
app/src/Modules/Media/Application/Dto/MediaUploadSpec.php:5:namespace App\Modules\Media\Application\Dto;
app/src/Modules/Media/Application/Query/Media/GetMediaUrl/GetMediaUrlHandler.php:5:namespace App\Modules\Media\Application\Query\Media\GetMediaUrl;
app/src/Modules/Media/Application/Query/Media/GetMediaUrl/GetMediaUrlHandler.php:7:use App\Modules\Media\Application\Contract\MediaFileServiceContract;
app/src/Modules/Media/Application/Query/Media/GetMediaUrl/GetMediaUrlHandler.php:8:use App\Modules\Media\Application\Dto\MediaUrlResult;
app/src/Modules/User/Domain/Entity/User.php:22:use App\Modules\User\Repository\UserRepository;
app/src/Modules/User/Domain/Entity/User.php:34:    repository: UserRepository::class,
app/src/Modules/Media/Application/Query/Media/CheckMediaExists/CheckMediaExistsHandler.php:5:namespace App\Modules\Media\Application\Query\Media\CheckMediaExists;
app/src/Modules/Media/Application/Dto/MediaPresignedPart.php:5:namespace App\Modules\Media\Application\Dto;
app/src/Modules/Media/Application/Query/Media/CheckMediaExists/CheckMediaExistsQuery.php:5:namespace App\Modules\Media\Application\Query\Media\CheckMediaExists;
app/src/Modules/Media/Application/Dto/MediaConversionSpec.php:5:namespace App\Modules\Media\Application\Dto;
app/src/Modules/Media/Application/Dto/MediaConversionSpec.php:10: * Профиль одной конверсии изображения. Примитив-дружественный Application-DTO с публичным
app/src/Modules/Media/Application/Dto/MediaObjectHead.php:5:namespace App\Modules\Media\Application\Dto;
app/src/Modules/Media/Application/Command/Media/CompleteMediaUpload/CompleteMediaUploadHandler.php:5:namespace App\Modules\Media\Application\Command\Media\CompleteMediaUpload;
app/src/Modules/Media/Application/Command/Media/CompleteMediaUpload/CompleteMediaUploadHandler.php:7:use App\Modules\Media\Application\Contract\MediaFileServiceContract;
app/src/Modules/Media/Application/Command/Media/CompleteMediaUpload/CompleteMediaUploadHandler.php:8:use App\Modules\Media\Application\Dto\MediaConversionSpec;
app/src/Modules/Media/Application/Command/Media/CompleteMediaUpload/CompleteMediaUploadHandler.php:9:use App\Modules\Media\Application\Dto\MediaResult;
app/src/Modules/Media/Application/Command/Media/CompleteMediaUpload/CompleteMediaUploadHandler.php:10:use App\Modules\Media\Application\Message\MediaUploaded;
app/src/Modules/Media/Application/Command/Media/CompleteMediaUpload/CompleteMediaUploadHandler.php:18:use App\Modules\Outbox\Application\Contract\OutboxEventStoreContract;
app/src/Modules/Media/Application/Command/Media/CompleteMediaUpload/CompleteMediaUploadHandler.php:48:            throw new ForbiddenException('app.media.access_denied');
app/src/Modules/Media/Application/Command/Media/CompleteMediaUpload/CompleteMediaUploadHandler.php:109:        // Симметричный guard на той же Application-границе: проверяем обе границы домена
app/src/Modules/Media/Application/Dto/MediaUploadMode.php:5:namespace App\Modules\Media\Application\Dto;
app/src/Modules/Media/Application/Command/Media/CompleteMediaUpload/CompleteMediaUploadCommand.php:5:namespace App\Modules\Media\Application\Command\Media\CompleteMediaUpload;
app/src/Modules/Media/Application/Command/Media/CompleteMediaUpload/CompleteMediaUploadCommand.php:7:use App\Modules\Media\Application\Dto\MediaConversionSpec;
app/src/Modules/Media/Application/Dto/MediaUrlResult.php:5:namespace App\Modules\Media\Application\Dto;
app/src/Modules/Media/Application/Dto/MediaResult.php:5:namespace App\Modules\Media\Application\Dto;
app/src/Modules/Media/Application/Command/Media/RequestMediaUpload/RequestMediaUploadCommand.php:5:namespace App\Modules\Media\Application\Command\Media\RequestMediaUpload;
app/src/Modules/Media/Application/Command/Media/RequestMediaUpload/RequestMediaUploadCommand.php:7:use App\Modules\Media\Application\Dto\MediaFileMeta;
app/src/Modules/Media/Application/Command/Media/RequestMediaUpload/RequestMediaUploadCommand.php:8:use App\Modules\Media\Application\Dto\MediaUploadSpec;
app/src/Modules/Media/Application/Dto/MediaFileMeta.php:5:namespace App\Modules\Media\Application\Dto;
app/src/Modules/Media/Application/Dto/MediaPresignedPartCollection.php:5:namespace App\Modules\Media\Application\Dto;
app/src/Modules/Media/Application/Dto/MediaConversionResult.php:5:namespace App\Modules\Media\Application\Dto;
app/src/Modules/Media/Application/Command/Media/RequestMediaUpload/RequestMediaUploadHandler.php:5:namespace App\Modules\Media\Application\Command\Media\RequestMediaUpload;
app/src/Modules/Media/Application/Command/Media/RequestMediaUpload/RequestMediaUploadHandler.php:7:use App\Modules\Media\Application\Contract\MediaFileServiceContract;
app/src/Modules/Media/Application/Command/Media/RequestMediaUpload/RequestMediaUploadHandler.php:8:use App\Modules\Media\Application\Dto\MediaFileMeta;
app/src/Modules/Media/Application/Command/Media/RequestMediaUpload/RequestMediaUploadHandler.php:9:use App\Modules\Media\Application\Dto\RequestMediaUploadResult;
app/src/Modules/Media/Application/Command/Media/RequestMediaUpload/RequestMediaUploadHandler.php:10:use App\Modules\Media\Application\Dto\MediaUploadSpec;
app/src/Modules/Media/Application/Command/Media/RequestMediaUpload/RequestMediaUploadHandler.php:11:use App\Modules\Media\Application\Service\MediaTypeResolver;
app/src/Modules/Media/Infrastructure/FileService/ImagickMediaImageProcessor.php:7:use App\Modules\Media\Application\Contract\MediaImageProcessorContract;
app/src/Modules/Media/Infrastructure/FileService/ImagickMediaImageProcessor.php:8:use App\Modules\Media\Application\Dto\MediaConversionResult;
app/src/Modules/Media/Application/Dto/RequestMediaUploadResult.php:5:namespace App\Modules\Media\Application\Dto;
app/src/Modules/Media/Application/Command/Media/ProcessMedia/ProcessMediaCommand.php:5:namespace App\Modules\Media\Application\Command\Media\ProcessMedia;
app/src/Modules/Media/Application/Command/Media/ProcessMedia/ProcessMediaCommand.php:7:use App\Modules\Media\Application\Dto\MediaConversionSpec;
app/src/Modules/Media/Application/Command/Media/RecordMediaProcessingFailure/RecordMediaProcessingFailureHandler.php:5:namespace App\Modules\Media\Application\Command\Media\RecordMediaProcessingFailure;
app/src/Modules/Media/Application/Command/Media/DeleteMedia/DeleteMediaHandler.php:5:namespace App\Modules\Media\Application\Command\Media\DeleteMedia;
app/src/Modules/Media/Application/Command/Media/DeleteMedia/DeleteMediaHandler.php:7:use App\Modules\Media\Application\Contract\MediaFileServiceContract;
app/src/Modules/Media/Application/Command/Media/DeleteMedia/DeleteMediaHandler.php:41:            throw new ForbiddenException('app.media.access_denied');
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:7:use App\Modules\Media\Application\Contract\MediaFileServiceContract;
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:8:use App\Modules\Media\Application\Dto\MediaObjectHead;
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:9:use App\Modules\Media\Application\Dto\MediaPresignedPart;
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:10:use App\Modules\Media\Application\Dto\MediaPresignedPartCollection;
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:19:use App\Modules\Media\Application\Exception\MediaFileServiceFailedException;
app/src/Modules/Media/Application/Command/Media/ProcessMedia/ProcessMediaHandler.php:5:namespace App\Modules\Media\Application\Command\Media\ProcessMedia;
app/src/Modules/Media/Application/Command/Media/ProcessMedia/ProcessMediaHandler.php:7:use App\Modules\Media\Application\Contract\MediaFileServiceContract;
app/src/Modules/Media/Application/Command/Media/ProcessMedia/ProcessMediaHandler.php:8:use App\Modules\Media\Application\Contract\MediaImageProcessorContract;
app/src/Modules/Media/Application/Command/Media/ProcessMedia/ProcessMediaHandler.php:9:use App\Modules\Media\Application\Dto\MediaConversionSpec;
app/src/Modules/Media/Application/Command/Media/RecordMediaProcessingFailure/RecordMediaProcessingFailureCommand.php:5:namespace App\Modules\Media\Application\Command\Media\RecordMediaProcessingFailure;
app/src/Modules/User/Repository/UserRepository.php:16:final class UserRepository extends Repository
app/src/Modules/User/Repository/UserRepository.php:23:    public function findByEmail(Email $email): User|null
app/src/Modules/User/Repository/UserRepository.php:35:        return $this->findByEmail($email) !== null;
app/src/Modules/Media/Application/Command/Media/DeleteMedia/DeleteMediaCommand.php:5:namespace App\Modules\Media\Application\Command\Media\DeleteMedia;
app/src/Modules/Media/Infrastructure/Bootloader/MediaBootloader.php:7:use App\Modules\Media\Application\Contract\MediaFileServiceContract;
app/src/Modules/Media/Infrastructure/Bootloader/MediaBootloader.php:8:use App\Modules\Media\Application\Contract\MediaImageProcessorContract;
app/src/Modules/Media/Infrastructure/Bootloader/MediaBootloader.php:9:use App\Modules\Media\Application\Message\MediaUploaded;
app/src/Modules/Media/Infrastructure/Bootloader/MediaBootloader.php:15:use App\Modules\Outbox\Application\Contract\OutboxJobRegistryContract;
app/src/Modules/Media/Application/Command/Media/MakeMediaPermanent/MakeMediaPermanentHandler.php:5:namespace App\Modules\Media\Application\Command\Media\MakeMediaPermanent;
app/src/Modules/Media/Application/Command/Media/MakeMediaPermanent/MakeMediaPermanentHandler.php:7:use App\Modules\Media\Application\Dto\MediaResult;
app/src/Modules/Media/Application/Command/Media/MakeMediaPermanent/MakeMediaPermanentHandler.php:32:            throw new ForbiddenException('app.media.access_denied');
app/src/Modules/Media/Application/Command/Media/MakeMediaPermanent/MakeMediaPermanentHandler.php:35:        // Статус-guard на Application-границе: домен makePermanent() без guard (бросил бы 500).
app/src/Modules/Media/Application/Command/Media/MakeMediaPermanent/MakeMediaPermanentCommand.php:5:namespace App\Modules\Media\Application\Command\Media\MakeMediaPermanent;
app/src/Modules/Access/Repository/PermissionRepository.php:5:namespace App\Modules\Access\Repository;
app/src/Modules/Access/Repository/PermissionRepository.php:7:use App\Modules\Access\Domain\Collection\PermissionCollection;
app/src/Modules/Access/Repository/PermissionRepository.php:8:use App\Modules\Access\Domain\Entity\Permission;
app/src/Modules/Access/Repository/PermissionRepository.php:9:use App\Modules\Access\Domain\ValueObject\PermissionId;
app/src/Modules/Access/Repository/PermissionRepository.php:10:use App\Modules\Access\Domain\ValueObject\PermissionSlug;
app/src/Modules/Outbox/Presentation/Console/OutboxRelayCommand.php:7:use App\Modules\Outbox\Application\Command\RelayOutbox\RelayOutboxCommand;
app/src/Modules/Outbox/Presentation/Console/OutboxRelayCommand.php:8:use App\Modules\Outbox\Application\Command\RelayOutbox\RelayOutboxHandler;
app/src/Modules/Outbox/Application/Exception/OutboxMessageLoadingException.php:5:namespace App\Modules\Outbox\Application\Exception;
app/src/Modules/Outbox/Application/Exception/OutboxMessageLoadingException.php:7:use App\Modules\Outbox\Application\Message\OutboxMessage;
app/src/Modules/Access/Domain/Enum/PermissionName.php:5:namespace App\Modules\Access\Domain\Enum;
app/src/Modules/Access/Domain/Enum/PermissionName.php:7:use App\Modules\Access\Domain\ValueObject\PermissionSlug;
app/src/Modules/Outbox/Application/Contract/OutboxRelaySleeperContract.php:5:namespace App\Modules\Outbox\Application\Contract;
app/src/Modules/Access/Repository/UserRoleRepository.php:5:namespace App\Modules\Access\Repository;
app/src/Modules/Access/Repository/UserRoleRepository.php:7:use App\Modules\Access\Domain\Collection\UserRoleCollection;
app/src/Modules/Access/Repository/UserRoleRepository.php:8:use App\Modules\Access\Domain\Entity\UserRole;
app/src/Modules/Access/Repository/UserRoleRepository.php:9:use App\Modules\Access\Domain\ValueObject\RoleId;
app/src/Modules/Outbox/Application/Exception/OutboxMessageSerializationException.php:5:namespace App\Modules\Outbox\Application\Exception;
app/src/Modules/Access/Domain/Enum/RoleName.php:5:namespace App\Modules\Access\Domain\Enum;
app/src/Modules/Access/Domain/Enum/RoleName.php:7:use App\Modules\Access\Domain\ValueObject\RoleSlug;
app/src/Modules/Outbox/Presentation/Job/OutboxDebugLogJob.php:7:use App\Modules\Outbox\Application\Command\ProcessDebugLogMessage\ProcessOutboxDebugLogMessageCommand;
app/src/Modules/Outbox/Presentation/Job/OutboxDebugLogJob.php:8:use App\Modules\Outbox\Application\Command\ProcessDebugLogMessage\ProcessOutboxDebugLogMessageHandler;
app/src/Modules/Outbox/Presentation/Job/OutboxDebugLogJob.php:9:use App\Modules\Outbox\Application\Contract\OutboxMessageLoaderContract;
app/src/Modules/Outbox/Presentation/Job/OutboxDebugLogJob.php:10:use App\Modules\Outbox\Application\Message\OutboxDebugLogMessage;
app/src/Modules/Outbox/Presentation/Job/OutboxDebugLogJob.php:11:use App\Modules\Outbox\Application\Message\OutboxQueueEnvelope;
app/src/Modules/Outbox/Application/Contract/OutboxMessageSerializerContract.php:5:namespace App\Modules\Outbox\Application\Contract;
app/src/Modules/Outbox/Application/Contract/OutboxMessageSerializerContract.php:7:use App\Modules\Outbox\Application\Message\OutboxMessage;
app/src/Modules/Outbox/Application/Contract/OutboxMessageSerializerContract.php:8:use App\Modules\Outbox\Application\Message\SerializedOutboxMessage;
app/src/Modules/Access/Repository/RoleRepository.php:5:namespace App\Modules\Access\Repository;
app/src/Modules/Access/Repository/RoleRepository.php:7:use App\Modules\Access\Domain\Collection\RoleCollection;
app/src/Modules/Access/Repository/RoleRepository.php:8:use App\Modules\Access\Domain\Entity\Role;
app/src/Modules/Access/Repository/RoleRepository.php:9:use App\Modules\Access\Domain\ValueObject\RoleId;
app/src/Modules/Access/Repository/RoleRepository.php:10:use App\Modules\Access\Domain\ValueObject\RoleSlug;
app/src/Modules/Outbox/Application/Contract/OutboxMessageLoaderContract.php:5:namespace App\Modules\Outbox\Application\Contract;
app/src/Modules/Outbox/Application/Contract/OutboxMessageLoaderContract.php:7:use App\Modules\Outbox\Application\Message\OutboxMessage;
app/src/Modules/Outbox/Application/Contract/OutboxRelayLoopControlContract.php:5:namespace App\Modules\Outbox\Application\Contract;
app/src/Modules/Outbox/Application/Command/RelayOutbox/RelayOutboxHandler.php:5:namespace App\Modules\Outbox\Application\Command\RelayOutbox;
app/src/Modules/Outbox/Application/Command/RelayOutbox/RelayOutboxHandler.php:7:use App\Modules\Outbox\Application\Contract\OutboxRelayWorkerContract;
app/src/Modules/Access/Domain/Entity/Permission.php:5:namespace App\Modules\Access\Domain\Entity;
app/src/Modules/Access/Domain/Entity/Permission.php:7:use App\Modules\Access\Domain\ValueObject\PermissionId;
app/src/Modules/Access/Domain/Entity/Permission.php:8:use App\Modules\Access\Domain\ValueObject\PermissionSlug;
app/src/Modules/Access/Domain/Entity/Permission.php:9:use App\Modules\Access\Repository\PermissionRepository;
app/src/Modules/Outbox/Application/Contract/OutboxRelayContract.php:5:namespace App\Modules\Outbox\Application\Contract;
app/src/Modules/Outbox/Application/Contract/OutboxJobRegistryContract.php:5:namespace App\Modules\Outbox\Application\Contract;
app/src/Modules/Outbox/Application/Contract/OutboxJobRegistryContract.php:7:use App\Modules\Outbox\Application\Message\OutboxMessage;
app/src/Modules/Access/Repository/RolePermissionRepository.php:5:namespace App\Modules\Access\Repository;
app/src/Modules/Access/Repository/RolePermissionRepository.php:7:use App\Modules\Access\Domain\Collection\RolePermissionCollection;
app/src/Modules/Access/Repository/RolePermissionRepository.php:8:use App\Modules\Access\Domain\Entity\RolePermission;
app/src/Modules/Access/Repository/RolePermissionRepository.php:9:use App\Modules\Access\Domain\ValueObject\PermissionId;
app/src/Modules/Access/Repository/RolePermissionRepository.php:10:use App\Modules\Access\Domain\ValueObject\RoleId;
app/src/Modules/Access/Domain/Collection/PermissionCollection.php:5:namespace App\Modules\Access\Domain\Collection;
app/src/Modules/Access/Domain/Collection/PermissionCollection.php:7:use App\Modules\Access\Domain\Entity\Permission;
app/src/Modules/Access/Domain/Entity/UserRole.php:5:namespace App\Modules\Access\Domain\Entity;
app/src/Modules/Access/Domain/Entity/UserRole.php:7:use App\Modules\Access\Domain\ValueObject\RoleId;
app/src/Modules/Access/Domain/Entity/UserRole.php:8:use App\Modules\Access\Domain\ValueObject\UserRoleId;
app/src/Modules/Access/Domain/Entity/UserRole.php:9:use App\Modules\Access\Repository\UserRoleRepository;
app/src/Modules/Outbox/Application/Contract/OutboxRelayWorkerContract.php:5:namespace App\Modules\Outbox\Application\Contract;
app/src/Modules/Access/Domain/Entity/RolePermission.php:5:namespace App\Modules\Access\Domain\Entity;
app/src/Modules/Access/Domain/Entity/RolePermission.php:7:use App\Modules\Access\Domain\ValueObject\PermissionId;
app/src/Modules/Access/Domain/Entity/RolePermission.php:8:use App\Modules\Access\Domain\ValueObject\RoleId;
app/src/Modules/Access/Domain/Entity/RolePermission.php:9:use App\Modules\Access\Domain\ValueObject\RolePermissionId;
app/src/Modules/Access/Domain/Entity/RolePermission.php:10:use App\Modules\Access\Repository\RolePermissionRepository;
app/src/Modules/Access/Domain/Collection/RolePermissionCollection.php:5:namespace App\Modules\Access\Domain\Collection;
app/src/Modules/Access/Domain/Collection/RolePermissionCollection.php:7:use App\Modules\Access\Domain\Entity\RolePermission;
app/src/Modules/Outbox/Application/Contract/OutboxEventStoreContract.php:5:namespace App\Modules\Outbox\Application\Contract;
app/src/Modules/Outbox/Application/Contract/OutboxEventStoreContract.php:7:use App\Modules\Outbox\Application\Message\OutboxMessage;
app/src/Modules/Outbox/Application/Contract/OutboxEventStoreContract.php:8:use App\Modules\Outbox\Application\Message\StoredOutboxEventId;
app/src/Modules/Outbox/Application/Command/RelayOutbox/RelayOutboxCommand.php:5:namespace App\Modules\Outbox\Application\Command\RelayOutbox;
app/src/Modules/Outbox/README.md:60:app/src/Modules/Outbox/Application/Contract/OutboxEventStoreContract.php
app/src/Modules/Outbox/README.md:61:app/src/Modules/Outbox/Application/Contract/OutboxMessageLoaderContract.php
app/src/Modules/Outbox/README.md:62:app/src/Modules/Outbox/Application/Message/OutboxMessage.php
app/src/Modules/Outbox/README.md:222:namespace App\Modules\Notification\Application\Message;
app/src/Modules/Outbox/README.md:224:use App\Modules\Outbox\Application\Message\OutboxMessage;
app/src/Modules/Outbox/README.md:244:Application-команду с бизнес-данными.
app/src/Modules/Outbox/README.md:270:use App\Modules\Notification\Application\Command\SendWelcomeEmail\SendWelcomeEmailCommand;
app/src/Modules/Outbox/README.md:271:use App\Modules\Notification\Application\Command\SendWelcomeEmail\SendWelcomeEmailHandler;
app/src/Modules/Outbox/README.md:272:use App\Modules\Notification\Application\Message\SendWelcomeEmailMessage;
app/src/Modules/Outbox/README.md:273:use App\Modules\Outbox\Application\Contract\OutboxMessageLoaderContract;
app/src/Modules/Outbox/README.md:274:use App\Modules\Outbox\Application\Message\OutboxQueueEnvelope;
app/src/Modules/Outbox/README.md:477:Job читает сообщение через `OutboxMessageLoaderContract`. Application Handler
app/src/Modules/Access/Domain/Entity/Role.php:5:namespace App\Modules\Access\Domain\Entity;
app/src/Modules/Access/Domain/Entity/Role.php:7:use App\Modules\Access\Domain\ValueObject\RoleId;
app/src/Modules/Access/Domain/Entity/Role.php:8:use App\Modules\Access\Domain\ValueObject\RoleSlug;
app/src/Modules/Access/Domain/Entity/Role.php:9:use App\Modules\Access\Repository\RoleRepository;
app/src/Modules/Access/Domain/Collection/RoleCollection.php:5:namespace App\Modules\Access\Domain\Collection;
app/src/Modules/Access/Domain/Collection/RoleCollection.php:7:use App\Modules\Access\Domain\Entity\Role;
app/src/Shared/Domain/Exception/AuthenticationException.php:7:final class AuthenticationException extends DomainTranslatableException
app/src/Modules/Outbox/Application/Message/OutboxMessage.php:5:namespace App\Modules\Outbox\Application\Message;
app/src/Modules/Access/Domain/Collection/UserRoleCollection.php:5:namespace App\Modules\Access\Domain\Collection;
app/src/Modules/Access/Domain/Collection/UserRoleCollection.php:7:use App\Modules\Access\Domain\Entity\UserRole;
app/src/Modules/Outbox/Application/Command/ProcessDebugLogMessage/ProcessOutboxDebugLogMessageHandler.php:5:namespace App\Modules\Outbox\Application\Command\ProcessDebugLogMessage;
app/src/Modules/Outbox/Application/Message/SerializedOutboxMessage.php:5:namespace App\Modules\Outbox\Application\Message;
app/src/Modules/Outbox/Application/Message/OutboxDebugLogMessage.php:5:namespace App\Modules\Outbox\Application\Message;
app/src/Modules/Outbox/Application/Message/OutboxQueueEnvelope.php:5:namespace App\Modules\Outbox\Application\Message;
app/src/Modules/Outbox/Application/Message/StoredOutboxEventId.php:5:namespace App\Modules\Outbox\Application\Message;
app/src/Modules/Access/Domain/ValueObject/RolePermissionId.php:5:namespace App\Modules\Access\Domain\ValueObject;
app/src/Modules/Outbox/Application/Command/ProcessDebugLogMessage/ProcessOutboxDebugLogMessageCommand.php:5:namespace App\Modules\Outbox\Application\Command\ProcessDebugLogMessage;
app/src/Modules/Access/Domain/ValueObject/PermissionId.php:5:namespace App\Modules\Access\Domain\ValueObject;
app/src/Modules/Access/Domain/ValueObject/RoleId.php:5:namespace App\Modules\Access\Domain\ValueObject;
app/src/Modules/Access/Domain/ValueObject/UserRoleId.php:5:namespace App\Modules\Access\Domain\ValueObject;
app/src/Modules/Access/Domain/ValueObject/PermissionSlug.php:5:namespace App\Modules\Access\Domain\ValueObject;
app/src/Shared/Domain/ValueObject/AbstractIntegerValue.php:33:     * Нужно на границе Application, чтобы перевести невалидный вход в ValidationException (422)
app/src/Modules/Access/Domain/ValueObject/RoleSlug.php:5:namespace App\Modules\Access\Domain\ValueObject;
app/src/Modules/Outbox/Infrastructure/Message/OutboxMessageLoader.php:7:use App\Modules\Outbox\Application\Contract\OutboxMessageLoaderContract;
app/src/Modules/Outbox/Infrastructure/Message/OutboxMessageLoader.php:8:use App\Modules\Outbox\Application\Contract\OutboxMessageSerializerContract;
app/src/Modules/Outbox/Infrastructure/Message/OutboxMessageLoader.php:9:use App\Modules\Outbox\Application\Message\OutboxMessage;
app/src/Modules/Outbox/Infrastructure/Message/OutboxMessageLoader.php:10:use App\Modules\Outbox\Application\Exception\OutboxMessageLoadingException;
app/src/Modules/Outbox/Infrastructure/Message/OutboxMessageLoader.php:11:use App\Modules\Outbox\Application\Message\SerializedOutboxMessage;
app/src/Modules/Outbox/Infrastructure/Relay/SystemOutboxRelaySleeper.php:7:use App\Modules\Outbox\Application\Contract\OutboxRelaySleeperContract;
app/src/Modules/Outbox/Infrastructure/Message/ValinorOutboxMessageSerializer.php:7:use App\Modules\Outbox\Application\Contract\OutboxMessageSerializerContract;
app/src/Modules/Outbox/Infrastructure/Message/ValinorOutboxMessageSerializer.php:8:use App\Modules\Outbox\Application\Exception\OutboxMessageSerializationException;
app/src/Modules/Outbox/Infrastructure/Message/ValinorOutboxMessageSerializer.php:9:use App\Modules\Outbox\Application\Message\SerializedOutboxMessage;
app/src/Modules/Outbox/Infrastructure/Message/ValinorOutboxMessageSerializer.php:10:use App\Modules\Outbox\Application\Message\OutboxMessage;
app/src/Modules/Outbox/Infrastructure/Message/OutboxEventStore.php:7:use App\Modules\Outbox\Application\Contract\OutboxEventStoreContract;
app/src/Modules/Outbox/Infrastructure/Message/OutboxEventStore.php:8:use App\Modules\Outbox\Application\Contract\OutboxMessageSerializerContract;
app/src/Modules/Outbox/Infrastructure/Message/OutboxEventStore.php:9:use App\Modules\Outbox\Application\Message\OutboxMessage;
app/src/Modules/Outbox/Infrastructure/Message/OutboxEventStore.php:10:use App\Modules\Outbox\Application\Message\StoredOutboxEventId;
app/src/Modules/Outbox/Infrastructure/Relay/OutboxRelay.php:10:use App\Modules\Outbox\Application\Contract\OutboxRelayContract;
app/src/Modules/Outbox/Infrastructure/Relay/OutboxRelayWorker.php:7:use App\Modules\Outbox\Application\Contract\OutboxRelayContract;
app/src/Modules/Outbox/Infrastructure/Relay/OutboxRelayWorker.php:8:use App\Modules\Outbox\Application\Contract\OutboxRelayLoopControlContract;
app/src/Modules/Outbox/Infrastructure/Relay/OutboxRelayWorker.php:9:use App\Modules\Outbox\Application\Contract\OutboxRelaySleeperContract;
app/src/Modules/Outbox/Infrastructure/Relay/OutboxRelayWorker.php:10:use App\Modules\Outbox\Application\Contract\OutboxRelayWorkerContract;
app/src/Modules/Outbox/Infrastructure/Relay/InfiniteOutboxRelayLoopControl.php:7:use App\Modules\Outbox\Application\Contract\OutboxRelayLoopControlContract;
app/src/Modules/Outbox/Infrastructure/Bootloader/OutboxBootloader.php:7:use App\Modules\Outbox\Application\Contract\OutboxEventStoreContract;
app/src/Modules/Outbox/Infrastructure/Bootloader/OutboxBootloader.php:8:use App\Modules\Outbox\Application\Contract\OutboxJobRegistryContract;
app/src/Modules/Outbox/Infrastructure/Bootloader/OutboxBootloader.php:9:use App\Modules\Outbox\Application\Contract\OutboxMessageLoaderContract;
app/src/Modules/Outbox/Infrastructure/Bootloader/OutboxBootloader.php:10:use App\Modules\Outbox\Application\Contract\OutboxMessageSerializerContract;
app/src/Modules/Outbox/Infrastructure/Bootloader/OutboxBootloader.php:11:use App\Modules\Outbox\Application\Contract\OutboxRelayContract;
app/src/Modules/Outbox/Infrastructure/Bootloader/OutboxBootloader.php:12:use App\Modules\Outbox\Application\Contract\OutboxRelayLoopControlContract;
app/src/Modules/Outbox/Infrastructure/Bootloader/OutboxBootloader.php:13:use App\Modules\Outbox\Application\Contract\OutboxRelaySleeperContract;
app/src/Modules/Outbox/Infrastructure/Bootloader/OutboxBootloader.php:14:use App\Modules\Outbox\Application\Contract\OutboxRelayWorkerContract;
app/src/Modules/Outbox/Infrastructure/Bootloader/OutboxBootloader.php:15:use App\Modules\Outbox\Application\Message\OutboxDebugLogMessage;
app/src/Modules/Outbox/Infrastructure/Registry/OutboxJobRegistry.php:7:use App\Modules\Outbox\Application\Contract\OutboxJobRegistryContract;
app/src/Modules/Outbox/Infrastructure/Registry/OutboxJobRegistry.php:8:use App\Modules\Outbox\Application\Message\OutboxMessage;
app/src/Modules/Outbox/Infrastructure/Queue/OutboxQueueHeaders.php:7:final readonly class OutboxQueueHeaders
app/src/Modules/Outbox/Infrastructure/Queue/OutboxQueueHeaders.php:20:    public static function fromHeaders(array $headers): self
app/src/Shared/Infrastructure/Framework/Middleware/LocaleMiddleware.php:14:use Spiral\Http\Header\AcceptHeader;
app/src/Shared/Infrastructure/Framework/Middleware/LocaleMiddleware.php:15:use Spiral\Http\Header\AcceptHeaderItem;
app/src/Shared/Infrastructure/Framework/Middleware/LocaleMiddleware.php:38:        $acceptLanguage = $request->getHeaderLine('Accept-Language');
app/src/Shared/Infrastructure/Framework/Middleware/LocaleMiddleware.php:55:        return Collection::make(AcceptHeader::fromString($acceptLanguage)->getAll())
app/src/Shared/Infrastructure/Framework/Middleware/LocaleMiddleware.php:56:            ->filter(static fn(AcceptHeaderItem $item): bool => $item->getQuality() > 0.0)
app/src/Shared/Infrastructure/Framework/Middleware/LocaleMiddleware.php:57:            ->map(fn(AcceptHeaderItem $item): string => $this->primarySubtag((string) $item->getValue()))
app/src/Modules/Outbox/Infrastructure/Queue/OutboxQueueSerializer.php:7:use App\Modules\Outbox\Application\Message\OutboxQueueEnvelope;
app/src/Modules/Outbox/Infrastructure/Queue/OutboxQueueSerializer.php:46:            OutboxQueueHeaders::OUTBOX_ID => $outboxQueueEnvelope->outboxEventId->value(),
app/src/Modules/Outbox/Infrastructure/Queue/OutboxQueueSerializer.php:47:            OutboxQueueHeaders::OUTBOX_TYPE => $outboxQueueEnvelope->outboxEventType->value(),
app/src/Modules/Outbox/Infrastructure/Queue/OutboxQueueSerializer.php:56:        $this->assertRequiredStringKey(transportPayload: $transportPayload, key: OutboxQueueHeaders::OUTBOX_ID);
app/src/Modules/Outbox/Infrastructure/Queue/OutboxQueueSerializer.php:57:        $this->assertRequiredStringKey(transportPayload: $transportPayload, key: OutboxQueueHeaders::OUTBOX_TYPE);
app/src/Modules/Outbox/Infrastructure/Queue/OutboxQueueSerializer.php:60:            outboxEventId: OutboxEventId::fromString($transportPayload[OutboxQueueHeaders::OUTBOX_ID]),
app/src/Modules/Outbox/Infrastructure/Queue/OutboxQueueSerializer.php:61:            outboxEventType: OutboxEventType::fromString($transportPayload[OutboxQueueHeaders::OUTBOX_TYPE]),
app/src/Modules/Outbox/Infrastructure/Queue/OutboxQueueStatusInterceptor.php:7:use App\Modules\Outbox\Application\Message\OutboxQueueEnvelope;
app/src/Modules/Outbox/Infrastructure/Queue/OutboxQueueStatusInterceptor.php:97:        $outboxQueueHeaders = $this->outboxQueueHeadersFromParameters($parameters);
app/src/Modules/Outbox/Infrastructure/Queue/OutboxQueueStatusInterceptor.php:101:            $outboxQueueHeaders->outboxId !== null
app/src/Modules/Outbox/Infrastructure/Queue/OutboxQueueStatusInterceptor.php:104:                $outboxQueueHeaders->outboxId !== $outboxQueueEnvelope->outboxEventId->value()
app/src/Modules/Outbox/Infrastructure/Queue/OutboxQueueStatusInterceptor.php:106:                    $outboxQueueHeaders->outboxType !== null
app/src/Modules/Outbox/Infrastructure/Queue/OutboxQueueStatusInterceptor.php:107:                    && $outboxQueueHeaders->outboxType !== $outboxQueueEnvelope->outboxEventType->value()
app/src/Modules/Outbox/Infrastructure/Queue/OutboxQueueStatusInterceptor.php:112:                'headerOutboxId' => $outboxQueueHeaders->outboxId,
app/src/Modules/Outbox/Infrastructure/Queue/OutboxQueueStatusInterceptor.php:114:                'headerOutboxType' => $outboxQueueHeaders->outboxType,
app/src/Modules/Outbox/Infrastructure/Queue/OutboxQueueStatusInterceptor.php:121:        if ($outboxQueueHeaders->outboxId !== null) {
app/src/Modules/Outbox/Infrastructure/Queue/OutboxQueueStatusInterceptor.php:122:            return OutboxEventId::fromString($outboxQueueHeaders->outboxId);
app/src/Modules/Outbox/Infrastructure/Queue/OutboxQueueStatusInterceptor.php:131:    private function outboxQueueHeadersFromParameters(array $parameters): OutboxQueueHeaders
app/src/Modules/Outbox/Infrastructure/Queue/OutboxQueueStatusInterceptor.php:134:            return new OutboxQueueHeaders(
app/src/Modules/Outbox/Infrastructure/Queue/OutboxQueueStatusInterceptor.php:140:        return OutboxQueueHeaders::fromHeaders($parameters['headers']);
app/src/Modules/Outbox/Infrastructure/Queue/OutboxQueueStatusInterceptor.php:162:        if (!\array_key_exists(key: OutboxQueueHeaders::OUTBOX_ID, array: $payload)
app/src/Modules/Outbox/Infrastructure/Queue/OutboxQueueStatusInterceptor.php:163:            || !\array_key_exists(key: OutboxQueueHeaders::OUTBOX_TYPE, array: $payload)) {
app/src/Modules/Outbox/Infrastructure/Queue/OutboxQueueStatusInterceptor.php:165:                'hasOutboxId' => \array_key_exists(key: OutboxQueueHeaders::OUTBOX_ID, array: $payload),
app/src/Modules/Outbox/Infrastructure/Queue/OutboxQueueStatusInterceptor.php:166:                'hasOutboxType' => \array_key_exists(key: OutboxQueueHeaders::OUTBOX_TYPE, array: $payload),
app/src/Modules/Outbox/Infrastructure/Queue/OutboxQueueStatusInterceptor.php:172:        if (!\is_string($payload[OutboxQueueHeaders::OUTBOX_ID]) || !\is_string($payload[OutboxQueueHeaders::OUTBOX_TYPE])) {
app/src/Modules/Outbox/Infrastructure/Queue/OutboxQueueStatusInterceptor.php:174:                'outboxIdType' => \get_debug_type($payload[OutboxQueueHeaders::OUTBOX_ID]),
app/src/Modules/Outbox/Infrastructure/Queue/OutboxQueueStatusInterceptor.php:175:                'outboxTypeType' => \get_debug_type($payload[OutboxQueueHeaders::OUTBOX_TYPE]),
app/src/Modules/Outbox/Infrastructure/Queue/OutboxQueueStatusInterceptor.php:182:            OutboxQueueHeaders::OUTBOX_ID => $payload[OutboxQueueHeaders::OUTBOX_ID],
app/src/Modules/Outbox/Infrastructure/Queue/OutboxQueueStatusInterceptor.php:183:            OutboxQueueHeaders::OUTBOX_TYPE => $payload[OutboxQueueHeaders::OUTBOX_TYPE],
app/src/Modules/Outbox/Infrastructure/Queue/OutboxQueuePublisher.php:7:use App\Modules\Outbox\Application\Contract\OutboxJobRegistryContract;
app/src/Modules/Outbox/Infrastructure/Queue/OutboxQueuePublisher.php:8:use App\Modules\Outbox\Application\Contract\OutboxMessageSerializerContract;
app/src/Modules/Outbox/Infrastructure/Queue/OutboxQueuePublisher.php:9:use App\Modules\Outbox\Application\Message\OutboxQueueEnvelope;
app/src/Modules/Outbox/Infrastructure/Queue/OutboxQueuePublisher.php:10:use App\Modules\Outbox\Application\Message\SerializedOutboxMessage;
app/src/Modules/Outbox/Infrastructure/Queue/OutboxQueuePublisher.php:45:                ->withHeader(
app/src/Modules/Outbox/Infrastructure/Queue/OutboxQueuePublisher.php:46:                    name: OutboxQueueHeaders::OUTBOX_ID,
app/src/Modules/Outbox/Infrastructure/Queue/OutboxQueuePublisher.php:47:                    value: $this->nonEmptyHeaderValue($storedOutboxEvent->id->value()),
app/src/Modules/Outbox/Infrastructure/Queue/OutboxQueuePublisher.php:49:                ->withHeader(
app/src/Modules/Outbox/Infrastructure/Queue/OutboxQueuePublisher.php:50:                    name: OutboxQueueHeaders::OUTBOX_TYPE,
app/src/Modules/Outbox/Infrastructure/Queue/OutboxQueuePublisher.php:51:                    value: $this->nonEmptyHeaderValue($storedOutboxEvent->type->value()),
app/src/Modules/Outbox/Infrastructure/Queue/OutboxQueuePublisher.php:90:    private function nonEmptyHeaderValue(string $value): string

codex
Уже видно несколько мест, где исследование опирается на несуществующий слой `User/Application` и на неточные ссылки. Дальше проверяю точные сигнатуры `spiral/auth` и текущие версии пакетов, потому что это самый рискованный кусок решения.
exec
/bin/zsh -lc "sed -n '260,380p' docs/researches/2026-06-13_22-36_auth-module.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
Auth/Presentation   -> Auth/Application, Shared RateLimited
Shared              -> RateLimited + RateLimitInterceptor (общие, не про Auth)
User                -> про токены/коды не знает; даёт CreateUser/FindUserForAuth на границе
```

### Версии и зависимости

Новых внешних пакетов не требуется. Всё на установленном: PHP 8.5, `spiral/framework` 3.16.2
(включает `spiral/auth`, `spiral/auth-http`, `spiral/security`), `spiral/cycle-bridge` ^2.11,
`ext-redis`, `MailerBootloader` + Mailpit, outbox-модуль. JWT-библиотека и OAuth-провайдеры
не нужны — выбран непрозрачный токен и беспарольный вход. Проверять актуальные версии нечего:
ничего нового не вводим (источник: `composer.json:11-40`, context7 Spiral 3.16 auth docs).

## Ответы на вопросы

```text
Что входит в этот заход?
  Беспарольный вход по email-коду = вход и регистрация одновременно.
  Сброса/смены пароля нет (пароля нет). В ядре: запрос кода, проверка кода,
  регистрация по талону, refresh, logout. Плюс rate limit через интерцептор.
  Отложено: вход через Apple/Google, привязка ролей Access к актору (Guard).

Чем выдавать/проверять токены?
  Непрозрачные access + refresh, хранилище на Cycle (своя таблица auth_tokens),
  refresh ротируется, Bearer-транспорт. JWT и сессии-куки отклонены
  (для одного монолита и нативного мобайла выгоды нет).

Где лежат данные аутентификации?
  Отдельный модуль Auth со своими таблицами (auth_login_codes,
  auth_registration_tickets, auth_tokens), связь по UserId. Пароля нет.

Что такое «талон регистрации»?
  Короткоживущая одноразовая запись по email, доказывающая «почта только что
  подтверждена кодом». Нужна, потому что User требует имя+ник, а email-вход их
  не даёт: код -> талон -> экран профиля -> создание User.

Поток регистрации для нового email?
  Код -> талон -> профиль. Домен User не меняем, пустых аккаунтов нет,
  ники не занимаются на неподтверждённые email. User рождается Active.

Где хранить коды?
  Таблица PostgreSQL в модуле Auth, только хэш (HMAC с серверным секретом),
  со сроком и счётчиком попыток. Redis оставили под rate limit.

Rate limit — какой интерцептор и по чему?
  Атрибут #[RateLimited] на действии контроллера + общий Spiral-интерцептор
  в Shared, лимит по IP + действие, счётчики в Redis. Лимит по email
  (код в минуту, попытки) — правило сценария Auth по таблице кодов.

Подтверждение почты?
  Это и есть код входа: получил код на почту = подтвердил владение.
  WaitingEmailConfirmation/confirmEmail остаются для смены email.
```

## Итог

Делаем модуль `Auth` с беспарольным входом по email-коду (вход = регистрация). Поток:
`request code` → `verify code` → существующему юзеру сразу пара токенов, новому — талон, по
талону `register` (имя+ник) → создание `User` (Active) + токены; плюс `refresh` (с ротацией)
и `logout` (отзыв). Токены непрозрачные access+refresh в собственной Cycle-таблице
`auth_tokens` через свою реализацию `spiral/auth TokenStorageInterface`; коды и талоны — в
своих таблицах Auth, хэшированные HMAC с серверным секретом, со сроком и счётчиком попыток.
Письмо с кодом — через outbox + Mailer. Rate limit — переиспользуемый `#[RateLimited]` +
интерцептор в `Shared` (IP+действие), email-троттлинг — в сценарии Auth.

Следующий шаг — `eda-plan` на реализацию модуля `Auth` по этому исследованию: миграции трёх
таблиц, Entity+VO+typecast, репозитории, `CycleTokenStorage` и `UserActorProvider`,
`SecretHasherContract`+реализация, сценарии RequestLoginCode/VerifyLoginCode/
CompleteRegistration/RefreshTokens/Logout, контроллер+фильтры+ресурсы, `LoginCodeRequested`
+ `SendLoginCodeJob`, `RateLimited`+`RateLimitInterceptor` в Shared, регистрация
AuthMiddleware (header) в API-группе и AuthBootloader. План обязан включить (или идти после)
`User/Application`: `CreateUser` и `FindUserForAuth` — без них Auth не собрать. Языки писем,
конкретные TTL и интеграционные тесты роутов фиксируются в плане.

exec
/bin/zsh -lc "nl -ba app/src/Modules/User/Domain/Entity/User.php | sed -n '1,180p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\User\Domain\Entity;
     6	
     7	use App\Modules\User\Domain\Enum\UserStatus;
     8	use App\Modules\User\Domain\Enum\UserVerification;
     9	use App\Modules\User\Domain\ValueObject\Email;
    10	use App\Modules\User\Domain\ValueObject\UserAvatar;
    11	use App\Modules\User\Domain\ValueObject\UserBio;
    12	use App\Modules\User\Domain\ValueObject\UserDeletion;
    13	use App\Modules\User\Domain\ValueObject\UserLocation;
    14	use App\Modules\User\Domain\ValueObject\UserName;
    15	use App\Modules\User\Domain\ValueObject\UserNickname;
    16	use App\Modules\User\Domain\ValueObject\UserSpiritualName;
    17	use App\Modules\User\Infrastructure\Cycle\UserAvatarTypecast;
    18	use App\Modules\User\Infrastructure\Cycle\UserBioTypecast;
    19	use App\Modules\User\Infrastructure\Cycle\UserDeletionTypecast;
    20	use App\Modules\User\Infrastructure\Cycle\UserLocationTypecast;
    21	use App\Modules\User\Infrastructure\Cycle\UserSpiritualNameTypecast;
    22	use App\Modules\User\Repository\UserRepository;
    23	use App\Shared\Domain\Enum\Locale;
    24	use App\Shared\Domain\Trait\HasTimestamps;
    25	use App\Shared\Domain\ValueObject\UserId;
    26	use App\Shared\Infrastructure\Cycle\ValueObjectCast;
    27	use Cycle\Annotated\Annotation\Column;
    28	use Cycle\Annotated\Annotation\Entity;
    29	use Cycle\ORM\Parser\Typecast;
    30	
    31	#[Entity(
    32	    role: 'user',
    33	    table: 'users',
    34	    repository: UserRepository::class,
    35	    typecast: [Typecast::class, ValueObjectCast::class],
    36	)]
    37	final class User
    38	{
    39	    use HasTimestamps;
    40	
    41	    #[Column(type: 'uuid', primary: true, typecast: UserId::class)]
    42	    public private(set) UserId $id;
    43	
    44	    #[Column(type: 'string(100)', typecast: UserName::class)]
    45	    public private(set) UserName $name;
    46	
    47	    #[Column(type: 'string(100)', name: 'spiritual_name', nullable: true, typecast: UserSpiritualNameTypecast::class)]
    48	    public private(set) UserSpiritualName $spiritualName;
    49	
    50	    #[Column(type: 'text', nullable: true, typecast: UserBioTypecast::class)]
    51	    public private(set) UserBio $bio;
    52	
    53	    #[Column(type: 'string(100)', nullable: true, typecast: UserLocationTypecast::class)]
    54	    public private(set) UserLocation $location;
    55	
    56	    #[Column(type: 'string(254)', typecast: Email::class)]
    57	    public private(set) Email $email;
    58	
    59	    #[Column(type: 'string(30)', typecast: UserNickname::class)]
    60	    public private(set) UserNickname $nickname;
    61	
    62	    #[Column(type: 'uuid', name: 'avatar_media_id', nullable: true, typecast: UserAvatarTypecast::class)]
    63	    public private(set) UserAvatar $avatar;
    64	
    65	    #[Column(type: 'string(32)', typecast: UserVerification::class)]
    66	    public private(set) UserVerification $verification;
    67	
    68	    #[Column(type: 'string(32)', typecast: UserStatus::class)]
    69	    public private(set) UserStatus $status;
    70	
    71	    #[Column(type: 'string(8)', typecast: Locale::class)]
    72	    public private(set) Locale $locale;
    73	
    74	    #[Column(type: 'datetime', name: 'deleted_at', nullable: true, typecast: UserDeletionTypecast::class)]
    75	    public private(set) UserDeletion $deletion;
    76	
    77	    public static function create(
    78	        UserName $name,
    79	        Email $email,
    80	        UserNickname $nickname,
    81	        Locale $locale,
    82	    ): self {
    83	        $user = new self();
    84	        $user->id = UserId::generate();
    85	        $user->name = $name;
    86	        $user->spiritualName = UserSpiritualName::none();
    87	        $user->bio = UserBio::none();
    88	        $user->location = UserLocation::none();
    89	        $user->email = $email;
    90	        $user->nickname = $nickname;
    91	        $user->avatar = UserAvatar::none();
    92	        $user->verification = UserVerification::Unverified;
    93	        $user->status = UserStatus::WaitingEmailConfirmation;
    94	        $user->locale = $locale;
    95	        $user->deletion = UserDeletion::active();
    96	        $user->initializeTimestamps();
    97	
    98	        return $user;
    99	    }
   100	
   101	    public function confirmEmail(): void
   102	    {
   103	        $this->status = UserStatus::Active;
   104	        $this->touch();
   105	    }
   106	
   107	    public function changeEmail(Email $email): void
   108	    {
   109	        $this->email = $email;
   110	        $this->status = UserStatus::WaitingEmailConfirmation;
   111	        $this->touch();
   112	    }
   113	
   114	    public function changeNickname(UserNickname $nickname): void
   115	    {
   116	        $this->nickname = $nickname;
   117	        $this->touch();
   118	    }
   119	
   120	    public function rename(UserName $name): void
   121	    {
   122	        $this->name = $name;
   123	        $this->touch();
   124	    }
   125	
   126	    public function changeSpiritualName(UserSpiritualName $spiritualName): void
   127	    {
   128	        $this->spiritualName = $spiritualName;
   129	        $this->touch();
   130	    }
   131	
   132	    public function changeBio(UserBio $bio): void
   133	    {
   134	        $this->bio = $bio;
   135	        $this->touch();
   136	    }
   137	
   138	    public function changeLocation(UserLocation $location): void
   139	    {
   140	        $this->location = $location;
   141	        $this->touch();
   142	    }
   143	
   144	    public function changeLocale(Locale $locale): void
   145	    {
   146	        $this->locale = $locale;
   147	        $this->touch();
   148	    }
   149	
   150	    public function setAvatar(UserAvatar $avatar): void
   151	    {
   152	        $this->avatar = $avatar;
   153	        $this->touch();
   154	    }
   155	
   156	    public function removeAvatar(): void
   157	    {
   158	        $this->avatar = UserAvatar::none();
   159	        $this->touch();
   160	    }
   161	
   162	    public function verify(): void
   163	    {
   164	        $this->verification = UserVerification::Verified;
   165	        $this->touch();
   166	    }
   167	
   168	    public function unverify(): void
   169	    {
   170	        $this->verification = UserVerification::Unverified;
   171	        $this->touch();
   172	    }
   173	
   174	    public function ban(): void
   175	    {
   176	        $this->status = UserStatus::Banned;
   177	        $this->touch();
   178	    }
   179	
   180	    public function unban(): void

exec
/bin/zsh -lc "nl -ba app/src/Modules/User/Repository/UserRepository.php | sed -n '1,220p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\User\Repository;
     6	
     7	use App\Modules\User\Domain\Entity\User;
     8	use App\Modules\User\Domain\ValueObject\Email;
     9	use App\Modules\User\Domain\ValueObject\UserNickname;
    10	use App\Shared\Domain\ValueObject\UserId;
    11	use Cycle\ORM\Select\Repository;
    12	
    13	/**
    14	 * @extends Repository<User>
    15	 */
    16	final class UserRepository extends Repository
    17	{
    18	    public function findById(UserId $userId): User|null
    19	    {
    20	        return $this->findByPK($userId->value());
    21	    }
    22	
    23	    public function findByEmail(Email $email): User|null
    24	    {
    25	        return $this->findOne(['email' => $email->value()]);
    26	    }
    27	
    28	    public function findByNickname(UserNickname $nickname): User|null
    29	    {
    30	        return $this->findOne(['nickname' => $nickname->value()]);
    31	    }
    32	
    33	    public function existsByEmail(Email $email): bool
    34	    {
    35	        return $this->findByEmail($email) !== null;
    36	    }
    37	
    38	    public function existsByNickname(UserNickname $nickname): bool
    39	    {
    40	        return $this->findByNickname($nickname) !== null;
    41	    }
    42	}

exec
/bin/zsh -lc "find vendor/spiral -path '*Auth*' -o -path '*auth*' | sort | sed -n '1,240p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc "composer show spiral/framework spiral/auth spiral/auth-http spiral/security spiral/cycle-bridge | sed -n '1,220p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 111ms:
vendor/spiral/cycle-bridge/src/Auth
vendor/spiral/cycle-bridge/src/Auth/Token.php
vendor/spiral/cycle-bridge/src/Auth/TokenStorage.php
vendor/spiral/cycle-bridge/src/Bootloader/AuthTokensBootloader.php
vendor/spiral/framework/src/Auth
vendor/spiral/framework/src/Auth/LICENSE
vendor/spiral/framework/src/Auth/README.md
vendor/spiral/framework/src/Auth/composer.json
vendor/spiral/framework/src/Auth/src
vendor/spiral/framework/src/Auth/src/ActorProviderInterface.php
vendor/spiral/framework/src/Auth/src/AuthContext.php
vendor/spiral/framework/src/Auth/src/AuthContextInterface.php
vendor/spiral/framework/src/Auth/src/Event
vendor/spiral/framework/src/Auth/src/Event/Authenticated.php
vendor/spiral/framework/src/Auth/src/Event/Logout.php
vendor/spiral/framework/src/Auth/src/Exception
vendor/spiral/framework/src/Auth/src/Exception/AuthException.php
vendor/spiral/framework/src/Auth/src/Exception/TokenStorageException.php
vendor/spiral/framework/src/Auth/src/TokenInterface.php
vendor/spiral/framework/src/Auth/src/TokenStorageInterface.php
vendor/spiral/framework/src/Auth/src/TokenStorageProvider.php
vendor/spiral/framework/src/Auth/src/TokenStorageProviderInterface.php
vendor/spiral/framework/src/AuthHttp
vendor/spiral/framework/src/AuthHttp/LICENSE
vendor/spiral/framework/src/AuthHttp/README.md
vendor/spiral/framework/src/AuthHttp/composer.json
vendor/spiral/framework/src/AuthHttp/src
vendor/spiral/framework/src/AuthHttp/src/Exception
vendor/spiral/framework/src/AuthHttp/src/Exception/TransportException.php
vendor/spiral/framework/src/AuthHttp/src/HttpTransportInterface.php
vendor/spiral/framework/src/AuthHttp/src/Middleware
vendor/spiral/framework/src/AuthHttp/src/Middleware/AuthMiddleware.php
vendor/spiral/framework/src/AuthHttp/src/Middleware/AuthTransportMiddleware.php
vendor/spiral/framework/src/AuthHttp/src/Middleware/AuthTransportWithStorageMiddleware.php
vendor/spiral/framework/src/AuthHttp/src/Middleware/Firewall
vendor/spiral/framework/src/AuthHttp/src/Middleware/Firewall/AbstractFirewall.php
vendor/spiral/framework/src/AuthHttp/src/Middleware/Firewall/ExceptionFirewall.php
vendor/spiral/framework/src/AuthHttp/src/Middleware/Firewall/OverwriteFirewall.php
vendor/spiral/framework/src/AuthHttp/src/Middleware/Firewall/RedirectFirewall.php
vendor/spiral/framework/src/AuthHttp/src/Transport
vendor/spiral/framework/src/AuthHttp/src/Transport/CookieTransport.php
vendor/spiral/framework/src/AuthHttp/src/Transport/HeaderTransport.php
vendor/spiral/framework/src/AuthHttp/src/TransportRegistry.php
vendor/spiral/framework/src/Broadcasting/src/AuthorizationStatus.php
vendor/spiral/framework/src/Broadcasting/src/Event/Authorized.php
vendor/spiral/framework/src/Broadcasting/src/Middleware/AuthorizationMiddleware.php
vendor/spiral/framework/src/Filters/src/Exception/AuthorizationException.php
vendor/spiral/framework/src/Framework/Auth
vendor/spiral/framework/src/Framework/Auth/AuthScope.php
vendor/spiral/framework/src/Framework/Auth/Config
vendor/spiral/framework/src/Framework/Auth/Config/AuthConfig.php
vendor/spiral/framework/src/Framework/Auth/Exception
vendor/spiral/framework/src/Framework/Auth/Exception/InvalidArgumentException.php
vendor/spiral/framework/src/Framework/Auth/Session
vendor/spiral/framework/src/Framework/Auth/Session/Token.php
vendor/spiral/framework/src/Framework/Auth/Session/TokenStorage.php
vendor/spiral/framework/src/Framework/Auth/TokenStorageScope.php
vendor/spiral/framework/src/Framework/Bootloader/Auth
vendor/spiral/framework/src/Framework/Bootloader/Auth/AuthBootloader.php
vendor/spiral/framework/src/Framework/Bootloader/Auth/HttpAuthBootloader.php
vendor/spiral/framework/src/Framework/Bootloader/Auth/SecurityActorBootloader.php
vendor/spiral/framework/src/Framework/Bootloader/Auth/TokenStorage
vendor/spiral/framework/src/Framework/Bootloader/Auth/TokenStorage/SessionTokensBootloader.php
vendor/spiral/framework/src/Http/src/Exception/ClientException/UnauthorizedException.php
vendor/spiral/roadrunner-grpc/src/Exception/UnauthenticatedException.php
vendor/spiral/testing/src/Auth
vendor/spiral/testing/src/Auth/FakeActorProvider.php

 succeeded in 376ms:

                                                                               
  Too many arguments to "show" command, expected arguments "package" "version  
  ".                                                                           
                                                                               

show [--all] [--locked] [-i|--installed] [-p|--platform] [-a|--available] [-s|--self] [-N|--name-only] [-P|--path] [-t|--tree] [-l|--latest] [-o|--outdated] [--ignore IGNORE] [-M|--major-only] [-m|--minor-only] [--patch-only] [-A|--sort-by-age] [-D|--direct] [--strict] [-f|--format FORMAT] [--no-dev] [--ignore-platform-req IGNORE-PLATFORM-REQ] [--ignore-platform-reqs] [--] [<package> [<version>]]


codex
Проверяю фактический код пакета в `vendor`, потому что формулировка в исследовании про `getActor(token)` выглядит подозрительно: важно не перепутать `TokenInterface` и строку токена.
exec
/bin/zsh -lc "nl -ba vendor/spiral/framework/src/Auth/src/TokenStorageInterface.php | sed -n '1,180p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace Spiral\Auth;
     6	
     7	use Spiral\Auth\Exception\TokenStorageException;
     8	
     9	/**
    10	 * Provides the ability to store tokens in persistent storage.
    11	 */
    12	interface TokenStorageInterface
    13	{
    14	    /**
    15	     * Load token by id, must return null if token not found.
    16	     *
    17	     * @throws TokenStorageException
    18	     */
    19	    public function load(string $id): ?TokenInterface;
    20	
    21	    /**
    22	     * Create token based on the payload provided by actor provider.
    23	     *
    24	     * @throws TokenStorageException
    25	     */
    26	    public function create(array $payload, ?\DateTimeInterface $expiresAt = null): TokenInterface;
    27	
    28	    /**
    29	     * Delete token from the persistent storage.
    30	     *
    31	     * @throws TokenStorageException
    32	     */
    33	    public function delete(TokenInterface $token): void;
    34	}

exec
/bin/zsh -lc "nl -ba vendor/spiral/framework/src/AuthHttp/src/Middleware/AuthMiddleware.php | sed -n '1,220p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc "nl -ba vendor/spiral/framework/src/Auth/src/ActorProviderInterface.php | sed -n '1,180p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace Spiral\Auth\Middleware;
     6	
     7	use Psr\EventDispatcher\EventDispatcherInterface;
     8	use Psr\Http\Message\ResponseInterface as Response;
     9	use Psr\Http\Message\ServerRequestInterface as Request;
    10	use Psr\Http\Server\MiddlewareInterface;
    11	use Psr\Http\Server\RequestHandlerInterface;
    12	use Spiral\Auth\ActorProviderInterface;
    13	use Spiral\Auth\AuthContext;
    14	use Spiral\Auth\AuthContextInterface;
    15	use Spiral\Auth\TokenStorageInterface;
    16	use Spiral\Auth\TransportRegistry;
    17	use Spiral\Core\Attribute\Scope;
    18	use Spiral\Core\ScopeInterface;
    19	
    20	/**
    21	 * Manages auth context scope.
    22	 */
    23	#[Scope('http')]
    24	final class AuthMiddleware implements MiddlewareInterface
    25	{
    26	    public const ATTRIBUTE = 'authContext';
    27	    public const TOKEN_STORAGE_ATTRIBUTE = 'tokenStorage';
    28	
    29	    /**
    30	     * @param ScopeInterface $scope Deprecated, will be removed in v4.0.
    31	     */
    32	    public function __construct(
    33	        private readonly ScopeInterface $scope,
    34	        private readonly ActorProviderInterface $actorProvider,
    35	        private readonly TokenStorageInterface $tokenStorage,
    36	        private readonly TransportRegistry $transportRegistry,
    37	        private readonly ?EventDispatcherInterface $eventDispatcher = null,
    38	    ) {}
    39	
    40	    /**
    41	     * @throws \Throwable
    42	     */
    43	    public function process(Request $request, RequestHandlerInterface $handler): Response
    44	    {
    45	        $authContext = $this->initContext($request, new AuthContext($this->actorProvider, $this->eventDispatcher));
    46	
    47	        $response = $handler->handle(
    48	            $request
    49	                ->withAttribute(self::ATTRIBUTE, $authContext)
    50	                ->withAttribute(self::TOKEN_STORAGE_ATTRIBUTE, $this->tokenStorage),
    51	        );
    52	
    53	        return $this->closeContext($request, $response, $authContext);
    54	    }
    55	
    56	    private function initContext(Request $request, AuthContextInterface $authContext): AuthContextInterface
    57	    {
    58	        foreach ($this->transportRegistry->getTransports() as $name => $transport) {
    59	            $tokenID = $transport->fetchToken($request);
    60	            if ($tokenID === null) {
    61	                continue;
    62	            }
    63	
    64	            $token = $this->tokenStorage->load($tokenID);
    65	            if ($token === null) {
    66	                continue;
    67	            }
    68	
    69	            // found valid token
    70	            $authContext->start($token, $name);
    71	            return $authContext;
    72	        }
    73	
    74	        return $authContext;
    75	    }
    76	
    77	    private function closeContext(Request $request, Response $response, AuthContextInterface $authContext): Response
    78	    {
    79	        if ($authContext->getToken() === null) {
    80	            return $response;
    81	        }
    82	
    83	        $transport = $this->transportRegistry->getTransport($authContext->getTransport());
    84	
    85	        if ($authContext->isClosed()) {
    86	            $this->tokenStorage->delete($authContext->getToken());
    87	
    88	            return $transport->removeToken(
    89	                $request,
    90	                $response,
    91	                $authContext->getToken()->getID(),
    92	            );
    93	        }
    94	
    95	        return $transport->commitToken(
    96	            $request,
    97	            $response,
    98	            $authContext->getToken()->getID(),
    99	            $authContext->getToken()->getExpiresAt(),
   100	        );
   101	    }
   102	}

 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace Spiral\Auth;
     6	
     7	/**
     8	 * Manages association of token payload and actor.
     9	 */
    10	interface ActorProviderInterface
    11	{
    12	    /**
    13	     * Return actor associated with token payload (if any). Must return null if actor not found.
    14	     */
    15	    public function getActor(TokenInterface $token): ?object;
    16	}

exec
/bin/zsh -lc "nl -ba vendor/spiral/cycle-bridge/src/Auth/TokenStorage.php | sed -n '1,240p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace Spiral\Cycle\Auth;
     6	
     7	use Cycle\ORM\EntityManagerInterface;
     8	use Cycle\ORM\ORMInterface;
     9	use Spiral\Auth\Exception\TokenStorageException;
    10	use Spiral\Auth\TokenInterface;
    11	use Spiral\Auth\TokenStorageInterface;
    12	
    13	/**
    14	 * Provides the ability to fetch token information from the database via Cycle ORM.
    15	 */
    16	final class TokenStorage implements TokenStorageInterface
    17	{
    18	    public function __construct(
    19	        private readonly ORMInterface $orm,
    20	        private readonly EntityManagerInterface $em,
    21	    ) {}
    22	
    23	    public function load(string $id): ?TokenInterface
    24	    {
    25	        if (!\str_contains($id, ':')) {
    26	            return null;
    27	        }
    28	
    29	        [$pk, $hash] = \explode(':', $id, 2);
    30	
    31	        /** @var Token $token */
    32	        $token = $this->orm->getRepository(Token::class)->findByPK($pk);
    33	
    34	        if ($token === null || !\hash_equals($token->getHashedValue(), \hash('sha512', $hash))) {
    35	            // hijacked or deleted
    36	            return null;
    37	        }
    38	
    39	        $token->setSecretValue($hash);
    40	
    41	        $expiresAt = $token->getExpiresAt();
    42	        if ($expiresAt !== null && $expiresAt < new \DateTimeImmutable()) {
    43	            $this->delete($token);
    44	
    45	            return null;
    46	        }
    47	
    48	        return $token;
    49	    }
    50	
    51	    public function create(array $payload, ?\DateTimeInterface $expiresAt = null): TokenInterface
    52	    {
    53	        try {
    54	            $token = new Token(
    55	                $this->issueID(),
    56	                $this->randomHash(128),
    57	                $payload,
    58	                new \DateTimeImmutable(),
    59	                $expiresAt,
    60	            );
    61	
    62	            $this->em->persist($token);
    63	            $this->em->run();
    64	
    65	            return $token;
    66	        } catch (\Throwable $e) {
    67	            throw new TokenStorageException('Unable to create token', (int) $e->getCode(), $e);
    68	        }
    69	    }
    70	
    71	    public function delete(TokenInterface $token): void
    72	    {
    73	        try {
    74	            $this->em->delete($token);
    75	            $this->em->run();
    76	        } catch (\Throwable $e) {
    77	            throw new TokenStorageException('Unable to delete token', (int) $e->getCode(), $e);
    78	        }
    79	    }
    80	
    81	    /**
    82	     * Issue unique token id.
    83	     */
    84	    private function issueID(): string
    85	    {
    86	        $id = $this->randomHash(64);
    87	
    88	        /** @psalm-suppress InternalMethod */
    89	        $query = $this->orm->getSource(Token::class)
    90	            ->getDatabase()
    91	            ->select()
    92	            ->from($this->orm->getSource(Token::class)->getTable());
    93	
    94	        /** @psalm-suppress InternalMethod */
    95	        while ((clone $query)->where('id', $id)->count('id') !== 0) {
    96	            $id = $this->randomHash(64);
    97	        }
    98	
    99	        return $id;
   100	    }
   101	
   102	    private function randomHash(int $length): string
   103	    {
   104	        return \substr(\bin2hex(\random_bytes($length)), 0, $length);
   105	    }
   106	}

exec
/bin/zsh -lc "nl -ba vendor/spiral/framework/src/AuthHttp/src/Transport/HeaderTransport.php | sed -n '1,220p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace Spiral\Auth\Transport;
     6	
     7	use Psr\Http\Message\ResponseInterface as Response;
     8	use Psr\Http\Message\ServerRequestInterface as Request;
     9	use Spiral\Auth\HttpTransportInterface;
    10	
    11	/**
    12	 * Reads and writes auth tokens via headers.
    13	 */
    14	final class HeaderTransport implements HttpTransportInterface
    15	{
    16	    public function __construct(
    17	        private readonly string $header = 'X-Auth-Token',
    18	        private readonly string $valueFormat = '%s',
    19	    ) {}
    20	
    21	    public function fetchToken(Request $request): ?string
    22	    {
    23	        if ($request->hasHeader($this->header)) {
    24	            return $this->extractToken($request);
    25	        }
    26	
    27	        return null;
    28	    }
    29	
    30	    public function commitToken(
    31	        Request $request,
    32	        Response $response,
    33	        string $tokenID,
    34	        ?\DateTimeInterface $expiresAt = null,
    35	    ): Response {
    36	        if ($request->hasHeader($this->header) && $this->extractToken($request) === $tokenID) {
    37	            return $response;
    38	        }
    39	
    40	        return $response->withAddedHeader($this->header, \sprintf($this->valueFormat, $tokenID));
    41	    }
    42	
    43	    public function removeToken(Request $request, Response $response, string $tokenID): Response
    44	    {
    45	        return $response;
    46	    }
    47	
    48	    private function extractToken(Request $request): ?string
    49	    {
    50	        $headerLine = $request->getHeaderLine($this->header);
    51	
    52	        if ($this->valueFormat !== '%s') {
    53	            [$token] = \sscanf($headerLine, $this->valueFormat);
    54	
    55	            return $token !== null ? (string) $token : null;
    56	        }
    57	
    58	        return $headerLine;
    59	    }
    60	}

exec
/bin/zsh -lc "composer show spiral/framework | sed -n '1,120p'; composer show spiral/cycle-bridge | sed -n '1,120p'; composer show spiral/auth | sed -n '1,80p'; composer show spiral/auth-http | sed -n '1,80p'; composer show spiral/security | sed -n '1,80p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 1527ms:
name     : spiral/framework
descrip. : Spiral, High-Performance PHP/Go Framework
keywords : 
versions : * 3.16.2
released : 2026-04-09, 2 months ago
type     : library
license  : MIT License (MIT) (OSI approved) https://spdx.org/licenses/MIT.html#licenseText
homepage : https://spiral.dev
source   : [git] https://github.com/spiral/framework.git 287accbfdf9b4e9031b93d7576550f3bef43b1f4
dist     : [zip] https://api.github.com/repos/spiral/framework/zipball/287accbfdf9b4e9031b93d7576550f3bef43b1f4 287accbfdf9b4e9031b93d7576550f3bef43b1f4
path     : /Users/gian_tiaga/Code/yoga-loka-spiral-2/vendor/spiral/framework
names    : spiral/framework, spiral/annotated-routes, spiral/auth, spiral/auth-http, spiral/boot, spiral/broadcasting, spiral/cache, spiral/config, spiral/console, spiral/cookies, spiral/core, spiral/csrf, spiral/debug, spiral/distribution, spiral/dotenv-bridge, spiral/encrypter, spiral/events, spiral/exceptions, spiral/files, spiral/filters, spiral/hmvc, spiral/http, spiral/interceptors, spiral/logger, spiral/mailer, spiral/models, spiral/monolog-bridge, spiral/pagination, spiral/prototype, spiral/queue, spiral/reactor, spiral/router, spiral/scaffolder, spiral/security, spiral/sendit, spiral/serializer, spiral/session, spiral/snapshots, spiral/stempler, spiral/stempler-bridge, spiral/storage, spiral/streams, spiral/telemetry, spiral/tokenizer, spiral/translator, spiral/validation, spiral/views

support
issues : https://github.com/spiral/framework/issues
source : https://github.com/spiral/framework

autoload
files
psr-4
Spiral\ => src/Framework
Spiral\Auth\ => src/Auth/src, src/AuthHttp/src
Spiral\Boot\ => src/Boot/src
Spiral\Core\ => src/Core/src, src/Hmvc/src
Spiral\Csrf\ => src/Csrf/src
Spiral\Http\ => src/Http/src
Spiral\Cache\ => src/Cache/src
Spiral\Debug\ => src/Debug/src
Spiral\Files\ => src/Files/src
Spiral\Queue\ => src/Queue/src
Spiral\Views\ => src/Views/src
Spiral\Config\ => src/Config/src
Spiral\DotEnv\ => src/Bridge/Dotenv/src
Spiral\Events\ => src/Events/src
Spiral\Logger\ => src/Logger/src
Spiral\Mailer\ => src/Mailer/src
Spiral\Models\ => src/Models/src
Spiral\Router\ => src/AnnotatedRoutes/src, src/Router/src
Spiral\SendIt\ => src/SendIt/src
Spiral\Console\ => src/Console/src
Spiral\Cookies\ => src/Cookies/src
Spiral\Filters\ => src/Filters/src
Spiral\Monolog\ => src/Bridge/Monolog/src
Spiral\Reactor\ => src/Reactor/src
Spiral\Session\ => src/Session/src
Spiral\Storage\ => src/Storage/src
Spiral\Streams\ => src/Streams/src
Spiral\Security\ => src/Security/src
Spiral\Stempler\ => src/Bridge/Stempler/src, src/Stempler/src
Spiral\Encrypter\ => src/Encrypter/src
Spiral\Prototype\ => src/Prototype/src
Spiral\Snapshots\ => src/Snapshots/src
Spiral\Telemetry\ => src/Telemetry/src
Spiral\Tokenizer\ => src/Tokenizer/src
Spiral\Exceptions\ => src/Exceptions/src
Spiral\Pagination\ => src/Pagination/src
Spiral\Scaffolder\ => src/Scaffolder/src
Spiral\Serializer\ => src/Serializer/src
Spiral\Translator\ => src/Translator/src
Spiral\Validation\ => src/Validation/src
Spiral\Broadcasting\ => src/Broadcasting/src
Spiral\Distribution\ => src/Distribution/src
Spiral\Interceptors\ => src/Interceptors/src

requires
cocur/slugify ^3.2 || ^4.0
codedungeon/php-cli-colors ^1.11
defuse/php-encryption ^2.2
doctrine/inflector ^1.4|^2.0
ext-json *
ext-mbstring *
ext-tokenizer *
league/flysystem ^2.3.1 || ^3.0
monolog/monolog ^2.9.2 || ^3.5
myclabs/deep-copy ^1.9
nette/php-generator ^4.1.7
nikic/php-parser ^5.4
php >=8.1
psr/container ^1.1|^2.0
psr/event-dispatcher ^1.0
psr/http-factory ^1.0
psr/http-factory-implementation ^1.0
psr/http-message ^1.0|^2.0
psr/http-server-middleware ^1.0
psr/log 1 - 3
psr/simple-cache 2 - 3
ramsey/uuid ^4.7
spiral/attributes ^3.1.8
spiral/composer-publish-plugin ^1.0
symfony/console ^6.4.30 || ^7.4 || ^8.0
symfony/finder ^6.4.30 || ^7.4 || ^8.0
symfony/mailer ^6.4.30 || ^7.4 || ^8.0
symfony/translation ^6.4.30 || ^7.4 || ^8.0
vlucas/phpdotenv ^5.4

requires (dev)
aws/aws-sdk-php ^3.338
buggregator/trap ^1.13.3
doctrine/annotations ^2.0.2
google/protobuf ^3.25|^4.29
guzzlehttp/psr7 ^1.7|^2.7
jetbrains/phpstorm-attributes ^1.2
league/flysystem-async-aws-s3 ^2.5 || ^3.29
league/flysystem-aws-s3-v3 ^2.5 || ^3.29
mikey179/vfsstream ^1.6.12
mockery/mockery ^1.6.12
phpunit/phpunit ^10.5.41
ramsey/collection ^1.3
rector/rector ~2.0.9
spiral/code-style ^2.2.2
spiral/nyholm-bridge ^1.3
spiral/testing ^2.12
spiral/validator ^1.5.4
symfony/stopwatch ^6.4.30 || ^7.4 || ^8.0
symplify/monorepo-builder ^10.3.3
vimeo/psalm ^6.0

conflicts
spiral/roadrunner-bridge <3.7
spiral/sapi-bridge <1.1
name     : spiral/cycle-bridge
descrip. : Cycle ORM integration package
keywords : 
versions : * v2.13.0
released : 2025-12-12, 6 months ago
type     : library
license  : MIT License (MIT) (OSI approved) https://spdx.org/licenses/MIT.html#licenseText
homepage : https://spiral.dev
source   : [git] https://github.com/spiral/cycle-bridge.git 24c6f83b208f8bda06af67b2e55f72bb1ad12833
dist     : [zip] https://api.github.com/repos/spiral/cycle-bridge/zipball/24c6f83b208f8bda06af67b2e55f72bb1ad12833 24c6f83b208f8bda06af67b2e55f72bb1ad12833
path     : /Users/gian_tiaga/Code/yoga-loka-spiral-2/vendor/spiral/cycle-bridge
names    : spiral/cycle-bridge

support
issues : https://github.com/spiral/framework/issues
source : https://github.com/spiral/cycle-bridge

autoload
psr-4
Spiral\Cycle\ => src

requires
cycle/annotated ^4.4.0
cycle/migrations ^4.2.6
cycle/orm ^2.12.0
cycle/schema-builder ^2.11.2
cycle/schema-migrations-generator ^2.3
cycle/schema-renderer ^1.4
doctrine/inflector ^1.4 || ^2.0.10
php >=8.1
psr/container ^1.1 || ^2.0.2
spiral/attributes ^2.10 || ^3.1.8
spiral/auth ^3.15
spiral/boot ^3.15
spiral/config ^3.15
spiral/console ^3.15
spiral/core ^3.15
spiral/data-grid-bridge ^3.0.1
spiral/filters ^3.15
spiral/prototype ^3.15
spiral/reactor ^3.15
spiral/scaffolder ^3.15
spiral/tokenizer ^3.15

requires (dev)
cycle/entity-behavior ^1.3.1
doctrine/collections ^2.2.2
illuminate/collections ^10.48 || ^11.0
infection/infection ^0.29.9
mockery/mockery ^1.6.12
phpunit/phpunit ^9.6.22
spiral-packages/database-seeder ^3.3
spiral/code-style ^2.2.2
spiral/framework ^3.15.1
spiral/nyholm-bridge ^1.3
spiral/testing ^2.9
spiral/validator ^1.5.4
vimeo/psalm ^6.4

In ShowCommand.php line 321:
                                                                               
  Package "spiral/auth" not found, try using --available (-a) to show all ava  
  ilable packages.                                                             
                                                                               

show [--all] [--locked] [-i|--installed] [-p|--platform] [-a|--available] [-s|--self] [-N|--name-only] [-P|--path] [-t|--tree] [-l|--latest] [-o|--outdated] [--ignore IGNORE] [-M|--major-only] [-m|--minor-only] [--patch-only] [-A|--sort-by-age] [-D|--direct] [--strict] [-f|--format FORMAT] [--no-dev] [--ignore-platform-req IGNORE-PLATFORM-REQ] [--ignore-platform-reqs] [--] [<package> [<version>]]


In ShowCommand.php line 321:
                                                                               
  Package "spiral/auth-http" not found, try using --available (-a) to show al  
  l available packages.                                                        
                                                                               

show [--all] [--locked] [-i|--installed] [-p|--platform] [-a|--available] [-s|--self] [-N|--name-only] [-P|--path] [-t|--tree] [-l|--latest] [-o|--outdated] [--ignore IGNORE] [-M|--major-only] [-m|--minor-only] [--patch-only] [-A|--sort-by-age] [-D|--direct] [--strict] [-f|--format FORMAT] [--no-dev] [--ignore-platform-req IGNORE-PLATFORM-REQ] [--ignore-platform-reqs] [--] [<package> [<version>]]


In ShowCommand.php line 321:
                                                                               
  Package "spiral/security" not found, try using --available (-a) to show all  
   available packages.                                                         
                                                                               

show [--all] [--locked] [-i|--installed] [-p|--platform] [-a|--available] [-s|--self] [-N|--name-only] [-P|--path] [-t|--tree] [-l|--latest] [-o|--outdated] [--ignore IGNORE] [-M|--major-only] [-m|--minor-only] [--patch-only] [-A|--sort-by-age] [-D|--direct] [--strict] [-f|--format FORMAT] [--no-dev] [--ignore-platform-req IGNORE-PLATFORM-REQ] [--ignore-platform-reqs] [--] [<package> [<version>]]


codex
Локальная проверка подтвердила: отдельные пакеты `spiral/auth` и `spiral/auth-http` в `composer show` не видны как установленные пакеты, они идут как заменяемые компоненты внутри `spiral/framework`. Сейчас сверю это с официальной документацией и Packagist, чтобы отделить ошибку текста от допустимой формулировки.
web search: 
web search: Spiral Framework 3.16 auth TokenStorageInterface ActorProviderInterface AuthMiddleware HeaderTransport docs
web search: 
web search: authentication spiral framework auth middleware header transport
web search: 
web search: site:spiral.dev/docs security authentication spiral php
web search: 
web search: https://spiral.dev/docs/security-authentication/current/en
web search: 
web search: https://spiral.dev/docs/security-authentication/current/en
web search: 
web search: https://packagist.org/packages/spiral/framework
web search: 
web search: https://packagist.org/packages/spiral/cycle-bridge
codex
Факты по Spiral такие: для версии 3.16 документация действительно описывает `TokenStorageInterface`, `ActorProviderInterface`, middleware и header-транспорт, но header по умолчанию не `Authorization: Bearer`, а `X-Auth-Token`. Это один из ключевых пунктов ревью.
exec
/bin/zsh -lc "nl -ba composer.json | sed -n '1,90p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	{
     2	    "name": "spiral/app",
     3	    "type": "project",
     4	    "license": "MIT",
     5	    "description": "Spiral Application installer",
     6	    "homepage": "https://spiral.dev",
     7	    "support": {
     8	        "issues": "https://github.com/spiral/app/issues",
     9	        "source": "https://github.com/spiral/app"
    10	    },
    11	    "require": {
    12	        "php": ">=8.5 <8.6",
    13	        "ext-intl": "*",
    14	        "ext-mbstring": "*",
    15	        "ext-pdo_pgsql": "*",
    16	        "ext-redis": "*",
    17	        "ext-sockets": "*",
    18	        "aws/aws-sdk-php": "^3.384",
    19	        "cuyz/valinor": "^2.4",
    20	        "gian-tiaga/spiral-api-errors": "dev-main",
    21	        "gian-tiaga/spiral-cqrs": "dev-main",
    22	        "gian-tiaga/spiral-openapi": "dev-main",
    23	        "illuminate/collections": "^13.0",
    24	        "illuminate/support": "^13.0",
    25	        "intervention/image": "^4",
    26	        "league/flysystem-aws-s3-v3": "^3.34",
    27	        "spiral-packages/league-event": "^1.0.1",
    28	        "spiral-packages/scheduler": "^2.1",
    29	        "spiral-packages/symfony-validator": "^1.5",
    30	        "spiral/cycle-bridge": "^2.11",
    31	        "spiral/data-grid-bridge": "^3.0.1",
    32	        "spiral/framework": "^3.15.7",
    33	        "spiral/http": "^3.15",
    34	        "spiral/nyholm-bridge": "^1.3",
    35	        "spiral/roadrunner-bridge": "^4.0",
    36	        "spiral/roadrunner-cli": "^2.5",
    37	        "spiral/sentry-bridge": "^2.3",
    38	        "spiral/temporal-bridge": "^3.3",
    39	        "spiral/translator": "^3.15",
    40	        "spiral/twig-bridge": "^2.0.1",
    41	        "swagger-api/swagger-ui": "^5.32"
    42	    },
    43	    "require-dev": {
    44	        "brianium/paratest": "7.22.4",
    45	        "friendsofphp/php-cs-fixer": "*",
    46	        "gian-tiaga/phpstan-strict-rules": "*",
    47	        "phpstan/phpstan": "^2.1.54",
    48	        "spiral/code-style": "^2.2",
    49	        "spiral/dumper": "^3.3.1",
    50	        "spiral/testing": "^3.0"
    51	    },
    52	    "autoload": {
    53	        "psr-4": {
    54	            "App\\": "app/src"
    55	        }
    56	    },
    57	    "autoload-dev": {
    58	        "psr-4": {
    59	            "Tests\\": "tests"
    60	        }
    61	    },
    62	    "repositories": [
    63	        {
    64	            "type": "path",
    65	            "url": "packages/*",
    66	            "options": {
    67	                "symlink": true
    68	            }
    69	        }
    70	    ],
    71	    "extra": {
    72	        "publish-cmd": "php app.php publish"
    73	    },
    74	    "config": {
    75	        "sort-packages": true,
    76	        "allow-plugins": {
    77	            "php-http/discovery": true,
    78	            "spiral/composer-publish-plugin": true
    79	        }
    80	    },
    81	    "scripts": {
    82	        "phpstan": "phpstan analyse --debug",
    83	        "cs": "php-cs-fixer fix --dry-run --diff -v",
    84	        "cs:fix": "php-cs-fixer fix -v",
    85	        "test": "phpunit",
    86	        "test:parallel": "vendor/bin/paratest --processes \"${TEST_PARALLEL_PROCESSES:-4}\"",
    87	        "test-coverage": "echo '[coverage] драйвер=PCOV clover=runtime/coverage/clover.xml порог=100%' && mkdir -p runtime/coverage && XDEBUG_MODE=off php -d pcov.enabled=1 -d pcov.directory=app/src vendor/bin/paratest --passthru-php=\"'-d' 'pcov.enabled=1' '-d' 'pcov.directory=app/src'\" --processes \"${TEST_PARALLEL_PROCESSES:-4}\" --testsuite Unit,Kernel,Feature --coverage-clover runtime/coverage/clover.xml && php docker/test/assert-coverage.php runtime/coverage/clover.xml 100",
    88	        "qa": [
    89	            "@cs",
    90	            "@phpstan",

exec
/bin/zsh -lc "rg -n '\"name\": \"spiral/(framework|cycle-bridge)\"|\"version\": \"(3\\.16\\.2|v2\\.13\\.0)\"|\"ext-redis\"|\"php\"|\"spiral/framework\"|\"spiral/cycle-bridge\"' composer.lock composer.json" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
composer.json:12:        "php": ">=8.5 <8.6",
composer.json:16:        "ext-redis": "*",
composer.json:30:        "spiral/cycle-bridge": "^2.11",
composer.json:32:        "spiral/framework": "^3.15.7",
composer.lock:24:                "php": ">=5.5"
composer.lock:86:                "php": ">=8.1",
composer.lock:175:                "php": "^8.2"
composer.lock:236:                "php": "^8.0"
composer.lock:293:                "php": "^8.1"
composer.lock:362:                "php": ">=5.3"
composer.lock:429:                "php": "~8.0.0 || ~8.1.0 || ~8.2.0 || ~8.3.0 || ~8.4.0 || ~8.5.0"
composer.lock:527:                "php"
composer.lock:550:                "php": "^5.3.2 || ^7.0 || ^8.0"
composer.lock:627:                "php": "~8.2.0 || ~8.3.0 || ~8.4.0 || ~8.5.0"
composer.lock:708:                "php": ">=8.1",
composer.lock:779:                "php": ">=8.1",
composer.lock:873:                "php": ">=8.1",
composer.lock:920:                "php": ">=8.1",
composer.lock:1007:                "php": ">=8.0",
composer.lock:1076:                "php": ">=8.1"
composer.lock:1084:                "spiral/framework": "^3.14.8",
composer.lock:1128:                "php": ">=8.1",
composer.lock:1171:                "php": ">=5.6.0"
composer.lock:1238:                "php": "^7.2 || ^8.0",
composer.lock:1250:                "php": "PHP 8.0 or higher comes with attributes, a native replacement for annotations"
composer.lock:1313:                "php": "^7.2 || ^8.0"
composer.lock:1361:                "php",
composer.lock:1403:                "php": "^8.4"
composer.lock:1472:                "php": "^8.1"
composer.lock:1512:                "php"
composer.lock:1549:                "php": "^8.2|^8.3|^8.4|^8.5"
composer.lock:1614:                "php": ">=8.1",
composer.lock:1675:                "php": ">=8.5 <8.6",
composer.lock:1679:                "spiral/framework": "^3.16"
composer.lock:1730:                "php": ">=8.5 <8.6",
composer.lock:1733:                "spiral/framework": "^3.16"
composer.lock:1785:                "php": ">=8.5 <8.6",
composer.lock:1787:                "spiral/framework": "^3.16",
composer.lock:1846:                "php": "^8.1"
composer.lock:1904:                "php": ">=8.1.0"
composer.lock:1948:                "php": "^7.2.5 || ^8.0",
composer.lock:2010:                "php": ">=7.1.0"
composer.lock:2057:                "php": "^7.2.5 || ^8.0",
composer.lock:2180:                "php": "^7.2.5 || ^8.0"
composer.lock:2263:                "php": "^7.2.5 || ^8.0",
composer.lock:2383:                "php": "^8.3",
composer.lock:2440:                "php": "^8.3"
composer.lock:2486:                "php": "^8.3",
composer.lock:2534:                "php": "^8.2"
composer.lock:2582:                "php": "^8.3"
composer.lock:2641:                "php": "^8.3",
composer.lock:2710:                "php": ">=8.1"
composer.lock:2769:                "php": ">=8.1.0"
composer.lock:2842:                "php": "^8.3"
composer.lock:2912:                "php": "^8.3"
composer.lock:2987:                "php": "^7.4|^8.0"
composer.lock:3046:                "php": ">=7.2.0",
composer.lock:3107:                "php": "^8.0.2"
composer.lock:3191:                "php": "^8.0.2"
composer.lock:3246:                "php": "^8.0.2"
composer.lock:3293:                "php": "^7.4 || ^8.0"
composer.lock:3348:                "php": ">=8.1",
composer.lock:3451:                "php": "^7.2.5 || ^8.0",
composer.lock:3517:                "php": "^7.1 || ^8.0"
composer.lock:3579:                "php": "^8.1",
composer.lock:3683:                "php": "8.1 - 8.5"
composer.lock:3732:                "php",
composer.lock:3756:                "php": "8.2 - 8.5"
composer.lock:3850:                "php": ">=7.4"
composer.lock:3882:                "php"
composer.lock:3905:                "php": ">=7.2",
composer.lock:3983:                "php": ">= 7"
composer.lock:4034:                "php": "^8.1",
composer.lock:4099:                "php": "^7.1 || ^8.0"
composer.lock:4177:                "php": "^7.1 || ^8.0",
composer.lock:4235:                "php": "^7.2 || ^8.0",
composer.lock:4303:                "php": "^7.1 || ^8.0"
composer.lock:4355:                "php": "^7.2.5 || ^8.0"
composer.lock:4396:                "php",
composer.lock:4430:                "php": "^7.4 || ^8.0"
composer.lock:4477:                "php": ">=8.0.0"
composer.lock:4526:                "php": "^7.0 || ^8.0"
composer.lock:4574:                "php": ">=7.4.0"
composer.lock:4627:                "php": ">=7.2.0"
composer.lock:4677:                "php": "^7.0 || ^8.0",
composer.lock:4729:                "php": ">=7.1",
composer.lock:4784:                "php": "^7.2 || ^8.0"
composer.lock:4837:                "php": ">=7.0",
composer.lock:4893:                "php": ">=7.0",
composer.lock:4950:                "php": ">=8.0.0"
composer.lock:5000:                "php": ">=8.0.0"
composer.lock:5051:                "php": ">=5.6"
composer.lock:5095:                "php": "^8.1"
composer.lock:5172:                "php": "^8.0",
composer.lock:5250:                "php": ">=8.1",
composer.lock:5308:                "php": ">=8.1",
composer.lock:5387:                "php": "^8.1",
composer.lock:5467:                "php": "^8.1"
composer.lock:5535:                "php": "^8.0",
composer.lock:5591:                "php": "^7.2|^8.0",
composer.lock:5682:                "php": "^8.1",
composer.lock:5739:                "php": ">=8.1",
composer.lock:5752:                "spiral/framework": "^3.14",
composer.lock:5813:                "php": "^8.1",
composer.lock:5825:                "spiral/framework": "^3.1",
composer.lock:5882:                "php": ">=8.1",
composer.lock:5965:                "php": ">=7.1"
composer.lock:5998:            "name": "spiral/cycle-bridge",
composer.lock:5999:            "version": "v2.13.0",
composer.lock:6019:                "php": ">=8.1",
composer.lock:6043:                "spiral/framework": "^3.15.1",
composer.lock:6102:                "php": ">=8.1",
composer.lock:6164:                "php": ">=8.1",
composer.lock:6205:            "name": "spiral/framework",
composer.lock:6206:            "version": "3.16.2",
composer.lock:6231:                "php": ">=8.1",
composer.lock:6448:                "php": ">=8.1",
composer.lock:6536:                "php": ">=8.1",
composer.lock:6618:                "php": ">=8.1",
composer.lock:6714:                "php": ">=8.1",
composer.lock:6737:                "spiral/framework": "^3.15.6",
composer.lock:6807:                "php": ">=8.1",
composer.lock:6877:                "php": ">=8.1",
composer.lock:6956:                "php": ">=8.1",
composer.lock:7046:                "php": ">=8.1",
composer.lock:7128:                "php": ">=8.1",
composer.lock:7211:                "php": ">=8.1",
composer.lock:7293:                "php": ">=8.1",
composer.lock:7372:                "php": ">=8.1",
composer.lock:7454:                "php": ">=8.1",
composer.lock:7527:                "php": ">=8.1",
composer.lock:7539:                "spiral/framework": "^3.14",
composer.lock:7581:                "php",
composer.lock:7616:                "php": "^8.1",
composer.lock:7618:                "spiral/framework": "^3.0",
composer.lock:7731:                "php": ">=8.2",
composer.lock:7741:                "ext-redis": "<6.1",
composer.lock:7835:                "php": ">=8.1",
composer.lock:7915:                "php": ">=8.4",
composer.lock:7992:                "php": ">=8.4",
composer.lock:8082:                "php": ">=8.1"
composer.lock:8153:                "php": ">=8.4",
composer.lock:8238:                "php": ">=8.1",
composer.lock:8318:                "php": ">=8.4",
composer.lock:8388:                "php": ">=8.4"
composer.lock:8456:                "php": ">=8.4",
composer.lock:8552:                "php": ">=8.1"
composer.lock:8634:                "php": ">=8.1",
composer.lock:8716:                "php": ">=8.4",
composer.lock:8795:                "php": ">=8.4",
composer.lock:8881:                "php": ">=8.4",
composer.lock:8952:                "php": ">=7.2"
composer.lock:9035:                "php": ">=7.2"
composer.lock:9117:                "php": ">=7.2",
composer.lock:9204:                "php": ">=7.2"
composer.lock:9290:                "php": ">=7.2"
composer.lock:9374:                "php": ">=7.2"
composer.lock:9458:                "php": ">=7.2"
composer.lock:9538:                "php": ">=7.2"
composer.lock:9618:                "php": ">=7.2"
composer.lock:9698:                "php": ">=7.2"
composer.lock:9778:                "php": ">=7.2"
composer.lock:9858:                "php": ">=8.4"
composer.lock:9923:                "php": ">=7.2.5",
composer.lock:10012:                "php": ">=8.1",
composer.lock:10099:                "php": ">=8.4",
composer.lock:10189:                "php": ">=8.4",
composer.lock:10282:                "php": ">=8.1"
composer.lock:10364:                "php": ">=8.2",
composer.lock:10468:                "php": ">=8.4"
composer.lock:10548:                "php": ">=8.4",
composer.lock:10631:                "php": ">=8.1",
composer.lock:10729:                "php": ">=8.1.0",
composer.lock:10811:                "php": "^7.2.5 || ^8.0",
composer.lock:10893:                "php": ">=7.1.0"
composer.lock:10922:                "php"
composer.lock:10967:                "php": "7.4.* || 8.0 - 8.5"
composer.lock:11043:                "php": "~8.4.0 || ~8.5.0",
composer.lock:11135:                "php": ">=8.1",
composer.lock:11229:                "php": "^7.2 || ^8.0"
composer.lock:11284:                "php": ">=8.5 <8.6",
composer.lock:11339:                "php": "^7.4|^8.0"
composer.lock:11392:                "php": ">=7.3"
composer.lock:11478:                "php": "^7.2 || ^8.0"
composer.lock:11540:                "php": "^7.2 || ^8.0"
composer.lock:11593:                "php": "^7.4 || ^8.0"
composer.lock:11638:                "php": "^7.4|^8.0"
composer.lock:11701:                "php": ">=8.4",
composer.lock:11786:                "php": ">=8.4"
composer.lock:11859:                "php": ">=8.4"
composer.lock:11935:                "php": ">=8.4"
composer.lock:12007:                "php": ">=8.4"
composer.lock:12088:                "php": ">=8.4.1",
composer.lock:12170:                "php": ">=8.4"
composer.lock:12241:                "php": ">=8.4",
composer.lock:12332:                "php": ">=8.4"
composer.lock:12401:                "php": ">=8.4"
composer.lock:12480:                "php": ">=8.4"
composer.lock:12557:                "php": ">=8.4",
composer.lock:12646:                "php": ">=8.4"
composer.lock:12715:                "php": ">=8.4",
composer.lock:12790:                "php": ">=8.4"
composer.lock:12859:                "php": ">=8.4",
composer.lock:12929:                "php": ">=8.4"
composer.lock:12997:                "php": ">=8.4"
composer.lock:13073:                "php": ">=8.4"
composer.lock:13142:                "php": ">=8.4"
composer.lock:13208:                "php": ">=8.0",
composer.lock:13265:                "php": ">= 8.1",
composer.lock:13340:                "php": ">=8.4",
composer.lock:13364:                "spiral/framework": "^3.16.0",
composer.lock:13447:                "php": "^7.4 || ^8.0"
composer.lock:13498:                "php": ">=8.2",
composer.lock:13588:                "php": "^8.1"
composer.lock:13635:                "php": "7.4 - 8.5"
composer.lock:13707:        "php": ">=8.5 <8.6",
composer.lock:13711:        "ext-redis": "*",

exec
/bin/zsh -lc "nl -ba app/src/Application/Kernel.php | sed -n '1,180p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
nl: app/src/Application/Kernel.php: No such file or directory

exec
/bin/zsh -lc 'find app/config -maxdepth 2 -type f | sort | xargs rg -n "Auth|auth|Mailer|SendIt|Redis|redis|session|security|middlewareGroups|RoutesBootloader|HttpAuthBootloader|AUTH_TOKEN"' in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
app/config/cache.php:5:use App\Shared\Infrastructure\Cache\RedisCacheStorage;
app/config/cache.php:50:        'redis' => [
app/config/cache.php:51:            'type' => RedisCacheStorage::class,
app/config/cache.php:52:            'dsn' => \env('REDIS_DSN', 'redis://redis:6379/0'),
app/config/session.php:10: * @link https://spiral.dev/docs/basics-session
app/config/session.php:20:            'storage' => \env('SESSION_CACHE_STORAGE', 'redis'),
app/config/session.php:22:            'prefix' => \env('SESSION_CACHE_PREFIX', 'session:'),

exec
/bin/zsh -lc "rg --files app/src | rg 'Kernel|Bootloader|Routes|Middleware' | sort" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
app/src/Modules/Media/Infrastructure/Bootloader/MediaBootloader.php
app/src/Modules/Outbox/Infrastructure/Bootloader/OutboxBootloader.php
app/src/Modules/Outbox/Infrastructure/Bootloader/OutboxConsoleBootloader.php
app/src/Shared/Infrastructure/Framework/Bootloader/AppBootloader.php
app/src/Shared/Infrastructure/Framework/Bootloader/ConfigBootloader.php
app/src/Shared/Infrastructure/Framework/Bootloader/ExceptionHandlerBootloader.php
app/src/Shared/Infrastructure/Framework/Bootloader/LoggingBootloader.php
app/src/Shared/Infrastructure/Framework/Bootloader/OpenApiBootloader.php
app/src/Shared/Infrastructure/Framework/Bootloader/RoutesBootloader.php
app/src/Shared/Infrastructure/Framework/Kernel.php
app/src/Shared/Infrastructure/Framework/Middleware/LocaleMiddleware.php

exec
/bin/zsh -lc "nl -ba vendor/spiral/framework/src/Framework/Bootloader/Auth/HttpAuthBootloader.php | sed -n '1,240p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace Spiral\Bootloader\Auth;
     6	
     7	use Psr\Http\Message\ServerRequestInterface;
     8	use Spiral\Auth\AuthContextInterface;
     9	use Spiral\Auth\Config\AuthConfig;
    10	use Spiral\Auth\HttpTransportInterface;
    11	use Spiral\Auth\Middleware\AuthMiddleware;
    12	use Spiral\Auth\Middleware\AuthTransportMiddleware;
    13	use Spiral\Auth\Middleware\AuthTransportWithStorageMiddleware;
    14	use Spiral\Auth\Session\TokenStorage as SessionTokenStorage;
    15	use Spiral\Auth\TokenStorageInterface;
    16	use Spiral\Auth\TokenStorageProvider;
    17	use Spiral\Auth\TokenStorageProviderInterface;
    18	use Spiral\Auth\Transport\CookieTransport;
    19	use Spiral\Auth\Transport\HeaderTransport;
    20	use Spiral\Auth\TransportRegistry;
    21	use Spiral\Boot\Bootloader\Bootloader;
    22	use Spiral\Boot\EnvironmentInterface;
    23	use Spiral\Bootloader\Http\Exception\ContextualObjectNotFoundException;
    24	use Spiral\Bootloader\Http\Exception\InvalidRequestScopeException;
    25	use Spiral\Bootloader\Http\HttpBootloader;
    26	use Spiral\Config\ConfiguratorInterface;
    27	use Spiral\Config\Patch\Append;
    28	use Spiral\Core\Attribute\Singleton;
    29	use Spiral\Core\BinderInterface;
    30	use Spiral\Core\Config\Proxy;
    31	use Spiral\Core\Container\Autowire;
    32	use Spiral\Core\FactoryInterface;
    33	use Spiral\Framework\Spiral;
    34	use Spiral\Http\Config\HttpConfig;
    35	
    36	/**
    37	 * Enables Auth middleware and http transports to read and write tokens in PSR-7 request/response.
    38	 */
    39	#[Singleton]
    40	final class HttpAuthBootloader extends Bootloader
    41	{
    42	    public function __construct(
    43	        private readonly ConfiguratorInterface $config,
    44	    ) {}
    45	
    46	    public function defineDependencies(): array
    47	    {
    48	        return [
    49	            AuthBootloader::class,
    50	            HttpBootloader::class,
    51	        ];
    52	    }
    53	
    54	    public function defineBindings(): array
    55	    {
    56	        return [
    57	            AuthContextInterface::class => new Proxy(AuthContextInterface::class, false),
    58	        ];
    59	    }
    60	
    61	    public function defineSingletons(): array
    62	    {
    63	        return [
    64	            TransportRegistry::class => [self::class, 'transportRegistry'],
    65	            TokenStorageProviderInterface::class => TokenStorageProvider::class,
    66	            // Default token storage outside of HTTP scope
    67	            TokenStorageInterface::class => static fn(TokenStorageProviderInterface $provider): TokenStorageInterface
    68	                => $provider->getStorage(),
    69	        ];
    70	    }
    71	
    72	    public function init(EnvironmentInterface $env, BinderInterface $binder): void
    73	    {
    74	        $httpBinder = $binder->getBinder(Spiral::Http);
    75	        // Token storage from request attribute in HTTP scope
    76	        $httpBinder->bindSingleton(TokenStorageInterface::class, [self::class, 'getTokenStorage']);
    77	        $httpBinder->bind(
    78	            AuthContextInterface::class,
    79	            static fn(?ServerRequestInterface $request): AuthContextInterface =>
    80	                ($request ?? throw new InvalidRequestScopeException(AuthContextInterface::class))
    81	                    ->getAttribute(AuthMiddleware::ATTRIBUTE) ?? throw new ContextualObjectNotFoundException(
    82	                        AuthContextInterface::class,
    83	                        AuthMiddleware::ATTRIBUTE,
    84	                    ),
    85	        );
    86	        // Bind middleware to `http` scope
    87	        $httpBinder->bind(AuthMiddleware::class, AuthMiddleware::class);
    88	        $httpBinder->bind(AuthTransportMiddleware::class, AuthTransportMiddleware::class);
    89	        $httpBinder->bind(AuthTransportWithStorageMiddleware::class, AuthTransportWithStorageMiddleware::class);
    90	
    91	        $this->config->setDefaults(
    92	            AuthConfig::CONFIG,
    93	            [
    94	                'defaultTransport' => $env->get('AUTH_TOKEN_TRANSPORT', 'cookie'),
    95	                'defaultStorage' => $env->get('AUTH_TOKEN_STORAGE', 'session'),
    96	                'transports' => [
    97	                    'cookie' => $this->createDefaultCookieTransport(),
    98	                    'header' => new HeaderTransport(header: 'X-Auth-Token'),
    99	                ],
   100	                'storages' => [
   101	                    'session' => SessionTokenStorage::class,
   102	                ],
   103	            ],
   104	        );
   105	    }
   106	
   107	    /**
   108	     * Add new Http token transport.
   109	     *
   110	     * @param non-empty-string $name
   111	     * @param Autowire|HttpTransportInterface|class-string<HttpTransportInterface> $transport
   112	     */
   113	    public function addTransport(string $name, Autowire|HttpTransportInterface|string $transport): void
   114	    {
   115	        $this->config->modify(AuthConfig::CONFIG, new Append('transports', $name, $transport));
   116	    }
   117	
   118	    /**
   119	     * Add new Http token storage.
   120	     *
   121	     * @param non-empty-string $name
   122	     * @param Autowire|TokenStorageInterface|class-string<TokenStorageInterface> $storage
   123	     */
   124	    public function addTokenStorage(string $name, Autowire|TokenStorageInterface|string $storage): void
   125	    {
   126	        $this->config->modify(AuthConfig::CONFIG, new Append('storages', $name, $storage));
   127	    }
   128	
   129	    /**
   130	     * Creates default cookie transport when "transports" section is empty.
   131	     */
   132	    private function createDefaultCookieTransport(): CookieTransport
   133	    {
   134	        $config = $this->config->getConfig(HttpConfig::CONFIG);
   135	
   136	        return new CookieTransport('token', $config['basePath'] ?? '/');
   137	    }
   138	
   139	    /**
   140	     * @noRector RemoveUnusedPrivateMethodRector
   141	     */
   142	    private function transportRegistry(AuthConfig $config, FactoryInterface $factory): TransportRegistry
   143	    {
   144	        $registry = new TransportRegistry();
   145	        $registry->setDefaultTransport($config->getDefaultTransport());
   146	
   147	        foreach ($config->getTransports() as $name => $transport) {
   148	            if ($transport instanceof Autowire) {
   149	                $transport = $transport->resolve($factory);
   150	            }
   151	
   152	            $registry->setTransport($name, $transport);
   153	        }
   154	
   155	        return $registry;
   156	    }
   157	
   158	    /**
   159	     * Get default token storage from provider
   160	     */
   161	    private function getTokenStorage(
   162	        TokenStorageProviderInterface $provider,
   163	        ServerRequestInterface $request,
   164	    ): TokenStorageInterface {
   165	        return $request->getAttribute(AuthMiddleware::TOKEN_STORAGE_ATTRIBUTE) ?? $provider->getStorage();
   166	    }
   167	}

exec
/bin/zsh -lc 'rg -n "HttpAuthBootloader|AuthBootloader|AuthMiddleware|AuthTransport|middlewareGroups|Route|Group|interceptor|Mailer|SendIt|SendItBootloader|MailerBootloader|SecurityActor" app/src app/config' in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
app/config/queue.php:152:     * @link https://spiral.dev/docs/queue-interceptors
app/config/queue.php:154:    'interceptors' => [
app/config/queue.php:158:        // RetryException и статус-interceptor спутает «оставить на повтор» с
app/config/queue.php:159:        // «окончательно failed». Перестановка interceptor-ов местами или удаление
app/src/Modules/System/Presentation/Http/Controller/HealthController.php:11:use Spiral\Router\Annotation\Route;
app/src/Modules/System/Presentation/Http/Controller/HealthController.php:22:    #[Route(route: '/api/v1/health', name: 'api.v1.health', methods: ['GET'], group: 'api')]
app/src/Modules/System/Presentation/Http/Controller/SwaggerController.php:12:use Spiral\Router\Annotation\Route;
app/src/Modules/System/Presentation/Http/Controller/SwaggerController.php:26:    #[Route(route: '/api/docs', name: 'api.docs', methods: ['GET'], group: 'api')]
app/src/Modules/System/Presentation/Http/Controller/SwaggerController.php:37:    #[Route(route: '/api/docs/openapi.yml', name: 'api.docs.openapi', methods: ['GET'], group: 'api')]
app/src/Modules/Outbox/Infrastructure/Relay/OutboxRelay.php:123:            // поэтому interceptor успевает увести нашу же Entity из publishing (handled/failed/
app/src/Modules/Outbox/Infrastructure/Relay/OutboxRelay.php:147:            // Тот же sync-сценарий, что и на успешной ветке: interceptor мог уже зафиксировать
app/src/Shared/Infrastructure/Framework/Kernel.php:27:use Spiral\SendIt\Bootloader\MailerBootloader;
app/src/Shared/Infrastructure/Framework/Kernel.php:87:            Framework\Http\RouterBootloader::class,
app/src/Shared/Infrastructure/Framework/Kernel.php:139:            MailerBootloader::class,
app/src/Shared/Infrastructure/Framework/Kernel.php:172:            Bootloader\RoutesBootloader::class,
app/src/Shared/Infrastructure/Framework/Bootloader/RoutesBootloader.php:8:use Spiral\Bootloader\Http\RoutesBootloader as BaseRoutesBootloader;
app/src/Shared/Infrastructure/Framework/Bootloader/RoutesBootloader.php:16:use Spiral\Router\Bootloader\AnnotatedRoutesBootloader;
app/src/Shared/Infrastructure/Framework/Bootloader/RoutesBootloader.php:18:use GianTiaga\SpiralApiErrors\Middleware\RouteNotFoundMiddleware;
app/src/Shared/Infrastructure/Framework/Bootloader/RoutesBootloader.php:25:final class RoutesBootloader extends BaseRoutesBootloader
app/src/Shared/Infrastructure/Framework/Bootloader/RoutesBootloader.php:29:    protected const array DEPENDENCIES = [AnnotatedRoutesBootloader::class];
app/src/Shared/Infrastructure/Framework/Bootloader/RoutesBootloader.php:37:            RouteNotFoundMiddleware::class,
app/src/Shared/Infrastructure/Framework/Bootloader/RoutesBootloader.php:45:    protected function middlewareGroups(): array
app/src/Shared/Infrastructure/Framework/Bootloader/AppBootloader.php:16: * @link https://spiral.dev/docs/http-interceptors
app/src/Modules/Outbox/Infrastructure/Queue/OutboxQueueStatusInterceptor.php:39:            $this->logger->debug(message: 'Outbox interceptor пропустил обычную задачу без outboxId.', context: [
app/src/Modules/Outbox/Infrastructure/Queue/OutboxQueueStatusInterceptor.php:49:            $this->logger->warning(message: 'Outbox interceptor не нашёл событие для задачи и не запустил Job.', context: [
app/src/Modules/Outbox/Infrastructure/Queue/OutboxQueueStatusInterceptor.php:58:            $this->logger->debug(message: 'Outbox interceptor пропустил дубль уже финального события.', context: [
app/src/Modules/Outbox/Infrastructure/Queue/OutboxQueueStatusInterceptor.php:84:        $this->logger->debug(message: 'Outbox interceptor поставил handled.', context: [
app/src/Modules/Outbox/Infrastructure/Queue/OutboxQueueStatusInterceptor.php:111:            $this->logger->warning(message: 'Outbox interceptor обнаружил несовпадение outboxId в headers и payload.', context: [
app/src/Modules/Outbox/Infrastructure/Queue/OutboxQueueStatusInterceptor.php:118:            throw new \UnexpectedValueException('Outbox interceptor получил разные outbox-данные в headers и payload.');
app/src/Modules/Outbox/Infrastructure/Queue/OutboxQueueStatusInterceptor.php:164:            $this->logger->warning(message: 'Outbox interceptor получил payload-массив без обоих outbox-ключей и не считает его outbox-задачей.', context: [
app/src/Modules/Outbox/Infrastructure/Queue/OutboxQueueStatusInterceptor.php:173:            $this->logger->warning(message: 'Outbox interceptor получил payload-массив с нестроковыми outbox-ключами и не считает его outbox-задачей.', context: [
app/src/Modules/Outbox/Infrastructure/Queue/OutboxQueueStatusInterceptor.php:226:            $this->logger->error(message: 'Outbox interceptor окончательно перевёл событие в failed.', context: $jobFailureContext);
app/src/Modules/Outbox/Infrastructure/Queue/OutboxQueueStatusInterceptor.php:231:        $this->logger->debug(message: 'Outbox interceptor зафиксировал ошибку Job и оставил событие на повтор.', context: $jobFailureContext);
app/src/Modules/Outbox/Infrastructure/Queue/OutboxQueueStatusInterceptor.php:247:        $this->logger->debug(message: 'Outbox interceptor не перезаписал ставшее финальным событие.', context: [
app/src/Modules/Outbox/README.md:51:Job упала, interceptor записывает ошибку и переводит событие в failed или
app/src/Modules/Outbox/README.md:187:В `interceptors.consume` должен быть `OutboxQueueStatusInterceptor`:
app/src/Modules/Outbox/README.md:199:Порядок interceptor-ов критичен для корректности классификации ошибок и не должен
app/src/Modules/Outbox/README.md:202:`RetryException`, и статус-interceptor отличает «оставить на повтор» от
app/src/Modules/Outbox/README.md:203:«окончательно failed». Если переставить эти два interceptor-а местами или убрать
app/src/Modules/Outbox/README.md:450:финального события будет пропущена interceptor-ом.
app/src/Shared/Infrastructure/Configuration/Cycle/CycleSchemaGeneratorGroupConfig.php:7:final readonly class CycleSchemaGeneratorGroupConfig
app/src/Shared/Infrastructure/Configuration/Cycle/CycleSchemaConfig.php:11:     * @param list<class-string>|array<string, CycleSchemaGeneratorGroupConfig>|null $generators
app/src/Shared/Infrastructure/Configuration/Queue/QueueConfig.php:31:        public QueueInterceptorsConfig $interceptors,
app/src/Shared/Infrastructure/Configuration/Mailer/MailerConfig.php:5:namespace App\Shared\Infrastructure\Configuration\Mailer;
app/src/Shared/Infrastructure/Configuration/Mailer/MailerConfig.php:9:final readonly class MailerConfig implements TypedConfig

exec
/bin/zsh -lc "rg -n \"spiral/auth|spiral/auth-http|spiral/security|replace|replaces\" vendor/spiral/framework/composer.json composer.lock | sed -n '1,160p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
vendor/spiral/framework/composer.json:215:    "replace": {
vendor/spiral/framework/composer.json:217:        "spiral/auth": "self.version",
vendor/spiral/framework/composer.json:218:        "spiral/auth-http": "self.version",
vendor/spiral/framework/composer.json:248:        "spiral/security": "self.version",
composer.lock:1250:                "php": "PHP 8.0 or higher comes with attributes, a native replacement for annotations"
composer.lock:1551:            "replace": {
composer.lock:2648:            "replace": {
composer.lock:2771:            "replace": {
composer.lock:2991:                "jean85/composer-provided-replaced-stub-package": "^1.0",
composer.lock:5175:            "replace": {
composer.lock:6022:                "spiral/auth": "^3.15",
composer.lock:6253:            "replace": {
composer.lock:6255:                "spiral/auth": "self.version",
composer.lock:6256:                "spiral/auth-http": "self.version",
composer.lock:6286:                "spiral/security": "self.version",
composer.lock:8907:            "description": "Provides an improved replacement for the array_replace PHP function",
composer.lock:11341:            "replace": {
composer.lock:11595:            "replace": {
composer.lock:13342:                "spiral/auth": "^3.16",
composer.lock:13343:                "spiral/auth-http": "^3.16",
composer.lock:13352:                "spiral/security": "^3.16",

exec
/bin/zsh -lc "nl -ba vendor/spiral/framework/src/Framework/Bootloader/Auth/AuthBootloader.php | sed -n '1,220p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace Spiral\Bootloader\Auth;
     6	
     7	use Spiral\Auth\ActorProviderInterface;
     8	use Spiral\Auth\AuthScope;
     9	use Spiral\Auth\Exception\AuthException;
    10	use Spiral\Auth\TokenInterface;
    11	use Spiral\Boot\Bootloader\Bootloader;
    12	use Spiral\Core\Attribute\Singleton;
    13	use Spiral\Core\Container\Autowire;
    14	use Spiral\Core\FactoryInterface;
    15	
    16	/**
    17	 * Manages the set of actor providers.
    18	 */
    19	#[Singleton]
    20	final class AuthBootloader extends Bootloader implements ActorProviderInterface
    21	{
    22	    protected const SINGLETONS = [
    23	        AuthScope::class => AuthScope::class,
    24	        ActorProviderInterface::class => self::class,
    25	    ];
    26	
    27	    /** @var array<int, ActorProviderInterface|Autowire|string> */
    28	    private array $actorProvider = [];
    29	
    30	    public function __construct(
    31	        private readonly FactoryInterface $factory,
    32	    ) {}
    33	
    34	    /**
    35	     * Find actor by first matching actor provider.
    36	     */
    37	    public function getActor(TokenInterface $token): ?object
    38	    {
    39	        foreach ($this->getProviders() as $provider) {
    40	            if (!$provider instanceof ActorProviderInterface) {
    41	                throw new AuthException(
    42	                    \sprintf(
    43	                        'Expected `ActorProviderInterface`, got `%s`',
    44	                        $provider::class,
    45	                    ),
    46	                );
    47	            }
    48	
    49	            $actor = $provider->getActor($token);
    50	            if ($actor !== null) {
    51	                return $actor;
    52	            }
    53	        }
    54	
    55	        if ($this->actorProvider === []) {
    56	            throw new AuthException('No actor provider');
    57	        }
    58	
    59	        return null;
    60	    }
    61	
    62	    /**
    63	     * Register new actor provider.
    64	     */
    65	    public function addActorProvider(ActorProviderInterface|Autowire|string $actorProvider): void
    66	    {
    67	        $this->actorProvider[] = $actorProvider;
    68	    }
    69	
    70	    /**
    71	     * @return \Generator<array-key, ActorProviderInterface>
    72	     */
    73	    private function getProviders(): \Generator
    74	    {
    75	        foreach ($this->actorProvider as $provider) {
    76	            if ($provider instanceof Autowire) {
    77	                yield $provider->resolve($this->factory);
    78	                continue;
    79	            }
    80	
    81	            if (\is_object($provider)) {
    82	                yield $provider;
    83	                continue;
    84	            }
    85	
    86	            yield $this->factory->make($provider);
    87	        }
    88	    }
    89	}

exec
/bin/zsh -lc "nl -ba app/src/Shared/Infrastructure/Framework/Bootloader/RoutesBootloader.php | sed -n '1,120p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc "nl -ba app/src/Shared/Infrastructure/Framework/Kernel.php | sed -n '1,220p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Shared\Infrastructure\Framework\Bootloader;
     6	
     7	use App\Shared\Infrastructure\Framework\Middleware\LocaleMiddleware;
     8	use Spiral\Bootloader\Http\RoutesBootloader as BaseRoutesBootloader;
     9	use Spiral\Cookies\Middleware\CookiesMiddleware;
    10	use Spiral\Csrf\Middleware\CsrfMiddleware;
    11	use Spiral\Debug\Middleware\DumperMiddleware;
    12	use Spiral\Debug\StateCollector\HttpCollector;
    13	use Spiral\Filter\ValidationHandlerMiddleware;
    14	use Spiral\Http\Middleware\ErrorHandlerMiddleware;
    15	use Spiral\Http\Middleware\JsonPayloadMiddleware;
    16	use Spiral\Router\Bootloader\AnnotatedRoutesBootloader;
    17	use Spiral\Session\Middleware\SessionMiddleware;
    18	use GianTiaga\SpiralApiErrors\Middleware\RouteNotFoundMiddleware;
    19	
    20	/**
    21	 * Настраивает маршруты и middleware приложения.
    22	 *
    23	 * @link https://spiral.dev/docs/http-routing
    24	 */
    25	final class RoutesBootloader extends BaseRoutesBootloader
    26	{
    27	    public const string GROUP_API = 'api';
    28	    public const string GROUP_WEB = 'web';
    29	    protected const array DEPENDENCIES = [AnnotatedRoutesBootloader::class];
    30	
    31	    #[\Override]
    32	    protected function globalMiddleware(): array
    33	    {
    34	        return [
    35	            ErrorHandlerMiddleware::class,
    36	            LocaleMiddleware::class,
    37	            RouteNotFoundMiddleware::class,
    38	            DumperMiddleware::class,
    39	            JsonPayloadMiddleware::class,
    40	            HttpCollector::class,
    41	        ];
    42	    }
    43	
    44	    #[\Override]
    45	    protected function middlewareGroups(): array
    46	    {
    47	        return [
    48	            self::GROUP_API => [
    49	                ValidationHandlerMiddleware::class,
    50	            ],
    51	            self::GROUP_WEB => [
    52	                CookiesMiddleware::class,
    53	                SessionMiddleware::class,
    54	                CsrfMiddleware::class,
    55	                ValidationHandlerMiddleware::class,
    56	            ],
    57	        ];
    58	    }
    59	}

 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Shared\Infrastructure\Framework;
     6	
     7	use Spiral\Boot\Bootloader\CoreBootloader;
     8	use Spiral\Bootloader as Framework;
     9	use Spiral\Bootloader\Http\HttpBootloader;
    10	use Spiral\Bootloader\I18nBootloader;
    11	use Spiral\Bootloader\Views\TranslatedCacheBootloader;
    12	use Spiral\Cache\Bootloader\CacheBootloader;
    13	use Spiral\Cycle\Bootloader as CycleBridge;
    14	use Spiral\DataGrid\Bootloader\GridBootloader;
    15	use Spiral\Debug\Bootloader\DumperBootloader;
    16	use Spiral\Distribution\Bootloader\DistributionBootloader;
    17	use Spiral\DotEnv\Bootloader\DotenvBootloader;
    18	use Spiral\Events\Bootloader\EventsBootloader;
    19	use Spiral\League\Event\Bootloader\EventBootloader;
    20	use Spiral\Monolog\Bootloader\MonologBootloader;
    21	use Spiral\Nyholm\Bootloader\NyholmBootloader;
    22	use Spiral\Prototype\Bootloader\PrototypeBootloader;
    23	use Spiral\Queue\Bootloader\QueueBootloader;
    24	use Spiral\RoadRunnerBridge\Bootloader as RoadRunnerBridge;
    25	use Spiral\Scaffolder\Bootloader\ScaffolderBootloader;
    26	use Spiral\Scheduler\Bootloader\SchedulerBootloader;
    27	use Spiral\SendIt\Bootloader\MailerBootloader;
    28	use Spiral\Sentry\Bootloader\SentryReporterBootloader;
    29	use Spiral\Storage\Bootloader\StorageBootloader;
    30	use Spiral\TemporalBridge\Bootloader as TemporalBridge;
    31	use Spiral\Tokenizer\Bootloader\TokenizerListenerBootloader;
    32	use Spiral\Twig\Bootloader\TwigBootloader;
    33	use Spiral\Validation\Bootloader\ValidationBootloader;
    34	use Spiral\Validation\Symfony\Bootloader\ValidatorBootloader;
    35	use Spiral\Views\Bootloader\ViewsBootloader;
    36	use App\Modules\Media\Infrastructure\Bootloader\MediaBootloader;
    37	use App\Modules\Outbox\Infrastructure\Bootloader\OutboxBootloader;
    38	use App\Modules\Outbox\Infrastructure\Bootloader\OutboxConsoleBootloader;
    39	use GianTiaga\SpiralApiErrors\Bootloader\ApiErrorBootloader;
    40	use GianTiaga\SpiralCqrs\Bootloader\CqrsBootloader;
    41	use GianTiaga\SpiralOpenApi\Bootloader\OpenApiToolsBootloader;
    42	
    43	class Kernel extends \Spiral\Framework\Kernel
    44	{
    45	    #[\Override]
    46	    public function defineSystemBootloaders(): array
    47	    {
    48	        return [
    49	            CoreBootloader::class,
    50	            DotenvBootloader::class,
    51	            TokenizerListenerBootloader::class,
    52	
    53	            DumperBootloader::class,
    54	        ];
    55	    }
    56	
    57	    #[\Override]
    58	    public function defineBootloaders(): array
    59	    {
    60	        return [
    61	            // Логирование и обработка исключений
    62	            MonologBootloader::class,
    63	            Bootloader\ExceptionHandlerBootloader::class,
    64	
    65	            // Логи приложения
    66	            Bootloader\ConfigBootloader::class,
    67	            Bootloader\LoggingBootloader::class,
    68	
    69	            // RoadRunner
    70	            RoadRunnerBridge\LoggerBootloader::class,
    71	            RoadRunnerBridge\QueueBootloader::class,
    72	            RoadRunnerBridge\HttpBootloader::class,
    73	            RoadRunnerBridge\CacheBootloader::class,
    74	            RoadRunnerBridge\LockBootloader::class,
    75	
    76	            // Базовые сервисы
    77	            Framework\SnapshotsBootloader::class,
    78	
    79	            // Безопасность и валидация
    80	            Framework\Security\EncrypterBootloader::class,
    81	            Framework\Security\FiltersBootloader::class,
    82	            Framework\Security\GuardBootloader::class,
    83	
    84	            // HTTP-расширения
    85	            HttpBootloader::class,
    86	            Framework\Http\ErrorHandlerBootloader::class,
    87	            Framework\Http\RouterBootloader::class,
    88	            Framework\Http\JsonPayloadsBootloader::class,
    89	            Framework\Http\CookiesBootloader::class,
    90	            Framework\Http\SessionBootloader::class,
    91	            Framework\Http\CsrfBootloader::class,
    92	            Framework\Http\PaginationBootloader::class,
    93	
    94	            // Базы данных
    95	            CycleBridge\DatabaseBootloader::class,
    96	            CycleBridge\MigrationsBootloader::class,
    97	
    98	            // ORM
    99	            CycleBridge\SchemaBootloader::class,
   100	            CycleBridge\CycleOrmBootloader::class,
   101	            CycleBridge\AnnotatedBootloader::class,
   102	
   103	            // Диспетчер событий
   104	            EventsBootloader::class,
   105	            EventBootloader::class,
   106	
   107	            // Планировщик
   108	            SchedulerBootloader::class,
   109	
   110	            // Sentry и сборщики данных
   111	            SentryReporterBootloader::class,
   112	            Framework\DebugBootloader::class,
   113	            Framework\Debug\LogCollectorBootloader::class,
   114	            Framework\Debug\HttpCollectorBootloader::class,
   115	
   116	            // Представления
   117	            ViewsBootloader::class,
   118	            TwigBootloader::class,
   119	
   120	            // Очереди
   121	            QueueBootloader::class,
   122	
   123	            // Кэш
   124	            CacheBootloader::class,
   125	
   126	            // Хранилище файлов
   127	            StorageBootloader::class,
   128	            DistributionBootloader::class,
   129	
   130	            // Интернационализация
   131	            I18nBootloader::class,
   132	            TranslatedCacheBootloader::class,
   133	            OpenApiToolsBootloader::class,
   134	            CqrsBootloader::class,
   135	            OutboxBootloader::class,
   136	            MediaBootloader::class,
   137	
   138	            // Почта
   139	            MailerBootloader::class,
   140	
   141	            // Data Grid
   142	            GridBootloader::class,
   143	
   144	            // Temporal
   145	            TemporalBridge\PrototypeBootloader::class,
   146	            TemporalBridge\TemporalBridgeBootloader::class,
   147	
   148	            NyholmBootloader::class,
   149	
   150	            CycleBridge\DataGridBootloader::class,
   151	
   152	            ValidationBootloader::class,
   153	            ValidatorBootloader::class,
   154	
   155	            RoadRunnerBridge\MetricsBootloader::class,
   156	
   157	            // Консольные команды
   158	            Framework\CommandBootloader::class,
   159	            Bootloader\OpenApiBootloader::class,
   160	            OutboxConsoleBootloader::class,
   161	            RoadRunnerBridge\CommandBootloader::class,
   162	            CycleBridge\CommandBootloader::class,
   163	            ScaffolderBootloader::class,
   164	            RoadRunnerBridge\ScaffolderBootloader::class,
   165	            CycleBridge\ScaffolderBootloader::class,
   166	
   167	            // Быстрое прототипирование кода
   168	            PrototypeBootloader::class,
   169	
   170	            // Группы маршрутов и middleware
   171	            ApiErrorBootloader::class,
   172	            Bootloader\RoutesBootloader::class,
   173	        ];
   174	    }
   175	
   176	    #[\Override]
   177	    public function defineAppBootloaders(): array
   178	    {
   179	        return [
   180	            // Доменный обработчик приложения
   181	            Bootloader\AppBootloader::class,
   182	        ];
   183	    }
   184	}

exec
/bin/zsh -lc "nl -ba app/src/Modules/User/Domain/Enum/UserStatus.php | sed -n '1,120p'; nl -ba app/src/Shared/Domain/ValueObject/UserId.php | sed -n '1,80p'; nl -ba app/src/Modules/Access/Domain/Entity/UserRole.php | sed -n '1,120p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\User\Domain\Enum;
     6	
     7	enum UserStatus: string
     8	{
     9	    case WaitingEmailConfirmation = 'waiting_email_confirmation';
    10	    case Active = 'active';
    11	    case Banned = 'banned';
    12	    case Deleted = 'deleted';
    13	}
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Shared\Domain\ValueObject;
     6	
     7	final readonly class UserId extends AbstractUuidV7Id {}
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Access\Domain\Entity;
     6	
     7	use App\Modules\Access\Domain\ValueObject\RoleId;
     8	use App\Modules\Access\Domain\ValueObject\UserRoleId;
     9	use App\Modules\Access\Repository\UserRoleRepository;
    10	use App\Shared\Domain\ValueObject\UserId;
    11	use App\Shared\Infrastructure\Cycle\ValueObjectCast;
    12	use Cycle\Annotated\Annotation\Column;
    13	use Cycle\Annotated\Annotation\Entity;
    14	use Cycle\ORM\Parser\Typecast;
    15	
    16	#[Entity(
    17	    role: 'user_role',
    18	    table: 'user_roles',
    19	    repository: UserRoleRepository::class,
    20	    typecast: [Typecast::class, ValueObjectCast::class],
    21	)]
    22	final class UserRole
    23	{
    24	    #[Column(type: 'uuid', primary: true, typecast: UserRoleId::class)]
    25	    public private(set) UserRoleId $id;
    26	
    27	    #[Column(type: 'uuid', name: 'user_id', typecast: UserId::class)]
    28	    public private(set) UserId $userId;
    29	
    30	    #[Column(type: 'uuid', name: 'role_id', typecast: RoleId::class)]
    31	    public private(set) RoleId $roleId;
    32	
    33	    public static function create(UserId $userId, RoleId $roleId): self
    34	    {
    35	        $userRole = new self();
    36	        $userRole->id = UserRoleId::generate();
    37	        $userRole->userId = $userId;
    38	        $userRole->roleId = $roleId;
    39	
    40	        return $userRole;
    41	    }
    42	}

exec
/bin/zsh -lc 'find app/src/Modules/User app/src/Modules/Access -maxdepth 3 -type d | sort' in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
app/src/Modules/Access
app/src/Modules/Access/Domain
app/src/Modules/Access/Domain/Collection
app/src/Modules/Access/Domain/Entity
app/src/Modules/Access/Domain/Enum
app/src/Modules/Access/Domain/ValueObject
app/src/Modules/Access/Repository
app/src/Modules/User
app/src/Modules/User/Domain
app/src/Modules/User/Domain/Entity
app/src/Modules/User/Domain/Enum
app/src/Modules/User/Domain/ValueObject
app/src/Modules/User/Infrastructure
app/src/Modules/User/Infrastructure/Cycle
app/src/Modules/User/Repository

exec
/bin/zsh -lc 'wc -l docs/arch.md docs/rules.md docs/researches/2026-06-13_22-36_auth-module.md docs/researches/2026-06-13_12-33_user-domain.md' in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc "nl -ba docs/researches/2026-06-13_22-36_auth-module.md | sed -n '1,360p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     646 docs/arch.md
     113 docs/rules.md
     332 docs/researches/2026-06-13_22-36_auth-module.md
     267 docs/researches/2026-06-13_12-33_user-domain.md
    1358 total

 succeeded in 0ms:
     1	---
     2	title: Модуль Auth — беспарольный вход по email-коду
     3	date: 2026-06-13 22:36
     4	mode: strict
     5	decision_mode: ask_each_time
     6	status: draft
     7	reviewer: pending
     8	sources:
     9	  rules: docs/rules.md
    10	  arch: docs/arch.md
    11	---
    12	
    13	# Модуль Auth — беспарольный вход по email-коду
    14	
    15	## Суть
    16	
    17	Проектируем модуль `Auth` — аутентификацию для API мобильного приложения YogaLoka.
    18	Домен и данные `User` и `Access` уже реализованы (`User.php:77`, модуль `Access`), но
    19	аутентификацию там осознанно не делали (`docs/researches/2026-06-13_12-33_user-domain.md:248`):
    20	у `User` нет поля пароля вообще, есть только статус `WaitingEmailConfirmation` и метод
    21	`confirmEmail()` (`User.php:101`).
    22	
    23	Решаем: как пользователь входит и регистрируется, чем выдаём и проверяем токены, где
    24	хранить коды и токены, как защититься от перебора, и как всё это лечь в модульный
    25	монолит на Spiral + Cycle + CQRS (`docs/arch.md:5`), не нарушая границы модулей и не
    26	переписывая готовый домен `User`.
    27	
    28	Рамка: модуль `Auth` уже назван в архитектуре отдельной областью (`docs/arch.md:23`).
    29	Идентификаторы — UUID v7, доменные значения — VO/enum, доступ к БД — через репозитории,
    30	внешние эффекты (письма) — через outbox (`docs/arch.md:514`).
    31	
    32	## Решение
    33	
    34	Беспарольная аутентификация: пользователь вводит email, получает на почту 6-значный код,
    35	вводит код — и входит. Один поток = и вход, и регистрация. Пароля нет, поэтому сброса и
    36	смены пароля тоже нет. Токены — непрозрачные (случайная строка, данные на сервере), пара
    37	access + refresh, хранятся в Cycle, refresh ротируется. Защита от перебора — переиспользуемый
    38	атрибут `#[RateLimited]` + Spiral-интерцептор в `Shared`.
    39	
    40	### Два потока входа
    41	
    42	```text
    43	СУЩЕСТВУЮЩИЙ email
    44	  POST /api/auth/code/request {email}     -> код на почту (через outbox)
    45	  POST /api/auth/code/verify  {email,code}-> сервер узнал юзера
    46	                                             -> {access, refresh, expiresIn}
    47	
    48	НОВЫЙ email (вход = регистрация)
    49	  POST /api/auth/code/request {email}     -> код на почту
    50	  POST /api/auth/code/verify  {email,code}-> юзера ещё нет
    51	                                             -> {registrationTicket, needsProfile:true}
    52	  POST /api/auth/register {ticket,name,nickname}
    53	                                          -> создаём User (сразу Active) + {access, refresh}
    54	
    55	ОБНОВЛЕНИЕ И ВЫХОД
    56	  POST /api/auth/refresh {refreshToken}   -> ротация -> новые {access, refresh}
    57	  POST /api/auth/logout  (Bearer access)  -> отзыв токена(ов)
    58	```
    59	
    60	«Талон регистрации» — короткоживущая (~10 мин) одноразовая запись, привязанная к email,
    61	а не к пользователю (пользователя ещё нет). Он переносит доказательство «email только что
    62	подтверждён кодом» с шага проверки кода на шаг заполнения профиля. Без него запрос
    63	`register` не имел бы доказательства, что почта подтверждена, и любой мог бы
    64	зарегистрировать чужой email. Талон годится только для завершения регистрации, это не токен
    65	доступа.
    66	
    67	Почему так, а не «авто-ник» или «всё сразу»: `User::create()` требует обязательные `UserName`
    68	и уникальный `UserNickname` (`User.php:77-99`), а email-вход даёт только email. Вариант
    69	«код → талон → профиль» не трогает готовый домен `User`, не плодит пустые аккаунты и не
    70	занимает ники на неподтверждённые email. Пользователь рождается сразу `Active` (код доказал
    71	владение почтой), поэтому `WaitingEmailConfirmation` при регистрации не используется — этот
    72	статус остаётся для смены email существующим пользователем (`changeEmail()` → код →
    73	`confirmEmail()`, `User.php:107`).
    74	
    75	### Карта модуля Auth
    76	
    77	```text
    78	Modules/Auth/
    79	  Domain/
    80	    Entity/        LoginCode, RegistrationTicket, AuthToken
    81	    ValueObject/   LoginCodeId, RegistrationTicketId, AuthTokenId,
    82	                   EmailFingerprint, SecretHash, CodeAttempts,
    83	                   CodeExpiration, TicketExpiration, TokenExpiration
    84	    Enum/          AuthTokenType (Access | Refresh)
    85	  Application/
    86	    Command/Auth/  RequestLoginCode, VerifyLoginCode, CompleteRegistration,
    87	                   RefreshTokens, Logout
    88	    Contract/      SecretHasherContract, TokenGeneratorContract
    89	    Message/       LoginCodeRequested            # outbox-сообщение (письмо)
    90	    Exception/     # ошибки слоя Application, если нужны сверх общих
    91	  Repository/      LoginCodeRepository, RegistrationTicketRepository, AuthTokenRepository
    92	  Infrastructure/
    93	    Cycle/         # typecast VO
    94	    Auth/          CycleTokenStorage, UserActorProvider
    95	    Hash/          HmacSecretHasher                # реализация SecretHasherContract
    96	    Bootloader/    AuthBootloader                  # биндинги storage/actor provider
    97	  Presentation/
    98	    Http/Controller/ AuthController
    99	    Http/Filter/     RequestCodeFilter, VerifyCodeFilter, RegisterFilter, RefreshFilter
   100	    Http/Resource/   TokenPairResource, VerifyResultResource
   101	    Job/             SendLoginCodeJob              # потребитель outbox-задачи
   102	
   103	Shared/                                            # переиспользуемый rate limit
   104	  Presentation/Http/Attribute/ RateLimited
   105	  Infrastructure/Framework/Interceptor/ RateLimitInterceptor
   106	```
   107	
   108	Auth ссылается на пользователя через общий `UserId` из `Shared/Domain/ValueObject`
   109	(уже есть), как это делает `Access`. В таблицу `users` и в `UserRepository` Auth напрямую
   110	не лезет — только через `User/Application` (`docs/arch.md:131`, `docs/arch.md:141-146`).
   111	
   112	### Таблицы (владелец — модуль Auth)
   113	
   114	```text
   115	auth_login_codes
   116	  id              UUID v7 PK
   117	  email           citext            -- по нему ищем; индекс
   118	  code_hash       text              -- HMAC-SHA256(code, app secret); сам код не храним
   119	  expires_at      datetime          -- now + ~5 мин
   120	  attempts        int               -- счётчик неверных вводов, лимит -> код сгорает
   121	  consumed_at     datetime null     -- одноразовость
   122	  created_at, updated_at
   123	
   124	auth_registration_tickets
   125	  id              UUID v7 PK
   126	  email           citext
   127	  ticket_hash     text              -- HMAC-SHA256(ticket, app secret)
   128	  expires_at      datetime          -- now + ~10 мин
   129	  consumed_at     datetime null
   130	  created_at, updated_at
   131	
   132	auth_tokens                          -- наше Cycle-хранилище токенов
   133	  id              UUID v7 PK
   134	  user_id         UUID              -- UserId; индекс (для отзыва по пользователю)
   135	  type            enum              -- access | refresh
   136	  token_hash      text              -- SHA-256(token); сам токен у клиента
   137	  expires_at      datetime
   138	  created_at, updated_at
   139	```
   140	
   141	### Токены: своё Cycle-хранилище поверх spiral/auth
   142	
   143	`spiral/auth` (входит в `spiral/framework` 3.16.2) даёт `TokenStorageInterface`,
   144	`ActorProviderInterface`, `AuthMiddleware` и транспорт `header` (Bearer) — подтверждено по
   145	документации Spiral 3.16. Берём этот механизм, но `TokenStorageInterface` реализуем **сами**
   146	(`Auth/Infrastructure/Auth/CycleTokenStorage`) поверх своей сущности `AuthToken`, а не
   147	готовое generic-хранилище `cycle-bridge` (`AUTH_TOKEN_STORAGE=cycle`).
   148	
   149	Причина: generic-хранилище кладёт payload в JSON и не индексирует по пользователю — а нам
   150	нужно: ротация refresh, отзыв при выходе, мульти-девайс и отзыв всех токенов при бане. Своя
   151	таблица с колонкой `user_id` это решает и ложится в паттерн проекта (Repository + Cycle). Это
   152	по-прежнему «хранилище Cycle», как и выбрано.
   153	
   154	```text
   155	выдача (verify/register): create access (TTL ~1ч) + create refresh (TTL ~30-60д)
   156	                          -> клиенту отдаём сырые строки токенов
   157	запрос к API:             AuthMiddleware (header) -> CycleTokenStorage.load(access)
   158	                          -> UserActorProvider.getActor -> actor = AuthenticatedUser(UserId)
   159	refresh:                  читаем refreshToken из тела -> load -> проверка type=refresh и срок
   160	                          -> delete старого refresh (ротация) + create новой пары access+refresh
   161	logout:                   delete текущего access (и refresh, если передан)
   162	```
   163	
   164	`UserActorProvider::getActor(token)` достаёт `userID` из payload и возвращает лёгкий объект
   165	актора с `UserId`. Тяжёлой проверки в БД на каждом запросе не делаем: бан отзывает токены
   166	(будущий `BanUser` дёргает `Auth/Application` для удаления токенов по `user_id`), а access
   167	короткоживущий. Привязка актора к ролям/правам `Access` (GuardInterceptor) — вне рамок этого
   168	захода.
   169	
   170	Токены — 256-бит случайная строка (high-entropy), в БД только `SHA-256`. Коды и талоны —
   171	низкоэнтропийные (6 цифр / короткая строка), поэтому хэшируем `HMAC-SHA256` с серверным
   172	секретом (app key из `EncrypterBootloader`): при утечке БД без ключа перебор offline
   173	невозможен. Сравнение — по хэшу (constant-time). Это `SecretHasherContract` с реализацией в
   174	`Infrastructure/Hash`.
   175	
   176	### Письмо с кодом — через outbox
   177	
   178	Отправка письма — внешний эффект, поэтому идёт через transactional outbox (`docs/arch.md:514`),
   179	а не напрямую из Handler-а:
   180	
   181	```text
   182	RequestLoginCode Handler (#[Transactional])
   183	  -> сгенерировать код, сохранить LoginCode (хэш) через LoginCodeRepository
   184	  -> OutboxEventStoreContract::add( LoginCodeRequested{email, code, locale} )
   185	  -> EntityManager::run()                       # код и outbox в одной транзакции
   186	outbox relay -> RabbitMQ -> SendLoginCodeJob -> Mailer (Mailpit в dev) шлёт письмо
   187	```
   188	
   189	Код в payload outbox идёт открытым текстом (иначе письмо не отправить) — это короткоживущая
   190	запись в служебной таблице, не видна пользователю, риск принимаем. Локаль письма кладём в
   191	сообщение: для нового email — из `Accept-Language` запроса (`LocaleMiddleware`), для
   192	существующего — из `User.preferredLocale`; в очереди per-request локали нет (`docs/arch.md:382`),
   193	поэтому язык переносим в сообщение.
   194	
   195	### Rate limit — атрибут + интерцептор в Shared
   196	
   197	```text
   198	Shared/Presentation/Http/Attribute/RateLimited(maxAttempts, perSeconds)
   199	Shared/Infrastructure/Framework/Interceptor/RateLimitInterceptor
   200	  - общий, ничего не знает про email
   201	  - ключ = client IP + действие (контроллер::метод), счётчики в Redis (уже есть)
   202	  - превышение -> 429 (через packages/spiral-api-errors)
   203	```
   204	
   205	Вешаем `#[RateLimited]` на действия `AuthController` (request/verify/register/refresh).
   206	Лимит «не чаще одного кода в минуту на email» и «N неверных вводов кода» — это доменное
   207	правило, живёт внутри сценариев Auth по таблице `auth_login_codes` (поля `attempts`,
   208	`expires_at`), а не в общем интерцепторе. Так общий механизм остаётся универсальным для
   209	любого эндпоинта, а email-специфика — там, где есть смысл email.
   210	
   211	### Сценарии (CQRS)
   212	
   213	```text
   214	RequestLoginCode(email)            Command #[Transactional]
   215	  валидирует email (Filter -> 422), генерит код, гасит/обновляет прошлый код для email,
   216	  пишет LoginCode (hash), кладёт LoginCodeRequested в outbox. Ответ всегда 200
   217	  (не раскрываем, есть ли такой пользователь).
   218	
   219	VerifyLoginCode(email, code)       Command #[Transactional]
   220	  грузит активный LoginCode по email, сверяет hash, проверяет срок и attempts.
   221	  Неверно -> attempts++, AuthenticationException (401). Верно -> consumed.
   222	    - User по email существует (через User/Application) и не Banned/Deleted -> выдать пару токенов
   223	      (если был WaitingEmailConfirmation -> confirmEmail()).
   224	    - User не существует -> создать RegistrationTicket, вернуть его + needsProfile.
   225	    - User Banned/Deleted -> отказ (Forbidden/Authentication).
   226	
   227	CompleteRegistration(ticket,name,nickname)  Command #[Transactional]
   228	  валидирует и гасит ticket, вызывает User/Application CreateUser(email,name,nickname,locale)
   229	  (он же отвечает за уникальность ника и ReservedNickname), выдаёт пару токенов.
   230	
   231	RefreshTokens(refreshToken)        Command #[Transactional]
   232	  load -> type=refresh + срок -> delete (ротация) -> create access+refresh.
   233	
   234	Logout(token)                      Command #[Transactional]
   235	  delete токена(ов) текущей сессии.
   236	```
   237	
   238	### Зависимость от User/Application (важно)
   239	
   240	Auth не может ходить в `UserRepository` напрямую (`docs/arch.md:141-146`), а у модуля `User`
   241	сейчас **нет** слоя Application — только Domain + Repository + Infrastructure. Значит для Auth
   242	нужно, чтобы в `User/Application` появились минимум:
   243	
   244	```text
   245	CreateUser(email, name, nickname, locale)   -> создаёт User (Active), держит уникальность ника
   246	FindUserForAuth(email)                       -> есть ли юзер + его UserId + статус (active/banned/deleted)
   247	```
   248	
   249	`User-domain` research уже ставит создание `User/Application` (CreateUser и т.д.) следующим
   250	шагом (`docs/researches/2026-06-13_12-33_user-domain.md:267`). Поэтому план Auth должен либо
   251	включать эти две User-команды, либо идти после плана User. Это единственная жёсткая внешняя
   252	зависимость Auth.
   253	
   254	### Границы зависимостей
   255	
   256	```text
   257	Auth/Domain        -> Shared/Domain (UserId), свой Domain
   258	Auth/Application    -> Auth/Domain, Auth/Repository, Auth/Contract, User/Application, Outbox/Application
   259	Auth/Infrastructure -> spiral/auth (TokenStorage, ActorProvider), свой Contract, Redis, Cycle
   260	Auth/Presentation   -> Auth/Application, Shared RateLimited
   261	Shared              -> RateLimited + RateLimitInterceptor (общие, не про Auth)
   262	User                -> про токены/коды не знает; даёт CreateUser/FindUserForAuth на границе
   263	```
   264	
   265	### Версии и зависимости
   266	
   267	Новых внешних пакетов не требуется. Всё на установленном: PHP 8.5, `spiral/framework` 3.16.2
   268	(включает `spiral/auth`, `spiral/auth-http`, `spiral/security`), `spiral/cycle-bridge` ^2.11,
   269	`ext-redis`, `MailerBootloader` + Mailpit, outbox-модуль. JWT-библиотека и OAuth-провайдеры
   270	не нужны — выбран непрозрачный токен и беспарольный вход. Проверять актуальные версии нечего:
   271	ничего нового не вводим (источник: `composer.json:11-40`, context7 Spiral 3.16 auth docs).
   272	
   273	## Ответы на вопросы
   274	
   275	```text
   276	Что входит в этот заход?
   277	  Беспарольный вход по email-коду = вход и регистрация одновременно.
   278	  Сброса/смены пароля нет (пароля нет). В ядре: запрос кода, проверка кода,
   279	  регистрация по талону, refresh, logout. Плюс rate limit через интерцептор.
   280	  Отложено: вход через Apple/Google, привязка ролей Access к актору (Guard).
   281	
   282	Чем выдавать/проверять токены?
   283	  Непрозрачные access + refresh, хранилище на Cycle (своя таблица auth_tokens),
   284	  refresh ротируется, Bearer-транспорт. JWT и сессии-куки отклонены
   285	  (для одного монолита и нативного мобайла выгоды нет).
   286	
   287	Где лежат данные аутентификации?
   288	  Отдельный модуль Auth со своими таблицами (auth_login_codes,
   289	  auth_registration_tickets, auth_tokens), связь по UserId. Пароля нет.
   290	
   291	Что такое «талон регистрации»?
   292	  Короткоживущая одноразовая запись по email, доказывающая «почта только что
   293	  подтверждена кодом». Нужна, потому что User требует имя+ник, а email-вход их
   294	  не даёт: код -> талон -> экран профиля -> создание User.
   295	
   296	Поток регистрации для нового email?
   297	  Код -> талон -> профиль. Домен User не меняем, пустых аккаунтов нет,
   298	  ники не занимаются на неподтверждённые email. User рождается Active.
   299	
   300	Где хранить коды?
   301	  Таблица PostgreSQL в модуле Auth, только хэш (HMAC с серверным секретом),
   302	  со сроком и счётчиком попыток. Redis оставили под rate limit.
   303	
   304	Rate limit — какой интерцептор и по чему?
   305	  Атрибут #[RateLimited] на действии контроллера + общий Spiral-интерцептор
   306	  в Shared, лимит по IP + действие, счётчики в Redis. Лимит по email
   307	  (код в минуту, попытки) — правило сценария Auth по таблице кодов.
   308	
   309	Подтверждение почты?
   310	  Это и есть код входа: получил код на почту = подтвердил владение.
   311	  WaitingEmailConfirmation/confirmEmail остаются для смены email.
   312	```
   313	
   314	## Итог
   315	
   316	Делаем модуль `Auth` с беспарольным входом по email-коду (вход = регистрация). Поток:
   317	`request code` → `verify code` → существующему юзеру сразу пара токенов, новому — талон, по
   318	талону `register` (имя+ник) → создание `User` (Active) + токены; плюс `refresh` (с ротацией)
   319	и `logout` (отзыв). Токены непрозрачные access+refresh в собственной Cycle-таблице
   320	`auth_tokens` через свою реализацию `spiral/auth TokenStorageInterface`; коды и талоны — в
   321	своих таблицах Auth, хэшированные HMAC с серверным секретом, со сроком и счётчиком попыток.
   322	Письмо с кодом — через outbox + Mailer. Rate limit — переиспользуемый `#[RateLimited]` +
   323	интерцептор в `Shared` (IP+действие), email-троттлинг — в сценарии Auth.
   324	
   325	Следующий шаг — `eda-plan` на реализацию модуля `Auth` по этому исследованию: миграции трёх
   326	таблиц, Entity+VO+typecast, репозитории, `CycleTokenStorage` и `UserActorProvider`,
   327	`SecretHasherContract`+реализация, сценарии RequestLoginCode/VerifyLoginCode/
   328	CompleteRegistration/RefreshTokens/Logout, контроллер+фильтры+ресурсы, `LoginCodeRequested`
   329	+ `SendLoginCodeJob`, `RateLimited`+`RateLimitInterceptor` в Shared, регистрация
   330	AuthMiddleware (header) в API-группе и AuthBootloader. План обязан включить (или идти после)
   331	`User/Application`: `CreateUser` и `FindUserForAuth` — без них Auth не собрать. Языки писем,
   332	конкретные TTL и интеграционные тесты роутов фиксируются в плане.

exec
/bin/zsh -lc "nl -ba docs/arch.md | sed -n '1,220p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	# Архитектура: модульный монолит на Spiral + CQRS + тактический DDD
     2	
     3	## Обзор
     4	
     5	YogaLoka - API-first backend для мобильного приложения на PHP 8.5, Spiral
     6	Framework, RoadRunner и Cycle ORM.
     7	
     8	Приложение работает как модульный монолит: один runtime, один deploy и один код
     9	приложения. Код группируется в модули по предметной области.
    10	
    11	## Модули
    12	
    13	Проект устроен как модульный монолит с прагматичным DDD-подходом.
    14	
    15	Бизнес-логика живёт в `Domain`. Важные значения оформляются как
    16	`ValueObject`, сущности содержат поведение, а технические детали вынесены в
    17	`Infrastructure`.
    18	
    19	DDD-паттерны не вводятся автоматически. Aggregate, Domain Service, Domain Event,
    20	отдельная read model или anti-corruption layer добавляются только когда они
    21	решают реальную проблему в коде.
    22	
    23	Модуль - это отдельная область приложения: `Media`, `User`, `Feed`, `Auth`.
    24	
    25	Структура нового модуля:
    26	
    27	```text
    28	Modules/
    29	  Media/
    30	    Domain/
    31	    Application/
    32	      Contract/
    33	    Repository/
    34	    Infrastructure/
    35	      Cycle/
    36	      FileService/
    37	    Presentation/
    38	```
    39	
    40	Назначение слоёв:
    41	
    42	```text
    43	Domain         - бизнес-логика модуля.
    44	Application    - сценарии модуля и контракты для технических зависимостей.
    45	Repository     - доступ к БД своего модуля через доменные методы.
    46	Infrastructure - технические реализации контрактов, Cycle typecast, S3, внешние сервисы.
    47	Presentation   - HTTP-контроллеры, фильтры запросов и другие входы.
    48	```
    49	
    50	Исключения каждого слоя лежат в папке `Exception/` своего слоя:
    51	`Application/Exception`, `Infrastructure/Exception`, `Presentation/Exception`, а
    52	общие доменные — в `Shared/Domain/Exception`. Слой исключения определяется
    53	контрактом, к которому оно относится, а не местом выброса: например
    54	`OutboxMessageLoadingException` лежит в `Application/Exception`, хотя бросается в
    55	инфраструктурной реализации загрузчика сообщений.
    56	
    57	Правила связей:
    58	
    59	```text
    60	Domain не зависит от других слоёв.
    61	
    62	Application может использовать:
    63	- свой Domain;
    64	- свой Repository;
    65	- свои Contract;
    66	- Application других модулей.
    67	
    68	Infrastructure реализует Contract своего модуля.
    69	
    70	Presentation вызывает Application своего модуля.
    71	```
    72	
    73	Контракты лежат в:
    74	
    75	```text
    76	Modules/{Module}/Application/Contract
    77	```
    78	
    79	Имена контрактов заканчиваются на `Contract`.
    80	
    81	Примеры:
    82	
    83	```text
    84	MediaFileServiceContract
    85	```
    86	
    87	Репозитории лежат в:
    88	
    89	```text
    90	Modules/{Module}/Repository
    91	```
    92	
    93	Пример:
    94	
    95	```text
    96	MediaRepository
    97	```
    98	
    99	Репозитории не являются публичным API модуля. `Application` своего модуля может
   100	использовать свои репозитории напрямую, но другие модули не обращаются к ним.
   101	
   102	Технические реализации контрактов лежат в `Infrastructure`:
   103	
   104	```text
   105	Infrastructure/
   106	  Cycle/        # typecast и другие классы для Cycle ORM
   107	  FileService/  # техническая работа с файлами
   108	```
   109	
   110	Примеры:
   111	
   112	```text
   113	S3MediaFileService
   114	```
   115	
   116	Общий доменный код, который не принадлежит одному модулю, лежит в `Shared`.
   117	
   118	Пример:
   119	
   120	```text
   121	Shared/
   122	  Domain/
   123	    Exception/
   124	    Trait/
   125	    ValueObject/
   126	  Presentation/
   127	    Http/
   128	      Resource/
   129	```
   130	
   131	Другие модули могут обращаться только к `Application`.
   132	
   133	Хорошо:
   134	
   135	```text
   136	User/Application -> Media/Application
   137	```
   138	
   139	Плохо:
   140	
   141	```text
   142	User/Application -> Media/Infrastructure
   143	User/Infrastructure -> Media/Infrastructure
   144	User -> media_files table
   145	User -> MediaRepository
   146	```
   147	
   148	Пример:
   149	
   150	```text
   151	User/Application/SetAvatar
   152	  -> Media/Application/CheckMediaIsImage
   153	  -> User/Domain/User::setAvatar
   154	```
   155	
   156	`Media` не должен знать про аватар. Аватар - это часть `User`.
   157	
   158	`Media` должен давать только свои сценарии:
   159	
   160	```text
   161	CreateMedia
   162	DeleteMedia
   163	CheckMediaExists
   164	CheckMediaIsImage
   165	GetMediaUrl
   166	```
   167	
   168	## Локальный Docker-runtime
   169	
   170	Локальная разработка и проверки выполняются через Docker Compose из
   171	`docker/docker-compose.dev.yml`. Приложение запускается в двух RoadRunner runtime:
   172	
   173	- `app-http`: RoadRunner слушает `0.0.0.0:8080` только внутри контейнера.
   174	  Наружу compose публикует сервис как `127.0.0.1:60080 -> 8080`. RoadRunner
   175	  jobs consumer для RabbitMQ работает в том же процессе.
   176	- `temporal-worker`: отдельный Temporal worker на task queue `default`.
   177	
   178	Dev runtime использует RabbitMQ как queue connection по умолчанию. Memory
   179	pipeline остаётся запасным вариантом для локальных экспериментов и обратной
   180	совместимости, но не является основной очередью dev-стенда. Redis в локальном
   181	стенде используется для cache/session и RoadRunner KV, но не является брокером
   182	очереди.
   183	
   184	Локальная инфраструктура: PostgreSQL, Redis, RabbitMQ, MinIO, Mailpit, Temporal,
   185	Temporal UI и Centrifugo. Dev storage по умолчанию использует MinIO bucket
   186	`yoga-loka`, тесты используют отдельные `yoga_loka_test` и `yoga-loka-test`.
   187	
   188	## Структура каталогов
   189	
   190	```
   191	app/
   192	  config/                         # Spiral config-файлы; env() допустим только здесь
   193	  database/
   194	    migrations/                   # Cycle ORM миграции
   195	  locale/                         # Переводы
   196	  src/
   197	    Modules/
   198	      Media/
   199	        Domain/                   # Entity, ValueObject, Enum, доменные коллекции
   200	        Application/              # Command/Query сценарии модуля
   201	          Contract/               # Контракты технических сервисов, *Contract
   202	        Repository/               # Cycle repositories с доменными методами
   203	        Infrastructure/
   204	          Cycle/                  # Typecast и другие классы Cycle ORM
   205	          FileService/            # Реализации файловых сервисов
   206	        Presentation/             # HTTP, console, queue, Temporal входы модуля
   207	
   208	      Outbox/
   209	        Domain/                   # Outbox-события, статусы, value object
   210	        Application/
   211	          Command/                # Сценарии relay и обработки outbox-сообщений
   212	          Contract/               # OutboxEventStoreContract и сериализация сообщений
   213	          Exception/              # Исключения слоя Application (загрузка, сериализация сообщений)
   214	          Message/                # DTO сообщений outbox
   215	        Repository/               # Доступ к outbox_events
   216	        Infrastructure/
   217	          Bootloader/             # OutboxBootloader, OutboxConsoleBootloader
   218	          Exception/              # Исключения слоя Infrastructure (registry, relay)
   219	          Relay/                  # Relay, worker, loop control, sleeper
   220	          Queue/                  # Publisher, serializer, headers, status interceptor

exec
/bin/zsh -lc "nl -ba docs/researches/2026-06-13_12-33_user-domain.md | sed -n '1,300p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	---
     2	title: Проектирование домена пользователя и авторизации (User + Access)
     3	date: 2026-06-13 12:33
     4	mode: normal
     5	decision_mode: recommend_and_ask
     6	status: draft
     7	reviewer: none
     8	sources:
     9	  rules: docs/rules.md
    10	  arch: docs/arch.md
    11	---
    12	
    13	# Проектирование домена пользователя и авторизации (User + Access)
    14	
    15	## Суть
    16	
    17	Проектируем доменный слой и Value Object-ы модуля пользователя для YogaLoka. Аутентификацию (пароли, токены, login/email-verification flow) сейчас НЕ проектируем — только домен: сущности, VO, enum, статусы, связи и границы модулей.
    18	
    19	Вход — черновая структура таблиц от пользователя: профиль (имя, духовное имя, bio, место, email, псевдоним, аватар, флаг «подтверждён», статус, таймстампы), роли, права, связи `role_permissions` и `user_roles`. Дополнительно нужно предложить, что упущено.
    20	
    21	Рамка проекта: модульный монолит на Spiral + Cycle ORM, тактический DDD, CQRS (`docs/arch.md:5`, `docs/arch.md:13`). Идентификаторы — UUID v7 (`docs/rules.md:31`). Доменные примитивы — VO/enum, Entity без примитивов, валидация только в VO, доступ к данным через репозитории (`docs/rules.md:36`, `docs/rules.md:37`, `docs/rules.md:39`). Есть эталонный модуль `Media` и общие базовые VO (`UserId`, `AbstractUuidV7Id`, `AbstractIntegerValue`) в `Shared/Domain/ValueObject`.
    22	
    23	## Решение
    24	
    25	Разносим на два модуля: **`User`** (профиль, статус, верификация, баны) и **`Access`** (роли, права, проверки доступа). Граница между ними и `Media` идёт только через `Application` (`docs/arch.md:131`).
    26	
    27	### Карта модулей
    28	
    29	```text
    30	Modules/
    31	  User/
    32	    Domain/
    33	      Entity/        User, UserBan
    34	      ValueObject/   Email, UserNickname, UserName, UserSpiritualName,
    35	                     UserBio, UserLocation, UserTimezone, UserAvatar,
    36	                     UserDeletion, BanReason, BanExpiration, UserBanId
    37	      Enum/          UserStatus, UserVerification
    38	    Application/     Command/Query (CreateUser, UpdateUserProfile, SetUserAvatar,
    39	                     BanUser, UnbanUser, VerifyUser, DeleteUser, GetUserProfile ...)
    40	    Repository/      UserRepository, UserBanRepository
    41	    Infrastructure/  Cycle/ (typecast nullable VO)
    42	    Presentation/    Http/ (Controller, Filter, Resource)
    43	
    44	  Access/
    45	    Domain/
    46	      Entity/        Role, Permission
    47	      ValueObject/   RoleId, RoleSlug, PermissionId, PermissionSlug
    48	      Enum/          RoleName, PermissionName   # канон ролей/прав в коде
    49	    Application/     AssignRoleToUser, RevokeRoleFromUser,
    50	                     CheckUserHasPermission, GetUserRoles, GetUserPermissions
    51	    Repository/      RoleRepository, PermissionRepository, UserRoleRepository
    52	    Infrastructure/  Bootloader (сидер ролей/прав из enum)
    53	
    54	Shared/Domain/
    55	  Enum/Locale         # ru/en, общая сквозная концепция (LocaleConfig.supported)
    56	  ValueObject/UserId  # уже есть; используется и User, и Access, и Media
    57	```
    58	
    59	`UserId` уже лежит в `Shared/Domain/ValueObject/UserId.php:7` — поэтому `Access` ссылается на пользователя через общий `UserId`, не зная Entity `User`. Цикла зависимостей `User ↔ Access` нет.
    60	
    61	### Сущности
    62	
    63	| Сущность | Таблица | Назначение |
    64	|---|---|---|
    65	| `User` | `users` | Профиль, email, псевдоним, статус, верификация, аватар, локаль, мягкое удаление |
    66	| `UserBan` | `user_bans` | Журнал банов/разбанов (история модерации) |
    67	| `Role` | `roles` | Роль (справочник, сид из кода) |
    68	| `Permission` | `permissions` | Право (справочник, сид из кода) |
    69	| `RolePermission` | `role_permissions` | Связь роль↔право (many-to-many) |
    70	| `UserRole` | `user_roles` | Связь пользователь↔роль (many-to-many) |
    71	
    72	### Entity `User`
    73	
    74	| Поле | Доменный тип | Колонка БД | Null | Правила |
    75	|---|---|---|---|---|
    76	| `id` | `UserId` (Shared, UUID v7) | `id` uuid PK | нет | генерируется при `create()` |
    77	| `name` | `UserName` | `name` | нет | буквы/пробел/дефис/апостроф, без эмодзи и спецсимволов, 1–100 |
    78	| `spiritualName` | `UserSpiritualName` (null-object) | `spiritual_name` | да | те же правила, опционально |
    79	| `bio` | `UserBio` (null-object) | `bio` text | да | свободный текст, эмодзи разрешены, без управляющих символов, ≤500 |
    80	| `location` | `UserLocation` (null-object) | `location` | да | буквы/цифры/пробел/пунктуация, без эмодзи, ≤100 |
    81	| `email` | `Email` | `email` citext UNIQUE | нет | RFC, нормализация в lower, ≤254, уникален |
    82	| `nickname` | `UserNickname` | `nickname` citext UNIQUE | нет | `[a-z0-9._-]`, 3–30, не начинается/кончается точкой, без `..`, lower, уникален |
    83	| `avatar` | `UserAvatar` (null-object) | `avatar_media_id` uuid | да | ссылка на `Media.id`, аватар один; FK RESTRICT |
    84	| `verification` | `UserVerification` (enum) | `verification` | нет | `Unverified` по умолчанию |
    85	| `status` | `UserStatus` (enum) | `status` | нет | `WaitingEmailConfirmation` по умолчанию |
    86	| `preferredLocale` | `Locale` (enum, Shared) | `preferred_locale` | нет | `ru`/`en` |
    87	| `timezone` | `UserTimezone` | `timezone` | нет | IANA-идентификатор (валидируется списком), дефолт задаёт сценарий |
    88	| `deletion` | `UserDeletion` (null-object) | `deleted_at` datetime | да | soft-delete |
    89	| `createdAt` | `\DateTimeImmutable` (`HasTimestamps`) | `created_at` | нет | |
    90	| `updatedAt` | `\DateTimeImmutable` (`HasTimestamps`) | `updated_at` | нет | |
    91	
    92	`User::create()` принимает обязательные VO (`UserName`, `Email`, `UserNickname`, `Locale`, `UserTimezone`), ставит `status = WaitingEmailConfirmation`, `verification = Unverified`, опциональные поля — в null-object `none()`, и инициализирует таймстампы. Доменные методы (примеры): `rename()`, `changeSpiritualName()`, `changeBio()`, `changeLocation()`, `changeEmail()` (сбрасывает в `WaitingEmailConfirmation`), `confirmEmail()` (→ `Active`), `setAvatar(UserAvatar)`, `removeAvatar()`, `verify()`/`unverify()`, `ban()`/`unban()`, `markDeleted()`, `isActive()`/`isVerified()`/`isBanned()` (чистые predicate, единственный разрешённый `bool` — `docs/rules.md:36`).
    93	
    94	`name`/`spiritualName`/`bio`/`location` — это VO, вся валидация и нормализация внутри них; Entity их не проверяет (`docs/rules.md:39`).
    95	
    96	### Enum-ы User
    97	
    98	```text
    99	UserStatus: string
   100	  WaitingEmailConfirmation   # создан, ждёт подтверждения почты
   101	  Active                     # подтверждён, активен
   102	  Banned                     # забанен (детали в user_bans)
   103	  Deleted                    # мягко удалён (deleted_at заполнен)
   104	
   105	UserVerification: string     # «галочка», в будущем платная услуга
   106	  Unverified
   107	  Verified
   108	```
   109	
   110	`status` и `verification` — две независимые оси: статус = жизненный цикл аккаунта, verification = бейдж доверия. Закрытые наборы → enum (`docs/rules.md:30`), исчерпывающий `match` без `default` (`docs/rules.md:16`).
   111	
   112	### Value Object-ы и правила валидации
   113	
   114	Все простые VO следуют паттерну проекта: `final readonly class`, `Stringable`, `JsonSerializable`, приватный конструктор, фабрика `fromString()` с валидацией, `value()`, `equals()`; бросают `InvalidDomainValueException` (500), не `ValidationException` (`docs/rules.md:37`, `docs/rules.md:40`). На HTTP-границе Filter/Handler проверяет вход и отдаёт 422 до построения VO (как `AbstractIntegerValue::supports()` в `app/src/Shared/Domain/ValueObject/AbstractIntegerValue.php:36`).
   115	
   116	| VO | Модуль | Тип | Валидация |
   117	|---|---|---|---|
   118	| `Email` | User/Domain | строка | trim+lower, формат RFC (`filter_var`), ≤254, не пусто |
   119	| `UserNickname` | User/Domain | строка | lower, `^[a-z0-9](?:[a-z0-9._-]{1,28})[a-z0-9]$`, без `..`, 3–30 |
   120	| `UserName` | User/Domain | строка, NFC | whitelist `\p{L}\p{M}` + пробел/дефис/апостроф, 1–100, схлопывание пробелов |
   121	| `UserSpiritualName` | User/Domain | null-object | как `UserName`, `none()` для пустого |
   122	| `UserBio` | User/Domain | null-object | без `\p{Cc}` (кроме `\n`), эмодзи разрешены, ≤500, `none()` |
   123	| `UserLocation` | User/Domain | null-object | whitelist `\p{L}\p{N}` + пробел/`.,-`, без эмодзи (`\p{So}\p{Cs}` запрещены), ≤100, `none()` |
   124	| `UserTimezone` | User/Domain | строка | `in_array($tz, \DateTimeZone::listIdentifiers(), true)` |
   125	| `UserAvatar` | User/Domain | null-object | `none()` / `pointingTo(string mediaId)` (UUID v7); `value(): ?string` |
   126	| `UserDeletion` | User/Domain | null-object | `active()` / `at(\DateTimeImmutable)`; predicate `isDeleted()` |
   127	| `BanReason` | User/Domain | строка | trim, не пусто, ≤500 |
   128	| `BanExpiration` | User/Domain | null-object | `permanent()` / `until(\DateTimeImmutable)` |
   129	| `UserBanId` | User/Domain | UUID v7 | `extends AbstractUuidV7Id` |
   130	| `RoleId`/`PermissionId` | Access/Domain | UUID v7 | `extends AbstractUuidV7Id` |
   131	| `RoleSlug`/`PermissionSlug` | Access/Domain | строка | `^[a-z][a-z0-9.]*[a-z0-9]$` (напр. `admin`, `user.ban`) |
   132	
   133	Запрет эмодзи/спецсимволов делаем **whitelist-подходом** (разрешаем только нужные категории Unicode через PCRE `\p{...}`), а не blacklist-ом эмодзи — так автоматически отсекаются и эмодзи, и неожиданные служебные символы. PCRE `\p{L}`/`\p{N}`/`\p{So}` встроены в PHP, внешних пакетов не требуется. NFC-нормализация имён желательна, но `ext-intl` в зависимостях нет (`composer.json:12-16`, есть только `ext-mbstring`); на старте нормализуем через `mbstring` (trim, схлопывание пробелов) — риск: визуально одинаковые имена в разных Unicode-формах будут считаться разными. Снятие риска (опционально, решение плана): добавить `ext-intl` и `Normalizer::normalize(..., FORM_C)` в фабриках текстовых VO.
   134	
   135	### Аватар: связь с `Media` через `Application`, без нарушения границ
   136	
   137	Аватар — часть `User`, `Media` про аватар не знает (`docs/arch.md:156`). Публичный API `Media/Application` принимает примитивы (`CheckMediaIsImageQuery:9` — `string $mediaId`; `MakeMediaPermanentCommand:9-11` — `string $userId, string $mediaId`), поэтому `User` хранит ссылку собственным VO `UserAvatar` и передаёт `value()` на границу, **не импортируя `Media\Domain`**. `MediaId` переносить в `Shared` не нужно.
   138	
   139	Поток (ровно пример из `docs/arch.md:150-154`):
   140	
   141	```text
   142	User/Application/SetUserAvatar
   143	  -> Media/Application CheckMediaIsImage(mediaId)    # существует и это картинка
   144	  -> Media/Application MakeMediaPermanent(userId, mediaId)  # снять expiresAt, иначе cleanup удалит аватар
   145	  -> User/Domain User::setAvatar(UserAvatar::pointingTo(mediaId))
   146	  -> persist
   147	```
   148	
   149	`MakeMediaPermanent` обязателен: загруженное медиа временное (`expiresAt = now + 1 day`, `docs/researches/2026-05-20_18-42_media-domain.md:455-463`); без перевода в permanent аватар вычистит фоновая очистка. На уровне БД — `users.avatar_media_id` FK → `media(id)` `ON DELETE RESTRICT` (БД запрещает удалить медиа, пока оно чей-то аватар — согласуется с принципом media-домена «Media нельзя удалить, пока есть связь», `docs/researches/2026-05-20_18-42_media-domain.md:425-433`). Смена/снятие аватара — отдельный сценарий, который при необходимости просит `Media/Application` удалить старый объект.
   150	
   151	Отвергнутая альтернатива: перенос `MediaId` в `Shared` и хранение `MediaId` в `User`. Не нужен, потому что граница `Media` уже принимает строки; перенос дал бы лишний рефактор `Media` без выгоды.
   152	
   153	### Мягкое удаление
   154	
   155	Удаление аккаунта — soft-delete: `deleted_at` + `status = Deleted`, физически строка не удаляется. Состояние в домене выражается null-object `UserDeletion` (`active()`/`at(dt)`), а не `?datetime` (`docs/rules.md:21`). Это отличается от решения по `Media` (там hard-delete, `docs/researches/2026-05-20_18-42_media-domain.md:72`) — осознанно: у пользователя есть юридические/UX-причины сохранять аккаунт (история, восстановление, аудит модерации).
   156	
   157	Открытый продуктовый момент, вынесенный в план: освобождение `email`/`nickname` удалённого пользователя. На старте — НЕ освобождаем (уникальный индекс держит занятость), потому что это проще и безопаснее для восстановления. Если потребуется переиспользование — отдельный сценарий анонимизации (перезапись `email`/`nickname` на синтетические при удалении). Риск молчаливого «занятого» псевдонима принимаем на старте, фиксируем здесь как известное ограничение.
   158	
   159	### Баны: отдельная таблица-журнал
   160	
   161	`user_bans` — журнал (история), а не одно поле в `users` (выбор пользователя). Текущее состояние денормализовано в `users.status = Banned` для быстрых проверок.
   162	
   163	```text
   164	UserBan (user_bans)
   165	  id            UserBanId (UUID v7)
   166	  userId        UserId            -> users.id  (FK CASCADE)
   167	  bannedById    UserId            кто забанил (админ)
   168	  reason        BanReason
   169	  expiresAt     BanExpiration     null-object: permanent / until(dt)
   170	  liftedAt      null-object       снят ли досрочно (когда)
   171	  liftedById    UserId | none     кто снял
   172	  createdAt / updatedAt
   173	```
   174	
   175	Активный бан — запись, где `liftedAt` пуст и (`expiresAt` permanent или в будущем). `BanUser`: создаёт `UserBan` + `User::ban()` (`status = Banned`) в одной транзакции (`#[Transactional]`). `UnbanUser`: проставляет `liftedAt`/`liftedById` + `User::unban()` (`status = Active`). FK `user_bans.user_id` → `users.id` `ON DELETE CASCADE` (журнал живёт ровно столько, сколько строка пользователя; при soft-delete строка остаётся).
   176	
   177	### Модуль `Access`: роли, права, RBAC
   178	
   179	Полные таблицы-справочники (выбор пользователя), но **источник истины — код**: канон ролей и прав определён enum `RoleName`/`PermissionName`, а инфраструктурный сидер (`Access/Infrastructure/Bootloader`) создаёт/обновляет строки `roles`/`permissions`/`role_permissions` из этого enum при деплое. Так совмещаем требование «таблицы-справочники» с правилами «enum вместо строк» и «создаются только из кода» (`docs/rules.md:30`).
   180	
   181	```text
   182	roles            id (RoleId), slug (RoleSlug)                 UNIQUE(slug)
   183	permissions      id (PermissionId), slug (PermissionSlug)     UNIQUE(slug)
   184	role_permissions role_id -> roles.id, permission_id -> permissions.id   (PK составной, FK CASCADE)
   185	user_roles       user_id (UserId), role_id -> roles.id        (PK составной, FK CASCADE)
   186	```
   187	
   188	Канон (стартовый, расширяется в плане):
   189	
   190	```text
   191	RoleName: admin
   192	PermissionName: user.ban, user.verify, user.role.assign, ...   # уточняется при реализации сценариев
   193	admin -> все права
   194	```
   195	
   196	Проверки доступа — Query в `Access/Application`: `CheckUserHasPermission(userId, PermissionName)`, `GetUserRoles(userId)`, `GetUserPermissions(userId)`. Они грузят роли пользователя через `UserRoleRepository`, разворачивают в права через `role_permissions` и сравнивают с `PermissionName`. Назначение роли — `AssignRoleToUser(userId, RoleName)`.
   197	
   198	По умолчанию пользователь **без роли** (нет строки в `user_roles`) — отсутствие роли = нет прав. Роль `admin` обязательна и создаётся сидером. `User/Application` при необходимости назначить роль вызывает `Access/Application/AssignRoleToUser` (кросс-модульно через Application, `docs/arch.md:131`).
   199	
   200	Связь `user_roles` хранит `UserId` без FK на `users` в строгом ORM-смысле кросс-модульно: на уровне БД допустим FK `user_roles.user_id` → `users.id` `ON DELETE CASCADE` как инвариант целостности (одна БД монолита), но `Access` в коде не обращается к таблице/репозиторию `User` (`docs/arch.md:141-146`).
   201	
   202	### Уникальность и индексы
   203	
   204	`email` и `nickname` уникальны без учёта регистра. Решение: тип PostgreSQL `citext` + `UNIQUE` (либо `text` + уникальный функциональный индекс по `lower(...)`). VO дополнительно нормализуют значение в нижний регистр до записи, поэтому конфликт регистра невозможен и на уровне домена. Проверку занятости делаем check-then-act в Handler-е (`UserRepository::findByEmail()`/`findByNickname()`), при гонке дубликат ловит уникальный индекс как 500 (`docs/rules.md:47`).
   205	
   206	### Что было упущено в исходной структуре (предложения, принятые в домен)
   207	
   208	```text
   209	preferredLocale   язык интерфейса/писем (ru/en) — проект многоязычный (LocaleMiddleware, docs/arch.md:382)
   210	timezone          для расписаний/времени уведомлений
   211	status = Deleted  + deleted_at — мягкое удаление
   212	user_bans         журнал банов: кто, кого, почему, до когда, когда снят
   213	verification      выделено в отдельную ось от status (бейдж ≠ жизненный цикл)
   214	canon enum        RoleName/PermissionName как источник истины для сидера
   215	citext/lower      явная стратегия регистронезависимой уникальности email/nickname
   216	```
   217	
   218	### Границы (итоговая схема зависимостей)
   219	
   220	```text
   221	User/Domain        -> Shared/Domain (UserId, Locale), свой Domain
   222	User/Application    -> User/Domain, User/Repository, Media/Application, Access/Application
   223	Access/Domain       -> Shared/Domain (UserId), свой Domain
   224	Access/Application  -> Access/Domain, Access/Repository
   225	Media               про аватар/пользователя-как-профиль не знает; принимает примитивы на границе
   226	```
   227	
   228	## Ответы на вопросы
   229	
   230	```text
   231	Как моделировать роли и права?
   232	  Полные таблицы-справочники: roles, permissions, role_permissions, user_roles.
   233	  Источник истины — enum RoleName/PermissionName в коде; сид из кода при деплое.
   234	
   235	Где разместить роли, права и проверки доступа?
   236	  Отдельный модуль Access (рекомендация принята). User обращается к Access/Application.
   237	  Плюсы/минусы разобраны: чистая граница и переиспользование будущим Auth против чуть большей
   238	  структуры на старте. UserId уже в Shared, поэтому разделение бесшовно.
   239	
   240	Как обрабатывать удаление аккаунта?
   241	  Soft-delete: deleted_at + status = Deleted. Физически строку не удаляем
   242	  (в отличие от hard-delete в Media). email/nickname на старте не освобождаем.
   243	
   244	Какие поля добавить сверх списка?
   245	  Язык/локаль (preferredLocale), таймзона (timezone),
   246	  метаданные бана — отдельной таблицей user_bans (журнал: кто/кого/почему/срок/снятие).
   247	
   248	Аутентификация?
   249	  Не проектируем сейчас. Статус WaitingEmailConfirmation и переход confirmEmail()
   250	  заложены в домен, но сам verification-flow (коды/токены) — вне рамок.
   251	
   252	Verified vs статус подтверждения почты?
   253	  Это разные оси: UserVerification (бейдж, будущая платная услуга) и
   254	  UserStatus.WaitingEmailConfirmation (жизненный цикл). Разделены.
   255	
   256	Аватар — поле или таблица связи?
   257	  Поле avatar_media_id (1:1, «аватар один»). FK -> media RESTRICT.
   258	  Поток через Media/Application (CheckMediaIsImage + MakeMediaPermanent).
   259	```
   260	
   261	## Итог
   262	
   263	Делаем два модуля. **`User`**: Entity `User` (профиль, `email`/`nickname` через citext-уникальность, статус `UserStatus`, бейдж `UserVerification`, локаль/таймзона, аватар как null-object VO `UserAvatar` со ссылкой на `Media` через примитив на границе, soft-delete через `UserDeletion`), плюс Entity `UserBan` как журнал модерации. **`Access`**: справочники `roles`/`permissions`/`role_permissions`/`user_roles` с источником истины в enum `RoleName`/`PermissionName` и сидером из кода; проверки доступа — Query в `Access/Application`; пользователь по умолчанию без роли, роль `admin` сидится.
   264	
   265	Все доменные значения — VO/enum по паттерну проекта (приватный конструктор + фабрика с валидацией + `value()`/`equals()`, `InvalidDomainValueException`), запрет эмодзи/спецсимволов — whitelist через PCRE `\p{...}` без новых зависимостей. Новых внешних пакетов не требуется; `ext-intl` для строгой NFC-нормализации имён — опциональное усиление на этапе плана.
   266	
   267	Следующий шаг — `eda-plan` на реализацию модуля `User` и модуля `Access` по этому исследованию: миграции, Entity + VO + typecast, репозитории, базовые Command/Query (создание/обновление профиля, установка аватара, бан/разбан, назначение роли, проверка прав) и сидер ролей/прав. Конкретные HTTP-сценарии и интеграционные тесты роутов проектируются в плане.

codex
Основные факты собраны: исследование достаточно конкретное по пользовательскому потоку, но есть ошибки в версиях/источниках, в описании header-транспорта Spiral и в привязке к текущему коду `User`. Сформирую список только по проблемам в указанном формате.
exec
/bin/zsh -lc "nl -ba app/src/Shared/Infrastructure/Framework/Bootloader/AppBootloader.php | sed -n '1,140p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Shared\Infrastructure\Framework\Bootloader;
     6	
     7	use Spiral\Bootloader\DomainBootloader;
     8	use Spiral\Cycle\Interceptor\CycleInterceptor;
     9	use Spiral\DataGrid\Interceptor\GridInterceptor;
    10	use Spiral\Domain\GuardInterceptor;
    11	use Spiral\Interceptors\HandlerInterface;
    12	use GianTiaga\SpiralApiErrors\Interceptor\ApiExceptionInterceptor;
    13	use GianTiaga\SpiralOpenApi\Response\Interceptor\HttpResponseInterceptor;
    14	
    15	/**
    16	 * @link https://spiral.dev/docs/http-interceptors
    17	 */
    18	final class AppBootloader extends DomainBootloader
    19	{
    20	    protected const array INTERCEPTORS = [
    21	        CycleInterceptor::class,
    22	        GridInterceptor::class,
    23	        GuardInterceptor::class,
    24	        HttpResponseInterceptor::class,
    25	        ApiExceptionInterceptor::class,
    26	    ];
    27	
    28	    #[\Override]
    29	    public function defineSingletons(): array
    30	    {
    31	        return [
    32	            ...parent::defineSingletons(),
    33	            HandlerInterface::class => [self::class, 'domainCore'],
    34	        ];
    35	    }
    36	}

exec
/bin/zsh -lc 'find app/database/migrations -type f | sort | tail -20 | xargs rg -n "users|preferred_locale|locale|timezone|citext|user_roles|roles|permissions"' in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
app/database/migrations/20260613.143901_0_create_user_domain_tables.php:15:        $this->table('users')
app/database/migrations/20260613.143901_0_create_user_domain_tables.php:26:            ->addColumn('locale', 'string', ['length' => 8, 'nullable' => false])
app/database/migrations/20260613.143901_0_create_user_domain_tables.php:56:                'users',
app/database/migrations/20260613.143901_0_create_user_domain_tables.php:73:                'users',
app/database/migrations/20260613.143901_0_create_user_domain_tables.php:85:        $this->table('users')->drop();
app/database/migrations/20260613.143902_0_create_access_domain_tables.php:15:        $this->table('roles')
app/database/migrations/20260613.143902_0_create_access_domain_tables.php:24:        $this->table('permissions')
app/database/migrations/20260613.143902_0_create_access_domain_tables.php:33:        $this->table('role_permissions')
app/database/migrations/20260613.143902_0_create_access_domain_tables.php:40:                'roles',
app/database/migrations/20260613.143902_0_create_access_domain_tables.php:46:                'permissions',
app/database/migrations/20260613.143902_0_create_access_domain_tables.php:53:        $this->table('user_roles')
app/database/migrations/20260613.143902_0_create_access_domain_tables.php:60:                'users',
app/database/migrations/20260613.143902_0_create_access_domain_tables.php:66:                'roles',
app/database/migrations/20260613.143902_0_create_access_domain_tables.php:76:        $this->table('user_roles')->drop();
app/database/migrations/20260613.143902_0_create_access_domain_tables.php:77:        $this->table('role_permissions')->drop();
app/database/migrations/20260613.143902_0_create_access_domain_tables.php:78:        $this->table('permissions')->drop();
app/database/migrations/20260613.143902_0_create_access_domain_tables.php:79:        $this->table('roles')->drop();

exec
/bin/zsh -lc "rg -n \"Interceptor|interceptors|RateLimited|Attribute\" app/src packages app/config | sed -n '1,220p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 574ms:
ass InvalidMiddlewareAttribute extends HandlerMiddlewareAttribute
packages/spiral-cqrs/tests/Bus/CommandBusTest.php:496:        HandlerMiddlewareAttribute $attribute,
packages/spiral-cqrs/tests/Bus/CommandBusTest.php:500:        if (!$attribute instanceof CustomMiddlewareAttribute) {
packages/spiral-cqrs/tests/Bus/CommandBusTest.php:504:        if (!$input instanceof MiddlewareAttributeCommand) {
packages/spiral-cqrs/src/Middleware/LogOperationMiddleware.php:8:use GianTiaga\SpiralCqrs\Attribute\HandlerMiddlewareAttribute;
packages/spiral-cqrs/src/Middleware/LogOperationMiddleware.php:9:use GianTiaga\SpiralCqrs\Attribute\LogOperation;
packages/spiral-cqrs/src/Middleware/LogOperationMiddleware.php:29:        HandlerMiddlewareAttribute $attribute,
packages/spiral-cqrs/src/Middleware/TransactionalMiddleware.php:8:use GianTiaga\SpiralCqrs\Attribute\HandlerMiddlewareAttribute;
packages/spiral-cqrs/src/Middleware/TransactionalMiddleware.php:9:use GianTiaga\SpiralCqrs\Attribute\Transactional;
packages/spiral-cqrs/src/Middleware/TransactionalMiddleware.php:30:        HandlerMiddlewareAttribute $attribute,
packages/spiral-cqrs/src/PHPStan/Rules/RequireCqrsHandlerCallableRule.php:16:use GianTiaga\SpiralCqrs\Attribute\Transactional;
packages/spiral-cqrs/src/PHPStan/Rules/RequireCqrsHandlerCallableRule.php:66:        if (!$this->hasTransactionalAttribute(handlerCall: $handlerArgument->value, scope: $scope)) {
packages/spiral-cqrs/src/PHPStan/Rules/RequireCqrsHandlerCallableRule.php:124:    private function hasTransactionalAttribute(MethodCall $handlerCall, Scope $scope): bool
packages/spiral-cqrs/src/PHPStan/Rules/RequireCqrsHandlerCallableRule.php:142:            ->getAttributes(Transactional::class) !== [];
packages/spiral-cqrs/tests/PHPStan/Fixtures/CqrsHandlerCallableForbidden.fixture:7:use GianTiaga\SpiralCqrs\Attribute\Transactional;
packages/spiral-cqrs/tests/PHPStan/Fixtures/CqrsHandlerCallableAllowed.php:7:use GianTiaga\SpiralCqrs\Attribute\LogOperation;
packages/spiral-cqrs/composer.lock:2086:                    "Spiral\\Attributes\\": "src"
packages/spiral-cqrs/composer.lock:2255:                "spiral/interceptors": "self.version",
packages/spiral-cqrs/composer.lock:2372:                    "Spiral\\Interceptors\\": "src/Interceptors/src"
packages/spiral-cqrs/vendor/composer/installed.php:719:        'spiral/interceptors' => array(
packages/spiral-cqrs/vendor/composer/installed.json:3980:                    "Spiral\\Attributes\\": "src"
packages/spiral-cqrs/vendor/composer/installed.json:4153:                "spiral/interceptors": "self.version",
packages/spiral-cqrs/vendor/composer/installed.json:4272:                    "Spiral\\Interceptors\\": "src/Interceptors/src"
packages/spiral-cqrs/vendor/composer/autoload_static.php:77:            'Spiral\\Interceptors\\' => 20,
packages/spiral-cqrs/vendor/composer/autoload_static.php:97:            'Spiral\\Attributes\\' => 18,
packages/spiral-cqrs/vendor/composer/autoload_static.php:329:        'Spiral\\Interceptors\\' =>
packages/spiral-cqrs/vendor/composer/autoload_static.php:331:            0 => __DIR__ . '/..' . '/spiral/framework/src/Interceptors/src',
packages/spiral-cqrs/vendor/composer/autoload_static.php:411:        'Spiral\\Attributes\\' =>
packages/spiral-cqrs/vendor/composer/autoload_static.php:549:        'Attribute' => __DIR__ . '/..' . '/symfony/polyfill-php80/Resources/stubs/Attribute.php',
packages/spiral-cqrs/vendor/composer/autoload_static.php:576:        'Nette\\PhpGenerator\\Attribute' => __DIR__ . '/..' . '/nette/php-generator/src/PhpGenerator/Attribute.php',
packages/spiral-cqrs/vendor/composer/autoload_static.php:605:        'Nette\\PhpGenerator\\Traits\\AttributeAware' => __DIR__ . '/..' . '/nette/php-generator/src/PhpGenerator/Traits/AttributeAware.php',
packages/spiral-cqrs/vendor/composer/autoload_static.php:906:        'PHPUnit\\Framework\\Attributes\\After' => __DIR__ . '/..' . '/phpunit/phpunit/src/Framework/Attributes/After.php',
packages/spiral-cqrs/vendor/composer/autoload_static.php:907:        'PHPUnit\\Framework\\Attributes\\AfterClass' => __DIR__ . '/..' . '/phpunit/phpunit/src/Framework/Attributes/AfterClass.php',
packages/spiral-cqrs/vendor/composer/autoload_static.php:908:        'PHPUnit\\Framework\\Attributes\\AllowMockObjectsWithoutExpectations' => __DIR__ . '/..' . '/phpunit/phpunit/src/Framework/Attributes/AllowMockObjectsWithoutExpectations.php',
packages/spiral-cqrs/vendor/composer/autoload_static.php:909:        'PHPUnit\\Framework\\Attributes\\BackupGlobals' => __DIR__ . '/..' . '/phpunit/phpunit/src/Framework/Attributes/BackupGlobals.php',
packages/spiral-cqrs/vendor/composer/autoload_static.php:910:        'PHPUnit\\Framework\\Attributes\\BackupStaticProperties' => __DIR__ . '/..' . '/phpunit/phpunit/src/Framework/Attributes/BackupStaticProperties.php',
packages/spiral-cqrs/vendor/composer/autoload_static.php:911:        'PHPUnit\\Framework\\Attributes\\Before' => __DIR__ . '/..' . '/phpunit/phpunit/src/Framework/Attributes/Before.php',
packages/spiral-cqrs/vendor/composer/autoload_static.php:912:        'PHPUnit\\Framework\\Attributes\\BeforeClass' => __DIR__ . '/..' . '/phpunit/phpunit/src/Framework/Attributes/BeforeClass.php',
packages/spiral-cqrs/vendor/composer/autoload_static.php:913:        'PHPUnit\\Framework\\Attributes\\CoversClass' => __DIR__ . '/..' . '/phpunit/phpunit/src/Framework/Attributes/CoversClass.php',
packages/spiral-cqrs/vendor/composer/autoload_static.php:914:        'PHPUnit\\Framework\\Attributes\\CoversClassesThatExtendClass' => __DIR__ . '/..' . '/phpunit/phpunit/src/Framework/Attributes/CoversClassesThatExtendClass.php',
packages/spiral-cqrs/vendor/composer/autoload_static.php:915:        'PHPUnit\\Framework\\Attributes\\CoversClassesThatImplementInterface' => __DIR__ . '/..' . '/phpunit/phpunit/src/Framework/Attributes/CoversClassesThatImplementInterface.php',
packages/spiral-cqrs/vendor/composer/autoload_static.php:916:        'PHPUnit\\Framework\\Attributes\\CoversFunction' => __DIR__ . '/..' . '/phpunit/phpunit/src/Framework/Attributes/CoversFunction.php',
packages/spiral-cqrs/vendor/composer/autoload_static.php:917:        'PHPUnit\\Framework\\Attributes\\CoversMethod' => __DIR__ . '/..' . '/phpunit/phpunit/src/Framework/Attributes/CoversMethod.php',
packages/spiral-cqrs/vendor/composer/autoload_static.php:918:        'PHPUnit\\Framework\\Attributes\\CoversNamespace' => __DIR__ . '/..' . '/phpunit/phpunit/src/Framework/Attributes/CoversNamespace.php',
packages/spiral-cqrs/vendor/composer/autoload_static.php:919:        'PHPUnit\\Framework\\Attributes\\CoversNothing' => __DIR__ . '/..' . '/phpunit/phpunit/src/Framework/Attributes/CoversNothing.php',
packages/spiral-cqrs/vendor/composer/autoload_static.php:920:        'PHPUnit\\Framework\\Attributes\\CoversTrait' => __DIR__ . '/..' . '/phpunit/phpunit/src/Framework/Attributes/CoversTrait.php',
packages/spiral-cqrs/vendor/composer/autoload_static.php:921:        'PHPUnit\\Framework\\Attributes\\DataProvider' => __DIR__ . '/..' . '/phpunit/phpunit/src/Framework/Attributes/DataProvider.php',
packages/spiral-cqrs/vendor/composer/autoload_static.php:922:        'PHPUnit\\Framework\\Attributes\\DataProviderClosure' => __DIR__ . '/..' . '/phpunit/phpunit/src/Framework/Attributes/DataProviderClosure.php',
packages/spiral-cqrs/vendor/composer/autoload_static.php:923:        'PHPUnit\\Framework\\Attributes\\DataProviderExternal' => __DIR__ . '/..' . '/phpunit/phpunit/src/Framework/Attributes/DataProviderExternal.php',
packages/spiral-cqrs/vendor/composer/autoload_static.php:924:        'PHPUnit\\Framework\\Attributes\\Depends' => __DIR__ . '/..' . '/phpunit/phpunit/src/Framework/Attributes/Depends.php',
packages/spiral-cqrs/vendor/composer/autoload_static.php:925:        'PHPUnit\\Framework\\Attributes\\DependsExternal' => __DIR__ . '/..' . '/phpunit/phpunit/src/Framework/Attributes/DependsExternal.php',
packages/spiral-cqrs/vendor/composer/autoload_static.php:926:        'PHPUnit\\Framework\\Attributes\\DependsExternalUsingDeepClone' => __DIR__ . '/..' . '/phpunit/phpunit/src/Framework/Attributes/DependsExternalUsingDeepClone.php',
packages/spiral-cqrs/vendor/composer/autoload_static.php:927:        'PHPUnit\\Framework\\Attributes\\DependsExternalUsingShallowClone' => __DIR__ . '/..' . '/phpunit/phpunit/src/Framework/Attributes/DependsExternalUsingShallowClone.php',
packages/spiral-cqrs/vendor/composer/autoload_static.php:928:        'PHPUnit\\Framework\\Attributes\\DependsOnClass' => __DIR__ . '/..' . '/phpunit/phpunit/src/Framework/Attributes/DependsOnClass.php',
packages/spiral-cqrs/vendor/composer/autoload_static.php:929:        'PHPUnit\\Framework\\Attributes\\DependsOnClassUsingDeepClone' => __DIR__ . '/..' . '/phpunit/phpunit/src/Framework/Attributes/DependsOnClassUsingDeepClone.php',
packages/spiral-cqrs/vendor/composer/autoload_static.php:930:        'PHPUnit\\Framework\\Attributes\\DependsOnClassUsingShallowClone' => __DIR__ . '/..' . '/phpunit/phpunit/src/Framework/Attributes/DependsOnClassUsingShallowClone.php',
packages/spiral-cqrs/vendor/composer/autoload_static.php:931:        'PHPUnit\\Framework\\Attributes\\DependsUsingDeepClone' => __DIR__ . '/..' . '/phpunit/phpunit/src/Framework/Attributes/DependsUsingDeepClone.php',
packages/spiral-cqrs/vendor/composer/autoload_static.php:932:        'PHPUnit\\Framework\\Attributes\\DependsUsingShallowClone' => __DIR__ . '/..' . '/phpunit/phpunit/src/Framework/Attributes/DependsUsingShallowClone.php',
packages/spiral-cqrs/vendor/composer/autoload_static.php:933:        'PHPUnit\\Framework\\Attributes\\DisableReturnValueGenerationForTestDoubles' => __DIR__ . '/..' . '/phpunit/phpunit/src/Framework/Attributes/DisableReturnValueGenerationForTestDoubles.php',
packages/spiral-cqrs/vendor/composer/autoload_static.php:934:        'PHPUnit\\Framework\\Attributes\\DoesNotPerformAssertions' => __DIR__ . '/..' . '/phpunit/phpunit/src/Framework/Attributes/DoesNotPerformAssertions.php',
packages/spiral-cqrs/vendor/composer/autoload_static.php:935:        'PHPUnit\\Framework\\Attributes\\ExcludeGlobalVariableFromBackup' => __DIR__ . '/..' . '/phpunit/phpunit/src/Framework/Attributes/ExcludeGlobalVariableFromBackup.php',
packages/spiral-cqrs/vendor/composer/autoload_static.php:936:        'PHPUnit\\Framework\\Attributes\\ExcludeStaticPropertyFromBackup' => __DIR__ . '/..' . '/phpunit/phpunit/src/Framework/Attributes/ExcludeStaticPropertyFromBackup.php',
packages/spiral-cqrs/vendor/composer/autoload_static.php:937:        'PHPUnit\\Framework\\Attributes\\Group' => __DIR__ . '/..' . '/phpunit/phpunit/src/Framework/Attributes/Group.php',
packages/spiral-cqrs/vendor/composer/autoload_static.php:938:        'PHPUnit\\Framework\\Attributes\\IgnoreDeprecations' => __DIR__ . '/..' . '/phpunit/phpunit/src/Framework/Attributes/IgnoreDeprecations.php',
packages/spiral-cqrs/vendor/composer/autoload_static.php:939:        'PHPUnit\\Framework\\Attributes\\IgnorePhpunitDeprecations' => __DIR__ . '/..' . '/phpunit/phpunit/src/Framework/Attributes/IgnorePhpunitDeprecations.php',
packages/spiral-cqrs/vendor/composer/autoload_static.php:940:        'PHPUnit\\Framework\\Attributes\\IgnorePhpunitWarnings' => __DIR__ . '/..' . '/phpunit/phpunit/src/Framework/Attributes/IgnorePhpunitWarnings.php',
packages/spiral-cqrs/vendor/composer/autoload_static.php:941:        'PHPUnit\\Framework\\Attributes\\Large' => __DIR__ . '/..' . '/phpunit/phpunit/src/Framework/Attributes/Large.php',
packages/spiral-cqrs/vendor/composer/autoload_static.php:942:        'PHPUnit\\Framework\\Attributes\\Medium' => __DIR__ . '/..' . '/phpunit/phpunit/src/Framework/Attributes/Medium.php',
packages/spiral-cqrs/vendor/composer/autoload_static.php:943:        'PHPUnit\\Framework\\Attributes\\PostCondition' => __DIR__ . '/..' . '/phpunit/phpunit/src/Framework/Attributes/PostCondition.php',
packages/spiral-cqrs/vendor/composer/autoload_static.php:944:        'PHPUnit\\Framework\\Attributes\\PreCondition' => __DIR__ . '/..' . '/phpunit/phpunit/src/Framework/Attributes/PreCondition.php',
packages/spiral-cqrs/vendor/composer/autoload_static.php:945:        'PHPUnit\\Framework\\Attributes\\PreserveGlobalState' => __DIR__ . '/..' . '/phpunit/phpunit/src/Framework/Attributes/PreserveGlobalState.php',
packages/spiral-cqrs/vendor/composer/autoload_static.php:946:        'PHPUnit\\Framework\\Attributes\\RequiresEnvironmentVariable' => __DIR__ . '/..' . '/phpunit/phpunit/src/Framework/Attributes/RequiresEnvironmentVariable.php',
packages/spiral-cqrs/vendor/composer/autoload_static.php:947:        'PHPUnit\\Framework\\Attributes\\RequiresFunction' => __DIR__ . '/..' . '/phpunit/phpunit/src/Framework/Attributes/RequiresFunction.php',
packages/spiral-cqrs/vendor/composer/autoload_static.php:948:        'PHPUnit\\Framework\\Attributes\\RequiresMethod' => __DIR__ . '/..' . '/phpunit/phpunit/src/Framework/Attributes/RequiresMethod.php',
packages/spiral-cqrs/vendor/composer/autoload_static.php:949:        'PHPUnit\\Framework\\Attributes\\RequiresOperatingSystem' => __DIR__ . '/..' . '/phpunit/phpunit/src/Framework/Attributes/RequiresOperatingSystem.php',
packages/spiral-cqrs/vendor/composer/autoload_static.php:950:        'PHPUnit\\Framework\\Attributes\\RequiresOperatingSystemFamily' => __DIR__ . '/..' . '/phpunit/phpunit/src/Framework/Attributes/RequiresOperatingSystemFamily.php',
packages/spiral-cqrs/vendor/composer/autoload_static.php:951:        'PHPUnit\\Framework\\Attributes\\RequiresPhp' => __DIR__ . '/..' . '/phpunit/phpunit/src/Framework/Attributes/RequiresPhp.php',
packages/spiral-cqrs/vendor/composer/autoload_static.php:952:        'PHPUnit\\Framework\\Attributes\\RequiresPhpExtension' => __DIR__ . '/..' . '/phpunit/phpunit/src/Framework/Attributes/RequiresPhpExtension.php',
packages/spiral-cqrs/vendor/composer/autoload_static.php:953:        'PHPUnit\\Framework\\Attributes\\RequiresPhpunit' => __DIR__ . '/..' . '/phpunit/phpunit/src/Framework/Attributes/RequiresPhpunit.php',
packages/spiral-cqrs/vendor/composer/autoload_static.php:954:        'PHPUnit\\Framework\\Attributes\\RequiresPhpunitExtension' => __DIR__ . '/..' . '/phpunit/phpunit/src/Framework/Attributes/RequiresPhpunitExtension.php',
packages/spiral-cqrs/vendor/composer/autoload_static.php:955:        'PHPUnit\\Framework\\Attributes\\RequiresSetting' => __DIR__ . '/..' . '/phpunit/phpunit/src/Framework/Attributes/RequiresSetting.php',
packages/spiral-cqrs/vendor/composer/autoload_static.php:956:        'PHPUnit\\Framework\\Attributes\\RunInSeparateProcess' => __DIR__ . '/..' . '/phpunit/phpunit/src/Framework/Attributes/RunInSeparateProcess.php',
packages/spiral-cqrs/vendor/composer/autoload_static.php:957:        'PHPUnit\\Framework\\Attributes\\RunTestsInSeparateProcesses' => __DIR__ . '/..' . '/phpunit/phpunit/src/Framework/Attributes/RunTestsInSeparateProcesses.php',
packages/spiral-cqrs/vendor/composer/autoload_static.php:958:        'PHPUnit\\Framework\\Attributes\\Small' => __DIR__ . '/..' . '/phpunit/phpunit/src/Framework/Attributes/Small.php',
packages/spiral-cqrs/vendor/composer/autoload_static.php:959:        'PHPUnit\\Framework\\Attributes\\Test' => __DIR__ . '/..' . '/phpunit/phpunit/src/Framework/Attributes/Test.php',
packages/spiral-cqrs/vendor/composer/autoload_static.php:960:        'PHPUnit\\Framework\\Attributes\\TestDox' => __DIR__ . '/..' . '/phpunit/phpunit/src/Framework/Attributes/TestDox.php',
packages/spiral-cqrs/vendor/composer/autoload_static.php:961:        'PHPUnit\\Framework\\Attributes\\TestDoxFormatter' => __DIR__ . '/..' . '/phpunit/phpunit/src/Framework/Attributes/TestDoxFormatter.php',

codex
Проверка текущего кода добавила ещё один важный риск: в `User` сейчас колонка и свойство называются `locale`, а не `preferredLocale`, и слой `User/Application` пока отсутствует полностью. Это надо явно поправить в исследовании, иначе план будет строиться на несуществующих именах.
exec
/bin/zsh -lc "nl -ba docs/arch.md | sed -n '360,540p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
   360	ключи без модульного сегмента — домен `messages`. Доменные исключения без
   361	поддерживаемого 4xx-кода, включая `InvalidDomainValueException`, считаются
   362	внутренними ошибками: HTTP-ответ получает обычное сообщение 500, а исходное
   363	сообщение исключения пользователю не отдаётся. Ошибки Spiral Filter рендерятся через
   364	`GianTiaga\SpiralApiErrors\Filter\ApiValidationErrorsRenderer` по ключу
   365	`gian_tiaga.spiral_api_errors.validation_error` в JSON
   366	`{"message":"Validation error","code":422,"errors":[...]}` или
   367	`{"message":"Ошибка валидации","code":422,"errors":[...]}` в зависимости от
   368	текущего locale.
   369	
   370	`ApiExceptionInterceptor` работает только внутри цепочки controller/action.
   371	Ненайденные маршруты возникают раньше controller/action, поэтому их обрабатывает
   372	`GianTiaga\SpiralApiErrors\Middleware\RouteNotFoundMiddleware` в глобальной HTTP-цепочке.
   373	Он переводит ключ `gian_tiaga.spiral_api_errors.route_not_found` и возвращает JSON
   374	`{"message":"Route not found.","code":404}` или
   375	`{"message":"Маршрут не найден.","code":404}` в зависимости от текущего locale.
   376	Ошибки bootstrap и middleware, не связанные с router 404, остаются в зоне
   377	стандартного Spiral error handler.
   378	
   379	`packages/spiral-api-errors` и `packages/spiral-openapi` только читают текущий locale Spiral
   380	translator. Выбор языка пользователя реализован в request-слое приложения:
   381	`App\Shared\Infrastructure\Framework\Middleware\LocaleMiddleware` определяет локаль запроса
   382	из заголовка `Accept-Language`, пересечённого с белым списком `LocaleConfig.supported`
   383	(иначе `LocaleConfig.default`), и выставляет её в Spiral translator до контроллера и до
   384	`RouteNotFoundMiddleware`. Для не-HTTP контекстов (queue/console/Temporal) per-request
   385	локали нет — там перевод на границе не выполняется, исключение несёт ключ.
   386	
   387	### Поток консольной команды
   388	
   389	```text
   390	Console command
   391	  -> input arguments/options
   392	  -> Modules/{Module}/Application/Command DTO
   393	  -> CommandBus::dispatch(command, handler)
   394	  -> Handler::handle(Command)
   395	  -> console output
   396	```
   397	
   398	Console command не содержит бизнес-логику. Он только читает CLI-ввод,
   399	создаёт Command/Query и выводит результат.
   400	
   401	### Поток задачи очереди
   402	
   403	```text
   404	Queue payload
   405	  -> Modules/{Module}/Presentation/Job handler
   406	  -> Payload DTO
   407	  -> Modules/{Module}/Application/Command DTO
   408	  -> CommandBus::dispatch(command, handler)
   409	  -> Handler::handle(Command)
   410	```
   411	
   412	Job handler - технический адаптер очереди. Бизнес-сценарий находится в
   413	Application Handler.
   414	
   415	### Поток Temporal
   416	
   417	```text
   418	Temporal Workflow / Activity
   419	  -> Modules/{Module}/Presentation/Temporal adapter
   420	  -> Modules/{Module}/Application Command или Query
   421	  -> Bus
   422	  -> Handler
   423	```
   424	
   425	Temporal workflow/activity не становится доменным слоем. Это внешний runtime
   426	adapter, как HTTP controller или queue job.
   427	
   428	## Правила CQRS
   429	
   430	CQRS используется на уровне use-case-ов: Command изменяет состояние, Query читает
   431	данные. Каждый use-case живёт в отдельной папке действия внутри
   432	`Modules/{Module}/Application/Command/{Area}/{Action}` или
   433	`Modules/{Module}/Application/Query/{Area}/{Action}`.
   434	
   435	Command flow:
   436	
   437	```text
   438	Command DTO
   439	  -> CommandBus::dispatch(command, handler)
   440	  -> Handler::handle(Command)
   441	  -> Domain types
   442	  -> Entity
   443	  -> Repository
   444	  -> EntityManager::run()
   445	```
   446	
   447	Query flow:
   448	
   449	```text
   450	Query DTO
   451	  -> QueryBus::dispatch(query, handler)
   452	  -> Handler::handle(Query)
   453	  -> Repository read method
   454	  -> Entity / typed collection / Result DTO / PaginatedResult<T>
   455	```
   456	
   457	Command Handler создаёт VO, enum и доменные коллекции до передачи данных в
   458	Entity. Примитивы не передаются в `Entity::create()` и доменные методы Entity.
   459	
   460	Command Handler возвращает Entity, Result DTO или `void`. Query Handler
   461	возвращает Entity, типизированную коллекцию, Result DTO или `PaginatedResult<T>`.
   462	Query не изменяет состояние и не оборачивается в транзакцию. Если Query
   463	Handler помечен `#[Transactional]`, это ошибка контракта.
   464	
   465	Data Grid не используется как бизнесовый Query-слой. Фильтрация и сортировка в
   466	Query Handler должны быть явными.
   467	
   468	## Архитектура шины
   469	
   470	Инфраструктура шины живёт в локальном Composer-пакете `packages/spiral-cqrs` с namespace
   471	`GianTiaga\SpiralCqrs`. Приложение подключает `GianTiaga\SpiralCqrs\CommandBusInterface` и
   472	`GianTiaga\SpiralCqrs\QueryBusInterface` через Spiral DI-контейнер. Прикладные Command,
   473	Query и Handler остаются в модулях приложения.
   474	
   475	Bus принимает DTO и first-class callable на `Handler::handle(...)`. Return type
   476	берётся из `Handler::handle()`, поэтому PHPStan и IDE видят точный тип
   477	результата без ручного приведения.
   478	
   479	```php
   480	/**
   481	 * @template TCommand of object
   482	 * @template TResult
   483	 * @param TCommand $command
   484	 * @param callable(TCommand): TResult $handler
   485	 * @return TResult
   486	 */
   487	public function dispatch(object $command, callable $handler);
   488	```
   489	
   490	```text
   491	CommandBus: Command DTO -> Handler::handle(Command)
   492	QueryBus:   Query DTO -> Handler::handle(Query)
   493	```
   494	
   495	`#[Transactional]` на `Handler::handle()` включает транзакцию для Command. Если
   496	атрибута нет, Command выполняется без транзакции. `#[NonTransactional]` не
   497	используется.
   498	
   499	`#[LogOperation]` на `Handler::handle()` включает debug-лог старта и времени
   500	выполнения операции. Если атрибута нет, operation-log не пишется.
   501	
   502	CQRS-bus не проверяет конкретные классы атрибутов. Он собирает все атрибуты
   503	`Handler::handle()`, которые наследуются от
   504	`GianTiaga\SpiralCqrs\Attribute\HandlerMiddlewareAttribute`, берёт из них middleware-класс
   505	и создаёт middleware через контейнер. Зависимости вроде `LoggerInterface` или
   506	`DatabaseInterface` получает сам middleware через constructor injection, а не
   507	executor.
   508	
   509	Lifecycle hooks и after-commit callbacks не входят в CQRS-bus. Внешние
   510	побочные эффекты идут через transactional outbox.
   511	
   512	## События и outbox
   513	
   514	События, которые приводят к внешним побочным эффектам, публикуются через
   515	transactional outbox. К таким эффектам относятся сообщения в Centrifugo, email,
   516	push-уведомления, webhooks и любые интеграции, которые нельзя выполнять до
   517	финального commit-а бизнес-транзакции.
   518	
   519	Outbox используется не только для внешних интеграций, но и для внутренних
   520	отложенных шагов, которым нужна гарантированная доставка после commit-а. Пример —
   521	асинхронная обработка медиа: `Media` Application Handler в одной транзакции
   522	переводит медиа в `uploaded` и кладёт `MediaUploaded` в outbox, а relay запускает
   523	тяжёлую обработку (перекладка оригинала, конверсии на Imagick) уже после commit-а.
   524	Определяющий признак — требование «надёжно выполнить после commit», а не то,
   525	внешний эффект или внутренний.
   526	
   527	```text
   528	HTTP / Console / Job / Temporal
   529	  -> Modules/{Module}/Application Command Handler
   530	    -> Domain Entity / Repository
   531	    -> Modules/Outbox/Application/Contract/OutboxEventStoreContract::add(IntegrationEvent DTO)
   532	    -> EntityManager::run()
   533	  -> commit транзакции, если Handler помечен #[Transactional]
   534	
   535	Modules/Outbox relay
   536	  -> забирает pending outbox-события
   537	  -> кладёт задачу в RabbitMQ
   538	  -> RoadRunner jobs consumer запускает Job
   539	  -> общий queue interceptor помечает событие handled / failed
   540	```

exec
/bin/zsh -lc "nl -ba app/database/migrations/20260613.143902_0_create_access_domain_tables.php | sed -n '1,110p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace Migration;
     6	
     7	use Cycle\Migrations\Migration;
     8	
     9	class CreateAccessDomainTables extends Migration
    10	{
    11	    protected const DATABASE = null;
    12	
    13	    public function up(): void
    14	    {
    15	        $this->table('roles')
    16	            ->addColumn('id', 'uuid', ['nullable' => false])
    17	            ->addColumn('slug', 'string', ['length' => 64, 'nullable' => false])
    18	            ->addColumn('created_at', 'datetime', ['nullable' => false])
    19	            ->addColumn('updated_at', 'datetime', ['nullable' => false])
    20	            ->setPrimaryKeys(['id'])
    21	            ->addIndex(['slug'], ['unique' => true])
    22	            ->create();
    23	
    24	        $this->table('permissions')
    25	            ->addColumn('id', 'uuid', ['nullable' => false])
    26	            ->addColumn('slug', 'string', ['length' => 64, 'nullable' => false])
    27	            ->addColumn('created_at', 'datetime', ['nullable' => false])
    28	            ->addColumn('updated_at', 'datetime', ['nullable' => false])
    29	            ->setPrimaryKeys(['id'])
    30	            ->addIndex(['slug'], ['unique' => true])
    31	            ->create();
    32	
    33	        $this->table('role_permissions')
    34	            ->addColumn('id', 'uuid', ['nullable' => false])
    35	            ->addColumn('role_id', 'uuid', ['nullable' => false])
    36	            ->addColumn('permission_id', 'uuid', ['nullable' => false])
    37	            ->setPrimaryKeys(['id'])
    38	            ->addForeignKey(
    39	                ['role_id'],
    40	                'roles',
    41	                ['id'],
    42	                ['delete' => 'CASCADE', 'update' => 'CASCADE', 'indexCreate' => false],
    43	            )
    44	            ->addForeignKey(
    45	                ['permission_id'],
    46	                'permissions',
    47	                ['id'],
    48	                ['delete' => 'CASCADE', 'update' => 'CASCADE', 'indexCreate' => false],
    49	            )
    50	            ->addIndex(['role_id', 'permission_id'], ['unique' => true])
    51	            ->create();
    52	
    53	        $this->table('user_roles')
    54	            ->addColumn('id', 'uuid', ['nullable' => false])
    55	            ->addColumn('user_id', 'uuid', ['nullable' => false])
    56	            ->addColumn('role_id', 'uuid', ['nullable' => false])
    57	            ->setPrimaryKeys(['id'])
    58	            ->addForeignKey(
    59	                ['user_id'],
    60	                'users',
    61	                ['id'],
    62	                ['delete' => 'CASCADE', 'update' => 'CASCADE', 'indexCreate' => false],
    63	            )
    64	            ->addForeignKey(
    65	                ['role_id'],
    66	                'roles',
    67	                ['id'],
    68	                ['delete' => 'CASCADE', 'update' => 'CASCADE', 'indexCreate' => false],
    69	            )
    70	            ->addIndex(['user_id', 'role_id'], ['unique' => true])
    71	            ->create();
    72	    }
    73	
    74	    public function down(): void
    75	    {
    76	        $this->table('user_roles')->drop();
    77	        $this->table('role_permissions')->drop();
    78	        $this->table('permissions')->drop();
    79	        $this->table('roles')->drop();
    80	    }
    81	}

exec
/bin/zsh -lc "nl -ba app/database/migrations/20260613.143901_0_create_user_domain_tables.php | sed -n '1,110p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace Migration;
     6	
     7	use Cycle\Migrations\Migration;
     8	
     9	class CreateUserDomainTables extends Migration
    10	{
    11	    protected const DATABASE = null;
    12	
    13	    public function up(): void
    14	    {
    15	        $this->table('users')
    16	            ->addColumn('id', 'uuid', ['nullable' => false])
    17	            ->addColumn('name', 'string', ['length' => 100, 'nullable' => false])
    18	            ->addColumn('spiritual_name', 'string', ['length' => 100, 'nullable' => true])
    19	            ->addColumn('bio', 'text', ['nullable' => true])
    20	            ->addColumn('location', 'string', ['length' => 100, 'nullable' => true])
    21	            ->addColumn('email', 'string', ['length' => 254, 'nullable' => false])
    22	            ->addColumn('nickname', 'string', ['length' => 30, 'nullable' => false])
    23	            ->addColumn('avatar_media_id', 'uuid', ['nullable' => true])
    24	            ->addColumn('verification', 'string', ['length' => 32, 'nullable' => false])
    25	            ->addColumn('status', 'string', ['length' => 32, 'nullable' => false])
    26	            ->addColumn('locale', 'string', ['length' => 8, 'nullable' => false])
    27	            ->addColumn('deleted_at', 'datetime', ['nullable' => true])
    28	            ->addColumn('created_at', 'datetime', ['nullable' => false])
    29	            ->addColumn('updated_at', 'datetime', ['nullable' => false])
    30	            ->setPrimaryKeys(['id'])
    31	            ->addIndex(['email'], ['unique' => true])
    32	            ->addIndex(['nickname'], ['unique' => true])
    33	            ->addIndex(['status'])
    34	            ->addForeignKey(
    35	                ['avatar_media_id'],
    36	                'media',
    37	                ['id'],
    38	                ['delete' => 'RESTRICT', 'update' => 'CASCADE', 'indexCreate' => false],
    39	            )
    40	            ->create();
    41	
    42	        $this->table('user_bans')
    43	            ->addColumn('id', 'uuid', ['nullable' => false])
    44	            ->addColumn('user_id', 'uuid', ['nullable' => false])
    45	            ->addColumn('banned_by_id', 'uuid', ['nullable' => false])
    46	            ->addColumn('reason', 'string', ['length' => 500, 'nullable' => false])
    47	            ->addColumn('expires_at', 'datetime', ['nullable' => true])
    48	            ->addColumn('unbanned_at', 'datetime', ['nullable' => true])
    49	            ->addColumn('unbanned_by_id', 'uuid', ['nullable' => true])
    50	            ->addColumn('unbanned_reason', 'string', ['length' => 500, 'nullable' => true])
    51	            ->addColumn('created_at', 'datetime', ['nullable' => false])
    52	            ->addColumn('updated_at', 'datetime', ['nullable' => false])
    53	            ->setPrimaryKeys(['id'])
    54	            ->addForeignKey(
    55	                ['user_id'],
    56	                'users',
    57	                ['id'],
    58	                ['delete' => 'CASCADE', 'update' => 'CASCADE', 'indexCreate' => false],
    59	            )
    60	            ->addIndex(['user_id'])
    61	            ->create();
    62	
    63	        $this->table('reserved_nicknames')
    64	            ->addColumn('id', 'uuid', ['nullable' => false])
    65	            ->addColumn('nickname', 'string', ['length' => 30, 'nullable' => false])
    66	            ->addColumn('assigned_user_id', 'uuid', ['nullable' => true])
    67	            ->addColumn('created_at', 'datetime', ['nullable' => false])
    68	            ->addColumn('updated_at', 'datetime', ['nullable' => false])
    69	            ->setPrimaryKeys(['id'])
    70	            ->addIndex(['nickname'], ['unique' => true])
    71	            ->addForeignKey(
    72	                ['assigned_user_id'],
    73	                'users',
    74	                ['id'],
    75	                ['delete' => 'SET NULL', 'update' => 'CASCADE', 'indexCreate' => false],
    76	            )
    77	            ->addIndex(['assigned_user_id'])
    78	            ->create();
    79	    }
    80	
    81	    public function down(): void
    82	    {
    83	        $this->table('reserved_nicknames')->drop();
    84	        $this->table('user_bans')->drop();
    85	        $this->table('users')->drop();
    86	    }
    87	}

exec
/bin/zsh -lc "find app/src/Modules/User app/src/Modules/Access -type d -name Application -o -path '*Application*' | sort" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
codex
Исследование `User` ссылается на исследование `Media` по аватару. Сейчас быстро проверю только эти упомянутые строки, чтобы не оставить непроверенную ссылку в цепочке источников.
exec
/bin/zsh -lc "nl -ba docs/researches/2026-05-20_18-42_media-domain.md | sed -n '60,82p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc "nl -ba docs/researches/2026-05-20_18-42_media-domain.md | sed -n '420,466p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
    60	
    61	processingAttempts  количество попыток обработки
    62	processingError     последняя ошибка обработки
    63	
    64	createdAt
    65	updatedAt
    66	```
    67	
    68	`Media.id` остаётся UUID v7 по правилу проекта (`docs/rules.md:30`). Для storage path используем отдельный `storageKey` UUID v4, потому что он не раскрывает время создания. `storageKey` должен быть уникальным в БД.
    69	
    70	`path` создаёт backend. Пользовательское имя файла не храним: ни `originalName`, ни `fileName`. Имя файла является частью `path`.
    71	
    72	`deletedAt` не используем. `status = deleted` не используем. При удалении запись `Media` удаляется из базы, а файлы удаляются из storage.
    73	
    74	`Media` хранит только общие данные файла. В `Media` не добавляем поля, которые относятся к конкретному формату:
    75	
    76	```text
    77	width
    78	height
    79	duration
    80	bitrate
    81	codec
    82	fps

 succeeded in 0ms:
   420	updatedAt
   421	```
   422	
   423	`sort` хранится в таблице связи, а не в `Media`, потому что порядок - свойство использования файла в конкретной внешней сущности.
   424	
   425	Удаление защищает база данных через foreign key:
   426	
   427	```text
   428	Внешняя сущность -> таблица связи
   429	  ON DELETE CASCADE
   430	
   431	Таблица связи -> Media
   432	  ON DELETE RESTRICT
   433	```
   434	
   435	Пример:
   436	
   437	```text
   438	Post --cascade--> PostMedia --restrict--> Media
   439	```
   440	
   441	Смысл:
   442	
   443	```text
   444	удалили Post
   445	  PostMedia удаляется автоматически
   446	
   447	пытаемся удалить Media
   448	  база запрещает, если есть PostMedia / MessageMedia / другие связи
   449	```
   450	
   451	После удаления связи внешний домен может попросить media-домен удалить `Media`. Если других связей нет, база разрешит удаление. Если связи остались, база запретит удаление, и файлы в storage не трогаются.
   452	
   453	### Временные Загрузки И Очистка
   454	
   455	`Media` может временно существовать без внешней связи, но всегда имеет `uploadedById`.
   456	
   457	```text
   458	При загрузке:
   459	  Media.expiresAt = now + 1 day
   460	
   461	При привязке к внешней сущности:
   462	  Media.expiresAt = null
   463	```
   464	
   465	Очистка:
   466	

exec
/bin/zsh -lc "nl -ba app/src/Shared/Infrastructure/Framework/Middleware/LocaleMiddleware.php | sed -n '1,100p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Shared\Infrastructure\Framework\Middleware;
     6	
     7	use App\Shared\Infrastructure\Configuration\Locale\LocaleConfig;
     8	use Illuminate\Support\Collection;
     9	use Psr\Http\Message\ResponseInterface;
    10	use Psr\Http\Message\ServerRequestInterface;
    11	use Psr\Http\Server\MiddlewareInterface;
    12	use Psr\Http\Server\RequestHandlerInterface;
    13	use Psr\Log\LoggerInterface;
    14	use Spiral\Http\Header\AcceptHeader;
    15	use Spiral\Http\Header\AcceptHeaderItem;
    16	use Spiral\Translator\Translator;
    17	
    18	/**
    19	 * Определяет локаль запроса из заголовка Accept-Language, пересечённого с белым списком
    20	 * поддерживаемых локалей, и выставляет её в translator на время обработки запроса.
    21	 *
    22	 * Зависит от конкретного Spiral\Translator\Translator (не TranslatorInterface): метод
    23	 * setLocale() объявлен в LocaleAwareInterface и реализован конкретным Translator, а не
    24	 * в TranslatorInterface. Translator — singleton, поэтому выставленная локаль видна и
    25	 * интерсептору ошибок, который читает тот же инстанс через TranslatorInterface.
    26	 */
    27	final readonly class LocaleMiddleware implements MiddlewareInterface
    28	{
    29	    public function __construct(
    30	        private Translator $translator,
    31	        private LocaleConfig $localeConfig,
    32	        private LoggerInterface $logger,
    33	    ) {}
    34	
    35	    #[\Override]
    36	    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    37	    {
    38	        $acceptLanguage = $request->getHeaderLine('Accept-Language');
    39	        $matchedLocale = $this->matchSupportedLocale($acceptLanguage);
    40	        $resolvedLocale = $matchedLocale ?? $this->localeConfig->default;
    41	
    42	        $this->translator->setLocale($resolvedLocale);
    43	        $this->logger->debug(message: 'Локаль запроса определена.', context: [
    44	            'acceptLanguage' => $acceptLanguage,
    45	            'resolvedLocale' => $resolvedLocale,
    46	            'usedFallback' => $matchedLocale === null,
    47	        ]);
    48	
    49	        return $handler->handle($request);
    50	    }
    51	
    52	    private function matchSupportedLocale(string $acceptLanguage): string|null
    53	    {
    54	        // getAll() уже отсортирован по quality по убыванию, поэтому first() даёт самый приоритетный язык.
    55	        return Collection::make(AcceptHeader::fromString($acceptLanguage)->getAll())
    56	            ->filter(static fn(AcceptHeaderItem $item): bool => $item->getQuality() > 0.0)
    57	            ->map(fn(AcceptHeaderItem $item): string => $this->primarySubtag((string) $item->getValue()))
    58	            ->first(fn(string $primarySubtag): bool => \in_array(
    59	                needle: $primarySubtag,
    60	                haystack: $this->localeConfig->supported,
    61	                strict: true,
    62	            ));
    63	    }
    64	
    65	    private function primarySubtag(string $value): string
    66	    {
    67	        $lower = \strtolower($value);
    68	        $separatorPosition = \strpos(haystack: $lower, needle: '-');
    69	
    70	        return $separatorPosition === false ? $lower : \substr(string: $lower, offset: 0, length: $separatorPosition);
    71	    }
    72	}

exec
/bin/zsh -lc "nl -ba app/src/Modules/Media/Application/Query/Media/CheckMediaIsImage/CheckMediaIsImageQuery.php | sed -n '1,60p'; nl -ba app/src/Modules/Media/Application/Command/Media/MakeMediaPermanent/MakeMediaPermanentCommand.php | sed -n '1,70p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Media\Application\Query\Media\CheckMediaIsImage;
     6	
     7	final readonly class CheckMediaIsImageQuery
     8	{
     9	    public function __construct(
    10	        public string $mediaId,
    11	    ) {}
    12	}
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Media\Application\Command\Media\MakeMediaPermanent;
     6	
     7	final readonly class MakeMediaPermanentCommand
     8	{
     9	    public function __construct(
    10	        public string $userId,
    11	        public string $mediaId,
    12	    ) {}
    13	}

codex
- [факт] `header`-транспорт Spiral 3.16 описан неверно: по умолчанию это `X-Auth-Token`, не `Authorization: Bearer ...`; `AuthMiddleware` также не означает «только header» — исправить на явную настройку `HeaderTransport(header: 'Authorization', valueFormat: 'Bearer %s')` и middleware `AuthTransportMiddleware` или `AuthTransportWithStorageMiddleware`

- [безопасность] refresh-токен может стать access-токеном: один `TokenStorageInterface::load()` будет вызван middleware для любого токена из header, а в исследовании refresh проверяется уже после `load()` — сделать отдельный storage/service для refresh или гарантировать, что storage для HTTP-auth возвращает только access-токены

- [контракт] своё `CycleTokenStorage` реализуемо, но контракт описан неполно: `TokenStorageInterface::create(array $payload, ?DateTimeInterface $expiresAt)` не принимает `access|refresh`, а `ActorProviderInterface::getActor()` получает `TokenInterface`, не строку — описать точный payload, `TokenInterface`, формат токена и где хранится тип токена

- [архитектура] граница `Auth -> User` названа правильно, но не закреплена: `FindUserForAuth` должен возвращать DTO из `User/Application`, а не `User` Entity и не `UserRepository` — явно описать DTO с `UserId` и статусом/разрешением входа

- [факт] в текущем коде нет `User/Application` вообще, а `User::create()` создаёт статус `WaitingEmailConfirmation`, не `Active` — указать, что `CreateUser` должен внутри вызвать `User::create(...)` и затем `confirmEmail()`, либо отдельно изменить доменный API

- [факт] исследование ссылается на `User.preferredLocale`, но в текущем коде поле называется `locale`: `app/src/Modules/User/Domain/Entity/User.php:71` и миграция `locale` на строке 26 — заменить `preferredLocale` на реальное имя или запланировать переименование

- [риск] logout и multi-device не закрыты: в `auth_tokens` нет `session_id`, `device_id` или связи access-refresh пары, поэтому по одному access-токену нельзя надёжно отозвать refresh текущей сессии — добавить модель сессии или общий идентификатор пары токенов

- [риск] не решена гонка проверки кода: два параллельных `verify` могут прочитать один активный код до `consumed_at` — описать блокировку строки или атомарное обновление при consume/attempts

- [факт] версия пакетов проверена слабо: сейчас установлен `spiral/framework` 3.16.2, но актуальная стабильная версия на Packagist — 3.17.0; `spiral/cycle-bridge` установлен и актуален как v2.13.0 — в исследовании заменить «проверять нечего» на «цель — текущий lock 3.16.2, latest проверен отдельно»

- [факт] ссылка на связанное исследование содержит устаревший факт: там сказано, что `ext-intl` нет, но в текущем `composer.json:13` он есть — исправить связанный документ или не использовать этот вывод как источник

- [факт] `citext` не соответствует текущей реализации `User`: миграция создаёт `email` и `nickname` как обычные `string`, не `citext` — либо привести Auth к текущей стратегии `string + lower VO`, либо явно добавить миграцию расширения `citext` и менять существующие колонки

- [развилка] TTL оставлены как `~5 мин`, `~10 мин`, `~30-60д`, а для `Banned/Deleted` написано `Forbidden/Authentication` — выбрать точные значения и точные исключения прямо в исследовании, не переносить это в план

- [источник] ссылки вида `User.php:77` слабые: путь не указан, а часть ссылок из связанного исследования уже не совпадает с текущим кодом — заменить на полные ссылки вроде `app/src/Modules/User/Domain/Entity/User.php:77`

Источники для Spiral/версий: официальная документация Spiral 3.16 по auth и Packagist `spiral/framework`, `spiral/cycle-bridge`.
tokens used
230 884
- [факт] `header`-транспорт Spiral 3.16 описан неверно: по умолчанию это `X-Auth-Token`, не `Authorization: Bearer ...`; `AuthMiddleware` также не означает «только header» — исправить на явную настройку `HeaderTransport(header: 'Authorization', valueFormat: 'Bearer %s')` и middleware `AuthTransportMiddleware` или `AuthTransportWithStorageMiddleware`

- [безопасность] refresh-токен может стать access-токеном: один `TokenStorageInterface::load()` будет вызван middleware для любого токена из header, а в исследовании refresh проверяется уже после `load()` — сделать отдельный storage/service для refresh или гарантировать, что storage для HTTP-auth возвращает только access-токены

- [контракт] своё `CycleTokenStorage` реализуемо, но контракт описан неполно: `TokenStorageInterface::create(array $payload, ?DateTimeInterface $expiresAt)` не принимает `access|refresh`, а `ActorProviderInterface::getActor()` получает `TokenInterface`, не строку — описать точный payload, `TokenInterface`, формат токена и где хранится тип токена

- [архитектура] граница `Auth -> User` названа правильно, но не закреплена: `FindUserForAuth` должен возвращать DTO из `User/Application`, а не `User` Entity и не `UserRepository` — явно описать DTO с `UserId` и статусом/разрешением входа

- [факт] в текущем коде нет `User/Application` вообще, а `User::create()` создаёт статус `WaitingEmailConfirmation`, не `Active` — указать, что `CreateUser` должен внутри вызвать `User::create(...)` и затем `confirmEmail()`, либо отдельно изменить доменный API

- [факт] исследование ссылается на `User.preferredLocale`, но в текущем коде поле называется `locale`: `app/src/Modules/User/Domain/Entity/User.php:71` и миграция `locale` на строке 26 — заменить `preferredLocale` на реальное имя или запланировать переименование

- [риск] logout и multi-device не закрыты: в `auth_tokens` нет `session_id`, `device_id` или связи access-refresh пары, поэтому по одному access-токену нельзя надёжно отозвать refresh текущей сессии — добавить модель сессии или общий идентификатор пары токенов

- [риск] не решена гонка проверки кода: два параллельных `verify` могут прочитать один активный код до `consumed_at` — описать блокировку строки или атомарное обновление при consume/attempts

- [факт] версия пакетов проверена слабо: сейчас установлен `spiral/framework` 3.16.2, но актуальная стабильная версия на Packagist — 3.17.0; `spiral/cycle-bridge` установлен и актуален как v2.13.0 — в исследовании заменить «проверять нечего» на «цель — текущий lock 3.16.2, latest проверен отдельно»

- [факт] ссылка на связанное исследование содержит устаревший факт: там сказано, что `ext-intl` нет, но в текущем `composer.json:13` он есть — исправить связанный документ или не использовать этот вывод как источник

- [факт] `citext` не соответствует текущей реализации `User`: миграция создаёт `email` и `nickname` как обычные `string`, не `citext` — либо привести Auth к текущей стратегии `string + lower VO`, либо явно добавить миграцию расширения `citext` и менять существующие колонки

- [развилка] TTL оставлены как `~5 мин`, `~10 мин`, `~30-60д`, а для `Banned/Deleted` написано `Forbidden/Authentication` — выбрать точные значения и точные исключения прямо в исследовании, не переносить это в план

- [источник] ссылки вида `User.php:77` слабые: путь не указан, а часть ссылок из связанного исследования уже не совпадает с текущим кодом — заменить на полные ссылки вроде `app/src/Modules/User/Domain/Entity/User.php:77`

Источники для Spiral/версий: официальная документация Spiral 3.16 по auth и Packagist `spiral/framework`, `spiral/cycle-bridge`.
