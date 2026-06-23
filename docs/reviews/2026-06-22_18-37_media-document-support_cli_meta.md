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
session id: 019ef002-10b9-7731-ad26-08684af35736
--------
user
Прочитай файлы (абсолютные пути): ревью /Users/gian_tiaga/Code/yoga-loka-spiral-2/docs/reviews/2026-06-22_18-37_media-document-support.md, план /Users/gian_tiaga/Code/yoga-loka-spiral-2/docs/plans/2026-06-22_17-35_media-document-support.md, /Users/gian_tiaga/Code/yoga-loka-spiral-2/docs/rules.md, /Users/gian_tiaga/Code/yoga-loka-spiral-2/docs/arch.md, связанный research /Users/gian_tiaga/Code/yoga-loka-spiral-2/docs/researches/2026-06-22_17-22_media-document-support.md. Target ревью: git diff HEAD только по модулю Media и его тестам (получи через 'git --no-pager diff HEAD -- app/src/Modules/Media/ tests/'; новый файл tests/Unit/Modules/Media/Domain/Collection/MediaMimeTypeCollectionTest.php untracked — читай его напрямую). Затем глубоко проверь ревью по плану, правилам, архитектуре и релевантному коду: открой файлы, модули, API и тесты, которые следуют из diff и замечаний, и оцени фактическую корректность. Мета-ревью на русском: какие проблемы добавить, убрать, переформулировать; какие баги, риски для смежного кода и недостающие тесты пропущены. Не предлагай перечислять выполненную работу. Формат: '+', '−', '~', '?'.
codex
Использую `eda-review-check`, потому что задача именно про проверку готового ревью относительно плана, правил, архитектуры и фактического diff. Сначала прочитаю инструкции скилла, затем подниму нужные документы и код.
exec
/bin/zsh -lc "sed -n '1,240p' /Users/gian_tiaga/Code/yoga-loka-spiral-2/.codex/skills/eda-review-check/SKILL.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
---
name: eda-review-check
description: 'Проверяет готовое ревью из `docs/reviews/...` специализированными субагентами и, в strict-режиме, соседним CLI. Не делает первичное ревью и не правит код. Читает ревью, target/plan из front matter, `docs/rules.md`, `docs/arch.md`, `docs/settings.yaml` и связанный research при наличии. Запускает architecture-check, rules-check, plan-check только при указанном plan-файле и опциональный quality-check, затем сам применяет подтверждённые замечания к файлу ревью: добавляет, убирает или переформулирует пункты, обновляет score/status/meta_reviewers и раздел изменений после мета-ревью.'
---

# Скил: Проверка ревью (eda-review-check)

Берёшь уже сохранённое ревью из `docs/reviews/`, проверяешь его специализированными субагентами, затем сам правишь файл ревью по подтверждённым замечаниям. Этот скил переиспользуется обычным `eda-review` после draft-этапа и может запускаться отдельно.

## Режим запуска

Прочитай `docs/settings.yaml`, если файл есть. Прямое указание пользователя в текущем сообщении важнее настроек.

Дефолты: `defaults.strict: false`, `review.include_code_quality: true`.

| Настройка | Значения | Аргументы в сообщении |
|---|---|---|
| `defaults.strict` | `true`, `false` | `strict`; выключение: `normal`, `без strict`, `без строгого режима` |
| `review.include_code_quality` | `true`, `false` | «проверь качество кода»; выключение: «без quality», «без проверки качества» |

`strict` добавляет кросс-CLI соседним агентом. `review.include_code_quality: true` добавляет `quality-check` в обычное мета-ревью.

## Вход из сообщения пользователя

Текст рядом с вызовом скилла в текущем сообщении пользователя — главный вход. Сначала разбери именно его: путь к ревью, название или часть названия, «последнее ревью», режим, ограничения и прямые указания.

Если этого входа достаточно, продолжай без вопроса. `AskUserQuestion` задавай только если входа нет, он противоречивый, найдено несколько равных вариантов или есть риск проверить не то ревью. Старое обсуждение в истории не считай входом, если пользователь не связал его с текущим вызовом явно.

## Главные правила

1. **Не делаешь первичное ревью.** Входом всегда является готовый файл `docs/reviews/...`.
2. **Не правишь код.** Можно менять только выбранный файл ревью и вспомогательный файл `${REVIEW_FILE%.md}_cli_meta.md` в strict-режиме.
3. **Мета-ревью специализированными агентами обязательно.** Базовые роли: `architecture-check`, `rules-check`. `plan-check` запускай только если в front matter ревью `plan` указывает на конкретный файл. Если включена проверка качества кода — добавь `quality-check` в тот же batch.
4. **Главный редактор ревью — ты.** Субагенты дают предложения; решение добавить, убрать или переформулировать пункт принимаешь сам.
5. **Ревью остаётся ревью только по проблемам.** Не добавляй выполненные пункты, похвалу, нейтральный отчёт о diff или общие рассуждения.
6. **Каждое принятое изменение фиксируй в `## Изменения после мета-ревью`.** Отклонённые предложения тоже запиши коротко с причиной.
7. **Оценку пересчитывай после правок.** Если изменился набор или тяжесть замечаний, обнови `score` в front matter и текст в разделе `## Оценка`.

## Интерактивные вопросы

Когда инструкция говорит `AskUserQuestion`, это означает блокирующий интерактивный вопрос.
- Claude Code: используй `AskUserQuestion`.
- Codex interactive: если доступен `request_user_input`, используй его. Если tool недоступен, задай один короткий вопрос в чат, дай варианты 1–3, напиши «Ответь номером или своим вариантом. Я продолжу только после ответа.» и остановись.
- Codex exec / неинтерактивный запуск: не задавай вопросы и не пытайся читать stdin. Если без ответа нельзя безопасно продолжать, заверши работу со статусом `blocked: нужен ответ пользователя`, перечисли вопросы и варианты, не выполняй рискованные действия.
- Не запускай команды, которые ждут интерактивного ввода в терминале; перед такими командами спроси в хост-интерфейсе или остановись с `blocked`.

## Этапы

### 1. Выбрать ревью и прочитать контекст

Определи `$REVIEW_FILE`:
- если пользователь указал путь к ревью — используй его;
- если указал название или часть названия — найди совпадение в `docs/reviews/`;
- если написал «последнее ревью» — возьми самый новый файл из `docs/reviews/`;
- если входа нет — `AskUserQuestion` со списком последних файлов из `docs/reviews/`;
- если `docs/reviews/` отсутствует или пуста — остановись со статусом `blocked: нужно ревью`.

Прочитай `$REVIEW_FILE` целиком. Из front matter и текста извлеки `$TARGET`, `$PLAN_FILE`, текущие `mode`, `score`, `status`, `meta_reviewers`. Если `plan: none` или план явно помечен как пропущенный, установи `$PLAN_FILE=none`. Если target указывает на diff-команду, получи актуальный diff. Если target указывает на файл, папку или PR — прочитай достаточно кода, чтобы проверить замечания. Прочитай `docs/rules.md`, `docs/arch.md`, `docs/settings.yaml`, если они есть. Если ревью ссылается на `docs/researches/...`, прочитай связанный research.

Если `$PLAN_FILE=none` или отсутствует, не угадывай план и не запускай `plan-check`. В разделе изменений после мета-ревью коротко зафиксируй: «plan-check не запускался: план не указан в ревью».

### 2. Запустить специализированных субагентов

Запусти **в одном сообщении и одним batch** агентов с выбранными ролями. В интерактивном Codex это означает субагентов, а не отдельные процессы `codex exec`. Не запускай сначала часть агентов, не жди их ответы и не запускай остальных позже: все выбранные проверки должны стартовать до чтения любого результата.

Роли агентов:
- `plan-check`: запускай только если `$PLAN_FILE` указывает на существующий файл. Проверяет, что реализация действительно выполнила `$PLAN_FILE`, включая частичные пункты, пропущенные требования, лишние отклонения и недостающие проверки.
- `architecture-check`: проверяет следование `docs/arch.md`, границы модулей, контракты, зависимости и соответствие существующим архитектурным решениям.
- `rules-check`: проверяет следование `docs/rules.md`, локальным запретам, обязательным командам, требованиям к тестам, миграциям, документации и процессу.
- `quality-check`: запускай только если `review.include_code_quality: true` или пользователь явно попросил качество кода. Проверяет качество реализации: читаемость, сложность, дублирование, имена, размер и ответственность функций/классов, сцепление модулей, поддерживаемость и соответствие локальному стилю. Quality-замечания помечай типом `quality`; по умолчанию рекомендация `на усмотрение автора`, кроме случаев, где сложность, дублирование или неясная структура реально создают риск багов, тестовых пробелов или дорогого сопровождения.

Модели:
- Claude Code: запускай все роли через `Agent` tool на `model: "sonnet"`.
- Codex interactive: если доступен инструмент субагентов (`spawn_agent` или аналог), запускай все роли через него на `gpt-5.3-codex`; не используй отдельные `codex exec` для обычного мета-ревью, когда субагенты доступны.
- Codex exec / неинтерактивный fallback: если инструмента субагентов нет, запускай роли через параллельные `codex exec --model gpt-5.3-codex` одной Bash-командой с `&` и общим `wait`.
- Если нужный идентификатор недоступен, возьми ближайшую code-capable модель того же уровня. Не используй быструю слабую модель для этих проверок: они должны читать diff, план при наличии, правила, архитектуру и релевантный код.

Каждому агенту дай путь к `$REVIEW_FILE`, `$TARGET`, `docs/rules.md`, `docs/arch.md`, `docs/settings.yaml`, связанный research при наличии. `plan-check` дополнительно получает `$PLAN_FILE`, если он есть. Каждый агент должен читать не только ревью, но и фактический diff/код, достаточный для проверки своей роли.

Формат ответа агентов:
- `+` какую проблему добавить в ревью;
- `−` что убрать как неверное или недоказанное;
- `~` что переформулировать;
- `?` что невозможно проверить и почему.

Если `$PLAN_FILE` не передан, равен `none` или файл не найден, `plan-check` не запускай.

### 3. Применить результаты обычного мета-ревью

Когда все агенты ответили: прочитай ответы, **сам реши**, что применять. Добавляй только подтверждённые проблемы; предложения вида «добавить, что пункт выполнен» отклоняй как неформат ревью. Внеси правки в файл ревью.

В разделе `## Изменения после мета-ревью` запиши:

```markdown
### После plan-check / architecture-check / rules-check / quality-check
- **+ Добавлено:** <короткий список>
- **~ Изменено:** <короткий список>
- **− Убрано:** <короткий список>
- **Отклонено:** <короткий список с причиной>
```

Поменяй `status: draft` → `meta-reviewed`, если статус был draft. В `meta_reviewers` добавь только фактически запущенные роли: `architecture-check`, `rules-check`, `plan-check` при наличии `$PLAN_FILE`, а если запускался — `quality-check`, и фактические модели, например `gpt-5.3-codex` или `sonnet`. Если оценка после корректировок изменилась — обнови `score`.

### 4. Кросс-CLI: отдать соседнему агенту — только в `strict`

Обычный режим — пропусти этап 4, иди на финал. `status: meta-reviewed` оставь как есть.

В `strict`: отдай файл соседнему агенту через `Bash`. Если ты в Claude Code — запускай Codex CLI:

```bash
codex exec "Прочитай $REVIEW_FILE, $TARGET, $PLAN_FILE, docs/rules.md, docs/arch.md, связанный research при наличии. Затем глубоко проверь ревью по плану, правилам, архитектуре и релевантному коду: открой файлы, модули, API и тесты, которые следуют из diff и замечаний, и оцени фактическую корректность. Мета-ревью на русском: какие проблемы добавить, убрать, переформулировать; какие баги, риски для смежного кода и недостающие тесты пропущены. Не предлагай перечислять выполненную работу. Формат: '+', '−', '~', '?'." > "${REVIEW_FILE%.md}_cli_meta.md"
```

Если ты в Codex — запускай Claude CLI с теми же требованиями глубины:

```bash
claude -p "Прочитай $REVIEW_FILE, $TARGET, $PLAN_FILE, docs/rules.md, docs/arch.md, связанный research при наличии. Затем глубоко проверь ревью по плану, правилам, архитектуре и релевантному коду: открой файлы, модули, API и тесты, которые следуют из diff и замечаний, и оцени фактическую корректность. Мета-ревью на русском: какие проблемы добавить, убрать, переформулировать; какие баги, риски для смежного кода и недостающие тесты пропущены. Не предлагай перечислять выполненную работу. Формат: '+', '−', '~', '?'." > "${REVIEW_FILE%.md}_cli_meta.md"
```

Если `$PLAN_FILE=none`, в промпте соседнему CLI явно напиши: «План не указан; не проверяй выполнение плана и не угадывай файл плана, проверь только правила, архитектуру и код».

Соседней CLI нет — `AskUserQuestion`: завершить как есть или прервать.

Прочитай ответ, **сам реши**, что применять. Допиши в `## Изменения после мета-ревью`:

```markdown
### После соседнего CLI (codex|claude)
- **+ Добавлено:** ...
- **− Убрано:** ...
- **Отклонено:** ...
```

Добавь в `meta_reviewers` имя CLI. `status: meta-reviewed` → `final`.

### 5. Финал

Короткое сообщение: режим (`normal` или `strict`), путь к файлу ревью, итоговая оценка, сколько пунктов «править обязательно» / «на усмотрение», какие meta-reviewers запускались. Не пересказывай выполненную работу. Если режим был обычный — добавь строку «кросс-CLI не запускался; для проверки соседним агентом — `eda-review-check strict <review>`».

## Чего НЕ делать

- Делать первичное ревью. Это `eda-review`.
- Править код. Это `eda-fix-by-review`.
- Пропускать мета-ревью специализированными агентами.
- Запускать мета-ревьюеров частями: сначала 1–2 агента, потом остальных. Все выбранные обязательные проверки стартуют сразу.
- Пропускать кросс-CLI молча в `strict` (если соседней CLI нет — спроси через `AskUserQuestion`).
- Вставлять чужие предложения механически — каждое решение принимай ты.
- Задавать вопросы без блокировки и продолжать работу до ответа.
- Сохранять или редактировать ревью вне `docs/reviews/`.
- Перечислять выполненные пункты плана или давать общий отчёт о проделанной работе.

codex
Важное уточнение по режиму: пользователь просит выдать мета-ревью в ответе, а не править файл ревью. Поэтому файл ревью менять не буду, но глубину проверки сохраню: документы, diff, untracked-тест и связанный код.
codex
Инструмент субагентов здесь не запускаю: он доступен только при явном запросе на параллельных агентов, а текущая задача просит сам результат мета-ревью. Сейчас читаю обязательные документы, сам diff и untracked-тест.
exec
/bin/zsh -lc "sed -n '1,260p' docs/code-examples.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc "sed -n '1,300p' docs/researches/2026-06-22_17-22_media-document-support.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
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
    repository: UserRepository::class,
    typecast: [Typecast::class, UserValueObjectTypecast::class],
)]
class User
{
    use HasTimestamps;

    #[Column(type: 'uuid', primary: true, typecast: UserId::class)]
    public private(set) UserId $id;

    #[Column(type: 'string', typecast: Email::class)]
    public private(set) Email $email;

    #[Column(type: 'string', typecast: PasswordHash::class)]
    public private(set) PasswordHash $passwordHash;

    #[Column(type: 'string', typecast: Username::class)]
    public private(set) Username $username;

    #[Column(type: 'string', typecast: DisplayName::class)]

exec
/bin/zsh -lc "sed -n '1,260p' docs/rules.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
---
title: Поддержка документов в модуле Media
date: 2026-06-22 17:22
mode: strict
decision_mode: recommend_and_ask
status: reviewed
reviewer: codex
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
| `MediaTypeResolver::resolve()` | 3 префиксных правила, остальное 422 | нормализовать MIME (нижний регистр, без параметров); whitelist MIME → `Document` проверяется **первым**, затем префиксы image/video/audio, затем 422 |
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
  text/csv                                                                     -> csv   (параметры charset/header нормализуются)
  text/markdown                                                                -> md    (charset нормализуется до базового MIME)

Явно НЕ поддержаны (не в whitelist): macro-enabled Office (docm/xlsm/pptm,
MIME с суффиксом …macroEnabled.12) — активное содержимое.
```

Сравнение с whitelist — по нормализованному MIME: базовый тип в нижнем регистре, без
параметров после `;` (нужно для `text/markdown;charset=…` и `text/csv;charset=…`).

### Риски — закрытие рядом с решением

- **DJVU ломает порядок проверок в резолвере.** MIME DJVU — `image/vnd.djvu`, он начинается
  с `image/`. Текущий резолвер проверяет `str_starts_with('image/')` первым
  (`MediaTypeResolver.php:22`), поэтому без изменений DJVU стал бы `Image` и пайплайн отправил
  бы его в Imagick-ресайз. **Снимаем**: whitelist документов (точное сравнение MIME)
  проверяется ДО префиксных правил. Это обязательное требование к реализации резолвера.
- **Whitelist резолвера — единственный источник набора, потребитель его не расширяет.**
  `MediaTypeResolver::resolve()` вызывается ДО проверки `allowedMimeTypes`
  (`RequestMediaUploadHandler.php:40-41`): сначала тип резолвится, и неизвестный резолверу MIME
  падает в 422 раньше, чем дойдёт до спецификации потребителя. Значит `allowedMimeTypes` может
  только сузить набор, заданный whitelist'ом, но не добавить новый MIME. Это влияет на
  формулировки ниже (FB2/CSV/RTF) и означает: любой принимаемый MIME должен быть в whitelist кода.
- **Модуль доверяет заявленному MIME и не проверяет содержимое.** Клиент PUT-ит байты прямо в
  S3, MIME приходит из `MediaFileMeta`. Это свойство существующей архитектуры (так же для
  image/video/audio). Для документов добавляется поверхность (вредоносный PDF; офисные файлы с
  активным содержимым), но Media документ не парсит и не исполняет — только хранит и отдаёт.
  **Принимаем** в рамках текущего дизайна; антивирус/проверка magic bytes — отдельная задача вне
  этих рамок. Отдельно про макросы: macro-enabled форматы (`docm`/`xlsm`/`pptm`, MIME с суффиксом
  `…macroEnabled.12`) в whitelist НЕ входят и остаются неподдержанными. Старый `doc`
  (`application/msword`) включён по запросу и теоретически может нести макросы, но риск нивелируется
  тем, что модуль контент не исполняет.
- **Параметры в MIME (charset/header).** По IANA у `text/markdown` параметр `charset` обязателен,
  у `text/csv` допустимы `charset` и `header`; клиент реально присылает, например,
  `text/markdown;charset=utf-8`. `MediaMimeType` хранит строку целиком и параметры не отделяет, а
  whitelist — точное сравнение, поэтому `text/markdown;charset=utf-8` не совпало бы с
  `text/markdown` и markdown/csv фактически не прошли бы. **Снимаем**: резолвер перед сравнением с
  whitelist нормализует MIME — отбрасывает параметры (часть после `;`) и приводит к нижнему
  регистру (`text/markdown;charset=UTF-8` → `text/markdown`). Это требование к реализации
  резолвера; нормализацию покрыть тестом.
- **MIME FB2 не стандартизирован.** `application/x-fictionbook+xml` — самый частый, но встречаются
  `application/fb2` и сырой `application/xml`. **Снимаем выбором**: в whitelist берём канонический
  `application/x-fictionbook+xml`; остальные MIME для FB2 считаются неподдержанными и при
  необходимости добавляются в whitelist правкой кода (потребитель их не включит — см. пункт о
  порядке resolve выше).
- **CSV/RTF имеют несколько MIME.** RTF — `application/rtf` и `text/rtf`; CSV — `text/csv` (Excel
  иногда помечает иначе). **Снимаем**: для RTF включаем в whitelist оба MIME; для CSV берём
  канонический `text/csv`. Прочие варианты — не «на усмотрение потребителя» (он whitelist не
  расширяет), а неподдержанные до явного добавления в whitelist.
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
конверсий»: резолвер нормализует MIME (нижний регистр, без параметров) и классифицирует его
по whitelist MIME (с приоритетом над префиксом
`image/` ради DJVU), `MediaPath` получает префикс `documents`, `ProcessMediaHandler`
возвращает пустой набор конверсий и делает штатную перекладку оригинала в ready, а
`CompleteMediaUpload` требует, чтобы план конверсий для документа был пустым. Полный whitelist
MIME → расширение зафиксирован выше. Тесты: дополнить `MediaTypeResolverTest` (включая
DJVU → Document, а не Image, и MIME с параметром `;charset=…`), `MediaValueObjectTest` (путь
`documents`), `ProcessMediaHandlerTest` (перекладка без конверсий), `CompleteMediaUploadHandlerTest`
(пустой план для документа), `RequestMediaUploadHandlerTest` (запрос загрузки документа) —
требуется 100% покрытие. Следующий шаг — `eda-plan` для пошаговой реализации.

## Реакция на ревью

Кросс-ревью выполнено Codex CLI (`codex-cli 0.141.0`); полный лог —
`2026-06-22_17-22_media-document-support_review.md`. Codex дал 3 содержательных замечания.

- **Принято [архитектура] — порядок resolve vs allowedMimeTypes.** Формулировка «другие MIME
  (FB2/CSV/RTF) — ответственность потребителя через `allowedMimeTypes`» была неверной: `resolve()`
  вызывается до проверки `allowedMimeTypes` (`RequestMediaUploadHandler.php:40-41`), поэтому
  потребитель whitelist только сужает. Добавлен отдельный пункт-риск про порядок, переписаны
  пункты FB2 и CSV/RTF.
- **Принято [MIME] — параметры MIME (charset/header).** Точное сравнение не пропустило бы
  `text/markdown;charset=utf-8` / `text/csv;charset=…`. Добавлено решение: резолвер нормализует
  MIME (нижний регистр, отбрасывание параметров после `;`) перед сравнением с whitelist; учтено в
  таблице правок, whitelist-блоке, Итоге и тестах.
- **Принято [риск] — macro-enabled форматы.** Уточнена формулировка: macro-enabled
  `docm`/`xlsm`/`pptm` (MIME `…macroEnabled.12`) в whitelist НЕ входят; старый `doc` включён по
  запросу, риск нивелируется тем, что Media контент не исполняет.

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
- **Не подменять методы коллекции array-функциями**: если значение уже `Illuminate\Support\Collection` (в т.ч. типизированная доменная коллекция), для выборок и трансформаций вызывай её методы (`->filter()`, `->map()`, `->first()`, `->last()`, `->take()`, `->contains()`, `->reject()`, `->keyBy()`), а не разворачивай в массив через `->all()` ради `array_filter`/`array_map`/`array_slice`/`\count()`. Антипаттерн: `array_filter($collection->all(), $fn)` вместо `$collection->filter($fn)`. `->all()` уместен только когда наружу действительно нужен `array`/`list<T>` (метод объявляет такой тип возврата, передаём в чужой API), а не как промежуточный шаг между двумя коллекциями. Подробности и идиомы — в скилле [`laravel-collections`](../.claude/skills/laravel-collections/SKILL.md).
- **Без round-trip «коллекция → массив → коллекция»**: запрещено брать `->all()`, обрабатывать массив (`\count()`, `array_slice()`, индексирование `$page[\count($page) - 1]`) и заворачивать результат обратно в `new SomeCollection($array)`. Если на входе и на выходе коллекция, весь конвейер остаётся на коллекции. Чтобы преобразовать типизированную коллекцию в другой тип элемента (ресурсы, DTO), используй `$collection->toBase()->map($fn)` — базовый `Collection` не ломает дженерик `final`-коллекции, — а не `new Collection($collection->all())`; в конструктор другой типизированной коллекции передавай `Collection`/`Arrayable` напрямую, без `->all()`.
- **Cursor-пагинация — через общие примитивы, не копипастом**: схему «взять `limit + 1`, понять, есть ли следующая страница, отдать первые `limit` и курсор последней отданной» не дублировать в каждом репозитории и обработчике. Используются два общих примитива:
  - **Репозиторий**: `App\Shared\Infrastructure\Cycle\WhenSelect::cursorById($cursor?->value(), $limit)` — последнее звено цепочки `select()`, инкапсулирует `id DESC` + `where('id', '<', $cursor)` при курсоре + `limit`. Не писать вручную блок `->when(...cursor...)->orderBy('id','DESC')->limit($limit)` в каждом методе.
  - **Обработчик (Query)**: `App\Shared\Domain\Pagination\CursorSlice::fromOverfetched(overfetched: $repoResult, limit: $query->limit, cursorOf: static fn(Entity $e): string => $e->id->value())` — возвращает `$slice->items` (та же типизированная коллекция, первые `limit`) и `$slice->nextCursor`. Не писать вручную `$page->take($limit)` + `$page->count() > $limit` + `$visible->last()?->id->value()` в каждом обработчике.

  Антипаттерн (исправлен в `GetUserFeedHandler`, `GetPostCommentsHandler`, `GetCommentRepliesHandler`, `ListNotificationsHandler` и cursor-методах `PostRepository`/`CommentRepository`/`NotificationRepository`): `->all()` → `\count()` → `array_slice()` → `new XCollection($visible)` → `$visible[\count($visible) - 1]`, а также копия `when(cursor)+orderBy+limit` в каждом запросе.
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
/bin/zsh -lc "sed -n '1,260p' tests/Unit/Modules/Media/Domain/Collection/MediaMimeTypeCollectionTest.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Media\Domain\Collection;

use App\Modules\Media\Domain\Collection\MediaMimeTypeCollection;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use PHPUnit\Framework\TestCase;

final class MediaMimeTypeCollectionTest extends TestCase
{
    public function testContainsMimeTypeMatchesByBaseValueIgnoringParameters(): void
    {
        $allowed = new MediaMimeTypeCollection([MediaMimeType::fromString('text/markdown')]);

        self::assertTrue($allowed->containsMimeType(MediaMimeType::fromString('text/markdown;charset=utf-8')));
    }

    public function testContainsMimeTypeRejectsDifferentMimeType(): void
    {
        $allowed = new MediaMimeTypeCollection([MediaMimeType::fromString('image/jpeg')]);

        self::assertFalse($allowed->containsMimeType(MediaMimeType::fromString('image/png')));
    }
}

exec
/bin/zsh -lc "sed -n '1,280p' docs/plans/2026-06-22_17-35_media-document-support.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
---
title: Поддержка документов в модуле Media
date: 2026-06-22 17:35
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
  research: docs/researches/2026-06-22_17-22_media-document-support.md
---

# План реализации

## Задача

Включить в модуле `Media` загрузку и выдачу документов (PDF, DOC, DOCX, DJVU, TXT и другие
ходовые форматы). Сейчас `MediaType::Document` объявлен в enum, но это заглушка: документ
сознательно отклоняется во всех точках пайплайна. Нужно снять заглушку так, чтобы документ
можно было загрузить, переложить в постоянное хранилище и отдать по URL, **не ломая** уже
работающий поток image/video/audio.

Готово, когда: документ проходит весь путь `RequestMediaUpload → CompleteMediaUpload →
ProcessMedia → GetMediaUrl` как «медиа без конверсий»; `make test` и `make phpstan` зелёные;
покрытие тестами 100%.

## Контекст

Документ в выбранной модели — это «image без конверсий»: тот же путь
`waitingUpload → uploaded → перекладка оригинала → ready`, но `MediaConversionPlan` пустой и
обработчик ничего не транскодирует. Доменная и архитектурная модель НЕ меняется — нет новых
Entity, контрактов, репозиториев, зависимостей, миграций, конфигов, очередей, outbox-сообщений
или ключей локали. Меняются 5 точек, где `MediaType::Document` сейчас бросает исключение, плюс
два места сравнения MIME (`MediaMimeType::baseValue()` и
`MediaMimeTypeCollection::containsMimeType()` — нормализация для Markdown/CSV с `;charset=…`),
плюс затрагиваемые тесты.

Точки, проверенные по коду:

```text
1 MediaTypeResolver::resolve()              app/.../Application/Service/MediaTypeResolver.php:18-38
2 MediaPath::assertValid() (regex)          app/.../Domain/ValueObject/MediaPath.php:154
3 MediaPath::originalReady()                app/.../Domain/ValueObject/MediaPath.php:83
4 ProcessMediaHandler::handle() match       app/.../Application/Command/ProcessMedia/ProcessMediaHandler.php:90
5 CompleteMediaUploadHandler::assertPlanValid()  app/.../Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php:123
```

Важные факты из кода:

- **У модуля Media нет HTTP-роутов и контроллеров.** `RequestMediaUpload`,
  `CompleteMediaUpload`, `GetMediaUrl` — это CQRS Application-сценарии (Command/Query + Handler),
  которые потребляются другими модулями (например `User`, `Posts`) через `Media/Application`.
  Единственный вход в `Media/Presentation` — технический `ProcessMediaJob`. Поэтому «правок
  роутов/контроллеров/OpenAPI» в этой задаче нет, а проверка идёт feature-тестами Handler'ов и
  end-to-end Flow-тестом.
- Резолвер вызывается ДО проверки `allowedMimeTypes`
  (`RequestMediaUploadHandler.php:40-41`): потребитель спецификации может только сузить набор,
  заданный whitelist'ом резолвера, но не добавить новый MIME. Значит любой принимаемый MIME
  документа обязан быть в whitelist кода.
- Расширение файла берётся из имени файла (`pathinfo($fileMeta->fileName, PATHINFO_EXTENSION)`
  в `RequestMediaUploadHandler::extension()`), а НЕ из MIME. Поэтому резолверу нужен только
  **набор MIME** документов; таблица «MIME → расширение» из research — справочная.
- `MediaPath::originalUpload()` (staging-путь `uploads/...`) от типа не зависит — не трогаем.
- `MediaConversionPlan` (три списка `image/video/audio`) и `MediaUploaded` не трогаем: для
  документа все три списка пусты.
- `MediaTypeResolver` — `final readonly` без зависимостей (чистый сервис); `MediaPath` — доменный
  VO (классового докстринга у него нет). Логгер в них не вводится (см. раздел «Логирование»).
- Смежные точки проверены и правок НЕ требуют: typecast `MediaType` уже поддерживает значение
  `document`; `GetMediaUrl` отдаёт оригинал по `visibility` без зависимости от типа;
  `S3MediaFileService` оперирует абстрактным `MediaPath` и префикс не хардкодит; staging-expiry
  чистит документы как и другие типы.

## Принятые решения

Все существенные решения подтверждены пользователем в research
(`docs/researches/2026-06-22_17-22_media-document-support.md`, раздел «Ответы на вопросы»):

- **Поведение** — документ только хранится: после подтверждения оригинал перекладывается в
  постоянный бакет по `visibility` и отдаётся через `GetMediaUrl`. Конверсий, превью первой
  страницы и извлечения метаданных нет. *(подтверждено пользователем)*
- **Распознавание типа** — явный whitelist MIME в `MediaTypeResolver`. Неизвестный
  `application/*` по-прежнему 422 `unsupported_file_type`. *(подтверждено пользователем)*
- **Набор форматов** — все четыре категории (полный whitelist в research, раздел «Whitelist
  MIME → расширение»). Whitelist зафиксирован пользователем; добавление прочих вариантов MIME
  (например `text/x-rtf`, `image/svg+xml`) в эту задачу не входит. *(подтверждено пользователем)*
- **Порядок проверок в резолвере** — whitelist документов сравнивается ДО префиксов
  `image/`/`video/`/`audio/`, потому что DJVU имеет MIME `image/vnd.djvu`; иначе он стал бы
  Image. *(закрытие риска из research)*
- **Нормализация MIME — общий метод VO, применяется при ВСЕХ сравнениях MIME.** В
  `MediaMimeType` добавляется метод `baseValue()` (нижний регистр + отбрасывание параметров после
  `;` + `trim`: `text/markdown;charset=utf-8` → `text/markdown`); сама строка в VO по-прежнему
  хранится целиком (`value()` не меняется). Резолвер сравнивает с разрешённым списком по
  `baseValue()`; `MediaMimeTypeCollection::containsMimeType()` тоже сравнивает по `baseValue()` с
  обеих сторон. Иначе документ с charset-параметром (Markdown/CSV) опознался бы резолвером как
  документ, но не прошёл бы проверку `allowedMimeTypes` (`text/markdown;charset=utf-8` ≠
  `text/markdown`). Три префиксные проверки image/video/audio остаются на исходном `value()` —
  поведение этих типов не меняется. *(закрытие риска из research + кросс-CLI ревью Codex; решение
  пользователя — вариант «нормализовать и в спецификации»)*
- **Представление whitelist** — приватная типизированная константа `list<string>` в
  `MediaTypeResolver` (нормализованные MIME в нижнем регистре), членство — `\in_array(...,
  strict: true)`. По `decision_mode: recommend_and_ask` это деталь реализации, не новое
  существенное решение (нет новых зависимостей/БД/API): закрытый набор вариантов рядом с
  использованием — по правилу «Не плодить технические константы» это уместная константа.

Ожидаемый объём (plan_size: normal): 7 правок кода в 6 файлах (5 точек-заглушек +
`MediaMimeType::baseValue()` + `MediaMimeTypeCollection::containsMimeType()`) + правки/добавления
в 8 тестовых файлах; миграций и новых файлов кода нет.

## Целевой алгоритм

```text
RequestMediaUpload
  resolve(mime): normalized = lower(strip_params(mime))
                 если normalized в whitelist документов ⇒ Document;
                 иначе по исходному value префикс image/ | video/ | audio/;
                 иначе 422 unsupported_file_type
  allowedMimeTypes сужает набор спецификацией потребителя (сравнение по базовому MIME без
                 параметров; может отклонить документ как mime_not_allowed, но не добавить новый MIME)
  media = waitingUpload, path = uploads/<shard>/<key>/source.<ext>  (staging, тип не влияет)
  клиент PUT-ит байты в staging-бакет

CompleteMediaUpload
  headObject подтверждает размер
  assertPlanValid(type=Document): все три списка плана (image/video/audio) ДОЛЖНЫ быть пусты,
                 иначе 422 conversion_plan_type_mismatch
  media = uploaded, MediaUploaded(plan=пустой) в outbox

outbox:relay → ProcessMediaJob → ProcessMedia
  match(type): Document ⇒ конверсий нет ([])
  persistReady: copyObject(uploads/... → documents/<shard>/<key>/source.<ext>),
                media → ready (storage = public|private по visibility)

GetMediaUrl
  public: прямой URL; private: presignGet  (без изменений)
```

## Контракты реализации

### Данные и БД

`Не затрагивается`. Колонка `media.type` уже хранит значение `document` (enum `MediaType`
объявлен с этим case-ом, Entity и typecast его уже поддерживают). Миграций, новых таблиц,
полей, индексов нет. Старые данные совместимы: документов в БД ещё не было, потому что загрузка
отклонялась раньше записи.

### API и внешние контракты

`Не затрагивается` в смысле внешних HTTP-контрактов: у модуля Media нет собственных
HTTP-роутов, контроллеров, Filter и Response-классов (см. «Контекст»). Затрагиваемые
`RequestMediaUpload`/`CompleteMediaUpload`/`GetMediaUrl` — внутренние Application-сценарии
(Command/Query + Handler) для других модулей; их сигнатуры и DTO не меняются. Новых ошибок,
status code, webhook'ов, очередей, outbox-сообщений нет.

Поведенческое расширение (в рамках существующих Application-сценариев):

```text
RequestMediaUpload  — теперь резолвит документный MIME из разрешённого списка в Document вместо
                      422. Конкретный потребитель примет документ, только если его MediaUploadSpec
                      .allowedMimeTypes включает нужный MIME (потребитель сужает набор; правка
                      потребителя — вне рамок модуля Media). Сравнение allowedMimeTypes теперь по
                      базовому MIME без параметров (containsMimeType): для image/video/audio без
                      параметров поведение не меняется, для Markdown/CSV с ;charset=… документ
                      проходит спецификацию, заданную базовым MIME.
CompleteMediaUpload — для документа ожидает пустой план конверсий; непустой план ⇒ 422
                      app.media.conversion_plan_type_mismatch (ключ уже есть в locale).
ProcessMedia        — для документа перекладывает оригинал в documents/... без конверсий.
unsupported_file_type остаётся для реально неизвестных MIME (например font/woff2).
```

Новых ключей локали не требуется (`conversion_plan_type_mismatch`, `unsupported_file_type` уже
есть в `app/locale/ru/media.php` и `app/locale/en/media.php`).

## Фазы выполнения

### 1. Нормализация MIME и классификация документа

Цель: резолвер распознаёт документ по разрешённому списку MIME раньше префиксных правил и
устойчив к регистру и параметрам MIME; проверка `allowedMimeTypes` тоже устойчива к параметрам,
чтобы Markdown/CSV с `;charset=…` проходили спецификацию.

Что сделать:
- Добавить в `MediaTypeResolver` приватную константу со списком разрешённых MIME-типов
  документов — точно `/** @var list<string> */ private const array DOCUMENT_MIME_TYPES = [...]`
  (нативный тип константы — `array`, элемент `list<string>` задаётся только в PHPDoc); MIME из
  research, все в нижнем регистре:
  `application/pdf`, `application/msword`,
  `application/vnd.openxmlformats-officedocument.wordprocessingml.document`, `text/plain`,
  `application/rtf`, `text/rtf`, `application/vnd.oasis.opendocument.text`,
  `application/vnd.oasis.opendocument.spreadsheet`,
  `application/vnd.oasis.opendocument.presentation`, `application/vnd.ms-excel`,
  `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`,
  `application/vnd.ms-powerpoint`,
  `application/vnd.openxmlformats-officedocument.presentationml.presentation`,
  `application/epub+zip`, `application/x-fictionbook+xml`, `image/vnd.djvu`, `text/csv`,
  `text/markdown`. Рядом с константой — короткий комментарий на русском без англицизмов: что
  форматы Office с макросами (MIME с суффиксом `…macroEnabled.12`) намеренно не включены из-за
  активного содержимого.
- Добавить в `MediaMimeType` метод `baseValue(): string`: нижний регистр + отбросить часть после
  `;` + `trim` (нормализованный базовый MIME). Хранимая строка (`value()`) не меняется. Это общий
  источник нормализации и для резолвера, и для сравнения `allowedMimeTypes`.
- В `MediaTypeResolver::resolve()`: **первым** проверить членство `$mimeType->baseValue()` в
  `DOCUMENT_MIME_TYPES` через `\in_array(needle: $mimeType->baseValue(), haystack:
  self::DOCUMENT_MIME_TYPES, strict: true)` → `MediaType::Document`; затем существующие три
  префиксные проверки `str_starts_with` **на исходном `$mimeType->value()`** (поведение
  image/video/audio не меняется); затем существующий `throw 422 unsupported_file_type`.
- В `MediaMimeTypeCollection::containsMimeType()` сравнивать по базовому MIME с обеих сторон:
  `$allowed->baseValue() === $mimeType->baseValue()` вместо точного `$allowed->equals($mimeType)`.
  Иначе документ с charset-параметром (Markdown/CSV) опознается резолвером, но не пройдёт
  `allowedMimeTypes`. Для image/video/audio без параметров поведение не меняется (база совпадает
  со строкой).
- Обновить классовый докстринг резолвера (строки 11-15): сейчас он утверждает, что документы
  отклоняются и что `MediaPath` допускает только `uploads|images|videos|audios` — после правки
  оба утверждения становятся ложными. Привести формулировку в соответствие (на русском, без
  англицизмов вроде «whitelist»: «поддержан список разрешённых MIME-типов документов»;
  `MediaPath` допускает и префикс `documents`).

Результат: `resolve('application/pdf') === Document`, `resolve('image/vnd.djvu') === Document`
(не Image), `resolve('text/markdown;charset=utf-8') === Document`, `resolve('image/jpeg') ===
Image`, `resolve('image/png') === Image` (прочий image/* не перехвачен списком документов),
`resolve('font/woff2')` → `ValidationException`. Дополнительно:
`MediaMimeType::fromString('TEXT/CSV;charset=utf-8')->baseValue() === 'text/csv'` при неизменном
`value()`; `MediaMimeTypeCollection([text/markdown])->containsMimeType('text/markdown;charset=utf-8')
=== true`.

Критерий готовности докстринга: в докблоке резолвера нет фразы про «документы отклоняются» и про
«только uploads|images|videos|audios»; есть упоминание списка разрешённых MIME-типов документов и
префикса `documents`; текст докблока на русском без англицизмов.

Сценарии тестирования (после фазы) — файл `tests/Unit/Modules/Media/Application/MediaTypeResolverTest.php`:
- Позитивные кейсы документов: `application/pdf`, `application/msword`, `text/csv`,
  `text/markdown` → `Document`.
- DJVU `image/vnd.djvu` → `Document`, а НЕ `Image` (порядок проверок).
- Нормализация: `text/markdown;charset=utf-8` → `Document`; `APPLICATION/PDF` и `TEXT/CSV`
  (верхний регистр) → `Document`.
- `image/png` → `Image` (whitelist не перехватывает прочие image/* — кроме djvu).
- `unsupportedMimeTypeProvider` (строки 37-40): убрать ставшие поддержанными `application/pdf` и
  `text/plain`; оставить/добавить реально неподдержанные `font/woff2`, `application/zip`,
  `application/octet-stream` и macro-enabled
  `application/vnd.ms-word.document.macroEnabled.12` (подтверждает исключение макросов).

Сценарии для нормализации (после фазы):
- `MediaValueObjectTest`: `MediaMimeType::baseValue()` — `text/markdown;charset=utf-8` →
  `text/markdown`, `APPLICATION/PDF` → `application/pdf`, `image/jpeg` → `image/jpeg`; `value()`
  возвращает исходную строку без изменений.
- Новый юнит-тест `tests/Unit/Modules/Media/Domain/Collection/MediaMimeTypeCollectionTest.php`:
  `containsMimeType()` матчит spec `text/markdown` против значения `text/markdown;charset=utf-8`
  (`true`) и не матчит spec `image/jpeg` против `image/png` (`false`).

Проверка: `make test` (новые кейсы зелёные), `make phpstan`.

### 2. Префикс `documents` в `MediaPath`

Цель: путь готового оригинала документа валиден и строится с префиксом `documents`.

Что сделать:
- В `MediaPath::assertValid()` (строка 154) расширить регулярку префиксов:
  `(uploads|images|videos|audios)` → `(uploads|images|videos|audios|documents)`. Проверка
  shard↔uuid (строки 162-164) общая для всех префиксов и не меняется.
- В `MediaPath::originalReady()` (строки 79-86) в `match($type)` заменить ветку
  `MediaType::Document => throw ...` на `MediaType::Document => 'documents'`. `match` остаётся
  исчерпывающим без `default`.
- `originalUpload()` не трогаем.

Результат: `MediaPath::originalReady(type: Document, extension: 'pdf')` →
`documents/<shard>/<key>/source.pdf`; `MediaPath::fromString('documents/aa/<uuid>/source.pdf')`
валиден; пути image/video/audio и uploads — без изменений.

Сценарии тестирования (после фазы) — файл
`tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php`:
- В `testMediaPathFactoriesBuildValidPaths` (≈строка 78) добавить
  `originalReady(Document, 'pdf')` → ожидаемая строка с префиксом `documents/`.
- Перевернуть существующий негатив на строках 132-141 (где `originalReady(Document)` ожидает
  `InvalidDomainValueException`) в позитивный кейс построения пути `documents/...`.
- Добавить валидный `MediaPath::fromString('documents/<shard>/<uuid>/source.pdf')` с корректным
  shard и один негатив: `documents/<bad-shard>/<uuid>/source.pdf` (shard не равен первым двум hex

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
/bin/zsh -lc "sed -n '1,260p' docs/reviews/2026-06-22_18-37_media-document-support.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
---
title: Поддержка документов в модуле Media
date: 2026-06-22 18:37
target: git diff HEAD (только изменения модуля Media и его тестов)
plan: docs/plans/2026-06-22_17-35_media-document-support.md
mode: strict
score: 89
status: meta-reviewed
meta_reviewers: [architecture-check (sonnet), rules-check (sonnet), plan-check (sonnet), quality-check (sonnet)]
---

# Ревью: Поддержка документов в модуле Media

## Оценка

**89/100.** План выполнен почти буквально: все пять точек-заглушек сняты, нормализация MIME и
префикс `documents` добавлены ровно там, где требовалось, поведение image/video/audio не
тронуто, тесты покрывают позитивные и негативные сценарии по всем фазам. Снижение оценки — не
за баги в коде документов (их я не нашёл), а за то, что незакоммиченное рабочее дерево смешивает
эту задачу с посторонней правкой коллекций, за один новый тестовый файл, лежащий вне целевого
diff, и за место, где словесное обещание докстринга шире того, что реально делает код.

## Проблемы сверки с планом

### 1. В рабочем дереве смешаны две разные задачи

Ревью просили сделать на реализацию плана поддержки документов в Media. Но в незакоммиченном
состоянии репозитория лежат изменения сразу двух задач: помимо файлов Media здесь же висят правки
модулей Posts, Auth, Notifications и общего слоя пагинации (новые `CursorSlice`, `PostViewCollection`,
`CommentViewCollection`, переписанные обработчики ленты и комментариев). Это явно отдельная работа по
устранению round-trip коллекций, у неё свой документ в `docs/fixes/`.

Риск в том, что при коммите или открытии PR «по документам» в один набор попадут изменения, которые
к документам отношения не имеют. Это путает историю, мешает откату и ломает сверку с планом: человек,
читающий diff коммита, не сможет отделить документную правку от правки пагинации. Проблема проявится в
момент `git commit`/PR, а не сейчас.

Само по себе ревью документной части это не блокирует — я ревьюил только файлы Media. Но перед
коммитом задачи нужно либо развести изменения по разным коммитам, либо явно подтвердить, что обе
задачи едут вместе осознанно.

Технические детали:

- **Статус:** отклонение (организационное, вне кода Media)
- **Где:** `git status` — изменены `app/src/Modules/Posts/**`, `app/src/Modules/Auth/**`,
  `app/src/Modules/Notifications/**`, `app/src/Shared/Infrastructure/Cycle/WhenSelect.php`,
  новые `app/src/Shared/Domain/Pagination/CursorSlice.php`,
  `app/src/Modules/Posts/Application/View/PostViewCollection.php`, `.../CommentViewCollection.php`,
  плюс `docs/fixes/2026-06-22_17-34_collection-round-trip.md`
- **Что подтверждает проблему:** план media-document-support прямо обещает «миграций и новых файлов
  кода нет» и список из 6 файлов кода Media; перечисленные файлы Posts/Auth/Shared в этот список не
  входят и относятся к другой теме (round-trip коллекций)
- **Как исправить:** при коммите развести на два независимых коммита — отдельно документы Media,
  отдельно round-trip коллекций; для этого использовать постадийное добавление только файлов Media
  (`app/src/Modules/Media/**`, `app/locale` не трогался, тесты Media, план/исследование/журнал
  документной задачи). Если задачи осознанно едут вместе — зафиксировать это решение явно.

### 2. Новый тест `MediaMimeTypeCollectionTest` лежит вне целевого diff и рискует не попасть в коммит документов

План явно требовал новый файл `tests/Unit/Modules/Media/Domain/Collection/MediaMimeTypeCollectionTest.php`
(фаза 1). Файл реально создан и содержит ровно те два кейса, что предписаны планом (матч
`text/markdown` против `text/markdown;charset=utf-8` → `true` и несовпадение `image/jpeg` против
`image/png` → `false`), то есть единственное покрытие нового поведения `containsMimeType()` по
`baseValue()` живёт именно в нём.

Проблема в том, что файл не отслеживается git (вся папка в статусе `??`), поэтому он **не входит в
`git diff HEAD`** — целевой diff этого ревью. Это пересекается с проблемой 1: если при коммите
документной задачи добавлять только изменённые файлы Media (`git add -u app/src/Modules/Media/**`
плюс изменённые тесты), новый непривязанный к индексу файл легко выпадет, и в коммите останется
правка `containsMimeType()` без своего теста — покрытие нового сравнения по `baseValue()` уйдёт в
ноль для этого коммита, хотя локально `make test` его видит.

Технические детали:

- **Статус:** отклонение (риск проявляется при коммите, как и проблема 1)
- **Где:** `tests/Unit/Modules/Media/Domain/Collection/MediaMimeTypeCollectionTest.php` —
  untracked (`git status` показывает `?? tests/Unit/Modules/Media/Domain/Collection/`)
- **Что подтверждает проблему:** файл не попадает в `git diff HEAD`; правка
  `MediaMimeTypeCollection::containsMimeType()` (переход с `equals()` на сравнение по `baseValue()`)
  в diff есть, а её единственный целевой тест — нет
- **Как исправить:** при коммите явно добавить новый файл теста в индекс
  (`git add tests/Unit/Modules/Media/Domain/Collection/MediaMimeTypeCollectionTest.php`) вместе с
  кодом Media, а не полагаться на `git add -u`; убедиться, что новый тест едет в том же коммите, что
  и правка коллекции

## Замечания

### 1. Докстринг VO обещает нормализацию «при всех сравнениях», а часть сравнений идёт по сырой строке

В описании метода `baseValue()` написано, что он «используется при всех сравнениях MIME, чтобы
charset-параметр не ломал сопоставление». На практике это не так: классификация по префиксам
`image/`, `video/`, `audio/` в резолвере намеренно работает по исходной строке `value()`, а не по
нормализованному значению — и это правильное решение из плана (нормализуем только сравнение со
списком документов, поведение остальных типов не меняем). Хуже того, обещание докстринга ложно и в
другую сторону: `MediaMimeType::equals()` тоже сравнивает по сырой строке (`$this->value ===
$other->value`), а `MediaMimeTypeCollection::containsMimeType()` — наоборот, по `baseValue()`. То
есть у одного VO два метода сравнения дают разный результат на одинаковом входе, и докстринг
`baseValue()` ни о какой из этих границ честно не предупреждает.

Само по себе это не баг: код делает ровно то, что задумано. Проблема в формулировке докстринга — она
шире кода и при беглом чтении может подтолкнуть следующего разработчика «привести к консистентности»
и начать гонять префиксы (или `equals()`) через `baseValue()`, что молча изменит поведение
image/video/audio (например MIME в верхнем регистре `IMAGE/JPEG` стал бы проходить) или сравнение по
точному значению. Риск отложенный — проявится при будущей правке резолвера или коллекции, когда
кто-то поверит докстрингу буквально. Докстринг — это человекочитаемый комментарий к коду
(`docs/rules.md`: комментарии на русском, точно отражают поведение); вводящая в заблуждение формула
делает это замечание не чисто косметическим.

Технические детали:

- **Тип:** `docs`
- **Рекомендация:** рекомендуется исправить (докстринг искажает фактическое поведение)
- **Где:** `app/src/Modules/Media/Domain/ValueObject/MediaMimeType.php:33-37` — фраза «Используется при
  всех сравнениях MIME»; контекст — `MediaTypeResolver.php:52-64`, где префиксы идут по `value()`, и
  `MediaMimeTypeCollection.php:17-19` (сравнение по `baseValue()`) против `MediaMimeType.php:43-46`
  (`equals()` по `value()`)
- **Что подтверждает проблему:** в `MediaTypeResolver::resolve()` после проверки списка документов
  берётся `$value = $mimeType->value()` и префиксы проверяются именно по нему; нормализация применяется
  только в `in_array(... baseValue() ...)` и в `MediaMimeTypeCollection::containsMimeType()`, тогда как
  `equals()` остаётся на точном `value()`
- **Как исправить:** уточнить докстринг: нормализованное значение используется при сопоставлении со
  списком разрешённых документов (резолвер) и при проверке `allowedMimeTypes`
  (`containsMimeType()`); префиксная классификация image/video/audio и `equals()` намеренно остаются
  на исходном значении
- **Тесты:** не нужны — правка только текста докстринга

### 2. Докстринг `baseValue()` не оговаривает, что метод годен только для сравнения, а не как готовый Content-Type

Новый метод приводит MIME к «базовому» виду, отбрасывая всё, что идёт после первой точки с запятой,
и для ожидаемых случаев (`text/markdown;charset=utf-8` → `text/markdown`) это правильно.

Прямого риска сейчас нет, и важно зафиксировать почему: пустую или вырожденную строку через публичный
API не подать — `MediaMimeType::fromString()` уже отвергает пустое значение
(`InvalidDomainValueException` при `$value === ''`) ещё до конструктора, поэтому VO со значением вида
`;charset=utf-8` создать нельзя, и `explode(';')[0] === ''` на корректно созданном VO недостижим.
Замечание поэтому не про текущий баг, а про контракт метода: `baseValue()` — публичный метод VO,
который возвращает «сырое» базовое значение и нигде не обещает, что оно пригодно как самостоятельный
MIME. Если его результат когда-нибудь начнут использовать не для сравнения, а, например, как значение
заголовка Content-Type, неявность контракта вылезет.

Технические детали:

- **Тип:** `quality`
- **Рекомендация:** на усмотрение автора
- **Где:** `app/src/Modules/Media/Domain/ValueObject/MediaMimeType.php:38-41`
- **Что подтверждает проблему:** `\strtolower(\trim(\explode(';', $this->value)[0]))` нормализует, но
  докстринг не ограничивает применение методом сравнения; при этом `fromString()` (строки 17-26)
  блокирует пустой ввод, так что вырожденный случай через публичный API не возникает
- **Как исправить:** в докстринге явно отметить, что метод предназначен только для сопоставления MIME
  и не гарантирует пригодность как готовый Content-Type (без утверждений о «будущей пустой строке» —
  фабрика её не пропустит)
- **Тесты:** при желании добавить в `MediaValueObjectTest` кейс `baseValue()` для значения с пробелами
  вокруг параметра (`text/csv ; charset=utf-8`), чтобы зафиксировать ожидаемый результат `text/csv`

### 3. Метод `assertDocumentPlan` повторяет уже трижды написанный guard «чужие списки пусты»

Проверка «все три списка плана должны быть пусты» — это, по сути, повтор того же условия
`image !== [] || video !== [] || audio !== []`, которое в этом же классе уже фигурирует кусками в
`assertImagePlan`, `assertVideoPlan`, `assertAudioPlan` (каждый проверяет «два чужих списка пусты»).
Документ — частный случай: «пусты все три».

Это не ошибка и план прямо предписал такой стиль («как у существующих assert-методов»), поэтому правка
необязательна. Но как наблюдение о поддерживаемости: четыре метода независимо повторяют одну и ту же
идею «список не своего типа должен быть пустым». Если набор типов конверсий когда-нибудь изменится,
прав&shy;ить придётся в нескольких местах. Сейчас риска нет — это чистая стилистика.

Технические детали:

- **Тип:** `quality`
- **Рекомендация:** на усмотрение автора
- **Где:** `app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php:127-132`
  и соседние `assertImagePlan`/`assertVideoPlan`/`assertAudioPlan`
- **Что подтверждает проблему:** во всех четырёх методах повторяется проверка пустоты «чужих» списков
  плана с одним и тем же ключом ошибки `app.media.conversion_plan_type_mismatch`
- **Как исправить:** оставить как есть (соответствует существующему стилю и плану) либо при будущем
  рефакторинге вынести общую проверку «разрешён только список такого-то типа» в один параметризованный
  метод. В рамках этой задачи менять не обязательно — план явно зафиксировал текущий стиль
- **Тесты:** не нужны — поведение уже покрыто `testRejectsNonEmptyPlanForDocument` с data-провайдером по
  всем трём спискам

## Рекомендации

- **Править обязательно:** —
- **Рекомендуется исправить:** замечание 1 (докстринг `baseValue()` искажает фактическое поведение)
- **На усмотрение автора:** проблемы сверки 1 (разведение коммитов) и 2 (новый тест вне diff);
  замечания 2 и 3

## Изменения после мета-ревью

Режим: strict. Запущены специализированные субагенты на sonnet: `architecture-check`,
`rules-check`, `plan-check` (план указан во front matter и существует), `quality-check`
(`review.include_code_quality: true`). Все четыре стартовали одним batch. Кросс-CLI этап в strict
ещё предстоит (соседний CLI), статус пока `meta-reviewed`.

### После plan-check / architecture-check / rules-check / quality-check

- **+ Добавлено:**
  - Проблема сверки 2: новый файл `MediaMimeTypeCollectionTest.php` лежит вне целевого `git diff HEAD`
    (untracked), план его требовал — риск потерять единственное покрытие нового `containsMimeType()`
    при коммите документов (plan-check; проверено: вся папка в статусе `??`, файл содержит ровно два
    плановых кейса).
- **~ Изменено:**
  - Замечание 1 (докстринг `baseValue()`): расширен риск — обещание докстринга ложно не только для
    префиксных проверок резолвера, но и для пары `equals()` (по `value()`) против `containsMimeType()`
    (по `baseValue()`); рекомендация поднята с «на усмотрение» до «рекомендуется исправить», так как
    докстринг искажает фактическое поведение кода (architecture-check + rules-check).
  - Замечание 2 (`baseValue()`): смягчено основание — переоценённый тезис «может вернуть пустую строку
    в будущем» убран, потому что `MediaMimeType::fromString()` отвергает пустой ввод и вырожденный
    случай через публичный API недостижим; оставлена только полезная рекомендация уточнить контракт
    докстринга (quality-check).
  - Раздел «Рекомендации» переписан под новый набор: добавлена категория «рекомендуется исправить».
  - Оценка 88 → 89: убран один фактически неверный пункт и смягчён переоценённый, что компенсировано
    новым реальным риском (тест вне diff); набор подтверждённых проблем стал точнее.
- **− Убрано:**
  - Прежнее замечание 4 (Flow-тест «почти тавтологичен»): фактически неверно. `$processedMedia`
    в `testCompleteThenRelayProcessesDocumentToReady` перезагружается из БД через
    `mediaRepository()->findById($media->id)` (строка 219), а `$readyPath` строится локально (строка
    227); `assertSame` (строка 232) сверяет записанный в БД путь с построенным, то есть проверяет, что
    обработчик действительно сохранил корректный путь, а не «детерминированность функции». Тезис
    «обе стороны из `MediaPath::originalReady(...)`» опровергнут чтением теста (plan-check; проверено
    мной по коду). quality-check соглашался с пунктом на том же ошибочном основании — отклонён.
- **Отклонено:**
  - Отдельный новый пункт «задокументировать асимметрию `equals()`/`containsMimeType()`»
    (architecture-check) как самостоятельное замечание — свёрнут в переформулированное замечание 1,
    чтобы не плодить дубль про ту же фразу докстринга.
  - Понижение пункта про смешение задач (architecture-check предлагал рассмотреть снятие) — пункт
    валиден, оставлен как есть (сам же agent подтвердил его корректность).
  - `?`-наблюдения агентов (создан ли тест коллекции — проверено, создан; прохождение `make test`/
    `make phpstan` без запуска; покрытие `trim`-ветки) — это не подтверждённые проблемы кода, в ревью
    не добавлены; журнал выполнения плана фиксирует финальный прогон `make test` + `make phpstan`.

exec
/bin/zsh -lc 'git --no-pager diff HEAD -- app/src/Modules/Media/ tests/' in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
diff --git a/app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php b/app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php
index e18831e..206c0a0 100644
--- a/app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php
+++ b/app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php
@@ -120,13 +120,17 @@ final readonly class CompleteMediaUploadHandler
             MediaType::Image => $this->assertImagePlan($plan),
             MediaType::Video => $this->assertVideoPlan($plan),
             MediaType::Audio => $this->assertAudioPlan($plan),
-            MediaType::Document => throw new ValidationException(
-                translationKey: 'app.media.unsupported_file_type',
-                translationParameters: ['type' => $type->value],
-            ),
+            MediaType::Document => $this->assertDocumentPlan($plan),
         };
     }
 
+    private function assertDocumentPlan(MediaConversionPlan $plan): void
+    {
+        if ($plan->image !== [] || $plan->video !== [] || $plan->audio !== []) {
+            throw new ValidationException('app.media.conversion_plan_type_mismatch');
+        }
+    }
+
     private function assertImagePlan(MediaConversionPlan $plan): void
     {
         if ($plan->video !== [] || $plan->audio !== []) {
diff --git a/app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php b/app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php
index 41d7c96..c63085f 100644
--- a/app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php
+++ b/app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php
@@ -22,7 +22,6 @@ use App\Modules\Media\Domain\ValueObject\MediaId;
 use App\Modules\Media\Domain\ValueObject\MediaPath;
 use App\Modules\Media\Domain\ValueObject\MediaPixelDimension;
 use App\Modules\Media\Repository\MediaRepository;
-use App\Shared\Domain\Exception\InvalidDomainValueException;
 use App\Shared\Domain\Exception\NotFoundException;
 use Cycle\ORM\EntityManagerInterface;
 use GianTiaga\SpiralCqrs\Attribute\LogOperation;
@@ -87,7 +86,7 @@ final readonly class ProcessMediaHandler
                 plan: $command->plan,
                 targetStorage: $targetStorage,
             ),
-            MediaType::Document => throw new InvalidDomainValueException('Обработка документов не поддержана.'),
+            MediaType::Document => [],
         };
 
         $this->persistReady(
diff --git a/app/src/Modules/Media/Application/Service/MediaTypeResolver.php b/app/src/Modules/Media/Application/Service/MediaTypeResolver.php
index b3afe45..09120ab 100644
--- a/app/src/Modules/Media/Application/Service/MediaTypeResolver.php
+++ b/app/src/Modules/Media/Application/Service/MediaTypeResolver.php
@@ -9,14 +9,46 @@ use App\Modules\Media\Domain\ValueObject\MediaMimeType;
 use App\Shared\Domain\Exception\ValidationException;
 
 /**
- * Резолвер MIME -> MediaType. Намеренно сужающий: поддержаны image/*, video/* и audio/*;
- * прочие типы (документы и т.п.) вне области пайплайна и отклоняются как ожидаемая клиентская
- * ошибка 422 (MediaPath допускает только префиксы uploads|images|videos|audios).
+ * Резолвер MIME -> MediaType. Поддержан список разрешённых MIME-типов документов (он
+ * проверяется первым) и префиксы image/*, video/* и audio/*; прочие MIME вне области пайплайна
+ * и отклоняются как ожидаемая клиентская ошибка 422. MediaPath допускает префиксы
+ * uploads|images|videos|audios|documents.
  */
 final readonly class MediaTypeResolver
 {
+    /**
+     * Форматы Office с макросами (MIME с суффиксом …macroEnabled.12) намеренно не включены
+     * из-за активного содержимого.
+     *
+     * @var list<string>
+     */
+    private const array DOCUMENT_MIME_TYPES = [
+        'application/pdf',
+        'application/msword',
+        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
+        'text/plain',
+        'application/rtf',
+        'text/rtf',
+        'application/vnd.oasis.opendocument.text',
+        'application/vnd.oasis.opendocument.spreadsheet',
+        'application/vnd.oasis.opendocument.presentation',
+        'application/vnd.ms-excel',
+        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
+        'application/vnd.ms-powerpoint',
+        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
+        'application/epub+zip',
+        'application/x-fictionbook+xml',
+        'image/vnd.djvu',
+        'text/csv',
+        'text/markdown',
+    ];
+
     public function resolve(MediaMimeType $mimeType): MediaType
     {
+        if (\in_array(needle: $mimeType->baseValue(), haystack: self::DOCUMENT_MIME_TYPES, strict: true)) {
+            return MediaType::Document;
+        }
+
         $value = $mimeType->value();
 
         if (\str_starts_with(haystack: $value, needle: 'image/')) {
diff --git a/app/src/Modules/Media/Domain/Collection/MediaMimeTypeCollection.php b/app/src/Modules/Media/Domain/Collection/MediaMimeTypeCollection.php
index 23ed6de..962af14 100644
--- a/app/src/Modules/Media/Domain/Collection/MediaMimeTypeCollection.php
+++ b/app/src/Modules/Media/Domain/Collection/MediaMimeTypeCollection.php
@@ -14,6 +14,8 @@ final class MediaMimeTypeCollection extends Collection
 {
     public function containsMimeType(MediaMimeType $mimeType): bool
     {
-        return $this->contains(static fn(MediaMimeType $allowed): bool => $allowed->equals($mimeType));
+        return $this->contains(
+            static fn(MediaMimeType $allowed): bool => $allowed->baseValue() === $mimeType->baseValue(),
+        );
     }
 }
diff --git a/app/src/Modules/Media/Domain/ValueObject/MediaMimeType.php b/app/src/Modules/Media/Domain/ValueObject/MediaMimeType.php
index 0b2b0b5..f5ffdf6 100644
--- a/app/src/Modules/Media/Domain/ValueObject/MediaMimeType.php
+++ b/app/src/Modules/Media/Domain/ValueObject/MediaMimeType.php
@@ -30,6 +30,16 @@ final readonly class MediaMimeType implements \Stringable, \JsonSerializable
         return $this->value;
     }
 
+    /**
+     * Базовый MIME без параметров: нижний регистр, отброшена часть после `;`, обрезаны пробелы
+     * (`text/markdown;charset=utf-8` -> `text/markdown`). Хранимое значение (`value()`) не меняется.
+     * Используется при всех сравнениях MIME, чтобы charset-параметр не ломал сопоставление.
+     */
+    public function baseValue(): string
+    {
+        return \strtolower(\trim(\explode(separator: ';', string: $this->value)[0]));
+    }
+
     public function equals(self $other): bool
     {
         return $this->value === $other->value;
diff --git a/app/src/Modules/Media/Domain/ValueObject/MediaPath.php b/app/src/Modules/Media/Domain/ValueObject/MediaPath.php
index ed00755..3f3f666 100644
--- a/app/src/Modules/Media/Domain/ValueObject/MediaPath.php
+++ b/app/src/Modules/Media/Domain/ValueObject/MediaPath.php
@@ -80,9 +80,7 @@ final readonly class MediaPath implements \Stringable, \JsonSerializable
             MediaType::Image => 'images',
             MediaType::Video => 'videos',
             MediaType::Audio => 'audios',
-            MediaType::Document => throw new InvalidDomainValueException(
-                'Перекладка готового оригинала для документов не поддержана.',
-            ),
+            MediaType::Document => 'documents',
         };
 
         return self::fromString(
@@ -152,7 +150,7 @@ final readonly class MediaPath implements \Stringable, \JsonSerializable
 
         $matches = [];
         if (\preg_match(
-            pattern: '/^(uploads|images|videos|audios)\/([0-9a-f]{2})\/([0-9a-f-]{36})\/([^\/]+)$/',
+            pattern: '/^(uploads|images|videos|audios|documents)\/([0-9a-f]{2})\/([0-9a-f-]{36})\/([^\/]+)$/',
             subject: $value,
             matches: $matches,
         ) !== 1) {
diff --git a/tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php b/tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php
index 5151b5a..6d4c5f2 100644
--- a/tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php
+++ b/tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php
@@ -33,6 +33,18 @@ final class CheckMediaQueriesTest extends MediaApplicationTestCase
         self::assertFalse($handler->handle(new CheckMediaIsImageQuery(mediaId: $media->id->value())));
     }
 
+    public function testCheckMediaIsImageReturnsFalseForDocument(): void
+    {
+        // DJVU имеет MIME image/vnd.djvu и теперь классифицируется как Document, а не Image.
+        // Тест пиннит, что документ нельзя подсунуть в image-only сценарий (например аватар).
+        $media = $this->createMedia(userId: UserId::generate(), type: MediaType::Document, extension: 'pdf', mimeType: 'application/pdf');
+        $this->persist($media);
+
+        $handler = new CheckMediaIsImageHandler(mediaRepository: $this->mediaRepository());
+
+        self::assertFalse($handler->handle(new CheckMediaIsImageQuery(mediaId: $media->id->value())));
+    }
+
     public function testCheckMediaIsImageReturnsFalseForMissingMedia(): void
     {
         $handler = new CheckMediaIsImageHandler(mediaRepository: $this->mediaRepository());
diff --git a/tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php b/tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php
index 75f69c4..af8efb8 100644
--- a/tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php
+++ b/tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php
@@ -7,14 +7,20 @@ namespace Tests\Feature\Modules\Media\Application;
 use App\Modules\Media\Application\Command\CompleteMediaUpload\CompleteMediaUploadCommand;
 use App\Modules\Media\Application\Command\CompleteMediaUpload\CompleteMediaUploadHandler;
 use App\Modules\Media\Application\Contract\MediaFileServiceContract;
+use App\Modules\Media\Application\Dto\MediaAudioConversionSpec;
 use App\Modules\Media\Application\Dto\MediaConversionPlan;
+use App\Modules\Media\Application\Dto\MediaImageConversionSpec;
 use App\Modules\Media\Application\Dto\MediaObjectHead;
+use App\Modules\Media\Application\Dto\MediaVideoConversionSpec;
 use App\Modules\Media\Application\Message\MediaUploaded;
 use App\Modules\Media\Domain\Collection\MediaMultipartPartCollection;
 use App\Modules\Media\Domain\Entity\Media;
 use App\Modules\Media\Domain\Entity\MediaMultipartUpload;
+use App\Modules\Media\Domain\Enum\MediaAudioConversionType;
+use App\Modules\Media\Domain\Enum\MediaImageConversionType;
 use App\Modules\Media\Domain\Enum\MediaStatus;
 use App\Modules\Media\Domain\Enum\MediaType;
+use App\Modules\Media\Domain\Enum\MediaVideoConversionType;
 use App\Modules\Media\Domain\ValueObject\MediaFileSize;
 use App\Modules\Media\Domain\ValueObject\MediaMultipartPart;
 use App\Modules\Media\Domain\ValueObject\MediaMultipartPartETag;
@@ -342,22 +348,87 @@ final class CompleteMediaUploadHandlerTest extends MediaApplicationTestCase
         ));
     }
 
-    public function testRejectsDocumentMedia(): void
+    public function testCompletesDocumentUploadWithEmptyPlan(): void
+    {
+        $userId = UserId::generate();
+        $media = $this->createMedia(userId: $userId, type: MediaType::Document, extension: 'pdf', mimeType: 'application/pdf');
+        $this->persist($media);
+
+        $captured = null;
+        $outboxStore = $this->createMock(OutboxEventStoreContract::class);
+        $outboxStore->expects(self::once())->method('add')->willReturnCallback(
+            function (MediaUploaded $message) use (&$captured): StoredOutboxEventId {
+                $captured = $message;
+
+                return StoredOutboxEventId::fromString('outbox-1');
+            },
+        );
+
+        $result = $this->handler($this->fileServiceWithHead(1024), $outboxStore)->handle(new CompleteMediaUploadCommand(
+            userId: $userId->value(),
+            mediaId: $media->id->value(),
+            plan: $this->emptyPlan(),
+            parts: null,
+        ));
+
+        self::assertSame(MediaStatus::Uploaded, $result->status);
+        self::assertSame(MediaStatus::Uploaded, $media->status);
+        self::assertInstanceOf(MediaUploaded::class, $captured);
+    }
+
+    #[DataProvider('nonEmptyDocumentPlanProvider')]
+    public function testRejectsNonEmptyPlanForDocument(MediaConversionPlan $plan): void
     {
         $userId = UserId::generate();
         $media = $this->createMedia(userId: $userId, type: MediaType::Document, extension: 'pdf', mimeType: 'application/pdf');
         $this->persist($media);
 
         $this->expectException(ValidationException::class);
+        $this->expectExceptionMessage('app.media.conversion_plan_type_mismatch');
 
         $this->handler($this->fileServiceWithHead(1024), $this->outboxStore())->handle(new CompleteMediaUploadCommand(
             userId: $userId->value(),
             mediaId: $media->id->value(),
-            plan: $this->emptyPlan(),
+            plan: $plan,
             parts: null,
         ));
     }
 
+    /**
+     * @return array<string, array{MediaConversionPlan}>
+     */
+    public static function nonEmptyDocumentPlanProvider(): array
+    {
+        return [
+            'непустой список image' => [new MediaConversionPlan(
+                image: [new MediaImageConversionSpec(type: MediaImageConversionType::Thumbnail, width: 100, height: 100)],
+                video: [],
+                audio: [],
+            )],
+            'непустой список video' => [new MediaConversionPlan(
+                image: [],
+                video: [new MediaVideoConversionSpec(
+                    type: MediaVideoConversionType::NormalizedMp4H264,
+                    width: 1280,
+                    height: 720,
+                    videoBitrate: 1_000_000,
+                    audioBitrate: 128_000,
+                )],
+                audio: [],
+            )],
+            'непустой список audio' => [new MediaConversionPlan(
+                image: [],
+                video: [],
+                audio: [new MediaAudioConversionSpec(
+                    type: MediaAudioConversionType::NormalizedAacM4a,
+                    bitrate: 128_000,
+                    sampleRate: 44_100,
+                    waveformPeaks: 64,
+                )],
+            )],
+        ];
+    }
+
     public function testRejectsMissingMedia(): void
     {
         $this->expectException(NotFoundException::class);
diff --git a/tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php b/tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php
index 343fd58..5d17a7e 100644
--- a/tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php
+++ b/tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php
@@ -199,6 +199,37 @@ final class GetMediaUrlHandlerTest extends MediaApplicationTestCase
         self::assertSame($conversion->path->value(), $capturedPath);
     }
 
+    public function testReturnsUrlForReadyDocument(): void
+    {
+        // GetMediaUrl не ветвится по типу: документ отдаётся как любое готовое медиа —
+        // public прямым URL, private через presignGet.
+        $publicDocument = $this->readyDocumentMedia(MediaVisibility::Public);
+        $this->persist($publicDocument);
+
+        $publicFileService = $this->createStub(MediaFileServiceContract::class);
+        $publicFileService->method('publicUrl')->willReturn('http://minio/media-public/document.pdf');
+
+        $publicResult = $this->handler($publicFileService)->handle(new GetMediaUrlQuery(
+            mediaId: $publicDocument->id->value(),
+            presignedTtlSeconds: 300,
+        ));
+        self::assertSame('http://minio/media-public/document.pdf', $publicResult->url);
+        self::assertNull($publicResult->expiresAt);
+
+        $privateDocument = $this->readyDocumentMedia(MediaVisibility::Private);
+        $this->persist($privateDocument);
+
+        $privateFileService = $this->createStub(MediaFileServiceContract::class);
+        $privateFileService->method('presignGet')->willReturn('http://minio/signed-document');
+
+        $privateResult = $this->handler($privateFileService)->handle(new GetMediaUrlQuery(
+            mediaId: $privateDocument->id->value(),
+            presignedTtlSeconds: 300,
+        ));
+        self::assertSame('http://minio/signed-document', $privateResult->url);
+        self::assertNotNull($privateResult->expiresAt);
+    }
+
     public function testRejectsMissingConversion(): void
     {
         $media = $this->readyMedia(MediaVisibility::Private);
@@ -256,6 +287,25 @@ final class GetMediaUrlHandlerTest extends MediaApplicationTestCase
         return $media;
     }
 
+    private function readyDocumentMedia(MediaVisibility $visibility): Media
+    {
+        $media = $this->createMedia(
+            userId: UserId::generate(),
+            visibility: $visibility,
+            type: MediaType::Document,
+            extension: 'pdf',
+            mimeType: 'application/pdf',
+        );
+        $media->markUploaded();
+        $targetStorage = $visibility === MediaVisibility::Public ? MediaStorage::Public : MediaStorage::Private;
+        $media->markReadyMovedTo(
+            $targetStorage,
+            MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Document, extension: 'pdf'),
+        );
+
+        return $media;
+    }
+
     private function videoConversion(Media $media): MediaVideoConversion
     {
         return MediaVideoConversion::create(
diff --git a/tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php b/tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php
index 27bcf3b..ab89711 100644
--- a/tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php
+++ b/tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php
@@ -250,10 +250,11 @@ final class ProcessMediaHandlerTest extends MediaApplicationTestCase
         self::assertTrue($media->isReady());
     }
 
-    public function testRejectsDocumentType(): void
+    public function testProcessesDocumentByMovingOriginalWithoutConversions(): void
     {
         $media = $this->createMedia(
             userId: UserId::generate(),
+            visibility: MediaVisibility::Public,
             type: MediaType::Document,
             extension: 'pdf',
             mimeType: 'application/pdf',
@@ -261,12 +262,21 @@ final class ProcessMediaHandlerTest extends MediaApplicationTestCase
         $media->markUploaded();
         $this->persist($media);
 
-        $this->expectException(InvalidDomainValueException::class);
+        $fileService = $this->createMock(MediaFileServiceContract::class);
+        $fileService->expects(self::never())->method('getObjectContents');
+        $fileService->expects(self::never())->method('putObject');
+        $fileService->expects(self::once())->method('copyObject');
 
-        $this->handler($this->createStub(MediaFileServiceContract::class))->handle(new ProcessMediaCommand(
+        $this->handler($fileService)->handle(new ProcessMediaCommand(
             mediaId: $media->id->value(),
             plan: $this->emptyPlan(),
         ));
+
+        self::assertTrue($media->isReady());
+        self::assertSame(MediaStorage::Public, $media->storage);
+        // Документ перекладывается в постоянное хранилище по пути с префиксом documents/.
+        self::assertStringStartsWith('documents/', $media->path->value());
+        self::assertCount(0, $this->imageConversionRepository()->findByMediaId($media->id));
     }
 
     public function testRejectsMissingMedia(): void
diff --git a/tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php b/tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php
index a4e11fb..5f115c7 100644
--- a/tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php
+++ b/tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php
@@ -16,6 +16,7 @@ use App\Modules\Media\Application\Service\MediaTypeResolver;
 use App\Modules\Media\Domain\Collection\MediaMimeTypeCollection;
 use App\Modules\Media\Domain\Enum\MediaStatus;
 use App\Modules\Media\Domain\Enum\MediaStorage;
+use App\Modules\Media\Domain\Enum\MediaType;
 use App\Modules\Media\Domain\Enum\MediaVisibility;
 use App\Modules\Media\Domain\ValueObject\MediaFileSize;
 use App\Modules\Media\Domain\ValueObject\MediaId;
@@ -160,6 +161,57 @@ final class RequestMediaUploadHandlerTest extends MediaApplicationTestCase
         ));
     }
 
+    public function testRequestsDocumentUpload(): void
+    {
+        $fileService = $this->createStub(MediaFileServiceContract::class);
+        $fileService->method('presignPut')->willReturn('http://minio/put-url');
+
+        $result = $this->handler($fileService)->handle(new RequestMediaUploadCommand(
+            userId: UserId::generate()->value(),
+            spec: $this->spec(allowedMimeTypes: new MediaMimeTypeCollection([MediaMimeType::fromString('application/pdf')])),
+            fileMeta: $this->fileMeta(fileName: 'document.pdf', mimeType: 'application/pdf'),
+        ));
+
+        $media = $this->mediaRepository()->findById(MediaId::fromString($result->mediaId));
+        self::assertNotNull($media);
+        self::assertSame(MediaType::Document, $media->type);
+        self::assertSame(MediaStatus::WaitingUpload, $media->status);
+        // Документ кладётся в staging как и другие типы: путь начинается с uploads/.
+        self::assertStringStartsWith('uploads/', $media->path->value());
+    }
+
+    public function testRequestsDocumentUploadWithCharsetMime(): void
+    {
+        $fileService = $this->createStub(MediaFileServiceContract::class);
+        $fileService->method('presignPut')->willReturn('http://minio/put-url');
+
+        // Спека задаёт базовый MIME без параметра, файл приходит с ;charset=utf-8 —
+        // нормализация в containsMimeType должна пропустить загрузку.
+        $result = $this->handler($fileService)->handle(new RequestMediaUploadCommand(
+            userId: UserId::generate()->value(),
+            spec: $this->spec(allowedMimeTypes: new MediaMimeTypeCollection([MediaMimeType::fromString('text/markdown')])),
+            fileMeta: $this->fileMeta(fileName: 'note.md', mimeType: 'text/markdown;charset=utf-8'),
+        ));
+
+        $media = $this->mediaRepository()->findById(MediaId::fromString($result->mediaId));
+        self::assertNotNull($media);
+        self::assertSame(MediaType::Document, $media->type);
+    }
+
+    public function testRejectsDocumentMimeOutsideSpec(): void
+    {
+        // Резолвер поддерживает text/csv как документ, но спека его не разрешает —
+        // потребитель сужает набор, а не расширяет.
+        $this->expectException(ValidationException::class);
+        $this->expectExceptionMessage('app.media.mime_not_allowed');
+
+        $this->handler()->handle(new RequestMediaUploadCommand(
+            userId: UserId::generate()->value(),
+            spec: $this->spec(allowedMimeTypes: new MediaMimeTypeCollection([MediaMimeType::fromString('application/pdf')])),
+            fileMeta: $this->fileMeta(fileName: 'sheet.csv', mimeType: 'text/csv'),
+        ));
+    }
+
     private function handler(
         MediaFileServiceContract|null $fileService = null,
         int $threshold = 16_777_216,
@@ -184,10 +236,13 @@ final class RequestMediaUploadHandlerTest extends MediaApplicationTestCase
         );
     }
 
-    private function spec(int $maxSize = 1_048_576, int $presignedTtl = 300): MediaUploadSpec
-    {
+    private function spec(
+        int $maxSize = 1_048_576,
+        int $presignedTtl = 300,
+        MediaMimeTypeCollection|null $allowedMimeTypes = null,
+    ): MediaUploadSpec {
         return new MediaUploadSpec(
-            allowedMimeTypes: new MediaMimeTypeCollection([MediaMimeType::fromString('image/jpeg')]),
+            allowedMimeTypes: $allowedMimeTypes ?? new MediaMimeTypeCollection([MediaMimeType::fromString('image/jpeg')]),
             maxSize: MediaFileSize::fromInt($maxSize),
             visibility: MediaVisibility::Private,
             presignedTtl: MediaPresignedTtl::fromInt($presignedTtl),
diff --git a/tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php b/tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php
index 3740c20..e7be60c 100644
--- a/tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php
+++ b/tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php
@@ -203,6 +203,36 @@ final class MediaProcessingFlowTest extends MediaApplicationTestCase
         self::assertNotNull($this->fileService()->headObject(MediaStorage::Public, $normalizedPath));
     }
 
+    public function testCompleteThenRelayProcessesDocumentToReady(): void
+    {
+        $media = $this->uploadedOriginal(
+            $this->documentBytes(),
+            MediaVisibility::Public,
+            MediaType::Document,
+            'pdf',
+            'application/pdf',
+        );
+
+        $this->completeUpload($media, $this->emptyPlan());
+        self::assertSame(1, $this->relay());
+
+        $processedMedia = $this->mediaRepository()->findById($media->id);
+        self::assertNotNull($processedMedia);
+        self::assertSame(MediaStatus::Ready, $processedMedia->status);
+        self::assertSame(MediaStorage::Public, $processedMedia->storage);
+
+        // У документа конверсий нет.
+        self::assertCount(0, $this->imageConversionRepository()->findByMediaId($media->id));
+
+        $readyPath = MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Document, extension: 'pdf');
+        self::assertStringStartsWith('documents/', $readyPath->value());
+        $this->track(MediaStorage::Public, $readyPath);
+
+        // Реальный оригинал переложен по ready-пути documents/<shard>/<key>/source.pdf без конверсий.
+        self::assertSame($readyPath->value(), $processedMedia->path->value());
+        self::assertNotNull($this->fileService()->headObject(MediaStorage::Public, $readyPath));
+    }
+
     public function testProcessingFailureRecordsErrorAndFailsOutbox(): void
     {
         $corruptBytes = \random_bytes(2048);
@@ -300,6 +330,11 @@ final class MediaProcessingFlowTest extends MediaApplicationTestCase
         return (string) \ob_get_clean();
     }
 
+    private function documentBytes(): string
+    {
+        return "%PDF-1.4\nТестовое содержимое документа для flow-теста.\n%%EOF";
+    }
+
     private function videoBytes(): string
     {
         return $this->ffmpegFixture('mp4', [
diff --git a/tests/Unit/Modules/Media/Application/MediaTypeResolverTest.php b/tests/Unit/Modules/Media/Application/MediaTypeResolverTest.php
index 9514ea8..b2f867c 100644
--- a/tests/Unit/Modules/Media/Application/MediaTypeResolverTest.php
+++ b/tests/Unit/Modules/Media/Application/MediaTypeResolverTest.php
@@ -23,6 +23,44 @@ final class MediaTypeResolverTest extends TestCase
         self::assertSame(MediaType::Audio, $resolver->resolve(MediaMimeType::fromString('audio/mp4')));
     }
 
+    public function testResolvesDocumentByAllowedMimeTypes(): void
+    {
+        $resolver = new MediaTypeResolver();
+
+        self::assertSame(MediaType::Document, $resolver->resolve(MediaMimeType::fromString('application/pdf')));
+        self::assertSame(MediaType::Document, $resolver->resolve(MediaMimeType::fromString('application/msword')));
+        self::assertSame(MediaType::Document, $resolver->resolve(MediaMimeType::fromString('text/csv')));
+        self::assertSame(MediaType::Document, $resolver->resolve(MediaMimeType::fromString('text/markdown')));
+    }
+
+    public function testResolvesDjvuAsDocumentNotImage(): void
+    {
+        // У DJVU MIME image/vnd.djvu — список документов проверяется раньше префикса image/,
+        // поэтому он должен стать Document, а не Image.
+        self::assertSame(
+            MediaType::Document,
+            new MediaTypeResolver()->resolve(MediaMimeType::fromString('image/vnd.djvu')),
+        );
+    }
+
+    public function testNormalizesMimeBeforeClassifyingDocument(): void
+    {
+        $resolver = new MediaTypeResolver();
+
+        self::assertSame(
+            MediaType::Document,
+            $resolver->resolve(MediaMimeType::fromString('text/markdown;charset=utf-8')),
+        );
+        self::assertSame(MediaType::Document, $resolver->resolve(MediaMimeType::fromString('APPLICATION/PDF')));
+        self::assertSame(MediaType::Document, $resolver->resolve(MediaMimeType::fromString('TEXT/CSV')));
+    }
+
+    public function testResolvesOtherImageMimeAsImage(): void
+    {
+        // Список документов перехватывает только image/vnd.djvu; прочие image/* остаются Image.
+        self::assertSame(MediaType::Image, new MediaTypeResolver()->resolve(MediaMimeType::fromString('image/png')));
+    }
+
     #[DataProvider('unsupportedMimeTypeProvider')]
     public function testRejectsUnsupportedMimeTypes(string $mimeType): void
     {
@@ -36,6 +74,12 @@ final class MediaTypeResolverTest extends TestCase
      */
     public static function unsupportedMimeTypeProvider(): array
     {
-        return [['application/pdf'], ['text/plain'], ['font/woff2']];
+        return [
+            ['font/woff2'],
+            ['application/zip'],
+            ['application/octet-stream'],
+            // Office-формат с макросами намеренно не входит в список документов.
+            ['application/vnd.ms-word.document.macroEnabled.12'],
+        ];
     }
 }
diff --git a/tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php b/tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php
index 4a99061..e07f561 100644
--- a/tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php
+++ b/tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php
@@ -99,6 +99,10 @@ final class MediaValueObjectTest extends TestCase
             'audios/ab/ab111111-1111-4111-8111-111111111111/source.m4a',
             (string) MediaPath::originalReady(storageKey: $storageKey, type: MediaType::Audio, extension: 'm4a'),
         );
+        self::assertSame(
+            'documents/ab/ab111111-1111-4111-8111-111111111111/source.pdf',
+            (string) MediaPath::originalReady(storageKey: $storageKey, type: MediaType::Document, extension: 'pdf'),
+        );
         self::assertSame(
             'videos/ab/ab111111-1111-4111-8111-111111111111/normalizedMp4H264.mp4',
             (string) MediaPath::videoConversion(
@@ -129,15 +133,20 @@ final class MediaValueObjectTest extends TestCase
         self::assertSame('', MediaPath::fromString('uploads/ab/ab111111-1111-4111-8111-111111111111/source')->extension());
     }
 
-    public function testOriginalReadyRejectsUnsupportedMediaType(): void
+    public function testMediaPathAcceptsDocumentsPrefix(): void
+    {
+        self::assertSame(
+            'documents/ab/ab111111-1111-4111-8111-111111111111/source.pdf',
+            MediaPath::fromString('documents/ab/ab111111-1111-4111-8111-111111111111/source.pdf')->value(),
+        );
+    }
+
+    public function testMediaPathRejectsDocumentsPrefixWithWrongShard(): void
     {
         $this->expectException(InvalidDomainValueException::class);
+        $this->expectExceptionMessage('Путь файла имеет неверный раздел.');
 
-        MediaPath::originalReady(
-            storageKey: MediaStorageKey::generate(),
-            type: MediaType::Document,
-            extension: 'pdf',
-        );
+        MediaPath::fromString('documents/cd/ab111111-1111-4111-8111-111111111111/source.pdf');
     }
 
     public function testWaveformValidatesPeaksAndSerializes(): void
@@ -184,6 +193,19 @@ final class MediaValueObjectTest extends TestCase
         MediaMimeType::fromString('');
     }
 
+    public function testMimeTypeBaseValueNormalizesWithoutChangingRawValue(): void
+    {
+        self::assertSame('text/markdown', MediaMimeType::fromString('text/markdown;charset=utf-8')->baseValue());
+        self::assertSame('application/pdf', MediaMimeType::fromString('APPLICATION/PDF')->baseValue());
+        self::assertSame('image/jpeg', MediaMimeType::fromString('image/jpeg')->baseValue());
+
+        // value() отдаёт исходную строку с параметрами без изменений.
+        self::assertSame(
+            'text/markdown;charset=utf-8',
+            MediaMimeType::fromString('text/markdown;charset=utf-8')->value(),
+        );
+    }
+
     /**
      * @param class-string $valueObjectClass
      */

codex
Первый проход показал ключевой момент: само ревью содержит пункты про состояние всего рабочего дерева, а пользователь сейчас задал цель уже только по `app/src/Modules/Media/` и `tests/`. Проверю это отдельно по статусу и по фактическим файлам, чтобы не смешать область проверки.
exec
/bin/zsh -lc "nl -ba app/src/Modules/Media/Domain/ValueObject/MediaPath.php | sed -n '1,230p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Media\Domain\ValueObject;
     6	
     7	use App\Modules\Media\Domain\Enum\MediaAudioConversionType;
     8	use App\Modules\Media\Domain\Enum\MediaImageConversionType;
     9	use App\Modules\Media\Domain\Enum\MediaType;
    10	use App\Modules\Media\Domain\Enum\MediaVideoConversionType;
    11	use App\Shared\Domain\Exception\InvalidDomainValueException;
    12	use Ramsey\Uuid\Uuid;
    13	
    14	final readonly class MediaPath implements \Stringable, \JsonSerializable
    15	{
    16	    private const int MAX_LENGTH = 1024;
    17	
    18	    private function __construct(
    19	        private string $value,
    20	    ) {}
    21	
    22	    public static function originalUpload(MediaStorageKey $storageKey, string $extension): self
    23	    {
    24	        return self::fromString(
    25	            \sprintf('uploads/%s/%s/source.%s', $storageKey->shard(), $storageKey, self::sanitizeExtension($extension)),
    26	        );
    27	    }
    28	
    29	    public static function imageConversion(
    30	        MediaStorageKey $storageKey,
    31	        MediaImageConversionType $type,
    32	        string $extension,
    33	    ): self {
    34	        return self::fromString(
    35	            \sprintf(
    36	                'images/%s/%s/%s.%s',
    37	                $storageKey->shard(),
    38	                $storageKey,
    39	                $type->value,
    40	                self::sanitizeExtension($extension),
    41	            ),
    42	        );
    43	    }
    44	
    45	    public static function videoConversion(
    46	        MediaStorageKey $storageKey,
    47	        MediaVideoConversionType $type,
    48	        string $extension,
    49	    ): self {
    50	        return self::fromString(
    51	            \sprintf(
    52	                'videos/%s/%s/%s.%s',
    53	                $storageKey->shard(),
    54	                $storageKey,
    55	                $type->value,
    56	                self::sanitizeExtension($extension),
    57	            ),
    58	        );
    59	    }
    60	
    61	    public static function audioConversion(
    62	        MediaStorageKey $storageKey,
    63	        MediaAudioConversionType $type,
    64	        string $extension,
    65	    ): self {
    66	        return self::fromString(
    67	            \sprintf(
    68	                'audios/%s/%s/%s.%s',
    69	                $storageKey->shard(),
    70	                $storageKey,
    71	                $type->value,
    72	                self::sanitizeExtension($extension),
    73	            ),
    74	        );
    75	    }
    76	
    77	    public static function originalReady(MediaStorageKey $storageKey, MediaType $type, string $extension): self
    78	    {
    79	        $prefix = match ($type) {
    80	            MediaType::Image => 'images',
    81	            MediaType::Video => 'videos',
    82	            MediaType::Audio => 'audios',
    83	            MediaType::Document => 'documents',
    84	        };
    85	
    86	        return self::fromString(
    87	            \sprintf('%s/%s/%s/source.%s', $prefix, $storageKey->shard(), $storageKey, self::sanitizeExtension($extension)),
    88	        );
    89	    }
    90	
    91	    public static function fromString(string $value): self
    92	    {
    93	        $value = \trim($value);
    94	        self::assertValid($value);
    95	
    96	        return new self(value: $value);
    97	    }
    98	
    99	    public function value(): string
   100	    {
   101	        return $this->value;
   102	    }
   103	
   104	    /**
   105	     * Расширение файла из последнего сегмента пути (без точки). Используется для построения
   106	     * путей конверсий и готового оригинала с тем же форматом, что у загруженного файла.
   107	     */
   108	    public function extension(): string
   109	    {
   110	        $segments = \explode(separator: '/', string: $this->value);
   111	        $fileName = (string) \end($segments);
   112	        $dotPosition = \strrpos(haystack: $fileName, needle: '.');
   113	
   114	        return $dotPosition === false ? '' : \substr(string: $fileName, offset: $dotPosition + 1);
   115	    }
   116	
   117	    public function equals(self $other): bool
   118	    {
   119	        return $this->value === $other->value;
   120	    }
   121	
   122	    #[\Override]
   123	    public function __toString(): string
   124	    {
   125	        return $this->value;
   126	    }
   127	
   128	    #[\Override]
   129	    public function jsonSerialize(): string
   130	    {
   131	        return $this->value;
   132	    }
   133	
   134	    private static function sanitizeExtension(string $extension): string
   135	    {
   136	        $safeExtension = \strtolower(\trim($extension));
   137	
   138	        if ($safeExtension === '' || \preg_match(pattern: '/^[a-z0-9]+$/', subject: $safeExtension) !== 1) {
   139	            throw new InvalidDomainValueException('Расширение файла имеет неверный формат.');
   140	        }
   141	
   142	        return $safeExtension;
   143	    }
   144	
   145	    private static function assertValid(string $value): void
   146	    {
   147	        if (\strlen($value) > self::MAX_LENGTH) {
   148	            throw new InvalidDomainValueException('Путь файла слишком длинный.');
   149	        }
   150	
   151	        $matches = [];
   152	        if (\preg_match(
   153	            pattern: '/^(uploads|images|videos|audios|documents)\/([0-9a-f]{2})\/([0-9a-f-]{36})\/([^\/]+)$/',
   154	            subject: $value,
   155	            matches: $matches,
   156	        ) !== 1) {
   157	            throw new InvalidDomainValueException('Путь файла имеет неверный формат.');
   158	        }
   159	
   160	        if (!Uuid::isValid($matches[3]) || $matches[2] !== \substr(string: $matches[3], offset: 0, length: 2)) {
   161	            throw new InvalidDomainValueException('Путь файла имеет неверный раздел.');
   162	        }
   163	    }
   164	}

exec
/bin/zsh -lc "nl -ba app/src/Modules/Media/Domain/ValueObject/MediaMimeType.php | sed -n '1,120p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Media\Domain\ValueObject;
     6	
     7	use App\Shared\Domain\Exception\InvalidDomainValueException;
     8	
     9	final readonly class MediaMimeType implements \Stringable, \JsonSerializable
    10	{
    11	    private const int MAX_LENGTH = 255;
    12	
    13	    private function __construct(
    14	        private string $value,
    15	    ) {}
    16	
    17	    public static function fromString(string $value): self
    18	    {
    19	        $value = \trim($value);
    20	
    21	        if ($value === '' || \strlen($value) > self::MAX_LENGTH) {
    22	            throw new InvalidDomainValueException('MIME type файла имеет неверную длину.');
    23	        }
    24	
    25	        return new self(value: $value);
    26	    }
    27	
    28	    public function value(): string
    29	    {
    30	        return $this->value;
    31	    }
    32	
    33	    /**
    34	     * Базовый MIME без параметров: нижний регистр, отброшена часть после `;`, обрезаны пробелы
    35	     * (`text/markdown;charset=utf-8` -> `text/markdown`). Хранимое значение (`value()`) не меняется.
    36	     * Используется при всех сравнениях MIME, чтобы charset-параметр не ломал сопоставление.
    37	     */
    38	    public function baseValue(): string
    39	    {
    40	        return \strtolower(\trim(\explode(separator: ';', string: $this->value)[0]));
    41	    }
    42	
    43	    public function equals(self $other): bool
    44	    {
    45	        return $this->value === $other->value;
    46	    }
    47	
    48	    #[\Override]
    49	    public function __toString(): string
    50	    {
    51	        return $this->value;
    52	    }
    53	
    54	    #[\Override]
    55	    public function jsonSerialize(): string
    56	    {
    57	        return $this->value;
    58	    }
    59	}

exec
/bin/zsh -lc "nl -ba app/src/Modules/Media/Domain/Collection/MediaMimeTypeCollection.php | sed -n '1,80p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Media\Domain\Collection;
     6	
     7	use App\Modules\Media\Domain\ValueObject\MediaMimeType;
     8	use Illuminate\Support\Collection;
     9	
    10	/**
    11	 * @extends Collection<int, MediaMimeType>
    12	 */
    13	final class MediaMimeTypeCollection extends Collection
    14	{
    15	    public function containsMimeType(MediaMimeType $mimeType): bool
    16	    {
    17	        return $this->contains(
    18	            static fn(MediaMimeType $allowed): bool => $allowed->baseValue() === $mimeType->baseValue(),
    19	        );
    20	    }
    21	}

exec
/bin/zsh -lc 'git --no-pager diff --name-only HEAD -- app/src/Modules/Media/ tests/' in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php
app/src/Modules/Media/Application/Service/MediaTypeResolver.php
app/src/Modules/Media/Domain/Collection/MediaMimeTypeCollection.php
app/src/Modules/Media/Domain/ValueObject/MediaMimeType.php
app/src/Modules/Media/Domain/ValueObject/MediaPath.php
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php
tests/Unit/Modules/Media/Application/MediaTypeResolverTest.php
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php

exec
/bin/zsh -lc "nl -ba app/src/Modules/Media/Application/Service/MediaTypeResolver.php | sed -n '1,180p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Media\Application\Service;
     6	
     7	use App\Modules\Media\Domain\Enum\MediaType;
     8	use App\Modules\Media\Domain\ValueObject\MediaMimeType;
     9	use App\Shared\Domain\Exception\ValidationException;
    10	
    11	/**
    12	 * Резолвер MIME -> MediaType. Поддержан список разрешённых MIME-типов документов (он
    13	 * проверяется первым) и префиксы image/*, video/* и audio/*; прочие MIME вне области пайплайна
    14	 * и отклоняются как ожидаемая клиентская ошибка 422. MediaPath допускает префиксы
    15	 * uploads|images|videos|audios|documents.
    16	 */
    17	final readonly class MediaTypeResolver
    18	{
    19	    /**
    20	     * Форматы Office с макросами (MIME с суффиксом …macroEnabled.12) намеренно не включены
    21	     * из-за активного содержимого.
    22	     *
    23	     * @var list<string>
    24	     */
    25	    private const array DOCUMENT_MIME_TYPES = [
    26	        'application/pdf',
    27	        'application/msword',
    28	        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    29	        'text/plain',
    30	        'application/rtf',
    31	        'text/rtf',
    32	        'application/vnd.oasis.opendocument.text',
    33	        'application/vnd.oasis.opendocument.spreadsheet',
    34	        'application/vnd.oasis.opendocument.presentation',
    35	        'application/vnd.ms-excel',
    36	        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    37	        'application/vnd.ms-powerpoint',
    38	        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    39	        'application/epub+zip',
    40	        'application/x-fictionbook+xml',
    41	        'image/vnd.djvu',
    42	        'text/csv',
    43	        'text/markdown',
    44	    ];
    45	
    46	    public function resolve(MediaMimeType $mimeType): MediaType
    47	    {
    48	        if (\in_array(needle: $mimeType->baseValue(), haystack: self::DOCUMENT_MIME_TYPES, strict: true)) {
    49	            return MediaType::Document;
    50	        }
    51	
    52	        $value = $mimeType->value();
    53	
    54	        if (\str_starts_with(haystack: $value, needle: 'image/')) {
    55	            return MediaType::Image;
    56	        }
    57	
    58	        if (\str_starts_with(haystack: $value, needle: 'video/')) {
    59	            return MediaType::Video;
    60	        }
    61	
    62	        if (\str_starts_with(haystack: $value, needle: 'audio/')) {
    63	            return MediaType::Audio;
    64	        }
    65	
    66	        throw new ValidationException(
    67	            translationKey: 'app.media.unsupported_file_type',
    68	            translationParameters: ['type' => $value],
    69	        );
    70	    }
    71	}

exec
/bin/zsh -lc "nl -ba app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php | sed -n '1,190p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Media\Application\Command\ProcessMedia;
     6	
     7	use App\Modules\Media\Application\Contract\MediaAudioProcessorContract;
     8	use App\Modules\Media\Application\Contract\MediaFileServiceContract;
     9	use App\Modules\Media\Application\Contract\MediaImageProcessorContract;
    10	use App\Modules\Media\Application\Contract\MediaVideoProcessorContract;
    11	use App\Modules\Media\Application\Dto\MediaConversionPlan;
    12	use App\Modules\Media\Domain\Entity\Media;
    13	use App\Modules\Media\Domain\Entity\MediaAudioConversion;
    14	use App\Modules\Media\Domain\Entity\MediaImageConversion;
    15	use App\Modules\Media\Domain\Entity\MediaVideoConversion;
    16	use App\Modules\Media\Domain\Enum\MediaConversionStatus;
    17	use App\Modules\Media\Domain\Enum\MediaImageConversionType;
    18	use App\Modules\Media\Domain\Enum\MediaStorage;
    19	use App\Modules\Media\Domain\Enum\MediaType;
    20	use App\Modules\Media\Domain\Enum\MediaVisibility;
    21	use App\Modules\Media\Domain\ValueObject\MediaId;
    22	use App\Modules\Media\Domain\ValueObject\MediaPath;
    23	use App\Modules\Media\Domain\ValueObject\MediaPixelDimension;
    24	use App\Modules\Media\Repository\MediaRepository;
    25	use App\Shared\Domain\Exception\NotFoundException;
    26	use Cycle\ORM\EntityManagerInterface;
    27	use GianTiaga\SpiralCqrs\Attribute\LogOperation;
    28	use Illuminate\Support\Collection;
    29	use Psr\Log\LoggerInterface;
    30	
    31	/**
    32	 * Без #[Transactional]: все S3/ffmpeg/Imagick-операции выполняются вне транзакции, затем один
    33	 * атомарный persist+run() с переходом в ready. Идемпотентен: на ready — no-op; конверсии
    34	 * создаются только в финальном flush, поэтому частичного состояния не бывает и повтор
    35	 * пересоздаёт их без конфликта по unique (media_id, type) (процессоры перезаписывают объекты
    36	 * по детерминированным путям).
    37	 */
    38	final readonly class ProcessMediaHandler
    39	{
    40	    public function __construct(
    41	        private MediaRepository $mediaRepository,
    42	        private MediaFileServiceContract $mediaFileService,
    43	        private MediaImageProcessorContract $mediaImageProcessor,
    44	        private MediaVideoProcessorContract $mediaVideoProcessor,
    45	        private MediaAudioProcessorContract $mediaAudioProcessor,
    46	        private EntityManagerInterface $entityManager,
    47	        private LoggerInterface $logger,
    48	    ) {}
    49	
    50	    #[LogOperation]
    51	    public function handle(ProcessMediaCommand $command): void
    52	    {
    53	        $media = $this->mediaRepository->findById(MediaId::fromString($command->mediaId))
    54	            ?? throw new NotFoundException('app.media.not_found');
    55	
    56	        if ($media->isReady()) {
    57	            $this->logger->debug(message: 'Обработка медиа пропущена: уже ready.', context: [
    58	                'mediaId' => $media->id->value(),
    59	            ]);
    60	
    61	            return;
    62	        }
    63	
    64	        $this->logger->debug(message: 'Начата обработка медиа.', context: [
    65	            'mediaId' => $media->id->value(),
    66	            'type' => $media->type->value,
    67	        ]);
    68	
    69	        $targetStorage = $this->targetStorage($media->visibility);
    70	        $extension = $media->path->extension();
    71	
    72	        $conversions = match ($media->type) {
    73	            MediaType::Image => $this->buildImageConversions(
    74	                media: $media,
    75	                plan: $command->plan,
    76	                targetStorage: $targetStorage,
    77	                extension: $extension,
    78	            ),
    79	            MediaType::Video => $this->buildVideoConversions(
    80	                media: $media,
    81	                plan: $command->plan,
    82	                targetStorage: $targetStorage,
    83	            ),
    84	            MediaType::Audio => $this->buildAudioConversions(
    85	                media: $media,
    86	                plan: $command->plan,
    87	                targetStorage: $targetStorage,
    88	            ),
    89	            MediaType::Document => [],
    90	        };
    91	
    92	        $this->persistReady(
    93	            media: $media,
    94	            conversions: $conversions,
    95	            targetStorage: $targetStorage,
    96	            extension: $extension,
    97	        );
    98	    }
    99	
   100	    /**
   101	     * @param list<MediaImageConversion|MediaVideoConversion|MediaAudioConversion> $conversions
   102	     */
   103	    private function persistReady(
   104	        Media $media,
   105	        array $conversions,
   106	        MediaStorage $targetStorage,
   107	        string $extension,
   108	    ): void {
   109	        $targetPath = MediaPath::originalReady(storageKey: $media->storageKey, type: $media->type, extension: $extension);
   110	        $this->mediaFileService->copyObject(
   111	            fromStorage: $media->storage,
   112	            fromPath: $media->path,
   113	            toStorage: $targetStorage,
   114	            toPath: $targetPath,
   115	        );
   116	        $media->markReadyMovedTo(storage: $targetStorage, path: $targetPath);
   117	
   118	        $this->entityManager->persist($media);
   119	        foreach ($conversions as $conversion) {
   120	            $this->entityManager->persist($conversion);
   121	        }
   122	        $this->entityManager->run();
   123	
   124	        $this->logger->debug(message: 'Медиа готово.', context: [
   125	            'mediaId' => $media->id->value(),
   126	            'targetStorage' => $targetStorage->value,
   127	            'imageConversions' => $this->countByClass(conversions: $conversions, conversionClass: MediaImageConversion::class),
   128	            'videoConversions' => $this->countByClass(conversions: $conversions, conversionClass: MediaVideoConversion::class),
   129	            'audioConversions' => $this->countByClass(conversions: $conversions, conversionClass: MediaAudioConversion::class),
   130	        ]);
   131	    }
   132	
   133	    /**
   134	     * @param list<MediaImageConversion|MediaVideoConversion|MediaAudioConversion> $conversions
   135	     * @param class-string $conversionClass
   136	     */
   137	    private function countByClass(array $conversions, string $conversionClass): int
   138	    {
   139	        return Collection::make($conversions)
   140	            ->filter(static fn(MediaImageConversion|MediaVideoConversion|MediaAudioConversion $conversion): bool
   141	                => $conversion instanceof $conversionClass)
   142	            ->count();
   143	    }
   144	
   145	    /**
   146	     * @return list<MediaImageConversion>
   147	     */
   148	    private function buildImageConversions(
   149	        Media $media,
   150	        MediaConversionPlan $plan,
   151	        MediaStorage $targetStorage,
   152	        string $extension,
   153	    ): array {
   154	        if ($plan->image === []) {
   155	            return [];
   156	        }
   157	
   158	        $originalContents = $this->mediaFileService->getObjectContents(storage: $media->storage, path: $media->path);
   159	
   160	        $conversions = [];
   161	
   162	        foreach ($plan->image as $spec) {
   163	            $conversionResult = $this->mediaImageProcessor->resize(
   164	                originalContents: $originalContents,
   165	                width: MediaPixelDimension::fromInt($spec->width),
   166	                height: MediaPixelDimension::fromInt($spec->height),
   167	                targetMimeType: $media->mimeType,
   168	            );
   169	            $conversionPath = MediaPath::imageConversion(
   170	                storageKey: $media->storageKey,
   171	                type: $spec->type,
   172	                extension: $extension,
   173	            );
   174	            $this->mediaFileService->putObject(
   175	                storage: $targetStorage,
   176	                path: $conversionPath,
   177	                contents: $conversionResult->contents,
   178	                mimeType: $conversionResult->mimeType,
   179	            );
   180	
   181	            $this->logger->debug(message: 'Создана конверсия изображения.', context: [
   182	                'type' => $spec->type->value,
   183	                'path' => $conversionPath->value(),
   184	                'size' => $conversionResult->size->value(),
   185	            ]);
   186	
   187	            // width/height берём из результата процессора (размер реально записанного объекта),
   188	            // а не из spec — устойчиво к смене режима ресайза. См. README.
   189	            $conversions[] = MediaImageConversion::create(
   190	                media: $media,

exec
/bin/zsh -lc 'git status --short' in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
 M app/src/Modules/Auth/Application/Query/GetUserSessions/GetUserSessionsHandler.php
 M app/src/Modules/Auth/Presentation/Http/Controller/AuthController.php
 M app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php
 M app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php
 M app/src/Modules/Media/Application/Service/MediaTypeResolver.php
 M app/src/Modules/Media/Domain/Collection/MediaMimeTypeCollection.php
 M app/src/Modules/Media/Domain/ValueObject/MediaMimeType.php
 M app/src/Modules/Media/Domain/ValueObject/MediaPath.php
 M app/src/Modules/Notifications/Application/Query/Notification/ListNotifications/ListNotificationsHandler.php
 M app/src/Modules/Notifications/Repository/NotificationRepository.php
 M app/src/Modules/Posts/Application/Query/GetCommentReplies/GetCommentRepliesHandler.php
 M app/src/Modules/Posts/Application/Query/GetCommentReplies/GetCommentRepliesResult.php
 M app/src/Modules/Posts/Application/Query/GetPostComments/GetPostCommentsHandler.php
 M app/src/Modules/Posts/Application/Query/GetPostComments/GetPostCommentsResult.php
 M app/src/Modules/Posts/Application/Query/GetUserFeed/GetUserFeedHandler.php
 M app/src/Modules/Posts/Application/Query/GetUserFeed/GetUserFeedResult.php
 M app/src/Modules/Posts/Application/View/CommentViewAssembler.php
 M app/src/Modules/Posts/Application/View/PostViewAssembler.php
 M app/src/Modules/Posts/Presentation/Http/Controller/CommentController.php
 M app/src/Modules/Posts/Presentation/Http/Controller/PostController.php
 M app/src/Modules/Posts/Repository/CommentRepository.php
 M app/src/Modules/Posts/Repository/PostRepository.php
 M app/src/Modules/User/Application/Query/GetUserPublicProfiles/GetUserPublicProfilesHandler.php
 M app/src/Shared/Infrastructure/Cycle/WhenSelect.php
 M docs/rules.md
 M tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php
 M tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php
 M tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php
 M tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php
 M tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php
 M tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php
 M tests/Unit/Modules/Media/Application/MediaTypeResolverTest.php
 M tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php
?? app/src/Modules/Posts/Application/View/CommentViewCollection.php
?? app/src/Modules/Posts/Application/View/PostViewCollection.php
?? app/src/Shared/Domain/Pagination/
?? docs/executions/2026-06-22_18-09_media-document-support.md
?? docs/fixes/2026-06-22_17-34_collection-round-trip.md
?? docs/plans/2026-06-22_17-35_media-document-support.md
?? docs/plans/2026-06-22_17-35_media-document-support_review.md
?? docs/researches/2026-06-22_17-22_media-document-support.md
?? docs/researches/2026-06-22_17-22_media-document-support_review.md
?? docs/reviews/2026-06-22_18-37_media-document-support.md
?? docs/reviews/2026-06-22_18-37_media-document-support_cli_meta.md
?? tests/Unit/Modules/Media/Domain/Collection/
?? tests/Unit/Shared/Domain/Pagination/

exec
/bin/zsh -lc "nl -ba app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php | sed -n '1,220p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Media\Application\Command\CompleteMediaUpload;
     6	
     7	use App\Modules\Media\Application\Contract\MediaFileServiceContract;
     8	use App\Modules\Media\Application\Dto\MediaConversionPlan;
     9	use App\Modules\Media\Application\Dto\MediaImageConversionSpec;
    10	use App\Modules\Media\Application\Dto\MediaResult;
    11	use App\Modules\Media\Application\Message\MediaUploaded;
    12	use App\Modules\Media\Domain\Collection\MediaMultipartPartCollection;
    13	use App\Modules\Media\Domain\Entity\Media;
    14	use App\Modules\Media\Domain\Enum\MediaStatus;
    15	use App\Modules\Media\Domain\Enum\MediaType;
    16	use App\Modules\Media\Domain\ValueObject\MediaBitrate;
    17	use App\Modules\Media\Domain\ValueObject\MediaId;
    18	use App\Modules\Media\Domain\ValueObject\MediaPixelDimension;
    19	use App\Modules\Media\Domain\ValueObject\MediaSampleRate;
    20	use App\Modules\Media\Domain\ValueObject\MediaWaveformPeakCount;
    21	use App\Modules\Media\Repository\MediaMultipartUploadRepository;
    22	use App\Modules\Media\Repository\MediaRepository;
    23	use App\Modules\Outbox\Application\Contract\OutboxEventStoreContract;
    24	use App\Shared\Domain\Exception\ForbiddenException;
    25	use App\Shared\Domain\Exception\NotFoundException;
    26	use App\Shared\Domain\Exception\ValidationException;
    27	use App\Shared\Domain\ValueObject\UserId;
    28	use Cycle\ORM\EntityManagerInterface;
    29	use GianTiaga\SpiralCqrs\Attribute\LogOperation;
    30	use GianTiaga\SpiralCqrs\Attribute\Transactional;
    31	use Illuminate\Support\Collection;
    32	use Psr\Log\LoggerInterface;
    33	
    34	final readonly class CompleteMediaUploadHandler
    35	{
    36	    public function __construct(
    37	        private MediaRepository $mediaRepository,
    38	        private MediaMultipartUploadRepository $mediaMultipartUploadRepository,
    39	        private MediaFileServiceContract $mediaFileService,
    40	        private OutboxEventStoreContract $outboxEventStore,
    41	        private EntityManagerInterface $entityManager,
    42	        private LoggerInterface $logger,
    43	    ) {}
    44	
    45	    #[Transactional]
    46	    #[LogOperation]
    47	    public function handle(CompleteMediaUploadCommand $command): MediaResult
    48	    {
    49	        $media = $this->mediaRepository->findById(MediaId::fromString($command->mediaId))
    50	            ?? throw new NotFoundException('app.media.not_found');
    51	
    52	        if (!$media->uploadedById->equals(UserId::fromString($command->userId))) {
    53	            throw new ForbiddenException('app.media.access_denied');
    54	        }
    55	
    56	        if ($media->status !== MediaStatus::WaitingUpload) {
    57	            throw new ValidationException('app.media.upload_not_pending');
    58	        }
    59	
    60	        $this->assertPlanValid(plan: $command->plan, type: $media->type);
    61	
    62	        if ($command->parts !== null) {
    63	            $this->completeMultipartUpload(media: $media, parts: $command->parts);
    64	        }
    65	
    66	        $this->assertObjectUploaded($media);
    67	
    68	        $media->markUploaded();
    69	        $this->outboxEventStore->add(new MediaUploaded(
    70	            mediaId: $media->id->value(),
    71	            plan: $command->plan,
    72	        ));
    73	        $this->entityManager->persist($media);
    74	        $this->entityManager->run();
    75	
    76	        $this->logger->debug(message: 'Загрузка медиа подтверждена.', context: [
    77	            'mediaId' => $media->id->value(),
    78	            'userId' => $command->userId,
    79	            'imageConversions' => \count($command->plan->image),
    80	            'videoConversions' => \count($command->plan->video),
    81	            'audioConversions' => \count($command->plan->audio),
    82	        ]);
    83	
    84	        return MediaResult::fromEntity($media);
    85	    }
    86	
    87	    private function completeMultipartUpload(Media $media, MediaMultipartPartCollection $parts): void
    88	    {
    89	        $multipartUpload = $this->mediaMultipartUploadRepository->findByMediaId($media->id)
    90	            ?? throw new ValidationException('app.media.multipart_upload_not_found');
    91	
    92	        $multipartUpload->replaceParts($parts);
    93	        $this->entityManager->persist($multipartUpload);
    94	        $this->mediaFileService->completeMultipartUpload(
    95	            storage: $media->storage,
    96	            path: $media->path,
    97	            uploadId: $multipartUpload->uploadId,
    98	            parts: $parts,
    99	        );
   100	    }
   101	
   102	    private function assertObjectUploaded(Media $media): void
   103	    {
   104	        $objectHead = $this->mediaFileService->headObject(storage: $media->storage, path: $media->path);
   105	
   106	        if ($objectHead === null || $objectHead->contentLength->value() !== $media->size->value()) {
   107	            throw new ValidationException('app.media.uploaded_object_mismatch');
   108	        }
   109	    }
   110	
   111	    /**
   112	     * Валидирует ТОЛЬКО список, относящийся к типу медиа; список «не своего» типа должен быть
   113	     * пустым (кросс-тип → 422). Диапазоны проверяются через контракт VO ::supports() на
   114	     * Application-границе, чтобы невалидная спека не прошла подтверждение и не упала асинхронно в
   115	     * ProcessMedia (после S3-записей и unique-конфликта).
   116	     */
   117	    private function assertPlanValid(MediaConversionPlan $plan, MediaType $type): void
   118	    {
   119	        match ($type) {
   120	            MediaType::Image => $this->assertImagePlan($plan),
   121	            MediaType::Video => $this->assertVideoPlan($plan),
   122	            MediaType::Audio => $this->assertAudioPlan($plan),
   123	            MediaType::Document => $this->assertDocumentPlan($plan),
   124	        };
   125	    }
   126	
   127	    private function assertDocumentPlan(MediaConversionPlan $plan): void
   128	    {
   129	        if ($plan->image !== [] || $plan->video !== [] || $plan->audio !== []) {
   130	            throw new ValidationException('app.media.conversion_plan_type_mismatch');
   131	        }
   132	    }
   133	
   134	    private function assertImagePlan(MediaConversionPlan $plan): void
   135	    {
   136	        if ($plan->video !== [] || $plan->audio !== []) {
   137	            throw new ValidationException('app.media.conversion_plan_type_mismatch');
   138	        }
   139	
   140	        $types = Collection::make($plan->image)->map(
   141	            static fn(MediaImageConversionSpec $spec): string => $spec->type->value,
   142	        );
   143	        if ($types->count() !== $types->unique()->count()) {
   144	            throw new ValidationException('app.media.conversion_duplicate_type');
   145	        }
   146	
   147	        foreach ($plan->image as $spec) {
   148	            if (!MediaPixelDimension::supports($spec->width) || !MediaPixelDimension::supports($spec->height)) {
   149	                throw new ValidationException('app.media.conversion_dimensions_out_of_range');
   150	            }
   151	        }
   152	    }
   153	
   154	    private function assertVideoPlan(MediaConversionPlan $plan): void
   155	    {
   156	        if ($plan->image !== [] || $plan->audio !== []) {
   157	            throw new ValidationException('app.media.conversion_plan_type_mismatch');
   158	        }
   159	
   160	        // Ровно один профиль: проверка дубликатов типа (как в assertImagePlan) тут не нужна —
   161	        // список из двух элементов отвергается раньше, поэтому дубль физически невозможен.
   162	        if (\count($plan->video) !== 1) {
   163	            throw new ValidationException('app.media.video_conversion_profile_required');
   164	        }
   165	
   166	        $spec = $plan->video[0];
   167	        if (!MediaPixelDimension::supports($spec->width) || !MediaPixelDimension::supports($spec->height)) {
   168	            throw new ValidationException('app.media.conversion_dimensions_out_of_range');
   169	        }
   170	
   171	        if (!MediaBitrate::supports($spec->videoBitrate) || !MediaBitrate::supports($spec->audioBitrate)) {
   172	            throw new ValidationException('app.media.conversion_bitrate_out_of_range');
   173	        }
   174	    }
   175	
   176	    private function assertAudioPlan(MediaConversionPlan $plan): void
   177	    {
   178	        if ($plan->image !== [] || $plan->video !== []) {
   179	            throw new ValidationException('app.media.conversion_plan_type_mismatch');
   180	        }
   181	
   182	        // Ровно один профиль: проверка дубликатов типа (как в assertImagePlan) тут не нужна —
   183	        // список из двух элементов отвергается раньше, поэтому дубль физически невозможен.
   184	        if (\count($plan->audio) !== 1) {
   185	            throw new ValidationException('app.media.audio_conversion_profile_required');
   186	        }
   187	
   188	        $spec = $plan->audio[0];
   189	        if (!MediaBitrate::supports($spec->bitrate)) {
   190	            throw new ValidationException('app.media.conversion_bitrate_out_of_range');
   191	        }
   192	
   193	        if (!MediaSampleRate::supports($spec->sampleRate)) {
   194	            throw new ValidationException('app.media.conversion_sample_rate_out_of_range');
   195	        }
   196	
   197	        if (!MediaWaveformPeakCount::supports($spec->waveformPeaks)) {
   198	            throw new ValidationException('app.media.conversion_waveform_peaks_out_of_range');
   199	        }
   200	    }
   201	}

codex
По коду Media пока видно, что основная реализация плана есть: документ распознаётся до префиксов, путь `documents/` разрешён, обработка делает перенос без конверсий. Дальше смотрю тесты и смежные сценарии, потому что пропущенные риски чаще всего сидят на границе `RequestMediaUpload` и `ProcessMedia`.
exec
/bin/zsh -lc "nl -ba app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php | sed -n '1,180p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Media\Application\Command\RequestMediaUpload;
     6	
     7	use App\Modules\Media\Application\Contract\MediaFileServiceContract;
     8	use App\Modules\Media\Application\Dto\MediaFileMeta;
     9	use App\Modules\Media\Application\Dto\RequestMediaUploadResult;
    10	use App\Modules\Media\Application\Dto\MediaUploadSpec;
    11	use App\Modules\Media\Application\Service\MediaTypeResolver;
    12	use App\Modules\Media\Domain\Entity\Media;
    13	use App\Modules\Media\Domain\Entity\MediaMultipartUpload;
    14	use App\Modules\Media\Domain\ValueObject\MediaExpiration;
    15	use App\Modules\Media\Domain\ValueObject\MediaFileSize;
    16	use App\Modules\Media\Domain\ValueObject\MediaMultipartPartSize;
    17	use App\Modules\Media\Domain\ValueObject\MediaMultipartPartsCount;
    18	use App\Modules\Media\Domain\ValueObject\MediaPath;
    19	use App\Modules\Media\Domain\ValueObject\MediaStorageKey;
    20	use App\Shared\Domain\Exception\ValidationException;
    21	use App\Shared\Domain\ValueObject\UserId;
    22	use App\Shared\Infrastructure\Configuration\Media\MediaConfig;
    23	use Cycle\ORM\EntityManagerInterface;
    24	use GianTiaga\SpiralCqrs\Attribute\LogOperation;
    25	use Psr\Log\LoggerInterface;
    26	
    27	final readonly class RequestMediaUploadHandler
    28	{
    29	    public function __construct(
    30	        private MediaFileServiceContract $mediaFileService,
    31	        private MediaTypeResolver $mediaTypeResolver,
    32	        private MediaConfig $mediaConfig,
    33	        private EntityManagerInterface $entityManager,
    34	        private LoggerInterface $logger,
    35	    ) {}
    36	
    37	    #[LogOperation]
    38	    public function handle(RequestMediaUploadCommand $command): RequestMediaUploadResult
    39	    {
    40	        $mediaType = $this->mediaTypeResolver->resolve($command->fileMeta->mimeType);
    41	        $this->assertUploadAllowed(spec: $command->spec, fileMeta: $command->fileMeta);
    42	
    43	        $storageKey = MediaStorageKey::generate();
    44	        $media = Media::create(
    45	            storageKey: $storageKey,
    46	            type: $mediaType,
    47	            visibility: $command->spec->visibility,
    48	            path: MediaPath::originalUpload(storageKey: $storageKey, extension: $this->extension($command->fileMeta)),
    49	            mimeType: $command->fileMeta->mimeType,
    50	            size: $command->fileMeta->size,
    51	            uploadedById: UserId::fromString($command->userId),
    52	            expiration: MediaExpiration::temporaryUntil($this->expiresIn($this->mediaConfig->stagingTtlSeconds)),
    53	        );
    54	
    55	        $presignedExpiresAt = $this->expiresIn($command->spec->presignedTtl->value());
    56	        $preparedUpload = $this->isMultipart($command->fileMeta->size)
    57	            ? $this->prepareMultipartUpload(media: $media, presignedExpiresAt: $presignedExpiresAt)
    58	            : $this->prepareSingleUpload(media: $media, presignedExpiresAt: $presignedExpiresAt);
    59	
    60	        $this->entityManager->run();
    61	
    62	        $this->logger->debug(message: 'Запрошена загрузка медиа.', context: [
    63	            'mediaId' => $media->id->value(),
    64	            'userId' => $command->userId,
    65	            'uploadMode' => $preparedUpload->uploadMode->value,
    66	            'size' => $command->fileMeta->size->value(),
    67	            'mimeType' => $command->fileMeta->mimeType->value(),
    68	            'presignedTtlSeconds' => $command->spec->presignedTtl->value(),
    69	        ]);
    70	
    71	        return $preparedUpload;
    72	    }
    73	
    74	    private function prepareSingleUpload(Media $media, \DateTimeImmutable $presignedExpiresAt): RequestMediaUploadResult
    75	    {
    76	        $putUrl = $this->mediaFileService->presignPut(
    77	            storage: $media->storage,
    78	            path: $media->path,
    79	            mimeType: $media->mimeType,
    80	            expiresAt: $presignedExpiresAt,
    81	        );
    82	        $this->entityManager->persist($media);
    83	
    84	        return RequestMediaUploadResult::single(
    85	            mediaId: $media->id->value(),
    86	            putUrl: $putUrl,
    87	            expiresAt: $presignedExpiresAt,
    88	        );
    89	    }
    90	
    91	    private function prepareMultipartUpload(Media $media, \DateTimeImmutable $presignedExpiresAt): RequestMediaUploadResult
    92	    {
    93	        $partSize = MediaMultipartPartSize::fromInt($this->mediaConfig->multipartPartSizeBytes);
    94	        $partsCount = MediaMultipartPartsCount::fromInt($this->partsCount(size: $media->size, partSize: $partSize));
    95	        $uploadId = $this->mediaFileService->createMultipartUpload(
    96	            storage: $media->storage,
    97	            path: $media->path,
    98	            mimeType: $media->mimeType,
    99	        );
   100	        $parts = $this->mediaFileService->presignUploadParts(
   101	            storage: $media->storage,
   102	            path: $media->path,
   103	            uploadId: $uploadId,
   104	            partsCount: $partsCount,
   105	            expiresAt: $presignedExpiresAt,
   106	        );
   107	
   108	        $this->entityManager->persist($media);
   109	        $this->entityManager->persist(MediaMultipartUpload::create(
   110	            media: $media,
   111	            uploadId: $uploadId,
   112	            partsCount: $partsCount,
   113	            partSize: $partSize,
   114	            fileSize: $media->size,
   115	        ));
   116	
   117	        return RequestMediaUploadResult::multipart(
   118	            mediaId: $media->id->value(),
   119	            uploadId: $uploadId->value(),
   120	            parts: $parts,
   121	            expiresAt: $presignedExpiresAt,
   122	        );
   123	    }
   124	
   125	    private function assertUploadAllowed(MediaUploadSpec $spec, MediaFileMeta $fileMeta): void
   126	    {
   127	        if (!$spec->allowedMimeTypes->containsMimeType($fileMeta->mimeType)) {
   128	            throw new ValidationException(
   129	                translationKey: 'app.media.mime_not_allowed',
   130	                translationParameters: ['mimeType' => $fileMeta->mimeType->value()],
   131	            );
   132	        }
   133	
   134	        if ($fileMeta->size->value() > $spec->maxSize->value()) {
   135	            throw new ValidationException('app.media.file_size_exceeded');
   136	        }
   137	    }
   138	
   139	    private function extension(MediaFileMeta $fileMeta): string
   140	    {
   141	        $extension = \pathinfo(path: $fileMeta->fileName, flags: \PATHINFO_EXTENSION);
   142	
   143	        if ($extension === '') {
   144	            throw new ValidationException('app.media.file_name_without_extension');
   145	        }
   146	
   147	        return $extension;
   148	    }
   149	
   150	    private function isMultipart(MediaFileSize $size): bool
   151	    {
   152	        return $size->value() >= $this->mediaConfig->multipartThresholdBytes;
   153	    }
   154	
   155	    private function partsCount(MediaFileSize $size, MediaMultipartPartSize $partSize): int
   156	    {
   157	        return (int) \ceil($size->value() / $partSize->value());
   158	    }
   159	
   160	    private function expiresIn(int $seconds): \DateTimeImmutable
   161	    {
   162	        return new \DateTimeImmutable()->add(new \DateInterval(\sprintf('PT%dS', $seconds)));
   163	    }
   164	}

exec
/bin/zsh -lc "nl -ba tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php | sed -n '1,320p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc "nl -ba app/src/Modules/Media/Application/Dto/MediaUploadSpec.php | sed -n '1,120p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Media\Application\Dto;
     6	
     7	use App\Modules\Media\Domain\Collection\MediaMimeTypeCollection;
     8	use App\Modules\Media\Domain\Enum\MediaVisibility;
     9	use App\Modules\Media\Domain\ValueObject\MediaFileSize;
    10	use App\Modules\Media\Domain\ValueObject\MediaPresignedTtl;
    11	
    12	/**
    13	 * Спецификация ограничений загрузки, которую потребитель передаёт в RequestMediaUpload.
    14	 * In-process DTO: держит политику загрузки на стороне серверного модуля-потребителя.
    15	 */
    16	final readonly class MediaUploadSpec
    17	{
    18	    public function __construct(
    19	        public MediaMimeTypeCollection $allowedMimeTypes,
    20	        public MediaFileSize $maxSize,
    21	        public MediaVisibility $visibility,
    22	        public MediaPresignedTtl $presignedTtl,
    23	    ) {}
    24	}

 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace Tests\Feature\Modules\Media\Application;
     6	
     7	use App\Modules\Media\Application\Command\RequestMediaUpload\RequestMediaUploadCommand;
     8	use App\Modules\Media\Application\Command\RequestMediaUpload\RequestMediaUploadHandler;
     9	use App\Modules\Media\Application\Contract\MediaFileServiceContract;
    10	use App\Modules\Media\Application\Dto\MediaFileMeta;
    11	use App\Modules\Media\Application\Dto\MediaPresignedPart;
    12	use App\Modules\Media\Application\Dto\MediaPresignedPartCollection;
    13	use App\Modules\Media\Application\Dto\MediaUploadMode;
    14	use App\Modules\Media\Application\Dto\MediaUploadSpec;
    15	use App\Modules\Media\Application\Service\MediaTypeResolver;
    16	use App\Modules\Media\Domain\Collection\MediaMimeTypeCollection;
    17	use App\Modules\Media\Domain\Enum\MediaStatus;
    18	use App\Modules\Media\Domain\Enum\MediaStorage;
    19	use App\Modules\Media\Domain\Enum\MediaType;
    20	use App\Modules\Media\Domain\Enum\MediaVisibility;
    21	use App\Modules\Media\Domain\ValueObject\MediaFileSize;
    22	use App\Modules\Media\Domain\ValueObject\MediaId;
    23	use App\Modules\Media\Domain\ValueObject\MediaMimeType;
    24	use App\Modules\Media\Domain\ValueObject\MediaMultipartPartsCount;
    25	use App\Modules\Media\Domain\ValueObject\MediaMultipartUploadIdValue;
    26	use App\Modules\Media\Domain\ValueObject\MediaPath;
    27	use App\Modules\Media\Domain\ValueObject\MediaPresignedTtl;
    28	use App\Shared\Domain\Exception\ValidationException;
    29	use App\Shared\Domain\ValueObject\UserId;
    30	use App\Shared\Infrastructure\Configuration\Media\MediaConfig;
    31	use Psr\Log\LoggerInterface;
    32	use Psr\Log\NullLogger;
    33	use Tests\Feature\Modules\Media\Flow\Fixture\RecordingMediaLogger;
    34	
    35	final class RequestMediaUploadHandlerTest extends MediaApplicationTestCase
    36	{
    37	    public function testRequestsSingleUpload(): void
    38	    {
    39	        $capturedExpiresAt = null;
    40	        $fileService = $this->createMock(MediaFileServiceContract::class);
    41	        $fileService->expects(self::once())->method('presignPut')->willReturnCallback(
    42	            static function (
    43	                MediaStorage $storage,
    44	                MediaPath $path,
    45	                MediaMimeType $mimeType,
    46	                \DateTimeImmutable $expiresAt,
    47	            ) use (&$capturedExpiresAt): string {
    48	                $capturedExpiresAt = $expiresAt;
    49	
    50	                return 'http://minio/put-url';
    51	            },
    52	        );
    53	
    54	        $logger = new RecordingMediaLogger();
    55	        $result = $this->handler($fileService, logger: $logger)->handle(new RequestMediaUploadCommand(
    56	            userId: UserId::generate()->value(),
    57	            spec: $this->spec(presignedTtl: 300),
    58	            fileMeta: $this->fileMeta(size: 1024),
    59	        ));
    60	
    61	        self::assertSame(MediaUploadMode::Single, $result->uploadMode);
    62	        self::assertSame('http://minio/put-url', $result->putUrl);
    63	        self::assertNull($result->parts);
    64	
    65	        // TTL presigned-ссылки берётся из спеки (300), а не из конфиг-дефолта 900.
    66	        self::assertNotNull($capturedExpiresAt);
    67	        self::assertEqualsWithDelta(
    68	            new \DateTimeImmutable('+300 seconds')->getTimestamp(),
    69	            $capturedExpiresAt->getTimestamp(),
    70	            5,
    71	        );
    72	        self::assertSame(300, $logger->contextFor('Запрошена загрузка медиа.')['presignedTtlSeconds']);
    73	
    74	        $media = $this->mediaRepository()->findById(MediaId::fromString($result->mediaId));
    75	        self::assertNotNull($media);
    76	        self::assertSame(MediaStatus::WaitingUpload, $media->status);
    77	    }
    78	
    79	    public function testRequestsMultipartUploadWhenSizeReachesThreshold(): void
    80	    {
    81	        $capturedExpiresAt = null;
    82	        $fileService = $this->createMock(MediaFileServiceContract::class);
    83	        $fileService->method('createMultipartUpload')->willReturn(MediaMultipartUploadIdValue::fromString('upload-1'));
    84	        $fileService->method('presignUploadParts')->willReturnCallback(
    85	            static function (
    86	                MediaStorage $storage,
    87	                MediaPath $path,
    88	                MediaMultipartUploadIdValue $uploadId,
    89	                MediaMultipartPartsCount $partsCount,
    90	                \DateTimeImmutable $expiresAt,
    91	            ) use (&$capturedExpiresAt): MediaPresignedPartCollection {
    92	                $capturedExpiresAt = $expiresAt;
    93	
    94	                return new MediaPresignedPartCollection([new MediaPresignedPart(partNumber: 1, url: 'http://minio/part-1')]);
    95	            },
    96	        );
    97	        $fileService->expects(self::never())->method('presignPut');
    98	
    99	        $result = $this->handler($fileService, threshold: 5_242_880, partSize: 5_242_880)->handle(new RequestMediaUploadCommand(
   100	            userId: UserId::generate()->value(),
   101	            spec: $this->spec(maxSize: 5_242_880, presignedTtl: 600),
   102	            fileMeta: $this->fileMeta(size: 5_242_880),
   103	        ));
   104	
   105	        self::assertSame(MediaUploadMode::Multipart, $result->uploadMode);
   106	        self::assertSame('upload-1', $result->uploadId);
   107	        self::assertNotNull($result->parts);
   108	        self::assertCount(1, $result->parts);
   109	        self::assertNotNull($this->multipartUploadRepository()->findByMediaId(MediaId::fromString($result->mediaId)));
   110	
   111	        // TTL частей multipart тоже берётся из спеки (600), а не из конфига.
   112	        self::assertNotNull($capturedExpiresAt);
   113	        self::assertEqualsWithDelta(
   114	            new \DateTimeImmutable('+600 seconds')->getTimestamp(),
   115	            $capturedExpiresAt->getTimestamp(),
   116	            5,
   117	        );
   118	    }
   119	
   120	    public function testRejectsUnsupportedMimeType(): void
   121	    {
   122	        $this->expectException(ValidationException::class);
   123	
   124	        $this->handler()->handle(new RequestMediaUploadCommand(
   125	            userId: UserId::generate()->value(),
   126	            spec: $this->spec(),
   127	            fileMeta: $this->fileMeta(mimeType: 'audio/mpeg'),
   128	        ));
   129	    }
   130	
   131	    public function testRejectsMimeTypeOutsideSpec(): void
   132	    {
   133	        $this->expectException(ValidationException::class);
   134	
   135	        $this->handler()->handle(new RequestMediaUploadCommand(
   136	            userId: UserId::generate()->value(),
   137	            spec: $this->spec(),
   138	            fileMeta: $this->fileMeta(mimeType: 'image/png'),
   139	        ));
   140	    }
   141	
   142	    public function testRejectsSizeAboveSpecMax(): void
   143	    {
   144	        $this->expectException(ValidationException::class);
   145	
   146	        $this->handler()->handle(new RequestMediaUploadCommand(
   147	            userId: UserId::generate()->value(),
   148	            spec: $this->spec(maxSize: 512),
   149	            fileMeta: $this->fileMeta(size: 1024),
   150	        ));
   151	    }
   152	
   153	    public function testRejectsFileNameWithoutExtension(): void
   154	    {
   155	        $this->expectException(ValidationException::class);
   156	
   157	        $this->handler()->handle(new RequestMediaUploadCommand(
   158	            userId: UserId::generate()->value(),
   159	            spec: $this->spec(),
   160	            fileMeta: $this->fileMeta(fileName: 'avatar'),
   161	        ));
   162	    }
   163	
   164	    public function testRequestsDocumentUpload(): void
   165	    {
   166	        $fileService = $this->createStub(MediaFileServiceContract::class);
   167	        $fileService->method('presignPut')->willReturn('http://minio/put-url');
   168	
   169	        $result = $this->handler($fileService)->handle(new RequestMediaUploadCommand(
   170	            userId: UserId::generate()->value(),
   171	            spec: $this->spec(allowedMimeTypes: new MediaMimeTypeCollection([MediaMimeType::fromString('application/pdf')])),
   172	            fileMeta: $this->fileMeta(fileName: 'document.pdf', mimeType: 'application/pdf'),
   173	        ));
   174	
   175	        $media = $this->mediaRepository()->findById(MediaId::fromString($result->mediaId));
   176	        self::assertNotNull($media);
   177	        self::assertSame(MediaType::Document, $media->type);
   178	        self::assertSame(MediaStatus::WaitingUpload, $media->status);
   179	        // Документ кладётся в staging как и другие типы: путь начинается с uploads/.
   180	        self::assertStringStartsWith('uploads/', $media->path->value());
   181	    }
   182	
   183	    public function testRequestsDocumentUploadWithCharsetMime(): void
   184	    {
   185	        $fileService = $this->createStub(MediaFileServiceContract::class);
   186	        $fileService->method('presignPut')->willReturn('http://minio/put-url');
   187	
   188	        // Спека задаёт базовый MIME без параметра, файл приходит с ;charset=utf-8 —
   189	        // нормализация в containsMimeType должна пропустить загрузку.
   190	        $result = $this->handler($fileService)->handle(new RequestMediaUploadCommand(
   191	            userId: UserId::generate()->value(),
   192	            spec: $this->spec(allowedMimeTypes: new MediaMimeTypeCollection([MediaMimeType::fromString('text/markdown')])),
   193	            fileMeta: $this->fileMeta(fileName: 'note.md', mimeType: 'text/markdown;charset=utf-8'),
   194	        ));
   195	
   196	        $media = $this->mediaRepository()->findById(MediaId::fromString($result->mediaId));
   197	        self::assertNotNull($media);
   198	        self::assertSame(MediaType::Document, $media->type);
   199	    }
   200	
   201	    public function testRejectsDocumentMimeOutsideSpec(): void
   202	    {
   203	        // Резолвер поддерживает text/csv как документ, но спека его не разрешает —
   204	        // потребитель сужает набор, а не расширяет.
   205	        $this->expectException(ValidationException::class);
   206	        $this->expectExceptionMessage('app.media.mime_not_allowed');
   207	
   208	        $this->handler()->handle(new RequestMediaUploadCommand(
   209	            userId: UserId::generate()->value(),
   210	            spec: $this->spec(allowedMimeTypes: new MediaMimeTypeCollection([MediaMimeType::fromString('application/pdf')])),
   211	            fileMeta: $this->fileMeta(fileName: 'sheet.csv', mimeType: 'text/csv'),
   212	        ));
   213	    }
   214	
   215	    private function handler(
   216	        MediaFileServiceContract|null $fileService = null,
   217	        int $threshold = 16_777_216,
   218	        int $partSize = 8_388_608,
   219	        LoggerInterface|null $logger = null,
   220	    ): RequestMediaUploadHandler {
   221	        return new RequestMediaUploadHandler(
   222	            mediaFileService: $fileService ?? $this->createStub(MediaFileServiceContract::class),
   223	            mediaTypeResolver: new MediaTypeResolver(),
   224	            mediaConfig: new MediaConfig(
   225	                stagingTtlSeconds: 86_400,
   226	                multipartThresholdBytes: $threshold,
   227	                multipartPartSizeBytes: $partSize,
   228	                imageProcessingDriver: 'imagick',
   229	                ffmpegBinaryPath: '/usr/bin/ffmpeg',
   230	                ffprobeBinaryPath: '/usr/bin/ffprobe',
   231	                ffmpegTimeoutSeconds: 1800,
   232	                ffmpegThreads: 0,
   233	            ),
   234	            entityManager: $this->entityManager(),
   235	            logger: $logger ?? new NullLogger(),
   236	        );
   237	    }
   238	
   239	    private function spec(
   240	        int $maxSize = 1_048_576,
   241	        int $presignedTtl = 300,
   242	        MediaMimeTypeCollection|null $allowedMimeTypes = null,
   243	    ): MediaUploadSpec {
   244	        return new MediaUploadSpec(
   245	            allowedMimeTypes: $allowedMimeTypes ?? new MediaMimeTypeCollection([MediaMimeType::fromString('image/jpeg')]),
   246	            maxSize: MediaFileSize::fromInt($maxSize),
   247	            visibility: MediaVisibility::Private,
   248	            presignedTtl: MediaPresignedTtl::fromInt($presignedTtl),
   249	        );
   250	    }
   251	
   252	    private function fileMeta(
   253	        string $fileName = 'avatar.jpg',
   254	        string $mimeType = 'image/jpeg',
   255	        int $size = 1024,
   256	    ): MediaFileMeta {
   257	        return new MediaFileMeta(
   258	            fileName: $fileName,
   259	            mimeType: MediaMimeType::fromString($mimeType),
   260	            size: MediaFileSize::fromInt($size),
   261	        );
   262	    }
   263	}

exec
/bin/zsh -lc "nl -ba tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php | sed -n '180,330p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc "nl -ba tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php | sed -n '230,310p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
   180	        $this->persist($media, $conversion);
   181	
   182	        $capturedPath = null;
   183	        $fileService = $this->createStub(MediaFileServiceContract::class);
   184	        $fileService->method('publicUrl')->willReturnCallback(
   185	            static function (MediaStorage $storage, MediaPath $path) use (&$capturedPath): string {
   186	                $capturedPath = $path->value();
   187	
   188	                return 'http://minio/media-public/normalized.m4a';
   189	            },
   190	        );
   191	
   192	        $result = $this->handler($fileService)->handle(new GetMediaUrlQuery(
   193	            mediaId: $media->id->value(),
   194	            presignedTtlSeconds: 300,
   195	            conversionType: MediaAudioConversionType::NormalizedAacM4a,
   196	        ));
   197	
   198	        self::assertSame('http://minio/media-public/normalized.m4a', $result->url);
   199	        self::assertSame($conversion->path->value(), $capturedPath);
   200	    }
   201	
   202	    public function testReturnsUrlForReadyDocument(): void
   203	    {
   204	        // GetMediaUrl не ветвится по типу: документ отдаётся как любое готовое медиа —
   205	        // public прямым URL, private через presignGet.
   206	        $publicDocument = $this->readyDocumentMedia(MediaVisibility::Public);
   207	        $this->persist($publicDocument);
   208	
   209	        $publicFileService = $this->createStub(MediaFileServiceContract::class);
   210	        $publicFileService->method('publicUrl')->willReturn('http://minio/media-public/document.pdf');
   211	
   212	        $publicResult = $this->handler($publicFileService)->handle(new GetMediaUrlQuery(
   213	            mediaId: $publicDocument->id->value(),
   214	            presignedTtlSeconds: 300,
   215	        ));
   216	        self::assertSame('http://minio/media-public/document.pdf', $publicResult->url);
   217	        self::assertNull($publicResult->expiresAt);
   218	
   219	        $privateDocument = $this->readyDocumentMedia(MediaVisibility::Private);
   220	        $this->persist($privateDocument);
   221	
   222	        $privateFileService = $this->createStub(MediaFileServiceContract::class);
   223	        $privateFileService->method('presignGet')->willReturn('http://minio/signed-document');
   224	
   225	        $privateResult = $this->handler($privateFileService)->handle(new GetMediaUrlQuery(
   226	            mediaId: $privateDocument->id->value(),
   227	            presignedTtlSeconds: 300,
   228	        ));
   229	        self::assertSame('http://minio/signed-document', $privateResult->url);
   230	        self::assertNotNull($privateResult->expiresAt);
   231	    }
   232	
   233	    public function testRejectsMissingConversion(): void
   234	    {
   235	        $media = $this->readyMedia(MediaVisibility::Private);
   236	        $this->persist($media);
   237	
   238	        $this->expectException(NotFoundException::class);
   239	
   240	        $this->handler($this->createStub(MediaFileServiceContract::class))->handle(new GetMediaUrlQuery(
   241	            mediaId: $media->id->value(),
   242	            presignedTtlSeconds: 300,
   243	            conversionType: MediaImageConversionType::Poster,
   244	        ));
   245	    }
   246	
   247	    public function testRejectsMediaThatIsNotReady(): void
   248	    {
   249	        $media = $this->createMedia(userId: UserId::generate());
   250	        $this->persist($media);
   251	
   252	        $this->expectException(NotFoundException::class);
   253	
   254	        $this->handler($this->createStub(MediaFileServiceContract::class))
   255	            ->handle(new GetMediaUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 300));
   256	    }
   257	
   258	    public function testRejectsMissingMedia(): void
   259	    {
   260	        $this->expectException(NotFoundException::class);
   261	
   262	        $this->handler($this->createStub(MediaFileServiceContract::class))
   263	            ->handle(new GetMediaUrlQuery(mediaId: UserId::generate()->value(), presignedTtlSeconds: 300));
   264	    }
   265	
   266	    private function handler(MediaFileServiceContract $fileService): GetMediaUrlHandler
   267	    {
   268	        return new GetMediaUrlHandler(
   269	            mediaRepository: $this->mediaRepository(),
   270	            mediaImageConversionRepository: $this->imageConversionRepository(),
   271	            mediaVideoConversionRepository: $this->videoConversionRepository(),
   272	            mediaAudioConversionRepository: $this->audioConversionRepository(),
   273	            mediaFileService: $fileService,
   274	        );
   275	    }
   276	
   277	    private function readyMedia(MediaVisibility $visibility): Media
   278	    {
   279	        $media = $this->createMedia(userId: UserId::generate(), visibility: $visibility);
   280	        $media->markUploaded();
   281	        $targetStorage = $visibility === MediaVisibility::Public ? MediaStorage::Public : MediaStorage::Private;
   282	        $media->markReadyMovedTo(
   283	            $targetStorage,
   284	            MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Image, extension: 'jpg'),
   285	        );
   286	
   287	        return $media;
   288	    }
   289	
   290	    private function readyDocumentMedia(MediaVisibility $visibility): Media
   291	    {
   292	        $media = $this->createMedia(
   293	            userId: UserId::generate(),
   294	            visibility: $visibility,
   295	            type: MediaType::Document,
   296	            extension: 'pdf',
   297	            mimeType: 'application/pdf',
   298	        );
   299	        $media->markUploaded();
   300	        $targetStorage = $visibility === MediaVisibility::Public ? MediaStorage::Public : MediaStorage::Private;
   301	        $media->markReadyMovedTo(
   302	            $targetStorage,
   303	            MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Document, extension: 'pdf'),
   304	        );
   305	
   306	        return $media;
   307	    }
   308	
   309	    private function videoConversion(Media $media): MediaVideoConversion
   310	    {
   311	        return MediaVideoConversion::create(
   312	            media: $media,
   313	            type: MediaVideoConversionType::NormalizedMp4H264,
   314	            status: MediaConversionStatus::Ready,
   315	            storage: $media->storage,
   316	            path: MediaPath::videoConversion(
   317	                storageKey: $media->storageKey,
   318	                type: MediaVideoConversionType::NormalizedMp4H264,
   319	                extension: 'mp4',
   320	            ),
   321	            mimeType: MediaMimeType::fromString('video/mp4'),
   322	            size: MediaFileSize::fromInt(4096),
   323	            width: MediaPixelDimension::fromInt(1280),
   324	            height: MediaPixelDimension::fromInt(720),
   325	            duration: MediaDuration::fromInt(2000),
   326	            bitrate: MediaBitrate::fromInt(900_000),
   327	        );
   328	    }
   329	
   330	    private function audioConversion(Media $media): MediaAudioConversion

 succeeded in 0ms:
   230	    }
   231	
   232	    public function testIsNoOpWhenMediaAlreadyReady(): void
   233	    {
   234	        $media = $this->uploadedMedia(MediaVisibility::Private);
   235	        $media->markReadyMovedTo(
   236	            MediaStorage::Private,
   237	            MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Image, extension: 'jpg'),
   238	        );
   239	        $this->persist($media);
   240	
   241	        $fileService = $this->createMock(MediaFileServiceContract::class);
   242	        $fileService->expects(self::never())->method('getObjectContents');
   243	        $fileService->expects(self::never())->method('copyObject');
   244	
   245	        $this->handler($fileService)->handle(new ProcessMediaCommand(
   246	            mediaId: $media->id->value(),
   247	            plan: $this->imagePlan($this->imageConversionSpec()),
   248	        ));
   249	
   250	        self::assertTrue($media->isReady());
   251	    }
   252	
   253	    public function testProcessesDocumentByMovingOriginalWithoutConversions(): void
   254	    {
   255	        $media = $this->createMedia(
   256	            userId: UserId::generate(),
   257	            visibility: MediaVisibility::Public,
   258	            type: MediaType::Document,
   259	            extension: 'pdf',
   260	            mimeType: 'application/pdf',
   261	        );
   262	        $media->markUploaded();
   263	        $this->persist($media);
   264	
   265	        $fileService = $this->createMock(MediaFileServiceContract::class);
   266	        $fileService->expects(self::never())->method('getObjectContents');
   267	        $fileService->expects(self::never())->method('putObject');
   268	        $fileService->expects(self::once())->method('copyObject');
   269	
   270	        $this->handler($fileService)->handle(new ProcessMediaCommand(
   271	            mediaId: $media->id->value(),
   272	            plan: $this->emptyPlan(),
   273	        ));
   274	
   275	        self::assertTrue($media->isReady());
   276	        self::assertSame(MediaStorage::Public, $media->storage);
   277	        // Документ перекладывается в постоянное хранилище по пути с префиксом documents/.
   278	        self::assertStringStartsWith('documents/', $media->path->value());
   279	        self::assertCount(0, $this->imageConversionRepository()->findByMediaId($media->id));
   280	    }
   281	
   282	    public function testRejectsMissingMedia(): void
   283	    {
   284	        $this->expectException(NotFoundException::class);
   285	
   286	        $this->handler($this->createStub(MediaFileServiceContract::class))->handle(new ProcessMediaCommand(
   287	            mediaId: UserId::generate()->value(),
   288	            plan: $this->emptyPlan(),
   289	        ));
   290	    }
   291	
   292	    public function testRejectsMediaInWaitingUploadStatus(): void
   293	    {
   294	        $media = $this->createMedia(userId: UserId::generate());
   295	        $this->persist($media);
   296	
   297	        $this->expectException(InvalidDomainValueException::class);
   298	
   299	        $this->handler($this->createStub(MediaFileServiceContract::class))->handle(new ProcessMediaCommand(
   300	            mediaId: $media->id->value(),
   301	            plan: $this->emptyPlan(),
   302	        ));
   303	    }
   304	
   305	    private function handler(
   306	        MediaFileServiceContract $fileService,
   307	        int $resultWidth = 100,
   308	        int $resultHeight = 100,
   309	        MediaVideoProcessorContract|null $videoProcessor = null,
   310	        MediaAudioProcessorContract|null $audioProcessor = null,

exec
/bin/zsh -lc "nl -ba tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php | sed -n '320,460p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
   320	    {
   321	        $userId = UserId::generate();
   322	        $media = $this->createMedia(userId: $userId, type: MediaType::Audio, extension: 'mp3', mimeType: 'audio/mpeg');
   323	        $this->persist($media);
   324	
   325	        $this->expectException(ValidationException::class);
   326	
   327	        $this->handler($this->fileServiceWithHead(1024), $this->outboxStore())->handle(new CompleteMediaUploadCommand(
   328	            userId: $userId->value(),
   329	            mediaId: $media->id->value(),
   330	            plan: $this->audioPlan($this->audioConversionSpec(), $this->audioConversionSpec()),
   331	            parts: null,
   332	        ));
   333	    }
   334	
   335	    public function testRejectsAudioBitrateOutOfRange(): void
   336	    {
   337	        $userId = UserId::generate();
   338	        $media = $this->createMedia(userId: $userId, type: MediaType::Audio, extension: 'mp3', mimeType: 'audio/mpeg');
   339	        $this->persist($media);
   340	
   341	        $this->expectException(ValidationException::class);
   342	
   343	        $this->handler($this->fileServiceWithHead(1024), $this->outboxStore())->handle(new CompleteMediaUploadCommand(
   344	            userId: $userId->value(),
   345	            mediaId: $media->id->value(),
   346	            plan: $this->audioPlan($this->audioConversionSpec(bitrate: 0)),
   347	            parts: null,
   348	        ));
   349	    }
   350	
   351	    public function testCompletesDocumentUploadWithEmptyPlan(): void
   352	    {
   353	        $userId = UserId::generate();
   354	        $media = $this->createMedia(userId: $userId, type: MediaType::Document, extension: 'pdf', mimeType: 'application/pdf');
   355	        $this->persist($media);
   356	
   357	        $captured = null;
   358	        $outboxStore = $this->createMock(OutboxEventStoreContract::class);
   359	        $outboxStore->expects(self::once())->method('add')->willReturnCallback(
   360	            function (MediaUploaded $message) use (&$captured): StoredOutboxEventId {
   361	                $captured = $message;
   362	
   363	                return StoredOutboxEventId::fromString('outbox-1');
   364	            },
   365	        );
   366	
   367	        $result = $this->handler($this->fileServiceWithHead(1024), $outboxStore)->handle(new CompleteMediaUploadCommand(
   368	            userId: $userId->value(),
   369	            mediaId: $media->id->value(),
   370	            plan: $this->emptyPlan(),
   371	            parts: null,
   372	        ));
   373	
   374	        self::assertSame(MediaStatus::Uploaded, $result->status);
   375	        self::assertSame(MediaStatus::Uploaded, $media->status);
   376	        self::assertInstanceOf(MediaUploaded::class, $captured);
   377	    }
   378	
   379	    #[DataProvider('nonEmptyDocumentPlanProvider')]
   380	    public function testRejectsNonEmptyPlanForDocument(MediaConversionPlan $plan): void
   381	    {
   382	        $userId = UserId::generate();
   383	        $media = $this->createMedia(userId: $userId, type: MediaType::Document, extension: 'pdf', mimeType: 'application/pdf');
   384	        $this->persist($media);
   385	
   386	        $this->expectException(ValidationException::class);
   387	        $this->expectExceptionMessage('app.media.conversion_plan_type_mismatch');
   388	
   389	        $this->handler($this->fileServiceWithHead(1024), $this->outboxStore())->handle(new CompleteMediaUploadCommand(
   390	            userId: $userId->value(),
   391	            mediaId: $media->id->value(),
   392	            plan: $plan,
   393	            parts: null,
   394	        ));
   395	    }
   396	
   397	    /**
   398	     * @return array<string, array{MediaConversionPlan}>
   399	     */
   400	    public static function nonEmptyDocumentPlanProvider(): array
   401	    {
   402	        return [
   403	            'непустой список image' => [new MediaConversionPlan(
   404	                image: [new MediaImageConversionSpec(type: MediaImageConversionType::Thumbnail, width: 100, height: 100)],
   405	                video: [],
   406	                audio: [],
   407	            )],
   408	            'непустой список video' => [new MediaConversionPlan(
   409	                image: [],
   410	                video: [new MediaVideoConversionSpec(
   411	                    type: MediaVideoConversionType::NormalizedMp4H264,
   412	                    width: 1280,
   413	                    height: 720,
   414	                    videoBitrate: 1_000_000,
   415	                    audioBitrate: 128_000,
   416	                )],
   417	                audio: [],
   418	            )],
   419	            'непустой список audio' => [new MediaConversionPlan(
   420	                image: [],
   421	                video: [],
   422	                audio: [new MediaAudioConversionSpec(
   423	                    type: MediaAudioConversionType::NormalizedAacM4a,
   424	                    bitrate: 128_000,
   425	                    sampleRate: 44_100,
   426	                    waveformPeaks: 64,
   427	                )],
   428	            )],
   429	        ];
   430	    }
   431	
   432	    public function testRejectsMissingMedia(): void
   433	    {
   434	        $this->expectException(NotFoundException::class);
   435	
   436	        $this->handler($this->fileServiceWithHead(2048), $this->outboxStore())->handle(new CompleteMediaUploadCommand(
   437	            userId: UserId::generate()->value(),
   438	            mediaId: UserId::generate()->value(),
   439	            plan: $this->emptyPlan(),
   440	            parts: null,
   441	        ));
   442	    }
   443	
   444	    public function testRejectsForeignOwner(): void
   445	    {
   446	        $media = $this->createMedia(userId: UserId::generate());
   447	        $this->persist($media);
   448	
   449	        $this->expectException(ForbiddenException::class);
   450	
   451	        $this->handler($this->fileServiceWithHead(1024), $this->outboxStore())->handle(new CompleteMediaUploadCommand(
   452	            userId: UserId::generate()->value(),
   453	            mediaId: $media->id->value(),
   454	            plan: $this->emptyPlan(),
   455	            parts: null,
   456	        ));
   457	    }
   458	
   459	    public function testRejectsMediaNotWaitingUpload(): void
   460	    {

exec
/bin/zsh -lc "nl -ba tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php | sed -n '190,245p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
   190	        $audioConversion = $this->audioConversionRepository()->findByMediaId($media->id)->first();
   191	        self::assertNotNull($audioConversion);
   192	        self::assertCount(48, $audioConversion->waveform->peaks());
   193	
   194	        $normalizedPath = MediaPath::audioConversion(
   195	            storageKey: $media->storageKey,
   196	            type: MediaAudioConversionType::NormalizedAacM4a,
   197	            extension: 'm4a',
   198	        );
   199	        $readyPath = MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Audio, extension: 'wav');
   200	        $this->track(MediaStorage::Public, $normalizedPath);
   201	        $this->track(MediaStorage::Public, $readyPath);
   202	
   203	        self::assertNotNull($this->fileService()->headObject(MediaStorage::Public, $normalizedPath));
   204	    }
   205	
   206	    public function testCompleteThenRelayProcessesDocumentToReady(): void
   207	    {
   208	        $media = $this->uploadedOriginal(
   209	            $this->documentBytes(),
   210	            MediaVisibility::Public,
   211	            MediaType::Document,
   212	            'pdf',
   213	            'application/pdf',
   214	        );
   215	
   216	        $this->completeUpload($media, $this->emptyPlan());
   217	        self::assertSame(1, $this->relay());
   218	
   219	        $processedMedia = $this->mediaRepository()->findById($media->id);
   220	        self::assertNotNull($processedMedia);
   221	        self::assertSame(MediaStatus::Ready, $processedMedia->status);
   222	        self::assertSame(MediaStorage::Public, $processedMedia->storage);
   223	
   224	        // У документа конверсий нет.
   225	        self::assertCount(0, $this->imageConversionRepository()->findByMediaId($media->id));
   226	
   227	        $readyPath = MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Document, extension: 'pdf');
   228	        self::assertStringStartsWith('documents/', $readyPath->value());
   229	        $this->track(MediaStorage::Public, $readyPath);
   230	
   231	        // Реальный оригинал переложен по ready-пути documents/<shard>/<key>/source.pdf без конверсий.
   232	        self::assertSame($readyPath->value(), $processedMedia->path->value());
   233	        self::assertNotNull($this->fileService()->headObject(MediaStorage::Public, $readyPath));
   234	    }
   235	
   236	    public function testProcessingFailureRecordsErrorAndFailsOutbox(): void
   237	    {
   238	        $corruptBytes = \random_bytes(2048);
   239	        $media = $this->uploadedOriginal($corruptBytes, MediaVisibility::Public);
   240	
   241	        $outboxEventId = $this->completeUpload(
   242	            $media,
   243	            $this->imagePlan($this->imageConversionSpec(MediaImageConversionType::Thumbnail)),
   244	        );
   245	        $publishedCount = $this->relay();

exec
/bin/zsh -lc "nl -ba tests/Unit/Modules/Media/Application/MediaTypeResolverTest.php | sed -n '1,130p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace Tests\Unit\Modules\Media\Application;
     6	
     7	use App\Modules\Media\Application\Service\MediaTypeResolver;
     8	use App\Modules\Media\Domain\Enum\MediaType;
     9	use App\Modules\Media\Domain\ValueObject\MediaMimeType;
    10	use App\Shared\Domain\Exception\ValidationException;
    11	use PHPUnit\Framework\Attributes\DataProvider;
    12	use PHPUnit\Framework\TestCase;
    13	
    14	final class MediaTypeResolverTest extends TestCase
    15	{
    16	    public function testResolvesImageVideoAndAudio(): void
    17	    {
    18	        $resolver = new MediaTypeResolver();
    19	
    20	        self::assertSame(MediaType::Image, $resolver->resolve(MediaMimeType::fromString('image/jpeg')));
    21	        self::assertSame(MediaType::Video, $resolver->resolve(MediaMimeType::fromString('video/mp4')));
    22	        self::assertSame(MediaType::Audio, $resolver->resolve(MediaMimeType::fromString('audio/mpeg')));
    23	        self::assertSame(MediaType::Audio, $resolver->resolve(MediaMimeType::fromString('audio/mp4')));
    24	    }
    25	
    26	    public function testResolvesDocumentByAllowedMimeTypes(): void
    27	    {
    28	        $resolver = new MediaTypeResolver();
    29	
    30	        self::assertSame(MediaType::Document, $resolver->resolve(MediaMimeType::fromString('application/pdf')));
    31	        self::assertSame(MediaType::Document, $resolver->resolve(MediaMimeType::fromString('application/msword')));
    32	        self::assertSame(MediaType::Document, $resolver->resolve(MediaMimeType::fromString('text/csv')));
    33	        self::assertSame(MediaType::Document, $resolver->resolve(MediaMimeType::fromString('text/markdown')));
    34	    }
    35	
    36	    public function testResolvesDjvuAsDocumentNotImage(): void
    37	    {
    38	        // У DJVU MIME image/vnd.djvu — список документов проверяется раньше префикса image/,
    39	        // поэтому он должен стать Document, а не Image.
    40	        self::assertSame(
    41	            MediaType::Document,
    42	            new MediaTypeResolver()->resolve(MediaMimeType::fromString('image/vnd.djvu')),
    43	        );
    44	    }
    45	
    46	    public function testNormalizesMimeBeforeClassifyingDocument(): void
    47	    {
    48	        $resolver = new MediaTypeResolver();
    49	
    50	        self::assertSame(
    51	            MediaType::Document,
    52	            $resolver->resolve(MediaMimeType::fromString('text/markdown;charset=utf-8')),
    53	        );
    54	        self::assertSame(MediaType::Document, $resolver->resolve(MediaMimeType::fromString('APPLICATION/PDF')));
    55	        self::assertSame(MediaType::Document, $resolver->resolve(MediaMimeType::fromString('TEXT/CSV')));
    56	    }
    57	
    58	    public function testResolvesOtherImageMimeAsImage(): void
    59	    {
    60	        // Список документов перехватывает только image/vnd.djvu; прочие image/* остаются Image.
    61	        self::assertSame(MediaType::Image, new MediaTypeResolver()->resolve(MediaMimeType::fromString('image/png')));
    62	    }
    63	
    64	    #[DataProvider('unsupportedMimeTypeProvider')]
    65	    public function testRejectsUnsupportedMimeTypes(string $mimeType): void
    66	    {
    67	        $this->expectException(ValidationException::class);
    68	
    69	        new MediaTypeResolver()->resolve(MediaMimeType::fromString($mimeType));
    70	    }
    71	
    72	    /**
    73	     * @return list<array{string}>
    74	     */
    75	    public static function unsupportedMimeTypeProvider(): array
    76	    {
    77	        return [
    78	            ['font/woff2'],
    79	            ['application/zip'],
    80	            ['application/octet-stream'],
    81	            // Office-формат с макросами намеренно не входит в список документов.
    82	            ['application/vnd.ms-word.document.macroEnabled.12'],
    83	        ];
    84	    }
    85	}

exec
/bin/zsh -lc "nl -ba tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php | sed -n '1,80p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace Tests\Feature\Modules\Media\Application;
     6	
     7	use App\Modules\Media\Application\Query\CheckMediaExists\CheckMediaExistsHandler;
     8	use App\Modules\Media\Application\Query\CheckMediaExists\CheckMediaExistsQuery;
     9	use App\Modules\Media\Application\Query\CheckMediaIsImage\CheckMediaIsImageHandler;
    10	use App\Modules\Media\Application\Query\CheckMediaIsImage\CheckMediaIsImageQuery;
    11	use App\Modules\Media\Domain\Enum\MediaType;
    12	use App\Shared\Domain\ValueObject\UserId;
    13	
    14	final class CheckMediaQueriesTest extends MediaApplicationTestCase
    15	{
    16	    public function testCheckMediaIsImageReturnsTrueForImage(): void
    17	    {
    18	        $media = $this->createMedia(userId: UserId::generate(), type: MediaType::Image);
    19	        $this->persist($media);
    20	
    21	        $handler = new CheckMediaIsImageHandler(mediaRepository: $this->mediaRepository());
    22	
    23	        self::assertTrue($handler->handle(new CheckMediaIsImageQuery(mediaId: $media->id->value())));
    24	    }
    25	
    26	    public function testCheckMediaIsImageReturnsFalseForVideo(): void
    27	    {
    28	        $media = $this->createMedia(userId: UserId::generate(), type: MediaType::Video, extension: 'mp4', mimeType: 'video/mp4');
    29	        $this->persist($media);
    30	
    31	        $handler = new CheckMediaIsImageHandler(mediaRepository: $this->mediaRepository());
    32	
    33	        self::assertFalse($handler->handle(new CheckMediaIsImageQuery(mediaId: $media->id->value())));
    34	    }
    35	
    36	    public function testCheckMediaIsImageReturnsFalseForDocument(): void
    37	    {
    38	        // DJVU имеет MIME image/vnd.djvu и теперь классифицируется как Document, а не Image.
    39	        // Тест пиннит, что документ нельзя подсунуть в image-only сценарий (например аватар).
    40	        $media = $this->createMedia(userId: UserId::generate(), type: MediaType::Document, extension: 'pdf', mimeType: 'application/pdf');
    41	        $this->persist($media);
    42	
    43	        $handler = new CheckMediaIsImageHandler(mediaRepository: $this->mediaRepository());
    44	
    45	        self::assertFalse($handler->handle(new CheckMediaIsImageQuery(mediaId: $media->id->value())));
    46	    }
    47	
    48	    public function testCheckMediaIsImageReturnsFalseForMissingMedia(): void
    49	    {
    50	        $handler = new CheckMediaIsImageHandler(mediaRepository: $this->mediaRepository());
    51	
    52	        self::assertFalse($handler->handle(new CheckMediaIsImageQuery(mediaId: UserId::generate()->value())));
    53	    }
    54	
    55	    public function testCheckMediaExistsReturnsTrueForExistingMedia(): void
    56	    {
    57	        $media = $this->createMedia(userId: UserId::generate());
    58	        $this->persist($media);
    59	
    60	        $handler = new CheckMediaExistsHandler(mediaRepository: $this->mediaRepository());
    61	
    62	        self::assertTrue($handler->handle(new CheckMediaExistsQuery(mediaId: $media->id->value())));
    63	    }
    64	
    65	    public function testCheckMediaExistsReturnsFalseForMissingMedia(): void
    66	    {
    67	        $handler = new CheckMediaExistsHandler(mediaRepository: $this->mediaRepository());
    68	
    69	        self::assertFalse($handler->handle(new CheckMediaExistsQuery(mediaId: UserId::generate()->value())));
    70	    }
    71	}

exec
/bin/zsh -lc "nl -ba tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php | sed -n '80,215p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
    80	        $storageKey = MediaStorageKey::fromString('ab111111-1111-4111-8111-111111111111');
    81	
    82	        self::assertSame(
    83	            'images/ab/ab111111-1111-4111-8111-111111111111/thumbnail.jpg',
    84	            (string) MediaPath::imageConversion(
    85	                storageKey: $storageKey,
    86	                type: MediaImageConversionType::Thumbnail,
    87	                extension: 'JPG',
    88	            ),
    89	        );
    90	        self::assertSame(
    91	            'images/ab/ab111111-1111-4111-8111-111111111111/source.png',
    92	            (string) MediaPath::originalReady(storageKey: $storageKey, type: MediaType::Image, extension: 'png'),
    93	        );
    94	        self::assertSame(
    95	            'videos/ab/ab111111-1111-4111-8111-111111111111/source.mp4',
    96	            (string) MediaPath::originalReady(storageKey: $storageKey, type: MediaType::Video, extension: 'mp4'),
    97	        );
    98	        self::assertSame(
    99	            'audios/ab/ab111111-1111-4111-8111-111111111111/source.m4a',
   100	            (string) MediaPath::originalReady(storageKey: $storageKey, type: MediaType::Audio, extension: 'm4a'),
   101	        );
   102	        self::assertSame(
   103	            'documents/ab/ab111111-1111-4111-8111-111111111111/source.pdf',
   104	            (string) MediaPath::originalReady(storageKey: $storageKey, type: MediaType::Document, extension: 'pdf'),
   105	        );
   106	        self::assertSame(
   107	            'videos/ab/ab111111-1111-4111-8111-111111111111/normalizedMp4H264.mp4',
   108	            (string) MediaPath::videoConversion(
   109	                storageKey: $storageKey,
   110	                type: MediaVideoConversionType::NormalizedMp4H264,
   111	                extension: 'MP4',
   112	            ),
   113	        );
   114	        self::assertSame(
   115	            'audios/ab/ab111111-1111-4111-8111-111111111111/normalizedAacM4a.m4a',
   116	            (string) MediaPath::audioConversion(
   117	                storageKey: $storageKey,
   118	                type: MediaAudioConversionType::NormalizedAacM4a,
   119	                extension: 'm4a',
   120	            ),
   121	        );
   122	        self::assertSame(
   123	            'audios/ab/ab111111-1111-4111-8111-111111111111/source.m4a',
   124	            MediaPath::fromString('audios/ab/ab111111-1111-4111-8111-111111111111/source.m4a')->value(),
   125	        );
   126	    }
   127	
   128	    public function testMediaPathExtensionReadsLastSegment(): void
   129	    {
   130	        $storageKey = MediaStorageKey::fromString('ab111111-1111-4111-8111-111111111111');
   131	
   132	        self::assertSame('jpg', MediaPath::originalUpload(storageKey: $storageKey, extension: 'jpg')->extension());
   133	        self::assertSame('', MediaPath::fromString('uploads/ab/ab111111-1111-4111-8111-111111111111/source')->extension());
   134	    }
   135	
   136	    public function testMediaPathAcceptsDocumentsPrefix(): void
   137	    {
   138	        self::assertSame(
   139	            'documents/ab/ab111111-1111-4111-8111-111111111111/source.pdf',
   140	            MediaPath::fromString('documents/ab/ab111111-1111-4111-8111-111111111111/source.pdf')->value(),
   141	        );
   142	    }
   143	
   144	    public function testMediaPathRejectsDocumentsPrefixWithWrongShard(): void
   145	    {
   146	        $this->expectException(InvalidDomainValueException::class);
   147	        $this->expectExceptionMessage('Путь файла имеет неверный раздел.');
   148	
   149	        MediaPath::fromString('documents/cd/ab111111-1111-4111-8111-111111111111/source.pdf');
   150	    }
   151	
   152	    public function testWaveformValidatesPeaksAndSerializes(): void
   153	    {
   154	        $waveform = MediaWaveform::fromPeaks([0, 128, 255]);
   155	
   156	        self::assertSame([0, 128, 255], $waveform->peaks());
   157	        self::assertSame([0, 128, 255], $waveform->jsonSerialize());
   158	        self::assertTrue($waveform->equals(MediaWaveform::fromPeaks([0, 128, 255])));
   159	        self::assertFalse($waveform->equals(MediaWaveform::fromPeaks([0, 128, 254])));
   160	    }
   161	
   162	    public function testWaveformRejectsEmptyPeaks(): void
   163	    {
   164	        $this->expectException(InvalidDomainValueException::class);
   165	
   166	        MediaWaveform::fromPeaks([]);
   167	    }
   168	
   169	    public function testWaveformRejectsPeakOutOfRange(): void
   170	    {
   171	        $this->expectException(InvalidDomainValueException::class);
   172	
   173	        MediaWaveform::fromPeaks([0, 256]);
   174	    }
   175	
   176	    public function testMediaPathFactoryRejectsInvalidExtension(): void
   177	    {
   178	        $this->expectException(InvalidDomainValueException::class);
   179	
   180	        MediaPath::imageConversion(
   181	            storageKey: MediaStorageKey::generate(),
   182	            type: MediaImageConversionType::Thumbnail,
   183	            extension: 'jp g',
   184	        );
   185	    }
   186	
   187	    public function testMimeTypeValidatesLength(): void
   188	    {
   189	        self::assertSame('image/jpeg', (string) MediaMimeType::fromString('image/jpeg'));
   190	
   191	        $this->expectException(InvalidDomainValueException::class);
   192	
   193	        MediaMimeType::fromString('');
   194	    }
   195	
   196	    public function testMimeTypeBaseValueNormalizesWithoutChangingRawValue(): void
   197	    {
   198	        self::assertSame('text/markdown', MediaMimeType::fromString('text/markdown;charset=utf-8')->baseValue());
   199	        self::assertSame('application/pdf', MediaMimeType::fromString('APPLICATION/PDF')->baseValue());
   200	        self::assertSame('image/jpeg', MediaMimeType::fromString('image/jpeg')->baseValue());
   201	
   202	        // value() отдаёт исходную строку с параметрами без изменений.
   203	        self::assertSame(
   204	            'text/markdown;charset=utf-8',
   205	            MediaMimeType::fromString('text/markdown;charset=utf-8')->value(),
   206	        );
   207	    }
   208	
   209	    /**
   210	     * @param class-string $valueObjectClass
   211	     */
   212	    #[DataProvider('integerValueObjectProvider')]
   213	    public function testIntegerValueObjectsValidateBorders(string $valueObjectClass, int $valid, int $invalid): void
   214	    {
   215	        $validValue = $valueObjectClass::fromInt($valid);

exec
/bin/zsh -lc "nl -ba tests/Unit/Modules/Media/Domain/Collection/MediaMimeTypeCollectionTest.php | sed -n '1,80p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace Tests\Unit\Modules\Media\Domain\Collection;
     6	
     7	use App\Modules\Media\Domain\Collection\MediaMimeTypeCollection;
     8	use App\Modules\Media\Domain\ValueObject\MediaMimeType;
     9	use PHPUnit\Framework\TestCase;
    10	
    11	final class MediaMimeTypeCollectionTest extends TestCase
    12	{
    13	    public function testContainsMimeTypeMatchesByBaseValueIgnoringParameters(): void
    14	    {
    15	        $allowed = new MediaMimeTypeCollection([MediaMimeType::fromString('text/markdown')]);
    16	
    17	        self::assertTrue($allowed->containsMimeType(MediaMimeType::fromString('text/markdown;charset=utf-8')));
    18	    }
    19	
    20	    public function testContainsMimeTypeRejectsDifferentMimeType(): void
    21	    {
    22	        $allowed = new MediaMimeTypeCollection([MediaMimeType::fromString('image/jpeg')]);
    23	
    24	        self::assertFalse($allowed->containsMimeType(MediaMimeType::fromString('image/png')));
    25	    }
    26	}

exec
/bin/zsh -lc "sed -n '1,260p' docs/executions/2026-06-22_18-09_media-document-support.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
---
plan: docs/plans/2026-06-22_17-35_media-document-support.md
started: 2026-06-22 18:09
finished: 2026-06-22 18:34
status: done
---

# Журнал: Поддержка документов в модуле Media

## Шаги
| # | Шаг | Файлы | Тесты | Статус |
|---|-----|-------|-------|--------|
| 1 | Нормализация MIME и классификация документа: `baseValue()`, whitelist в резолвере (проверяется первым), сравнение по `baseValue()` в `containsMimeType()`, обновлён докблок резолвера | `MediaMimeType.php`, `MediaMimeTypeCollection.php`, `MediaTypeResolver.php` | `MediaTypeResolverTest`, `MediaValueObjectTest` (baseValue), новый `MediaMimeTypeCollectionTest` | `make test-unit` OK (572), `make phpstan` OK |
| 2 | Префикс `documents`: расширена regex в `assertValid()`, ветка `Document => 'documents'` в `originalReady()` | `MediaPath.php` | `MediaValueObjectTest` (originalReady Document, fromString documents/ позитив+негатив по shard) | `make test-unit` OK (573), `make phpstan` OK |
| 3 | `ProcessMediaHandler`: `Document => []` (пустой набор конверсий), удалён неиспользуемый импорт `InvalidDomainValueException` | `ProcessMediaHandler.php` | `ProcessMediaHandlerTest` (перевёрнут негатив в `testProcessesDocumentByMovingOriginalWithoutConversions`) | точечный `ProcessMediaHandlerTest` OK (11), `make phpstan` OK |
| 4 | `CompleteMediaUploadHandler`: `Document => assertDocumentPlan()` (пустой план обязателен, иначе `conversion_plan_type_mismatch`) + end-to-end загрузка документа | `CompleteMediaUploadHandler.php` | `CompleteMediaUploadHandlerTest` (перевёрнут негатив + data-provider непустого плана), `RequestMediaUploadHandlerTest` (документ, charset-MIME, негатив `mime_not_allowed`), `MediaProcessingFlowTest` (end-to-end documents/ через MinIO), `GetMediaUrlHandlerTest` (документ public/private), `CheckMediaQueriesTest` (документ не image) | точечный `tests/Feature/Modules/Media` OK (140), `make phpstan` OK |

## Заметки

- Фаза 1 проверена быстрым `make test-unit` (все её тесты в Unit-сьюте) + `make phpstan`. Полный `make test` запланирован в финальной проверке.
- Фазы 3–4 (feature-тесты) проверены точечным прогоном `tests/Feature/Modules/Media` (TEST_TOKEN пуст → базовая БД `yoga_loka_test`/bucket `yoga-loka-test`), чтобы не гонять весь Feature-сьют дважды. Полный gate — в финальной проверке.
- В `ProcessMediaHandler` после замены `Document => throw` на `Document => []` импорт `InvalidDomainValueException` стал неиспользуемым — удалён (правило «Нет мёртвого кода»).
- Резолвер: новый докблок убирает ложные утверждения «документы отклоняются» и «только uploads|images|videos|audios», добавляет упоминание списка разрешённых MIME-типов и префикса `documents` (критерий готовности докблока выполнен).
- Изменений в `docs/rules.md`/`docs/arch.md` не потребовалось: план сознательно без новых сущностей, контрактов, миграций и архитектурных решений.

## Финальная проверка

- `make phpstan` — OK (No errors), прогнан после всех правок Фазы 4.
- `make test` — первый полный прогон дал 1224/1225 и 1 ошибку в `OutboxRelayPublishTest::testRelayReclaimsStuckPublishingEventAndMarksFailedWhenAttemptsExhausted` (модуль Outbox, мной не затронут; «строка без статуса»). Тест проходит в изоляции (12/12) и при повторном полном прогоне — 1225/1225, 4006 assertions, OK. Вывод: транзиентный флаки в Outbox при параллельном выполнении, не связан с задачей документов. Свой код не подавлялся.

## Изменения в docs

## Изменения в docs

exec
/bin/zsh -lc 'find app/src/Modules/Media tests/Feature/Modules/Media tests/Unit/Modules/Media -type f | sort' in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadCommand.php
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php
app/src/Modules/Media/Application/Command/DeleteMedia/DeleteMediaCommand.php
app/src/Modules/Media/Application/Command/DeleteMedia/DeleteMediaHandler.php
app/src/Modules/Media/Application/Command/MakeMediaPermanent/MakeMediaPermanentCommand.php
app/src/Modules/Media/Application/Command/MakeMediaPermanent/MakeMediaPermanentHandler.php
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaCommand.php
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php
app/src/Modules/Media/Application/Command/RecordMediaProcessingFailure/RecordMediaProcessingFailureCommand.php
app/src/Modules/Media/Application/Command/RecordMediaProcessingFailure/RecordMediaProcessingFailureHandler.php
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadCommand.php
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php
app/src/Modules/Media/Application/Contract/MediaAudioProcessorContract.php
app/src/Modules/Media/Application/Contract/MediaFileServiceContract.php
app/src/Modules/Media/Application/Contract/MediaImageProcessorContract.php
app/src/Modules/Media/Application/Contract/MediaVideoProcessorContract.php
app/src/Modules/Media/Application/Dto/MediaAudioConversionSpec.php
app/src/Modules/Media/Application/Dto/MediaAudioProcessingResult.php
app/src/Modules/Media/Application/Dto/MediaConversionPlan.php
app/src/Modules/Media/Application/Dto/MediaConversionResult.php
app/src/Modules/Media/Application/Dto/MediaFileMeta.php
app/src/Modules/Media/Application/Dto/MediaImageConversionSpec.php
app/src/Modules/Media/Application/Dto/MediaObjectHead.php
app/src/Modules/Media/Application/Dto/MediaPresignedPart.php
app/src/Modules/Media/Application/Dto/MediaPresignedPartCollection.php
app/src/Modules/Media/Application/Dto/MediaResult.php
app/src/Modules/Media/Application/Dto/MediaUploadMode.php
app/src/Modules/Media/Application/Dto/MediaUploadSpec.php
app/src/Modules/Media/Application/Dto/MediaUrlResult.php
app/src/Modules/Media/Application/Dto/MediaVideoConversionSpec.php
app/src/Modules/Media/Application/Dto/MediaVideoProcessingResult.php
app/src/Modules/Media/Application/Dto/RequestMediaUploadResult.php
app/src/Modules/Media/Application/Exception/MediaFileServiceFailedException.php
app/src/Modules/Media/Application/Exception/MediaProcessorFailedException.php
app/src/Modules/Media/Application/Message/MediaUploaded.php
app/src/Modules/Media/Application/Query/CheckMediaAttachable/CheckMediaAttachableHandler.php
app/src/Modules/Media/Application/Query/CheckMediaAttachable/CheckMediaAttachableQuery.php
app/src/Modules/Media/Application/Query/CheckMediaAttachable/MediaAttachableResult.php
app/src/Modules/Media/Application/Query/CheckMediaExists/CheckMediaExistsHandler.php
app/src/Modules/Media/Application/Query/CheckMediaExists/CheckMediaExistsQuery.php
app/src/Modules/Media/Application/Query/CheckMediaIsImage/CheckMediaIsImageHandler.php
app/src/Modules/Media/Application/Query/CheckMediaIsImage/CheckMediaIsImageQuery.php
app/src/Modules/Media/Application/Query/FindMediaUrl/FindMediaUrlHandler.php
app/src/Modules/Media/Application/Query/FindMediaUrl/FindMediaUrlQuery.php
app/src/Modules/Media/Application/Query/GetAudioWaveform/GetAudioWaveformHandler.php
app/src/Modules/Media/Application/Query/GetAudioWaveform/GetAudioWaveformQuery.php
app/src/Modules/Media/Application/Query/GetMediaUrl/GetMediaUrlHandler.php
app/src/Modules/Media/Application/Query/GetMediaUrl/GetMediaUrlQuery.php
app/src/Modules/Media/Application/Service/MediaTypeResolver.php
app/src/Modules/Media/Domain/Collection/MediaAudioConversionCollection.php
app/src/Modules/Media/Domain/Collection/MediaCollection.php
app/src/Modules/Media/Domain/Collection/MediaImageConversionCollection.php
app/src/Modules/Media/Domain/Collection/MediaMimeTypeCollection.php
app/src/Modules/Media/Domain/Collection/MediaMultipartPartCollection.php
app/src/Modules/Media/Domain/Collection/MediaVideoConversionCollection.php
app/src/Modules/Media/Domain/Entity/Media.php
app/src/Modules/Media/Domain/Entity/MediaAudioConversion.php
app/src/Modules/Media/Domain/Entity/MediaImageConversion.php
app/src/Modules/Media/Domain/Entity/MediaMultipartUpload.php
app/src/Modules/Media/Domain/Entity/MediaVideoConversion.php
app/src/Modules/Media/Domain/Enum/MediaAudioConversionType.php
app/src/Modules/Media/Domain/Enum/MediaConversionStatus.php
app/src/Modules/Media/Domain/Enum/MediaImageConversionType.php
app/src/Modules/Media/Domain/Enum/MediaStatus.php
app/src/Modules/Media/Domain/Enum/MediaStorage.php
app/src/Modules/Media/Domain/Enum/MediaType.php
app/src/Modules/Media/Domain/Enum/MediaVideoConversionType.php
app/src/Modules/Media/Domain/Enum/MediaVisibility.php
app/src/Modules/Media/Domain/ValueObject/MediaAudioConversionId.php
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
app/src/Modules/Media/Domain/ValueObject/MediaSampleRate.php
app/src/Modules/Media/Domain/ValueObject/MediaStorageKey.php
app/src/Modules/Media/Domain/ValueObject/MediaVideoConversionId.php
app/src/Modules/Media/Domain/ValueObject/MediaWaveform.php
app/src/Modules/Media/Domain/ValueObject/MediaWaveformPeakCount.php
app/src/Modules/Media/Infrastructure/Bootloader/MediaBootloader.php
app/src/Modules/Media/Infrastructure/Cycle/MediaExpirationTypecast.php
app/src/Modules/Media/Infrastructure/Cycle/MediaMultipartPartCollectionTypecast.php
app/src/Modules/Media/Infrastructure/Cycle/MediaProcessingErrorTypecast.php
app/src/Modules/Media/Infrastructure/Cycle/MediaWaveformTypecast.php
app/src/Modules/Media/Infrastructure/Exception/MediaImageProcessorException.php
app/src/Modules/Media/Infrastructure/Exception/MediaStorageNotConfiguredException.php
app/src/Modules/Media/Infrastructure/FileService/AbstractFfmpegMediaProcessor.php
app/src/Modules/Media/Infrastructure/FileService/ConfiguredS3ClientProvider.php
app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaAudioProcessor.php
app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaVideoProcessor.php
app/src/Modules/Media/Infrastructure/FileService/ImagickMediaImageProcessor.php
app/src/Modules/Media/Infrastructure/FileService/NativeAacAudioFormat.php
app/src/Modules/Media/Infrastructure/FileService/S3ClientProvider.php
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php
app/src/Modules/Media/Presentation/Job/ProcessMediaJob.php
app/src/Modules/Media/README.md
app/src/Modules/Media/Repository/MediaAudioConversionRepository.php
app/src/Modules/Media/Repository/MediaImageConversionRepository.php
app/src/Modules/Media/Repository/MediaMultipartUploadRepository.php
app/src/Modules/Media/Repository/MediaRepository.php
app/src/Modules/Media/Repository/MediaVideoConversionRepository.php
tests/Feature/Modules/Media/Application/CheckMediaAttachableHandlerTest.php
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php
tests/Feature/Modules/Media/Application/GetAudioWaveformHandlerTest.php
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php
tests/Feature/Modules/Media/Application/MakeMediaPermanentHandlerTest.php
tests/Feature/Modules/Media/Application/MediaApplicationTestCase.php
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php
tests/Feature/Modules/Media/Application/RecordMediaProcessingFailureHandlerTest.php
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php
tests/Feature/Modules/Media/Flow/Fixture/RecordingMediaLogger.php
tests/Feature/Modules/Media/Flow/Fixture/ThrowingProcessMediaCommandBus.php
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php
tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php
tests/Unit/Modules/Media/Application/MediaTypeResolverTest.php
tests/Unit/Modules/Media/Application/MediaUploadedSerializationTest.php
tests/Unit/Modules/Media/Domain/Collection/MediaMimeTypeCollectionTest.php
tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php
tests/Unit/Modules/Media/Domain/Enum/MediaEnumTest.php
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php
tests/Unit/Modules/Media/Infrastructure/Cycle/MediaTypecastTest.php
tests/Unit/Modules/Media/Infrastructure/FfmpegMediaProcessorTest.php
tests/Unit/Modules/Media/Infrastructure/FfmpegWaveformTest.php
tests/Unit/Modules/Media/Infrastructure/Fixture/FakeS3Client.php
tests/Unit/Modules/Media/Infrastructure/Fixture/FakeS3ClientProvider.php
tests/Unit/Modules/Media/Infrastructure/Fixture/FfmpegCommandRecordingProcessor.php
tests/Unit/Modules/Media/Infrastructure/Fixture/RecordingAudioProcessor.php
tests/Unit/Modules/Media/Infrastructure/Fixture/RecordingVideoProcessor.php
tests/Unit/Modules/Media/Infrastructure/Fixture/WaveformExposingAudioProcessor.php
tests/Unit/Modules/Media/Infrastructure/ImagickMediaImageProcessorTest.php
tests/Unit/Modules/Media/Infrastructure/S3MediaFileServiceErrorTest.php

exec
/bin/zsh -lc 'rg -n "Document|documents|unsupported_file_type|containsMimeType|baseValue|CheckMediaIsImage|allowedMimeTypes" app/src/Modules/Media tests/Feature/Modules/Media tests/Unit/Modules/Media' in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
app/src/Modules/Media/Application/Query/CheckMediaIsImage/CheckMediaIsImageHandler.php:5:namespace App\Modules\Media\Application\Query\CheckMediaIsImage;
app/src/Modules/Media/Application/Query/CheckMediaIsImage/CheckMediaIsImageHandler.php:11:final readonly class CheckMediaIsImageHandler
app/src/Modules/Media/Application/Query/CheckMediaIsImage/CheckMediaIsImageHandler.php:17:    public function handle(CheckMediaIsImageQuery $query): bool
app/src/Modules/Media/Application/Dto/MediaUploadSpec.php:19:        public MediaMimeTypeCollection $allowedMimeTypes,
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:351:    public function testCompletesDocumentUploadWithEmptyPlan(): void
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:354:        $media = $this->createMedia(userId: $userId, type: MediaType::Document, extension: 'pdf', mimeType: 'application/pdf');
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:379:    #[DataProvider('nonEmptyDocumentPlanProvider')]
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:380:    public function testRejectsNonEmptyPlanForDocument(MediaConversionPlan $plan): void
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:383:        $media = $this->createMedia(userId: $userId, type: MediaType::Document, extension: 'pdf', mimeType: 'application/pdf');
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:400:    public static function nonEmptyDocumentPlanProvider(): array
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:206:    public function testCompleteThenRelayProcessesDocumentToReady(): void
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:211:            MediaType::Document,
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:227:        $readyPath = MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Document, extension: 'pdf');
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:228:        self::assertStringStartsWith('documents/', $readyPath->value());
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:231:        // Реальный оригинал переложен по ready-пути documents/<shard>/<key>/source.pdf без конверсий.
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:103:            'documents/ab/ab111111-1111-4111-8111-111111111111/source.pdf',
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:104:            (string) MediaPath::originalReady(storageKey: $storageKey, type: MediaType::Document, extension: 'pdf'),
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:136:    public function testMediaPathAcceptsDocumentsPrefix(): void
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:139:            'documents/ab/ab111111-1111-4111-8111-111111111111/source.pdf',
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:140:            MediaPath::fromString('documents/ab/ab111111-1111-4111-8111-111111111111/source.pdf')->value(),
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:144:    public function testMediaPathRejectsDocumentsPrefixWithWrongShard(): void
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:149:        MediaPath::fromString('documents/cd/ab111111-1111-4111-8111-111111111111/source.pdf');
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:198:        self::assertSame('text/markdown', MediaMimeType::fromString('text/markdown;charset=utf-8')->baseValue());
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:199:        self::assertSame('application/pdf', MediaMimeType::fromString('APPLICATION/PDF')->baseValue());
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:200:        self::assertSame('image/jpeg', MediaMimeType::fromString('image/jpeg')->baseValue());
tests/Unit/Modules/Media/Domain/Collection/MediaMimeTypeCollectionTest.php:17:        self::assertTrue($allowed->containsMimeType(MediaMimeType::fromString('text/markdown;charset=utf-8')));
tests/Unit/Modules/Media/Domain/Collection/MediaMimeTypeCollectionTest.php:24:        self::assertFalse($allowed->containsMimeType(MediaMimeType::fromString('image/png')));
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:202:    public function testReturnsUrlForReadyDocument(): void
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:206:        $publicDocument = $this->readyDocumentMedia(MediaVisibility::Public);
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:207:        $this->persist($publicDocument);
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:213:            mediaId: $publicDocument->id->value(),
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:219:        $privateDocument = $this->readyDocumentMedia(MediaVisibility::Private);
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:220:        $this->persist($privateDocument);
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:226:            mediaId: $privateDocument->id->value(),
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:290:    private function readyDocumentMedia(MediaVisibility $visibility): Media
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:295:            type: MediaType::Document,
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:303:            MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Document, extension: 'pdf'),
app/src/Modules/Media/Application/Query/CheckMediaIsImage/CheckMediaIsImageQuery.php:5:namespace App\Modules\Media\Application\Query\CheckMediaIsImage;
app/src/Modules/Media/Application/Query/CheckMediaIsImage/CheckMediaIsImageQuery.php:7:final readonly class CheckMediaIsImageQuery
app/src/Modules/Media/Domain/Enum/MediaType.php:12:    case Document = 'document';
tests/Unit/Modules/Media/Application/MediaTypeResolverTest.php:26:    public function testResolvesDocumentByAllowedMimeTypes(): void
tests/Unit/Modules/Media/Application/MediaTypeResolverTest.php:30:        self::assertSame(MediaType::Document, $resolver->resolve(MediaMimeType::fromString('application/pdf')));
tests/Unit/Modules/Media/Application/MediaTypeResolverTest.php:31:        self::assertSame(MediaType::Document, $resolver->resolve(MediaMimeType::fromString('application/msword')));
tests/Unit/Modules/Media/Application/MediaTypeResolverTest.php:32:        self::assertSame(MediaType::Document, $resolver->resolve(MediaMimeType::fromString('text/csv')));
tests/Unit/Modules/Media/Application/MediaTypeResolverTest.php:33:        self::assertSame(MediaType::Document, $resolver->resolve(MediaMimeType::fromString('text/markdown')));
tests/Unit/Modules/Media/Application/MediaTypeResolverTest.php:36:    public function testResolvesDjvuAsDocumentNotImage(): void
tests/Unit/Modules/Media/Application/MediaTypeResolverTest.php:39:        // поэтому он должен стать Document, а не Image.
tests/Unit/Modules/Media/Application/MediaTypeResolverTest.php:41:            MediaType::Document,
tests/Unit/Modules/Media/Application/MediaTypeResolverTest.php:46:    public function testNormalizesMimeBeforeClassifyingDocument(): void
tests/Unit/Modules/Media/Application/MediaTypeResolverTest.php:51:            MediaType::Document,
tests/Unit/Modules/Media/Application/MediaTypeResolverTest.php:54:        self::assertSame(MediaType::Document, $resolver->resolve(MediaMimeType::fromString('APPLICATION/PDF')));
tests/Unit/Modules/Media/Application/MediaTypeResolverTest.php:55:        self::assertSame(MediaType::Document, $resolver->resolve(MediaMimeType::fromString('TEXT/CSV')));
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:253:    public function testProcessesDocumentByMovingOriginalWithoutConversions(): void
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:258:            type: MediaType::Document,
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:277:        // Документ перекладывается в постоянное хранилище по пути с префиксом documents/.
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:278:        self::assertStringStartsWith('documents/', $media->path->value());
app/src/Modules/Media/README.md:27:| `CheckMediaIsImage(mediaId)` | Query | `bool` |
app/src/Modules/Media/README.md:38:- `MediaUploadSpec{ allowedMimeTypes: MediaMimeTypeCollection, maxSize: MediaFileSize, visibility, presignedTtl: MediaPresignedTtl }`
app/src/Modules/Media/Application/Service/MediaTypeResolver.php:15: * uploads|images|videos|audios|documents.
app/src/Modules/Media/Application/Service/MediaTypeResolver.php:48:        if (\in_array(needle: $mimeType->baseValue(), haystack: self::DOCUMENT_MIME_TYPES, strict: true)) {
app/src/Modules/Media/Application/Service/MediaTypeResolver.php:49:            return MediaType::Document;
app/src/Modules/Media/Application/Service/MediaTypeResolver.php:67:            translationKey: 'app.media.unsupported_file_type',
tests/Unit/Modules/Media/Infrastructure/Cycle/MediaTypecastTest.php:27:        self::assertTrue(MediaExpirationTypecast::castDatabaseValue(null)->isPermanent());
tests/Unit/Modules/Media/Infrastructure/Cycle/MediaTypecastTest.php:28:        self::assertSame($expiresAt, MediaExpirationTypecast::castDatabaseValue($expiresAt)->value());
tests/Unit/Modules/Media/Infrastructure/Cycle/MediaTypecastTest.php:38:            MediaExpirationTypecast::castDatabaseValue($mutableDate)->value(),
tests/Unit/Modules/Media/Infrastructure/Cycle/MediaTypecastTest.php:45:        $processingError = MediaProcessingErrorTypecast::castDatabaseValue('Не удалось обработать файл');
tests/Unit/Modules/Media/Infrastructure/Cycle/MediaTypecastTest.php:48:        self::assertTrue(MediaProcessingErrorTypecast::castDatabaseValue(null)->isEmpty());
tests/Unit/Modules/Media/Infrastructure/Cycle/MediaTypecastTest.php:58:        MediaMultipartPartCollectionTypecast::castDatabaseValue('123');
tests/Unit/Modules/Media/Infrastructure/Cycle/MediaTypecastTest.php:65:        MediaMultipartPartCollectionTypecast::castDatabaseValue('[1, 2]');
tests/Unit/Modules/Media/Infrastructure/Cycle/MediaTypecastTest.php:72:        MediaMultipartPartCollectionTypecast::castDatabaseValue('[{"partNumber":"x","eTag":"y"}]');
tests/Unit/Modules/Media/Infrastructure/Cycle/MediaTypecastTest.php:77:        $parts = MediaMultipartPartCollectionTypecast::castDatabaseValue(
tests/Unit/Modules/Media/Infrastructure/Cycle/MediaTypecastTest.php:102:        $waveform = MediaWaveformTypecast::castDatabaseValue('[0,128,255]');
tests/Unit/Modules/Media/Infrastructure/Cycle/MediaTypecastTest.php:113:        MediaWaveformTypecast::castDatabaseValue('123');
tests/Unit/Modules/Media/Infrastructure/Cycle/MediaTypecastTest.php:120:        MediaWaveformTypecast::castDatabaseValue('[1, "two"]');
tests/Unit/Modules/Media/Infrastructure/Cycle/MediaTypecastTest.php:127:        MediaWaveformTypecast::castDatabaseValue('[0, 256]');
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php:123:            MediaType::Document => $this->assertDocumentPlan($plan),
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php:127:    private function assertDocumentPlan(MediaConversionPlan $plan): void
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:164:    public function testRequestsDocumentUpload(): void
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:171:            spec: $this->spec(allowedMimeTypes: new MediaMimeTypeCollection([MediaMimeType::fromString('application/pdf')])),
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:177:        self::assertSame(MediaType::Document, $media->type);
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:183:    public function testRequestsDocumentUploadWithCharsetMime(): void
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:189:        // нормализация в containsMimeType должна пропустить загрузку.
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:192:            spec: $this->spec(allowedMimeTypes: new MediaMimeTypeCollection([MediaMimeType::fromString('text/markdown')])),
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:198:        self::assertSame(MediaType::Document, $media->type);
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:201:    public function testRejectsDocumentMimeOutsideSpec(): void
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:210:            spec: $this->spec(allowedMimeTypes: new MediaMimeTypeCollection([MediaMimeType::fromString('application/pdf')])),
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:242:        MediaMimeTypeCollection|null $allowedMimeTypes = null,
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:245:            allowedMimeTypes: $allowedMimeTypes ?? new MediaMimeTypeCollection([MediaMimeType::fromString('image/jpeg')]),
app/src/Modules/Media/Domain/Collection/MediaMimeTypeCollection.php:15:    public function containsMimeType(MediaMimeType $mimeType): bool
app/src/Modules/Media/Domain/Collection/MediaMimeTypeCollection.php:18:            static fn(MediaMimeType $allowed): bool => $allowed->baseValue() === $mimeType->baseValue(),
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:9:use App\Modules\Media\Application\Query\CheckMediaIsImage\CheckMediaIsImageHandler;
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:10:use App\Modules\Media\Application\Query\CheckMediaIsImage\CheckMediaIsImageQuery;
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:16:    public function testCheckMediaIsImageReturnsTrueForImage(): void
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:21:        $handler = new CheckMediaIsImageHandler(mediaRepository: $this->mediaRepository());
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:23:        self::assertTrue($handler->handle(new CheckMediaIsImageQuery(mediaId: $media->id->value())));
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:26:    public function testCheckMediaIsImageReturnsFalseForVideo(): void
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:31:        $handler = new CheckMediaIsImageHandler(mediaRepository: $this->mediaRepository());
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:33:        self::assertFalse($handler->handle(new CheckMediaIsImageQuery(mediaId: $media->id->value())));
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:36:    public function testCheckMediaIsImageReturnsFalseForDocument(): void
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:38:        // DJVU имеет MIME image/vnd.djvu и теперь классифицируется как Document, а не Image.
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:40:        $media = $this->createMedia(userId: UserId::generate(), type: MediaType::Document, extension: 'pdf', mimeType: 'application/pdf');
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:43:        $handler = new CheckMediaIsImageHandler(mediaRepository: $this->mediaRepository());
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:45:        self::assertFalse($handler->handle(new CheckMediaIsImageQuery(mediaId: $media->id->value())));
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:48:    public function testCheckMediaIsImageReturnsFalseForMissingMedia(): void
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:50:        $handler = new CheckMediaIsImageHandler(mediaRepository: $this->mediaRepository());
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:52:        self::assertFalse($handler->handle(new CheckMediaIsImageQuery(mediaId: UserId::generate()->value())));
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:89:            MediaType::Document => [],
app/src/Modules/Media/Infrastructure/Cycle/MediaMultipartPartCollectionTypecast.php:13:    public static function castDatabaseValue(
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php:127:        if (!$spec->allowedMimeTypes->containsMimeType($fileMeta->mimeType)) {
app/src/Modules/Media/Infrastructure/Cycle/MediaWaveformTypecast.php:12:    public static function castDatabaseValue(string $value): MediaWaveform
app/src/Modules/Media/Infrastructure/Cycle/MediaExpirationTypecast.php:12:    public static function castDatabaseValue(
app/src/Modules/Media/Domain/ValueObject/MediaPath.php:83:            MediaType::Document => 'documents',
app/src/Modules/Media/Domain/ValueObject/MediaPath.php:153:            pattern: '/^(uploads|images|videos|audios|documents)\/([0-9a-f]{2})\/([0-9a-f-]{36})\/([^\/]+)$/',
app/src/Modules/Media/Infrastructure/Cycle/MediaProcessingErrorTypecast.php:12:    public static function castDatabaseValue(
app/src/Modules/Media/Domain/ValueObject/MediaMimeType.php:38:    public function baseValue(): string

exec
/bin/zsh -lc "sed -n '1,220p' docs/reviews/2026-06-22_18-37_media-document-support_cli_meta.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc "sed -n '1,260p' app/src/Modules/Media/README.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
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
session id: 019ef002-10b9-7731-ad26-08684af35736
--------
user
Прочитай файлы (абсолютные пути): ревью /Users/gian_tiaga/Code/yoga-loka-spiral-2/docs/reviews/2026-06-22_18-37_media-document-support.md, план /Users/gian_tiaga/Code/yoga-loka-spiral-2/docs/plans/2026-06-22_17-35_media-document-support.md, /Users/gian_tiaga/Code/yoga-loka-spiral-2/docs/rules.md, /Users/gian_tiaga/Code/yoga-loka-spiral-2/docs/arch.md, связанный research /Users/gian_tiaga/Code/yoga-loka-spiral-2/docs/researches/2026-06-22_17-22_media-document-support.md. Target ревью: git diff HEAD только по модулю Media и его тестам (получи через 'git --no-pager diff HEAD -- app/src/Modules/Media/ tests/'; новый файл tests/Unit/Modules/Media/Domain/Collection/MediaMimeTypeCollectionTest.php untracked — читай его напрямую). Затем глубоко проверь ревью по плану, правилам, архитектуре и релевантному коду: открой файлы, модули, API и тесты, которые следуют из diff и замечаний, и оцени фактическую корректность. Мета-ревью на русском: какие проблемы добавить, убрать, переформулировать; какие баги, риски для смежного кода и недостающие тесты пропущены. Не предлагай перечислять выполненную работу. Формат: '+', '−', '~', '?'.
codex
Использую `eda-review-check`, потому что задача именно про проверку готового ревью относительно плана, правил, архитектуры и фактического diff. Сначала прочитаю инструкции скилла, затем подниму нужные документы и код.
exec
/bin/zsh -lc "sed -n '1,240p' /Users/gian_tiaga/Code/yoga-loka-spiral-2/.codex/skills/eda-review-check/SKILL.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
---
name: eda-review-check
description: 'Проверяет готовое ревью из `docs/reviews/...` специализированными субагентами и, в strict-режиме, соседним CLI. Не делает первичное ревью и не правит код. Читает ревью, target/plan из front matter, `docs/rules.md`, `docs/arch.md`, `docs/settings.yaml` и связанный research при наличии. Запускает architecture-check, rules-check, plan-check только при указанном plan-файле и опциональный quality-check, затем сам применяет подтверждённые замечания к файлу ревью: добавляет, убирает или переформулирует пункты, обновляет score/status/meta_reviewers и раздел изменений после мета-ревью.'
---

# Скил: Проверка ревью (eda-review-check)

Берёшь уже сохранённое ревью из `docs/reviews/`, проверяешь его специализированными субагентами, затем сам правишь файл ревью по подтверждённым замечаниям. Этот скил переиспользуется обычным `eda-review` после draft-этапа и может запускаться отдельно.

## Режим запуска

Прочитай `docs/settings.yaml`, если файл есть. Прямое указание пользователя в текущем сообщении важнее настроек.

Дефолты: `defaults.strict: false`, `review.include_code_quality: true`.

| Настройка | Значения | Аргументы в сообщении |
|---|---|---|
| `defaults.strict` | `true`, `false` | `strict`; выключение: `normal`, `без strict`, `без строгого режима` |
| `review.include_code_quality` | `true`, `false` | «проверь качество кода»; выключение: «без quality», «без проверки качества» |

`strict` добавляет кросс-CLI соседним агентом. `review.include_code_quality: true` добавляет `quality-check` в обычное мета-ревью.

## Вход из сообщения пользователя

Текст рядом с вызовом скилла в текущем сообщении пользователя — главный вход. Сначала разбери именно его: путь к ревью, название или часть названия, «последнее ревью», режим, ограничения и прямые указания.

Если этого входа достаточно, продолжай без вопроса. `AskUserQuestion` задавай только если входа нет, он противоречивый, найдено несколько равных вариантов или есть риск проверить не то ревью. Старое обсуждение в истории не считай входом, если пользователь не связал его с текущим вызовом явно.

## Главные правила

1. **Не делаешь первичное ревью.** Входом всегда является готовый файл `docs/reviews/...`.
2. **Не правишь код.** Можно менять только выбранный файл ревью и вспомогательный файл `${REVIEW_FILE%.md}_cli_meta.md` в strict-режиме.
3. **Мета-ревью специализированными агентами обязательно.** Базовые роли: `architecture-check`, `rules-check`. `plan-check` запускай только если в front matter ревью `plan` указывает на конкретный файл. Если включена проверка качества кода — добавь `quality-check` в тот же batch.
4. **Главный редактор ревью — ты.** Субагенты дают предложения; решение добавить, убрать или переформулировать пункт принимаешь сам.
5. **Ревью остаётся ревью только по проблемам.** Не добавляй выполненные пункты, похвалу, нейтральный отчёт о diff или общие рассуждения.
6. **Каждое принятое изменение фиксируй в `## Изменения после мета-ревью`.** Отклонённые предложения тоже запиши коротко с причиной.
7. **Оценку пересчитывай после правок.** Если изменился набор или тяжесть замечаний, обнови `score` в front matter и текст в разделе `## Оценка`.

## Интерактивные вопросы

Когда инструкция говорит `AskUserQuestion`, это означает блокирующий интерактивный вопрос.
- Claude Code: используй `AskUserQuestion`.
- Codex interactive: если доступен `request_user_input`, используй его. Если tool недоступен, задай один короткий вопрос в чат, дай варианты 1–3, напиши «Ответь номером или своим вариантом. Я продолжу только после ответа.» и остановись.
- Codex exec / неинтерактивный запуск: не задавай вопросы и не пытайся читать stdin. Если без ответа нельзя безопасно продолжать, заверши работу со статусом `blocked: нужен ответ пользователя`, перечисли вопросы и варианты, не выполняй рискованные действия.
- Не запускай команды, которые ждут интерактивного ввода в терминале; перед такими командами спроси в хост-интерфейсе или остановись с `blocked`.

## Этапы

### 1. Выбрать ревью и прочитать контекст

Определи `$REVIEW_FILE`:
- если пользователь указал путь к ревью — используй его;
- если указал название или часть названия — найди совпадение в `docs/reviews/`;
- если написал «последнее ревью» — возьми самый новый файл из `docs/reviews/`;
- если входа нет — `AskUserQuestion` со списком последних файлов из `docs/reviews/`;
- если `docs/reviews/` отсутствует или пуста — остановись со статусом `blocked: нужно ревью`.

Прочитай `$REVIEW_FILE` целиком. Из front matter и текста извлеки `$TARGET`, `$PLAN_FILE`, текущие `mode`, `score`, `status`, `meta_reviewers`. Если `plan: none` или план явно помечен как пропущенный, установи `$PLAN_FILE=none`. Если target указывает на diff-команду, получи актуальный diff. Если target указывает на файл, папку или PR — прочитай достаточно кода, чтобы проверить замечания. Прочитай `docs/rules.md`, `docs/arch.md`, `docs/settings.yaml`, если они есть. Если ревью ссылается на `docs/researches/...`, прочитай связанный research.

Если `$PLAN_FILE=none` или отсутствует, не угадывай план и не запускай `plan-check`. В разделе изменений после мета-ревью коротко зафиксируй: «plan-check не запускался: план не указан в ревью».

### 2. Запустить специализированных субагентов

Запусти **в одном сообщении и одним batch** агентов с выбранными ролями. В интерактивном Codex это означает субагентов, а не отдельные процессы `codex exec`. Не запускай сначала часть агентов, не жди их ответы и не запускай остальных позже: все выбранные проверки должны стартовать до чтения любого результата.

Роли агентов:
- `plan-check`: запускай только если `$PLAN_FILE` указывает на существующий файл. Проверяет, что реализация действительно выполнила `$PLAN_FILE`, включая частичные пункты, пропущенные требования, лишние отклонения и недостающие проверки.
- `architecture-check`: проверяет следование `docs/arch.md`, границы модулей, контракты, зависимости и соответствие существующим архитектурным решениям.
- `rules-check`: проверяет следование `docs/rules.md`, локальным запретам, обязательным командам, требованиям к тестам, миграциям, документации и процессу.
- `quality-check`: запускай только если `review.include_code_quality: true` или пользователь явно попросил качество кода. Проверяет качество реализации: читаемость, сложность, дублирование, имена, размер и ответственность функций/классов, сцепление модулей, поддерживаемость и соответствие локальному стилю. Quality-замечания помечай типом `quality`; по умолчанию рекомендация `на усмотрение автора`, кроме случаев, где сложность, дублирование или неясная структура реально создают риск багов, тестовых пробелов или дорогого сопровождения.

Модели:
- Claude Code: запускай все роли через `Agent` tool на `model: "sonnet"`.
- Codex interactive: если доступен инструмент субагентов (`spawn_agent` или аналог), запускай все роли через него на `gpt-5.3-codex`; не используй отдельные `codex exec` для обычного мета-ревью, когда субагенты доступны.
- Codex exec / неинтерактивный fallback: если инструмента субагентов нет, запускай роли через параллельные `codex exec --model gpt-5.3-codex` одной Bash-командой с `&` и общим `wait`.
- Если нужный идентификатор недоступен, возьми ближайшую code-capable модель того же уровня. Не используй быструю слабую модель для этих проверок: они должны читать diff, план при наличии, правила, архитектуру и релевантный код.

Каждому агенту дай путь к `$REVIEW_FILE`, `$TARGET`, `docs/rules.md`, `docs/arch.md`, `docs/settings.yaml`, связанный research при наличии. `plan-check` дополнительно получает `$PLAN_FILE`, если он есть. Каждый агент должен читать не только ревью, но и фактический diff/код, достаточный для проверки своей роли.

Формат ответа агентов:
- `+` какую проблему добавить в ревью;
- `−` что убрать как неверное или недоказанное;
- `~` что переформулировать;
- `?` что невозможно проверить и почему.

Если `$PLAN_FILE` не передан, равен `none` или файл не найден, `plan-check` не запускай.

### 3. Применить результаты обычного мета-ревью

Когда все агенты ответили: прочитай ответы, **сам реши**, что применять. Добавляй только подтверждённые проблемы; предложения вида «добавить, что пункт выполнен» отклоняй как неформат ревью. Внеси правки в файл ревью.

В разделе `## Изменения после мета-ревью` запиши:

```markdown
### После plan-check / architecture-check / rules-check / quality-check
- **+ Добавлено:** <короткий список>
- **~ Изменено:** <короткий список>
- **− Убрано:** <короткий список>
- **Отклонено:** <короткий список с причиной>
```

Поменяй `status: draft` → `meta-reviewed`, если статус был draft. В `meta_reviewers` добавь только фактически запущенные роли: `architecture-check`, `rules-check`, `plan-check` при наличии `$PLAN_FILE`, а если запускался — `quality-check`, и фактические модели, например `gpt-5.3-codex` или `sonnet`. Если оценка после корректировок изменилась — обнови `score`.

### 4. Кросс-CLI: отдать соседнему агенту — только в `strict`

Обычный режим — пропусти этап 4, иди на финал. `status: meta-reviewed` оставь как есть.

В `strict`: отдай файл соседнему агенту через `Bash`. Если ты в Claude Code — запускай Codex CLI:

```bash
codex exec "Прочитай $REVIEW_FILE, $TARGET, $PLAN_FILE, docs/rules.md, docs/arch.md, связанный research при наличии. Затем глубоко проверь ревью по плану, правилам, архитектуре и релевантному коду: открой файлы, модули, API и тесты, которые следуют из diff и замечаний, и оцени фактическую корректность. Мета-ревью на русском: какие проблемы добавить, убрать, переформулировать; какие баги, риски для смежного кода и недостающие тесты пропущены. Не предлагай перечислять выполненную работу. Формат: '+', '−', '~', '?'." > "${REVIEW_FILE%.md}_cli_meta.md"
```

Если ты в Codex — запускай Claude CLI с теми же требованиями глубины:

```bash
claude -p "Прочитай $REVIEW_FILE, $TARGET, $PLAN_FILE, docs/rules.md, docs/arch.md, связанный research при наличии. Затем глубоко проверь ревью по плану, правилам, архитектуре и релевантному коду: открой файлы, модули, API и тесты, которые следуют из diff и замечаний, и оцени фактическую корректность. Мета-ревью на русском: какие проблемы добавить, убрать, переформулировать; какие баги, риски для смежного кода и недостающие тесты пропущены. Не предлагай перечислять выполненную работу. Формат: '+', '−', '~', '?'." > "${REVIEW_FILE%.md}_cli_meta.md"
```

Если `$PLAN_FILE=none`, в промпте соседнему CLI явно напиши: «План не указан; не проверяй выполнение плана и не угадывай файл плана, проверь только правила, архитектуру и код».

Соседней CLI нет — `AskUserQuestion`: завершить как есть или прервать.

Прочитай ответ, **сам реши**, что применять. Допиши в `## Изменения после мета-ревью`:

```markdown
### После соседнего CLI (codex|claude)
- **+ Добавлено:** ...
- **− Убрано:** ...
- **Отклонено:** ...
```

Добавь в `meta_reviewers` имя CLI. `status: meta-reviewed` → `final`.

### 5. Финал

Короткое сообщение: режим (`normal` или `strict`), путь к файлу ревью, итоговая оценка, сколько пунктов «править обязательно» / «на усмотрение», какие meta-reviewers запускались. Не пересказывай выполненную работу. Если режим был обычный — добавь строку «кросс-CLI не запускался; для проверки соседним агентом — `eda-review-check strict <review>`».

## Чего НЕ делать

- Делать первичное ревью. Это `eda-review`.
- Править код. Это `eda-fix-by-review`.
- Пропускать мета-ревью специализированными агентами.
- Запускать мета-ревьюеров частями: сначала 1–2 агента, потом остальных. Все выбранные обязательные проверки стартуют сразу.
- Пропускать кросс-CLI молча в `strict` (если соседней CLI нет — спроси через `AskUserQuestion`).
- Вставлять чужие предложения механически — каждое решение принимай ты.
- Задавать вопросы без блокировки и продолжать работу до ответа.
- Сохранять или редактировать ревью вне `docs/reviews/`.
- Перечислять выполненные пункты плана или давать общий отчёт о проделанной работе.

codex
Важное уточнение по режиму: пользователь просит выдать мета-ревью в ответе, а не править файл ревью. Поэтому файл ревью менять не буду, но глубину проверки сохраню: документы, diff, untracked-тест и связанный код.
codex
Инструмент субагентов здесь не запускаю: он доступен только при явном запросе на параллельных агентов, а текущая задача просит сам результат мета-ревью. Сейчас читаю обязательные документы, сам diff и untracked-тест.
exec
/bin/zsh -lc "sed -n '1,260p' docs/code-examples.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc "sed -n '1,300p' docs/researches/2026-06-22_17-22_media-document-support.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
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
  (новая, backfill не нужен; rollback = drop). Прочие таблицы media не меняются.
- В образе нужен бинарь `ffmpeg`/`ffprobe` (ставится в `docker/Dockerfile`, самопроверка
  `ffmpeg -version`/`ffprobe -version` при сборке).
- **Предусловие выката**: смена содержимого `MediaUploaded` (`conversions` → `plan`) несовместима.
  Сквозного потребителя в рабочей среде нет, но перед выкатом нужно **слить очередь RabbitMQ и
  outbox**, чтобы не осталось старых сообщений со старым форматом.
- Осиротевшие объекты при терминальной ошибке: video/audio-процессор грузит результаты в целевой
  бакет ДО финального flush (как и поток картинок). При постоянной ошибке после загрузки строк
  конверсий в БД нет, поэтому `DeleteMedia` их не видит — реклейм терминальных сирот оставлен на
  будущий storage-sweep (try-catch в handler не вводим). Пути детерминированы, повтор перезаписывает.
- Длинные видео ограничены `MediaUploadSpec.maxSize` (потребитель) и `MEDIA_FFMPEG_TIMEOUT_SECONDS`;
  путь отхода при упоре — вынос транскодирования в Temporal (осознанный компромисс ради
  единообразия с картинками).
- Нужен запущенный `outbox:relay` (один экземпляр) и consumer RabbitMQ.
- `media-public` должен иметь anonymous-read policy (в dev ставится `ensure-buckets.sh`; в
  prod/stage — задача деплоя).
- Переключение ленты `Posts` на нормализованные video/audio (mp4 + постер, m4a + волна) — **вне
  этой задачи**: Media производит конверсии и отдаёт их своими запросами, подключение ленты —
  отдельная задача.
- Полноценный сквозной сценарий по HTTP появится с модулем-потребителем, который добавит точку
  входа с настройками и вызовет этот Application-API.

exec
/bin/zsh -lc "nl -ba app/src/Modules/Media/Application/Query/CheckMediaIsImage/CheckMediaIsImageHandler.php | sed -n '1,80p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Media\Application\Query\CheckMediaIsImage;
     6	
     7	use App\Modules\Media\Domain\Enum\MediaType;
     8	use App\Modules\Media\Domain\ValueObject\MediaId;
     9	use App\Modules\Media\Repository\MediaRepository;
    10	
    11	final readonly class CheckMediaIsImageHandler
    12	{
    13	    public function __construct(
    14	        private MediaRepository $mediaRepository,
    15	    ) {}
    16	
    17	    public function handle(CheckMediaIsImageQuery $query): bool
    18	    {
    19	        $media = $this->mediaRepository->findById(MediaId::fromString($query->mediaId));
    20	
    21	        return $media !== null && $media->type === MediaType::Image;
    22	    }
    23	}

exec
/bin/zsh -lc "nl -ba app/src/Modules/Media/Application/Query/CheckMediaAttachable/CheckMediaAttachableHandler.php | sed -n '1,160p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Media\Application\Query\CheckMediaAttachable;
     6	
     7	use App\Modules\Media\Domain\ValueObject\MediaId;
     8	use App\Modules\Media\Repository\MediaRepository;
     9	use App\Shared\Domain\Exception\ForbiddenException;
    10	use App\Shared\Domain\Exception\NotFoundException;
    11	use App\Shared\Domain\Exception\ValidationException;
    12	use App\Shared\Domain\ValueObject\UserId;
    13	use GianTiaga\SpiralCqrs\Attribute\LogOperation;
    14	
    15	/**
    16	 * Проверяет, можно ли вложить медиа в запись: оно должно существовать (иначе 404), принадлежать
    17	 * владельцу (иначе 403) и быть обработанным/готовым (иначе 422). Вызывается до MakeMediaPermanent,
    18	 * поэтому тот всегда получает готовое медиа.
    19	 */
    20	final readonly class CheckMediaAttachableHandler
    21	{
    22	    public function __construct(
    23	        private MediaRepository $mediaRepository,
    24	    ) {}
    25	
    26	    #[LogOperation]
    27	    public function handle(CheckMediaAttachableQuery $query): MediaAttachableResult
    28	    {
    29	        $media = $this->mediaRepository->findById(MediaId::fromString($query->mediaId))
    30	            ?? throw new NotFoundException('app.media.not_found');
    31	
    32	        if (!$media->uploadedById->equals(UserId::fromString($query->ownerUserId))) {
    33	            throw new ForbiddenException('app.media.access_denied');
    34	        }
    35	
    36	        if (!$media->isReady()) {
    37	            throw new ValidationException('app.media.not_ready');
    38	        }
    39	
    40	        return new MediaAttachableResult(mediaId: $media->id->value());
    41	    }
    42	}

exec
/bin/zsh -lc "nl -ba app/src/Modules/Media/Application/Query/FindMediaUrl/FindMediaUrlHandler.php | sed -n '1,140p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Media\Application\Query\FindMediaUrl;
     6	
     7	use App\Modules\Media\Application\Contract\MediaFileServiceContract;
     8	use App\Modules\Media\Application\Dto\MediaUrlResult;
     9	use App\Modules\Media\Domain\Enum\MediaVisibility;
    10	use App\Modules\Media\Domain\ValueObject\MediaId;
    11	use App\Modules\Media\Domain\ValueObject\MediaPresignedTtl;
    12	use App\Modules\Media\Repository\MediaRepository;
    13	use GianTiaga\SpiralCqrs\Attribute\LogOperation;
    14	
    15	/**
    16	 * Не бросающий вариант GetMediaUrl для best-effort отображения (например, аватара в профиле):
    17	 * если медиа отсутствует или ещё не готово — возвращает null, чтобы вызывающий подставил значение
    18	 * по умолчанию без try-catch. Для публичного медиа отдаёт прямой URL, для приватного — presigned.
    19	 */
    20	final readonly class FindMediaUrlHandler
    21	{
    22	    public function __construct(
    23	        private MediaRepository $mediaRepository,
    24	        private MediaFileServiceContract $mediaFileService,
    25	    ) {}
    26	
    27	    #[LogOperation]
    28	    public function handle(FindMediaUrlQuery $query): MediaUrlResult|null
    29	    {
    30	        $media = $this->mediaRepository->findById(MediaId::fromString($query->mediaId));
    31	
    32	        if ($media === null || !$media->isReady()) {
    33	            return null;
    34	        }
    35	
    36	        if ($media->visibility === MediaVisibility::Public) {
    37	            return new MediaUrlResult(
    38	                url: $this->mediaFileService->publicUrl(storage: $media->storage, path: $media->path),
    39	                expiresAt: null,
    40	            );
    41	        }
    42	
    43	        $expiresAt = new \DateTimeImmutable()->add(
    44	            new \DateInterval(\sprintf('PT%dS', MediaPresignedTtl::fromInt($query->presignedTtlSeconds)->value())),
    45	        );
    46	
    47	        return new MediaUrlResult(
    48	            url: $this->mediaFileService->presignGet(storage: $media->storage, path: $media->path, expiresAt: $expiresAt),
    49	            expiresAt: $expiresAt,
    50	        );
    51	    }
    52	}

exec
/bin/zsh -lc "nl -ba app/src/Modules/Media/Application/Query/GetMediaUrl/GetMediaUrlHandler.php | sed -n '1,180p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Media\Application\Query\GetMediaUrl;
     6	
     7	use App\Modules\Media\Application\Contract\MediaFileServiceContract;
     8	use App\Modules\Media\Application\Dto\MediaUrlResult;
     9	use App\Modules\Media\Domain\Entity\MediaAudioConversion;
    10	use App\Modules\Media\Domain\Entity\MediaImageConversion;
    11	use App\Modules\Media\Domain\Entity\MediaVideoConversion;
    12	use App\Modules\Media\Domain\Enum\MediaAudioConversionType;
    13	use App\Modules\Media\Domain\Enum\MediaImageConversionType;
    14	use App\Modules\Media\Domain\Enum\MediaStorage;
    15	use App\Modules\Media\Domain\Enum\MediaVideoConversionType;
    16	use App\Modules\Media\Domain\Enum\MediaVisibility;
    17	use App\Modules\Media\Domain\ValueObject\MediaId;
    18	use App\Modules\Media\Domain\ValueObject\MediaPath;
    19	use App\Modules\Media\Domain\ValueObject\MediaPresignedTtl;
    20	use App\Modules\Media\Repository\MediaAudioConversionRepository;
    21	use App\Modules\Media\Repository\MediaImageConversionRepository;
    22	use App\Modules\Media\Repository\MediaRepository;
    23	use App\Modules\Media\Repository\MediaVideoConversionRepository;
    24	use App\Shared\Domain\Exception\NotFoundException;
    25	
    26	final readonly class GetMediaUrlHandler
    27	{
    28	    public function __construct(
    29	        private MediaRepository $mediaRepository,
    30	        private MediaImageConversionRepository $mediaImageConversionRepository,
    31	        private MediaVideoConversionRepository $mediaVideoConversionRepository,
    32	        private MediaAudioConversionRepository $mediaAudioConversionRepository,
    33	        private MediaFileServiceContract $mediaFileService,
    34	    ) {}
    35	
    36	    public function handle(GetMediaUrlQuery $query): MediaUrlResult
    37	    {
    38	        $media = $this->mediaRepository->findById(MediaId::fromString($query->mediaId))
    39	            ?? throw new NotFoundException('app.media.not_found');
    40	
    41	        if (!$media->isReady()) {
    42	            throw new NotFoundException('app.media.not_ready');
    43	        }
    44	
    45	        if ($query->conversionType !== null) {
    46	            $conversion = $this->findConversion(mediaId: $media->id, type: $query->conversionType)
    47	                ?? throw new NotFoundException('app.media.conversion_not_found');
    48	
    49	            return $this->buildUrl(
    50	                visibility: $media->visibility,
    51	                storage: $conversion->storage,
    52	                path: $conversion->path,
    53	                presignedTtlSeconds: $query->presignedTtlSeconds,
    54	            );
    55	        }
    56	
    57	        return $this->buildUrl(
    58	            visibility: $media->visibility,
    59	            storage: $media->storage,
    60	            path: $media->path,
    61	            presignedTtlSeconds: $query->presignedTtlSeconds,
    62	        );
    63	    }
    64	
    65	    private function findConversion(
    66	        MediaId $mediaId,
    67	        MediaImageConversionType|MediaVideoConversionType|MediaAudioConversionType $type,
    68	    ): MediaImageConversion|MediaVideoConversion|MediaAudioConversion|null {
    69	        // У изображений несколько типов — выбираем нужный по type. У видео и аудио каталог типов
    70	        // содержит ровно один профиль и на медиа не больше одной такой конверсии, поэтому берём
    71	        // первую (фильтр по единственному типу был бы всегда истинным).
    72	        return match (true) {
    73	            $type instanceof MediaImageConversionType => $this->mediaImageConversionRepository->findByMediaId($mediaId)->first(
    74	                static fn(MediaImageConversion $conversion): bool => $conversion->type === $type,
    75	            ),
    76	            $type instanceof MediaVideoConversionType => $this->mediaVideoConversionRepository->findByMediaId($mediaId)->first(),
    77	            $type instanceof MediaAudioConversionType => $this->mediaAudioConversionRepository->findByMediaId($mediaId)->first(),
    78	        };
    79	    }
    80	
    81	    private function buildUrl(
    82	        MediaVisibility $visibility,
    83	        MediaStorage $storage,
    84	        MediaPath $path,
    85	        int $presignedTtlSeconds,
    86	    ): MediaUrlResult {
    87	        if ($visibility === MediaVisibility::Public) {
    88	            return new MediaUrlResult(
    89	                url: $this->mediaFileService->publicUrl(storage: $storage, path: $path),
    90	                expiresAt: null,
    91	            );
    92	        }
    93	
    94	        $expiresAt = new \DateTimeImmutable()->add(
    95	            new \DateInterval(\sprintf('PT%dS', MediaPresignedTtl::fromInt($presignedTtlSeconds)->value())),
    96	        );
    97	
    98	        return new MediaUrlResult(
    99	            url: $this->mediaFileService->presignGet(storage: $storage, path: $path, expiresAt: $expiresAt),
   100	            expiresAt: $expiresAt,
   101	        );
   102	    }
   103	}

codex
Нашёл отдельный класс замечаний, который ревью почти не тронуло: новые комментарии в тестах местами используют английские слова в русском тексте. Для этого проекта это не вкусовщина, а прямое правило из `docs/rules.md`.
exec
/bin/zsh -lc "nl -ba tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php | sed -n '320,338p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
   320	    }
   321	
   322	    private function jpegBytes(): string
   323	    {
   324	        $image = \imagecreatetruecolor(200, 150);
   325	        \imagefill($image, 0, 0, (int) \imagecolorallocate($image, 40, 160, 90));
   326	
   327	        \ob_start();
   328	        \imagejpeg($image);
   329	
   330	        return (string) \ob_get_clean();
   331	    }
   332	
   333	    private function documentBytes(): string
   334	    {
   335	        return "%PDF-1.4\nТестовое содержимое документа для flow-теста.\n%%EOF";
   336	    }
   337	
   338	    private function videoBytes(): string

exec
/bin/zsh -lc 'git --no-pager diff HEAD -- tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php' in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
diff --git a/tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php b/tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php
index 5151b5a..6d4c5f2 100644
--- a/tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php
+++ b/tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php
@@ -33,6 +33,18 @@ final class CheckMediaQueriesTest extends MediaApplicationTestCase
         self::assertFalse($handler->handle(new CheckMediaIsImageQuery(mediaId: $media->id->value())));
     }
 
+    public function testCheckMediaIsImageReturnsFalseForDocument(): void
+    {
+        // DJVU имеет MIME image/vnd.djvu и теперь классифицируется как Document, а не Image.
+        // Тест пиннит, что документ нельзя подсунуть в image-only сценарий (например аватар).
+        $media = $this->createMedia(userId: UserId::generate(), type: MediaType::Document, extension: 'pdf', mimeType: 'application/pdf');
+        $this->persist($media);
+
+        $handler = new CheckMediaIsImageHandler(mediaRepository: $this->mediaRepository());
+
+        self::assertFalse($handler->handle(new CheckMediaIsImageQuery(mediaId: $media->id->value())));
+    }
+
     public function testCheckMediaIsImageReturnsFalseForMissingMedia(): void
     {
         $handler = new CheckMediaIsImageHandler(mediaRepository: $this->mediaRepository());
diff --git a/tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php b/tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php
index a4e11fb..5f115c7 100644
--- a/tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php
+++ b/tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php
@@ -16,6 +16,7 @@ use App\Modules\Media\Application\Service\MediaTypeResolver;
 use App\Modules\Media\Domain\Collection\MediaMimeTypeCollection;
 use App\Modules\Media\Domain\Enum\MediaStatus;
 use App\Modules\Media\Domain\Enum\MediaStorage;
+use App\Modules\Media\Domain\Enum\MediaType;
 use App\Modules\Media\Domain\Enum\MediaVisibility;
 use App\Modules\Media\Domain\ValueObject\MediaFileSize;
 use App\Modules\Media\Domain\ValueObject\MediaId;
@@ -160,6 +161,57 @@ final class RequestMediaUploadHandlerTest extends MediaApplicationTestCase
         ));
     }
 
+    public function testRequestsDocumentUpload(): void
+    {
+        $fileService = $this->createStub(MediaFileServiceContract::class);
+        $fileService->method('presignPut')->willReturn('http://minio/put-url');
+
+        $result = $this->handler($fileService)->handle(new RequestMediaUploadCommand(
+            userId: UserId::generate()->value(),
+            spec: $this->spec(allowedMimeTypes: new MediaMimeTypeCollection([MediaMimeType::fromString('application/pdf')])),
+            fileMeta: $this->fileMeta(fileName: 'document.pdf', mimeType: 'application/pdf'),
+        ));
+
+        $media = $this->mediaRepository()->findById(MediaId::fromString($result->mediaId));
+        self::assertNotNull($media);
+        self::assertSame(MediaType::Document, $media->type);
+        self::assertSame(MediaStatus::WaitingUpload, $media->status);
+        // Документ кладётся в staging как и другие типы: путь начинается с uploads/.
+        self::assertStringStartsWith('uploads/', $media->path->value());
+    }
+
+    public function testRequestsDocumentUploadWithCharsetMime(): void
+    {
+        $fileService = $this->createStub(MediaFileServiceContract::class);
+        $fileService->method('presignPut')->willReturn('http://minio/put-url');
+
+        // Спека задаёт базовый MIME без параметра, файл приходит с ;charset=utf-8 —
+        // нормализация в containsMimeType должна пропустить загрузку.
+        $result = $this->handler($fileService)->handle(new RequestMediaUploadCommand(
+            userId: UserId::generate()->value(),
+            spec: $this->spec(allowedMimeTypes: new MediaMimeTypeCollection([MediaMimeType::fromString('text/markdown')])),
+            fileMeta: $this->fileMeta(fileName: 'note.md', mimeType: 'text/markdown;charset=utf-8'),
+        ));
+
+        $media = $this->mediaRepository()->findById(MediaId::fromString($result->mediaId));
+        self::assertNotNull($media);
+        self::assertSame(MediaType::Document, $media->type);
+    }
+
+    public function testRejectsDocumentMimeOutsideSpec(): void
+    {
+        // Резолвер поддерживает text/csv как документ, но спека его не разрешает —
+        // потребитель сужает набор, а не расширяет.
+        $this->expectException(ValidationException::class);
+        $this->expectExceptionMessage('app.media.mime_not_allowed');
+
+        $this->handler()->handle(new RequestMediaUploadCommand(
+            userId: UserId::generate()->value(),
+            spec: $this->spec(allowedMimeTypes: new MediaMimeTypeCollection([MediaMimeType::fromString('application/pdf')])),
+            fileMeta: $this->fileMeta(fileName: 'sheet.csv', mimeType: 'text/csv'),
+        ));
+    }
+
     private function handler(
         MediaFileServiceContract|null $fileService = null,
         int $threshold = 16_777_216,
@@ -184,10 +236,13 @@ final class RequestMediaUploadHandlerTest extends MediaApplicationTestCase
         );
     }
 
-    private function spec(int $maxSize = 1_048_576, int $presignedTtl = 300): MediaUploadSpec
-    {
+    private function spec(
+        int $maxSize = 1_048_576,
+        int $presignedTtl = 300,
+        MediaMimeTypeCollection|null $allowedMimeTypes = null,
+    ): MediaUploadSpec {
         return new MediaUploadSpec(
-            allowedMimeTypes: new MediaMimeTypeCollection([MediaMimeType::fromString('image/jpeg')]),
+            allowedMimeTypes: $allowedMimeTypes ?? new MediaMimeTypeCollection([MediaMimeType::fromString('image/jpeg')]),
             maxSize: MediaFileSize::fromInt($maxSize),
             visibility: MediaVisibility::Private,
             presignedTtl: MediaPresignedTtl::fromInt($presignedTtl),
diff --git a/tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php b/tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php
index 3740c20..e7be60c 100644
--- a/tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php
+++ b/tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php
@@ -203,6 +203,36 @@ final class MediaProcessingFlowTest extends MediaApplicationTestCase
         self::assertNotNull($this->fileService()->headObject(MediaStorage::Public, $normalizedPath));
     }
 
+    public function testCompleteThenRelayProcessesDocumentToReady(): void
+    {
+        $media = $this->uploadedOriginal(
+            $this->documentBytes(),
+            MediaVisibility::Public,
+            MediaType::Document,
+            'pdf',
+            'application/pdf',
+        );
+
+        $this->completeUpload($media, $this->emptyPlan());
+        self::assertSame(1, $this->relay());
+
+        $processedMedia = $this->mediaRepository()->findById($media->id);
+        self::assertNotNull($processedMedia);
+        self::assertSame(MediaStatus::Ready, $processedMedia->status);
+        self::assertSame(MediaStorage::Public, $processedMedia->storage);
+
+        // У документа конверсий нет.
+        self::assertCount(0, $this->imageConversionRepository()->findByMediaId($media->id));
+
+        $readyPath = MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Document, extension: 'pdf');
+        self::assertStringStartsWith('documents/', $readyPath->value());
+        $this->track(MediaStorage::Public, $readyPath);
+
+        // Реальный оригинал переложен по ready-пути documents/<shard>/<key>/source.pdf без конверсий.
+        self::assertSame($readyPath->value(), $processedMedia->path->value());
+        self::assertNotNull($this->fileService()->headObject(MediaStorage::Public, $readyPath));
+    }
+
     public function testProcessingFailureRecordsErrorAndFailsOutbox(): void
     {
         $corruptBytes = \random_bytes(2048);
@@ -300,6 +330,11 @@ final class MediaProcessingFlowTest extends MediaApplicationTestCase
         return (string) \ob_get_clean();
     }
 
+    private function documentBytes(): string
+    {
+        return "%PDF-1.4\nТестовое содержимое документа для flow-теста.\n%%EOF";
+    }
+
     private function videoBytes(): string
     {
         return $this->ffmpegFixture('mp4', [

exec
/bin/zsh -lc 'rg -n "staging|image-only|ready-|flow-|pin|пин|whitelist|pipeline|charset|presigned|multipart|public|private" app/src/Modules/Media tests/Feature/Modules/Media tests/Unit/Modules/Media' in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:40:    public function testUuidV7IdValidatesInputAndGeneratesValue(): void
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:52:    public function testStorageKeyUsesUuidV4(): void
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:64:    public function testPathValidatesFormatAndShard(): void
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:78:    public function testMediaPathFactoriesBuildValidPaths(): void
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:128:    public function testMediaPathExtensionReadsLastSegment(): void
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:136:    public function testMediaPathAcceptsDocumentsPrefix(): void
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:144:    public function testMediaPathRejectsDocumentsPrefixWithWrongShard(): void
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:152:    public function testWaveformValidatesPeaksAndSerializes(): void
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:162:    public function testWaveformRejectsEmptyPeaks(): void
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:169:    public function testWaveformRejectsPeakOutOfRange(): void
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:176:    public function testMediaPathFactoryRejectsInvalidExtension(): void
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:187:    public function testMimeTypeValidatesLength(): void
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:196:    public function testMimeTypeBaseValueNormalizesWithoutChangingRawValue(): void
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:198:        self::assertSame('text/markdown', MediaMimeType::fromString('text/markdown;charset=utf-8')->baseValue());
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:204:            'text/markdown;charset=utf-8',
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:205:            MediaMimeType::fromString('text/markdown;charset=utf-8')->value(),
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:213:    public function testIntegerValueObjectsValidateBorders(string $valueObjectClass, int $valid, int $invalid): void
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:227:    public function testProcessingAttemptsCanIncrement(): void
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:232:    public function testExpirationRepresentsPermanentAndTemporaryStates(): void
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:241:    public function testProcessingErrorRejectsUnsafeTechnicalData(): void
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:251:    public function testMultipartValuesValidateInput(): void
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:257:    public function testMultipartPartCollectionSortsPartsAndRejectsDuplicates(): void
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:284:    public function testProcessingAttemptsRejectsIncrementPastMaximum(): void
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:292:    public function testMultipartUploadIdComparesAndSerializes(): void
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:306:    public function testMultipartPartETagSerializesAndRejectsEmpty(): void
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:315:    public function testMimeTypeSerializesToJson(): void
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:320:    public function testStorageKeySerializesToJson(): void
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:328:    public function testExpirationFailsWhenPermanentAskedForExpiry(): void
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:336:    public function testExpirationComparesAndSerializes(): void
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:350:    public function testProcessingErrorSerializesAndRejectsEmpty(): void
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:362:    public function testMediaPathSerializesToJson(): void
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:370:    public function testMediaPathRejectsTooLongValue(): void
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:378:    public function testMediaPathRejectsInvalidFormat(): void
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:386:    public static function integerValueObjectProvider(): iterable
tests/Unit/Modules/Media/Application/MediaUploadedSerializationTest.php:20:    public function testRestoresNestedConversionPlanThroughValinor(): void
tests/Unit/Modules/Media/Domain/Collection/MediaMimeTypeCollectionTest.php:13:    public function testContainsMimeTypeMatchesByBaseValueIgnoringParameters(): void
tests/Unit/Modules/Media/Domain/Collection/MediaMimeTypeCollectionTest.php:17:        self::assertTrue($allowed->containsMimeType(MediaMimeType::fromString('text/markdown;charset=utf-8')));
tests/Unit/Modules/Media/Domain/Collection/MediaMimeTypeCollectionTest.php:20:    public function testContainsMimeTypeRejectsDifferentMimeType(): void
tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php:31:    public function testCreateInitializesMedia(): void
tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php:44:    public function testMultipartStatusTransitions(): void
tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php:60:    public function testProcessingStatusTransitions(): void
tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php:83:    public function testRecordProcessingErrorIsNoOpOnReadyMedia(): void
tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php:86:        // на уже ready-медиа — no-op, статус и пустой processingError сохраняются.
tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php:102:    public function testRecordPermanentProcessingErrorMovesUploadedToProcessingFailed(): void
tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php:114:    public function testCanMakeTemporaryUploadPermanent(): void
tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php:125:    public function testMarkReadyMovedToReassignsStorageAndPathAndIsIdempotent(): void
tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php:145:    public function testMarkReadyMovedToAllowsRetryFromProcessingFailed(): void
tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php:157:    public function testMarkReadyMovedToRejectsTransitionFromWaitingUpload(): void
tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php:166:    public function testAudioConversionCreateInitializesFields(): void
tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php:197:    private function readyPath(): MediaPath
tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php:206:    private function createMedia(): Media
tests/Unit/Modules/Media/Infrastructure/FfmpegMediaProcessorTest.php:30:use FFMpeg\FFProbe\DataMapping\Stream;
tests/Unit/Modules/Media/Infrastructure/FfmpegMediaProcessorTest.php:40:    public function testMediaProcessorFailedExceptionExposesTransience(): void
tests/Unit/Modules/Media/Infrastructure/FfmpegMediaProcessorTest.php:53:    public function testNativeAacAudioFormatUsesNativeCodec(): void
tests/Unit/Modules/Media/Infrastructure/FfmpegMediaProcessorTest.php:61:    public function testNativeAacAudioFormatPassesProfileSampleRateToFfmpeg(): void
tests/Unit/Modules/Media/Infrastructure/FfmpegMediaProcessorTest.php:69:    public function testVideoProcessorClassifiesTimeoutAsTransient(): void
tests/Unit/Modules/Media/Infrastructure/FfmpegMediaProcessorTest.php:85:    public function testVideoProcessorClassifiesGenericFailureAsPermanent(): void
tests/Unit/Modules/Media/Infrastructure/FfmpegMediaProcessorTest.php:101:    public function testVideoProcessorUploadsResultAndCleansTempFilesOnSuccess(): void
tests/Unit/Modules/Media/Infrastructure/FfmpegMediaProcessorTest.php:117:    public function testVideoProcessorCleansTempFilesWhenUploadFails(): void
tests/Unit/Modules/Media/Infrastructure/FfmpegMediaProcessorTest.php:142:    public function testAudioProcessorClassifiesTimeoutAsTransient(): void
tests/Unit/Modules/Media/Infrastructure/FfmpegMediaProcessorTest.php:158:    public function testAudioProcessorClassifiesGenericFailureAsPermanent(): void
tests/Unit/Modules/Media/Infrastructure/FfmpegMediaProcessorTest.php:174:    public function testAudioProcessorUploadsResultAndCleansTempFilesOnSuccess(): void
tests/Unit/Modules/Media/Infrastructure/FfmpegMediaProcessorTest.php:190:    public function testAudioProcessorCleansTempFilesWhenUploadFails(): void
tests/Unit/Modules/Media/Infrastructure/FfmpegMediaProcessorTest.php:215:    public function testRunFfmpegAddsThreadsLimitWhenConfigured(): void
tests/Unit/Modules/Media/Infrastructure/FfmpegMediaProcessorTest.php:230:    public function testRunFfmpegOmitsThreadsLimitWhenZero(): void
tests/Unit/Modules/Media/Infrastructure/FfmpegMediaProcessorTest.php:245:    public function testIsAttachedPictureTreatsNonArrayDispositionAsNotCover(): void
tests/Unit/Modules/Media/Infrastructure/FfmpegMediaProcessorTest.php:258:    private function processVideo(RecordingVideoProcessor $processor): MediaVideoProcessingResult
tests/Unit/Modules/Media/Infrastructure/FfmpegMediaProcessorTest.php:286:    private function processAudio(RecordingAudioProcessor $processor): MediaAudioProcessingResult
tests/Unit/Modules/Media/Infrastructure/FfmpegMediaProcessorTest.php:308:    private function videoResult(): MediaVideoProcessingResult
tests/Unit/Modules/Media/Infrastructure/FfmpegMediaProcessorTest.php:322:    private function audioResult(): MediaAudioProcessingResult
tests/Unit/Modules/Media/Infrastructure/FfmpegMediaProcessorTest.php:334:    private function fileServiceReturning(string $sourceFile): MediaFileServiceContract
tests/Unit/Modules/Media/Infrastructure/FfmpegMediaProcessorTest.php:342:    private function trackedSourceFile(): string
tests/Unit/Modules/Media/Infrastructure/FfmpegMediaProcessorTest.php:350:    private function timeout(): ProcessTimedOutException
tests/Unit/Modules/Media/Infrastructure/FfmpegMediaProcessorTest.php:355:    private function mediaConfig(int $ffmpegThreads = 0): MediaConfig
tests/Unit/Modules/Media/Infrastructure/FfmpegMediaProcessorTest.php:358:            stagingTtlSeconds: 86_400,
tests/Unit/Modules/Media/Infrastructure/FfmpegMediaProcessorTest.php:359:            multipartThresholdBytes: 16_777_216,
tests/Unit/Modules/Media/Infrastructure/FfmpegMediaProcessorTest.php:360:            multipartPartSizeBytes: 8_388_608,
app/src/Modules/Media/Presentation/Job/ProcessMediaJob.php:30:    private const string STORAGE_FAILURE_MESSAGE = 'Ошибка хранилища при обработке медиа.';
app/src/Modules/Media/Presentation/Job/ProcessMediaJob.php:31:    private const string PROCESSING_RETRY_MESSAGE = 'Повторная обработка медиа запланирована.';
app/src/Modules/Media/Presentation/Job/ProcessMediaJob.php:32:    private const string PROCESSING_FAILURE_MESSAGE = 'Не удалось обработать медиа.';
app/src/Modules/Media/Presentation/Job/ProcessMediaJob.php:34:    public function invoke(
app/src/Modules/Media/Presentation/Job/ProcessMediaJob.php:116:    private function safeMessage(\Throwable $exception): string
tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php:33:    public function testClassifiesProcessingFailureAndRecordsErrorOnMedia(
tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php:68:    public function testLogsTransientFailureAsWarningAndPermanentAsError(
tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php:101:    public function testTransientFailureStillRetriesWhenRecordingErrorThrows(): void
tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php:146:    public function testMigratedNotFoundExceptionCarriesTranslationKeyWithoutTranslationInQueueContext(): void
tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php:169:    public static function logLevelProvider(): array
tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php:200:    public static function failureProvider(): array
tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php:230:    private function envelope(): OutboxQueueEnvelope
tests/Unit/Modules/Media/Application/MediaTypeResolverTest.php:16:    public function testResolvesImageVideoAndAudio(): void
tests/Unit/Modules/Media/Application/MediaTypeResolverTest.php:26:    public function testResolvesDocumentByAllowedMimeTypes(): void
tests/Unit/Modules/Media/Application/MediaTypeResolverTest.php:36:    public function testResolvesDjvuAsDocumentNotImage(): void
tests/Unit/Modules/Media/Application/MediaTypeResolverTest.php:46:    public function testNormalizesMimeBeforeClassifyingDocument(): void
tests/Unit/Modules/Media/Application/MediaTypeResolverTest.php:52:            $resolver->resolve(MediaMimeType::fromString('text/markdown;charset=utf-8')),
tests/Unit/Modules/Media/Application/MediaTypeResolverTest.php:58:    public function testResolvesOtherImageMimeAsImage(): void
tests/Unit/Modules/Media/Application/MediaTypeResolverTest.php:65:    public function testRejectsUnsupportedMimeTypes(string $mimeType): void
tests/Unit/Modules/Media/Application/MediaTypeResolverTest.php:75:    public static function unsupportedMimeTypeProvider(): array
tests/Unit/Modules/Media/Infrastructure/S3MediaFileServiceErrorTest.php:34:    public function testThrowsWhenBucketAliasIsNotConfigured(): void
tests/Unit/Modules/Media/Infrastructure/S3MediaFileServiceErrorTest.php:46:    public function testThrowsWhenBucketNameIsMissing(): void
tests/Unit/Modules/Media/Infrastructure/S3MediaFileServiceErrorTest.php:62:    public function testThrowsWhenServerIsMissing(): void
tests/Unit/Modules/Media/Infrastructure/S3MediaFileServiceErrorTest.php:78:    public function testThrowsWhenCreateMultipartUploadHasNoUploadId(): void
tests/Unit/Modules/Media/Infrastructure/S3MediaFileServiceErrorTest.php:89:    public function testThrowsWhenHeadObjectHasNoContentLength(): void
tests/Unit/Modules/Media/Infrastructure/S3MediaFileServiceErrorTest.php:100:    public function testThrowsWhenObjectBodyIsNotStream(): void
tests/Unit/Modules/Media/Infrastructure/S3MediaFileServiceErrorTest.php:111:    public function testCompleteMultipartUploadTreatsNoSuchUploadAsSuccessWhenObjectExists(): void
tests/Unit/Modules/Media/Infrastructure/S3MediaFileServiceErrorTest.php:133:    public function testCompleteMultipartUploadRethrowsNoSuchUploadWhenObjectIsMissing(): void
tests/Unit/Modules/Media/Infrastructure/S3MediaFileServiceErrorTest.php:151:    public function testCompleteMultipartUploadRethrowsNonNoSuchUploadError(): void
tests/Unit/Modules/Media/Infrastructure/S3MediaFileServiceErrorTest.php:167:    public function testCreateMultipartUploadWrapsAwsError(): void
tests/Unit/Modules/Media/Infrastructure/S3MediaFileServiceErrorTest.php:178:    public function testPutObjectWrapsAwsError(): void
tests/Unit/Modules/Media/Infrastructure/S3MediaFileServiceErrorTest.php:189:    public function testDownloadToFileWrapsAwsError(): void
tests/Unit/Modules/Media/Infrastructure/S3MediaFileServiceErrorTest.php:200:    public function testDownloadToFileRemovesPartialFileWhenDownloadFails(): void
tests/Unit/Modules/Media/Infrastructure/S3MediaFileServiceErrorTest.php:226:    public function testUploadFromFileWrapsAwsError(): void
tests/Unit/Modules/Media/Infrastructure/S3MediaFileServiceErrorTest.php:237:    public function testCopyObjectWrapsAwsError(): void
tests/Unit/Modules/Media/Infrastructure/S3MediaFileServiceErrorTest.php:253:    public function testDeleteObjectRethrowsNonNotFoundError(): void
tests/Unit/Modules/Media/Infrastructure/S3MediaFileServiceErrorTest.php:264:    public function testAbortMultipartUploadRethrowsNonNotFoundError(): void
tests/Unit/Modules/Media/Infrastructure/S3MediaFileServiceErrorTest.php:279:    public function testHeadObjectRethrowsNonNotFoundError(): void
tests/Unit/Modules/Media/Infrastructure/S3MediaFileServiceErrorTest.php:291:    public function testClassifiesAwsErrorTransience(S3Exception $awsError, bool $expectedTransient): void
tests/Unit/Modules/Media/Infrastructure/S3MediaFileServiceErrorTest.php:308:    public static function awsErrorTransienceProvider(): array
tests/Unit/Modules/Media/Infrastructure/S3MediaFileServiceErrorTest.php:332:    public function testDeleteObjectIgnoresNotFound(): void
tests/Unit/Modules/Media/Infrastructure/S3MediaFileServiceErrorTest.php:343:    public function testAbortMultipartUploadIgnoresNoSuchUpload(): void
tests/Unit/Modules/Media/Infrastructure/S3MediaFileServiceErrorTest.php:358:    public function testObjectKeyOmitsPrefixWhenNotConfigured(): void
tests/Unit/Modules/Media/Infrastructure/S3MediaFileServiceErrorTest.php:377:    private function serviceWith(FakeS3Client $client): S3MediaFileService
tests/Unit/Modules/Media/Infrastructure/S3MediaFileServiceErrorTest.php:392:    private function servers(): array
tests/Unit/Modules/Media/Infrastructure/S3MediaFileServiceErrorTest.php:405:    private function path(): MediaPath
tests/Unit/Modules/Media/Infrastructure/S3MediaFileServiceErrorTest.php:412:    private function completedParts(): MediaMultipartPartCollection
tests/Unit/Modules/Media/Infrastructure/S3MediaFileServiceErrorTest.php:422:    private function mime(): MediaMimeType
tests/Unit/Modules/Media/Infrastructure/S3MediaFileServiceErrorTest.php:427:    private static function s3Exception(string $awsErrorCode): S3Exception
tests/Unit/Modules/Media/Domain/Enum/MediaEnumTest.php:19:    public function testMediaTypeValues(): void
tests/Unit/Modules/Media/Domain/Enum/MediaEnumTest.php:24:    public function testMediaStatusValues(): void
tests/Unit/Modules/Media/Domain/Enum/MediaEnumTest.php:30:                'multipartCompletionFailedCanRetry',
tests/Unit/Modules/Media/Domain/Enum/MediaEnumTest.php:31:                'multipartCompletionFailedNeedReupload',
tests/Unit/Modules/Media/Domain/Enum/MediaEnumTest.php:42:    public function testMediaVisibilityValues(): void
tests/Unit/Modules/Media/Domain/Enum/MediaEnumTest.php:44:        self::assertSame(['private', 'public'], $this->values(MediaVisibility::cases()));
tests/Unit/Modules/Media/Domain/Enum/MediaEnumTest.php:47:    public function testMediaStorageValues(): void
tests/Unit/Modules/Media/Domain/Enum/MediaEnumTest.php:49:        self::assertSame(['media-upload', 'media-private', 'media-public'], $this->values(MediaStorage::cases()));
tests/Unit/Modules/Media/Domain/Enum/MediaEnumTest.php:52:    public function testImageConversionTypeValues(): void
tests/Unit/Modules/Media/Domain/Enum/MediaEnumTest.php:57:    public function testVideoConversionTypeValues(): void
tests/Unit/Modules/Media/Domain/Enum/MediaEnumTest.php:62:    public function testAudioConversionTypeValues(): void
tests/Unit/Modules/Media/Domain/Enum/MediaEnumTest.php:67:    public function testConversionStatusValues(): void
tests/Unit/Modules/Media/Domain/Enum/MediaEnumTest.php:72:    private function values(array $cases): array
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:43:    private array $createdObjects = [];
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:53:    public function testCompleteThenRelayProcessesMediaToReady(): void
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:91:    public function testProcessingRecoversFromProcessingFailedToReady(): void
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:133:    public function testCompleteThenRelayProcessesVideoToReady(): void
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:173:    public function testCompleteThenRelayProcessesAudioToReady(): void
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:206:    public function testCompleteThenRelayProcessesDocumentToReady(): void
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:231:        // Реальный оригинал переложен по ready-пути documents/<shard>/<key>/source.pdf без конверсий.
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:236:    public function testProcessingFailureRecordsErrorAndFailsOutbox(): void
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:259:    private function uploadedOriginal(
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:287:    private function completeUpload(Media $media, MediaConversionPlan $plan): OutboxEventId
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:309:    private function relay(): int
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:317:    private function fileService(): MediaFileServiceContract
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:322:    private function jpegBytes(): string
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:333:    private function documentBytes(): string
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:335:        return "%PDF-1.4\nТестовое содержимое документа для flow-теста.\n%%EOF";
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:338:    private function videoBytes(): string
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:347:    private function audioBytes(): string
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:355:    private function ffmpegFixture(string $extension, array $inputArgs): string
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:369:    private function track(MediaStorage $storage, MediaPath $path): void
tests/Feature/Modules/Media/Flow/Fixture/RecordingMediaLogger.php:11: * постоянный -> ERROR) и контекста (например, applied presignedTtlSeconds в запросе загрузки).
tests/Feature/Modules/Media/Flow/Fixture/RecordingMediaLogger.php:18:    public array $records = [];
tests/Feature/Modules/Media/Flow/Fixture/RecordingMediaLogger.php:24:    public function log($level, \Stringable|string $message, array $context = []): void
tests/Feature/Modules/Media/Flow/Fixture/RecordingMediaLogger.php:33:    public function hasLevel(string $level): bool
tests/Feature/Modules/Media/Flow/Fixture/RecordingMediaLogger.php:49:    public function contextFor(string $message): array
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:34:    private array $createdObjects = [];
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:36:    public function testVideoProcessorTranscodesToMp4WithPoster(): void
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:92:    public function testVideoProcessorAddsSilentAacTrackForSilentInput(): void
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:128:    public function testAudioProcessorTranscodesAudioWithEmbeddedCover(): void
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:161:    public function testAudioProcessorTranscodesToM4aWithWaveform(): void
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:195:    public function testAudioProcessorAppliesProfileSampleRate(): void
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:221:    public function testVideoProcessorRejectsAudioSource(): void
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:247:    public function testAudioProcessorRejectsVideoSource(): void
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:268:    public function testAudioProcessorRejectsSourceWithoutAudioStream(): void
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:292:    public function testVideoProcessorClassifiesBrokenInputAsPermanentFailure(): void
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:321:    public function testVideoProcessorFailsWhenSourceMissing(): void
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:357:    private function videoSpec(): MediaVideoConversionSpec
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:368:    private function audioSpec(int $sampleRate = 44_100): MediaAudioConversionSpec
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:378:    private function putSource(MediaPath $path, string $bytes, string $mime): void
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:389:    private function generateVideoBytes(): string
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:398:    private function generateAudioBytes(int $sampleRate = 44_100): string
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:405:    private function generateSilentVideoBytes(): string
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:413:    private function generateAudioWithCoverBytes(): string
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:428:    private function generateFixture(string $extension, array $inputArgs): string
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:444:    private function probeStreamCodecs(MediaStorage $storage, MediaPath $path): array
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:467:    private function videoProcessor(): FfmpegMediaVideoProcessor
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:475:    private function audioProcessor(): FfmpegMediaAudioProcessor
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:483:    private function fileService(): MediaFileServiceContract
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:488:    private function mediaConfig(): MediaConfig
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:493:    private function track(MediaStorage $storage, MediaPath $path): void
tests/Unit/Modules/Media/Infrastructure/ImagickMediaImageProcessorTest.php:18:    public function testResizeProducesConversionWithExactDimensions(string $driver): void
tests/Unit/Modules/Media/Infrastructure/ImagickMediaImageProcessorTest.php:36:    public function testResizeCanConvertFormatToPng(string $driver): void
tests/Unit/Modules/Media/Infrastructure/ImagickMediaImageProcessorTest.php:50:    public function testUnsupportedDriverThrows(): void
tests/Unit/Modules/Media/Infrastructure/ImagickMediaImageProcessorTest.php:60:    public static function driverProvider(): array
tests/Unit/Modules/Media/Infrastructure/ImagickMediaImageProcessorTest.php:65:    private function processor(string $driver): ImagickMediaImageProcessor
tests/Unit/Modules/Media/Infrastructure/ImagickMediaImageProcessorTest.php:68:            stagingTtlSeconds: 86_400,
tests/Unit/Modules/Media/Infrastructure/ImagickMediaImageProcessorTest.php:69:            multipartThresholdBytes: 16_777_216,
tests/Unit/Modules/Media/Infrastructure/ImagickMediaImageProcessorTest.php:70:            multipartPartSizeBytes: 8_388_608,
tests/Unit/Modules/Media/Infrastructure/ImagickMediaImageProcessorTest.php:79:    private function jpegFixture(): string
tests/Unit/Modules/Media/Infrastructure/ImagickMediaImageProcessorTest.php:84:    private function pngFixture(): string
tests/Unit/Modules/Media/Infrastructure/ImagickMediaImageProcessorTest.php:89:    private function encodeFixture(callable $encode): string
tests/Unit/Modules/Media/Infrastructure/Fixture/FakeS3ClientProvider.php:13:    public function __construct(
tests/Unit/Modules/Media/Infrastructure/Fixture/FakeS3ClientProvider.php:14:        private S3Client $client,
tests/Unit/Modules/Media/Infrastructure/Fixture/FakeS3ClientProvider.php:18:    public function forServer(StorageServerConfig $serverConfig): S3Client
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:25:    private const string MIME = 'image/jpeg';
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:30:    private array $createdObjects = [];
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:32:    public function testPresignPutThenHeadObjectReportsContentLength(): void
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:57:    public function testMultipartUploadCreatePresignCompleteAssemblesObject(): void
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:69:        $presignedParts = $fileService->presignUploadParts(
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:77:        self::assertCount(1, $presignedParts);
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:78:        $presignedPart = $presignedParts->first();
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:79:        $partResponse = $this->httpClient()->put($presignedPart->url, ['body' => $bytes]);
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:88:                    partNumber: MediaMultipartPartNumber::fromInt($presignedPart->partNumber),
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:99:    public function testCopyObjectToPublicBucketIsAnonymouslyReadable(): void
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:103:        $publicPath = $this->imagesPath();
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:106:        $this->track(MediaStorage::Public, $publicPath);
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:118:            toPath: $publicPath,
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:121:        $publicUrl = $fileService->publicUrl(MediaStorage::Public, $publicPath);
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:122:        $publicResponse = $this->httpClient()->get($publicUrl);
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:124:        self::assertStringContainsString('media-public', $publicUrl);
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:125:        self::assertStringContainsString('test/', $publicUrl);
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:126:        self::assertSame(200, $publicResponse->getStatusCode());
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:127:        self::assertSame($bytes, (string) $publicResponse->getBody());
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:130:    public function testPresignGetReturnsWorkingUrlForPrivateBucket(): void
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:144:        $presignedUrl = $fileService->presignGet(MediaStorage::Private, $path, $this->expiresAt());
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:145:        $response = $this->httpClient()->get($presignedUrl);
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:151:    public function testDeleteObjectRemovesObjectAndIgnoresMissing(): void
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:173:    public function testGetObjectContentsReadsBackStoredBytes(): void
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:190:    public function testHeadObjectReturnsNullForMissingObject(): void
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:195:    public function testDownloadToFileAndUploadFromFileRoundTrip(): void
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:227:    public function testAbortMultipartUploadIsIdempotent(): void
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:245:    public function testCompleteMultipartUploadRethrowsOnInvalidPart(): void
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:289:    private function fileService(): S3MediaFileService
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:297:    private function httpClient(): Client
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:302:    private function uploadPath(): MediaPath
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:309:    private function imagesPath(): MediaPath
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:316:    private function expiresAt(): \DateTimeImmutable
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:321:    private function track(MediaStorage $storage, MediaPath $path): void
tests/Unit/Modules/Media/Infrastructure/FfmpegWaveformTest.php:19:    public function testBuildWaveformNormalizesSamplesPerInterval(): void
tests/Unit/Modules/Media/Infrastructure/FfmpegWaveformTest.php:29:    public function testBuildWaveformFillsEmptyIntervalsForShortBuffer(): void
tests/Unit/Modules/Media/Infrastructure/FfmpegWaveformTest.php:40:    public function testBuildWaveformTakesIntervalMaximum(): void
tests/Unit/Modules/Media/Infrastructure/FfmpegWaveformTest.php:50:    private function processor(): WaveformExposingAudioProcessor
tests/Unit/Modules/Media/Infrastructure/FfmpegWaveformTest.php:58:    private function mediaConfig(): MediaConfig
tests/Unit/Modules/Media/Infrastructure/FfmpegWaveformTest.php:61:            stagingTtlSeconds: 86_400,
tests/Unit/Modules/Media/Infrastructure/FfmpegWaveformTest.php:62:            multipartThresholdBytes: 16_777_216,
tests/Unit/Modules/Media/Infrastructure/FfmpegWaveformTest.php:63:            multipartPartSizeBytes: 8_388_608,
tests/Unit/Modules/Media/Infrastructure/Fixture/WaveformExposingAudioProcessor.php:16:    public function buildWaveformFrom(string $pcm, int $peaks): MediaWaveform
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:51:    public function testStoresAndRestoresMediaWithValueObjects(): void
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:69:    public function testStoresAndRestoresConversionsAndMultipartUpload(): void
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:75:        $multipartUpload = $this->createMultipartUpload($media);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:81:        $this->entityManager()->persist($multipartUpload);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:87:        $restoredMultipartUpload = $this->multipartUploadRepository()->findByMediaId($media->id);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:104:    public function testLazyGhostMapperRestoresRelationsAndKeepsThemAfterSave(): void
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:148:    public function testLazyGhostMapperSavesMediaWithoutReadingRelations(): void
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:179:    public function testLazyGhostMapperKeepsBelongsToRelationAfterReadingAndSavingConversion(): void
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:206:    public function testFindExpiredReturnsTypedCollection(): void
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:220:    public function testStorageKeyIsUnique(): void
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:232:    public function testCascadeDeletesRelatedRows(): void
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:238:        $multipartUpload = $this->createMultipartUpload($media);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:244:        $this->entityManager()->persist($multipartUpload);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:253:        self::assertNull($this->multipartUploadRepository()->findByMediaId($media->id));
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:256:    private function createMedia(
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:274:    private function createImageConversion(Media $media): MediaImageConversion
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:291:    private function createVideoConversion(Media $media): MediaVideoConversion
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:310:    private function createAudioConversion(Media $media): MediaAudioConversion
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:329:    private function createMultipartUpload(Media $media): MediaMultipartUpload
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:331:        $multipartUpload = MediaMultipartUpload::create(
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:338:        $multipartUpload->replaceParts(new MediaMultipartPartCollection([
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:345:        return $multipartUpload;
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:348:    private function entityManager(): EntityManagerInterface
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:353:    private function mediaRepository(): MediaRepository
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:358:    private function imageConversionRepository(): MediaImageConversionRepository
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:363:    private function videoConversionRepository(): MediaVideoConversionRepository
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:368:    private function audioConversionRepository(): MediaAudioConversionRepository
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:373:    private function multipartUploadRepository(): MediaMultipartUploadRepository
tests/Feature/Modules/Media/Flow/Fixture/ThrowingProcessMediaCommandBus.php:17:    public function __construct(
tests/Feature/Modules/Media/Flow/Fixture/ThrowingProcessMediaCommandBus.php:18:        private \Throwable $processingException,
tests/Feature/Modules/Media/Flow/Fixture/ThrowingProcessMediaCommandBus.php:21:    public function dispatch(object $command, callable $handler)
tests/Unit/Modules/Media/Infrastructure/Fixture/RecordingVideoProcessor.php:19:    private \Throwable|null $failure = null;
tests/Unit/Modules/Media/Infrastructure/Fixture/RecordingVideoProcessor.php:20:    private MediaVideoProcessingResult|null $result = null;
tests/Unit/Modules/Media/Infrastructure/Fixture/RecordingVideoProcessor.php:25:    public array $touchedFiles = [];
tests/Unit/Modules/Media/Infrastructure/Fixture/RecordingVideoProcessor.php:27:    public function failWith(\Throwable $failure): void
tests/Unit/Modules/Media/Infrastructure/Fixture/RecordingVideoProcessor.php:32:    public function succeedWith(MediaVideoProcessingResult $result): void
tests/Unit/Modules/Media/Infrastructure/Cycle/MediaTypecastTest.php:23:    public function testExpirationTypecastHandlesNullableDate(): void
tests/Unit/Modules/Media/Infrastructure/Cycle/MediaTypecastTest.php:32:    public function testExpirationTypecastConvertsMutableDateAndUncastsNull(): void
tests/Unit/Modules/Media/Infrastructure/Cycle/MediaTypecastTest.php:43:    public function testProcessingErrorTypecastHandlesNullableString(): void
tests/Unit/Modules/Media/Infrastructure/Cycle/MediaTypecastTest.php:54:    public function testMultipartPartCollectionTypecastRejectsNonArrayJson(): void
tests/Unit/Modules/Media/Infrastructure/Cycle/MediaTypecastTest.php:61:    public function testMultipartPartCollectionTypecastRejectsNonArrayElement(): void
tests/Unit/Modules/Media/Infrastructure/Cycle/MediaTypecastTest.php:68:    public function testMultipartPartCollectionTypecastRejectsInvalidPartShape(): void
tests/Unit/Modules/Media/Infrastructure/Cycle/MediaTypecastTest.php:75:    public function testMultipartPartCollectionTypecastHandlesJsonCollection(): void
tests/Unit/Modules/Media/Infrastructure/Cycle/MediaTypecastTest.php:88:    public function testMultipartPartCollectionTypecastStoresDomainCollection(): void
tests/Unit/Modules/Media/Infrastructure/Cycle/MediaTypecastTest.php:100:    public function testWaveformTypecastRoundTripsJson(): void
tests/Unit/Modules/Media/Infrastructure/Cycle/MediaTypecastTest.php:109:    public function testWaveformTypecastRejectsNonArrayJson(): void
tests/Unit/Modules/Media/Infrastructure/Cycle/MediaTypecastTest.php:116:    public function testWaveformTypecastRejectsNonIntElement(): void
tests/Unit/Modules/Media/Infrastructure/Cycle/MediaTypecastTest.php:123:    public function testWaveformTypecastRejectsPeakOutOfRange(): void
app/src/Modules/Media/README.md:25:| `GetMediaUrl(mediaId, presignedTtlSeconds, conversionType?)` | Query | `MediaUrlResult` (`conversionType` — union image/video/audio-enum или null; для public-медиа `presignedTtlSeconds` игнорируется и не валидируется — прямой URL без срока; срок применяется только для private) |
app/src/Modules/Media/README.md:38:- `MediaUploadSpec{ allowedMimeTypes: MediaMimeTypeCollection, maxSize: MediaFileSize, visibility, presignedTtl: MediaPresignedTtl }`
app/src/Modules/Media/README.md:39:  — политика загрузки от потребителя. Конверсий здесь нет. `presignedTtl` — срок жизни
app/src/Modules/Media/README.md:40:  presigned-ссылок загрузки (PUT/части), задаёт потребитель под контекст, а не общий конфиг.
app/src/Modules/Media/README.md:51:- `RequestMediaUploadResult` — `single`: `putUrl`; `multipart`: `uploadId` + коллекция
app/src/Modules/Media/README.md:52:  presigned-ссылок частей; всегда `mediaId`, `uploadMode`, `expiresAt`.
app/src/Modules/Media/README.md:59:1. RequestMediaUpload  -> presigned PUT (single) или multipart-ссылки; media = waitingUpload
app/src/Modules/Media/README.md:68:6. GetMediaUrl: public -> прямой URL (media-public, anonymous read); private -> presignGet
app/src/Modules/Media/README.md:69:   (TTL из presignedTtlSeconds запроса). Волна аудио — отдельным GetAudioWaveform (числа, не URL)
app/src/Modules/Media/README.md:72:`uploadMode` (single/multipart) выбирается по порогу `MediaConfig.multipartThresholdBytes`;
app/src/Modules/Media/README.md:73:размер части — `MediaConfig.multipartPartSizeBytes` (S3 требует ≥ 5 MiB на часть, кроме последней).
app/src/Modules/Media/README.md:99:  outbox происходят в одной транзакции. Компромисс: для multipart-ветки внешние S3-операции
app/src/Modules/Media/README.md:103:  рассинхрон БД↔S3 при таймауте), сборку multipart и HEAD стоит вынести за пределы транзакции
app/src/Modules/Media/README.md:118:- Оригинал И конверсии живут в одном бакете по `visibility` (`media-public`/`media-private`),
app/src/Modules/Media/README.md:119:  чтобы `GetMediaUrl` резолвил их единообразно и не было утечки private-медиа.
app/src/Modules/Media/README.md:147:- `app/config/media.php` + `MediaConfig` (`Shared/Infrastructure/Configuration/Media`): staging-TTL,
app/src/Modules/Media/README.md:148:  порог и размер части multipart, драйвер обработки изображений, ffmpeg-настройки
app/src/Modules/Media/README.md:151:  TTL presigned-ссылок в конфиге **нет** — его
app/src/Modules/Media/README.md:152:  задаёт потребитель под контекст: `MediaUploadSpec.presignedTtl` (загрузка) и
app/src/Modules/Media/README.md:153:  `GetMediaUrlQuery.presignedTtlSeconds` (скачивание). Ключи `MEDIA_*` — в `.env.sample` и
app/src/Modules/Media/README.md:158:- presigned/multipart и серверные S3-операции — `S3MediaFileService` поверх `Aws\S3\S3Client`
app/src/Modules/Media/README.md:195:- `media-public` должен иметь anonymous-read policy (в dev ставится `ensure-buckets.sh`; в
tests/Unit/Modules/Media/Infrastructure/Fixture/RecordingAudioProcessor.php:18:    private \Throwable|null $failure = null;
tests/Unit/Modules/Media/Infrastructure/Fixture/RecordingAudioProcessor.php:19:    private MediaAudioProcessingResult|null $result = null;
tests/Unit/Modules/Media/Infrastructure/Fixture/RecordingAudioProcessor.php:24:    public array $touchedFiles = [];
tests/Unit/Modules/Media/Infrastructure/Fixture/RecordingAudioProcessor.php:26:    public function failWith(\Throwable $failure): void
tests/Unit/Modules/Media/Infrastructure/Fixture/RecordingAudioProcessor.php:31:    public function succeedWith(MediaAudioProcessingResult $result): void
app/src/Modules/Media/Infrastructure/FileService/NativeAacAudioFormat.php:20:    public function __construct(
app/src/Modules/Media/Infrastructure/FileService/NativeAacAudioFormat.php:21:        private readonly int $sampleRate,
app/src/Modules/Media/Infrastructure/FileService/NativeAacAudioFormat.php:30:    public function getExtraParams(): array
app/src/Modules/Media/Infrastructure/FileService/NativeAacAudioFormat.php:39:    public function getAvailableAudioCodecs(): array
app/src/Modules/Media/Domain/Enum/MediaVisibility.php:9:    case Private = 'private';
app/src/Modules/Media/Domain/Enum/MediaVisibility.php:10:    case Public = 'public';
app/src/Modules/Media/Infrastructure/Cycle/MediaMultipartPartCollectionTypecast.php:13:    public static function castDatabaseValue(
app/src/Modules/Media/Infrastructure/Cycle/MediaMultipartPartCollectionTypecast.php:45:    public static function uncastValue(
tests/Unit/Modules/Media/Infrastructure/Fixture/FakeS3Client.php:21:    public function __construct(
tests/Unit/Modules/Media/Infrastructure/Fixture/FakeS3Client.php:22:        private readonly array $handlers,
tests/Unit/Modules/Media/Infrastructure/Fixture/FakeS3Client.php:41:    public function __call($name, array $args): Result
app/src/Modules/Media/Infrastructure/FileService/ImagickMediaImageProcessor.php:25:    private ImageManager $imageManager;
app/src/Modules/Media/Infrastructure/FileService/ImagickMediaImageProcessor.php:27:    public function __construct(MediaConfig $mediaConfig)
app/src/Modules/Media/Infrastructure/FileService/ImagickMediaImageProcessor.php:33:    public function resize(
app/src/Modules/Media/Infrastructure/FileService/ImagickMediaImageProcessor.php:54:    private function driver(string $driver): DriverInterface
app/src/Modules/Media/Repository/MediaMultipartUploadRepository.php:16:    public function findByMediaId(MediaId $mediaId): MediaMultipartUpload|null
tests/Unit/Modules/Media/Infrastructure/Fixture/FfmpegCommandRecordingProcessor.php:19:    public array $command = [];
tests/Unit/Modules/Media/Infrastructure/Fixture/FfmpegCommandRecordingProcessor.php:24:    public function runFfmpegWith(array $arguments): void
app/src/Modules/Media/Infrastructure/Cycle/MediaWaveformTypecast.php:12:    public static function castDatabaseValue(string $value): MediaWaveform
app/src/Modules/Media/Infrastructure/Cycle/MediaWaveformTypecast.php:36:    public static function uncastValue(MediaWaveform $value): string
app/src/Modules/Media/Domain/Enum/MediaStatus.php:11:    case MultipartCompletionFailedCanRetry = 'multipartCompletionFailedCanRetry';
app/src/Modules/Media/Domain/Enum/MediaStatus.php:12:    case MultipartCompletionFailedNeedReupload = 'multipartCompletionFailedNeedReupload';
app/src/Modules/Media/Infrastructure/FileService/ConfiguredS3ClientProvider.php:12:    private const string DEFAULT_REGION = 'us-east-1';
app/src/Modules/Media/Infrastructure/FileService/ConfiguredS3ClientProvider.php:13:    private const string DEFAULT_VERSION = 'latest';
app/src/Modules/Media/Infrastructure/FileService/ConfiguredS3ClientProvider.php:16:    public function forServer(StorageServerConfig $serverConfig): S3Client
app/src/Modules/Media/Repository/MediaRepository.php:18:    public function findById(MediaId $mediaId): Media|null
app/src/Modules/Media/Repository/MediaRepository.php:23:    public function findByStorageKey(MediaStorageKey $storageKey): Media|null
app/src/Modules/Media/Repository/MediaRepository.php:28:    public function findExpired(\DateTimeImmutable $now): MediaCollection
app/src/Modules/Media/Repository/MediaAudioConversionRepository.php:17:    public function findByMediaId(MediaId $mediaId): MediaAudioConversionCollection
app/src/Modules/Media/Infrastructure/Cycle/MediaExpirationTypecast.php:12:    public static function castDatabaseValue(
app/src/Modules/Media/Infrastructure/Cycle/MediaExpirationTypecast.php:30:    public static function uncastValue(
app/src/Modules/Media/Repository/MediaVideoConversionRepository.php:17:    public function findByMediaId(MediaId $mediaId): MediaVideoConversionCollection
tests/Feature/Modules/Media/Application/MakeMediaPermanentHandlerTest.php:17:    public function testMakesUploadedMediaPermanent(): void
tests/Feature/Modules/Media/Application/MakeMediaPermanentHandlerTest.php:33:    public function testRejectsMissingMedia(): void
tests/Feature/Modules/Media/Application/MakeMediaPermanentHandlerTest.php:43:    public function testRejectsForeignOwner(): void
tests/Feature/Modules/Media/Application/MakeMediaPermanentHandlerTest.php:57:    public function testRejectsMediaInInvalidStatus(): void
tests/Feature/Modules/Media/Application/MakeMediaPermanentHandlerTest.php:71:    private function handler(): MakeMediaPermanentHandler
app/src/Modules/Media/Domain/Entity/MediaMultipartUpload.php:24:    role: 'media_multipart_upload',
app/src/Modules/Media/Domain/Entity/MediaMultipartUpload.php:25:    table: 'media_multipart_uploads',
app/src/Modules/Media/Domain/Entity/MediaMultipartUpload.php:34:    public private(set) MediaMultipartUploadId $id;
app/src/Modules/Media/Domain/Entity/MediaMultipartUpload.php:37:    public private(set) MediaId $mediaId;
app/src/Modules/Media/Domain/Entity/MediaMultipartUpload.php:40:    public private(set) MediaMultipartUploadIdValue $uploadId;
app/src/Modules/Media/Domain/Entity/MediaMultipartUpload.php:43:    public private(set) MediaMultipartPartsCount $partsCount;
app/src/Modules/Media/Domain/Entity/MediaMultipartUpload.php:46:    public private(set) MediaMultipartPartSize $partSize;
app/src/Modules/Media/Domain/Entity/MediaMultipartUpload.php:49:    public private(set) MediaFileSize $fileSize;
app/src/Modules/Media/Domain/Entity/MediaMultipartUpload.php:52:    public private(set) MediaMultipartPartCollection $parts;
app/src/Modules/Media/Domain/Entity/MediaMultipartUpload.php:55:    public private(set) Media $media;
app/src/Modules/Media/Domain/Entity/MediaMultipartUpload.php:57:    public static function create(
app/src/Modules/Media/Domain/Entity/MediaMultipartUpload.php:64:        $multipartUpload = new self();
app/src/Modules/Media/Domain/Entity/MediaMultipartUpload.php:65:        $multipartUpload->id = MediaMultipartUploadId::generate();
app/src/Modules/Media/Domain/Entity/MediaMultipartUpload.php:66:        $multipartUpload->media = $media;
app/src/Modules/Media/Domain/Entity/MediaMultipartUpload.php:67:        $multipartUpload->mediaId = $media->id;
app/src/Modules/Media/Domain/Entity/MediaMultipartUpload.php:68:        $multipartUpload->uploadId = $uploadId;
app/src/Modules/Media/Domain/Entity/MediaMultipartUpload.php:69:        $multipartUpload->partsCount = $partsCount;
app/src/Modules/Media/Domain/Entity/MediaMultipartUpload.php:70:        $multipartUpload->partSize = $partSize;
app/src/Modules/Media/Domain/Entity/MediaMultipartUpload.php:71:        $multipartUpload->fileSize = $fileSize;
app/src/Modules/Media/Domain/Entity/MediaMultipartUpload.php:72:        $multipartUpload->parts = new MediaMultipartPartCollection();
app/src/Modules/Media/Domain/Entity/MediaMultipartUpload.php:73:        $multipartUpload->initializeTimestamps();
app/src/Modules/Media/Domain/Entity/MediaMultipartUpload.php:75:        return $multipartUpload;
app/src/Modules/Media/Domain/Entity/MediaMultipartUpload.php:78:    public function replaceParts(MediaMultipartPartCollection $parts): void
app/src/Modules/Media/Repository/MediaImageConversionRepository.php:17:    public function findByMediaId(MediaId $mediaId): MediaImageConversionCollection
app/src/Modules/Media/Infrastructure/Cycle/MediaProcessingErrorTypecast.php:12:    public static function castDatabaseValue(
app/src/Modules/Media/Infrastructure/Cycle/MediaProcessingErrorTypecast.php:22:    public static function uncastValue(
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:42:    public function testCompletesSingleUploadAndQueuesProcessing(): void
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:74:    public function testCompletesMultipartUpload(): void
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:78:        $multipartUpload = $this->multipartUploadFor($media);
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:79:        $this->persist($media, $multipartUpload);
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:95:    public function testCompletesVideoUploadWithVideoPlan(): void
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:119:    public function testCompletesAudioUploadWithAudioPlan(): void
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:143:    public function testRejectsCrossTypePlan(): void
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:159:    public function testRejectsDuplicateImageConversionTypes(): void
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:175:    public function testRejectsEmptyVideoPlanForVideoMedia(): void
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:191:    public function testRejectsMultipleVideoProfiles(): void
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:207:    public function testRejectsVideoBitrateOutOfRange(): void
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:223:    public function testRejectsAudioSampleRateOutOfRange(): void
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:239:    public function testRejectsAudioWaveformPeaksOutOfRange(): void
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:255:    public function testRejectsForeignListForVideoMedia(): void
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:271:    public function testRejectsVideoDimensionOutOfRange(): void
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:287:    public function testRejectsForeignListForAudioMedia(): void
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:303:    public function testRejectsEmptyAudioPlanForAudioMedia(): void
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:319:    public function testRejectsMultipleAudioProfiles(): void
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:335:    public function testRejectsAudioBitrateOutOfRange(): void
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:351:    public function testCompletesDocumentUploadWithEmptyPlan(): void
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:380:    public function testRejectsNonEmptyPlanForDocument(MediaConversionPlan $plan): void
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:400:    public static function nonEmptyDocumentPlanProvider(): array
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:432:    public function testRejectsMissingMedia(): void
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:444:    public function testRejectsForeignOwner(): void
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:459:    public function testRejectsMediaNotWaitingUpload(): void
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:477:    public function testRejectsConversionDimensionsOutOfDomainRange(int $width, int $height): void
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:496:    public static function outOfRangeConversionDimensionProvider(): array
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:506:    public function testRejectsMultipartWithoutUploadRecord(): void
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:522:    public function testRejectsWhenUploadedObjectMissing(): void
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:541:    public function testRejectsWhenUploadedObjectSizeMismatches(): void
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:557:    private function handler(
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:563:            mediaMultipartUploadRepository: $this->multipartUploadRepository(),
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:571:    private function fileServiceWithHead(int $contentLength): MediaFileServiceContract
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:581:    private function outboxStore(): OutboxEventStoreContract
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:589:    private function multipartUploadFor(Media $media): MediaMultipartUpload
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:600:    private function parts(): MediaMultipartPartCollection
app/src/Modules/Media/Domain/Enum/MediaStorage.php:10:    case Private = 'media-private';
app/src/Modules/Media/Domain/Enum/MediaStorage.php:11:    case Public = 'media-public';
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
app/src/Modules/Media/Domain/Entity/Media.php:177:     * ready-медиа — no-op (симметрично guard'у в markReadyMovedTo). Защищает инвариант
app/src/Modules/Media/Domain/Entity/Media.php:181:    public function recordTemporaryProcessingError(MediaProcessingError $processingError): void
app/src/Modules/Media/Domain/Entity/Media.php:195:     * ready-медиа фиксация постоянной ошибки также no-op.
app/src/Modules/Media/Domain/Entity/Media.php:197:    public function recordPermanentProcessingError(MediaProcessingError $processingError): void
app/src/Modules/Media/Domain/Entity/Media.php:209:    public function markReady(): void
app/src/Modules/Media/Domain/Entity/Media.php:218:     * из staging). Идемпотентен: повторная доставка на ready — no-op. Допустим из uploaded,
app/src/Modules/Media/Domain/Entity/Media.php:221:    public function markReadyMovedTo(MediaStorage $storage, MediaPath $path): void
app/src/Modules/Media/Domain/Entity/Media.php:244:    public function isReady(): bool
app/src/Modules/Media/Domain/Entity/Media.php:249:    public function markReadyOriginalRemoved(): void
app/src/Modules/Media/Domain/Entity/Media.php:256:    public function makePermanent(): void
app/src/Modules/Media/Application/Exception/MediaFileServiceFailedException.php:18:    private function __construct(
app/src/Modules/Media/Application/Exception/MediaFileServiceFailedException.php:20:        private readonly bool $transient,
app/src/Modules/Media/Application/Exception/MediaFileServiceFailedException.php:29:    public static function transient(string $message, \Throwable $previous): self
app/src/Modules/Media/Application/Exception/MediaFileServiceFailedException.php:37:    public static function permanent(string $message, \Throwable|null $previous = null): self
app/src/Modules/Media/Application/Exception/MediaFileServiceFailedException.php:42:    public function isTransient(): bool
tests/Feature/Modules/Media/Application/GetAudioWaveformHandlerTest.php:28:    public function testReturnsWaveformForReadyAudioMedia(): void
tests/Feature/Modules/Media/Application/GetAudioWaveformHandlerTest.php:38:    public function testRejectsNonReadyMedia(): void
tests/Feature/Modules/Media/Application/GetAudioWaveformHandlerTest.php:48:    public function testRejectsNonAudioMedia(): void
tests/Feature/Modules/Media/Application/GetAudioWaveformHandlerTest.php:63:    public function testRejectsMissingConversion(): void
tests/Feature/Modules/Media/Application/GetAudioWaveformHandlerTest.php:73:    public function testRejectsMissingMedia(): void
tests/Feature/Modules/Media/Application/GetAudioWaveformHandlerTest.php:80:    private function handler(): GetAudioWaveformHandler
tests/Feature/Modules/Media/Application/GetAudioWaveformHandlerTest.php:88:    private function readyAudioMedia(): Media
tests/Feature/Modules/Media/Application/GetAudioWaveformHandlerTest.php:106:    private function audioConversion(Media $media): MediaAudioConversion
app/src/Modules/Media/Infrastructure/FileService/AbstractFfmpegMediaProcessor.php:11:use FFMpeg\FFProbe\DataMapping\AbstractData;
app/src/Modules/Media/Infrastructure/FileService/AbstractFfmpegMediaProcessor.php:25:    public function __construct(
app/src/Modules/Media/Infrastructure/FileService/AbstractFfmpegMediaProcessor.php:122:    private static function isTransientFailure(\Throwable $exception): bool
app/src/Modules/Media/Application/Exception/MediaProcessorFailedException.php:19:    private function __construct(
app/src/Modules/Media/Application/Exception/MediaProcessorFailedException.php:21:        private readonly bool $transient,
app/src/Modules/Media/Application/Exception/MediaProcessorFailedException.php:30:    public static function transient(string $message, \Throwable $previous): self
app/src/Modules/Media/Application/Exception/MediaProcessorFailedException.php:38:    public static function permanent(string $message, \Throwable|null $previous = null): self
app/src/Modules/Media/Application/Exception/MediaProcessorFailedException.php:43:    public function isTransient(): bool
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
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:34:    public function testReturnsDirectPublicUrlForPublicMedia(): void
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:40:        $fileService->method('publicUrl')->willReturn('http://minio/media-public/object');
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:45:            presignedTtlSeconds: 300,
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:48:        // Для public TTL не применяется и VO не строится: прямой URL без срока.
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:49:        self::assertSame('http://minio/media-public/object', $result->url);
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:53:    public function testIgnoresInvalidTtlForPublicMedia(): void
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:55:        // Для public-медиа срок не применяется и VO не строится, поэтому заведомо
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:61:        $fileService->method('publicUrl')->willReturn('http://minio/media-public/object');
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:66:            presignedTtlSeconds: 0,
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:69:        self::assertSame('http://minio/media-public/object', $result->url);
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:73:    public function testReturnsPresignedUrlForPrivateMedia(): void
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:94:            presignedTtlSeconds: 300,
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:100:        // TTL presigned GET берётся из запроса (300), а не из конфиг-дефолта 900.
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:109:    public function testReturnsUrlForRequestedConversion(): void
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:133:            presignedTtlSeconds: 450,
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:140:        // Конверсия private отдаёт presigned URL с тем же сроком из запроса (450).
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:149:    public function testReturnsUrlForVideoConversion(): void
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:157:        $fileService->method('publicUrl')->willReturnCallback(
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:161:                return 'http://minio/media-public/normalized.mp4';
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:167:            presignedTtlSeconds: 300,
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:171:        self::assertSame('http://minio/media-public/normalized.mp4', $result->url);
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:176:    public function testReturnsUrlForAudioConversion(): void
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:184:        $fileService->method('publicUrl')->willReturnCallback(
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:188:                return 'http://minio/media-public/normalized.m4a';
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:194:            presignedTtlSeconds: 300,
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:198:        self::assertSame('http://minio/media-public/normalized.m4a', $result->url);
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:202:    public function testReturnsUrlForReadyDocument(): void
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:205:        // public прямым URL, private через presignGet.
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:206:        $publicDocument = $this->readyDocumentMedia(MediaVisibility::Public);
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:207:        $this->persist($publicDocument);
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:209:        $publicFileService = $this->createStub(MediaFileServiceContract::class);
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:210:        $publicFileService->method('publicUrl')->willReturn('http://minio/media-public/document.pdf');
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:212:        $publicResult = $this->handler($publicFileService)->handle(new GetMediaUrlQuery(
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:213:            mediaId: $publicDocument->id->value(),
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:214:            presignedTtlSeconds: 300,
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:216:        self::assertSame('http://minio/media-public/document.pdf', $publicResult->url);
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:217:        self::assertNull($publicResult->expiresAt);
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:219:        $privateDocument = $this->readyDocumentMedia(MediaVisibility::Private);
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:220:        $this->persist($privateDocument);
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:222:        $privateFileService = $this->createStub(MediaFileServiceContract::class);
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:223:        $privateFileService->method('presignGet')->willReturn('http://minio/signed-document');
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:225:        $privateResult = $this->handler($privateFileService)->handle(new GetMediaUrlQuery(
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:226:            mediaId: $privateDocument->id->value(),
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:227:            presignedTtlSeconds: 300,
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:229:        self::assertSame('http://minio/signed-document', $privateResult->url);
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:230:        self::assertNotNull($privateResult->expiresAt);
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:233:    public function testRejectsMissingConversion(): void
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:242:            presignedTtlSeconds: 300,
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:247:    public function testRejectsMediaThatIsNotReady(): void
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:255:            ->handle(new GetMediaUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 300));
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:258:    public function testRejectsMissingMedia(): void
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:263:            ->handle(new GetMediaUrlQuery(mediaId: UserId::generate()->value(), presignedTtlSeconds: 300));
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:266:    private function handler(MediaFileServiceContract $fileService): GetMediaUrlHandler
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:277:    private function readyMedia(MediaVisibility $visibility): Media
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:290:    private function readyDocumentMedia(MediaVisibility $visibility): Media
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:309:    private function videoConversion(Media $media): MediaVideoConversion
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:330:    private function audioConversion(Media $media): MediaAudioConversion
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:351:    private function thumbnailConversion(Media $media): MediaImageConversion
app/src/Modules/Media/Application/Message/MediaUploaded.php:18:    public function __construct(
app/src/Modules/Media/Application/Message/MediaUploaded.php:19:        public string $mediaId,
app/src/Modules/Media/Application/Message/MediaUploaded.php:20:        public MediaConversionPlan $plan,
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
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:19:    public function testReturnsNullForMissingMedia(): void
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:22:            new FindMediaUrlQuery(mediaId: UserId::generate()->value(), presignedTtlSeconds: 300),
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:28:    public function testReturnsNullForMediaThatIsNotReady(): void
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:34:            new FindMediaUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 300),
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:40:    public function testReturnsDirectUrlForReadyPublicMedia(): void
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:46:        $fileService->method('publicUrl')->willReturn('http://minio/media-public/object');
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:50:            new FindMediaUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 300),
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:54:        self::assertSame('http://minio/media-public/object', $result->url);
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:58:    public function testReturnsPresignedUrlForReadyPrivateMedia(): void
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:67:            new FindMediaUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 300),
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:75:    private function handler(MediaFileServiceContract $fileService): FindMediaUrlHandler
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:83:    private function readyMedia(MediaVisibility $visibility): Media
app/src/Modules/Media/Application/Contract/MediaImageProcessorContract.php:19:    public function resize(
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
app/src/Modules/Media/Application/Contract/MediaVideoProcessorContract.php:22:    public function process(
app/src/Modules/Media/Application/Dto/MediaVideoProcessingResult.php:21:    public function __construct(
app/src/Modules/Media/Application/Dto/MediaVideoProcessingResult.php:22:        public MediaMimeType $normalizedMimeType,
app/src/Modules/Media/Application/Dto/MediaVideoProcessingResult.php:23:        public MediaFileSize $normalizedSize,
app/src/Modules/Media/Application/Dto/MediaVideoProcessingResult.php:24:        public MediaPixelDimension $width,
app/src/Modules/Media/Application/Dto/MediaVideoProcessingResult.php:25:        public MediaPixelDimension $height,
app/src/Modules/Media/Application/Dto/MediaVideoProcessingResult.php:26:        public MediaDuration $duration,
app/src/Modules/Media/Application/Dto/MediaVideoProcessingResult.php:27:        public MediaBitrate $bitrate,
app/src/Modules/Media/Application/Dto/MediaVideoProcessingResult.php:28:        public MediaMimeType $posterMimeType,
app/src/Modules/Media/Application/Dto/MediaVideoProcessingResult.php:29:        public MediaFileSize $posterSize,
app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaAudioProcessor.php:20:use FFMpeg\FFProbe\DataMapping\Stream;
app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaAudioProcessor.php:30:    private const string NORMALIZED_MIME = 'audio/mp4';
app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaAudioProcessor.php:31:    private const string FAILURE_MESSAGE = 'Не удалось обработать аудио.';
app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaAudioProcessor.php:32:    private const int WAVEFORM_SAMPLE_RATE = 8000;
app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaAudioProcessor.php:34:    private const int PCM_FULL_SCALE = 32767;
app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaAudioProcessor.php:36:    private const int PCM_SIGNED_OFFSET = 65536;
app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaAudioProcessor.php:38:    private const int PCM_SIGNED_THRESHOLD = 32768;
app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaAudioProcessor.php:39:    private const int WAVEFORM_PEAK_MAX = 255;
app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaAudioProcessor.php:42:    public function process(
app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaAudioProcessor.php:68:    private function encode(
app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaAudioProcessor.php:126:    private static function assertSourceIsAudio(FFProbe $probe, string $sourceFile): void
app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaAudioProcessor.php:147:    private static function isAttachedPicture(Stream $videoStream): bool
app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaAudioProcessor.php:159:    private function extractWaveform(string $sourceFile, int $peaks): MediaWaveform
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php:36:    public function __construct(
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php:37:        private MediaRepository $mediaRepository,
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php:38:        private MediaMultipartUploadRepository $mediaMultipartUploadRepository,
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php:39:        private MediaFileServiceContract $mediaFileService,
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php:40:        private OutboxEventStoreContract $outboxEventStore,
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php:41:        private EntityManagerInterface $entityManager,
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php:42:        private LoggerInterface $logger,
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php:47:    public function handle(CompleteMediaUploadCommand $command): MediaResult
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php:87:    private function completeMultipartUpload(Media $media, MediaMultipartPartCollection $parts): void
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php:89:        $multipartUpload = $this->mediaMultipartUploadRepository->findByMediaId($media->id)
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php:90:            ?? throw new ValidationException('app.media.multipart_upload_not_found');
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php:92:        $multipartUpload->replaceParts($parts);
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php:93:        $this->entityManager->persist($multipartUpload);
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php:97:            uploadId: $multipartUpload->uploadId,
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php:102:    private function assertObjectUploaded(Media $media): void
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php:117:    private function assertPlanValid(MediaConversionPlan $plan, MediaType $type): void
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php:127:    private function assertDocumentPlan(MediaConversionPlan $plan): void
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php:134:    private function assertImagePlan(MediaConversionPlan $plan): void
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php:154:    private function assertVideoPlan(MediaConversionPlan $plan): void
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php:176:    private function assertAudioPlan(MediaConversionPlan $plan): void
app/src/Modules/Media/Application/Contract/MediaAudioProcessorContract.php:22:    public function process(
app/src/Modules/Media/Application/Query/CheckMediaIsImage/CheckMediaIsImageHandler.php:13:    public function __construct(
app/src/Modules/Media/Application/Query/CheckMediaIsImage/CheckMediaIsImageHandler.php:14:        private MediaRepository $mediaRepository,
app/src/Modules/Media/Application/Query/CheckMediaIsImage/CheckMediaIsImageHandler.php:17:    public function handle(CheckMediaIsImageQuery $query): bool
app/src/Modules/Media/Domain/Collection/MediaMimeTypeCollection.php:15:    public function containsMimeType(MediaMimeType $mimeType): bool
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:37:    public function testProcessesImageMediaWithConversions(): void
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:60:    public function testStoresConversionDimensionsFromProcessorResult(): void
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:79:    public function testProcessesMediaWithoutConversionsMovesOriginalOnly(): void
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:99:    public function testProcessesVideoMediaIntoNormalizedAndPoster(): void
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:138:    public function testProcessesAudioMediaIntoNormalizedWithWaveform(): void
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:170:    public function testRerunsVideoAfterProcessingFailed(): void
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:200:    public function testRerunsAudioAfterProcessingFailed(): void
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:232:    public function testIsNoOpWhenMediaAlreadyReady(): void
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:253:    public function testProcessesDocumentByMovingOriginalWithoutConversions(): void
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:282:    public function testRejectsMissingMedia(): void
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:292:    public function testRejectsMediaInWaitingUploadStatus(): void
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:305:    private function handler(
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:332:    private function uploadedMedia(MediaVisibility $visibility): Media
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:340:    private function uploadedVideoMedia(): Media
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:354:    private function uploadedAudioMedia(): Media
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadCommand.php:12:    public function __construct(
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadCommand.php:13:        public string $userId,
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadCommand.php:14:        public string $mediaId,
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadCommand.php:15:        public MediaConversionPlan $plan,
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadCommand.php:16:        public MediaMultipartPartCollection|null $parts,
app/src/Modules/Media/Application/Query/CheckMediaIsImage/CheckMediaIsImageQuery.php:9:    public function __construct(
app/src/Modules/Media/Application/Query/CheckMediaIsImage/CheckMediaIsImageQuery.php:10:        public string $mediaId,
app/src/Modules/Media/Application/Contract/MediaFileServiceContract.php:20: * а не из значения enum напрямую. Срок действия presigned-ссылок задаёт потребитель — через
app/src/Modules/Media/Application/Contract/MediaFileServiceContract.php:28:    public function presignPut(
app/src/Modules/Media/Application/Contract/MediaFileServiceContract.php:36:     * Инициирует multipart-загрузку и возвращает её uploadId.
app/src/Modules/Media/Application/Contract/MediaFileServiceContract.php:38:    public function createMultipartUpload(
app/src/Modules/Media/Application/Contract/MediaFileServiceContract.php:47:    public function presignUploadParts(
app/src/Modules/Media/Application/Contract/MediaFileServiceContract.php:56:     * Собирает multipart-объект из загруженных частей.
app/src/Modules/Media/Application/Contract/MediaFileServiceContract.php:61:    public function completeMultipartUpload(
app/src/Modules/Media/Application/Contract/MediaFileServiceContract.php:69:     * Отменяет незавершённую multipart-загрузку. 404/NoSuchUpload игнорируется.
app/src/Modules/Media/Application/Contract/MediaFileServiceContract.php:71:    public function abortMultipartUpload(
app/src/Modules/Media/Application/Contract/MediaFileServiceContract.php:80:    public function headObject(MediaStorage $storage, MediaPath $path): MediaObjectHead|null;
app/src/Modules/Media/Application/Contract/MediaFileServiceContract.php:85:    public function getObjectContents(MediaStorage $storage, MediaPath $path): string;
app/src/Modules/Media/Application/Contract/MediaFileServiceContract.php:92:    public function downloadToFile(MediaStorage $storage, MediaPath $path): string;
app/src/Modules/Media/Application/Contract/MediaFileServiceContract.php:98:    public function uploadFromFile(
app/src/Modules/Media/Application/Contract/MediaFileServiceContract.php:108:    public function putObject(
app/src/Modules/Media/Application/Contract/MediaFileServiceContract.php:118:    public function copyObject(
app/src/Modules/Media/Application/Contract/MediaFileServiceContract.php:128:    public function deleteObject(MediaStorage $storage, MediaPath $path): void;
app/src/Modules/Media/Application/Contract/MediaFileServiceContract.php:131:     * Presigned GET-ссылка с TTL (для private-медиа).
app/src/Modules/Media/Application/Contract/MediaFileServiceContract.php:133:    public function presignGet(MediaStorage $storage, MediaPath $path, \DateTimeImmutable $expiresAt): string;
app/src/Modules/Media/Application/Contract/MediaFileServiceContract.php:136:     * Прямой публичный URL (для public-медиа, бакет с anonymous-read policy).
app/src/Modules/Media/Application/Contract/MediaFileServiceContract.php:138:    public function publicUrl(MediaStorage $storage, MediaPath $path): string;
tests/Feature/Modules/Media/Application/MediaApplicationTestCase.php:132:    protected function multipartUploadRepository(): MediaMultipartUploadRepository
app/src/Modules/Media/Domain/Collection/MediaMultipartPartCollection.php:19:    public function __construct(iterable $parts = [])
app/src/Modules/Media/Domain/Collection/MediaMultipartPartCollection.php:27:    public function jsonSerialize(): array
app/src/Modules/Media/Domain/Collection/MediaMultipartPartCollection.php:40:    private function normalizeParts(iterable $parts): array
app/src/Modules/Media/Application/Dto/MediaUploadSpec.php:18:    public function __construct(
app/src/Modules/Media/Application/Dto/MediaUploadSpec.php:19:        public MediaMimeTypeCollection $allowedMimeTypes,
app/src/Modules/Media/Application/Dto/MediaUploadSpec.php:20:        public MediaFileSize $maxSize,
app/src/Modules/Media/Application/Dto/MediaUploadSpec.php:21:        public MediaVisibility $visibility,
app/src/Modules/Media/Application/Dto/MediaUploadSpec.php:22:        public MediaPresignedTtl $presignedTtl,
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:33:    private const array TRANSIENT_AWS_ERROR_CODES = [
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:41:    private const string STORAGE_FAILURE_MESSAGE = 'Ошибка хранилища при обработке медиа.';
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:43:    public function __construct(
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:44:        private StorageConfig $storageConfig,
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:45:        private S3ClientProvider $clientProvider,
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:49:    public function presignPut(
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:66:    public function createMultipartUpload(
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:82:            throw MediaFileServiceFailedException::permanent('S3 не вернул UploadId при инициации multipart-загрузки.');
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
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:337:    private function bucketConfig(MediaStorage $storage): StorageBucketConfig
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:343:    private function bucketName(MediaStorage $storage): string
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:349:    private function objectKey(MediaStorage $storage, MediaPath $path): string
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:360:    private function client(MediaStorage $storage): S3Client
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:369:    private function isNotFound(S3Exception $exception): bool
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:384:    private function awsFailure(AwsException $exception): MediaFileServiceFailedException
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:391:    private function isTransientAwsException(AwsException $exception): bool
app/src/Modules/Media/Application/Dto/MediaResult.php:16:    public function __construct(
app/src/Modules/Media/Application/Dto/MediaResult.php:17:        public string $mediaId,
app/src/Modules/Media/Application/Dto/MediaResult.php:18:        public MediaStatus $status,
app/src/Modules/Media/Application/Dto/MediaResult.php:19:        public MediaVisibility $visibility,
app/src/Modules/Media/Application/Dto/MediaResult.php:22:    public static function fromEntity(Media $media): self
tests/Feature/Modules/Media/Application/RecordMediaProcessingFailureHandlerTest.php:19:    public function testRecordsProcessingFailure(): void
tests/Feature/Modules/Media/Application/RecordMediaProcessingFailureHandlerTest.php:36:    public function testKeepsReadyMediaIntact(): void
tests/Feature/Modules/Media/Application/RecordMediaProcessingFailureHandlerTest.php:39:        // на уже ready-медиа — no-op (инвариант «ready без ошибки»).
tests/Feature/Modules/Media/Application/RecordMediaProcessingFailureHandlerTest.php:59:    public function testRejectsMissingMedia(): void
tests/Feature/Modules/Media/Application/RecordMediaProcessingFailureHandlerTest.php:70:    private function handler(): RecordMediaProcessingFailureHandler
app/src/Modules/Media/Infrastructure/Bootloader/MediaBootloader.php:32:    public function boot(OutboxJobRegistryContract $outboxJobRegistry): void
app/src/Modules/Media/Application/Dto/MediaPresignedPart.php:8: * Одна presigned-ссылка части multipart-загрузки. Именованный DTO вместо array-shape.
app/src/Modules/Media/Application/Dto/MediaPresignedPart.php:12:    public function __construct(
app/src/Modules/Media/Application/Dto/MediaPresignedPart.php:13:        public int $partNumber,
app/src/Modules/Media/Application/Dto/MediaPresignedPart.php:14:        public string $url,
app/src/Modules/Media/Application/Dto/MediaAudioConversionSpec.php:17:    public function __construct(
app/src/Modules/Media/Application/Dto/MediaAudioConversionSpec.php:18:        public MediaAudioConversionType $type,
app/src/Modules/Media/Application/Dto/MediaAudioConversionSpec.php:19:        public int $bitrate,
app/src/Modules/Media/Application/Dto/MediaAudioConversionSpec.php:20:        public int $sampleRate,
app/src/Modules/Media/Application/Dto/MediaAudioConversionSpec.php:21:        public int $waveformPeaks,
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadCommand.php:12:    public function __construct(
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadCommand.php:13:        public string $userId,
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadCommand.php:14:        public MediaUploadSpec $spec,
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadCommand.php:15:        public MediaFileMeta $fileMeta,
app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaVideoProcessor.php:31:    private const string NORMALIZED_MIME = 'video/mp4';
app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaVideoProcessor.php:32:    private const string POSTER_MIME = 'image/jpeg';
app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaVideoProcessor.php:33:    private const string FAILURE_MESSAGE = 'Не удалось обработать видео.';
app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaVideoProcessor.php:35:    private const string SILENT_AUDIO_SOURCE = 'anullsrc=channel_layout=stereo:sample_rate=44100';
app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaVideoProcessor.php:38:    public function process(
app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaVideoProcessor.php:78:    private function encode(
app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaVideoProcessor.php:153:    private function extractPoster(string $normalizedFile, string $posterFile): void
app/src/Modules/Media/Application/Dto/MediaVideoConversionSpec.php:17:    public function __construct(
app/src/Modules/Media/Application/Dto/MediaVideoConversionSpec.php:18:        public MediaVideoConversionType $type,
app/src/Modules/Media/Application/Dto/MediaVideoConversionSpec.php:19:        public int $width,
app/src/Modules/Media/Application/Dto/MediaVideoConversionSpec.php:20:        public int $height,
app/src/Modules/Media/Application/Dto/MediaVideoConversionSpec.php:21:        public int $videoBitrate,
app/src/Modules/Media/Application/Dto/MediaVideoConversionSpec.php:22:        public int $audioBitrate,
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php:29:    public function __construct(
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php:30:        private MediaFileServiceContract $mediaFileService,
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php:31:        private MediaTypeResolver $mediaTypeResolver,
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php:32:        private MediaConfig $mediaConfig,
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php:33:        private EntityManagerInterface $entityManager,
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php:34:        private LoggerInterface $logger,
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php:38:    public function handle(RequestMediaUploadCommand $command): RequestMediaUploadResult
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php:52:            expiration: MediaExpiration::temporaryUntil($this->expiresIn($this->mediaConfig->stagingTtlSeconds)),
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php:55:        $presignedExpiresAt = $this->expiresIn($command->spec->presignedTtl->value());
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php:57:            ? $this->prepareMultipartUpload(media: $media, presignedExpiresAt: $presignedExpiresAt)
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php:58:            : $this->prepareSingleUpload(media: $media, presignedExpiresAt: $presignedExpiresAt);
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php:68:            'presignedTtlSeconds' => $command->spec->presignedTtl->value(),
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php:74:    private function prepareSingleUpload(Media $media, \DateTimeImmutable $presignedExpiresAt): RequestMediaUploadResult
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php:80:            expiresAt: $presignedExpiresAt,
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php:87:            expiresAt: $presignedExpiresAt,
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php:91:    private function prepareMultipartUpload(Media $media, \DateTimeImmutable $presignedExpiresAt): RequestMediaUploadResult
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php:93:        $partSize = MediaMultipartPartSize::fromInt($this->mediaConfig->multipartPartSizeBytes);
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php:105:            expiresAt: $presignedExpiresAt,
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php:117:        return RequestMediaUploadResult::multipart(
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php:121:            expiresAt: $presignedExpiresAt,
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php:125:    private function assertUploadAllowed(MediaUploadSpec $spec, MediaFileMeta $fileMeta): void
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php:139:    private function extension(MediaFileMeta $fileMeta): string
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php:150:    private function isMultipart(MediaFileSize $size): bool
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php:152:        return $size->value() >= $this->mediaConfig->multipartThresholdBytes;
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php:155:    private function partsCount(MediaFileSize $size, MediaMultipartPartSize $partSize): int
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php:160:    private function expiresIn(int $seconds): \DateTimeImmutable
app/src/Modules/Media/Infrastructure/FileService/S3ClientProvider.php:17:    public function forServer(StorageServerConfig $serverConfig): S3Client;
app/src/Modules/Media/Application/Query/GetAudioWaveform/GetAudioWaveformHandler.php:20:    public function __construct(
app/src/Modules/Media/Application/Query/GetAudioWaveform/GetAudioWaveformHandler.php:21:        private MediaRepository $mediaRepository,
app/src/Modules/Media/Application/Query/GetAudioWaveform/GetAudioWaveformHandler.php:22:        private MediaAudioConversionRepository $mediaAudioConversionRepository,
app/src/Modules/Media/Application/Query/GetAudioWaveform/GetAudioWaveformHandler.php:25:    public function handle(GetAudioWaveformQuery $query): MediaWaveform
app/src/Modules/Media/Domain/ValueObject/MediaProcessingError.php:11:    private const int MAX_LENGTH = 2000;
app/src/Modules/Media/Domain/ValueObject/MediaProcessingError.php:13:    private function __construct(
app/src/Modules/Media/Domain/ValueObject/MediaProcessingError.php:14:        private string $value,
app/src/Modules/Media/Domain/ValueObject/MediaProcessingError.php:17:    public static function none(): self
app/src/Modules/Media/Domain/ValueObject/MediaProcessingError.php:22:    public static function fromString(string $value): self
app/src/Modules/Media/Domain/ValueObject/MediaProcessingError.php:43:    public function value(): string|null
app/src/Modules/Media/Domain/ValueObject/MediaProcessingError.php:48:    public function isEmpty(): bool
app/src/Modules/Media/Domain/ValueObject/MediaProcessingError.php:53:    public function equals(self $other): bool
app/src/Modules/Media/Domain/ValueObject/MediaProcessingError.php:59:    public function __toString(): string
app/src/Modules/Media/Domain/ValueObject/MediaProcessingError.php:65:    public function jsonSerialize(): string
app/src/Modules/Media/Application/Dto/MediaAudioProcessingResult.php:21:    public function __construct(
app/src/Modules/Media/Application/Dto/MediaAudioProcessingResult.php:22:        public MediaMimeType $normalizedMimeType,
app/src/Modules/Media/Application/Dto/MediaAudioProcessingResult.php:23:        public MediaFileSize $normalizedSize,
app/src/Modules/Media/Application/Dto/MediaAudioProcessingResult.php:24:        public MediaDuration $duration,
app/src/Modules/Media/Application/Dto/MediaAudioProcessingResult.php:25:        public MediaBitrate $bitrate,
app/src/Modules/Media/Application/Dto/MediaAudioProcessingResult.php:26:        public MediaSampleRate $sampleRate,
app/src/Modules/Media/Application/Dto/MediaAudioProcessingResult.php:27:        public MediaWaveform $waveform,
app/src/Modules/Media/Domain/ValueObject/MediaExpiration.php:11:    private function __construct(
app/src/Modules/Media/Domain/ValueObject/MediaExpiration.php:12:        private \DateTimeImmutable|null $expiresAt,
app/src/Modules/Media/Domain/ValueObject/MediaExpiration.php:15:    public static function permanent(): self
app/src/Modules/Media/Domain/ValueObject/MediaExpiration.php:20:    public static function temporaryUntil(\DateTimeImmutable $expiresAt): self
app/src/Modules/Media/Domain/ValueObject/MediaExpiration.php:25:    public function value(): \DateTimeImmutable|null
app/src/Modules/Media/Domain/ValueObject/MediaExpiration.php:30:    public function isPermanent(): bool
app/src/Modules/Media/Domain/ValueObject/MediaExpiration.php:35:    public function isTemporary(): bool
app/src/Modules/Media/Domain/ValueObject/MediaExpiration.php:40:    public function expiresAtOrFail(): \DateTimeImmutable
app/src/Modules/Media/Domain/ValueObject/MediaExpiration.php:49:    public function equals(self $other): bool
app/src/Modules/Media/Domain/ValueObject/MediaExpiration.php:55:    public function __toString(): string
app/src/Modules/Media/Domain/ValueObject/MediaExpiration.php:61:    public function jsonSerialize(): string|null
app/src/Modules/Media/Infrastructure/Exception/MediaImageProcessorException.php:9:    public static function unsupportedDriver(string $driver): self
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaCommand.php:11:    public function __construct(
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaCommand.php:12:        public string $mediaId,
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaCommand.php:13:        public MediaConversionPlan $plan,
app/src/Modules/Media/Application/Dto/MediaConversionPlan.php:21:    public function __construct(
app/src/Modules/Media/Application/Dto/MediaConversionPlan.php:22:        public array $image,
app/src/Modules/Media/Application/Dto/MediaConversionPlan.php:23:        public array $video,
app/src/Modules/Media/Application/Dto/MediaConversionPlan.php:24:        public array $audio,
app/src/Modules/Media/Domain/ValueObject/MediaMultipartPart.php:9:    private function __construct(
app/src/Modules/Media/Domain/ValueObject/MediaMultipartPart.php:10:        public MediaMultipartPartNumber $partNumber,
app/src/Modules/Media/Domain/ValueObject/MediaMultipartPart.php:11:        public MediaMultipartPartETag $eTag,
app/src/Modules/Media/Domain/ValueObject/MediaMultipartPart.php:14:    public static function create(MediaMultipartPartNumber $partNumber, MediaMultipartPartETag $eTag): self
app/src/Modules/Media/Domain/ValueObject/MediaMultipartPart.php:19:    public static function fromValues(int $partNumber, string $eTag): self
app/src/Modules/Media/Domain/ValueObject/MediaMultipartPart.php:27:    public function equals(self $other): bool
app/src/Modules/Media/Domain/ValueObject/MediaMultipartPart.php:37:    public function jsonSerialize(): array
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:40:    public function __construct(
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:41:        private MediaRepository $mediaRepository,
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:42:        private MediaFileServiceContract $mediaFileService,
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:43:        private MediaImageProcessorContract $mediaImageProcessor,
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:44:        private MediaVideoProcessorContract $mediaVideoProcessor,
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:45:        private MediaAudioProcessorContract $mediaAudioProcessor,
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:46:        private EntityManagerInterface $entityManager,
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:47:        private LoggerInterface $logger,
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:51:    public function handle(ProcessMediaCommand $command): void
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:103:    private function persistReady(
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:137:    private function countByClass(array $conversions, string $conversionClass): int
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:148:    private function buildImageConversions(
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:208:    private function buildVideoConversions(
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:271:    private function buildAudioConversions(
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:313:    private function targetStorage(MediaVisibility $visibility): MediaStorage
app/src/Modules/Media/Application/Dto/MediaObjectHead.php:15:    public function __construct(
app/src/Modules/Media/Application/Dto/MediaObjectHead.php:16:        public MediaFileSize $contentLength,
app/src/Modules/Media/Application/Dto/RequestMediaUploadResult.php:8: * Результат RequestMediaUpload. Для single заполнен putUrl; для multipart — uploadId и parts.
app/src/Modules/Media/Application/Dto/RequestMediaUploadResult.php:13:    public function __construct(
app/src/Modules/Media/Application/Dto/RequestMediaUploadResult.php:14:        public string $mediaId,
app/src/Modules/Media/Application/Dto/RequestMediaUploadResult.php:15:        public MediaUploadMode $uploadMode,
app/src/Modules/Media/Application/Dto/RequestMediaUploadResult.php:16:        public \DateTimeImmutable $expiresAt,
app/src/Modules/Media/Application/Dto/RequestMediaUploadResult.php:17:        public string|null $putUrl,
app/src/Modules/Media/Application/Dto/RequestMediaUploadResult.php:18:        public string|null $uploadId,
app/src/Modules/Media/Application/Dto/RequestMediaUploadResult.php:19:        public MediaPresignedPartCollection|null $parts,
app/src/Modules/Media/Application/Dto/RequestMediaUploadResult.php:22:    public static function single(string $mediaId, string $putUrl, \DateTimeImmutable $expiresAt): self
app/src/Modules/Media/Application/Dto/RequestMediaUploadResult.php:34:    public static function multipart(
app/src/Modules/Media/Application/Dto/MediaUploadMode.php:10:    case Multipart = 'multipart';
app/src/Modules/Media/Domain/ValueObject/MediaStorageKey.php:12:    private function __construct(
app/src/Modules/Media/Domain/ValueObject/MediaStorageKey.php:13:        private string $value,
app/src/Modules/Media/Domain/ValueObject/MediaStorageKey.php:16:    public static function generate(): self
app/src/Modules/Media/Domain/ValueObject/MediaStorageKey.php:21:    public static function fromString(string $value): self
app/src/Modules/Media/Domain/ValueObject/MediaStorageKey.php:30:    public function value(): string
app/src/Modules/Media/Domain/ValueObject/MediaStorageKey.php:35:    public function shard(): string
app/src/Modules/Media/Domain/ValueObject/MediaStorageKey.php:40:    public function equals(self $other): bool
app/src/Modules/Media/Domain/ValueObject/MediaStorageKey.php:46:    public function __toString(): string
app/src/Modules/Media/Domain/ValueObject/MediaStorageKey.php:52:    public function jsonSerialize(): string
app/src/Modules/Media/Application/Command/MakeMediaPermanent/MakeMediaPermanentHandler.php:20:    public function __construct(
app/src/Modules/Media/Application/Command/MakeMediaPermanent/MakeMediaPermanentHandler.php:21:        private MediaRepository $mediaRepository,
app/src/Modules/Media/Application/Command/MakeMediaPermanent/MakeMediaPermanentHandler.php:22:        private EntityManagerInterface $entityManager,
app/src/Modules/Media/Application/Command/MakeMediaPermanent/MakeMediaPermanentHandler.php:23:        private LoggerInterface $logger,
app/src/Modules/Media/Application/Command/MakeMediaPermanent/MakeMediaPermanentHandler.php:26:    public function handle(MakeMediaPermanentCommand $command): MediaResult
app/src/Modules/Media/Infrastructure/Exception/MediaStorageNotConfiguredException.php:11:    public static function bucketAliasMissing(MediaStorage $storage): self
app/src/Modules/Media/Infrastructure/Exception/MediaStorageNotConfiguredException.php:16:    public static function bucketNameMissing(MediaStorage $storage): self
app/src/Modules/Media/Infrastructure/Exception/MediaStorageNotConfiguredException.php:21:    public static function serverMissing(MediaStorage $storage, string $server): self
app/src/Modules/Media/Application/Dto/MediaImageConversionSpec.php:17:    public function __construct(
app/src/Modules/Media/Application/Dto/MediaImageConversionSpec.php:18:        public MediaImageConversionType $type,
app/src/Modules/Media/Application/Dto/MediaImageConversionSpec.php:19:        public int $width,
app/src/Modules/Media/Application/Dto/MediaImageConversionSpec.php:20:        public int $height,
app/src/Modules/Media/Application/Dto/MediaUrlResult.php:8: * URL к медиа или его конверсии. expiresAt = null для прямого публичного URL (media-public),
app/src/Modules/Media/Application/Dto/MediaUrlResult.php:9: * заполнен для presigned-ссылки private-медиа.
app/src/Modules/Media/Application/Dto/MediaUrlResult.php:13:    public function __construct(
app/src/Modules/Media/Application/Dto/MediaUrlResult.php:14:        public string $url,
app/src/Modules/Media/Application/Dto/MediaUrlResult.php:15:        public \DateTimeImmutable|null $expiresAt,
app/src/Modules/Media/Domain/ValueObject/MediaMultipartPartETag.php:11:    private const int MAX_LENGTH = 512;
app/src/Modules/Media/Domain/ValueObject/MediaMultipartPartETag.php:13:    private function __construct(
app/src/Modules/Media/Domain/ValueObject/MediaMultipartPartETag.php:14:        private string $value,
app/src/Modules/Media/Domain/ValueObject/MediaMultipartPartETag.php:17:    public static function fromString(string $value): self
app/src/Modules/Media/Domain/ValueObject/MediaMultipartPartETag.php:28:    public function value(): string
app/src/Modules/Media/Domain/ValueObject/MediaMultipartPartETag.php:33:    public function equals(self $other): bool
app/src/Modules/Media/Domain/ValueObject/MediaMultipartPartETag.php:39:    public function __toString(): string
app/src/Modules/Media/Domain/ValueObject/MediaMultipartPartETag.php:45:    public function jsonSerialize(): string
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:37:    public function testRequestsSingleUpload(): void
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:57:            spec: $this->spec(presignedTtl: 300),
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:65:        // TTL presigned-ссылки берётся из спеки (300), а не из конфиг-дефолта 900.
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:72:        self::assertSame(300, $logger->contextFor('Запрошена загрузка медиа.')['presignedTtlSeconds']);
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:79:    public function testRequestsMultipartUploadWhenSizeReachesThreshold(): void
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:101:            spec: $this->spec(maxSize: 5_242_880, presignedTtl: 600),
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:109:        self::assertNotNull($this->multipartUploadRepository()->findByMediaId(MediaId::fromString($result->mediaId)));
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:111:        // TTL частей multipart тоже берётся из спеки (600), а не из конфига.
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:120:    public function testRejectsUnsupportedMimeType(): void
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:131:    public function testRejectsMimeTypeOutsideSpec(): void
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:142:    public function testRejectsSizeAboveSpecMax(): void
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:153:    public function testRejectsFileNameWithoutExtension(): void
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:164:    public function testRequestsDocumentUpload(): void
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:179:        // Документ кладётся в staging как и другие типы: путь начинается с uploads/.
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:183:    public function testRequestsDocumentUploadWithCharsetMime(): void
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:188:        // Спека задаёт базовый MIME без параметра, файл приходит с ;charset=utf-8 —
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:193:            fileMeta: $this->fileMeta(fileName: 'note.md', mimeType: 'text/markdown;charset=utf-8'),
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:201:    public function testRejectsDocumentMimeOutsideSpec(): void
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:215:    private function handler(
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:225:                stagingTtlSeconds: 86_400,
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:226:                multipartThresholdBytes: $threshold,
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:227:                multipartPartSizeBytes: $partSize,
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:239:    private function spec(
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:241:        int $presignedTtl = 300,
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:248:            presignedTtl: MediaPresignedTtl::fromInt($presignedTtl),
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:252:    private function fileMeta(
app/src/Modules/Media/Domain/ValueObject/MediaPresignedTtl.php:13:    protected const string NAME = 'TTL presigned-ссылки';
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
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:39:    public function testDeletesWaitingUploadWithActiveMultipart(): void
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:43:        $this->persist($media, $this->multipartUploadFor($media));
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:57:    public function testDeletesReadyMediaWithoutMultipartAbort(): void
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:76:    public function testDeletesReadyMediaConversionObjectsFromStorage(): void
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:102:    public function testDeletesReadyMediaVideoConversionObjectsFromStorage(): void
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:128:    public function testDeletesReadyMediaAudioConversionObjectsFromStorage(): void
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:154:    public function testDeletesWaitingUploadWithoutMultipartRecord(): void
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:172:    public function testRejectsMissingMedia(): void
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:182:    public function testRejectsForeignOwner(): void
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:195:    private function handler(MediaFileServiceContract $fileService): DeleteMediaHandler
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:199:            mediaMultipartUploadRepository: $this->multipartUploadRepository(),
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:209:    private function readyMediaWithImageConversion(UserId $userId, MediaStorage $storage): Media
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:238:    private function readyMediaWithVideoConversion(UserId $userId, MediaStorage $storage): Media
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:267:    private function readyMediaWithAudioConversion(UserId $userId, MediaStorage $storage): Media
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:298:    private function multipartUploadFor(Media $media): MediaMultipartUpload
app/src/Modules/Media/Domain/ValueObject/MediaProcessingAttempts.php:16:    public static function zero(): self
app/src/Modules/Media/Domain/ValueObject/MediaProcessingAttempts.php:21:    public function increment(): self
app/src/Modules/Media/Domain/ValueObject/MediaPath.php:16:    private const int MAX_LENGTH = 1024;
app/src/Modules/Media/Domain/ValueObject/MediaPath.php:18:    private function __construct(
app/src/Modules/Media/Domain/ValueObject/MediaPath.php:19:        private string $value,
app/src/Modules/Media/Domain/ValueObject/MediaPath.php:22:    public static function originalUpload(MediaStorageKey $storageKey, string $extension): self
app/src/Modules/Media/Domain/ValueObject/MediaPath.php:29:    public static function imageConversion(
app/src/Modules/Media/Domain/ValueObject/MediaPath.php:45:    public static function videoConversion(
app/src/Modules/Media/Domain/ValueObject/MediaPath.php:61:    public static function audioConversion(
app/src/Modules/Media/Domain/ValueObject/MediaPath.php:77:    public static function originalReady(MediaStorageKey $storageKey, MediaType $type, string $extension): self
app/src/Modules/Media/Domain/ValueObject/MediaPath.php:91:    public static function fromString(string $value): self
app/src/Modules/Media/Domain/ValueObject/MediaPath.php:99:    public function value(): string
app/src/Modules/Media/Domain/ValueObject/MediaPath.php:108:    public function extension(): string
app/src/Modules/Media/Domain/ValueObject/MediaPath.php:117:    public function equals(self $other): bool
app/src/Modules/Media/Domain/ValueObject/MediaPath.php:123:    public function __toString(): string
app/src/Modules/Media/Domain/ValueObject/MediaPath.php:129:    public function jsonSerialize(): string
app/src/Modules/Media/Domain/ValueObject/MediaPath.php:134:    private static function sanitizeExtension(string $extension): string
app/src/Modules/Media/Domain/ValueObject/MediaPath.php:145:    private static function assertValid(string $value): void
app/src/Modules/Media/Application/Command/DeleteMedia/DeleteMediaHandler.php:25:    public function __construct(
app/src/Modules/Media/Application/Command/DeleteMedia/DeleteMediaHandler.php:26:        private MediaRepository $mediaRepository,
app/src/Modules/Media/Application/Command/DeleteMedia/DeleteMediaHandler.php:27:        private MediaMultipartUploadRepository $mediaMultipartUploadRepository,
app/src/Modules/Media/Application/Command/DeleteMedia/DeleteMediaHandler.php:28:        private MediaImageConversionRepository $mediaImageConversionRepository,
app/src/Modules/Media/Application/Command/DeleteMedia/DeleteMediaHandler.php:29:        private MediaVideoConversionRepository $mediaVideoConversionRepository,
app/src/Modules/Media/Application/Command/DeleteMedia/DeleteMediaHandler.php:30:        private MediaAudioConversionRepository $mediaAudioConversionRepository,
app/src/Modules/Media/Application/Command/DeleteMedia/DeleteMediaHandler.php:31:        private MediaFileServiceContract $mediaFileService,
app/src/Modules/Media/Application/Command/DeleteMedia/DeleteMediaHandler.php:32:        private EntityManagerInterface $entityManager,
app/src/Modules/Media/Application/Command/DeleteMedia/DeleteMediaHandler.php:33:        private LoggerInterface $logger,
app/src/Modules/Media/Application/Command/DeleteMedia/DeleteMediaHandler.php:37:    public function handle(DeleteMediaCommand $command): void
app/src/Modules/Media/Application/Command/DeleteMedia/DeleteMediaHandler.php:54:        // Удаляем текущий оригинал (staging или целевой бакет). 404 игнорируется сервисом.
app/src/Modules/Media/Application/Command/DeleteMedia/DeleteMediaHandler.php:66:    private function deleteConversionObjects(Media $media): void
app/src/Modules/Media/Application/Command/DeleteMedia/DeleteMediaHandler.php:83:    private function abortActiveMultipartUpload(Media $media): void
app/src/Modules/Media/Application/Command/DeleteMedia/DeleteMediaHandler.php:85:        $multipartUpload = $this->mediaMultipartUploadRepository->findByMediaId($media->id);
app/src/Modules/Media/Application/Command/DeleteMedia/DeleteMediaHandler.php:87:        if ($multipartUpload === null) {
app/src/Modules/Media/Application/Command/DeleteMedia/DeleteMediaHandler.php:94:            uploadId: $multipartUpload->uploadId,
app/src/Modules/Media/Application/Query/GetAudioWaveform/GetAudioWaveformQuery.php:9:    public function __construct(
app/src/Modules/Media/Application/Query/GetAudioWaveform/GetAudioWaveformQuery.php:10:        public string $mediaId,
app/src/Modules/Media/Domain/ValueObject/MediaMultipartUploadIdValue.php:11:    private const int MAX_LENGTH = 1024;
app/src/Modules/Media/Domain/ValueObject/MediaMultipartUploadIdValue.php:13:    private function __construct(
app/src/Modules/Media/Domain/ValueObject/MediaMultipartUploadIdValue.php:14:        private string $value,
app/src/Modules/Media/Domain/ValueObject/MediaMultipartUploadIdValue.php:17:    public static function fromString(string $value): self
app/src/Modules/Media/Domain/ValueObject/MediaMultipartUploadIdValue.php:22:            throw new InvalidDomainValueException('Идентификатор multipart upload имеет неверную длину.');
app/src/Modules/Media/Domain/ValueObject/MediaMultipartUploadIdValue.php:28:    public function value(): string
app/src/Modules/Media/Domain/ValueObject/MediaMultipartUploadIdValue.php:33:    public function equals(self $other): bool
app/src/Modules/Media/Domain/ValueObject/MediaMultipartUploadIdValue.php:39:    public function __toString(): string
app/src/Modules/Media/Domain/ValueObject/MediaMultipartUploadIdValue.php:45:    public function jsonSerialize(): string
app/src/Modules/Media/Domain/ValueObject/MediaWaveform.php:18:    private const int MIN_PEAK = 0;
app/src/Modules/Media/Domain/ValueObject/MediaWaveform.php:19:    private const int MAX_PEAK = 255;
app/src/Modules/Media/Domain/ValueObject/MediaWaveform.php:24:    private function __construct(
app/src/Modules/Media/Domain/ValueObject/MediaWaveform.php:25:        private array $peaks,
app/src/Modules/Media/Domain/ValueObject/MediaWaveform.php:31:    public static function fromPeaks(array $peaks): self
app/src/Modules/Media/Domain/ValueObject/MediaWaveform.php:51:    public function peaks(): array
app/src/Modules/Media/Domain/ValueObject/MediaWaveform.php:56:    public function equals(self $other): bool
app/src/Modules/Media/Domain/ValueObject/MediaWaveform.php:65:    public function jsonSerialize(): array
app/src/Modules/Media/Application/Command/DeleteMedia/DeleteMediaCommand.php:9:    public function __construct(
app/src/Modules/Media/Application/Command/DeleteMedia/DeleteMediaCommand.php:10:        public string $userId,
app/src/Modules/Media/Application/Command/DeleteMedia/DeleteMediaCommand.php:11:        public string $mediaId,
app/src/Modules/Media/Application/Command/RecordMediaProcessingFailure/RecordMediaProcessingFailureHandler.php:22:    public function __construct(
app/src/Modules/Media/Application/Command/RecordMediaProcessingFailure/RecordMediaProcessingFailureHandler.php:23:        private MediaRepository $mediaRepository,
app/src/Modules/Media/Application/Command/RecordMediaProcessingFailure/RecordMediaProcessingFailureHandler.php:24:        private EntityManagerInterface $entityManager,
app/src/Modules/Media/Application/Command/RecordMediaProcessingFailure/RecordMediaProcessingFailureHandler.php:25:        private LoggerInterface $logger,
app/src/Modules/Media/Application/Command/RecordMediaProcessingFailure/RecordMediaProcessingFailureHandler.php:28:    public function handle(RecordMediaProcessingFailureCommand $command): void
app/src/Modules/Media/Application/Command/RecordMediaProcessingFailure/RecordMediaProcessingFailureCommand.php:9:    public function __construct(
app/src/Modules/Media/Application/Command/RecordMediaProcessingFailure/RecordMediaProcessingFailureCommand.php:10:        public string $mediaId,
app/src/Modules/Media/Application/Command/RecordMediaProcessingFailure/RecordMediaProcessingFailureCommand.php:11:        public string $error,
app/src/Modules/Media/Application/Command/RecordMediaProcessingFailure/RecordMediaProcessingFailureCommand.php:12:        public bool $isTransient,
app/src/Modules/Media/Application/Query/GetMediaUrl/GetMediaUrlQuery.php:13:    public function __construct(
app/src/Modules/Media/Application/Query/GetMediaUrl/GetMediaUrlQuery.php:14:        public string $mediaId,
app/src/Modules/Media/Application/Query/GetMediaUrl/GetMediaUrlQuery.php:15:        public int $presignedTtlSeconds,
app/src/Modules/Media/Application/Query/GetMediaUrl/GetMediaUrlQuery.php:16:        public MediaImageConversionType|MediaVideoConversionType|MediaAudioConversionType|null $conversionType = null,
app/src/Modules/Media/Domain/ValueObject/MediaMimeType.php:11:    private const int MAX_LENGTH = 255;
app/src/Modules/Media/Domain/ValueObject/MediaMimeType.php:13:    private function __construct(
app/src/Modules/Media/Domain/ValueObject/MediaMimeType.php:14:        private string $value,
app/src/Modules/Media/Domain/ValueObject/MediaMimeType.php:17:    public static function fromString(string $value): self
app/src/Modules/Media/Domain/ValueObject/MediaMimeType.php:28:    public function value(): string
app/src/Modules/Media/Domain/ValueObject/MediaMimeType.php:35:     * (`text/markdown;charset=utf-8` -> `text/markdown`). Хранимое значение (`value()`) не меняется.
app/src/Modules/Media/Domain/ValueObject/MediaMimeType.php:36:     * Используется при всех сравнениях MIME, чтобы charset-параметр не ломал сопоставление.
app/src/Modules/Media/Domain/ValueObject/MediaMimeType.php:38:    public function baseValue(): string
app/src/Modules/Media/Domain/ValueObject/MediaMimeType.php:43:    public function equals(self $other): bool
app/src/Modules/Media/Domain/ValueObject/MediaMimeType.php:49:    public function __toString(): string
app/src/Modules/Media/Domain/ValueObject/MediaMimeType.php:55:    public function jsonSerialize(): string
app/src/Modules/Media/Application/Query/GetMediaUrl/GetMediaUrlHandler.php:28:    public function __construct(
app/src/Modules/Media/Application/Query/GetMediaUrl/GetMediaUrlHandler.php:29:        private MediaRepository $mediaRepository,
app/src/Modules/Media/Application/Query/GetMediaUrl/GetMediaUrlHandler.php:30:        private MediaImageConversionRepository $mediaImageConversionRepository,
app/src/Modules/Media/Application/Query/GetMediaUrl/GetMediaUrlHandler.php:31:        private MediaVideoConversionRepository $mediaVideoConversionRepository,
app/src/Modules/Media/Application/Query/GetMediaUrl/GetMediaUrlHandler.php:32:        private MediaAudioConversionRepository $mediaAudioConversionRepository,
app/src/Modules/Media/Application/Query/GetMediaUrl/GetMediaUrlHandler.php:33:        private MediaFileServiceContract $mediaFileService,
app/src/Modules/Media/Application/Query/GetMediaUrl/GetMediaUrlHandler.php:36:    public function handle(GetMediaUrlQuery $query): MediaUrlResult
app/src/Modules/Media/Application/Query/GetMediaUrl/GetMediaUrlHandler.php:53:                presignedTtlSeconds: $query->presignedTtlSeconds,
app/src/Modules/Media/Application/Query/GetMediaUrl/GetMediaUrlHandler.php:61:            presignedTtlSeconds: $query->presignedTtlSeconds,
app/src/Modules/Media/Application/Query/GetMediaUrl/GetMediaUrlHandler.php:65:    private function findConversion(
app/src/Modules/Media/Application/Query/GetMediaUrl/GetMediaUrlHandler.php:81:    private function buildUrl(
app/src/Modules/Media/Application/Query/GetMediaUrl/GetMediaUrlHandler.php:85:        int $presignedTtlSeconds,
app/src/Modules/Media/Application/Query/GetMediaUrl/GetMediaUrlHandler.php:89:                url: $this->mediaFileService->publicUrl(storage: $storage, path: $path),
app/src/Modules/Media/Application/Query/GetMediaUrl/GetMediaUrlHandler.php:95:            new \DateInterval(\sprintf('PT%dS', MediaPresignedTtl::fromInt($presignedTtlSeconds)->value())),
app/src/Modules/Media/Application/Command/MakeMediaPermanent/MakeMediaPermanentCommand.php:9:    public function __construct(
app/src/Modules/Media/Application/Command/MakeMediaPermanent/MakeMediaPermanentCommand.php:10:        public string $userId,
app/src/Modules/Media/Application/Command/MakeMediaPermanent/MakeMediaPermanentCommand.php:11:        public string $mediaId,
tests/Feature/Modules/Media/Application/CheckMediaAttachableHandlerTest.php:21:    public function testAllowsReadyMediaOwnedByUser(): void
tests/Feature/Modules/Media/Application/CheckMediaAttachableHandlerTest.php:35:    public function testRejectsMissingMedia(): void
tests/Feature/Modules/Media/Application/CheckMediaAttachableHandlerTest.php:45:    public function testRejectsForeignMedia(): void
tests/Feature/Modules/Media/Application/CheckMediaAttachableHandlerTest.php:58:    public function testRejectsMediaThatIsNotReady(): void
tests/Feature/Modules/Media/Application/CheckMediaAttachableHandlerTest.php:72:    private function handler(): CheckMediaAttachableHandler
tests/Feature/Modules/Media/Application/CheckMediaAttachableHandlerTest.php:77:    private function readyMedia(UserId $owner): Media
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:16:    public function testCheckMediaIsImageReturnsTrueForImage(): void
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:26:    public function testCheckMediaIsImageReturnsFalseForVideo(): void
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:36:    public function testCheckMediaIsImageReturnsFalseForDocument(): void
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:39:        // Тест пиннит, что документ нельзя подсунуть в image-only сценарий (например аватар).
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:48:    public function testCheckMediaIsImageReturnsFalseForMissingMedia(): void
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:55:    public function testCheckMediaExistsReturnsTrueForExistingMedia(): void
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:65:    public function testCheckMediaExistsReturnsFalseForMissingMedia(): void
app/src/Modules/Media/Application/Service/MediaTypeResolver.php:25:    private const array DOCUMENT_MIME_TYPES = [
app/src/Modules/Media/Application/Service/MediaTypeResolver.php:46:    public function resolve(MediaMimeType $mimeType): MediaType
app/src/Modules/Media/Application/Query/CheckMediaExists/CheckMediaExistsHandler.php:12:    public function __construct(
app/src/Modules/Media/Application/Query/CheckMediaExists/CheckMediaExistsHandler.php:13:        private MediaRepository $mediaRepository,
app/src/Modules/Media/Application/Query/CheckMediaExists/CheckMediaExistsHandler.php:16:    public function handle(CheckMediaExistsQuery $query): bool
app/src/Modules/Media/Application/Query/CheckMediaExists/CheckMediaExistsQuery.php:9:    public function __construct(
app/src/Modules/Media/Application/Query/CheckMediaExists/CheckMediaExistsQuery.php:10:        public string $mediaId,
app/src/Modules/Media/Application/Query/FindMediaUrl/FindMediaUrlHandler.php:18: * по умолчанию без try-catch. Для публичного медиа отдаёт прямой URL, для приватного — presigned.
app/src/Modules/Media/Application/Query/FindMediaUrl/FindMediaUrlHandler.php:22:    public function __construct(
app/src/Modules/Media/Application/Query/FindMediaUrl/FindMediaUrlHandler.php:23:        private MediaRepository $mediaRepository,
app/src/Modules/Media/Application/Query/FindMediaUrl/FindMediaUrlHandler.php:24:        private MediaFileServiceContract $mediaFileService,
app/src/Modules/Media/Application/Query/FindMediaUrl/FindMediaUrlHandler.php:28:    public function handle(FindMediaUrlQuery $query): MediaUrlResult|null
app/src/Modules/Media/Application/Query/FindMediaUrl/FindMediaUrlHandler.php:38:                url: $this->mediaFileService->publicUrl(storage: $media->storage, path: $media->path),
app/src/Modules/Media/Application/Query/FindMediaUrl/FindMediaUrlHandler.php:44:            new \DateInterval(\sprintf('PT%dS', MediaPresignedTtl::fromInt($query->presignedTtlSeconds)->value())),
app/src/Modules/Media/Application/Query/FindMediaUrl/FindMediaUrlQuery.php:9:    public function __construct(
app/src/Modules/Media/Application/Query/FindMediaUrl/FindMediaUrlQuery.php:10:        public string $mediaId,
app/src/Modules/Media/Application/Query/FindMediaUrl/FindMediaUrlQuery.php:11:        public int $presignedTtlSeconds,
app/src/Modules/Media/Application/Query/CheckMediaAttachable/CheckMediaAttachableQuery.php:9:    public function __construct(
app/src/Modules/Media/Application/Query/CheckMediaAttachable/CheckMediaAttachableQuery.php:10:        public string $mediaId,
app/src/Modules/Media/Application/Query/CheckMediaAttachable/CheckMediaAttachableQuery.php:11:        public string $ownerUserId,
app/src/Modules/Media/Application/Query/CheckMediaAttachable/MediaAttachableResult.php:12:    public function __construct(
app/src/Modules/Media/Application/Query/CheckMediaAttachable/MediaAttachableResult.php:13:        public string $mediaId,
app/src/Modules/Media/Application/Query/CheckMediaAttachable/CheckMediaAttachableHandler.php:22:    public function __construct(
app/src/Modules/Media/Application/Query/CheckMediaAttachable/CheckMediaAttachableHandler.php:23:        private MediaRepository $mediaRepository,
app/src/Modules/Media/Application/Query/CheckMediaAttachable/CheckMediaAttachableHandler.php:27:    public function handle(CheckMediaAttachableQuery $query): MediaAttachableResult

codex
+ Добавить: тесты не покрывают весь согласованный список MIME документов. В `MediaTypeResolverTest` проверены только `pdf`, `doc`, `csv`, `markdown`, `djvu`, но не покрыты `docx`, `rtf`, OpenDocument, Excel, PowerPoint, EPUB, FB2. Риск: опечатка в длинном MIME из списка не будет поймана.

+ Добавить: новые комментарии в тестах нарушают правило «без англицизмов» и местами неточны. Примеры: `image-only`, `staging`, `ready-пути`, `flow-теста`, «пиннит». Это в `CheckMediaQueriesTest`, `RequestMediaUploadHandlerTest`, `MediaProcessingFlowTest`.

+ Добавить: комментарий в `CheckMediaQueriesTest` говорит про DJVU, но тест создаёт PDF (`application/pdf`). Если цель — проверить, что DJVU не считается картинкой, надо создать документ с `image/vnd.djvu`; если цель просто документ, комментарий нужно упростить.

− Убрать проблему 1 про смешение задач в рабочем дереве. Для текущего target пользователь явно ограничил проверку `git diff HEAD -- app/src/Modules/Media/ tests/` плюс один untracked media-тест. Всё, что вне Media и его тестов, сейчас не должно быть замечанием этого ревью.

~ Переформулировать проблему 2 про untracked `MediaMimeTypeCollectionTest`. Сама проблема верная как риск коммита, но в текущем target файл явно включён в проверку напрямую. Лучше писать не «вне целевого ревью», а «не попадёт в коммит при `git add -u`, хотя нужен для покрытия `containsMimeType()`».

~ Замечание про докстринг `baseValue()` оставить, но сделать короче и жёстче: фраза «при всех сравнениях MIME» неверна, потому что `equals()` сравнивает raw `value()`, а префиксы image/video/audio тоже идут по `value()`. Этого достаточно; пункт про «не готовый Content-Type» выглядит слабым и не связан с текущим поведением.

~ Замечание про дублирование `assertDocumentPlan()` лучше убрать или явно оставить как чистую необязательную стилистику. План сам просил писать по стилю соседних `assert*Plan`, поэтому это не проблема реализации.

? Я не запускал заново `make test` и `make phpstan`; сверил код и журнал выполнения, где указан успешный повторный `make test` и `make phpstan`.
tokens used
118 004
+ Добавить: тесты не покрывают весь согласованный список MIME документов. В `MediaTypeResolverTest` проверены только `pdf`, `doc`, `csv`, `markdown`, `djvu`, но не покрыты `docx`, `rtf`, OpenDocument, Excel, PowerPoint, EPUB, FB2. Риск: опечатка в длинном MIME из списка не будет поймана.

+ Добавить: новые комментарии в тестах нарушают правило «без англицизмов» и местами неточны. Примеры: `image-only`, `staging`, `ready-пути`, `flow-теста`, «пиннит». Это в `CheckMediaQueriesTest`, `RequestMediaUploadHandlerTest`, `MediaProcessingFlowTest`.

+ Добавить: комментарий в `CheckMediaQueriesTest` говорит про DJVU, но тест создаёт PDF (`application/pdf`). Если цель — проверить, что DJVU не считается картинкой, надо создать документ с `image/vnd.djvu`; если цель просто документ, комментарий нужно упростить.

− Убрать проблему 1 про смешение задач в рабочем дереве. Для текущего target пользователь явно ограничил проверку `git diff HEAD -- app/src/Modules/Media/ tests/` плюс один untracked media-тест. Всё, что вне Media и его тестов, сейчас не должно быть замечанием этого ревью.

~ Переформулировать проблему 2 про untracked `MediaMimeTypeCollectionTest`. Сама проблема верная как риск коммита, но в текущем target файл явно включён в проверку напрямую. Лучше писать не «вне целевого ревью», а «не попадёт в коммит при `git add -u`, хотя нужен для покрытия `containsMimeType()`».

~ Замечание про докстринг `baseValue()` оставить, но сделать короче и жёстче: фраза «при всех сравнениях MIME» неверна, потому что `equals()` сравнивает raw `value()`, а префиксы image/video/audio тоже идут по `value()`. Этого достаточно; пункт про «не готовый Content-Type» выглядит слабым и не связан с текущим поведением.

~ Замечание про дублирование `assertDocumentPlan()` лучше убрать или явно оставить как чистую необязательную стилистику. План сам просил писать по стилю соседних `assert*Plan`, поэтому это не проблема реализации.

? Я не запускал заново `make test` и `make phpstan`; сверил код и журнал выполнения, где указан успешный повторный `make test` и `make phpstan`.
