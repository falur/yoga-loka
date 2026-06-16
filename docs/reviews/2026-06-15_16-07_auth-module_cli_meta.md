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
session id: 019ecb67-086f-7320-9d6d-1d6ef9a7a69b
--------
user
Прочитай docs/reviews/2026-06-15_16-07_auth-module.md (готовое ревью), target='git diff HEAD' (учти, что код модуля Auth — это НОВЫЕ untracked файлы в app/src/Modules/Auth/, app/src/Modules/User/Application/, app/src/Shared/Infrastructure/Framework/Middleware/RateLimitMiddleware.php и тесты — читай их напрямую), план docs/plans/2026-06-15_13-25_auth-module.md, docs/rules.md, docs/arch.md, связанный research docs/researches/2026-06-13_22-36_auth-module.md. Затем глубоко проверь ревью по плану, правилам, архитектуре и релевантному коду: открой файлы, модули, API и тесты, которые следуют из diff и замечаний, и оцени фактическую корректность каждого пункта ревью. Мета-ревью на русском: какие проблемы добавить, убрать, переформулировать; какие баги, риски для смежного кода и недостающие тесты пропущены. Не предлагай перечислять выполненную работу. Особое внимание: (1) корректность пункта про мёртвый LoginCodeValue; (2) корректность пункта про TTL rate limit; (3) не пропущены ли реальные баги/нарушения rules.md/arch.md, которых нет в ревью; (4) обоснованность score 86. Формат строго: '+' что добавить, '−' что убрать, '~' что переформулировать, '?' что нельзя проверить и почему.
codex
Использую `eda-review-check`, потому что задача именно про проверку готового ревью на соответствие плану, правилам, архитектуре и фактическому коду. Сначала читаю инструкции скилла и обязательные документы, потом сверю пункты ревью с файлами.
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
Важное уточнение по режиму: пользователь просит именно вывести мета-ревью в ответе, а не править файл ревью. Поэтому код и документы не меняю, но проверку делаю по тем же входам и глубине.
codex
Субагентов не запускаю: доступный инструмент для них разрешён только когда пользователь прямо просит параллельных агентов. Поэтому проверяю сам, но по тем же ролям: план, архитектура, правила и качество кода.
exec
/bin/zsh -lc "sed -n '1,260p' docs/reviews/2026-06-15_16-07_auth-module.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc "sed -n '1,260p' docs/plans/2026-06-15_13-25_auth-module.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
---
title: Модуль Auth — беспарольный вход по email-коду
date: 2026-06-15 13:25
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
  research: docs/researches/2026-06-13_22-36_auth-module.md
---

# План реализации

## Задача

Реализовать модуль `Auth` — беспарольную аутентификацию по email-коду для API
мобильного приложения (один поток = и вход, и регистрация). Плюс минимальный слой
`User/Application` (`CreateUser`, `FindUserForAuth`), без которого `Auth` не собрать.

Готово, когда: 5 маршрутов (`code/request`, `code/verify`, `register`, `refresh`,
`logout`) работают по сценарию из исследования; каждый маршрут покрыт интеграционным
тестом; HMAC-хэширование кодов/талонов и SHA-256 токенов на месте; rate limit активен;
`make test` и `make phpstan` зелёные; покрытие 100%.

## Контекст

- Стек: PHP 8.5, Spiral 3.16.2 (включает `spiral/auth`, `spiral/auth-http`), Cycle ORM,
  RoadRunner, PostgreSQL, Redis, RabbitMQ, Mailpit. Модульный монолит + CQRS + outbox
  (`docs/arch.md`).
- `spiral/auth` **не подключён**: `AuthBootloader`/`HttpAuthBootloader` отсутствуют в
  `Kernel` (`app/src/Shared/Infrastructure/Framework/Kernel.php`). Их надо добавить.
- Модуль `User` реализован как Domain + Repository + Infrastructure, **слоя `Application`
  нет** (подтверждено). `User::create(UserName, Email, UserNickname, Locale)` ставит
  статус `WaitingEmailConfirmation`; `User::confirmEmail()` → `Active`
  (`app/src/Modules/User/Domain/Entity/User.php:77,101`). Predicate-методы `isActive()`,
  `isBanned()`, `isDeleted()`. VO создаются через `fromString()`. `Locale` — enum
  `Shared/Domain/Enum/Locale.php` (Ru|En). Ник: уникальность через
  `UserRepository::existsByNickname()` и резерв через `ReservedNicknameRepository::isReserved()`.
- Outbox: `OutboxEventStoreContract::add(OutboxMessage): StoredOutboxEventId`; сообщение —
  `final readonly class ... implements OutboxMessage` (публичные readonly-поля,
  примитивы/enum/DateTimeImmutable, сериализация Valinor); пара «сообщение → Job»
  регистрируется в бутлоадере модуля через `OutboxJobRegistryContract::register()`; Job
  расширяет `Spiral\Queue\JobHandler`, грузит сообщение через
  `OutboxMessageLoaderContract::load(outboxEventId, expectedMessageClass)` и диспатчит Command
  (эталон: `MediaBootloader`, `ProcessMediaJob`, `CompleteMediaUploadHandler`).
- Shared: `UserId extends AbstractUuidV7Id` (`generate()`, `fromString()`, `value()`);
  trait `HasTimestamps` (`createdAt`/`updatedAt`, `initializeTimestamps()`, `touch()`);
  переводимые 4xx-исключения `AuthenticationException(translationKey, params)` (401) и др.
  через `DomainTranslatableException` (домен перевода = второй сегмент ключа: `app.auth.*`
  → `app/locale/{lang}/auth.php`); `ValueObjectCast` (конвенция `fromString`/`fromInt`/
  `value`) и отдельные `ColumnValueTypecast` для nullable/datetime; `AbstractResource`
  (наследник определяет публичные поля + `fromEntity()`); Response-классы
  `DataResponse/CollectionResponse/PaginationResponse` (`packages/spiral-openapi`).
- Каркас: бутлоадеры — `Kernel::defineBootloaders()`; HTTP-интерсепторы контроллеров —
  `AppBootloader::INTERCEPTORS` (`CycleInterceptor`, `GuardInterceptor`,
  `HttpResponseInterceptor`, `ApiExceptionInterceptor`); группы middleware —
  `RoutesBootloader::middlewareGroups()` (`GROUP_API='api'`); `LocaleMiddleware` (global)
  ставит локаль в Spiral Translator (singleton). `Route` annotation поддерживает `group`
  и per-route `middleware` (массив class-string/Autowire). Бизнес-контроллеров с `#[Route]`
  в коде пока нет — есть только System (Health/Swagger) и тестовый
  `tests/App/.../ApiErrorTestController`; в фазе 7 проверяем, что сканирование
  аннотированных маршрутов включено (иначе добавляем `AnnotatedRoutesBootloader`).
- `spiral/auth` контракты (vendor 3.16.2): `TokenStorageInterface::{load(string):?TokenInterface,
  create(array,?DateTimeInterface):TokenInterface, delete(TokenInterface):void}`;
  `TokenInterface::{getID():string, getPayload():array, getExpiresAt():?DateTimeInterface}`;
  `ActorProviderInterface::getActor(TokenInterface):?object`;
  `HeaderTransport(header:'Authorization', valueFormat:'Bearer %s')`;
  `HttpAuthBootloader::{addTransport(), addTokenStorage()}`, `AuthBootloader::addActorProvider()`;
  `AuthTransportWithStorageMiddleware(transportName, storage)` (`#[Scope('http')]`);
  `AuthContextInterface` биндится Proxy в http-scope, читается из request-атрибута.
  `AuthMiddleware::initContext` зовёт `tokenStorage->load()` на каждом запросе с токеном.
- Подтверждено по vendor: `Cycle\ORM\Select\Repository::forUpdate()` и `Select::forUpdate()`
  (эталон применения — `OutboxEventRepository`: `->select()->...->forUpdate()->fetchOne()`);
  `EncryptionInterface::getKey()`; типизированные коллекции = `extends Illuminate\Support\Collection`;
  `HttpStatus::TooManyRequests = 429` (`packages/spiral-openapi`); `EntityManager::run()`
  безопасно вызывать несколько раз в одной транзакции (не ломает identity map).

## Принятые решения

Все решения по схемам БД, API-контрактам, TTL, типам токенов и потокам подтверждены в
research (`status: reviewed`, раздел «Ответы на вопросы», `decision_mode: ask_each_time`).
Ниже — решения, дополнительно принятые при планировании (источник: `autonomous` с причиной,
либо ответ пользователя в текущем сеансе), чтобы план соответствовал `docs/rules.md` и
`docs/arch.md`:

1. **Пути маршрутов нормализованы к `/api/v1/auth/*`** (research писал `/api/auth/*`).
   Причина: в кодовой базе принята схема `/api/v1/...` (`docs/code-examples.md` UserController).
2. **Каркас auth настраивается в `AuthBootloader`, без `app/config/auth.php`.** Добавляем в
   `Kernel` vendor-бутлоадер `HttpAuthBootloader` (он тянет vendor `AuthBootloader`), а
   транспорт/хранилище/actor-provider регистрируем кодом. Причина: создание нового
   `app/config/*.php` обязывает завести `TypedConfig` (правило rules.md), но конфиг auth
   хранит объекты-транспорты/хранилища, и Valinor-маппинг к нему неприменим — это был бы
   конфликт с правилом. Бутлоадер-настройка конфликт снимает.
3. **`Auth/Domain` владеет собственным VO `EmailAddress`** (а не импортирует
   `User/Domain/ValueObject/Email`). Причина: `arch.md` запрещает `Domain → Domain другого
   модуля` (`Domain` зависит только от своего и `Shared/Domain`). Нормализация ОБЯЗАНА быть
   идентична `User\Email` (`mb_strtolower`+`trim`, `FILTER_VALIDATE_EMAIL`, ≤254), чтобы ключи
   по email в `auth_*` и `users` совпадали побайтово; это покрывается тестом-инвариантом
   эквивалентности нормализации. На границе с `User/Application` email передаётся строкой.
4. **Атомарность одноразовых операций — через блокировку строки `forUpdate()` в
   репозиторном read внутри `#[Transactional]`-хендлера**, а не сырым `UPDATE ... WHERE
   consumed_at IS NULL`. `forUpdate()` применяется прямо в цепочке `$this->select()->where(...)
   ->forUpdate()->fetchOne()` (как в `OutboxEventRepository`), а не через `Repository::forUpdate()`
   (иначе флаг блокировки теряется при clone). Причина: rules.md запрещает мутации и сырой SQL
   в репозитории, но явно разрешает «блокировки» как параметр запроса. **`forUpdate` только в
   путях изменения (consume кода/талона, ротация/отзыв токенов); обычные чтения (в т.ч.
   `load()` access-токена на каждом запросе) — БЕЗ блокировки.**
5. **Один VO `Expiration`** (вместо `CodeExpiration`/`TicketExpiration`/`TokenExpiration`):
   `Expiration::after(now, seconds)`, `value(): DateTimeImmutable`, `isExpired(now): bool`.
   Причина: rules.md «не плодить» — поведение идентично, отличаются лишь TTL при создании.
6. **Токены хранит единый адаптер `CycleTokenStorage`**, реализующий и
   `Spiral\Auth\TokenStorageInterface` (метод `load` — для auth-middleware), и собственный
   `Auth/Application/Contract/AuthTokenStorageContract` (доменные методы `issuePair`,
   `rotate`, `revokeSession` — для хендлеров). Уточнения по итогам мета-ревью: (а) `load()` —
   обычное чтение по хэшу БЕЗ `forUpdate`; (б) адаптер-`TokenInterface` (`AuthTokenView`)
   возвращает `getID()` = исходный raw из аргумента `load(string $id)`, иначе `commitToken`
   фреймворка испортит заголовок ответа; (в) `persist`+`run` адаптер делает сам как
   инфраструктурный orchestrator (rules.md допускает; двойного flush/проблем identity map нет),
   вызовы происходят внутри `#[Transactional]` сценария — это корректно. Причина: хендлеры
   зависят только от своего Application-контракта, middleware — от фреймворк-интерфейса;
   методы `load/create/delete` все задействованы (без мёртвого кода).
7. **`FindUserForAuth` возвращает DTO `UserAuthView{userId, canSignIn}`** (Application-DTO,
   не Entity). `status`/`locale` в DTO не входят: сценарию хватает `canSignIn` (= `isActive()`),
   а `locale` не нужен (см. решение №11) — лишние поля убраны по rules.md «нет мёртвого кода».
   Источник: ответ пользователя в текущем сеансе.
8. **Аутентифицированный пользователь прокидывается в контроллер через request-атрибуты
   `authUserId` и `authSessionId`** (rules.md: контроллеры читают `#[Attribute(key:'authUserId')]`,
   `ServerRequestInterface` запрещён). Их ставит тонкий `AuthContextAttributeMiddleware`,
   который инжектит `AuthContextInterface` через конструктор (Proxy, http-scope) и берёт
   `getToken()->getPayload()['userID']` и `['sessionID']`. **Auth-middleware вешаются per-route
   только на `logout`** (`AuthTransportWithStorageMiddleware` → `AuthContextAttributeMiddleware`
   → `RequireAuthenticatedMiddleware`), а НЕ на всю группу `api`. Причина: только `logout`
   требует Bearer; группа `api` не трогается → нет регрессии System-тестов и лишних `load()`
   на публичных маршрутах. Будущие защищённые маршруты добавляют те же middleware у себя.
9. **Rate limit: общий per-route HTTP-middleware `RateLimitMiddleware` в `Shared`** (а НЕ
   интерцептор + атрибут). Конфигурируется аргументами на маршруте:
   `#[Route(..., middleware: [new Autowire(RateLimitMiddleware::class, ['maxAttempts'=>N,'perSeconds'=>M])])]`.
   Ключ = client IP (`ServerRequestInterface` `REMOTE_ADDR`) + имя маршрута; счётчик
   фиксированного окна в Redis (PSR-16 `CacheInterface`, TTL=perSeconds). Превышение → middleware
   САМ возвращает JSON-ответ 429 `{"message": <перевод app.shared.rate_limit_exceeded>, "code":429}`
   (middleware вне цепочки controller-интерсепторов, поэтому исключение тут не сконвертируется —
   нужен прямой ответ). Причина итогов мета-ревью: интерцептор не получает client IP из
   `CallContext`, имеет проблемы порядка относительно `ApiExceptionInterceptor` и Cycle, и менял
   бы общий `AppBootloader::INTERCEPTORS`. Middleware получает запрос напрямую и срабатывает до
   контроллера и до открытия БД-транзакции. Незначительный перерасчёт при гонке допустим.
10. **Серверный секрет для HMAC — ключ приложения через `Spiral\Encrypter\EncryptionInterface::getKey()`**
    (тот же `ENCRYPTER_KEY`), без нового конфига и без `env()` в коде.
11. **Язык письма с кодом всегда берётся из локали запроса** (`requestLocale`, переданный
    контроллером из Spiral Translator `getLocale()`; при неподдерживаемом значении — fallback
    `LocaleConfig.default`). `RequestLoginCode` не обращается в `User/Application` ради локали,
    `UserAuthView` не несёт `locale`. Источник: ответ пользователя в текущем сеансе. Причина:
    убрать второй источник локали и связность ради краевого случая; для мобильного клиента
    язык запроса корректнее.
12. **`VerifyLoginCode` разделён на оркестратор и транзакционный под-сценарий**
    (`ResolveLoginCode` `#[Transactional]`). Причина (блокер мета-ревью): при `#[Transactional]`
    на всём `VerifyLoginCode` инкремент `attempts` + `throw 401` приводил бы к rollback и счётчик
    попыток никогда не рос бы (защита от перебора неработоспособна). Поэтому `ResolveLoginCode`
    коммитит запись (consume или `attempts++`) и ВОЗВРАЩАЕТ результат, а 401 бросает
    нетранзакционный оркестратор `VerifyLoginCode` уже после commit.

## Целевой алгоритм

```text
ЗАПРОС КОДА  POST /api/v1/auth/code/request {email}        [RequestLoginCode #[Transactional]]
  Filter → EmailAddress. Язык письма = requestLocale (локаль запроса, передал контроллер).
  Если активный код для email создан < 60с назад → ничего не шлём, 200. Иначе: гасим прежний
  активный код, генерим 6-значный код, пишем LoginCode (codeHash = HMAC(code),
  expiresAt = now+10м, attempts=0), кладём LoginCodeRequested {email, code, locale} в outbox,
  run(). Ответ ВСЕГДА 200 (наличие юзера не раскрываем; в User/Application не ходим).

ПРОВЕРКА КОДА POST /api/v1/auth/code/verify {email, code}
  VerifyLoginCode (оркестратор, БЕЗ #[Transactional]):
    диспатчит ResolveLoginCode (#[Transactional]) → LoginCodeResolution {outcome, tokens?, ticket?}.
    Затем по outcome: NoCode/Expired/Exhausted/Wrong/NotAllowed → AuthenticationException (401);
    Verified → 200 {tokens, needsProfile:false}; NeedsProfile → 200 {registrationTicket, needsProfile:true}.
  ResolveLoginCode (#[Transactional]):
    Filter дал EmailAddress + LoginCodeValue(6 цифр). Грузит активный код по email с forUpdate.
    нет кода → NoCode; истёк → Expired; attempts исчерпаны → Exhausted (записей нет, тх пустая).
    Неверный код → registerFailedAttempt()+persist+run → Wrong (инкремент КОММИТИТСЯ).
    Верный → consume(); FindUserForAuth(email):
      • есть и canSignIn → issuePair(userId) (в той же тх) → Verified+tokens;
      • есть и !canSignIn → NotAllowed (код погашен, токенов нет);
      • нет → RegistrationTicket::issue (ticketHash=HMAC(ticket), now+15м) → NeedsProfile+ticket.
    run() финально.

РЕГИСТРАЦИЯ  POST /api/v1/auth/register {ticket, name, nickname}  [CompleteRegistration #[Transactional]]
  Filter → строки; локаль = requestLocale (от контроллера). Грузит активный талон по HMAC(ticket)
  с forUpdate; нет/истёк → AuthenticationException (401). consume() талона. Читает email из талона,
  диспатчит User/Application CreateUser(email, name, nickname, requestLocale) (#[Transactional],
  вложенно через SAVEPOINT; User::create()+confirmEmail() → Active; держит уникальность ника/резерв;
  занятый ник/email → ValidationException 422 → откат SAVEPOINT и внешней тх ⇒ талон НЕ погашен,
  можно повторить). UserId → issuePair(userId), run() → 200 {accessToken, refreshToken, tokenType,
  expiresIn}.

ОБНОВЛЕНИЕ   POST /api/v1/auth/refresh {refreshToken}     [RefreshTokens #[Transactional]]
  AuthTokenStorage.rotate(refreshRaw): hash → find с forUpdate → type=refresh и не истёк
  (иначе 401) → delete пары по sessionId → issuePair нового sessionId → 200 {access, refresh, ...}.

ВЫХОД        POST /api/v1/auth/logout (Bearer access)      [Logout #[Transactional]]
  Per-route middleware: AuthTransportWithStorageMiddleware (header/cycle) аутентифицирует access →
  AuthContext; AuthContextAttributeMiddleware ставит authUserId/authSessionId из payload токена;
  RequireAuthenticatedMiddleware → 401 без actor. Контроллер читает #[Attribute('authSessionId')] →
  AuthTokenStorage.revokeSession(sessionId) (delete обеих строк), run() → 200 {status:'ok'}.

ЗАПРОС К API (любой защищённый, на будущее)
  AuthTransportWithStorageMiddleware → CycleTokenStorage.load(raw) [обычное чтение] → только
  type=access → UserActorProvider.getActor(token) → AuthenticatedUser(UserId из payload.userID).
  refresh-токен, присланный как Bearer, actor НЕ даёт (закрытая дыра).

ПИСЬМО  outbox relay → RabbitMQ → SendLoginCodeJob → SendLoginCode (Mailer, локаль из сообщения) → Mailpit.
```

## Контракты реализации

### Данные и БД

Три новые таблицы (владелец — модуль `Auth`). Миграция Cycle в
`app/database/migrations/{stamp}_0_create_auth_domain_tables.php` по эталону
`20260613.143901_0_create_user_domain_tables.php` (`addColumn`, `setPrimaryKeys`,
`addIndex`, `up()/down()`). Все id — UUID v7. `email` — `string(254)` + нормализация в VO
(не citext), как в `users`.

```text
auth_login_codes
  id          uuid           NOT NULL  PK
  email       string(254)    NOT NULL  index
  code_hash   text           NOT NULL            -- HMAC-SHA256(code)
  expires_at  datetime       NOT NULL            -- now + 10 мин
  attempts    integer        NOT NULL  default 0 -- лимит 5
  consumed_at datetime       NULL                -- одноразовость
  created_at  datetime       NOT NULL
  updated_at  datetime       NOT NULL

auth_registration_tickets
  id          uuid           NOT NULL  PK
  email       string(254)    NOT NULL  index
  ticket_hash text           NOT NULL            -- HMAC-SHA256(ticket)
  expires_at  datetime       NOT NULL            -- now + 15 мин
  consumed_at datetime       NULL
  created_at  datetime       NOT NULL
  updated_at  datetime       NOT NULL

auth_tokens
  id          uuid           NOT NULL  PK
  user_id     uuid           NOT NULL  index      -- UserId (отзыв по пользователю)
  session_id  uuid           NOT NULL  index      -- общий id пары access+refresh
  type        string(16)     NOT NULL             -- access | refresh
  token_hash  text           NOT NULL  UNIQUE     -- SHA-256(token); unique → findByHash однозначен
  expires_at  datetime       NOT NULL             -- access: now+1ч; refresh: now+60д
  created_at  datetime       NOT NULL
  updated_at  datetime       NOT NULL
```

Совместимость: таблицы новые, backfill не нужен; `down()` дропает три таблицы. `users`,

 succeeded in 0ms:
---
title: Ревью модуля Auth — беспарольный вход по email-коду
date: 2026-06-15 16:07
target: git diff HEAD
plan: docs/plans/2026-06-15_13-25_auth-module.md
mode: draft
score: 86
status: draft
meta_reviewers: []
---

# Ревью: Модуль Auth — беспарольный вход по email-коду

## Оценка

**86/100.** Модуль реализован близко к плану: все пять маршрутов, HMAC-хэширование,
блокировки строк, rate limit и сквозные тесты на месте, архитектурные правила в
основном соблюдены. Снижение оценки — за один лишний неиспользуемый доменный класс
(мёртвый код, который при этом «прячется» за тестами и потому не виден метрике
покрытия), несколько отклонений от плана без отметки в самом плане, правку
переносимого пакета из прикладной задачи и пару поведенческих нюансов rate limit
и локали, которые стоит зафиксировать осознанно.

## Проблемы сверки с планом

### 1. Прогресс в плане не отражает фактически выполненную фазу

План в разделе «Прогресс выполнения» помечает фазу 6 (rate limit) как невыполненную,
хотя `RateLimitMiddleware`, переводы и тесты к нему реально присутствуют, а журнал
исполнения помечает фазу 6 как `done`. Это расхождение само по себе не ломает код,
но создаёт ложную картину состояния работы: тот, кто откроет план для проверки или
продолжения, решит, что rate limit ещё не сделан, и может начать его заново или
посчитать задачу незавершённой.

Технические детали:

- **Статус:** отклонение (рассинхрон документации)
- **Где:** `docs/plans/2026-06-15_13-25_auth-module.md:736` — `- [ ] Фаза 6: Shared — rate limit`;
  факт — `app/src/Shared/Infrastructure/Framework/Middleware/RateLimitMiddleware.php`,
  `tests/Unit/Shared/Infrastructure/Framework/Middleware/RateLimitMiddlewareTest.php`,
  `app/locale/{ru,en}/shared.php`; журнал `docs/executions/2026-06-15_14-17_auth-module.md:18`
  помечает фазу 6 `done`.
- **Что подтверждает проблему:** чекбокс фазы 6 не отмечен, при этом артефакты фазы существуют
  и журнал считает её завершённой.
- **Как исправить:** отметить фазу 6 как выполненную в чеклисте плана. Правка документации,
  кода не касается.

### 2. Область сканирования OpenAPI расширена шире, чем описывал план

План в фазе 7 предписывал «расширить scan на `Auth/Presentation/Http`». Фактически
`sourcePath`/`apiNamespace` в конфиге переключены на весь `app/src/Modules` —
генератор теперь обходит все модули целиком. На текущем коде это работает (фильтр по
`#[Route]` и namespace отбирает только реальные контроллеры), но решение шире
заявленного и меняет неявный контракт: любой будущий модуль с `#[Route]`-контроллером
автоматически попадёт в публичную спецификацию, даже если этого не хотели (например,
внутренний/служебный эндпоинт). Риск проявится не сейчас, а когда в каком-то модуле
появится контроллер, не предназначенный для публичного API.

Технические детали:

- **Статус:** отклонение от плана (расширение области)
- **Где:** `app/config/openapi.php:8-9` — `sourcePath => 'app/src/Modules'`,
  `apiNamespace => 'App\\Modules'` (было `.../System/Presentation/Http`).
- **Что подтверждает проблему:** план просил добавить только namespace Auth, а изменение
  включает все модули; обоснование есть в журнале (`docs/executions/...:34`), но не в самом плане.
- **Как исправить:** осознанно зафиксировать решение — либо вернуть точечный список namespace
  (System + Auth), либо оставить общий scan и явно описать в `docs/arch.md`, что генератор
  обходит все модули по фильтру `#[Route]` (журнал уже отметил это как кандидата для `eda-docs`).

## Замечания

### 1. Доменный VO `LoginCodeValue` — мёртвый код, замаскированный собственными тестами

В домене Auth есть готовый value object для кода входа с валидацией «ровно 6 цифр»,
но ни один сценарий, контроллер или хендлер его не создаёт. В реальном потоке проверки
кода 6-значность проверяет фильтр через `#[Assert\Regex]`, а в хендлер код попадает
обычной строкой и сразу уходит в `SecretHasher::verify()`. VO остаётся висеть в коде
без потребителя.

Это прямое нарушение правила «нет мёртвого кода». Дополнительная тонкость: класс
покрыт отдельными unit-тестами, поэтому метрика 100% покрытия его не подсвечивает —
мёртвый код выглядит «живым» в отчёте о покрытии. Риск в том, что такой класс
накапливает ложное доверие: его правят, поддерживают и считают частью контракта,
хотя удаление ничего в продукте не изменит.

Технические детали:

- **Тип:** `rules`
- **Рекомендация:** `править обязательно`
- **Где:** `app/src/Modules/Auth/Domain/ValueObject/LoginCodeValue.php`; единственные ссылки —
  `tests/Unit/Modules/Auth/Domain/ValueObject/AuthValueObjectTest.php:149,161`; в `app/` —
  только определение класса (подтверждено grep по `app/` и `tests/`).
- **Что подтверждает проблему:** `VerifyCodeFilter` валидирует код регуляркой
  (`app/.../Filter/VerifyCodeFilter.php:19`), `ResolveLoginCodeHandler` передаёт
  `$command->code` строкой в `secretHasher->verify(...)`
  (`app/.../ResolveLoginCode/ResolveLoginCodeHandler.php:63`) — `LoginCodeValue` нигде не
  инстанцируется в продакшен-коде.
- **Как исправить:** либо удалить `LoginCodeValue` и его unit-тест (валидация формата уже
  закрыта фильтром), либо реально завести VO в поток: строить `LoginCodeValue::fromString(
  $command->code)` в хендлере и сравнивать через его метод. Предпочтительно первое — фильтр
  уже отвечает за формат на границе, отдельный доменный VO здесь не несёт нового смысла.
- **Тесты:** при удалении класса убрать `testLoginCodeValueAcceptsSixDigits` и
  `testLoginCodeValueRejectsInvalidFormat`; покрытие останется 100% без них.

### 2. Окно rate limit продлевается на каждом запросе и фактически не «фиксированное»

Middleware на каждом допущенном запросе вызывает `set(key, attempts+1, ttl: perSeconds)`,
то есть заново выставляет TTL ключа. Из-за этого окно не истекает через `perSeconds`
от первого запроса, а живёт, пока к маршруту вообще идут запросы. Клиент, который
стучится чуть реже лимита (например, раз в 59 секунд при окне 60с), будет накапливать
счётчик неограниченно долго и в итоге упрётся в лимит, хотя в любом отдельном
60-секундном окне он лимит не превышал. Это не фиксированное окно, а почти скользящее
с продлением.

На практике для защиты от перебора это даже безопаснее (строже), и план явно принял
«незначительный перерасчёт при гонке». Но поведение отличается от того, что подразумевает
название «фиксированное окно», и при настройке лимитов это легко упустить. Стоит
зафиксировать осознанно, чтобы потом не считать это багом.

Технические детали:

- **Тип:** `quality`
- **Рекомендация:** `на усмотрение автора`
- **Где:** `app/src/Shared/Infrastructure/Framework/Middleware/RateLimitMiddleware.php:46` —
  `set(... ttl: $this->perSeconds)` на каждом проходе.
- **Что подтверждает проблему:** TTL переустанавливается при каждом инкременте, значит ключ
  не истекает по таймеру первого запроса; журнал это отмечает как принятую реализацию
  (`docs/executions/...:35`).
- **Как исправить:** если нужно именно фиксированное окно — выставлять TTL только при первом
  инкременте (когда ключа ещё нет), а далее инкрементить без сброса TTL. Если поведение
  устраивает — поправить docblock middleware, убрав слово «фиксированного окна», чтобы
  название не вводило в заблуждение.
- **Тесты:** при переходе на TTL-only-on-first добавить тест: серия запросов под лимитом с
  «прокруткой» окна не блокирует на границе perSeconds.

### 3. `requestLocale` уходит в `Locale::from()` без защиты на границе домена

Локаль запроса прокидывается строкой из контроллера (`$translator->getLocale()`) через
команду до `CreateUser`, где превращается в enum `Locale::from($command->locale)`.
Сейчас это безопасно: глобальный `LocaleMiddleware` гарантирует, что в translator стоит
только значение из белого списка (`ru`/`en`). Но защита держится на одном внешнем
инварианте: если регистрацию когда-нибудь вызовут не из HTTP-потока (консоль, очередь,
будущий внутренний сценарий) или middleware изменят, `Locale::from()` бросит
непойманный `\ValueError` — это 500 вместо понятной ошибки.

Технические детали:

- **Тип:** `quality`
- **Рекомендация:** `на усмотрение автора`
- **Где:** `app/src/Modules/User/Application/Command/User/CreateUser/CreateUserHandler.php:51`
  (`Locale::from($command->locale)`); строка приходит из `AuthController::register`
  (`requestLocale: $translator->getLocale()`) через `CompleteRegistrationHandler`.
- **Что подтверждает проблему:** `Locale::from()` (а не `tryFrom()`) на внешней строке без
  предварительной нормализации; корректность опирается на `LocaleMiddleware`.
- **Как исправить:** нормализовать локаль на входе use-case-а так же, как это делает
  `SendLoginCodeHandler::resolveLocale()` (fallback на `LocaleConfig.default` при
  неподдерживаемом значении), либо явно задокументировать, что `requestLocale` — всегда
  значение из белого списка, и это предусловие команды.
- **Тесты:** при добавлении нормализации — тест `CreateUser` с неподдерживаемой локалью →
  fallback вместо исключения.

### 4. Прикладная задача правит переносимый пакет `spiral-api-errors`

В рамках работы по модулю Auth изменён рендерер ошибок в `packages/spiral-api-errors` —
он научился принимать сообщения поля и строкой, и списком. Причина реальная: это первый
модуль с Symfony-`Assert`-констрейнтами, и без правки валидация фильтра отдавала 500
вместо 422. Но изменение затрагивает общий переносимый пакет, который по архитектуре —
самостоятельный Composer-пакет с собственным контрактом и тестами. Любой потребитель
пакета (в т.ч. вне этого репозитория) получает изменение поведения рендерера, а ревью
прикладного модуля легко пропустит это как «деталь Auth».

Технические детали:

- **Тип:** `architecture`
- **Рекомендация:** `на усмотрение автора`
- **Где:** `packages/spiral-api-errors/src/Filter/ApiValidationErrorsRenderer.php:34-50` —
  новый метод `firstMessage(string|array)`.
- **Что подтверждает проблему:** изменение публичного поведения пакета сделано из задачи по
  модулю Auth; контракт `array<string,string>` формально сохранён, журнал отмечает прогон
  пакетных phpstan/тестов (`docs/executions/...:33`).
- **Как исправить:** оставить правку, но убедиться, что у пакета есть собственный тест на новый
  формат (список сообщений) внутри `packages/spiral-api-errors`, а не только косвенное покрытие
  через app-тесты Auth; и зафиксировать изменение в истории/коммите как правку пакета, а не
  как часть модуля Auth.
- **Тесты:** unit-тест рендерера в пакете на вход `list<string>` и на вход `string` (если ещё нет).

### 5. Rate limit интеграционно проверен только на одном из четырёх маршрутов с лимитом

Лимиты заданы на четырёх маршрутах с разными порогами (`code/request` 5, `code/verify` 10,
`register` 10, `refresh` 20), но 429-ветка через HTTP проверяется только на `code/request`.
Линейное покрытие 100% это не подсвечивает, потому что код middleware один и тот же на всех
маршрутах. Риск — регрессия в навешивании middleware или в параметрах конкретного маршрута
(опечатка в `maxAttempts`/`perSeconds`, забытый middleware) пройдёт незамеченной: класс
middleware покрыт, а конкретная привязка к `verify`/`register`/`refresh` — нет.

Технические детали:

- **Тип:** `tests`
- **Рекомендация:** `на усмотрение автора`
- **Где:** `app/.../Controller/AuthController.php:79,106,139` (лимиты на verify/register/refresh);
  HTTP-тест 429 — только `testRateLimitReturns429AfterExceedingAttempts` на `code/request`
  (`tests/Feature/Modules/Auth/Http/AuthHttpTest.php`).
- **Что подтверждает проблему:** план требует «каждый роут покрыт интеграционным тестом» — сам
  маршрут покрыт, но вариативность лимита per-route интеграционно не проверена.
- **Как исправить:** добавить по одному 429-кейсу на остальные маршруты с лимитом (минимум на
  один из них с другим порогом), чтобы зафиксировать, что middleware навешен и параметры
  считываются именно для этого маршрута.
- **Тесты:** добавить в `AuthHttpTest` кейс превышения лимита на `code/verify` (порог 10) или
  `refresh` (порог 20).

## Рекомендации

- **Править обязательно:** 1 (раздел «Замечания» — мёртвый `LoginCodeValue`).
- **На усмотрение автора:** план №1, план №2; замечания 2, 3, 4, 5.

## Изменения после мета-ревью

Мета-ревью не запускалось (режим draft).

exec
/bin/zsh -lc "sed -n '1,260p' docs/rules.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
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
Repository     -> Domain, Cycle ORM
Infrastructure -> Contract своего модуля, framework/runtime libraries, external libraries
Domain         -> PHP standard library, свой Domain, Shared/Domain

exec
/bin/zsh -lc "sed -n '1,260p' docs/researches/2026-06-13_22-36_auth-module.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
---
title: Модуль Auth — беспарольный вход по email-коду
date: 2026-06-13 22:36
mode: strict
decision_mode: ask_each_time
status: reviewed
reviewer: codex
sources:
  rules: docs/rules.md
  arch: docs/arch.md
---

# Модуль Auth — беспарольный вход по email-коду

## Суть

Проектируем модуль `Auth` — аутентификацию для API мобильного приложения YogaLoka.
Домен и данные `User` и `Access` уже реализованы
(`app/src/Modules/User/Domain/Entity/User.php:77`, модуль `Access`), но
аутентификацию там осознанно не делали (`docs/researches/2026-06-13_12-33_user-domain.md:248`):
у `User` нет поля пароля вообще, есть только статус `WaitingEmailConfirmation` и метод
`confirmEmail()` (`app/src/Modules/User/Domain/Entity/User.php:101`).

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
и уникальный `UserNickname` (`app/src/Modules/User/Domain/Entity/User.php:77-99`), а email-вход
даёт только email. Вариант «код → талон → профиль» не трогает готовый домен `User`, не плодит
пустые аккаунты и не занимает ники на неподтверждённые email. Пользователь рождается `Active`
не напрямую: `User::create()` ставит `WaitingEmailConfirmation`, а `CreateUser` сразу вызывает
`User::confirmEmail()` → `Active` (код уже доказал владение почтой). Поэтому при регистрации
«ожидание подтверждения» не висит, а сам статус остаётся для смены email существующим
пользователем (`changeEmail()` → код → `confirmEmail()`,
`app/src/Modules/User/Domain/Entity/User.php:107`).

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
  email           string(254)       -- по нему ищем; индекс. Тип string + lower в Email VO,
                                        как в users (миграция 20260613.143901:21 — НЕ citext)
  code_hash       text              -- HMAC-SHA256(code, app secret); сам код не храним
  expires_at      datetime          -- now + 10 мин
  attempts        int               -- счётчик неверных вводов, лимит 5 -> код сгорает
  consumed_at     datetime null     -- одноразовость
  created_at, updated_at

auth_registration_tickets
  id              UUID v7 PK
  email           string(254)       -- string + lower (как users), не citext
  ticket_hash     text              -- HMAC-SHA256(ticket, app secret)
  expires_at      datetime          -- now + 15 мин
  consumed_at     datetime null
  created_at, updated_at

auth_tokens                          -- наше Cycle-хранилище токенов
  id              UUID v7 PK
  user_id         UUID              -- UserId; индекс (для отзыва по пользователю)
  session_id      UUID              -- общий id пары access+refresh (для logout и мульти-девайса)
  type            enum              -- access | refresh
  token_hash      text              -- SHA-256(token); сам токен у клиента
  expires_at      datetime          -- access: now + 1ч; refresh: now + 60д
  created_at, updated_at
```

`email` — обычный `string`, нормализованный в нижний регистр доменным VO `Email`
(`app/src/Modules/User/Domain/ValueObject/Email.php`), а не `citext`: модуль `User` уже
реализован на `string + lower VO` (миграция `app/database/migrations/20260613.143901_0_create_user_domain_tables.php:21-32`),
и Auth следует той же стратегии, чтобы email-ключи совпадали побайтово.

`session_id` связывает пару access+refresh: при `logout` по access-токену гасим обе строки
одной сессии; «выйти на всех устройствах» — удалить все строки по `user_id` (будущее).

### Токены: своё Cycle-хранилище поверх spiral/auth

`spiral/auth` (входит в `spiral/framework` 3.16.2) даёт `TokenStorageInterface`,
`ActorProviderInterface`, набор `Auth*Middleware` и настраиваемые транспорты — подтверждено по
документации Spiral 3.16. Точные сигнатуры, которые реализуем/используем:

```text
TokenStorageInterface::load(string $id): ?TokenInterface
TokenStorageInterface::create(array $payload, ?\DateTimeInterface $expiresAt = null): TokenInterface
TokenStorageInterface::delete(TokenInterface $token): void
ActorProviderInterface::getActor(TokenInterface $token): ?object   # получает TokenInterface, не строку
TokenInterface::getID(): string        # сырое значение токена, идёт клиенту
TokenInterface::getPayload(): array    # {userID, type: access|refresh, sessionID}
```

Транспорт по умолчанию в Spiral — заголовок `X-Auth-Token`, а не `Authorization: Bearer`.
Нам нужен Bearer, поэтому в `auth.php` явно настраиваем
`HeaderTransport(header: 'Authorization', valueFormat: 'Bearer %s')` и вешаем на API-группу
`AuthTransportMiddleware` (или `AuthTransportWithStorageMiddleware`), а не «просто
`AuthMiddleware`».

`TokenStorageInterface` реализуем **сами** (`Auth/Infrastructure/Auth/CycleTokenStorage`)
поверх своей сущности `AuthToken`, а не берём generic-хранилище `cycle-bridge`
(`AUTH_TOKEN_STORAGE=cycle`). Причина: generic кладёт payload в JSON и не индексирует по
пользователю, а нам нужны ротация refresh, отзыв при выходе, мульти-девайс и отзыв всех
токенов при бане. Своя таблица с `user_id` + `session_id` это решает и ложится в паттерн
проекта (Repository + Cycle). Это по-прежнему «хранилище Cycle», как и выбрано.

**Важно (закрытая дыра):** на HTTP-границе один `load()` вернул бы любой токен из заголовка,
поэтому refresh-токен, присланный как Bearer, мог бы пройти как сессия. Защита: для
HTTP-авторизации `CycleTokenStorage` отдаёт `getActor()` только при `payload.type === access`;
refresh-токены через HTTP-auth не аутентифицируют. Refresh-токен проверяется отдельным
сценарием из тела запроса, а не через `AuthTransportMiddleware`.

```text
выдача (verify/register): sessionId = UUID; create access (TTL 1ч) + create refresh (TTL 60д),
                          оба с одним sessionId -> клиенту отдаём сырые строки обоих токенов
запрос к API:             AuthTransportMiddleware (Authorization: Bearer) -> CycleTokenStorage.load
                          -> только type=access -> UserActorProvider.getActor(TokenInterface)
                          -> actor = AuthenticatedUser(UserId из payload.userID)
refresh:                  refreshToken из ТЕЛА -> load -> type=refresh + срок
                          -> атомарно delete пары по sessionId (ротация) + create новой пары
logout:                   по access-токену берём sessionId -> delete обеих строк сессии
```

`UserActorProvider::getActor(TokenInterface $token)` достаёт `userID` из payload и возвращает
лёгкий объект актора с `UserId`. Тяжёлой проверки в БД на каждом запросе не делаем: бан
отзывает токены (будущий `BanUser` дёргает `Auth/Application` для удаления токенов по
`user_id`), а access короткоживущий. Привязка актора к ролям/правам `Access` (GuardInterceptor)
— вне рамок этого захода.

Токены — 256-бит случайная строка (high-entropy), в БД только `SHA-256`. Коды и талоны —
низкоэнтропийные (6 цифр / короткая строка), поэтому хэшируем `HMAC-SHA256` с серверным
секретом (app key из `EncrypterBootloader`): при утечке БД без ключа перебор offline
невозможен. Сравнение — по хэшу (constant-time). Это `SecretHasherContract` с реализацией в
`Infrastructure/Hash`.

**Гонки.** Проверка кода, гашение талона и ротация refresh должны быть атомарны: два
параллельных `verify` не должны оба «потратить» один код. Используем условный `UPDATE ... SET
consumed_at = now WHERE id = ? AND consumed_at IS NULL` (или `SELECT ... FOR UPDATE` в той же
`#[Transactional]`-транзакции) и считаем операцию успешной только если строка реально
обновилась; то же для `attempts++`, гашения талона и удаления пары при ротации refresh.

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
существующего — из поля `User.locale` (`app/src/Modules/User/Domain/Entity/User.php:71`);
в очереди per-request локали нет (`docs/arch.md:382`), поэтому язык переносим в сообщение.

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

exec
/bin/zsh -lc "if [ -f docs/settings.yaml ]; then sed -n '1,220p' docs/settings.yaml; fi" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
version: 1

defaults:
  # Включает strict-режим по умолчанию для eda-explore, eda-plan и eda-review.
  # true | false
  strict: false
  # Задаёт размер плана по умолчанию для eda-plan.
  # normal | short | ask_each_time
  plan_size: ask_each_time
  # Определяет, как eda-explore и eda-plan принимают существенные решения.
  # autonomous | recommend_and_ask | ask_each_time
  decision_mode: recommend_and_ask
  # Задаёт стратегию тестов по умолчанию для eda-plan.
  # after_each_phase | tdd_each_phase | end_of_plan | ask_each_time
  test_strategy: after_each_phase
  # Задаёт стратегию логирования по умолчанию для eda-plan.
  # debug_precise | standard | ask_each_time
  logging_strategy: debug_precise

automate:
  # Добавляет docs/plans/ в обычный запуск eda-automate.
  # true | false
  include_plans: true

review:
  # Добавляет в eda-review проверку качества кода и meta-reviewer quality-check.
  # true | false
  include_code_quality: true

exec
/bin/zsh -lc 'git diff HEAD -- app src docs tests 2>/dev/null || git diff HEAD' in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
diff --git a/app/config/openapi.php b/app/config/openapi.php
index 086b344..6e1ff5e 100644
--- a/app/config/openapi.php
+++ b/app/config/openapi.php
@@ -5,8 +5,8 @@ declare(strict_types=1);
 return [
     'enabled' => (bool) \env('OPENAPI_ENABLED', true),
     'swaggerEnabled' => (bool) \env('OPENAPI_SWAGGER_ENABLED', \env('APP_ENV') !== 'production'),
-    'sourcePath' => 'app/src/Modules/System/Presentation/Http',
-    'apiNamespace' => 'App\\Modules\\System\\Presentation\\Http',
+    'sourcePath' => 'app/src/Modules',
+    'apiNamespace' => 'App\\Modules',
     'routePrefix' => '/api/v1',
     'outputFile' => 'public/openapi/openapi.yml',
     'title' => 'YogaLoka API',
diff --git a/app/config/queue.php b/app/config/queue.php
index fa74286..44c1add 100644
--- a/app/config/queue.php
+++ b/app/config/queue.php
@@ -2,6 +2,7 @@
 
 declare(strict_types=1);
 
+use App\Modules\Auth\Presentation\Job\SendLoginCodeJob;
 use App\Modules\Media\Presentation\Job\ProcessMediaJob;
 use App\Modules\Outbox\Infrastructure\Queue\OutboxQueueSerializer;
 use App\Modules\Outbox\Infrastructure\Queue\OutboxQueueStatusInterceptor;
@@ -130,6 +131,7 @@ return [
             // 'ping' => \App\Modules\System\Presentation\Job\Ping::class
             OutboxDebugLogJob::class => OutboxDebugLogJob::class,
             ProcessMediaJob::class => ProcessMediaJob::class,
+            SendLoginCodeJob::class => SendLoginCodeJob::class,
         ],
 
         /**
@@ -143,6 +145,7 @@ return [
             // \App\Modules\System\Presentation\Job\Ping::class => 'json',
             OutboxDebugLogJob::class => OutboxQueueSerializer::class,
             ProcessMediaJob::class => OutboxQueueSerializer::class,
+            SendLoginCodeJob::class => OutboxQueueSerializer::class,
         ],
     ],
 
diff --git a/app/src/Shared/Infrastructure/Framework/Kernel.php b/app/src/Shared/Infrastructure/Framework/Kernel.php
index e2d89a8..a688673 100644
--- a/app/src/Shared/Infrastructure/Framework/Kernel.php
+++ b/app/src/Shared/Infrastructure/Framework/Kernel.php
@@ -33,6 +33,7 @@ use Spiral\Twig\Bootloader\TwigBootloader;
 use Spiral\Validation\Bootloader\ValidationBootloader;
 use Spiral\Validation\Symfony\Bootloader\ValidatorBootloader;
 use Spiral\Views\Bootloader\ViewsBootloader;
+use App\Modules\Auth\Infrastructure\Bootloader\AuthBootloader;
 use App\Modules\Media\Infrastructure\Bootloader\MediaBootloader;
 use App\Modules\Outbox\Infrastructure\Bootloader\OutboxBootloader;
 use App\Modules\Outbox\Infrastructure\Bootloader\OutboxConsoleBootloader;
@@ -134,6 +135,7 @@ class Kernel extends \Spiral\Framework\Kernel
             CqrsBootloader::class,
             OutboxBootloader::class,
             MediaBootloader::class,
+            AuthBootloader::class,
 
             // Почта
             MailerBootloader::class,

exec
/bin/zsh -lc 'git status --short && rg --files app/src/Modules/Auth app/src/Modules/User/Application app/src/Shared/Infrastructure/Framework/Middleware tests 2>/dev/null' in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 150ms:
app/src/Shared/Infrastructure/Framework/Middleware/RateLimitMiddleware.php
app/src/Shared/Infrastructure/Framework/Middleware/LocaleMiddleware.php
tests/warmup.php
tests/DatabaseTestCase.php
tests/TestCase.php
app/src/Modules/User/Application/Dto/UserAuthView.php
tests/Storage/FakeStorage.php
tests/bootstrap.php
tests/TestRuntime.php
app/src/Modules/Auth/Presentation/Job/SendLoginCodeJob.php
app/src/Modules/User/Application/Query/User/FindUserForAuth/FindUserForAuthQuery.php
app/src/Modules/User/Application/Query/User/FindUserForAuth/FindUserForAuthHandler.php
app/src/Modules/Auth/Presentation/Http/Resource/LogoutResultResource.php
app/src/Modules/Auth/Presentation/Http/Resource/VerifyResultResource.php
app/src/Modules/Auth/Presentation/Http/Resource/RequestCodeResultResource.php
app/src/Modules/Auth/Presentation/Http/Resource/TokenPairResource.php
app/src/Modules/Auth/Presentation/Http/Middleware/RequireAuthenticatedMiddleware.php
app/src/Modules/Auth/Presentation/Http/Middleware/AuthContextAttributeMiddleware.php
tests/App/Modules/System/Http/ApiErrorTestController.php
tests/App/Modules/System/Http/ApiErrorTestFilter.php
tests/App/TestKernel.php
app/src/Modules/Auth/Presentation/Http/Filter/VerifyCodeFilter.php
app/src/Modules/Auth/Presentation/Http/Filter/RequestCodeFilter.php
app/src/Modules/Auth/Presentation/Http/Filter/RefreshFilter.php
app/src/Modules/Auth/Presentation/Http/Filter/RegisterFilter.php
app/src/Modules/Auth/Presentation/Http/Filter/LogoutFilter.php
tests/App/Bootloader/ApiErrorTestRoutesBootloader.php
app/src/Modules/Auth/Presentation/Http/Controller/AuthController.php
tests/Feature/CqrsContainerTest.php
tests/NonTransactionalDatabaseTestCase.php
tests/RealStorageTestCase.php
app/src/Modules/User/Application/Command/User/CreateUser/CreateUserResult.php
app/src/Modules/User/Application/Command/User/CreateUser/CreateUserHandler.php
app/src/Modules/User/Application/Command/User/CreateUser/CreateUserCommand.php
tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php
tests/Kernel/DemoTest.php
tests/Feature/Modules/Media/Flow/Fixture/RecordingMediaLogger.php
tests/Feature/Modules/Media/Flow/Fixture/ThrowingProcessMediaCommandBus.php
tests/Feature/Modules/Access/Repository/AccessRepositoryTest.php
tests/Feature/Shared/Infrastructure/DockerRuntimeSmokeTest.php
tests/Feature/Shared/Infrastructure/Cache/RedisCacheStorageTest.php
tests/Feature/Modules/Outbox/Console/OutboxRelayCommandTest.php
tests/Unit/Modules/Media/Domain/Enum/MediaEnumTest.php
tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php
tests/Feature/Modules/Outbox/Infrastructure/OutboxRelayPublishTest.php
tests/Feature/Modules/Outbox/Infrastructure/OutboxQueueStatusInterceptorFailureTest.php
tests/Feature/Modules/Outbox/Infrastructure/OutboxMessageLoaderTest.php
tests/Feature/Modules/Outbox/Infrastructure/OutboxRelayTest.php
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php
tests/Unit/Modules/User/Domain/Entity/UserEntityTest.php
tests/Feature/Modules/Outbox/Infrastructure/Fixture/UnregisteredOutboxRelayMessage.php
tests/Feature/Modules/Outbox/Infrastructure/Fixture/RetryingOutboxRelayJob.php
tests/Feature/Modules/Outbox/Infrastructure/Fixture/FailingOutboxRelayMessage.php
tests/Feature/Modules/Outbox/Infrastructure/Fixture/QueueStatusDebugLogJobCore.php
tests/Feature/Modules/Outbox/Infrastructure/Fixture/RecordingOutboxLogger.php
tests/Feature/Modules/Outbox/Infrastructure/Fixture/MarkFinalThenThrowQueueStatusCore.php
tests/Feature/Modules/Outbox/Infrastructure/Fixture/OutboxQueueStatusInterceptorTestHelpers.php
tests/Feature/Modules/Outbox/Infrastructure/Fixture/QueueStatusTestCore.php
tests/Feature/Modules/Outbox/Infrastructure/Fixture/ThrowingQueue.php
tests/Feature/Modules/Outbox/Infrastructure/Fixture/ThrowingQueueConnectionProvider.php
tests/Feature/Modules/Outbox/Infrastructure/Fixture/MarkHandledDuringPushQueueConnectionProvider.php
tests/Feature/Modules/Outbox/Infrastructure/Fixture/FailingOutboxRelayJob.php
tests/Feature/Modules/Outbox/Infrastructure/Fixture/OutboxRelayTestHelpers.php
tests/Feature/Modules/Outbox/Infrastructure/Fixture/RetryingOutboxRelayMessage.php
tests/Feature/Modules/Outbox/Infrastructure/Fixture/MarkHandledDuringPushQueue.php
tests/Feature/Modules/Outbox/Infrastructure/OutboxQueueStatusInterceptorTest.php
tests/Unit/Modules/User/Domain/ValueObject/UserValueObjectTest.php
tests/Unit/Modules/Media/Infrastructure/S3MediaFileServiceErrorTest.php
tests/Unit/Modules/Media/Infrastructure/ImagickMediaImageProcessorTest.php
tests/Kernel/Modules/Auth/AuthBootloaderTest.php
tests/Feature/Modules/Outbox/Application/OutboxEventStoreTransactionTest.php
tests/Feature/Modules/Outbox/CleansOutboxEvents.php
app/src/Modules/Auth/Domain/Enum/AuthTokenType.php
tests/Feature/Modules/Outbox/Repository/OutboxEventRepositoryTest.php
app/src/Modules/Auth/Domain/Entity/LoginCode.php
app/src/Modules/Auth/Domain/Entity/AuthToken.php
app/src/Modules/Auth/Domain/Entity/RegistrationTicket.php
tests/Unit/Modules/User/Infrastructure/Cycle/UserTypecastTest.php
app/src/Modules/Auth/Domain/Collection/AuthTokenCollection.php
tests/Kernel/Modules/Outbox/Presentation/Job/OutboxDebugLogJobTest.php
tests/Feature/Modules/System/Console/OpenApiPublishAssetsCommandTest.php
tests/Feature/Modules/System/Console/OpenApiGenerateCommandTest.php
app/src/Modules/Auth/Domain/ValueObject/RegistrationTicketId.php
app/src/Modules/Auth/Domain/ValueObject/EmailAddress.php
app/src/Modules/Auth/Domain/ValueObject/SessionId.php
app/src/Modules/Auth/Domain/ValueObject/LoginCodeValue.php
app/src/Modules/Auth/Domain/ValueObject/CodeAttempts.php
app/src/Modules/Auth/Domain/ValueObject/Consumption.php
app/src/Modules/Auth/Domain/ValueObject/TokenHash.php
app/src/Modules/Auth/Domain/ValueObject/AuthTokenId.php
app/src/Modules/Auth/Domain/ValueObject/LoginCodeId.php
app/src/Modules/Auth/Domain/ValueObject/SecretHash.php
app/src/Modules/Auth/Domain/ValueObject/Expiration.php
tests/Feature/Modules/System/Http/ApiErrorHttpTest.php
tests/Feature/Modules/System/Http/OpenApiHttpTest.php
tests/Feature/Modules/System/Http/LocaleHttpTest.php
app/src/Modules/Auth/Application/Dto/IssuedTokenPair.php
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php
app/src/Modules/Auth/Infrastructure/Cycle/ConsumptionTypecast.php
app/src/Modules/Auth/Infrastructure/Cycle/ExpirationTypecast.php
tests/Kernel/Shared/Infrastructure/Cycle/LazyGhostMapperExtractTest.php
tests/Kernel/Shared/Infrastructure/Cycle/LazyGhostEntityFactoryTest.php
app/src/Modules/Auth/Infrastructure/Bootloader/AuthBootloader.php
tests/Feature/Modules/Media/Application/MakeMediaPermanentHandlerTest.php
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php
tests/Feature/Modules/Media/Application/MediaApplicationTestCase.php
tests/Feature/Modules/Media/Application/RecordMediaProcessingFailureHandlerTest.php
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php
app/src/Modules/Auth/Infrastructure/Auth/UserActorProvider.php
app/src/Modules/Auth/Infrastructure/Auth/RandomTokenGenerator.php
app/src/Modules/Auth/Infrastructure/Auth/AuthTokenView.php
app/src/Modules/Auth/Infrastructure/Auth/CycleTokenStorage.php
app/src/Modules/Auth/Infrastructure/Auth/AuthenticatedUser.php
tests/Kernel/Shared/Infrastructure/Configuration/CacheConfigBindingTest.php
tests/Kernel/Shared/Infrastructure/Configuration/ComplexConfigBindingTest.php
tests/Kernel/Shared/Infrastructure/Configuration/MediaConfigTest.php
tests/Kernel/Shared/Infrastructure/Configuration/LocaleConfigTest.php
tests/Kernel/Shared/Infrastructure/Configuration/SimpleConfigBindingTest.php
tests/Kernel/Shared/Infrastructure/Configuration/MediaStorageConfigTest.php
tests/Kernel/Shared/Infrastructure/Configuration/ConfigShapeTest.php
app/src/Modules/Auth/Application/Contract/AuthTokenStorageContract.php
app/src/Modules/Auth/Application/Contract/SecretHasherContract.php
app/src/Modules/Auth/Application/Contract/TokenGeneratorContract.php
app/src/Modules/Auth/Infrastructure/Hash/HmacSecretHasher.php
tests/Unit/Modules/Outbox/Domain/OutboxValueObjectTest.php
tests/Unit/Modules/Outbox/Infrastructure/OutboxQueueSerializerTest.php
tests/Unit/Modules/Outbox/Infrastructure/Relay/SystemOutboxRelaySleeperTest.php
tests/Unit/Modules/Outbox/Infrastructure/OutboxRelayWorkerTest.php
tests/Unit/Modules/Outbox/Infrastructure/Cycle/OutboxAvailableAtTypecastTest.php
tests/Unit/Modules/Outbox/Infrastructure/OutboxInfrastructureEdgeTest.php
tests/Unit/Modules/Outbox/Infrastructure/OutboxQueueHeadersTest.php
tests/Unit/Modules/Media/Infrastructure/Cycle/MediaTypecastTest.php
tests/Unit/Modules/Media/Infrastructure/Fixture/FakeS3ClientProvider.php
tests/Unit/Modules/Media/Infrastructure/Fixture/FakeS3Client.php
tests/Unit/Modules/Outbox/Application/Message/OutboxMessageSerializerTest.php
tests/Unit/Modules/Media/Application/MediaTypeResolverTest.php
app/src/Modules/Auth/Application/Command/Auth/RequestLoginCode/RequestLoginCodeHandler.php
app/src/Modules/Auth/Application/Command/Auth/RequestLoginCode/RequestLoginCodeCommand.php
app/src/Modules/Auth/Application/Command/Auth/VerifyLoginCode/VerifyLoginCodeHandler.php
app/src/Modules/Auth/Application/Command/Auth/VerifyLoginCode/VerifyLoginCodeResult.php
app/src/Modules/Auth/Application/Command/Auth/VerifyLoginCode/VerifyLoginCodeCommand.php
tests/Unit/Shared/Domain/Exception/ApiDomainExceptionTest.php
tests/Unit/Modules/System/Presentation/Temporal/PingTest.php
app/src/Modules/Auth/Application/Command/Auth/RefreshTokens/RefreshTokensHandler.php
app/src/Modules/Auth/Application/Command/Auth/RefreshTokens/RefreshTokensCommand.php
tests/Unit/Shared/Domain/Trait/HasTimestampsTest.php
app/src/Modules/Auth/Application/Command/Auth/ResolveLoginCode/ResolveLoginCodeCommand.php
app/src/Modules/Auth/Application/Command/Auth/ResolveLoginCode/LoginCodeOutcome.php
app/src/Modules/Auth/Application/Command/Auth/ResolveLoginCode/ResolveLoginCodeHandler.php
app/src/Modules/Auth/Application/Command/Auth/ResolveLoginCode/LoginCodeResolution.php
tests/Unit/Shared/Domain/Enum/LocaleTest.php
tests/Unit/Shared/Domain/ValueObject/AbstractUuidV7IdTest.php
tests/Feature/Modules/Auth/Console/AuthOpenApiGenerationTest.php
app/src/Modules/Auth/Application/Command/Auth/Logout/LogoutCommand.php
app/src/Modules/Auth/Application/Command/Auth/Logout/LogoutHandler.php
tests/Unit/Shared/Infrastructure/Exception/ConfigMappingExceptionTest.php
tests/Unit/Shared/Infrastructure/Cycle/ValueObjectCastTest.php
tests/Unit/Shared/Infrastructure/Cycle/LazyGhostMapperDefensiveTest.php
tests/Unit/Shared/Infrastructure/Cycle/LazyGhostReflectionRegistryTest.php
tests/Unit/Shared/Infrastructure/Cycle/ValueObjectCastDefensiveTest.php
app/src/Modules/Auth/Application/Command/Auth/SendLoginCode/SendLoginCodeCommand.php
app/src/Modules/Auth/Application/Command/Auth/SendLoginCode/SendLoginCodeHandler.php
tests/Unit/Shared/Infrastructure/Framework/Middleware/LocaleMiddlewareTest.php
tests/Unit/Shared/Infrastructure/Framework/Middleware/RateLimitMiddlewareTest.php
tests/Unit/Shared/Infrastructure/Configuration/SimpleConfigMapperTest.php
tests/Unit/Shared/Infrastructure/Configuration/RoadRunnerRabbitMqConfigTest.php
tests/Unit/Shared/Infrastructure/Configuration/ConfigMapperTest.php
tests/Unit/Shared/Infrastructure/Configuration/ComplexConfigMapperTest.php
app/src/Modules/Auth/Application/Command/Auth/CompleteRegistration/CompleteRegistrationCommand.php
app/src/Modules/Auth/Application/Command/Auth/CompleteRegistration/CompleteRegistrationHandler.php
app/src/Modules/Auth/Application/Message/LoginCodeRequested.php
tests/Unit/Modules/Auth/Presentation/Http/Middleware/AuthContextAttributeMiddlewareTest.php
tests/Unit/Modules/Auth/Infrastructure/Cycle/AuthTypecastTest.php
tests/Feature/Modules/Auth/Http/AuthHttpTest.php
tests/Feature/Modules/Auth/Repository/AuthRepositoryTest.php
tests/Unit/Modules/Auth/Domain/Entity/AuthEntityTest.php
tests/Unit/Modules/Auth/Domain/ValueObject/AuthValueObjectTest.php
tests/Unit/Modules/Auth/Infrastructure/Auth/RandomTokenGeneratorTest.php
tests/Unit/Modules/Access/Domain/AccessDomainTest.php
tests/Unit/Modules/Auth/Infrastructure/Hash/HmacSecretHasherTest.php
tests/Unit/Modules/Auth/Infrastructure/Auth/AuthTokenViewTest.php
tests/Unit/Modules/Auth/Infrastructure/Auth/UserActorProviderTest.php
app/src/Modules/Auth/Repository/AuthTokenRepository.php
app/src/Modules/Auth/Repository/LoginCodeRepository.php
app/src/Modules/Auth/Repository/RegistrationTicketRepository.php
tests/Feature/Modules/User/Application/CreateUserHandlerTest.php
tests/Feature/Modules/User/Application/FindUserForAuthHandlerTest.php
tests/Feature/Modules/User/Repository/UserRepositoryTest.php
tests/Kernel/Shared/Infrastructure/Framework/Bootloader/ExceptionHandlerBootloaderTest.php
tests/Feature/Modules/Auth/Presentation/SendLoginCodeJobTest.php
tests/Feature/Modules/Auth/Infrastructure/CycleTokenStorageTest.php
tests/Feature/Modules/Auth/Application/LoginCodeVerificationTest.php
tests/Feature/Modules/Auth/Application/SendLoginCodeHandlerTest.php
tests/Feature/Modules/Auth/Application/RefreshTokensAndLogoutTest.php
tests/Feature/Modules/Auth/Application/AuthApplicationTestCase.php
tests/Feature/Modules/Auth/Application/RequestLoginCodeHandlerTest.php
tests/Feature/Modules/Auth/Application/Fixture/RecordingMailer.php
tests/Feature/Modules/Auth/Application/Fixture/RecordingOutboxEventStore.php
tests/Feature/Modules/Auth/Application/Fixture/FakeSecretHasher.php
tests/Feature/Modules/Auth/Application/CompleteRegistrationHandlerTest.php

codex
Первый проход показал важную особенность: `git diff HEAD` не содержит почти весь Auth, потому что это новые untracked-файлы. Дальше читаю сами файлы напрямую и отдельно проверяю пункты про `LoginCodeValue` и TTL rate limit.
exec
/bin/zsh -lc "sed -n '260,820p' docs/plans/2026-06-15_13-25_auth-module.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
Совместимость: таблицы новые, backfill не нужен; `down()` дропает три таблицы. `users`,
`user_bans`, `reserved_nicknames` не меняются. Принятый риск (из research): payload
outbox-события `LoginCodeRequested` содержит код открытым текстом в `outbox_events` —
запись короткоживущая, relay обрабатывает её сразу (не накапливать).

### API и внешние контракты

Маршруты регистрируются аннотацией `#[Route(group:'api')]` в `AuthController`. Тела —
Spiral Filter, ответы — типизированные Resource в `DataResponse`. Ошибки — через
`ApiExceptionInterceptor` (401/422); 429 — напрямую из `RateLimitMiddleware`.

```text
POST /api/v1/auth/code/request          middleware: RateLimitMiddleware
  body  {email: string}
  200   DataResponse<RequestCodeResultResource{status:'sent'}>   -- всегда, юзера не раскрываем
  422   ошибка валидации Filter
  429   RateLimitMiddleware

POST /api/v1/auth/code/verify           middleware: RateLimitMiddleware
  body  {email: string, code: string(6 цифр)}
  200   DataResponse<VerifyResultResource{needsProfile:bool, tokens:?TokenPairResource,
                                          registrationTicket:?string}>
  401   AuthenticationException (нет/истёк/неверный код, attempts исчерпаны, вход запрещён)
  422   валидация Filter
  429   RateLimitMiddleware

POST /api/v1/auth/register              middleware: RateLimitMiddleware
  body  {ticket: string, name: string, nickname: string}
  200   DataResponse<TokenPairResource{accessToken, refreshToken, tokenType:'Bearer', expiresIn:int}>
  401   AuthenticationException (нет/истёк талон)
  422   ValidationException (ник занят/зарезервирован, email уже зарегистрирован) / валидация Filter
  429   RateLimitMiddleware

POST /api/v1/auth/refresh               middleware: RateLimitMiddleware
  body  {refreshToken: string}
  200   DataResponse<TokenPairResource>
  401   AuthenticationException (невалидный/истёкший refresh, не type=refresh)
  429   RateLimitMiddleware

POST /api/v1/auth/logout    (Authorization: Bearer <access>)
  middleware: AuthTransportWithStorageMiddleware → AuthContextAttributeMiddleware → RequireAuthenticatedMiddleware
  body  —
  200   DataResponse<LogoutResultResource{status:'ok'}>
  401   нет/невалидный access (RequireAuthenticatedMiddleware)
```

Внешние сервисы: письмо с кодом — через outbox → RabbitMQ → `SendLoginCodeJob` → Spiral
Mailer (Mailpit в dev). Bearer-транспорт `Authorization: Bearer %s`.

## Фазы выполнения

### 1. User/Application: CreateUser и FindUserForAuth

Цель: дать модулю `Auth` единственную внешнюю зависимость — два сценария создания и поиска
пользователя на границе `User/Application`, не трогая Domain `User`.

Что сделать:
- `app/src/Modules/User/Application/Command/User/CreateUser/`: `CreateUserCommand`
  (`email, name, nickname, locale` — примитивы), `CreateUserHandler` `#[Transactional]`,
  `CreateUserResult{userId}`. Handler: строит VO (`Email::fromString`, `UserName::fromString`,
  `UserNickname::fromString`, `Locale::from`), проверяет `existsByEmail` → `ValidationException`
  (`app.user.email_taken`, 422), `existsByNickname`/`ReservedNicknameRepository::isReserved`
  → `ValidationException` (`app.user.nickname_taken`), `User::create(...)` затем
  `User::confirmEmail()` (→ Active), `entityManager->persist`+`run`, возвращает `UserId`.
  Вызывается и автономно, и вложенно из `CompleteRegistration` (тогда работает в SAVEPOINT
  внешней транзакции — при ошибке откат и талон не гасится).
- `app/src/Modules/User/Application/Query/User/FindUserForAuth/`: `FindUserForAuthQuery{email}`,
  `FindUserForAuthHandler`, DTO `Application/Dto/UserAuthView{userId:string, canSignIn:bool}`.
  Handler: `UserRepository::findByEmail`; нет юзера → `null`; есть → `UserAuthView`
  (`canSignIn = isActive()`).
- Хендлеры резолвятся контейнером, отдельный бутлоадер не требуется (как в Media).
- Переводы `app/locale/{ru,en}/user.php`: `app.user.email_taken`, `app.user.nickname_taken`.

Результат: `User/Application` отдаёт `CreateUser`/`FindUserForAuth`; Domain `User` не изменён.

Сценарии тестирования:
- CreateUser: успех (создан Active, вернулся UserId); email занят → 422; ник занят → 422;
  ник зарезервирован → 422; вложенный вызов с ошибкой → внешняя транзакция откатывается.
- FindUserForAuth: активный (canSignIn=true), Banned/Deleted (canSignIn=false), отсутствует (null).

Проверка: `make test` (новые unit/kernel-тесты `User/Application`), `make phpstan`.

### 2. Auth Domain: VO, enum, сущности, репозитории, typecast, миграция

Цель: доменная модель и доступ к данным трёх таблиц.

Что сделать:
- VO `Auth/Domain/ValueObject/`: `LoginCodeId`, `RegistrationTicketId`, `AuthTokenId`,
  `SessionId` (все `extends AbstractUuidV7Id`); `EmailAddress` (норм. идентична `User\Email`);
  `SecretHash` (`fromString`/`value` — HMAC-строка); `TokenHash`
  (`fromRawToken(string)`→sha256, `fromString`, `value`; конвенция typecast возьмёт `fromString`);
  `CodeAttempts` (`initial()`, `fromInt(int)`, `increment()`, `isExhausted()` ≥5, `value():int`
  — `fromInt` обязателен для `ValueObjectCast`); `LoginCodeValue` (валидация 6 цифр, `value()` —
  транзиентный, не хранится); `Expiration` (`after(now,seconds)`, `value():DateTimeImmutable`,
  `isExpired(now)`); `Consumption` (`notConsumed()`/`at(now)`, `isConsumed()`, `value():?DateTimeImmutable`).
- Enum `Auth/Domain/Enum/AuthTokenType` (`Access='access'`, `Refresh='refresh'`).
- Коллекция `Auth/Domain/Collection/AuthTokenCollection extends Illuminate\Support\Collection`
  (`@extends Collection<int, AuthToken>`).
- Сущности `Auth/Domain/Entity/`:
  - `LoginCode` (`issue(LoginCodeId, EmailAddress, SecretHash codeHash, Expiration, now)`;
    методы `registerFailedAttempt()`, `consume(now)`; predicate `isExpired(now)`,
    `attemptsExhausted()`, `isConsumed()`).
  - `RegistrationTicket` (`issue(...)`, `consume(now)`, `isExpired(now)`, `isConsumed()`; доступ
    к email для регистрации — property hook/предикат `email`/`emailAddress`).
  - `AuthToken` (`issue(AuthTokenId, UserId, SessionId, AuthTokenType, TokenHash, Expiration)`,
    `isExpired(now)`, `isAccess()`/`isRefresh()`).
  Все — `typecast: [Typecast::class, ValueObjectCast::class]`, колонки uuid/string/int — по
  конвенции (`CodeAttempts` через `fromInt`/`value`), `expires_at`/`consumed_at` — отдельные
  `ColumnValueTypecast` (`Auth/Infrastructure/Cycle/`: `ExpirationTypecast` datetime,
  `ConsumptionTypecast` nullable↔null-object, uncast `notConsumed()`→NULL), `HasTimestamps`.
- Репозитории `Auth/Repository/` (read-only, доменные методы; `forUpdate` — в цепочке
  `select()`):
  - `LoginCodeRepository::findActiveByEmailForUpdate(EmailAddress): ?LoginCode`
    (consumed_at IS NULL и не истёк, последний по id, `->forUpdate()`),
    `findActiveByEmail(EmailAddress): ?LoginCode` (без блокировки — для троттлинга 60с по
    активному коду; consumed коды не учитываются).
  - `RegistrationTicketRepository::findActiveByHashForUpdate(SecretHash): ?RegistrationTicket`.
  - `AuthTokenRepository::findByHash(TokenHash): ?AuthToken` (обычное чтение — для `load`),
    `findByHashForUpdate(TokenHash): ?AuthToken` (для rotate),
    `findBySessionIdForUpdate(SessionId): AuthTokenCollection` (для rotate/revoke).
- Миграция `create_auth_domain_tables` (три таблицы; индексы email/user_id/session_id;
  `token_hash` UNIQUE).

Результат: сущности гидрируются/сохраняются, миграция применяется и откатывается.

Сценарии тестирования: VO (валидация формата кода/email, `CodeAttempts` increment/isExhausted/
fromInt, `Expiration::isExpired`, `Consumption` null-object); **инвариант: `EmailAddress::fromString(x)
->value()` === нормализация `User\Email` для тех же входов**; сущности (issue/consume/attempt);
репозитории (find* на тестовой БД, forUpdate-чтение); миграция up/down; `token_hash` unique.

Проверка: `make test` (unit VO/Entity + feature-репозитории), `make phpstan`.

### 3. Auth Infrastructure: токены, хэширование, actor provider

Цель: технические сервисы — генерация/хранение токенов, HMAC, провайдер актора.

Что сделать:
- `Auth/Application/Contract/`: `SecretHasherContract` (`hash(string):string`,
  `verify(string $secret, string $hash):bool` constant-time), `TokenGeneratorContract`
  (`generate():string` — 32 случайных байта → base64url), `AuthTokenStorageContract`
  (`issuePair(UserId):IssuedTokenPair`, `rotate(string $refreshRaw):IssuedTokenPair`,
  `revokeSession(SessionId):void`).
- `Auth/Application/Dto/IssuedTokenPair{accessToken, refreshToken, expiresIn:int}`.
- `Auth/Infrastructure/Hash/HmacSecretHasher` (HMAC-SHA256 с ключом
  `EncryptionInterface::getKey()`; `verify` через `hash_equals`).
- `Auth/Infrastructure/Auth/RandomTokenGenerator` (`random_bytes(32)`).
- `Auth/Infrastructure/Auth/AuthTokenView implements TokenInterface` — адаптер: хранит
  исходный raw (`getID()` возвращает его), `getPayload()` = `{userID, type, sessionID}`,
  `getExpiresAt()`.
- `Auth/Infrastructure/Auth/CycleTokenStorage` — реализует `Spiral\Auth\TokenStorageInterface`
  И `AuthTokenStorageContract`:
  - `load(string $id): ?TokenInterface` — `TokenHash::fromRawToken`, `findByHash` (БЕЗ forUpdate),
    не истёк → `AuthTokenView` (getID() = $id); просрочен/нет → null.
  - `create(array $payload, ?expiresAt)` — генерит raw, строит `AuthToken::issue` из payload,
    `persist`+`run` (self-flushing: vendor `AuthMiddleware::closeContext` может звать
    `create`/`delete` напрямую и ждёт реального flush); возвращает `AuthTokenView` (getID() = raw).
  - `delete(TokenInterface)` — `findByHashForUpdate` → `entityManager->delete`+`run` (self-flushing).
  - `issuePair(UserId)` — `SessionId::generate`, `create` access (TTL 1ч) + refresh (TTL 60д)
    с общим sessionId, вернуть `IssuedTokenPair` (raw-строки + expiresIn=3600).
  - `rotate(refreshRaw)` — `findByHashForUpdate`, проверка type=refresh и срока (иначе
    `AuthenticationException` 401), `revokeSession(sessionId)`, `issuePair(userId)`.
  - `revokeSession(SessionId)` — `findBySessionIdForUpdate` → delete всех строк → `run`.
- `Auth/Infrastructure/Auth/UserActorProvider implements ActorProviderInterface`:
  `getActor(TokenInterface)` — только `payload.type==='access'` → `AuthenticatedUser(UserId)`
  (`Auth/Infrastructure/Auth/AuthenticatedUser`); иначе null.

Результат: токены выдаются/проверяются/ротируются/отзываются; refresh как Bearer не даёт актора;
`load` не берёт лишних блокировок.

Сценарии тестирования: HMAC hash/verify (constant-time, неверный → false); генератор
(длина/уникальность); CycleTokenStorage `issuePair`/`load`(access ok, истёкший access → null,
refresh→null для актора)/`rotate`(старая пара удалена, новая выдана, старый refresh→null)/
`revokeSession`; `AuthTokenView::getID()` === поданный raw; `UserActorProvider` (access→actor,
refresh→null).

Проверка: `make test` (feature на тестовой БД), `make phpstan`.

### 4. Auth Application: сценарии CQRS + outbox-сообщение

Цель: use-case-ы записи и постановка письма в outbox.

Что сделать (каждый use-case — папка `Auth/Application/Command/Auth/{Action}/` с
`*Command`, `*Handler`, `#[LogOperation]`, при необходимости `*Result`):
- `RequestLoginCode` (`#[Transactional]`) — язык письма = `requestLocale` из команды; в
  `User/Application` НЕ ходит. Грузит активный код `findActiveByEmail`; если он есть и создан
  < 60с назад → ничего не делаем, 200 (троттлинг). Иначе гасим прежний активный код, генерим
  6-значный код, `LoginCode::issue` (codeHash =
  `SecretHasher::hash(code)`), `OutboxEventStore::add(new LoginCodeRequested{email, code,
  locale})`, `persist`+`run`. Результат — всегда успех (200).
- `ResolveLoginCode` (`#[Transactional]`) — `findActiveByEmailForUpdate`; маппинг в
  `LoginCodeResolution{outcome: LoginCodeOutcome, tokens:?IssuedTokenPair, registrationTicket:?string}`:
  null→NoCode; истёк→Expired; `attemptsExhausted`→Exhausted; `SecretHasher::verify` false →
  `registerFailedAttempt()`+persist+run → Wrong; ok → `consume`; `FindUserForAuth`:
  есть+canSignIn → `AuthTokenStorage::issuePair` → Verified+tokens; есть+!canSignIn → NotAllowed;
  нет → `RegistrationTicket::issue` → NeedsProfile+ticket. `run` финально.
- `VerifyLoginCode` (оркестратор, БЕЗ `#[Transactional]`) — диспатчит `ResolveLoginCode`;
  outcome ∈ {NoCode,Expired,Exhausted,Wrong,NotAllowed} → `AuthenticationException` (401,
  ключи `app.auth.invalid_code` / `app.auth.sign_in_not_allowed`); Verified → `VerifyLoginCodeResult`
  с токенами (needsProfile=false); NeedsProfile → результат с ticket (needsProfile=true).
- `CompleteRegistration` (`#[Transactional]`) — `findActiveByHashForUpdate(HMAC(ticket))`;
  нет/истёк → 401; `consume`; читает email из талона; диспатчит `User/Application CreateUser(
  email, name, nickname, requestLocale)` → `UserId`; `AuthTokenStorage::issuePair`; `run`.
- `RefreshTokens` (`#[Transactional]`) — `AuthTokenStorage::rotate(refreshToken)`.
- `Logout` (`#[Transactional]`) — `AuthTokenStorage::revokeSession(SessionId::fromString(authSessionId))`.
- `Auth/Application/Message/LoginCodeRequested implements OutboxMessage`
  (`public string $email; public string $code; public string $locale;`).

Результат: бизнес-сценарии собраны; `attempts++` на неверном коде коммитится; письмо ставится
в outbox в одной транзакции с кодом.

Сценарии тестирования (хендлеры; дублёры контрактов и User/Application): request (outbox получил
сообщение с локалью запроса; троттлинг 60с; в User/Application не ходим); resolve/verify (успех
существующего → пара; новый → талон; **неверный код → attempts++ сохранён И 401** (проверка, что
инкремент пережил throw); истёк/исчерпан → 401; attempts ровно =5 — граница; Banned → 401);
register (успех Active; талон истёк → 401; ник занят → 422 и талон не погашен); refresh (успех;
невалидный/чужой → 401); logout (отзыв сессии).

Проверка: `make test`, `make phpstan`.

### 5. Доставка письма: Job + Mailer + переводы

Цель: потребитель outbox шлёт письмо с кодом на нужном языке.

Что сделать:
- `Auth/Application/Command/Auth/SendLoginCode/`: `SendLoginCodeCommand{email, code, locale}`,
  `SendLoginCodeHandler` — формирует и отправляет письмо через Spiral Mailer (тема/тело из
  переводов по `locale` с fallback `LocaleConfig.default`; код в теле). Без `#[Transactional]`.
- `Auth/Presentation/Job/SendLoginCodeJob extends JobHandler` — `invoke(OutboxQueueEnvelope,
  ..., OutboxMessageLoaderContract, CommandBusInterface, SendLoginCodeHandler, LoggerInterface)`:
  грузит `LoginCodeRequested`, диспатчит `SendLoginCodeCommand` (эталон `ProcessMediaJob`);
  сбой Mailer → WARN + RetryException.
- Переводы `app/locale/{ru,en}/auth.php`: тема и текст письма с плейсхолдером кода + ключи
  ошибок входа (`app.auth.invalid_code`, `app.auth.sign_in_not_allowed`,
  `app.auth.invalid_ticket`, `app.auth.invalid_refresh`).
- Регистрация пары `LoginCodeRequested → SendLoginCodeJob` — в `AuthBootloader` (фаза 7).

Результат: при relay письмо с кодом уходит в Mailpit на языке запроса.

Сценарии тестирования: Job грузит сообщение и диспатчит команду; `SendLoginCodeHandler` строит
письмо с кодом и локалью (через тестовый mailer/transport); сбой Mailer → WARN+retry; ключи
переводов есть для ru и en.

Проверка: `make test` (feature на Job/Mailer), `make phpstan`.

### 6. Shared: rate limit (per-route middleware + 429)

Цель: переиспользуемое ограничение частоты по IP+маршрут, без правки общих интерсепторов.

Что сделать:
- `Shared/Infrastructure/Framework/Middleware/RateLimitMiddleware implements MiddlewareInterface`
  — конструктор `(int $maxAttempts, int $perSeconds, CacheStorageProviderInterface, TranslatorInterface)`;
  использует ИМЕНОВАННОЕ хранилище `redis` (`->storage('redis')`), а не дефолтный кэш (в dev
  `CACHE_STORAGE=local`, иначе лимит был бы локальным на процесс); `process()`: ключ =
  `sha256(clientIp . '|' . routeName)` (IP из `REMOTE_ADDR`; хэш, т.к. PSR-16 ключи не дружат с
  `/`,`:`,IPv6); читает/инкрементит счётчик фиксированного окна (TTL=perSeconds на первом
  инкременте). Превышение → возвращает `ErrorResponse(message: <trans app.shared.rate_limit_exceeded>,
  code: 429)->toResponse()` (`HttpStatus::TooManyRequests`); иначе `$handler->handle()`.
- Переводы `app/locale/{ru,en}/shared.php`: `app.shared.rate_limit_exceeded`.
- Применение — per-route через `#[Route(middleware: [new Autowire(RateLimitMiddleware::class,
  ['maxAttempts'=>N,'perSeconds'=>M])])]` (в фазе 7 на маршрутах Auth).

Результат: маршрут с навешенным `RateLimitMiddleware` ограничивается по IP; превышение → 429 JSON.
Общий `AppBootloader::INTERCEPTORS` не меняется (нет регрессии порядка интерсепторов).

Сценарии тестирования: в пределах лимита проходит; при превышении → 429 с корректным телом;
ключ зависит от IP и маршрута; TTL сбрасывает окно; HTTP-тест 429 на тестовом маршруте.

Проверка: `make test`, `make phpstan`.

### 7. Auth Presentation + каркас + интеграционные тесты маршрутов

Цель: HTTP-вход, подключение auth-каркаса и сквозные тесты всех маршрутов.

Что сделать:
- Filter `Auth/Presentation/Http/Filter/`: `RequestCodeFilter{email}`,
  `VerifyCodeFilter{email, code}`, `RegisterFilter{ticket, name, nickname}`,
  `RefreshFilter{refreshToken}` (обязательные поля без дефолтов, `#[Assert\NotBlank]`,
  `code` — `#[Assert\Regex]` на 6 цифр).
- Resource `Auth/Presentation/Http/Resource/`: `TokenPairResource`, `VerifyResultResource`
  (nullable `tokens`/`registrationTicket`), `RequestCodeResultResource`, `LogoutResultResource`
  (наследуют `AbstractResource`).
- `Auth/Presentation/Http/Controller/AuthController` — 5 тонких методов, `group:'api'`,
  `@return DataResponse<...>` PHPDoc на каждом; `code/request`, `code/verify`, `register`,
  `refresh` несут per-route `middleware: [new Autowire(RateLimitMiddleware::class, [...])]`;
  `code/request` и `register` кладут в команду `requestLocale` = `$translator->getLocale()`
  (fallback `LocaleConfig.default`); `logout` — per-route `middleware:
  [AuthTransportWithStorageMiddleware(header,cycle через Autowire), AuthContextAttributeMiddleware,
  RequireAuthenticatedMiddleware]` и читает `#[Attribute('authUserId')]`, `#[Attribute('authSessionId')]`.
  Методы строят Command/диспатчат через `CommandBusInterface`, маппят в Resource/`DataResponse`.
- Middleware `Auth/Presentation/Http/Middleware/` (middleware НЕ проходит через
  `ApiExceptionInterceptor`, поэтому исключения тут не конвертируются — отдаём 401 JSON напрямую
  через `ErrorResponse(...)->toResponse()`):
  `AuthContextAttributeMiddleware` (инжектит `AuthContextInterface` конструктором (Proxy,
  http-scope); если actor есть и payload содержит `userID`/`sessionID` при `type=access` — ставит
  request-атрибуты `authUserId`/`authSessionId`; при отсутствии/битом payload атрибуты не ставит и
  не падает), `RequireAuthenticatedMiddleware` (нет actor / нет `authUserId` → 401 JSON).
- `Auth/Infrastructure/Bootloader/AuthBootloader`:
  - `BINDINGS`: `SecretHasherContract→HmacSecretHasher`, `TokenGeneratorContract→RandomTokenGenerator`,
    `AuthTokenStorageContract→CycleTokenStorage`.
  - `defineDependencies(): [HttpAuthBootloader::class, SpiralAuthBootloader::class]` (порядок init).
  - `init(HttpAuthBootloader $httpAuth, SpiralAuthBootloader $auth)` — alias
    `use Spiral\Bootloader\Auth\AuthBootloader as SpiralAuthBootloader` (иначе конфликт с этим же
    классом): `$httpAuth->addTransport('header', new HeaderTransport('Authorization','Bearer %s'))`,
    `$httpAuth->addTokenStorage('cycle', CycleTokenStorage::class)`,
    `$auth->addActorProvider(UserActorProvider::class)`.
  - `boot(OutboxJobRegistryContract)`: `register(LoginCodeRequested::class, SendLoginCodeJob::class)`.
  - `defineDependencies(): [HttpAuthBootloader::class]` — vendor `HttpAuthBootloader` сам тянет
    vendor `AuthBootloader` и `HttpBootloader`.
- `Kernel::defineBootloaders()`: добавить ТОЛЬКО
  `App\Modules\Auth\Infrastructure\Bootloader\AuthBootloader::class` (vendor auth-бутлоадеры
  подтягиваются через его `defineDependencies`, дублировать в Kernel не нужно).
- `app/config/queue.php`: зарегистрировать `SendLoginCodeJob` так же, как прочие outbox-Job
  (в `handlers` и с `OutboxQueueSerializer` в `serializers`) — иначе очередь не обработает или
  неверно сериализует задачу.
- Проверить, что аннотированные `#[Route]` сканируются (по System-контроллерам); если нет —
  добавить `Spiral\Router\Bootloader\AnnotatedRoutesBootloader::class`.
- OpenAPI: расширить scan `OpenApiConfig` на `Auth/Presentation/Http` (сейчас сканируется только
  `System/Presentation/Http`), затем прогнать `php app.php openapi:generate`, проверить
  `public/openapi/openapi.yml` (в т.ч. корректность схемы `VerifyResultResource` с nullable-полями
  и наличие 5 маршрутов Auth).

Результат: все 5 маршрутов отвечают по контракту; Bearer-аутентификация (на logout) и rate limit
активны; группа `api` и общие интерсепторы не тронуты.

Сценарии тестирования (интеграционные HTTP по эталону `ApiErrorHttpTest`, `fakeHttp()`):
- `code/request`: 200 на новый и существующий email; письмо ушло в outbox; 422 на пустой email.
- `code/verify`: новый email → `needsProfile:true` + ticket; существующий → пара токенов;
  неверный код → 401 и attempts увеличился; повтор после исчерпания attempts (=5) → 401.
- `register`: валидный ticket → пара токенов и пользователь Active; занятый ник → 422 (талон
  остаётся валидным для повтора); истёкший/неверный ticket → 401.
- `refresh`: валидный refresh → новая пара, старый refresh больше не работает (401);
  access как refresh → 401.
- `logout`: с Bearer access → 200 и токены сессии отозваны (тот же access потом → 401);
  без токена → 401; refresh как Bearer → 401; повторный logout тем же access → 401.
- rate limit: превышение лимита на `code/request` → 429.
- регрессия: существующие System api-тесты (`/test/api/...`) проходят (группа `api` не менялась).
- kernel-тест: биндинги `AuthBootloader` (`SecretHasherContract`/`TokenGeneratorContract`/
  `AuthTokenStorageContract`) и регистрация транспорта/хранилища/actor-provider резолвятся.
- конфигурация: `app/config/queue.php` знает `SendLoginCodeJob` (+`OutboxQueueSerializer`);
  OpenAPI после генерации содержит 5 маршрутов Auth; `VerifyResultResource` валиден в YAML.
- middleware logout: битый/чужой payload токена → 401 JSON (а не 500/notice); порядок
  middleware (transport → attribute → require) даёт `authSessionId` на валидном access.
- гонки (best-effort, через подготовленное состояние БД/forUpdate): повторный verify одного
  кода не проходит дважды; параллельный refresh не выдаёт две живые пары; при отсутствии
  активного кода на новый email возможны два кода (принятый риск — verify берёт последний активный).

Проверка: полный `make test` (все unit/feature, интеграция всех маршрутов, 100% покрытие),
`make phpstan`, `php app.php openapi:generate`.

## Тесты

Стратегия `after_each_phase`: в конце каждой фазы добавляются и прогоняются тесты на её
артефакты (`make test`), фаза не считается готовой без зелёного прогона. Unit — для VO,
enum, сущностей, чистой логики хендлеров (дублёры контрактов: `createStub`+`willReturn*`
для возвратов, `createMock`+`expects` — только где проверяем факт вызова). Feature —
репозитории (включая forUpdate), `CycleTokenStorage`, Job/Mailer, kernel-тесты биндингов.
Интеграционные HTTP (`fakeHttp()`) — каждый маршрут с позитивными и негативными ветками
(обязательно по rules.md), плюс регрессия System api-тестов. Особо покрыть: `attempts++`
переживает 401, граница attempts=5, истёкший access в `load`, повторный logout, отзыв сессии,
эквивалентность нормализации `EmailAddress` и `User\Email`. Итог фазы 7 — 100% покрытие.

## Логирование

Стратегия `debug_precise`: `#[LogOperation]` на Command-хендлерах (debug-лог старта и времени).
Точечные DEBUG-логи ключевых шагов с контекстом (email, sessionId, userId, причина): код
запрошен/выдан, попытка кода неверна, код подтверждён, талон создан/погашен, регистрация
завершена, пара выдана/ротирована/сессия отозвана, rate limit сработал. INFO — успешная
регистрация (ключевое бизнес-событие). WARN — только инфраструктурные сбои (ошибка отправки
письма в `SendLoginCodeJob`). Неверный/истёкший код, невалидный токен, исчерпанные попытки —
нормальный пользовательский flow → DEBUG (rules.md), не WARN. Сырые коды и токены в логи не
пишутся (логируются email и идентификаторы).

## Документация и эксплуатация

- `.env`/`.env.sample`: заданы `ENCRYPTER_KEY` (используется как HMAC-секрет), `MAILER_DSN`
  (Mailpit в dev), очередь RabbitMQ — переменные уже есть, новых не требуется.
- `AUTH_TOKEN_TRANSPORT`/`AUTH_TOKEN_STORAGE` не задаём: транспорт/хранилище привязаны явно в
  per-route middleware `logout` (`header`/`cycle`), дефолты vendor (`cookie`/`session`) не влияют.
- Эксплуатация: `outbox:relay --loop` (один экземпляр) — письма с кодом идут через него; payload
  содержит код открытым текстом (короткоживущая запись, обрабатывать сразу). Проверить Mailpit.
- OpenAPI: `public/openapi/openapi.yml` обновить `openapi:generate`; маршруты Auth появятся
  в Swagger `/api/docs`.
- Переводы: добавлены `app/locale/{ru,en}/auth.php`, ключи в `user.php` и `shared.php`.
- Rate limit использует именованное Redis-хранилище кэша; в dev `CACHE_STORAGE=local` — для
  общего лимита между процессами middleware берёт `redis` storage явно.
- Принятые риски: при точной гонке двух первых `code/request` на новый email возможны два
  активных кода (два письма; verify берёт последний активный) — частичный unique-индекс не
  вводим ради контракта «всегда 200»; конкурентная уникальность email/ника в `CreateUser`
  может дать 500 (допустимо по rules.md, check-then-act).

## Изменения после мета-ревью

### После моделей (haiku, sonnet, opus)

- **+ Добавлено:**
  - Разделение `VerifyLoginCode` на нетранзакционный оркестратор + `#[Transactional]`
    `ResolveLoginCode` — иначе `attempts++` на неверном коде откатывался бы вместе с `throw 401`
    (блокер: защита от перебора не работала).
  - `RateLimitMiddleware` (per-route HTTP-middleware, сам строит 429) вместо
    `#[RateLimited]`+`RateLimitInterceptor`: интерцептор не получал client IP из `CallContext`,
    имел проблемы порядка и менял общие `INTERCEPTORS`.
  - `AuthTokenView::getID()` обязан возвращать исходный raw из аргумента `load` (иначе vendor
    `commitToken` портит заголовок ответа); раздельные репозиторные методы `findByHash`
    (чтение для `load`) и `findByHashForUpdate` (для rotate/revoke).
  - Тесты-инварианты: `attempts++` переживает 401, граница attempts=5, истёкший access в `load`,
    повторный logout, эквивалентность нормализации `EmailAddress`↔`User\Email`, регрессия System
    api-тестов, kernel-тест биндингов `AuthBootloader`.
  - `token_hash` UNIQUE; `CodeAttempts::fromInt`; `RegistrationTicket` отдаёт email;
    проверка сканирования аннотированных `#[Route]` (иначе `AnnotatedRoutesBootloader`).
- **~ Изменено:**
  - `load()` access-токена — обычное чтение БЕЗ `forUpdate` (блокировка только в путях
    изменения); `forUpdate` применяется в цепочке `select()`, не через `Repository::forUpdate()`.
  - Auth-middleware вешаются per-route на `logout`, а не на всю группу `api` (нет регрессии
    System-тестов и лишних `load()` на публичных маршрутах).
  - `AuthBootloader`: alias `SpiralAuthBootloader` + `defineDependencies` (конфликт имён и
    порядок init транспорта/хранилища/actor-provider).
  - `UserAuthView{userId, canSignIn}` — убраны неиспользуемые `status` и `locale`.
  - `CompleteRegistration→CreateUser` — задокументировано поведение вложенной транзакции
    (SAVEPOINT): ошибка `CreateUser` откатывает и consume талона (талон переиспользуем).
- **− Убрано:**
  - `#[RateLimited]`-атрибут и `RateLimitInterceptor`, изменение `AppBootloader::INTERCEPTORS`,
    `TooManyRequestsException` (429 строит middleware напрямую).
- **Отклонено:**
  - «`run()` в `CycleTokenStorage` нарушает rules.md» (sonnet) — отклонено: адаптер хранилища
    токенов — инфраструктурный orchestrator (аналог исключения для outbox relay), несколько
    `run()` в одной транзакции безопасны (подтвердил opus, без двойного flush). Реальная
    проблема была не во flush, а в `forUpdate` на `load` — она исправлена.

## Реакция на ревью

Кросс-CLI ревью: codex (`2026-06-15_13-25_auth-module_review.md`).

Принято (внесено в план):

```text
[блокер] Middleware вне ApiExceptionInterceptor → исключения не конвертируются
  -> RequireAuthenticatedMiddleware/AuthContextAttributeMiddleware отдают 401 JSON
     напрямую через ErrorResponse->toResponse() (фаза 7).
[блокер] SendLoginCodeJob не зарегистрирован в app/config/queue.php
  -> добавлен шаг: Job в handlers + OutboxQueueSerializer в serializers (фаза 7).
[блокер] OpenAPI сканирует только System/Presentation/Http
  -> расширить scan OpenApiConfig на Auth/Presentation/Http; проверка 5 маршрутов (фаза 7).
[блокер] CycleTokenStorage.create()/delete() должны self-flush (vendor зовёт delete напрямую)
  -> публичные create()/delete() делают persist/delete + run() (фаза 3).
[блокер] code/request: FOR UPDATE не блокирует отсутствующую строку
  -> троттлинг по findActiveByEmail; verify берёт последний активный код; двойной код —
     принятый риск без partial-unique (контракт «всегда 200»), задокументирован.
[риск] revokeSession без блокировки -> findBySessionIdForUpdate (фазы 2,3).
[риск] findLatestByEmail мог вернуть consumed -> findActiveByEmail (только активный) (фазы 2,4).
[риск] RateLimit: ключ с / : IPv6 -> sha256(ip|routeName); ответ через ErrorResponse (фаза 6).
[риск] dev CACHE_STORAGE=local -> middleware берёт именованный redis storage явно (фаза 6, эксплуатация).
[риск] AuthContextAttributeMiddleware: бить payload без userID/sessionID -> 401 JSON, не 500 (фаза 7).
[риск] Конкурентная уникальность в CreateUser может дать 500 -> назван принятый риск (эксплуатация).
[нарушение] AuthTokenRepository::findByUserId — мёртвый код (нет revoke-all) -> убран (фаза 2).
[риск] Дублирование vendor HttpAuthBootloader в Kernel и defineDependencies
  -> в Kernel только модульный AuthBootloader; зависимости тянет он сам (фаза 7).
[тесты] Гонки + конфиг-тесты (queue/OpenAPI/bootloader) + middleware-порядок -> добавлены в фазу 7.
```

Подтверждено ревью как корректное (изменений не требует): `load()` без `forUpdate`,
`AuthTokenView::getID()=raw`, разделение `VerifyLoginCode`/`ResolveLoginCode` ради коммита
`attempts`, `forUpdate` в цепочке `select()`, вложенная транзакция `CompleteRegistration→CreateUser`
через SAVEPOINT.

Отклонено/вне правок: отдельный partial-unique индекс на активный код по email — не вводим
(сломал бы контракт «code/request всегда 200» ради крайне редкой гонки; вместо этого verify
берёт последний активный код, двойное письмо принято как риск).

## Прогресс выполнения
Журнал: `docs/executions/2026-06-15_14-17_auth-module.md`

- [x] Фаза 1: User/Application — CreateUser и FindUserForAuth
- [x] Фаза 2: Auth Domain — VO, enum, сущности, репозитории, typecast, миграция
- [x] Фаза 3: Auth Infrastructure — токены, хэширование, actor provider
- [x] Фаза 4: Auth Application — CQRS сценарии + outbox-сообщение
- [x] Фаза 5: Доставка письма — Job + Mailer + переводы
- [ ] Фаза 6: Shared — rate limit (per-route middleware + 429)
- [x] Фаза 7: Auth Presentation + каркас + интеграционные тесты маршрутов

exec
/bin/zsh -lc "sed -n '260,620p' docs/rules.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
exec
/bin/zsh -lc "sed -n '1,220p' app/src/Shared/Infrastructure/Framework/Middleware/RateLimitMiddleware.php && sed -n '1,260p' tests/Unit/Shared/Infrastructure/Framework/Middleware/RateLimitMiddlewareTest.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Framework\Middleware;

use GianTiaga\SpiralOpenApi\Response\Enum\HttpStatus;
use GianTiaga\SpiralOpenApi\Response\ErrorResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Spiral\Cache\CacheStorageProviderInterface;
use Spiral\Translator\TranslatorInterface;

/**
 * Переиспользуемое ограничение частоты по IP + маршрут. Навешивается per-route через Autowire
 * с аргументами maxAttempts/perSeconds. Счётчик в именованном Redis-хранилище (общий между
 * процессами middleware; дефолтный кэш в dev — local, поэтому берём redis явно). Превышение →
 * 429 JSON напрямую (middleware вне цепочки controller-интерсепторов, исключение тут не
 * сконвертируется). Незначительный перерасчёт при гонке get/set допустим.
 */
final readonly class RateLimitMiddleware implements MiddlewareInterface
{
    private const string STORAGE = 'redis';

    public function __construct(
        private int $maxAttempts,
        private int $perSeconds,
        private CacheStorageProviderInterface $cacheStorageProvider,
        private TranslatorInterface $translator,
    ) {}

    #[\Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $cache = $this->cacheStorageProvider->storage(self::STORAGE);
        $key = $this->counterKey($request);
        $storedAttempts = $cache->get($key);
        $currentAttempts = \is_int($storedAttempts) ? $storedAttempts : 0;

        if ($currentAttempts >= $this->maxAttempts) {
            return $this->tooManyRequestsResponse();
        }

        $cache->set(key: $key, value: $currentAttempts + 1, ttl: $this->perSeconds);

        return $handler->handle($request);
    }

    private function counterKey(ServerRequestInterface $request): string
    {
        $remoteAddress = $request->getServerParams()['REMOTE_ADDR'] ?? '';
        $clientIp = \is_string($remoteAddress) ? $remoteAddress : '';

        return \hash(algo: 'sha256', data: $clientIp . '|' . $request->getUri()->getPath());
    }

    private function tooManyRequestsResponse(): ResponseInterface
    {
        $errorResponse = new ErrorResponse(
            message: $this->translator->trans(id: 'app.shared.rate_limit_exceeded', parameters: [], domain: 'shared'),
            code: HttpStatus::TooManyRequests->value,
        );

        return $errorResponse->withStatus(HttpStatus::TooManyRequests)->toResponse();
    }
}
<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Framework\Middleware;

use App\Shared\Infrastructure\Framework\Middleware\RateLimitMiddleware;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\SimpleCache\CacheInterface;
use Spiral\Cache\CacheStorageProviderInterface;
use Spiral\Cache\Storage\ArrayStorage;
use Spiral\Translator\TranslatorInterface;

final class RateLimitMiddlewareTest extends TestCase
{
    public function testAllowsRequestsWithinLimitAndBlocksOverLimit(): void
    {
        $middleware = $this->middleware(maxAttempts: 2, cache: new ArrayStorage());
        $request = $this->request(clientIp: '10.0.0.1', path: '/api/v1/auth/code/request');

        self::assertSame(200, $middleware->process($request, $this->handler())->getStatusCode());
        self::assertSame(200, $middleware->process($request, $this->handler())->getStatusCode());

        $blocked = $middleware->process($request, $this->handler());

        self::assertSame(429, $blocked->getStatusCode());
        $body = (string) $blocked->getBody();
        self::assertStringContainsString('"code":429', $body);
        self::assertStringContainsString('Слишком много запросов', $body);
    }

    public function testSeparatesCountersByClientIp(): void
    {
        $middleware = $this->middleware(maxAttempts: 1, cache: new ArrayStorage());

        self::assertSame(200, $middleware->process($this->request('1.1.1.1', '/p'), $this->handler())->getStatusCode());
        self::assertSame(429, $middleware->process($this->request('1.1.1.1', '/p'), $this->handler())->getStatusCode());
        self::assertSame(200, $middleware->process($this->request('2.2.2.2', '/p'), $this->handler())->getStatusCode());
    }

    public function testSeparatesCountersByRoute(): void
    {
        $middleware = $this->middleware(maxAttempts: 1, cache: new ArrayStorage());

        self::assertSame(200, $middleware->process($this->request('9.9.9.9', '/route-a'), $this->handler())->getStatusCode());
        self::assertSame(429, $middleware->process($this->request('9.9.9.9', '/route-a'), $this->handler())->getStatusCode());
        self::assertSame(200, $middleware->process($this->request('9.9.9.9', '/route-b'), $this->handler())->getStatusCode());
    }

    private function middleware(int $maxAttempts, CacheInterface $cache): RateLimitMiddleware
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturn('Слишком много запросов. Попробуйте позже.');

        $cacheStorageProvider = new class ($cache) implements CacheStorageProviderInterface {
            public function __construct(private readonly CacheInterface $cache) {}

            #[\Override]
            public function storage(string|null $name = null): CacheInterface
            {
                return $this->cache;
            }
        };

        return new RateLimitMiddleware(
            maxAttempts: $maxAttempts,
            perSeconds: 60,
            cacheStorageProvider: $cacheStorageProvider,
            translator: $translator,
        );
    }

    private function handler(): RequestHandlerInterface
    {
        return new class implements RequestHandlerInterface {
            #[\Override]
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(status: 200, headers: [], body: 'ok');
            }
        };
    }

    private function request(string $clientIp, string $path): ServerRequestInterface
    {
        return new ServerRequest(method: 'POST', uri: $path, serverParams: ['REMOTE_ADDR' => $clientIp]);
    }
}

exec
/bin/zsh -lc "sed -n '1,240p' app/src/Modules/Auth/Presentation/Http/Controller/AuthController.php && sed -n '1,220p' app/src/Modules/Auth/Presentation/Http/Filter/VerifyCodeFilter.php && sed -n '1,220p' app/src/Modules/Auth/Presentation/Http/Filter/RegisterFilter.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Auth\Presentation\Http\Controller;

use App\Modules\Auth\Application\Command\Auth\CompleteRegistration\CompleteRegistrationCommand;
use App\Modules\Auth\Application\Command\Auth\CompleteRegistration\CompleteRegistrationHandler;
use App\Modules\Auth\Application\Command\Auth\Logout\LogoutCommand;
use App\Modules\Auth\Application\Command\Auth\Logout\LogoutHandler;
use App\Modules\Auth\Application\Command\Auth\RefreshTokens\RefreshTokensCommand;
use App\Modules\Auth\Application\Command\Auth\RefreshTokens\RefreshTokensHandler;
use App\Modules\Auth\Application\Command\Auth\RequestLoginCode\RequestLoginCodeCommand;
use App\Modules\Auth\Application\Command\Auth\RequestLoginCode\RequestLoginCodeHandler;
use App\Modules\Auth\Application\Command\Auth\VerifyLoginCode\VerifyLoginCodeCommand;
use App\Modules\Auth\Application\Command\Auth\VerifyLoginCode\VerifyLoginCodeHandler;
use App\Modules\Auth\Presentation\Http\Filter\LogoutFilter;
use App\Modules\Auth\Presentation\Http\Filter\RefreshFilter;
use App\Modules\Auth\Presentation\Http\Filter\RegisterFilter;
use App\Modules\Auth\Presentation\Http\Filter\RequestCodeFilter;
use App\Modules\Auth\Presentation\Http\Filter\VerifyCodeFilter;
use App\Modules\Auth\Presentation\Http\Middleware\AuthContextAttributeMiddleware;
use App\Modules\Auth\Presentation\Http\Middleware\RequireAuthenticatedMiddleware;
use App\Modules\Auth\Presentation\Http\Resource\LogoutResultResource;
use App\Modules\Auth\Presentation\Http\Resource\RequestCodeResultResource;
use App\Modules\Auth\Presentation\Http\Resource\TokenPairResource;
use App\Modules\Auth\Presentation\Http\Resource\VerifyResultResource;
use App\Shared\Infrastructure\Framework\Middleware\RateLimitMiddleware;
use GianTiaga\SpiralCqrs\CommandBusInterface;
use GianTiaga\SpiralOpenApi\Response\DataResponse;
use Spiral\Auth\Middleware\AuthTransportWithStorageMiddleware;
use Spiral\Core\Container\Autowire;
use Spiral\Router\Annotation\Route;
use Spiral\Translator\TranslatorInterface;

final readonly class AuthController
{
    /**
     * Запрос кода входа на email. Ответ всегда 200 (наличие пользователя не раскрываем).
     *
     * @return DataResponse<RequestCodeResultResource>
     */
    #[Route(
        route: '/api/v1/auth/code/request',
        name: 'api.v1.auth.code.request',
        methods: ['POST'],
        group: 'api',
        middleware: [
            new Autowire(alias: RateLimitMiddleware::class, parameters: ['maxAttempts' => 5, 'perSeconds' => 60]),
        ],
    )]
    public function requestCode(
        RequestCodeFilter $requestCodeFilter,
        TranslatorInterface $translator,
        CommandBusInterface $commandBus,
        RequestLoginCodeHandler $requestLoginCodeHandler,
    ): DataResponse {
        $commandBus->dispatch(
            command: new RequestLoginCodeCommand(
                email: $requestCodeFilter->email,
                requestLocale: $translator->getLocale(),
            ),
            handler: $requestLoginCodeHandler->handle(...),
        );

        return new DataResponse(RequestCodeResultResource::sent());
    }

    /**
     * Проверка кода: пара токенов существующему пользователю или талон регистрации новому email.
     *
     * @return DataResponse<VerifyResultResource>
     */
    #[Route(
        route: '/api/v1/auth/code/verify',
        name: 'api.v1.auth.code.verify',
        methods: ['POST'],
        group: 'api',
        middleware: [
            new Autowire(alias: RateLimitMiddleware::class, parameters: ['maxAttempts' => 10, 'perSeconds' => 60]),
        ],
    )]
    public function verifyCode(
        VerifyCodeFilter $verifyCodeFilter,
        CommandBusInterface $commandBus,
        VerifyLoginCodeHandler $verifyLoginCodeHandler,
    ): DataResponse {
        $result = $commandBus->dispatch(
            command: new VerifyLoginCodeCommand(email: $verifyCodeFilter->email, code: $verifyCodeFilter->code),
            handler: $verifyLoginCodeHandler->handle(...),
        );

        return new DataResponse(VerifyResultResource::fromResult($result));
    }

    /**
     * Завершение регистрации по талону: создаёт пользователя и выдаёт пару токенов.
     *
     * @return DataResponse<TokenPairResource>
     */
    #[Route(
        route: '/api/v1/auth/register',
        name: 'api.v1.auth.register',
        methods: ['POST'],
        group: 'api',
        middleware: [
            new Autowire(alias: RateLimitMiddleware::class, parameters: ['maxAttempts' => 10, 'perSeconds' => 60]),
        ],
    )]
    public function register(
        RegisterFilter $registerFilter,
        TranslatorInterface $translator,
        CommandBusInterface $commandBus,
        CompleteRegistrationHandler $completeRegistrationHandler,
    ): DataResponse {
        $tokens = $commandBus->dispatch(
            command: new CompleteRegistrationCommand(
                ticket: $registerFilter->ticket,
                name: $registerFilter->name,
                nickname: $registerFilter->nickname,
                requestLocale: $translator->getLocale(),
            ),
            handler: $completeRegistrationHandler->handle(...),
        );

        return new DataResponse(TokenPairResource::fromPair($tokens));
    }

    /**
     * Обновление пары токенов по refresh-токену (ротация).
     *
     * @return DataResponse<TokenPairResource>
     */
    #[Route(
        route: '/api/v1/auth/refresh',
        name: 'api.v1.auth.refresh',
        methods: ['POST'],
        group: 'api',
        middleware: [
            new Autowire(alias: RateLimitMiddleware::class, parameters: ['maxAttempts' => 20, 'perSeconds' => 60]),
        ],
    )]
    public function refresh(
        RefreshFilter $refreshFilter,
        CommandBusInterface $commandBus,
        RefreshTokensHandler $refreshTokensHandler,
    ): DataResponse {
        $tokens = $commandBus->dispatch(
            command: new RefreshTokensCommand(refreshToken: $refreshFilter->refreshToken),
            handler: $refreshTokensHandler->handle(...),
        );

        return new DataResponse(TokenPairResource::fromPair($tokens));
    }

    /**
     * Выход: отзыв всех токенов текущей сессии. Требует Bearer access-токен.
     *
     * @return DataResponse<LogoutResultResource>
     */
    #[Route(
        route: '/api/v1/auth/logout',
        name: 'api.v1.auth.logout',
        methods: ['POST'],
        group: 'api',
        middleware: [
            new Autowire(
                alias: AuthTransportWithStorageMiddleware::class,
                parameters: ['transportName' => 'header', 'storage' => 'cycle'],
            ),
            AuthContextAttributeMiddleware::class,
            RequireAuthenticatedMiddleware::class,
        ],
    )]
    public function logout(
        LogoutFilter $logoutFilter,
        CommandBusInterface $commandBus,
        LogoutHandler $logoutHandler,
    ): DataResponse {
        $commandBus->dispatch(
            command: new LogoutCommand(authSessionId: $logoutFilter->authSessionId),
            handler: $logoutHandler->handle(...),
        );

        return new DataResponse(LogoutResultResource::ok());
    }
}
<?php

declare(strict_types=1);

namespace App\Modules\Auth\Presentation\Http\Filter;

use Spiral\Filters\Attribute\Input\Post;
use Spiral\Validation\Symfony\AttributesFilter;
use Symfony\Component\Validator\Constraints as Assert;

final class VerifyCodeFilter extends AttributesFilter
{
    #[Post]
    #[Assert\NotBlank]
    public string $email;

    #[Post]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^\d{6}$/')]
    public string $code;
}
<?php

declare(strict_types=1);

namespace App\Modules\Auth\Presentation\Http\Filter;

use Spiral\Filters\Attribute\Input\Post;
use Spiral\Validation\Symfony\AttributesFilter;
use Symfony\Component\Validator\Constraints as Assert;

final class RegisterFilter extends AttributesFilter
{
    #[Post]
    #[Assert\NotBlank]
    public string $ticket;

    #[Post]
    #[Assert\NotBlank]
    public string $name;

    #[Post]
    #[Assert\NotBlank]
    public string $nickname;
}

exec
/bin/zsh -lc "sed -n '1,220p' app/src/Modules/User/Application/Command/User/CreateUser/CreateUserHandler.php && sed -n '1,220p' app/src/Modules/User/Application/Command/User/CreateUser/CreateUserCommand.php && sed -n '1,180p' app/src/Modules/User/Application/Query/User/FindUserForAuth/FindUserForAuthHandler.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\User\Application\Command\User\CreateUser;

use App\Modules\User\Domain\Entity\User;
use App\Modules\User\Domain\ValueObject\Email;
use App\Modules\User\Domain\ValueObject\UserName;
use App\Modules\User\Domain\ValueObject\UserNickname;
use App\Modules\User\Repository\ReservedNicknameRepository;
use App\Modules\User\Repository\UserRepository;
use App\Shared\Domain\Enum\Locale;
use App\Shared\Domain\Exception\ValidationException;
use Cycle\ORM\EntityManagerInterface;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;
use GianTiaga\SpiralCqrs\Attribute\Transactional;
use Psr\Log\LoggerInterface;

final readonly class CreateUserHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private ReservedNicknameRepository $reservedNicknameRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}

    #[Transactional]
    #[LogOperation]
    public function handle(CreateUserCommand $command): CreateUserResult
    {
        $email = Email::fromString($command->email);
        $nickname = UserNickname::fromString($command->nickname);

        if ($this->userRepository->existsByEmail($email)) {
            throw new ValidationException('app.user.email_taken');
        }

        if (
            $this->userRepository->existsByNickname($nickname)
            || $this->reservedNicknameRepository->isReserved($nickname)
        ) {
            throw new ValidationException('app.user.nickname_taken');
        }

        $user = User::create(
            name: UserName::fromString($command->name),
            email: $email,
            nickname: $nickname,
            locale: Locale::from($command->locale),
        );
        $user->confirmEmail();

        $this->entityManager->persist($user);
        $this->entityManager->run();

        $this->logger->debug(message: 'Пользователь создан.', context: [
            'userId' => $user->id->value(),
            'email' => $email->value(),
        ]);

        return new CreateUserResult(userId: $user->id->value());
    }
}
<?php

declare(strict_types=1);

namespace App\Modules\User\Application\Command\User\CreateUser;

final readonly class CreateUserCommand
{
    public function __construct(
        public string $email,
        public string $name,
        public string $nickname,
        public string $locale,
    ) {}
}
<?php

declare(strict_types=1);

namespace App\Modules\User\Application\Query\User\FindUserForAuth;

use App\Modules\User\Application\Dto\UserAuthView;
use App\Modules\User\Domain\ValueObject\Email;
use App\Modules\User\Repository\UserRepository;

final readonly class FindUserForAuthHandler
{
    public function __construct(
        private UserRepository $userRepository,
    ) {}

    public function handle(FindUserForAuthQuery $query): UserAuthView|null
    {
        $user = $this->userRepository->findByEmail(Email::fromString($query->email));

        if ($user === null) {
            return null;
        }

        return new UserAuthView(
            userId: $user->id->value(),
            canSignIn: $user->isActive(),
        );
    }
}

exec
/bin/zsh -lc "sed -n '1,260p' app/src/Modules/Auth/Application/Command/Auth/ResolveLoginCode/ResolveLoginCodeHandler.php && sed -n '1,240p' app/src/Modules/Auth/Application/Command/Auth/VerifyLoginCode/VerifyLoginCodeHandler.php && sed -n '1,240p' app/src/Modules/Auth/Application/Command/Auth/CompleteRegistration/CompleteRegistrationHandler.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Command\Auth\ResolveLoginCode;

use App\Modules\Auth\Application\Contract\AuthTokenStorageContract;
use App\Modules\Auth\Application\Contract\SecretHasherContract;
use App\Modules\Auth\Application\Contract\TokenGeneratorContract;
use App\Modules\Auth\Domain\Entity\RegistrationTicket;
use App\Modules\Auth\Domain\ValueObject\EmailAddress;
use App\Modules\Auth\Domain\ValueObject\Expiration;
use App\Modules\Auth\Domain\ValueObject\RegistrationTicketId;
use App\Modules\Auth\Domain\ValueObject\SecretHash;
use App\Modules\Auth\Repository\LoginCodeRepository;
use App\Modules\User\Application\Query\User\FindUserForAuth\FindUserForAuthHandler;
use App\Modules\User\Application\Query\User\FindUserForAuth\FindUserForAuthQuery;
use App\Shared\Domain\ValueObject\UserId;
use Cycle\ORM\EntityManagerInterface;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;
use GianTiaga\SpiralCqrs\Attribute\Transactional;
use GianTiaga\SpiralCqrs\QueryBusInterface;

/**
 * Транзакционный разбор кода. Коммитит запись (consume или attempts++) и ВОЗВРАЩАЕТ исход;
 * 401 бросает уже нетранзакционный оркестратор VerifyLoginCode после commit — иначе инкремент
 * попыток откатился бы вместе с throw, и защита от перебора не работала бы.
 */
final readonly class ResolveLoginCodeHandler
{
    private const int TICKET_TTL_SECONDS = 900;

    public function __construct(
        private LoginCodeRepository $loginCodeRepository,
        private SecretHasherContract $secretHasher,
        private TokenGeneratorContract $tokenGenerator,
        private AuthTokenStorageContract $authTokenStorage,
        private QueryBusInterface $queryBus,
        private FindUserForAuthHandler $findUserForAuthHandler,
        private EntityManagerInterface $entityManager,
    ) {}

    #[Transactional]
    #[LogOperation]
    public function handle(ResolveLoginCodeCommand $command): LoginCodeResolution
    {
        $email = EmailAddress::fromString($command->email);
        $now = new \DateTimeImmutable();
        $loginCode = $this->loginCodeRepository->findActiveByEmailForUpdate($email);

        if ($loginCode === null) {
            return LoginCodeResolution::failed(LoginCodeOutcome::NoCode);
        }

        if ($loginCode->isExpired($now)) {
            return LoginCodeResolution::failed(LoginCodeOutcome::Expired);
        }

        if ($loginCode->attemptsExhausted()) {
            return LoginCodeResolution::failed(LoginCodeOutcome::Exhausted);
        }

        if (!$this->secretHasher->verify(secret: $command->code, hash: $loginCode->codeHash->value())) {
            $loginCode->registerFailedAttempt($now);
            $this->entityManager->persist($loginCode);
            $this->entityManager->run();

            return LoginCodeResolution::failed(LoginCodeOutcome::Wrong);
        }

        $loginCode->consume($now);
        $this->entityManager->persist($loginCode);

        return $this->resolveVerifiedCode(email: $email, now: $now);
    }

    private function resolveVerifiedCode(EmailAddress $email, \DateTimeImmutable $now): LoginCodeResolution
    {
        $userAuthView = $this->queryBus->dispatch(
            query: new FindUserForAuthQuery(email: $email->value()),
            handler: $this->findUserForAuthHandler->handle(...),
        );

        if ($userAuthView === null) {
            return $this->issueRegistrationTicket(email: $email, now: $now);
        }

        if (!$userAuthView->canSignIn) {
            $this->entityManager->run();

            return LoginCodeResolution::failed(LoginCodeOutcome::NotAllowed);
        }

        $tokens = $this->authTokenStorage->issuePair(UserId::fromString($userAuthView->userId));
        $this->entityManager->run();

        return LoginCodeResolution::verified($tokens);
    }

    private function issueRegistrationTicket(EmailAddress $email, \DateTimeImmutable $now): LoginCodeResolution
    {
        $ticketRaw = $this->tokenGenerator->generate();
        $ticket = RegistrationTicket::issue(
            id: RegistrationTicketId::generate(),
            email: $email,
            ticketHash: SecretHash::fromString($this->secretHasher->hash($ticketRaw)),
            expiration: Expiration::after(now: $now, seconds: self::TICKET_TTL_SECONDS),
            now: $now,
        );
        $this->entityManager->persist($ticket);
        $this->entityManager->run();

        return LoginCodeResolution::needsProfile($ticketRaw);
    }
}
<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Command\Auth\VerifyLoginCode;

use App\Modules\Auth\Application\Command\Auth\ResolveLoginCode\LoginCodeOutcome;
use App\Modules\Auth\Application\Command\Auth\ResolveLoginCode\ResolveLoginCodeCommand;
use App\Modules\Auth\Application\Command\Auth\ResolveLoginCode\ResolveLoginCodeHandler;
use App\Shared\Domain\Exception\AuthenticationException;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;
use GianTiaga\SpiralCqrs\CommandBusInterface;

/**
 * Оркестратор проверки кода БЕЗ #[Transactional]. Делегирует разбор транзакционному
 * ResolveLoginCode (он коммитит consume/attempts++), затем по исходу строит результат или
 * бросает 401 уже после commit — чтобы инкремент попыток пережил отказ.
 */
final readonly class VerifyLoginCodeHandler
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private ResolveLoginCodeHandler $resolveLoginCodeHandler,
    ) {}

    #[LogOperation]
    public function handle(VerifyLoginCodeCommand $command): VerifyLoginCodeResult
    {
        $resolution = $this->commandBus->dispatch(
            command: new ResolveLoginCodeCommand(email: $command->email, code: $command->code),
            handler: $this->resolveLoginCodeHandler->handle(...),
        );

        return match ($resolution->outcome) {
            LoginCodeOutcome::Verified => new VerifyLoginCodeResult(
                needsProfile: false,
                tokens: $resolution->tokens,
                registrationTicket: null,
            ),
            LoginCodeOutcome::NeedsProfile => new VerifyLoginCodeResult(
                needsProfile: true,
                tokens: null,
                registrationTicket: $resolution->registrationTicket,
            ),
            LoginCodeOutcome::NotAllowed => throw new AuthenticationException('app.auth.sign_in_not_allowed'),
            LoginCodeOutcome::NoCode,
            LoginCodeOutcome::Expired,
            LoginCodeOutcome::Exhausted,
            LoginCodeOutcome::Wrong => throw new AuthenticationException('app.auth.invalid_code'),
        };
    }
}
<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Command\Auth\CompleteRegistration;

use App\Modules\Auth\Application\Contract\AuthTokenStorageContract;
use App\Modules\Auth\Application\Contract\SecretHasherContract;
use App\Modules\Auth\Application\Dto\IssuedTokenPair;
use App\Modules\Auth\Domain\ValueObject\SecretHash;
use App\Modules\Auth\Repository\RegistrationTicketRepository;
use App\Modules\User\Application\Command\User\CreateUser\CreateUserCommand;
use App\Modules\User\Application\Command\User\CreateUser\CreateUserHandler;
use App\Shared\Domain\Exception\AuthenticationException;
use App\Shared\Domain\ValueObject\UserId;
use Cycle\ORM\EntityManagerInterface;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;
use GianTiaga\SpiralCqrs\Attribute\Transactional;
use GianTiaga\SpiralCqrs\CommandBusInterface;
use Psr\Log\LoggerInterface;

/**
 * Завершение регистрации по талону. CreateUser диспатчится вложенно (#[Transactional] →
 * SAVEPOINT): занятый ник/email бросает ValidationException 422, откатывая SAVEPOINT и внешнюю
 * транзакцию — поэтому талон НЕ гасится и попытку можно повторить.
 */
final readonly class CompleteRegistrationHandler
{
    public function __construct(
        private RegistrationTicketRepository $registrationTicketRepository,
        private SecretHasherContract $secretHasher,
        private AuthTokenStorageContract $authTokenStorage,
        private CommandBusInterface $commandBus,
        private CreateUserHandler $createUserHandler,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}

    #[Transactional]
    #[LogOperation]
    public function handle(CompleteRegistrationCommand $command): IssuedTokenPair
    {
        $now = new \DateTimeImmutable();
        $ticketHash = SecretHash::fromString($this->secretHasher->hash($command->ticket));
        $ticket = $this->registrationTicketRepository->findActiveByHashForUpdate($ticketHash)
            ?? throw new AuthenticationException('app.auth.invalid_ticket');

        if ($ticket->isExpired($now)) {
            throw new AuthenticationException('app.auth.invalid_ticket');
        }

        $ticket->consume($now);
        $this->entityManager->persist($ticket);

        $createUserResult = $this->commandBus->dispatch(
            command: new CreateUserCommand(
                email: $ticket->email->value(),
                name: $command->name,
                nickname: $command->nickname,
                locale: $command->requestLocale,
            ),
            handler: $this->createUserHandler->handle(...),
        );

        $tokens = $this->authTokenStorage->issuePair(UserId::fromString($createUserResult->userId));
        $this->entityManager->run();

        $this->logger->info(message: 'Регистрация завершена.', context: [
            'userId' => $createUserResult->userId,
            'email' => $ticket->email->value(),
        ]);

        return $tokens;
    }
}

exec
/bin/zsh -lc "sed -n '180,520p' docs/arch.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
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
данные. Каждый use-case живёт в отдельной папке действия внутри
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

exec
/bin/zsh -lc "sed -n '1,220p' app/src/Modules/Auth/Domain/ValueObject/LoginCodeValue.php && rg -n \"LoginCodeValue\" app/src/Modules/Auth app/src/Modules/User/Application tests" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidDomainValueException;

/**
 * Сырой 6-значный код входа. Транзиентное значение: не хранится в БД (в БД лежит только
 * SecretHash), используется только на границе ввода и для сверки хэша.
 */
final readonly class LoginCodeValue implements \Stringable, \JsonSerializable
{
    private function __construct(
        private string $value,
    ) {}

    public static function fromString(string $value): self
    {
        if (\preg_match(pattern: '/^\d{6}$/', subject: $value) !== 1) {
            throw new InvalidDomainValueException('Код должен состоять из 6 цифр.');
        }

        return new self(value: $value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $code): bool
    {
        return \hash_equals(known_string: $this->value, user_string: $code->value);
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
app/src/Modules/Auth/Domain/ValueObject/LoginCodeValue.php:13:final readonly class LoginCodeValue implements \Stringable, \JsonSerializable
tests/Unit/Modules/Auth/Domain/ValueObject/AuthValueObjectTest.php:11:use App\Modules\Auth\Domain\ValueObject\LoginCodeValue;
tests/Unit/Modules/Auth/Domain/ValueObject/AuthValueObjectTest.php:149:    public function testLoginCodeValueAcceptsSixDigits(): void
tests/Unit/Modules/Auth/Domain/ValueObject/AuthValueObjectTest.php:151:        $code = LoginCodeValue::fromString('123456');
tests/Unit/Modules/Auth/Domain/ValueObject/AuthValueObjectTest.php:156:        self::assertTrue($code->equals(LoginCodeValue::fromString('123456')));
tests/Unit/Modules/Auth/Domain/ValueObject/AuthValueObjectTest.php:157:        self::assertFalse($code->equals(LoginCodeValue::fromString('654321')));
tests/Unit/Modules/Auth/Domain/ValueObject/AuthValueObjectTest.php:161:    public function testLoginCodeValueRejectsInvalidFormat(string $invalidCode): void
tests/Unit/Modules/Auth/Domain/ValueObject/AuthValueObjectTest.php:165:        LoginCodeValue::fromString($invalidCode);

exec
/bin/zsh -lc "sed -n '1,360p' app/src/Modules/Auth/Infrastructure/Auth/CycleTokenStorage.php && sed -n '1,220p' app/src/Modules/Auth/Infrastructure/Auth/UserActorProvider.php && sed -n '1,180p' app/src/Modules/Auth/Infrastructure/Auth/AuthTokenView.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc "sed -n '1,280p' app/src/Modules/Auth/Application/Command/Auth/RequestLoginCode/RequestLoginCodeHandler.php && sed -n '1,220p' app/src/Modules/Auth/Application/Command/Auth/RequestLoginCode/RequestLoginCodeCommand.php && sed -n '1,220p' app/src/Modules/Auth/Application/Command/Auth/SendLoginCode/SendLoginCodeHandler.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Command\Auth\RequestLoginCode;

use App\Modules\Auth\Application\Contract\SecretHasherContract;
use App\Modules\Auth\Application\Message\LoginCodeRequested;
use App\Modules\Auth\Domain\Entity\LoginCode;
use App\Modules\Auth\Domain\ValueObject\EmailAddress;
use App\Modules\Auth\Domain\ValueObject\Expiration;
use App\Modules\Auth\Domain\ValueObject\LoginCodeId;
use App\Modules\Auth\Domain\ValueObject\SecretHash;
use App\Modules\Auth\Repository\LoginCodeRepository;
use App\Modules\Outbox\Application\Contract\OutboxEventStoreContract;
use Cycle\ORM\EntityManagerInterface;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;
use GianTiaga\SpiralCqrs\Attribute\Transactional;
use Psr\Log\LoggerInterface;

/**
 * Запрос кода входа. Наличие пользователя не раскрываем (в User/Application не ходим, ответ
 * всегда 200). Язык письма берём из локали запроса. Троттлинг: повторный запрос в пределах
 * окна не шлёт новое письмо.
 */
final readonly class RequestLoginCodeHandler
{
    private const int CODE_TTL_SECONDS = 600;
    private const int RESEND_THROTTLE_SECONDS = 60;

    public function __construct(
        private LoginCodeRepository $loginCodeRepository,
        private SecretHasherContract $secretHasher,
        private OutboxEventStoreContract $outboxEventStore,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}

    #[Transactional]
    #[LogOperation]
    public function handle(RequestLoginCodeCommand $command): void
    {
        $email = EmailAddress::fromString($command->email);
        $now = new \DateTimeImmutable();
        $activeCode = $this->loginCodeRepository->findActiveByEmail($email);

        if ($activeCode !== null && $this->isWithinResendWindow(activeCode: $activeCode, now: $now)) {
            $this->logger->debug(message: 'Запрос кода в окне троттлинга, письмо не отправляется.', context: [
                'email' => $email->value(),
            ]);

            return;
        }

        if ($activeCode !== null) {
            $activeCode->consume($now);
            $this->entityManager->persist($activeCode);
        }

        $code = \str_pad(
            string: (string) \random_int(min: 0, max: 999999),
            length: 6,
            pad_string: '0',
            pad_type: STR_PAD_LEFT,
        );
        $loginCode = LoginCode::issue(
            id: LoginCodeId::generate(),
            email: $email,
            codeHash: SecretHash::fromString($this->secretHasher->hash($code)),
            expiration: Expiration::after(now: $now, seconds: self::CODE_TTL_SECONDS),
            now: $now,
        );
        $this->entityManager->persist($loginCode);
        $this->outboxEventStore->add(new LoginCodeRequested(
            email: $email->value(),
            code: $code,
            locale: $command->requestLocale,
        ));
        $this->entityManager->run();

        $this->logger->debug(message: 'Код входа запрошен и поставлен в outbox.', context: [
            'email' => $email->value(),
        ]);
    }

    private function isWithinResendWindow(LoginCode $activeCode, \DateTimeImmutable $now): bool
    {
        $resendAvailableAt = $activeCode->createdAt->add(
            new \DateInterval(\sprintf('PT%dS', self::RESEND_THROTTLE_SECONDS)),
        );

        return $now < $resendAvailableAt;
    }
}
<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Command\Auth\RequestLoginCode;

final readonly class RequestLoginCodeCommand
{
    public function __construct(
        public string $email,
        public string $requestLocale,
    ) {}
}
<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Command\Auth\SendLoginCode;

use App\Shared\Infrastructure\Configuration\Locale\LocaleConfig;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;
use Spiral\Mailer\MailerInterface;
use Spiral\Mailer\Message;
use Spiral\Translator\TranslatorInterface;

/**
 * Отправка письма с кодом входа. Выполняется в очереди (per-request локали нет), поэтому язык
 * берётся из сообщения с явной передачей в translator и fallback на LocaleConfig.default при
 * неподдерживаемом значении. Тема и тело письма — из переводов домена auth.
 */
final readonly class SendLoginCodeHandler
{
    private const string EMAIL_VIEW = 'login-code';

    public function __construct(
        private MailerInterface $mailer,
        private TranslatorInterface $translator,
        private LocaleConfig $localeConfig,
    ) {}

    #[LogOperation]
    public function handle(SendLoginCodeCommand $command): void
    {
        $locale = $this->resolveLocale($command->locale);

        $message = new Message(
            subject: self::EMAIL_VIEW,
            to: $command->email,
            data: [
                'subject' => $this->translator->trans(
                    id: 'app.auth.code_email_subject',
                    parameters: [],
                    domain: 'auth',
                    locale: $locale,
                ),
                'body' => $this->translator->trans(
                    id: 'app.auth.code_email_body',
                    parameters: ['code' => $command->code],
                    domain: 'auth',
                    locale: $locale,
                ),
            ],
        );

        $this->mailer->send($message);
    }

    private function resolveLocale(string $locale): string
    {
        return \in_array(needle: $locale, haystack: $this->localeConfig->supported, strict: true)
            ? $locale
            : $this->localeConfig->default;
    }
}

 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Auth;

use App\Modules\Auth\Application\Contract\AuthTokenStorageContract;
use App\Modules\Auth\Application\Contract\TokenGeneratorContract;
use App\Modules\Auth\Application\Dto\IssuedTokenPair;
use App\Modules\Auth\Domain\Entity\AuthToken;
use App\Modules\Auth\Domain\Enum\AuthTokenType;
use App\Modules\Auth\Domain\ValueObject\AuthTokenId;
use App\Modules\Auth\Domain\ValueObject\Expiration;
use App\Modules\Auth\Domain\ValueObject\SessionId;
use App\Modules\Auth\Domain\ValueObject\TokenHash;
use App\Modules\Auth\Repository\AuthTokenRepository;
use App\Shared\Domain\Exception\AuthenticationException;
use App\Shared\Domain\Exception\InvalidDomainValueException;
use App\Shared\Domain\ValueObject\UserId;
use Cycle\ORM\EntityManagerInterface;
use Spiral\Auth\TokenInterface;
use Spiral\Auth\TokenStorageInterface;

/**
 * Единый адаптер хранилища токенов: реализует и Spiral\Auth\TokenStorageInterface (load для
 * auth-middleware), и доменный AuthTokenStorageContract (issuePair/rotate/revokeSession для
 * хендлеров). Инфраструктурный orchestrator: persist/run делает сам (vendor-middleware зовёт
 * create/delete напрямую и ждёт реального flush; вызовы доменных методов идут внутри
 * #[Transactional]-сценария, несколько run() в одной транзакции безопасны).
 */
final readonly class CycleTokenStorage implements TokenStorageInterface, AuthTokenStorageContract
{
    private const int ACCESS_TTL_SECONDS = 3600;
    private const int REFRESH_TTL_SECONDS = 5_184_000;

    public function __construct(
        private AuthTokenRepository $authTokenRepository,
        private TokenGeneratorContract $tokenGenerator,
        private EntityManagerInterface $entityManager,
    ) {}

    /**
     * Обычное чтение по хэшу БЕЗ блокировки — вызывается на каждом запросе с токеном.
     */
    #[\Override]
    public function load(string $id): TokenInterface|null
    {
        if ($id === '') {
            return null;
        }

        $authToken = $this->authTokenRepository->findByHash(TokenHash::fromRawToken($id));

        if ($authToken === null || $authToken->isExpired(new \DateTimeImmutable())) {
            return null;
        }

        return $this->view(rawToken: $id, authToken: $authToken);
    }

    /**
     * @param array<string, string> $payload
     */
    #[\Override]
    public function create(array $payload, \DateTimeInterface|null $expiresAt = null): TokenInterface
    {
        if ($expiresAt === null) {
            throw new InvalidDomainValueException('Срок жизни токена обязателен.');
        }

        $userId = $payload['userID'] ?? null;
        $type = $payload['type'] ?? null;
        $sessionId = $payload['sessionID'] ?? null;

        if ($userId === null || $type === null || $sessionId === null) {
            throw new InvalidDomainValueException('Некорректный payload токена.');
        }

        $rawToken = $this->tokenGenerator->generate();
        $expiration = Expiration::fromDateTime(\DateTimeImmutable::createFromInterface($expiresAt));
        $authToken = AuthToken::issue(
            id: AuthTokenId::generate(),
            userId: UserId::fromString($userId),
            sessionId: SessionId::fromString($sessionId),
            type: AuthTokenType::from($type),
            tokenHash: TokenHash::fromRawToken($rawToken),
            expiration: $expiration,
            now: new \DateTimeImmutable(),
        );

        $this->entityManager->persist($authToken);
        $this->entityManager->run();

        return new AuthTokenView(
            id: $rawToken,
            payload: ['userID' => $userId, 'type' => $type, 'sessionID' => $sessionId],
            expiresAt: $expiration->value(),
        );
    }

    #[\Override]
    public function delete(TokenInterface $token): void
    {
        $authToken = $this->authTokenRepository->findByHashForUpdate(TokenHash::fromRawToken($token->getID()));

        if ($authToken === null) {
            return;
        }

        $this->entityManager->delete($authToken);
        $this->entityManager->run();
    }

    #[\Override]
    public function issuePair(UserId $userId): IssuedTokenPair
    {
        $sessionId = SessionId::generate();
        $now = new \DateTimeImmutable();

        $accessToken = $this->create(
            payload: [
                'userID' => $userId->value(),
                'type' => AuthTokenType::Access->value,
                'sessionID' => $sessionId->value(),
            ],
            expiresAt: $now->add(new \DateInterval(\sprintf('PT%dS', self::ACCESS_TTL_SECONDS))),
        );
        $refreshToken = $this->create(
            payload: [
                'userID' => $userId->value(),
                'type' => AuthTokenType::Refresh->value,
                'sessionID' => $sessionId->value(),
            ],
            expiresAt: $now->add(new \DateInterval(\sprintf('PT%dS', self::REFRESH_TTL_SECONDS))),
        );

        return new IssuedTokenPair(
            accessToken: $accessToken->getID(),
            refreshToken: $refreshToken->getID(),
            expiresIn: self::ACCESS_TTL_SECONDS,
        );
    }

    #[\Override]
    public function rotate(string $refreshRaw): IssuedTokenPair
    {
        $authToken = $this->authTokenRepository->findByHashForUpdate(TokenHash::fromRawToken($refreshRaw))
            ?? throw new AuthenticationException('app.auth.invalid_refresh');

        if (!$authToken->isRefresh() || $authToken->isExpired(new \DateTimeImmutable())) {
            throw new AuthenticationException('app.auth.invalid_refresh');
        }

        $userId = $authToken->userId;
        $this->revokeSession($authToken->sessionId);

        return $this->issuePair($userId);
    }

    #[\Override]
    public function revokeSession(SessionId $sessionId): void
    {
        $sessionTokens = $this->authTokenRepository->findBySessionIdForUpdate($sessionId);

        foreach ($sessionTokens as $sessionToken) {
            $this->entityManager->delete($sessionToken);
        }

        $this->entityManager->run();
    }

    private function view(string $rawToken, AuthToken $authToken): AuthTokenView
    {
        return new AuthTokenView(
            id: $rawToken,
            payload: [
                'userID' => $authToken->userId->value(),
                'type' => $authToken->type->value,
                'sessionID' => $authToken->sessionId->value(),
            ],
            expiresAt: $authToken->expiration->value(),
        );
    }
}
<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Auth;

use App\Modules\Auth\Domain\Enum\AuthTokenType;
use App\Shared\Domain\ValueObject\UserId;
use Spiral\Auth\ActorProviderInterface;
use Spiral\Auth\TokenInterface;

/**
 * Провайдер актора для HTTP-аутентификации. Возвращает актора ТОЛЬКО для access-токена:
 * refresh-токен, присланный как Bearer, актора не даёт (закрытая дыра — иначе один load()
 * пропустил бы refresh как сессию).
 */
final readonly class UserActorProvider implements ActorProviderInterface
{
    #[\Override]
    public function getActor(TokenInterface $token): object|null
    {
        $payload = $token->getPayload();

        if (($payload['type'] ?? null) !== AuthTokenType::Access->value) {
            return null;
        }

        $userId = $payload['userID'] ?? null;

        if (!\is_string($userId)) {
            return null;
        }

        return new AuthenticatedUser(userId: UserId::fromString($userId));
    }
}
<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Auth;

use Spiral\Auth\TokenInterface;

/**
 * Адаптер AuthToken к Spiral\Auth\TokenInterface. getID() возвращает ИСХОДНЫЙ raw-токен из
 * аргумента load()/create() (в БД его нет — только хэш), иначе фреймворковый commitToken
 * испортил бы заголовок ответа.
 */
final readonly class AuthTokenView implements TokenInterface
{
    /**
     * @param array<string, string> $payload
     */
    public function __construct(
        private string $id,
        private array $payload,
        private \DateTimeImmutable|null $expiresAt,
    ) {}

    #[\Override]
    public function getID(): string
    {
        return $this->id;
    }

    #[\Override]
    public function getExpiresAt(): \DateTimeInterface|null
    {
        return $this->expiresAt;
    }

    /**
     * @return array<string, string>
     */
    #[\Override]
    public function getPayload(): array
    {
        return $this->payload;
    }
}

exec
/bin/zsh -lc "sed -n '1,280p' app/src/Modules/Auth/Domain/Entity/LoginCode.php && sed -n '1,260p' app/src/Modules/Auth/Domain/Entity/AuthToken.php && sed -n '1,240p' app/src/Modules/Auth/Domain/Entity/RegistrationTicket.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\Entity;

use App\Modules\Auth\Domain\ValueObject\CodeAttempts;
use App\Modules\Auth\Domain\ValueObject\Consumption;
use App\Modules\Auth\Domain\ValueObject\EmailAddress;
use App\Modules\Auth\Domain\ValueObject\Expiration;
use App\Modules\Auth\Domain\ValueObject\LoginCodeId;
use App\Modules\Auth\Domain\ValueObject\SecretHash;
use App\Modules\Auth\Infrastructure\Cycle\ConsumptionTypecast;
use App\Modules\Auth\Infrastructure\Cycle\ExpirationTypecast;
use App\Modules\Auth\Repository\LoginCodeRepository;
use App\Shared\Domain\Trait\HasTimestamps;
use App\Shared\Infrastructure\Cycle\ValueObjectCast;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\ORM\Parser\Typecast;

#[Entity(
    role: 'auth_login_code',
    table: 'auth_login_codes',
    repository: LoginCodeRepository::class,
    typecast: [Typecast::class, ValueObjectCast::class],
)]
final class LoginCode
{
    use HasTimestamps;

    #[Column(type: 'uuid', primary: true, typecast: LoginCodeId::class)]
    public private(set) LoginCodeId $id;

    #[Column(type: 'string(254)', typecast: EmailAddress::class)]
    public private(set) EmailAddress $email;

    #[Column(type: 'text', name: 'code_hash', typecast: SecretHash::class)]
    public private(set) SecretHash $codeHash;

    #[Column(type: 'datetime', name: 'expires_at', typecast: ExpirationTypecast::class)]
    public private(set) Expiration $expiration;

    #[Column(type: 'integer', typecast: CodeAttempts::class)]
    public private(set) CodeAttempts $attempts;

    #[Column(type: 'datetime', name: 'consumed_at', nullable: true, typecast: ConsumptionTypecast::class)]
    public private(set) Consumption $consumption;

    public static function issue(
        LoginCodeId $id,
        EmailAddress $email,
        SecretHash $codeHash,
        Expiration $expiration,
        \DateTimeImmutable $now,
    ): self {
        $loginCode = new self();
        $loginCode->id = $id;
        $loginCode->email = $email;
        $loginCode->codeHash = $codeHash;
        $loginCode->expiration = $expiration;
        $loginCode->attempts = CodeAttempts::initial();
        $loginCode->consumption = Consumption::notConsumed();
        $loginCode->initializeTimestamps($now);

        return $loginCode;
    }

    public function registerFailedAttempt(\DateTimeImmutable $now): void
    {
        $this->attempts = $this->attempts->increment();
        $this->touch($now);
    }

    public function consume(\DateTimeImmutable $now): void
    {
        $this->consumption = Consumption::at($now);
        $this->touch($now);
    }

    public function isExpired(\DateTimeImmutable $now): bool
    {
        return $this->expiration->isExpired($now);
    }

    public function attemptsExhausted(): bool
    {
        return $this->attempts->isExhausted();
    }

    public function isConsumed(): bool
    {
        return $this->consumption->isConsumed();
    }
}
<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\Entity;

use App\Modules\Auth\Domain\Enum\AuthTokenType;
use App\Modules\Auth\Domain\ValueObject\AuthTokenId;
use App\Modules\Auth\Domain\ValueObject\Expiration;
use App\Modules\Auth\Domain\ValueObject\SessionId;
use App\Modules\Auth\Domain\ValueObject\TokenHash;
use App\Modules\Auth\Infrastructure\Cycle\ExpirationTypecast;
use App\Modules\Auth\Repository\AuthTokenRepository;
use App\Shared\Domain\Trait\HasTimestamps;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Cycle\ValueObjectCast;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\ORM\Parser\Typecast;

#[Entity(
    role: 'auth_token',
    table: 'auth_tokens',
    repository: AuthTokenRepository::class,
    typecast: [Typecast::class, ValueObjectCast::class],
)]
final class AuthToken
{
    use HasTimestamps;

    #[Column(type: 'uuid', primary: true, typecast: AuthTokenId::class)]
    public private(set) AuthTokenId $id;

    #[Column(type: 'uuid', name: 'user_id', typecast: UserId::class)]
    public private(set) UserId $userId;

    #[Column(type: 'uuid', name: 'session_id', typecast: SessionId::class)]
    public private(set) SessionId $sessionId;

    #[Column(type: 'string(16)', typecast: AuthTokenType::class)]
    public private(set) AuthTokenType $type;

    #[Column(type: 'text', name: 'token_hash', typecast: TokenHash::class)]
    public private(set) TokenHash $tokenHash;

    #[Column(type: 'datetime', name: 'expires_at', typecast: ExpirationTypecast::class)]
    public private(set) Expiration $expiration;

    public static function issue(
        AuthTokenId $id,
        UserId $userId,
        SessionId $sessionId,
        AuthTokenType $type,
        TokenHash $tokenHash,
        Expiration $expiration,
        \DateTimeImmutable $now,
    ): self {
        $authToken = new self();
        $authToken->id = $id;
        $authToken->userId = $userId;
        $authToken->sessionId = $sessionId;
        $authToken->type = $type;
        $authToken->tokenHash = $tokenHash;
        $authToken->expiration = $expiration;
        $authToken->initializeTimestamps($now);

        return $authToken;
    }

    public function isExpired(\DateTimeImmutable $now): bool
    {
        return $this->expiration->isExpired($now);
    }

    public function isAccess(): bool
    {
        return $this->type === AuthTokenType::Access;
    }

    public function isRefresh(): bool
    {
        return $this->type === AuthTokenType::Refresh;
    }
}
<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\Entity;

use App\Modules\Auth\Domain\ValueObject\Consumption;
use App\Modules\Auth\Domain\ValueObject\EmailAddress;
use App\Modules\Auth\Domain\ValueObject\Expiration;
use App\Modules\Auth\Domain\ValueObject\RegistrationTicketId;
use App\Modules\Auth\Domain\ValueObject\SecretHash;
use App\Modules\Auth\Infrastructure\Cycle\ConsumptionTypecast;
use App\Modules\Auth\Infrastructure\Cycle\ExpirationTypecast;
use App\Modules\Auth\Repository\RegistrationTicketRepository;
use App\Shared\Domain\Trait\HasTimestamps;
use App\Shared\Infrastructure\Cycle\ValueObjectCast;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\ORM\Parser\Typecast;

#[Entity(
    role: 'auth_registration_ticket',
    table: 'auth_registration_tickets',
    repository: RegistrationTicketRepository::class,
    typecast: [Typecast::class, ValueObjectCast::class],
)]
final class RegistrationTicket
{
    use HasTimestamps;

    #[Column(type: 'uuid', primary: true, typecast: RegistrationTicketId::class)]
    public private(set) RegistrationTicketId $id;

    #[Column(type: 'string(254)', typecast: EmailAddress::class)]
    public private(set) EmailAddress $email;

    #[Column(type: 'text', name: 'ticket_hash', typecast: SecretHash::class)]
    public private(set) SecretHash $ticketHash;

    #[Column(type: 'datetime', name: 'expires_at', typecast: ExpirationTypecast::class)]
    public private(set) Expiration $expiration;

    #[Column(type: 'datetime', name: 'consumed_at', nullable: true, typecast: ConsumptionTypecast::class)]
    public private(set) Consumption $consumption;

    public static function issue(
        RegistrationTicketId $id,
        EmailAddress $email,
        SecretHash $ticketHash,
        Expiration $expiration,
        \DateTimeImmutable $now,
    ): self {
        $ticket = new self();
        $ticket->id = $id;
        $ticket->email = $email;
        $ticket->ticketHash = $ticketHash;
        $ticket->expiration = $expiration;
        $ticket->consumption = Consumption::notConsumed();
        $ticket->initializeTimestamps($now);

        return $ticket;
    }

    public function consume(\DateTimeImmutable $now): void
    {
        $this->consumption = Consumption::at($now);
        $this->touch($now);
    }

    public function isExpired(\DateTimeImmutable $now): bool
    {
        return $this->expiration->isExpired($now);
    }

    public function isConsumed(): bool
    {
        return $this->consumption->isConsumed();
    }
}

exec
/bin/zsh -lc "sed -n '1,360p' tests/Feature/Modules/Auth/Http/AuthHttpTest.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Auth\Http;

use App\Modules\Auth\Application\Contract\AuthTokenStorageContract;
use App\Modules\Auth\Application\Contract\SecretHasherContract;
use App\Modules\Auth\Application\Dto\IssuedTokenPair;
use App\Modules\Auth\Domain\Entity\LoginCode;
use App\Modules\Auth\Domain\Entity\RegistrationTicket;
use App\Modules\Auth\Domain\ValueObject\EmailAddress;
use App\Modules\Auth\Domain\ValueObject\Expiration;
use App\Modules\Auth\Domain\ValueObject\LoginCodeId;
use App\Modules\Auth\Domain\ValueObject\RegistrationTicketId;
use App\Modules\Auth\Domain\ValueObject\SecretHash;
use App\Modules\Auth\Repository\LoginCodeRepository;
use App\Modules\User\Domain\Entity\User;
use App\Modules\User\Domain\ValueObject\Email;
use App\Modules\User\Domain\ValueObject\UserName;
use App\Modules\User\Domain\ValueObject\UserNickname;
use App\Modules\User\Repository\UserRepository;
use App\Shared\Domain\Enum\Locale;
use App\Shared\Domain\ValueObject\UserId;
use Cycle\Database\DatabaseInterface;
use Cycle\ORM\EntityManagerInterface;
use Spiral\Testing\Http\FakeHttp;
use Tests\NonTransactionalDatabaseTestCase;

final class AuthHttpTest extends NonTransactionalDatabaseTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $database = $this->getContainer()->get(DatabaseInterface::class);

        foreach ([
            'auth_tokens',
            'auth_registration_tickets',
            'auth_login_codes',
            'user_bans',
            'reserved_nicknames',
            'users',
            'outbox_events',
        ] as $table) {
            $database->delete($table)->run();
        }
    }

    public function testRequestCodeReturnsSentAndStoresLoginCode(): void
    {
        $response = $this->http()->postJson('/api/v1/auth/code/request', ['email' => 'request@example.com']);

        $response->assertOk();
        $response->assertBodyContains('"status":"sent"');
        self::assertNotNull(
            $this->loginCodeRepository()->findActiveByEmail(EmailAddress::fromString('request@example.com')),
        );
    }

    public function testRequestCodeRejectsEmptyEmail(): void
    {
        $this->http()->postJson('/api/v1/auth/code/request', ['email' => ''])->assertUnprocessable();
    }

    public function testVerifyExistingUserReturnsTokens(): void
    {
        $this->seedActiveUser('verify-existing@example.com', 'verify.existing');
        $this->seedLoginCode('verify-existing@example.com', '123456');

        $response = $this->http()->postJson('/api/v1/auth/code/verify', [
            'email' => 'verify-existing@example.com',
            'code' => '123456',
        ]);

        $response->assertOk();
        $response->assertBodyContains('"needsProfile":false');
        $response->assertBodyContains('accessToken');
    }

    public function testVerifyNewEmailReturnsRegistrationTicket(): void
    {
        $this->seedLoginCode('verify-new@example.com', '123456');

        $response = $this->http()->postJson('/api/v1/auth/code/verify', [
            'email' => 'verify-new@example.com',
            'code' => '123456',
        ]);

        $response->assertOk();
        $response->assertBodyContains('"needsProfile":true');
        $response->assertBodyContains('registrationTicket');
    }

    public function testVerifyWrongCodeReturns401AndIncrementsAttempts(): void
    {
        $this->seedLoginCode('verify-wrong@example.com', '123456');

        $this->http()->postJson('/api/v1/auth/code/verify', [
            'email' => 'verify-wrong@example.com',
            'code' => '000000',
        ])->assertUnauthorized();

        $this->cleanOrmHeap();
        $loginCode = $this->loginCodeRepository()->findActiveByEmail(EmailAddress::fromString('verify-wrong@example.com'));
        self::assertInstanceOf(LoginCode::class, $loginCode);
        self::assertSame(1, $loginCode->attempts->value());
    }

    public function testVerifyExhaustedAttemptsReturns401(): void
    {
        $this->seedLoginCode('verify-exhausted@example.com', '123456', failedAttempts: 5);

        $this->http()->postJson('/api/v1/auth/code/verify', [
            'email' => 'verify-exhausted@example.com',
            'code' => '123456',
        ])->assertUnauthorized();
    }

    public function testRegisterCreatesActiveUserAndReturnsTokens(): void
    {
        $this->seedRegistrationTicket('register@example.com', 'ticket-http-1');

        $response = $this->http()->postJson('/api/v1/auth/register', [
            'ticket' => 'ticket-http-1',
            'name' => 'Имя Тест',
            'nickname' => 'register.user',
        ]);

        $response->assertOk();
        $response->assertBodyContains('accessToken');

        $this->cleanOrmHeap();
        $user = $this->userRepository()->findByEmail(Email::fromString('register@example.com'));
        self::assertInstanceOf(User::class, $user);
        self::assertTrue($user->isActive());
    }

    public function testRegisterRejectsTakenNicknameAndKeepsTicket(): void
    {
        $this->seedActiveUser('owner@example.com', 'occupied.nick');
        $this->seedRegistrationTicket('register-taken@example.com', 'ticket-http-2');

        $this->http()->postJson('/api/v1/auth/register', [
            'ticket' => 'ticket-http-2',
            'name' => 'Имя Тест',
            'nickname' => 'occupied.nick',
        ])->assertUnprocessable();

        $this->cleanOrmHeap();
        self::assertNotNull(
            $this->registrationTicketRepositoryFindActive('ticket-http-2'),
        );
    }

    public function testRegisterRejectsUnknownTicket(): void
    {
        $this->http()->postJson('/api/v1/auth/register', [
            'ticket' => 'missing-ticket',
            'name' => 'Имя Тест',
            'nickname' => 'whoever.user',
        ])->assertUnauthorized();
    }

    public function testRefreshReturnsNewPairAndInvalidatesOldRefresh(): void
    {
        $pair = $this->issueTokenPair();

        $this->http()->postJson('/api/v1/auth/refresh', ['refreshToken' => $pair->refreshToken])->assertOk();
        $this->http()->postJson('/api/v1/auth/refresh', ['refreshToken' => $pair->refreshToken])->assertUnauthorized();
    }

    public function testRefreshRejectsAccessTokenAsRefresh(): void
    {
        $pair = $this->issueTokenPair();

        $this->http()->postJson('/api/v1/auth/refresh', ['refreshToken' => $pair->accessToken])->assertUnauthorized();
    }

    public function testLogoutRevokesSession(): void
    {
        $pair = $this->issueTokenPair();

        $this->http()
            ->withAuthorizationToken($pair->accessToken)
            ->postJson('/api/v1/auth/logout')
            ->assertOk();

        $this->http()
            ->withAuthorizationToken($pair->accessToken)
            ->postJson('/api/v1/auth/logout')
            ->assertUnauthorized();
    }

    public function testLogoutWithoutTokenReturns401(): void
    {
        $this->http()->postJson('/api/v1/auth/logout')->assertUnauthorized();
    }

    public function testLogoutWithRefreshTokenAsBearerReturns401(): void
    {
        $pair = $this->issueTokenPair();

        $this->http()
            ->withAuthorizationToken($pair->refreshToken)
            ->postJson('/api/v1/auth/logout')
            ->assertUnauthorized();
    }

    public function testRateLimitReturns429AfterExceedingAttempts(): void
    {
        $http = $this->http();

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $http->postJson('/api/v1/auth/code/request', ['email' => 'rate-limit@example.com'])->assertOk();
        }

        $http->postJson('/api/v1/auth/code/request', ['email' => 'rate-limit@example.com'])->assertStatus(429);
    }

    public function testSystemHealthRouteStillWorks(): void
    {
        $this->http()->getJson('/api/v1/health')->assertOk();
    }

    private function http(): FakeHttp
    {
        return $this->fakeHttp()
            ->withServerVariables(['REMOTE_ADDR' => $this->uniqueClientIp()])
            ->withHeader('Accept-Language', 'ru');
    }

    private function uniqueClientIp(): string
    {
        return \sprintf(
            '10.%d.%d.%d',
            \random_int(min: 0, max: 255),
            \random_int(min: 0, max: 255),
            \random_int(min: 1, max: 254),
        );
    }

    private function seedActiveUser(string $email, string $nickname): void
    {
        $user = User::create(
            name: UserName::fromString('Тест Пользователь'),
            email: Email::fromString($email),
            nickname: UserNickname::fromString($nickname),
            locale: Locale::Ru,
        );
        $user->confirmEmail();

        $this->entityManager()->persist($user);
        $this->entityManager()->run();
    }

    private function seedLoginCode(string $email, string $code, int $failedAttempts = 0): void
    {
        $now = new \DateTimeImmutable();
        $loginCode = LoginCode::issue(
            id: LoginCodeId::generate(),
            email: EmailAddress::fromString($email),
            codeHash: SecretHash::fromString($this->secretHasher()->hash($code)),
            expiration: Expiration::after(now: $now, seconds: 600),
            now: $now,
        );

        for ($attempt = 0; $attempt < $failedAttempts; $attempt++) {
            $loginCode->registerFailedAttempt($now);
        }

        $this->entityManager()->persist($loginCode);
        $this->entityManager()->run();
    }

    private function seedRegistrationTicket(string $email, string $ticketRaw): void
    {
        $now = new \DateTimeImmutable();
        $ticket = RegistrationTicket::issue(
            id: RegistrationTicketId::generate(),
            email: EmailAddress::fromString($email),
            ticketHash: SecretHash::fromString($this->secretHasher()->hash($ticketRaw)),
            expiration: Expiration::after(now: $now, seconds: 900),
            now: $now,
        );

        $this->entityManager()->persist($ticket);
        $this->entityManager()->run();
    }

    private function registrationTicketRepositoryFindActive(string $ticketRaw): RegistrationTicket|null
    {
        return $this->getContainer()
            ->get(\App\Modules\Auth\Repository\RegistrationTicketRepository::class)
            ->findActiveByHashForUpdate(SecretHash::fromString($this->secretHasher()->hash($ticketRaw)));
    }

    private function issueTokenPair(): IssuedTokenPair
    {
        return $this->getContainer()->get(AuthTokenStorageContract::class)->issuePair(UserId::generate());
    }

    private function secretHasher(): SecretHasherContract
    {
        return $this->getContainer()->get(SecretHasherContract::class);
    }

    private function entityManager(): EntityManagerInterface
    {
        return $this->getContainer()->get(EntityManagerInterface::class);
    }

    private function loginCodeRepository(): LoginCodeRepository
    {
        return $this->getContainer()->get(LoginCodeRepository::class);
    }

    private function userRepository(): UserRepository
    {
        return $this->getContainer()->get(UserRepository::class);
    }
}

exec
/bin/zsh -lc "sed -n '1,240p' app/src/Modules/Auth/Repository/LoginCodeRepository.php && sed -n '1,240p' app/src/Modules/Auth/Repository/AuthTokenRepository.php && sed -n '1,220p' app/src/Modules/Auth/Repository/RegistrationTicketRepository.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Auth\Repository;

use App\Modules\Auth\Domain\Entity\LoginCode;
use App\Modules\Auth\Domain\ValueObject\EmailAddress;
use Cycle\ORM\Select\Repository;

/**
 * @extends Repository<LoginCode>
 */
final class LoginCodeRepository extends Repository
{
    /**
     * Последний непогашенный код по email с блокировкой строки. Срок не фильтруется в запросе:
     * различение Expired/NoCode делает доменный сценарий по isExpired(). forUpdate применяется
     * прямо в цепочке select() (через Repository::forUpdate() флаг блокировки теряется при clone).
     */
    public function findActiveByEmailForUpdate(EmailAddress $email): LoginCode|null
    {
        return $this->select()
            ->where('email', $email->value())
            ->where('consumed_at', null)
            ->orderBy(['id' => 'DESC'])
            ->forUpdate()
            ->fetchOne();
    }

    /**
     * Последний непогашенный код по email без блокировки — для троттлинга «не чаще одного кода
     * в минуту»: сценарий смотрит на createdAt последнего активного кода.
     */
    public function findActiveByEmail(EmailAddress $email): LoginCode|null
    {
        return $this->select()
            ->where('email', $email->value())
            ->where('consumed_at', null)
            ->orderBy(['id' => 'DESC'])
            ->fetchOne();
    }
}
<?php

declare(strict_types=1);

namespace App\Modules\Auth\Repository;

use App\Modules\Auth\Domain\Collection\AuthTokenCollection;
use App\Modules\Auth\Domain\Entity\AuthToken;
use App\Modules\Auth\Domain\ValueObject\SessionId;
use App\Modules\Auth\Domain\ValueObject\TokenHash;
use Cycle\ORM\Select\Repository;

/**
 * @extends Repository<AuthToken>
 */
final class AuthTokenRepository extends Repository
{
    /**
     * Обычное чтение по хэшу (token_hash UNIQUE) — для load() на каждом запросе, без блокировки.
     */
    public function findByHash(TokenHash $tokenHash): AuthToken|null
    {
        return $this->select()
            ->where('token_hash', $tokenHash->value())
            ->fetchOne();
    }

    /**
     * Чтение по хэшу с блокировкой строки — для ротации refresh-токена.
     */
    public function findByHashForUpdate(TokenHash $tokenHash): AuthToken|null
    {
        return $this->select()
            ->where('token_hash', $tokenHash->value())
            ->forUpdate()
            ->fetchOne();
    }

    /**
     * Все токены сессии с блокировкой строк — для ротации и отзыва сессии.
     */
    public function findBySessionIdForUpdate(SessionId $sessionId): AuthTokenCollection
    {
        return new AuthTokenCollection(
            $this->select()
                ->where('session_id', $sessionId->value())
                ->forUpdate()
                ->fetchAll(),
        );
    }
}
<?php

declare(strict_types=1);

namespace App\Modules\Auth\Repository;

use App\Modules\Auth\Domain\Entity\RegistrationTicket;
use App\Modules\Auth\Domain\ValueObject\SecretHash;
use Cycle\ORM\Select\Repository;

/**
 * @extends Repository<RegistrationTicket>
 */
final class RegistrationTicketRepository extends Repository
{
    /**
     * Непогашенный талон по хэшу с блокировкой строки. Срок проверяет доменный сценарий.
     */
    public function findActiveByHashForUpdate(SecretHash $ticketHash): RegistrationTicket|null
    {
        return $this->select()
            ->where('ticket_hash', $ticketHash->value())
            ->where('consumed_at', null)
            ->forUpdate()
            ->fetchOne();
    }
}

exec
/bin/zsh -lc "sed -n '1,260p' app/src/Modules/Auth/Infrastructure/Bootloader/AuthBootloader.php && sed -n '1,220p' app/src/Modules/Auth/Presentation/Http/Middleware/AuthContextAttributeMiddleware.php && sed -n '1,220p' app/src/Modules/Auth/Presentation/Http/Middleware/RequireAuthenticatedMiddleware.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Bootloader;

use App\Modules\Auth\Application\Contract\AuthTokenStorageContract;
use App\Modules\Auth\Application\Contract\SecretHasherContract;
use App\Modules\Auth\Application\Contract\TokenGeneratorContract;
use App\Modules\Auth\Application\Message\LoginCodeRequested;
use App\Modules\Auth\Infrastructure\Auth\CycleTokenStorage;
use App\Modules\Auth\Infrastructure\Auth\RandomTokenGenerator;
use App\Modules\Auth\Infrastructure\Auth\UserActorProvider;
use App\Modules\Auth\Infrastructure\Hash\HmacSecretHasher;
use App\Modules\Auth\Presentation\Job\SendLoginCodeJob;
use App\Modules\Outbox\Application\Contract\OutboxJobRegistryContract;
use Spiral\Auth\Transport\HeaderTransport;
use Spiral\Bootloader\Auth\AuthBootloader as SpiralAuthBootloader;
use Spiral\Bootloader\Auth\HttpAuthBootloader;
use Spiral\Boot\Bootloader\Bootloader;

/**
 * Каркас аутентификации модуля Auth. Контракты Application привязаны к инфраструктурным
 * реализациям. Транспорт (Authorization: Bearer), хранилище токенов (cycle) и actor-provider
 * регистрируются кодом без app/config/auth.php. Пара LoginCodeRequested → SendLoginCodeJob
 * регистрируется в outbox-реестре.
 */
final class AuthBootloader extends Bootloader
{
    protected const BINDINGS = [
        SecretHasherContract::class => HmacSecretHasher::class,
        TokenGeneratorContract::class => RandomTokenGenerator::class,
        AuthTokenStorageContract::class => CycleTokenStorage::class,
    ];

    /**
     * @return array<int, class-string>
     */
    public function defineDependencies(): array
    {
        return [HttpAuthBootloader::class, SpiralAuthBootloader::class];
    }

    public function init(HttpAuthBootloader $httpAuth, SpiralAuthBootloader $auth): void
    {
        $httpAuth->addTransport(
            name: 'header',
            transport: new HeaderTransport(header: 'Authorization', valueFormat: 'Bearer %s'),
        );
        $httpAuth->addTokenStorage(name: 'cycle', storage: CycleTokenStorage::class);
        $auth->addActorProvider(UserActorProvider::class);
    }

    public function boot(OutboxJobRegistryContract $outboxJobRegistry): void
    {
        $outboxJobRegistry->register(
            outboxMessageClass: LoginCodeRequested::class,
            outboxJobClass: SendLoginCodeJob::class,
        );
    }
}
<?php

declare(strict_types=1);

namespace App\Modules\Auth\Presentation\Http\Middleware;

use App\Modules\Auth\Domain\Enum\AuthTokenType;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Spiral\Auth\AuthContextInterface;

/**
 * Кладёт authUserId/authSessionId из payload access-токена в request-атрибуты, чтобы контроллер
 * читал их через Filter #[Attribute], а не ServerRequestInterface. При отсутствии/битом payload
 * или для refresh-токена атрибуты не ставятся (и middleware не падает).
 */
final readonly class AuthContextAttributeMiddleware implements MiddlewareInterface
{
    public const string ATTRIBUTE_USER_ID = 'authUserId';
    public const string ATTRIBUTE_SESSION_ID = 'authSessionId';

    public function __construct(
        private AuthContextInterface $authContext,
    ) {}

    #[\Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $token = $this->authContext->getToken();

        if ($token === null) {
            return $handler->handle($request);
        }

        $payload = $token->getPayload();

        if (($payload['type'] ?? null) !== AuthTokenType::Access->value) {
            return $handler->handle($request);
        }

        $userId = $payload['userID'] ?? null;
        $sessionId = $payload['sessionID'] ?? null;

        if (\is_string($userId) && \is_string($sessionId)) {
            // PSR-7 ServerRequestInterface::withAttribute объявляет первый параметр как $name,
            // но конкретные реализации (Nyholm — $attribute) расходятся в имени, поэтому named
            // arguments невозможны и используются позиционные (правило погашено в phpstan.neon).
            $request = $request
                ->withAttribute(self::ATTRIBUTE_USER_ID, $userId)
                ->withAttribute(self::ATTRIBUTE_SESSION_ID, $sessionId);
        }

        return $handler->handle($request);
    }
}
<?php

declare(strict_types=1);

namespace App\Modules\Auth\Presentation\Http\Middleware;

use GianTiaga\SpiralOpenApi\Response\Enum\HttpStatus;
use GianTiaga\SpiralOpenApi\Response\ErrorResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Spiral\Translator\TranslatorInterface;

/**
 * Требует аутентифицированного пользователя: если authUserId не выставлен (нет/невалидный
 * access-токен), отдаёт 401 JSON напрямую (middleware вне цепочки controller-интерсепторов,
 * поэтому исключение тут не сконвертируется).
 */
final readonly class RequireAuthenticatedMiddleware implements MiddlewareInterface
{
    public function __construct(
        private TranslatorInterface $translator,
    ) {}

    #[\Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getAttribute(AuthContextAttributeMiddleware::ATTRIBUTE_USER_ID) === null) {
            $errorResponse = new ErrorResponse(
                message: $this->translator->trans(id: 'app.auth.unauthenticated', parameters: [], domain: 'auth'),
                code: HttpStatus::Unauthorized->value,
            );

            return $errorResponse->withStatus(HttpStatus::Unauthorized)->toResponse();
        }

        return $handler->handle($request);
    }
}

exec
/bin/zsh -lc "sed -n '1,260p' tests/Feature/Modules/Auth/Application/LoginCodeVerificationTest.php && sed -n '1,260p' tests/Feature/Modules/Auth/Application/CompleteRegistrationHandlerTest.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Auth\Application;

use App\Modules\Auth\Application\Command\Auth\ResolveLoginCode\ResolveLoginCodeHandler;
use App\Modules\Auth\Application\Command\Auth\VerifyLoginCode\VerifyLoginCodeCommand;
use App\Modules\Auth\Application\Command\Auth\VerifyLoginCode\VerifyLoginCodeHandler;
use App\Modules\Auth\Domain\Entity\LoginCode;
use App\Modules\Auth\Domain\Entity\RegistrationTicket;
use App\Modules\Auth\Domain\ValueObject\EmailAddress;
use App\Modules\Auth\Domain\ValueObject\Expiration;
use App\Modules\Auth\Domain\ValueObject\SecretHash;
use App\Modules\Auth\Infrastructure\Auth\RandomTokenGenerator;
use App\Shared\Domain\Exception\AuthenticationException;
use Tests\Feature\Modules\Auth\Application\AuthApplicationTestCase;

final class LoginCodeVerificationTest extends AuthApplicationTestCase
{
    public function testVerifiesExistingActiveUserAndReturnsTokens(): void
    {
        $this->persistUser('user@example.com', 'user.one');
        $this->persistLoginCode('user@example.com', '123456');

        $result = $this->verifyHandler()->handle(new VerifyLoginCodeCommand(email: 'user@example.com', code: '123456'));

        self::assertFalse($result->needsProfile);
        self::assertNotNull($result->tokens);
        self::assertNotSame('', $result->tokens->accessToken);
        self::assertNull($this->loginCodeRepository()->findActiveByEmail(EmailAddress::fromString('user@example.com')));
    }

    public function testVerifiesNewEmailAndIssuesRegistrationTicket(): void
    {
        $this->persistLoginCode('newbie@example.com', '123456');

        $result = $this->verifyHandler()->handle(new VerifyLoginCodeCommand(email: 'newbie@example.com', code: '123456'));

        self::assertTrue($result->needsProfile);
        self::assertNull($result->tokens);
        self::assertNotNull($result->registrationTicket);

        $ticket = $this->registrationTicketRepository()->findActiveByHashForUpdate(
            SecretHash::fromString($this->secretHasher()->hash($result->registrationTicket)),
        );
        self::assertInstanceOf(RegistrationTicket::class, $ticket);
        self::assertSame('newbie@example.com', $ticket->email->value());
    }

    public function testWrongCodeIncrementsAttemptsAndThrows(): void
    {
        $this->persistLoginCode('user@example.com', '123456');

        try {
            $this->verifyHandler()->handle(new VerifyLoginCodeCommand(email: 'user@example.com', code: '000000'));
            self::fail('Ожидалось AuthenticationException.');
        } catch (AuthenticationException $authenticationException) {
            self::assertSame('app.auth.invalid_code', $authenticationException->getMessage());
        }

        $this->cleanOrmHeap();
        $loginCode = $this->loginCodeRepository()->findActiveByEmail(EmailAddress::fromString('user@example.com'));
        self::assertInstanceOf(LoginCode::class, $loginCode);
        self::assertSame(1, $loginCode->attempts->value());
        self::assertFalse($loginCode->isConsumed());
    }

    public function testExhaustedAttemptsThrows(): void
    {
        $this->persistLoginCode('user@example.com', '123456', failedAttempts: 5);

        $this->expectException(AuthenticationException::class);

        $this->verifyHandler()->handle(new VerifyLoginCodeCommand(email: 'user@example.com', code: '123456'));
    }

    public function testExpiredCodeThrows(): void
    {
        $this->persistLoginCode(
            'user@example.com',
            '123456',
            expiration: Expiration::fromDateTime((new \DateTimeImmutable())->sub(new \DateInterval('PT1H'))),
        );

        $this->expectException(AuthenticationException::class);

        $this->verifyHandler()->handle(new VerifyLoginCodeCommand(email: 'user@example.com', code: '123456'));
    }

    public function testMissingCodeThrows(): void
    {
        $this->expectException(AuthenticationException::class);

        $this->verifyHandler()->handle(new VerifyLoginCodeCommand(email: 'absent@example.com', code: '123456'));
    }

    public function testBannedUserIsNotAllowedAndCodeConsumed(): void
    {
        $this->persistUser('banned@example.com', 'banned.user', banned: true);
        $this->persistLoginCode('banned@example.com', '123456');

        try {
            $this->verifyHandler()->handle(new VerifyLoginCodeCommand(email: 'banned@example.com', code: '123456'));
            self::fail('Ожидалось AuthenticationException.');
        } catch (AuthenticationException $authenticationException) {
            self::assertSame('app.auth.sign_in_not_allowed', $authenticationException->getMessage());
        }

        $this->cleanOrmHeap();
        self::assertNull($this->loginCodeRepository()->findActiveByEmail(EmailAddress::fromString('banned@example.com')));
    }

    private function verifyHandler(): VerifyLoginCodeHandler
    {
        return new VerifyLoginCodeHandler(
            commandBus: $this->commandBus(),
            resolveLoginCodeHandler: new ResolveLoginCodeHandler(
                loginCodeRepository: $this->loginCodeRepository(),
                secretHasher: $this->secretHasher(),
                tokenGenerator: new RandomTokenGenerator(),
                authTokenStorage: $this->tokenStorage(),
                queryBus: $this->queryBus(),
                findUserForAuthHandler: $this->findUserForAuthHandler(),
                entityManager: $this->entityManager(),
            ),
        );
    }
}
<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Auth\Application;

use App\Modules\Auth\Application\Command\Auth\CompleteRegistration\CompleteRegistrationCommand;
use App\Modules\Auth\Application\Command\Auth\CompleteRegistration\CompleteRegistrationHandler;
use App\Modules\Auth\Domain\Entity\RegistrationTicket;
use App\Modules\Auth\Domain\ValueObject\Expiration;
use App\Modules\Auth\Domain\ValueObject\SecretHash;
use App\Modules\User\Domain\Entity\User;
use App\Modules\User\Domain\ValueObject\Email;
use App\Shared\Domain\Exception\AuthenticationException;
use App\Shared\Domain\Exception\ValidationException;
use Psr\Log\NullLogger;

final class CompleteRegistrationHandlerTest extends AuthApplicationTestCase
{
    public function testCompletesRegistrationCreatesActiveUserAndTokens(): void
    {
        $this->persistRegistrationTicket('newbie@example.com', 'ticket-raw-1');

        $tokens = $this->handler()->handle(new CompleteRegistrationCommand(
            ticket: 'ticket-raw-1',
            name: 'Имя Тест',
            nickname: 'newbie.one',
            requestLocale: 'ru',
        ));

        self::assertNotSame('', $tokens->accessToken);
        self::assertNotSame('', $tokens->refreshToken);

        $user = $this->userRepository()->findByEmail(Email::fromString('newbie@example.com'));
        self::assertInstanceOf(User::class, $user);
        self::assertTrue($user->isActive());

        self::assertNull(
            $this->registrationTicketRepository()->findActiveByHashForUpdate(
                SecretHash::fromString($this->secretHasher()->hash('ticket-raw-1')),
            ),
        );
    }

    public function testRejectsUnknownTicket(): void
    {
        $this->expectException(AuthenticationException::class);

        $this->handler()->handle(new CompleteRegistrationCommand(
            ticket: 'unknown-ticket',
            name: 'Имя Тест',
            nickname: 'newbie.one',
            requestLocale: 'ru',
        ));
    }

    public function testRejectsExpiredTicket(): void
    {
        $this->persistRegistrationTicket(
            'newbie@example.com',
            'ticket-raw-2',
            expiration: Expiration::fromDateTime((new \DateTimeImmutable())->sub(new \DateInterval('PT1H'))),
        );

        $this->expectException(AuthenticationException::class);

        $this->handler()->handle(new CompleteRegistrationCommand(
            ticket: 'ticket-raw-2',
            name: 'Имя Тест',
            nickname: 'newbie.one',
            requestLocale: 'ru',
        ));
    }

    public function testRejectsTakenNicknameAndKeepsTicketReusable(): void
    {
        $this->persistUser('owner@example.com', 'taken.nick');
        $this->persistRegistrationTicket('newbie@example.com', 'ticket-raw-3');

        try {
            $this->handler()->handle(new CompleteRegistrationCommand(
                ticket: 'ticket-raw-3',
                name: 'Имя Тест',
                nickname: 'taken.nick',
                requestLocale: 'ru',
            ));
            self::fail('Ожидалось ValidationException.');
        } catch (ValidationException $validationException) {
            self::assertSame('app.user.nickname_taken', $validationException->getMessage());
        }

        $this->cleanOrmHeap();
        $ticket = $this->registrationTicketRepository()->findActiveByHashForUpdate(
            SecretHash::fromString($this->secretHasher()->hash('ticket-raw-3')),
        );
        self::assertInstanceOf(RegistrationTicket::class, $ticket);
    }

    private function handler(): CompleteRegistrationHandler
    {
        return new CompleteRegistrationHandler(
            registrationTicketRepository: $this->registrationTicketRepository(),
            secretHasher: $this->secretHasher(),
            authTokenStorage: $this->tokenStorage(),
            commandBus: $this->commandBus(),
            createUserHandler: $this->createUserHandler(),
            entityManager: $this->entityManager(),
            logger: new NullLogger(),
        );
    }
}

exec
/bin/zsh -lc "sed -n '1,220p' packages/spiral-api-errors/src/Filter/ApiValidationErrorsRenderer.php && rg -n \"ApiValidationErrorsRenderer|validation\" packages/spiral-api-errors/tests tests/Feature/Modules/Auth tests/Feature/Modules/System" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare (strict_types=1);

namespace GianTiaga\SpiralApiErrors\Filter;

use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Spiral\Filters\ErrorsRendererInterface;
use Spiral\Translator\TranslatorInterface;
use GianTiaga\SpiralOpenApi\Response\Enum\HttpStatus;
use GianTiaga\SpiralOpenApi\Response\ValidationErrorItemResponse;
use GianTiaga\SpiralOpenApi\Response\ValidationErrorResponse;

final readonly class ApiValidationErrorsRenderer implements ErrorsRendererInterface
{
    public function __construct(private LoggerInterface $logger, private TranslatorInterface $translator) {}
    /**
     * @param array<string, string> $errors
     */
    public function render(array $errors, mixed $context = null): ResponseInterface
    {
        $this->logger->debug(message: 'API вернул ошибки валидации Filter.', context: ['fields' => \array_keys($errors)]);
        return (new ValidationErrorResponse(message: $this->translator->trans(id: 'gian_tiaga.spiral_api_errors.validation_error'), code: HttpStatus::UnprocessableEntity->value, errors: $this->validationErrors($errors)))->withStatus(HttpStatus::UnprocessableEntity)->toResponse();
    }
    /**
     * @param array<string, string> $errors
     * @return list<ValidationErrorItemResponse>
     */
    private function validationErrors(array $errors): array
    {
        $validationErrors = [];
        foreach ($errors as $field => $message) {
            $validationErrors[] = new ValidationErrorItemResponse(field: $field, message: $this->firstMessage($message));
        }
        return $validationErrors;
    }
    /**
     * Symfony-валидатор отдаёт сообщения поля списком, а casting-ошибки — строкой. Берём первое
     * сообщение, чтобы рендерер работал с обоими форматами без сложных типов в контракте.
     *
     * @param string|list<string> $message
     */
    private function firstMessage(string|array $message): string
    {
        if (\is_array($message)) {
            return $message[0] ?? '';
        }
        return $message;
    }
}
packages/spiral-api-errors/tests/Filter/ApiValidationErrorsRendererTest.php:9:use GianTiaga\SpiralApiErrors\Filter\ApiValidationErrorsRenderer;
packages/spiral-api-errors/tests/Filter/ApiValidationErrorsRendererTest.php:15:final class ApiValidationErrorsRendererTest extends TestCase
packages/spiral-api-errors/tests/Filter/ApiValidationErrorsRendererTest.php:23:        $this->assertValidationErrorResponse(translator: new FakeTranslator(locale: 'ru', messages: ['gian_tiaga.spiral_api_errors.validation_error' => 'Ошибка валидации']), expectedBody: '{"message":"Ошибка валидации","code":422,"errors":[{"field":"email","message":"Некорректный email"},{"field":"password","message":"Пароль обязателен"}]}');
packages/spiral-api-errors/tests/Filter/ApiValidationErrorsRendererTest.php:27:        $response = (new ApiValidationErrorsRenderer(logger: new NullLogger(), translator: $translator))->render(['email' => 'Некорректный email', 'password' => 'Пароль обязателен']);
packages/spiral-api-errors/tests/Filter/ApiValidationErrorsRendererTest.php:34:        return new FakeTranslator(locale: 'en', messages: ['gian_tiaga.spiral_api_errors.validation_error' => 'Validation error']);
tests/Feature/Modules/System/Http/ApiErrorHttpTest.php:13:use GianTiaga\SpiralApiErrors\Filter\ApiValidationErrorsRenderer;
tests/Feature/Modules/System/Http/ApiErrorHttpTest.php:74:    public function testValidationHandlerMiddlewareUsesApiValidationErrorsRenderer(): void
tests/Feature/Modules/System/Http/ApiErrorHttpTest.php:77:            ApiValidationErrorsRenderer::class,
packages/spiral-api-errors/tests/ConstructorContractTest.php:9:use GianTiaga\SpiralApiErrors\Filter\ApiValidationErrorsRenderer;
packages/spiral-api-errors/tests/ConstructorContractTest.php:18:        $this->assertRequiredTranslatorParameter(className: ApiValidationErrorsRenderer::class, parameterIndex: 1);
tests/Feature/Modules/Auth/Application/CompleteRegistrationHandlerTest.php:88:        } catch (ValidationException $validationException) {
tests/Feature/Modules/Auth/Application/CompleteRegistrationHandlerTest.php:89:            self::assertSame('app.user.nickname_taken', $validationException->getMessage());

codex
Пункты про `LoginCodeValue` и TTL уже подтверждаются кодом. Сейчас добираю возможные пропуски: нарушения правил проекта, проблемы на границе HTTP, тесты маршрутов и правку переносимого пакета.
exec
/bin/zsh -lc "rg -n \"try\\s*\\{|catch\\s*\\(|RuntimeException|UnexpectedValueException|assert\\(|\\bmixed\\b|==(?"'!=)|!=(?!=)|<>|switch'"\\s*\\(\" app/src/Modules/Auth app/src/Modules/User/Application app/src/Shared/Infrastructure/Framework/Middleware tests/Feature/Modules/Auth tests/Unit/Modules/Auth packages/spiral-api-errors/src/Filter/ApiValidationErrorsRenderer.php packages/spiral-api-errors/tests/Filter/ApiValidationErrorsRendererTest.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 exited 2 in 0ms:
rg: regex parse error:
    (?:try\s*\{|catch\s*\(|RuntimeException|UnexpectedValueException|assert\(|\bmixed\b|==(?!=)|!=(?!=)|<>|switch\s*\()
                                                                                          ^^^
error: look-around, including look-ahead and look-behind, is not supported

Consider enabling PCRE2 with the --pcre2 flag, which can handle backreferences
and look-around.

exec
/bin/zsh -lc "find app/src/Modules/Auth app/src/Modules/User/Application app/src/Shared/Infrastructure/Framework/Middleware -name '*.php' -print0 | xargs -0 -n1 sh -c 'lines="'$(wc -l < "$0"); if [ "$lines" -gt 120 ]; then printf "%s %s'"\\n\" \""'$lines" "$0"; fi'"'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc "sed -n '1,220p' packages/spiral-api-errors/tests/Filter/ApiValidationErrorsRendererTest.php && sed -n '1,220p' docs/executions/2026-06-15_14-17_auth-module.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare (strict_types=1);

namespace GianTiaga\SpiralApiErrors\Tests\Filter;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use GianTiaga\SpiralApiErrors\Filter\ApiValidationErrorsRenderer;
use GianTiaga\SpiralApiErrors\Tests\Support\FakeTranslator;
use GianTiaga\SpiralOpenApi\Response\Enum\ContentType;
use GianTiaga\SpiralOpenApi\Response\Enum\HttpHeader;
use GianTiaga\SpiralOpenApi\Response\Enum\HttpStatus;

final class ApiValidationErrorsRendererTest extends TestCase
{
    public function testRendererReturnsEnglishValidationErrorResponse(): void
    {
        $this->assertValidationErrorResponse(translator: self::englishTranslator(), expectedBody: '{"message":"Validation error","code":422,"errors":[{"field":"email","message":"Некорректный email"},{"field":"password","message":"Пароль обязателен"}]}');
    }
    public function testRendererReturnsRussianValidationErrorResponse(): void
    {
        $this->assertValidationErrorResponse(translator: new FakeTranslator(locale: 'ru', messages: ['gian_tiaga.spiral_api_errors.validation_error' => 'Ошибка валидации']), expectedBody: '{"message":"Ошибка валидации","code":422,"errors":[{"field":"email","message":"Некорректный email"},{"field":"password","message":"Пароль обязателен"}]}');
    }
    private function assertValidationErrorResponse(FakeTranslator $translator, string $expectedBody): void
    {
        $response = (new ApiValidationErrorsRenderer(logger: new NullLogger(), translator: $translator))->render(['email' => 'Некорректный email', 'password' => 'Пароль обязателен']);
        self::assertSame(HttpStatus::UnprocessableEntity->value, $response->getStatusCode());
        self::assertSame(ContentType::Json->value, $response->getHeaderLine(HttpHeader::ContentType->value));
        self::assertSame($expectedBody, (string) $response->getBody());
    }
    private static function englishTranslator(): FakeTranslator
    {
        return new FakeTranslator(locale: 'en', messages: ['gian_tiaga.spiral_api_errors.validation_error' => 'Validation error']);
    }
}
---
plan: docs/plans/2026-06-15_13-25_auth-module.md
started: 2026-06-15 14:17
finished: 2026-06-15 15:48
status: done
---

# Журнал: Модуль Auth — беспарольный вход по email-коду

## Шаги
| # | Шаг | Файлы | Тесты | Статус |
|---|-----|-------|-------|--------|
| 1 | User/Application: CreateUser (#[Transactional]) + FindUserForAuth + UserAuthView + переводы user.php | CreateUserCommand/Handler/Result, FindUserForAuthQuery/Handler, Dto/UserAuthView, locale/{ru,en}/user.php | CreateUserHandlerTest, FindUserForAuthHandlerTest (8 OK) | done |
| 2 | Auth Domain: 11 VO, enum AuthTokenType, AuthTokenCollection, сущности LoginCode/RegistrationTicket/AuthToken, ExpirationTypecast/ConsumptionTypecast, 3 репозитория, миграция 3 таблиц | Domain/ValueObject/*, Domain/Entity/*, Domain/Enum/AuthTokenType, Domain/Collection/AuthTokenCollection, Infrastructure/Cycle/*, Repository/*, migrations/...create_auth_domain_tables | AuthValueObjectTest, AuthEntityTest, AuthTypecastTest, AuthRepositoryTest (40 OK) | done |
| 3 | Auth Infrastructure: контракты, IssuedTokenPair, HmacSecretHasher, RandomTokenGenerator, AuthTokenView, CycleTokenStorage, UserActorProvider, AuthenticatedUser | Application/Contract/*, Application/Dto/IssuedTokenPair, Infrastructure/Hash/*, Infrastructure/Auth/* | HmacSecretHasherTest, RandomTokenGeneratorTest, AuthTokenViewTest, UserActorProviderTest, CycleTokenStorageTest (19 OK) | done |
| 4 | Auth Application: RequestLoginCode, ResolveLoginCode (#[Transactional]), VerifyLoginCode (оркестратор), CompleteRegistration, RefreshTokens, Logout; LoginCodeOutcome/Resolution; VerifyLoginCodeResult; Message LoginCodeRequested | Application/Command/Auth/* (6 действий), Application/Message/LoginCodeRequested | RequestLoginCodeHandlerTest, LoginCodeVerificationTest, CompleteRegistrationHandlerTest, RefreshTokensAndLogoutTest (17 OK) | done |
| 5 | Доставка письма: SendLoginCode (Command/Handler, #[LogOperation]), SendLoginCodeJob, twig-шаблон login-code, переводы auth.php (тема/тело + ключи ошибок) | Application/Command/Auth/SendLoginCode/*, Presentation/Job/SendLoginCodeJob, app/views/login-code.twig, locale/{ru,en}/auth.php | SendLoginCodeHandlerTest, SendLoginCodeJobTest (5 OK) | done |
| 6 | Shared rate limit: RateLimitMiddleware (per-route, redis storage, sha256(ip\|path), 429 через ErrorResponse), перевод shared.php | Shared/Infrastructure/Framework/Middleware/RateLimitMiddleware, locale/{ru,en}/shared.php | RateLimitMiddlewareTest (весь Unit-сьют 299 OK) | done |
| 7 | Auth Presentation: 5 Filter, 4 Resource, AuthController (5 маршрутов), 2 middleware (AuthContextAttribute, RequireAuthenticated), AuthBootloader, Kernel, queue.php, OpenAPI scan; правка рендерера пакета (массив сообщений) | Presentation/Http/{Filter,Resource,Controller,Middleware}/*, Infrastructure/Bootloader/AuthBootloader, Kernel.php, config/{queue,openapi}.php, packages/spiral-api-errors ApiValidationErrorsRenderer, locale auth.php (unauthenticated) | AuthHttpTest (16), AuthContextAttributeMiddlewareTest (4), AuthBootloaderTest (3), AuthOpenApiGenerationTest (1) — 24 OK | done |

## Заметки

- **Уточнение по фазе 2 (репозитории кодов/талонов).** В разделе «Контракты» план говорит `findActiveByEmailForUpdate` = «consumed_at IS NULL и не истёк», но «Целевой алгоритм» требует, чтобы `ResolveLoginCode` различал `Expired` vs `NoCode` — значит репозиторий обязан возвращать последний НЕпогашенный код БЕЗ фильтра по сроку (срок проверяет домен). Фильтрация срока в запросе сделала бы ветку `Expired` недостижимой → мёртвый код (нарушение rules.md + проваленное 100% покрытие). Реализую `findActiveByEmail(For Update)` как «consumed_at IS NULL, последний по id», срок проверяется в хендлере. Это согласует контракт с алгоритмом и правилами.
- Cycle `Select::where()` использует имена колонок snake_case (`where('user_id', ...)`, `where('consumed_at', null)` → IS NULL). forUpdate() — в цепочке select().

- Место выполнения: остаёмся на ветке `main` (выбор пользователя).
- Тесты запускаются только в Docker: `make test` (полный gate: reset-test → migrate → warmup → ParaTest Unit+Kernel+Feature), `make phpstan`. Docker-сервисы (postgres/redis/minio/mailpit) подняты.
- Тестовая схема строится миграциями (`migrate-test-databases.sh`), не sync из аннотаций → миграция Auth обязательна (фаза 2).
- В тестах `QUEUE_CONNECTION=sync`, `CACHE_STORAGE=local`, `MAILER_DSN=smtp://mailpit:1025`, `REDIS_DSN=redis://redis:6379/0`.

## Принятые по ходу решения

- **`ApiValidationErrorsRenderer` (packages/spiral-api-errors) научен принимать и строку, и список сообщений поля.** Symfony-валидатор (`spiral-packages/symfony-validator`) отдаёт ошибки поля как `list<string>`, а рендерер ждал строку → Filter-валидация (Assert) давала 500 вместо 422. Это первый модуль с Assert-констрейнтами. Решение подтверждено пользователем (вариант «починить рендерер в пакете»). Добавлен `firstMessage(string|list<string>)`, берёт первое сообщение; контракт `array<string,string>` сохранён (без сложных типов / mixed в PHPDoc, strict-rules пакета зелёные). Пакетные phpstan+тесты прогнаны.
- **OpenAPI scan расширен на все модули.** `app/config/openapi.php`: `sourcePath` = `app/src/Modules`, `apiNamespace` = `App\Modules`. Генератор фильтрует контроллеры по префиксу namespace и строит операции только из `#[Route]`-методов, поэтому System и Auth попадают вместе; ключ `openapi.sourcePath` (строка) сохранён — тест `OpenApiGenerateCommandTest` не сломан. Сгенерировано 6 операций (5 Auth + health).
- **`RateLimitMiddleware`: ключ окна = `sha256(clientIp . '|' . requestPath)`.** План писал «routeName»; путь запроса уникален на эндпоинт, не зависит от внутренних атрибутов роутера — функционально эквивалентно. TTL обновляется на каждом запросе окна (PSR-16 не даёт инкремент с сохранением TTL) — допустимая реализация фиксированного/скользящего окна, перерасчёт при гонке принят планом.
- **`withAttribute` в `AuthContextAttributeMiddleware` — позиционные аргументы.** PSR-7 объявляет `$name`, но Nyholm — `$attribute`; named arguments падали в рантайме. Правило named-arguments погашено точечно в `phpstan.neon` (по образцу `Normalizer::normalize`).
- **Чтение `authSessionId` в контроллере — через `LogoutFilter` с `#[Attribute(key: 'authSessionId')]`** (input-источник Filter), а не параметр контроллера: в стеке нет резолвера request-атрибутов для параметров метода, а Filter-источник `attribute` идиоматичен и не нарушает запрет `ServerRequestInterface` в контроллере. Читается только `authSessionId` (logout не использует `authUserId`).
- **Тесты `User/Application` и `Auth/Application` (sync-сценарии) и HTTP** строятся ручной сборкой хендлеров (как `MediaApplicationTestCase`); `AuthHttpTest` — `NonTransactionalDatabaseTestCase` с самоочисткой таблиц в `setUp` и уникальным client IP на тест (изоляция Redis-счётчика rate limit между воркерами/прогонами).

## Финальная проверка

- `make qa` (стиль + PHPStan + покрытие PCOV, после reset-test) — **зелёный**: 592 теста, 1929 assertions, покрытие **100.00%** (порог 100%), PHPStan без ошибок, php-cs-fixer чист.
- 1 PHPUnit Notice во всём сьюте — предсуществующий, не из модуля Auth (прогон всех Auth-тестов `--display-notices`: 110 тестов OK, нотисов нет); политика проекта валит только на deprecations, гейт зелёный (exit 0).
- `php app.php openapi:generate` — 6 операций (5 Auth + health), 7 schemas; `public/openapi/openapi.yml` обновлён, содержит все 5 маршрутов Auth.
- Пакет `packages/spiral-api-errors`: `composer phpstan` + `composer test` — зелёные после правки рендерера.
- Git: изменения НЕ закоммичены.

## Изменения в docs

- Кандидаты на упоминание в `docs/arch.md` (вынесено отдельной задачей `eda-docs`, не критично):
  - OpenAPI сканирует `app/src/Modules` с фильтром по namespace `App\Modules` (раньше — только System/Presentation/Http).
  - `ApiValidationErrorsRenderer` поддерживает формат ошибок Symfony-валидатора (список сообщений на поле).
  - Новый общий `RateLimitMiddleware` (per-route, Redis, IP+путь) в `Shared/Infrastructure/Framework/Middleware`.

exec
/bin/zsh -lc "rg -n \"public function handle|function process|function issuePair|function rotate|function revokeSession|function create\\(\" app/src/Modules/Auth app/src/Modules/User/Application app/src/Shared/Infrastructure/Framework/Middleware" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
app/src/Modules/User/Application/Query/User/FindUserForAuth/FindUserForAuthHandler.php:17:    public function handle(FindUserForAuthQuery $query): UserAuthView|null
app/src/Shared/Infrastructure/Framework/Middleware/RateLimitMiddleware.php:35:    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
app/src/Modules/Auth/Application/Command/Auth/SendLoginCode/SendLoginCodeHandler.php:29:    public function handle(SendLoginCodeCommand $command): void
app/src/Modules/Auth/Application/Command/Auth/CompleteRegistration/CompleteRegistrationHandler.php:41:    public function handle(CompleteRegistrationCommand $command): IssuedTokenPair
app/src/Modules/Auth/Application/Contract/AuthTokenStorageContract.php:18:    public function issuePair(UserId $userId): IssuedTokenPair;
app/src/Modules/Auth/Application/Contract/AuthTokenStorageContract.php:20:    public function rotate(string $refreshRaw): IssuedTokenPair;
app/src/Modules/Auth/Application/Contract/AuthTokenStorageContract.php:22:    public function revokeSession(SessionId $sessionId): void;
app/src/Modules/Auth/Application/Command/Auth/Logout/LogoutHandler.php:25:    public function handle(LogoutCommand $command): void
app/src/Modules/User/Application/Command/User/CreateUser/CreateUserHandler.php:31:    public function handle(CreateUserCommand $command): CreateUserResult
app/src/Modules/Auth/Application/Command/Auth/ResolveLoginCode/ResolveLoginCodeHandler.php:45:    public function handle(ResolveLoginCodeCommand $command): LoginCodeResolution
app/src/Modules/Auth/Presentation/Http/Middleware/RequireAuthenticatedMiddleware.php:27:    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
app/src/Modules/Auth/Presentation/Http/Middleware/AuthContextAttributeMiddleware.php:29:    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
app/src/Modules/Auth/Application/Command/Auth/RefreshTokens/RefreshTokensHandler.php:26:    public function handle(RefreshTokensCommand $command): IssuedTokenPair
app/src/Modules/Auth/Application/Command/Auth/VerifyLoginCode/VerifyLoginCodeHandler.php:27:    public function handle(VerifyLoginCodeCommand $command): VerifyLoginCodeResult
app/src/Modules/Auth/Infrastructure/Auth/CycleTokenStorage.php:65:    public function create(array $payload, \DateTimeInterface|null $expiresAt = null): TokenInterface
app/src/Modules/Auth/Infrastructure/Auth/CycleTokenStorage.php:115:    public function issuePair(UserId $userId): IssuedTokenPair
app/src/Modules/Auth/Infrastructure/Auth/CycleTokenStorage.php:145:    public function rotate(string $refreshRaw): IssuedTokenPair
app/src/Modules/Auth/Infrastructure/Auth/CycleTokenStorage.php:161:    public function revokeSession(SessionId $sessionId): void
app/src/Shared/Infrastructure/Framework/Middleware/LocaleMiddleware.php:36:    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
app/src/Modules/Auth/Application/Command/Auth/RequestLoginCode/RequestLoginCodeHandler.php:41:    public function handle(RequestLoginCodeCommand $command): void

exec
/bin/zsh -lc "sed -n '1,240p' app/src/Modules/Auth/Application/Command/Auth/ResolveLoginCode/LoginCodeResolution.php && sed -n '1,160p' app/src/Modules/Auth/Application/Command/Auth/ResolveLoginCode/LoginCodeOutcome.php && sed -n '1,160p' app/src/Modules/Auth/Application/Command/Auth/VerifyLoginCode/VerifyLoginCodeResult.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Command\Auth\ResolveLoginCode;

use App\Modules\Auth\Application\Dto\IssuedTokenPair;

/**
 * Результат транзакционного разбора кода: исход плюс выданная пара токенов (Verified) или
 * сырой талон регистрации (NeedsProfile). Для остальных исходов оба поля пустые.
 */
final readonly class LoginCodeResolution
{
    private function __construct(
        public LoginCodeOutcome $outcome,
        public IssuedTokenPair|null $tokens,
        public string|null $registrationTicket,
    ) {}

    public static function failed(LoginCodeOutcome $outcome): self
    {
        return new self(outcome: $outcome, tokens: null, registrationTicket: null);
    }

    public static function verified(IssuedTokenPair $tokens): self
    {
        return new self(outcome: LoginCodeOutcome::Verified, tokens: $tokens, registrationTicket: null);
    }

    public static function needsProfile(string $registrationTicket): self
    {
        return new self(
            outcome: LoginCodeOutcome::NeedsProfile,
            tokens: null,
            registrationTicket: $registrationTicket,
        );
    }
}
<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Command\Auth\ResolveLoginCode;

/**
 * Исход транзакционного разбора кода. Внутренний enum между ResolveLoginCode (коммитит запись)
 * и нетранзакционным оркестратором VerifyLoginCode (по исходу строит ответ или бросает 401).
 */
enum LoginCodeOutcome
{
    case NoCode;
    case Expired;
    case Exhausted;
    case Wrong;
    case NotAllowed;
    case Verified;
    case NeedsProfile;
}
<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Command\Auth\VerifyLoginCode;

use App\Modules\Auth\Application\Dto\IssuedTokenPair;

/**
 * Результат проверки кода: существующему пользователю — пара токенов (needsProfile=false),
 * новому email — сырой талон регистрации (needsProfile=true).
 */
final readonly class VerifyLoginCodeResult
{
    public function __construct(
        public bool $needsProfile,
        public IssuedTokenPair|null $tokens,
        public string|null $registrationTicket,
    ) {}
}

exec
/bin/zsh -lc "sed -n '1,260p' app/src/Modules/Auth/Application/Command/Auth/RefreshTokens/RefreshTokensHandler.php && sed -n '1,220p' app/src/Modules/Auth/Application/Command/Auth/Logout/LogoutHandler.php && sed -n '1,160p' app/src/Modules/Auth/Application/Command/Auth/RefreshTokens/RefreshTokensCommand.php && sed -n '1,160p' app/src/Modules/Auth/Application/Command/Auth/Logout/LogoutCommand.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Command\Auth\RefreshTokens;

use App\Modules\Auth\Application\Contract\AuthTokenStorageContract;
use App\Modules\Auth\Application\Dto\IssuedTokenPair;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;
use GianTiaga\SpiralCqrs\Attribute\Transactional;
use Psr\Log\LoggerInterface;

/**
 * Обновление пары токенов: ротация refresh-токена (удаление старой пары сессии и выдача новой).
 * Невалидный/истёкший/не-refresh токен → AuthenticationException (401) из хранилища.
 */
final readonly class RefreshTokensHandler
{
    public function __construct(
        private AuthTokenStorageContract $authTokenStorage,
        private LoggerInterface $logger,
    ) {}

    #[Transactional]
    #[LogOperation]
    public function handle(RefreshTokensCommand $command): IssuedTokenPair
    {
        $tokens = $this->authTokenStorage->rotate($command->refreshToken);

        $this->logger->debug(message: 'Пара токенов ротирована.');

        return $tokens;
    }
}
<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Command\Auth\Logout;

use App\Modules\Auth\Application\Contract\AuthTokenStorageContract;
use App\Modules\Auth\Domain\ValueObject\SessionId;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;
use GianTiaga\SpiralCqrs\Attribute\Transactional;
use Psr\Log\LoggerInterface;

/**
 * Выход: отзыв всех токенов текущей сессии (access + refresh) по sessionId из payload токена.
 */
final readonly class LogoutHandler
{
    public function __construct(
        private AuthTokenStorageContract $authTokenStorage,
        private LoggerInterface $logger,
    ) {}

    #[Transactional]
    #[LogOperation]
    public function handle(LogoutCommand $command): void
    {
        $this->authTokenStorage->revokeSession(SessionId::fromString($command->authSessionId));

        $this->logger->debug(message: 'Сессия отозвана.', context: [
            'sessionId' => $command->authSessionId,
        ]);
    }
}
<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Command\Auth\RefreshTokens;

final readonly class RefreshTokensCommand
{
    public function __construct(
        public string $refreshToken,
    ) {}
}
<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Command\Auth\Logout;

final readonly class LogoutCommand
{
    public function __construct(
        public string $authSessionId,
    ) {}
}

exec
/bin/zsh -lc "sed -n '1,260p' app/database/migrations/20260615.141700_0_create_auth_domain_tables.php && sed -n '1,220p' app/src/Modules/Auth/Domain/ValueObject/CodeAttempts.php && sed -n '1,220p' app/src/Modules/Auth/Domain/ValueObject/Expiration.php && sed -n '1,220p' app/src/Modules/Auth/Domain/ValueObject/Consumption.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace Migration;

use Cycle\Migrations\Migration;

class CreateAuthDomainTables extends Migration
{
    protected const DATABASE = null;

    public function up(): void
    {
        $this->table('auth_login_codes')
            ->addColumn('id', 'uuid', ['nullable' => false])
            ->addColumn('email', 'string', ['length' => 254, 'nullable' => false])
            ->addColumn('code_hash', 'text', ['nullable' => false])
            ->addColumn('expires_at', 'datetime', ['nullable' => false])
            ->addColumn('attempts', 'integer', ['nullable' => false, 'default' => 0])
            ->addColumn('consumed_at', 'datetime', ['nullable' => true])
            ->addColumn('created_at', 'datetime', ['nullable' => false])
            ->addColumn('updated_at', 'datetime', ['nullable' => false])
            ->setPrimaryKeys(['id'])
            ->addIndex(['email'])
            ->create();

        $this->table('auth_registration_tickets')
            ->addColumn('id', 'uuid', ['nullable' => false])
            ->addColumn('email', 'string', ['length' => 254, 'nullable' => false])
            ->addColumn('ticket_hash', 'text', ['nullable' => false])
            ->addColumn('expires_at', 'datetime', ['nullable' => false])
            ->addColumn('consumed_at', 'datetime', ['nullable' => true])
            ->addColumn('created_at', 'datetime', ['nullable' => false])
            ->addColumn('updated_at', 'datetime', ['nullable' => false])
            ->setPrimaryKeys(['id'])
            ->addIndex(['email'])
            ->create();

        $this->table('auth_tokens')
            ->addColumn('id', 'uuid', ['nullable' => false])
            ->addColumn('user_id', 'uuid', ['nullable' => false])
            ->addColumn('session_id', 'uuid', ['nullable' => false])
            ->addColumn('type', 'string', ['length' => 16, 'nullable' => false])
            ->addColumn('token_hash', 'text', ['nullable' => false])
            ->addColumn('expires_at', 'datetime', ['nullable' => false])
            ->addColumn('created_at', 'datetime', ['nullable' => false])
            ->addColumn('updated_at', 'datetime', ['nullable' => false])
            ->setPrimaryKeys(['id'])
            ->addIndex(['token_hash'], ['unique' => true])
            ->addIndex(['user_id'])
            ->addIndex(['session_id'])
            ->create();
    }

    public function down(): void
    {
        $this->table('auth_tokens')->drop();
        $this->table('auth_registration_tickets')->drop();
        $this->table('auth_login_codes')->drop();
    }
}
<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidDomainValueException;

/**
 * Счётчик неверных вводов кода. Код сгорает после LIMIT неудачных попыток.
 * fromInt обязателен для восстановления через конвенцию ValueObjectCast.
 */
final readonly class CodeAttempts implements \Stringable, \JsonSerializable
{
    private const int LIMIT = 5;

    private function __construct(
        private int $value,
    ) {}

    public static function initial(): self
    {
        return new self(value: 0);
    }

    public static function fromInt(int $value): self
    {
        if ($value < 0) {
            throw new InvalidDomainValueException('Число попыток не может быть отрицательным.');
        }

        return new self(value: $value);
    }

    public function increment(): self
    {
        return new self(value: $this->value + 1);
    }

    public function isExhausted(): bool
    {
        return $this->value >= self::LIMIT;
    }

    public function value(): int
    {
        return $this->value;
    }

    public function equals(self $attempts): bool
    {
        return $this->value === $attempts->value;
    }

    #[\Override]
    public function __toString(): string
    {
        return (string) $this->value;
    }

    #[\Override]
    public function jsonSerialize(): int
    {
        return $this->value;
    }
}
<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\ValueObject;

use App\Shared\Domain\Trait\ComparesDateTimeToMicroseconds;

/**
 * Момент истечения срока (кода, талона, токена). Единый VO для всех TTL: отличается только
 * длительностью при создании. after() считает now + seconds, fromDateTime восстанавливает
 * сохранённый момент (граница БД через ExpirationTypecast).
 */
final readonly class Expiration implements \Stringable, \JsonSerializable
{
    use ComparesDateTimeToMicroseconds;

    private function __construct(
        private \DateTimeImmutable $value,
    ) {}

    public static function after(\DateTimeImmutable $now, int $seconds): self
    {
        return new self(value: $now->add(new \DateInterval(\sprintf('PT%dS', $seconds))));
    }

    public static function fromDateTime(\DateTimeImmutable $value): self
    {
        return new self(value: $value);
    }

    public function value(): \DateTimeImmutable
    {
        return $this->value;
    }

    public function isExpired(\DateTimeImmutable $now): bool
    {
        return $now >= $this->value;
    }

    public function equals(self $other): bool
    {
        return self::dateTimeEqualsToMicroseconds(value: $this->value, other: $other->value);
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->value->format(\DateTimeInterface::ATOM);
    }

    #[\Override]
    public function jsonSerialize(): string
    {
        return $this->value->format(\DateTimeInterface::ATOM);
    }
}
<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\ValueObject;

use App\Shared\Domain\Trait\ComparesDateTimeToMicroseconds;

/**
 * Одноразовость записи (кода/талона): null-object для nullable-колонки consumed_at. notConsumed()
 * — запись ещё не использована (в БД NULL), at() — момент гашения. Избегает голого ?DateTime
 * в домене (rules.md «Явные типы вместо null»).
 */
final readonly class Consumption implements \Stringable, \JsonSerializable
{
    use ComparesDateTimeToMicroseconds;

    private function __construct(
        private \DateTimeImmutable|null $consumedAt,
    ) {}

    public static function notConsumed(): self
    {
        return new self(consumedAt: null);
    }

    public static function at(\DateTimeImmutable $consumedAt): self
    {
        return new self(consumedAt: $consumedAt);
    }

    public function isConsumed(): bool
    {
        return $this->consumedAt !== null;
    }

    public function value(): \DateTimeImmutable|null
    {
        return $this->consumedAt;
    }

    public function equals(self $other): bool
    {
        if ($this->consumedAt === null || $other->consumedAt === null) {
            return $this->consumedAt === $other->consumedAt;
        }

        return self::dateTimeEqualsToMicroseconds(value: $this->consumedAt, other: $other->consumedAt);
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->consumedAt?->format(\DateTimeInterface::ATOM) ?? '';
    }

    #[\Override]
    public function jsonSerialize(): string|null
    {
        return $this->consumedAt?->format(\DateTimeInterface::ATOM);
    }
}

exec
/bin/zsh -lc "sed -n '1,220p' app/src/Modules/Auth/Presentation/Http/Filter/LogoutFilter.php && sed -n '1,220p' app/src/Modules/Auth/Presentation/Http/Filter/RefreshFilter.php && sed -n '1,220p' app/src/Modules/Auth/Presentation/Http/Filter/RequestCodeFilter.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Auth\Presentation\Http\Filter;

use Spiral\Filters\Attribute\Input\Attribute;
use Spiral\Validation\Symfony\AttributesFilter;

/**
 * Читает sessionId из request-атрибута, выставленного AuthContextAttributeMiddleware
 * (наличие гарантирует RequireAuthenticatedMiddleware), без обращения к ServerRequestInterface.
 */
final class LogoutFilter extends AttributesFilter
{
    #[Attribute(key: 'authSessionId')]
    public string $authSessionId;
}
<?php

declare(strict_types=1);

namespace App\Modules\Auth\Presentation\Http\Filter;

use Spiral\Filters\Attribute\Input\Post;
use Spiral\Validation\Symfony\AttributesFilter;
use Symfony\Component\Validator\Constraints as Assert;

final class RefreshFilter extends AttributesFilter
{
    #[Post]
    #[Assert\NotBlank]
    public string $refreshToken;
}
<?php

declare(strict_types=1);

namespace App\Modules\Auth\Presentation\Http\Filter;

use Spiral\Filters\Attribute\Input\Post;
use Spiral\Validation\Symfony\AttributesFilter;
use Symfony\Component\Validator\Constraints as Assert;

final class RequestCodeFilter extends AttributesFilter
{
    #[Post]
    #[Assert\NotBlank]
    public string $email;
}

 succeeded in 539ms:
     184 app/src/Modules/Auth/Infrastructure/Auth/CycleTokenStorage.php
     187 app/src/Modules/Auth/Presentation/Http/Controller/AuthController.php

exec
/bin/zsh -lc "rg --pcre2 -n \"try\\s*\\{|catch\\s*\\(|RuntimeException|UnexpectedValueException|assert\\(|\\bmixed\\b|==(?"'!=)|!=(?!=)|<>|switch'"\\s*\\(\" app/src/Modules/Auth app/src/Modules/User/Application app/src/Shared/Infrastructure/Framework/Middleware tests/Feature/Modules/Auth tests/Unit/Modules/Auth packages/spiral-api-errors/src/Filter/ApiValidationErrorsRenderer.php packages/spiral-api-errors/tests/Filter/ApiValidationErrorsRendererTest.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
packages/spiral-api-errors/src/Filter/ApiValidationErrorsRenderer.php:21:    public function render(array $errors, mixed $context = null): ResponseInterface
app/src/Modules/Auth/Presentation/Job/SendLoginCodeJob.php:39:        try {
app/src/Modules/Auth/Presentation/Job/SendLoginCodeJob.php:48:        } catch (\Throwable $exception) {
tests/Feature/Modules/Auth/Presentation/SendLoginCodeJobTest.php:49:        $failingMailer->method('send')->willThrowException(new \RuntimeException('smtp недоступен'));
app/src/Modules/Auth/Presentation/Http/Resource/VerifyResultResource.php:22:            tokens: $result->tokens !== null ? TokenPairResource::fromPair($result->tokens) : null,
app/src/Shared/Infrastructure/Framework/Middleware/LocaleMiddleware.php:46:            'usedFallback' => $matchedLocale === null,
app/src/Shared/Infrastructure/Framework/Middleware/LocaleMiddleware.php:70:        return $separatorPosition === false ? $lower : \substr(string: $lower, offset: 0, length: $separatorPosition);
tests/Unit/Modules/Auth/Presentation/Http/Middleware/AuthContextAttributeMiddlewareTest.php:104:        return $captureHandler->request ?? throw new \RuntimeException('Обработчик не был вызван.');
app/src/Modules/Auth/Presentation/Http/Middleware/RequireAuthenticatedMiddleware.php:29:        if ($request->getAttribute(AuthContextAttributeMiddleware::ATTRIBUTE_USER_ID) === null) {
app/src/Modules/User/Application/Query/User/FindUserForAuth/FindUserForAuthHandler.php:21:        if ($user === null) {
app/src/Modules/Auth/Presentation/Http/Middleware/AuthContextAttributeMiddleware.php:33:        if ($token === null) {
app/src/Modules/Auth/Presentation/Http/Middleware/AuthContextAttributeMiddleware.php:39:        if (($payload['type'] ?? null) !== AuthTokenType::Access->value) {
tests/Feature/Modules/Auth/Application/CompleteRegistrationHandlerTest.php:80:        try {
tests/Feature/Modules/Auth/Application/CompleteRegistrationHandlerTest.php:88:        } catch (ValidationException $validationException) {
app/src/Modules/Auth/Infrastructure/Cycle/ConsumptionTypecast.php:19:        if ($value === null) {
app/src/Modules/Auth/Domain/Entity/AuthToken.php:77:        return $this->type === AuthTokenType::Access;
app/src/Modules/Auth/Domain/Entity/AuthToken.php:82:        return $this->type === AuthTokenType::Refresh;
app/src/Modules/Auth/Application/Command/Auth/RequestLoginCode/RequestLoginCodeHandler.php:47:        if ($activeCode !== null && $this->isWithinResendWindow(activeCode: $activeCode, now: $now)) {
app/src/Modules/Auth/Application/Command/Auth/RequestLoginCode/RequestLoginCodeHandler.php:55:        if ($activeCode !== null) {
app/src/Modules/Auth/Infrastructure/Auth/UserActorProvider.php:24:        if (($payload['type'] ?? null) !== AuthTokenType::Access->value) {
app/src/Modules/Auth/Domain/ValueObject/EmailAddress.php:29:            $email === ''
app/src/Modules/Auth/Domain/ValueObject/EmailAddress.php:46:        return $this->value === $email->value;
app/src/Modules/Auth/Application/Command/Auth/ResolveLoginCode/ResolveLoginCodeHandler.php:51:        if ($loginCode === null) {
app/src/Modules/Auth/Application/Command/Auth/ResolveLoginCode/ResolveLoginCodeHandler.php:84:        if ($userAuthView === null) {
app/src/Modules/Auth/Infrastructure/Auth/CycleTokenStorage.php:48:        if ($id === '') {
app/src/Modules/Auth/Infrastructure/Auth/CycleTokenStorage.php:54:        if ($authToken === null || $authToken->isExpired(new \DateTimeImmutable())) {
app/src/Modules/Auth/Infrastructure/Auth/CycleTokenStorage.php:67:        if ($expiresAt === null) {
app/src/Modules/Auth/Infrastructure/Auth/CycleTokenStorage.php:75:        if ($userId === null || $type === null || $sessionId === null) {
app/src/Modules/Auth/Infrastructure/Auth/CycleTokenStorage.php:106:        if ($authToken === null) {
app/src/Modules/Auth/Domain/ValueObject/LoginCodeValue.php:21:        if (\preg_match(pattern: '/^\d{6}$/', subject: $value) !== 1) {
app/src/Modules/Auth/Domain/ValueObject/SecretHash.php:22:        if ($value === '') {
app/src/Modules/Auth/Domain/ValueObject/Consumption.php:34:        return $this->consumedAt !== null;
app/src/Modules/Auth/Domain/ValueObject/Consumption.php:44:        if ($this->consumedAt === null || $other->consumedAt === null) {
app/src/Modules/Auth/Domain/ValueObject/Consumption.php:45:            return $this->consumedAt === $other->consumedAt;
app/src/Modules/Auth/Domain/ValueObject/TokenHash.php:22:        if ($rawToken === '') {
app/src/Modules/Auth/Domain/ValueObject/TokenHash.php:31:        if ($value === '') {
app/src/Modules/Auth/Domain/ValueObject/CodeAttempts.php:52:        return $this->value === $attempts->value;
tests/Feature/Modules/Auth/Application/LoginCodeVerificationTest.php:55:        try {
tests/Feature/Modules/Auth/Application/LoginCodeVerificationTest.php:58:        } catch (AuthenticationException $authenticationException) {
tests/Feature/Modules/Auth/Application/LoginCodeVerificationTest.php:103:        try {
tests/Feature/Modules/Auth/Application/LoginCodeVerificationTest.php:106:        } catch (AuthenticationException $authenticationException) {

codex
Нашёл ещё один кандидат на обязательное замечание: фильтры проверяют email только на пустоту. Сейчас невалидный email может пройти до доменного объекта и превратиться во внутреннюю ошибку вместо понятной ошибки запроса. Проверяю это по фильтрам, VO и HTTP-тестам.
exec
/bin/zsh -lc "sed -n '1,260p' app/src/Shared/Domain/Exception/InvalidDomainValueException.php && sed -n '1,260p' app/src/Shared/Domain/Exception/ValidationException.php && sed -n '1,260p' app/src/Shared/Domain/Exception/DomainTranslatableException.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

final class InvalidDomainValueException extends \DomainException
{
    private const int STATUS_CODE = 500;

    public function __construct(string $message)
    {
        parent::__construct(message: $message, code: self::STATUS_CODE);
    }
}
<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

final class ValidationException extends DomainTranslatableException
{
    #[\Override]
    protected function statusCode(): int
    {
        return 422;
    }
}
<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

use GianTiaga\SpiralApiErrors\Exception\TranslatableException;

abstract class DomainTranslatableException extends \DomainException implements TranslatableException
{
    /**
     * @param array<string, string> $translationParameters
     */
    public function __construct(private readonly string $translationKey, private readonly array $translationParameters = [])
    {
        parent::__construct(message: $translationKey, code: $this->statusCode());
    }

    abstract protected function statusCode(): int;

    #[\Override]
    public function translationKey(): string
    {
        return $this->translationKey;
    }

    /**
     * Домен перевода (= файл каталога) выводится из второго сегмента ключа:
     * `app.media.not_found` -> `media`, `app.system.swagger_ui_disabled` -> `system`.
     * Ключи без модульного сегмента переводятся в домене по умолчанию `messages`.
     */
    #[\Override]
    public function translationDomain(): string
    {
        return \explode(separator: '.', string: $this->translationKey)[1] ?? 'messages';
    }

    /**
     * @return array<string, string>
     */
    #[\Override]
    public function translationParameters(): array
    {
        return $this->translationParameters;
    }
}

exec
/bin/zsh -lc "sed -n '1,180p' app/src/Modules/Auth/Domain/ValueObject/EmailAddress.php && sed -n '1,180p' app/src/Modules/User/Domain/ValueObject/Email.php && rg -n \"invalid email|bad email|assert(Unprocessable|Status\\(422\\)|422)|RequestCodeFilter|VerifyCodeFilter\" tests/Feature/Modules/Auth tests/Feature/Modules/User tests/Unit/Modules/Auth" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Auth\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidDomainValueException;

/**
 * Email модуля Auth. Нормализация ОБЯЗАНА быть побайтово идентична
 * App\Modules\User\Domain\ValueObject\Email (mb_strtolower + trim, FILTER_VALIDATE_EMAIL,
 * длина ≤ 254), чтобы ключи по email в auth_* и users совпадали. Эквивалентность
 * нормализации покрыта тестом-инвариантом. Собственный VO нужен, потому что arch.md
 * запрещает зависимость Domain одного модуля от Domain другого.
 */
final readonly class EmailAddress implements \Stringable, \JsonSerializable
{
    private const int MAX_LENGTH = 254;

    private function __construct(
        private string $value,
    ) {}

    public static function fromString(string $value): self
    {
        $email = \mb_strtolower(\trim($value));

        if (
            $email === ''
            || \mb_strlen($email) > self::MAX_LENGTH
            || !\filter_var(value: $email, filter: FILTER_VALIDATE_EMAIL)
        ) {
            throw new InvalidDomainValueException('Email имеет неверный формат.');
        }

        return new self(value: $email);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $email): bool
    {
        return $this->value === $email->value;
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
<?php

declare(strict_types=1);

namespace App\Modules\User\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidDomainValueException;

final readonly class Email implements \Stringable, \JsonSerializable
{
    private const int MAX_LENGTH = 254;

    private function __construct(
        private string $value,
    ) {}

    public static function fromString(string $value): self
    {
        $email = \mb_strtolower(\trim($value));

        if (
            $email === ''
            || \mb_strlen($email) > self::MAX_LENGTH
            || !\filter_var(value: $email, filter: FILTER_VALIDATE_EMAIL)
        ) {
            throw new InvalidDomainValueException('Email имеет неверный формат.');
        }

        return new self(value: $email);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $email): bool
    {
        return $this->value === $email->value;
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
tests/Feature/Modules/Auth/Http/AuthHttpTest.php:65:        $this->http()->postJson('/api/v1/auth/code/request', ['email' => ''])->assertUnprocessable();
tests/Feature/Modules/Auth/Http/AuthHttpTest.php:150:        ])->assertUnprocessable();

exec
/bin/zsh -lc "rg -n \"RequestCodeFilter|VerifyCodeFilter|RegisterFilter|RefreshFilter|Assert\\\\Email|email\" public/openapi/openapi.yml tests/Feature/Modules/Auth/Console/AuthOpenApiGenerationTest.php tests/Feature/Modules/Auth/Http/AuthHttpTest.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 exited 2 in 0ms:
rg: regex parse error:
    (?:RequestCodeFilter|VerifyCodeFilter|RegisterFilter|RefreshFilter|Assert\Email|email)
                                                                             ^^
error: unrecognized escape sequence

exec
/bin/zsh -lc "sed -n '1,260p' tests/Kernel/Modules/Auth/AuthBootloaderTest.php && sed -n '1,220p' tests/Feature/Modules/Auth/Console/AuthOpenApiGenerationTest.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace Tests\Kernel\Modules\Auth;

use App\Modules\Auth\Application\Contract\AuthTokenStorageContract;
use App\Modules\Auth\Application\Contract\SecretHasherContract;
use App\Modules\Auth\Application\Contract\TokenGeneratorContract;
use App\Modules\Auth\Infrastructure\Auth\CycleTokenStorage;
use App\Modules\Auth\Infrastructure\Auth\RandomTokenGenerator;
use App\Modules\Auth\Infrastructure\Hash\HmacSecretHasher;
use App\Modules\Auth\Presentation\Job\SendLoginCodeJob;
use App\Modules\Outbox\Infrastructure\Queue\OutboxQueueSerializer;
use Spiral\Auth\TokenStorageProviderInterface;
use Spiral\Config\ConfiguratorInterface;
use Tests\TestCase;

final class AuthBootloaderTest extends TestCase
{
    public function testContractBindingsResolveToImplementations(): void
    {
        self::assertInstanceOf(HmacSecretHasher::class, $this->getContainer()->get(SecretHasherContract::class));
        self::assertInstanceOf(RandomTokenGenerator::class, $this->getContainer()->get(TokenGeneratorContract::class));
        self::assertInstanceOf(CycleTokenStorage::class, $this->getContainer()->get(AuthTokenStorageContract::class));
    }

    public function testCycleTokenStorageRegisteredUnderCycleName(): void
    {
        $storage = $this->getContainer()->get(TokenStorageProviderInterface::class)->getStorage('cycle');

        self::assertInstanceOf(CycleTokenStorage::class, $storage);
    }

    public function testQueueRegistersSendLoginCodeJobWithOutboxSerializer(): void
    {
        $queueConfig = $this->getContainer()->get(ConfiguratorInterface::class)->getConfig('queue');

        self::assertArrayHasKey(SendLoginCodeJob::class, $queueConfig['registry']['handlers']);
        self::assertSame(
            OutboxQueueSerializer::class,
            $queueConfig['registry']['serializers'][SendLoginCodeJob::class],
        );
    }
}
<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Auth\Console;

use Spiral\Testing\Attribute\Config;
use Tests\TestCase;

final class AuthOpenApiGenerationTest extends TestCase
{
    #[Config('openapi.outputFile', 'runtime/openapi-auth-test.yml')]
    public function testGeneratesAllAuthRoutes(): void
    {
        $this->runCommand(command: 'openapi:generate');

        $contents = \file_get_contents($this->rootDirectory() . '/runtime/openapi-auth-test.yml');

        self::assertIsString($contents);
        self::assertStringContainsString('/auth/code/request:', $contents);
        self::assertStringContainsString('/auth/code/verify:', $contents);
        self::assertStringContainsString('/auth/register:', $contents);
        self::assertStringContainsString('/auth/refresh:', $contents);
        self::assertStringContainsString('/auth/logout:', $contents);
    }
}

exec
/bin/zsh -lc "sed -n '1,260p' app/src/Modules/Auth/Presentation/Http/Resource/VerifyResultResource.php && sed -n '1,220p' app/src/Modules/Auth/Presentation/Http/Resource/TokenPairResource.php && sed -n '1,180p' app/src/Modules/Auth/Presentation/Http/Resource/RequestCodeResultResource.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Auth\Presentation\Http\Resource;

use App\Modules\Auth\Application\Command\Auth\VerifyLoginCode\VerifyLoginCodeResult;
use App\Shared\Presentation\Http\Resource\AbstractResource;

final readonly class VerifyResultResource extends AbstractResource
{
    public function __construct(
        public bool $needsProfile,
        public TokenPairResource|null $tokens,
        public string|null $registrationTicket,
    ) {}

    public static function fromResult(VerifyLoginCodeResult $result): self
    {
        return new self(
            needsProfile: $result->needsProfile,
            tokens: $result->tokens !== null ? TokenPairResource::fromPair($result->tokens) : null,
            registrationTicket: $result->registrationTicket,
        );
    }
}
<?php

declare(strict_types=1);

namespace App\Modules\Auth\Presentation\Http\Resource;

use App\Modules\Auth\Application\Dto\IssuedTokenPair;
use App\Shared\Presentation\Http\Resource\AbstractResource;

final readonly class TokenPairResource extends AbstractResource
{
    public function __construct(
        public string $accessToken,
        public string $refreshToken,
        public string $tokenType,
        public int $expiresIn,
    ) {}

    public static function fromPair(IssuedTokenPair $pair): self
    {
        return new self(
            accessToken: $pair->accessToken,
            refreshToken: $pair->refreshToken,
            tokenType: 'Bearer',
            expiresIn: $pair->expiresIn,
        );
    }
}
<?php

declare(strict_types=1);

namespace App\Modules\Auth\Presentation\Http\Resource;

use App\Shared\Presentation\Http\Resource\AbstractResource;

final readonly class RequestCodeResultResource extends AbstractResource
{
    public function __construct(
        public string $status,
    ) {}

    public static function sent(): self
    {
        return new self(status: 'sent');
    }
}

exec
/bin/zsh -lc "sed -n '1,260p' tests/Feature/Modules/Auth/Infrastructure/CycleTokenStorageTest.php && sed -n '1,220p' tests/Unit/Modules/Auth/Infrastructure/Auth/UserActorProviderTest.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Auth\Infrastructure;

use App\Modules\Auth\Domain\Enum\AuthTokenType;
use App\Modules\Auth\Domain\ValueObject\SessionId;
use App\Modules\Auth\Infrastructure\Auth\AuthTokenView;
use App\Modules\Auth\Infrastructure\Auth\CycleTokenStorage;
use App\Modules\Auth\Infrastructure\Auth\RandomTokenGenerator;
use App\Modules\Auth\Repository\AuthTokenRepository;
use App\Shared\Domain\Exception\AuthenticationException;
use App\Shared\Domain\Exception\InvalidDomainValueException;
use App\Shared\Domain\ValueObject\UserId;
use Cycle\ORM\EntityManagerInterface;
use Spiral\Auth\TokenInterface;
use Tests\DatabaseTestCase;

final class CycleTokenStorageTest extends DatabaseTestCase
{
    public function testIssuePairCreatesLoadableAccessAndRefresh(): void
    {
        $userId = UserId::generate();
        $pair = $this->storage()->issuePair($userId);

        self::assertSame(3600, $pair->expiresIn);
        self::assertNotSame($pair->accessToken, $pair->refreshToken);

        $accessView = $this->storage()->load($pair->accessToken);

        self::assertInstanceOf(TokenInterface::class, $accessView);
        self::assertSame($pair->accessToken, $accessView->getID());
        self::assertSame(AuthTokenType::Access->value, $accessView->getPayload()['type']);
        self::assertSame($userId->value(), $accessView->getPayload()['userID']);
        self::assertNotNull($accessView->getExpiresAt());

        $sessionId = SessionId::fromString($accessView->getPayload()['sessionID']);
        self::assertCount(2, $this->authTokenRepository()->findBySessionIdForUpdate($sessionId));
    }

    public function testLoadReturnsNullForEmptyUnknownAndExpiredTokens(): void
    {
        self::assertNull($this->storage()->load(''));
        self::assertNull($this->storage()->load('unknown-token'));

        $expiredView = $this->storage()->create(
            payload: $this->payload(AuthTokenType::Access),
            expiresAt: new \DateTimeImmutable('-1 hour'),
        );

        self::assertNull($this->storage()->load($expiredView->getID()));
    }

    public function testCreateRejectsNullExpiry(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        $this->storage()->create(payload: $this->payload(AuthTokenType::Access), expiresAt: null);
    }

    public function testCreateRejectsIncompletePayload(): void
    {
        $this->expectException(InvalidDomainValueException::class);

        $this->storage()->create(
            payload: ['type' => AuthTokenType::Access->value, 'sessionID' => SessionId::generate()->value()],
            expiresAt: new \DateTimeImmutable('+1 hour'),
        );
    }

    public function testDeleteRemovesToken(): void
    {
        $pair = $this->storage()->issuePair(UserId::generate());
        $accessView = $this->storage()->load($pair->accessToken);

        self::assertInstanceOf(TokenInterface::class, $accessView);

        $this->storage()->delete($accessView);

        self::assertNull($this->storage()->load($pair->accessToken));
    }

    public function testDeleteIgnoresUnknownToken(): void
    {
        $this->storage()->delete(new AuthTokenView(id: 'unknown-token', payload: [], expiresAt: null));

        self::assertNull($this->storage()->load('unknown-token'));
    }

    public function testRotateIssuesNewPairAndRevokesOld(): void
    {
        $pair = $this->storage()->issuePair(UserId::generate());

        $rotatedPair = $this->storage()->rotate($pair->refreshToken);

        self::assertNotSame($pair->accessToken, $rotatedPair->accessToken);
        self::assertNotSame($pair->refreshToken, $rotatedPair->refreshToken);
        self::assertNull($this->storage()->load($pair->refreshToken));
        self::assertNull($this->storage()->load($pair->accessToken));
        self::assertInstanceOf(TokenInterface::class, $this->storage()->load($rotatedPair->accessToken));
    }

    public function testRotateRejectsUnknownRefresh(): void
    {
        $this->expectException(AuthenticationException::class);

        $this->storage()->rotate('unknown-token');
    }

    public function testRotateRejectsAccessTokenAsRefresh(): void
    {
        $pair = $this->storage()->issuePair(UserId::generate());

        $this->expectException(AuthenticationException::class);

        $this->storage()->rotate($pair->accessToken);
    }

    public function testRotateRejectsExpiredRefresh(): void
    {
        $expiredRefresh = $this->storage()->create(
            payload: $this->payload(AuthTokenType::Refresh),
            expiresAt: new \DateTimeImmutable('-1 hour'),
        );

        $this->expectException(AuthenticationException::class);

        $this->storage()->rotate($expiredRefresh->getID());
    }

    public function testRevokeSessionDeletesAllSessionTokens(): void
    {
        $pair = $this->storage()->issuePair(UserId::generate());
        $accessView = $this->storage()->load($pair->accessToken);

        self::assertInstanceOf(TokenInterface::class, $accessView);

        $this->storage()->revokeSession(SessionId::fromString($accessView->getPayload()['sessionID']));

        self::assertNull($this->storage()->load($pair->accessToken));
        self::assertNull($this->storage()->load($pair->refreshToken));
    }

    /**
     * @return array<string, string>
     */
    private function payload(AuthTokenType $type): array
    {
        return [
            'userID' => UserId::generate()->value(),
            'type' => $type->value,
            'sessionID' => SessionId::generate()->value(),
        ];
    }

    private function storage(): CycleTokenStorage
    {
        return new CycleTokenStorage(
            authTokenRepository: $this->authTokenRepository(),
            tokenGenerator: new RandomTokenGenerator(),
            entityManager: $this->getContainer()->get(EntityManagerInterface::class),
        );
    }

    private function authTokenRepository(): AuthTokenRepository
    {
        return $this->getContainer()->get(AuthTokenRepository::class);
    }
}
<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Auth\Infrastructure\Auth;

use App\Modules\Auth\Domain\Enum\AuthTokenType;
use App\Modules\Auth\Infrastructure\Auth\AuthenticatedUser;
use App\Modules\Auth\Infrastructure\Auth\AuthTokenView;
use App\Modules\Auth\Infrastructure\Auth\UserActorProvider;
use App\Shared\Domain\ValueObject\UserId;
use PHPUnit\Framework\TestCase;

final class UserActorProviderTest extends TestCase
{
    public function testReturnsActorForAccessToken(): void
    {
        $userId = UserId::generate();
        $token = new AuthTokenView(
            id: 'raw',
            payload: ['userID' => $userId->value(), 'type' => AuthTokenType::Access->value, 'sessionID' => 'session-1'],
            expiresAt: null,
        );

        $actor = (new UserActorProvider())->getActor($token);

        self::assertInstanceOf(AuthenticatedUser::class, $actor);
        self::assertTrue($actor->userId->equals($userId));
    }

    public function testRejectsRefreshToken(): void
    {
        $token = new AuthTokenView(
            id: 'raw',
            payload: ['userID' => UserId::generate()->value(), 'type' => AuthTokenType::Refresh->value, 'sessionID' => 's'],
            expiresAt: null,
        );

        self::assertNull((new UserActorProvider())->getActor($token));
    }

    public function testRejectsAccessTokenWithoutUserId(): void
    {
        $token = new AuthTokenView(
            id: 'raw',
            payload: ['type' => AuthTokenType::Access->value],
            expiresAt: null,
        );

        self::assertNull((new UserActorProvider())->getActor($token));
    }
}

exec
/bin/zsh -lc "sed -n '1,240p' app/src/Shared/Infrastructure/Framework/Bootloader/RoutesBootloader.php && sed -n '1,160p' app/src/Modules/System/Presentation/Http/Controller/HealthController.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Framework\Bootloader;

use App\Shared\Infrastructure\Framework\Middleware\LocaleMiddleware;
use Spiral\Bootloader\Http\RoutesBootloader as BaseRoutesBootloader;
use Spiral\Cookies\Middleware\CookiesMiddleware;
use Spiral\Csrf\Middleware\CsrfMiddleware;
use Spiral\Debug\Middleware\DumperMiddleware;
use Spiral\Debug\StateCollector\HttpCollector;
use Spiral\Filter\ValidationHandlerMiddleware;
use Spiral\Http\Middleware\ErrorHandlerMiddleware;
use Spiral\Http\Middleware\JsonPayloadMiddleware;
use Spiral\Router\Bootloader\AnnotatedRoutesBootloader;
use Spiral\Session\Middleware\SessionMiddleware;
use GianTiaga\SpiralApiErrors\Middleware\RouteNotFoundMiddleware;

/**
 * Настраивает маршруты и middleware приложения.
 *
 * @link https://spiral.dev/docs/http-routing
 */
final class RoutesBootloader extends BaseRoutesBootloader
{
    public const string GROUP_API = 'api';
    public const string GROUP_WEB = 'web';
    protected const array DEPENDENCIES = [AnnotatedRoutesBootloader::class];

    #[\Override]
    protected function globalMiddleware(): array
    {
        return [
            ErrorHandlerMiddleware::class,
            LocaleMiddleware::class,
            RouteNotFoundMiddleware::class,
            DumperMiddleware::class,
            JsonPayloadMiddleware::class,
            HttpCollector::class,
        ];
    }

    #[\Override]
    protected function middlewareGroups(): array
    {
        return [
            self::GROUP_API => [
                ValidationHandlerMiddleware::class,
            ],
            self::GROUP_WEB => [
                CookiesMiddleware::class,
                SessionMiddleware::class,
                CsrfMiddleware::class,
                ValidationHandlerMiddleware::class,
            ],
        ];
    }
}
<?php

declare(strict_types=1);

namespace App\Modules\System\Presentation\Http\Controller;

use App\Shared\Domain\Exception\NotFoundException;
use App\Modules\System\Presentation\Http\Enum\HealthStatus;
use App\Modules\System\Presentation\Http\Resource\HealthResource;
use App\Shared\Infrastructure\Configuration\Cache\CacheConfig;
use Spiral\Router\Annotation\Route;
use GianTiaga\SpiralOpenApi\Attribute\OpenApi;
use GianTiaga\SpiralOpenApi\Response\DataResponse;

final class HealthController
{
    /**
     * Проверка работоспособности API.
     *
     * @return DataResponse<HealthResource>
     */
    #[Route(route: '/api/v1/health', name: 'api.v1.health', methods: ['GET'], group: 'api')]
    #[OpenApi(id: 'health', description: 'Проверка работоспособности API')]
    public function show(): DataResponse
    {
        return new DataResponse(new HealthResource(status: HealthStatus::Ok));
    }
}

exec
/bin/zsh -lc "rg -n \"class RoutesBootloader|GROUP_API|Route\\(\" app/src/Shared app/src/Modules/System app/src/Modules/Media app/src/Modules/Auth | head -80" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
app/src/Modules/Auth/Presentation/Http/Controller/AuthController.php:43:    #[Route(
app/src/Modules/Auth/Presentation/Http/Controller/AuthController.php:74:    #[Route(
app/src/Modules/Auth/Presentation/Http/Controller/AuthController.php:101:    #[Route(
app/src/Modules/Auth/Presentation/Http/Controller/AuthController.php:134:    #[Route(
app/src/Modules/Auth/Presentation/Http/Controller/AuthController.php:161:    #[Route(
app/src/Modules/System/Presentation/Http/Controller/HealthController.php:22:    #[Route(route: '/api/v1/health', name: 'api.v1.health', methods: ['GET'], group: 'api')]
app/src/Modules/System/Presentation/Http/Controller/SwaggerController.php:26:    #[Route(route: '/api/docs', name: 'api.docs', methods: ['GET'], group: 'api')]
app/src/Modules/System/Presentation/Http/Controller/SwaggerController.php:37:    #[Route(route: '/api/docs/openapi.yml', name: 'api.docs.openapi', methods: ['GET'], group: 'api')]
app/src/Shared/Infrastructure/Framework/Bootloader/RoutesBootloader.php:25:final class RoutesBootloader extends BaseRoutesBootloader
app/src/Shared/Infrastructure/Framework/Bootloader/RoutesBootloader.php:27:    public const string GROUP_API = 'api';
app/src/Shared/Infrastructure/Framework/Bootloader/RoutesBootloader.php:48:            self::GROUP_API => [

exec
/bin/zsh -lc 'rg -n "auth/code/request|auth/code/verify|auth/register|auth/refresh|auth/logout|VerifyResult|nullable" public/openapi/openapi.yml' in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
9:  /auth/code/request:
25:  /auth/code/verify:
33:          content: { application/json: { schema: { type: object, properties: { data: { $ref: '#/components/schemas/VerifyResultResource' } }, required: [data] } } }
41:  /auth/register:
57:  /auth/refresh:
73:  /auth/logout:
106:          nullable: true
150:    VerifyResultResource:

exec
/bin/zsh -lc "sed -n '1,220p' app/src/Shared/Infrastructure/Framework/Middleware/LocaleMiddleware.php && sed -n '1,220p' app/src/Shared/Infrastructure/Configuration/Locale/LocaleConfig.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Framework\Middleware;

use App\Shared\Infrastructure\Configuration\Locale\LocaleConfig;
use Illuminate\Support\Collection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Spiral\Http\Header\AcceptHeader;
use Spiral\Http\Header\AcceptHeaderItem;
use Spiral\Translator\Translator;

/**
 * Определяет локаль запроса из заголовка Accept-Language, пересечённого с белым списком
 * поддерживаемых локалей, и выставляет её в translator на время обработки запроса.
 *
 * Зависит от конкретного Spiral\Translator\Translator (не TranslatorInterface): метод
 * setLocale() объявлен в LocaleAwareInterface и реализован конкретным Translator, а не
 * в TranslatorInterface. Translator — singleton, поэтому выставленная локаль видна и
 * интерсептору ошибок, который читает тот же инстанс через TranslatorInterface.
 */
final readonly class LocaleMiddleware implements MiddlewareInterface
{
    public function __construct(
        private Translator $translator,
        private LocaleConfig $localeConfig,
        private LoggerInterface $logger,
    ) {}

    #[\Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $acceptLanguage = $request->getHeaderLine('Accept-Language');
        $matchedLocale = $this->matchSupportedLocale($acceptLanguage);
        $resolvedLocale = $matchedLocale ?? $this->localeConfig->default;

        $this->translator->setLocale($resolvedLocale);
        $this->logger->debug(message: 'Локаль запроса определена.', context: [
            'acceptLanguage' => $acceptLanguage,
            'resolvedLocale' => $resolvedLocale,
            'usedFallback' => $matchedLocale === null,
        ]);

        return $handler->handle($request);
    }

    private function matchSupportedLocale(string $acceptLanguage): string|null
    {
        // getAll() уже отсортирован по quality по убыванию, поэтому first() даёт самый приоритетный язык.
        return Collection::make(AcceptHeader::fromString($acceptLanguage)->getAll())
            ->filter(static fn(AcceptHeaderItem $item): bool => $item->getQuality() > 0.0)
            ->map(fn(AcceptHeaderItem $item): string => $this->primarySubtag((string) $item->getValue()))
            ->first(fn(string $primarySubtag): bool => \in_array(
                needle: $primarySubtag,
                haystack: $this->localeConfig->supported,
                strict: true,
            ));
    }

    private function primarySubtag(string $value): string
    {
        $lower = \strtolower($value);
        $separatorPosition = \strpos(haystack: $lower, needle: '-');

        return $separatorPosition === false ? $lower : \substr(string: $lower, offset: 0, length: $separatorPosition);
    }
}
<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Configuration\Locale;

use App\Shared\Infrastructure\Configuration\TypedConfig;
use App\Shared\Infrastructure\Exception\InvalidConfigValueException;

final readonly class LocaleConfig implements TypedConfig
{
    public static function configName(): string
    {
        return 'locale';
    }

    /**
     * @param list<string> $supported
     */
    public function __construct(
        public array $supported,
        public string $default,
    ) {
        // Базовая локаль обязана входить в белый список: иначе резолв при отсутствии совпадений
        // выставил бы translator в неподдерживаемую локаль без каталога переводов.
        if (!\in_array(needle: $default, haystack: $supported, strict: true)) {
            throw new InvalidConfigValueException(
                path: 'locale.default',
                expected: \sprintf('одна из поддерживаемых (%s)', \implode(separator: ', ', array: $supported)),
                actual: $default,
            );
        }
    }
}

codex
Похожая проблема есть не только у email: `register` проверяет `name` и `nickname` только на пустоту, а дальше доменные объекты могут бросить внутреннюю ошибку. Это уже не косметика, а пропущенные HTTP-проверки и тесты на 422 для плохих входных данных.
exec
/bin/zsh -lc "sed -n '1,220p' app/src/Modules/User/Domain/ValueObject/UserName.php && sed -n '1,260p' app/src/Modules/User/Domain/ValueObject/UserNickname.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\User\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidDomainValueException;

final readonly class UserName implements \Stringable, \JsonSerializable
{
    private const int MAX_LENGTH = 100;

    private function __construct(
        private string $value,
    ) {}

    public static function fromString(string $value): self
    {
        $name = self::normalize($value);

        if ($name === '' || \mb_strlen($name) > self::MAX_LENGTH) {
            throw new InvalidDomainValueException('Имя имеет неверную длину.');
        }

        if (\preg_match(pattern: "/^[\\p{L}\\p{M}' -]+$/u", subject: $name) !== 1) {
            throw new InvalidDomainValueException('Имя содержит недопустимые символы.');
        }

        return new self(value: $name);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $name): bool
    {
        return $this->value === $name->value;
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

    private static function normalize(string $value): string
    {
        // Форма нормализации фиксируется явно (FORM_C). Имена параметров расходятся
        // между нативным ext-intl ($string/$form) и стабом
        // symfony/polyfill-intl-normalizer ($s/$form), поэтому аргументы позиционные,
        // а named-arguments-правило для этой строки погашено в phpstan.neon.
        $normalized = \Normalizer::normalize(\trim($value), \Normalizer::FORM_C);

        if (!\is_string($normalized)) {
            throw new InvalidDomainValueException('Имя не удалось нормализовать.');
        }

        return \preg_replace(pattern: '/\s+/u', replacement: ' ', subject: $normalized) ?? '';
    }
}
<?php

declare(strict_types=1);

namespace App\Modules\User\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidDomainValueException;

final readonly class UserNickname implements \Stringable, \JsonSerializable
{
    private function __construct(
        private string $value,
    ) {}

    public static function fromString(string $value): self
    {
        $nickname = \mb_strtolower(\trim($value));

        if (\preg_match(pattern: '/^[a-z0-9](?:[a-z0-9._-]{1,28})[a-z0-9]$/', subject: $nickname) !== 1) {
            throw new InvalidDomainValueException('Никнейм имеет неверный формат.');
        }

        if (\str_contains(haystack: $nickname, needle: '..')) {
            throw new InvalidDomainValueException('Никнейм не должен содержать две точки подряд.');
        }

        return new self(value: $nickname);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $nickname): bool
    {
        return $this->value === $nickname->value;
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
/bin/zsh -lc 'rg -n "nickname|name|invalid|empty|assertUnprocessable|ValidationException|UserName|UserNickname" tests/Feature/Modules/Auth tests/Feature/Modules/User/Application tests/Unit/Modules/User/Domain/ValueObject' in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
tests/Feature/Modules/Auth/Application/Fixture/RecordingMailer.php:5:namespace Tests\Feature\Modules\Auth\Application\Fixture;
tests/Unit/Modules/User/Domain/ValueObject/UserValueObjectTest.php:5:namespace Tests\Unit\Modules\User\Domain\ValueObject;
tests/Unit/Modules/User/Domain/ValueObject/UserValueObjectTest.php:13:use App\Modules\User\Domain\ValueObject\ReservedNicknameHolder;
tests/Unit/Modules/User/Domain/ValueObject/UserValueObjectTest.php:14:use App\Modules\User\Domain\ValueObject\ReservedNicknameId;
tests/Unit/Modules/User/Domain/ValueObject/UserValueObjectTest.php:20:use App\Modules\User\Domain\ValueObject\UserName;
tests/Unit/Modules/User/Domain/ValueObject/UserValueObjectTest.php:21:use App\Modules\User\Domain\ValueObject\UserNickname;
tests/Unit/Modules/User/Domain/ValueObject/UserValueObjectTest.php:50:    public function testNicknameNormalizesAndRejectsInvalidValue(): void
tests/Unit/Modules/User/Domain/ValueObject/UserValueObjectTest.php:52:        $nickname = UserNickname::fromString(' Yoga.User ');
tests/Unit/Modules/User/Domain/ValueObject/UserValueObjectTest.php:54:        self::assertSame('yoga.user', $nickname->value());
tests/Unit/Modules/User/Domain/ValueObject/UserValueObjectTest.php:55:        self::assertTrue($nickname->equals(UserNickname::fromString('yoga.user')));
tests/Unit/Modules/User/Domain/ValueObject/UserValueObjectTest.php:56:        self::assertSame('yoga.user', (string) $nickname);
tests/Unit/Modules/User/Domain/ValueObject/UserValueObjectTest.php:57:        self::assertSame('yoga.user', $nickname->jsonSerialize());
tests/Unit/Modules/User/Domain/ValueObject/UserValueObjectTest.php:61:        UserNickname::fromString('-bad');
tests/Unit/Modules/User/Domain/ValueObject/UserValueObjectTest.php:64:    public function testNicknameRejectsDoubleDot(): void
tests/Unit/Modules/User/Domain/ValueObject/UserValueObjectTest.php:68:        UserNickname::fromString('bad..name');
tests/Unit/Modules/User/Domain/ValueObject/UserValueObjectTest.php:73:        $name = UserName::fromString(" Йога   Тест ");
tests/Unit/Modules/User/Domain/ValueObject/UserValueObjectTest.php:75:        self::assertSame('Йога Тест', $name->value());
tests/Unit/Modules/User/Domain/ValueObject/UserValueObjectTest.php:76:        self::assertTrue($name->equals(UserName::fromString('Йога Тест')));
tests/Unit/Modules/User/Domain/ValueObject/UserValueObjectTest.php:77:        self::assertSame('Йога Тест', (string) $name);
tests/Unit/Modules/User/Domain/ValueObject/UserValueObjectTest.php:78:        self::assertSame('Йога Тест', $name->jsonSerialize());
tests/Unit/Modules/User/Domain/ValueObject/UserValueObjectTest.php:82:        UserName::fromString('Name 1');
tests/Unit/Modules/User/Domain/ValueObject/UserValueObjectTest.php:89:        UserName::fromString(\str_repeat('я', 101));
tests/Unit/Modules/User/Domain/ValueObject/UserValueObjectTest.php:96:        UserName::fromString("\xC3\x28");
tests/Unit/Modules/User/Domain/ValueObject/UserValueObjectTest.php:101:        $empty = UserSpiritualName::none();
tests/Unit/Modules/User/Domain/ValueObject/UserValueObjectTest.php:104:        self::assertNull($empty->value());
tests/Unit/Modules/User/Domain/ValueObject/UserValueObjectTest.php:105:        self::assertTrue($empty->isEmpty());
tests/Unit/Modules/User/Domain/ValueObject/UserValueObjectTest.php:106:        self::assertSame('', (string) $empty);
tests/Unit/Modules/User/Domain/ValueObject/UserValueObjectTest.php:107:        self::assertNull($empty->jsonSerialize());
tests/Unit/Modules/User/Domain/ValueObject/UserValueObjectTest.php:134:        $empty = UserBio::none();
tests/Unit/Modules/User/Domain/ValueObject/UserValueObjectTest.php:137:        self::assertNull($empty->value());
tests/Unit/Modules/User/Domain/ValueObject/UserValueObjectTest.php:138:        self::assertTrue($empty->isEmpty());
tests/Unit/Modules/User/Domain/ValueObject/UserValueObjectTest.php:139:        self::assertSame('', (string) $empty);
tests/Unit/Modules/User/Domain/ValueObject/UserValueObjectTest.php:140:        self::assertNull($empty->jsonSerialize());
tests/Unit/Modules/User/Domain/ValueObject/UserValueObjectTest.php:167:        $empty = UserLocation::none();
tests/Unit/Modules/User/Domain/ValueObject/UserValueObjectTest.php:170:        self::assertNull($empty->value());
tests/Unit/Modules/User/Domain/ValueObject/UserValueObjectTest.php:171:        self::assertTrue($empty->isEmpty());
tests/Unit/Modules/User/Domain/ValueObject/UserValueObjectTest.php:172:        self::assertSame('', (string) $empty);
tests/Unit/Modules/User/Domain/ValueObject/UserValueObjectTest.php:173:        self::assertNull($empty->jsonSerialize());
tests/Unit/Modules/User/Domain/ValueObject/UserValueObjectTest.php:194:        $empty = UserAvatar::none();
tests/Unit/Modules/User/Domain/ValueObject/UserValueObjectTest.php:197:        self::assertNull($empty->value());
tests/Unit/Modules/User/Domain/ValueObject/UserValueObjectTest.php:198:        self::assertTrue($empty->isEmpty());
tests/Unit/Modules/User/Domain/ValueObject/UserValueObjectTest.php:199:        self::assertSame('', (string) $empty);
tests/Unit/Modules/User/Domain/ValueObject/UserValueObjectTest.php:200:        self::assertNull($empty->jsonSerialize());
tests/Unit/Modules/User/Domain/ValueObject/UserValueObjectTest.php:302:    public function testReservedNicknameHolderSupportsUnassignedAndAssigned(): void
tests/Unit/Modules/User/Domain/ValueObject/UserValueObjectTest.php:305:        $holder = ReservedNicknameHolder::assignedTo($userId);
tests/Unit/Modules/User/Domain/ValueObject/UserValueObjectTest.php:307:        self::assertTrue(ReservedNicknameHolder::unassigned()->isUnassigned());
tests/Unit/Modules/User/Domain/ValueObject/UserValueObjectTest.php:308:        self::assertNull(ReservedNicknameHolder::unassigned()->value());
tests/Unit/Modules/User/Domain/ValueObject/UserValueObjectTest.php:309:        self::assertSame('', (string) ReservedNicknameHolder::unassigned());
tests/Unit/Modules/User/Domain/ValueObject/UserValueObjectTest.php:310:        self::assertNull(ReservedNicknameHolder::unassigned()->jsonSerialize());
tests/Unit/Modules/User/Domain/ValueObject/UserValueObjectTest.php:311:        self::assertFalse(ReservedNicknameHolder::unassigned()->isAssignedTo($userId));
tests/Unit/Modules/User/Domain/ValueObject/UserValueObjectTest.php:315:        self::assertTrue($holder->equals(ReservedNicknameHolder::assignedTo($userId)));
tests/Unit/Modules/User/Domain/ValueObject/UserValueObjectTest.php:323:        self::assertSame(7, \Ramsey\Uuid\Uuid::fromString(ReservedNicknameId::generate()->value())->getVersion());
tests/Feature/Modules/User/Application/CreateUserHandlerTest.php:5:namespace Tests\Feature\Modules\User\Application;
tests/Feature/Modules/User/Application/CreateUserHandlerTest.php:9:use App\Modules\User\Domain\Entity\ReservedNickname;
tests/Feature/Modules/User/Application/CreateUserHandlerTest.php:13:use App\Modules\User\Domain\ValueObject\UserName;
tests/Feature/Modules/User/Application/CreateUserHandlerTest.php:14:use App\Modules\User\Domain\ValueObject\UserNickname;
tests/Feature/Modules/User/Application/CreateUserHandlerTest.php:15:use App\Modules\User\Repository\ReservedNicknameRepository;
tests/Feature/Modules/User/Application/CreateUserHandlerTest.php:18:use App\Shared\Domain\Exception\ValidationException;
tests/Feature/Modules/User/Application/CreateUserHandlerTest.php:31:            name: 'Йога Тест',
tests/Feature/Modules/User/Application/CreateUserHandlerTest.php:32:            nickname: 'new.nick',
tests/Feature/Modules/User/Application/CreateUserHandlerTest.php:44:        self::assertSame('new.nick', $createdUser->nickname->value());
tests/Feature/Modules/User/Application/CreateUserHandlerTest.php:50:        $this->persist($this->createUser(email: 'taken@example.com', nickname: 'someone'));
tests/Feature/Modules/User/Application/CreateUserHandlerTest.php:52:        $this->expectException(ValidationException::class);
tests/Feature/Modules/User/Application/CreateUserHandlerTest.php:57:            name: 'Йога Тест',
tests/Feature/Modules/User/Application/CreateUserHandlerTest.php:58:            nickname: 'free.nick',
tests/Feature/Modules/User/Application/CreateUserHandlerTest.php:63:    public function testRejectsTakenNickname(): void
tests/Feature/Modules/User/Application/CreateUserHandlerTest.php:65:        $this->persist($this->createUser(email: 'owner@example.com', nickname: 'busy.nick'));
tests/Feature/Modules/User/Application/CreateUserHandlerTest.php:67:        $this->expectException(ValidationException::class);
tests/Feature/Modules/User/Application/CreateUserHandlerTest.php:68:        $this->expectExceptionMessage('app.user.nickname_taken');
tests/Feature/Modules/User/Application/CreateUserHandlerTest.php:72:            name: 'Йога Тест',
tests/Feature/Modules/User/Application/CreateUserHandlerTest.php:73:            nickname: 'BUSY.NICK',
tests/Feature/Modules/User/Application/CreateUserHandlerTest.php:78:    public function testRejectsReservedNickname(): void
tests/Feature/Modules/User/Application/CreateUserHandlerTest.php:80:        $this->persist(ReservedNickname::create(UserNickname::fromString('reserved')));
tests/Feature/Modules/User/Application/CreateUserHandlerTest.php:82:        $this->expectException(ValidationException::class);
tests/Feature/Modules/User/Application/CreateUserHandlerTest.php:83:        $this->expectExceptionMessage('app.user.nickname_taken');
tests/Feature/Modules/User/Application/CreateUserHandlerTest.php:87:            name: 'Йога Тест',
tests/Feature/Modules/User/Application/CreateUserHandlerTest.php:88:            nickname: 'reserved',
tests/Feature/Modules/User/Application/CreateUserHandlerTest.php:97:            reservedNicknameRepository: $this->reservedNicknameRepository(),
tests/Feature/Modules/User/Application/CreateUserHandlerTest.php:103:    private function createUser(string $email, string $nickname): User
tests/Feature/Modules/User/Application/CreateUserHandlerTest.php:106:            name: UserName::fromString('Существующий'),
tests/Feature/Modules/User/Application/CreateUserHandlerTest.php:108:            nickname: UserNickname::fromString($nickname),
tests/Feature/Modules/User/Application/CreateUserHandlerTest.php:129:    private function reservedNicknameRepository(): ReservedNicknameRepository
tests/Feature/Modules/User/Application/CreateUserHandlerTest.php:131:        return $this->getContainer()->get(ReservedNicknameRepository::class);
tests/Feature/Modules/Auth/Console/AuthOpenApiGenerationTest.php:5:namespace Tests\Feature\Modules\Auth\Console;
tests/Feature/Modules/Auth/Application/Fixture/RecordingOutboxEventStore.php:5:namespace Tests\Feature\Modules\Auth\Application\Fixture;
tests/Feature/Modules/Auth/Presentation/SendLoginCodeJobTest.php:5:namespace Tests\Feature\Modules\Auth\Presentation;
tests/Feature/Modules/Auth/Application/Fixture/FakeSecretHasher.php:5:namespace Tests\Feature\Modules\Auth\Application\Fixture;
tests/Feature/Modules/Auth/Application/CompleteRegistrationHandlerTest.php:5:namespace Tests\Feature\Modules\Auth\Application;
tests/Feature/Modules/Auth/Application/CompleteRegistrationHandlerTest.php:15:use App\Shared\Domain\Exception\ValidationException;
tests/Feature/Modules/Auth/Application/CompleteRegistrationHandlerTest.php:26:            name: 'Имя Тест',
tests/Feature/Modules/Auth/Application/CompleteRegistrationHandlerTest.php:27:            nickname: 'newbie.one',
tests/Feature/Modules/Auth/Application/CompleteRegistrationHandlerTest.php:51:            name: 'Имя Тест',
tests/Feature/Modules/Auth/Application/CompleteRegistrationHandlerTest.php:52:            nickname: 'newbie.one',
tests/Feature/Modules/Auth/Application/CompleteRegistrationHandlerTest.php:69:            name: 'Имя Тест',
tests/Feature/Modules/Auth/Application/CompleteRegistrationHandlerTest.php:70:            nickname: 'newbie.one',
tests/Feature/Modules/Auth/Application/CompleteRegistrationHandlerTest.php:75:    public function testRejectsTakenNicknameAndKeepsTicketReusable(): void
tests/Feature/Modules/Auth/Application/CompleteRegistrationHandlerTest.php:83:                name: 'Имя Тест',
tests/Feature/Modules/Auth/Application/CompleteRegistrationHandlerTest.php:84:                nickname: 'taken.nick',
tests/Feature/Modules/Auth/Application/CompleteRegistrationHandlerTest.php:87:            self::fail('Ожидалось ValidationException.');
tests/Feature/Modules/Auth/Application/CompleteRegistrationHandlerTest.php:88:        } catch (ValidationException $validationException) {
tests/Feature/Modules/Auth/Application/CompleteRegistrationHandlerTest.php:89:            self::assertSame('app.user.nickname_taken', $validationException->getMessage());
tests/Feature/Modules/User/Application/FindUserForAuthHandlerTest.php:5:namespace Tests\Feature\Modules\User\Application;
tests/Feature/Modules/User/Application/FindUserForAuthHandlerTest.php:12:use App\Modules\User\Domain\ValueObject\UserName;
tests/Feature/Modules/User/Application/FindUserForAuthHandlerTest.php:13:use App\Modules\User\Domain\ValueObject\UserNickname;
tests/Feature/Modules/User/Application/FindUserForAuthHandlerTest.php:23:        $activeUser = $this->createUser(email: 'active@example.com', nickname: 'active.user');
tests/Feature/Modules/User/Application/FindUserForAuthHandlerTest.php:36:        $bannedUser = $this->createUser(email: 'banned@example.com', nickname: 'banned.user');
tests/Feature/Modules/User/Application/FindUserForAuthHandlerTest.php:49:        $deletedUser = $this->createUser(email: 'deleted@example.com', nickname: 'deleted.user');
tests/Feature/Modules/User/Application/FindUserForAuthHandlerTest.php:70:    private function createUser(string $email, string $nickname): User
tests/Feature/Modules/User/Application/FindUserForAuthHandlerTest.php:73:            name: UserName::fromString('Йога Тест'),
tests/Feature/Modules/User/Application/FindUserForAuthHandlerTest.php:75:            nickname: UserNickname::fromString($nickname),
tests/Feature/Modules/Auth/Repository/AuthRepositoryTest.php:5:namespace Tests\Feature\Modules\Auth\Repository;
tests/Feature/Modules/Auth/Application/RefreshTokensAndLogoutTest.php:5:namespace Tests\Feature\Modules\Auth\Application;
tests/Feature/Modules/Auth/Application/RefreshTokensAndLogoutTest.php:34:        $this->refreshHandler()->handle(new RefreshTokensCommand(refreshToken: 'invalid-token'));
tests/Feature/Modules/Auth/Http/AuthHttpTest.php:5:namespace Tests\Feature\Modules\Auth\Http;
tests/Feature/Modules/Auth/Http/AuthHttpTest.php:20:use App\Modules\User\Domain\ValueObject\UserName;
tests/Feature/Modules/Auth/Http/AuthHttpTest.php:21:use App\Modules\User\Domain\ValueObject\UserNickname;
tests/Feature/Modules/Auth/Http/AuthHttpTest.php:44:            'reserved_nicknames',
tests/Feature/Modules/Auth/Http/AuthHttpTest.php:65:        $this->http()->postJson('/api/v1/auth/code/request', ['email' => ''])->assertUnprocessable();
tests/Feature/Modules/Auth/Http/AuthHttpTest.php:128:            'name' => 'Имя Тест',
tests/Feature/Modules/Auth/Http/AuthHttpTest.php:129:            'nickname' => 'register.user',
tests/Feature/Modules/Auth/Http/AuthHttpTest.php:141:    public function testRegisterRejectsTakenNicknameAndKeepsTicket(): void
tests/Feature/Modules/Auth/Http/AuthHttpTest.php:148:            'name' => 'Имя Тест',
tests/Feature/Modules/Auth/Http/AuthHttpTest.php:149:            'nickname' => 'occupied.nick',
tests/Feature/Modules/Auth/Http/AuthHttpTest.php:150:        ])->assertUnprocessable();
tests/Feature/Modules/Auth/Http/AuthHttpTest.php:162:            'name' => 'Имя Тест',
tests/Feature/Modules/Auth/Http/AuthHttpTest.php:163:            'nickname' => 'whoever.user',
tests/Feature/Modules/Auth/Http/AuthHttpTest.php:245:    private function seedActiveUser(string $email, string $nickname): void
tests/Feature/Modules/Auth/Http/AuthHttpTest.php:248:            name: UserName::fromString('Тест Пользователь'),
tests/Feature/Modules/Auth/Http/AuthHttpTest.php:250:            nickname: UserNickname::fromString($nickname),
tests/Feature/Modules/Auth/Application/AuthApplicationTestCase.php:5:namespace Tests\Feature\Modules\Auth\Application;
tests/Feature/Modules/Auth/Application/AuthApplicationTestCase.php:23:use App\Modules\User\Domain\ValueObject\UserName;
tests/Feature/Modules/Auth/Application/AuthApplicationTestCase.php:24:use App\Modules\User\Domain\ValueObject\UserNickname;
tests/Feature/Modules/Auth/Application/AuthApplicationTestCase.php:25:use App\Modules\User\Repository\ReservedNicknameRepository;
tests/Feature/Modules/Auth/Application/AuthApplicationTestCase.php:54:            reservedNicknameRepository: $this->reservedNicknameRepository(),
tests/Feature/Modules/Auth/Application/AuthApplicationTestCase.php:65:    protected function persistUser(string $email, string $nickname, bool $banned = false): User
tests/Feature/Modules/Auth/Application/AuthApplicationTestCase.php:68:            name: UserName::fromString('Тест Пользователь'),
tests/Feature/Modules/Auth/Application/AuthApplicationTestCase.php:70:            nickname: UserNickname::fromString($nickname),
tests/Feature/Modules/Auth/Application/AuthApplicationTestCase.php:166:    protected function reservedNicknameRepository(): ReservedNicknameRepository
tests/Feature/Modules/Auth/Application/AuthApplicationTestCase.php:168:        return $this->getContainer()->get(ReservedNicknameRepository::class);
tests/Feature/Modules/Auth/Infrastructure/CycleTokenStorageTest.php:5:namespace Tests\Feature\Modules\Auth\Infrastructure;
tests/Feature/Modules/Auth/Application/LoginCodeVerificationTest.php:5:namespace Tests\Feature\Modules\Auth\Application;
tests/Feature/Modules/Auth/Application/LoginCodeVerificationTest.php:59:            self::assertSame('app.auth.invalid_code', $authenticationException->getMessage());
tests/Feature/Modules/Auth/Application/SendLoginCodeHandlerTest.php:5:namespace Tests\Feature\Modules\Auth\Application;
tests/Feature/Modules/Auth/Application/RequestLoginCodeHandlerTest.php:5:namespace Tests\Feature\Modules\Auth\Application;

exec
/bin/zsh -lc "sed -n '1,240p' app/src/Modules/Auth/Presentation/Job/SendLoginCodeJob.php && sed -n '1,220p' tests/Feature/Modules/Auth/Presentation/SendLoginCodeJobTest.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Auth\Presentation\Job;

use App\Modules\Auth\Application\Command\Auth\SendLoginCode\SendLoginCodeCommand;
use App\Modules\Auth\Application\Command\Auth\SendLoginCode\SendLoginCodeHandler;
use App\Modules\Auth\Application\Message\LoginCodeRequested;
use App\Modules\Outbox\Application\Contract\OutboxMessageLoaderContract;
use App\Modules\Outbox\Application\Message\OutboxQueueEnvelope;
use GianTiaga\SpiralCqrs\CommandBusInterface;
use Psr\Log\LoggerInterface;
use Spiral\Queue\Exception\RetryException;
use Spiral\Queue\JobHandler;

/**
 * Инфраструктурный Job отправки письма с кодом: грузит LoginCodeRequested из outbox и
 * диспатчит SendLoginCodeCommand. Сбой отправки (граница системы) → WARN + RetryException,
 * чтобы письмо было повторено (статусы outbox правит общий queue interceptor).
 */
final class SendLoginCodeJob extends JobHandler
{
    private const string MAILER_FAILURE_MESSAGE = 'Не удалось отправить письмо с кодом входа.';

    public function invoke(
        OutboxQueueEnvelope $payload,
        string $id,
        OutboxMessageLoaderContract $outboxMessageLoader,
        CommandBusInterface $commandBus,
        SendLoginCodeHandler $sendLoginCodeHandler,
        LoggerInterface $logger,
    ): void {
        $loginCodeRequested = $outboxMessageLoader->load(
            outboxEventId: $payload->outboxEventId,
            expectedMessageClass: LoginCodeRequested::class,
        );

        try {
            $commandBus->dispatch(
                command: new SendLoginCodeCommand(
                    email: $loginCodeRequested->email,
                    code: $loginCodeRequested->code,
                    locale: $loginCodeRequested->locale,
                ),
                handler: $sendLoginCodeHandler->handle(...),
            );
        } catch (\Throwable $exception) {
            $logger->warning(message: self::MAILER_FAILURE_MESSAGE, context: [
                'email' => $loginCodeRequested->email,
                'jobId' => $id,
                'errorClass' => $exception::class,
            ]);

            throw new RetryException(reason: self::MAILER_FAILURE_MESSAGE);
        }
    }
}
<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Auth\Presentation;

use App\Modules\Auth\Application\Command\Auth\SendLoginCode\SendLoginCodeHandler;
use App\Modules\Auth\Application\Message\LoginCodeRequested;
use App\Modules\Auth\Presentation\Job\SendLoginCodeJob;
use App\Modules\Outbox\Application\Contract\OutboxMessageLoaderContract;
use App\Modules\Outbox\Application\Message\OutboxQueueEnvelope;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventId;
use App\Modules\Outbox\Domain\ValueObject\OutboxEventType;
use App\Shared\Infrastructure\Configuration\Locale\LocaleConfig;
use GianTiaga\SpiralCqrs\CommandBusInterface;
use Psr\Log\NullLogger;
use Ramsey\Uuid\Uuid;
use Spiral\Mailer\MailerInterface;
use Spiral\Mailer\Message;
use Spiral\Queue\Exception\RetryException;
use Spiral\Translator\TranslatorInterface;
use Tests\Feature\Modules\Auth\Application\Fixture\RecordingMailer;
use Tests\TestCase;

final class SendLoginCodeJobTest extends TestCase
{
    public function testLoadsMessageAndSendsEmail(): void
    {
        $mailer = new RecordingMailer();

        $this->job()->invoke(
            payload: $this->envelope(),
            id: 'job-1',
            outboxMessageLoader: $this->loaderReturning(),
            commandBus: $this->getContainer()->get(CommandBusInterface::class),
            sendLoginCodeHandler: $this->handler($mailer),
            logger: new NullLogger(),
        );

        self::assertCount(1, $mailer->messages);
        $message = $mailer->messages[0];
        self::assertInstanceOf(Message::class, $message);
        self::assertStringContainsString('123456', (string) $message->getData()['body']);
    }

    public function testRetriesWhenMailerFails(): void
    {
        $failingMailer = $this->createStub(MailerInterface::class);
        $failingMailer->method('send')->willThrowException(new \RuntimeException('smtp недоступен'));

        $this->expectException(RetryException::class);

        $this->job()->invoke(
            payload: $this->envelope(),
            id: 'job-1',
            outboxMessageLoader: $this->loaderReturning(),
            commandBus: $this->getContainer()->get(CommandBusInterface::class),
            sendLoginCodeHandler: $this->handler($failingMailer),
            logger: new NullLogger(),
        );
    }

    private function loaderReturning(): OutboxMessageLoaderContract
    {
        $loader = $this->createStub(OutboxMessageLoaderContract::class);
        $loader->method('load')->willReturn(new LoginCodeRequested(
            email: 'user@example.com',
            code: '123456',
            locale: 'ru',
        ));

        return $loader;
    }

    private function handler(MailerInterface $mailer): SendLoginCodeHandler
    {
        return new SendLoginCodeHandler(
            mailer: $mailer,
            translator: $this->getContainer()->get(TranslatorInterface::class),
            localeConfig: $this->getContainer()->get(LocaleConfig::class),
        );
    }

    private function job(): SendLoginCodeJob
    {
        return $this->getContainer()->get(SendLoginCodeJob::class);
    }

    private function envelope(): OutboxQueueEnvelope
    {
        return new OutboxQueueEnvelope(
            outboxEventId: OutboxEventId::fromString(Uuid::uuid7()->toString()),
            outboxEventType: OutboxEventType::fromString(LoginCodeRequested::class),
        );
    }
}

exec
/bin/zsh -lc "sed -n '1,260p' tests/Feature/Modules/User/Application/CreateUserHandlerTest.php && sed -n '1,220p' tests/Feature/Modules/User/Application/FindUserForAuthHandlerTest.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc 'rg -n "RateLimit|rate limit|429|code/request|code/verify|register|refresh|logout|invalid email|invalid nickname|invalid name" docs/reviews/2026-06-15_16-07_auth-module.md tests/Feature/Modules/Auth/Http/AuthHttpTest.php tests/Unit/Shared/Infrastructure/Framework/Middleware/RateLimitMiddlewareTest.php' in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\User\Application;

use App\Modules\User\Application\Command\User\CreateUser\CreateUserCommand;
use App\Modules\User\Application\Command\User\CreateUser\CreateUserHandler;
use App\Modules\User\Domain\Entity\ReservedNickname;
use App\Modules\User\Domain\Entity\User;
use App\Modules\User\Domain\Enum\UserStatus;
use App\Modules\User\Domain\ValueObject\Email;
use App\Modules\User\Domain\ValueObject\UserName;
use App\Modules\User\Domain\ValueObject\UserNickname;
use App\Modules\User\Repository\ReservedNicknameRepository;
use App\Modules\User\Repository\UserRepository;
use App\Shared\Domain\Enum\Locale;
use App\Shared\Domain\Exception\ValidationException;
use App\Shared\Domain\ValueObject\AbstractUuidV7Id;
use App\Shared\Domain\ValueObject\UserId;
use Cycle\ORM\EntityManagerInterface;
use Psr\Log\NullLogger;
use Tests\DatabaseTestCase;

final class CreateUserHandlerTest extends DatabaseTestCase
{
    public function testCreatesActiveUserAndReturnsId(): void
    {
        $result = $this->handler()->handle(new CreateUserCommand(
            email: 'new@example.com',
            name: 'Йога Тест',
            nickname: 'new.nick',
            locale: 'ru',
        ));

        self::assertTrue(AbstractUuidV7Id::isUuidV7($result->userId));

        $this->cleanOrmHeap();
        $createdUser = $this->userRepository()->findById(UserId::fromString($result->userId));

        self::assertInstanceOf(User::class, $createdUser);
        self::assertSame(UserStatus::Active, $createdUser->status);
        self::assertSame('new@example.com', $createdUser->email->value());
        self::assertSame('new.nick', $createdUser->nickname->value());
        self::assertSame(Locale::Ru, $createdUser->locale);
    }

    public function testRejectsTakenEmail(): void
    {
        $this->persist($this->createUser(email: 'taken@example.com', nickname: 'someone'));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('app.user.email_taken');

        $this->handler()->handle(new CreateUserCommand(
            email: 'TAKEN@example.com',
            name: 'Йога Тест',
            nickname: 'free.nick',
            locale: 'ru',
        ));
    }

    public function testRejectsTakenNickname(): void
    {
        $this->persist($this->createUser(email: 'owner@example.com', nickname: 'busy.nick'));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('app.user.nickname_taken');

        $this->handler()->handle(new CreateUserCommand(
            email: 'fresh@example.com',
            name: 'Йога Тест',
            nickname: 'BUSY.NICK',
            locale: 'ru',
        ));
    }

    public function testRejectsReservedNickname(): void
    {
        $this->persist(ReservedNickname::create(UserNickname::fromString('reserved')));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('app.user.nickname_taken');

        $this->handler()->handle(new CreateUserCommand(
            email: 'fresh@example.com',
            name: 'Йога Тест',
            nickname: 'reserved',
            locale: 'ru',
        ));
    }

    private function handler(): CreateUserHandler
    {
        return new CreateUserHandler(
            userRepository: $this->userRepository(),
            reservedNicknameRepository: $this->reservedNicknameRepository(),
            entityManager: $this->entityManager(),
            logger: new NullLogger(),
        );
    }

    private function createUser(string $email, string $nickname): User
    {
        return User::create(
            name: UserName::fromString('Существующий'),
            email: Email::fromString($email),
            nickname: UserNickname::fromString($nickname),
            locale: Locale::Ru,
        );
    }

    private function persist(object $entity): void
    {
        $this->entityManager()->persist($entity);
        $this->entityManager()->run();
    }

    private function entityManager(): EntityManagerInterface
    {
        return $this->getContainer()->get(EntityManagerInterface::class);
    }

    private function userRepository(): UserRepository
    {
        return $this->getContainer()->get(UserRepository::class);
    }

    private function reservedNicknameRepository(): ReservedNicknameRepository
    {
        return $this->getContainer()->get(ReservedNicknameRepository::class);
    }
}
<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\User\Application;

use App\Modules\User\Application\Dto\UserAuthView;
use App\Modules\User\Application\Query\User\FindUserForAuth\FindUserForAuthHandler;
use App\Modules\User\Application\Query\User\FindUserForAuth\FindUserForAuthQuery;
use App\Modules\User\Domain\Entity\User;
use App\Modules\User\Domain\ValueObject\Email;
use App\Modules\User\Domain\ValueObject\UserName;
use App\Modules\User\Domain\ValueObject\UserNickname;
use App\Modules\User\Repository\UserRepository;
use App\Shared\Domain\Enum\Locale;
use Cycle\ORM\EntityManagerInterface;
use Tests\DatabaseTestCase;

final class FindUserForAuthHandlerTest extends DatabaseTestCase
{
    public function testReturnsViewWithSignInAllowedForActiveUser(): void
    {
        $activeUser = $this->createUser(email: 'active@example.com', nickname: 'active.user');
        $activeUser->confirmEmail();
        $this->persist($activeUser);

        $view = $this->handler()->handle(new FindUserForAuthQuery(email: 'ACTIVE@example.com'));

        self::assertInstanceOf(UserAuthView::class, $view);
        self::assertSame($activeUser->id->value(), $view->userId);
        self::assertTrue($view->canSignIn);
    }

    public function testReturnsViewWithSignInDeniedForBannedUser(): void
    {
        $bannedUser = $this->createUser(email: 'banned@example.com', nickname: 'banned.user');
        $bannedUser->confirmEmail();
        $bannedUser->ban();
        $this->persist($bannedUser);

        $view = $this->handler()->handle(new FindUserForAuthQuery(email: 'banned@example.com'));

        self::assertInstanceOf(UserAuthView::class, $view);
        self::assertFalse($view->canSignIn);
    }

    public function testReturnsViewWithSignInDeniedForDeletedUser(): void
    {
        $deletedUser = $this->createUser(email: 'deleted@example.com', nickname: 'deleted.user');
        $deletedUser->confirmEmail();
        $deletedUser->markDeleted(new \DateTimeImmutable());
        $this->persist($deletedUser);

        $view = $this->handler()->handle(new FindUserForAuthQuery(email: 'deleted@example.com'));

        self::assertInstanceOf(UserAuthView::class, $view);
        self::assertFalse($view->canSignIn);
    }

    public function testReturnsNullForMissingUser(): void
    {
        self::assertNull($this->handler()->handle(new FindUserForAuthQuery(email: 'absent@example.com')));
    }

    private function handler(): FindUserForAuthHandler
    {
        return new FindUserForAuthHandler(userRepository: $this->userRepository());
    }

    private function createUser(string $email, string $nickname): User
    {
        return User::create(
            name: UserName::fromString('Йога Тест'),
            email: Email::fromString($email),
            nickname: UserNickname::fromString($nickname),
            locale: Locale::Ru,
        );
    }

    private function persist(object $entity): void
    {
        $this->entityManager()->persist($entity);
        $this->entityManager()->run();
    }

    private function entityManager(): EntityManagerInterface
    {
        return $this->getContainer()->get(EntityManagerInterface::class);
    }

    private function userRepository(): UserRepository
    {
        return $this->getContainer()->get(UserRepository::class);
    }
}

 succeeded in 0ms:
tests/Unit/Shared/Infrastructure/Framework/Middleware/RateLimitMiddlewareTest.php:7:use App\Shared\Infrastructure\Framework\Middleware\RateLimitMiddleware;
tests/Unit/Shared/Infrastructure/Framework/Middleware/RateLimitMiddlewareTest.php:19:final class RateLimitMiddlewareTest extends TestCase
tests/Unit/Shared/Infrastructure/Framework/Middleware/RateLimitMiddlewareTest.php:24:        $request = $this->request(clientIp: '10.0.0.1', path: '/api/v1/auth/code/request');
tests/Unit/Shared/Infrastructure/Framework/Middleware/RateLimitMiddlewareTest.php:31:        self::assertSame(429, $blocked->getStatusCode());
tests/Unit/Shared/Infrastructure/Framework/Middleware/RateLimitMiddlewareTest.php:33:        self::assertStringContainsString('"code":429', $body);
tests/Unit/Shared/Infrastructure/Framework/Middleware/RateLimitMiddlewareTest.php:42:        self::assertSame(429, $middleware->process($this->request('1.1.1.1', '/p'), $this->handler())->getStatusCode());
tests/Unit/Shared/Infrastructure/Framework/Middleware/RateLimitMiddlewareTest.php:51:        self::assertSame(429, $middleware->process($this->request('9.9.9.9', '/route-a'), $this->handler())->getStatusCode());
tests/Unit/Shared/Infrastructure/Framework/Middleware/RateLimitMiddlewareTest.php:55:    private function middleware(int $maxAttempts, CacheInterface $cache): RateLimitMiddleware
tests/Unit/Shared/Infrastructure/Framework/Middleware/RateLimitMiddlewareTest.php:70:        return new RateLimitMiddleware(
tests/Feature/Modules/Auth/Http/AuthHttpTest.php:54:        $response = $this->http()->postJson('/api/v1/auth/code/request', ['email' => 'request@example.com']);
tests/Feature/Modules/Auth/Http/AuthHttpTest.php:65:        $this->http()->postJson('/api/v1/auth/code/request', ['email' => ''])->assertUnprocessable();
tests/Feature/Modules/Auth/Http/AuthHttpTest.php:73:        $response = $this->http()->postJson('/api/v1/auth/code/verify', [
tests/Feature/Modules/Auth/Http/AuthHttpTest.php:87:        $response = $this->http()->postJson('/api/v1/auth/code/verify', [
tests/Feature/Modules/Auth/Http/AuthHttpTest.php:101:        $this->http()->postJson('/api/v1/auth/code/verify', [
tests/Feature/Modules/Auth/Http/AuthHttpTest.php:116:        $this->http()->postJson('/api/v1/auth/code/verify', [
tests/Feature/Modules/Auth/Http/AuthHttpTest.php:124:        $this->seedRegistrationTicket('register@example.com', 'ticket-http-1');
tests/Feature/Modules/Auth/Http/AuthHttpTest.php:126:        $response = $this->http()->postJson('/api/v1/auth/register', [
tests/Feature/Modules/Auth/Http/AuthHttpTest.php:129:            'nickname' => 'register.user',
tests/Feature/Modules/Auth/Http/AuthHttpTest.php:136:        $user = $this->userRepository()->findByEmail(Email::fromString('register@example.com'));
tests/Feature/Modules/Auth/Http/AuthHttpTest.php:144:        $this->seedRegistrationTicket('register-taken@example.com', 'ticket-http-2');
tests/Feature/Modules/Auth/Http/AuthHttpTest.php:146:        $this->http()->postJson('/api/v1/auth/register', [
tests/Feature/Modules/Auth/Http/AuthHttpTest.php:160:        $this->http()->postJson('/api/v1/auth/register', [
tests/Feature/Modules/Auth/Http/AuthHttpTest.php:171:        $this->http()->postJson('/api/v1/auth/refresh', ['refreshToken' => $pair->refreshToken])->assertOk();
tests/Feature/Modules/Auth/Http/AuthHttpTest.php:172:        $this->http()->postJson('/api/v1/auth/refresh', ['refreshToken' => $pair->refreshToken])->assertUnauthorized();
tests/Feature/Modules/Auth/Http/AuthHttpTest.php:179:        $this->http()->postJson('/api/v1/auth/refresh', ['refreshToken' => $pair->accessToken])->assertUnauthorized();
tests/Feature/Modules/Auth/Http/AuthHttpTest.php:188:            ->postJson('/api/v1/auth/logout')
tests/Feature/Modules/Auth/Http/AuthHttpTest.php:193:            ->postJson('/api/v1/auth/logout')
tests/Feature/Modules/Auth/Http/AuthHttpTest.php:199:        $this->http()->postJson('/api/v1/auth/logout')->assertUnauthorized();
tests/Feature/Modules/Auth/Http/AuthHttpTest.php:207:            ->withAuthorizationToken($pair->refreshToken)
tests/Feature/Modules/Auth/Http/AuthHttpTest.php:208:            ->postJson('/api/v1/auth/logout')
tests/Feature/Modules/Auth/Http/AuthHttpTest.php:212:    public function testRateLimitReturns429AfterExceedingAttempts(): void
tests/Feature/Modules/Auth/Http/AuthHttpTest.php:217:            $http->postJson('/api/v1/auth/code/request', ['email' => 'rate-limit@example.com'])->assertOk();
tests/Feature/Modules/Auth/Http/AuthHttpTest.php:220:        $http->postJson('/api/v1/auth/code/request', ['email' => 'rate-limit@example.com'])->assertStatus(429);
tests/Feature/Modules/Auth/Http/AuthHttpTest.php:271:            $loginCode->registerFailedAttempt($now);
docs/reviews/2026-06-15_16-07_auth-module.md:17:блокировки строк, rate limit и сквозные тесты на месте, архитектурные правила в
docs/reviews/2026-06-15_16-07_auth-module.md:21:переносимого пакета из прикладной задачи и пару поведенческих нюансов rate limit
docs/reviews/2026-06-15_16-07_auth-module.md:28:План в разделе «Прогресс выполнения» помечает фазу 6 (rate limit) как невыполненную,
docs/reviews/2026-06-15_16-07_auth-module.md:29:хотя `RateLimitMiddleware`, переводы и тесты к нему реально присутствуют, а журнал
docs/reviews/2026-06-15_16-07_auth-module.md:32:продолжения, решит, что rate limit ещё не сделан, и может начать его заново или
docs/reviews/2026-06-15_16-07_auth-module.md:38:- **Где:** `docs/plans/2026-06-15_13-25_auth-module.md:736` — `- [ ] Фаза 6: Shared — rate limit`;
docs/reviews/2026-06-15_16-07_auth-module.md:39:  факт — `app/src/Shared/Infrastructure/Framework/Middleware/RateLimitMiddleware.php`,
docs/reviews/2026-06-15_16-07_auth-module.md:40:  `tests/Unit/Shared/Infrastructure/Framework/Middleware/RateLimitMiddlewareTest.php`,
docs/reviews/2026-06-15_16-07_auth-module.md:105:### 2. Окно rate limit продлевается на каждом запросе и фактически не «фиксированное»
docs/reviews/2026-06-15_16-07_auth-module.md:124:- **Где:** `app/src/Shared/Infrastructure/Framework/Middleware/RateLimitMiddleware.php:46` —
docs/reviews/2026-06-15_16-07_auth-module.md:151:  (`Locale::from($command->locale)`); строка приходит из `AuthController::register`
docs/reviews/2026-06-15_16-07_auth-module.md:189:Лимиты заданы на четырёх маршрутах с разными порогами (`code/request` 5, `code/verify` 10,
docs/reviews/2026-06-15_16-07_auth-module.md:190:`register` 10, `refresh` 20), но 429-ветка через HTTP проверяется только на `code/request`.
docs/reviews/2026-06-15_16-07_auth-module.md:194:middleware покрыт, а конкретная привязка к `verify`/`register`/`refresh` — нет.
docs/reviews/2026-06-15_16-07_auth-module.md:200:- **Где:** `app/.../Controller/AuthController.php:79,106,139` (лимиты на verify/register/refresh);
docs/reviews/2026-06-15_16-07_auth-module.md:201:  HTTP-тест 429 — только `testRateLimitReturns429AfterExceedingAttempts` на `code/request`
docs/reviews/2026-06-15_16-07_auth-module.md:205:- **Как исправить:** добавить по одному 429-кейсу на остальные маршруты с лимитом (минимум на
docs/reviews/2026-06-15_16-07_auth-module.md:208:- **Тесты:** добавить в `AuthHttpTest` кейс превышения лимита на `code/verify` (порог 10) или
docs/reviews/2026-06-15_16-07_auth-module.md:209:  `refresh` (порог 20).

exec
/bin/zsh -lc "sed -n '1,220p' app/src/Modules/Auth/Application/Message/LoginCodeRequested.php && sed -n '1,160p' app/src/Modules/Auth/Application/Command/Auth/SendLoginCode/SendLoginCodeCommand.php && sed -n '1,180p' app/views/login-code.twig" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Message;

use App\Modules\Outbox\Application\Message\OutboxMessage;

/**
 * Outbox-сообщение: запрошен код входа, нужно отправить письмо. Payload — только примитивы
 * (email, code, locale), чтобы Valinor восстановил его без кастомных конструкторов VO.
 * Код идёт открытым текстом (иначе письмо не отправить) — короткоживущая запись, принятый риск.
 */
final readonly class LoginCodeRequested implements OutboxMessage
{
    public function __construct(
        public string $email,
        public string $code,
        public string $locale,
    ) {}
}
<?php

declare(strict_types=1);

namespace App\Modules\Auth\Application\Command\Auth\SendLoginCode;

final readonly class SendLoginCodeCommand
{
    public function __construct(
        public string $email,
        public string $code,
        public string $locale,
    ) {}
}
{%- do _msg_.subject(subject) -%}
{%- do _msg_.text(body) -%}

exec
/bin/zsh -lc "sed -n '1,220p' app/src/Modules/Media/Presentation/Job/ProcessMediaJob.php && sed -n '1,220p' app/src/Modules/Outbox/Presentation/Job/OutboxDebugLogJob.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Media\Presentation\Job;

use App\Modules\Media\Application\Command\Media\ProcessMedia\ProcessMediaCommand;
use App\Modules\Media\Application\Command\Media\ProcessMedia\ProcessMediaHandler;
use App\Modules\Media\Application\Command\Media\RecordMediaProcessingFailure\RecordMediaProcessingFailureCommand;
use App\Modules\Media\Application\Command\Media\RecordMediaProcessingFailure\RecordMediaProcessingFailureHandler;
use App\Modules\Media\Application\Exception\MediaFileServiceFailedException;
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
 * разрешён): фиксируем безопасную ошибку на Media и классифицируем — транзиентную просим
 * повторить (RetryException, его читает OutboxQueueStatusInterceptor), постоянную пробрасываем
 * терминально (outbox -> failed). Статусы outbox Job сам не трогает.
 */
final class ProcessMediaJob extends JobHandler
{
    private const string STORAGE_FAILURE_MESSAGE = 'Ошибка хранилища при обработке медиа.';
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
                    conversions: $mediaUploaded->conversions,
                ),
                handler: $processMediaHandler->handle(...),
            );
        } catch (\Throwable $exception) {
            // Транзиентность приходит контрактным сигналом MediaFileServiceFailedException::isTransient():
            // классификацию AWS делает Infrastructure, Presentation не знает про реализацию хранилища.
            $isTransient = $exception instanceof MediaFileServiceFailedException && $exception->isTransient();

            // Запись ошибки на Media — отдельный сбойный путь: медиа могли конкурентно удалить
            // (NotFoundException) или короткий сбой БД. Защищаем только этот вызов локальным guard,
            // чтобы вторичный сбой записи не подменил исходную причину и решение retry/terminal:
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
                // Транзиентный ретраябельный сбой инфраструктуры -> WARN (rules.md:84), повтор ожидаем.
                $logger->warning(
                    message: 'Транзиентная ошибка обработки медиа, запланирован повтор.',
                    context: $logContext,
                );

                throw new RetryException(reason: self::STORAGE_FAILURE_MESSAGE);
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
<?php

declare(strict_types=1);

namespace App\Modules\Outbox\Presentation\Job;

use App\Modules\Outbox\Application\Command\ProcessDebugLogMessage\ProcessOutboxDebugLogMessageCommand;
use App\Modules\Outbox\Application\Command\ProcessDebugLogMessage\ProcessOutboxDebugLogMessageHandler;
use App\Modules\Outbox\Application\Contract\OutboxMessageLoaderContract;
use App\Modules\Outbox\Application\Message\OutboxDebugLogMessage;
use App\Modules\Outbox\Application\Message\OutboxQueueEnvelope;
use GianTiaga\SpiralCqrs\CommandBusInterface;
use Spiral\Queue\JobHandler;

final class OutboxDebugLogJob extends JobHandler
{
    public function invoke(
        OutboxQueueEnvelope $payload,
        string $id,
        OutboxMessageLoaderContract $outboxMessageLoader,
        CommandBusInterface $commandBus,
        ProcessOutboxDebugLogMessageHandler $processOutboxDebugLogMessageHandler,
    ): void {
        $outboxDebugLogMessage = $outboxMessageLoader->load(
            outboxEventId: $payload->outboxEventId,
            expectedMessageClass: OutboxDebugLogMessage::class,
        );

        $commandBus->dispatch(
            command: new ProcessOutboxDebugLogMessageCommand(
                text: $outboxDebugLogMessage->text,
                createdAt: $outboxDebugLogMessage->createdAt,
                jobId: $id,
            ),
            handler: $processOutboxDebugLogMessageHandler->handle(...),
        );
    }
}

exec
/bin/zsh -lc "sed -n '1,220p' tests/Unit/Modules/Auth/Presentation/Http/Middleware/AuthContextAttributeMiddlewareTest.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Auth\Presentation\Http\Middleware;

use App\Modules\Auth\Infrastructure\Auth\AuthTokenView;
use App\Modules\Auth\Presentation\Http\Middleware\AuthContextAttributeMiddleware;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Spiral\Auth\AuthContextInterface;
use Spiral\Auth\TokenInterface;

final class AuthContextAttributeMiddlewareTest extends TestCase
{
    public function testSetsAttributesForAccessToken(): void
    {
        $request = $this->process(new AuthTokenView(
            id: 'raw',
            payload: ['userID' => 'user-1', 'type' => 'access', 'sessionID' => 'session-1'],
            expiresAt: null,
        ));

        self::assertSame('user-1', $request->getAttribute('authUserId'));
        self::assertSame('session-1', $request->getAttribute('authSessionId'));
    }

    public function testSkipsWhenNoToken(): void
    {
        self::assertNull($this->process(null)->getAttribute('authUserId'));
    }

    public function testSkipsRefreshToken(): void
    {
        $request = $this->process(new AuthTokenView(
            id: 'raw',
            payload: ['userID' => 'user-1', 'type' => 'refresh', 'sessionID' => 'session-1'],
            expiresAt: null,
        ));

        self::assertNull($request->getAttribute('authUserId'));
    }

    public function testSkipsAccessTokenWithoutUserId(): void
    {
        $request = $this->process(new AuthTokenView(
            id: 'raw',
            payload: ['type' => 'access', 'sessionID' => 'session-1'],
            expiresAt: null,
        ));

        self::assertNull($request->getAttribute('authUserId'));
        self::assertNull($request->getAttribute('authSessionId'));
    }

    private function process(TokenInterface|null $token): ServerRequestInterface
    {
        $authContext = new class ($token) implements AuthContextInterface {
            public function __construct(private readonly TokenInterface|null $token) {}

            public function start(TokenInterface $token, string|null $transport = null): void {}

            public function getToken(): TokenInterface|null
            {
                return $this->token;
            }

            public function getTransport(): string|null
            {
                return null;
            }

            public function getActor(): object|null
            {
                return null;
            }

            public function close(): void {}

            public function isClosed(): bool
            {
                return false;
            }
        };

        $captureHandler = new class implements RequestHandlerInterface {
            public ServerRequestInterface|null $request = null;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->request = $request;

                return new Response(status: 200);
            }
        };

        new AuthContextAttributeMiddleware(authContext: $authContext)
            ->process(new ServerRequest(method: 'POST', uri: '/api/v1/auth/logout'), $captureHandler);

        return $captureHandler->request ?? throw new \RuntimeException('Обработчик не был вызван.');
    }
}

exec
/bin/zsh -lc 'git diff HEAD -- phpstan.neon packages/spiral-api-errors/src/Filter/ApiValidationErrorsRenderer.php packages/spiral-api-errors/tests/Filter/ApiValidationErrorsRendererTest.php app/src/Shared/Infrastructure/Framework/Kernel.php app/config/queue.php app/config/openapi.php public/openapi/openapi.yml' in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
diff --git a/app/config/openapi.php b/app/config/openapi.php
index 086b344..6e1ff5e 100644
--- a/app/config/openapi.php
+++ b/app/config/openapi.php
@@ -5,8 +5,8 @@ declare(strict_types=1);
 return [
     'enabled' => (bool) \env('OPENAPI_ENABLED', true),
     'swaggerEnabled' => (bool) \env('OPENAPI_SWAGGER_ENABLED', \env('APP_ENV') !== 'production'),
-    'sourcePath' => 'app/src/Modules/System/Presentation/Http',
-    'apiNamespace' => 'App\\Modules\\System\\Presentation\\Http',
+    'sourcePath' => 'app/src/Modules',
+    'apiNamespace' => 'App\\Modules',
     'routePrefix' => '/api/v1',
     'outputFile' => 'public/openapi/openapi.yml',
     'title' => 'YogaLoka API',
diff --git a/app/config/queue.php b/app/config/queue.php
index fa74286..44c1add 100644
--- a/app/config/queue.php
+++ b/app/config/queue.php
@@ -2,6 +2,7 @@
 
 declare(strict_types=1);
 
+use App\Modules\Auth\Presentation\Job\SendLoginCodeJob;
 use App\Modules\Media\Presentation\Job\ProcessMediaJob;
 use App\Modules\Outbox\Infrastructure\Queue\OutboxQueueSerializer;
 use App\Modules\Outbox\Infrastructure\Queue\OutboxQueueStatusInterceptor;
@@ -130,6 +131,7 @@ return [
             // 'ping' => \App\Modules\System\Presentation\Job\Ping::class
             OutboxDebugLogJob::class => OutboxDebugLogJob::class,
             ProcessMediaJob::class => ProcessMediaJob::class,
+            SendLoginCodeJob::class => SendLoginCodeJob::class,
         ],
 
         /**
@@ -143,6 +145,7 @@ return [
             // \App\Modules\System\Presentation\Job\Ping::class => 'json',
             OutboxDebugLogJob::class => OutboxQueueSerializer::class,
             ProcessMediaJob::class => OutboxQueueSerializer::class,
+            SendLoginCodeJob::class => OutboxQueueSerializer::class,
         ],
     ],
 
diff --git a/app/src/Shared/Infrastructure/Framework/Kernel.php b/app/src/Shared/Infrastructure/Framework/Kernel.php
index e2d89a8..a688673 100644
--- a/app/src/Shared/Infrastructure/Framework/Kernel.php
+++ b/app/src/Shared/Infrastructure/Framework/Kernel.php
@@ -33,6 +33,7 @@ use Spiral\Twig\Bootloader\TwigBootloader;
 use Spiral\Validation\Bootloader\ValidationBootloader;
 use Spiral\Validation\Symfony\Bootloader\ValidatorBootloader;
 use Spiral\Views\Bootloader\ViewsBootloader;
+use App\Modules\Auth\Infrastructure\Bootloader\AuthBootloader;
 use App\Modules\Media\Infrastructure\Bootloader\MediaBootloader;
 use App\Modules\Outbox\Infrastructure\Bootloader\OutboxBootloader;
 use App\Modules\Outbox\Infrastructure\Bootloader\OutboxConsoleBootloader;
@@ -134,6 +135,7 @@ class Kernel extends \Spiral\Framework\Kernel
             CqrsBootloader::class,
             OutboxBootloader::class,
             MediaBootloader::class,
+            AuthBootloader::class,
 
             // Почта
             MailerBootloader::class,
diff --git a/packages/spiral-api-errors/src/Filter/ApiValidationErrorsRenderer.php b/packages/spiral-api-errors/src/Filter/ApiValidationErrorsRenderer.php
index 74a9a0d..8d51521 100644
--- a/packages/spiral-api-errors/src/Filter/ApiValidationErrorsRenderer.php
+++ b/packages/spiral-api-errors/src/Filter/ApiValidationErrorsRenderer.php
@@ -31,8 +31,21 @@ final readonly class ApiValidationErrorsRenderer implements ErrorsRendererInterf
     {
         $validationErrors = [];
         foreach ($errors as $field => $message) {
-            $validationErrors[] = new ValidationErrorItemResponse(field: $field, message: $message);
+            $validationErrors[] = new ValidationErrorItemResponse(field: $field, message: $this->firstMessage($message));
         }
         return $validationErrors;
     }
+    /**
+     * Symfony-валидатор отдаёт сообщения поля списком, а casting-ошибки — строкой. Берём первое
+     * сообщение, чтобы рендерер работал с обоими форматами без сложных типов в контракте.
+     *
+     * @param string|list<string> $message
+     */
+    private function firstMessage(string|array $message): string
+    {
+        if (\is_array($message)) {
+            return $message[0] ?? '';
+        }
+        return $message;
+    }
 }
diff --git a/phpstan.neon b/phpstan.neon
index c67008e..28d008d 100644
--- a/phpstan.neon
+++ b/phpstan.neon
@@ -22,3 +22,11 @@ parameters:
             paths:
                 - app/src/Modules/User/Domain/ValueObject/UserName.php
                 - app/src/Modules/User/Domain/ValueObject/UserSpiritualName.php
+        # PSR-7 ServerRequestInterface::withAttribute объявляет первый параметр как $name, но
+        # реализации (Nyholm — $attribute) расходятся в имени, поэтому named arguments вызвали бы
+        # ошибку рантайма — здесь допустимы позиционные аргументы.
+        -
+            identifier: gianTiaga.phpstanStrictRules.namedArgumentsRequired
+            message: '#Calls with two or more ordinary arguments must use named arguments\.#'
+            paths:
+                - app/src/Modules/Auth/Presentation/Http/Middleware/AuthContextAttributeMiddleware.php
diff --git a/public/openapi/openapi.yml b/public/openapi/openapi.yml
index afa8f9f..5194689 100644
--- a/public/openapi/openapi.yml
+++ b/public/openapi/openapi.yml
@@ -6,6 +6,82 @@ servers:
   -
     url: /api/v1
 paths:
+  /auth/code/request:
+    post:
+      operationId: api_v1_auth_code_request
+      description: 'Запрос кода входа на email. Ответ всегда 200 (наличие пользователя не раскрываем).'
+      parameters: {  }
+      responses:
+        200:
+          description: 'Успешный ответ.'
+          content: { application/json: { schema: { type: object, properties: { data: { $ref: '#/components/schemas/RequestCodeResultResource' } }, required: [data] } } }
+        default:
+          description: 'Ошибка API.'
+          content: { application/json: { schema: { $ref: '#/components/schemas/ErrorResponse' } } }
+      requestBody:
+        required: true
+        content:
+          application/json: { schema: { type: object, properties: { email: { type: string } }, required: [email] } }
+  /auth/code/verify:
+    post:
+      operationId: api_v1_auth_code_verify
+      description: 'Проверка кода: пара токенов существующему пользователю или талон регистрации новому email.'
+      parameters: {  }
+      responses:
+        200:
+          description: 'Успешный ответ.'
+          content: { application/json: { schema: { type: object, properties: { data: { $ref: '#/components/schemas/VerifyResultResource' } }, required: [data] } } }
+        default:
+          description: 'Ошибка API.'
+          content: { application/json: { schema: { $ref: '#/components/schemas/ErrorResponse' } } }
+      requestBody:
+        required: true
+        content:
+          application/json: { schema: { type: object, properties: { email: { type: string }, code: { type: string } }, required: [email, code] } }
+  /auth/register:
+    post:
+      operationId: api_v1_auth_register
+      description: 'Завершение регистрации по талону: создаёт пользователя и выдаёт пару токенов.'
+      parameters: {  }
+      responses:
+        200:
+          description: 'Успешный ответ.'
+          content: { application/json: { schema: { type: object, properties: { data: { $ref: '#/components/schemas/TokenPairResource' } }, required: [data] } } }
+        default:
+          description: 'Ошибка API.'
+          content: { application/json: { schema: { $ref: '#/components/schemas/ErrorResponse' } } }
+      requestBody:
+        required: true
+        content:
+          application/json: { schema: { type: object, properties: { ticket: { type: string }, name: { type: string }, nickname: { type: string } }, required: [ticket, name, nickname] } }
+  /auth/refresh:
+    post:
+      operationId: api_v1_auth_refresh
+      description: 'Обновление пары токенов по refresh-токену (ротация).'
+      parameters: {  }
+      responses:
+        200:
+          description: 'Успешный ответ.'
+          content: { application/json: { schema: { type: object, properties: { data: { $ref: '#/components/schemas/TokenPairResource' } }, required: [data] } } }
+        default:
+          description: 'Ошибка API.'
+          content: { application/json: { schema: { $ref: '#/components/schemas/ErrorResponse' } } }
+      requestBody:
+        required: true
+        content:
+          application/json: { schema: { type: object, properties: { refreshToken: { type: string } }, required: [refreshToken] } }
+  /auth/logout:
+    post:
+      operationId: api_v1_auth_logout
+      description: 'Выход: отзыв всех токенов текущей сессии. Требует Bearer access-токен.'
+      parameters: {  }
+      responses:
+        200:
+          description: 'Успешный ответ.'
+          content: { application/json: { schema: { type: object, properties: { data: { $ref: '#/components/schemas/LogoutResultResource' } }, required: [data] } } }
+        default:
+          description: 'Ошибка API.'
+          content: { application/json: { schema: { $ref: '#/components/schemas/ErrorResponse' } } }
   /health:
     get:
       operationId: health
@@ -13,10 +89,10 @@ paths:
       parameters: {  }
       responses:
         200:
-          description: 'Successful response.'
+          description: 'Успешный ответ.'
           content: { application/json: { schema: { type: object, properties: { data: { $ref: '#/components/schemas/HealthResource' } }, required: [data] } } }
         default:
-          description: 'API error.'
+          description: 'Ошибка API.'
           content: { application/json: { schema: { $ref: '#/components/schemas/ErrorResponse' } } }
 components:
   schemas:
@@ -41,3 +117,46 @@ components:
       type: string
       enum:
         - ok
+    LogoutResultResource:
+      type: object
+      properties:
+        status:
+          type: string
+      required:
+        - status
+    RequestCodeResultResource:
+      type: object
+      properties:
+        status:
+          type: string
+      required:
+        - status
+    TokenPairResource:
+      type: object
+      properties:
+        accessToken:
+          type: string
+        refreshToken:
+          type: string
+        tokenType:
+          type: string
+        expiresIn:
+          type: integer
+      required:
+        - accessToken
+        - refreshToken
+        - tokenType
+        - expiresIn
+    VerifyResultResource:
+      type: object
+      properties:
+        needsProfile:
+          type: boolean
+        tokens:
+          $ref: '#/components/schemas/TokenPairResource'
+        registrationTicket:
+          type: string
+      required:
+        - needsProfile
+        - tokens
+        - registrationTicket

exec
/bin/zsh -lc "sed -n '96,180p' public/openapi/openapi.yml" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
          content: { application/json: { schema: { $ref: '#/components/schemas/ErrorResponse' } } }
components:
  schemas:
    ErrorResponse:
      type: object
      properties:
        message:
          type: string
        code:
          type: integer
          nullable: true
      required:
        - message
    HealthResource:
      type: object
      properties:
        status:
          $ref: '#/components/schemas/HealthStatus'
      required:
        - status
    HealthStatus:
      type: string
      enum:
        - ok
    LogoutResultResource:
      type: object
      properties:
        status:
          type: string
      required:
        - status
    RequestCodeResultResource:
      type: object
      properties:
        status:
          type: string
      required:
        - status
    TokenPairResource:
      type: object
      properties:
        accessToken:
          type: string
        refreshToken:
          type: string
        tokenType:
          type: string
        expiresIn:
          type: integer
      required:
        - accessToken
        - refreshToken
        - tokenType
        - expiresIn
    VerifyResultResource:
      type: object
      properties:
        needsProfile:
          type: boolean
        tokens:
          $ref: '#/components/schemas/TokenPairResource'
        registrationTicket:
          type: string
      required:
        - needsProfile
        - tokens
        - registrationTicket

exec
/bin/zsh -lc 'rg -n "parallel|concurrent|гонк|одного кода|две живые|refresh" tests/Feature/Modules/Auth docs/reviews/2026-06-15_16-07_auth-module.md docs/plans/2026-06-15_13-25_auth-module.md' in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
tests/Feature/Modules/Auth/Infrastructure/CycleTokenStorageTest.php:28:        self::assertNotSame($pair->accessToken, $pair->refreshToken);
tests/Feature/Modules/Auth/Infrastructure/CycleTokenStorageTest.php:95:        $rotatedPair = $this->storage()->rotate($pair->refreshToken);
tests/Feature/Modules/Auth/Infrastructure/CycleTokenStorageTest.php:98:        self::assertNotSame($pair->refreshToken, $rotatedPair->refreshToken);
tests/Feature/Modules/Auth/Infrastructure/CycleTokenStorageTest.php:99:        self::assertNull($this->storage()->load($pair->refreshToken));
tests/Feature/Modules/Auth/Infrastructure/CycleTokenStorageTest.php:142:        self::assertNull($this->storage()->load($pair->refreshToken));
tests/Feature/Modules/Auth/Console/AuthOpenApiGenerationTest.php:23:        self::assertStringContainsString('/auth/refresh:', $contents);
tests/Feature/Modules/Auth/Repository/AuthRepositoryTest.php:135:        $refreshToken = $this->authToken($userId, $sessionId, AuthTokenType::Refresh, 'refresh-raw', 5_184_000);
tests/Feature/Modules/Auth/Repository/AuthRepositoryTest.php:136:        $this->persist($accessToken, $refreshToken);
tests/Feature/Modules/Auth/Repository/AuthRepositoryTest.php:140:        $restoredRefresh = $this->authTokenRepository()->findByHashForUpdate(TokenHash::fromRawToken('refresh-raw'));
tests/Feature/Modules/Auth/Application/CompleteRegistrationHandlerTest.php:32:        self::assertNotSame('', $tokens->refreshToken);
docs/reviews/2026-06-15_16-07_auth-module.md:116:«незначительный перерасчёт при гонке». Но поведение отличается от того, что подразумевает
docs/reviews/2026-06-15_16-07_auth-module.md:190:`register` 10, `refresh` 20), но 429-ветка через HTTP проверяется только на `code/request`.
docs/reviews/2026-06-15_16-07_auth-module.md:194:middleware покрыт, а конкретная привязка к `verify`/`register`/`refresh` — нет.
docs/reviews/2026-06-15_16-07_auth-module.md:200:- **Где:** `app/.../Controller/AuthController.php:79,106,139` (лимиты на verify/register/refresh);
docs/reviews/2026-06-15_16-07_auth-module.md:209:  `refresh` (порог 20).
docs/plans/2026-06-15_13-25_auth-module.md:26:Готово, когда: 5 маршрутов (`code/request`, `code/verify`, `register`, `refresh`,
docs/plans/2026-06-15_13-25_auth-module.md:151:   контроллера и до открытия БД-транзакции. Незначительный перерасчёт при гонке допустим.
docs/plans/2026-06-15_13-25_auth-module.md:198:  можно повторить). UserId → issuePair(userId), run() → 200 {accessToken, refreshToken, tokenType,
docs/plans/2026-06-15_13-25_auth-module.md:201:ОБНОВЛЕНИЕ   POST /api/v1/auth/refresh {refreshToken}     [RefreshTokens #[Transactional]]
docs/plans/2026-06-15_13-25_auth-module.md:202:  AuthTokenStorage.rotate(refreshRaw): hash → find с forUpdate → type=refresh и не истёк
docs/plans/2026-06-15_13-25_auth-module.md:203:  (иначе 401) → delete пары по sessionId → issuePair нового sessionId → 200 {access, refresh, ...}.
docs/plans/2026-06-15_13-25_auth-module.md:214:  refresh-токен, присланный как Bearer, actor НЕ даёт (закрытая дыра).
docs/plans/2026-06-15_13-25_auth-module.md:252:  session_id  uuid           NOT NULL  index      -- общий id пары access+refresh
docs/plans/2026-06-15_13-25_auth-module.md:253:  type        string(16)     NOT NULL             -- access | refresh
docs/plans/2026-06-15_13-25_auth-module.md:255:  expires_at  datetime       NOT NULL             -- access: now+1ч; refresh: now+60д
docs/plans/2026-06-15_13-25_auth-module.md:288:  200   DataResponse<TokenPairResource{accessToken, refreshToken, tokenType:'Bearer', expiresIn:int}>
docs/plans/2026-06-15_13-25_auth-module.md:293:POST /api/v1/auth/refresh               middleware: RateLimitMiddleware
docs/plans/2026-06-15_13-25_auth-module.md:294:  body  {refreshToken: string}
docs/plans/2026-06-15_13-25_auth-module.md:296:  401   AuthenticationException (невалидный/истёкший refresh, не type=refresh)
docs/plans/2026-06-15_13-25_auth-module.md:355:- Enum `Auth/Domain/Enum/AuthTokenType` (`Access='access'`, `Refresh='refresh'`).
docs/plans/2026-06-15_13-25_auth-module.md:400:  (`issuePair(UserId):IssuedTokenPair`, `rotate(string $refreshRaw):IssuedTokenPair`,
docs/plans/2026-06-15_13-25_auth-module.md:402:- `Auth/Application/Dto/IssuedTokenPair{accessToken, refreshToken, expiresIn:int}`.
docs/plans/2026-06-15_13-25_auth-module.md:417:  - `issuePair(UserId)` — `SessionId::generate`, `create` access (TTL 1ч) + refresh (TTL 60д)
docs/plans/2026-06-15_13-25_auth-module.md:419:  - `rotate(refreshRaw)` — `findByHashForUpdate`, проверка type=refresh и срока (иначе
docs/plans/2026-06-15_13-25_auth-module.md:426:Результат: токены выдаются/проверяются/ротируются/отзываются; refresh как Bearer не даёт актора;
docs/plans/2026-06-15_13-25_auth-module.md:431:refresh→null для актора)/`rotate`(старая пара удалена, новая выдана, старый refresh→null)/
docs/plans/2026-06-15_13-25_auth-module.md:433:refresh→null).
docs/plans/2026-06-15_13-25_auth-module.md:462:- `RefreshTokens` (`#[Transactional]`) — `AuthTokenStorage::rotate(refreshToken)`.
docs/plans/2026-06-15_13-25_auth-module.md:474:register (успех Active; талон истёк → 401; ник занят → 422 и талон не погашен); refresh (успех;
docs/plans/2026-06-15_13-25_auth-module.md:493:  `app.auth.invalid_ticket`, `app.auth.invalid_refresh`).
docs/plans/2026-06-15_13-25_auth-module.md:536:  `RefreshFilter{refreshToken}` (обязательные поля без дефолтов, `#[Assert\NotBlank]`,
docs/plans/2026-06-15_13-25_auth-module.md:543:  `refresh` несут per-route `middleware: [new Autowire(RateLimitMiddleware::class, [...])]`;
docs/plans/2026-06-15_13-25_auth-module.md:590:- `refresh`: валидный refresh → новая пара, старый refresh больше не работает (401);
docs/plans/2026-06-15_13-25_auth-module.md:591:  access как refresh → 401.
docs/plans/2026-06-15_13-25_auth-module.md:593:  без токена → 401; refresh как Bearer → 401; повторный logout тем же access → 401.
docs/plans/2026-06-15_13-25_auth-module.md:602:- гонки (best-effort, через подготовленное состояние БД/forUpdate): повторный verify одного
docs/plans/2026-06-15_13-25_auth-module.md:603:  кода не проходит дважды; параллельный refresh не выдаёт две живые пары; при отсутствии
docs/plans/2026-06-15_13-25_auth-module.md:645:- Принятые риски: при точной гонке двух первых `code/request` на новый email возможны два
docs/plans/2026-06-15_13-25_auth-module.md:725:(сломал бы контракт «code/request всегда 200» ради крайне редкой гонки; вместо этого verify
tests/Feature/Modules/Auth/Http/AuthHttpTest.php:171:        $this->http()->postJson('/api/v1/auth/refresh', ['refreshToken' => $pair->refreshToken])->assertOk();
tests/Feature/Modules/Auth/Http/AuthHttpTest.php:172:        $this->http()->postJson('/api/v1/auth/refresh', ['refreshToken' => $pair->refreshToken])->assertUnauthorized();
tests/Feature/Modules/Auth/Http/AuthHttpTest.php:179:        $this->http()->postJson('/api/v1/auth/refresh', ['refreshToken' => $pair->accessToken])->assertUnauthorized();
tests/Feature/Modules/Auth/Http/AuthHttpTest.php:207:            ->withAuthorizationToken($pair->refreshToken)
tests/Feature/Modules/Auth/Application/RefreshTokensAndLogoutTest.php:22:        $newPair = $this->refreshHandler()->handle(new RefreshTokensCommand(refreshToken: $pair->refreshToken));
tests/Feature/Modules/Auth/Application/RefreshTokensAndLogoutTest.php:24:        self::assertNotSame($pair->refreshToken, $newPair->refreshToken);
tests/Feature/Modules/Auth/Application/RefreshTokensAndLogoutTest.php:26:        self::assertNull($this->tokenStorage()->load($pair->refreshToken));
tests/Feature/Modules/Auth/Application/RefreshTokensAndLogoutTest.php:34:        $this->refreshHandler()->handle(new RefreshTokensCommand(refreshToken: 'invalid-token'));
tests/Feature/Modules/Auth/Application/RefreshTokensAndLogoutTest.php:46:        self::assertNull($this->tokenStorage()->load($pair->refreshToken));
tests/Feature/Modules/Auth/Application/RefreshTokensAndLogoutTest.php:49:    private function refreshHandler(): RefreshTokensHandler

codex
Ещё один пропуск по плану: OpenAPI-файл сгенерирован, но схема `VerifyResultResource` не помечает nullable-поля как nullable, хотя один из них всегда `null` в каждом варианте ответа. Это надо добавить в мета-ревью как проблему API-контракта, а не как мелкую правку документации.
exec
/bin/zsh -lc 'nl -ba app/src/Modules/Auth/Presentation/Http/Filter/RequestCodeFilter.php && nl -ba app/src/Modules/Auth/Presentation/Http/Filter/VerifyCodeFilter.php && nl -ba app/src/Modules/Auth/Presentation/Http/Filter/RegisterFilter.php' in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Auth\Presentation\Http\Filter;
     6	
     7	use Spiral\Filters\Attribute\Input\Post;
     8	use Spiral\Validation\Symfony\AttributesFilter;
     9	use Symfony\Component\Validator\Constraints as Assert;
    10	
    11	final class RequestCodeFilter extends AttributesFilter
    12	{
    13	    #[Post]
    14	    #[Assert\NotBlank]
    15	    public string $email;
    16	}
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Auth\Presentation\Http\Filter;
     6	
     7	use Spiral\Filters\Attribute\Input\Post;
     8	use Spiral\Validation\Symfony\AttributesFilter;
     9	use Symfony\Component\Validator\Constraints as Assert;
    10	
    11	final class VerifyCodeFilter extends AttributesFilter
    12	{
    13	    #[Post]
    14	    #[Assert\NotBlank]
    15	    public string $email;
    16	
    17	    #[Post]
    18	    #[Assert\NotBlank]
    19	    #[Assert\Regex(pattern: '/^\d{6}$/')]
    20	    public string $code;
    21	}
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Auth\Presentation\Http\Filter;
     6	
     7	use Spiral\Filters\Attribute\Input\Post;
     8	use Spiral\Validation\Symfony\AttributesFilter;
     9	use Symfony\Component\Validator\Constraints as Assert;
    10	
    11	final class RegisterFilter extends AttributesFilter
    12	{
    13	    #[Post]
    14	    #[Assert\NotBlank]
    15	    public string $ticket;
    16	
    17	    #[Post]
    18	    #[Assert\NotBlank]
    19	    public string $name;
    20	
    21	    #[Post]
    22	    #[Assert\NotBlank]
    23	    public string $nickname;
    24	}

exec
/bin/zsh -lc "nl -ba docs/reviews/2026-06-15_16-07_auth-module.md | sed -n '80,230p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
    80	Это прямое нарушение правила «нет мёртвого кода». Дополнительная тонкость: класс
    81	покрыт отдельными unit-тестами, поэтому метрика 100% покрытия его не подсвечивает —
    82	мёртвый код выглядит «живым» в отчёте о покрытии. Риск в том, что такой класс
    83	накапливает ложное доверие: его правят, поддерживают и считают частью контракта,
    84	хотя удаление ничего в продукте не изменит.
    85	
    86	Технические детали:
    87	
    88	- **Тип:** `rules`
    89	- **Рекомендация:** `править обязательно`
    90	- **Где:** `app/src/Modules/Auth/Domain/ValueObject/LoginCodeValue.php`; единственные ссылки —
    91	  `tests/Unit/Modules/Auth/Domain/ValueObject/AuthValueObjectTest.php:149,161`; в `app/` —
    92	  только определение класса (подтверждено grep по `app/` и `tests/`).
    93	- **Что подтверждает проблему:** `VerifyCodeFilter` валидирует код регуляркой
    94	  (`app/.../Filter/VerifyCodeFilter.php:19`), `ResolveLoginCodeHandler` передаёт
    95	  `$command->code` строкой в `secretHasher->verify(...)`
    96	  (`app/.../ResolveLoginCode/ResolveLoginCodeHandler.php:63`) — `LoginCodeValue` нигде не
    97	  инстанцируется в продакшен-коде.
    98	- **Как исправить:** либо удалить `LoginCodeValue` и его unit-тест (валидация формата уже
    99	  закрыта фильтром), либо реально завести VO в поток: строить `LoginCodeValue::fromString(
   100	  $command->code)` в хендлере и сравнивать через его метод. Предпочтительно первое — фильтр
   101	  уже отвечает за формат на границе, отдельный доменный VO здесь не несёт нового смысла.
   102	- **Тесты:** при удалении класса убрать `testLoginCodeValueAcceptsSixDigits` и
   103	  `testLoginCodeValueRejectsInvalidFormat`; покрытие останется 100% без них.
   104	
   105	### 2. Окно rate limit продлевается на каждом запросе и фактически не «фиксированное»
   106	
   107	Middleware на каждом допущенном запросе вызывает `set(key, attempts+1, ttl: perSeconds)`,
   108	то есть заново выставляет TTL ключа. Из-за этого окно не истекает через `perSeconds`
   109	от первого запроса, а живёт, пока к маршруту вообще идут запросы. Клиент, который
   110	стучится чуть реже лимита (например, раз в 59 секунд при окне 60с), будет накапливать
   111	счётчик неограниченно долго и в итоге упрётся в лимит, хотя в любом отдельном
   112	60-секундном окне он лимит не превышал. Это не фиксированное окно, а почти скользящее
   113	с продлением.
   114	
   115	На практике для защиты от перебора это даже безопаснее (строже), и план явно принял
   116	«незначительный перерасчёт при гонке». Но поведение отличается от того, что подразумевает
   117	название «фиксированное окно», и при настройке лимитов это легко упустить. Стоит
   118	зафиксировать осознанно, чтобы потом не считать это багом.
   119	
   120	Технические детали:
   121	
   122	- **Тип:** `quality`
   123	- **Рекомендация:** `на усмотрение автора`
   124	- **Где:** `app/src/Shared/Infrastructure/Framework/Middleware/RateLimitMiddleware.php:46` —
   125	  `set(... ttl: $this->perSeconds)` на каждом проходе.
   126	- **Что подтверждает проблему:** TTL переустанавливается при каждом инкременте, значит ключ
   127	  не истекает по таймеру первого запроса; журнал это отмечает как принятую реализацию
   128	  (`docs/executions/...:35`).
   129	- **Как исправить:** если нужно именно фиксированное окно — выставлять TTL только при первом
   130	  инкременте (когда ключа ещё нет), а далее инкрементить без сброса TTL. Если поведение
   131	  устраивает — поправить docblock middleware, убрав слово «фиксированного окна», чтобы
   132	  название не вводило в заблуждение.
   133	- **Тесты:** при переходе на TTL-only-on-first добавить тест: серия запросов под лимитом с
   134	  «прокруткой» окна не блокирует на границе perSeconds.
   135	
   136	### 3. `requestLocale` уходит в `Locale::from()` без защиты на границе домена
   137	
   138	Локаль запроса прокидывается строкой из контроллера (`$translator->getLocale()`) через
   139	команду до `CreateUser`, где превращается в enum `Locale::from($command->locale)`.
   140	Сейчас это безопасно: глобальный `LocaleMiddleware` гарантирует, что в translator стоит
   141	только значение из белого списка (`ru`/`en`). Но защита держится на одном внешнем
   142	инварианте: если регистрацию когда-нибудь вызовут не из HTTP-потока (консоль, очередь,
   143	будущий внутренний сценарий) или middleware изменят, `Locale::from()` бросит
   144	непойманный `\ValueError` — это 500 вместо понятной ошибки.
   145	
   146	Технические детали:
   147	
   148	- **Тип:** `quality`
   149	- **Рекомендация:** `на усмотрение автора`
   150	- **Где:** `app/src/Modules/User/Application/Command/User/CreateUser/CreateUserHandler.php:51`
   151	  (`Locale::from($command->locale)`); строка приходит из `AuthController::register`
   152	  (`requestLocale: $translator->getLocale()`) через `CompleteRegistrationHandler`.
   153	- **Что подтверждает проблему:** `Locale::from()` (а не `tryFrom()`) на внешней строке без
   154	  предварительной нормализации; корректность опирается на `LocaleMiddleware`.
   155	- **Как исправить:** нормализовать локаль на входе use-case-а так же, как это делает
   156	  `SendLoginCodeHandler::resolveLocale()` (fallback на `LocaleConfig.default` при
   157	  неподдерживаемом значении), либо явно задокументировать, что `requestLocale` — всегда
   158	  значение из белого списка, и это предусловие команды.
   159	- **Тесты:** при добавлении нормализации — тест `CreateUser` с неподдерживаемой локалью →
   160	  fallback вместо исключения.
   161	
   162	### 4. Прикладная задача правит переносимый пакет `spiral-api-errors`
   163	
   164	В рамках работы по модулю Auth изменён рендерер ошибок в `packages/spiral-api-errors` —
   165	он научился принимать сообщения поля и строкой, и списком. Причина реальная: это первый
   166	модуль с Symfony-`Assert`-констрейнтами, и без правки валидация фильтра отдавала 500
   167	вместо 422. Но изменение затрагивает общий переносимый пакет, который по архитектуре —
   168	самостоятельный Composer-пакет с собственным контрактом и тестами. Любой потребитель
   169	пакета (в т.ч. вне этого репозитория) получает изменение поведения рендерера, а ревью
   170	прикладного модуля легко пропустит это как «деталь Auth».
   171	
   172	Технические детали:
   173	
   174	- **Тип:** `architecture`
   175	- **Рекомендация:** `на усмотрение автора`
   176	- **Где:** `packages/spiral-api-errors/src/Filter/ApiValidationErrorsRenderer.php:34-50` —
   177	  новый метод `firstMessage(string|array)`.
   178	- **Что подтверждает проблему:** изменение публичного поведения пакета сделано из задачи по
   179	  модулю Auth; контракт `array<string,string>` формально сохранён, журнал отмечает прогон
   180	  пакетных phpstan/тестов (`docs/executions/...:33`).
   181	- **Как исправить:** оставить правку, но убедиться, что у пакета есть собственный тест на новый
   182	  формат (список сообщений) внутри `packages/spiral-api-errors`, а не только косвенное покрытие
   183	  через app-тесты Auth; и зафиксировать изменение в истории/коммите как правку пакета, а не
   184	  как часть модуля Auth.
   185	- **Тесты:** unit-тест рендерера в пакете на вход `list<string>` и на вход `string` (если ещё нет).
   186	
   187	### 5. Rate limit интеграционно проверен только на одном из четырёх маршрутов с лимитом
   188	
   189	Лимиты заданы на четырёх маршрутах с разными порогами (`code/request` 5, `code/verify` 10,
   190	`register` 10, `refresh` 20), но 429-ветка через HTTP проверяется только на `code/request`.
   191	Линейное покрытие 100% это не подсвечивает, потому что код middleware один и тот же на всех
   192	маршрутах. Риск — регрессия в навешивании middleware или в параметрах конкретного маршрута
   193	(опечатка в `maxAttempts`/`perSeconds`, забытый middleware) пройдёт незамеченной: класс
   194	middleware покрыт, а конкретная привязка к `verify`/`register`/`refresh` — нет.
   195	
   196	Технические детали:
   197	
   198	- **Тип:** `tests`
   199	- **Рекомендация:** `на усмотрение автора`
   200	- **Где:** `app/.../Controller/AuthController.php:79,106,139` (лимиты на verify/register/refresh);
   201	  HTTP-тест 429 — только `testRateLimitReturns429AfterExceedingAttempts` на `code/request`
   202	  (`tests/Feature/Modules/Auth/Http/AuthHttpTest.php`).
   203	- **Что подтверждает проблему:** план требует «каждый роут покрыт интеграционным тестом» — сам
   204	  маршрут покрыт, но вариативность лимита per-route интеграционно не проверена.
   205	- **Как исправить:** добавить по одному 429-кейсу на остальные маршруты с лимитом (минимум на
   206	  один из них с другим порогом), чтобы зафиксировать, что middleware навешен и параметры
   207	  считываются именно для этого маршрута.
   208	- **Тесты:** добавить в `AuthHttpTest` кейс превышения лимита на `code/verify` (порог 10) или
   209	  `refresh` (порог 20).
   210	
   211	## Рекомендации
   212	
   213	- **Править обязательно:** 1 (раздел «Замечания» — мёртвый `LoginCodeValue`).
   214	- **На усмотрение автора:** план №1, план №2; замечания 2, 3, 4, 5.
   215	
   216	## Изменения после мета-ревью
   217	
   218	Мета-ревью не запускалось (режим draft).

exec
/bin/zsh -lc "nl -ba packages/spiral-api-errors/src/Filter/ApiValidationErrorsRenderer.php | sed -n '24,60p' && nl -ba packages/spiral-api-errors/tests/Filter/ApiValidationErrorsRendererTest.php | sed -n '1,80p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
    24	        return (new ValidationErrorResponse(message: $this->translator->trans(id: 'gian_tiaga.spiral_api_errors.validation_error'), code: HttpStatus::UnprocessableEntity->value, errors: $this->validationErrors($errors)))->withStatus(HttpStatus::UnprocessableEntity)->toResponse();
    25	    }
    26	    /**
    27	     * @param array<string, string> $errors
    28	     * @return list<ValidationErrorItemResponse>
    29	     */
    30	    private function validationErrors(array $errors): array
    31	    {
    32	        $validationErrors = [];
    33	        foreach ($errors as $field => $message) {
    34	            $validationErrors[] = new ValidationErrorItemResponse(field: $field, message: $this->firstMessage($message));
    35	        }
    36	        return $validationErrors;
    37	    }
    38	    /**
    39	     * Symfony-валидатор отдаёт сообщения поля списком, а casting-ошибки — строкой. Берём первое
    40	     * сообщение, чтобы рендерер работал с обоими форматами без сложных типов в контракте.
    41	     *
    42	     * @param string|list<string> $message
    43	     */
    44	    private function firstMessage(string|array $message): string
    45	    {
    46	        if (\is_array($message)) {
    47	            return $message[0] ?? '';
    48	        }
    49	        return $message;
    50	    }
    51	}
     1	<?php
     2	
     3	declare (strict_types=1);
     4	
     5	namespace GianTiaga\SpiralApiErrors\Tests\Filter;
     6	
     7	use PHPUnit\Framework\TestCase;
     8	use Psr\Log\NullLogger;
     9	use GianTiaga\SpiralApiErrors\Filter\ApiValidationErrorsRenderer;
    10	use GianTiaga\SpiralApiErrors\Tests\Support\FakeTranslator;
    11	use GianTiaga\SpiralOpenApi\Response\Enum\ContentType;
    12	use GianTiaga\SpiralOpenApi\Response\Enum\HttpHeader;
    13	use GianTiaga\SpiralOpenApi\Response\Enum\HttpStatus;
    14	
    15	final class ApiValidationErrorsRendererTest extends TestCase
    16	{
    17	    public function testRendererReturnsEnglishValidationErrorResponse(): void
    18	    {
    19	        $this->assertValidationErrorResponse(translator: self::englishTranslator(), expectedBody: '{"message":"Validation error","code":422,"errors":[{"field":"email","message":"Некорректный email"},{"field":"password","message":"Пароль обязателен"}]}');
    20	    }
    21	    public function testRendererReturnsRussianValidationErrorResponse(): void
    22	    {
    23	        $this->assertValidationErrorResponse(translator: new FakeTranslator(locale: 'ru', messages: ['gian_tiaga.spiral_api_errors.validation_error' => 'Ошибка валидации']), expectedBody: '{"message":"Ошибка валидации","code":422,"errors":[{"field":"email","message":"Некорректный email"},{"field":"password","message":"Пароль обязателен"}]}');
    24	    }
    25	    private function assertValidationErrorResponse(FakeTranslator $translator, string $expectedBody): void
    26	    {
    27	        $response = (new ApiValidationErrorsRenderer(logger: new NullLogger(), translator: $translator))->render(['email' => 'Некорректный email', 'password' => 'Пароль обязателен']);
    28	        self::assertSame(HttpStatus::UnprocessableEntity->value, $response->getStatusCode());
    29	        self::assertSame(ContentType::Json->value, $response->getHeaderLine(HttpHeader::ContentType->value));
    30	        self::assertSame($expectedBody, (string) $response->getBody());
    31	    }
    32	    private static function englishTranslator(): FakeTranslator
    33	    {
    34	        return new FakeTranslator(locale: 'en', messages: ['gian_tiaga.spiral_api_errors.validation_error' => 'Validation error']);
    35	    }
    36	}

exec
/bin/zsh -lc "nl -ba tests/Feature/Modules/Auth/Http/AuthHttpTest.php | sed -n '54,225p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc "nl -ba public/openapi/openapi.yml | sed -n '136,170p' && nl -ba app/src/Modules/Auth/Presentation/Http/Resource/VerifyResultResource.php | sed -n '1,80p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
    54	        $response = $this->http()->postJson('/api/v1/auth/code/request', ['email' => 'request@example.com']);
    55	
    56	        $response->assertOk();
    57	        $response->assertBodyContains('"status":"sent"');
    58	        self::assertNotNull(
    59	            $this->loginCodeRepository()->findActiveByEmail(EmailAddress::fromString('request@example.com')),
    60	        );
    61	    }
    62	
    63	    public function testRequestCodeRejectsEmptyEmail(): void
    64	    {
    65	        $this->http()->postJson('/api/v1/auth/code/request', ['email' => ''])->assertUnprocessable();
    66	    }
    67	
    68	    public function testVerifyExistingUserReturnsTokens(): void
    69	    {
    70	        $this->seedActiveUser('verify-existing@example.com', 'verify.existing');
    71	        $this->seedLoginCode('verify-existing@example.com', '123456');
    72	
    73	        $response = $this->http()->postJson('/api/v1/auth/code/verify', [
    74	            'email' => 'verify-existing@example.com',
    75	            'code' => '123456',
    76	        ]);
    77	
    78	        $response->assertOk();
    79	        $response->assertBodyContains('"needsProfile":false');
    80	        $response->assertBodyContains('accessToken');
    81	    }
    82	
    83	    public function testVerifyNewEmailReturnsRegistrationTicket(): void
    84	    {
    85	        $this->seedLoginCode('verify-new@example.com', '123456');
    86	
    87	        $response = $this->http()->postJson('/api/v1/auth/code/verify', [
    88	            'email' => 'verify-new@example.com',
    89	            'code' => '123456',
    90	        ]);
    91	
    92	        $response->assertOk();
    93	        $response->assertBodyContains('"needsProfile":true');
    94	        $response->assertBodyContains('registrationTicket');
    95	    }
    96	
    97	    public function testVerifyWrongCodeReturns401AndIncrementsAttempts(): void
    98	    {
    99	        $this->seedLoginCode('verify-wrong@example.com', '123456');
   100	
   101	        $this->http()->postJson('/api/v1/auth/code/verify', [
   102	            'email' => 'verify-wrong@example.com',
   103	            'code' => '000000',
   104	        ])->assertUnauthorized();
   105	
   106	        $this->cleanOrmHeap();
   107	        $loginCode = $this->loginCodeRepository()->findActiveByEmail(EmailAddress::fromString('verify-wrong@example.com'));
   108	        self::assertInstanceOf(LoginCode::class, $loginCode);
   109	        self::assertSame(1, $loginCode->attempts->value());
   110	    }
   111	
   112	    public function testVerifyExhaustedAttemptsReturns401(): void
   113	    {
   114	        $this->seedLoginCode('verify-exhausted@example.com', '123456', failedAttempts: 5);
   115	
   116	        $this->http()->postJson('/api/v1/auth/code/verify', [
   117	            'email' => 'verify-exhausted@example.com',
   118	            'code' => '123456',
   119	        ])->assertUnauthorized();
   120	    }
   121	
   122	    public function testRegisterCreatesActiveUserAndReturnsTokens(): void
   123	    {
   124	        $this->seedRegistrationTicket('register@example.com', 'ticket-http-1');
   125	
   126	        $response = $this->http()->postJson('/api/v1/auth/register', [
   127	            'ticket' => 'ticket-http-1',
   128	            'name' => 'Имя Тест',
   129	            'nickname' => 'register.user',
   130	        ]);
   131	
   132	        $response->assertOk();
   133	        $response->assertBodyContains('accessToken');
   134	
   135	        $this->cleanOrmHeap();
   136	        $user = $this->userRepository()->findByEmail(Email::fromString('register@example.com'));
   137	        self::assertInstanceOf(User::class, $user);
   138	        self::assertTrue($user->isActive());
   139	    }
   140	
   141	    public function testRegisterRejectsTakenNicknameAndKeepsTicket(): void
   142	    {
   143	        $this->seedActiveUser('owner@example.com', 'occupied.nick');
   144	        $this->seedRegistrationTicket('register-taken@example.com', 'ticket-http-2');
   145	
   146	        $this->http()->postJson('/api/v1/auth/register', [
   147	            'ticket' => 'ticket-http-2',
   148	            'name' => 'Имя Тест',
   149	            'nickname' => 'occupied.nick',
   150	        ])->assertUnprocessable();
   151	
   152	        $this->cleanOrmHeap();
   153	        self::assertNotNull(
   154	            $this->registrationTicketRepositoryFindActive('ticket-http-2'),
   155	        );
   156	    }
   157	
   158	    public function testRegisterRejectsUnknownTicket(): void
   159	    {
   160	        $this->http()->postJson('/api/v1/auth/register', [
   161	            'ticket' => 'missing-ticket',
   162	            'name' => 'Имя Тест',
   163	            'nickname' => 'whoever.user',
   164	        ])->assertUnauthorized();
   165	    }
   166	
   167	    public function testRefreshReturnsNewPairAndInvalidatesOldRefresh(): void
   168	    {
   169	        $pair = $this->issueTokenPair();
   170	
   171	        $this->http()->postJson('/api/v1/auth/refresh', ['refreshToken' => $pair->refreshToken])->assertOk();
   172	        $this->http()->postJson('/api/v1/auth/refresh', ['refreshToken' => $pair->refreshToken])->assertUnauthorized();
   173	    }
   174	
   175	    public function testRefreshRejectsAccessTokenAsRefresh(): void
   176	    {
   177	        $pair = $this->issueTokenPair();
   178	
   179	        $this->http()->postJson('/api/v1/auth/refresh', ['refreshToken' => $pair->accessToken])->assertUnauthorized();
   180	    }
   181	
   182	    public function testLogoutRevokesSession(): void
   183	    {
   184	        $pair = $this->issueTokenPair();
   185	
   186	        $this->http()
   187	            ->withAuthorizationToken($pair->accessToken)
   188	            ->postJson('/api/v1/auth/logout')
   189	            ->assertOk();
   190	
   191	        $this->http()
   192	            ->withAuthorizationToken($pair->accessToken)
   193	            ->postJson('/api/v1/auth/logout')
   194	            ->assertUnauthorized();
   195	    }
   196	
   197	    public function testLogoutWithoutTokenReturns401(): void
   198	    {
   199	        $this->http()->postJson('/api/v1/auth/logout')->assertUnauthorized();
   200	    }
   201	
   202	    public function testLogoutWithRefreshTokenAsBearerReturns401(): void
   203	    {
   204	        $pair = $this->issueTokenPair();
   205	
   206	        $this->http()
   207	            ->withAuthorizationToken($pair->refreshToken)
   208	            ->postJson('/api/v1/auth/logout')
   209	            ->assertUnauthorized();
   210	    }
   211	
   212	    public function testRateLimitReturns429AfterExceedingAttempts(): void
   213	    {
   214	        $http = $this->http();
   215	
   216	        for ($attempt = 1; $attempt <= 5; $attempt++) {
   217	            $http->postJson('/api/v1/auth/code/request', ['email' => 'rate-limit@example.com'])->assertOk();
   218	        }
   219	
   220	        $http->postJson('/api/v1/auth/code/request', ['email' => 'rate-limit@example.com'])->assertStatus(429);
   221	    }
   222	
   223	    public function testSystemHealthRouteStillWorks(): void
   224	    {
   225	        $this->http()->getJson('/api/v1/health')->assertOk();

 succeeded in 0ms:
   136	      properties:
   137	        accessToken:
   138	          type: string
   139	        refreshToken:
   140	          type: string
   141	        tokenType:
   142	          type: string
   143	        expiresIn:
   144	          type: integer
   145	      required:
   146	        - accessToken
   147	        - refreshToken
   148	        - tokenType
   149	        - expiresIn
   150	    VerifyResultResource:
   151	      type: object
   152	      properties:
   153	        needsProfile:
   154	          type: boolean
   155	        tokens:
   156	          $ref: '#/components/schemas/TokenPairResource'
   157	        registrationTicket:
   158	          type: string
   159	      required:
   160	        - needsProfile
   161	        - tokens
   162	        - registrationTicket
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Auth\Presentation\Http\Resource;
     6	
     7	use App\Modules\Auth\Application\Command\Auth\VerifyLoginCode\VerifyLoginCodeResult;
     8	use App\Shared\Presentation\Http\Resource\AbstractResource;
     9	
    10	final readonly class VerifyResultResource extends AbstractResource
    11	{
    12	    public function __construct(
    13	        public bool $needsProfile,
    14	        public TokenPairResource|null $tokens,
    15	        public string|null $registrationTicket,
    16	    ) {}
    17	
    18	    public static function fromResult(VerifyLoginCodeResult $result): self
    19	    {
    20	        return new self(
    21	            needsProfile: $result->needsProfile,
    22	            tokens: $result->tokens !== null ? TokenPairResource::fromPair($result->tokens) : null,
    23	            registrationTicket: $result->registrationTicket,
    24	        );
    25	    }
    26	}

exec
/bin/zsh -lc "nl -ba app/src/Modules/Auth/Domain/ValueObject/EmailAddress.php | sed -n '1,80p' && nl -ba app/src/Modules/User/Domain/ValueObject/UserName.php | sed -n '1,80p' && nl -ba app/src/Modules/User/Domain/ValueObject/UserNickname.php | sed -n '1,80p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc "nl -ba app/src/Modules/Auth/Domain/ValueObject/LoginCodeValue.php | sed -n '1,80p' && nl -ba app/src/Modules/Auth/Application/Command/Auth/ResolveLoginCode/ResolveLoginCodeHandler.php | sed -n '45,70p' && nl -ba app/src/Modules/Auth/Presentation/Http/Filter/VerifyCodeFilter.php | sed -n '12,24p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Auth\Domain\ValueObject;
     6	
     7	use App\Shared\Domain\Exception\InvalidDomainValueException;
     8	
     9	/**
    10	 * Сырой 6-значный код входа. Транзиентное значение: не хранится в БД (в БД лежит только
    11	 * SecretHash), используется только на границе ввода и для сверки хэша.
    12	 */
    13	final readonly class LoginCodeValue implements \Stringable, \JsonSerializable
    14	{
    15	    private function __construct(
    16	        private string $value,
    17	    ) {}
    18	
    19	    public static function fromString(string $value): self
    20	    {
    21	        if (\preg_match(pattern: '/^\d{6}$/', subject: $value) !== 1) {
    22	            throw new InvalidDomainValueException('Код должен состоять из 6 цифр.');
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
    33	    public function equals(self $code): bool
    34	    {
    35	        return \hash_equals(known_string: $this->value, user_string: $code->value);
    36	    }
    37	
    38	    #[\Override]
    39	    public function __toString(): string
    40	    {
    41	        return $this->value;
    42	    }
    43	
    44	    #[\Override]
    45	    public function jsonSerialize(): string
    46	    {
    47	        return $this->value;
    48	    }
    49	}
    45	    public function handle(ResolveLoginCodeCommand $command): LoginCodeResolution
    46	    {
    47	        $email = EmailAddress::fromString($command->email);
    48	        $now = new \DateTimeImmutable();
    49	        $loginCode = $this->loginCodeRepository->findActiveByEmailForUpdate($email);
    50	
    51	        if ($loginCode === null) {
    52	            return LoginCodeResolution::failed(LoginCodeOutcome::NoCode);
    53	        }
    54	
    55	        if ($loginCode->isExpired($now)) {
    56	            return LoginCodeResolution::failed(LoginCodeOutcome::Expired);
    57	        }
    58	
    59	        if ($loginCode->attemptsExhausted()) {
    60	            return LoginCodeResolution::failed(LoginCodeOutcome::Exhausted);
    61	        }
    62	
    63	        if (!$this->secretHasher->verify(secret: $command->code, hash: $loginCode->codeHash->value())) {
    64	            $loginCode->registerFailedAttempt($now);
    65	            $this->entityManager->persist($loginCode);
    66	            $this->entityManager->run();
    67	
    68	            return LoginCodeResolution::failed(LoginCodeOutcome::Wrong);
    69	        }
    70	
    12	{
    13	    #[Post]
    14	    #[Assert\NotBlank]
    15	    public string $email;
    16	
    17	    #[Post]
    18	    #[Assert\NotBlank]
    19	    #[Assert\Regex(pattern: '/^\d{6}$/')]
    20	    public string $code;
    21	}

 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Auth\Domain\ValueObject;
     6	
     7	use App\Shared\Domain\Exception\InvalidDomainValueException;
     8	
     9	/**
    10	 * Email модуля Auth. Нормализация ОБЯЗАНА быть побайтово идентична
    11	 * App\Modules\User\Domain\ValueObject\Email (mb_strtolower + trim, FILTER_VALIDATE_EMAIL,
    12	 * длина ≤ 254), чтобы ключи по email в auth_* и users совпадали. Эквивалентность
    13	 * нормализации покрыта тестом-инвариантом. Собственный VO нужен, потому что arch.md
    14	 * запрещает зависимость Domain одного модуля от Domain другого.
    15	 */
    16	final readonly class EmailAddress implements \Stringable, \JsonSerializable
    17	{
    18	    private const int MAX_LENGTH = 254;
    19	
    20	    private function __construct(
    21	        private string $value,
    22	    ) {}
    23	
    24	    public static function fromString(string $value): self
    25	    {
    26	        $email = \mb_strtolower(\trim($value));
    27	
    28	        if (
    29	            $email === ''
    30	            || \mb_strlen($email) > self::MAX_LENGTH
    31	            || !\filter_var(value: $email, filter: FILTER_VALIDATE_EMAIL)
    32	        ) {
    33	            throw new InvalidDomainValueException('Email имеет неверный формат.');
    34	        }
    35	
    36	        return new self(value: $email);
    37	    }
    38	
    39	    public function value(): string
    40	    {
    41	        return $this->value;
    42	    }
    43	
    44	    public function equals(self $email): bool
    45	    {
    46	        return $this->value === $email->value;
    47	    }
    48	
    49	    #[\Override]
    50	    public function __toString(): string
    51	    {
    52	        return $this->value;
    53	    }
    54	
    55	    #[\Override]
    56	    public function jsonSerialize(): string
    57	    {
    58	        return $this->value;
    59	    }
    60	}
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\User\Domain\ValueObject;
     6	
     7	use App\Shared\Domain\Exception\InvalidDomainValueException;
     8	
     9	final readonly class UserName implements \Stringable, \JsonSerializable
    10	{
    11	    private const int MAX_LENGTH = 100;
    12	
    13	    private function __construct(
    14	        private string $value,
    15	    ) {}
    16	
    17	    public static function fromString(string $value): self
    18	    {
    19	        $name = self::normalize($value);
    20	
    21	        if ($name === '' || \mb_strlen($name) > self::MAX_LENGTH) {
    22	            throw new InvalidDomainValueException('Имя имеет неверную длину.');
    23	        }
    24	
    25	        if (\preg_match(pattern: "/^[\\p{L}\\p{M}' -]+$/u", subject: $name) !== 1) {
    26	            throw new InvalidDomainValueException('Имя содержит недопустимые символы.');
    27	        }
    28	
    29	        return new self(value: $name);
    30	    }
    31	
    32	    public function value(): string
    33	    {
    34	        return $this->value;
    35	    }
    36	
    37	    public function equals(self $name): bool
    38	    {
    39	        return $this->value === $name->value;
    40	    }
    41	
    42	    #[\Override]
    43	    public function __toString(): string
    44	    {
    45	        return $this->value;
    46	    }
    47	
    48	    #[\Override]
    49	    public function jsonSerialize(): string
    50	    {
    51	        return $this->value;
    52	    }
    53	
    54	    private static function normalize(string $value): string
    55	    {
    56	        // Форма нормализации фиксируется явно (FORM_C). Имена параметров расходятся
    57	        // между нативным ext-intl ($string/$form) и стабом
    58	        // symfony/polyfill-intl-normalizer ($s/$form), поэтому аргументы позиционные,
    59	        // а named-arguments-правило для этой строки погашено в phpstan.neon.
    60	        $normalized = \Normalizer::normalize(\trim($value), \Normalizer::FORM_C);
    61	
    62	        if (!\is_string($normalized)) {
    63	            throw new InvalidDomainValueException('Имя не удалось нормализовать.');
    64	        }
    65	
    66	        return \preg_replace(pattern: '/\s+/u', replacement: ' ', subject: $normalized) ?? '';
    67	    }
    68	}
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\User\Domain\ValueObject;
     6	
     7	use App\Shared\Domain\Exception\InvalidDomainValueException;
     8	
     9	final readonly class UserNickname implements \Stringable, \JsonSerializable
    10	{
    11	    private function __construct(
    12	        private string $value,
    13	    ) {}
    14	
    15	    public static function fromString(string $value): self
    16	    {
    17	        $nickname = \mb_strtolower(\trim($value));
    18	
    19	        if (\preg_match(pattern: '/^[a-z0-9](?:[a-z0-9._-]{1,28})[a-z0-9]$/', subject: $nickname) !== 1) {
    20	            throw new InvalidDomainValueException('Никнейм имеет неверный формат.');
    21	        }
    22	
    23	        if (\str_contains(haystack: $nickname, needle: '..')) {
    24	            throw new InvalidDomainValueException('Никнейм не должен содержать две точки подряд.');
    25	        }
    26	
    27	        return new self(value: $nickname);
    28	    }
    29	
    30	    public function value(): string
    31	    {
    32	        return $this->value;
    33	    }
    34	
    35	    public function equals(self $nickname): bool
    36	    {
    37	        return $this->value === $nickname->value;
    38	    }
    39	
    40	    #[\Override]
    41	    public function __toString(): string
    42	    {
    43	        return $this->value;
    44	    }
    45	
    46	    #[\Override]
    47	    public function jsonSerialize(): string
    48	    {
    49	        return $this->value;
    50	    }
    51	}

exec
/bin/zsh -lc "nl -ba app/src/Shared/Infrastructure/Framework/Middleware/RateLimitMiddleware.php | sed -n '30,60p' && nl -ba docs/plans/2026-06-15_13-25_auth-module.md | sed -n '508,522p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
    30	        private CacheStorageProviderInterface $cacheStorageProvider,
    31	        private TranslatorInterface $translator,
    32	    ) {}
    33	
    34	    #[\Override]
    35	    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    36	    {
    37	        $cache = $this->cacheStorageProvider->storage(self::STORAGE);
    38	        $key = $this->counterKey($request);
    39	        $storedAttempts = $cache->get($key);
    40	        $currentAttempts = \is_int($storedAttempts) ? $storedAttempts : 0;
    41	
    42	        if ($currentAttempts >= $this->maxAttempts) {
    43	            return $this->tooManyRequestsResponse();
    44	        }
    45	
    46	        $cache->set(key: $key, value: $currentAttempts + 1, ttl: $this->perSeconds);
    47	
    48	        return $handler->handle($request);
    49	    }
    50	
    51	    private function counterKey(ServerRequestInterface $request): string
    52	    {
    53	        $remoteAddress = $request->getServerParams()['REMOTE_ADDR'] ?? '';
    54	        $clientIp = \is_string($remoteAddress) ? $remoteAddress : '';
    55	
    56	        return \hash(algo: 'sha256', data: $clientIp . '|' . $request->getUri()->getPath());
    57	    }
    58	
    59	    private function tooManyRequestsResponse(): ResponseInterface
    60	    {
   508	Что сделать:
   509	- `Shared/Infrastructure/Framework/Middleware/RateLimitMiddleware implements MiddlewareInterface`
   510	  — конструктор `(int $maxAttempts, int $perSeconds, CacheStorageProviderInterface, TranslatorInterface)`;
   511	  использует ИМЕНОВАННОЕ хранилище `redis` (`->storage('redis')`), а не дефолтный кэш (в dev
   512	  `CACHE_STORAGE=local`, иначе лимит был бы локальным на процесс); `process()`: ключ =
   513	  `sha256(clientIp . '|' . routeName)` (IP из `REMOTE_ADDR`; хэш, т.к. PSR-16 ключи не дружат с
   514	  `/`,`:`,IPv6); читает/инкрементит счётчик фиксированного окна (TTL=perSeconds на первом
   515	  инкременте). Превышение → возвращает `ErrorResponse(message: <trans app.shared.rate_limit_exceeded>,
   516	  code: 429)->toResponse()` (`HttpStatus::TooManyRequests`); иначе `$handler->handle()`.
   517	- Переводы `app/locale/{ru,en}/shared.php`: `app.shared.rate_limit_exceeded`.
   518	- Применение — per-route через `#[Route(middleware: [new Autowire(RateLimitMiddleware::class,
   519	  ['maxAttempts'=>N,'perSeconds'=>M])])]` (в фазе 7 на маршрутах Auth).
   520	
   521	Результат: маршрут с навешенным `RateLimitMiddleware` ограничивается по IP; превышение → 429 JSON.
   522	Общий `AppBootloader::INTERCEPTORS` не меняется (нет регрессии порядка интерсепторов).

codex
+ Добавить: HTTP-фильтры Auth не закрывают невалидный пользовательский ввод. `RequestCodeFilter` и `VerifyCodeFilter` проверяют email только на пустоту, а `RegisterFilter` так же проверяет только пустоту `name` и `nickname`. Дальше `EmailAddress`, `UserName`, `UserNickname` бросают `InvalidDomainValueException` с кодом 500. Для API это должно быть 422. Нужны тесты на плохой email, имя и ник.

+ Добавить: OpenAPI для `VerifyResultResource` неверный. В коде `tokens` и `registrationTicket` nullable, а в `public/openapi/openapi.yml` они обязательные и не nullable. Это ломает контракт для клиента. Тест `AuthOpenApiGenerationTest` проверяет только наличие маршрутов, но не схему.

+ Добавить: пакетный тест для `ApiValidationErrorsRenderer` на `list<string>` реально отсутствует. Сейчас тесты пакета проверяют только старый формат `array<string,string>`, хотя правка добавила поддержку массива сообщений.

+ Добавить: план требовал best-effort тесты гонок для повторного verify одного кода и параллельного refresh. По найденным тестам есть обычные последовательные проверки, но нет проверки гонки или хотя бы повторного успешного verify одного и того же кода.

− Убрать: фразу в пункте про rate limit, что план «явно принял» такое TTL-поведение. План прямо говорит: TTL на первом инкременте. Журнал принял отклонение, но это не равно выполнению плана.

− Убрать: «если ещё нет» в пункте про тест `ApiValidationErrorsRenderer`. Проверка показывает, что теста на список сообщений нет.

~ `LoginCodeValue`: пункт по сути корректный. Класс действительно мёртвый в production-коде: `ResolveLoginCodeHandler` передаёт строку напрямую в `SecretHasher::verify`, а `LoginCodeValue` используется только в unit-тесте. Но рекомендацию лучше переформулировать: с учётом плана предпочтительнее либо реально использовать VO в `ResolveLoginCode`, либо явно удалить его и зафиксировать отклонение от плана. Просто «лучше удалить» звучит слабее, чем нужно.

~ `RateLimitMiddleware`: факт про продление TTL корректный. Но это не просто `quality` и не мелкая документация: реализация расходится с планом фиксированного окна и может блокировать клиента, который не превышал лимит ни в одном отдельном 60-секундном окне. Лучше поднять до отклонения от плана + недостающего теста на истечение окна.

~ Пункт про переносимый пакет стоит усилить: проблема не только архитектурная. Раз пакет изменён, его собственные тесты должны покрывать новый формат ошибок. Это ближе к обязательному тестовому пробелу, чем к «на усмотрение».

~ Оценка 86 завышена. С учётом пропущенных 500 на невалидном вводе, неверной OpenAPI-схемы, неподтверждённого пакетного теста и спорного TTL rate limit я бы ставил примерно 80–82, пока эти пункты не закрыты.

? Не могу подтвердить зелёный `make test` / `make phpstan`, потому что по задаче делал проверку ревью и кода, а не запускал полный набор проверок.

? Не могу подтвердить фактическую защиту от гонок без конкурентного теста или запуска под реальной БД-нагрузкой. По коду есть `forUpdate`, но тестовое подтверждение из найденных файлов неполное.
tokens used
145 475
+ Добавить: HTTP-фильтры Auth не закрывают невалидный пользовательский ввод. `RequestCodeFilter` и `VerifyCodeFilter` проверяют email только на пустоту, а `RegisterFilter` так же проверяет только пустоту `name` и `nickname`. Дальше `EmailAddress`, `UserName`, `UserNickname` бросают `InvalidDomainValueException` с кодом 500. Для API это должно быть 422. Нужны тесты на плохой email, имя и ник.

+ Добавить: OpenAPI для `VerifyResultResource` неверный. В коде `tokens` и `registrationTicket` nullable, а в `public/openapi/openapi.yml` они обязательные и не nullable. Это ломает контракт для клиента. Тест `AuthOpenApiGenerationTest` проверяет только наличие маршрутов, но не схему.

+ Добавить: пакетный тест для `ApiValidationErrorsRenderer` на `list<string>` реально отсутствует. Сейчас тесты пакета проверяют только старый формат `array<string,string>`, хотя правка добавила поддержку массива сообщений.

+ Добавить: план требовал best-effort тесты гонок для повторного verify одного кода и параллельного refresh. По найденным тестам есть обычные последовательные проверки, но нет проверки гонки или хотя бы повторного успешного verify одного и того же кода.

− Убрать: фразу в пункте про rate limit, что план «явно принял» такое TTL-поведение. План прямо говорит: TTL на первом инкременте. Журнал принял отклонение, но это не равно выполнению плана.

− Убрать: «если ещё нет» в пункте про тест `ApiValidationErrorsRenderer`. Проверка показывает, что теста на список сообщений нет.

~ `LoginCodeValue`: пункт по сути корректный. Класс действительно мёртвый в production-коде: `ResolveLoginCodeHandler` передаёт строку напрямую в `SecretHasher::verify`, а `LoginCodeValue` используется только в unit-тесте. Но рекомендацию лучше переформулировать: с учётом плана предпочтительнее либо реально использовать VO в `ResolveLoginCode`, либо явно удалить его и зафиксировать отклонение от плана. Просто «лучше удалить» звучит слабее, чем нужно.

~ `RateLimitMiddleware`: факт про продление TTL корректный. Но это не просто `quality` и не мелкая документация: реализация расходится с планом фиксированного окна и может блокировать клиента, который не превышал лимит ни в одном отдельном 60-секундном окне. Лучше поднять до отклонения от плана + недостающего теста на истечение окна.

~ Пункт про переносимый пакет стоит усилить: проблема не только архитектурная. Раз пакет изменён, его собственные тесты должны покрывать новый формат ошибок. Это ближе к обязательному тестовому пробелу, чем к «на усмотрение».

~ Оценка 86 завышена. С учётом пропущенных 500 на невалидном вводе, неверной OpenAPI-схемы, неподтверждённого пакетного теста и спорного TTL rate limit я бы ставил примерно 80–82, пока эти пункты не закрыты.

? Не могу подтвердить зелёный `make test` / `make phpstan`, потому что по задаче делал проверку ревью и кода, а не запускал полный набор проверок.

? Не могу подтвердить фактическую защиту от гонок без конкурентного теста или запуска под реальной БД-нагрузкой. По коду есть `forUpdate`, но тестовое подтверждение из найденных файлов неполное.
