Reading additional input from stdin...
OpenAI Codex v0.141.0
--------
workdir: /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
model: gpt-5.5
provider: openai
approval: never
sandbox: workspace-write [workdir, /tmp, $TMPDIR]
reasoning effort: high
reasoning summaries: none
session id: 019ee002-6686-7590-ac65-6674eff882a5
--------
user
Прочитай docs/reviews/2026-06-19_16-02_posts-application-presentation.md, target = незакоммиченный git diff HEAD + untracked-файлы на ветке work-1 (новый код в app/src/Modules/Posts/{Application,Presentation}, Media/Application/Query/{CheckMediaAttachable,FindMediaUrl}, Tags/Application, User/Application, изменённые репозитории, app/config/user.php, app/locale/{ru,en}/posts.php, public/openapi/openapi.yml; untracked-файлы НЕ видны в git diff HEAD — используй git status --porcelain и читай файлы напрямую), план docs/plans/2026-06-18_17-42_posts-application-presentation.md, docs/rules.md, docs/arch.md. Связанного research-файла нет (docs/researches/2026-06-18_15-15_... отсутствует). Затем глубоко проверь ревью по плану, правилам, архитектуре и релевантному коду: открой реальные файлы, модули, контроллеры (PostController/CommentController), Handler-ы, PostViewAssembler, GetMediaUrl/FindMediaUrl Handler-ы, Filter-ы, openapi.yml и тесты, и оцени фактическую корректность каждого пункта. Особо проверь: (1) Проблема 1 — mediaItems() использует бросающий GetMediaUrl, риск 500 при недоступном медиа записи; квалификация 'отклонение от плана' — план требовал GetMediaUrl именно для медиа записи, мягкая деградация была только для аватара (решение 20); это архитектурный риск, а не нарушение плана. (2) Проблема 2 — 204 vs EmptySuccessResponse: существующие Auth-роуты (logout, code_request) тоже маппят EmptySuccessResponse в 204 без тела, значит расхождения с решением 24 'как у существующих контроллеров' НЕТ — проверь и подтверди/опровергни. (3) под /posts ровно 15 операций (план в тексте говорит 16, но таблица даёт 15) — Замечание 6. Мета-ревью на русском: какие проблемы добавить, убрать, переформулировать; какие баги, риски для смежного кода и недостающие тесты пропущены. Не предлагай перечислять выполненную работу. Формат: '+' добавить, '−' убрать, '~' переформулировать, '?' нельзя проверить.
codex
Использую `eda-review-check`: задача именно про проверку готового ревью по плану, правилам, архитектуре и реальному коду. Сначала прочитаю инструкции скилла, затем нужные документы и файлы.
exec
/bin/zsh -lc "sed -n '1,240p' /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1/.codex/skills/eda-review-check/SKILL.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
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
Скилл требует проверку ревью через роли `plan-check`, `architecture-check`, `rules-check` и, если включено, `quality-check`. Сейчас проверю, доступен ли инструмент субагентов; если нет, продолжу вручную по тем же ролям и явно отмечу ограничение.
codex
Инструмент субагентов доступен, но его собственные правила разрешают запуск только при явной просьбе делегировать работу агентам. В этом запросе такой просьбы нет, поэтому делаю проверку сам, но по тем же ролям: план, архитектура, правила и качество кода.
exec
/bin/zsh -lc "sed -n '1,220p' docs/code-examples.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
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
/bin/zsh -lc "sed -n '1,260p' docs/reviews/2026-06-19_16-02_posts-application-presentation.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
---
title: Модуль Posts/Tags/User — прикладной и презентационный слой
date: 2026-06-19 16:02
target: git diff HEAD + untracked (work-1)
plan: docs/plans/2026-06-18_17-42_posts-application-presentation.md
mode: draft
score: 82
status: draft
meta_reviewers: []
---

# Ревью: Модуль Posts/Tags/User — прикладной и презентационный слой

## Оценка

**82/100.** План реализован полно и аккуратно: все 16 маршрутов, 7 видов уведомлений, межмодульные
сценарии, дедуп, идемпотентность, cursor-пагинация и батч-обогащение на месте, тесты покрывают
каждый роут, `make qa` зелёный. Снижение оценки — за один реальный риск падения чтения ленты в 500
при недоступном медиа записи (та же ловушка, которую для аватара осознанно обошли), за рассинхрон
формата лайков с задокументированным телом ответа и пару некритичных расхождений с контрактом плана.

## Проблемы сверки с планом

### 1. Чтение записи падает в 500, если приложенное медиа стало недоступным

При сборке ленты и одиночной записи ссылки на приложенное медиа берутся «строгим» сценарием,
который бросает ошибку, если медиа удалено или ещё не готово. На практике это значит: пользователь
приложил картинку к посту, позже медиа удалили или оно перешло в необработанное состояние — и тогда
любой просмотр этого поста (а в ленте — всей страницы ленты) отвечает 500 вместо того, чтобы
показать пост без картинки или с заглушкой.

Это ровно та ситуация, которую для аватара в профиле осознанно обошли отдельным не бросающим
сценарием (план, заметка про `FindMediaUrl`; решение 20 — «аватар всегда непустой, медиа недоступно
→ дефолт»). Для медиа записи такой же мягкой деградации нет, хотя запись живёт долго и её медиа
может исчезнуть независимо. Риск проявляется не на «счастливом пути», а позже в проде — при
удалении/перемещении медиа, и ломает не один пост, а целую страницу ленты.

Технические детали:

- **Статус:** отклонение / риск
- **Где:** `app/src/Modules/Posts/Application/View/PostViewAssembler.php:169-190` (`mediaItems()`),
  использует `GetMediaUrlHandler` и читает `$mediaUrl->url` напрямую.
- **Что подтверждает проблему:** `app/src/Modules/Media/Application/Query/GetMediaUrl/GetMediaUrlHandler.php:30-35`
  бросает `NotFoundException` на отсутствующее (`app.media.not_found`) и не готовое (`app.media.not_ready`)
  медиа. Аналогичный риск был осознан для аватара и решён через `FindMediaUrlHandler`
  (`UserPublicProfileAssembler.php:43-60`), который возвращает `null` и подставляет дефолт. В тестах
  чтения (`GetPostHttpTest`, `GetUserFeedHttpTest`) сценарий «приложенное медиа недоступно при
  чтении» не покрыт — есть только soft-deleted-посты.
- **Как исправить:** в `mediaItems()` использовать не бросающий путь (тот же `FindMediaUrlHandler`
  или его батч-аналог), а недоступные медиа либо исключать из списка `media`, либо отдавать с
  заглушкой; решить продуктово, что показывать. Добавить интеграционный тест: пост с приложенным,
  затем удалённым/не-Ready медиа читается без 500.

### 2. Тело ответа лайка/удаления отдаёт 204, а не задокументированный `EmptySuccessResponse`-контракт

План (решение 24) явно фиксировал: лайк/снятие лайка, публикация и удаление возвращают
`EmptySuccessResponse` «как в существующих контроллерах, без 201/204/409». В сгенерированной
OpenAPI у этих операций стоит `204` без тела. Это формально не баг (контроллеры действительно
возвращают `EmptySuccessResponse`, а 204 — его маппинг во фреймворке), но стоит свериться: решение
плана было «как у существующих контроллеров», и если существующие success-ответы отдают 200 с телом
`{}`, то новые роуты на 204 ведут себя иначе, чем обещал контракт, и клиент, ожидающий тело, его не
получит.

Технические детали:

- **Статус:** требует проверки / возможное отклонение
- **Где:** `public/openapi/openapi.yml` — операции `*_delete`, `*_like`, `*_unlike`,
  `*_comments_like/unlike` описаны как `204` без `content`. Контроллеры:
  `PostController.php` (`delete`/`like`/`unlike` → `EmptySuccessResponse`),
  `CommentController.php` (аналогично).
- **Что подтверждает проблему:** план, решение 24 — «`EmptySuccessResponse` (как в существующих
  контроллерах, без 201/204/409)». В OpenAPI стоит именно 204.
- **Как исправить:** сверить, как `EmptySuccessResponse` маппится у уже существующих контроллеров
  (например Auth/Notifications) — если там 200, расхождения нет на уровне кода и достаточно
  поправить формулировку решения 24; если контракт «без 204» был намеренным, скорректировать
  маппинг/ответ. Решение за автором — это вопрос соответствия публичному контракту, а не логики.

### 3. Поле `attachmentType` в теле запроса не реализовано (расходится с контрактом API в плане)

В разделе «Параметры и ошибки» план описывал тело `CreatePost`/`RepostPost` с полем
`attachmentType?:string('none'|'media')` и `#[Assert\Choice(['none','media'])]` во `Filter`.
В реализации этого поля нет: `attachmentType` выводится из того, пуст ли список `mediaIds`.
Это разумное упрощение (тип однозначно следует из наличия медиа, lesson/practice отложены по
решению 21) и журнал это фиксирует, но фактический контракт API разошёлся с тем, что описано в
плане и могло уйти в задачи фронта. Риск низкий — клиент, отправивший `attachmentType`, просто
получит проигнорированное поле.

Технические детали:

- **Статус:** отклонение (осознанное, задокументировано в журнале фазы 3)
- **Где:** `CreatePostFilter.php`, `RepostPostFilter.php` — поля `attachmentType` нет;
  `CreatePostHandler.php:49`, `RepostPostHandler.php:62` — `attachmentType` выводится из `mediaIds`.
- **Что подтверждает проблему:** план, блок «Параметры и ошибки», и таблица в `## Контракты
  реализации` упоминают `attachmentType` в body; OpenAPI его не содержит.
- **Как исправить:** правка не обязательна; достаточно отметить расхождение и согласовать контракт
  с потребителями API. Если поле всё же ожидается фронтом — добавить его во Filter с `#[Assert\Choice]`
  и сверять с `mediaIds`.

## Замечания

### 1. Лайк комментария не проверяет видимость самой записи

Лайк и снятие лайка комментария проверяют только то, что комментарий не удалён, но не проверяют
состояние записи, к которой он относится. В результате можно лайкнуть комментарий под записью,
которая уже удалена, заблокирована модерацией или переведена в черновик. Для записи и репоста такой
guard есть (`isActionable` — только опубликованная и не удалённая), а для реакций на комментарии его
нет — поведение между «лайк поста» и «лайк комментария под тем же постом» получается разным.

Риск умеренный: это не утечка данных, но позволяет накручивать реакции и слать уведомление
`comment_like` автору на контент, который по политике видимости уже скрыт. Проявится, когда запись
заблокируют/удалят, а комментарии под ней останутся доступны для лайка.

Технические детали:

- **Тип:** `architecture`
- **Рекомендация:** `на усмотрение автора`
- **Где:** `app/src/Modules/Posts/Application/Command/LikeComment/LikeCommentHandler.php:40-45`,
  `UnlikeComment/UnlikeCommentHandler.php` — проверяется только `Comment::isDeleted()`, запись не
  загружается. Сравни с `CommentPostHandler.php:43-48` / `ReplyCommentHandler.php:54-58`, где
  доступность записи проверяется через `PostVisibilityPolicy::isActionable`.
- **Что подтверждает проблему:** решение 15 плана формулирует guard для лайка/коммента/репоста «над
  Published и не удалённой/не Blocked записью»; для лайка комментария запись в guard не участвует.
- **Как исправить:** при наличии продуктовой потребности — в `LikeComment`/`UnlikeComment` загружать
  запись комментария и проверять `isActionable`/видимость, как в комментировании. Добавить тест:
  лайк комментария под заблокированной/удалённой записью → 404. Если продукт сознательно разрешает
  реакции на «исторические» комментарии — зафиксировать это решение.

### 2. Порядок guard-ов в удалении: удалённая чужая запись отдаёт 403 вместо 404

В удалении записи и комментария сначала проверяется право собственности, и только потом — факт уже
выполненного удаления. Из-за этого если запись уже мягко удалена и принадлежит другому
пользователю, ответ будет 403 (нет прав), хотя по смыслу удалённая запись для постороннего просто
«не найдена» (404). Это мелкое расхождение в кодах ошибок на краевом сценарии, оно не влияет на
данные, но делает ответы API менее предсказуемыми и может неявно подтверждать существование чужой
удалённой записи.

Технические детали:

- **Тип:** `quality`
- **Рекомендация:** `на усмотрение автора`
- **Где:** `app/src/Modules/Posts/Application/Command/DeletePost/DeletePostHandler.php:37-45`
  (сначала `ForbiddenException`, потом guard `isDeleted()`); аналогично
  `DeleteComment/DeleteCommentHandler.php:43-51`.
- **Что подтверждает проблему:** для своей уже удалённой записи возвращается идемпотентный no-op,
  а для чужой удалённой — 403, то есть код ответа зависит от владельца удалённой сущности.
- **Как исправить:** решить, что важнее — идемпотентность или сокрытие. Если приоритет сокрытию,
  имеет смысл сверять порядок проверок (для невидимой/удалённой сущности отдавать 404 раньше
  проверки прав). Не обязательно — поведение спорное и зависит от продуктовой политики.

### 3. `DeletePost` декремент репостов оригинала не «зеркалит» инкремент симметрично

При удалении записи-репоста счётчик репостов оригинала уменьшается только если он строго больше
нуля. Это защищает от ухода в минус (хорошо), но создаёт асимметрию: репост всегда инкрементит
оригинал безусловно, а декремент условный. В норме они согласованы, но если по какой-то причине
счётчик оригинала уже равен нулю (рассинхрон из-за прошлых операций/ручных правок), удаление репоста
молча не восстановит инвариант. Риск низкий и в пределах принятой в плане модели счётчиков
(«не уходят ниже нуля»), отмечаю для полноты.

Технические детали:

- **Тип:** `quality`
- **Рекомендация:** `на усмотрение автора`
- **Где:** `DeletePostHandler.php:55-58` — `if ($original !== null && $original->repostsCount->value() > 0)`.
- **Что подтверждает проблему:** инкремент в `RepostPostHandler.php:73` безусловный, декремент при
  удалении — условный.
- **Как исправить:** оставить как есть (соответствует решению 11 «счётчики не ниже нуля») либо
  вынести логику «зеркального» декремента в доменный метод, который сам гарантирует неотрицательность,
  чтобы guard `> 0` не дублировался в каждом Handler-е (есть и в Unlike, и в DeleteComment).

### 4. Дублирование сборки профилей/получателей в двух composer-ах

`PostContentComposer` и `CommentComposer` содержат идентичные приватные методы `resolveRecipients()`
и `profile()` (включая один и тот же текст исключения `app.posts.mention_user_not_found` и проверку
полноты по `count()`). Логика проверки существования упоминаний и получения профиля повторяется
дословно. Это не баг, но при изменении правила (например, другой код ошибки или другая политика
проверки упоминаний) придётся править в двух местах, и легко забыть одно из них.

Технические детали:

- **Тип:** `quality`
- **Рекомендация:** `на усмотрение автора`
- **Где:** `app/src/Modules/Posts/Application/Post/PostContentComposer.php:169-189` и
  `app/src/Modules/Posts/Application/Post/CommentComposer.php:121-141` — совпадающие
  `resolveRecipients()`/`profile()`.
- **Что подтверждает проблему:** методы идентичны по телу и по тексту исключения.
- **Как исправить:** вынести разрешение получателей/профилей в один общий сервис
  (`Posts/Application/Post/` или `Posts/Application/Notification/`), который оба composer-а
  переиспользуют через инъекцию. Тесты уже покрывают поведение через HTTP — отдельный unit на сервис
  желателен, но не обязателен.

### 5. Профили актора и получателей запрашиваются по одному, возможен лишний трафик к User

В сценариях комментирования/ответа и в обогащении одиночной записи профиль автора и профиль
актора/получателя запрашиваются отдельными вызовами `GetUserPublicProfile`, хотя рядом уже есть
батч-сценарий `GetUserPublicProfiles`. В одиночных действиях это терпимо (1–2 пользователя), но в
`CommentComposer` при наличии упоминаний получается: один батч на упомянутых плюс отдельные вызовы
на актора и на primary-получателя — то есть несколько round-trip к User-модулю там, где можно было
бы собрать все нужные id в один батч.

Риск производительности низкий (число запросов ограничено доменом — актор, автор, упомянутые ≤50),
но это лишние обращения на горячем пути создания комментария.

Технические детали:

- **Тип:** `performance`
- **Рекомендация:** `на усмотрение автора`
- **Где:** `CommentComposer.php:63,80` (`$this->profile($actorUserId)` и `profile($primaryRecipientUserId)`
  отдельно от батча упоминаний); `PostViewAssembler.php:50-59,221-242` (одиночный `authorView`
  в `fromPost`/`originalView` против батча в `fromPosts`).
- **Что подтверждает проблему:** наличие `GetUserPublicProfiles` (батч) и одновременно несколько
  одиночных `GetUserPublicProfile` в одном сценарии.
- **Как исправить:** собрать все нужные userId (актор + primary + упоминания) в один
  `GetUserPublicProfilesQuery` и брать профили из результата. Не обязательно — выигрыш заметен лишь
  под нагрузкой.

### 6. Расхождение плана и реализации в количестве операций OpenAPI (16 vs 31)

План в разделе «Документация и эксплуатация» ожидал «16 новых операций под `/api/v1/posts`», а
журнал и факт генерации говорят о 31 операции в спецификации. Расхождение объясняется тем, что 16 —
это новые роуты Posts, а 31 — общее число операций в файле (с учётом ранее существовавших). Само по
себе это не проблема, но при приёмке по плану цифра «16» может сбить с толку. Стоит свериться, что в
`openapi.yml` присутствуют ровно 16 операций под префиксом `/posts` и ни одна не потерялась.

Технические детали:

- **Тип:** `docs`
- **Рекомендация:** `на усмотрение автора`
- **Где:** `public/openapi/openapi.yml` (добавленные пути `/posts*`); план, блок «Документация и
  эксплуатация»; журнал — «генерация снова работает (31 операция)».
- **Что подтверждает проблему:** в diff openapi видно 14 путей с несколькими методами; нужно
  пересчитать операции именно под `/posts`, чтобы подтвердить полноту 16.
- **Как исправить:** при приёмке выполнить быстрый подсчёт операций под `/posts` в
  `openapi.yml` и сверить с таблицей плана (6 записей + 5 комментариев/реакций + 4 чтения + like/unlike
  = 16). Кода не касается.

## Рекомендации

- **Править обязательно:** 1 (план)
- **На усмотрение автора:** 2, 3 (план); 1, 2, 3, 4, 5, 6 (замечания)

## Изменения после мета-ревью

(черновик — мета-ревью не запускалось)

exec
/bin/zsh -lc "sed -n '1,260p' docs/arch.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
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
/bin/zsh -lc "sed -n '1,280p' docs/plans/2026-06-18_17-42_posts-application-presentation.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
exec
/bin/zsh -lc "sed -n '1,260p' docs/rules.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
---
title: Модуль Posts — прикладной и презентационный слой + уведомления на действия
date: 2026-06-18 17:42
mode: strict
plan_size: normal
decision_mode: recommend_and_ask
status: meta-reviewed
reviewer: pending
meta_reviewers: [claude-haiku, claude-sonnet, claude-opus]
test_strategy: after_each_phase
logging_strategy: debug_precise
sources:
  rules: docs/rules.md
  arch: docs/arch.md
  research: docs/researches/2026-06-18_15-15_posts-application-presentation.md
---

# План реализации

## Задача

Достроить два внешних слоя модуля `Posts` (`Application` + `Presentation`) поверх
готового и закоммиченного `Domain`/`Repository` (`2f3bc87`): инфраструктурный каркас
(папки, `PostsBootloader`, регистрация в `Kernel`), 16 прикладных сценариев (записи,
реакции, комментарии, чтение), 7 видов уведомлений на действия, минимальные публичные
сценарии в смежных модулях `User`/`Media`/`Tags` и доработка read-фильтров
репозиториев.

Готово, когда: реализованы все маршруты схемы B (единый префикс `/api/v1/posts`),
каждый роут покрыт интеграционным тестом, уведомления 7 видов отправляются по правилам
получателей, `make test` и `make phpstan` зелёные, покрытие 100%.

## Контекст

- `Domain` + `Repository` модулей `Posts`/`Tags` закоммичены (`2f3bc87`). Папок
  `Application`/`Presentation`/`Infrastructure/Bootloader` у обоих модулей нет.
- **Схема БД не меняется, миграции не создаются.** Все изменения репозиториев — только
  методы/фильтры чтения (query-level через `when()`/`WhenSelect`), без правки таблиц.
- Точные доменные сигнатуры (проверено по коду):
  - `Post::create(UserId, PostText, PostStatus, AttachmentType, PostLesson, PostPractice, PostOriginal)`;
    методы `publish()`, `softDelete(\DateTimeImmutable)`, `incrementLikes/decrementLikes`,
    `incrementReposts/decrementReposts`, `incrementComments/decrementComments`,
    `setMediaAttachment()`, `setLesson/ setPractice`; связи `media`/`tags` — `HasMany`.
  - `Comment::create(PostId, UserId, CommentText, CommentParent)`;
    `delete(CommentDeletedBy, CommentDeletedAt, CommentDeletionReason)`,
    `incrementReplies/decrementReplies`, `isDeleted()`.
  - `PostLike::create(PostId, UserId)`, `CommentLike::create(CommentId, UserId)`,
    `PostMention::create(PostId, UserId)`, `CommentMention::create(CommentId, UserId)`,
    `PostMedia::create(Post, PostMediaReference, MediaPosition)`,
    `PostTag::create(PostId, TagId)`.
  - VO-фабрики: `PostText::fromString|none`, `CommentText::fromString`,
    `PostOriginal::pointingTo|none`, `CommentParent::pointingTo|none`,
    `CommentDeletedBy::by|none`, `CommentDeletedAt::at|notDeleted`,
    `CommentDeletionReason::none`, `PostLesson::none`, `PostPractice::none`.
  - `PostStatus { Draft, Published, Blocked }`, `AttachmentType { None, Media, Lesson, Practice }`.
  - Репозитории: `PostRepository::findById|findByUserId(UserId,?PostStatus,?PostId cursor,int limit)|findRepostsOf`;
    `CommentRepository::findById|findByPostId|findReplies`;
    `PostLikeRepository::findByPostAndUser|existsByPostAndUser`;
    `CommentLikeRepository::findByCommentAndUser|existsByCommentAndUser`;
    `PostMentionRepository`, `PostMediaRepository`, `PostTagRepository`;
    `TagRepository::findById|findByText|findByTexts(TagText ...)`.
- Контракты `Notifications` (проверено):
  - `NotificationSenderContract::send(UserId $recipient, NotificationContent): void` —
    стейджит один `NotificationRequested` в outbox и **не** делает свой `run()`.
  - `NotificationContent(NotificationTypeDefinition $type, NotificationTitle, NotificationBody, NotificationAction, NotificationActor)` — принимает VO, не строки.
  - `NotificationTitle::fromString` (≤255), `NotificationBody::fromString` (непустой),
    `NotificationAction::linkTo(string $actionType, string $actionId)`,
    `NotificationActor::of(UserId, string $name, string $avatarUrl)` — **бросает** на пустые
    `name`/`avatarUrl`.
  - `NotificationTypeDefinition { code(): NotificationTypeCode; defaultChannels(): NotificationChannelDefaults }`;
    `NotificationTypeRegistryContract::register(NotificationTypeDefinition ...)` — реестр-синглтон;
    модули-источники регистрируют свои виды в `boot(NotificationTypeRegistryContract)` своего бутлоадера
    (образец — README Notifications/другие модули-источники; сам `NotificationsBootloader` виды **не**
    регистрирует). `send()` делает fail-fast `registry->get()` — незарегистрированный вид падает в момент Command.
  - `NotificationChannelDefaults::of(NotificationChannel ...)`, enum `{ Database, Push, Realtime }`.
  - Образец вида: `tests/Support/Notifications/FixtureNotificationTypeDefinition`.
- Каркас HTTP/CQRS (проверено): контроллер `final readonly`, инъекция `Handler` + `CommandBusInterface`/`QueryBusInterface`
  в метод, `#[Route]`, `#[Attribute(key:'authUserId')]` в `Filter` (наследует `AttributesFilter`),
  ресурсы `extends AbstractResource` с `fromEntity`/`from*`, ответы
  `DataResponse<T>`/`CollectionResponse<T>`/`PaginationResponse<T>` + `PaginationMetaResponse(nextCursor, limit)`,
  `EmptySuccessResponse`. Атрибуты `#[Transactional]`/`#[LogOperation]` из `GianTiaga\SpiralCqrs`.
  Cursor-пагинация: репозиторий принимает `cursor`+`limit`, Handler запрашивает `limit+1` и
  вычисляет `nextCursor` (образец `ListNotificationsHandler`). Auth-стек middleware на роуты —
  как у `AuthController::sessions` (`AuthTransportWithStorageMiddleware` + `AuthContextAttributeMiddleware` + `RequireAuthenticatedMiddleware`),
  прописывается **в каждом `#[Route]`** (группового auth для `api` нет). **Параметры пути — угловые скобки
  `<id>`, не `{id}`**; путь-id читается во `Filter` через `#[Route(key:'id')]` + `#[Assert\Uuid]`
  (образец `MarkNotificationReadFilter`). `TranslatorInterface::trans(id, params, domain, locale)`
  поддерживает явную локаль (`Translator.php:113`, паттерн `SendLoginCodeHandler`); `User.locale` — enum
  `Shared\Domain\Enum\Locale` (`->value()` → `'ru'`/`'en'`). Возврат скаляра из репозитория допустим
  только для `count`/`exists`/статуса (`rules.md:73`); списки — доменными коллекциями.
- **Транзакционная семантика (проверено):** вложенный `#[Transactional]`-dispatch через Bus создаёт
  SAVEPOINT внутри текущей транзакции (`TransactionalMiddleware` → `database->transaction()`, докблок
  `CompleteRegistrationHandler`), а не отдельную закоммиченную транзакцию. Несколько `run()` в одной
  транзакции допустимы.
- Регистрация модульных бутлоадеров — `Kernel.php` (~строки 140–146), `PostsBootloader` добавляется
  после `NotificationsBootloader`.
- Локалей `posts.php` нет (есть `en`, `ru`). Конфига `user.php` нет (есть `locale.php`, `media.php`).
- Перевод 4xx-исключений на границе берётся из ключа: домен = второй сегмент (`app.posts.*` → `posts.php`).
- Новых внешних зависимостей план не вводит — проверка версий пакетов не требуется.

## Принятые решения

Источник «research» = интерактивные ответы пользователя, зафиксированные в research
`2026-06-18_15-15_...` (раздел «Ответы на вопросы»). Источник «пользователь (этот запрос)»
= ответы на вопросы при запуске `eda-plan`. Источник «autonomous» = `decision_mode:
recommend_and_ask`, низкие ставки/прямое следствие правил.

1. **Маршруты — схема B, единый префикс `/api/v1/posts`** (комментарии под `/posts/comments/<id>/...`,
   лента под `/posts/user/<id>`). Источник: пользователь (этот запрос).
2. **Межмодульные сценарии включены в этот план** (`User`/`Media`/`Tags`). Источник: пользователь (этот запрос).
3. Запись создаётся сразу `Published` либо как черновик `Draft`; черновик публикуется отдельным
   действием. Источник: research.
4. Медиа-вложения проходят проверку «существует+владелец+`Ready`» и перевод в permanent. Источник: research.
5. Теги — find-or-create через `Tags`. Источник: research.
6. Упоминания — явный список `userId` от клиента (без парсинга `@nick`). Источник: research.
7. Репост = цитата: новый `Post` с заполненным `original` + свой текст/медиа. Источник: research.
8. Уведомления — 7 раздельных видов. Источник: research.
9. Модерация/админка, глобальная лента (Feed), лента по тегу, редактирование, «надгробие»
   удалённого комментария, FK на занятия/практики — в roadmap, вне этой итерации. Источник: research.
10. Лайк идемпотентен (check-then-act, редкий конкурентный дубль допустим как 500 по
    `rules.md:52`); репост множественный. Источник: research / autonomous.
11. Счётчики: `comments_count` = комментарии верхнего уровня, `replies_count` = прямые ответы;
    удаление зеркалит инкремент; повторное удаление — no-op (guard `Comment::isDeleted()`). Источник: research / autonomous.
12. Self-action не уведомляет (`recipient == actor` → `send()` не вызывается). Источник: research / autonomous.
13. Дедуп по получателю в рамках одного действия по приоритету `mention` > `comment_reply` >
    `post_commented`. Источник: research / autonomous.
14. Ответ уведомляет только автора родительского комментария (`comment_reply`); автор записи
    получает `post_commented` только на комментарии верхнего уровня. Источник: research / autonomous.
15. Guard допустимости действия по состоянию цели: лайк/коммент/репост — только над
    `Published` и не удалённой/не `Blocked` записью; ответ — только на не удалённый комментарий
    не удалённой записи; `publish()` — только владелец и только из `Draft`. Источник: research / autonomous.
16. Права: удаление/публикация своей записи и удаление своего комментария — `entity.userId == authUserId`,
    иначе `ForbiddenException`. Источник: research / autonomous.
17. **Граница транзакции — единая и атомарная** (исправлено по мета-ревью, проверено по коду). Вложенный
    `#[Transactional]`-dispatch `Tags ResolveTags` создаёт SAVEPOINT внутри транзакции `CreatePostHandler`-а,
    а не отдельную закоммиченную транзакцию; `Media MakeMediaPermanent` (без `#[Transactional]`) своим
    `run()` флашит изменения **внутри** той же внешней транзакции. Итог: создание записи атомарно — при
    откате теги и permanent-медиа откатываются **вместе** с записью, осиротевших записей не возникает.
    Множественные `run()` в рамках одной транзакции допустимы. (Research ошибочно считал эти вызовы
    отдельными транзакциями — отсюда мнимая проблема осиротевших данных.) Источник: autonomous (исправление research по коду).
18. Лимит упоминаний на запись/комментарий — **≤50**; лимит тегов — **≤20**; лимит медиа-вложений —
    **≤10**. Применяется в `Filter` (`#[Assert\Count]`). Источник: autonomous (research предлагал лимит упоминаний).
19. Дефолтные каналы видов: `post_mention`/`comment_mention`/`post_commented`/`comment_reply` →
    `Database`+`Push`+`Realtime`; `post_like`/`post_repost`/`comment_like` → `Database`+`Push`.
    Настраиваемо, на архитектуру не влияет. Источник: autonomous (research).
20. **Дефолтный URL аватара** отдаётся через новый типизированный конфиг модуля `User`
    (`app/config/user.php` + `UserConfig` DTO в `Shared/Infrastructure/Configuration/User` +
    тест маппинга `ConfigMapper`), т.к. `env()` допустим только в конфигах и `NotificationActor::of()`
    требует непустой `avatarUrl`. Источник: autonomous (следствие правил «Typed config» и «env() только в конфигах»).
21. **Вложения `lesson`/`practice` отложены** (нет модулей для валидации; FK в roadmap). В этой
    итерации `attachmentType ∈ {None, Media}`; `Post::create` получает `PostLesson::none()`/`PostPractice::none()`.
    Источник: autonomous (следствие roadmap).
22. Текст уведомления рендерится **в локали получателя**: ключи `app.posts.notification.{вид}.{title|body}`
    в `app/locale/{lang}/posts.php`, перевод через `TranslatorInterface::trans(..., locale: $recipientLocale)`.
    Источник: research / autonomous.
23. **`Tags ResolveTags` — check-then-act**, а не insert+catch: `findByTexts` → создать недостающие
    `Tag::create` → `run()`; редкий конкурентный дубль по уникальному `tags.text` допустим как 500
    (`rules.md:52`), как и у лайков. (Уточнение research, который предлагал try-catch reread — он
    противоречит правилу «запрет try-catch в Handler-ах».) Источник: autonomous (следствие правил).
24. Тела ответов: создание записи/комментария → `200 DataResponse<*Resource>`; лайк/снятие лайка,
    публикация, удаление → `EmptySuccessResponse` (как в существующих контроллерах, без 201/204/409).
    «Уже опубликовано»/«уже удалено»/«повторный лайк» → идемпотентный no-op с успешным ответом.
    Источник: autonomous (следствие существующей конвенции и отсутствия 409-типа исключения).
25. **Синтаксис route-параметров — `<id>`** (Spiral), не `{id}`; путь-id читается во `Filter` через
    `#[Route(key:'id')]` + `#[Assert\Uuid]` (образец `MarkNotificationReadFilter`), чтобы кривой id давал
    `422`, а не `500` из доменного VO. Источник: autonomous (исправление по мета-ревью, проверено по коду).
26. **Медиа-вложение требует статус `Ready`** (обработанное). `CheckMediaAttachable`: нет → `404`, чужое →
    `403`, есть но не `Ready` → `422` (`ValidationException`); вызывается **до** `MakeMediaPermanent`,
    поэтому рассогласования кодов ошибок между ними нет. Источник: autonomous (мета-ревью).
27. **`#[LogOperation]` на всех Handler-ах**, включая межмодульные сценарии фаз 1–2. Источник: autonomous (мета-ревью).

## Целевой алгоритм

**Запись действия (Command, например создание поста).**
HTTP → `Controller` (тонкий, `#[Route]` со схемой B + auth-middleware) → `Filter`
(`#[Attribute('authUserId')]` + `#[Post]`/`#[Assert]`) → `Command` DTO → `CommandBus` →
`Handler::handle` (`#[Transactional]`, `#[LogOperation]`):
1. Guard по состоянию цели и правам (ранний `throw` `ForbiddenException`/`NotFoundException`/`ValidationException`).
2. До финального `run()`, **в той же транзакции** (SAVEPOINT/общий flush, решение 17):
   `Tags ResolveTags` (тексты → `TagId[]`), на каждый `mediaId` — `Media CheckMediaAttachable`
   (валидация) + `Media MakeMediaPermanent`.
3. Профили: актор (`authUserId`) и получатели через `User GetUserPublicProfile(s)`
   (имя+аватар актора, локаль получателей; для упоминаний — проверка существования).
4. Доменные операции: `VO → Entity::create()/доменный метод` (счётчики — доменными методами).
5. Стейджинг уведомлений: на каждого получателя (кроме self, после дедупа) —
   `NotificationSenderContract::send($recipient, NotificationContent)`.
6. `EntityManager::persist(...)` + один `EntityManager::run()` (записи `Posts` + стейджинг уведомлений).
7. `Controller` маппит результат в `Resource` → `DataResponse`/`EmptySuccessResponse`.

**Чтение (Query).** HTTP → `Controller` → `Filter` → `Query` DTO → `QueryBus` →
`Handler::handle` (без транзакции, `#[LogOperation]`): репозиторий своего модуля с фильтрами
(soft-delete, статус, верхний уровень, курсор) → сборка read-model `*View` обогащением из
`User`/`Media`/`Tags` (`GetUserPublicProfile(s)`, `GetMediaUrl`, `GetTags`) + флаг `likedByMe`
(`existsBy...AndUser`) → `Resource::fromView` → `DataResponse`/`PaginationResponse`.

**Сборка `NotificationContent`** (helper `Posts/Application/Notification/NotificationContentBuilder`):
`type` = экземпляр вида; `title`/`body` = `NotificationTitle/Body::fromString` из переведённых в
локали получателя ключей `app.posts.notification.*`; `action` = `NotificationAction::linkTo($type, $id->value())`;
`actor` = `NotificationActor::of($actorUserId, $actorProfile->name, $actorProfile->avatarUrl)`.

## Контракты реализации

### Данные и БД

Схема БД не затрагивается, миграции не создаются. Добавляются только методы/фильтры **чтения**
репозиториев (query-level через `when()`):

- `PostRepository::findVisibleByUserId(UserId, ?PostStatus, ?PostId cursor, int limit): PostCollection` —
  **новый** метод (существующий `findByUserId` не трогаем — у него докблок «soft-deleted решает вызывающий»
  и тест `PostRepositoryTest`): скрывает soft-deleted (`deletion IS NULL`); статус — параметр (Handler
  передаёт `Published` для чужой ленты, `null` для своей).
- `CommentRepository::findTopLevelByPostId(PostId, ?CommentId cursor, int limit): CommentCollection` —
  **новый** метод: `parent_comment_id IS NULL` + не удалённые, до `limit`/курсора (существующий
  `findByPostId` не трогаем — у него другие потребители).
- `CommentRepository::findReplies(...)` — добавить фильтр «не удалённые» (правка метода + его теста).
- `TagRepository::findByIds(TagId ...$ids): TagCollection` — новый read-метод для `GetTags`.
- `PostLikeRepository::findByUserAndPostIds(UserId, PostId ...$postIds): PostLikeCollection` и
  `CommentLikeRepository::findByUserAndCommentIds(UserId, CommentId ...$commentIds): CommentLikeCollection` —
  батч-выборка лайков пользователя (возвращают доменные коллекции, **не** `list<string>` — `rules.md:73`);
  флаг `likedByMe` собирает Handler, без N+1 в листингах.

### API и внешние контракты

Все роуты — `methods` по таблице, group `api`, auth-middleware-стек прописывается **в каждом `#[Route]`**
(как `AuthController::sessions`). `Filter`: `#[Attribute('authUserId')]` для текущего пользователя,
`#[Route(key:'id')]` + `#[Assert\Uuid]` для path-id, `#[Post]`/`#[Query]` для тела/строки с
`#[Assert\Length]` (PostText ≤5000, CommentText ≤2000) и `#[Assert\Count]`-лимитами (решение 18), чтобы
превышение/кривой id давали `422`, а не `500` из VO. Ответы — типизированные `Resource` в
`DataResponse`/`PaginationResponse`/`EmptySuccessResponse`.

```text
ЗАПИСИ (Command)
  POST   /api/v1/posts                       CreatePost     → DataResponse<PostResource>
  POST   /api/v1/posts/<id>/publish          PublishPost    → DataResponse<PostResource>
  POST   /api/v1/posts/<id>/repost           RepostPost     → DataResponse<PostResource>
  DELETE /api/v1/posts/<id>                  DeletePost     → EmptySuccessResponse
  POST   /api/v1/posts/<id>/like             LikePost       → EmptySuccessResponse
  DELETE /api/v1/posts/<id>/like             UnlikePost     → EmptySuccessResponse
КОММЕНТАРИИ (Command)
  POST   /api/v1/posts/<id>/comments         CommentPost    → DataResponse<CommentResource>
  POST   /api/v1/posts/comments/<id>/replies ReplyComment   → DataResponse<CommentResource>
  DELETE /api/v1/posts/comments/<id>         DeleteComment  → EmptySuccessResponse
  POST   /api/v1/posts/comments/<id>/like    LikeComment    → EmptySuccessResponse
  DELETE /api/v1/posts/comments/<id>/like    UnlikeComment  → EmptySuccessResponse
ЧТЕНИЕ (Query)
  GET    /api/v1/posts/<id>                   GetPost           → DataResponse<PostResource>
  GET    /api/v1/posts/user/<id>             GetUserFeed       → PaginationResponse<PostResource>
  GET    /api/v1/posts/<id>/comments         GetPostComments   → PaginationResponse<CommentResource>
  GET    /api/v1/posts/comments/<id>/replies GetCommentReplies → PaginationResponse<CommentResource>
```

Параметры и ошибки (ключевое):

- `CreatePost` body: `text?:string`, `draft?:bool=false`, `attachmentType?:string('none'|'media')`,
  `mediaIds?:list<uuid>` (≤10), `tags?:list<string>` (≤20), `mentions?:list<uuid>` (≤50). Ошибки:
  `422` (валидация/несуществующее упоминание), `404` (медиа нет/не `Ready`), `403` (медиа чужое).
- `RepostPost` `<id>` — целевая запись; body как у `CreatePost` (text/media/tags/mentions). `404`/`403` если
  цель невидима; уведомление `posts.post_repost` автору оригинала (не self).
- `PublishPost` — `403` не владелец, `404` нет/Blocked/удалена, успешный no-op если уже `Published`.
- `DeletePost` — `403`/`404`; если запись-репост → `decrementReposts` у оригинала; идемпотентно.
- `LikePost`/`UnlikePost` — идемпотентно; `404`/`403` если запись невидима; уведомление `posts.post_like` (не self).
- `CommentPost` body: `text:string` (обяз.), `mentions?:list<uuid>` (≤50). Уведомления `posts.post_commented`
  (автору записи, не self) + `posts.comment_mention` (упомянутым). `incrementComments`.
- `ReplyComment` body как у `CommentPost`; `incrementReplies` родителя; уведомление `posts.comment_reply`
  (автору родителя, не self) + `posts.comment_mention`.
- `DeleteComment` — `403`/`404`; зеркалит счётчик (верхний → `decrementComments` записи; ответ →
  `decrementReplies` родителя); идемпотентно.
- `LikeComment`/`UnlikeComment` — идемпотентно; уведомление `posts.comment_like` (не self).
- Чтение: `GetPost` — `404` если невидима (чужой `Draft`/`Blocked`/удалена). `GetUserFeed` — свои
  черновики видны только владельцу; иначе только `Published`. Листинги — cursor (`cursor?:uuid`, `limit?:int≤100`).

**Новые публичные сценарии смежных модулей** (вызываются Posts через Bus):

```text
User/Application/Query/GetUserPublicProfile
  GetUserPublicProfileQuery(userId:string) → UserPublicProfileView{userId:string, name:string, avatarUrl:string, locale:string}
  Handler: name = User.name->value(); locale = User.locale->value(); avatarUrl = Media GetMediaUrl(avatar_media_id),
  а если аватар пуст ИЛИ медиа недоступно (удалено/не Ready) → UserConfig.defaultAvatarUrl. avatarUrl
  ВСЕГДА непустой (иначе NotificationActor::of() даёт 500). NotFoundException если пользователя нет.


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
/bin/zsh -lc 'git status --porcelain' in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
 M .env.sample
 M app/locale/en/user.php
 M app/locale/ru/user.php
 M app/src/Modules/Posts/Repository/CommentLikeRepository.php
 M app/src/Modules/Posts/Repository/CommentRepository.php
 M app/src/Modules/Posts/Repository/PostLikeRepository.php
 M app/src/Modules/Posts/Repository/PostRepository.php
 M app/src/Modules/Tags/Repository/TagRepository.php
 M app/src/Modules/User/Repository/UserRepository.php
 M app/src/Shared/Infrastructure/Framework/Kernel.php
 M docs/rules.md
 M public/openapi/openapi.yml
 M tests/Feature/Modules/Notifications/Application/NotificationUseCaseTest.php
 M tests/Feature/Modules/Notifications/Http/NotificationHttpTest.php
 M tests/Kernel/Shared/Infrastructure/Configuration/ConfigShapeTest.php
?? .phpactor.json
?? app/config/user.php
?? app/locale/en/posts.php
?? app/locale/ru/posts.php
?? app/src/Modules/Media/Application/Query/CheckMediaAttachable/
?? app/src/Modules/Media/Application/Query/FindMediaUrl/
?? app/src/Modules/Posts/Application/
?? app/src/Modules/Posts/Infrastructure/Bootloader/
?? app/src/Modules/Posts/Presentation/
?? app/src/Modules/Tags/Application/
?? app/src/Modules/User/Application/Dto/UserPublicProfileCollection.php
?? app/src/Modules/User/Application/Dto/UserPublicProfileView.php
?? app/src/Modules/User/Application/Profile/
?? app/src/Modules/User/Application/Query/GetUserPublicProfile/
?? app/src/Modules/User/Application/Query/GetUserPublicProfiles/
?? app/src/Modules/User/Domain/Collection/
?? app/src/Shared/Infrastructure/Configuration/User/
?? docs/executions/2026-06-19_12-51_posts-application-presentation.md
?? docs/plans/2026-06-18_17-42_posts-application-presentation.md
?? docs/reviews/2026-06-19_16-02_posts-application-presentation.md
?? docs/reviews/2026-06-19_16-02_posts-application-presentation_cli_meta.md
?? tests/Feature/Modules/Media/Application/CheckMediaAttachableHandlerTest.php
?? tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php
?? tests/Feature/Modules/Posts/Http/
?? tests/Feature/Modules/Posts/Repository/LikeBatchRepositoryTest.php
?? tests/Feature/Modules/Tags/Application/
?? tests/Feature/Modules/Tags/TagsApplicationTestCase.php
?? tests/Feature/Modules/User/Application/GetUserPublicProfileHandlerTest.php
?? tests/Feature/Modules/User/Application/GetUserPublicProfilesHandlerTest.php
?? tests/Feature/Modules/User/Application/UserApplicationTestCase.php
?? tests/Kernel/Modules/Posts/
?? tests/Kernel/Shared/Infrastructure/Configuration/UserConfigTest.php

codex
Контекст уже показывает важное: само ревью помечает пункт про `204` как «требует проверки», а пользователь просит проверить конкретно существующую практику Auth. Теперь читаю фактический код и спецификацию, включая незакоммиченные файлы, потому что `git diff HEAD` их не показывает.
exec
/bin/zsh -lc "sed -n '261,620p' docs/rules.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
exec
/bin/zsh -lc "sed -n '261,620p' docs/arch.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
Repository     -> Domain, Cycle ORM
Infrastructure -> Contract своего модуля, framework/runtime libraries, external libraries
Domain         -> PHP standard library, свой Domain, Shared/Domain
Shared         -> общий доменный и инфраструктурный код без привязки к одному модулю
```

Осознанное исключение: типизированный `TypedConfig` из
`Shared/Infrastructure/Configuration` может инжектиться напрямую в Application-Handler,
когда сценарию нужны инфра-дефолты (staging-TTL, пороги, размеры, драйвер). Пример —
`MediaConfig` в `RequestMediaUploadHandler` модуля `Media`.
Формально `Shared/Infrastructure` не входит в список зависимостей Application выше, но
`TypedConfig` — это не технический сервис с поведением и не зависимость от чужого модуля:
правила (`rules.md` «Typed config для каждого config-файла») и эта же `arch.md`
(«Configuration → app/config → Shared/Infrastructure/Configuration») предписывают единое
размещение всех config-DTO в `Shared/Infrastructure/Configuration`. Оборачивать такой
config-DTO в Application-`*Contract` и привязывать его в бутлоадере означало бы создать
pass-through-обёртку над `TypedConfig` ради формального списка — это запрещённый паттерн
(ср. «Без pass-through typecast-обёрток») и сделало бы модуль единственным, кто прячет
собственный typed-config за контрактом. Поэтому прямая инъекция `TypedConfig` в Application
допускается явно. Если Application нужен именно технический сервис с поведением (S3,
процессор, внешний клиент) — он по-прежнему идёт через `Application/Contract` + реализацию
в `Infrastructure`, без исключений.

## Взаимодействие слоёв

### Поток HTTP-команды

```text
HTTP Request
  -> RoadRunner
    -> Spiral HTTP middleware
      -> Modules/{Module}/Presentation/Http/Controller
        -> Modules/{Module}/Presentation/Http/Filter
        -> Modules/{Module}/Application/Command DTO
        -> CommandBus::dispatch(command, handler)
          -> Handler::handle(Command)
            -> Modules/{Module}/Domain ValueObject
            -> Modules/{Module}/Domain Entity
            -> Modules/{Module}/Repository
            -> EntityManager::run()
        -> Modules/{Module}/Presentation/Http/Resource
        -> Response
      -> JSON Response
```

### Поток HTTP-запроса

```text
HTTP Request
  -> RoadRunner
    -> Spiral HTTP middleware
      -> Modules/{Module}/Presentation/Http/Controller
        -> Modules/{Module}/Presentation/Http/Filter
        -> Modules/{Module}/Application/Query DTO
        -> QueryBus::dispatch(query, handler)
          -> Handler::handle(Query)
            -> Modules/{Module}/Repository read method
        -> Modules/{Module}/Presentation/Http/Resource
        -> Response
      -> JSON Response
```

## API-документация

API-документация генерируется автоматически из типизированного HTTP-слоя:
контроллеров, Filter DTO, Response DTO, Resource-классов, enum-ов и API
attributes в `Modules/{Module}/Presentation/Http`. OpenAPI-спецификация строится из кода, а
Swagger используется как UI для её просмотра.

Генератор OpenAPI живёт в переносимом Composer-пакете `packages/spiral-openapi` с
namespace `GianTiaga\SpiralOpenApi`. Приложение не содержит логики статического разбора:
оно только собирает типизированный `OpenApiConfig`, задаёт mapping базовых
response wrappers и вызывает пакет через команду `openapi:generate`. YAML
записывается в `public/openapi/openapi.yml`, а Swagger UI по `/api/docs` читает
тот же файл через route `/api/docs/openapi.yml`.

Генератор сканирует все модули приложения: `openapi.sourcePath` = `app/src/Modules`,
`openapi.apiNamespace` = `App\Modules`. Он отбирает классы по префиксу namespace и строит
операции только из публичных методов с атрибутом `#[Route]`. Поэтому любой модуль с
`#[Route]`-контроллером автоматически попадает в публичную спецификацию. Если контроллер не
должен публиковаться (внутренний/служебный эндпоинт), его исключают через
`#[OpenApi(ignore: true)]`, а не через сужение области сканирования.

`packages/spiral-openapi` использует Spiral translator только для стандартных описаний
response по ключам `gian_tiaga.spiral_openapi.successful_response` и
`gian_tiaga.spiral_openapi.api_error`. Описания из `#[OpenApi(description: ...)]` и
PHPDoc остаются текстом приложения. YAML статический, поэтому язык выбирается во
время команды генерации.

## API-ошибки

Общая обработка API-ошибок живёт в переносимом Composer-пакете
`packages/spiral-api-errors` с namespace `GianTiaga\SpiralApiErrors`. Пакет зависит от
`packages/spiral-openapi`, потому что возвращает `ErrorResponse` и
`ValidationErrorResponse`.

Общие доменные исключения остаются в приложении в `App\Shared\Domain\Exception` и
расширяют `\DomainException`. Переводимые 4xx (`NotFoundException`, `ForbiddenException`,
`ValidationException`, `AuthenticationException`) расширяют его через общий абстрактный
`DomainTranslatableException`, который реализует
`GianTiaga\SpiralApiErrors\Exception\TranslatableException` и несёт ключ перевода с
параметрами вместо готовой строки. `InvalidDomainValueException` (500) переводимым не
является. Ожидаемые клиентские ошибки с кодами 4xx
`GianTiaga\SpiralApiErrors\Interceptor\ApiExceptionInterceptor` превращает в JSON
`{"message":"...","code":...}` без логирования; для `TranslatableException` сообщение
переводится на границе в локали текущего запроса (`translationKey()` в домене
`translationDomain()`), иначе берётся `getMessage()`. Каталоги переводов приложения
разбиты по модулям: `translationDomain()` выводит домен (= файл `app/locale/{lang}/{domain}.php`)
из второго сегмента ключа — `app.media.*` → `media.php`, `app.system.*` → `system.php`,
ключи без модульного сегмента — домен `messages`. Доменные исключения без
поддерживаемого 4xx-кода, включая `InvalidDomainValueException`, считаются
внутренними ошибками: HTTP-ответ получает обычное сообщение 500, а исходное
сообщение исключения пользователю не отдаётся. Ошибки Spiral Filter рендерятся через
`GianTiaga\SpiralApiErrors\Filter\ApiValidationErrorsRenderer` по ключу
`gian_tiaga.spiral_api_errors.validation_error` в JSON
`{"message":"Validation error","code":422,"errors":[...]}` или
`{"message":"Ошибка валидации","code":422,"errors":[...]}` в зависимости от
текущего locale.

`ApiExceptionInterceptor` работает только внутри цепочки controller/action.
Ненайденные маршруты возникают раньше controller/action, поэтому их обрабатывает
`GianTiaga\SpiralApiErrors\Middleware\RouteNotFoundMiddleware` в глобальной HTTP-цепочке.
Он переводит ключ `gian_tiaga.spiral_api_errors.route_not_found` и возвращает JSON
`{"message":"Route not found.","code":404}` или
`{"message":"Маршрут не найден.","code":404}` в зависимости от текущего locale.
Ошибки bootstrap и middleware, не связанные с router 404, остаются в зоне
стандартного Spiral error handler.

`packages/spiral-api-errors` и `packages/spiral-openapi` только читают текущий locale Spiral
translator. Выбор языка пользователя реализован в request-слое приложения:
`App\Shared\Infrastructure\Framework\Middleware\LocaleMiddleware` определяет локаль запроса
из заголовка `Accept-Language`, пересечённого с белым списком `LocaleConfig.supported`
(иначе `LocaleConfig.default`), и выставляет её в Spiral translator до контроллера и до
`RouteNotFoundMiddleware`. Для не-HTTP контекстов (queue/console/Temporal) per-request
локали нет — там перевод на границе не выполняется, исключение несёт ключ.

### Поток консольной команды

```text
Console command
  -> input arguments/options
  -> Modules/{Module}/Application/Command DTO
  -> CommandBus::dispatch(command, handler)
  -> Handler::handle(Command)
  -> console output
```

Console command не содержит бизнес-логику. Он только читает CLI-ввод,
создаёт Command/Query и выводит результат.

### Поток задачи очереди

```text
Queue payload
  -> Modules/{Module}/Presentation/Job handler
  -> Payload DTO
  -> Modules/{Module}/Application/Command DTO
  -> CommandBus::dispatch(command, handler)
  -> Handler::handle(Command)
```

Job handler - технический адаптер очереди. Бизнес-сценарий находится в
Application Handler.

### Поток Temporal

```text
Temporal Workflow / Activity
  -> Modules/{Module}/Presentation/Temporal adapter
  -> Modules/{Module}/Application Command или Query
  -> Bus
  -> Handler
```

Temporal workflow/activity не становится доменным слоем. Это внешний runtime
adapter, как HTTP controller или queue job.

## Правила CQRS

CQRS используется на уровне use-case-ов: Command изменяет состояние, Query читает
данные. Каждый use-case живёт в отдельной папке действия. Пока в модуле одна
область (Area), действие лежит прямо в корне:
`Modules/{Module}/Application/Command/{Action}` или
`Modules/{Module}/Application/Query/{Action}`. Уровень области добавляется только
когда областей становится несколько — тогда действия группируются по областям:
`Modules/{Module}/Application/Command/{Area}/{Action}` или
`Modules/{Module}/Application/Query/{Area}/{Action}`.

Command flow:

```text
Command DTO
  -> CommandBus::dispatch(command, handler)
  -> Handler::handle(Command)
  -> Domain types
  -> Entity
  -> Repository
  -> EntityManager::run()
```

Query flow:

```text
Query DTO
  -> QueryBus::dispatch(query, handler)
  -> Handler::handle(Query)
  -> Repository read method
  -> Entity / typed collection / Result DTO / PaginatedResult<T>
```

Command Handler создаёт VO, enum и доменные коллекции до передачи данных в
Entity. Примитивы не передаются в `Entity::create()` и доменные методы Entity.

Command Handler возвращает Entity, Result DTO или `void`. Query Handler
возвращает Entity, типизированную коллекцию, Result DTO или `PaginatedResult<T>`.
Query не изменяет состояние и не оборачивается в транзакцию. Если Query
Handler помечен `#[Transactional]`, это ошибка контракта.

Data Grid не используется как бизнесовый Query-слой. Фильтрация и сортировка в
Query Handler должны быть явными.

## Архитектура шины

Инфраструктура шины живёт в локальном Composer-пакете `packages/spiral-cqrs` с namespace
`GianTiaga\SpiralCqrs`. Приложение подключает `GianTiaga\SpiralCqrs\CommandBusInterface` и
`GianTiaga\SpiralCqrs\QueryBusInterface` через Spiral DI-контейнер. Прикладные Command,
Query и Handler остаются в модулях приложения.

Bus принимает DTO и first-class callable на `Handler::handle(...)`. Return type
берётся из `Handler::handle()`, поэтому PHPStan и IDE видят точный тип
результата без ручного приведения.

```php
/**
 * @template TCommand of object
 * @template TResult
 * @param TCommand $command
 * @param callable(TCommand): TResult $handler
 * @return TResult
 */
public function dispatch(object $command, callable $handler);
```

```text
CommandBus: Command DTO -> Handler::handle(Command)
QueryBus:   Query DTO -> Handler::handle(Query)
```

`#[Transactional]` на `Handler::handle()` включает транзакцию для Command. Если
атрибута нет, Command выполняется без транзакции. `#[NonTransactional]` не
используется.

`#[LogOperation]` на `Handler::handle()` включает debug-лог старта и времени
выполнения операции. Если атрибута нет, operation-log не пишется.

CQRS-bus не проверяет конкретные классы атрибутов. Он собирает все атрибуты
`Handler::handle()`, которые наследуются от
`GianTiaga\SpiralCqrs\Attribute\HandlerMiddlewareAttribute`, берёт из них middleware-класс
и создаёт middleware через контейнер. Зависимости вроде `LoggerInterface` или
`DatabaseInterface` получает сам middleware через constructor injection, а не
executor.

Lifecycle hooks и after-commit callbacks не входят в CQRS-bus. Внешние
побочные эффекты идут через transactional outbox.

## События и outbox

События, которые приводят к внешним побочным эффектам, публикуются через
transactional outbox. К таким эффектам относятся сообщения в Centrifugo, email,
push-уведомления, webhooks и любые интеграции, которые нельзя выполнять до
финального commit-а бизнес-транзакции.

Outbox используется не только для внешних интеграций, но и для внутренних
отложенных шагов, которым нужна гарантированная доставка после commit-а. Пример —
асинхронная обработка медиа: `Media` Application Handler в одной транзакции
переводит медиа в `uploaded` и кладёт `MediaUploaded` в outbox, а relay запускает
тяжёлую обработку (перекладка оригинала, конверсии на Imagick) уже после commit-а.
Определяющий признак — требование «надёжно выполнить после commit», а не то,
внешний эффект или внутренний.

```text
HTTP / Console / Job / Temporal
  -> Modules/{Module}/Application Command Handler
    -> Domain Entity / Repository
    -> Modules/Outbox/Application/Contract/OutboxEventStoreContract::add(IntegrationEvent DTO)
    -> EntityManager::run()
  -> commit транзакции, если Handler помечен #[Transactional]

Modules/Outbox relay
  -> забирает pending outbox-события
  -> кладёт задачу в RabbitMQ
  -> RoadRunner jobs consumer запускает Job
  -> общий queue interceptor помечает событие handled / failed
```

Application Handler фиксирует интеграционное событие как факт завершённого
use-case-а. Например, после создания поста Handler сохраняет `Post` и добавляет
`PostCreated` в outbox в той же транзакции. Он не вызывает Centrifugo, email или
другие внешние сервисы напрямую.

`OutboxEventStoreContract::add()` только ставит outbox-событие на сохранение.
Handler вызывает его до финального `EntityManager::run()`, чтобы доменное
изменение и outbox-событие записались одним flush внутри одной транзакции. Если
outbox-событие добавлено после `EntityManager::run()`, Handler обязан явно
вызвать ещё один `EntityManager::run()` до выхода из транзакции.

Публичная граница outbox для других модулей находится в Application-слое:
интеграционное событие реализует `Application\Message\OutboxMessage`, а
`OutboxEventStoreContract::add()` возвращает Application DTO с идентификатором
сохранённого outbox-события. Domain-типы outbox остаются внутренней моделью
модуля.

Outbox-событие - сериализуемый DTO с camelCase-полями. Payload не передаётся как
ассоциативный массив. DTO должен содержать только данные, нужные подписчикам или
publisher-у: идентификаторы, тип события, время создания и минимальный набор
данных для публикации.

`Domain` не знает про outbox, queue, Centrifugo и transport-level события.
Доменная модель может выражать инварианты и состояние, но решение "это надо
опубликовать наружу" принимает Application use-case.

Spiral events можно использовать для локальных in-process расширений, которые не
требуют гарантированной доставки и не зависят от commit-а транзакции. Для
пользовательских уведомлений и интеграций они не являются основным механизмом.

Outbox relay является техническим входом: он забирает событие и кладёт Job в
RabbitMQ. Job выполняет внешнее действие после commit-а. Общий queue interceptor
меняет статусы outbox и отвечает за обработку дублей, retry-сценарии и
логирование инфраструктурных ошибок.

Relay сам управляет сохранением технических переходов outbox-события:
`publishing`, `queued`, publish-failure и ошибки queue Job фиксируются после
commit-а бизнес-сценария. Поэтому `outbox:relay` считается инфраструктурным
orchestrator-ом, а не обычным Application Handler-ом с бизнес-записью.

Текущий relay рассчитан на один постоянный процесс `outbox:relay --loop`.
Параллельные relay-процессы не запускаются, пока для выборки событий не будет
добавлен отдельный безопасный контракт `SKIP LOCKED`.

Реальные Job для email, push, Centrifugo и webhooks должны быть идемпотентными
по `outboxId`. Если внешний сервис поддерживает idempotency key, использовать
`outboxId`.

## Правила доменной модели

`Domain` модуля содержит Entity, ValueObject, Enum, доменные коллекции и
доменные сервисы. Общие исключения и трейты лежат в `Shared\Domain`.
Domain не зависит от HTTP, Application Handler, Repository, framework, request
и config.

Entity создаётся через `create()`, меняется через доменные методы и не содержит
доменные примитивы.

```text
single value -> ValueObject или enum
many values  -> typed domain collection
```

Свойства Entity, `create()` и доменные методы не принимают `string`, `int`,
`float`, `bool`, `array` или голый `Collection` для доменных данных. `bool`

exec
/bin/zsh -lc "sed -n '281,620p' docs/plans/2026-06-18_17-42_posts-application-presentation.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
User/Application/Query/GetUserPublicProfiles  (батч)
  GetUserPublicProfilesQuery(userIds:list<string>) → UserPublicProfileCollection (extends Illuminate Collection)
  Для списков (авторы ленты/комментариев) и проверки упоминаний (вызывающий сверяет
  запрошенные vs найденные; недостающие → ValidationException у Posts).

Media/Application/Query/CheckMediaAttachable
  CheckMediaAttachableQuery(mediaId:string, ownerUserId:string) → MediaAttachableResult{mediaId}
  «существует И принадлежит ownerUserId И статус Ready»: NotFoundException (нет) → 404,
  ForbiddenException (чужое) → 403, ValidationException (есть, но не Ready) → 422. Вызывается ДО
  MakeMediaPermanent, поэтому тот всегда получает Ready. MakeMediaPermanent переиспользуется как есть.

Tags/Application/Command/ResolveTags
  ResolveTagsCommand(texts:list<string>, creatorUserId:string) → ResolveTagsResult{tagIds:list<string>}
  #[Transactional]; check-then-act: findByTexts → недостающие Tag::create + EntityManager::persist → один run();
  результат ПЕРЕСОБРАТЬ в порядке входных текстов (findByTexts через where in порядок не гарантирует);
  дубль текста по уникальному tags.text при гонке → 500 (rules.md:52).

Tags/Application/Query/GetTags
  GetTagsQuery(tagIds:list<string>) → TagTextCollection (id→текст), для ресурса записи.
```

**7 видов уведомлений** (`Posts/Application/Notification/*`, реализуют `NotificationTypeDefinition`,
регистрируются в `PostsBootloader`):

```text
posts.post_mention     posts.comment_mention   posts.post_commented   posts.comment_reply
posts.post_like        posts.post_repost       posts.comment_like
```

`action` (deep-link): `post`→`linkTo('post', postId)`, `comment`/`reply`/`comment_*`→`linkTo('comment', commentId)`.

## Фазы выполнения

### 1. Каркас модуля Posts, бутлоадер и 7 видов уведомлений

Цель: завести инфраструктуру модуля и фундамент уведомлений, на котором стоят остальные фазы.

Что сделать:
- Создать папки `Posts/Application`, `Posts/Presentation/Http/{Controller,Filter,Resource}`,
  `Posts/Application/Notification`, `Posts/Infrastructure/Bootloader`.
- `Posts/Infrastructure/Bootloader/PostsBootloader extends Bootloader`: метод
  `boot(NotificationTypeRegistryContract $typeRegistry)` вызывает `$typeRegistry->register(...)` с 7
  экземплярами видов (образец регистрации — README Notifications/другие модули-источники, **не**
  `NotificationsBootloader`, который виды не регистрирует). Зарегистрировать `PostsBootloader::class`
  в `Kernel.php` после `NotificationsBootloader::class`.
- 7 классов `*NotificationType` (`final readonly`, реализуют `NotificationTypeDefinition`): `code()`
  = `NotificationTypeCode::fromString('posts.<вид>')`, `defaultChannels()` = `NotificationChannelDefaults::of(...)`
  по решению 19.
- `Posts/Application/Notification/NotificationContentBuilder` — сервис сборки `NotificationContent`:
  на вход вид, профиль актора, локаль получателя, тип/`id` действия, параметры текста; рендерит
  `title`/`body` через `TranslatorInterface::trans(key, params, domain:'posts', locale:$recipientLocale)`
  по ключам `app.posts.notification.<вид>.{title|body}`, собирает `NotificationAction`/`NotificationActor`.
- Файлы переводов `app/locale/ru/posts.php` и `app/locale/en/posts.php`. Ключи хранят **полный путь**
  `app.posts.notification.<вид>.{title|body}` (домен выводится из второго сегмента → `posts.php`),
  плюс ключи сообщений доменных ошибок Posts (`app.posts.not_found`, `app.posts.forbidden`,
  `app.posts.validation_*` и т.п.) для переводимых 4xx-исключений.

Результат: `PostsBootloader` поднимается в ядре и регистрирует ровно 7 видов; `NotificationContentBuilder`
отдаёт валидный `NotificationContent` на тестовых данных; переводы доступны на ru/en.

Сценарии тестирования:
- Kernel-тест: контейнер поднимается; каждый из 7 кодов `posts.*` реально резолвится
  `NotificationTypeRegistryContract::get()` (не только присутствует в `all()`); каналы по умолчанию
  совпадают с решением 19.
- Unit/Kernel-тест `NotificationContentBuilder`: для каждого вида `title`/`body` непустые и переведены в
  заданной локали (ru и en дают разный текст); `action`/`actor` собраны корректно; пустой `avatarUrl`
  невозможен (используется дефолт из профиля).

Проверка: `make test`, `make phpstan` зелёные; новые тесты фазы проходят.

### 2. Межмодульные публичные сценарии (User, Media, Tags) + конфиг User

Цель: дать Posts легальные точки входа в чужие модули; без них уведомления, медиа и теги не собираются.

Что сделать:
- **User**: `app/config/user.php` + `UserConfig` (`Shared/Infrastructure/Configuration/User`, реализует
  `TypedConfig`, `configName()='user'`) с полем `defaultAvatarUrl` (через `env()` в конфиге; **валидировать
  непустоту** — пустой дефолт сорвёт `NotificationActor::of()` в 500) + тест маппинга `ConfigMapper`
  (образец `MediaConfigTest`/`LocaleConfigTest`). `GetUserPublicProfile`/`GetUserPublicProfiles` (батч) по
  контрактам выше; коллекция `UserPublicProfileCollection extends Illuminate\Support\Collection`. Все
  Handler-ы — `#[LogOperation]`. Кросс-модульные Query-Handler-ы — конкретные классы, контейнер Spiral
  автовайрит их в Handler-ы Posts без явных биндингов.
- **Media**: `Media/Application/Query/CheckMediaAttachable` (`Query`+`Handler`+`MediaAttachableResult`):
  `findById` → `NotFoundException` если нет, `ForbiddenException` если `uploadedById != ownerUserId`,
  `ValidationException` если есть, но не `isReady()` (решение 26). (`MakeMediaPermanent` не трогаем —
  переиспользуем.)
- **Tags**: `Tags/Application/Command/ResolveTags` (решение 23, `#[Transactional]`): `findByTexts` →
  недостающие `Tag::create` + `persist` → один `run()`; результат пересобрать **в порядке входных текстов**.
  И `Tags/Application/Query/GetTags`; `TagRepository::findByIds(TagId ...)` (read-метод).

Результат: каждый сценарий вызывается через Bus и возвращает описанный контракт; профиль всегда отдаёт
непустой `avatarUrl` и `locale`; `ResolveTags` идемпотентен по тексту; `CheckMediaAttachable` различает
404/403.

Сценарии тестирования:
- `GetUserPublicProfile`: есть аватар → реальный URL; нет аватара → дефолтный URL из конфига; нет
  пользователя → 404; `locale` отдаётся. Батч: порядок/полнота, отсутствующие исключены.
- `CheckMediaAttachable`: `Ready`+владелец → ok; не `Ready` → 422; чужое → 403; нет → 404.
- `ResolveTags`: новые тексты создаются; существующие переиспользуются (id стабилен); повтор текста
  в одном запросе не плодит дублей; порядок `tagIds` = порядку текстов. `GetTags`: id→текст,
  отсутствующие исключены. `UserConfig` mapping-тест + тест «пустой `defaultAvatarUrl` → ошибка на старте».

Проверка: `make test`, `make phpstan` зелёные.

### 3. Записи: создание, публикация, репост, удаление (Command + HTTP + интеграционные тесты)

Цель: сквозной слой работы с записью, включая медиа-вложения, теги, упоминания и уведомления
`post_mention`/`post_repost`.

Что сделать:
- `Application/Command/{CreatePost,PublishPost,RepostPost,DeletePost}` — `Command` DTO + `Handler`
  (`#[Transactional]`,`#[LogOperation]`). Логика по «Целевому алгоритму»: guard состояния/прав,
  вложенные вызовы (`ResolveTags`, `CheckMediaAttachable`+`MakeMediaPermanent`) внутри той же транзакции
  (решение 17 — атомарно), доменные методы и счётчики. `CreatePost`: `Post::create(...,
  PostLesson::none(), PostPractice::none(), PostOriginal::none())`, при медиа — `setMediaAttachment()` +
  `PostMedia::create`, `PostTag::create`, `PostMention::create` + `send(post_mention)` каждому
  упомянутому (не self). `RepostPost`: загрузить оригинал (`findById`, проверить видимость),
  `Post::create(..., PostOriginal::pointingTo($id->value()))`, `original->incrementReposts()`,
  `send(post_repost)` автору оригинала (не self). `DeletePost`: guard `PostDeletion::isDeleted()`
  (повторное удаление → no-op, без повторного `decrementReposts`); иначе `softDelete($now)` + для репоста
  `original->decrementReposts()`.
- `Presentation/Http`: `PostController` с методами роутов записей (схема B) + auth-middleware;
  `Filter` для каждого действия (`#[Attribute('authUserId')]`, `#[Post]`, `#[Assert\Count]`-лимиты
  решения 18, `attachmentType` через `#[Assert\Choice(['none','media'])]`); `PostResource extends
  AbstractResource` (+ вложенные `AuthorResource`, `PostMediaItemResource`, опциональный `original:
  PostResource|null`) и read-model `PostView`/`AuthorView`/`PostMediaItemView` в `Application`.
  `Handler` записи возвращает `PostView` (собранный из созданной сущности + известных профилей/URL).

Результат: все 6 роутов записей работают и проходят интеграционный тест; счётчики и уведомления
ведут себя по решениям 10–17; счётчики не уходят ниже нуля при повторных операциях; источник времени
`softDelete` — единый (DI `\DateTimeImmutable` / clock).

Сценарии тестирования (интеграционные на каждый роут + поведенческие):
- Создание `Published`/`Draft`; с медиа (валидное → permanent; чужое → 403; не `Ready` → 422; нет → 404);
  с тегами (новые/существующие); с упоминаниями (валидные → `post_mention` в outbox каждому, кроме
  self; несуществующий → 422; >50 → 422). Кривой path-id/превышение лимитов текста → 422.
- Публикация: владелец из `Draft` → `Published`; не владелец → 403; уже `Published` → no-op 200;
  удалённая/`Blocked` → 404.
- Репост: создаёт запись с `original`, `+1` к `reposts` оригинала, `post_repost` автору (не self);
  репост своей записи не уведомляет; цель невидима → 404.
- Удаление: владелец → soft-deleted; повторное → no-op; репост → `-1` reposts оригинала; не владелец → 403.

Проверка: `make test`, `make phpstan` зелёные; интеграционные тесты роутов записей зелёные.

### 4. Комментарии и реакции (Command + HTTP + интеграционные тесты)

Цель: сквозной слой комментариев, ответов и лайков с уведомлениями `post_commented`/`comment_reply`/
`comment_mention`/`post_like`/`comment_like` и дедупом.

Что сделать:
- `Application/Command/{LikePost,UnlikePost,CommentPost,ReplyComment,DeleteComment,LikeComment,UnlikeComment}`
  — `Command` DTO + `Handler` (`#[Transactional]`,`#[LogOperation]`). Идемпотентность лайков
  (`existsByPostAndUser`/`existsByCommentAndUser` → no-op), снятие лайка через `EntityManager::delete`.
  `CommentPost`: `Comment::create(..., CommentParent::none())` + `incrementComments` записи;
  `ReplyComment`: `CommentParent::pointingTo($parentId)` + `incrementReplies` родителя.
  Упоминания в комментариях → `CommentMention::create` + `send(comment_mention)`.
  Уведомления адресатам по решениям 12–14, **дедуп** перед `send()`: собрать кандидатов `(получатель, вид)`,
  на каждого получателя оставить один вид с наибольшим приоритетом (`mention` > `comment_reply` >
  `post_commented`), исключить self, затем `send()`.
  `DeleteComment`: `Comment::delete(CommentDeletedBy::by, CommentDeletedAt::at, CommentDeletionReason::none())`,
  зеркальный счётчик (верхний → запись, ответ → родитель), идемпотентно.
- `Presentation/Http`: `CommentController` (роуты комментариев/лайков схемы B) + auth-middleware;
  `Filter` для каждого действия; `CommentResource extends AbstractResource` (+ `AuthorResource`,
  `parentId`, `likedByMe`) и read-model `CommentView` в `Application`. `Handler` создания
  комментария/ответа возвращает `CommentView`.

Результат: 7 роутов комментариев/реакций работают и покрыты интеграционными тестами; счётчики
верхнего уровня и ответов раздельны; дедуп и self-suppression уведомлений соблюдены.

Сценарии тестирования (интеграционные на каждый роут + поведенческие):
- Комментарий верхнего уровня: `+1 comments_count`, `post_commented` автору (не self), `comment_mention`
  упомянутым; guard невидимой/удалённой записи → 404/403.
- Ответ: `+1 replies_count` родителя, `comment_reply` автору родителя (не self), автор записи
  `post_commented` на ответ **не** получает; ответ на удалённый комментарий → 404/403.
- Дедуп: автор записи упомянут в комментарии к своей записи → получает **один** `comment_mention`
  (приоритет mention), не `post_commented`.
- Лайки записи/комментария: идемпотентность (двойной лайк = один `*_like` и один инкремент),
  снятие отсутствующего = no-op, self-лайк не уведомляет.
- Удаление комментария: верхний → `-1 comments_count`; ответ → `-1 replies_count` родителя;
  повторное → no-op; не владелец → 403.

Проверка: `make test`, `make phpstan` зелёные; интеграционные тесты роутов фазы зелёные.

### 5. Чтение: запись, лента автора, комментарии, ответы (Query + HTTP + интеграционные тесты)

Цель: cursor-чтение с обогащением read-model из User/Media/Tags и флагом `likedByMe`, с фильтрами в репозиториях.

Что сделать:
- Доработать репозитории (см. «Данные и БД»): новый `findVisibleByUserId` (не менять `findByUserId`),
  новый `findTopLevelByPostId`, фильтр удалённых в `findReplies`, `TagRepository::findByIds`,
  батч-методы `findByUserAndPostIds`/`findByUserAndCommentIds` (возвращают коллекции лайков).
- `Application/Query/{GetPost,GetUserFeed,GetPostComments,GetCommentReplies}` — `Query` DTO + `Handler`
  (`#[LogOperation]`, без транзакции). Cursor `limit+1` (образец `ListNotificationsHandler`).
  Обогащение: авторы через `GetUserPublicProfile(s)` (в листингах — батч), URL медиа через
  `Media GetMediaUrl` на каждый `PostMedia`, тексты тегов через `Tags GetTags`, `likedByMe` через
  батч-методы лайков, данные оригинала для репоста. `GetPost`: guard видимости (чужой `Draft`/`Blocked`/
  удалённая → 404). `GetUserFeed`: свои черновики только владельцу (статус `null` если `authUserId==id`,
  иначе `Published`). `GetPostComments`: только верхний уровень. Возвращают `PostView`/`PostView[]+nextCursor`/
  `CommentView[]+nextCursor`.
- `Presentation/Http`: добавить read-методы в `PostController`/`CommentController` (роуты чтения схемы B) +
  auth-middleware; `Filter` чтения (`#[Attribute('authUserId')]`, `#[Query]` `cursor`/`limit` с
  `#[Assert\Uuid]`/`#[Assert\LessThanOrEqual(100)]`). Переиспользовать `PostResource`/`CommentResource`
  из фаз 3–4 (`fromView`), ответы `DataResponse`/`PaginationResponse`+`PaginationMetaResponse`.

Результат: 4 роута чтения работают и покрыты интеграционными тестами; листинги отдают ровно `limit`
строк с корректным `nextCursor`; soft-deleted и чужие черновики не утекают; read-model обогащён.

Сценарии тестирования (интеграционные на каждый роут + поведенческие):
- `GetPost`: своя/чужая `Published` с автором, URL медиа, тегами, счётчиками, `likedByMe`, оригиналом
  (для репоста); чужой `Draft`/`Blocked`/удалённая → 404.
- `GetUserFeed`: владелец видит свои черновики; посторонний — только `Published`; soft-deleted скрыты;
  пагинация по курсору отдаёт `limit` и `nextCursor`, последняя страница → `nextCursor=null`.
- `GetPostComments`: только верхний уровень (`parent IS NULL`), удалённые скрыты, cursor стабилен.
- `GetCommentReplies`: только ответы родителя, удалённые скрыты, cursor.

Проверка: `make test`, `make phpstan` зелёные; интеграционные тесты роутов чтения зелёные; общий прогон
покрытия (`make qa`/`test-coverage`) — 100%.

## Тесты

Стратегия: `after_each_phase`. Каждая фаза завершается своими тестами и зелёными `make test`/`make phpstan`;
фаза не считается готовой, пока тесты не проходят. Фазы 1–2 — Kernel/Unit-тесты сценариев и контрактов;
фазы 3–5 — **интеграционный тест на каждый HTTP-роут** (`rules.md:116`) плюс поведенческие тесты счётчиков,
идемпотентности, guard-ов и уведомлений (проверка стейджинга в outbox). Тесты приложения запускаются только
через Docker (`make test`); адрес `minio:9000` доступен лишь в Docker-сети. Итоговая цель — 100% покрытие
**всего проекта** (гейт `make qa`/`test-coverage` зелёный), не только новых строк.

Обязательные доп. кейсы (по мета-ревью): каждый из 7 видов резолвится `NotificationTypeRegistryContract::get()`
(не только `all()`); пустой `defaultAvatarUrl` → ошибка на старте; повторное удаление записи/комментария не
уводит счётчик в минус и не шлёт повторное уведомление; ответ на комментарий не порождает `post_commented`
автору записи; кривой path-id → 422; превышение лимитов текста/тегов/упоминаний/медиа → 422; медиа не `Ready`
при вложении → 422.

## Логирование

Стратегия: `debug_precise`. Каждый `Handler` помечается `#[LogOperation]` (debug-лог старта и времени).
Дополнительно — точечные `logger->debug` на ключевых развилках с контекстом-идентификаторами (camelCase):
guard-отказы (`forbidden`/`not found`/`already published`/`already deleted` — нормальный flow → DEBUG, не WARN),
идемпотентные no-op лайков, изменения счётчиков, стейджинг каждого уведомления (вид, получатель), результат
дедупа (кого схлопнули). Тексты логов — на русском,
без англицизмов; идентификаторы (`postId`, `commentId`, `recipientId`, `typeCode`) остаются техническими.
Бизнес-успехи остаются на DEBUG; INFO/WARN/ERROR не вводятся (нет ключевых бизнес-событий уровня INFO и
инфраструктурных сбоев в этих сценариях).

## Изменения после мета-ревью

### После моделей

- **+ Добавлено:** транзакционная семантика SAVEPOINT (решение 17 переписано: создание записи атомарно,
  «осиротевших тег/медиа» не бывает); синтаксис роутов `<id>` + чтение path-id во `Filter` через
  `#[Route(key:'id')]`+`#[Assert\Uuid]` (решение 25); коды ошибок `CheckMediaAttachable` 404/403/422 и
  требование `Ready` (решение 26); `#[LogOperation]` на всех Handler-ах (решение 27); валидация непустого
  `defaultAvatarUrl` и fallback аватара при недоступном медиа; персист новых тегов + пересборка порядка в
  `ResolveTags`; guard `isDeleted()` в `DeletePost`; явный алгоритм дедупа; доп. тест-кейсы (резолв вида
  через `get()`, счётчик не в минус, ответ не шлёт `post_commented`, кривой id/лимиты → 422).
- **~ Изменено:** паттерн регистрации видов — `boot(NotificationTypeRegistryContract)` модуля-источника,
  а не `NotificationsBootloader`; `findLikedPostIds(): list<string>` → батч-методы, возвращающие коллекции
  лайков (`rules.md:73`); `findByUserId`/`findByPostId` не переопределяем, а добавляем `findVisibleByUserId`/
  `findTopLevelByPostId` (есть тесты у существующих); ключи переводов хранят полный путь `app.posts.*`;
  `UserPublicProfileView` поля — строки (`name->value()`, `locale->value()`); 100% покрытие — всего проекта.
- **Отклонено:** «добавить `#[Transactional]` в `MakeMediaPermanentHandler`» — не нужно, его `run()`
  участвует во внешней транзакции через SAVEPOINT (решение 17). «Регистрировать кросс-модульные
  Query-Handler-ы биндингами в бутлоадерах» — Spiral автовайрит конкретные классы Handler-ов без биндингов.

## Документация и эксплуатация

- `app/config/user.php` + env-переменная дефолтного URL аватара (`UserConfig.defaultAvatarUrl`) — добавить
  в `.env`/`.env.sample` и описать в эксплуатации (значение зависит от окружения/CDN).
- Новые ключи переводов `app/locale/{ru,en}/posts.php` — поддерживать оба языка синхронно.
- После добавления роутов прогнать `php app.php openapi:generate` и проверить обновление
  `public/openapi/openapi.yml` (16 новых операций под `/api/v1/posts`).
- Уведомления требуют работающего `outbox:relay` (один экземпляр) — без него `send()` только стейджит,
  доставки не будет; учесть в релизе.
- `docs/roadmaps/posts.md` (вне этого плана): модерация/админка, Feed, лента по тегу/поиск тегов,
  редактирование записей/комментариев, «надгробие» удалённого комментария, FK на занятия/практики,
  вложения `lesson`/`practice`.

## Прогресс выполнения

Журнал: `docs/executions/2026-06-19_12-51_posts-application-presentation.md`

- [x] Шаг 1: Каркас модуля Posts, бутлоадер и 7 видов уведомлений
- [x] Шаг 2: Межмодульные публичные сценарии User, Media, Tags и конфиг User
- [x] Шаг 3: Записи: создание, публикация, репост, удаление
- [x] Шаг 4: Комментарии и реакции
- [x] Шаг 5: Чтение: запись, лента автора, комментарии, ответы
- [x] Финальная проверка: `make qa` (стиль + `make phpstan` + 100% покрытие) зелёный

exec
/bin/zsh -lc 'git diff HEAD -- app/src/Modules/Posts/Repository app/src/Modules/Tags/Repository app/src/Modules/User/Repository app/src/Shared/Infrastructure/Framework/Kernel.php app/locale/en/user.php app/locale/ru/user.php .env.sample public/openapi/openapi.yml tests/Feature tests/Kernel docs/rules.md' in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
exec
/bin/zsh -lc 'rg --files app/src/Modules/Posts app/src/Modules/Media/Application app/src/Modules/Tags app/src/Modules/User/Application tests/Feature/Modules/Posts tests/Feature/Modules/Media tests/Feature/Modules/User tests/Feature/Modules/Tags tests/Kernel/Modules/Posts | sort' in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
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
app/src/Modules/Media/Application/Query/CheckMediaAttachable/CheckMediaAttachableHandler.php
app/src/Modules/Media/Application/Query/CheckMediaAttachable/CheckMediaAttachableQuery.php
app/src/Modules/Media/Application/Query/CheckMediaAttachable/MediaAttachableResult.php
app/src/Modules/Media/Application/Query/CheckMediaExists/CheckMediaExistsHandler.php
app/src/Modules/Media/Application/Query/CheckMediaExists/CheckMediaExistsQuery.php
app/src/Modules/Media/Application/Query/CheckMediaIsImage/CheckMediaIsImageHandler.php
app/src/Modules/Media/Application/Query/CheckMediaIsImage/CheckMediaIsImageQuery.php
app/src/Modules/Media/Application/Query/FindMediaUrl/FindMediaUrlHandler.php
app/src/Modules/Media/Application/Query/FindMediaUrl/FindMediaUrlQuery.php
app/src/Modules/Media/Application/Query/GetMediaUrl/GetMediaUrlHandler.php
app/src/Modules/Media/Application/Query/GetMediaUrl/GetMediaUrlQuery.php
app/src/Modules/Media/Application/Service/MediaTypeResolver.php
app/src/Modules/Posts/Application/Command/CommentPost/CommentPostCommand.php
app/src/Modules/Posts/Application/Command/CommentPost/CommentPostHandler.php
app/src/Modules/Posts/Application/Command/CreatePost/CreatePostCommand.php
app/src/Modules/Posts/Application/Command/CreatePost/CreatePostHandler.php
app/src/Modules/Posts/Application/Command/DeleteComment/DeleteCommentCommand.php
app/src/Modules/Posts/Application/Command/DeleteComment/DeleteCommentHandler.php
app/src/Modules/Posts/Application/Command/DeletePost/DeletePostCommand.php
app/src/Modules/Posts/Application/Command/DeletePost/DeletePostHandler.php
app/src/Modules/Posts/Application/Command/LikeComment/LikeCommentCommand.php
app/src/Modules/Posts/Application/Command/LikeComment/LikeCommentHandler.php
app/src/Modules/Posts/Application/Command/LikePost/LikePostCommand.php
app/src/Modules/Posts/Application/Command/LikePost/LikePostHandler.php
app/src/Modules/Posts/Application/Command/PublishPost/PublishPostCommand.php
app/src/Modules/Posts/Application/Command/PublishPost/PublishPostHandler.php
app/src/Modules/Posts/Application/Command/ReplyComment/ReplyCommentCommand.php
app/src/Modules/Posts/Application/Command/ReplyComment/ReplyCommentHandler.php
app/src/Modules/Posts/Application/Command/RepostPost/RepostPostCommand.php
app/src/Modules/Posts/Application/Command/RepostPost/RepostPostHandler.php
app/src/Modules/Posts/Application/Command/UnlikeComment/UnlikeCommentCommand.php
app/src/Modules/Posts/Application/Command/UnlikeComment/UnlikeCommentHandler.php
app/src/Modules/Posts/Application/Command/UnlikePost/UnlikePostCommand.php
app/src/Modules/Posts/Application/Command/UnlikePost/UnlikePostHandler.php
app/src/Modules/Posts/Application/Notification/CommentNotificationTarget.php
app/src/Modules/Posts/Application/Notification/NotificationContentBuilder.php
app/src/Modules/Posts/Application/Notification/PostNotificationType.php
app/src/Modules/Posts/Application/Notification/PostNotifier.php
app/src/Modules/Posts/Application/Post/CommentComposer.php
app/src/Modules/Posts/Application/Post/PostContentComposer.php
app/src/Modules/Posts/Application/Post/PostVisibilityPolicy.php
app/src/Modules/Posts/Application/Query/GetCommentReplies/GetCommentRepliesHandler.php
app/src/Modules/Posts/Application/Query/GetCommentReplies/GetCommentRepliesQuery.php
app/src/Modules/Posts/Application/Query/GetCommentReplies/GetCommentRepliesResult.php
app/src/Modules/Posts/Application/Query/GetPost/GetPostHandler.php
app/src/Modules/Posts/Application/Query/GetPost/GetPostQuery.php
app/src/Modules/Posts/Application/Query/GetPostComments/GetPostCommentsHandler.php
app/src/Modules/Posts/Application/Query/GetPostComments/GetPostCommentsQuery.php
app/src/Modules/Posts/Application/Query/GetPostComments/GetPostCommentsResult.php
app/src/Modules/Posts/Application/Query/GetUserFeed/GetUserFeedHandler.php
app/src/Modules/Posts/Application/Query/GetUserFeed/GetUserFeedQuery.php
app/src/Modules/Posts/Application/Query/GetUserFeed/GetUserFeedResult.php
app/src/Modules/Posts/Application/View/AuthorView.php
app/src/Modules/Posts/Application/View/CommentView.php
app/src/Modules/Posts/Application/View/CommentViewAssembler.php
app/src/Modules/Posts/Application/View/PostMediaItemView.php
app/src/Modules/Posts/Application/View/PostView.php
app/src/Modules/Posts/Application/View/PostViewAssembler.php
app/src/Modules/Posts/Application/View/TagView.php
app/src/Modules/Posts/Domain/Collection/CommentCollection.php
app/src/Modules/Posts/Domain/Collection/CommentLikeCollection.php
app/src/Modules/Posts/Domain/Collection/CommentMentionCollection.php
app/src/Modules/Posts/Domain/Collection/PostBlockCollection.php
app/src/Modules/Posts/Domain/Collection/PostCollection.php
app/src/Modules/Posts/Domain/Collection/PostLikeCollection.php
app/src/Modules/Posts/Domain/Collection/PostMediaCollection.php
app/src/Modules/Posts/Domain/Collection/PostMentionCollection.php
app/src/Modules/Posts/Domain/Collection/PostTagCollection.php
app/src/Modules/Posts/Domain/Entity/Comment.php
app/src/Modules/Posts/Domain/Entity/CommentLike.php
app/src/Modules/Posts/Domain/Entity/CommentMention.php
app/src/Modules/Posts/Domain/Entity/Post.php
app/src/Modules/Posts/Domain/Entity/PostBlock.php
app/src/Modules/Posts/Domain/Entity/PostLike.php
app/src/Modules/Posts/Domain/Entity/PostMedia.php
app/src/Modules/Posts/Domain/Entity/PostMention.php
app/src/Modules/Posts/Domain/Entity/PostTag.php
app/src/Modules/Posts/Domain/Enum/AttachmentType.php
app/src/Modules/Posts/Domain/Enum/PostStatus.php
app/src/Modules/Posts/Domain/ValueObject/BlockReason.php
app/src/Modules/Posts/Domain/ValueObject/BlockUnblockedAt.php
app/src/Modules/Posts/Domain/ValueObject/BlockUnblockedBy.php
app/src/Modules/Posts/Domain/ValueObject/BlockUnblockedReason.php
app/src/Modules/Posts/Domain/ValueObject/CommentDeletedAt.php
app/src/Modules/Posts/Domain/ValueObject/CommentDeletedBy.php
app/src/Modules/Posts/Domain/ValueObject/CommentDeletionReason.php
app/src/Modules/Posts/Domain/ValueObject/CommentId.php
app/src/Modules/Posts/Domain/ValueObject/CommentLikeId.php
app/src/Modules/Posts/Domain/ValueObject/CommentMentionId.php
app/src/Modules/Posts/Domain/ValueObject/CommentParent.php
app/src/Modules/Posts/Domain/ValueObject/CommentText.php
app/src/Modules/Posts/Domain/ValueObject/CommentsCount.php
app/src/Modules/Posts/Domain/ValueObject/LikesCount.php
app/src/Modules/Posts/Domain/ValueObject/MediaPosition.php
app/src/Modules/Posts/Domain/ValueObject/PostBlockId.php
app/src/Modules/Posts/Domain/ValueObject/PostDeletion.php
app/src/Modules/Posts/Domain/ValueObject/PostId.php
app/src/Modules/Posts/Domain/ValueObject/PostLesson.php
app/src/Modules/Posts/Domain/ValueObject/PostLikeId.php
app/src/Modules/Posts/Domain/ValueObject/PostMediaId.php
app/src/Modules/Posts/Domain/ValueObject/PostMediaReference.php
app/src/Modules/Posts/Domain/ValueObject/PostMentionId.php
app/src/Modules/Posts/Domain/ValueObject/PostOriginal.php
app/src/Modules/Posts/Domain/ValueObject/PostPractice.php
app/src/Modules/Posts/Domain/ValueObject/PostTagId.php
app/src/Modules/Posts/Domain/ValueObject/PostText.php
app/src/Modules/Posts/Domain/ValueObject/RepliesCount.php
app/src/Modules/Posts/Domain/ValueObject/RepostsCount.php
app/src/Modules/Posts/Infrastructure/Bootloader/PostsBootloader.php
app/src/Modules/Posts/Infrastructure/Cycle/BlockUnblockedAtTypecast.php
app/src/Modules/Posts/Infrastructure/Cycle/BlockUnblockedByTypecast.php
app/src/Modules/Posts/Infrastructure/Cycle/BlockUnblockedReasonTypecast.php
app/src/Modules/Posts/Infrastructure/Cycle/CommentDeletedAtTypecast.php
app/src/Modules/Posts/Infrastructure/Cycle/CommentDeletedByTypecast.php
app/src/Modules/Posts/Infrastructure/Cycle/CommentDeletionReasonTypecast.php
app/src/Modules/Posts/Infrastructure/Cycle/CommentParentTypecast.php
app/src/Modules/Posts/Infrastructure/Cycle/PostDeletionTypecast.php
app/src/Modules/Posts/Infrastructure/Cycle/PostLessonTypecast.php
app/src/Modules/Posts/Infrastructure/Cycle/PostOriginalTypecast.php
app/src/Modules/Posts/Infrastructure/Cycle/PostPracticeTypecast.php
app/src/Modules/Posts/Infrastructure/Cycle/PostTextTypecast.php
app/src/Modules/Posts/Presentation/Http/Controller/CommentController.php
app/src/Modules/Posts/Presentation/Http/Controller/PostController.php
app/src/Modules/Posts/Presentation/Http/Filter/CommentPostFilter.php
app/src/Modules/Posts/Presentation/Http/Filter/CreatePostFilter.php
app/src/Modules/Posts/Presentation/Http/Filter/DeleteCommentFilter.php
app/src/Modules/Posts/Presentation/Http/Filter/DeletePostFilter.php
app/src/Modules/Posts/Presentation/Http/Filter/GetCommentRepliesFilter.php
app/src/Modules/Posts/Presentation/Http/Filter/GetPostCommentsFilter.php
app/src/Modules/Posts/Presentation/Http/Filter/GetPostFilter.php
app/src/Modules/Posts/Presentation/Http/Filter/GetUserFeedFilter.php
app/src/Modules/Posts/Presentation/Http/Filter/LikeCommentFilter.php
app/src/Modules/Posts/Presentation/Http/Filter/LikePostFilter.php
app/src/Modules/Posts/Presentation/Http/Filter/PublishPostFilter.php
app/src/Modules/Posts/Presentation/Http/Filter/ReplyCommentFilter.php
app/src/Modules/Posts/Presentation/Http/Filter/RepostPostFilter.php
app/src/Modules/Posts/Presentation/Http/Filter/UnlikeCommentFilter.php
app/src/Modules/Posts/Presentation/Http/Filter/UnlikePostFilter.php
app/src/Modules/Posts/Presentation/Http/Resource/AuthorResource.php
app/src/Modules/Posts/Presentation/Http/Resource/CommentResource.php
app/src/Modules/Posts/Presentation/Http/Resource/PostMediaItemResource.php
app/src/Modules/Posts/Presentation/Http/Resource/PostResource.php
app/src/Modules/Posts/Presentation/Http/Resource/TagResource.php
app/src/Modules/Posts/Repository/CommentLikeRepository.php
app/src/Modules/Posts/Repository/CommentMentionRepository.php
app/src/Modules/Posts/Repository/CommentRepository.php
app/src/Modules/Posts/Repository/PostBlockRepository.php
app/src/Modules/Posts/Repository/PostLikeRepository.php
app/src/Modules/Posts/Repository/PostMediaRepository.php
app/src/Modules/Posts/Repository/PostMentionRepository.php
app/src/Modules/Posts/Repository/PostRepository.php
app/src/Modules/Posts/Repository/PostTagRepository.php
app/src/Modules/Tags/Application/Command/ResolveTags/ResolveTagsCommand.php
app/src/Modules/Tags/Application/Command/ResolveTags/ResolveTagsHandler.php
app/src/Modules/Tags/Application/Command/ResolveTags/ResolveTagsResult.php
app/src/Modules/Tags/Application/Dto/TagTextCollection.php
app/src/Modules/Tags/Application/Query/GetTags/GetTagsHandler.php
app/src/Modules/Tags/Application/Query/GetTags/GetTagsQuery.php
app/src/Modules/Tags/Domain/Collection/TagCollection.php
app/src/Modules/Tags/Domain/Entity/Tag.php
app/src/Modules/Tags/Domain/ValueObject/TagText.php
app/src/Modules/Tags/Repository/TagRepository.php
app/src/Modules/User/Application/Command/CreateUser/CreateUserCommand.php
app/src/Modules/User/Application/Command/CreateUser/CreateUserHandler.php
app/src/Modules/User/Application/Command/CreateUser/CreateUserResult.php
app/src/Modules/User/Application/Dto/UserAuthView.php
app/src/Modules/User/Application/Dto/UserPublicProfileCollection.php
app/src/Modules/User/Application/Dto/UserPublicProfileView.php
app/src/Modules/User/Application/Profile/UserPublicProfileAssembler.php
app/src/Modules/User/Application/Query/FindUserForAuth/FindUserForAuthHandler.php
app/src/Modules/User/Application/Query/FindUserForAuth/FindUserForAuthQuery.php
app/src/Modules/User/Application/Query/GetUserPublicProfile/GetUserPublicProfileHandler.php
app/src/Modules/User/Application/Query/GetUserPublicProfile/GetUserPublicProfileQuery.php
app/src/Modules/User/Application/Query/GetUserPublicProfiles/GetUserPublicProfilesHandler.php
app/src/Modules/User/Application/Query/GetUserPublicProfiles/GetUserPublicProfilesQuery.php
tests/Feature/Modules/Media/Application/CheckMediaAttachableHandlerTest.php
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php
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
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php
tests/Feature/Modules/Posts/Http/CommentHttpTest.php
tests/Feature/Modules/Posts/Http/CommentReactionHttpTest.php
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php
tests/Feature/Modules/Posts/Http/DeletePostHttpTest.php
tests/Feature/Modules/Posts/Http/GetCommentRepliesHttpTest.php
tests/Feature/Modules/Posts/Http/GetPostCommentsHttpTest.php
tests/Feature/Modules/Posts/Http/GetPostHttpTest.php
tests/Feature/Modules/Posts/Http/GetUserFeedHttpTest.php
tests/Feature/Modules/Posts/Http/PostReactionHttpTest.php
tests/Feature/Modules/Posts/Http/PostsHttpTestCase.php
tests/Feature/Modules/Posts/Http/PublishPostHttpTest.php
tests/Feature/Modules/Posts/Http/RepostPostHttpTest.php
tests/Feature/Modules/Posts/PostsRepositoryTestCase.php
tests/Feature/Modules/Posts/Repository/CommentRepositoryTest.php
tests/Feature/Modules/Posts/Repository/LikeBatchRepositoryTest.php
tests/Feature/Modules/Posts/Repository/LikesAndMentionsRepositoryTest.php
tests/Feature/Modules/Posts/Repository/PostBlockRepositoryTest.php
tests/Feature/Modules/Posts/Repository/PostMediaRepositoryTest.php
tests/Feature/Modules/Posts/Repository/PostRepositoryTest.php
tests/Feature/Modules/Posts/Repository/PostTagRepositoryTest.php
tests/Feature/Modules/Tags/Application/GetTagsHandlerTest.php
tests/Feature/Modules/Tags/Application/ResolveTagsHandlerTest.php
tests/Feature/Modules/Tags/Repository/TagRepositoryTest.php
tests/Feature/Modules/Tags/TagsApplicationTestCase.php
tests/Feature/Modules/Tags/TagsRepositoryTestCase.php
tests/Feature/Modules/User/Application/CreateUserHandlerTest.php
tests/Feature/Modules/User/Application/FindUserForAuthHandlerTest.php
tests/Feature/Modules/User/Application/GetUserPublicProfileHandlerTest.php
tests/Feature/Modules/User/Application/GetUserPublicProfilesHandlerTest.php
tests/Feature/Modules/User/Application/UserApplicationTestCase.php
tests/Feature/Modules/User/Repository/UserRepositoryTest.php
tests/Kernel/Modules/Posts/Notification/NotificationContentBuilderTest.php
tests/Kernel/Modules/Posts/Notification/PostNotificationTypesTest.php

 succeeded in 0ms:
diff --git a/.env.sample b/.env.sample
index 47bdc16..b744096 100644
--- a/.env.sample
+++ b/.env.sample
@@ -82,6 +82,9 @@ MEDIA_MULTIPART_THRESHOLD_BYTES=16777216
 MEDIA_MULTIPART_PART_SIZE_BYTES=8388608
 MEDIA_IMAGE_PROCESSING_DRIVER=imagick
 
+# Ссылка на аватар по умолчанию для публичного профиля (app/config/user.php). Зависит от CDN, не может быть пустой.
+USER_DEFAULT_AVATAR_URL=https://cdn.yoga-loka.local/default-avatar.png
+
 MINIO_ROOT_USER=yoga_loka
 MINIO_ROOT_PASSWORD=yoga_loka_password
 MINIO_TEST_BUCKET=yoga-loka-test
diff --git a/app/locale/en/user.php b/app/locale/en/user.php
index fc0ff66..047bf2f 100644
--- a/app/locale/en/user.php
+++ b/app/locale/en/user.php
@@ -5,4 +5,5 @@ declare(strict_types=1);
 return [
     'app.user.email_taken' => 'This email is already registered.',
     'app.user.nickname_taken' => 'This nickname is already taken.',
+    'app.user.not_found' => 'User not found.',
 ];
diff --git a/app/locale/ru/user.php b/app/locale/ru/user.php
index 4c044af..2925526 100644
--- a/app/locale/ru/user.php
+++ b/app/locale/ru/user.php
@@ -5,4 +5,5 @@ declare(strict_types=1);
 return [
     'app.user.email_taken' => 'Этот email уже зарегистрирован.',
     'app.user.nickname_taken' => 'Этот никнейм уже занят.',
+    'app.user.not_found' => 'Пользователь не найден.',
 ];
diff --git a/app/src/Modules/Posts/Repository/CommentLikeRepository.php b/app/src/Modules/Posts/Repository/CommentLikeRepository.php
index d282d49..13d94b8 100644
--- a/app/src/Modules/Posts/Repository/CommentLikeRepository.php
+++ b/app/src/Modules/Posts/Repository/CommentLikeRepository.php
@@ -4,10 +4,12 @@ declare(strict_types=1);
 
 namespace App\Modules\Posts\Repository;
 
+use App\Modules\Posts\Domain\Collection\CommentLikeCollection;
 use App\Modules\Posts\Domain\Entity\CommentLike;
 use App\Modules\Posts\Domain\ValueObject\CommentId;
 use App\Shared\Domain\ValueObject\UserId;
 use App\Shared\Infrastructure\Cycle\AbstractRepository;
+use Cycle\Database\Injection\Parameter;
 
 /**
  * @extends AbstractRepository<CommentLike>
@@ -23,4 +25,24 @@ final class CommentLikeRepository extends AbstractRepository
     {
         return $this->findByCommentAndUser(commentId: $commentId, userId: $userId) !== null;
     }
+
+    /**
+     * Лайки пользователя по набору комментариев — для флага likedByMe в листингах без N+1.
+     */
+    public function findByUserAndCommentIds(UserId $userId, CommentId ...$commentIds): CommentLikeCollection
+    {
+        if ($commentIds === []) {
+            return new CommentLikeCollection();
+        }
+
+        return new CommentLikeCollection(
+            $this->select()
+                ->where('user_id', $userId->value())
+                ->where('comment_id', 'in', new Parameter(\array_map(
+                    static fn(CommentId $commentId): string => $commentId->value(),
+                    $commentIds,
+                )))
+                ->fetchAll(),
+        );
+    }
 }
diff --git a/app/src/Modules/Posts/Repository/CommentRepository.php b/app/src/Modules/Posts/Repository/CommentRepository.php
index 210e4a6..2a16352 100644
--- a/app/src/Modules/Posts/Repository/CommentRepository.php
+++ b/app/src/Modules/Posts/Repository/CommentRepository.php
@@ -40,6 +40,30 @@ final class CommentRepository extends AbstractRepository
         );
     }
 
+    /**
+     * Комментарии верхнего уровня записи (parent_comment_id IS NULL), без удалённых, cursor-пагинация.
+     */
+    public function findTopLevelByPostId(PostId $postId, CommentId|null $cursor, int $limit): CommentCollection
+    {
+        $cursorId = $cursor?->value();
+
+        return new CommentCollection(
+            $this->select()
+                ->where('post_id', $postId->value())
+                ->where('parent_comment_id', '=', null)
+                ->where('deleted_at', '=', null)
+                ->when(
+                    condition: $cursorId !== null,
+                    callback: static function (WhenSelect $query) use ($cursorId): void {
+                        $query->where('id', '<', $cursorId);
+                    },
+                )
+                ->orderBy(expression: 'id', direction: 'DESC')
+                ->limit($limit)
+                ->fetchAll(),
+        );
+    }
+
     public function findReplies(CommentId $parentId, CommentId|null $cursor, int $limit): CommentCollection
     {
         $cursorId = $cursor?->value();
@@ -47,6 +71,7 @@ final class CommentRepository extends AbstractRepository
         return new CommentCollection(
             $this->select()
                 ->where('parent_comment_id', $parentId->value())
+                ->where('deleted_at', '=', null)
                 ->when(
                     condition: $cursorId !== null,
                     callback: static function (WhenSelect $query) use ($cursorId): void {
diff --git a/app/src/Modules/Posts/Repository/PostLikeRepository.php b/app/src/Modules/Posts/Repository/PostLikeRepository.php
index edc9cf1..c8ef8dc 100644
--- a/app/src/Modules/Posts/Repository/PostLikeRepository.php
+++ b/app/src/Modules/Posts/Repository/PostLikeRepository.php
@@ -9,6 +9,7 @@ use App\Modules\Posts\Domain\Entity\PostLike;
 use App\Modules\Posts\Domain\ValueObject\PostId;
 use App\Shared\Domain\ValueObject\UserId;
 use App\Shared\Infrastructure\Cycle\AbstractRepository;
+use Cycle\Database\Injection\Parameter;
 
 /**
  * @extends AbstractRepository<PostLike>
@@ -34,4 +35,24 @@ final class PostLikeRepository extends AbstractRepository
                 ->fetchAll(),
         );
     }
+
+    /**
+     * Лайки пользователя по набору записей — для флага likedByMe в листингах без N+1.
+     */
+    public function findByUserAndPostIds(UserId $userId, PostId ...$postIds): PostLikeCollection
+    {
+        if ($postIds === []) {
+            return new PostLikeCollection();
+        }
+
+        return new PostLikeCollection(
+            $this->select()
+                ->where('user_id', $userId->value())
+                ->where('post_id', 'in', new Parameter(\array_map(
+                    static fn(PostId $postId): string => $postId->value(),
+                    $postIds,
+                )))
+                ->fetchAll(),
+        );
+    }
 }
diff --git a/app/src/Modules/Posts/Repository/PostRepository.php b/app/src/Modules/Posts/Repository/PostRepository.php
index 313a036..c2ce736 100644
--- a/app/src/Modules/Posts/Repository/PostRepository.php
+++ b/app/src/Modules/Posts/Repository/PostRepository.php
@@ -56,6 +56,41 @@ final class PostRepository extends AbstractRepository
         );
     }
 
+    /**
+     * Видимая лента автора: как findByUserId, но скрывает мягко удалённые записи (deleted_at IS NULL).
+     * Статус опционален: вызывающий передаёт Published для чужой ленты и null (все статусы) для своей.
+     */
+    public function findVisibleByUserId(
+        UserId $userId,
+        PostStatus|null $status,
+        PostId|null $cursor,
+        int $limit,
+    ): PostCollection {
+        $statusValue = $status?->value;
+        $cursorId = $cursor?->value();
+
+        return new PostCollection(
+            $this->select()
+                ->where('user_id', $userId->value())
+                ->where('deleted_at', '=', null)
+                ->when(
+                    condition: $statusValue !== null,
+                    callback: static function (WhenSelect $query) use ($statusValue): void {
+                        $query->where('status', $statusValue);
+                    },
+                )
+                ->when(
+                    condition: $cursorId !== null,
+                    callback: static function (WhenSelect $query) use ($cursorId): void {
+                        $query->where('id', '<', $cursorId);
+                    },
+                )
+                ->orderBy(expression: 'id', direction: 'DESC')
+                ->limit($limit)
+                ->fetchAll(),
+        );
+    }
+
     public function findRepostsOf(PostId $postId): PostCollection
     {
         return new PostCollection(
diff --git a/app/src/Modules/Tags/Repository/TagRepository.php b/app/src/Modules/Tags/Repository/TagRepository.php
index 9fe2153..11da2a3 100644
--- a/app/src/Modules/Tags/Repository/TagRepository.php
+++ b/app/src/Modules/Tags/Repository/TagRepository.php
@@ -37,4 +37,20 @@ final class TagRepository extends AbstractRepository
                 ->fetchAll(),
         );
     }
+
+    public function findByIds(TagId ...$tagIds): TagCollection
+    {
+        if ($tagIds === []) {
+            return new TagCollection();
+        }
+
+        return new TagCollection(
+            $this->select()
+                ->where('id', 'in', new Parameter(\array_map(
+                    static fn(TagId $tagId): string => $tagId->value(),
+                    $tagIds,
+                )))
+                ->fetchAll(),
+        );
+    }
 }
diff --git a/app/src/Modules/User/Repository/UserRepository.php b/app/src/Modules/User/Repository/UserRepository.php
index b817be2..c5b1b99 100644
--- a/app/src/Modules/User/Repository/UserRepository.php
+++ b/app/src/Modules/User/Repository/UserRepository.php
@@ -4,10 +4,12 @@ declare(strict_types=1);
 
 namespace App\Modules\User\Repository;
 
+use App\Modules\User\Domain\Collection\UserCollection;
 use App\Modules\User\Domain\Entity\User;
 use App\Modules\User\Domain\ValueObject\Email;
 use App\Modules\User\Domain\ValueObject\UserNickname;
 use App\Shared\Domain\ValueObject\UserId;
+use Cycle\Database\Injection\Parameter;
 use Cycle\ORM\Select\Repository;
 
 /**
@@ -20,6 +22,22 @@ final class UserRepository extends Repository
         return $this->findByPK($userId->value());
     }
 
+    public function findByIds(UserId ...$userIds): UserCollection
+    {
+        if ($userIds === []) {
+            return new UserCollection();
+        }
+
+        return new UserCollection(
+            $this->select()
+                ->where('id', 'in', new Parameter(\array_map(
+                    static fn(UserId $userId): string => $userId->value(),
+                    $userIds,
+                )))
+                ->fetchAll(),
+        );
+    }
+
     public function findByEmail(Email $email): User|null
     {
         return $this->findOne(['email' => $email->value()]);
diff --git a/app/src/Shared/Infrastructure/Framework/Kernel.php b/app/src/Shared/Infrastructure/Framework/Kernel.php
index b6e65be..abe2472 100644
--- a/app/src/Shared/Infrastructure/Framework/Kernel.php
+++ b/app/src/Shared/Infrastructure/Framework/Kernel.php
@@ -38,6 +38,7 @@ use App\Modules\Media\Infrastructure\Bootloader\MediaBootloader;
 use App\Modules\Notifications\Infrastructure\Bootloader\NotificationsBootloader;
 use App\Modules\Outbox\Infrastructure\Bootloader\OutboxBootloader;
 use App\Modules\Outbox\Infrastructure\Bootloader\OutboxConsoleBootloader;
+use App\Modules\Posts\Infrastructure\Bootloader\PostsBootloader;
 use App\Modules\System\Infrastructure\Bootloader\SystemBootloader;
 use GianTiaga\SpiralApiErrors\Bootloader\ApiErrorBootloader;
 use GianTiaga\SpiralCqrs\Bootloader\CqrsBootloader;
@@ -144,6 +145,7 @@ class Kernel extends \Spiral\Framework\Kernel
             AuthBootloader::class,
             SystemBootloader::class,
             NotificationsBootloader::class,
+            PostsBootloader::class,
 
             // Почта
             MailerBootloader::class,
diff --git a/docs/rules.md b/docs/rules.md
index 263cda6..5cf0fb7 100644
--- a/docs/rules.md
+++ b/docs/rules.md
@@ -18,6 +18,7 @@
 - **Именованные аргументы**: обязательны при 2+ обычных параметрах и при любом необязательном/булевом параметре. Позиционные — для 1–2 очевидных параметров. Variadic-вызовы и вызовы с unpack (`...$args`) допускают позиционные аргументы, потому что именование ломает читаемость таких API (`sprintf`, `implode` и т.д.). (Проверяется PHPStan)
 - **`sprintf()` для строк**: для пользовательских сообщений и строк ошибок. Интерполяция `"{$a}-{$b}"` — только для компактных ключей/идентификаторов. Конкатенация `.` — избегать.
 - **Collection-пайплайны**: `->map()`, `->filter()`, `->groupBy()` для чистых трансформаций. `foreach` — только при побочных эффектах.
+- **Не подменять методы коллекции array-функциями**: если значение уже `Illuminate\Support\Collection` (в т.ч. типизированная доменная коллекция), для выборок и трансформаций вызывай её методы (`->filter()`, `->map()`, `->first()`, `->contains()`, `->reject()`, `->keyBy()`), а не разворачивай в массив через `->all()` ради `array_filter`/`array_map`. Антипаттерн: `array_filter($collection->all(), $fn)` вместо `$collection->filter($fn)`. `->all()` уместен только когда действительно нужен `array`/`list<T>`: передать в конструктор другой типизированной коллекции, вернуть наружу или применить позиционную операцию без удобного аналога у коллекции (например `array_slice` для курсора).
 - **Типизированные Laravel Collections вместо массивов и Doctrine Collections**: в Entity и Repository не использовать массивы для наборов сущностей или value object. Для каждой доменной коллекции создавать именованный класс на базе `Illuminate\Support\Collection` с generic-типом элемента: например, связь `User -> Role` хранится и возвращается как `RoleCollection`, а результат репозитория со списком пользователей — как `UserCollection`. `Doctrine\Common\Collections\ArrayCollection` запрещена. Все связи Cycle ORM и методы репозиториев, возвращающие несколько элементов, должны возвращать конкретную типизированную коллекцию, а не `array` и не голый `Illuminate\Support\Collection`.
 - **Явные типы вместо `null`**: `null` в доменной модели не протаскивается через границы — тип свойства Entity, параметры `create()`, аргументы и возвраты доменных методов не должны быть `?T` для доменных данных. Отсутствие, неизвестность или особое состояние выражается отдельным value object, enum или доменным типом, а не `?string`/`?Ip`. Форма null-object VO выбирается по содержанию состояний:
   - **Одно опциональное значение, поведение сводится к «есть/нет»** — один `readonly`-VO с приватным nullable внутри и фабриками присутствия/отсутствия (образец `MediaExpiration` — `permanent()`/`temporaryUntil()`; `MediaProcessingError` — `none()`). Такой инкапсулированный nullable правилу не противоречит: наружу `?T` не виден.
diff --git a/public/openapi/openapi.yml b/public/openapi/openapi.yml
index 211a134..7af6c36 100644
--- a/public/openapi/openapi.yml
+++ b/public/openapi/openapi.yml
@@ -225,6 +225,291 @@ paths:
         default:
           description: 'Ошибка API.'
           content: { application/json: { schema: { $ref: '#/components/schemas/ErrorResponse' } } }
+  '/posts/{id}/comments':
+    post:
+      operationId: api_v1_posts_comments_create
+      description: ''
+      parameters:
+        -
+          name: id
+          in: path
+          required: true
+          schema: { type: string }
+      responses:
+        200:
+          description: 'Успешный ответ.'
+          content: { application/json: { schema: { type: object, properties: { data: { $ref: '#/components/schemas/CommentResource' } }, required: [data] } } }
+        default:
+          description: 'Ошибка API.'
+          content: { application/json: { schema: { $ref: '#/components/schemas/ErrorResponse' } } }
+      requestBody:
+        required: true
+        content:
+          application/json: { schema: { type: object, properties: { text: { type: string }, mentions: { type: array, items: { type: string } } }, required: [text] } }
+    get:
+      operationId: api_v1_posts_comments_list
+      description: ''
+      parameters:
+        -
+          name: id
+          in: path
+          required: true
+          schema: { type: string }
+        -
+          name: cursor
+          in: query
+          required: false
+          schema: { type: [string, 'null'] }
+        -
+          name: limit
+          in: query
+          required: false
+          schema: { type: integer }
+      responses:
+        200:
+          description: 'Успешный ответ.'
+          content: { application/json: { schema: { type: object, properties: { data: { type: array, items: { $ref: '#/components/schemas/CommentResource' } }, meta: { type: object, properties: { nextCursor: { type: [string, 'null'] }, limit: { type: integer } }, required: [nextCursor, limit] } }, required: [data, meta] } } }
+        default:
+          description: 'Ошибка API.'
+          content: { application/json: { schema: { $ref: '#/components/schemas/ErrorResponse' } } }
+  '/posts/comments/{id}/replies':
+    post:
+      operationId: api_v1_posts_comments_reply
+      description: ''
+      parameters:
+        -
+          name: id
+          in: path
+          required: true
+          schema: { type: string }
+      responses:
+        200:
+          description: 'Успешный ответ.'
+          content: { application/json: { schema: { type: object, properties: { data: { $ref: '#/components/schemas/CommentResource' } }, required: [data] } } }
+        default:
+          description: 'Ошибка API.'
+          content: { application/json: { schema: { $ref: '#/components/schemas/ErrorResponse' } } }
+      requestBody:
+        required: true
+        content:
+          application/json: { schema: { type: object, properties: { text: { type: string }, mentions: { type: array, items: { type: string } } }, required: [text] } }
+    get:
+      operationId: api_v1_posts_comments_replies
+      description: ''
+      parameters:
+        -
+          name: id
+          in: path
+          required: true
+          schema: { type: string }
+        -
+          name: cursor
+          in: query
+          required: false
+          schema: { type: [string, 'null'] }
+        -
+          name: limit
+          in: query
+          required: false
+          schema: { type: integer }
+      responses:
+        200:
+          description: 'Успешный ответ.'
+          content: { application/json: { schema: { type: object, properties: { data: { type: array, items: { $ref: '#/components/schemas/CommentResource' } }, meta: { type: object, properties: { nextCursor: { type: [string, 'null'] }, limit: { type: integer } }, required: [nextCursor, limit] } }, required: [data, meta] } } }
+        default:
+          description: 'Ошибка API.'
+          content: { application/json: { schema: { $ref: '#/components/schemas/ErrorResponse' } } }
+  '/posts/comments/{id}':
+    delete:
+      operationId: api_v1_posts_comments_delete
+      description: ''
+      parameters:
+        -
+          name: id
+          in: path
+          required: true
+          schema: { type: string }
+      responses:
+        204:
+          description: 'Успешный ответ.'
+        default:
+          description: 'Ошибка API.'
+          content: { application/json: { schema: { $ref: '#/components/schemas/ErrorResponse' } } }
+  '/posts/comments/{id}/like':
+    post:
+      operationId: api_v1_posts_comments_like
+      description: ''
+      parameters:
+        -
+          name: id
+          in: path
+          required: true
+          schema: { type: string }
+      responses:
+        204:
+          description: 'Успешный ответ.'
+        default:
+          description: 'Ошибка API.'
+          content: { application/json: { schema: { $ref: '#/components/schemas/ErrorResponse' } } }
+    delete:
+      operationId: api_v1_posts_comments_unlike
+      description: ''
+      parameters:
+        -
+          name: id
+          in: path
+          required: true
+          schema: { type: string }
+      responses:
+        204:
+          description: 'Успешный ответ.'
+        default:
+          description: 'Ошибка API.'
+          content: { application/json: { schema: { $ref: '#/components/schemas/ErrorResponse' } } }
+  /posts:
+    post:
+      operationId: api_v1_posts_create
+      description: ''
+      parameters: {  }
+      responses:
+        200:
+          description: 'Успешный ответ.'
+          content: { application/json: { schema: { type: object, properties: { data: { $ref: '#/components/schemas/PostResource' } }, required: [data] } } }
+        default:
+          description: 'Ошибка API.'
+          content: { application/json: { schema: { $ref: '#/components/schemas/ErrorResponse' } } }
+      requestBody:
+        required: true
+        content:
+          application/json: { schema: { type: object, properties: { text: { type: [string, 'null'] }, draft: { type: boolean }, mediaIds: { type: array, items: { type: string } }, tags: { type: array, items: { type: string } }, mentions: { type: array, items: { type: string } } } } }
+  '/posts/{id}/publish':
+    post:
+      operationId: api_v1_posts_publish
+      description: ''
+      parameters:
+        -
+          name: id
+          in: path
+          required: true
+          schema: { type: string }
+      responses:
+        200:
+          description: 'Успешный ответ.'
+          content: { application/json: { schema: { type: object, properties: { data: { $ref: '#/components/schemas/PostResource' } }, required: [data] } } }
+        default:
+          description: 'Ошибка API.'
+          content: { application/json: { schema: { $ref: '#/components/schemas/ErrorResponse' } } }
+  '/posts/{id}/repost':
+    post:
+      operationId: api_v1_posts_repost
+      description: ''
+      parameters:
+        -
+          name: id
+          in: path
+          required: true
+          schema: { type: string }
+      responses:
+        200:
+          description: 'Успешный ответ.'
+          content: { application/json: { schema: { type: object, properties: { data: { $ref: '#/components/schemas/PostResource' } }, required: [data] } } }
+        default:
+          description: 'Ошибка API.'
+          content: { application/json: { schema: { $ref: '#/components/schemas/ErrorResponse' } } }
+      requestBody:
+        required: true
+        content:
+          application/json: { schema: { type: object, properties: { text: { type: [string, 'null'] }, mediaIds: { type: array, items: { type: string } }, tags: { type: array, items: { type: string } }, mentions: { type: array, items: { type: string } } } } }
+  '/posts/{id}':
+    delete:
+      operationId: api_v1_posts_delete
+      description: ''
+      parameters:
+        -
+          name: id
+          in: path
+          required: true
+          schema: { type: string }
+      responses:
+        204:
+          description: 'Успешный ответ.'
+        default:
+          description: 'Ошибка API.'
+          content: { application/json: { schema: { $ref: '#/components/schemas/ErrorResponse' } } }
+    get:
+      operationId: api_v1_posts_show
+      description: ''
+      parameters:
+        -
+          name: id
+          in: path
+          required: true
+          schema: { type: string }
+      responses:
+        200:
+          description: 'Успешный ответ.'
+          content: { application/json: { schema: { type: object, properties: { data: { $ref: '#/components/schemas/PostResource' } }, required: [data] } } }
+        default:
+          description: 'Ошибка API.'
+          content: { application/json: { schema: { $ref: '#/components/schemas/ErrorResponse' } } }
+  '/posts/{id}/like':
+    post:
+      operationId: api_v1_posts_like
+      description: ''
+      parameters:
+        -
+          name: id
+          in: path
+          required: true
+          schema: { type: string }
+      responses:
+        204:
+          description: 'Успешный ответ.'
+        default:
+          description: 'Ошибка API.'
+          content: { application/json: { schema: { $ref: '#/components/schemas/ErrorResponse' } } }
+    delete:
+      operationId: api_v1_posts_unlike
+      description: ''
+      parameters:
+        -
+          name: id
+          in: path
+          required: true
+          schema: { type: string }
+      responses:
+        204:
+          description: 'Успешный ответ.'
+        default:
+          description: 'Ошибка API.'
+          content: { application/json: { schema: { $ref: '#/components/schemas/ErrorResponse' } } }
+  '/posts/user/{id}':
+    get:
+      operationId: api_v1_posts_user_feed
+      description: ''
+      parameters:
+        -
+          name: id
+          in: path
+          required: true
+          schema: { type: string }
+        -
+          name: cursor
+          in: query
+          required: false
+          schema: { type: [string, 'null'] }
+        -
+          name: limit
+          in: query
+          required: false
+          schema: { type: integer }
+      responses:
+        200:
+          description: 'Успешный ответ.'
+          content: { application/json: { schema: { type: object, properties: { data: { type: array, items: { $ref: '#/components/schemas/PostResource' } }, meta: { type: object, properties: { nextCursor: { type: [string, 'null'] }, limit: { type: integer } }, required: [nextCursor, limit] } }, required: [data, meta] } } }
+        default:
+          description: 'Ошибка API.'
+          content: { application/json: { schema: { $ref: '#/components/schemas/ErrorResponse' } } }
   /health:
     get:
       operationId: health
@@ -239,6 +524,49 @@ paths:
           content: { application/json: { schema: { $ref: '#/components/schemas/ErrorResponse' } } }
 components:
   schemas:
+    AuthorResource:
+      type: object
+      properties:
+        userId:
+          type: string
+        name:
+          type: string
+        avatarUrl:
+          type: string
+      required:
+        - userId
+        - name
+        - avatarUrl
+    CommentResource:
+      type: object
+      properties:
+        id:
+          type: string
+        postId:
+          type: string
+        parentId:
+          type: [string, 'null']
+        text:
+          type: string
+        likesCount:
+          type: integer
+        repliesCount:
+          type: integer
+        likedByMe:
+          type: boolean
+        createdAt:
+          type: string
+        author:
+          $ref: '#/components/schemas/AuthorResource'
+      required:
+        - id
+        - postId
+        - text
+        - likesCount
+        - repliesCount
+        - likedByMe
+        - createdAt
+        - author
     ErrorResponse:
       type: object
       properties:
@@ -337,6 +665,49 @@ components:
         - channel
         - enabled
         - default
+    PostResource:
+      type: object
+      properties:
+        id:
+          type: string
+        text:
+          type: [string, 'null']
+        status:
+          type: string
+        attachmentType:
+          type: string
+        likesCount:
+          type: integer
+        repostsCount:
+          type: integer
+        commentsCount:
+          type: integer
+        likedByMe:
+          type: boolean
+        createdAt:
+          type: string
+        author:
+          $ref: '#/components/schemas/AuthorResource'
+        media:
+          type: array
+          items: { type: string }
+        tags:
+          type: array
+          items: { type: string }
+        original:
+          oneOf: [{ $ref: '#/components/schemas/PostResource' }, { type: 'null' }]
+      required:
+        - id
+        - status
+        - attachmentType
+        - likesCount
+        - repostsCount
+        - commentsCount
+        - likedByMe
+        - createdAt
+        - author
+        - media
+        - tags
     SessionResource:
       type: object
       properties:
diff --git a/tests/Feature/Modules/Notifications/Application/NotificationUseCaseTest.php b/tests/Feature/Modules/Notifications/Application/NotificationUseCaseTest.php
index c1927ea..27d1ce7 100644
--- a/tests/Feature/Modules/Notifications/Application/NotificationUseCaseTest.php
+++ b/tests/Feature/Modules/Notifications/Application/NotificationUseCaseTest.php
@@ -231,8 +231,11 @@ final class NotificationUseCaseTest extends DatabaseTestCase
             new GetNotificationSettingsQuery(userId: $userId->value()),
         );
 
-        // Один вид × три канала.
-        self::assertCount(3, $views);
+        // Матрица содержит все зарегистрированные виды; у фикстурного вида ровно три канала.
+        $fixtureViews = $views->filter(
+            static fn(NotificationSettingView $view): bool => $view->type->value() === self::TYPE,
+        );
+        self::assertCount(3, $fixtureViews);
         self::assertTrue($this->viewFor($views->all(), NotificationChannel::Push)->enabled);
         self::assertFalse($this->viewFor($views->all(), NotificationChannel::Realtime)->enabled);
         self::assertTrue($this->viewFor($views->all(), NotificationChannel::Realtime)->default);
@@ -261,7 +264,7 @@ final class NotificationUseCaseTest extends DatabaseTestCase
     private function viewFor(array $views, NotificationChannel $channel): NotificationSettingView
     {
         foreach ($views as $view) {
-            if ($view->channel === $channel) {
+            if ($view->channel === $channel && $view->type->value() === self::TYPE) {
                 return $view;
             }
         }
diff --git a/tests/Feature/Modules/Notifications/Http/NotificationHttpTest.php b/tests/Feature/Modules/Notifications/Http/NotificationHttpTest.php
index 92146c5..5703025 100644
--- a/tests/Feature/Modules/Notifications/Http/NotificationHttpTest.php
+++ b/tests/Feature/Modules/Notifications/Http/NotificationHttpTest.php
@@ -180,8 +180,12 @@ final class NotificationHttpTest extends DatabaseTestCase
         );
 
         $response->assertOk();
-        // Один фикстурный вид × три канала.
-        self::assertCount(3, $this->json($response)['data']);
+        // Матрица содержит все зарегистрированные виды; у фикстурного вида ровно три канала.
+        $fixtureRows = \array_filter(
+            $this->json($response)['data'],
+            static fn(array $row): bool => $row['type'] === self::TYPE,
+        );
+        self::assertCount(3, $fixtureRows);
     }
 
     public function testUpdateNotificationSettings(): void
@@ -336,7 +340,7 @@ final class NotificationHttpTest extends DatabaseTestCase
     private function settingRow(array $rows, string $channel): array
     {
         foreach ($rows as $row) {
-            if ($row['channel'] === $channel) {
+            if ($row['channel'] === $channel && $row['type'] === self::TYPE) {
                 return $row;
             }
         }
diff --git a/tests/Kernel/Shared/Infrastructure/Configuration/ConfigShapeTest.php b/tests/Kernel/Shared/Infrastructure/Configuration/ConfigShapeTest.php
index 0bc4d30..2175856 100644
--- a/tests/Kernel/Shared/Infrastructure/Configuration/ConfigShapeTest.php
+++ b/tests/Kernel/Shared/Infrastructure/Configuration/ConfigShapeTest.php
@@ -34,6 +34,7 @@ final class ConfigShapeTest extends TestCase
                 'session',
                 'storage',
                 'translator',
+                'user',
             ],
             $this->configFileSections(),
         );

exec
/bin/zsh -lc 'rg "EmptySuccessResponse|code_request|logout|204|responseCode" app/src/Modules/Auth public/openapi/openapi.yml tests -n' in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
public/openapi/openapi.yml:11:      operationId: api_v1_auth_code_request
public/openapi/openapi.yml:12:      description: 'Запрос кода входа на email. Ответ всегда 204 (наличие пользователя не раскрываем).'
public/openapi/openapi.yml:15:        204:
public/openapi/openapi.yml:72:  /auth/logout:
public/openapi/openapi.yml:74:      operationId: api_v1_auth_logout
public/openapi/openapi.yml:78:        204:
public/openapi/openapi.yml:106:        204:
public/openapi/openapi.yml:333:        204:
public/openapi/openapi.yml:349:        204:
public/openapi/openapi.yml:364:        204:
public/openapi/openapi.yml:434:        204:
public/openapi/openapi.yml:466:        204:
public/openapi/openapi.yml:481:        204:
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:131:        $corruptBytes = \random_bytes(2048);
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:36:        $bytes = \random_bytes(2048);
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:54:        self::assertSame(2048, $head->contentLength->value());
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:36:        $media = $this->createMedia(userId: $userId, size: MediaFileSize::fromInt(2048));
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:42:        $result = $this->handler($this->fileServiceWithHead(2048), $outboxStore)->handle(
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:58:        $media = $this->createMedia(userId: $userId, size: MediaFileSize::fromInt(2048));
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:63:        $fileService->method('headObject')->willReturn(new MediaObjectHead(contentLength: MediaFileSize::fromInt(2048)));
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:80:        $this->handler($this->fileServiceWithHead(2048), $this->outboxStore())->handle(new CompleteMediaUploadCommand(
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:189:        $media = $this->createMedia(userId: $userId, size: MediaFileSize::fromInt(2048));
app/src/Modules/Auth/Presentation/Http/Controller/AuthController.php:39:use GianTiaga\SpiralOpenApi\Response\EmptySuccessResponse;
app/src/Modules/Auth/Presentation/Http/Controller/AuthController.php:49:     * Запрос кода входа на email. Ответ всегда 204 (наличие пользователя не раскрываем).
app/src/Modules/Auth/Presentation/Http/Controller/AuthController.php:65:    ): EmptySuccessResponse {
app/src/Modules/Auth/Presentation/Http/Controller/AuthController.php:74:        return new EmptySuccessResponse();
app/src/Modules/Auth/Presentation/Http/Controller/AuthController.php:179:        route: '/api/v1/auth/logout',
app/src/Modules/Auth/Presentation/Http/Controller/AuthController.php:180:        name: 'api.v1.auth.logout',
app/src/Modules/Auth/Presentation/Http/Controller/AuthController.php:192:    public function logout(
app/src/Modules/Auth/Presentation/Http/Controller/AuthController.php:193:        LogoutFilter $logoutFilter,
app/src/Modules/Auth/Presentation/Http/Controller/AuthController.php:195:        LogoutHandler $logoutHandler,
app/src/Modules/Auth/Presentation/Http/Controller/AuthController.php:196:    ): EmptySuccessResponse {
app/src/Modules/Auth/Presentation/Http/Controller/AuthController.php:198:            command: new LogoutCommand(authSessionId: $logoutFilter->authSessionId),
app/src/Modules/Auth/Presentation/Http/Controller/AuthController.php:199:            handler: $logoutHandler->handle(...),
app/src/Modules/Auth/Presentation/Http/Controller/AuthController.php:202:        return new EmptySuccessResponse();
app/src/Modules/Auth/Presentation/Http/Controller/AuthController.php:268:    ): EmptySuccessResponse {
app/src/Modules/Auth/Presentation/Http/Controller/AuthController.php:277:        return new EmptySuccessResponse();
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:226:            size: MediaFileSize::fromInt(2048),
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:285:            size: MediaFileSize::fromInt(2048),
tests/Unit/Modules/Auth/Presentation/Http/Middleware/AuthContextAttributeMiddlewareTest.php:112:            ->process(new ServerRequest(method: 'POST', uri: '/api/v1/auth/logout'), $captureHandler);
tests/Unit/Modules/Notifications/Domain/ValueObject/NotificationValueObjectTest.php:358:        NotificationActor::of(userId: UserId::generate(), name: 'Иван', avatarUrl: \str_repeat('a', 2049));
tests/Unit/Modules/Notifications/Infrastructure/Bootloader/NotificationsBootloaderTest.php:66:        $key = \openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => \OPENSSL_KEYTYPE_RSA]);
tests/Feature/Modules/Posts/Http/PostsHttpTestCase.php:287:            size: MediaFileSize::fromInt(2048),
tests/Feature/Modules/Auth/Console/AuthOpenApiGenerationTest.php:25:        self::assertStringContainsString('/auth/logout:', $contents);
tests/Feature/Modules/Auth/Console/AuthOpenApiGenerationTest.php:75:        self::assertArrayHasKey(204, $requestResponses);
tests/Feature/Modules/Auth/Console/AuthOpenApiGenerationTest.php:78:        $logoutResponses = $spec['paths']['/auth/logout']['post']['responses'] ?? null;
tests/Feature/Modules/Auth/Console/AuthOpenApiGenerationTest.php:79:        self::assertIsArray($logoutResponses);
tests/Feature/Modules/Auth/Console/AuthOpenApiGenerationTest.php:80:        self::assertArrayHasKey(204, $logoutResponses);
tests/Feature/Modules/Auth/Console/AuthOpenApiGenerationTest.php:81:        self::assertArrayNotHasKey(200, $logoutResponses);
tests/Feature/Modules/Auth/Console/AuthOpenApiGenerationTest.php:83:        // Защита от того, что 204 ошибочно применился ко всем эндпоинтам: соседний verify сохраняет 200 с телом.
tests/Feature/Modules/Auth/Http/AuthHttpTest.php:226:            ->postJson('/api/v1/auth/logout')
tests/Feature/Modules/Auth/Http/AuthHttpTest.php:231:            ->postJson('/api/v1/auth/logout')
tests/Feature/Modules/Auth/Http/AuthHttpTest.php:237:        $this->http()->postJson('/api/v1/auth/logout')->assertUnauthorized();
tests/Feature/Modules/Auth/Http/AuthHttpTest.php:246:            ->postJson('/api/v1/auth/logout')
tests/Feature/Modules/Auth/Application/RefreshTokensAndLogoutTest.php:44:        $this->logoutHandler()->handle(new LogoutCommand(authSessionId: $accessView->getPayload()['sessionID']));
tests/Feature/Modules/Auth/Application/RefreshTokensAndLogoutTest.php:55:    private function logoutHandler(): LogoutHandler

codex
По пункту `204` уже есть сильное подтверждение: OpenAPI для Auth прямо ожидает `204`, а тест `AuthOpenApiGenerationTest` проверяет, что у logout есть `204` и нет `200`. Теперь проверяю спорные места в `Posts`: сборку медиа, обработчики комментариев/лайков, контроллеры, фильтры и тесты.
exec
/bin/zsh -lc "nl -ba app/src/Modules/Media/Application/Query/GetMediaUrl/GetMediaUrlHandler.php app/src/Modules/Media/Application/Query/FindMediaUrl/FindMediaUrlHandler.php app/src/Modules/User/Application/Profile/UserPublicProfileAssembler.php | sed -n '1,240p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
usage: nl [-p] [-b type] [-d delim] [-f type] [-h type] [-i incr] [-l num]
          [-n format] [-s sep] [-v startnum] [-w width] [file]

exec
/bin/zsh -lc "nl -ba app/src/Modules/Posts/Application/View/PostViewAssembler.php | sed -n '1,280p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Posts\Application\View;
     6	
     7	use App\Modules\Media\Application\Query\GetMediaUrl\GetMediaUrlHandler;
     8	use App\Modules\Media\Application\Query\GetMediaUrl\GetMediaUrlQuery;
     9	use App\Modules\Posts\Application\Post\PostVisibilityPolicy;
    10	use App\Modules\Posts\Domain\Collection\PostCollection;
    11	use App\Modules\Posts\Domain\Entity\Post;
    12	use App\Modules\Posts\Domain\ValueObject\PostId;
    13	use App\Modules\Posts\Repository\PostLikeRepository;
    14	use App\Modules\Posts\Repository\PostMediaRepository;
    15	use App\Modules\Posts\Repository\PostRepository;
    16	use App\Modules\Posts\Repository\PostTagRepository;
    17	use App\Modules\Tags\Application\Query\GetTags\GetTagsHandler;
    18	use App\Modules\Tags\Application\Query\GetTags\GetTagsQuery;
    19	use App\Modules\User\Application\Query\GetUserPublicProfile\GetUserPublicProfileHandler;
    20	use App\Modules\User\Application\Query\GetUserPublicProfile\GetUserPublicProfileQuery;
    21	use App\Modules\User\Application\Query\GetUserPublicProfiles\GetUserPublicProfilesHandler;
    22	use App\Modules\User\Application\Query\GetUserPublicProfiles\GetUserPublicProfilesQuery;
    23	use App\Shared\Domain\ValueObject\UserId;
    24	use GianTiaga\SpiralCqrs\QueryBusInterface;
    25	
    26	/**
    27	 * Собирает read-model PostView из доменной записи, обогащая её данными смежных модулей: автор —
    28	 * через User, URL медиа — через Media, тексты тегов — через Tags; флаг likedByMe и счётчики — из
    29	 * своего модуля. Для репоста доглубляет исходную запись на один уровень (без её собственного
    30	 * оригинала), если она видна зрителю. В листингах авторы и флаги likedByMe собираются пакетно
    31	 * (без N+1).
    32	 */
    33	final readonly class PostViewAssembler
    34	{
    35	    // TTL ссылки на медиа записи для показа (для приватного медиа — срок presigned-ссылки).
    36	    private const int POST_MEDIA_URL_TTL_SECONDS = 3600;
    37	
    38	    public function __construct(
    39	        private QueryBusInterface $queryBus,
    40	        private GetUserPublicProfileHandler $getUserPublicProfileHandler,
    41	        private GetUserPublicProfilesHandler $getUserPublicProfilesHandler,
    42	        private GetMediaUrlHandler $getMediaUrlHandler,
    43	        private GetTagsHandler $getTagsHandler,
    44	        private PostRepository $postRepository,
    45	        private PostMediaRepository $postMediaRepository,
    46	        private PostTagRepository $postTagRepository,
    47	        private PostLikeRepository $postLikeRepository,
    48	    ) {}
    49	
    50	    public function fromPost(Post $post, UserId $viewer): PostView
    51	    {
    52	        return $this->build(
    53	            post: $post,
    54	            viewer: $viewer,
    55	            author: $this->authorView($post->userId),
    56	            likedByMe: $this->postLikeRepository->existsByPostAndUser(postId: $post->id, userId: $viewer),
    57	            includeOriginal: true,
    58	        );
    59	    }
    60	
    61	    /**
    62	     * @return list<PostView>
    63	     */
    64	    public function fromPosts(PostCollection $posts, UserId $viewer): array
    65	    {
    66	        $entities = $posts->all();
    67	
    68	        if ($entities === []) {
    69	            return [];
    70	        }
    71	
    72	        $authors = $this->authorViews($entities);
    73	        $likedPostIds = $this->likedPostIds(posts: $entities, viewer: $viewer);
    74	
    75	        $views = [];
    76	
    77	        foreach ($entities as $post) {
    78	            $views[] = $this->build(
    79	                post: $post,
    80	                viewer: $viewer,
    81	                author: $authors[$post->userId->value()],
    82	                likedByMe: isset($likedPostIds[$post->id->value()]),
    83	                includeOriginal: true,
    84	            );
    85	        }
    86	
    87	        return $views;
    88	    }
    89	
    90	    private function build(Post $post, UserId $viewer, AuthorView $author, bool $likedByMe, bool $includeOriginal): PostView
    91	    {
    92	        return new PostView(
    93	            id: $post->id->value(),
    94	            text: $post->text->value(),
    95	            status: $post->status->value,
    96	            attachmentType: $post->attachmentType->value,
    97	            likesCount: $post->likesCount->value(),
    98	            repostsCount: $post->repostsCount->value(),
    99	            commentsCount: $post->commentsCount->value(),
   100	            likedByMe: $likedByMe,
   101	            createdAt: $post->createdAt->format(\DateTimeInterface::ATOM),
   102	            author: $author,
   103	            media: $this->mediaItems($post),
   104	            tags: $this->tagViews($post),
   105	            original: $includeOriginal ? $this->originalView(post: $post, viewer: $viewer) : null,
   106	        );
   107	    }
   108	
   109	    private function authorView(UserId $userId): AuthorView
   110	    {
   111	        $profile = $this->queryBus->dispatch(
   112	            query: new GetUserPublicProfileQuery($userId->value()),
   113	            handler: $this->getUserPublicProfileHandler->handle(...),
   114	        );
   115	
   116	        return new AuthorView(userId: $profile->userId, name: $profile->name, avatarUrl: $profile->avatarUrl);
   117	    }
   118	
   119	    /**
   120	     * @param array<int, Post> $posts
   121	     *
   122	     * @return array<string, AuthorView>
   123	     */
   124	    private function authorViews(array $posts): array
   125	    {
   126	        $userIds = \array_values(\array_unique(\array_map(
   127	            static fn(Post $post): string => $post->userId->value(),
   128	            $posts,
   129	        )));
   130	
   131	        $profiles = $this->queryBus->dispatch(
   132	            query: new GetUserPublicProfilesQuery($userIds),
   133	            handler: $this->getUserPublicProfilesHandler->handle(...),
   134	        );
   135	
   136	        $authors = [];
   137	
   138	        foreach ($profiles as $profile) {
   139	            $authors[$profile->userId] = new AuthorView(
   140	                userId: $profile->userId,
   141	                name: $profile->name,
   142	                avatarUrl: $profile->avatarUrl,
   143	            );
   144	        }
   145	
   146	        return $authors;
   147	    }
   148	
   149	    /**
   150	     * @param array<int, Post> $posts
   151	     *
   152	     * @return array<string, true>
   153	     */
   154	    private function likedPostIds(array $posts, UserId $viewer): array
   155	    {
   156	        $postIds = \array_map(static fn(Post $post): PostId => $post->id, $posts);
   157	        $liked = [];
   158	
   159	        foreach ($this->postLikeRepository->findByUserAndPostIds($viewer, ...$postIds) as $like) {
   160	            $liked[$like->postId->value()] = true;
   161	        }
   162	
   163	        return $liked;
   164	    }
   165	
   166	    /**
   167	     * @return list<PostMediaItemView>
   168	     */
   169	    private function mediaItems(Post $post): array
   170	    {
   171	        $items = [];
   172	
   173	        foreach ($this->postMediaRepository->findByPostId($post->id) as $postMedia) {
   174	            $mediaUrl = $this->queryBus->dispatch(
   175	                query: new GetMediaUrlQuery(
   176	                    mediaId: $postMedia->media->value(),
   177	                    presignedTtlSeconds: self::POST_MEDIA_URL_TTL_SECONDS,
   178	                ),
   179	                handler: $this->getMediaUrlHandler->handle(...),
   180	            );
   181	
   182	            $items[] = new PostMediaItemView(
   183	                mediaId: $postMedia->media->value(),
   184	                url: $mediaUrl->url,
   185	                position: $postMedia->position->value(),
   186	            );
   187	        }
   188	
   189	        return $items;
   190	    }
   191	
   192	    /**
   193	     * @return list<TagView>
   194	     */
   195	    private function tagViews(Post $post): array
   196	    {
   197	        $tagIds = [];
   198	
   199	        foreach ($this->postTagRepository->findByPostId($post->id) as $postTag) {
   200	            $tagIds[] = $postTag->tagId->value();
   201	        }
   202	
   203	        if ($tagIds === []) {
   204	            return [];
   205	        }
   206	
   207	        $texts = $this->queryBus->dispatch(
   208	            query: new GetTagsQuery($tagIds),
   209	            handler: $this->getTagsHandler->handle(...),
   210	        );
   211	
   212	        $views = [];
   213	
   214	        foreach ($texts as $tagId => $text) {
   215	            $views[] = new TagView(id: $tagId, text: $text);
   216	        }
   217	
   218	        return $views;
   219	    }
   220	
   221	    private function originalView(Post $post, UserId $viewer): PostView|null
   222	    {
   223	        $originalId = $post->original->value();
   224	
   225	        if ($originalId === null) {
   226	            return null;
   227	        }
   228	
   229	        $original = $this->postRepository->findById(PostId::fromString($originalId));
   230	
   231	        if ($original === null || !PostVisibilityPolicy::isVisibleTo(post: $original, viewer: $viewer)) {
   232	            return null;
   233	        }
   234	
   235	        return $this->build(
   236	            post: $original,
   237	            viewer: $viewer,
   238	            author: $this->authorView($original->userId),
   239	            likedByMe: $this->postLikeRepository->existsByPostAndUser(postId: $original->id, userId: $viewer),
   240	            includeOriginal: false,
   241	        );
   242	    }
   243	}

exec
/bin/zsh -lc "nl -ba app/src/Modules/Auth/Presentation/Http/Controller/AuthController.php tests/Feature/Modules/Auth/Console/AuthOpenApiGenerationTest.php | sed -n '1,330p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
usage: nl [-p] [-b type] [-d delim] [-f type] [-h type] [-i incr] [-l num]
          [-n format] [-s sep] [-v startnum] [-w width] [file]

exec
/bin/zsh -lc "nl -ba app/src/Modules/Posts/Application/Command/LikeComment/LikeCommentHandler.php app/src/Modules/Posts/Application/Command/UnlikeComment/UnlikeCommentHandler.php app/src/Modules/Posts/Application/Command/CommentPost/CommentPostHandler.php app/src/Modules/Posts/Application/Command/ReplyComment/ReplyCommentHandler.php | sed -n '1,260p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
usage: nl [-p] [-b type] [-d delim] [-f type] [-h type] [-i incr] [-l num]
          [-n format] [-s sep] [-v startnum] [-w width] [file]

exec
/bin/zsh -lc "nl -ba app/src/Modules/Posts/Presentation/Http/Controller/PostController.php app/src/Modules/Posts/Presentation/Http/Controller/CommentController.php | sed -n '1,360p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
usage: nl [-p] [-b type] [-d delim] [-f type] [-h type] [-i incr] [-l num]
          [-n format] [-s sep] [-v startnum] [-w width] [file]

exec
/bin/zsh -lc 'rg "media|not_ready|not_found|FindMediaUrl|GetMediaUrl|deleted|Ready|soft" tests/Feature/Modules/Posts tests/Feature/Modules/Media -n' in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:47:        $media = $this->createMedia();
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:49:        $this->entityManager()->persist($media);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:52:        $restoredMedia = $this->mediaRepository()->findById($media->id);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:55:        self::assertTrue($media->id->equals($restoredMedia->id));
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:56:        self::assertTrue($media->storageKey->equals($restoredMedia->storageKey));
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:59:        self::assertTrue($media->uploadedById->equals($restoredMedia->uploadedById));
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:60:        self::assertInstanceOf(Media::class, $this->mediaRepository()->findByStorageKey($media->storageKey));
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:65:        $media = $this->createMedia();
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:66:        $imageConversion = $this->createImageConversion($media);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:67:        $videoConversion = $this->createVideoConversion($media);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:68:        $multipartUpload = $this->createMultipartUpload($media);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:70:        $this->entityManager()->persist($media);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:76:        $imageConversions = $this->imageConversionRepository()->findByMediaId($media->id);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:77:        $videoConversions = $this->videoConversionRepository()->findByMediaId($media->id);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:78:        $restoredMultipartUpload = $this->multipartUploadRepository()->findByMediaId($media->id);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:92:        $media = $this->createMedia();
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:93:        $imageConversion = $this->createImageConversion($media);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:94:        $videoConversion = $this->createVideoConversion($media);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:96:        $this->entityManager()->persist($media);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:100:        $mediaId = $media->id;
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:104:        $restoredImageConversion = $this->imageConversionRepository()->findByMediaId($mediaId)->first();
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:107:        self::assertInstanceOf(Media::class, $restoredImageConversion->media);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:108:        self::assertTrue($mediaId->equals($restoredImageConversion->media->id));
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:112:        $restoredMedia = $this->mediaRepository()->findById($mediaId);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:115:        self::assertTrue($mediaId->equals($restoredMedia->id));
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:121:        $restoredMedia->markReady();
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:126:        $savedMedia = $this->mediaRepository()->findById($mediaId);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:129:        self::assertSame(MediaStatus::Ready, $savedMedia->status);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:136:        $media = $this->createMedia();
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:137:        $imageConversion = $this->createImageConversion($media);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:139:        $this->entityManager()->persist($media);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:142:        $mediaId = $media->id;
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:146:        $restoredMedia = $this->mediaRepository()->findById($mediaId);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:149:        $restoredMedia->markReady();
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:154:        $savedMedia = $this->mediaRepository()->findById($mediaId);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:155:        $savedImageConversion = $this->imageConversionRepository()->findByMediaId($mediaId)->first();
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:158:        self::assertSame(MediaStatus::Ready, $savedMedia->status);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:162:        self::assertTrue($mediaId->equals($savedImageConversion->mediaId));
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:167:        $media = $this->createMedia();
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:168:        $imageConversion = $this->createImageConversion($media);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:170:        $this->entityManager()->persist($media);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:173:        $mediaId = $media->id;
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:177:        $restoredImageConversion = $this->imageConversionRepository()->findByMediaId($mediaId)->first();
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:179:        self::assertInstanceOf(Media::class, $restoredImageConversion->media);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:180:        self::assertTrue($mediaId->equals($restoredImageConversion->media->id));
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:186:        $savedImageConversion = $this->imageConversionRepository()->findByMediaId($mediaId)->first();
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:188:        self::assertInstanceOf(Media::class, $savedImageConversion->media);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:189:        self::assertTrue($mediaId->equals($savedImageConversion->media->id));
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:194:        $media = $this->createMedia(
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:198:        $this->entityManager()->persist($media);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:201:        $expiredMedia = $this->mediaRepository()->findExpired(new \DateTimeImmutable());
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:203:        self::assertTrue($expiredMedia->contains(static fn(Media $expired) => $expired->id->equals($media->id)));
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:220:        $media = $this->createMedia();
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:221:        $imageConversion = $this->createImageConversion($media);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:222:        $videoConversion = $this->createVideoConversion($media);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:223:        $multipartUpload = $this->createMultipartUpload($media);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:225:        $this->entityManager()->persist($media);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:231:        $this->entityManager()->delete($media);
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:234:        self::assertCount(0, $this->imageConversionRepository()->findByMediaId($media->id));
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:235:        self::assertCount(0, $this->videoConversionRepository()->findByMediaId($media->id));
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:236:        self::assertNull($this->multipartUploadRepository()->findByMediaId($media->id));
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:257:    private function createImageConversion(Media $media): MediaImageConversion
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:262:            media: $media,
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:264:            status: MediaConversionStatus::Ready,
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:274:    private function createVideoConversion(Media $media): MediaVideoConversion
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:279:            media: $media,
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:281:            status: MediaConversionStatus::Ready,
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:293:    private function createMultipartUpload(Media $media): MediaMultipartUpload
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:296:            media: $media,
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:317:    private function mediaRepository(): MediaRepository
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:124:        self::assertStringContainsString('media-public', $publicUrl);
tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php:36:        $media = $this->createMedia(userId: UserId::generate());
tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php:37:        $media->markUploaded();
tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php:38:        $this->persist($media);
tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php:41:        $loader->method('load')->willReturn(new MediaUploaded(mediaId: $media->id->value(), conversions: []));
tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php:60:        $failedMedia = $this->mediaRepository()->findById($media->id);
tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php:72:        $media = $this->createMedia(userId: UserId::generate());
tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php:73:        $media->markUploaded();
tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php:74:        $this->persist($media);
tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php:77:        $loader->method('load')->willReturn(new MediaUploaded(mediaId: $media->id->value(), conversions: []));
tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php:109:        $loader->method('load')->willReturn(new MediaUploaded(mediaId: $missingMediaId, conversions: []));
tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php:152:                    mediaId: Uuid::uuid7()->toString(),
tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php:159:            self::assertSame('app.media.not_found', $exception->translationKey());
tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php:160:            self::assertSame('app.media.not_found', $exception->getMessage());
tests/Feature/Modules/Media/Application/MakeMediaPermanentHandlerTest.php:20:        $media = $this->createMedia(userId: $userId);
tests/Feature/Modules/Media/Application/MakeMediaPermanentHandlerTest.php:21:        $media->markUploaded();
tests/Feature/Modules/Media/Application/MakeMediaPermanentHandlerTest.php:22:        $this->persist($media);
tests/Feature/Modules/Media/Application/MakeMediaPermanentHandlerTest.php:26:            mediaId: $media->id->value(),
tests/Feature/Modules/Media/Application/MakeMediaPermanentHandlerTest.php:29:        self::assertTrue($media->expiration->isPermanent());
tests/Feature/Modules/Media/Application/MakeMediaPermanentHandlerTest.php:30:        self::assertSame($media->id->value(), $result->mediaId);
tests/Feature/Modules/Media/Application/MakeMediaPermanentHandlerTest.php:39:            mediaId: UserId::generate()->value(),
tests/Feature/Modules/Media/Application/MakeMediaPermanentHandlerTest.php:45:        $media = $this->createMedia(userId: UserId::generate());
tests/Feature/Modules/Media/Application/MakeMediaPermanentHandlerTest.php:46:        $media->markUploaded();
tests/Feature/Modules/Media/Application/MakeMediaPermanentHandlerTest.php:47:        $this->persist($media);
tests/Feature/Modules/Media/Application/MakeMediaPermanentHandlerTest.php:53:            mediaId: $media->id->value(),
tests/Feature/Modules/Media/Application/MakeMediaPermanentHandlerTest.php:60:        $media = $this->createMedia(userId: $userId);
tests/Feature/Modules/Media/Application/MakeMediaPermanentHandlerTest.php:61:        $this->persist($media);
tests/Feature/Modules/Media/Application/MakeMediaPermanentHandlerTest.php:67:            mediaId: $media->id->value(),
tests/Feature/Modules/Media/Application/MakeMediaPermanentHandlerTest.php:74:            mediaRepository: $this->mediaRepository(),
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:49:    public function testCompleteThenRelayProcessesMediaToReady(): void
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:52:        $media = $this->uploadedOriginal($bytes, MediaVisibility::Public);
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:54:        $this->completeUpload($media, [$this->conversionSpec(MediaImageConversionType::Thumbnail)]);
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:59:        $processedMedia = $this->mediaRepository()->findById($media->id);
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:61:        self::assertSame(MediaStatus::Ready, $processedMedia->status);
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:64:        $conversions = $this->imageConversionRepository()->findByMediaId($media->id);
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:68:            storageKey: $media->storageKey,
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:72:        $readyPath = MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Image, extension: 'jpg');
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:81:            mediaId: $media->id->value(),
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:84:        self::assertCount(1, $this->imageConversionRepository()->findByMediaId($media->id));
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:87:    public function testProcessingRecoversFromProcessingFailedToReady(): void
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:90:        $media = $this->uploadedOriginal($bytes, MediaVisibility::Public);
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:93:        $media->recordTemporaryProcessingError(
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:96:        $this->persist($media);
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:97:        self::assertSame(MediaStatus::ProcessingFailed, $media->status);
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:101:            mediaId: $media->id->value(),
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:105:        $readyMedia = $this->mediaRepository()->findById($media->id);
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:107:        self::assertSame(MediaStatus::Ready, $readyMedia->status);
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:111:        $conversions = $this->imageConversionRepository()->findByMediaId($media->id);
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:115:            storageKey: $media->storageKey,
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:119:        $readyPath = MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Image, extension: 'jpg');
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:132:        $media = $this->uploadedOriginal($corruptBytes, MediaVisibility::Public);
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:134:        $outboxEventId = $this->completeUpload($media, [$this->conversionSpec(MediaImageConversionType::Thumbnail)]);
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:139:        $failedMedia = $this->mediaRepository()->findById($media->id);
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:151:        $media = $this->createMedia(
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:157:        $this->persist($media);
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:161:            path: $media->path,
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:163:            mimeType: $media->mimeType,
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:165:        $this->track(MediaStorage::Upload, $media->path);
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:167:        return $media;
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:173:    private function completeUpload(Media $media, array $conversions): OutboxEventId
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:177:                userId: $media->uploadedById->value(),
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:178:                mediaId: $media->id->value(),
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php:47:        $media = $this->createReadyMedia($user->id, MediaVisibility::Public);
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php:50:            'mediaIds' => [$media->id->value()],
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php:55:        self::assertSame('media', $data['attachmentType']);
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php:56:        self::assertCount(1, $data['media']);
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php:57:        self::assertSame($media->id->value(), $data['media'][0]['mediaId']);
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php:64:        $media = $this->createReadyMedia($other->id, MediaVisibility::Public);
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php:67:            'mediaIds' => [$media->id->value()],
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php:71:    public function testRejectsMediaThatIsNotReady(): void
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php:74:        $media = $this->createUploadedMedia($user->id);
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php:77:            'mediaIds' => [$media->id->value()],
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php:86:            'mediaIds' => [UserId::generate()->value()],
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php:172:            'mediaIds' => ['not-a-uuid'],
tests/Feature/Modules/Media/Application/RecordMediaProcessingFailureHandlerTest.php:21:        $media = $this->createMedia(userId: UserId::generate());
tests/Feature/Modules/Media/Application/RecordMediaProcessingFailureHandlerTest.php:22:        $media->markUploaded();
tests/Feature/Modules/Media/Application/RecordMediaProcessingFailureHandlerTest.php:23:        $this->persist($media);
tests/Feature/Modules/Media/Application/RecordMediaProcessingFailureHandlerTest.php:26:            mediaId: $media->id->value(),
tests/Feature/Modules/Media/Application/RecordMediaProcessingFailureHandlerTest.php:31:        self::assertSame(MediaStatus::ProcessingFailed, $media->status);
tests/Feature/Modules/Media/Application/RecordMediaProcessingFailureHandlerTest.php:32:        self::assertSame(1, $media->processingAttempts->value());
tests/Feature/Modules/Media/Application/RecordMediaProcessingFailureHandlerTest.php:33:        self::assertSame('Не удалось обработать изображение', $media->processingError->value());
tests/Feature/Modules/Media/Application/RecordMediaProcessingFailureHandlerTest.php:36:    public function testKeepsReadyMediaIntact(): void
tests/Feature/Modules/Media/Application/RecordMediaProcessingFailureHandlerTest.php:40:        $media = $this->createMedia(userId: UserId::generate());
tests/Feature/Modules/Media/Application/RecordMediaProcessingFailureHandlerTest.php:41:        $media->markUploaded();
tests/Feature/Modules/Media/Application/RecordMediaProcessingFailureHandlerTest.php:42:        $media->markReadyMovedTo(
tests/Feature/Modules/Media/Application/RecordMediaProcessingFailureHandlerTest.php:44:            MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Image, extension: 'jpg'),
tests/Feature/Modules/Media/Application/RecordMediaProcessingFailureHandlerTest.php:46:        $this->persist($media);
tests/Feature/Modules/Media/Application/RecordMediaProcessingFailureHandlerTest.php:49:            mediaId: $media->id->value(),
tests/Feature/Modules/Media/Application/RecordMediaProcessingFailureHandlerTest.php:54:        self::assertSame(MediaStatus::Ready, $media->status);
tests/Feature/Modules/Media/Application/RecordMediaProcessingFailureHandlerTest.php:55:        self::assertSame(0, $media->processingAttempts->value());
tests/Feature/Modules/Media/Application/RecordMediaProcessingFailureHandlerTest.php:56:        self::assertTrue($media->processingError->isEmpty());
tests/Feature/Modules/Media/Application/RecordMediaProcessingFailureHandlerTest.php:64:            mediaId: UserId::generate()->value(),
tests/Feature/Modules/Media/Application/RecordMediaProcessingFailureHandlerTest.php:73:            mediaRepository: $this->mediaRepository(),
tests/Feature/Modules/Posts/Http/GetUserFeedHttpTest.php:42:        $this->persistPost($author->id, PostStatus::Published, deleted: true);
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:36:        $media = $this->createMedia(userId: $userId, size: MediaFileSize::fromInt(2048));
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:37:        $this->persist($media);
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:45:                mediaId: $media->id->value(),
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:52:        self::assertSame(MediaStatus::Uploaded, $media->status);
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:58:        $media = $this->createMedia(userId: $userId, size: MediaFileSize::fromInt(2048));
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:59:        $multipartUpload = $this->multipartUploadFor($media);
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:60:        $this->persist($media, $multipartUpload);
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:68:            mediaId: $media->id->value(),
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:82:            mediaId: UserId::generate()->value(),
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:90:        $media = $this->createMedia(userId: UserId::generate());
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:91:        $this->persist($media);
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:97:            mediaId: $media->id->value(),
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:106:        $media = $this->createMedia(userId: $userId);
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:107:        $media->markUploaded();
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:108:        $this->persist($media);
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:114:            mediaId: $media->id->value(),
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:124:        $media = $this->createMedia(userId: $userId);
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:125:        $this->persist($media);
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:131:            mediaId: $media->id->value(),
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:154:        $media = $this->createMedia(userId: $userId);
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:155:        $this->persist($media);
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:161:            mediaId: $media->id->value(),
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:170:        $media = $this->createMedia(userId: $userId);
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:171:        $this->persist($media);
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:180:            mediaId: $media->id->value(),
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:189:        $media = $this->createMedia(userId: $userId, size: MediaFileSize::fromInt(2048));
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:190:        $this->persist($media);
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:196:            mediaId: $media->id->value(),
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:207:            mediaRepository: $this->mediaRepository(),
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:208:            mediaMultipartUploadRepository: $this->multipartUploadRepository(),
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:209:            mediaFileService: $fileService,
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:234:    private function multipartUploadFor(Media $media): MediaMultipartUpload
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:237:            media: $media,
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:241:            fileSize: $media->size,
tests/Feature/Modules/Posts/Http/GetPostHttpTest.php:16:        $media = $this->createReadyMedia($user->id, MediaVisibility::Public);
tests/Feature/Modules/Posts/Http/GetPostHttpTest.php:20:            'mediaIds' => [$media->id->value()],
tests/Feature/Modules/Posts/Http/GetPostHttpTest.php:34:        self::assertCount(1, $data['media']);
tests/Feature/Modules/Posts/Http/GetPostHttpTest.php:110:        $post = $this->persistPost($author->id, PostStatus::Published, deleted: true);
tests/Feature/Modules/Posts/Http/PostsHttpTestCase.php:67:        $fileService->method('publicUrl')->willReturn('https://media.test/object.jpg');
tests/Feature/Modules/Posts/Http/PostsHttpTestCase.php:68:        $fileService->method('presignGet')->willReturn('https://media.test/signed.jpg');
tests/Feature/Modules/Posts/Http/PostsHttpTestCase.php:87:    protected function createReadyMedia(UserId $owner, MediaVisibility $visibility = MediaVisibility::Private): Media
tests/Feature/Modules/Posts/Http/PostsHttpTestCase.php:89:        $media = $this->newMedia($owner, $visibility);
tests/Feature/Modules/Posts/Http/PostsHttpTestCase.php:90:        $media->markUploaded();
tests/Feature/Modules/Posts/Http/PostsHttpTestCase.php:92:        $media->markReadyMovedTo(
tests/Feature/Modules/Posts/Http/PostsHttpTestCase.php:94:            MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Image, extension: 'jpg'),
tests/Feature/Modules/Posts/Http/PostsHttpTestCase.php:96:        $this->persist($media);
tests/Feature/Modules/Posts/Http/PostsHttpTestCase.php:98:        return $media;
tests/Feature/Modules/Posts/Http/PostsHttpTestCase.php:103:        $media = $this->newMedia($owner, MediaVisibility::Private);
tests/Feature/Modules/Posts/Http/PostsHttpTestCase.php:104:        $media->markUploaded();
tests/Feature/Modules/Posts/Http/PostsHttpTestCase.php:105:        $this->persist($media);
tests/Feature/Modules/Posts/Http/PostsHttpTestCase.php:107:        return $media;
tests/Feature/Modules/Posts/Http/PostsHttpTestCase.php:188:        bool $deleted = false,
tests/Feature/Modules/Posts/Http/PostsHttpTestCase.php:200:        if ($deleted) {
tests/Feature/Modules/Posts/Http/PostsHttpTestCase.php:201:            $post->softDelete(new \DateTimeImmutable());
tests/Feature/Modules/Posts/Http/PublishPostHttpTest.php:48:        $post = $this->persistPost($user->id, PostStatus::Draft, deleted: true);
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:73:        $media = $this->mediaRepository()->findById(MediaId::fromString($result->mediaId));
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:74:        self::assertNotNull($media);
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:75:        self::assertSame(MediaStatus::WaitingUpload, $media->status);
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:108:        self::assertNotNull($this->multipartUploadRepository()->findByMediaId(MediaId::fromString($result->mediaId)));
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:170:            mediaFileService: $fileService ?? $this->createStub(MediaFileServiceContract::class),
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:171:            mediaTypeResolver: new MediaTypeResolver(),
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:172:            mediaConfig: new MediaConfig(
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:8:use App\Modules\Media\Application\Query\GetMediaUrl\GetMediaUrlHandler;
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:9:use App\Modules\Media\Application\Query\GetMediaUrl\GetMediaUrlQuery;
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:24:final class GetMediaUrlHandlerTest extends MediaApplicationTestCase
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:28:        $media = $this->readyMedia(MediaVisibility::Public);
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:29:        $this->persist($media);
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:32:        $fileService->method('publicUrl')->willReturn('http://minio/media-public/object');
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:35:        $result = $this->handler($fileService)->handle(new GetMediaUrlQuery(
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:36:            mediaId: $media->id->value(),
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:41:        self::assertSame('http://minio/media-public/object', $result->url);
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:49:        $media = $this->readyMedia(MediaVisibility::Public);
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:50:        $this->persist($media);
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:53:        $fileService->method('publicUrl')->willReturn('http://minio/media-public/object');
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:56:        $result = $this->handler($fileService)->handle(new GetMediaUrlQuery(
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:57:            mediaId: $media->id->value(),
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:61:        self::assertSame('http://minio/media-public/object', $result->url);
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:67:        $media = $this->readyMedia(MediaVisibility::Private);
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:68:        $this->persist($media);
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:84:        $result = $this->handler($fileService)->handle(new GetMediaUrlQuery(
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:85:            mediaId: $media->id->value(),
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:103:        $media = $this->readyMedia(MediaVisibility::Private);
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:104:        $conversion = $this->thumbnailConversion($media);
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:105:        $this->persist($media, $conversion);
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:123:        $result = $this->handler($fileService)->handle(new GetMediaUrlQuery(
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:124:            mediaId: $media->id->value(),
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:143:        $media = $this->readyMedia(MediaVisibility::Private);
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:144:        $this->persist($media);
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:148:        $this->handler($this->createStub(MediaFileServiceContract::class))->handle(new GetMediaUrlQuery(
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:149:            mediaId: $media->id->value(),
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:155:    public function testRejectsMediaThatIsNotReady(): void
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:157:        $media = $this->createMedia(userId: UserId::generate());
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:158:        $this->persist($media);
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:163:            ->handle(new GetMediaUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 300));
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:171:            ->handle(new GetMediaUrlQuery(mediaId: UserId::generate()->value(), presignedTtlSeconds: 300));
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:174:    private function handler(MediaFileServiceContract $fileService): GetMediaUrlHandler
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:176:        return new GetMediaUrlHandler(
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:177:            mediaRepository: $this->mediaRepository(),
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:178:            mediaImageConversionRepository: $this->imageConversionRepository(),
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:179:            mediaFileService: $fileService,
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:185:        $media = $this->createMedia(userId: UserId::generate(), visibility: $visibility);
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:186:        $media->markUploaded();
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:188:        $media->markReadyMovedTo(
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:190:            MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Image, extension: 'jpg'),
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:193:        return $media;
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:196:    private function thumbnailConversion(Media $media): MediaImageConversion
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:199:            media: $media,
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:201:            status: MediaConversionStatus::Ready,
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:202:            storage: $media->storage,
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:204:                storageKey: $media->storageKey,
tests/Feature/Modules/Posts/Http/RepostPostHttpTest.php:63:        $original = $this->persistPost($author->id, PostStatus::Published, deleted: true);
tests/Feature/Modules/Media/Application/CheckMediaAttachableHandlerTest.php:21:    public function testAllowsReadyMediaOwnedByUser(): void
tests/Feature/Modules/Media/Application/CheckMediaAttachableHandlerTest.php:24:        $media = $this->readyMedia($owner);
tests/Feature/Modules/Media/Application/CheckMediaAttachableHandlerTest.php:25:        $this->persist($media);
tests/Feature/Modules/Media/Application/CheckMediaAttachableHandlerTest.php:28:            mediaId: $media->id->value(),
tests/Feature/Modules/Media/Application/CheckMediaAttachableHandlerTest.php:32:        self::assertSame($media->id->value(), $result->mediaId);
tests/Feature/Modules/Media/Application/CheckMediaAttachableHandlerTest.php:40:            mediaId: UserId::generate()->value(),
tests/Feature/Modules/Media/Application/CheckMediaAttachableHandlerTest.php:47:        $media = $this->readyMedia(UserId::generate());
tests/Feature/Modules/Media/Application/CheckMediaAttachableHandlerTest.php:48:        $this->persist($media);
tests/Feature/Modules/Media/Application/CheckMediaAttachableHandlerTest.php:53:            mediaId: $media->id->value(),
tests/Feature/Modules/Media/Application/CheckMediaAttachableHandlerTest.php:58:    public function testRejectsMediaThatIsNotReady(): void
tests/Feature/Modules/Media/Application/CheckMediaAttachableHandlerTest.php:61:        $media = $this->createMedia(userId: $owner);
tests/Feature/Modules/Media/Application/CheckMediaAttachableHandlerTest.php:62:        $this->persist($media);
tests/Feature/Modules/Media/Application/CheckMediaAttachableHandlerTest.php:67:            mediaId: $media->id->value(),
tests/Feature/Modules/Media/Application/CheckMediaAttachableHandlerTest.php:74:        return new CheckMediaAttachableHandler(mediaRepository: $this->mediaRepository());
tests/Feature/Modules/Media/Application/CheckMediaAttachableHandlerTest.php:79:        $media = $this->createMedia(userId: $owner, visibility: MediaVisibility::Public);
tests/Feature/Modules/Media/Application/CheckMediaAttachableHandlerTest.php:80:        $media->markUploaded();
tests/Feature/Modules/Media/Application/CheckMediaAttachableHandlerTest.php:81:        $media->markReadyMovedTo(
tests/Feature/Modules/Media/Application/CheckMediaAttachableHandlerTest.php:83:            MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Image, extension: 'jpg'),
tests/Feature/Modules/Media/Application/CheckMediaAttachableHandlerTest.php:86:        return $media;
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:18:        $media = $this->createMedia(userId: UserId::generate(), type: MediaType::Image);
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:19:        $this->persist($media);
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:21:        $handler = new CheckMediaIsImageHandler(mediaRepository: $this->mediaRepository());
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:23:        self::assertTrue($handler->handle(new CheckMediaIsImageQuery(mediaId: $media->id->value())));
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:28:        $media = $this->createMedia(userId: UserId::generate(), type: MediaType::Video, extension: 'mp4', mimeType: 'video/mp4');
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:29:        $this->persist($media);
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:31:        $handler = new CheckMediaIsImageHandler(mediaRepository: $this->mediaRepository());
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:33:        self::assertFalse($handler->handle(new CheckMediaIsImageQuery(mediaId: $media->id->value())));
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:38:        $handler = new CheckMediaIsImageHandler(mediaRepository: $this->mediaRepository());
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:40:        self::assertFalse($handler->handle(new CheckMediaIsImageQuery(mediaId: UserId::generate()->value())));
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:45:        $media = $this->createMedia(userId: UserId::generate());
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:46:        $this->persist($media);
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:48:        $handler = new CheckMediaExistsHandler(mediaRepository: $this->mediaRepository());
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:50:        self::assertTrue($handler->handle(new CheckMediaExistsQuery(mediaId: $media->id->value())));
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:55:        $handler = new CheckMediaExistsHandler(mediaRepository: $this->mediaRepository());
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php:57:        self::assertFalse($handler->handle(new CheckMediaExistsQuery(mediaId: UserId::generate()->value())));
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:8:use App\Modules\Media\Application\Query\FindMediaUrl\FindMediaUrlHandler;
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:9:use App\Modules\Media\Application\Query\FindMediaUrl\FindMediaUrlQuery;
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:17:final class FindMediaUrlHandlerTest extends MediaApplicationTestCase
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:22:            new FindMediaUrlQuery(mediaId: UserId::generate()->value(), presignedTtlSeconds: 300),
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:28:    public function testReturnsNullForMediaThatIsNotReady(): void
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:30:        $media = $this->createMedia(userId: UserId::generate());
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:31:        $this->persist($media);
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:34:            new FindMediaUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 300),
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:40:    public function testReturnsDirectUrlForReadyPublicMedia(): void
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:42:        $media = $this->readyMedia(MediaVisibility::Public);
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:43:        $this->persist($media);
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:46:        $fileService->method('publicUrl')->willReturn('http://minio/media-public/object');
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:50:            new FindMediaUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 300),
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:54:        self::assertSame('http://minio/media-public/object', $result->url);
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:58:    public function testReturnsPresignedUrlForReadyPrivateMedia(): void
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:60:        $media = $this->readyMedia(MediaVisibility::Private);
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:61:        $this->persist($media);
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:67:            new FindMediaUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 300),
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:75:    private function handler(MediaFileServiceContract $fileService): FindMediaUrlHandler
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:77:        return new FindMediaUrlHandler(
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:78:            mediaRepository: $this->mediaRepository(),
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:79:            mediaFileService: $fileService,
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:85:        $media = $this->createMedia(userId: UserId::generate(), visibility: $visibility);
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:86:        $media->markUploaded();
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:88:        $media->markReadyMovedTo(
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:90:            MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Image, extension: 'jpg'),
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:93:        return $media;
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:38:        $media = $this->createMedia(userId: $userId);
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:39:        $this->persist($media, $this->multipartUploadFor($media));
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:47:            mediaId: $media->id->value(),
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:50:        self::assertNull($this->mediaRepository()->findById($media->id));
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:53:    public function testDeletesReadyMediaWithoutMultipartAbort(): void
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:56:        $media = $this->createMedia(userId: $userId);
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:57:        $media->markUploaded();
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:58:        $this->persist($media);
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:66:            mediaId: $media->id->value(),
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:69:        self::assertNull($this->mediaRepository()->findById($media->id));
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:72:    public function testDeletesReadyMediaConversionObjectsFromStorage(): void
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:75:        $media = $this->readyMediaWithImageConversion($userId, MediaStorage::Public);
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:76:        $conversionPath = $this->imageConversionRepository()->findByMediaId($media->id)->first()?->path;
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:80:        $deletedPaths = [];
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:82:            function (MediaStorage $storage, MediaPath $path) use (&$deletedPaths): void {
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:83:                $deletedPaths[] = $path->value();
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:89:            mediaId: $media->id->value(),
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:92:        self::assertContains($conversionPath->value(), $deletedPaths);
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:93:        self::assertContains($media->path->value(), $deletedPaths);
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:94:        self::assertNull($this->mediaRepository()->findById($media->id));
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:95:        self::assertCount(0, $this->imageConversionRepository()->findByMediaId($media->id));
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:98:    public function testDeletesReadyMediaVideoConversionObjectsFromStorage(): void
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:101:        $media = $this->readyMediaWithVideoConversion($userId, MediaStorage::Public);
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:102:        $conversionPath = $this->videoConversionRepository()->findByMediaId($media->id)->first()?->path;
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:106:        $deletedPaths = [];
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:108:            function (MediaStorage $storage, MediaPath $path) use (&$deletedPaths): void {
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:109:                $deletedPaths[] = $path->value();
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:115:            mediaId: $media->id->value(),
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:118:        self::assertContains($conversionPath->value(), $deletedPaths);
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:119:        self::assertContains($media->path->value(), $deletedPaths);
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:120:        self::assertNull($this->mediaRepository()->findById($media->id));
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:121:        self::assertCount(0, $this->videoConversionRepository()->findByMediaId($media->id));
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:127:        $media = $this->createMedia(userId: $userId);
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:128:        $this->persist($media);
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:136:            mediaId: $media->id->value(),
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:139:        self::assertNull($this->mediaRepository()->findById($media->id));
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:148:            mediaId: UserId::generate()->value(),
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:154:        $media = $this->createMedia(userId: UserId::generate());
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:155:        $this->persist($media);
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:161:            mediaId: $media->id->value(),
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:168:            mediaRepository: $this->mediaRepository(),
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:169:            mediaMultipartUploadRepository: $this->multipartUploadRepository(),
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:170:            mediaImageConversionRepository: $this->imageConversionRepository(),
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:171:            mediaVideoConversionRepository: $this->videoConversionRepository(),
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:172:            mediaFileService: $fileService,
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:180:        $media = $this->createMedia(userId: $userId);
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:181:        $media->markUploaded();
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:182:        $media->markReadyMovedTo(
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:184:            MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Image, extension: 'jpg'),
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:186:        $this->persist($media);
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:189:            media: $media,
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:191:            status: MediaConversionStatus::Ready,
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:194:                storageKey: $media->storageKey,
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:204:        return $media;
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:209:        $media = $this->createMedia(userId: $userId, type: MediaType::Video, extension: 'mp4', mimeType: 'video/mp4');
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:210:        $media->markUploaded();
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:211:        $media->markReadyMovedTo(
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:213:            MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Video, extension: 'mp4'),
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:215:        $this->persist($media);
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:218:            media: $media,
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:220:            status: MediaConversionStatus::Ready,
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:223:                \sprintf('videos/%s/%s/normalized.mp4', $media->storageKey->shard(), $media->storageKey),
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:233:        return $media;
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:236:    private function multipartUploadFor(Media $media): MediaMultipartUpload
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:239:            media: $media,
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:243:            fileSize: $media->size,
tests/Feature/Modules/Media/Application/MediaApplicationTestCase.php:71:    protected function mediaRepository(): MediaRepository
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:29:        $media = $this->uploadedMedia(MediaVisibility::Private);
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:30:        $this->persist($media);
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:38:            mediaId: $media->id->value(),
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:42:        self::assertTrue($media->isReady());
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:43:        self::assertSame(MediaStorage::Private, $media->storage);
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:45:        $conversions = $this->imageConversionRepository()->findByMediaId($media->id);
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:58:        $media = $this->uploadedMedia(MediaVisibility::Private);
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:59:        $this->persist($media);
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:70:            mediaId: $media->id->value(),
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:74:        $conversion = $this->imageConversionRepository()->findByMediaId($media->id)->first();
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:81:        $media = $this->uploadedMedia(MediaVisibility::Public);
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:82:        $this->persist($media);
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:90:            mediaId: $media->id->value(),
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:94:        self::assertTrue($media->isReady());
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:95:        self::assertSame(MediaStorage::Public, $media->storage);
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:96:        self::assertCount(0, $this->imageConversionRepository()->findByMediaId($media->id));
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:99:    public function testIsNoOpWhenMediaAlreadyReady(): void
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:101:        $media = $this->uploadedMedia(MediaVisibility::Private);
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:102:        $media->markReadyMovedTo(
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:104:            MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Image, extension: 'jpg'),
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:106:        $this->persist($media);
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:113:            mediaId: $media->id->value(),
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:117:        self::assertTrue($media->isReady());
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:125:            mediaId: UserId::generate()->value(),
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:132:        // Медиа в waitingUpload (загрузка не подтверждена) — markReadyMovedTo запрещает переход.
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:133:        $media = $this->createMedia(userId: UserId::generate());
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:134:        $this->persist($media);
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:139:            mediaId: $media->id->value(),
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:159:            mediaRepository: $this->mediaRepository(),
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:160:            mediaFileService: $fileService,
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:161:            mediaImageProcessor: $processor,
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:169:        $media = $this->createMedia(userId: UserId::generate(), visibility: $visibility);
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:170:        $media->markUploaded();
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:172:        return $media;
tests/Feature/Modules/Posts/Repository/PostRepositoryTest.php:118:        $post->softDelete(new \DateTimeImmutable('2026-06-17 10:00:00'));
tests/Feature/Modules/Posts/Repository/PostRepositoryTest.php:241:        $media = $this->createMedia($user->id);
tests/Feature/Modules/Posts/Repository/PostRepositoryTest.php:242:        $this->persist($media);
tests/Feature/Modules/Posts/Repository/PostRepositoryTest.php:252:            media: PostMediaReference::fromString($media->id->value()),
tests/Feature/Modules/Posts/Repository/PostMediaRepositoryTest.php:38:            media: PostMediaReference::fromString($secondMedia->id->value()),
tests/Feature/Modules/Posts/Repository/PostMediaRepositoryTest.php:43:            media: PostMediaReference::fromString($firstMedia->id->value()),
tests/Feature/Modules/Posts/Repository/PostMediaRepositoryTest.php:53:        self::assertSame($firstMedia->id->value(), $attachments->first()?->media->value());
tests/Feature/Modules/Posts/Repository/PostMediaRepositoryTest.php:57:        self::assertCount(2, $restoredPost->media);
tests/Feature/Modules/Posts/Repository/PostMediaRepositoryTest.php:58:        self::assertSame(0, $restoredPost->media->first()?->position->value());
tests/Feature/Modules/Posts/Repository/PostMediaRepositoryTest.php:65:        $media = $this->createMedia($user->id);
tests/Feature/Modules/Posts/Repository/PostMediaRepositoryTest.php:66:        $this->persist($media);
tests/Feature/Modules/Posts/Repository/PostMediaRepositoryTest.php:71:            media: PostMediaReference::fromString($media->id->value()),
tests/Feature/Modules/Posts/Repository/PostMediaRepositoryTest.php:87:        $media = $this->createMedia($user->id);
tests/Feature/Modules/Posts/Repository/PostMediaRepositoryTest.php:88:        $this->persist($media);
tests/Feature/Modules/Posts/Repository/PostMediaRepositoryTest.php:94:            media: PostMediaReference::fromString($media->id->value()),
tests/Feature/Modules/Posts/Repository/PostMediaRepositoryTest.php:99:            media: PostMediaReference::fromString($media->id->value()),
tests/Feature/Modules/Posts/Repository/PostMediaRepositoryTest.php:112:        $media = $this->createMedia($user->id);
tests/Feature/Modules/Posts/Repository/PostMediaRepositoryTest.php:113:        $this->persist($media);
tests/Feature/Modules/Posts/Repository/PostMediaRepositoryTest.php:118:            media: PostMediaReference::fromString($media->id->value()),
tests/Feature/Modules/Posts/Repository/PostMediaRepositoryTest.php:124:        $this->entityManager()->delete($media);
tests/Feature/Modules/Posts/Repository/CommentRepositoryTest.php:55:        $deletedAt = new \DateTimeImmutable('2026-06-17 10:00:00');
tests/Feature/Modules/Posts/Repository/CommentRepositoryTest.php:59:            deletedBy: CommentDeletedBy::by($user->id),
tests/Feature/Modules/Posts/Repository/CommentRepositoryTest.php:60:            deletedAt: CommentDeletedAt::at($deletedAt),
tests/Feature/Modules/Posts/Repository/CommentRepositoryTest.php:70:        self::assertSame($user->id->value(), $restored->deletedBy->value());
tests/Feature/Modules/Posts/Repository/CommentRepositoryTest.php:71:        self::assertEquals($deletedAt, $restored->deletedAt->value());

codex
`PostViewAssembler` действительно вызывает строгий `GetMediaUrl` для каждого вложения. Проверяю, чем это подтверждается в `Media` и есть ли тест чтения поста/ленты после удаления или смены статуса вложенного медиа.
exec
/bin/zsh -lc "nl -ba app/src/Modules/Media/Application/Query/GetMediaUrl/GetMediaUrlHandler.php | sed -n '1,180p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Media\Application\Query\GetMediaUrl;
     6	
     7	use App\Modules\Media\Application\Contract\MediaFileServiceContract;
     8	use App\Modules\Media\Application\Dto\MediaUrlResult;
     9	use App\Modules\Media\Domain\Entity\MediaImageConversion;
    10	use App\Modules\Media\Domain\Enum\MediaImageConversionType;
    11	use App\Modules\Media\Domain\Enum\MediaStorage;
    12	use App\Modules\Media\Domain\Enum\MediaVisibility;
    13	use App\Modules\Media\Domain\ValueObject\MediaId;
    14	use App\Modules\Media\Domain\ValueObject\MediaPath;
    15	use App\Modules\Media\Domain\ValueObject\MediaPresignedTtl;
    16	use App\Modules\Media\Repository\MediaImageConversionRepository;
    17	use App\Modules\Media\Repository\MediaRepository;
    18	use App\Shared\Domain\Exception\NotFoundException;
    19	
    20	final readonly class GetMediaUrlHandler
    21	{
    22	    public function __construct(
    23	        private MediaRepository $mediaRepository,
    24	        private MediaImageConversionRepository $mediaImageConversionRepository,
    25	        private MediaFileServiceContract $mediaFileService,
    26	    ) {}
    27	
    28	    public function handle(GetMediaUrlQuery $query): MediaUrlResult
    29	    {
    30	        $media = $this->mediaRepository->findById(MediaId::fromString($query->mediaId))
    31	            ?? throw new NotFoundException('app.media.not_found');
    32	
    33	        if (!$media->isReady()) {
    34	            throw new NotFoundException('app.media.not_ready');
    35	        }
    36	
    37	        if ($query->conversionType !== null) {
    38	            $conversion = $this->findConversion(mediaId: $media->id, type: $query->conversionType)
    39	                ?? throw new NotFoundException('app.media.conversion_not_found');
    40	
    41	            return $this->buildUrl(
    42	                visibility: $media->visibility,
    43	                storage: $conversion->storage,
    44	                path: $conversion->path,
    45	                presignedTtlSeconds: $query->presignedTtlSeconds,
    46	            );
    47	        }
    48	
    49	        return $this->buildUrl(
    50	            visibility: $media->visibility,
    51	            storage: $media->storage,
    52	            path: $media->path,
    53	            presignedTtlSeconds: $query->presignedTtlSeconds,
    54	        );
    55	    }
    56	
    57	    private function findConversion(MediaId $mediaId, MediaImageConversionType $type): MediaImageConversion|null
    58	    {
    59	        return $this->mediaImageConversionRepository->findByMediaId($mediaId)->first(
    60	            static fn(MediaImageConversion $conversion): bool => $conversion->type === $type,
    61	        );
    62	    }
    63	
    64	    private function buildUrl(
    65	        MediaVisibility $visibility,
    66	        MediaStorage $storage,
    67	        MediaPath $path,
    68	        int $presignedTtlSeconds,
    69	    ): MediaUrlResult {
    70	        if ($visibility === MediaVisibility::Public) {
    71	            return new MediaUrlResult(
    72	                url: $this->mediaFileService->publicUrl(storage: $storage, path: $path),
    73	                expiresAt: null,
    74	            );
    75	        }
    76	
    77	        $expiresAt = new \DateTimeImmutable()->add(
    78	            new \DateInterval(\sprintf('PT%dS', MediaPresignedTtl::fromInt($presignedTtlSeconds)->value())),
    79	        );
    80	
    81	        return new MediaUrlResult(
    82	            url: $this->mediaFileService->presignGet(storage: $storage, path: $path, expiresAt: $expiresAt),
    83	            expiresAt: $expiresAt,
    84	        );
    85	    }
    86	}

exec
/bin/zsh -lc "nl -ba app/src/Modules/Media/Application/Query/FindMediaUrl/FindMediaUrlHandler.php | sed -n '1,180p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
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
/bin/zsh -lc "nl -ba app/src/Modules/Posts/Application/Command/LikeComment/LikeCommentHandler.php | sed -n '1,180p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Posts\Application\Command\LikeComment;
     6	
     7	use App\Modules\Posts\Application\Notification\PostNotificationType;
     8	use App\Modules\Posts\Application\Post\CommentComposer;
     9	use App\Modules\Posts\Domain\Entity\CommentLike;
    10	use App\Modules\Posts\Domain\ValueObject\CommentId;
    11	use App\Modules\Posts\Repository\CommentLikeRepository;
    12	use App\Modules\Posts\Repository\CommentRepository;
    13	use App\Shared\Domain\Exception\NotFoundException;
    14	use App\Shared\Domain\ValueObject\UserId;
    15	use Cycle\ORM\EntityManagerInterface;
    16	use GianTiaga\SpiralCqrs\Attribute\LogOperation;
    17	use GianTiaga\SpiralCqrs\Attribute\Transactional;
    18	use Psr\Log\LoggerInterface;
    19	
    20	/**
    21	 * Лайк комментария. Удалённый/несуществующий комментарий -> 404. Идемпотентно: повторный лайк —
    22	 * no-op. Автору комментария (кроме self) стейджится comment_like.
    23	 */
    24	final readonly class LikeCommentHandler
    25	{
    26	    public function __construct(
    27	        private CommentRepository $commentRepository,
    28	        private CommentLikeRepository $commentLikeRepository,
    29	        private CommentComposer $composer,
    30	        private EntityManagerInterface $entityManager,
    31	        private LoggerInterface $logger,
    32	    ) {}
    33	
    34	    #[Transactional]
    35	    #[LogOperation]
    36	    public function handle(LikeCommentCommand $command): void
    37	    {
    38	        $userId = UserId::fromString($command->authUserId);
    39	
    40	        $comment = $this->commentRepository->findById(CommentId::fromString($command->commentId))
    41	            ?? throw new NotFoundException('app.posts.comment_not_found');
    42	
    43	        if ($comment->isDeleted()) {
    44	            throw new NotFoundException('app.posts.comment_not_found');
    45	        }
    46	
    47	        if ($this->commentLikeRepository->existsByCommentAndUser(commentId: $comment->id, userId: $userId)) {
    48	            $this->logger->debug(message: 'Повторный лайк комментария — no-op.', context: ['commentId' => $comment->id->value()]);
    49	
    50	            return;
    51	        }
    52	
    53	        $this->entityManager->persist(CommentLike::create(commentId: $comment->id, userId: $userId));
    54	        $comment->incrementLikes();
    55	        $this->entityManager->persist($comment);
    56	
    57	        $this->composer->notifyCommentAuthor(
    58	            type: PostNotificationType::CommentLike,
    59	            commentAuthor: $comment->userId,
    60	            actorUserId: $command->authUserId,
    61	            commentId: $comment->id->value(),
    62	        );
    63	
    64	        $this->entityManager->run();
    65	    }
    66	}

exec
/bin/zsh -lc "nl -ba app/src/Modules/User/Application/Profile/UserPublicProfileAssembler.php | sed -n '1,180p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\User\Application\Profile;
     6	
     7	use App\Modules\Media\Application\Query\FindMediaUrl\FindMediaUrlHandler;
     8	use App\Modules\Media\Application\Query\FindMediaUrl\FindMediaUrlQuery;
     9	use App\Modules\User\Application\Dto\UserPublicProfileView;
    10	use App\Modules\User\Domain\Entity\User;
    11	use App\Shared\Infrastructure\Configuration\User\UserConfig;
    12	
    13	/**
    14	 * Собирает публичный профиль из доменной сущности User, разрешая ссылку на аватар через модуль
    15	 * Media. Аватар всегда непустой: если у пользователя нет аватара или его медиа недоступно
    16	 * (удалено/не готово) — подставляется значение по умолчанию из UserConfig.
    17	 *
    18	 * FindMediaUrl вызывается напрямую (а не через QueryBus), потому что это единственный сценарий,
    19	 * возвращающий nullable, и обёртка шины теряет null из вывода типов — прямой вызов сохраняет
    20	 * контракт «медиа недоступно -> null -> дефолт» без try-catch и без подавления статанализа.
    21	 */
    22	final readonly class UserPublicProfileAssembler
    23	{
    24	    // Аватары публичны (прямой URL без срока), поэтому TTL фактически не используется; значение
    25	    // нужно только для приватной ветки FindMediaUrl и берётся техническим дефолтом рядом с местом.
    26	    private const int AVATAR_URL_TTL_SECONDS = 3600;
    27	
    28	    public function __construct(
    29	        private FindMediaUrlHandler $findMediaUrlHandler,
    30	        private UserConfig $userConfig,
    31	    ) {}
    32	
    33	    public function fromUser(User $user): UserPublicProfileView
    34	    {
    35	        return new UserPublicProfileView(
    36	            userId: $user->id->value(),
    37	            name: $user->name->value(),
    38	            avatarUrl: $this->resolveAvatarUrl($user),
    39	            locale: $user->locale->value,
    40	        );
    41	    }
    42	
    43	    private function resolveAvatarUrl(User $user): string
    44	    {
    45	        $mediaId = $user->avatar->value();
    46	
    47	        if ($mediaId === null) {
    48	            return $this->userConfig->defaultAvatarUrl;
    49	        }
    50	
    51	        $mediaUrl = $this->findMediaUrlHandler->handle(
    52	            new FindMediaUrlQuery(mediaId: $mediaId, presignedTtlSeconds: self::AVATAR_URL_TTL_SECONDS),
    53	        );
    54	
    55	        if ($mediaUrl === null) {
    56	            return $this->userConfig->defaultAvatarUrl;
    57	        }
    58	
    59	        return $mediaUrl->url;
    60	    }
    61	}

exec
/bin/zsh -lc "nl -ba app/src/Modules/Posts/Application/Command/UnlikeComment/UnlikeCommentHandler.php | sed -n '1,180p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Posts\Application\Command\UnlikeComment;
     6	
     7	use App\Modules\Posts\Domain\ValueObject\CommentId;
     8	use App\Modules\Posts\Repository\CommentLikeRepository;
     9	use App\Modules\Posts\Repository\CommentRepository;
    10	use App\Shared\Domain\Exception\NotFoundException;
    11	use App\Shared\Domain\ValueObject\UserId;
    12	use Cycle\ORM\EntityManagerInterface;
    13	use GianTiaga\SpiralCqrs\Attribute\LogOperation;
    14	use GianTiaga\SpiralCqrs\Attribute\Transactional;
    15	use Psr\Log\LoggerInterface;
    16	
    17	/**
    18	 * Снятие лайка с комментария. Несуществующий комментарий -> 404. Идемпотентно: снятие
    19	 * отсутствующего лайка — no-op; счётчик не уходит ниже нуля.
    20	 */
    21	final readonly class UnlikeCommentHandler
    22	{
    23	    public function __construct(
    24	        private CommentRepository $commentRepository,
    25	        private CommentLikeRepository $commentLikeRepository,
    26	        private EntityManagerInterface $entityManager,
    27	        private LoggerInterface $logger,
    28	    ) {}
    29	
    30	    #[Transactional]
    31	    #[LogOperation]
    32	    public function handle(UnlikeCommentCommand $command): void
    33	    {
    34	        $userId = UserId::fromString($command->authUserId);
    35	
    36	        $comment = $this->commentRepository->findById(CommentId::fromString($command->commentId))
    37	            ?? throw new NotFoundException('app.posts.comment_not_found');
    38	
    39	        $like = $this->commentLikeRepository->findByCommentAndUser(commentId: $comment->id, userId: $userId);
    40	
    41	        if ($like === null) {
    42	            $this->logger->debug(message: 'Снятие отсутствующего лайка комментария — no-op.', context: ['commentId' => $comment->id->value()]);
    43	
    44	            return;
    45	        }
    46	
    47	        $this->entityManager->delete($like);
    48	
    49	        if ($comment->likesCount->value() > 0) {
    50	            $comment->decrementLikes();
    51	            $this->entityManager->persist($comment);
    52	        }
    53	
    54	        $this->entityManager->run();
    55	    }
    56	}

exec
/bin/zsh -lc "nl -ba app/src/Modules/Posts/Application/Command/CommentPost/CommentPostHandler.php | sed -n '1,180p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Posts\Application\Command\CommentPost;
     6	
     7	use App\Modules\Posts\Application\Notification\PostNotificationType;
     8	use App\Modules\Posts\Application\Post\CommentComposer;
     9	use App\Modules\Posts\Application\Post\PostVisibilityPolicy;
    10	use App\Modules\Posts\Application\View\CommentView;
    11	use App\Modules\Posts\Application\View\CommentViewAssembler;
    12	use App\Modules\Posts\Domain\Entity\Comment;
    13	use App\Modules\Posts\Domain\ValueObject\CommentParent;
    14	use App\Modules\Posts\Domain\ValueObject\CommentText;
    15	use App\Modules\Posts\Domain\ValueObject\PostId;
    16	use App\Modules\Posts\Repository\PostRepository;
    17	use App\Shared\Domain\Exception\NotFoundException;
    18	use App\Shared\Domain\ValueObject\UserId;
    19	use Cycle\ORM\EntityManagerInterface;
    20	use GianTiaga\SpiralCqrs\Attribute\LogOperation;
    21	use GianTiaga\SpiralCqrs\Attribute\Transactional;
    22	
    23	/**
    24	 * Комментарий верхнего уровня к записи. Невидимая/недоступная запись -> 404. Увеличивает счётчик
    25	 * комментариев записи; автору записи (кроме self) стейджится post_commented, упомянутым —
    26	 * comment_mention, с дедупом по получателю (mention перебивает post_commented).
    27	 */
    28	final readonly class CommentPostHandler
    29	{
    30	    public function __construct(
    31	        private PostRepository $postRepository,
    32	        private CommentComposer $composer,
    33	        private CommentViewAssembler $commentViewAssembler,
    34	        private EntityManagerInterface $entityManager,
    35	    ) {}
    36	
    37	    #[Transactional]
    38	    #[LogOperation]
    39	    public function handle(CommentPostCommand $command): CommentView
    40	    {
    41	        $authUserId = UserId::fromString($command->authUserId);
    42	
    43	        $post = $this->postRepository->findById(PostId::fromString($command->postId))
    44	            ?? throw new NotFoundException('app.posts.not_found');
    45	
    46	        if (!PostVisibilityPolicy::isActionable($post)) {
    47	            throw new NotFoundException('app.posts.not_found');
    48	        }
    49	
    50	        $comment = Comment::create(
    51	            postId: $post->id,
    52	            userId: $authUserId,
    53	            text: CommentText::fromString($command->text),
    54	            parent: CommentParent::none(),
    55	        );
    56	        $this->entityManager->persist($comment);
    57	
    58	        $post->incrementComments();
    59	        $this->entityManager->persist($post);
    60	
    61	        $this->composer->attachMentionsAndNotify(
    62	            comment: $comment,
    63	            mentionIds: $command->mentions,
    64	            actorUserId: $command->authUserId,
    65	            primaryRecipientUserId: $post->userId->value(),
    66	            primaryType: PostNotificationType::PostCommented,
    67	        );
    68	
    69	        $this->entityManager->run();
    70	
    71	        return $this->commentViewAssembler->fromComment(comment: $comment, viewer: $authUserId);
    72	    }
    73	}

codex
Нашёл отдельный факт по второму спорному месту: `UnlikeCommentHandler` не проверяет, что сам комментарий удалён, в отличие от `LikeCommentHandler`. Это сильнее, чем замечание ревью про видимость записи, потому что снятие лайка возможно даже с уже удалённого комментария.
exec
/bin/zsh -lc "nl -ba app/src/Modules/Posts/Application/Command/DeleteComment/DeleteCommentHandler.php | sed -n '1,200p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Posts\Application\Command\DeleteComment;
     6	
     7	use App\Modules\Posts\Domain\ValueObject\CommentDeletedAt;
     8	use App\Modules\Posts\Domain\ValueObject\CommentDeletedBy;
     9	use App\Modules\Posts\Domain\ValueObject\CommentDeletionReason;
    10	use App\Modules\Posts\Domain\ValueObject\CommentId;
    11	use App\Modules\Posts\Domain\ValueObject\PostId;
    12	use App\Modules\Posts\Repository\CommentRepository;
    13	use App\Modules\Posts\Repository\PostRepository;
    14	use App\Shared\Domain\Exception\ForbiddenException;
    15	use App\Shared\Domain\Exception\NotFoundException;
    16	use App\Shared\Domain\ValueObject\UserId;
    17	use Cycle\ORM\EntityManagerInterface;
    18	use GianTiaga\SpiralCqrs\Attribute\LogOperation;
    19	use GianTiaga\SpiralCqrs\Attribute\Transactional;
    20	use Psr\Log\LoggerInterface;
    21	
    22	/**
    23	 * Мягкое удаление своего комментария. Чужой -> 403, несуществующий -> 404, повторное удаление ->
    24	 * идемпотентный no-op. Зеркалит счётчик: комментарий верхнего уровня уменьшает счётчик записи,
    25	 * ответ — счётчик ответов родителя. Счётчики не уходят ниже нуля.
    26	 */
    27	final readonly class DeleteCommentHandler
    28	{
    29	    public function __construct(
    30	        private CommentRepository $commentRepository,
    31	        private PostRepository $postRepository,
    32	        private EntityManagerInterface $entityManager,
    33	        private LoggerInterface $logger,
    34	    ) {}
    35	
    36	    #[Transactional]
    37	    #[LogOperation]
    38	    public function handle(DeleteCommentCommand $command): void
    39	    {
    40	        $comment = $this->commentRepository->findById(CommentId::fromString($command->commentId))
    41	            ?? throw new NotFoundException('app.posts.comment_not_found');
    42	
    43	        if (!$comment->userId->equals(UserId::fromString($command->authUserId))) {
    44	            throw new ForbiddenException('app.posts.forbidden');
    45	        }
    46	
    47	        if ($comment->isDeleted()) {
    48	            $this->logger->debug(message: 'Повторное удаление комментария — no-op.', context: ['commentId' => $comment->id->value()]);
    49	
    50	            return;
    51	        }
    52	
    53	        $now = new \DateTimeImmutable();
    54	        $comment->delete(
    55	            deletedBy: CommentDeletedBy::by(UserId::fromString($command->authUserId)),
    56	            deletedAt: CommentDeletedAt::at($now),
    57	            deletionReason: CommentDeletionReason::none(),
    58	        );
    59	        $this->entityManager->persist($comment);
    60	
    61	        $this->mirrorCounter(postId: $comment->postId->value(), parentId: $comment->parent->value());
    62	
    63	        $this->entityManager->run();
    64	
    65	        $this->logger->debug(message: 'Комментарий удалён.', context: ['commentId' => $comment->id->value()]);
    66	    }
    67	
    68	    private function mirrorCounter(string $postId, string|null $parentId): void
    69	    {
    70	        if ($parentId === null) {
    71	            $post = $this->postRepository->findById(PostId::fromString($postId));
    72	
    73	            if ($post !== null && $post->commentsCount->value() > 0) {
    74	                $post->decrementComments();
    75	                $this->entityManager->persist($post);
    76	            }
    77	
    78	            return;
    79	        }
    80	
    81	        $parent = $this->commentRepository->findById(CommentId::fromString($parentId));
    82	
    83	        if ($parent !== null && $parent->repliesCount->value() > 0) {
    84	            $parent->decrementReplies();
    85	            $this->entityManager->persist($parent);
    86	        }
    87	    }
    88	}

exec
/bin/zsh -lc "nl -ba app/src/Modules/Posts/Application/Command/ReplyComment/ReplyCommentHandler.php | sed -n '1,220p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Posts\Application\Command\ReplyComment;
     6	
     7	use App\Modules\Posts\Application\Notification\PostNotificationType;
     8	use App\Modules\Posts\Application\Post\CommentComposer;
     9	use App\Modules\Posts\Application\Post\PostVisibilityPolicy;
    10	use App\Modules\Posts\Application\View\CommentView;
    11	use App\Modules\Posts\Application\View\CommentViewAssembler;
    12	use App\Modules\Posts\Domain\Entity\Comment;
    13	use App\Modules\Posts\Domain\ValueObject\CommentId;
    14	use App\Modules\Posts\Domain\ValueObject\CommentParent;
    15	use App\Modules\Posts\Domain\ValueObject\CommentText;
    16	use App\Modules\Posts\Repository\CommentRepository;
    17	use App\Modules\Posts\Repository\PostRepository;
    18	use App\Shared\Domain\Exception\NotFoundException;
    19	use App\Shared\Domain\ValueObject\UserId;
    20	use Cycle\ORM\EntityManagerInterface;
    21	use GianTiaga\SpiralCqrs\Attribute\LogOperation;
    22	use GianTiaga\SpiralCqrs\Attribute\Transactional;
    23	
    24	/**
    25	 * Ответ на комментарий. Удалённый родитель/недоступная запись -> 404. Увеличивает счётчик ответов
    26	 * родителя; автору родителя (кроме self) стейджится comment_reply, упомянутым — comment_mention
    27	 * (с дедупом). Автор записи post_commented на ответ НЕ получает (решение 14).
    28	 */
    29	final readonly class ReplyCommentHandler
    30	{
    31	    public function __construct(
    32	        private CommentRepository $commentRepository,
    33	        private PostRepository $postRepository,
    34	        private CommentComposer $composer,
    35	        private CommentViewAssembler $commentViewAssembler,
    36	        private EntityManagerInterface $entityManager,
    37	    ) {}
    38	
    39	    #[Transactional]
    40	    #[LogOperation]
    41	    public function handle(ReplyCommentCommand $command): CommentView
    42	    {
    43	        $authUserId = UserId::fromString($command->authUserId);
    44	
    45	        $parent = $this->commentRepository->findById(CommentId::fromString($command->commentId))
    46	            ?? throw new NotFoundException('app.posts.comment_not_found');
    47	
    48	        if ($parent->isDeleted()) {
    49	            throw new NotFoundException('app.posts.comment_not_found');
    50	        }
    51	
    52	        // Запись существующего комментария всегда есть (FK), но проверку null оставляем в одном
    53	        // условии с доступностью: ответ допустим только на доступную (опубликованную) запись.
    54	        $post = $this->postRepository->findById($parent->postId);
    55	
    56	        if ($post === null || !PostVisibilityPolicy::isActionable($post)) {
    57	            throw new NotFoundException('app.posts.not_found');
    58	        }
    59	
    60	        $comment = Comment::create(
    61	            postId: $parent->postId,
    62	            userId: $authUserId,
    63	            text: CommentText::fromString($command->text),
    64	            parent: CommentParent::pointingTo($parent->id->value()),
    65	        );
    66	        $this->entityManager->persist($comment);
    67	
    68	        $parent->incrementReplies();
    69	        $this->entityManager->persist($parent);
    70	
    71	        $this->composer->attachMentionsAndNotify(
    72	            comment: $comment,
    73	            mentionIds: $command->mentions,
    74	            actorUserId: $command->authUserId,
    75	            primaryRecipientUserId: $parent->userId->value(),
    76	            primaryType: PostNotificationType::CommentReply,
    77	        );
    78	
    79	        $this->entityManager->run();
    80	
    81	        return $this->commentViewAssembler->fromComment(comment: $comment, viewer: $authUserId);
    82	    }
    83	}

exec
/bin/zsh -lc "nl -ba app/src/Modules/Posts/Application/Command/DeletePost/DeletePostHandler.php | sed -n '1,180p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Posts\Application\Command\DeletePost;
     6	
     7	use App\Modules\Posts\Domain\ValueObject\PostId;
     8	use App\Modules\Posts\Repository\PostRepository;
     9	use App\Shared\Domain\Exception\ForbiddenException;
    10	use App\Shared\Domain\Exception\NotFoundException;
    11	use App\Shared\Domain\ValueObject\UserId;
    12	use Cycle\ORM\EntityManagerInterface;
    13	use GianTiaga\SpiralCqrs\Attribute\LogOperation;
    14	use GianTiaga\SpiralCqrs\Attribute\Transactional;
    15	use Psr\Log\LoggerInterface;
    16	
    17	/**
    18	 * Мягкое удаление своей записи. Чужая -> 403, несуществующая -> 404, повторное удаление ->
    19	 * идемпотентный no-op (счётчик репостов оригинала не уводится в минус повторно). Если удаляемая
    20	 * запись — репост, у оригинала уменьшается счётчик репостов.
    21	 */
    22	final readonly class DeletePostHandler
    23	{
    24	    public function __construct(
    25	        private PostRepository $postRepository,
    26	        private EntityManagerInterface $entityManager,
    27	        private LoggerInterface $logger,
    28	    ) {}
    29	
    30	    #[Transactional]
    31	    #[LogOperation]
    32	    public function handle(DeletePostCommand $command): void
    33	    {
    34	        $post = $this->postRepository->findById(PostId::fromString($command->postId))
    35	            ?? throw new NotFoundException('app.posts.not_found');
    36	
    37	        if (!$post->userId->equals(UserId::fromString($command->authUserId))) {
    38	            throw new ForbiddenException('app.posts.forbidden');
    39	        }
    40	
    41	        if ($post->deletion->isDeleted()) {
    42	            $this->logger->debug(message: 'Повторное удаление записи — no-op.', context: ['postId' => $post->id->value()]);
    43	
    44	            return;
    45	        }
    46	
    47	        $post->softDelete(new \DateTimeImmutable());
    48	        $this->entityManager->persist($post);
    49	
    50	        $originalId = $post->original->value();
    51	
    52	        if ($originalId !== null) {
    53	            $original = $this->postRepository->findById(PostId::fromString($originalId));
    54	
    55	            if ($original !== null && $original->repostsCount->value() > 0) {
    56	                $original->decrementReposts();
    57	                $this->entityManager->persist($original);
    58	            }
    59	        }
    60	
    61	        $this->entityManager->run();
    62	
    63	        $this->logger->debug(message: 'Запись удалена.', context: ['postId' => $post->id->value()]);
    64	    }
    65	}

exec
/bin/zsh -lc "nl -ba app/src/Modules/Posts/Application/Post/CommentComposer.php | sed -n '1,240p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
exec
/bin/zsh -lc "nl -ba app/src/Modules/Posts/Application/Post/PostContentComposer.php | sed -n '1,260p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Posts\Application\Post;
     6	
     7	use App\Modules\Posts\Application\Notification\CommentNotificationTarget;
     8	use App\Modules\Posts\Application\Notification\PostNotificationType;
     9	use App\Modules\Posts\Application\Notification\PostNotifier;
    10	use App\Modules\Posts\Domain\Entity\Comment;
    11	use App\Modules\Posts\Domain\Entity\CommentMention;
    12	use App\Modules\User\Application\Dto\UserPublicProfileCollection;
    13	use App\Modules\User\Application\Dto\UserPublicProfileView;
    14	use App\Modules\User\Application\Query\GetUserPublicProfile\GetUserPublicProfileHandler;
    15	use App\Modules\User\Application\Query\GetUserPublicProfile\GetUserPublicProfileQuery;
    16	use App\Modules\User\Application\Query\GetUserPublicProfiles\GetUserPublicProfilesHandler;
    17	use App\Modules\User\Application\Query\GetUserPublicProfiles\GetUserPublicProfilesQuery;
    18	use App\Shared\Domain\Exception\ValidationException;
    19	use App\Shared\Domain\ValueObject\UserId;
    20	use Cycle\ORM\EntityManagerInterface;
    21	use GianTiaga\SpiralCqrs\QueryBusInterface;
    22	
    23	/**
    24	 * Сборка содержимого комментария: сохранение упоминаний и стейджинг уведомлений с дедупликацией по
    25	 * получателю. На одного получателя уходит ровно одно уведомление с наибольшим приоритетом
    26	 * (mention > comment_reply > post_commented), self-получатель исключается. Все deep-link
    27	 * комментария ведут на сам комментарий.
    28	 */
    29	final readonly class CommentComposer
    30	{
    31	    public function __construct(
    32	        private QueryBusInterface $queryBus,
    33	        private GetUserPublicProfileHandler $getUserPublicProfileHandler,
    34	        private GetUserPublicProfilesHandler $getUserPublicProfilesHandler,
    35	        private PostNotifier $postNotifier,
    36	        private EntityManagerInterface $entityManager,
    37	    ) {}
    38	
    39	    /**
    40	     * Сохраняет упоминания комментария и стейджит уведомления. primaryRecipient — автор записи
    41	     * (для post_commented у комментария верхнего уровня) либо автор родителя (для comment_reply у
    42	     * ответа); его вид задаёт primaryType. Упоминание перебивает primary при совпадении получателя.
    43	     *
    44	     * @param list<string> $mentionIds
    45	     */
    46	    public function attachMentionsAndNotify(
    47	        Comment $comment,
    48	        array $mentionIds,
    49	        string $actorUserId,
    50	        string $primaryRecipientUserId,
    51	        PostNotificationType $primaryType,
    52	    ): void {
    53	        $uniqueMentions = \array_values(\array_unique($mentionIds));
    54	
    55	        foreach ($uniqueMentions as $mentionId) {
    56	            $this->entityManager->persist(CommentMention::create(commentId: $comment->id, userId: UserId::fromString($mentionId)));
    57	        }
    58	
    59	        $mentionProfiles = $uniqueMentions === []
    60	            ? new UserPublicProfileCollection()
    61	            : $this->resolveRecipients($uniqueMentions);
    62	
    63	        $actor = $this->profile($actorUserId);
    64	
    65	        /** @var array<string, CommentNotificationTarget> $targets */
    66	        $targets = [];
    67	
    68	        foreach ($mentionProfiles as $mentionProfile) {
    69	            $targets[$mentionProfile->userId] = new CommentNotificationTarget(
    70	                type: PostNotificationType::CommentMention,
    71	                recipient: $mentionProfile,
    72	            );
    73	        }
    74	
    75	        // Упоминание (приоритет выше) перебивает primary: добавляем primary только если получатель
    76	        // ещё не охвачен упоминанием.
    77	        if (!isset($targets[$primaryRecipientUserId])) {
    78	            $targets[$primaryRecipientUserId] = new CommentNotificationTarget(
    79	                type: $primaryType,
    80	                recipient: $this->profile($primaryRecipientUserId),
    81	            );
    82	        }
    83	
    84	        foreach ($targets as $target) {
    85	            $this->postNotifier->notify(
    86	                type: $target->type,
    87	                actor: $actor,
    88	                recipient: $target->recipient,
    89	                actionType: 'comment',
    90	                actionId: $comment->id->value(),
    91	            );
    92	        }
    93	    }
    94	
    95	    /**
    96	     * Стейджит уведомление автору комментария о реакции на него (comment_like). Самодействие не
    97	     * уведомляет.
    98	     */
    99	    public function notifyCommentAuthor(
   100	        PostNotificationType $type,
   101	        UserId $commentAuthor,
   102	        string $actorUserId,
   103	        string $commentId,
   104	    ): void {
   105	        if ($commentAuthor->value() === $actorUserId) {
   106	            return;
   107	        }
   108	
   109	        $this->postNotifier->notify(
   110	            type: $type,
   111	            actor: $this->profile($actorUserId),
   112	            recipient: $this->profile($commentAuthor->value()),
   113	            actionType: 'comment',
   114	            actionId: $commentId,
   115	        );
   116	    }
   117	
   118	    /**
   119	     * @param list<string> $userIds
   120	     */
   121	    private function resolveRecipients(array $userIds): UserPublicProfileCollection
   122	    {
   123	        $recipients = $this->queryBus->dispatch(
   124	            query: new GetUserPublicProfilesQuery($userIds),
   125	            handler: $this->getUserPublicProfilesHandler->handle(...),
   126	        );
   127	
   128	        if ($recipients->count() !== \count($userIds)) {
   129	            throw new ValidationException('app.posts.mention_user_not_found');
   130	        }
   131	
   132	        return $recipients;
   133	    }
   134	
   135	    private function profile(string $userId): UserPublicProfileView
   136	    {
   137	        return $this->queryBus->dispatch(
   138	            query: new GetUserPublicProfileQuery($userId),
   139	            handler: $this->getUserPublicProfileHandler->handle(...),
   140	        );
   141	    }
   142	}

 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Posts\Application\Post;
     6	
     7	use App\Modules\Media\Application\Command\MakeMediaPermanent\MakeMediaPermanentCommand;
     8	use App\Modules\Media\Application\Command\MakeMediaPermanent\MakeMediaPermanentHandler;
     9	use App\Modules\Media\Application\Query\CheckMediaAttachable\CheckMediaAttachableHandler;
    10	use App\Modules\Media\Application\Query\CheckMediaAttachable\CheckMediaAttachableQuery;
    11	use App\Modules\Posts\Application\Notification\PostNotificationType;
    12	use App\Modules\Posts\Application\Notification\PostNotifier;
    13	use App\Modules\Posts\Domain\Entity\Post;
    14	use App\Modules\Posts\Domain\Entity\PostMedia;
    15	use App\Modules\Posts\Domain\Entity\PostMention;
    16	use App\Modules\Posts\Domain\Entity\PostTag;
    17	use App\Modules\Posts\Domain\ValueObject\MediaPosition;
    18	use App\Modules\Posts\Domain\ValueObject\PostMediaReference;
    19	use App\Modules\Tags\Application\Command\ResolveTags\ResolveTagsCommand;
    20	use App\Modules\Tags\Application\Command\ResolveTags\ResolveTagsHandler;
    21	use App\Modules\User\Application\Dto\UserPublicProfileCollection;
    22	use App\Modules\User\Application\Dto\UserPublicProfileView;
    23	use App\Modules\User\Application\Query\GetUserPublicProfile\GetUserPublicProfileHandler;
    24	use App\Modules\User\Application\Query\GetUserPublicProfile\GetUserPublicProfileQuery;
    25	use App\Modules\User\Application\Query\GetUserPublicProfiles\GetUserPublicProfilesHandler;
    26	use App\Modules\User\Application\Query\GetUserPublicProfiles\GetUserPublicProfilesQuery;
    27	use App\Shared\Domain\Exception\ValidationException;
    28	use App\Shared\Domain\ValueObject\TagId;
    29	use App\Shared\Domain\ValueObject\UserId;
    30	use Cycle\ORM\EntityManagerInterface;
    31	use GianTiaga\SpiralCqrs\CommandBusInterface;
    32	use GianTiaga\SpiralCqrs\QueryBusInterface;
    33	
    34	/**
    35	 * Общая сборка содержимого записи для сценариев создания и репоста: разрешение тегов, вложение
    36	 * медиа (проверка + перевод в permanent), теги и упоминания, а также стейджинг уведомлений
    37	 * post_mention/post_repost. Все вызовы смежных модулей идут внутри той же транзакции Handler-а
    38	 * (вложенный #[Transactional]-dispatch -> SAVEPOINT), persist выполняет этот сервис, а финальный
    39	 * run() — вызывающий Handler.
    40	 */
    41	final readonly class PostContentComposer
    42	{
    43	    public function __construct(
    44	        private CommandBusInterface $commandBus,
    45	        private QueryBusInterface $queryBus,
    46	        private ResolveTagsHandler $resolveTagsHandler,
    47	        private CheckMediaAttachableHandler $checkMediaAttachableHandler,
    48	        private MakeMediaPermanentHandler $makeMediaPermanentHandler,
    49	        private GetUserPublicProfileHandler $getUserPublicProfileHandler,
    50	        private GetUserPublicProfilesHandler $getUserPublicProfilesHandler,
    51	        private PostNotifier $postNotifier,
    52	        private EntityManagerInterface $entityManager,
    53	    ) {}
    54	
    55	    /**
    56	     * @param list<string> $texts
    57	     *
    58	     * @return list<string>
    59	     */
    60	    public function resolveTags(array $texts, string $creatorUserId): array
    61	    {
    62	        if ($texts === []) {
    63	            return [];
    64	        }
    65	
    66	        return $this->commandBus->dispatch(
    67	            command: new ResolveTagsCommand(texts: $texts, creatorUserId: $creatorUserId),
    68	            handler: $this->resolveTagsHandler->handle(...),
    69	        )->tagIds;
    70	    }
    71	
    72	    /**
    73	     * Проверяет каждое медиа (существование, владелец, готовность), переводит в permanent и
    74	     * сохраняет строки вложения. Порядок вложений — позиция по порядку в списке.
    75	     *
    76	     * @param list<string> $mediaIds
    77	     */
    78	    public function attachMedia(Post $post, array $mediaIds, string $ownerUserId): void
    79	    {
    80	        $position = 0;
    81	
    82	        foreach ($mediaIds as $mediaId) {
    83	            $this->queryBus->dispatch(
    84	                query: new CheckMediaAttachableQuery(mediaId: $mediaId, ownerUserId: $ownerUserId),
    85	                handler: $this->checkMediaAttachableHandler->handle(...),
    86	            );
    87	            $this->commandBus->dispatch(
    88	                command: new MakeMediaPermanentCommand(userId: $ownerUserId, mediaId: $mediaId),
    89	                handler: $this->makeMediaPermanentHandler->handle(...),
    90	            );
    91	            $this->entityManager->persist(PostMedia::create(
    92	                post: $post,
    93	                media: PostMediaReference::fromString($mediaId),
    94	                position: MediaPosition::fromInt($position),
    95	            ));
    96	            $position++;
    97	        }
    98	    }
    99	
   100	    /**
   101	     * @param list<string> $tagIds
   102	     */
   103	    public function attachTags(Post $post, array $tagIds): void
   104	    {
   105	        foreach ($tagIds as $tagId) {
   106	            $this->entityManager->persist(PostTag::create(postId: $post->id, tagId: TagId::fromString($tagId)));
   107	        }
   108	    }
   109	
   110	    /**
   111	     * Сохраняет упоминания записи и стейджит post_mention каждому упомянутому (кроме автора).
   112	     * Дубликаты в списке схлопываются; несуществующий пользователь -> 422.
   113	     *
   114	     * @param list<string> $mentionIds
   115	     */
   116	    public function attachPostMentions(Post $post, array $mentionIds, string $actorUserId): void
   117	    {
   118	        $uniqueIds = \array_values(\array_unique($mentionIds));
   119	
   120	        if ($uniqueIds === []) {
   121	            return;
   122	        }
   123	
   124	        $recipients = $this->resolveRecipients($uniqueIds);
   125	
   126	        foreach ($uniqueIds as $mentionId) {
   127	            $this->entityManager->persist(PostMention::create(postId: $post->id, userId: UserId::fromString($mentionId)));
   128	        }
   129	
   130	        $actor = $this->profile($actorUserId);
   131	
   132	        foreach ($recipients as $recipient) {
   133	            $this->postNotifier->notify(
   134	                type: PostNotificationType::PostMention,
   135	                actor: $actor,
   136	                recipient: $recipient,
   137	                actionType: 'post',
   138	                actionId: $post->id->value(),
   139	            );
   140	        }
   141	    }
   142	
   143	    /**
   144	     * Стейджит уведомление автору записи о действии над ней (репост, лайк). Самодействие
   145	     * (автор == инициатор) не уведомляет.
   146	     */
   147	    public function notifyPostAuthor(
   148	        PostNotificationType $type,
   149	        UserId $postAuthor,
   150	        string $actorUserId,
   151	        string $postId,
   152	    ): void {
   153	        if ($postAuthor->value() === $actorUserId) {
   154	            return;
   155	        }
   156	
   157	        $this->postNotifier->notify(
   158	            type: $type,
   159	            actor: $this->profile($actorUserId),
   160	            recipient: $this->profile($postAuthor->value()),
   161	            actionType: 'post',
   162	            actionId: $postId,
   163	        );
   164	    }
   165	
   166	    /**
   167	     * @param list<string> $userIds
   168	     */
   169	    private function resolveRecipients(array $userIds): UserPublicProfileCollection
   170	    {
   171	        $recipients = $this->queryBus->dispatch(
   172	            query: new GetUserPublicProfilesQuery($userIds),
   173	            handler: $this->getUserPublicProfilesHandler->handle(...),
   174	        );
   175	
   176	        if ($recipients->count() !== \count($userIds)) {
   177	            throw new ValidationException('app.posts.mention_user_not_found');
   178	        }
   179	
   180	        return $recipients;
   181	    }
   182	
   183	    private function profile(string $userId): UserPublicProfileView
   184	    {
   185	        return $this->queryBus->dispatch(
   186	            query: new GetUserPublicProfileQuery($userId),
   187	            handler: $this->getUserPublicProfileHandler->handle(...),
   188	        );
   189	    }
   190	}

exec
/bin/zsh -lc "nl -ba app/src/Modules/Posts/Presentation/Http/Filter/CreatePostFilter.php app/src/Modules/Posts/Presentation/Http/Filter/RepostPostFilter.php | sed -n '1,260p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
usage: nl [-p] [-b type] [-d delim] [-f type] [-h type] [-i incr] [-l num]
          [-n format] [-s sep] [-v startnum] [-w width] [file]

exec
/bin/zsh -lc "nl -ba app/src/Modules/Posts/Presentation/Http/Filter/RepostPostFilter.php | sed -n '1,220p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
exec
/bin/zsh -lc "nl -ba app/src/Modules/Posts/Presentation/Http/Filter/CreatePostFilter.php | sed -n '1,200p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Posts\Presentation\Http\Filter;
     6	
     7	use Spiral\Filters\Attribute\Input\Attribute;
     8	use Spiral\Filters\Attribute\Input\Post;
     9	use Spiral\Filters\Attribute\Input\Route;
    10	use Spiral\Validation\Symfony\AttributesFilter;
    11	use Symfony\Component\Validator\Constraints as Assert;
    12	
    13	/**
    14	 * Репост целевой записи: <id> оригинала из пути (валидируется как uuid, чтобы кривой id давал 422),
    15	 * тело — как у создания (текст/медиа/теги/упоминания) с теми же лимитами.
    16	 */
    17	final class RepostPostFilter extends AttributesFilter
    18	{
    19	    #[Attribute(key: 'authUserId')]
    20	    #[Assert\NotBlank]
    21	    public string $authUserId;
    22	
    23	    #[Route(key: 'id')]
    24	    #[Assert\NotBlank]
    25	    #[Assert\Uuid]
    26	    public string $id;
    27	
    28	    #[Post]
    29	    #[Assert\Length(max: 5000)]
    30	    public string|null $text = null;
    31	
    32	    /**
    33	     * @var list<string>
    34	     */
    35	    #[Post]
    36	    #[Assert\Count(max: 10)]
    37	    #[Assert\All([new Assert\Uuid()])]
    38	    public array $mediaIds = [];
    39	
    40	    /**
    41	     * @var list<string>
    42	     */
    43	    #[Post]
    44	    #[Assert\Count(max: 20)]
    45	    #[Assert\All([new Assert\NotBlank(), new Assert\Length(max: 50)])]
    46	    public array $tags = [];
    47	
    48	    /**
    49	     * @var list<string>
    50	     */
    51	    #[Post]
    52	    #[Assert\Count(max: 50)]
    53	    #[Assert\All([new Assert\Uuid()])]
    54	    public array $mentions = [];
    55	}

 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Posts\Presentation\Http\Filter;
     6	
     7	use Spiral\Filters\Attribute\Input\Attribute;
     8	use Spiral\Filters\Attribute\Input\Post;
     9	use Spiral\Validation\Symfony\AttributesFilter;
    10	use Symfony\Component\Validator\Constraints as Assert;
    11	
    12	/**
    13	 * Создание записи. Лимиты применяются на HTTP-границе (422), чтобы превышение длины/количества и
    14	 * кривой uuid медиа/упоминания не доходили до доменных VO (500): текст <=5000, медиа <=10 (uuid),
    15	 * теги <=20 (длина <=50), упоминания <=50 (uuid).
    16	 */
    17	final class CreatePostFilter extends AttributesFilter
    18	{
    19	    #[Attribute(key: 'authUserId')]
    20	    #[Assert\NotBlank]
    21	    public string $authUserId;
    22	
    23	    #[Post]
    24	    #[Assert\Length(max: 5000)]
    25	    public string|null $text = null;
    26	
    27	    #[Post]
    28	    public bool $draft = false;
    29	
    30	    /**
    31	     * @var list<string>
    32	     */
    33	    #[Post]
    34	    #[Assert\Count(max: 10)]
    35	    #[Assert\All([new Assert\Uuid()])]
    36	    public array $mediaIds = [];
    37	
    38	    /**
    39	     * @var list<string>
    40	     */
    41	    #[Post]
    42	    #[Assert\Count(max: 20)]
    43	    #[Assert\All([new Assert\NotBlank(), new Assert\Length(max: 50)])]
    44	    public array $tags = [];
    45	
    46	    /**
    47	     * @var list<string>
    48	     */
    49	    #[Post]
    50	    #[Assert\Count(max: 50)]
    51	    #[Assert\All([new Assert\Uuid()])]
    52	    public array $mentions = [];
    53	}

exec
/bin/zsh -lc "nl -ba app/src/Modules/Posts/Presentation/Http/Controller/PostController.php | sed -n '1,320p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Posts\Presentation\Http\Controller;
     6	
     7	use App\Modules\Auth\Presentation\Http\Middleware\AuthContextAttributeMiddleware;
     8	use App\Modules\Auth\Presentation\Http\Middleware\RequireAuthenticatedMiddleware;
     9	use App\Modules\Posts\Application\Command\CreatePost\CreatePostCommand;
    10	use App\Modules\Posts\Application\Command\CreatePost\CreatePostHandler;
    11	use App\Modules\Posts\Application\Command\DeletePost\DeletePostCommand;
    12	use App\Modules\Posts\Application\Command\DeletePost\DeletePostHandler;
    13	use App\Modules\Posts\Application\Command\LikePost\LikePostCommand;
    14	use App\Modules\Posts\Application\Command\LikePost\LikePostHandler;
    15	use App\Modules\Posts\Application\Command\PublishPost\PublishPostCommand;
    16	use App\Modules\Posts\Application\Command\PublishPost\PublishPostHandler;
    17	use App\Modules\Posts\Application\Command\RepostPost\RepostPostCommand;
    18	use App\Modules\Posts\Application\Command\RepostPost\RepostPostHandler;
    19	use App\Modules\Posts\Application\Command\UnlikePost\UnlikePostCommand;
    20	use App\Modules\Posts\Application\Command\UnlikePost\UnlikePostHandler;
    21	use App\Modules\Posts\Application\Query\GetPost\GetPostHandler;
    22	use App\Modules\Posts\Application\Query\GetPost\GetPostQuery;
    23	use App\Modules\Posts\Application\Query\GetUserFeed\GetUserFeedHandler;
    24	use App\Modules\Posts\Application\Query\GetUserFeed\GetUserFeedQuery;
    25	use App\Modules\Posts\Application\View\PostView;
    26	use App\Modules\Posts\Presentation\Http\Filter\CreatePostFilter;
    27	use App\Modules\Posts\Presentation\Http\Filter\DeletePostFilter;
    28	use App\Modules\Posts\Presentation\Http\Filter\GetPostFilter;
    29	use App\Modules\Posts\Presentation\Http\Filter\GetUserFeedFilter;
    30	use App\Modules\Posts\Presentation\Http\Filter\LikePostFilter;
    31	use App\Modules\Posts\Presentation\Http\Filter\PublishPostFilter;
    32	use App\Modules\Posts\Presentation\Http\Filter\RepostPostFilter;
    33	use App\Modules\Posts\Presentation\Http\Filter\UnlikePostFilter;
    34	use App\Modules\Posts\Presentation\Http\Resource\PostResource;
    35	use GianTiaga\SpiralCqrs\CommandBusInterface;
    36	use GianTiaga\SpiralCqrs\QueryBusInterface;
    37	use GianTiaga\SpiralOpenApi\Response\DataResponse;
    38	use GianTiaga\SpiralOpenApi\Response\EmptySuccessResponse;
    39	use GianTiaga\SpiralOpenApi\Response\PaginationMetaResponse;
    40	use GianTiaga\SpiralOpenApi\Response\PaginationResponse;
    41	use Spiral\Auth\Middleware\AuthTransportWithStorageMiddleware;
    42	use Spiral\Core\Container\Autowire;
    43	use Spiral\Router\Annotation\Route;
    44	
    45	final readonly class PostController
    46	{
    47	    /**
    48	     * @return DataResponse<PostResource>
    49	     */
    50	    #[Route(
    51	        route: '/api/v1/posts',
    52	        name: 'api.v1.posts.create',
    53	        methods: ['POST'],
    54	        group: 'api',
    55	        middleware: [
    56	            new Autowire(
    57	                alias: AuthTransportWithStorageMiddleware::class,
    58	                parameters: ['transportName' => 'header', 'storage' => 'cycle'],
    59	            ),
    60	            AuthContextAttributeMiddleware::class,
    61	            RequireAuthenticatedMiddleware::class,
    62	        ],
    63	    )]
    64	    public function create(
    65	        CreatePostFilter $createPostFilter,
    66	        CommandBusInterface $commandBus,
    67	        CreatePostHandler $createPostHandler,
    68	    ): DataResponse {
    69	        $post = $commandBus->dispatch(
    70	            command: new CreatePostCommand(
    71	                authUserId: $createPostFilter->authUserId,
    72	                text: $createPostFilter->text,
    73	                draft: $createPostFilter->draft,
    74	                mediaIds: $createPostFilter->mediaIds,
    75	                tags: $createPostFilter->tags,
    76	                mentions: $createPostFilter->mentions,
    77	            ),
    78	            handler: $createPostHandler->handle(...),
    79	        );
    80	
    81	        return new DataResponse(PostResource::fromView($post));
    82	    }
    83	
    84	    /**
    85	     * @return DataResponse<PostResource>
    86	     */
    87	    #[Route(
    88	        route: '/api/v1/posts/<id>/publish',
    89	        name: 'api.v1.posts.publish',
    90	        methods: ['POST'],
    91	        group: 'api',
    92	        middleware: [
    93	            new Autowire(
    94	                alias: AuthTransportWithStorageMiddleware::class,
    95	                parameters: ['transportName' => 'header', 'storage' => 'cycle'],
    96	            ),
    97	            AuthContextAttributeMiddleware::class,
    98	            RequireAuthenticatedMiddleware::class,
    99	        ],
   100	    )]
   101	    public function publish(
   102	        string $id,
   103	        PublishPostFilter $publishPostFilter,
   104	        CommandBusInterface $commandBus,
   105	        PublishPostHandler $publishPostHandler,
   106	    ): DataResponse {
   107	        $post = $commandBus->dispatch(
   108	            command: new PublishPostCommand(
   109	                authUserId: $publishPostFilter->authUserId,
   110	                postId: $publishPostFilter->id,
   111	            ),
   112	            handler: $publishPostHandler->handle(...),
   113	        );
   114	
   115	        return new DataResponse(PostResource::fromView($post));
   116	    }
   117	
   118	    /**
   119	     * @return DataResponse<PostResource>
   120	     */
   121	    #[Route(
   122	        route: '/api/v1/posts/<id>/repost',
   123	        name: 'api.v1.posts.repost',
   124	        methods: ['POST'],
   125	        group: 'api',
   126	        middleware: [
   127	            new Autowire(
   128	                alias: AuthTransportWithStorageMiddleware::class,
   129	                parameters: ['transportName' => 'header', 'storage' => 'cycle'],
   130	            ),
   131	            AuthContextAttributeMiddleware::class,
   132	            RequireAuthenticatedMiddleware::class,
   133	        ],
   134	    )]
   135	    public function repost(
   136	        string $id,
   137	        RepostPostFilter $repostPostFilter,
   138	        CommandBusInterface $commandBus,
   139	        RepostPostHandler $repostPostHandler,
   140	    ): DataResponse {
   141	        $post = $commandBus->dispatch(
   142	            command: new RepostPostCommand(
   143	                authUserId: $repostPostFilter->authUserId,
   144	                postId: $repostPostFilter->id,
   145	                text: $repostPostFilter->text,
   146	                mediaIds: $repostPostFilter->mediaIds,
   147	                tags: $repostPostFilter->tags,
   148	                mentions: $repostPostFilter->mentions,
   149	            ),
   150	            handler: $repostPostHandler->handle(...),
   151	        );
   152	
   153	        return new DataResponse(PostResource::fromView($post));
   154	    }
   155	
   156	    #[Route(
   157	        route: '/api/v1/posts/<id>',
   158	        name: 'api.v1.posts.delete',
   159	        methods: ['DELETE'],
   160	        group: 'api',
   161	        middleware: [
   162	            new Autowire(
   163	                alias: AuthTransportWithStorageMiddleware::class,
   164	                parameters: ['transportName' => 'header', 'storage' => 'cycle'],
   165	            ),
   166	            AuthContextAttributeMiddleware::class,
   167	            RequireAuthenticatedMiddleware::class,
   168	        ],
   169	    )]
   170	    public function delete(
   171	        string $id,
   172	        DeletePostFilter $deletePostFilter,
   173	        CommandBusInterface $commandBus,
   174	        DeletePostHandler $deletePostHandler,
   175	    ): EmptySuccessResponse {
   176	        $commandBus->dispatch(
   177	            command: new DeletePostCommand(
   178	                authUserId: $deletePostFilter->authUserId,
   179	                postId: $deletePostFilter->id,
   180	            ),
   181	            handler: $deletePostHandler->handle(...),
   182	        );
   183	
   184	        return new EmptySuccessResponse();
   185	    }
   186	
   187	    #[Route(
   188	        route: '/api/v1/posts/<id>/like',
   189	        name: 'api.v1.posts.like',
   190	        methods: ['POST'],
   191	        group: 'api',
   192	        middleware: [
   193	            new Autowire(
   194	                alias: AuthTransportWithStorageMiddleware::class,
   195	                parameters: ['transportName' => 'header', 'storage' => 'cycle'],
   196	            ),
   197	            AuthContextAttributeMiddleware::class,
   198	            RequireAuthenticatedMiddleware::class,
   199	        ],
   200	    )]
   201	    public function like(
   202	        string $id,
   203	        LikePostFilter $likePostFilter,
   204	        CommandBusInterface $commandBus,
   205	        LikePostHandler $likePostHandler,
   206	    ): EmptySuccessResponse {
   207	        $commandBus->dispatch(
   208	            command: new LikePostCommand(
   209	                authUserId: $likePostFilter->authUserId,
   210	                postId: $likePostFilter->id,
   211	            ),
   212	            handler: $likePostHandler->handle(...),
   213	        );
   214	
   215	        return new EmptySuccessResponse();
   216	    }
   217	
   218	    #[Route(
   219	        route: '/api/v1/posts/<id>/like',
   220	        name: 'api.v1.posts.unlike',
   221	        methods: ['DELETE'],
   222	        group: 'api',
   223	        middleware: [
   224	            new Autowire(
   225	                alias: AuthTransportWithStorageMiddleware::class,
   226	                parameters: ['transportName' => 'header', 'storage' => 'cycle'],
   227	            ),
   228	            AuthContextAttributeMiddleware::class,
   229	            RequireAuthenticatedMiddleware::class,
   230	        ],
   231	    )]
   232	    public function unlike(
   233	        string $id,
   234	        UnlikePostFilter $unlikePostFilter,
   235	        CommandBusInterface $commandBus,
   236	        UnlikePostHandler $unlikePostHandler,
   237	    ): EmptySuccessResponse {
   238	        $commandBus->dispatch(
   239	            command: new UnlikePostCommand(
   240	                authUserId: $unlikePostFilter->authUserId,
   241	                postId: $unlikePostFilter->id,
   242	            ),
   243	            handler: $unlikePostHandler->handle(...),
   244	        );
   245	
   246	        return new EmptySuccessResponse();
   247	    }
   248	
   249	    /**
   250	     * @return DataResponse<PostResource>
   251	     */
   252	    #[Route(
   253	        route: '/api/v1/posts/<id>',
   254	        name: 'api.v1.posts.show',
   255	        methods: ['GET'],
   256	        group: 'api',
   257	        middleware: [
   258	            new Autowire(
   259	                alias: AuthTransportWithStorageMiddleware::class,
   260	                parameters: ['transportName' => 'header', 'storage' => 'cycle'],
   261	            ),
   262	            AuthContextAttributeMiddleware::class,
   263	            RequireAuthenticatedMiddleware::class,
   264	        ],
   265	    )]
   266	    public function show(
   267	        string $id,
   268	        GetPostFilter $getPostFilter,
   269	        GetPostHandler $getPostHandler,
   270	        QueryBusInterface $queryBus,
   271	    ): DataResponse {
   272	        $post = $queryBus->dispatch(
   273	            query: new GetPostQuery(
   274	                postId: $getPostFilter->id,
   275	                authUserId: $getPostFilter->authUserId,
   276	            ),
   277	            handler: $getPostHandler->handle(...),
   278	        );
   279	
   280	        return new DataResponse(PostResource::fromView($post));
   281	    }
   282	
   283	    /**
   284	     * @return PaginationResponse<PostResource>
   285	     */
   286	    #[Route(
   287	        route: '/api/v1/posts/user/<id>',
   288	        name: 'api.v1.posts.user_feed',
   289	        methods: ['GET'],
   290	        group: 'api',
   291	        middleware: [
   292	            new Autowire(
   293	                alias: AuthTransportWithStorageMiddleware::class,
   294	                parameters: ['transportName' => 'header', 'storage' => 'cycle'],
   295	            ),
   296	            AuthContextAttributeMiddleware::class,
   297	            RequireAuthenticatedMiddleware::class,
   298	        ],
   299	    )]
   300	    public function userFeed(
   301	        string $id,
   302	        GetUserFeedFilter $getUserFeedFilter,
   303	        GetUserFeedHandler $getUserFeedHandler,
   304	        QueryBusInterface $queryBus,
   305	    ): PaginationResponse {
   306	        $result = $queryBus->dispatch(
   307	            query: new GetUserFeedQuery(
   308	                ownerUserId: $getUserFeedFilter->id,
   309	                authUserId: $getUserFeedFilter->authUserId,
   310	                cursor: $getUserFeedFilter->cursor,
   311	                limit: $getUserFeedFilter->limit,
   312	            ),
   313	            handler: $getUserFeedHandler->handle(...),
   314	        );
   315	
   316	        $resources = \array_map(
   317	            static fn(PostView $post): PostResource => PostResource::fromView($post),
   318	            $result->posts,
   319	        );
   320	

exec
/bin/zsh -lc "nl -ba tests/Feature/Modules/Auth/Console/AuthOpenApiGenerationTest.php | sed -n '60,95p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
exec
/bin/zsh -lc "nl -ba app/src/Modules/Posts/Presentation/Http/Controller/CommentController.php | sed -n '1,320p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
    60	        self::assertNotContains('tokens', $required);
    61	        self::assertNotContains('registrationTicket', $required);
    62	    }
    63	
    64	    #[Config('openapi.outputFile', 'runtime/openapi-auth-no-content-test.yml')]
    65	    public function testCommandEndpointsReturnNoContentResponses(): void
    66	    {
    67	        $this->runCommand(command: 'openapi:generate');
    68	
    69	        $spec = Yaml::parseFile($this->rootDirectory() . '/runtime/openapi-auth-no-content-test.yml');
    70	
    71	        self::assertIsArray($spec);
    72	
    73	        $requestResponses = $spec['paths']['/auth/code/request']['post']['responses'] ?? null;
    74	        self::assertIsArray($requestResponses);
    75	        self::assertArrayHasKey(204, $requestResponses);
    76	        self::assertArrayNotHasKey(200, $requestResponses);
    77	
    78	        $logoutResponses = $spec['paths']['/auth/logout']['post']['responses'] ?? null;
    79	        self::assertIsArray($logoutResponses);
    80	        self::assertArrayHasKey(204, $logoutResponses);
    81	        self::assertArrayNotHasKey(200, $logoutResponses);
    82	
    83	        // Защита от того, что 204 ошибочно применился ко всем эндпоинтам: соседний verify сохраняет 200 с телом.
    84	        $verifyResponses = $spec['paths']['/auth/code/verify']['post']['responses'] ?? null;
    85	        self::assertIsArray($verifyResponses);
    86	        self::assertArrayHasKey(200, $verifyResponses);
    87	    }
    88	}

 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Posts\Presentation\Http\Controller;
     6	
     7	use App\Modules\Auth\Presentation\Http\Middleware\AuthContextAttributeMiddleware;
     8	use App\Modules\Auth\Presentation\Http\Middleware\RequireAuthenticatedMiddleware;
     9	use App\Modules\Posts\Application\Command\CommentPost\CommentPostCommand;
    10	use App\Modules\Posts\Application\Command\CommentPost\CommentPostHandler;
    11	use App\Modules\Posts\Application\Command\DeleteComment\DeleteCommentCommand;
    12	use App\Modules\Posts\Application\Command\DeleteComment\DeleteCommentHandler;
    13	use App\Modules\Posts\Application\Command\LikeComment\LikeCommentCommand;
    14	use App\Modules\Posts\Application\Command\LikeComment\LikeCommentHandler;
    15	use App\Modules\Posts\Application\Command\ReplyComment\ReplyCommentCommand;
    16	use App\Modules\Posts\Application\Command\ReplyComment\ReplyCommentHandler;
    17	use App\Modules\Posts\Application\Command\UnlikeComment\UnlikeCommentCommand;
    18	use App\Modules\Posts\Application\Command\UnlikeComment\UnlikeCommentHandler;
    19	use App\Modules\Posts\Application\Query\GetCommentReplies\GetCommentRepliesHandler;
    20	use App\Modules\Posts\Application\Query\GetCommentReplies\GetCommentRepliesQuery;
    21	use App\Modules\Posts\Application\Query\GetPostComments\GetPostCommentsHandler;
    22	use App\Modules\Posts\Application\Query\GetPostComments\GetPostCommentsQuery;
    23	use App\Modules\Posts\Application\View\CommentView;
    24	use App\Modules\Posts\Presentation\Http\Filter\CommentPostFilter;
    25	use App\Modules\Posts\Presentation\Http\Filter\DeleteCommentFilter;
    26	use App\Modules\Posts\Presentation\Http\Filter\GetCommentRepliesFilter;
    27	use App\Modules\Posts\Presentation\Http\Filter\GetPostCommentsFilter;
    28	use App\Modules\Posts\Presentation\Http\Filter\LikeCommentFilter;
    29	use App\Modules\Posts\Presentation\Http\Filter\ReplyCommentFilter;
    30	use App\Modules\Posts\Presentation\Http\Filter\UnlikeCommentFilter;
    31	use App\Modules\Posts\Presentation\Http\Resource\CommentResource;
    32	use GianTiaga\SpiralCqrs\CommandBusInterface;
    33	use GianTiaga\SpiralCqrs\QueryBusInterface;
    34	use GianTiaga\SpiralOpenApi\Response\DataResponse;
    35	use GianTiaga\SpiralOpenApi\Response\EmptySuccessResponse;
    36	use GianTiaga\SpiralOpenApi\Response\PaginationMetaResponse;
    37	use GianTiaga\SpiralOpenApi\Response\PaginationResponse;
    38	use Spiral\Auth\Middleware\AuthTransportWithStorageMiddleware;
    39	use Spiral\Core\Container\Autowire;
    40	use Spiral\Router\Annotation\Route;
    41	
    42	final readonly class CommentController
    43	{
    44	    /**
    45	     * @return DataResponse<CommentResource>
    46	     */
    47	    #[Route(
    48	        route: '/api/v1/posts/<id>/comments',
    49	        name: 'api.v1.posts.comments.create',
    50	        methods: ['POST'],
    51	        group: 'api',
    52	        middleware: [
    53	            new Autowire(
    54	                alias: AuthTransportWithStorageMiddleware::class,
    55	                parameters: ['transportName' => 'header', 'storage' => 'cycle'],
    56	            ),
    57	            AuthContextAttributeMiddleware::class,
    58	            RequireAuthenticatedMiddleware::class,
    59	        ],
    60	    )]
    61	    public function comment(
    62	        string $id,
    63	        CommentPostFilter $commentPostFilter,
    64	        CommandBusInterface $commandBus,
    65	        CommentPostHandler $commentPostHandler,
    66	    ): DataResponse {
    67	        $comment = $commandBus->dispatch(
    68	            command: new CommentPostCommand(
    69	                authUserId: $commentPostFilter->authUserId,
    70	                postId: $commentPostFilter->id,
    71	                text: $commentPostFilter->text,
    72	                mentions: $commentPostFilter->mentions,
    73	            ),
    74	            handler: $commentPostHandler->handle(...),
    75	        );
    76	
    77	        return new DataResponse(CommentResource::fromView($comment));
    78	    }
    79	
    80	    /**
    81	     * @return DataResponse<CommentResource>
    82	     */
    83	    #[Route(
    84	        route: '/api/v1/posts/comments/<id>/replies',
    85	        name: 'api.v1.posts.comments.reply',
    86	        methods: ['POST'],
    87	        group: 'api',
    88	        middleware: [
    89	            new Autowire(
    90	                alias: AuthTransportWithStorageMiddleware::class,
    91	                parameters: ['transportName' => 'header', 'storage' => 'cycle'],
    92	            ),
    93	            AuthContextAttributeMiddleware::class,
    94	            RequireAuthenticatedMiddleware::class,
    95	        ],
    96	    )]
    97	    public function reply(
    98	        string $id,
    99	        ReplyCommentFilter $replyCommentFilter,
   100	        CommandBusInterface $commandBus,
   101	        ReplyCommentHandler $replyCommentHandler,
   102	    ): DataResponse {
   103	        $comment = $commandBus->dispatch(
   104	            command: new ReplyCommentCommand(
   105	                authUserId: $replyCommentFilter->authUserId,
   106	                commentId: $replyCommentFilter->id,
   107	                text: $replyCommentFilter->text,
   108	                mentions: $replyCommentFilter->mentions,
   109	            ),
   110	            handler: $replyCommentHandler->handle(...),
   111	        );
   112	
   113	        return new DataResponse(CommentResource::fromView($comment));
   114	    }
   115	
   116	    #[Route(
   117	        route: '/api/v1/posts/comments/<id>',
   118	        name: 'api.v1.posts.comments.delete',
   119	        methods: ['DELETE'],
   120	        group: 'api',
   121	        middleware: [
   122	            new Autowire(
   123	                alias: AuthTransportWithStorageMiddleware::class,
   124	                parameters: ['transportName' => 'header', 'storage' => 'cycle'],
   125	            ),
   126	            AuthContextAttributeMiddleware::class,
   127	            RequireAuthenticatedMiddleware::class,
   128	        ],
   129	    )]
   130	    public function delete(
   131	        string $id,
   132	        DeleteCommentFilter $deleteCommentFilter,
   133	        CommandBusInterface $commandBus,
   134	        DeleteCommentHandler $deleteCommentHandler,
   135	    ): EmptySuccessResponse {
   136	        $commandBus->dispatch(
   137	            command: new DeleteCommentCommand(
   138	                authUserId: $deleteCommentFilter->authUserId,
   139	                commentId: $deleteCommentFilter->id,
   140	            ),
   141	            handler: $deleteCommentHandler->handle(...),
   142	        );
   143	
   144	        return new EmptySuccessResponse();
   145	    }
   146	
   147	    #[Route(
   148	        route: '/api/v1/posts/comments/<id>/like',
   149	        name: 'api.v1.posts.comments.like',
   150	        methods: ['POST'],
   151	        group: 'api',
   152	        middleware: [
   153	            new Autowire(
   154	                alias: AuthTransportWithStorageMiddleware::class,
   155	                parameters: ['transportName' => 'header', 'storage' => 'cycle'],
   156	            ),
   157	            AuthContextAttributeMiddleware::class,
   158	            RequireAuthenticatedMiddleware::class,
   159	        ],
   160	    )]
   161	    public function like(
   162	        string $id,
   163	        LikeCommentFilter $likeCommentFilter,
   164	        CommandBusInterface $commandBus,
   165	        LikeCommentHandler $likeCommentHandler,
   166	    ): EmptySuccessResponse {
   167	        $commandBus->dispatch(
   168	            command: new LikeCommentCommand(
   169	                authUserId: $likeCommentFilter->authUserId,
   170	                commentId: $likeCommentFilter->id,
   171	            ),
   172	            handler: $likeCommentHandler->handle(...),
   173	        );
   174	
   175	        return new EmptySuccessResponse();
   176	    }
   177	
   178	    #[Route(
   179	        route: '/api/v1/posts/comments/<id>/like',
   180	        name: 'api.v1.posts.comments.unlike',
   181	        methods: ['DELETE'],
   182	        group: 'api',
   183	        middleware: [
   184	            new Autowire(
   185	                alias: AuthTransportWithStorageMiddleware::class,
   186	                parameters: ['transportName' => 'header', 'storage' => 'cycle'],
   187	            ),
   188	            AuthContextAttributeMiddleware::class,
   189	            RequireAuthenticatedMiddleware::class,
   190	        ],
   191	    )]
   192	    public function unlike(
   193	        string $id,
   194	        UnlikeCommentFilter $unlikeCommentFilter,
   195	        CommandBusInterface $commandBus,
   196	        UnlikeCommentHandler $unlikeCommentHandler,
   197	    ): EmptySuccessResponse {
   198	        $commandBus->dispatch(
   199	            command: new UnlikeCommentCommand(
   200	                authUserId: $unlikeCommentFilter->authUserId,
   201	                commentId: $unlikeCommentFilter->id,
   202	            ),
   203	            handler: $unlikeCommentHandler->handle(...),
   204	        );
   205	
   206	        return new EmptySuccessResponse();
   207	    }
   208	
   209	    /**
   210	     * @return PaginationResponse<CommentResource>
   211	     */
   212	    #[Route(
   213	        route: '/api/v1/posts/<id>/comments',
   214	        name: 'api.v1.posts.comments.list',
   215	        methods: ['GET'],
   216	        group: 'api',
   217	        middleware: [
   218	            new Autowire(
   219	                alias: AuthTransportWithStorageMiddleware::class,
   220	                parameters: ['transportName' => 'header', 'storage' => 'cycle'],
   221	            ),
   222	            AuthContextAttributeMiddleware::class,
   223	            RequireAuthenticatedMiddleware::class,
   224	        ],
   225	    )]
   226	    public function list(
   227	        string $id,
   228	        GetPostCommentsFilter $getPostCommentsFilter,
   229	        GetPostCommentsHandler $getPostCommentsHandler,
   230	        QueryBusInterface $queryBus,
   231	    ): PaginationResponse {
   232	        $result = $queryBus->dispatch(
   233	            query: new GetPostCommentsQuery(
   234	                postId: $getPostCommentsFilter->id,
   235	                authUserId: $getPostCommentsFilter->authUserId,
   236	                cursor: $getPostCommentsFilter->cursor,
   237	                limit: $getPostCommentsFilter->limit,
   238	            ),
   239	            handler: $getPostCommentsHandler->handle(...),
   240	        );
   241	
   242	        $resources = \array_map(
   243	            static fn(CommentView $comment): CommentResource => CommentResource::fromView($comment),
   244	            $result->comments,
   245	        );
   246	
   247	        return new PaginationResponse(
   248	            data: $resources,
   249	            meta: new PaginationMetaResponse(nextCursor: $result->nextCursor, limit: $getPostCommentsFilter->limit),
   250	        );
   251	    }
   252	
   253	    /**
   254	     * @return PaginationResponse<CommentResource>
   255	     */
   256	    #[Route(
   257	        route: '/api/v1/posts/comments/<id>/replies',
   258	        name: 'api.v1.posts.comments.replies',
   259	        methods: ['GET'],
   260	        group: 'api',
   261	        middleware: [
   262	            new Autowire(
   263	                alias: AuthTransportWithStorageMiddleware::class,
   264	                parameters: ['transportName' => 'header', 'storage' => 'cycle'],
   265	            ),
   266	            AuthContextAttributeMiddleware::class,
   267	            RequireAuthenticatedMiddleware::class,
   268	        ],
   269	    )]
   270	    public function replies(
   271	        string $id,
   272	        GetCommentRepliesFilter $getCommentRepliesFilter,
   273	        GetCommentRepliesHandler $getCommentRepliesHandler,
   274	        QueryBusInterface $queryBus,
   275	    ): PaginationResponse {
   276	        $result = $queryBus->dispatch(
   277	            query: new GetCommentRepliesQuery(
   278	                commentId: $getCommentRepliesFilter->id,
   279	                authUserId: $getCommentRepliesFilter->authUserId,
   280	                cursor: $getCommentRepliesFilter->cursor,
   281	                limit: $getCommentRepliesFilter->limit,
   282	            ),
   283	            handler: $getCommentRepliesHandler->handle(...),
   284	        );
   285	
   286	        $resources = \array_map(
   287	            static fn(CommentView $comment): CommentResource => CommentResource::fromView($comment),
   288	            $result->replies,
   289	        );
   290	
   291	        return new PaginationResponse(
   292	            data: $resources,
   293	            meta: new PaginationMetaResponse(nextCursor: $result->nextCursor, limit: $getCommentRepliesFilter->limit),
   294	        );
   295	    }
   296	}

exec
/bin/zsh -lc "sed -n '1,130p' public/openapi/openapi.yml" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
openapi: 3.1.0
info:
  title: 'YogaLoka API'
  version: 1.0.0
servers:
  -
    url: /api/v1
paths:
  /auth/code/request:
    post:
      operationId: api_v1_auth_code_request
      description: 'Запрос кода входа на email. Ответ всегда 204 (наличие пользователя не раскрываем).'
      parameters: {  }
      responses:
        204:
          description: 'Успешный ответ.'
        default:
          description: 'Ошибка API.'
          content: { application/json: { schema: { $ref: '#/components/schemas/ErrorResponse' } } }
      requestBody:
        required: true
        content:
          application/json: { schema: { type: object, properties: { email: { type: string } }, required: [email] } }
  /auth/code/verify:
    post:
      operationId: api_v1_auth_code_verify
      description: 'Проверка кода: пара токенов существующему пользователю или талон регистрации новому email.'
      parameters: {  }
      responses:
        200:
          description: 'Успешный ответ.'
          content: { application/json: { schema: { type: object, properties: { data: { $ref: '#/components/schemas/VerifyResultResource' } }, required: [data] } } }
        default:
          description: 'Ошибка API.'
          content: { application/json: { schema: { $ref: '#/components/schemas/ErrorResponse' } } }
      requestBody:
        required: true
        content:
          application/json: { schema: { type: object, properties: { email: { type: string }, code: { type: string } }, required: [email, code] } }
  /auth/register:
    post:
      operationId: api_v1_auth_register
      description: 'Завершение регистрации по талону: создаёт пользователя и выдаёт пару токенов.'
      parameters: {  }
      responses:
        200:
          description: 'Успешный ответ.'
          content: { application/json: { schema: { type: object, properties: { data: { $ref: '#/components/schemas/TokenPairResource' } }, required: [data] } } }
        default:
          description: 'Ошибка API.'
          content: { application/json: { schema: { $ref: '#/components/schemas/ErrorResponse' } } }
      requestBody:
        required: true
        content:
          application/json: { schema: { type: object, properties: { ticket: { type: string }, name: { type: string }, nickname: { type: string } }, required: [ticket, name, nickname] } }
  /auth/refresh:
    post:
      operationId: api_v1_auth_refresh
      description: 'Обновление пары токенов по refresh-токену (ротация).'
      parameters: {  }
      responses:
        200:
          description: 'Успешный ответ.'
          content: { application/json: { schema: { type: object, properties: { data: { $ref: '#/components/schemas/TokenPairResource' } }, required: [data] } } }
        default:
          description: 'Ошибка API.'
          content: { application/json: { schema: { $ref: '#/components/schemas/ErrorResponse' } } }
      requestBody:
        required: true
        content:
          application/json: { schema: { type: object, properties: { refreshToken: { type: string } }, required: [refreshToken] } }
  /auth/logout:
    post:
      operationId: api_v1_auth_logout
      description: 'Выход: отзыв всех токенов текущей сессии. Требует Bearer access-токен.'
      parameters: {  }
      responses:
        204:
          description: 'Успешный ответ.'
        default:
          description: 'Ошибка API.'
          content: { application/json: { schema: { $ref: '#/components/schemas/ErrorResponse' } } }
  /auth/sessions:
    get:
      operationId: api_v1_auth_sessions_index
      description: 'Список своих активных сессий с устройством, IP и пометкой текущей. Требует Bearer access-токен.'
      parameters: {  }
      responses:
        200:
          description: 'Успешный ответ.'
          content: { application/json: { schema: { type: object, properties: { data: { type: array, items: { $ref: '#/components/schemas/SessionResource' } } }, required: [data] } } }
        default:
          description: 'Ошибка API.'
          content: { application/json: { schema: { $ref: '#/components/schemas/ErrorResponse' } } }
  '/auth/sessions/{sessionId}':
    delete:
      operationId: api_v1_auth_sessions_revoke
      description: 'Отзыв своей сессии по её id; требует Bearer access-токен; чужая/несуществующая → 404.'
      parameters:
        -
          name: sessionId
          in: path
          required: true
          schema: { type: string }
      responses:
        204:
          description: 'Успешный ответ.'
        default:
          description: 'Ошибка API.'
          content: { application/json: { schema: { $ref: '#/components/schemas/ErrorResponse' } } }
  /notifications:
    get:
      operationId: api_v1_notifications_list
      description: ''
      parameters:
        -
          name: cursor
          in: query
          required: false
          schema: { type: [string, 'null'] }
        -
          name: limit
          in: query
          required: false
          schema: { type: integer }
      responses:
        200:
          description: 'Успешный ответ.'
          content: { application/json: { schema: { type: object, properties: { data: { type: array, items: { $ref: '#/components/schemas/NotificationResource' } }, meta: { type: object, properties: { nextCursor: { type: [string, 'null'] }, limit: { type: integer } }, required: [nextCursor, limit] } }, required: [data, meta] } } }
        default:

codex
`attachmentType` в фильтрах отсутствует, а в OpenAPI его нет в теле создания и репоста. Это подтверждает расхождение с текстом плана, но выглядит как осознанное упрощение: в ответном ресурсе поле есть, во входе тип выводится из `mediaIds`.
exec
/bin/zsh -lc "php -r 'require \"vendor/autoload.php\"; "'$spec=Symfony'"\\Component\\Yaml\\Yaml::parseFile(\"public/openapi/openapi.yml\"); "'$n=0; foreach ($spec["paths"] as $path=>$ops) { if (str_starts_with($path,"/posts") || str_starts_with($path,"'"'/posts\")) { foreach ("'$ops as $method=>$op) { if (in_array($method,["get","post","put","patch","delete"],true)) { $n++; echo $method." ".$path."'"\\n\"; } } } } echo \"TOTAL=\"."'$n."'"\\n\";'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 exited 1 in 0ms:
zsh:1: unmatched "

exec
/bin/zsh -lc "nl -ba app/src/Modules/Posts/Presentation/Http/Controller/PostController.php | sed -n '316,380p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
   316	        $resources = \array_map(
   317	            static fn(PostView $post): PostResource => PostResource::fromView($post),
   318	            $result->posts,
   319	        );
   320	
   321	        return new PaginationResponse(
   322	            data: $resources,
   323	            meta: new PaginationMetaResponse(nextCursor: $result->nextCursor, limit: $getUserFeedFilter->limit),
   324	        );
   325	    }
   326	}

exec
/bin/zsh -lc 'rg "GetMediaUrl|FindMediaUrl|media|deleted media|not ready|not_ready|assertServerError|500" tests/Feature/Modules/Posts/Http/GetPostHttpTest.php tests/Feature/Modules/Posts/Http/GetUserFeedHttpTest.php tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php tests/Feature/Modules/Posts/Http/RepostPostHttpTest.php -n' in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
tests/Feature/Modules/Posts/Http/GetPostHttpTest.php:16:        $media = $this->createReadyMedia($user->id, MediaVisibility::Public);
tests/Feature/Modules/Posts/Http/GetPostHttpTest.php:20:            'mediaIds' => [$media->id->value()],
tests/Feature/Modules/Posts/Http/GetPostHttpTest.php:34:        self::assertCount(1, $data['media']);
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php:47:        $media = $this->createReadyMedia($user->id, MediaVisibility::Public);
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php:50:            'mediaIds' => [$media->id->value()],
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php:55:        self::assertSame('media', $data['attachmentType']);
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php:56:        self::assertCount(1, $data['media']);
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php:57:        self::assertSame($media->id->value(), $data['media'][0]['mediaId']);
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php:64:        $media = $this->createReadyMedia($other->id, MediaVisibility::Public);
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php:67:            'mediaIds' => [$media->id->value()],
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php:74:        $media = $this->createUploadedMedia($user->id);
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php:77:            'mediaIds' => [$media->id->value()],
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php:86:            'mediaIds' => [UserId::generate()->value()],
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php:163:            'text' => \str_repeat('а', 5001),
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php:172:            'mediaIds' => ['not-a-uuid'],

exec
/bin/zsh -lc 'rg "function test|assertNoContent|204|like|unlike|deleted|Blocked|Draft|not_found|forbidden" tests/Feature/Modules/Posts/Http/CommentReactionHttpTest.php tests/Feature/Modules/Posts/Http/PostReactionHttpTest.php tests/Feature/Modules/Posts/Http/GetPostHttpTest.php tests/Feature/Modules/Posts/Http/GetUserFeedHttpTest.php tests/Feature/Modules/Posts/Http/DeleteCommentHttpTest.php tests/Feature/Modules/Posts/Http/DeletePostHttpTest.php -n' in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 exited 2 in 0ms:
rg: tests/Feature/Modules/Posts/Http/DeleteCommentHttpTest.php: No such file or directory (os error 2)
tests/Feature/Modules/Posts/Http/CommentReactionHttpTest.php:12:    public function testLikesCommentAndNotifiesAuthor(): void
tests/Feature/Modules/Posts/Http/CommentReactionHttpTest.php:15:        $liker = $this->createUser();
tests/Feature/Modules/Posts/Http/CommentReactionHttpTest.php:19:        $this->authedJson('POST', \sprintf('/api/v1/posts/comments/%s/like', $comment->id->value()), $liker->id)
tests/Feature/Modules/Posts/Http/CommentReactionHttpTest.php:20:            ->assertNoContent();
tests/Feature/Modules/Posts/Http/CommentReactionHttpTest.php:22:        self::assertSame(1, $this->reloadComment($comment)->likesCount->value());
tests/Feature/Modules/Posts/Http/CommentReactionHttpTest.php:24:        $likes = $this->stagedNotifications('posts.comment_like');
tests/Feature/Modules/Posts/Http/CommentReactionHttpTest.php:25:        self::assertCount(1, $likes);
tests/Feature/Modules/Posts/Http/CommentReactionHttpTest.php:26:        self::assertSame($author->id->value(), $likes[0]->userId);
tests/Feature/Modules/Posts/Http/CommentReactionHttpTest.php:29:    public function testLikeCommentIsIdempotent(): void
tests/Feature/Modules/Posts/Http/CommentReactionHttpTest.php:32:        $liker = $this->createUser();
tests/Feature/Modules/Posts/Http/CommentReactionHttpTest.php:36:        $this->authedJson('POST', \sprintf('/api/v1/posts/comments/%s/like', $comment->id->value()), $liker->id)->assertNoContent();
tests/Feature/Modules/Posts/Http/CommentReactionHttpTest.php:37:        $this->authedJson('POST', \sprintf('/api/v1/posts/comments/%s/like', $comment->id->value()), $liker->id)->assertNoContent();
tests/Feature/Modules/Posts/Http/CommentReactionHttpTest.php:39:        self::assertSame(1, $this->reloadComment($comment)->likesCount->value());
tests/Feature/Modules/Posts/Http/CommentReactionHttpTest.php:40:        self::assertCount(1, $this->stagedNotifications('posts.comment_like'));
tests/Feature/Modules/Posts/Http/CommentReactionHttpTest.php:43:    public function testSelfLikeCommentDoesNotNotify(): void
tests/Feature/Modules/Posts/Http/CommentReactionHttpTest.php:49:        $this->authedJson('POST', \sprintf('/api/v1/posts/comments/%s/like', $comment->id->value()), $user->id)
tests/Feature/Modules/Posts/Http/CommentReactionHttpTest.php:50:            ->assertNoContent();
tests/Feature/Modules/Posts/Http/CommentReactionHttpTest.php:52:        self::assertSame(1, $this->reloadComment($comment)->likesCount->value());
tests/Feature/Modules/Posts/Http/CommentReactionHttpTest.php:53:        self::assertCount(0, $this->stagedNotifications('posts.comment_like'));
tests/Feature/Modules/Posts/Http/CommentReactionHttpTest.php:56:    public function testUnlikeComment(): void
tests/Feature/Modules/Posts/Http/CommentReactionHttpTest.php:59:        $liker = $this->createUser();
tests/Feature/Modules/Posts/Http/CommentReactionHttpTest.php:63:        $this->authedJson('POST', \sprintf('/api/v1/posts/comments/%s/like', $comment->id->value()), $liker->id)->assertNoContent();
tests/Feature/Modules/Posts/Http/CommentReactionHttpTest.php:64:        $this->authedJson('DELETE', \sprintf('/api/v1/posts/comments/%s/like', $comment->id->value()), $liker->id)->assertNoContent();
tests/Feature/Modules/Posts/Http/CommentReactionHttpTest.php:66:        self::assertSame(0, $this->reloadComment($comment)->likesCount->value());
tests/Feature/Modules/Posts/Http/CommentReactionHttpTest.php:67:        self::assertFalse($this->commentLikeRepository()->existsByCommentAndUser($comment->id, $liker->id));
tests/Feature/Modules/Posts/Http/CommentReactionHttpTest.php:70:    public function testUnlikeWithoutPriorLikeIsNoOp(): void
tests/Feature/Modules/Posts/Http/CommentReactionHttpTest.php:73:        $liker = $this->createUser();
tests/Feature/Modules/Posts/Http/CommentReactionHttpTest.php:77:        $this->authedJson('DELETE', \sprintf('/api/v1/posts/comments/%s/like', $comment->id->value()), $liker->id)
tests/Feature/Modules/Posts/Http/CommentReactionHttpTest.php:78:            ->assertNoContent();
tests/Feature/Modules/Posts/Http/CommentReactionHttpTest.php:80:        self::assertSame(0, $this->reloadComment($comment)->likesCount->value());
tests/Feature/Modules/Posts/Http/CommentReactionHttpTest.php:83:    public function testLikeDeletedCommentReturns404(): void
tests/Feature/Modules/Posts/Http/CommentReactionHttpTest.php:86:        $liker = $this->createUser();
tests/Feature/Modules/Posts/Http/CommentReactionHttpTest.php:90:        $this->authedJson('DELETE', \sprintf('/api/v1/posts/comments/%s', $comment->id->value()), $author->id)->assertNoContent();
tests/Feature/Modules/Posts/Http/CommentReactionHttpTest.php:92:        $this->authedJson('POST', \sprintf('/api/v1/posts/comments/%s/like', $comment->id->value()), $liker->id)
tests/Feature/Modules/Posts/Http/CommentReactionHttpTest.php:96:    public function testLikeMissingCommentReturns404(): void
tests/Feature/Modules/Posts/Http/CommentReactionHttpTest.php:100:        $this->authedJson('POST', \sprintf('/api/v1/posts/comments/%s/like', UserId::generate()->value()), $user->id)
tests/Feature/Modules/Posts/Http/CommentReactionHttpTest.php:104:    public function testUnlikeMissingCommentReturns404(): void
tests/Feature/Modules/Posts/Http/CommentReactionHttpTest.php:108:        $this->authedJson('DELETE', \sprintf('/api/v1/posts/comments/%s/like', UserId::generate()->value()), $user->id)
tests/Feature/Modules/Posts/Http/CommentReactionHttpTest.php:112:    public function testRequiresAuthentication(): void
tests/Feature/Modules/Posts/Http/CommentReactionHttpTest.php:118:        $this->fakeHttp()->postJson(\sprintf('/api/v1/posts/comments/%s/like', $comment->id->value()))
tests/Feature/Modules/Posts/Http/DeletePostHttpTest.php:12:    public function testDeletesOwnPost(): void
tests/Feature/Modules/Posts/Http/DeletePostHttpTest.php:18:            ->assertNoContent();
tests/Feature/Modules/Posts/Http/DeletePostHttpTest.php:23:    public function testRepeatedDeleteIsIdempotent(): void
tests/Feature/Modules/Posts/Http/DeletePostHttpTest.php:28:        $this->authedJson('DELETE', \sprintf('/api/v1/posts/%s', $post->id->value()), $user->id)->assertNoContent();
tests/Feature/Modules/Posts/Http/DeletePostHttpTest.php:29:        $this->authedJson('DELETE', \sprintf('/api/v1/posts/%s', $post->id->value()), $user->id)->assertNoContent();
tests/Feature/Modules/Posts/Http/DeletePostHttpTest.php:32:    public function testDeletingRepostDecrementsOriginal(): void
tests/Feature/Modules/Posts/Http/DeletePostHttpTest.php:42:            ->assertNoContent();
tests/Feature/Modules/Posts/Http/DeletePostHttpTest.php:47:    public function testRejectsDeleteByNonOwner(): void
tests/Feature/Modules/Posts/Http/DeletePostHttpTest.php:57:    public function testRejectsDeleteOfMissingPost(): void
tests/Feature/Modules/Posts/Http/DeletePostHttpTest.php:65:    public function testRejectsInvalidPostId(): void
tests/Feature/Modules/Posts/Http/DeletePostHttpTest.php:72:    public function testRequiresAuthentication(): void
tests/Feature/Modules/Posts/Http/GetPostHttpTest.php:13:    public function testReturnsEnrichedPost(): void
tests/Feature/Modules/Posts/Http/GetPostHttpTest.php:25:        // Лайкнем, чтобы проверить флаг likedByMe в чтении.
tests/Feature/Modules/Posts/Http/GetPostHttpTest.php:26:        $this->authedJson('POST', \sprintf('/api/v1/posts/%s/like', $postId), $user->id)->assertNoContent();
tests/Feature/Modules/Posts/Http/GetPostHttpTest.php:36:        self::assertTrue($data['likedByMe']);
tests/Feature/Modules/Posts/Http/GetPostHttpTest.php:37:        self::assertSame(1, $data['likesCount']);
tests/Feature/Modules/Posts/Http/GetPostHttpTest.php:40:    public function testReturnsOriginalForRepost(): void
tests/Feature/Modules/Posts/Http/GetPostHttpTest.php:61:    public function testReturnsNullOriginalWhenOriginalDeleted(): void
tests/Feature/Modules/Posts/Http/GetPostHttpTest.php:74:            ->assertNoContent();
tests/Feature/Modules/Posts/Http/GetPostHttpTest.php:82:    public function testForeignDraftReturns404(): void
tests/Feature/Modules/Posts/Http/GetPostHttpTest.php:86:        $post = $this->persistPost($author->id, PostStatus::Draft);
tests/Feature/Modules/Posts/Http/GetPostHttpTest.php:91:    public function testOwnerSeesOwnDraft(): void
tests/Feature/Modules/Posts/Http/GetPostHttpTest.php:94:        $post = $this->persistPost($author->id, PostStatus::Draft);
tests/Feature/Modules/Posts/Http/GetPostHttpTest.php:99:    public function testBlockedReturns404(): void
tests/Feature/Modules/Posts/Http/GetPostHttpTest.php:102:        $post = $this->persistPost($author->id, PostStatus::Blocked);
tests/Feature/Modules/Posts/Http/GetPostHttpTest.php:107:    public function testDeletedReturns404(): void
tests/Feature/Modules/Posts/Http/GetPostHttpTest.php:110:        $post = $this->persistPost($author->id, PostStatus::Published, deleted: true);
tests/Feature/Modules/Posts/Http/GetPostHttpTest.php:115:    public function testMissingReturns404(): void
tests/Feature/Modules/Posts/Http/GetPostHttpTest.php:122:    public function testInvalidIdReturns422(): void
tests/Feature/Modules/Posts/Http/GetPostHttpTest.php:129:    public function testRequiresAuthentication(): void
tests/Feature/Modules/Posts/Http/GetUserFeedHttpTest.php:11:    public function testOwnerSeesDraftsAndPublished(): void
tests/Feature/Modules/Posts/Http/GetUserFeedHttpTest.php:15:        $this->persistPost($author->id, PostStatus::Draft);
tests/Feature/Modules/Posts/Http/GetUserFeedHttpTest.php:23:    public function testStrangerSeesOnlyPublished(): void
tests/Feature/Modules/Posts/Http/GetUserFeedHttpTest.php:28:        $this->persistPost($author->id, PostStatus::Draft);
tests/Feature/Modules/Posts/Http/GetUserFeedHttpTest.php:38:    public function testHidesSoftDeletedPosts(): void
tests/Feature/Modules/Posts/Http/GetUserFeedHttpTest.php:42:        $this->persistPost($author->id, PostStatus::Published, deleted: true);
tests/Feature/Modules/Posts/Http/GetUserFeedHttpTest.php:50:    public function testPaginatesWithCursor(): void
tests/Feature/Modules/Posts/Http/GetUserFeedHttpTest.php:76:    public function testExposesLikedByMeFlagPerPost(): void
tests/Feature/Modules/Posts/Http/GetUserFeedHttpTest.php:80:        $likedPost = $this->persistPost($author->id, PostStatus::Published);
tests/Feature/Modules/Posts/Http/GetUserFeedHttpTest.php:83:        $this->authedJson('POST', \sprintf('/api/v1/posts/%s/like', $likedPost->id->value()), $viewer->id)
tests/Feature/Modules/Posts/Http/GetUserFeedHttpTest.php:84:            ->assertNoContent();
tests/Feature/Modules/Posts/Http/GetUserFeedHttpTest.php:89:        $likedFlags = [];
tests/Feature/Modules/Posts/Http/GetUserFeedHttpTest.php:91:            $likedFlags[$post['id']] = $post['likedByMe'];
tests/Feature/Modules/Posts/Http/GetUserFeedHttpTest.php:94:        self::assertTrue($likedFlags[$likedPost->id->value()]);
tests/Feature/Modules/Posts/Http/GetUserFeedHttpTest.php:95:        self::assertContains(false, $likedFlags);
tests/Feature/Modules/Posts/Http/GetUserFeedHttpTest.php:98:    public function testEmptyFeedReturnsEmptyData(): void
tests/Feature/Modules/Posts/Http/GetUserFeedHttpTest.php:110:    public function testRequiresAuthentication(): void
tests/Feature/Modules/Posts/Http/PostReactionHttpTest.php:12:    public function testLikesPostAndNotifiesAuthor(): void
tests/Feature/Modules/Posts/Http/PostReactionHttpTest.php:15:        $liker = $this->createUser();
tests/Feature/Modules/Posts/Http/PostReactionHttpTest.php:18:        $this->authedJson('POST', \sprintf('/api/v1/posts/%s/like', $post->id->value()), $liker->id)
tests/Feature/Modules/Posts/Http/PostReactionHttpTest.php:19:            ->assertNoContent();
tests/Feature/Modules/Posts/Http/PostReactionHttpTest.php:21:        self::assertSame(1, $this->reloadPost($post)->likesCount->value());
tests/Feature/Modules/Posts/Http/PostReactionHttpTest.php:23:        $likes = $this->stagedNotifications('posts.post_like');
tests/Feature/Modules/Posts/Http/PostReactionHttpTest.php:24:        self::assertCount(1, $likes);
tests/Feature/Modules/Posts/Http/PostReactionHttpTest.php:25:        self::assertSame($author->id->value(), $likes[0]->userId);
tests/Feature/Modules/Posts/Http/PostReactionHttpTest.php:28:    public function testLikeIsIdempotent(): void
tests/Feature/Modules/Posts/Http/PostReactionHttpTest.php:31:        $liker = $this->createUser();
tests/Feature/Modules/Posts/Http/PostReactionHttpTest.php:34:        $this->authedJson('POST', \sprintf('/api/v1/posts/%s/like', $post->id->value()), $liker->id)->assertNoContent();
tests/Feature/Modules/Posts/Http/PostReactionHttpTest.php:35:        $this->authedJson('POST', \sprintf('/api/v1/posts/%s/like', $post->id->value()), $liker->id)->assertNoContent();
tests/Feature/Modules/Posts/Http/PostReactionHttpTest.php:37:        self::assertSame(1, $this->reloadPost($post)->likesCount->value());
tests/Feature/Modules/Posts/Http/PostReactionHttpTest.php:38:        self::assertCount(1, $this->stagedNotifications('posts.post_like'));
tests/Feature/Modules/Posts/Http/PostReactionHttpTest.php:41:    public function testSelfLikeDoesNotNotify(): void
tests/Feature/Modules/Posts/Http/PostReactionHttpTest.php:46:        $this->authedJson('POST', \sprintf('/api/v1/posts/%s/like', $post->id->value()), $user->id)->assertNoContent();
tests/Feature/Modules/Posts/Http/PostReactionHttpTest.php:48:        self::assertSame(1, $this->reloadPost($post)->likesCount->value());
tests/Feature/Modules/Posts/Http/PostReactionHttpTest.php:49:        self::assertCount(0, $this->stagedNotifications('posts.post_like'));
tests/Feature/Modules/Posts/Http/PostReactionHttpTest.php:52:    public function testUnlikePost(): void
tests/Feature/Modules/Posts/Http/PostReactionHttpTest.php:55:        $liker = $this->createUser();
tests/Feature/Modules/Posts/Http/PostReactionHttpTest.php:58:        $this->authedJson('POST', \sprintf('/api/v1/posts/%s/like', $post->id->value()), $liker->id)->assertNoContent();
tests/Feature/Modules/Posts/Http/PostReactionHttpTest.php:59:        $this->authedJson('DELETE', \sprintf('/api/v1/posts/%s/like', $post->id->value()), $liker->id)->assertNoContent();
tests/Feature/Modules/Posts/Http/PostReactionHttpTest.php:61:        self::assertSame(0, $this->reloadPost($post)->likesCount->value());
tests/Feature/Modules/Posts/Http/PostReactionHttpTest.php:62:        self::assertFalse($this->postLikeRepository()->existsByPostAndUser($post->id, $liker->id));
tests/Feature/Modules/Posts/Http/PostReactionHttpTest.php:65:    public function testUnlikeWithoutPriorLikeIsNoOp(): void
tests/Feature/Modules/Posts/Http/PostReactionHttpTest.php:68:        $liker = $this->createUser();
tests/Feature/Modules/Posts/Http/PostReactionHttpTest.php:71:        $this->authedJson('DELETE', \sprintf('/api/v1/posts/%s/like', $post->id->value()), $liker->id)->assertNoContent();
tests/Feature/Modules/Posts/Http/PostReactionHttpTest.php:73:        self::assertSame(0, $this->reloadPost($post)->likesCount->value());
tests/Feature/Modules/Posts/Http/PostReactionHttpTest.php:76:    public function testLikeOnDraftReturns404(): void
tests/Feature/Modules/Posts/Http/PostReactionHttpTest.php:79:        $liker = $this->createUser();
tests/Feature/Modules/Posts/Http/PostReactionHttpTest.php:80:        $post = $this->persistPost($author->id, PostStatus::Draft);
tests/Feature/Modules/Posts/Http/PostReactionHttpTest.php:82:        $this->authedJson('POST', \sprintf('/api/v1/posts/%s/like', $post->id->value()), $liker->id)
tests/Feature/Modules/Posts/Http/PostReactionHttpTest.php:86:    public function testLikeMissingPostReturns404(): void
tests/Feature/Modules/Posts/Http/PostReactionHttpTest.php:90:        $this->authedJson('POST', \sprintf('/api/v1/posts/%s/like', UserId::generate()->value()), $user->id)
tests/Feature/Modules/Posts/Http/PostReactionHttpTest.php:94:    public function testUnlikeMissingPostReturns404(): void
tests/Feature/Modules/Posts/Http/PostReactionHttpTest.php:98:        $this->authedJson('DELETE', \sprintf('/api/v1/posts/%s/like', UserId::generate()->value()), $user->id)
tests/Feature/Modules/Posts/Http/PostReactionHttpTest.php:102:    public function testRequiresAuthentication(): void
tests/Feature/Modules/Posts/Http/PostReactionHttpTest.php:107:        $this->fakeHttp()->postJson(\sprintf('/api/v1/posts/%s/like', $post->id->value()))->assertUnauthorized();

exec
/bin/zsh -lc "rg \"array_filter|array_map|->all\\(\\)\" app/src/Modules/Posts/Application app/src/Modules/Tags/Application app/src/Modules/User/Application -n" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
app/src/Modules/Tags/Application/Query/GetTags/GetTagsHandler.php:25:        $tagIds = \array_map(
app/src/Modules/User/Application/Query/GetUserPublicProfiles/GetUserPublicProfilesHandler.php:30:        $userIds = \array_map(
app/src/Modules/User/Application/Query/GetUserPublicProfiles/GetUserPublicProfilesHandler.php:35:        $profiles = \array_map(
app/src/Modules/User/Application/Query/GetUserPublicProfiles/GetUserPublicProfilesHandler.php:37:            $this->userRepository->findByIds(...$userIds)->all(),
app/src/Modules/Posts/Application/Query/GetUserFeed/GetUserFeedHandler.php:39:        )->all();
app/src/Modules/Posts/Application/Query/GetPostComments/GetPostCommentsHandler.php:48:        )->all();
app/src/Modules/Posts/Application/Query/GetCommentReplies/GetCommentRepliesHandler.php:51:        )->all();
app/src/Modules/Posts/Application/View/PostViewAssembler.php:66:        $entities = $posts->all();
app/src/Modules/Posts/Application/View/PostViewAssembler.php:126:        $userIds = \array_values(\array_unique(\array_map(
app/src/Modules/Posts/Application/View/PostViewAssembler.php:156:        $postIds = \array_map(static fn(Post $post): PostId => $post->id, $posts);
app/src/Modules/Posts/Application/View/CommentViewAssembler.php:45:        $entities = $comments->all();
app/src/Modules/Posts/Application/View/CommentViewAssembler.php:99:        $userIds = \array_values(\array_unique(\array_map(
app/src/Modules/Posts/Application/View/CommentViewAssembler.php:129:        $commentIds = \array_map(static fn(Comment $comment): CommentId => $comment->id, $comments);

exec
/bin/zsh -lc 'rg "public function test" tests/Feature/Modules/Posts/Http -n' in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
tests/Feature/Modules/Posts/Http/CommentHttpTest.php:12:    public function testCommentsOnPostAndNotifiesAuthor(): void
tests/Feature/Modules/Posts/Http/CommentHttpTest.php:38:    public function testCommentStagesMentionNotifications(): void
tests/Feature/Modules/Posts/Http/CommentHttpTest.php:58:    public function testMentionDedupBeatsPostCommentedForAuthor(): void
tests/Feature/Modules/Posts/Http/CommentHttpTest.php:78:    public function testSelfCommentDoesNotNotify(): void
tests/Feature/Modules/Posts/Http/CommentHttpTest.php:94:    public function testCommentOnDraftReturns404(): void
tests/Feature/Modules/Posts/Http/CommentHttpTest.php:108:    public function testRejectsEmptyCommentText(): void
tests/Feature/Modules/Posts/Http/CommentHttpTest.php:122:    public function testRepliesToCommentNotifiesParentAuthorOnly(): void
tests/Feature/Modules/Posts/Http/CommentHttpTest.php:150:    public function testReplyToDeletedCommentReturns404(): void
tests/Feature/Modules/Posts/Http/CommentHttpTest.php:168:    public function testDeletesTopLevelCommentDecrementsPost(): void
tests/Feature/Modules/Posts/Http/CommentHttpTest.php:183:    public function testDeletesReplyDecrementsParent(): void
tests/Feature/Modules/Posts/Http/CommentHttpTest.php:198:    public function testRepeatedDeleteIsIdempotent(): void
tests/Feature/Modules/Posts/Http/CommentHttpTest.php:210:    public function testRejectsDeleteByNonOwner(): void
tests/Feature/Modules/Posts/Http/CommentHttpTest.php:221:    public function testRejectsDeleteOfMissingComment(): void
tests/Feature/Modules/Posts/Http/CommentHttpTest.php:229:    public function testRejectsInvalidCommentId(): void
tests/Feature/Modules/Posts/Http/CommentHttpTest.php:237:    public function testCommentOnMissingPostReturns404(): void
tests/Feature/Modules/Posts/Http/CommentHttpTest.php:249:    public function testRejectsNonexistentMentionInComment(): void
tests/Feature/Modules/Posts/Http/CommentHttpTest.php:263:    public function testReplyToMissingCommentReturns404(): void
tests/Feature/Modules/Posts/Http/CommentHttpTest.php:275:    public function testReplyOnBlockedPostReturns404(): void
tests/Feature/Modules/Posts/Http/GetUserFeedHttpTest.php:11:    public function testOwnerSeesDraftsAndPublished(): void
tests/Feature/Modules/Posts/Http/GetUserFeedHttpTest.php:23:    public function testStrangerSeesOnlyPublished(): void
tests/Feature/Modules/Posts/Http/GetUserFeedHttpTest.php:38:    public function testHidesSoftDeletedPosts(): void
tests/Feature/Modules/Posts/Http/GetUserFeedHttpTest.php:50:    public function testPaginatesWithCursor(): void
tests/Feature/Modules/Posts/Http/GetUserFeedHttpTest.php:76:    public function testExposesLikedByMeFlagPerPost(): void
tests/Feature/Modules/Posts/Http/GetUserFeedHttpTest.php:98:    public function testEmptyFeedReturnsEmptyData(): void
tests/Feature/Modules/Posts/Http/GetUserFeedHttpTest.php:110:    public function testRequiresAuthentication(): void
tests/Feature/Modules/Posts/Http/PostReactionHttpTest.php:12:    public function testLikesPostAndNotifiesAuthor(): void
tests/Feature/Modules/Posts/Http/PostReactionHttpTest.php:28:    public function testLikeIsIdempotent(): void
tests/Feature/Modules/Posts/Http/PostReactionHttpTest.php:41:    public function testSelfLikeDoesNotNotify(): void
tests/Feature/Modules/Posts/Http/PostReactionHttpTest.php:52:    public function testUnlikePost(): void
tests/Feature/Modules/Posts/Http/PostReactionHttpTest.php:65:    public function testUnlikeWithoutPriorLikeIsNoOp(): void
tests/Feature/Modules/Posts/Http/PostReactionHttpTest.php:76:    public function testLikeOnDraftReturns404(): void
tests/Feature/Modules/Posts/Http/PostReactionHttpTest.php:86:    public function testLikeMissingPostReturns404(): void
tests/Feature/Modules/Posts/Http/PostReactionHttpTest.php:94:    public function testUnlikeMissingPostReturns404(): void
tests/Feature/Modules/Posts/Http/PostReactionHttpTest.php:102:    public function testRequiresAuthentication(): void
tests/Feature/Modules/Posts/Http/CommentReactionHttpTest.php:12:    public function testLikesCommentAndNotifiesAuthor(): void
tests/Feature/Modules/Posts/Http/CommentReactionHttpTest.php:29:    public function testLikeCommentIsIdempotent(): void
tests/Feature/Modules/Posts/Http/CommentReactionHttpTest.php:43:    public function testSelfLikeCommentDoesNotNotify(): void
tests/Feature/Modules/Posts/Http/CommentReactionHttpTest.php:56:    public function testUnlikeComment(): void
tests/Feature/Modules/Posts/Http/CommentReactionHttpTest.php:70:    public function testUnlikeWithoutPriorLikeIsNoOp(): void
tests/Feature/Modules/Posts/Http/CommentReactionHttpTest.php:83:    public function testLikeDeletedCommentReturns404(): void
tests/Feature/Modules/Posts/Http/CommentReactionHttpTest.php:96:    public function testLikeMissingCommentReturns404(): void
tests/Feature/Modules/Posts/Http/CommentReactionHttpTest.php:104:    public function testUnlikeMissingCommentReturns404(): void
tests/Feature/Modules/Posts/Http/CommentReactionHttpTest.php:112:    public function testRequiresAuthentication(): void
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php:14:    public function testCreatesPublishedPost(): void
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php:31:    public function testCreatesDraftPost(): void
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php:44:    public function testCreatesPostWithMedia(): void
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php:60:    public function testRejectsForeignMedia(): void
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php:71:    public function testRejectsMediaThatIsNotReady(): void
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php:81:    public function testRejectsMissingMedia(): void
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php:90:    public function testCreatesPostWithNewAndExistingTags(): void
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php:106:    public function testCreatesPostWithMentionsStagesNotifications(): void
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php:126:    public function testDoesNotNotifySelfMention(): void
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php:138:    public function testRejectsNonexistentMention(): void
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php:147:    public function testRejectsTooManyMentions(): void
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php:158:    public function testRejectsTextOverLimit(): void
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php:167:    public function testRejectsInvalidMediaUuid(): void
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php:176:    public function testRequiresAuthentication(): void
tests/Feature/Modules/Posts/Http/DeletePostHttpTest.php:12:    public function testDeletesOwnPost(): void
tests/Feature/Modules/Posts/Http/DeletePostHttpTest.php:23:    public function testRepeatedDeleteIsIdempotent(): void
tests/Feature/Modules/Posts/Http/DeletePostHttpTest.php:32:    public function testDeletingRepostDecrementsOriginal(): void
tests/Feature/Modules/Posts/Http/DeletePostHttpTest.php:47:    public function testRejectsDeleteByNonOwner(): void
tests/Feature/Modules/Posts/Http/DeletePostHttpTest.php:57:    public function testRejectsDeleteOfMissingPost(): void
tests/Feature/Modules/Posts/Http/DeletePostHttpTest.php:65:    public function testRejectsInvalidPostId(): void
tests/Feature/Modules/Posts/Http/DeletePostHttpTest.php:72:    public function testRequiresAuthentication(): void
tests/Feature/Modules/Posts/Http/GetPostHttpTest.php:13:    public function testReturnsEnrichedPost(): void
tests/Feature/Modules/Posts/Http/GetPostHttpTest.php:40:    public function testReturnsOriginalForRepost(): void
tests/Feature/Modules/Posts/Http/GetPostHttpTest.php:61:    public function testReturnsNullOriginalWhenOriginalDeleted(): void
tests/Feature/Modules/Posts/Http/GetPostHttpTest.php:82:    public function testForeignDraftReturns404(): void
tests/Feature/Modules/Posts/Http/GetPostHttpTest.php:91:    public function testOwnerSeesOwnDraft(): void
tests/Feature/Modules/Posts/Http/GetPostHttpTest.php:99:    public function testBlockedReturns404(): void
tests/Feature/Modules/Posts/Http/GetPostHttpTest.php:107:    public function testDeletedReturns404(): void
tests/Feature/Modules/Posts/Http/GetPostHttpTest.php:115:    public function testMissingReturns404(): void
tests/Feature/Modules/Posts/Http/GetPostHttpTest.php:122:    public function testInvalidIdReturns422(): void
tests/Feature/Modules/Posts/Http/GetPostHttpTest.php:129:    public function testRequiresAuthentication(): void
tests/Feature/Modules/Posts/Http/PublishPostHttpTest.php:12:    public function testPublishesOwnDraft(): void
tests/Feature/Modules/Posts/Http/PublishPostHttpTest.php:24:    public function testAlreadyPublishedIsIdempotentNoOp(): void
tests/Feature/Modules/Posts/Http/PublishPostHttpTest.php:35:    public function testRejectsPublishByNonOwner(): void
tests/Feature/Modules/Posts/Http/PublishPostHttpTest.php:45:    public function testRejectsPublishOfDeletedPost(): void
tests/Feature/Modules/Posts/Http/PublishPostHttpTest.php:54:    public function testRejectsPublishOfBlockedPost(): void
tests/Feature/Modules/Posts/Http/PublishPostHttpTest.php:63:    public function testRejectsPublishOfMissingPost(): void
tests/Feature/Modules/Posts/Http/PublishPostHttpTest.php:71:    public function testRejectsInvalidPostId(): void
tests/Feature/Modules/Posts/Http/RepostPostHttpTest.php:12:    public function testCreatesRepostIncrementsOriginalAndNotifiesAuthor(): void
tests/Feature/Modules/Posts/Http/RepostPostHttpTest.php:38:    public function testSelfRepostDoesNotNotify(): void
tests/Feature/Modules/Posts/Http/RepostPostHttpTest.php:49:    public function testRejectsRepostOfDraftTarget(): void
tests/Feature/Modules/Posts/Http/RepostPostHttpTest.php:59:    public function testRejectsRepostOfDeletedTarget(): void
tests/Feature/Modules/Posts/Http/RepostPostHttpTest.php:69:    public function testRejectsRepostOfMissingTarget(): void
tests/Feature/Modules/Posts/Http/RepostPostHttpTest.php:77:    public function testRejectsInvalidTargetId(): void
tests/Feature/Modules/Posts/Http/RepostPostHttpTest.php:84:    public function testRequiresAuthentication(): void
tests/Feature/Modules/Posts/Http/GetCommentRepliesHttpTest.php:12:    public function testReturnsRepliesOfParent(): void
tests/Feature/Modules/Posts/Http/GetCommentRepliesHttpTest.php:29:    public function testHidesDeletedReplies(): void
tests/Feature/Modules/Posts/Http/GetCommentRepliesHttpTest.php:48:    public function testPaginatesWithCursor(): void
tests/Feature/Modules/Posts/Http/GetCommentRepliesHttpTest.php:76:    public function testReturns404WhenParentMissing(): void
tests/Feature/Modules/Posts/Http/GetCommentRepliesHttpTest.php:84:    public function testReturns404WhenPostNotVisible(): void
tests/Feature/Modules/Posts/Http/GetCommentRepliesHttpTest.php:95:    public function testRequiresAuthentication(): void
tests/Feature/Modules/Posts/Http/GetPostCommentsHttpTest.php:12:    public function testReturnsTopLevelCommentsOnly(): void
tests/Feature/Modules/Posts/Http/GetPostCommentsHttpTest.php:27:    public function testHidesDeletedComments(): void
tests/Feature/Modules/Posts/Http/GetPostCommentsHttpTest.php:42:    public function testExposesLikedByMeFlag(): void
tests/Feature/Modules/Posts/Http/GetPostCommentsHttpTest.php:65:    public function testPaginatesWithCursor(): void
tests/Feature/Modules/Posts/Http/GetPostCommentsHttpTest.php:92:    public function testReturns404WhenPostNotVisible(): void
tests/Feature/Modules/Posts/Http/GetPostCommentsHttpTest.php:101:    public function testReturns404WhenPostMissing(): void
tests/Feature/Modules/Posts/Http/GetPostCommentsHttpTest.php:109:    public function testRequiresAuthentication(): void

codex
Подсчёт тестов по именам показывает: каждый маршрут покрыт, но нет кейса «снятие лайка с удалённого комментария» и нет кейса чтения поста/ленты с уже недоступным вложенным медиа. Сейчас досчитываю операции OpenAPI и проверяю ещё обработчики чтения.
exec
/bin/zsh -lc "nl -ba app/src/Modules/Posts/Application/Query/GetPost/GetPostHandler.php | sed -n '1,180p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
exec
/bin/zsh -lc "nl -ba app/src/Modules/Posts/Application/Query/GetCommentReplies/GetCommentRepliesHandler.php | sed -n '1,180p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Posts\Application\Query\GetPost;
     6	
     7	use App\Modules\Posts\Application\Post\PostVisibilityPolicy;
     8	use App\Modules\Posts\Application\View\PostView;
     9	use App\Modules\Posts\Application\View\PostViewAssembler;
    10	use App\Modules\Posts\Domain\ValueObject\PostId;
    11	use App\Modules\Posts\Repository\PostRepository;
    12	use App\Shared\Domain\Exception\NotFoundException;
    13	use App\Shared\Domain\ValueObject\UserId;
    14	use GianTiaga\SpiralCqrs\Attribute\LogOperation;
    15	
    16	/**
    17	 * Чтение одной записи. Невидимая зрителю (чужой черновик/заблокированная/удалённая) -> 404.
    18	 */
    19	final readonly class GetPostHandler
    20	{
    21	    public function __construct(
    22	        private PostRepository $postRepository,
    23	        private PostViewAssembler $postViewAssembler,
    24	    ) {}
    25	
    26	    #[LogOperation]
    27	    public function handle(GetPostQuery $query): PostView
    28	    {
    29	        $viewer = UserId::fromString($query->authUserId);
    30	
    31	        $post = $this->postRepository->findById(PostId::fromString($query->postId))
    32	            ?? throw new NotFoundException('app.posts.not_found');
    33	
    34	        if (!PostVisibilityPolicy::isVisibleTo(post: $post, viewer: $viewer)) {
    35	            throw new NotFoundException('app.posts.not_found');
    36	        }
    37	
    38	        return $this->postViewAssembler->fromPost(post: $post, viewer: $viewer);
    39	    }
    40	}

 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Posts\Application\Query\GetCommentReplies;
     6	
     7	use App\Modules\Posts\Application\Post\PostVisibilityPolicy;
     8	use App\Modules\Posts\Application\View\CommentViewAssembler;
     9	use App\Modules\Posts\Domain\Collection\CommentCollection;
    10	use App\Modules\Posts\Domain\ValueObject\CommentId;
    11	use App\Modules\Posts\Repository\CommentRepository;
    12	use App\Modules\Posts\Repository\PostRepository;
    13	use App\Shared\Domain\Exception\NotFoundException;
    14	use App\Shared\Domain\ValueObject\UserId;
    15	use GianTiaga\SpiralCqrs\Attribute\LogOperation;
    16	
    17	/**
    18	 * Ответы на комментарий с cursor-пагинацией. Родительский комментарий должен существовать, а его
    19	 * запись — быть видна зрителю (иначе 404); удалённые ответы скрыты репозиторием.
    20	 */
    21	final readonly class GetCommentRepliesHandler
    22	{
    23	    public function __construct(
    24	        private CommentRepository $commentRepository,
    25	        private PostRepository $postRepository,
    26	        private CommentViewAssembler $commentViewAssembler,
    27	    ) {}
    28	
    29	    #[LogOperation]
    30	    public function handle(GetCommentRepliesQuery $query): GetCommentRepliesResult
    31	    {
    32	        $viewer = UserId::fromString($query->authUserId);
    33	
    34	        $parent = $this->commentRepository->findById(CommentId::fromString($query->commentId))
    35	            ?? throw new NotFoundException('app.posts.comment_not_found');
    36	
    37	        // Запись существующего комментария всегда есть (FK), но проверку null оставляем в одном
    38	        // условии с видимостью: читать ответы можно только у видимой зрителю записи.
    39	        $post = $this->postRepository->findById($parent->postId);
    40	
    41	        if ($post === null || !PostVisibilityPolicy::isVisibleTo(post: $post, viewer: $viewer)) {
    42	            throw new NotFoundException('app.posts.not_found');
    43	        }
    44	
    45	        $cursor = $query->cursor !== null ? CommentId::fromString($query->cursor) : null;
    46	
    47	        $page = $this->commentRepository->findReplies(
    48	            parentId: $parent->id,
    49	            cursor: $cursor,
    50	            limit: $query->limit + 1,
    51	        )->all();
    52	
    53	        if (\count($page) <= $query->limit) {
    54	            return new GetCommentRepliesResult(
    55	                replies: $this->commentViewAssembler->fromComments(comments: new CommentCollection($page), viewer: $viewer),
    56	                nextCursor: null,
    57	            );
    58	        }
    59	
    60	        $visible = \array_slice(array: $page, offset: 0, length: $query->limit);
    61	
    62	        return new GetCommentRepliesResult(
    63	            replies: $this->commentViewAssembler->fromComments(comments: new CommentCollection($visible), viewer: $viewer),
    64	            nextCursor: $visible[\count($visible) - 1]->id->value(),
    65	        );
    66	    }
    67	}

exec
/bin/zsh -lc "nl -ba app/src/Modules/Posts/Application/Query/GetUserFeed/GetUserFeedHandler.php | sed -n '1,180p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Posts\Application\Query\GetUserFeed;
     6	
     7	use App\Modules\Posts\Application\View\PostViewAssembler;
     8	use App\Modules\Posts\Domain\Collection\PostCollection;
     9	use App\Modules\Posts\Domain\Enum\PostStatus;
    10	use App\Modules\Posts\Domain\ValueObject\PostId;
    11	use App\Modules\Posts\Repository\PostRepository;
    12	use App\Shared\Domain\ValueObject\UserId;
    13	use GianTiaga\SpiralCqrs\Attribute\LogOperation;
    14	
    15	/**
    16	 * Лента записей автора с cursor-пагинацией. Свои черновики видны только владельцу (статус null —
    17	 * все статусы), посторонним — только опубликованные. Мягко удалённые скрыты репозиторием.
    18	 */
    19	final readonly class GetUserFeedHandler
    20	{
    21	    public function __construct(
    22	        private PostRepository $postRepository,
    23	        private PostViewAssembler $postViewAssembler,
    24	    ) {}
    25	
    26	    #[LogOperation]
    27	    public function handle(GetUserFeedQuery $query): GetUserFeedResult
    28	    {
    29	        $viewer = UserId::fromString($query->authUserId);
    30	        $owner = UserId::fromString($query->ownerUserId);
    31	        $status = $owner->equals($viewer) ? null : PostStatus::Published;
    32	        $cursor = $query->cursor !== null ? PostId::fromString($query->cursor) : null;
    33	
    34	        $page = $this->postRepository->findVisibleByUserId(
    35	            userId: $owner,
    36	            status: $status,
    37	            cursor: $cursor,
    38	            limit: $query->limit + 1,
    39	        )->all();
    40	
    41	        if (\count($page) <= $query->limit) {
    42	            return new GetUserFeedResult(
    43	                posts: $this->postViewAssembler->fromPosts(posts: new PostCollection($page), viewer: $viewer),
    44	                nextCursor: null,
    45	            );
    46	        }
    47	
    48	        $visible = \array_slice(array: $page, offset: 0, length: $query->limit);
    49	
    50	        return new GetUserFeedResult(
    51	            posts: $this->postViewAssembler->fromPosts(posts: new PostCollection($visible), viewer: $viewer),
    52	            nextCursor: $visible[\count($visible) - 1]->id->value(),
    53	        );
    54	    }
    55	}

exec
/bin/zsh -lc "nl -ba app/src/Modules/Posts/Application/Query/GetPostComments/GetPostCommentsHandler.php | sed -n '1,180p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Posts\Application\Query\GetPostComments;
     6	
     7	use App\Modules\Posts\Application\Post\PostVisibilityPolicy;
     8	use App\Modules\Posts\Application\View\CommentViewAssembler;
     9	use App\Modules\Posts\Domain\Collection\CommentCollection;
    10	use App\Modules\Posts\Domain\ValueObject\CommentId;
    11	use App\Modules\Posts\Domain\ValueObject\PostId;
    12	use App\Modules\Posts\Repository\CommentRepository;
    13	use App\Modules\Posts\Repository\PostRepository;
    14	use App\Shared\Domain\Exception\NotFoundException;
    15	use App\Shared\Domain\ValueObject\UserId;
    16	use GianTiaga\SpiralCqrs\Attribute\LogOperation;
    17	
    18	/**
    19	 * Комментарии верхнего уровня записи с cursor-пагинацией. Запись должна быть видна зрителю (иначе
    20	 * 404); удалённые комментарии скрыты репозиторием.
    21	 */
    22	final readonly class GetPostCommentsHandler
    23	{
    24	    public function __construct(
    25	        private PostRepository $postRepository,
    26	        private CommentRepository $commentRepository,
    27	        private CommentViewAssembler $commentViewAssembler,
    28	    ) {}
    29	
    30	    #[LogOperation]
    31	    public function handle(GetPostCommentsQuery $query): GetPostCommentsResult
    32	    {
    33	        $viewer = UserId::fromString($query->authUserId);
    34	
    35	        $post = $this->postRepository->findById(PostId::fromString($query->postId))
    36	            ?? throw new NotFoundException('app.posts.not_found');
    37	
    38	        if (!PostVisibilityPolicy::isVisibleTo(post: $post, viewer: $viewer)) {
    39	            throw new NotFoundException('app.posts.not_found');
    40	        }
    41	
    42	        $cursor = $query->cursor !== null ? CommentId::fromString($query->cursor) : null;
    43	
    44	        $page = $this->commentRepository->findTopLevelByPostId(
    45	            postId: $post->id,
    46	            cursor: $cursor,
    47	            limit: $query->limit + 1,
    48	        )->all();
    49	
    50	        if (\count($page) <= $query->limit) {
    51	            return new GetPostCommentsResult(
    52	                comments: $this->commentViewAssembler->fromComments(comments: new CommentCollection($page), viewer: $viewer),
    53	                nextCursor: null,
    54	            );
    55	        }
    56	
    57	        $visible = \array_slice(array: $page, offset: 0, length: $query->limit);
    58	
    59	        return new GetPostCommentsResult(
    60	            comments: $this->commentViewAssembler->fromComments(comments: new CommentCollection($visible), viewer: $viewer),
    61	            nextCursor: $visible[\count($visible) - 1]->id->value(),
    62	        );
    63	    }
    64	}

exec
/bin/zsh -lc "nl -ba app/src/Modules/Posts/Application/View/CommentViewAssembler.php | sed -n '1,210p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Posts\Application\View;
     6	
     7	use App\Modules\Posts\Domain\Collection\CommentCollection;
     8	use App\Modules\Posts\Domain\Entity\Comment;
     9	use App\Modules\Posts\Domain\ValueObject\CommentId;
    10	use App\Modules\Posts\Repository\CommentLikeRepository;
    11	use App\Modules\User\Application\Query\GetUserPublicProfile\GetUserPublicProfileHandler;
    12	use App\Modules\User\Application\Query\GetUserPublicProfile\GetUserPublicProfileQuery;
    13	use App\Modules\User\Application\Query\GetUserPublicProfiles\GetUserPublicProfilesHandler;
    14	use App\Modules\User\Application\Query\GetUserPublicProfiles\GetUserPublicProfilesQuery;
    15	use App\Shared\Domain\ValueObject\UserId;
    16	use GianTiaga\SpiralCqrs\QueryBusInterface;
    17	
    18	/**
    19	 * Собирает read-model CommentView из доменного комментария: автор — через User, флаг likedByMe и
    20	 * счётчики — из своего модуля. В листингах авторы и флаги likedByMe собираются пакетно (без N+1).
    21	 */
    22	final readonly class CommentViewAssembler
    23	{
    24	    public function __construct(
    25	        private QueryBusInterface $queryBus,
    26	        private GetUserPublicProfileHandler $getUserPublicProfileHandler,
    27	        private GetUserPublicProfilesHandler $getUserPublicProfilesHandler,
    28	        private CommentLikeRepository $commentLikeRepository,
    29	    ) {}
    30	
    31	    public function fromComment(Comment $comment, UserId $viewer): CommentView
    32	    {
    33	        return $this->build(
    34	            comment: $comment,
    35	            author: $this->authorView($comment->userId),
    36	            likedByMe: $this->commentLikeRepository->existsByCommentAndUser(commentId: $comment->id, userId: $viewer),
    37	        );
    38	    }
    39	
    40	    /**
    41	     * @return list<CommentView>
    42	     */
    43	    public function fromComments(CommentCollection $comments, UserId $viewer): array
    44	    {
    45	        $entities = $comments->all();
    46	
    47	        if ($entities === []) {
    48	            return [];
    49	        }
    50	
    51	        $authors = $this->authorViews($entities);
    52	        $likedCommentIds = $this->likedCommentIds(comments: $entities, viewer: $viewer);
    53	
    54	        $views = [];
    55	
    56	        foreach ($entities as $comment) {
    57	            $views[] = $this->build(
    58	                comment: $comment,
    59	                author: $authors[$comment->userId->value()],
    60	                likedByMe: isset($likedCommentIds[$comment->id->value()]),
    61	            );
    62	        }
    63	
    64	        return $views;
    65	    }
    66	
    67	    private function build(Comment $comment, AuthorView $author, bool $likedByMe): CommentView
    68	    {
    69	        return new CommentView(
    70	            id: $comment->id->value(),
    71	            postId: $comment->postId->value(),
    72	            parentId: $comment->parent->value(),
    73	            text: $comment->text->value(),
    74	            likesCount: $comment->likesCount->value(),
    75	            repliesCount: $comment->repliesCount->value(),
    76	            likedByMe: $likedByMe,
    77	            createdAt: $comment->createdAt->format(\DateTimeInterface::ATOM),
    78	            author: $author,
    79	        );
    80	    }
    81	
    82	    private function authorView(UserId $userId): AuthorView
    83	    {
    84	        $profile = $this->queryBus->dispatch(
    85	            query: new GetUserPublicProfileQuery($userId->value()),
    86	            handler: $this->getUserPublicProfileHandler->handle(...),
    87	        );
    88	
    89	        return new AuthorView(userId: $profile->userId, name: $profile->name, avatarUrl: $profile->avatarUrl);
    90	    }
    91	
    92	    /**
    93	     * @param array<int, Comment> $comments
    94	     *
    95	     * @return array<string, AuthorView>
    96	     */
    97	    private function authorViews(array $comments): array
    98	    {
    99	        $userIds = \array_values(\array_unique(\array_map(
   100	            static fn(Comment $comment): string => $comment->userId->value(),
   101	            $comments,
   102	        )));
   103	
   104	        $profiles = $this->queryBus->dispatch(
   105	            query: new GetUserPublicProfilesQuery($userIds),
   106	            handler: $this->getUserPublicProfilesHandler->handle(...),
   107	        );
   108	
   109	        $authors = [];
   110	
   111	        foreach ($profiles as $profile) {
   112	            $authors[$profile->userId] = new AuthorView(
   113	                userId: $profile->userId,
   114	                name: $profile->name,
   115	                avatarUrl: $profile->avatarUrl,
   116	            );
   117	        }
   118	
   119	        return $authors;
   120	    }
   121	
   122	    /**
   123	     * @param array<int, Comment> $comments
   124	     *
   125	     * @return array<string, true>
   126	     */
   127	    private function likedCommentIds(array $comments, UserId $viewer): array
   128	    {
   129	        $commentIds = \array_map(static fn(Comment $comment): CommentId => $comment->id, $comments);
   130	        $liked = [];
   131	
   132	        foreach ($this->commentLikeRepository->findByUserAndCommentIds($viewer, ...$commentIds) as $like) {
   133	            $liked[$like->commentId->value()] = true;
   134	        }
   135	
   136	        return $liked;
   137	    }
   138	}

exec
/bin/zsh -lc "php -r 'require \"vendor/autoload.php\"; "'$spec=Symfony'"\\Component\\Yaml\\Yaml::parseFile(\"public/openapi/openapi.yml\"); "'$n=0; foreach ($spec["paths"] as $path=>$ops) { if (str_starts_with($path,"/posts")) { foreach ($ops as $method=>$op) { if (in_array($method,["get","post","put","patch","delete"],true)) { $n++; echo $method." ".$path.PHP_EOL; } } } } echo "TOTAL=".$n.PHP_EOL;'"'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 exited 255 in 161ms:
PHP Fatal error:  Uncaught RuntimeException: Composer detected issues in your platform: Your Composer dependencies require a PHP version ">= 8.5.0". You are running 8.4.17. in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1/vendor/composer/platform_check.php:22
Stack trace:
#0 /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1/vendor/composer/autoload_real.php(25): require()
#1 /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1/vendor/autoload.php(22): ComposerAutoloaderInit94342db7394171196117d1f259d58f46::getLoader()
#2 Command line code(1): require('/Users/gian_tia...')
#3 {main}
  thrown in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1/vendor/composer/platform_check.php on line 22

Fatal error: Uncaught RuntimeException: Composer detected issues in your platform: Your Composer dependencies require a PHP version ">= 8.5.0". You are running 8.4.17. in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1/vendor/composer/platform_check.php:22
Stack trace:
#0 /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1/vendor/composer/autoload_real.php(25): require()
#1 /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1/vendor/autoload.php(22): ComposerAutoloaderInit94342db7394171196117d1f259d58f46::getLoader()
#2 Command line code(1): require('/Users/gian_tia...')
#3 {main}
  thrown in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1/vendor/composer/platform_check.php on line 22

codex
Хостовый PHP не подходит для парсинга YAML: Composer требует PHP 8.5, а на хосте 8.4.17. Для подсчёта операций использую простой разбор самого `openapi.yml`, потому что структура файла здесь линейная и уже прочитана.
exec
/bin/zsh -lc "nl -ba app/src/Modules/Posts/Application/Post/PostVisibilityPolicy.php | sed -n '1,160p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
exec
/bin/zsh -lc "awk '
/"'^  ['"\\047]?\\/posts/ {in_posts=1; path="'$0; next}
/''^  ['"\\047]?\\// && "'$0 !~ /''^  ['"\\047]?\\/posts/ {in_posts=0}
in_posts && /"'^    (get|post|put|patch|delete):/ {gsub(/''^ +/, "", $0); print $0 " " path; count++}
END {print "TOTAL=" count}
'"' public/openapi/openapi.yml" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
post:   '/posts/{id}/comments':
get:   '/posts/{id}/comments':
post:   '/posts/comments/{id}/replies':
get:   '/posts/comments/{id}/replies':
delete:   '/posts/comments/{id}':
post:   '/posts/comments/{id}/like':
delete:   '/posts/comments/{id}/like':
post:   /posts:
post:   '/posts/{id}/publish':
post:   '/posts/{id}/repost':
delete:   '/posts/{id}':
get:   '/posts/{id}':
post:   '/posts/{id}/like':
delete:   '/posts/{id}/like':
get:   '/posts/user/{id}':
TOTAL=15

 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Posts\Application\Post;
     6	
     7	use App\Modules\Posts\Domain\Entity\Post;
     8	use App\Modules\Posts\Domain\Enum\PostStatus;
     9	use App\Shared\Domain\ValueObject\UserId;
    10	
    11	/**
    12	 * Прикладная политика доступа к записи (не доменный инвариант сущности): кому запись видна и над
    13	 * какой записью допустимо действие (лайк/комментарий/репост). Held в Application, потому что это
    14	 * правило сценариев чтения/действий, а не состояние самой записи.
    15	 */
    16	final class PostVisibilityPolicy
    17	{
    18	    /**
    19	     * Запись видна: не удалена и опубликована, либо это черновик её владельца. Заблокированная
    20	     * (модерация) не видна никому.
    21	     */
    22	    public static function isVisibleTo(Post $post, UserId $viewer): bool
    23	    {
    24	        if ($post->deletion->isDeleted()) {
    25	            return false;
    26	        }
    27	
    28	        return match ($post->status) {
    29	            PostStatus::Published => true,
    30	            PostStatus::Draft => $post->userId->equals($viewer),
    31	            PostStatus::Blocked => false,
    32	        };
    33	    }
    34	
    35	    /**
    36	     * Над записью допустимо действие (лайк/комментарий/репост) только если она опубликована и не
    37	     * удалена; черновик и заблокированная — нет.
    38	     */
    39	    public static function isActionable(Post $post): bool
    40	    {
    41	        return !$post->deletion->isDeleted() && $post->status === PostStatus::Published;
    42	    }
    43	}

exec
/bin/zsh -lc "nl -ba tests/Feature/Modules/Posts/Http/GetPostHttpTest.php | sed -n '1,150p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace Tests\Feature\Modules\Posts\Http;
     6	
     7	use App\Modules\Media\Domain\Enum\MediaVisibility;
     8	use App\Modules\Posts\Domain\Enum\PostStatus;
     9	use App\Shared\Domain\ValueObject\UserId;
    10	
    11	final class GetPostHttpTest extends PostsHttpTestCase
    12	{
    13	    public function testReturnsEnrichedPost(): void
    14	    {
    15	        $user = $this->createUser();
    16	        $media = $this->createReadyMedia($user->id, MediaVisibility::Public);
    17	
    18	        $createResponse = $this->authedJson('POST', '/api/v1/posts', $user->id, [
    19	            'text' => 'Запись с вложениями',
    20	            'mediaIds' => [$media->id->value()],
    21	            'tags' => ['yoga'],
    22	        ]);
    23	        $postId = $this->json($createResponse)['data']['id'];
    24	
    25	        // Лайкнем, чтобы проверить флаг likedByMe в чтении.
    26	        $this->authedJson('POST', \sprintf('/api/v1/posts/%s/like', $postId), $user->id)->assertNoContent();
    27	
    28	        $response = $this->authedGet(\sprintf('/api/v1/posts/%s', $postId), $user->id);
    29	
    30	        $response->assertOk();
    31	        $data = $this->json($response)['data'];
    32	        self::assertSame('Запись с вложениями', $data['text']);
    33	        self::assertSame($user->id->value(), $data['author']['userId']);
    34	        self::assertCount(1, $data['media']);
    35	        self::assertSame('yoga', $data['tags'][0]['text']);
    36	        self::assertTrue($data['likedByMe']);
    37	        self::assertSame(1, $data['likesCount']);
    38	    }
    39	
    40	    public function testReturnsOriginalForRepost(): void
    41	    {
    42	        $author = $this->createUser();
    43	        $reposter = $this->createUser();
    44	        $original = $this->persistPost($author->id, PostStatus::Published);
    45	
    46	        $repostResponse = $this->authedJson(
    47	            'POST',
    48	            \sprintf('/api/v1/posts/%s/repost', $original->id->value()),
    49	            $reposter->id,
    50	        );
    51	        $repostId = $this->json($repostResponse)['data']['id'];
    52	
    53	        $response = $this->authedGet(\sprintf('/api/v1/posts/%s', $repostId), $reposter->id);
    54	
    55	        $response->assertOk();
    56	        $data = $this->json($response)['data'];
    57	        self::assertNotNull($data['original']);
    58	        self::assertSame($original->id->value(), $data['original']['id']);
    59	    }
    60	
    61	    public function testReturnsNullOriginalWhenOriginalDeleted(): void
    62	    {
    63	        $author = $this->createUser();
    64	        $reposter = $this->createUser();
    65	        $original = $this->persistPost($author->id, PostStatus::Published);
    66	
    67	        $repostId = $this->json($this->authedJson(
    68	            'POST',
    69	            \sprintf('/api/v1/posts/%s/repost', $original->id->value()),
    70	            $reposter->id,
    71	        ))['data']['id'];
    72	
    73	        $this->authedJson('DELETE', \sprintf('/api/v1/posts/%s', $original->id->value()), $author->id)
    74	            ->assertNoContent();
    75	
    76	        $response = $this->authedGet(\sprintf('/api/v1/posts/%s', $repostId), $reposter->id);
    77	
    78	        $response->assertOk();
    79	        self::assertNull($this->json($response)['data']['original']);
    80	    }
    81	
    82	    public function testForeignDraftReturns404(): void
    83	    {
    84	        $author = $this->createUser();
    85	        $viewer = $this->createUser();
    86	        $post = $this->persistPost($author->id, PostStatus::Draft);
    87	
    88	        $this->authedGet(\sprintf('/api/v1/posts/%s', $post->id->value()), $viewer->id)->assertNotFound();
    89	    }
    90	
    91	    public function testOwnerSeesOwnDraft(): void
    92	    {
    93	        $author = $this->createUser();
    94	        $post = $this->persistPost($author->id, PostStatus::Draft);
    95	
    96	        $this->authedGet(\sprintf('/api/v1/posts/%s', $post->id->value()), $author->id)->assertOk();
    97	    }
    98	
    99	    public function testBlockedReturns404(): void
   100	    {
   101	        $author = $this->createUser();
   102	        $post = $this->persistPost($author->id, PostStatus::Blocked);
   103	
   104	        $this->authedGet(\sprintf('/api/v1/posts/%s', $post->id->value()), $author->id)->assertNotFound();
   105	    }
   106	
   107	    public function testDeletedReturns404(): void
   108	    {
   109	        $author = $this->createUser();
   110	        $post = $this->persistPost($author->id, PostStatus::Published, deleted: true);
   111	
   112	        $this->authedGet(\sprintf('/api/v1/posts/%s', $post->id->value()), $author->id)->assertNotFound();
   113	    }
   114	
   115	    public function testMissingReturns404(): void
   116	    {
   117	        $user = $this->createUser();
   118	
   119	        $this->authedGet(\sprintf('/api/v1/posts/%s', UserId::generate()->value()), $user->id)->assertNotFound();
   120	    }
   121	
   122	    public function testInvalidIdReturns422(): void
   123	    {
   124	        $user = $this->createUser();
   125	
   126	        $this->authedGet('/api/v1/posts/not-a-uuid', $user->id)->assertUnprocessable();
   127	    }
   128	
   129	    public function testRequiresAuthentication(): void
   130	    {
   131	        $author = $this->createUser();
   132	        $post = $this->persistPost($author->id, PostStatus::Published);
   133	
   134	        $this->fakeHttp()->getJson(\sprintf('/api/v1/posts/%s', $post->id->value()))->assertUnauthorized();
   135	    }
   136	}

exec
/bin/zsh -lc "nl -ba app/src/Modules/Posts/Application/Command/LikePost/LikePostHandler.php app/src/Modules/Posts/Application/Command/UnlikePost/UnlikePostHandler.php | sed -n '1,220p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
usage: nl [-p] [-b type] [-d delim] [-f type] [-h type] [-i incr] [-l num]
          [-n format] [-s sep] [-v startnum] [-w width] [file]

exec
/bin/zsh -lc "nl -ba tests/Feature/Modules/Posts/Http/CommentReactionHttpTest.php | sed -n '1,140p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace Tests\Feature\Modules\Posts\Http;
     6	
     7	use App\Modules\Posts\Domain\Enum\PostStatus;
     8	use App\Shared\Domain\ValueObject\UserId;
     9	
    10	final class CommentReactionHttpTest extends PostsHttpTestCase
    11	{
    12	    public function testLikesCommentAndNotifiesAuthor(): void
    13	    {
    14	        $author = $this->createUser();
    15	        $liker = $this->createUser();
    16	        $post = $this->persistPost($author->id, PostStatus::Published);
    17	        $comment = $this->persistComment($author->id, $post);
    18	
    19	        $this->authedJson('POST', \sprintf('/api/v1/posts/comments/%s/like', $comment->id->value()), $liker->id)
    20	            ->assertNoContent();
    21	
    22	        self::assertSame(1, $this->reloadComment($comment)->likesCount->value());
    23	
    24	        $likes = $this->stagedNotifications('posts.comment_like');
    25	        self::assertCount(1, $likes);
    26	        self::assertSame($author->id->value(), $likes[0]->userId);
    27	    }
    28	
    29	    public function testLikeCommentIsIdempotent(): void
    30	    {
    31	        $author = $this->createUser();
    32	        $liker = $this->createUser();
    33	        $post = $this->persistPost($author->id, PostStatus::Published);
    34	        $comment = $this->persistComment($author->id, $post);
    35	
    36	        $this->authedJson('POST', \sprintf('/api/v1/posts/comments/%s/like', $comment->id->value()), $liker->id)->assertNoContent();
    37	        $this->authedJson('POST', \sprintf('/api/v1/posts/comments/%s/like', $comment->id->value()), $liker->id)->assertNoContent();
    38	
    39	        self::assertSame(1, $this->reloadComment($comment)->likesCount->value());
    40	        self::assertCount(1, $this->stagedNotifications('posts.comment_like'));
    41	    }
    42	
    43	    public function testSelfLikeCommentDoesNotNotify(): void
    44	    {
    45	        $user = $this->createUser();
    46	        $post = $this->persistPost($user->id, PostStatus::Published);
    47	        $comment = $this->persistComment($user->id, $post);
    48	
    49	        $this->authedJson('POST', \sprintf('/api/v1/posts/comments/%s/like', $comment->id->value()), $user->id)
    50	            ->assertNoContent();
    51	
    52	        self::assertSame(1, $this->reloadComment($comment)->likesCount->value());
    53	        self::assertCount(0, $this->stagedNotifications('posts.comment_like'));
    54	    }
    55	
    56	    public function testUnlikeComment(): void
    57	    {
    58	        $author = $this->createUser();
    59	        $liker = $this->createUser();
    60	        $post = $this->persistPost($author->id, PostStatus::Published);
    61	        $comment = $this->persistComment($author->id, $post);
    62	
    63	        $this->authedJson('POST', \sprintf('/api/v1/posts/comments/%s/like', $comment->id->value()), $liker->id)->assertNoContent();
    64	        $this->authedJson('DELETE', \sprintf('/api/v1/posts/comments/%s/like', $comment->id->value()), $liker->id)->assertNoContent();
    65	
    66	        self::assertSame(0, $this->reloadComment($comment)->likesCount->value());
    67	        self::assertFalse($this->commentLikeRepository()->existsByCommentAndUser($comment->id, $liker->id));
    68	    }
    69	
    70	    public function testUnlikeWithoutPriorLikeIsNoOp(): void
    71	    {
    72	        $author = $this->createUser();
    73	        $liker = $this->createUser();
    74	        $post = $this->persistPost($author->id, PostStatus::Published);
    75	        $comment = $this->persistComment($author->id, $post);
    76	
    77	        $this->authedJson('DELETE', \sprintf('/api/v1/posts/comments/%s/like', $comment->id->value()), $liker->id)
    78	            ->assertNoContent();
    79	
    80	        self::assertSame(0, $this->reloadComment($comment)->likesCount->value());
    81	    }
    82	
    83	    public function testLikeDeletedCommentReturns404(): void
    84	    {
    85	        $author = $this->createUser();
    86	        $liker = $this->createUser();
    87	        $post = $this->persistPost($author->id, PostStatus::Published);
    88	        $comment = $this->persistComment($author->id, $post);
    89	
    90	        $this->authedJson('DELETE', \sprintf('/api/v1/posts/comments/%s', $comment->id->value()), $author->id)->assertNoContent();
    91	
    92	        $this->authedJson('POST', \sprintf('/api/v1/posts/comments/%s/like', $comment->id->value()), $liker->id)
    93	            ->assertNotFound();
    94	    }
    95	
    96	    public function testLikeMissingCommentReturns404(): void
    97	    {
    98	        $user = $this->createUser();
    99	
   100	        $this->authedJson('POST', \sprintf('/api/v1/posts/comments/%s/like', UserId::generate()->value()), $user->id)
   101	            ->assertNotFound();
   102	    }
   103	
   104	    public function testUnlikeMissingCommentReturns404(): void
   105	    {
   106	        $user = $this->createUser();
   107	
   108	        $this->authedJson('DELETE', \sprintf('/api/v1/posts/comments/%s/like', UserId::generate()->value()), $user->id)
   109	            ->assertNotFound();
   110	    }
   111	
   112	    public function testRequiresAuthentication(): void
   113	    {
   114	        $author = $this->createUser();
   115	        $post = $this->persistPost($author->id, PostStatus::Published);
   116	        $comment = $this->persistComment($author->id, $post);
   117	
   118	        $this->fakeHttp()->postJson(\sprintf('/api/v1/posts/comments/%s/like', $comment->id->value()))
   119	            ->assertUnauthorized();
   120	    }
   121	}

exec
/bin/zsh -lc "nl -ba app/src/Modules/User/Application/Query/GetUserPublicProfiles/GetUserPublicProfilesHandler.php app/src/Modules/Tags/Application/Query/GetTags/GetTagsHandler.php | sed -n '1,180p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
usage: nl [-p] [-b type] [-d delim] [-f type] [-h type] [-i incr] [-l num]
          [-n format] [-s sep] [-v startnum] [-w width] [file]

exec
/bin/zsh -lc "nl -ba app/src/Modules/Posts/Application/Command/LikePost/LikePostHandler.php | sed -n '1,180p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Posts\Application\Command\LikePost;
     6	
     7	use App\Modules\Posts\Application\Notification\PostNotificationType;
     8	use App\Modules\Posts\Application\Post\PostContentComposer;
     9	use App\Modules\Posts\Application\Post\PostVisibilityPolicy;
    10	use App\Modules\Posts\Domain\Entity\PostLike;
    11	use App\Modules\Posts\Domain\ValueObject\PostId;
    12	use App\Modules\Posts\Repository\PostLikeRepository;
    13	use App\Modules\Posts\Repository\PostRepository;
    14	use App\Shared\Domain\Exception\NotFoundException;
    15	use App\Shared\Domain\ValueObject\UserId;
    16	use Cycle\ORM\EntityManagerInterface;
    17	use GianTiaga\SpiralCqrs\Attribute\LogOperation;
    18	use GianTiaga\SpiralCqrs\Attribute\Transactional;
    19	use Psr\Log\LoggerInterface;
    20	
    21	/**
    22	 * Лайк записи. Невидимая/недоступная запись (черновик/удалена/заблокирована) -> 404. Идемпотентно:
    23	 * повторный лайк — no-op без второго инкремента и уведомления. Автору записи (кроме self)
    24	 * стейджится post_like.
    25	 */
    26	final readonly class LikePostHandler
    27	{
    28	    public function __construct(
    29	        private PostRepository $postRepository,
    30	        private PostLikeRepository $postLikeRepository,
    31	        private PostContentComposer $composer,
    32	        private EntityManagerInterface $entityManager,
    33	        private LoggerInterface $logger,
    34	    ) {}
    35	
    36	    #[Transactional]
    37	    #[LogOperation]
    38	    public function handle(LikePostCommand $command): void
    39	    {
    40	        $userId = UserId::fromString($command->authUserId);
    41	
    42	        $post = $this->postRepository->findById(PostId::fromString($command->postId))
    43	            ?? throw new NotFoundException('app.posts.not_found');
    44	
    45	        if (!PostVisibilityPolicy::isActionable($post)) {
    46	            throw new NotFoundException('app.posts.not_found');
    47	        }
    48	
    49	        if ($this->postLikeRepository->existsByPostAndUser(postId: $post->id, userId: $userId)) {
    50	            $this->logger->debug(message: 'Повторный лайк записи — no-op.', context: ['postId' => $post->id->value()]);
    51	
    52	            return;
    53	        }
    54	
    55	        $this->entityManager->persist(PostLike::create(postId: $post->id, userId: $userId));
    56	        $post->incrementLikes();
    57	        $this->entityManager->persist($post);
    58	
    59	        $this->composer->notifyPostAuthor(
    60	            type: PostNotificationType::PostLike,
    61	            postAuthor: $post->userId,
    62	            actorUserId: $command->authUserId,
    63	            postId: $post->id->value(),
    64	        );
    65	
    66	        $this->entityManager->run();
    67	    }
    68	}

exec
/bin/zsh -lc "nl -ba app/src/Modules/Posts/Application/Command/UnlikePost/UnlikePostHandler.php | sed -n '1,180p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Posts\Application\Command\UnlikePost;
     6	
     7	use App\Modules\Posts\Domain\ValueObject\PostId;
     8	use App\Modules\Posts\Repository\PostLikeRepository;
     9	use App\Modules\Posts\Repository\PostRepository;
    10	use App\Shared\Domain\Exception\NotFoundException;
    11	use App\Shared\Domain\ValueObject\UserId;
    12	use Cycle\ORM\EntityManagerInterface;
    13	use GianTiaga\SpiralCqrs\Attribute\LogOperation;
    14	use GianTiaga\SpiralCqrs\Attribute\Transactional;
    15	use Psr\Log\LoggerInterface;
    16	
    17	/**
    18	 * Снятие лайка с записи. Несуществующая запись -> 404. Идемпотентно: снятие отсутствующего лайка —
    19	 * no-op; счётчик не уходит ниже нуля.
    20	 */
    21	final readonly class UnlikePostHandler
    22	{
    23	    public function __construct(
    24	        private PostRepository $postRepository,
    25	        private PostLikeRepository $postLikeRepository,
    26	        private EntityManagerInterface $entityManager,
    27	        private LoggerInterface $logger,
    28	    ) {}
    29	
    30	    #[Transactional]
    31	    #[LogOperation]
    32	    public function handle(UnlikePostCommand $command): void
    33	    {
    34	        $userId = UserId::fromString($command->authUserId);
    35	
    36	        $post = $this->postRepository->findById(PostId::fromString($command->postId))
    37	            ?? throw new NotFoundException('app.posts.not_found');
    38	
    39	        $like = $this->postLikeRepository->findByPostAndUser(postId: $post->id, userId: $userId);
    40	
    41	        if ($like === null) {
    42	            $this->logger->debug(message: 'Снятие отсутствующего лайка записи — no-op.', context: ['postId' => $post->id->value()]);
    43	
    44	            return;
    45	        }
    46	
    47	        $this->entityManager->delete($like);
    48	
    49	        if ($post->likesCount->value() > 0) {
    50	            $post->decrementLikes();
    51	            $this->entityManager->persist($post);
    52	        }
    53	
    54	        $this->entityManager->run();
    55	    }
    56	}

exec
/bin/zsh -lc "nl -ba app/src/Modules/Tags/Application/Query/GetTags/GetTagsHandler.php | sed -n '1,140p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Tags\Application\Query\GetTags;
     6	
     7	use App\Modules\Tags\Application\Dto\TagTextCollection;
     8	use App\Modules\Tags\Repository\TagRepository;
     9	use App\Shared\Domain\ValueObject\TagId;
    10	use GianTiaga\SpiralCqrs\Attribute\LogOperation;
    11	
    12	/**
    13	 * Возвращает карту «идентификатор тега -> текст» по списку идентификаторов. Несуществующие
    14	 * идентификаторы просто отсутствуют в результате (их не было среди найденных тегов).
    15	 */
    16	final readonly class GetTagsHandler
    17	{
    18	    public function __construct(
    19	        private TagRepository $tagRepository,
    20	    ) {}
    21	
    22	    #[LogOperation]
    23	    public function handle(GetTagsQuery $query): TagTextCollection
    24	    {
    25	        $tagIds = \array_map(
    26	            static fn(string $tagId): TagId => TagId::fromString($tagId),
    27	            $query->tagIds,
    28	        );
    29	
    30	        $map = [];
    31	
    32	        foreach ($this->tagRepository->findByIds(...$tagIds) as $tag) {
    33	            $map[$tag->id->value()] = $tag->text->value();
    34	        }
    35	
    36	        return new TagTextCollection($map);
    37	    }
    38	}

exec
/bin/zsh -lc "nl -ba app/src/Modules/User/Application/Query/GetUserPublicProfiles/GetUserPublicProfilesHandler.php | sed -n '1,140p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\User\Application\Query\GetUserPublicProfiles;
     6	
     7	use App\Modules\User\Application\Dto\UserPublicProfileCollection;
     8	use App\Modules\User\Application\Dto\UserPublicProfileView;
     9	use App\Modules\User\Application\Profile\UserPublicProfileAssembler;
    10	use App\Modules\User\Domain\Entity\User;
    11	use App\Modules\User\Repository\UserRepository;
    12	use App\Shared\Domain\ValueObject\UserId;
    13	use GianTiaga\SpiralCqrs\Attribute\LogOperation;
    14	
    15	/**
    16	 * Публичные профили нескольких пользователей (авторы ленты/комментариев, проверка упоминаний).
    17	 * Несуществующие идентификаторы просто отсутствуют в результате — вызывающий сам сверяет
    18	 * запрошенные и найденные, чтобы при необходимости вернуть 422 на несуществующее упоминание.
    19	 */
    20	final readonly class GetUserPublicProfilesHandler
    21	{
    22	    public function __construct(
    23	        private UserRepository $userRepository,
    24	        private UserPublicProfileAssembler $assembler,
    25	    ) {}
    26	
    27	    #[LogOperation]
    28	    public function handle(GetUserPublicProfilesQuery $query): UserPublicProfileCollection
    29	    {
    30	        $userIds = \array_map(
    31	            static fn(string $userId): UserId => UserId::fromString($userId),
    32	            $query->userIds,
    33	        );
    34	
    35	        $profiles = \array_map(
    36	            fn(User $user): UserPublicProfileView => $this->assembler->fromUser($user),
    37	            $this->userRepository->findByIds(...$userIds)->all(),
    38	        );
    39	
    40	        return new UserPublicProfileCollection($profiles);
    41	    }
    42	}

exec
/bin/zsh -lc "nl -ba app/src/Modules/Tags/Application/Command/ResolveTags/ResolveTagsHandler.php | sed -n '1,220p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Tags\Application\Command\ResolveTags;
     6	
     7	use App\Modules\Tags\Domain\Entity\Tag;
     8	use App\Modules\Tags\Domain\ValueObject\TagText;
     9	use App\Modules\Tags\Repository\TagRepository;
    10	use App\Shared\Domain\ValueObject\UserId;
    11	use Cycle\ORM\EntityManagerInterface;
    12	use GianTiaga\SpiralCqrs\Attribute\LogOperation;
    13	use GianTiaga\SpiralCqrs\Attribute\Transactional;
    14	use Psr\Log\LoggerInterface;
    15	
    16	/**
    17	 * Превращает набор текстов в идентификаторы тегов (find-or-create). check-then-act, не insert+catch:
    18	 * существующие теги переиспользуются, недостающие создаются в той же транзакции одним run(). Редкий
    19	 * конкурентный дубль по уникальному tags.text допустим как 500 (rules.md). Результат пересобирается в
    20	 * порядке входных текстов с дедупликацией: повтор текста в одном запросе не плодит дублей.
    21	 */
    22	final readonly class ResolveTagsHandler
    23	{
    24	    public function __construct(
    25	        private TagRepository $tagRepository,
    26	        private EntityManagerInterface $entityManager,
    27	        private LoggerInterface $logger,
    28	    ) {}
    29	
    30	    #[Transactional]
    31	    #[LogOperation]
    32	    public function handle(ResolveTagsCommand $command): ResolveTagsResult
    33	    {
    34	        $creatorUserId = UserId::fromString($command->creatorUserId);
    35	
    36	        // Уникальные тексты в порядке первого появления (ключ — нормализованное значение TagText).
    37	        $orderedTexts = [];
    38	
    39	        foreach ($command->texts as $rawText) {
    40	            $text = TagText::fromString($rawText);
    41	            $orderedTexts[$text->value()] ??= $text;
    42	        }
    43	
    44	        if ($orderedTexts === []) {
    45	            return new ResolveTagsResult(tagIds: []);
    46	        }
    47	
    48	        $existingByText = [];
    49	
    50	        foreach ($this->tagRepository->findByTexts(...\array_values($orderedTexts)) as $tag) {
    51	            $existingByText[$tag->text->value()] = $tag;
    52	        }
    53	
    54	        $tagIds = [];
    55	        $createdCount = 0;
    56	
    57	        foreach ($orderedTexts as $textValue => $text) {
    58	            $tag = $existingByText[$textValue] ?? null;
    59	
    60	            if ($tag === null) {
    61	                $tag = Tag::create(text: $text, createdBy: $creatorUserId);
    62	                $this->entityManager->persist($tag);
    63	                $createdCount++;
    64	            }
    65	
    66	            $tagIds[] = $tag->id->value();
    67	        }
    68	
    69	        if ($createdCount > 0) {
    70	            $this->entityManager->run();
    71	        }
    72	
    73	        $this->logger->debug(message: 'Теги разрешены.', context: [
    74	            'requested' => \count($orderedTexts),
    75	            'created' => $createdCount,
    76	        ]);
    77	
    78	        return new ResolveTagsResult(tagIds: $tagIds);
    79	    }
    80	}

exec
/bin/zsh -lc "nl -ba tests/Feature/Modules/Posts/Http/CommentHttpTest.php | sed -n '140,310p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
   140	        self::assertSame(1, $this->reloadComment($parent)->repliesCount->value());
   141	
   142	        $replies = $this->stagedNotifications('posts.comment_reply');
   143	        self::assertCount(1, $replies);
   144	        self::assertSame($parentAuthor->id->value(), $replies[0]->userId);
   145	
   146	        // Автор записи на ответ post_commented НЕ получает (решение 14).
   147	        self::assertCount(0, $this->stagedNotifications('posts.post_commented'));
   148	    }
   149	
   150	    public function testReplyToDeletedCommentReturns404(): void
   151	    {
   152	        $author = $this->createUser();
   153	        $replier = $this->createUser();
   154	        $post = $this->persistPost($author->id, PostStatus::Published);
   155	        $parent = $this->persistComment($author->id, $post);
   156	
   157	        $this->authedJson('DELETE', \sprintf('/api/v1/posts/comments/%s', $parent->id->value()), $author->id)
   158	            ->assertNoContent();
   159	
   160	        $this->authedJson(
   161	            'POST',
   162	            \sprintf('/api/v1/posts/comments/%s/replies', $parent->id->value()),
   163	            $replier->id,
   164	            ['text' => 'Ответ'],
   165	        )->assertNotFound();
   166	    }
   167	
   168	    public function testDeletesTopLevelCommentDecrementsPost(): void
   169	    {
   170	        $author = $this->createUser();
   171	        $post = $this->persistPost($author->id, PostStatus::Published);
   172	        $comment = $this->persistComment($author->id, $post);
   173	
   174	        self::assertSame(1, $this->reloadPost($post)->commentsCount->value());
   175	
   176	        $this->authedJson('DELETE', \sprintf('/api/v1/posts/comments/%s', $comment->id->value()), $author->id)
   177	            ->assertNoContent();
   178	
   179	        self::assertSame(0, $this->reloadPost($post)->commentsCount->value());
   180	        self::assertTrue($this->reloadComment($comment)->isDeleted());
   181	    }
   182	
   183	    public function testDeletesReplyDecrementsParent(): void
   184	    {
   185	        $author = $this->createUser();
   186	        $post = $this->persistPost($author->id, PostStatus::Published);
   187	        $parent = $this->persistComment($author->id, $post);
   188	        $reply = $this->persistComment($author->id, $post, $parent);
   189	
   190	        self::assertSame(1, $this->reloadComment($parent)->repliesCount->value());
   191	
   192	        $this->authedJson('DELETE', \sprintf('/api/v1/posts/comments/%s', $reply->id->value()), $author->id)
   193	            ->assertNoContent();
   194	
   195	        self::assertSame(0, $this->reloadComment($parent)->repliesCount->value());
   196	    }
   197	
   198	    public function testRepeatedDeleteIsIdempotent(): void
   199	    {
   200	        $author = $this->createUser();
   201	        $post = $this->persistPost($author->id, PostStatus::Published);
   202	        $comment = $this->persistComment($author->id, $post);
   203	
   204	        $this->authedJson('DELETE', \sprintf('/api/v1/posts/comments/%s', $comment->id->value()), $author->id)->assertNoContent();
   205	        $this->authedJson('DELETE', \sprintf('/api/v1/posts/comments/%s', $comment->id->value()), $author->id)->assertNoContent();
   206	
   207	        self::assertSame(0, $this->reloadPost($post)->commentsCount->value());
   208	    }
   209	
   210	    public function testRejectsDeleteByNonOwner(): void
   211	    {
   212	        $author = $this->createUser();
   213	        $stranger = $this->createUser();
   214	        $post = $this->persistPost($author->id, PostStatus::Published);
   215	        $comment = $this->persistComment($author->id, $post);
   216	
   217	        $this->authedJson('DELETE', \sprintf('/api/v1/posts/comments/%s', $comment->id->value()), $stranger->id)
   218	            ->assertStatus(403);
   219	    }
   220	
   221	    public function testRejectsDeleteOfMissingComment(): void
   222	    {
   223	        $user = $this->createUser();
   224	
   225	        $this->authedJson('DELETE', \sprintf('/api/v1/posts/comments/%s', UserId::generate()->value()), $user->id)
   226	            ->assertNotFound();
   227	    }
   228	
   229	    public function testRejectsInvalidCommentId(): void
   230	    {
   231	        $user = $this->createUser();
   232	
   233	        $this->authedJson('POST', '/api/v1/posts/comments/not-a-uuid/replies', $user->id, ['text' => 'x'])
   234	            ->assertUnprocessable();
   235	    }
   236	
   237	    public function testCommentOnMissingPostReturns404(): void
   238	    {
   239	        $user = $this->createUser();
   240	
   241	        $this->authedJson(
   242	            'POST',
   243	            \sprintf('/api/v1/posts/%s/comments', UserId::generate()->value()),
   244	            $user->id,
   245	            ['text' => 'Текст'],
   246	        )->assertNotFound();
   247	    }
   248	
   249	    public function testRejectsNonexistentMentionInComment(): void
   250	    {
   251	        $author = $this->createUser();
   252	        $commenter = $this->createUser();
   253	        $post = $this->persistPost($author->id, PostStatus::Published);
   254	
   255	        $this->authedJson(
   256	            'POST',
   257	            \sprintf('/api/v1/posts/%s/comments', $post->id->value()),
   258	            $commenter->id,
   259	            ['text' => 'Текст', 'mentions' => [UserId::generate()->value()]],
   260	        )->assertUnprocessable();
   261	    }
   262	
   263	    public function testReplyToMissingCommentReturns404(): void
   264	    {
   265	        $user = $this->createUser();
   266	
   267	        $this->authedJson(
   268	            'POST',
   269	            \sprintf('/api/v1/posts/comments/%s/replies', UserId::generate()->value()),
   270	            $user->id,
   271	            ['text' => 'Ответ'],
   272	        )->assertNotFound();
   273	    }
   274	
   275	    public function testReplyOnBlockedPostReturns404(): void
   276	    {
   277	        $author = $this->createUser();
   278	        $replier = $this->createUser();
   279	        $post = $this->persistPost($author->id, PostStatus::Blocked);
   280	        $parent = $this->persistComment($author->id, $post);
   281	
   282	        $this->authedJson(
   283	            'POST',
   284	            \sprintf('/api/v1/posts/comments/%s/replies', $parent->id->value()),
   285	            $replier->id,
   286	            ['text' => 'Ответ'],
   287	        )->assertNotFound();
   288	    }
   289	}

codex
Нашёл более серьёзный пропуск в ревью: OpenAPI для `PostResource` описывает `media` и `tags` как массив строк, а реальные ресурсы отдают объекты. Это уже не спорная мелочь плана, а публичный контракт, который может сломать клиента по сгенерированной схеме.
exec
/bin/zsh -lc "nl -ba app/src/Modules/Posts/Presentation/Http/Resource/PostMediaItemResource.php | sed -n '1,160p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Posts\Presentation\Http\Resource;
     6	
     7	use App\Modules\Posts\Application\View\PostMediaItemView;
     8	use App\Shared\Presentation\Http\Resource\AbstractResource;
     9	
    10	final readonly class PostMediaItemResource extends AbstractResource
    11	{
    12	    public function __construct(
    13	        public string $mediaId,
    14	        public string $url,
    15	        public int $position,
    16	    ) {}
    17	
    18	    public static function fromView(PostMediaItemView $media): self
    19	    {
    20	        return new self(
    21	            mediaId: $media->mediaId,
    22	            url: $media->url,
    23	            position: $media->position,
    24	        );
    25	    }
    26	}

exec
/bin/zsh -lc "nl -ba app/src/Modules/Posts/Presentation/Http/Resource/TagResource.php | sed -n '1,160p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
exec
/bin/zsh -lc "nl -ba app/src/Modules/Posts/Application/View/PostView.php app/src/Modules/Posts/Application/View/PostMediaItemView.php app/src/Modules/Posts/Application/View/TagView.php | sed -n '1,220p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Posts\Presentation\Http\Resource;
     6	
     7	use App\Modules\Posts\Application\View\TagView;
     8	use App\Shared\Presentation\Http\Resource\AbstractResource;
     9	
    10	final readonly class TagResource extends AbstractResource
    11	{
    12	    public function __construct(
    13	        public string $id,
    14	        public string $text,
    15	    ) {}
    16	
    17	    public static function fromView(TagView $tag): self
    18	    {
    19	        return new self(id: $tag->id, text: $tag->text);
    20	    }
    21	}

 succeeded in 0ms:
usage: nl [-p] [-b type] [-d delim] [-f type] [-h type] [-i incr] [-l num]
          [-n format] [-s sep] [-v startnum] [-w width] [file]

exec
/bin/zsh -lc "nl -ba app/src/Modules/Posts/Presentation/Http/Resource/PostResource.php | sed -n '1,220p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Posts\Presentation\Http\Resource;
     6	
     7	use App\Modules\Posts\Application\View\PostMediaItemView;
     8	use App\Modules\Posts\Application\View\PostView;
     9	use App\Modules\Posts\Application\View\TagView;
    10	use App\Shared\Presentation\Http\Resource\AbstractResource;
    11	
    12	final readonly class PostResource extends AbstractResource
    13	{
    14	    /**
    15	     * @param list<PostMediaItemResource> $media
    16	     * @param list<TagResource> $tags
    17	     */
    18	    public function __construct(
    19	        public string $id,
    20	        public string|null $text,
    21	        public string $status,
    22	        public string $attachmentType,
    23	        public int $likesCount,
    24	        public int $repostsCount,
    25	        public int $commentsCount,
    26	        public bool $likedByMe,
    27	        public string $createdAt,
    28	        public AuthorResource $author,
    29	        public array $media,
    30	        public array $tags,
    31	        public PostResource|null $original,
    32	    ) {}
    33	
    34	    public static function fromView(PostView $post): self
    35	    {
    36	        return new self(
    37	            id: $post->id,
    38	            text: $post->text,
    39	            status: $post->status,
    40	            attachmentType: $post->attachmentType,
    41	            likesCount: $post->likesCount,
    42	            repostsCount: $post->repostsCount,
    43	            commentsCount: $post->commentsCount,
    44	            likedByMe: $post->likedByMe,
    45	            createdAt: $post->createdAt,
    46	            author: AuthorResource::fromView($post->author),
    47	            media: \array_map(
    48	                static fn(PostMediaItemView $media): PostMediaItemResource => PostMediaItemResource::fromView($media),
    49	                $post->media,
    50	            ),
    51	            tags: \array_map(
    52	                static fn(TagView $tag): TagResource => TagResource::fromView($tag),
    53	                $post->tags,
    54	            ),
    55	            original: $post->original !== null ? self::fromView($post->original) : null,
    56	        );
    57	    }
    58	}

exec
/bin/zsh -lc 'rg "PostMediaItemResource|TagResource|media:|tags:" public/openapi/openapi.yml app/src/Modules/Posts/Presentation -n' in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
public/openapi/openapi.yml:384:          application/json: { schema: { type: object, properties: { text: { type: [string, 'null'] }, draft: { type: boolean }, mediaIds: { type: array, items: { type: string } }, tags: { type: array, items: { type: string } }, mentions: { type: array, items: { type: string } } } } }
public/openapi/openapi.yml:422:          application/json: { schema: { type: object, properties: { text: { type: [string, 'null'] }, mediaIds: { type: array, items: { type: string } }, tags: { type: array, items: { type: string } }, mentions: { type: array, items: { type: string } } } } }
public/openapi/openapi.yml:691:        media:
public/openapi/openapi.yml:694:        tags:
app/src/Modules/Posts/Presentation/Http/Resource/PostMediaItemResource.php:10:final readonly class PostMediaItemResource extends AbstractResource
app/src/Modules/Posts/Presentation/Http/Resource/PostResource.php:15:     * @param list<PostMediaItemResource> $media
app/src/Modules/Posts/Presentation/Http/Resource/PostResource.php:16:     * @param list<TagResource> $tags
app/src/Modules/Posts/Presentation/Http/Resource/PostResource.php:47:            media: \array_map(
app/src/Modules/Posts/Presentation/Http/Resource/PostResource.php:48:                static fn(PostMediaItemView $media): PostMediaItemResource => PostMediaItemResource::fromView($media),
app/src/Modules/Posts/Presentation/Http/Resource/PostResource.php:51:            tags: \array_map(
app/src/Modules/Posts/Presentation/Http/Resource/PostResource.php:52:                static fn(TagView $tag): TagResource => TagResource::fromView($tag),
app/src/Modules/Posts/Presentation/Http/Resource/TagResource.php:10:final readonly class TagResource extends AbstractResource
app/src/Modules/Posts/Presentation/Http/Controller/PostController.php:75:                tags: $createPostFilter->tags,
app/src/Modules/Posts/Presentation/Http/Controller/PostController.php:147:                tags: $repostPostFilter->tags,

exec
/bin/zsh -lc "sed -n '640,725p' public/openapi/openapi.yml" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
          oneOf: [{ $ref: '#/components/schemas/NotificationActorResource' }, { type: 'null' }]
        read:
          type: boolean
        createdAt:
          type: string
      required:
        - id
        - type
        - title
        - body
        - read
        - createdAt
    NotificationSettingResource:
      type: object
      properties:
        type:
          type: string
        channel:
          type: string
        enabled:
          type: boolean
        default:
          type: boolean
      required:
        - type
        - channel
        - enabled
        - default
    PostResource:
      type: object
      properties:
        id:
          type: string
        text:
          type: [string, 'null']
        status:
          type: string
        attachmentType:
          type: string
        likesCount:
          type: integer
        repostsCount:
          type: integer
        commentsCount:
          type: integer
        likedByMe:
          type: boolean
        createdAt:
          type: string
        author:
          $ref: '#/components/schemas/AuthorResource'
        media:
          type: array
          items: { type: string }
        tags:
          type: array
          items: { type: string }
        original:
          oneOf: [{ $ref: '#/components/schemas/PostResource' }, { type: 'null' }]
      required:
        - id
        - status
        - attachmentType
        - likesCount
        - repostsCount
        - commentsCount
        - likedByMe
        - createdAt
        - author
        - media
        - tags
    SessionResource:
      type: object
      properties:
        id:
          type: string
        createdAt:
          type: string
        expiresAt:
          type: string
        ip:
          type: [string, 'null']
        device:
          type: [string, 'null']
        current:
          type: boolean

exec
/bin/zsh -lc "nl -ba tests/Feature/Modules/User/Application/GetUserPublicProfilesHandlerTest.php | sed -n '1,180p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace Tests\Feature\Modules\User\Application;
     6	
     7	use App\Modules\User\Application\Dto\UserPublicProfileView;
     8	use App\Modules\User\Application\Query\GetUserPublicProfiles\GetUserPublicProfilesHandler;
     9	use App\Modules\User\Application\Query\GetUserPublicProfiles\GetUserPublicProfilesQuery;
    10	use App\Shared\Domain\ValueObject\UserId;
    11	
    12	final class GetUserPublicProfilesHandlerTest extends UserApplicationTestCase
    13	{
    14	    public function testReturnsProfilesForFoundUsersExcludingMissing(): void
    15	    {
    16	        $first = $this->persistUser();
    17	        $second = $this->persistUser();
    18	
    19	        $profiles = $this->handler()->handle(new GetUserPublicProfilesQuery(
    20	            userIds: [$first->id->value(), UserId::generate()->value(), $second->id->value()],
    21	        ));
    22	
    23	        self::assertCount(2, $profiles);
    24	
    25	        $userIds = $profiles
    26	            ->map(static fn(UserPublicProfileView $profile): string => $profile->userId)
    27	            ->all();
    28	
    29	        self::assertContains($first->id->value(), $userIds);
    30	        self::assertContains($second->id->value(), $userIds);
    31	    }
    32	
    33	    public function testReturnsEmptyCollectionForEmptyInput(): void
    34	    {
    35	        $profiles = $this->handler()->handle(new GetUserPublicProfilesQuery(userIds: []));
    36	
    37	        self::assertCount(0, $profiles);
    38	    }
    39	
    40	    private function handler(): GetUserPublicProfilesHandler
    41	    {
    42	        return new GetUserPublicProfilesHandler(
    43	            userRepository: $this->userRepository(),
    44	            assembler: $this->profileHandlerAssembler(),
    45	        );
    46	    }
    47	}

exec
/bin/zsh -lc "nl -ba app/src/Modules/User/Repository/UserRepository.php | sed -n '1,110p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\User\Repository;
     6	
     7	use App\Modules\User\Domain\Collection\UserCollection;
     8	use App\Modules\User\Domain\Entity\User;
     9	use App\Modules\User\Domain\ValueObject\Email;
    10	use App\Modules\User\Domain\ValueObject\UserNickname;
    11	use App\Shared\Domain\ValueObject\UserId;
    12	use Cycle\Database\Injection\Parameter;
    13	use Cycle\ORM\Select\Repository;
    14	
    15	/**
    16	 * @extends Repository<User>
    17	 */
    18	final class UserRepository extends Repository
    19	{
    20	    public function findById(UserId $userId): User|null
    21	    {
    22	        return $this->findByPK($userId->value());
    23	    }
    24	
    25	    public function findByIds(UserId ...$userIds): UserCollection
    26	    {
    27	        if ($userIds === []) {
    28	            return new UserCollection();
    29	        }
    30	
    31	        return new UserCollection(
    32	            $this->select()
    33	                ->where('id', 'in', new Parameter(\array_map(
    34	                    static fn(UserId $userId): string => $userId->value(),
    35	                    $userIds,
    36	                )))
    37	                ->fetchAll(),
    38	        );
    39	    }
    40	
    41	    public function findByEmail(Email $email): User|null
    42	    {
    43	        return $this->findOne(['email' => $email->value()]);
    44	    }
    45	
    46	    public function findByNickname(UserNickname $nickname): User|null
    47	    {
    48	        return $this->findOne(['nickname' => $nickname->value()]);
    49	    }
    50	
    51	    public function existsByEmail(Email $email): bool
    52	    {
    53	        return $this->findByEmail($email) !== null;
    54	    }
    55	
    56	    public function existsByNickname(UserNickname $nickname): bool
    57	    {
    58	        return $this->findByNickname($nickname) !== null;
    59	    }
    60	}

exec
/bin/zsh -lc "nl -ba tests/Feature/Modules/Tags/Application/GetTagsHandlerTest.php tests/Feature/Modules/Tags/Application/ResolveTagsHandlerTest.php | sed -n '1,260p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
usage: nl [-p] [-b type] [-d delim] [-f type] [-h type] [-i incr] [-l num]
          [-n format] [-s sep] [-v startnum] [-w width] [file]

exec
/bin/zsh -lc "nl -ba app/src/Shared/Infrastructure/Configuration/User/UserConfig.php app/config/user.php tests/Kernel/Shared/Infrastructure/Configuration/UserConfigTest.php | sed -n '1,220p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
usage: nl [-p] [-b type] [-d delim] [-f type] [-h type] [-i incr] [-l num]
          [-n format] [-s sep] [-v startnum] [-w width] [file]

exec
/bin/zsh -lc "rg \"method\\('.*'\\)|createMock\\(|expects\\(\\"'$this->any'"\\(\\)\" tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php tests/Feature/Modules/User/Application/GetUserPublicProfileHandlerTest.php tests/Feature/Modules/User/Application/GetUserPublicProfilesHandlerTest.php tests/Kernel/Shared/Infrastructure/Configuration/UserConfigTest.php -n" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
tests/Kernel/Shared/Infrastructure/Configuration/UserConfigTest.php:51:            ->method('getConfig')
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:45:        $fileService = $this->createMock(MediaFileServiceContract::class);
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:46:        $fileService->method('publicUrl')->willReturn('http://minio/media-public/object');
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:47:        $fileService->expects(self::never())->method('presignGet');
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:64:        $fileService->method('presignGet')->willReturn('http://minio/signed');

exec
/bin/zsh -lc 'rg "make qa|make test|make phpstan|зел" docs/executions/2026-06-19_12-51_posts-application-presentation.md docs/reviews/2026-06-19_16-02_posts-application-presentation.md -n' in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
docs/executions/2026-06-19_12-51_posts-application-presentation.md:27:- Тесты HTTP: `DatabaseTestCase` (откат) + инъекция `authUserId` (auth-middleware его не затирает) + `RecordingOutboxEventStore` для проверки стейджинга + стаб `MediaFileServiceContract`. 35 тестов зелёные, PHPStan зелёный.
docs/executions/2026-06-19_12-51_posts-application-presentation.md:35:- Дедуп уведомлений на действие: на одного получателя одно уведомление с приоритетом mention > comment_reply > post_commented; self-получатель исключается в `PostNotifier`. 30 HTTP-тестов зелёные, PHPStan зелёный.
docs/executions/2026-06-19_12-51_posts-application-presentation.md:53:- `make qa` (стиль + `make phpstan` + покрытие) — зелёный. `make phpstan` — без ошибок. Тесты: 1103 зелёных. Покрытие всего проекта — 100.00% (порог 100%). 1 PHPUnit Notice — предсуществующий, не связан с изменениями.
docs/executions/2026-06-19_12-51_posts-application-presentation.md:59:- Фаза 1+2: `make phpstan` зелёный; новые тесты (31) зелёные.
docs/reviews/2026-06-19_16-02_posts-application-presentation.md:18:каждый роут, `make qa` зелёный. Снижение оценки — за один реальный риск падения чтения ленты в 500

exec
/bin/zsh -lc "nl -ba app/src/Modules/User/Application/Dto/UserPublicProfileCollection.php | sed -n '1,100p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\User\Application\Dto;
     6	
     7	use Illuminate\Support\Collection;
     8	
     9	/**
    10	 * @extends Collection<int, UserPublicProfileView>
    11	 */
    12	final class UserPublicProfileCollection extends Collection {}

codex
Ещё один плановый пропуск: батч `GetUserPublicProfiles` должен был проверяться на порядок, а тест проверяет только наличие двух пользователей. Репозиторий делает `where in` без сортировки, так что порядок ответа фактически не гарантирован.
exec
/bin/zsh -lc "nl -ba app/config/user.php | sed -n '1,80p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	/**
     6	 * Конфигурация модуля User.
     7	 *
     8	 * defaultAvatarUrl — ссылка на аватар по умолчанию, которую публичный профиль отдаёт, когда у
     9	 * пользователя нет аватара или его медиа недоступно. Значение зависит от окружения/CDN; не может
    10	 * быть пустым, иначе снимок автора уведомления (NotificationActor) упал бы с ошибкой.
    11	 */
    12	return [
    13	    'defaultAvatarUrl' => (string) \env('USER_DEFAULT_AVATAR_URL', 'https://cdn.yogaloka.app/avatars/default.png'),
    14	];

exec
/bin/zsh -lc "nl -ba app/src/Shared/Infrastructure/Configuration/User/UserConfig.php | sed -n '1,140p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Shared\Infrastructure\Configuration\User;
     6	
     7	use App\Shared\Infrastructure\Configuration\TypedConfig;
     8	use App\Shared\Infrastructure\Exception\InvalidConfigValueException;
     9	
    10	final readonly class UserConfig implements TypedConfig
    11	{
    12	    public static function configName(): string
    13	    {
    14	        return 'user';
    15	    }
    16	
    17	    public function __construct(
    18	        public string $defaultAvatarUrl,
    19	    ) {
    20	        // Дефолтный URL аватара обязан быть непустым: публичный профиль подставляет его, когда у
    21	        // пользователя нет аватара, а NotificationActor::of() бросает на пустой avatarUrl.
    22	        if (\trim($defaultAvatarUrl) === '') {
    23	            throw new InvalidConfigValueException(
    24	                path: 'user.defaultAvatarUrl',
    25	                expected: 'непустой URL аватара по умолчанию',
    26	                actual: $defaultAvatarUrl,
    27	            );
    28	        }
    29	    }
    30	}

exec
/bin/zsh -lc "nl -ba tests/Kernel/Shared/Infrastructure/Configuration/UserConfigTest.php | sed -n '1,120p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace Tests\Kernel\Shared\Infrastructure\Configuration;
     6	
     7	use App\Shared\Infrastructure\Configuration\Mapping\ConfigMapper;
     8	use App\Shared\Infrastructure\Configuration\User\UserConfig;
     9	use App\Shared\Infrastructure\Exception\InvalidConfigValueException;
    10	use CuyZ\Valinor\Mapper\Configurator\ConvertKeysToCamelCase;
    11	use CuyZ\Valinor\MapperBuilder;
    12	use CuyZ\Valinor\Normalizer\Format;
    13	use CuyZ\Valinor\NormalizerBuilder;
    14	use Spiral\Config\ConfiguratorInterface;
    15	use Tests\TestCase;
    16	
    17	final class UserConfigTest extends TestCase
    18	{
    19	    public function testRealUserConfigIsMappedFromContainer(): void
    20	    {
    21	        $userConfig = $this->getContainer()->get(UserConfig::class);
    22	
    23	        self::assertNotSame('', $userConfig->defaultAvatarUrl);
    24	    }
    25	
    26	    public function testMapsUserConfigSection(): void
    27	    {
    28	        $userConfig = $this->mapperFor([
    29	            'defaultAvatarUrl' => 'https://cdn.example/default.png',
    30	        ])->map(section: UserConfig::configName(), targetClass: UserConfig::class);
    31	
    32	        self::assertSame('user', UserConfig::configName());
    33	        self::assertSame('https://cdn.example/default.png', $userConfig->defaultAvatarUrl);
    34	    }
    35	
    36	    public function testRejectsEmptyDefaultAvatarUrl(): void
    37	    {
    38	        $this->expectException(InvalidConfigValueException::class);
    39	        $this->expectExceptionMessage('user.defaultAvatarUrl');
    40	
    41	        new UserConfig(defaultAvatarUrl: '   ');
    42	    }
    43	
    44	    /**
    45	     * @param array<string, mixed> $config
    46	     */
    47	    private function mapperFor(array $config): ConfigMapper
    48	    {
    49	        $configurator = $this->createStub(ConfiguratorInterface::class);
    50	        $configurator
    51	            ->method('getConfig')
    52	            ->willReturnMap([[UserConfig::configName(), $config]]);
    53	
    54	        return new ConfigMapper(
    55	            configurator: $configurator,
    56	            mapper: new MapperBuilder()
    57	                ->configureWith(new ConvertKeysToCamelCase())
    58	                ->allowPermissiveTypes()
    59	                ->allowScalarValueCasting()
    60	                ->mapper(),
    61	            normalizer: new NormalizerBuilder()->normalizer(Format::array()),
    62	        );
    63	    }
    64	}

exec
/bin/zsh -lc "nl -ba tests/Feature/Modules/Tags/Application/GetTagsHandlerTest.php | sed -n '1,160p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace Tests\Feature\Modules\Tags\Application;
     6	
     7	use App\Modules\Tags\Application\Query\GetTags\GetTagsHandler;
     8	use App\Modules\Tags\Application\Query\GetTags\GetTagsQuery;
     9	use App\Modules\Tags\Domain\Entity\Tag;
    10	use App\Modules\Tags\Domain\ValueObject\TagText;
    11	use App\Shared\Domain\ValueObject\TagId;
    12	use Tests\Feature\Modules\Tags\TagsApplicationTestCase;
    13	
    14	final class GetTagsHandlerTest extends TagsApplicationTestCase
    15	{
    16	    public function testReturnsIdToTextMap(): void
    17	    {
    18	        $user = $this->persistUser();
    19	        $yoga = Tag::create(text: TagText::fromString('yoga'), createdBy: $user->id);
    20	        $meditation = Tag::create(text: TagText::fromString('meditation'), createdBy: $user->id);
    21	        $this->persist($yoga);
    22	        $this->persist($meditation);
    23	
    24	        $tags = $this->handler()->handle(new GetTagsQuery(
    25	            tagIds: [$yoga->id->value(), $meditation->id->value()],
    26	        ));
    27	
    28	        self::assertSame('yoga', $tags->get($yoga->id->value()));
    29	        self::assertSame('meditation', $tags->get($meditation->id->value()));
    30	        self::assertCount(2, $tags);
    31	    }
    32	
    33	    public function testExcludesMissingIds(): void
    34	    {
    35	        $user = $this->persistUser();
    36	        $yoga = Tag::create(text: TagText::fromString('yoga'), createdBy: $user->id);
    37	        $this->persist($yoga);
    38	
    39	        $tags = $this->handler()->handle(new GetTagsQuery(
    40	            tagIds: [$yoga->id->value(), TagId::generate()->value()],
    41	        ));
    42	
    43	        self::assertCount(1, $tags);
    44	        self::assertSame('yoga', $tags->get($yoga->id->value()));
    45	    }
    46	
    47	    public function testEmptyInputReturnsEmptyCollection(): void
    48	    {
    49	        $tags = $this->handler()->handle(new GetTagsQuery(tagIds: []));
    50	
    51	        self::assertCount(0, $tags);
    52	    }
    53	
    54	    private function handler(): GetTagsHandler
    55	    {
    56	        return $this->getContainer()->get(GetTagsHandler::class);
    57	    }
    58	}

exec
/bin/zsh -lc "nl -ba tests/Feature/Modules/Tags/Application/ResolveTagsHandlerTest.php | sed -n '1,190p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace Tests\Feature\Modules\Tags\Application;
     6	
     7	use App\Modules\Tags\Application\Command\ResolveTags\ResolveTagsCommand;
     8	use App\Modules\Tags\Application\Command\ResolveTags\ResolveTagsHandler;
     9	use App\Modules\Tags\Domain\Entity\Tag;
    10	use App\Modules\Tags\Domain\ValueObject\TagText;
    11	use App\Shared\Domain\ValueObject\TagId;
    12	use Tests\Feature\Modules\Tags\TagsApplicationTestCase;
    13	
    14	final class ResolveTagsHandlerTest extends TagsApplicationTestCase
    15	{
    16	    public function testCreatesNewTags(): void
    17	    {
    18	        $user = $this->persistUser();
    19	
    20	        $result = $this->handler()->handle(new ResolveTagsCommand(
    21	            texts: ['yoga', 'meditation'],
    22	            creatorUserId: $user->id->value(),
    23	        ));
    24	
    25	        self::assertCount(2, $result->tagIds);
    26	        self::assertNotNull($this->tagRepository()->findByText(TagText::fromString('yoga')));
    27	        self::assertNotNull($this->tagRepository()->findByText(TagText::fromString('meditation')));
    28	    }
    29	
    30	    public function testReusesExistingTagWithStableId(): void
    31	    {
    32	        $user = $this->persistUser();
    33	        $existing = Tag::create(text: TagText::fromString('yoga'), createdBy: $user->id);
    34	        $this->persist($existing);
    35	
    36	        $result = $this->handler()->handle(new ResolveTagsCommand(
    37	            texts: ['yoga'],
    38	            creatorUserId: $user->id->value(),
    39	        ));
    40	
    41	        self::assertSame([$existing->id->value()], $result->tagIds);
    42	    }
    43	
    44	    public function testDeduplicatesRepeatedTextInSingleRequest(): void
    45	    {
    46	        $user = $this->persistUser();
    47	
    48	        $result = $this->handler()->handle(new ResolveTagsCommand(
    49	            texts: ['yoga', 'Yoga', 'yoga'],
    50	            creatorUserId: $user->id->value(),
    51	        ));
    52	
    53	        self::assertCount(1, $result->tagIds);
    54	        self::assertCount(1, $this->tagRepository()->findByTexts(TagText::fromString('yoga')));
    55	    }
    56	
    57	    public function testPreservesInputTextOrder(): void
    58	    {
    59	        $user = $this->persistUser();
    60	
    61	        $result = $this->handler()->handle(new ResolveTagsCommand(
    62	            texts: ['beta', 'alpha'],
    63	            creatorUserId: $user->id->value(),
    64	        ));
    65	
    66	        self::assertCount(2, $result->tagIds);
    67	
    68	        $firstTag = $this->tagRepository()->findById(TagId::fromString($result->tagIds[0]));
    69	        $secondTag = $this->tagRepository()->findById(TagId::fromString($result->tagIds[1]));
    70	
    71	        self::assertNotNull($firstTag);
    72	        self::assertNotNull($secondTag);
    73	        self::assertSame('beta', $firstTag->text->value());
    74	        self::assertSame('alpha', $secondTag->text->value());
    75	    }
    76	
    77	    public function testEmptyInputReturnsEmptyResult(): void
    78	    {
    79	        $user = $this->persistUser();
    80	
    81	        $result = $this->handler()->handle(new ResolveTagsCommand(
    82	            texts: [],
    83	            creatorUserId: $user->id->value(),
    84	        ));
    85	
    86	        self::assertSame([], $result->tagIds);
    87	    }
    88	
    89	    private function handler(): ResolveTagsHandler
    90	    {
    91	        return $this->getContainer()->get(ResolveTagsHandler::class);
    92	    }
    93	}

exec
/bin/zsh -lc 'rg "PostMediaItemResource|TagResource|schema" packages/spiral-openapi app/src -n' in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
packages/spiral-openapi/src/OpenApiGenerator.php:36:        $logger->debug(\sprintf('Операций: %d, schemas: %d.', $buildResult->operationCount, $buildResult->schemaCount));
packages/spiral-openapi/src/OpenApiGenerator.php:37:        return new OpenApiGenerationResult(outputFile: $config->outputFile, operationCount: $buildResult->operationCount, schemaCount: $buildResult->schemaCount);
app/src/Shared/Infrastructure/Cycle/LazyGhostMapper.php:33:        $this->schema = $orm->getSchema();
app/src/Shared/Infrastructure/Cycle/LazyGhostMapper.php:103:        $entityClass = $this->schema->define(role: $role, property: SchemaInterface::ENTITY);
app/src/Shared/Infrastructure/Cycle/LazyGhostMapper.php:106:            throw new \UnexpectedValueException('Cycle schema вернула некорректный класс Entity.');
app/src/Shared/Infrastructure/Cycle/LazyGhostMapper.php:117:        $children = $this->schema->define(role: $role, property: SchemaInterface::CHILDREN) ?? [];
app/src/Shared/Infrastructure/Cycle/LazyGhostMapper.php:120:            throw new \UnexpectedValueException('Cycle schema вернула некорректный список наследников Entity.');
app/src/Shared/Infrastructure/Cycle/LazyGhostMapper.php:127:                throw new \UnexpectedValueException('Cycle schema вернула некорректный класс наследника Entity.');
app/src/Shared/Infrastructure/Cycle/LazyGhostMapper.php:138:        $discriminator = $this->schema->define(role: $role, property: SchemaInterface::DISCRIMINATOR);
app/src/Shared/Infrastructure/Cycle/LazyGhostMapper.php:145:            throw new \UnexpectedValueException('Cycle schema вернула некорректный discriminator.');
packages/spiral-openapi/src/Schema/NullableSchema.php:23:     * @param array<string, mixed> $schema
packages/spiral-openapi/src/Schema/NullableSchema.php:26:    public function makeNullable(array $schema): array
packages/spiral-openapi/src/Schema/NullableSchema.php:29:            return $this->makeNullableForOpenApi31(schema: $schema);
packages/spiral-openapi/src/Schema/NullableSchema.php:32:        return $this->makeNullableForOpenApi30(schema: $schema);
packages/spiral-openapi/src/Schema/NullableSchema.php:41:     * @param array<string, mixed> $schema
packages/spiral-openapi/src/Schema/NullableSchema.php:44:    private function makeNullableForOpenApi31(array $schema): array
packages/spiral-openapi/src/Schema/NullableSchema.php:46:        $type = $schema['type'] ?? null;
packages/spiral-openapi/src/Schema/NullableSchema.php:48:            $schema['type'] = [$type, self::NULL_TYPE];
packages/spiral-openapi/src/Schema/NullableSchema.php:50:            return $schema;
packages/spiral-openapi/src/Schema/NullableSchema.php:53:        return ['oneOf' => [$schema, ['type' => self::NULL_TYPE]]];
packages/spiral-openapi/src/Schema/NullableSchema.php:57:     * @param array<string, mixed> $schema
packages/spiral-openapi/src/Schema/NullableSchema.php:60:    private function makeNullableForOpenApi30(array $schema): array
packages/spiral-openapi/src/Schema/NullableSchema.php:62:        if (isset($schema['$ref'])) {
packages/spiral-openapi/src/Schema/NullableSchema.php:63:            return ['allOf' => [$schema], 'nullable' => true];
packages/spiral-openapi/src/Schema/NullableSchema.php:66:        $schema['nullable'] = true;
packages/spiral-openapi/src/Schema/NullableSchema.php:68:        return $schema;
packages/spiral-openapi/src/Schema/SchemaRegistry.php:10:    private array $schemas = [];
packages/spiral-openapi/src/Schema/SchemaRegistry.php:11:    public function has(string $schemaName): bool
packages/spiral-openapi/src/Schema/SchemaRegistry.php:13:        return isset($this->schemas[$schemaName]);
packages/spiral-openapi/src/Schema/SchemaRegistry.php:16:     * @param array<string, mixed> $schema
packages/spiral-openapi/src/Schema/SchemaRegistry.php:18:    public function add(string $schemaName, array $schema): void
packages/spiral-openapi/src/Schema/SchemaRegistry.php:20:        $this->schemas[$schemaName] = $schema;
packages/spiral-openapi/src/Schema/SchemaRegistry.php:27:        \ksort($this->schemas);
packages/spiral-openapi/src/Schema/SchemaRegistry.php:28:        return $this->schemas;
packages/spiral-openapi/src/Schema/SchemaRegistry.php:32:        return \count($this->schemas);
packages/spiral-openapi/src/Schema/SchemaBuilder.php:17:    public function __construct(private array $classesByName, private array $classesByShortName, private SchemaRegistry $schemaRegistry, private NullableSchema $nullableSchema) {}
packages/spiral-openapi/src/Schema/SchemaBuilder.php:25:        return ['$ref' => \sprintf('#/components/schemas/%s', $classMetadata->shortName)];
packages/spiral-openapi/src/Schema/SchemaBuilder.php:29:        if ($this->schemaRegistry->has($classMetadata->shortName)) {
packages/spiral-openapi/src/Schema/SchemaBuilder.php:33:            $this->schemaRegistry->add(schemaName: $classMetadata->shortName, schema: ['type' => 'string', 'enum' => $classMetadata->enumCases]);
packages/spiral-openapi/src/Schema/SchemaBuilder.php:36:        $this->schemaRegistry->add(schemaName: $classMetadata->shortName, schema: ['type' => 'object', 'properties' => new \stdClass()]);
packages/spiral-openapi/src/Schema/SchemaBuilder.php:40:            $properties[$propertyMetadata->name] = $this->schemaForProperty($propertyMetadata);
packages/spiral-openapi/src/Schema/SchemaBuilder.php:45:        $schema = ['type' => 'object', 'properties' => $properties];
packages/spiral-openapi/src/Schema/SchemaBuilder.php:47:            $schema['required'] = $required;
packages/spiral-openapi/src/Schema/SchemaBuilder.php:49:        $this->schemaRegistry->add(schemaName: $classMetadata->shortName, schema: $schema);
packages/spiral-openapi/src/Schema/SchemaBuilder.php:54:    public function schemaForProperty(PropertyMetadata $propertyMetadata): array
packages/spiral-openapi/src/Schema/SchemaBuilder.php:56:        $schema = $propertyMetadata->listItemType !== null ? ['type' => 'array', 'items' => $this->schemaForType($propertyMetadata->listItemType)] : $this->schemaForType($propertyMetadata->type);
packages/spiral-openapi/src/Schema/SchemaBuilder.php:58:            return $this->nullableSchema->makeNullable($schema);
packages/spiral-openapi/src/Schema/SchemaBuilder.php:60:        return $schema;
packages/spiral-openapi/src/Schema/SchemaBuilder.php:65:    public function schemaForType(string $type): array
packages/spiral-openapi/src/Spec/SpecBuilder.php:28:        $schemaRegistry = new SchemaRegistry();
packages/spiral-openapi/src/Spec/SpecBuilder.php:30:        $schemaBuilder = new SchemaBuilder(classesByName: $classesByName, classesByShortName: $classesByShortName, schemaRegistry: $schemaRegistry, nullableSchema: $nullableSchema);
packages/spiral-openapi/src/Spec/SpecBuilder.php:49:                    $paths[$this->pathWithoutRoutePrefix(routePath: $methodMetadata->route->path, routePrefix: $config->routePrefix)][\strtolower($httpMethod)] = $this->operation(classMetadata: $classMetadata, methodMetadata: $methodMetadata, operationId: $operationId, schemaBuilder: $schemaBuilder, classesByName: $classesByName, config: $config, nullableSchema: $nullableSchema);
packages/spiral-openapi/src/Spec/SpecBuilder.php:54:        $this->ensureErrorResponseSchema(schemaRegistry: $schemaRegistry, config: $config, nullableSchema: $nullableSchema);
packages/spiral-openapi/src/Spec/SpecBuilder.php:56:            spec: ['openapi' => $config->openApiVersion, 'info' => ['title' => $config->title, 'version' => $config->version], 'servers' => [['url' => $config->routePrefix]], 'paths' => $paths, 'components' => ['schemas' => $schemaRegistry->all()]],
packages/spiral-openapi/src/Spec/SpecBuilder.php:58:            schemaCount: $schemaRegistry->count(),
packages/spiral-openapi/src/Spec/SpecBuilder.php:91:    private function operation(ClassMetadata $classMetadata, MethodMetadata $methodMetadata, string $operationId, SchemaBuilder $schemaBuilder, array $classesByName, OpenApiGeneratorConfig $config, NullableSchema $nullableSchema): array
packages/spiral-openapi/src/Spec/SpecBuilder.php:93:        $successResponses = $this->successResponses(classMetadata: $classMetadata, methodMetadata: $methodMetadata, schemaBuilder: $schemaBuilder, config: $config, nullableSchema: $nullableSchema);
packages/spiral-openapi/src/Spec/SpecBuilder.php:94:        $errorResponse = ['description' => $this->translator->trans(id: 'gian_tiaga.spiral_openapi.api_error'), 'content' => ['application/json' => ['schema' => $this->errorResponseSchema(config: $config)]]];
packages/spiral-openapi/src/Spec/SpecBuilder.php:96:        $operation = ['operationId' => $operationId, 'description' => $methodMetadata->openApi?->description ?: $methodMetadata->summary, 'parameters' => $this->parameters(methodMetadata: $methodMetadata, schemaBuilder: $schemaBuilder, classesByName: $classesByName), 'responses' => $successResponses + ['default' => $errorResponse]];
packages/spiral-openapi/src/Spec/SpecBuilder.php:97:        $requestBody = $this->requestBody(methodMetadata: $methodMetadata, schemaBuilder: $schemaBuilder, classesByName: $classesByName);
packages/spiral-openapi/src/Spec/SpecBuilder.php:106:    private function successResponses(ClassMetadata $classMetadata, MethodMetadata $methodMetadata, SchemaBuilder $schemaBuilder, OpenApiGeneratorConfig $config, NullableSchema $nullableSchema): array
packages/spiral-openapi/src/Spec/SpecBuilder.php:112:        return ['200' => $this->successResponse(classMetadata: $classMetadata, methodMetadata: $methodMetadata, schemaBuilder: $schemaBuilder, config: $config, nullableSchema: $nullableSchema)];
packages/spiral-openapi/src/Spec/SpecBuilder.php:117:    private function successResponse(ClassMetadata $classMetadata, MethodMetadata $methodMetadata, SchemaBuilder $schemaBuilder, OpenApiGeneratorConfig $config, NullableSchema $nullableSchema): array
packages/spiral-openapi/src/Spec/SpecBuilder.php:120:            return ['description' => $this->translator->trans(id: 'gian_tiaga.spiral_openapi.successful_response'), 'content' => [$this->mediaType(contentType: $methodMetadata->fileResponse->contentType) => ['schema' => $methodMetadata->fileResponse->binary ? ['type' => 'string', 'format' => 'binary'] : ['type' => 'string']]]];
packages/spiral-openapi/src/Spec/SpecBuilder.php:126:        return ['description' => $this->translator->trans(id: 'gian_tiaga.spiral_openapi.successful_response'), 'content' => ['application/json' => ['schema' => $this->responseSchema(wrapperClass: $genericReturnType->wrapperClass, resourceClass: $genericReturnType->resourceClass, schemaBuilder: $schemaBuilder, config: $config, nullableSchema: $nullableSchema)]]];
packages/spiral-openapi/src/Spec/SpecBuilder.php:133:    private function ensureErrorResponseSchema(SchemaRegistry $schemaRegistry, OpenApiGeneratorConfig $config, NullableSchema $nullableSchema): void
packages/spiral-openapi/src/Spec/SpecBuilder.php:135:        $schemaName = $this->shortName(className: $config->responseWrapperMapping->errorResponseClass);
packages/spiral-openapi/src/Spec/SpecBuilder.php:136:        if ($schemaRegistry->has($schemaName)) {
packages/spiral-openapi/src/Spec/SpecBuilder.php:139:        $schemaRegistry->add(schemaName: $schemaName, schema: ['type' => 'object', 'properties' => ['message' => ['type' => 'string'], 'code' => $nullableSchema->makeNullable(['type' => 'integer'])], 'required' => ['message']]);
packages/spiral-openapi/src/Spec/SpecBuilder.php:146:        return ['$ref' => \sprintf('#/components/schemas/%s', $this->shortName(className: $config->responseWrapperMapping->errorResponseClass))];
packages/spiral-openapi/src/Spec/SpecBuilder.php:152:    private function parameters(MethodMetadata $methodMetadata, SchemaBuilder $schemaBuilder, array $classesByName): array
packages/spiral-openapi/src/Spec/SpecBuilder.php:159:            $parameters[] = ['name' => $parameterMetadata->name, 'in' => 'path', 'required' => true, 'schema' => $schemaBuilder->schemaForType($parameterMetadata->type)];
packages/spiral-openapi/src/Spec/SpecBuilder.php:162:            $parameters[] = ['name' => $propertyMetadata->name, 'in' => 'query', 'required' => $propertyMetadata->isRequired(), 'schema' => $schemaBuilder->schemaForProperty($propertyMetadata)];
packages/spiral-openapi/src/Spec/SpecBuilder.php:170:    private function requestBody(MethodMetadata $methodMetadata, SchemaBuilder $schemaBuilder, array $classesByName): array|null
packages/spiral-openapi/src/Spec/SpecBuilder.php:179:            $properties[$propertyMetadata->name] = $schemaBuilder->schemaForProperty($propertyMetadata);
packages/spiral-openapi/src/Spec/SpecBuilder.php:184:        $schema = ['type' => 'object', 'properties' => $properties];
packages/spiral-openapi/src/Spec/SpecBuilder.php:186:            $schema['required'] = $required;
packages/spiral-openapi/src/Spec/SpecBuilder.php:188:        return ['required' => true, 'content' => ['application/json' => ['schema' => $schema]]];
packages/spiral-openapi/src/Spec/SpecBuilder.php:193:    private function responseSchema(string $wrapperClass, string $resourceClass, SchemaBuilder $schemaBuilder, OpenApiGeneratorConfig $config, NullableSchema $nullableSchema): array
packages/spiral-openapi/src/Spec/SpecBuilder.php:197:            return ['type' => 'object', 'properties' => ['data' => $schemaBuilder->referenceFor($resourceClass)], 'required' => ['data']];
packages/spiral-openapi/src/Spec/SpecBuilder.php:200:            return ['type' => 'object', 'properties' => ['data' => ['type' => 'array', 'items' => $schemaBuilder->referenceFor($resourceClass)]], 'required' => ['data']];
packages/spiral-openapi/src/Spec/SpecBuilder.php:203:            return ['type' => 'object', 'properties' => ['data' => ['type' => 'array', 'items' => $schemaBuilder->referenceFor($resourceClass)], 'meta' => ['type' => 'object', 'properties' => ['nextCursor' => $nullableSchema->makeNullable(['type' => 'string']), 'limit' => ['type' => 'integer']], 'required' => ['nextCursor', 'limit']]], 'required' => ['data', 'meta']];
packages/spiral-openapi/tests/Schema/NullableSchemaTest.php:34:            ['oneOf' => [['$ref' => '#/components/schemas/HealthResource'], ['type' => 'null']]],
packages/spiral-openapi/tests/Schema/NullableSchemaTest.php:35:            $nullableSchema->makeNullable(['$ref' => '#/components/schemas/HealthResource']),
packages/spiral-openapi/tests/Schema/NullableSchemaTest.php:52:            ['allOf' => [['$ref' => '#/components/schemas/HealthResource']], 'nullable' => true],
packages/spiral-openapi/tests/Schema/NullableSchemaTest.php:53:            $nullableSchema->makeNullable(['$ref' => '#/components/schemas/HealthResource']),
packages/spiral-openapi/src/Spec/OpenApiGenerationResult.php:9:    public function __construct(public string $outputFile, public int $operationCount, public int $schemaCount) {}
packages/spiral-openapi/src/Spec/OpenApiBuildResult.php:15:        public int $schemaCount,
packages/spiral-openapi/tests/Generator/OpenApiGeneratorTest.php:71:        $schemas = $specNode->child(key: 'components')->child(key: 'schemas');
packages/spiral-openapi/tests/Generator/OpenApiGeneratorTest.php:72:        self::assertTrue($schemas->has(key: 'UserResource'));
packages/spiral-openapi/tests/Generator/OpenApiGeneratorTest.php:73:        $this->assertUserResourceNullableSchemaForOpenApi30(schemas: $schemas);
packages/spiral-openapi/tests/Generator/OpenApiGeneratorTest.php:93:        $schemas = $specNode->child(key: 'components')->child(key: 'schemas');
packages/spiral-openapi/tests/Generator/OpenApiGeneratorTest.php:94:        self::assertFalse($schemas->has(key: 'EmptySuccessResponse'));
packages/spiral-openapi/tests/Generator/OpenApiGeneratorTest.php:156:        $schemas = $components->child(key: 'schemas');
packages/spiral-openapi/tests/Generator/OpenApiGeneratorTest.php:167:        self::assertTrue($schemas->has(key: 'HealthResource'));
packages/spiral-openapi/tests/Generator/OpenApiGeneratorTest.php:168:        self::assertTrue($schemas->has(key: 'UserResource'));
packages/spiral-openapi/tests/Generator/OpenApiGeneratorTest.php:169:        self::assertTrue($schemas->has(key: 'ErrorResponse'));
packages/spiral-openapi/tests/Generator/OpenApiGeneratorTest.php:171:        $this->assertUserResourceNullableSchema(schemas: $schemas);
packages/spiral-openapi/tests/Generator/OpenApiGeneratorTest.php:173:    private function assertUserResourceNullableSchema(OpenApiSpecNode $schemas): void
packages/spiral-openapi/tests/Generator/OpenApiGeneratorTest.php:175:        $userResource = $schemas->child(key: 'UserResource');
packages/spiral-openapi/tests/Generator/OpenApiGeneratorTest.php:193:            [['$ref' => '#/components/schemas/HealthResource'], ['type' => 'null']],
packages/spiral-openapi/tests/Generator/OpenApiGeneratorTest.php:204:    private function assertUserResourceNullableSchemaForOpenApi30(OpenApiSpecNode $schemas): void
packages/spiral-openapi/tests/Generator/OpenApiGeneratorTest.php:206:        $userResource = $schemas->child(key: 'UserResource');
packages/spiral-openapi/tests/Generator/OpenApiGeneratorTest.php:221:        self::assertSame([['$ref' => '#/components/schemas/HealthResource']], $health->value(key: 'allOf'));
packages/spiral-openapi/tests/Generator/OpenApiGeneratorTest.php:245:        $schema = $yamlContent->child(key: 'schema');
packages/spiral-openapi/tests/Generator/OpenApiGeneratorTest.php:247:        self::assertSame('string', $schema->value(key: 'type'));
packages/spiral-openapi/tests/Generator/OpenApiGeneratorTest.php:248:        self::assertFalse($schema->has(key: 'format'));
packages/spiral-openapi/tests/Generator/OpenApiGeneratorTest.php:256:        $schema = $pdfContent->child(key: 'schema');
packages/spiral-openapi/tests/Generator/OpenApiGeneratorTest.php:258:        self::assertSame('string', $schema->value(key: 'type'));
packages/spiral-openapi/tests/Generator/OpenApiGeneratorTest.php:259:        self::assertSame('binary', $schema->value(key: 'format'));
app/src/Modules/Posts/Presentation/Http/Resource/PostMediaItemResource.php:10:final readonly class PostMediaItemResource extends AbstractResource
app/src/Modules/Posts/Presentation/Http/Resource/PostResource.php:15:     * @param list<PostMediaItemResource> $media
app/src/Modules/Posts/Presentation/Http/Resource/PostResource.php:16:     * @param list<TagResource> $tags
app/src/Modules/Posts/Presentation/Http/Resource/PostResource.php:48:                static fn(PostMediaItemView $media): PostMediaItemResource => PostMediaItemResource::fromView($media),
app/src/Modules/Posts/Presentation/Http/Resource/PostResource.php:52:                static fn(TagView $tag): TagResource => TagResource::fromView($tag),
app/src/Modules/Posts/Presentation/Http/Resource/TagResource.php:10:final readonly class TagResource extends AbstractResource
app/src/Shared/Infrastructure/Configuration/Cycle/CycleConfig.php:21:        public CycleSchemaConfig $schema,
app/src/Shared/Infrastructure/Configuration/Cycle/CycleCollectionsConfig.php:29:                    path: \sprintf('schema.collections.factories.%s', $name),
packages/spiral-openapi/composer.lock:998:                "nette/schema": "<1.2.2"
app/src/Modules/System/Presentation/Console/OpenApiGenerateCommand.php:45:            string: 'OpenAPI YAML записан: {file}. Операций: {operations}, schemas: {schemas}.',
app/src/Modules/System/Presentation/Console/OpenApiGenerateCommand.php:49:                '{schemas}' => (string) $openApiGenerationResult->schemaCount,

codex
+ Добавить: OpenAPI неверно описывает `PostResource.media` и `PostResource.tags` как массив строк, хотя код отдаёт объекты `PostMediaItemResource` и `TagResource`. Это реальный публичный рассинхрон контракта.

+ Добавить: `UnlikePostHandler` не проверяет, что запись опубликована и не удалена; `UnlikeCommentHandler` не проверяет, что комментарий не удалён. Для лайка такие проверки есть, для снятия лайка нет. Нужны тесты на unlike у draft/blocked/deleted post и deleted comment.

+ Добавить: `GetUserPublicProfiles` не гарантирует порядок результата, хотя план требовал проверить порядок/полноту batch-сценария. Тест сейчас проверяет только `contains`, а `where in` порядок не гарантирует.

+ Добавить: в ленте есть N+1 по медиа и тегам: `fromPosts()` батчит авторов и лайки, но `mediaItems()` и `tagViews()` ходят в репозитории отдельно на каждый пост.

+ Добавить: часть нового кода нарушает новое правило `docs/rules.md` про коллекции: есть `->all()` + `array_map` там, где уже есть коллекции. Особенно `PostViewAssembler`, `CommentViewAssembler`, `GetUserPublicProfilesHandler`.

− Убрать проблему 2 про `204`. Она неверная: существующие Auth-роуты `code_request`, `logout`, `sessions revoke` уже используют `EmptySuccessResponse` как `204` без тела; тест `AuthOpenApiGenerationTest` прямо проверяет `204` и отсутствие `200`.

~ Переформулировать проблему 1: риск 500 от `GetMediaUrl` подтверждён, но это не отклонение от плана. План как раз требовал `GetMediaUrl` для медиа записи; мягкая деградация через `FindMediaUrl` была явно только для аватара. Правильная квалификация: архитектурный риск и недостающий тест, а не нарушение плана.

~ Переформулировать замечание 6: под `/posts` сейчас ровно 15 операций. Реализация совпадает с таблицей плана; ошибка в тексте плана, где написано 16. Это не проблема кода.

~ Замечание 1 про лайк комментария усилить: проблема не только в видимости записи. Ещё есть асимметрия `LikeComment`/`UnlikeComment`: лайк удалённого комментария даёт 404, а unlike удалённого комментария guard не имеет.

? Текущий `make qa` я не запускал; проверял фактический код, OpenAPI и тестовые файлы.
tokens used
214 771
+ Добавить: OpenAPI неверно описывает `PostResource.media` и `PostResource.tags` как массив строк, хотя код отдаёт объекты `PostMediaItemResource` и `TagResource`. Это реальный публичный рассинхрон контракта.

+ Добавить: `UnlikePostHandler` не проверяет, что запись опубликована и не удалена; `UnlikeCommentHandler` не проверяет, что комментарий не удалён. Для лайка такие проверки есть, для снятия лайка нет. Нужны тесты на unlike у draft/blocked/deleted post и deleted comment.

+ Добавить: `GetUserPublicProfiles` не гарантирует порядок результата, хотя план требовал проверить порядок/полноту batch-сценария. Тест сейчас проверяет только `contains`, а `where in` порядок не гарантирует.

+ Добавить: в ленте есть N+1 по медиа и тегам: `fromPosts()` батчит авторов и лайки, но `mediaItems()` и `tagViews()` ходят в репозитории отдельно на каждый пост.

+ Добавить: часть нового кода нарушает новое правило `docs/rules.md` про коллекции: есть `->all()` + `array_map` там, где уже есть коллекции. Особенно `PostViewAssembler`, `CommentViewAssembler`, `GetUserPublicProfilesHandler`.

− Убрать проблему 2 про `204`. Она неверная: существующие Auth-роуты `code_request`, `logout`, `sessions revoke` уже используют `EmptySuccessResponse` как `204` без тела; тест `AuthOpenApiGenerationTest` прямо проверяет `204` и отсутствие `200`.

~ Переформулировать проблему 1: риск 500 от `GetMediaUrl` подтверждён, но это не отклонение от плана. План как раз требовал `GetMediaUrl` для медиа записи; мягкая деградация через `FindMediaUrl` была явно только для аватара. Правильная квалификация: архитектурный риск и недостающий тест, а не нарушение плана.

~ Переформулировать замечание 6: под `/posts` сейчас ровно 15 операций. Реализация совпадает с таблицей плана; ошибка в тексте плана, где написано 16. Это не проблема кода.

~ Замечание 1 про лайк комментария усилить: проблема не только в видимости записи. Ещё есть асимметрия `LikeComment`/`UnlikeComment`: лайк удалённого комментария даёт 404, а unlike удалённого комментария guard не имеет.

? Текущий `make qa` я не запускал; проверял фактический код, OpenAPI и тестовые файлы.
