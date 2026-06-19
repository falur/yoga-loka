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
session id: 019ee0a3-d93d-7933-ad45-fdeef5c7dc9f
--------
user
Прочитай docs/reviews/2026-06-19_21-30_posts-application-presentation.md, его target (git diff HEAD + untracked-файлы Posts/User/Media/Tags), docs/plans/2026-06-18_17-42_posts-application-presentation.md, docs/rules.md, docs/arch.md. Затем глубоко проверь ревью по плану, правилам, архитектуре и релевантному коду: открой файлы, модули, API и тесты, которые следуют из diff и замечаний, и оцени фактическую корректность.

Это пятый (финальный) круг доводки, score 97/100. Особое внимание — возможным регрессиям от фиксов 4-го круга: новый app/src/Modules/Posts/Application/Post/MentionRecipientResolver.php (три режима requireAllExist/resolveRequired/resolveExisting), query app/src/Modules/User/Application/Query/CheckUsersExist/ + UserRepository::countByIds, guard requireAuthor() в PostViewAssembler/CommentViewAssembler, мягкая рассылка post_mention при публикации (PublishPostHandler + PostContentComposer::notifyPostMentions). Многие optional-замечания осознанно отклонены в прошлых кругах — не возвращай их без материально новых аргументов. Не подтверждай искусственных пунктов и не занижай оценку без обоснования.

Мета-ревью на русском: какие проблемы добавить, убрать, переформулировать; какие баги, риски для смежного кода и недостающие тесты пропущены. Не предлагай перечислять выполненную работу. Формат: '+', '−', '~', '?'.
codex
Использую `eda-review-check`, потому что задача именно на проверку готового ревью по плану, правилам, архитектуре и коду. Сначала прочитаю инструкции скилла, затем обязательные документы и сам target.
exec
/bin/zsh -lc "sed -n '1,240p' /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1/.codex/skills/eda-review-check/SKILL.md && sed -n '1,260p' docs/reviews/2026-06-19_21-30_posts-application-presentation.md && sed -n '1,260p' docs/plans/2026-06-18_17-42_posts-application-presentation.md && sed -n '1,260p' docs/rules.md && sed -n '1,260p' docs/arch.md && sed -n '1,220p' docs/code-examples.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
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
---
title: Модуль Posts/Tags/User — прикладной и презентационный слой (5-й круг, фокус на фиксах 4-го круга)
date: 2026-06-19 21:30
target: git diff HEAD + untracked
plan: docs/plans/2026-06-18_17-42_posts-application-presentation.md
mode: strict
score: 97
status: meta-reviewed
meta_reviewers: [plan-check (sonnet), architecture-check (sonnet), rules-check (sonnet), quality-check (sonnet)]
---

# Ревью: Модуль Posts/Tags/User — прикладной и презентационный слой (5-й круг)

## Оценка

**97/100.** Реализация плана полная, фиксы четвёртого круга корректны и не дали регрессий: новый
общий сервис разрешения упоминаний разведён по трём режимам осмысленно, дешёвая проверка
существования и guard автора покрыты тестами, мягкая рассылка при публикации не ломает прежнее
поведение и не дублирует уведомления. Новых реальных проблем не найдено; остаток до 100 — это
естественный потолок для большого изменения, прошедшего четыре круга, а не конкретный дефект, под
который стоило бы снижать оценку.

## Проблемы сверки с планом

Явных проблем по плану не найдено. План реализован по всем пяти фазам: 15 прикладных сценариев, 7
видов уведомлений, межмодульные публичные сценарии User/Media/Tags, конфиг User с дефолтным аватаром,
read-фильтры репозиториев, каждый HTTP-роут покрыт интеграционным тестом. Решения 1–27 соблюдены, в
том числе спорные (схема B, атомарная транзакция через SAVEPOINT, идемпотентность лайков/удаления,
дедуп уведомлений, лимиты во Filter, `Ready`-проверка медиа до перевода в permanent).

## Замечания

Явных проблем в коде не найдено.

Отдельно по запрошенным зонам фокуса (фиксы четвёртого круга) — проверено и подтверждено как
корректное, без снижения оценки:

- **`MentionRecipientResolver` и его три режима.** `requireAllExist` (черновик, без рассылки),
  `resolveRequired` (создание опубликованной записи и комментарии, строгая проверка полноты -> 422) и
  `resolveExisting` (мягкая рассылка по сохранённым упоминаниям при публикации) разведены по
  назначению и не пересекаются. Все вызывающие передают уже дедуплицированные списки, поэтому проверка
  полноты `count() !== count($userIds)` в `resolveRequired` не даёт ложного 422. Двойной рассылки нет:
  опубликованная запись уведомляет упоминания при создании, черновик — только при публикации, а
  `PublishPost` входит в ветку публикации лишь из статуса `Draft`, поэтому повторная публикация —
  no-op без второго уведомления.

- **`CheckUsersExist` / `UserRepository::countByIds`.** Дешёвая проверка существования согласована с
  «дорогим» путём: `User` использует обычную nullable-колонку `deleted_at` с собственным typecast, а
  не Cycle `SoftDelete` behavior, поэтому глобального скоупа `WHERE deleted_at IS NULL` нет — `count()`,
  `findByIds()` и `findById()` одинаково видят (или не видят) soft-deleted пользователей. Значит,
  валидация черновика (`countByIds`) и валидация опубликованной записи (`findByIds`) дадут один и тот
  же результат на одних и тех же данных — расхождения 422 между путями нет. Хэндлер и репозиторий
  корректно обрабатывают пустой вход и дедуплицируют идентификаторы.

- **Guard `requireAuthor` в ассемблерах.** Отсутствие автора в пакетной карте профилей теперь даёт ту
  же 404 (`app.user.not_found`), что и одиночный путь, вместо неконтролируемого обращения по
  несуществующему ключу массива -> 500. Поведение одиночного и пакетного путей приведено к единому
  контракту во всех трёх местах (`fromPosts`, `originalViewsByPost`, `fromComments`).

- **Мягкая рассылка при публикации (`notifyPostMentions` -> `resolveExisting`).** Читает сохранённые
  упоминания из своего репозитория, тихо пропускает исчезнувших получателей и не падает на строгой
  проверке полноты. Актор (публикующий владелец) существует по определению, поэтому строгий
  `profile()` для актора безопасен. Подтверждено интеграционным тестом
  `PublishPostHttpTest::testPublishingDraftNotifiesMentionedUsers` (0 уведомлений на черновике, ровно 1
  после публикации).

## Рекомендации

- **Править обязательно:** нет.
- **На усмотрение автора:** нет.

## Изменения после мета-ревью

### После plan-check / architecture-check / rules-check / quality-check

- **+ Добавлено:** ничего. Реальных новых проблем не подтверждено.
- **~ Изменено:** ничего.
- **− Убрано:** ничего.
- **Отклонено:**
  - «Добавить пункт: `MentionRecipientResolver::resolveRequired` не защищён от дублей изнутри —
    при будущем вызывающем без `array_unique` метод даст ложный 422; добавить
    `array_unique` в начало метода» (quality-check `+`, тип robustness, «на усмотрение») —
    отклонено. Проверено по коду: оба текущих вызывающих (`PostContentComposer:117` и
    `CommentComposer:43`) дедуплицируют список через `\array_values(\array_unique(...))` до
    вызова; контракт зафиксирован `@param list<string>`. Текущего дефекта нет, это
    спекулятивная защита под несуществующего будущего вызывающего — искусственный пункт для
    финального круга, ревью ведётся только по проблемам.
  - «Переформулировать тезис строки 42 (про дедуплицированные списки), т.к. он создаёт
    впечатление полного отсутствия проблемы» (quality-check `~`) — отклонено. Формулировка
    фактически верна и проверена по коду: оба вызывающих действительно передают
    дедуплицированные списки. Тезис не утверждает внутреннюю защиту метода и не вводит в
    заблуждение.
  - «`RepostPostHandler::handle()` ~47 строк, формально превышает ориентир ~40»
    (quality-check) — отклонено. Метод плотно структурирован, без вложенности, максимально
    делегирован в `PostContentComposer`; правило задаёт ориентир «~40», искусственное дробление
    ухудшит читаемость. Не дефект.
  - Англицизмы «стейджинг/стейджит/батч» в комментариях (rules-check) — не восстановлено:
    осознанно отклонено в 1-м круге как устоявшийся технический жаргон по всему проекту
    (Notifications/Outbox/Shared), материально новых аргументов нет.
- **plan-check запускался:** план указан в front matter (`docs/plans/2026-06-18_17-42_...`);
  все 5 фаз, 15 роутов схемы B, 7 видов уведомлений, межмодульные сценарии, конфиг User и
  read-фильтры репозиториев подтверждены реализованными; каждый HTTP-роут покрыт интеграционным
  тестом. Фиксы 4-го круга (три режима `MentionRecipientResolver`, `CheckUsersExist`/`countByIds`,
  guard `requireAuthor`, мягкая рассылка `post_mention` при публикации) проверены по коду всеми
  четырьмя ролями как корректные, без регрессий.
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
  research: интерактивные ответы пользователя (отдельный research-файл не создавался)
---

# План реализации

## Задача

Достроить два внешних слоя модуля `Posts` (`Application` + `Presentation`) поверх
готового и закоммиченного `Domain`/`Repository` (`2f3bc87`): инфраструктурный каркас
(папки, `PostsBootloader`, регистрация в `Kernel`), 15 прикладных сценариев (записи,
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

codex
Я прочитал скилл и основные документы. Дальше беру фактический diff и список новых файлов, затем отдельно открою классы, которые пользователь выделил как рискованные: resolver упоминаний, query проверки пользователей, сборщики view и публикацию уведомлений.
exec
/bin/zsh -lc 'git diff --stat HEAD' in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
 .env.sample                                        |     3 +
 .phpactor.json                                     |     4 +
 app/config/user.php                                |    14 +
 app/locale/en/posts.php                            |    27 +
 app/locale/en/user.php                             |     1 +
 app/locale/ru/posts.php                            |    27 +
 app/locale/ru/user.php                             |     1 +
 .../CheckMediaAttachableHandler.php                |    42 +
 .../CheckMediaAttachableQuery.php                  |    13 +
 .../CheckMediaAttachable/MediaAttachableResult.php |    15 +
 .../Query/FindMediaUrl/FindMediaUrlHandler.php     |    52 +
 .../Query/FindMediaUrl/FindMediaUrlQuery.php       |    13 +
 .../Command/CommentPost/CommentPostCommand.php     |    21 +
 .../Command/CommentPost/CommentPostHandler.php     |    73 +
 .../Command/CreatePost/CreatePostCommand.php       |    27 +
 .../Command/CreatePost/CreatePostHandler.php       |    69 +
 .../Command/DeleteComment/DeleteCommentCommand.php |    13 +
 .../Command/DeleteComment/DeleteCommentHandler.php |    88 +
 .../Command/DeletePost/DeletePostCommand.php       |    13 +
 .../Command/DeletePost/DeletePostHandler.php       |    65 +
 .../Command/LikeComment/LikeCommentCommand.php     |    13 +
 .../Command/LikeComment/LikeCommentHandler.php     |    66 +
 .../Command/LikePost/LikePostCommand.php           |    13 +
 .../Command/LikePost/LikePostHandler.php           |    68 +
 .../Command/PublishPost/PublishPostCommand.php     |    13 +
 .../Command/PublishPost/PublishPostHandler.php     |    61 +
 .../Command/ReplyComment/ReplyCommentCommand.php   |    21 +
 .../Command/ReplyComment/ReplyCommentHandler.php   |    83 +
 .../Command/RepostPost/RepostPostCommand.php       |    27 +
 .../Command/RepostPost/RepostPostHandler.php       |    92 +
 .../Command/UnlikeComment/UnlikeCommentCommand.php |    13 +
 .../Command/UnlikeComment/UnlikeCommentHandler.php |    56 +
 .../Command/UnlikePost/UnlikePostCommand.php       |    13 +
 .../Command/UnlikePost/UnlikePostHandler.php       |    56 +
 .../Notification/CommentNotificationTarget.php     |    20 +
 .../Notification/NotificationContentBuilder.php    |    69 +
 .../Notification/PostNotificationType.php          |    65 +
 .../Application/Notification/PostNotifier.php      |    56 +
 .../Posts/Application/Post/CommentComposer.php     |   105 +
 .../Posts/Application/Post/PostContentComposer.php |   213 +
 .../Application/Post/PostVisibilityPolicy.php      |    43 +
 .../GetCommentReplies/GetCommentRepliesHandler.php |    73 +
 .../GetCommentReplies/GetCommentRepliesQuery.php   |    15 +
 .../GetCommentReplies/GetCommentRepliesResult.php  |    21 +
 .../Application/Query/GetPost/GetPostHandler.php   |    40 +
 .../Application/Query/GetPost/GetPostQuery.php     |    13 +
 .../GetPostComments/GetPostCommentsHandler.php     |    64 +
 .../Query/GetPostComments/GetPostCommentsQuery.php |    15 +
 .../GetPostComments/GetPostCommentsResult.php      |    21 +
 .../Query/GetUserFeed/GetUserFeedHandler.php       |    59 +
 .../Query/GetUserFeed/GetUserFeedQuery.php         |    15 +
 .../Query/GetUserFeed/GetUserFeedResult.php        |    21 +
 .../Modules/Posts/Application/View/AuthorView.php  |    17 +
 .../Modules/Posts/Application/View/CommentView.php |    24 +
 .../Application/View/CommentViewAssembler.php      |   151 +
 .../Posts/Application/View/PostMediaItemView.php   |    17 +
 .../Modules/Posts/Application/View/PostView.php    |    32 +
 .../Posts/Application/View/PostViewAssembler.php   |   457 +
 app/src/Modules/Posts/Application/View/TagView.php |    16 +
 .../Infrastructure/Bootloader/PostsBootloader.php  |    23 +
 .../Http/Controller/CommentController.php          |   296 +
 .../Http/Controller/PostController.php             |   326 +
 .../Presentation/Http/Filter/CommentPostFilter.php |    40 +
 .../Presentation/Http/Filter/CreatePostFilter.php  |    53 +
 .../Http/Filter/DeleteCommentFilter.php            |    25 +
 .../Presentation/Http/Filter/DeletePostFilter.php  |    26 +
 .../Http/Filter/GetCommentRepliesFilter.php        |    35 +
 .../Http/Filter/GetPostCommentsFilter.php          |    35 +
 .../Presentation/Http/Filter/GetPostFilter.php     |    25 +
 .../Presentation/Http/Filter/GetUserFeedFilter.php |    35 +
 .../Presentation/Http/Filter/LikeCommentFilter.php |    25 +
 .../Presentation/Http/Filter/LikePostFilter.php    |    25 +
 .../Presentation/Http/Filter/PublishPostFilter.php |    26 +
 .../Http/Filter/ReplyCommentFilter.php             |    40 +
 .../Presentation/Http/Filter/RepostPostFilter.php  |    55 +
 .../Http/Filter/UnlikeCommentFilter.php            |    25 +
 .../Presentation/Http/Filter/UnlikePostFilter.php  |    25 +
 .../Presentation/Http/Resource/AuthorResource.php  |    26 +
 .../Presentation/Http/Resource/CommentResource.php |    38 +
 .../Http/Resource/PostMediaItemResource.php        |    26 +
 .../Presentation/Http/Resource/PostResource.php    |    58 +
 .../Presentation/Http/Resource/TagResource.php     |    21 +
 .../Posts/Repository/CommentLikeRepository.php     |    22 +
 .../Modules/Posts/Repository/CommentRepository.php |    25 +
 .../Posts/Repository/PostLikeRepository.php        |    21 +
 .../Posts/Repository/PostMediaRepository.php       |    23 +
 .../Modules/Posts/Repository/PostRepository.php    |    66 +
 .../Modules/Posts/Repository/PostTagRepository.php |    20 +
 .../Command/ResolveTags/ResolveTagsCommand.php     |    19 +
 .../Command/ResolveTags/ResolveTagsHandler.php     |    80 +
 .../Command/ResolveTags/ResolveTagsResult.php      |    18 +
 .../Tags/Application/Dto/TagTextCollection.php     |    16 +
 .../Application/Query/GetTags/GetTagsHandler.php   |    38 +
 .../Application/Query/GetTags/GetTagsQuery.php     |    18 +
 app/src/Modules/Tags/Repository/TagRepository.php  |    20 +
 .../Dto/UserPublicProfileCollection.php            |    12 +
 .../User/Application/Dto/UserPublicProfileView.php |    20 +
 .../Profile/UserPublicProfileAssembler.php         |    61 +
 .../GetUserPublicProfileHandler.php                |    32 +
 .../GetUserPublicProfileQuery.php                  |    12 +
 .../GetUserPublicProfilesHandler.php               |    46 +
 .../GetUserPublicProfilesQuery.php                 |    18 +
 .../User/Domain/Collection/UserCollection.php      |    13 +
 app/src/Modules/User/Repository/UserRepository.php |    32 +
 .../Configuration/User/UserConfig.php              |    30 +
 app/src/Shared/Infrastructure/Framework/Kernel.php |     2 +
 ...6-06-19_12-51_posts-application-presentation.md |    61 +
 ...6-06-18_17-42_posts-application-presentation.md |   567 +
 ...6-06-19_17-10_posts-application-presentation.md |    74 +
 ...6-06-19_19-10_posts-application-presentation.md |    79 +
 ...6-06-19_16-02_posts-application-presentation.md |   366 +
 ...6-02_posts-application-presentation_cli_meta.md |  8125 +++++++++++++++
 ...6-06-19_17-42_posts-application-presentation.md |   346 +
 ...6-06-19_18-30_posts-application-presentation.md |   309 +
 ...8-30_posts-application-presentation_cli_meta.md | 10267 +++++++++++++++++++
 docs/rules.md                                      |     1 +
 .../spiral-openapi/src/Parser/PhpAstParser.php     |    19 +-
 .../Endpoint/Api/V1/Resource/UserResource.php      |     5 +-
 .../tests/Generator/OpenApiGeneratorTest.php       |     5 +
 public/openapi/openapi.yml                         |   394 +
 .../CheckMediaAttachableHandlerTest.php            |    88 +
 .../Media/Application/FindMediaUrlHandlerTest.php  |    95 +
 .../Application/NotificationUseCaseTest.php        |     9 +-
 .../Notifications/Http/NotificationHttpTest.php    |    10 +-
 .../Feature/Modules/Posts/Http/CommentHttpTest.php |   289 +
 .../Modules/Posts/Http/CommentReactionHttpTest.php |   121 +
 .../Modules/Posts/Http/CreatePostHttpTest.php      |   228 +
 .../Modules/Posts/Http/DeletePostHttpTest.php      |    79 +
 .../Posts/Http/GetCommentRepliesHttpTest.php       |   121 +
 .../Modules/Posts/Http/GetPostCommentsHttpTest.php |   116 +
 .../Feature/Modules/Posts/Http/GetPostHttpTest.php |   171 +
 .../Modules/Posts/Http/GetUserFeedHttpTest.php     |   211 +
 .../Modules/Posts/Http/PostReactionHttpTest.php    |   109 +
 .../Modules/Posts/Http/PostsHttpTestCase.php       |   292 +
 .../Posts/Http/PostsOpenApiGenerationTest.php      |    47 +
 .../Modules/Posts/Http/PublishPostHttpTest.php     |    99 +
 .../Modules/Posts/Http/RepostPostHttpTest.php      |   114 +
 .../Posts/Repository/LikeBatchRepositoryTest.php   |    99 +
 .../Posts/Repository/PostMediaRepositoryTest.php   |    43 +
 .../Posts/Repository/PostRepositoryTest.php        |    61 +
 .../Posts/Repository/PostTagRepositoryTest.php     |    20 +
 .../Tags/Application/GetTagsHandlerTest.php        |    58 +
 .../Tags/Application/ResolveTagsHandlerTest.php    |    93 +
 .../Modules/Tags/Repository/TagRepositoryTest.php  |     5 +
 .../Modules/Tags/TagsApplicationTestCase.php       |    54 +
 .../GetUserPublicProfileHandlerTest.php            |    63 +
 .../GetUserPublicProfilesHandlerTest.php           |    47 +
 .../User/Application/UserApplicationTestCase.php   |   133 +
 .../Modules/User/Repository/UserRepositoryTest.php |    16 +
 .../NotificationContentBuilderTest.php             |   100 +
 .../Notification/PostNotificationTypesTest.php     |    61 +
 .../Configuration/ConfigShapeTest.php              |     1 +
 .../Configuration/UserConfigTest.php               |    64 +
 153 files changed, 28518 insertions(+), 8 deletions(-)

exec
/bin/zsh -lc 'git diff --name-only HEAD' in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
.env.sample
.phpactor.json
app/config/user.php
app/locale/en/posts.php
app/locale/en/user.php
app/locale/ru/posts.php
app/locale/ru/user.php
app/src/Modules/Media/Application/Query/CheckMediaAttachable/CheckMediaAttachableHandler.php
app/src/Modules/Media/Application/Query/CheckMediaAttachable/CheckMediaAttachableQuery.php
app/src/Modules/Media/Application/Query/CheckMediaAttachable/MediaAttachableResult.php
app/src/Modules/Media/Application/Query/FindMediaUrl/FindMediaUrlHandler.php
app/src/Modules/Media/Application/Query/FindMediaUrl/FindMediaUrlQuery.php
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
app/src/Modules/Posts/Infrastructure/Bootloader/PostsBootloader.php
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
app/src/Modules/Posts/Repository/CommentRepository.php
app/src/Modules/Posts/Repository/PostLikeRepository.php
app/src/Modules/Posts/Repository/PostMediaRepository.php
app/src/Modules/Posts/Repository/PostRepository.php
app/src/Modules/Posts/Repository/PostTagRepository.php
app/src/Modules/Tags/Application/Command/ResolveTags/ResolveTagsCommand.php
app/src/Modules/Tags/Application/Command/ResolveTags/ResolveTagsHandler.php
app/src/Modules/Tags/Application/Command/ResolveTags/ResolveTagsResult.php
app/src/Modules/Tags/Application/Dto/TagTextCollection.php
app/src/Modules/Tags/Application/Query/GetTags/GetTagsHandler.php
app/src/Modules/Tags/Application/Query/GetTags/GetTagsQuery.php
app/src/Modules/Tags/Repository/TagRepository.php
app/src/Modules/User/Application/Dto/UserPublicProfileCollection.php
app/src/Modules/User/Application/Dto/UserPublicProfileView.php
app/src/Modules/User/Application/Profile/UserPublicProfileAssembler.php
app/src/Modules/User/Application/Query/GetUserPublicProfile/GetUserPublicProfileHandler.php
app/src/Modules/User/Application/Query/GetUserPublicProfile/GetUserPublicProfileQuery.php
app/src/Modules/User/Application/Query/GetUserPublicProfiles/GetUserPublicProfilesHandler.php
app/src/Modules/User/Application/Query/GetUserPublicProfiles/GetUserPublicProfilesQuery.php
app/src/Modules/User/Domain/Collection/UserCollection.php
app/src/Modules/User/Repository/UserRepository.php
app/src/Shared/Infrastructure/Configuration/User/UserConfig.php
app/src/Shared/Infrastructure/Framework/Kernel.php
docs/executions/2026-06-19_12-51_posts-application-presentation.md
docs/plans/2026-06-18_17-42_posts-application-presentation.md
docs/review-fixes/2026-06-19_17-10_posts-application-presentation.md
docs/review-fixes/2026-06-19_19-10_posts-application-presentation.md
docs/reviews/2026-06-19_16-02_posts-application-presentation.md
docs/reviews/2026-06-19_16-02_posts-application-presentation_cli_meta.md
docs/reviews/2026-06-19_17-42_posts-application-presentation.md
docs/reviews/2026-06-19_18-30_posts-application-presentation.md
docs/reviews/2026-06-19_18-30_posts-application-presentation_cli_meta.md
docs/rules.md
packages/spiral-openapi/src/Parser/PhpAstParser.php
packages/spiral-openapi/tests/Fixtures/Endpoint/Api/V1/Resource/UserResource.php
packages/spiral-openapi/tests/Generator/OpenApiGeneratorTest.php
public/openapi/openapi.yml
tests/Feature/Modules/Media/Application/CheckMediaAttachableHandlerTest.php
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php
tests/Feature/Modules/Notifications/Application/NotificationUseCaseTest.php
tests/Feature/Modules/Notifications/Http/NotificationHttpTest.php
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
tests/Feature/Modules/Posts/Http/PostsOpenApiGenerationTest.php
tests/Feature/Modules/Posts/Http/PublishPostHttpTest.php
tests/Feature/Modules/Posts/Http/RepostPostHttpTest.php
tests/Feature/Modules/Posts/Repository/LikeBatchRepositoryTest.php
tests/Feature/Modules/Posts/Repository/PostMediaRepositoryTest.php
tests/Feature/Modules/Posts/Repository/PostRepositoryTest.php
tests/Feature/Modules/Posts/Repository/PostTagRepositoryTest.php
tests/Feature/Modules/Tags/Application/GetTagsHandlerTest.php
tests/Feature/Modules/Tags/Application/ResolveTagsHandlerTest.php
tests/Feature/Modules/Tags/Repository/TagRepositoryTest.php
tests/Feature/Modules/Tags/TagsApplicationTestCase.php
tests/Feature/Modules/User/Application/GetUserPublicProfileHandlerTest.php
tests/Feature/Modules/User/Application/GetUserPublicProfilesHandlerTest.php
tests/Feature/Modules/User/Application/UserApplicationTestCase.php
tests/Feature/Modules/User/Repository/UserRepositoryTest.php
tests/Kernel/Modules/Posts/Notification/NotificationContentBuilderTest.php
tests/Kernel/Modules/Posts/Notification/PostNotificationTypesTest.php
tests/Kernel/Shared/Infrastructure/Configuration/ConfigShapeTest.php
tests/Kernel/Shared/Infrastructure/Configuration/UserConfigTest.php

exec
/bin/zsh -lc 'git status --short' in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
M  .env.sample
A  .phpactor.json
A  app/config/user.php
AM app/locale/en/posts.php
M  app/locale/en/user.php
A  app/locale/ru/posts.php
M  app/locale/ru/user.php
A  app/src/Modules/Media/Application/Query/CheckMediaAttachable/CheckMediaAttachableHandler.php
A  app/src/Modules/Media/Application/Query/CheckMediaAttachable/CheckMediaAttachableQuery.php
A  app/src/Modules/Media/Application/Query/CheckMediaAttachable/MediaAttachableResult.php
A  app/src/Modules/Media/Application/Query/FindMediaUrl/FindMediaUrlHandler.php
A  app/src/Modules/Media/Application/Query/FindMediaUrl/FindMediaUrlQuery.php
A  app/src/Modules/Posts/Application/Command/CommentPost/CommentPostCommand.php
A  app/src/Modules/Posts/Application/Command/CommentPost/CommentPostHandler.php
A  app/src/Modules/Posts/Application/Command/CreatePost/CreatePostCommand.php
A  app/src/Modules/Posts/Application/Command/CreatePost/CreatePostHandler.php
A  app/src/Modules/Posts/Application/Command/DeleteComment/DeleteCommentCommand.php
A  app/src/Modules/Posts/Application/Command/DeleteComment/DeleteCommentHandler.php
A  app/src/Modules/Posts/Application/Command/DeletePost/DeletePostCommand.php
A  app/src/Modules/Posts/Application/Command/DeletePost/DeletePostHandler.php
A  app/src/Modules/Posts/Application/Command/LikeComment/LikeCommentCommand.php
A  app/src/Modules/Posts/Application/Command/LikeComment/LikeCommentHandler.php
A  app/src/Modules/Posts/Application/Command/LikePost/LikePostCommand.php
A  app/src/Modules/Posts/Application/Command/LikePost/LikePostHandler.php
A  app/src/Modules/Posts/Application/Command/PublishPost/PublishPostCommand.php
AM app/src/Modules/Posts/Application/Command/PublishPost/PublishPostHandler.php
A  app/src/Modules/Posts/Application/Command/ReplyComment/ReplyCommentCommand.php
A  app/src/Modules/Posts/Application/Command/ReplyComment/ReplyCommentHandler.php
A  app/src/Modules/Posts/Application/Command/RepostPost/RepostPostCommand.php
A  app/src/Modules/Posts/Application/Command/RepostPost/RepostPostHandler.php
A  app/src/Modules/Posts/Application/Command/UnlikeComment/UnlikeCommentCommand.php
A  app/src/Modules/Posts/Application/Command/UnlikeComment/UnlikeCommentHandler.php
A  app/src/Modules/Posts/Application/Command/UnlikePost/UnlikePostCommand.php
A  app/src/Modules/Posts/Application/Command/UnlikePost/UnlikePostHandler.php
A  app/src/Modules/Posts/Application/Notification/CommentNotificationTarget.php
A  app/src/Modules/Posts/Application/Notification/NotificationContentBuilder.php
A  app/src/Modules/Posts/Application/Notification/PostNotificationType.php
A  app/src/Modules/Posts/Application/Notification/PostNotifier.php
AM app/src/Modules/Posts/Application/Post/CommentComposer.php
AM app/src/Modules/Posts/Application/Post/PostContentComposer.php
A  app/src/Modules/Posts/Application/Post/PostVisibilityPolicy.php
A  app/src/Modules/Posts/Application/Query/GetCommentReplies/GetCommentRepliesHandler.php
A  app/src/Modules/Posts/Application/Query/GetCommentReplies/GetCommentRepliesQuery.php
A  app/src/Modules/Posts/Application/Query/GetCommentReplies/GetCommentRepliesResult.php
A  app/src/Modules/Posts/Application/Query/GetPost/GetPostHandler.php
A  app/src/Modules/Posts/Application/Query/GetPost/GetPostQuery.php
A  app/src/Modules/Posts/Application/Query/GetPostComments/GetPostCommentsHandler.php
A  app/src/Modules/Posts/Application/Query/GetPostComments/GetPostCommentsQuery.php
A  app/src/Modules/Posts/Application/Query/GetPostComments/GetPostCommentsResult.php
A  app/src/Modules/Posts/Application/Query/GetUserFeed/GetUserFeedHandler.php
A  app/src/Modules/Posts/Application/Query/GetUserFeed/GetUserFeedQuery.php
A  app/src/Modules/Posts/Application/Query/GetUserFeed/GetUserFeedResult.php
A  app/src/Modules/Posts/Application/View/AuthorView.php
A  app/src/Modules/Posts/Application/View/CommentView.php
AM app/src/Modules/Posts/Application/View/CommentViewAssembler.php
A  app/src/Modules/Posts/Application/View/PostMediaItemView.php
A  app/src/Modules/Posts/Application/View/PostView.php
AM app/src/Modules/Posts/Application/View/PostViewAssembler.php
A  app/src/Modules/Posts/Application/View/TagView.php
A  app/src/Modules/Posts/Infrastructure/Bootloader/PostsBootloader.php
A  app/src/Modules/Posts/Presentation/Http/Controller/CommentController.php
A  app/src/Modules/Posts/Presentation/Http/Controller/PostController.php
A  app/src/Modules/Posts/Presentation/Http/Filter/CommentPostFilter.php
A  app/src/Modules/Posts/Presentation/Http/Filter/CreatePostFilter.php
A  app/src/Modules/Posts/Presentation/Http/Filter/DeleteCommentFilter.php
A  app/src/Modules/Posts/Presentation/Http/Filter/DeletePostFilter.php
A  app/src/Modules/Posts/Presentation/Http/Filter/GetCommentRepliesFilter.php
A  app/src/Modules/Posts/Presentation/Http/Filter/GetPostCommentsFilter.php
A  app/src/Modules/Posts/Presentation/Http/Filter/GetPostFilter.php
A  app/src/Modules/Posts/Presentation/Http/Filter/GetUserFeedFilter.php
A  app/src/Modules/Posts/Presentation/Http/Filter/LikeCommentFilter.php
A  app/src/Modules/Posts/Presentation/Http/Filter/LikePostFilter.php
A  app/src/Modules/Posts/Presentation/Http/Filter/PublishPostFilter.php
A  app/src/Modules/Posts/Presentation/Http/Filter/ReplyCommentFilter.php
A  app/src/Modules/Posts/Presentation/Http/Filter/RepostPostFilter.php
A  app/src/Modules/Posts/Presentation/Http/Filter/UnlikeCommentFilter.php
A  app/src/Modules/Posts/Presentation/Http/Filter/UnlikePostFilter.php
A  app/src/Modules/Posts/Presentation/Http/Resource/AuthorResource.php
A  app/src/Modules/Posts/Presentation/Http/Resource/CommentResource.php
A  app/src/Modules/Posts/Presentation/Http/Resource/PostMediaItemResource.php
A  app/src/Modules/Posts/Presentation/Http/Resource/PostResource.php
A  app/src/Modules/Posts/Presentation/Http/Resource/TagResource.php
M  app/src/Modules/Posts/Repository/CommentLikeRepository.php
M  app/src/Modules/Posts/Repository/CommentRepository.php
M  app/src/Modules/Posts/Repository/PostLikeRepository.php
M  app/src/Modules/Posts/Repository/PostMediaRepository.php
M  app/src/Modules/Posts/Repository/PostRepository.php
M  app/src/Modules/Posts/Repository/PostTagRepository.php
A  app/src/Modules/Tags/Application/Command/ResolveTags/ResolveTagsCommand.php
A  app/src/Modules/Tags/Application/Command/ResolveTags/ResolveTagsHandler.php
A  app/src/Modules/Tags/Application/Command/ResolveTags/ResolveTagsResult.php
A  app/src/Modules/Tags/Application/Dto/TagTextCollection.php
A  app/src/Modules/Tags/Application/Query/GetTags/GetTagsHandler.php
A  app/src/Modules/Tags/Application/Query/GetTags/GetTagsQuery.php
MM app/src/Modules/Tags/Repository/TagRepository.php
A  app/src/Modules/User/Application/Dto/UserPublicProfileCollection.php
A  app/src/Modules/User/Application/Dto/UserPublicProfileView.php
A  app/src/Modules/User/Application/Profile/UserPublicProfileAssembler.php
A  app/src/Modules/User/Application/Query/GetUserPublicProfile/GetUserPublicProfileHandler.php
A  app/src/Modules/User/Application/Query/GetUserPublicProfile/GetUserPublicProfileQuery.php
AM app/src/Modules/User/Application/Query/GetUserPublicProfiles/GetUserPublicProfilesHandler.php
A  app/src/Modules/User/Application/Query/GetUserPublicProfiles/GetUserPublicProfilesQuery.php
A  app/src/Modules/User/Domain/Collection/UserCollection.php
MM app/src/Modules/User/Repository/UserRepository.php
A  app/src/Shared/Infrastructure/Configuration/User/UserConfig.php
M  app/src/Shared/Infrastructure/Framework/Kernel.php
A  docs/executions/2026-06-19_12-51_posts-application-presentation.md
AM docs/plans/2026-06-18_17-42_posts-application-presentation.md
A  docs/review-fixes/2026-06-19_17-10_posts-application-presentation.md
A  docs/review-fixes/2026-06-19_19-10_posts-application-presentation.md
A  docs/reviews/2026-06-19_16-02_posts-application-presentation.md
A  docs/reviews/2026-06-19_16-02_posts-application-presentation_cli_meta.md
AM docs/reviews/2026-06-19_17-42_posts-application-presentation.md
A  docs/reviews/2026-06-19_18-30_posts-application-presentation.md
A  docs/reviews/2026-06-19_18-30_posts-application-presentation_cli_meta.md
M  docs/rules.md
M  packages/spiral-openapi/src/Parser/PhpAstParser.php
M  packages/spiral-openapi/tests/Fixtures/Endpoint/Api/V1/Resource/UserResource.php
M  packages/spiral-openapi/tests/Generator/OpenApiGeneratorTest.php
M  public/openapi/openapi.yml
A  tests/Feature/Modules/Media/Application/CheckMediaAttachableHandlerTest.php
A  tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php
M  tests/Feature/Modules/Notifications/Application/NotificationUseCaseTest.php
M  tests/Feature/Modules/Notifications/Http/NotificationHttpTest.php
A  tests/Feature/Modules/Posts/Http/CommentHttpTest.php
A  tests/Feature/Modules/Posts/Http/CommentReactionHttpTest.php
AM tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php
A  tests/Feature/Modules/Posts/Http/DeletePostHttpTest.php
A  tests/Feature/Modules/Posts/Http/GetCommentRepliesHttpTest.php
A  tests/Feature/Modules/Posts/Http/GetPostCommentsHttpTest.php
A  tests/Feature/Modules/Posts/Http/GetPostHttpTest.php
A  tests/Feature/Modules/Posts/Http/GetUserFeedHttpTest.php
A  tests/Feature/Modules/Posts/Http/PostReactionHttpTest.php
A  tests/Feature/Modules/Posts/Http/PostsHttpTestCase.php
A  tests/Feature/Modules/Posts/Http/PostsOpenApiGenerationTest.php
AM tests/Feature/Modules/Posts/Http/PublishPostHttpTest.php
AM tests/Feature/Modules/Posts/Http/RepostPostHttpTest.php
A  tests/Feature/Modules/Posts/Repository/LikeBatchRepositoryTest.php
M  tests/Feature/Modules/Posts/Repository/PostMediaRepositoryTest.php
M  tests/Feature/Modules/Posts/Repository/PostRepositoryTest.php
M  tests/Feature/Modules/Posts/Repository/PostTagRepositoryTest.php
A  tests/Feature/Modules/Tags/Application/GetTagsHandlerTest.php
A  tests/Feature/Modules/Tags/Application/ResolveTagsHandlerTest.php
 M tests/Feature/Modules/Tags/Repository/TagRepositoryTest.php
A  tests/Feature/Modules/Tags/TagsApplicationTestCase.php
A  tests/Feature/Modules/User/Application/GetUserPublicProfileHandlerTest.php
A  tests/Feature/Modules/User/Application/GetUserPublicProfilesHandlerTest.php
A  tests/Feature/Modules/User/Application/UserApplicationTestCase.php
 M tests/Feature/Modules/User/Repository/UserRepositoryTest.php
A  tests/Kernel/Modules/Posts/Notification/NotificationContentBuilderTest.php
A  tests/Kernel/Modules/Posts/Notification/PostNotificationTypesTest.php
M  tests/Kernel/Shared/Infrastructure/Configuration/ConfigShapeTest.php
A  tests/Kernel/Shared/Infrastructure/Configuration/UserConfigTest.php
?? app/src/Modules/Posts/Application/Post/MentionRecipientResolver.php
?? app/src/Modules/User/Application/Query/CheckUsersExist/
?? docs/review-fixes/2026-06-19_18-30_posts-application-presentation.md
?? docs/review-fixes/2026-06-19_20-45_posts-application-presentation.md
?? docs/reviews/2026-06-19_17-42_posts-application-presentation_cli_meta.md
?? docs/reviews/2026-06-19_20-30_posts-application-presentation.md
?? docs/reviews/2026-06-19_20-30_posts-application-presentation_cli_meta.md
?? docs/reviews/2026-06-19_21-30_posts-application-presentation.md
?? docs/reviews/2026-06-19_21-30_posts-application-presentation_cli_meta.md
?? tests/Feature/Modules/User/Application/CheckUsersExistHandlerTest.php

exec
/bin/zsh -lc "rg --files app/src app/tests tests docs | rg '(Posts|User|Media|Tags|Post|Comment|Mention|Notification|OpenApi)'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
rg: app/tests: No such file or directory (os error 2)
app/src/Modules/Media/Presentation/Job/ProcessMediaJob.php
tests/Kernel/Modules/Posts/Notification/NotificationContentBuilderTest.php
tests/Kernel/Modules/Posts/Notification/PostNotificationTypesTest.php
app/src/Modules/Media/Domain/Enum/MediaStatus.php
app/src/Modules/Media/Domain/Enum/MediaConversionStatus.php
app/src/Modules/Media/Domain/Enum/MediaStorage.php
app/src/Modules/Media/Domain/Enum/MediaType.php
app/src/Modules/Media/Domain/Enum/MediaVideoConversionType.php
app/src/Modules/Media/Domain/Enum/MediaVisibility.php
app/src/Modules/Media/Domain/Enum/MediaImageConversionType.php
app/src/Modules/Media/Domain/Entity/MediaMultipartUpload.php
app/src/Modules/Media/Domain/Entity/Media.php
app/src/Modules/Media/Domain/Entity/MediaImageConversion.php
app/src/Modules/Media/Domain/Entity/MediaVideoConversion.php
app/src/Modules/Media/README.md
app/src/Modules/Media/Domain/Collection/MediaMimeTypeCollection.php
app/src/Modules/Media/Domain/Collection/MediaCollection.php
app/src/Modules/Media/Domain/Collection/MediaMultipartPartCollection.php
app/src/Modules/Media/Domain/Collection/MediaImageConversionCollection.php
app/src/Modules/Media/Domain/Collection/MediaVideoConversionCollection.php
app/src/Modules/Media/Repository/MediaMultipartUploadRepository.php
app/src/Modules/Media/Repository/MediaRepository.php
app/src/Modules/Media/Repository/MediaVideoConversionRepository.php
app/src/Modules/Media/Repository/MediaImageConversionRepository.php
tests/Support/Notifications/RecordingOutboxEventStore.php
tests/Support/Notifications/FixtureNotificationTypeDefinition.php
tests/Kernel/Shared/Infrastructure/Configuration/UserConfigTest.php
tests/Kernel/Shared/Infrastructure/Configuration/MediaConfigTest.php
tests/Kernel/Shared/Infrastructure/Configuration/MediaStorageConfigTest.php
app/src/Modules/Media/Domain/ValueObject/MediaMultipartUploadId.php
app/src/Modules/Media/Domain/ValueObject/MediaProcessingError.php
app/src/Modules/Media/Domain/ValueObject/MediaDuration.php
app/src/Modules/Media/Domain/ValueObject/MediaMultipartPart.php
app/src/Modules/Media/Domain/ValueObject/MediaFileSize.php
app/src/Modules/Media/Domain/ValueObject/MediaStorageKey.php
app/src/Modules/Media/Domain/ValueObject/MediaMultipartPartETag.php
app/src/Modules/Media/Domain/ValueObject/MediaProcessingAttempts.php
app/src/Modules/Media/Domain/ValueObject/MediaVideoConversionId.php
app/src/Modules/Media/Domain/ValueObject/MediaMultipartUploadIdValue.php
app/src/Modules/Media/Domain/ValueObject/MediaBitrate.php
app/src/Modules/Media/Domain/ValueObject/MediaPixelDimension.php
app/src/Modules/Media/Domain/ValueObject/MediaExpiration.php
app/src/Modules/Media/Domain/ValueObject/MediaId.php
app/src/Modules/Media/Domain/ValueObject/MediaMultipartPartSize.php
app/src/Modules/Media/Domain/ValueObject/MediaMultipartPartsCount.php
app/src/Modules/Media/Domain/ValueObject/MediaPresignedTtl.php
app/src/Modules/Media/Domain/ValueObject/MediaPath.php
app/src/Modules/Media/Domain/ValueObject/MediaImageConversionId.php
app/src/Modules/Media/Domain/ValueObject/MediaMultipartPartNumber.php
app/src/Modules/Media/Domain/ValueObject/MediaMimeType.php
tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php
app/src/Modules/System/Presentation/Console/OpenApiPublishAssetsCommand.php
app/src/Modules/System/Presentation/Console/OpenApiGenerateCommand.php
tests/Feature/Modules/Media/Flow/Fixture/RecordingMediaLogger.php
tests/Feature/Modules/Media/Flow/Fixture/ThrowingProcessMediaCommandBus.php
tests/Feature/Modules/User/Application/GetUserPublicProfileHandlerTest.php
tests/Feature/Modules/User/Application/UserApplicationTestCase.php
tests/Feature/Modules/User/Application/CreateUserHandlerTest.php
tests/Feature/Modules/User/Application/CheckUsersExistHandlerTest.php
tests/Feature/Modules/User/Application/GetUserPublicProfilesHandlerTest.php
tests/Feature/Modules/User/Application/FindUserForAuthHandlerTest.php
app/src/Modules/System/Presentation/Exception/OpenApiAssetsPublicationException.php
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php
app/src/Modules/Media/Infrastructure/FileService/ImagickMediaImageProcessor.php
app/src/Modules/Media/Infrastructure/FileService/ConfiguredS3ClientProvider.php
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php
app/src/Modules/Media/Infrastructure/FileService/S3ClientProvider.php
tests/Feature/Modules/User/Repository/UserRepositoryTest.php
tests/Feature/Modules/Notifications/Presentation/DispatchNotificationJobTest.php
tests/Feature/Modules/Notifications/Presentation/DeliveryJobTest.php
tests/Feature/Modules/Media/Application/MakeMediaPermanentHandlerTest.php
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php
tests/Feature/Modules/Media/Application/MediaApplicationTestCase.php
tests/Feature/Modules/Media/Application/RecordMediaProcessingFailureHandlerTest.php
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php
tests/Feature/Modules/Media/Application/CheckMediaAttachableHandlerTest.php
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php
app/src/Modules/Media/Infrastructure/Exception/MediaImageProcessorException.php
app/src/Modules/Media/Infrastructure/Exception/MediaStorageNotConfiguredException.php
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php
tests/Feature/Modules/Notifications/Application/NotificationUseCaseTest.php
tests/Feature/Modules/Notifications/Application/SendPushNotificationHandlerTest.php
tests/Feature/Modules/Notifications/Application/DispatchNotificationHandlerTest.php
app/src/Modules/Media/Infrastructure/Cycle/MediaMultipartPartCollectionTypecast.php
app/src/Modules/Media/Infrastructure/Cycle/MediaExpirationTypecast.php
app/src/Modules/Media/Infrastructure/Cycle/MediaProcessingErrorTypecast.php
tests/Feature/Modules/Auth/Console/AuthOpenApiGenerationTest.php
tests/Feature/Modules/Notifications/Http/NotificationHttpTest.php
app/src/Modules/Media/Infrastructure/Bootloader/MediaBootloader.php
tests/Feature/Modules/Notifications/Repository/NotificationRepositoryTest.php
app/src/Modules/Notifications/Application/Exception/NotificationTypeRegistryException.php
app/src/Modules/Notifications/Application/Exception/FcmPushFailedException.php
app/src/Modules/Notifications/Application/Exception/CentrifugoPublishException.php
tests/Feature/Modules/System/Console/OpenApiPublishAssetsCommandTest.php
tests/Feature/Modules/System/Console/OpenApiGenerateCommandTest.php
app/src/Modules/Notifications/Presentation/Job/SendPushNotificationJob.php
app/src/Modules/Notifications/Presentation/Job/DispatchNotificationJob.php
app/src/Modules/Notifications/Presentation/Job/PublishRealtimeNotificationJob.php
tests/Feature/Modules/System/Http/OpenApiHttpTest.php
tests/Feature/Modules/Auth/Application/GetUserSessionsHandlerTest.php
tests/Feature/Modules/Auth/Application/RevokeUserSessionHandlerTest.php
app/src/Modules/Media/Application/Exception/MediaFileServiceFailedException.php
app/src/Modules/Notifications/Application/Query/Setting/GetNotificationSettings/GetNotificationSettingsHandler.php
app/src/Modules/Notifications/Application/Query/Setting/GetNotificationSettings/GetNotificationSettingsQuery.php
tests/Feature/Modules/Tags/Application/ResolveTagsHandlerTest.php
tests/Feature/Modules/Tags/Application/GetTagsHandlerTest.php
tests/Feature/Modules/Tags/TagsApplicationTestCase.php
tests/Feature/Modules/Posts/Http/CommentHttpTest.php
tests/Feature/Modules/Posts/Http/GetUserFeedHttpTest.php
tests/Feature/Modules/Posts/Http/PostReactionHttpTest.php
tests/Feature/Modules/Posts/Http/CommentReactionHttpTest.php
tests/Feature/Modules/Posts/Http/PostsOpenApiGenerationTest.php
tests/Feature/Modules/Posts/Http/PostsHttpTestCase.php
tests/Feature/Modules/Posts/Http/RepostPostHttpTest.php
tests/Feature/Modules/Posts/Http/PublishPostHttpTest.php
tests/Feature/Modules/Posts/Http/DeletePostHttpTest.php
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php
tests/Feature/Modules/Posts/Http/GetPostHttpTest.php
tests/Feature/Modules/Posts/Http/GetCommentRepliesHttpTest.php
tests/Feature/Modules/Posts/Http/GetPostCommentsHttpTest.php
tests/Feature/Modules/Posts/PostsRepositoryTestCase.php
tests/Feature/Modules/Tags/TagsRepositoryTestCase.php
tests/Feature/Modules/Tags/Repository/TagRepositoryTest.php
app/src/Modules/Notifications/Application/Query/Notification/ListNotifications/ListNotificationsQuery.php
app/src/Modules/Notifications/Application/Query/Notification/ListNotifications/ListNotificationsResult.php
app/src/Modules/Notifications/Application/Query/Notification/ListNotifications/ListNotificationsHandler.php
tests/Feature/Modules/Posts/Repository/LikesAndMentionsRepositoryTest.php
tests/Feature/Modules/Posts/Repository/CommentRepositoryTest.php
tests/Feature/Modules/Posts/Repository/LikeBatchRepositoryTest.php
tests/Feature/Modules/Posts/Repository/PostMediaRepositoryTest.php
tests/Feature/Modules/Posts/Repository/PostRepositoryTest.php
tests/Feature/Modules/Posts/Repository/PostTagRepositoryTest.php
tests/Feature/Modules/Posts/Repository/PostBlockRepositoryTest.php
app/src/Modules/Notifications/README.md
app/src/Modules/Notifications/Presentation/Http/Resource/NotificationSettingResource.php
app/src/Modules/Notifications/Presentation/Http/Resource/NotificationResource.php
app/src/Modules/Notifications/Presentation/Http/Resource/UnreadCountResource.php
app/src/Modules/Notifications/Presentation/Http/Resource/NotificationDeviceTokenResource.php
app/src/Modules/Notifications/Presentation/Http/Resource/NotificationActionResource.php
app/src/Modules/Notifications/Presentation/Http/Resource/NotificationActorResource.php
tests/Unit/Modules/Media/Domain/Enum/MediaEnumTest.php
app/src/Modules/Notifications/Application/Message/NotificationPushRequested.php
app/src/Modules/Notifications/Application/Message/NotificationRequested.php
app/src/Modules/Notifications/Application/Message/NotificationRealtimeRequested.php
app/src/Modules/Notifications/Repository/NotificationRepository.php
app/src/Modules/Notifications/Repository/NotificationSettingRepository.php
app/src/Modules/Notifications/Repository/NotificationDeviceTokenRepository.php
app/src/Modules/Notifications/Application/Query/Notification/GetUnreadCount/GetUnreadCountQuery.php
app/src/Modules/Notifications/Application/Query/Notification/GetUnreadCount/GetUnreadCountHandler.php
app/src/Modules/Notifications/Application/Query/Notification/GetUnreadCount/UnreadCountResult.php
tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php
app/src/Modules/Tags/Domain/Entity/Tag.php
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php
app/src/Modules/Notifications/Application/Service/NotificationSettingsViewFactory.php
app/src/Modules/Notifications/Application/Contract/CentrifugoServiceContract.php
app/src/Modules/Notifications/Application/Contract/NotificationTypeRegistryContract.php
app/src/Modules/Notifications/Application/Contract/OnlinePresenceContract.php
app/src/Modules/Notifications/Application/Contract/NotificationTypeDefinition.php
app/src/Modules/Notifications/Application/Contract/NotificationBulkWriterContract.php
app/src/Modules/Notifications/Application/Contract/NotificationSenderContract.php
app/src/Modules/Notifications/Application/Contract/FcmPushSenderContract.php
app/src/Modules/Tags/Domain/Collection/TagCollection.php
app/src/Modules/Notifications/Application/Dto/NotificationActionPayload.php
app/src/Modules/Notifications/Application/Dto/NotificationSettingView.php
app/src/Modules/Notifications/Application/Dto/FcmPushResult.php
app/src/Modules/Notifications/Application/Dto/NotificationPush.php
app/src/Modules/Notifications/Application/Dto/RealtimeNotificationPayload.php
app/src/Modules/Notifications/Application/Dto/NotificationSettingViewCollection.php
app/src/Modules/Notifications/Application/Dto/NotificationTypeDefinitionCollection.php
app/src/Modules/Notifications/Application/Dto/NotificationActorPayload.php
app/src/Modules/Notifications/Application/Dto/NotificationContent.php
app/src/Modules/Notifications/Application/NotificationSender.php
app/src/Modules/Media/Application/Query/CheckMediaIsImage/CheckMediaIsImageHandler.php
app/src/Modules/Media/Application/Query/CheckMediaIsImage/CheckMediaIsImageQuery.php
tests/Unit/Modules/Media/Infrastructure/S3MediaFileServiceErrorTest.php
tests/Unit/Modules/Media/Infrastructure/ImagickMediaImageProcessorTest.php
app/src/Modules/Notifications/Application/Command/Setting/UpdateNotificationSettings/UpdateNotificationSettingsCommand.php
app/src/Modules/Notifications/Application/Command/Setting/UpdateNotificationSettings/NotificationSettingUpdate.php
app/src/Modules/Notifications/Application/Command/Setting/UpdateNotificationSettings/UpdateNotificationSettingsHandler.php
app/src/Modules/Tags/Domain/ValueObject/TagText.php
tests/Unit/Modules/Media/Infrastructure/Cycle/MediaTypecastTest.php
app/src/Modules/Notifications/Domain/Enum/NotificationSettingStatus.php
app/src/Modules/Notifications/Domain/Enum/DevicePlatform.php
app/src/Modules/Notifications/Domain/Enum/NotificationChannel.php
tests/Unit/Modules/Media/Infrastructure/Fixture/FakeS3ClientProvider.php
tests/Unit/Modules/Media/Infrastructure/Fixture/FakeS3Client.php
app/src/Modules/Notifications/Application/Command/Realtime/PublishRealtimeNotification/PublishRealtimeNotificationCommand.php
app/src/Modules/Notifications/Application/Command/Realtime/PublishRealtimeNotification/PublishRealtimeNotificationHandler.php
app/src/Modules/Notifications/Presentation/Http/Filter/Setting/NotificationSettingUpdateInput.php
app/src/Modules/Notifications/Presentation/Http/Filter/Setting/UpdateNotificationSettingsFilter.php
app/src/Modules/Notifications/Application/Command/Notification/MarkAllNotificationsRead/MarkAllNotificationsReadHandler.php
app/src/Modules/Notifications/Domain/Entity/Notification.php
app/src/Modules/Notifications/Domain/Entity/NotificationSetting.php
app/src/Modules/Notifications/Domain/Entity/NotificationDeviceToken.php
app/src/Modules/Notifications/Application/Command/Notification/MarkAllNotificationsRead/MarkAllNotificationsReadCommand.php
app/src/Modules/Media/Application/Query/GetMediaUrl/GetMediaUrlQuery.php
app/src/Modules/Media/Application/Query/GetMediaUrl/GetMediaUrlHandler.php
app/src/Modules/Notifications/Application/Command/DeviceToken/RemoveNotificationDeviceToken/RemoveNotificationDeviceTokenCommand.php
app/src/Modules/Notifications/Application/Command/DeviceToken/RemoveNotificationDeviceToken/RemoveNotificationDeviceTokenHandler.php
tests/Unit/Modules/Media/Application/MediaTypeResolverTest.php
app/src/Modules/Notifications/Domain/Collection/NotificationCollection.php
app/src/Modules/Notifications/Domain/Collection/NotificationDeviceTokenCollection.php
app/src/Modules/Notifications/Domain/Collection/NotificationSettingCollection.php
app/src/Modules/Tags/Application/Query/GetTags/GetTagsHandler.php
app/src/Modules/Tags/Application/Query/GetTags/GetTagsQuery.php
app/src/Modules/Notifications/Application/Command/Notification/DispatchNotification/DispatchNotificationCommand.php
app/src/Modules/Notifications/Application/Command/Notification/DispatchNotification/DispatchNotificationHandler.php
app/src/Modules/Notifications/Application/Command/DeviceToken/RegisterNotificationDeviceToken/RegisterNotificationDeviceTokenHandler.php
app/src/Modules/Notifications/Application/Command/DeviceToken/RegisterNotificationDeviceToken/RegisterNotificationDeviceTokenCommand.php
app/src/Modules/Notifications/Application/Command/Push/SendPushNotification/SendPushNotificationCommand.php
app/src/Modules/Notifications/Application/Command/Push/SendPushNotification/SendPushNotificationHandler.php
app/src/Modules/Notifications/Presentation/Http/Filter/NotificationRecipientFilter.php
app/src/Modules/Notifications/Application/Command/Notification/MarkNotificationRead/MarkNotificationReadHandler.php
app/src/Modules/Notifications/Application/Command/Notification/MarkNotificationRead/MarkNotificationReadCommand.php
app/src/Modules/Notifications/Domain/ValueObject/NotificationBody.php
app/src/Modules/Notifications/Domain/ValueObject/NotificationActionId.php
app/src/Modules/Notifications/Domain/ValueObject/DeviceToken.php
app/src/Modules/Notifications/Domain/ValueObject/NotificationTypeCode.php
app/src/Modules/Notifications/Domain/ValueObject/NotificationId.php
app/src/Modules/Notifications/Domain/ValueObject/NotificationReadState.php
app/src/Modules/Notifications/Domain/ValueObject/NotificationActionType.php
app/src/Modules/Notifications/Domain/ValueObject/NotificationDeviceTokenId.php
app/src/Modules/Notifications/Domain/ValueObject/NotificationOutboxId.php
app/src/Modules/Notifications/Domain/ValueObject/NotificationTitle.php
app/src/Modules/Notifications/Domain/ValueObject/NotificationChannelDefaults.php
app/src/Modules/Notifications/Domain/ValueObject/NotificationSettingId.php
app/src/Modules/Notifications/Domain/ValueObject/NotificationAction.php
app/src/Modules/Notifications/Domain/ValueObject/NotificationActor.php
app/src/Modules/Notifications/Presentation/Http/Filter/DeviceToken/RemoveNotificationDeviceTokenFilter.php
app/src/Modules/Notifications/Presentation/Http/Filter/DeviceToken/RegisterNotificationDeviceTokenFilter.php
app/src/Modules/Tags/Application/Command/ResolveTags/ResolveTagsHandler.php
app/src/Modules/Tags/Application/Command/ResolveTags/ResolveTagsResult.php
app/src/Modules/Tags/Application/Command/ResolveTags/ResolveTagsCommand.php
app/src/Modules/Notifications/Presentation/Http/Controller/NotificationSettingController.php
app/src/Modules/Media/Application/Query/FindMediaUrl/FindMediaUrlHandler.php
app/src/Modules/Notifications/Presentation/Http/Controller/NotificationDeviceTokenController.php
app/src/Modules/Notifications/Presentation/Http/Controller/NotificationController.php
app/src/Modules/Media/Application/Query/FindMediaUrl/FindMediaUrlQuery.php
app/src/Modules/Notifications/Presentation/Http/Filter/Notification/MarkNotificationReadFilter.php
app/src/Modules/Notifications/Presentation/Http/Filter/Notification/ListNotificationsFilter.php
app/src/Modules/Tags/Application/Dto/TagTextCollection.php
app/src/Modules/Notifications/Infrastructure/Push/KreaitFcmPushSender.php
app/src/Modules/Notifications/Infrastructure/Exception/CentrifugoPresenceException.php
app/src/Modules/Notifications/Infrastructure/Bootloader/NotificationsBootloader.php
app/src/Modules/Tags/Repository/TagRepository.php
tests/Unit/Modules/Notifications/Domain/Enum/NotificationEnumTest.php
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadCommand.php
app/src/Modules/Media/Application/Query/CheckMediaAttachable/CheckMediaAttachableQuery.php
app/src/Modules/Media/Application/Query/CheckMediaAttachable/MediaAttachableResult.php
app/src/Modules/Media/Application/Query/CheckMediaAttachable/CheckMediaAttachableHandler.php
app/src/Modules/Notifications/Infrastructure/Registry/NotificationTypeRegistry.php
app/src/Modules/Notifications/Infrastructure/Persistence/NotificationBulkWriter.php
app/src/Modules/Notifications/Infrastructure/Cycle/NotificationSettingStatusTypecast.php
app/src/Modules/Media/Application/Query/CheckMediaExists/CheckMediaExistsHandler.php
tests/Unit/Modules/Notifications/Domain/Entity/NotificationEntityTest.php
app/src/Modules/Media/Application/Query/CheckMediaExists/CheckMediaExistsQuery.php
app/src/Modules/Notifications/Infrastructure/Cycle/NotificationActorTypecast.php
app/src/Modules/Notifications/Infrastructure/Cycle/NotificationReadStateTypecast.php
app/src/Modules/Notifications/Infrastructure/Cycle/NotificationActionIdTypecast.php
app/src/Modules/Notifications/Infrastructure/Cycle/NotificationActionTypeTypecast.php
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadCommand.php
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php
app/src/Modules/Media/Application/Service/MediaTypeResolver.php
app/src/Modules/Media/Application/Command/DeleteMedia/DeleteMediaHandler.php
app/src/Modules/Media/Application/Command/DeleteMedia/DeleteMediaCommand.php
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaCommand.php
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php
app/src/Modules/Media/Application/Command/MakeMediaPermanent/MakeMediaPermanentHandler.php
app/src/Modules/Media/Application/Command/MakeMediaPermanent/MakeMediaPermanentCommand.php
tests/Unit/Modules/Notifications/Domain/ValueObject/NotificationValueObjectTest.php
app/src/Modules/Notifications/Infrastructure/Centrifugo/CentrifugoPresenceStats.php
app/src/Modules/Notifications/Infrastructure/Centrifugo/CentrifugoService.php
app/src/Modules/Notifications/Infrastructure/Centrifugo/CentrifugoClient.php
app/src/Modules/Notifications/Infrastructure/Centrifugo/CentrifugoOnlinePresence.php
app/src/Modules/Media/Application/Message/MediaUploaded.php
app/src/Modules/User/Domain/Enum/UserStatus.php
app/src/Modules/User/Domain/Enum/UserVerification.php
app/src/Modules/Media/Application/Command/RecordMediaProcessingFailure/RecordMediaProcessingFailureHandler.php
app/src/Modules/Media/Application/Command/RecordMediaProcessingFailure/RecordMediaProcessingFailureCommand.php
app/src/Modules/Media/Application/Contract/MediaImageProcessorContract.php
app/src/Modules/Media/Application/Contract/MediaFileServiceContract.php
tests/Unit/Modules/Notifications/Infrastructure/Registry/NotificationTypeRegistryTest.php
app/src/Modules/User/Domain/Entity/ReservedNickname.php
app/src/Modules/User/Domain/Entity/UserBan.php
app/src/Modules/User/Domain/Entity/User.php
app/src/Modules/Media/Application/Dto/MediaUploadSpec.php
app/src/Modules/Media/Application/Dto/MediaPresignedPart.php
app/src/Modules/Media/Application/Dto/MediaConversionSpec.php
app/src/Modules/Media/Application/Dto/MediaObjectHead.php
app/src/Modules/Media/Application/Dto/MediaUploadMode.php
app/src/Modules/Media/Application/Dto/MediaUrlResult.php
app/src/Modules/Media/Application/Dto/MediaFileMeta.php
app/src/Modules/Media/Application/Dto/MediaConversionResult.php
app/src/Modules/Media/Application/Dto/MediaResult.php
app/src/Modules/Media/Application/Dto/MediaPresignedPartCollection.php
app/src/Modules/Media/Application/Dto/RequestMediaUploadResult.php
tests/Unit/Modules/Posts/Domain/Enum/PostsEnumTest.php
tests/Unit/Modules/Notifications/Infrastructure/Centrifugo/CentrifugoServiceTest.php
tests/Unit/Modules/Notifications/Infrastructure/Centrifugo/CentrifugoClientTest.php
tests/Unit/Modules/Notifications/Infrastructure/Centrifugo/CentrifugoOnlinePresenceTest.php
app/src/Modules/User/Domain/Collection/UserCollection.php
tests/Unit/Modules/Posts/Infrastructure/Cycle/PostsTypecastTest.php
tests/Unit/Modules/Posts/Domain/Collection/PostsCollectionTest.php
tests/Unit/Modules/Notifications/Infrastructure/Push/KreaitFcmPushSenderTest.php
tests/Unit/Modules/Posts/Domain/Entity/PostEntityTest.php
tests/Unit/Modules/Posts/Domain/Entity/CommentEntityTest.php
tests/Unit/Modules/Posts/Domain/Entity/JoinEntityTest.php
tests/Unit/Modules/Posts/Domain/Entity/PostBlockEntityTest.php
app/src/Modules/User/Domain/ValueObject/ReservedNicknameId.php
app/src/Modules/User/Domain/ValueObject/UserNickname.php
app/src/Modules/User/Domain/ValueObject/ReservedNicknameHolder.php
app/src/Modules/User/Domain/ValueObject/UserBio.php
app/src/Modules/User/Domain/ValueObject/UserAvatar.php
app/src/Modules/User/Domain/ValueObject/BanUnbannedAt.php
app/src/Modules/User/Domain/ValueObject/BanUnbannedBy.php
app/src/Modules/User/Domain/ValueObject/BanUnbannedReason.php
app/src/Modules/User/Domain/ValueObject/UserDeletion.php
app/src/Modules/User/Domain/ValueObject/UserName.php
app/src/Modules/User/Domain/ValueObject/UserSpiritualName.php
app/src/Modules/User/Domain/ValueObject/UserLocation.php
app/src/Modules/User/Domain/ValueObject/UserBanId.php
app/src/Modules/User/Domain/ValueObject/BanReason.php
app/src/Modules/User/Domain/ValueObject/BanExpiration.php
app/src/Modules/User/Domain/ValueObject/Email.php
tests/Unit/Modules/Posts/Domain/ValueObject/PostsNullObjectValueObjectTest.php
tests/Unit/Modules/Posts/Domain/ValueObject/PostsCounterTest.php
tests/Unit/Modules/Posts/Domain/ValueObject/PostsReferenceValueObjectTest.php
tests/Unit/Modules/Posts/Domain/ValueObject/PostsTextValueObjectTest.php
tests/Unit/Modules/Posts/Domain/ValueObject/PostsIdentifierTest.php
tests/Unit/Modules/Notifications/Infrastructure/Cycle/NotificationTypecastTest.php
tests/Unit/Modules/Notifications/Infrastructure/Bootloader/NotificationsBootloaderTest.php
tests/Unit/Modules/Notifications/Application/NotificationSenderTest.php
tests/Unit/Modules/Notifications/Application/NotificationMessageSerializerTest.php
tests/Unit/Modules/Notifications/Application/PublishRealtimeNotificationHandlerTest.php
app/src/Modules/User/Infrastructure/Cycle/UserAvatarTypecast.php
app/src/Modules/User/Infrastructure/Cycle/UserBioTypecast.php
app/src/Modules/User/Infrastructure/Cycle/UserDeletionTypecast.php
app/src/Modules/User/Infrastructure/Cycle/UserSpiritualNameTypecast.php
app/src/Modules/User/Infrastructure/Cycle/UserLocationTypecast.php
app/src/Modules/User/Infrastructure/Cycle/ReservedNicknameHolderTypecast.php
app/src/Modules/User/Infrastructure/Cycle/BanUnbannedReasonTypecast.php
app/src/Modules/User/Infrastructure/Cycle/BanUnbannedByTypecast.php
app/src/Modules/User/Infrastructure/Cycle/BanExpirationTypecast.php
app/src/Modules/User/Infrastructure/Cycle/BanUnbannedAtTypecast.php
tests/Unit/Modules/Auth/Application/Query/GetUserSessions/AuthSessionTest.php
tests/Unit/Modules/User/Domain/Entity/UserEntityTest.php
tests/Unit/Modules/Auth/Infrastructure/Auth/UserActorProviderTest.php
app/src/Modules/User/Application/Query/GetUserPublicProfile/GetUserPublicProfileQuery.php
app/src/Modules/User/Application/Query/GetUserPublicProfile/GetUserPublicProfileHandler.php
tests/Unit/Modules/Tags/Domain/ValueObject/TagTextValueObjectTest.php
tests/Unit/Modules/Tags/Domain/Entity/TagEntityTest.php
tests/Unit/Modules/User/Domain/ValueObject/UserValueObjectTest.php
app/src/Modules/User/Application/Query/CheckUsersExist/CheckUsersExistHandler.php
app/src/Modules/User/Application/Query/CheckUsersExist/CheckUsersExistQuery.php
app/src/Modules/User/Repository/UserRepository.php
app/src/Modules/User/Repository/UserBanRepository.php
app/src/Modules/User/Repository/ReservedNicknameRepository.php
tests/Unit/Modules/Tags/Domain/Collection/TagCollectionTest.php
app/src/Modules/User/Application/Query/GetUserPublicProfiles/GetUserPublicProfilesQuery.php
app/src/Modules/User/Application/Query/GetUserPublicProfiles/GetUserPublicProfilesHandler.php
tests/Unit/Modules/User/Infrastructure/Cycle/UserTypecastTest.php
app/src/Modules/User/Application/Query/FindUserForAuth/FindUserForAuthQuery.php
app/src/Modules/User/Application/Query/FindUserForAuth/FindUserForAuthHandler.php
app/src/Modules/User/Application/Command/CreateUser/CreateUserResult.php
app/src/Modules/User/Application/Command/CreateUser/CreateUserHandler.php
app/src/Modules/User/Application/Command/CreateUser/CreateUserCommand.php
app/src/Modules/User/Application/Profile/UserPublicProfileAssembler.php
app/src/Modules/User/Application/Dto/UserPublicProfileView.php
app/src/Modules/User/Application/Dto/UserAuthView.php
app/src/Modules/User/Application/Dto/UserPublicProfileCollection.php
app/src/Modules/Posts/Presentation/Http/Resource/PostMediaItemResource.php
app/src/Modules/Posts/Presentation/Http/Resource/PostResource.php
app/src/Modules/Posts/Presentation/Http/Resource/AuthorResource.php
app/src/Modules/Posts/Presentation/Http/Resource/TagResource.php
app/src/Modules/Posts/Presentation/Http/Resource/CommentResource.php
app/src/Shared/Infrastructure/Framework/Bootloader/OpenApiBootloader.php
app/src/Modules/Posts/Presentation/Http/Filter/PublishPostFilter.php
app/src/Modules/Posts/Presentation/Http/Filter/DeletePostFilter.php
app/src/Modules/Posts/Presentation/Http/Filter/CreatePostFilter.php
app/src/Modules/Posts/Presentation/Http/Filter/DeleteCommentFilter.php
app/src/Modules/Posts/Presentation/Http/Filter/GetCommentRepliesFilter.php
app/src/Modules/Posts/Presentation/Http/Filter/GetPostFilter.php
app/src/Modules/Posts/Presentation/Http/Filter/ReplyCommentFilter.php
app/src/Modules/Posts/Presentation/Http/Filter/LikePostFilter.php
app/src/Modules/Posts/Presentation/Http/Filter/UnlikeCommentFilter.php
app/src/Modules/Posts/Presentation/Http/Filter/GetUserFeedFilter.php
app/src/Modules/Posts/Presentation/Http/Filter/GetPostCommentsFilter.php
app/src/Modules/Posts/Presentation/Http/Filter/UnlikePostFilter.php
app/src/Modules/Posts/Presentation/Http/Filter/RepostPostFilter.php
app/src/Modules/Posts/Presentation/Http/Filter/CommentPostFilter.php
app/src/Modules/Posts/Presentation/Http/Filter/LikeCommentFilter.php
app/src/Modules/Posts/Presentation/Http/Controller/PostController.php
app/src/Modules/Posts/Presentation/Http/Controller/CommentController.php
app/src/Modules/Posts/Application/Query/GetUserFeed/GetUserFeedResult.php
app/src/Modules/Posts/Application/Query/GetUserFeed/GetUserFeedQuery.php
app/src/Modules/Posts/Application/Query/GetUserFeed/GetUserFeedHandler.php
app/src/Modules/Access/Domain/ValueObject/UserRoleId.php
app/src/Modules/Access/Domain/Entity/UserRole.php
app/src/Shared/Infrastructure/Configuration/Media/MediaConfig.php
app/src/Modules/Posts/Domain/Enum/PostStatus.php
app/src/Modules/Posts/Domain/Enum/AttachmentType.php
app/src/Modules/Access/Repository/UserRoleRepository.php
app/src/Shared/Domain/ValueObject/UserId.php
app/src/Modules/Posts/Application/Query/GetCommentReplies/GetCommentRepliesHandler.php
app/src/Modules/Posts/Application/Query/GetCommentReplies/GetCommentRepliesQuery.php
app/src/Modules/Posts/Application/Query/GetCommentReplies/GetCommentRepliesResult.php
app/src/Modules/Posts/Domain/Entity/PostMention.php
app/src/Modules/Posts/Domain/Entity/PostMedia.php
app/src/Modules/Posts/Domain/Entity/CommentLike.php
app/src/Modules/Posts/Domain/Entity/Comment.php
app/src/Modules/Posts/Domain/Entity/CommentMention.php
app/src/Modules/Posts/Domain/Entity/PostBlock.php
app/src/Modules/Posts/Domain/Entity/PostTag.php
app/src/Modules/Posts/Domain/Entity/PostLike.php
app/src/Modules/Posts/Domain/Entity/Post.php
app/src/Modules/Posts/Application/Query/GetPostComments/GetPostCommentsQuery.php
app/src/Modules/Posts/Application/Query/GetPostComments/GetPostCommentsResult.php
app/src/Modules/Posts/Application/Query/GetPostComments/GetPostCommentsHandler.php
app/src/Modules/Access/Domain/Collection/UserRoleCollection.php
app/src/Modules/Posts/Domain/Collection/PostBlockCollection.php
app/src/Modules/Posts/Domain/Collection/CommentCollection.php
app/src/Modules/Posts/Domain/Collection/PostCollection.php
app/src/Modules/Posts/Domain/Collection/CommentLikeCollection.php
app/src/Modules/Posts/Domain/Collection/PostLikeCollection.php
app/src/Modules/Posts/Domain/Collection/PostTagCollection.php
app/src/Modules/Posts/Domain/Collection/PostMediaCollection.php
app/src/Modules/Posts/Domain/Collection/PostMentionCollection.php
app/src/Modules/Posts/Domain/Collection/CommentMentionCollection.php
app/src/Modules/Posts/Application/Query/GetPost/GetPostQuery.php
app/src/Modules/Posts/Application/Query/GetPost/GetPostHandler.php
app/src/Modules/Posts/Domain/ValueObject/PostMentionId.php
app/src/Modules/Posts/Domain/ValueObject/PostBlockId.php
app/src/Modules/Posts/Domain/ValueObject/CommentDeletedBy.php
app/src/Modules/Posts/Domain/ValueObject/CommentDeletedAt.php
app/src/Modules/Posts/Domain/ValueObject/PostText.php
app/src/Modules/Posts/Domain/ValueObject/BlockReason.php
app/src/Modules/Posts/Domain/ValueObject/CommentId.php
app/src/Modules/Posts/Domain/ValueObject/CommentDeletionReason.php
app/src/Modules/Posts/Domain/ValueObject/PostDeletion.php
app/src/Modules/Posts/Domain/ValueObject/CommentMentionId.php
app/src/Modules/Posts/Domain/ValueObject/PostLesson.php
app/src/Modules/Posts/Domain/ValueObject/CommentsCount.php
app/src/Modules/Posts/Domain/ValueObject/PostMediaReference.php
app/src/Modules/Posts/Domain/ValueObject/PostLikeId.php
app/src/Modules/Posts/Domain/ValueObject/CommentLikeId.php
app/src/Modules/Posts/Domain/ValueObject/LikesCount.php
app/src/Modules/Posts/Domain/ValueObject/RepostsCount.php
app/src/Modules/Posts/Domain/ValueObject/PostId.php
app/src/Modules/Posts/Domain/ValueObject/CommentText.php
app/src/Modules/Posts/Domain/ValueObject/PostPractice.php
app/src/Modules/Posts/Domain/ValueObject/BlockUnblockedBy.php
app/src/Modules/Posts/Domain/ValueObject/PostTagId.php
app/src/Modules/Posts/Domain/ValueObject/BlockUnblockedReason.php
app/src/Modules/Posts/Domain/ValueObject/BlockUnblockedAt.php
app/src/Modules/Posts/Domain/ValueObject/CommentParent.php
app/src/Modules/Posts/Domain/ValueObject/PostMediaId.php
app/src/Modules/Posts/Domain/ValueObject/RepliesCount.php
app/src/Modules/Posts/Domain/ValueObject/PostOriginal.php
app/src/Modules/Posts/Domain/ValueObject/MediaPosition.php
app/src/Modules/Posts/Application/Notification/PostNotifier.php
app/src/Modules/Posts/Application/Notification/PostNotificationType.php
app/src/Modules/Posts/Application/Notification/CommentNotificationTarget.php
app/src/Modules/Posts/Application/Notification/NotificationContentBuilder.php
app/src/Modules/Posts/Application/View/TagView.php
app/src/Modules/Posts/Application/View/PostView.php
app/src/Modules/Posts/Application/View/PostViewAssembler.php
app/src/Modules/Posts/Application/View/AuthorView.php
app/src/Modules/Posts/Application/View/PostMediaItemView.php
app/src/Modules/Posts/Application/View/CommentViewAssembler.php
app/src/Modules/Posts/Application/View/CommentView.php
app/src/Modules/Posts/Application/Post/PostContentComposer.php
app/src/Modules/Posts/Application/Post/CommentComposer.php
app/src/Modules/Posts/Application/Post/MentionRecipientResolver.php
app/src/Modules/Posts/Application/Post/PostVisibilityPolicy.php
app/src/Modules/Posts/Infrastructure/Cycle/PostDeletionTypecast.php
app/src/Modules/Posts/Infrastructure/Cycle/PostLessonTypecast.php
app/src/Modules/Posts/Infrastructure/Cycle/PostTextTypecast.php
app/src/Modules/Posts/Infrastructure/Cycle/PostPracticeTypecast.php
app/src/Modules/Posts/Infrastructure/Cycle/CommentParentTypecast.php
app/src/Modules/Posts/Infrastructure/Cycle/BlockUnblockedByTypecast.php
app/src/Modules/Posts/Infrastructure/Cycle/PostOriginalTypecast.php
app/src/Modules/Posts/Infrastructure/Cycle/CommentDeletedAtTypecast.php
app/src/Modules/Posts/Infrastructure/Cycle/BlockUnblockedAtTypecast.php
app/src/Modules/Posts/Infrastructure/Cycle/CommentDeletionReasonTypecast.php
app/src/Modules/Posts/Infrastructure/Cycle/CommentDeletedByTypecast.php
app/src/Modules/Posts/Infrastructure/Cycle/BlockUnblockedReasonTypecast.php
app/src/Modules/Posts/Infrastructure/Bootloader/PostsBootloader.php
app/src/Modules/Posts/Repository/PostBlockRepository.php
app/src/Modules/Posts/Repository/CommentLikeRepository.php
app/src/Modules/Posts/Repository/PostRepository.php
app/src/Modules/Posts/Repository/CommentRepository.php
app/src/Modules/Posts/Repository/PostMediaRepository.php
app/src/Modules/Posts/Repository/PostTagRepository.php
app/src/Modules/Posts/Repository/PostLikeRepository.php
app/src/Modules/Posts/Repository/CommentMentionRepository.php
app/src/Modules/Posts/Repository/PostMentionRepository.php
app/src/Shared/Infrastructure/Configuration/OpenApi/OpenApiConfig.php
app/src/Modules/Posts/Application/Command/UnlikeComment/UnlikeCommentCommand.php
app/src/Modules/Posts/Application/Command/UnlikeComment/UnlikeCommentHandler.php
app/src/Modules/Posts/Application/Command/LikePost/LikePostHandler.php
app/src/Modules/Posts/Application/Command/LikePost/LikePostCommand.php
app/src/Modules/Posts/Application/Command/CreatePost/CreatePostCommand.php
app/src/Modules/Posts/Application/Command/CreatePost/CreatePostHandler.php
app/src/Shared/Infrastructure/Configuration/User/UserConfig.php
app/src/Modules/Posts/Application/Command/RepostPost/RepostPostCommand.php
app/src/Modules/Posts/Application/Command/RepostPost/RepostPostHandler.php
app/src/Modules/Posts/Application/Command/UnlikePost/UnlikePostHandler.php
app/src/Modules/Posts/Application/Command/UnlikePost/UnlikePostCommand.php
app/src/Modules/Posts/Application/Command/PublishPost/PublishPostHandler.php
app/src/Modules/Posts/Application/Command/CommentPost/CommentPostHandler.php
app/src/Modules/Posts/Application/Command/CommentPost/CommentPostCommand.php
app/src/Modules/Posts/Application/Command/PublishPost/PublishPostCommand.php
app/src/Modules/Auth/Domain/ValueObject/UserAgent.php
app/src/Modules/Auth/Domain/ValueObject/KnownUserAgent.php
app/src/Modules/Auth/Domain/ValueObject/UnknownUserAgent.php
app/src/Modules/Posts/Application/Command/ReplyComment/ReplyCommentHandler.php
app/src/Modules/Posts/Application/Command/ReplyComment/ReplyCommentCommand.php
app/src/Modules/Auth/Application/Query/GetUserSessions/AuthSession.php
app/src/Modules/Auth/Application/Query/GetUserSessions/AuthSessionCollection.php
app/src/Modules/Auth/Application/Query/GetUserSessions/GetUserSessionsQuery.php
app/src/Modules/Auth/Application/Query/GetUserSessions/GetUserSessionsHandler.php
app/src/Modules/Posts/Application/Command/LikeComment/LikeCommentCommand.php
app/src/Modules/Posts/Application/Command/LikeComment/LikeCommentHandler.php
app/src/Modules/Posts/Application/Command/DeletePost/DeletePostHandler.php
app/src/Modules/Posts/Application/Command/DeletePost/DeletePostCommand.php
app/src/Modules/Posts/Application/Command/DeleteComment/DeleteCommentCommand.php
app/src/Modules/Posts/Application/Command/DeleteComment/DeleteCommentHandler.php
app/src/Modules/Auth/Infrastructure/Cycle/UserAgentTypecast.php
app/src/Modules/Auth/Application/Command/RevokeUserSession/RevokeUserSessionHandler.php
app/src/Modules/Auth/Application/Command/RevokeUserSession/RevokeUserSessionCommand.php
app/src/Modules/Auth/Infrastructure/Auth/UserActorProvider.php
app/src/Modules/Auth/Infrastructure/Auth/AuthenticatedUser.php

codex
Вижу, что target большой: 153 файла, но критичная часть для финального круга уже ограничена фиксом упоминаний, авторами во view и уведомлениями. Теперь сужаю проверку до этих мест и связанных тестов, чтобы не возвращать старые отклонённые optional-пункты без новых фактов.
collab: SpawnAgent
collab: SpawnAgent
collab: SpawnAgent
codex
Запустил три независимые проверки: план, архитектура/правила и качество кода. Пока они работают, сам читаю спорные участки и сверяю их с тестами, чтобы итог был не пересказом чужих выводов.
exec
/bin/zsh -lc "sed -n '1,260p' app/src/Modules/Posts/Application/Post/MentionRecipientResolver.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Post;

use App\Modules\User\Application\Dto\UserPublicProfileCollection;
use App\Modules\User\Application\Dto\UserPublicProfileView;
use App\Modules\User\Application\Query\CheckUsersExist\CheckUsersExistHandler;
use App\Modules\User\Application\Query\CheckUsersExist\CheckUsersExistQuery;
use App\Modules\User\Application\Query\GetUserPublicProfile\GetUserPublicProfileHandler;
use App\Modules\User\Application\Query\GetUserPublicProfile\GetUserPublicProfileQuery;
use App\Modules\User\Application\Query\GetUserPublicProfiles\GetUserPublicProfilesHandler;
use App\Modules\User\Application\Query\GetUserPublicProfiles\GetUserPublicProfilesQuery;
use App\Shared\Domain\Exception\ValidationException;
use GianTiaga\SpiralCqrs\QueryBusInterface;

/**
 * Разрешение получателей упоминаний и сборка профилей для сценариев записей и комментариев.
 * Сводит воедино две роли, которые раньше дублировались в обоих Composer-ах, и разводит их по
 * назначению:
 *
 * - строгий путь (валидация входного списка при создании): клиент прислал идентификаторы упоминаний,
 *   несуществующий -> 422. requireAllExist() — дешёвая проверка наличия (черновик, рассылки нет),
 *   resolveRequired() — та же проверка плюс полные профили получателей для немедленной рассылки;
 * - мягкий путь (рассылка по уже сохранённым упоминаниям): упоминания уже отвалидированы при
 *   создании, к моменту рассылки кого-то могло не стать -> недоступные тихо пропускаются без 422
 *   (resolveExisting()). Так публикация черновика не падает на строгой проверке полноты.
 *
 * Ключ перевода ошибки упоминания и правило проверки полноты живут здесь, в одном месте.
 */
final readonly class MentionRecipientResolver
{
    private const string MENTION_USER_NOT_FOUND_KEY = 'app.posts.mention_user_not_found';

    public function __construct(
        private QueryBusInterface $queryBus,
        private CheckUsersExistHandler $checkUsersExistHandler,
        private GetUserPublicProfileHandler $getUserPublicProfileHandler,
        private GetUserPublicProfilesHandler $getUserPublicProfilesHandler,
    ) {}

    /**
     * Строгая проверка существования входного списка упоминаний без сборки профилей. Несуществующий
     * упомянутый -> 422. Используется при создании черновика: упоминания валидируются, но рассылки
     * нет, поэтому полный профиль (с разрешением ссылки на аватар) собирать не нужно.
     *
     * @param list<string> $userIds
     */
    public function requireAllExist(array $userIds): void
    {
        $allExist = $this->queryBus->dispatch(
            query: new CheckUsersExistQuery($userIds),
            handler: $this->checkUsersExistHandler->handle(...),
        );

        if (!$allExist) {
            throw new ValidationException(self::MENTION_USER_NOT_FOUND_KEY);
        }
    }

    /**
     * Строгое разрешение профилей входного списка упоминаний для немедленной рассылки. Несуществующий
     * упомянутый -> 422 (валидация входа при создании опубликованной записи или комментария).
     *
     * @param list<string> $userIds
     */
    public function resolveRequired(array $userIds): UserPublicProfileCollection
    {
        if ($userIds === []) {
            return new UserPublicProfileCollection();
        }

        $recipients = $this->profiles($userIds);

        if ($recipients->count() !== \count($userIds)) {
            throw new ValidationException(self::MENTION_USER_NOT_FOUND_KEY);
        }

        return $recipients;
    }

    /**
     * Мягкое разрешение профилей по уже сохранённым упоминаниям: возвращает только существующих,
     * недоступных тихо пропускает без 422. Используется при публикации черновика, когда упоминания
     * уже были отвалидированы при создании.
     *
     * @param list<string> $userIds
     */
    public function resolveExisting(array $userIds): UserPublicProfileCollection
    {
        return $this->profiles($userIds);
    }

    public function profile(string $userId): UserPublicProfileView
    {
        return $this->queryBus->dispatch(
            query: new GetUserPublicProfileQuery($userId),
            handler: $this->getUserPublicProfileHandler->handle(...),
        );
    }

    /**
     * @param list<string> $userIds
     */
    private function profiles(array $userIds): UserPublicProfileCollection
    {
        return $this->queryBus->dispatch(
            query: new GetUserPublicProfilesQuery($userIds),
            handler: $this->getUserPublicProfilesHandler->handle(...),
        );
    }
}

exec
/bin/zsh -lc "find app/src/Modules/User/Application/Query/CheckUsersExist -type f -maxdepth 2 -print -exec sed -n '1,220p' {} \\;" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
app/src/Modules/User/Application/Query/CheckUsersExist/CheckUsersExistQuery.php
<?php

declare(strict_types=1);

namespace App\Modules\User\Application\Query\CheckUsersExist;

final readonly class CheckUsersExistQuery
{
    /**
     * @param list<string> $userIds
     */
    public function __construct(
        public array $userIds,
    ) {}
}
app/src/Modules/User/Application/Query/CheckUsersExist/CheckUsersExistHandler.php
<?php

declare(strict_types=1);

namespace App\Modules\User\Application\Query\CheckUsersExist;

use App\Modules\User\Repository\UserRepository;
use App\Shared\Domain\ValueObject\UserId;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;

/**
 * Проверка существования всех переданных пользователей по идентификаторам. Возвращает true только
 * если каждый запрошенный идентификатор найден. Дешевле GetUserPublicProfiles: считает строки в БД
 * и не собирает профиль с разрешением ссылки на аватар через Media. Нужна для валидации упоминаний
 * без рассылки (черновик), где полный профиль не используется.
 */
final readonly class CheckUsersExistHandler
{
    public function __construct(
        private UserRepository $userRepository,
    ) {}

    #[LogOperation]
    public function handle(CheckUsersExistQuery $query): bool
    {
        $userIds = \array_map(
            static fn(string $userId): UserId => UserId::fromString($userId),
            \array_values(\array_unique($query->userIds)),
        );

        if ($userIds === []) {
            return true;
        }

        return $this->userRepository->countByIds(...$userIds) === \count($userIds);
    }
}

exec
/bin/zsh -lc 'rg -n "countByIds|findPublicProfilesByIds|findByIds" app/src/Modules/User/Repository/UserRepository.php tests/Feature/Modules/User -C 6' in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
app/src/Modules/User/Repository/UserRepository.php-19-{
app/src/Modules/User/Repository/UserRepository.php-20-    public function findById(UserId $userId): User|null
app/src/Modules/User/Repository/UserRepository.php-21-    {
app/src/Modules/User/Repository/UserRepository.php-22-        return $this->findByPK($userId->value());
app/src/Modules/User/Repository/UserRepository.php-23-    }
app/src/Modules/User/Repository/UserRepository.php-24-
app/src/Modules/User/Repository/UserRepository.php:25:    public function findByIds(UserId ...$userIds): UserCollection
app/src/Modules/User/Repository/UserRepository.php-26-    {
app/src/Modules/User/Repository/UserRepository.php-27-        if ($userIds === []) {
app/src/Modules/User/Repository/UserRepository.php-28-            return new UserCollection();
app/src/Modules/User/Repository/UserRepository.php-29-        }
app/src/Modules/User/Repository/UserRepository.php-30-
app/src/Modules/User/Repository/UserRepository.php-31-        return new UserCollection(
--
app/src/Modules/User/Repository/UserRepository.php-35-                    $userIds,
app/src/Modules/User/Repository/UserRepository.php-36-                )))
app/src/Modules/User/Repository/UserRepository.php-37-                ->fetchAll(),
app/src/Modules/User/Repository/UserRepository.php-38-        );
app/src/Modules/User/Repository/UserRepository.php-39-    }
app/src/Modules/User/Repository/UserRepository.php-40-
app/src/Modules/User/Repository/UserRepository.php:41:    public function countByIds(UserId ...$userIds): int
app/src/Modules/User/Repository/UserRepository.php-42-    {
app/src/Modules/User/Repository/UserRepository.php-43-        if ($userIds === []) {
app/src/Modules/User/Repository/UserRepository.php-44-            return 0;
app/src/Modules/User/Repository/UserRepository.php-45-        }
app/src/Modules/User/Repository/UserRepository.php-46-
app/src/Modules/User/Repository/UserRepository.php-47-        return $this->select()
--
tests/Feature/Modules/User/Repository/UserRepositoryTest.php-98-
tests/Feature/Modules/User/Repository/UserRepositoryTest.php-99-        $this->entityManager()->persist($first);
tests/Feature/Modules/User/Repository/UserRepositoryTest.php-100-        $this->entityManager()->persist($second);
tests/Feature/Modules/User/Repository/UserRepositoryTest.php-101-        $this->entityManager()->run();
tests/Feature/Modules/User/Repository/UserRepositoryTest.php-102-        $this->cleanOrmHeap();
tests/Feature/Modules/User/Repository/UserRepositoryTest.php-103-
tests/Feature/Modules/User/Repository/UserRepositoryTest.php:104:        self::assertSame(0, $this->userRepository()->countByIds());
tests/Feature/Modules/User/Repository/UserRepositoryTest.php:105:        self::assertSame(2, $this->userRepository()->countByIds($first->id, $second->id));
tests/Feature/Modules/User/Repository/UserRepositoryTest.php:106:        self::assertSame(1, $this->userRepository()->countByIds($first->id, UserId::generate()));
tests/Feature/Modules/User/Repository/UserRepositoryTest.php:107:        self::assertCount(0, $this->userRepository()->findByIds());
tests/Feature/Modules/User/Repository/UserRepositoryTest.php-108-    }
tests/Feature/Modules/User/Repository/UserRepositoryTest.php-109-
tests/Feature/Modules/User/Repository/UserRepositoryTest.php-110-    public function testStoresAndFindsActiveUserBans(): void
tests/Feature/Modules/User/Repository/UserRepositoryTest.php-111-    {
tests/Feature/Modules/User/Repository/UserRepositoryTest.php-112-        $user = $this->createUser();
tests/Feature/Modules/User/Repository/UserRepositoryTest.php-113-        $permanentBan = UserBan::create(

exec
/bin/zsh -lc 'rg -n "requireAuthor|notifyPostMentions|MentionRecipientResolver|post_mention|POST_MENTION|resolveRequired|resolveExisting|requireAllExist" app/src tests -C 5' in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
tests/Feature/Modules/Posts/Http/PublishPostHttpTest.php-32-            'mentions' => [$mentioned->id->value()],
tests/Feature/Modules/Posts/Http/PublishPostHttpTest.php-33-        ]);
tests/Feature/Modules/Posts/Http/PublishPostHttpTest.php-34-        $createResponse->assertOk();
tests/Feature/Modules/Posts/Http/PublishPostHttpTest.php-35-        $postId = $this->json($createResponse)['data']['id'];
tests/Feature/Modules/Posts/Http/PublishPostHttpTest.php-36-
tests/Feature/Modules/Posts/Http/PublishPostHttpTest.php:37:        self::assertCount(0, $this->stagedNotifications('posts.post_mention'));
tests/Feature/Modules/Posts/Http/PublishPostHttpTest.php-38-
tests/Feature/Modules/Posts/Http/PublishPostHttpTest.php-39-        $this->authedJson('POST', \sprintf('/api/v1/posts/%s/publish', $postId), $author->id)->assertOk();
tests/Feature/Modules/Posts/Http/PublishPostHttpTest.php-40-
tests/Feature/Modules/Posts/Http/PublishPostHttpTest.php:41:        $mentions = $this->stagedNotifications('posts.post_mention');
tests/Feature/Modules/Posts/Http/PublishPostHttpTest.php-42-        self::assertCount(1, $mentions);
tests/Feature/Modules/Posts/Http/PublishPostHttpTest.php-43-        self::assertSame($mentioned->id->value(), $mentions[0]->userId);
tests/Feature/Modules/Posts/Http/PublishPostHttpTest.php-44-    }
tests/Feature/Modules/Posts/Http/PublishPostHttpTest.php-45-
tests/Feature/Modules/Posts/Http/PublishPostHttpTest.php-46-    public function testAlreadyPublishedIsIdempotentNoOp(): void
--
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php-130-            'mentions' => [$mentionedFirst->id->value(), $mentionedSecond->id->value()],
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php-131-        ]);
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php-132-
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php-133-        $response->assertOk();
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php-134-
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php:135:        $mentions = $this->stagedNotifications('posts.post_mention');
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php-136-        self::assertCount(2, $mentions);
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php-137-
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php-138-        $recipients = \array_map(static fn(object $message): string => $message->userId, $mentions);
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php-139-        self::assertContains($mentionedFirst->id->value(), $recipients);
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php-140-        self::assertContains($mentionedSecond->id->value(), $recipients);
--
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php-152-        ]);
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php-153-
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php-154-        $response->assertOk();
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php-155-        $postId = $this->json($response)['data']['id'];
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php-156-
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php:157:        self::assertCount(0, $this->stagedNotifications('posts.post_mention'));
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php-158-        self::assertCount(1, $this->postMentionRepository()->findByPostId(PostId::fromString($postId)));
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php-159-    }
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php-160-
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php-161-    public function testDoesNotNotifySelfMention(): void
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php-162-    {
--
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php-165-        $this->authedJson('POST', '/api/v1/posts', $user->id, [
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php-166-            'text' => 'Сам себя упомянул',
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php-167-            'mentions' => [$user->id->value()],
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php-168-        ])->assertOk();
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php-169-
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php:170:        self::assertCount(0, $this->stagedNotifications('posts.post_mention'));
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php-171-    }
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php-172-
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php-173-    public function testRejectsNonexistentMention(): void
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php-174-    {
tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php-175-        $user = $this->createUser();
--
app/src/Modules/Posts/Domain/Entity/PostMention.php-13-use Cycle\Annotated\Annotation\Column;
app/src/Modules/Posts/Domain/Entity/PostMention.php-14-use Cycle\Annotated\Annotation\Entity;
app/src/Modules/Posts/Domain/Entity/PostMention.php-15-use Cycle\ORM\Parser\Typecast;
app/src/Modules/Posts/Domain/Entity/PostMention.php-16-
app/src/Modules/Posts/Domain/Entity/PostMention.php-17-#[Entity(
app/src/Modules/Posts/Domain/Entity/PostMention.php:18:    role: 'post_mention',
app/src/Modules/Posts/Domain/Entity/PostMention.php:19:    table: 'post_mentions',
app/src/Modules/Posts/Domain/Entity/PostMention.php-20-    repository: PostMentionRepository::class,
app/src/Modules/Posts/Domain/Entity/PostMention.php-21-    typecast: [Typecast::class, ValueObjectCast::class],
app/src/Modules/Posts/Domain/Entity/PostMention.php-22-)]
app/src/Modules/Posts/Domain/Entity/PostMention.php-23-final class PostMention
app/src/Modules/Posts/Domain/Entity/PostMention.php-24-{
--
app/src/Modules/Posts/Application/View/PostViewAssembler.php-96-        foreach ($entities as $post) {
app/src/Modules/Posts/Application/View/PostViewAssembler.php-97-            $postId = $post->id->value();
app/src/Modules/Posts/Application/View/PostViewAssembler.php-98-            $originalId = $post->original->value();
app/src/Modules/Posts/Application/View/PostViewAssembler.php-99-            $views[] = $this->build(
app/src/Modules/Posts/Application/View/PostViewAssembler.php-100-                post: $post,
app/src/Modules/Posts/Application/View/PostViewAssembler.php:101:                author: $this->requireAuthor(authors: $authors, userId: $post->userId->value()),
app/src/Modules/Posts/Application/View/PostViewAssembler.php-102-                likedByMe: isset($likedPostIds[$postId]),
app/src/Modules/Posts/Application/View/PostViewAssembler.php-103-                media: $this->mediaItems($mediaByPost[$postId] ?? new PostMediaCollection()),
app/src/Modules/Posts/Application/View/PostViewAssembler.php-104-                tags: $this->tagViews(tags: $tagsByPost[$postId] ?? new PostTagCollection(), texts: $tagTexts),
app/src/Modules/Posts/Application/View/PostViewAssembler.php-105-                original: $originalId !== null ? ($originals[$originalId] ?? null) : null,
app/src/Modules/Posts/Application/View/PostViewAssembler.php-106-            );
--
app/src/Modules/Posts/Application/View/PostViewAssembler.php-193-     * поэтому отсутствие ключа обрабатываем явно — той же 404, что и одиночный путь
app/src/Modules/Posts/Application/View/PostViewAssembler.php-194-     * (fromPost -> GetUserPublicProfile), а не неконтролируемым undefined array key -> 500.
app/src/Modules/Posts/Application/View/PostViewAssembler.php-195-     *
app/src/Modules/Posts/Application/View/PostViewAssembler.php-196-     * @param array<string, AuthorView> $authors
app/src/Modules/Posts/Application/View/PostViewAssembler.php-197-     */
app/src/Modules/Posts/Application/View/PostViewAssembler.php:198:    private function requireAuthor(array $authors, string $userId): AuthorView
app/src/Modules/Posts/Application/View/PostViewAssembler.php-199-    {
app/src/Modules/Posts/Application/View/PostViewAssembler.php-200-        return $authors[$userId] ?? throw new NotFoundException('app.user.not_found');
app/src/Modules/Posts/Application/View/PostViewAssembler.php-201-    }
app/src/Modules/Posts/Application/View/PostViewAssembler.php-202-
app/src/Modules/Posts/Application/View/PostViewAssembler.php-203-    /**
--
app/src/Modules/Posts/Application/View/PostViewAssembler.php-442-
app/src/Modules/Posts/Application/View/PostViewAssembler.php-443-        foreach ($entities as $original) {
app/src/Modules/Posts/Application/View/PostViewAssembler.php-444-            $originalId = $original->id->value();
app/src/Modules/Posts/Application/View/PostViewAssembler.php-445-            $views[$originalId] = $this->build(
app/src/Modules/Posts/Application/View/PostViewAssembler.php-446-                post: $original,
app/src/Modules/Posts/Application/View/PostViewAssembler.php:447:                author: $this->requireAuthor(authors: $authors, userId: $original->userId->value()),
app/src/Modules/Posts/Application/View/PostViewAssembler.php-448-                likedByMe: isset($likedPostIds[$originalId]),
app/src/Modules/Posts/Application/View/PostViewAssembler.php-449-                media: $this->mediaItems($mediaByPost[$originalId] ?? new PostMediaCollection()),
app/src/Modules/Posts/Application/View/PostViewAssembler.php-450-                tags: $this->tagViews(tags: $tagsByPost[$originalId] ?? new PostTagCollection(), texts: $tagTexts),
app/src/Modules/Posts/Application/View/PostViewAssembler.php-451-                original: null,
app/src/Modules/Posts/Application/View/PostViewAssembler.php-452-            );
--
app/src/Modules/Posts/Application/View/CommentViewAssembler.php-55-        $views = [];
app/src/Modules/Posts/Application/View/CommentViewAssembler.php-56-
app/src/Modules/Posts/Application/View/CommentViewAssembler.php-57-        foreach ($entities as $comment) {
app/src/Modules/Posts/Application/View/CommentViewAssembler.php-58-            $views[] = $this->build(
app/src/Modules/Posts/Application/View/CommentViewAssembler.php-59-                comment: $comment,
app/src/Modules/Posts/Application/View/CommentViewAssembler.php:60:                author: $this->requireAuthor(authors: $authors, userId: $comment->userId->value()),
app/src/Modules/Posts/Application/View/CommentViewAssembler.php-61-                likedByMe: isset($likedCommentIds[$comment->id->value()]),
app/src/Modules/Posts/Application/View/CommentViewAssembler.php-62-            );
app/src/Modules/Posts/Application/View/CommentViewAssembler.php-63-        }
app/src/Modules/Posts/Application/View/CommentViewAssembler.php-64-
app/src/Modules/Posts/Application/View/CommentViewAssembler.php-65-        return $views;
--
app/src/Modules/Posts/Application/View/CommentViewAssembler.php-125-     * поэтому отсутствие ключа обрабатываем явно — той же 404, что и одиночный путь
app/src/Modules/Posts/Application/View/CommentViewAssembler.php-126-     * (fromComment -> GetUserPublicProfile), а не неконтролируемым undefined array key -> 500.
app/src/Modules/Posts/Application/View/CommentViewAssembler.php-127-     *
app/src/Modules/Posts/Application/View/CommentViewAssembler.php-128-     * @param array<string, AuthorView> $authors
app/src/Modules/Posts/Application/View/CommentViewAssembler.php-129-     */
app/src/Modules/Posts/Application/View/CommentViewAssembler.php:130:    private function requireAuthor(array $authors, string $userId): AuthorView
app/src/Modules/Posts/Application/View/CommentViewAssembler.php-131-    {
app/src/Modules/Posts/Application/View/CommentViewAssembler.php-132-        return $authors[$userId] ?? throw new NotFoundException('app.user.not_found');
app/src/Modules/Posts/Application/View/CommentViewAssembler.php-133-    }
app/src/Modules/Posts/Application/View/CommentViewAssembler.php-134-
app/src/Modules/Posts/Application/View/CommentViewAssembler.php-135-    /**
--
app/src/Modules/Posts/Application/Post/PostContentComposer.php-28-use GianTiaga\SpiralCqrs\QueryBusInterface;
app/src/Modules/Posts/Application/Post/PostContentComposer.php-29-
app/src/Modules/Posts/Application/Post/PostContentComposer.php-30-/**
app/src/Modules/Posts/Application/Post/PostContentComposer.php-31- * Общая сборка содержимого записи для сценариев создания и репоста: разрешение тегов, вложение
app/src/Modules/Posts/Application/Post/PostContentComposer.php-32- * медиа (проверка + перевод в permanent), теги и упоминания, а также стейджинг уведомлений
app/src/Modules/Posts/Application/Post/PostContentComposer.php:33: * post_mention/post_repost. Все вызовы смежных модулей идут внутри той же транзакции Handler-а
app/src/Modules/Posts/Application/Post/PostContentComposer.php-34- * (вложенный #[Transactional]-dispatch -> SAVEPOINT), persist выполняет этот сервис, а финальный
app/src/Modules/Posts/Application/Post/PostContentComposer.php-35- * run() — вызывающий Handler.
app/src/Modules/Posts/Application/Post/PostContentComposer.php-36- */
app/src/Modules/Posts/Application/Post/PostContentComposer.php-37-final readonly class PostContentComposer
app/src/Modules/Posts/Application/Post/PostContentComposer.php-38-{
--
app/src/Modules/Posts/Application/Post/PostContentComposer.php-40-        private CommandBusInterface $commandBus,
app/src/Modules/Posts/Application/Post/PostContentComposer.php-41-        private QueryBusInterface $queryBus,
app/src/Modules/Posts/Application/Post/PostContentComposer.php-42-        private ResolveTagsHandler $resolveTagsHandler,
app/src/Modules/Posts/Application/Post/PostContentComposer.php-43-        private CheckMediaAttachableHandler $checkMediaAttachableHandler,
app/src/Modules/Posts/Application/Post/PostContentComposer.php-44-        private MakeMediaPermanentHandler $makeMediaPermanentHandler,
app/src/Modules/Posts/Application/Post/PostContentComposer.php:45:        private MentionRecipientResolver $mentionRecipientResolver,
app/src/Modules/Posts/Application/Post/PostContentComposer.php-46-        private PostNotifier $postNotifier,
app/src/Modules/Posts/Application/Post/PostContentComposer.php-47-        private PostMentionRepository $postMentionRepository,
app/src/Modules/Posts/Application/Post/PostContentComposer.php-48-        private EntityManagerInterface $entityManager,
app/src/Modules/Posts/Application/Post/PostContentComposer.php-49-    ) {}
app/src/Modules/Posts/Application/Post/PostContentComposer.php-50-
--
app/src/Modules/Posts/Application/Post/PostContentComposer.php-103-            $this->entityManager->persist(PostTag::create(postId: $post->id, tagId: TagId::fromString($tagId)));
app/src/Modules/Posts/Application/Post/PostContentComposer.php-104-        }
app/src/Modules/Posts/Application/Post/PostContentComposer.php-105-    }
app/src/Modules/Posts/Application/Post/PostContentComposer.php-106-
app/src/Modules/Posts/Application/Post/PostContentComposer.php-107-    /**
app/src/Modules/Posts/Application/Post/PostContentComposer.php:108:     * Сохраняет упоминания записи и стейджит post_mention каждому упомянутому (кроме автора).
app/src/Modules/Posts/Application/Post/PostContentComposer.php-109-     * Дубликаты в списке схлопываются; несуществующий пользователь -> 422. Для черновика
app/src/Modules/Posts/Application/Post/PostContentComposer.php-110-     * упоминания только сохраняются, без рассылки уведомлений: чужой черновик невидим и deep-link
app/src/Modules/Posts/Application/Post/PostContentComposer.php:111:     * вёл бы в 404. Уведомления по сохранённым упоминаниям рассылает publish через notifyPostMentions.
app/src/Modules/Posts/Application/Post/PostContentComposer.php-112-     *
app/src/Modules/Posts/Application/Post/PostContentComposer.php-113-     * @param list<string> $mentionIds
app/src/Modules/Posts/Application/Post/PostContentComposer.php-114-     */
app/src/Modules/Posts/Application/Post/PostContentComposer.php-115-    public function attachPostMentions(Post $post, array $mentionIds, string $actorUserId): void
app/src/Modules/Posts/Application/Post/PostContentComposer.php-116-    {
--
app/src/Modules/Posts/Application/Post/PostContentComposer.php-120-            return;
app/src/Modules/Posts/Application/Post/PostContentComposer.php-121-        }
app/src/Modules/Posts/Application/Post/PostContentComposer.php-122-
app/src/Modules/Posts/Application/Post/PostContentComposer.php-123-        // Для черновика рассылки нет, поэтому достаточно дешёвой проверки существования без сборки
app/src/Modules/Posts/Application/Post/PostContentComposer.php-124-        // профилей с разрешением ссылки на аватар. Уведомления по сохранённым упоминаниям рассылает
app/src/Modules/Posts/Application/Post/PostContentComposer.php:125:        // publish через notifyPostMentions, когда запись станет видимой.
app/src/Modules/Posts/Application/Post/PostContentComposer.php-126-        if ($post->status === PostStatus::Draft) {
app/src/Modules/Posts/Application/Post/PostContentComposer.php:127:            $this->mentionRecipientResolver->requireAllExist($uniqueIds);
app/src/Modules/Posts/Application/Post/PostContentComposer.php-128-            $this->persistMentions(post: $post, mentionIds: $uniqueIds);
app/src/Modules/Posts/Application/Post/PostContentComposer.php-129-
app/src/Modules/Posts/Application/Post/PostContentComposer.php-130-            return;
app/src/Modules/Posts/Application/Post/PostContentComposer.php-131-        }
app/src/Modules/Posts/Application/Post/PostContentComposer.php-132-
app/src/Modules/Posts/Application/Post/PostContentComposer.php-133-        // Опубликованная запись: строгое разрешение профилей (проверка полноты -> 422) и сразу
app/src/Modules/Posts/Application/Post/PostContentComposer.php:134:        // рассылка post_mention существующим получателям.
app/src/Modules/Posts/Application/Post/PostContentComposer.php:135:        $recipients = $this->mentionRecipientResolver->resolveRequired($uniqueIds);
app/src/Modules/Posts/Application/Post/PostContentComposer.php-136-        $this->persistMentions(post: $post, mentionIds: $uniqueIds);
app/src/Modules/Posts/Application/Post/PostContentComposer.php-137-        $this->notifyMentions(post: $post, recipients: $recipients, actorUserId: $actorUserId);
app/src/Modules/Posts/Application/Post/PostContentComposer.php-138-    }
app/src/Modules/Posts/Application/Post/PostContentComposer.php-139-
app/src/Modules/Posts/Application/Post/PostContentComposer.php-140-    /**
--
app/src/Modules/Posts/Application/Post/PostContentComposer.php-146-            $this->entityManager->persist(PostMention::create(postId: $post->id, userId: UserId::fromString($mentionId)));
app/src/Modules/Posts/Application/Post/PostContentComposer.php-147-        }
app/src/Modules/Posts/Application/Post/PostContentComposer.php-148-    }
app/src/Modules/Posts/Application/Post/PostContentComposer.php-149-
app/src/Modules/Posts/Application/Post/PostContentComposer.php-150-    /**
app/src/Modules/Posts/Application/Post/PostContentComposer.php:151:     * Рассылает post_mention по уже сохранённым упоминаниям записи. Вызывается при публикации
app/src/Modules/Posts/Application/Post/PostContentComposer.php-152-     * черновика, чтобы упомянутые получили уведомление с рабочей ссылкой только после того, как
app/src/Modules/Posts/Application/Post/PostContentComposer.php-153-     * запись стала видимой.
app/src/Modules/Posts/Application/Post/PostContentComposer.php-154-     */
app/src/Modules/Posts/Application/Post/PostContentComposer.php:155:    public function notifyPostMentions(Post $post, string $actorUserId): void
app/src/Modules/Posts/Application/Post/PostContentComposer.php-156-    {
app/src/Modules/Posts/Application/Post/PostContentComposer.php-157-        /** @var list<string> $recipientIds */
app/src/Modules/Posts/Application/Post/PostContentComposer.php-158-        $recipientIds = $this->postMentionRepository->findByPostId($post->id)
app/src/Modules/Posts/Application/Post/PostContentComposer.php-159-            ->map(static fn(PostMention $postMention): string => $postMention->userId->value())
app/src/Modules/Posts/Application/Post/PostContentComposer.php-160-            ->values()
--
app/src/Modules/Posts/Application/Post/PostContentComposer.php-166-
app/src/Modules/Posts/Application/Post/PostContentComposer.php-167-        // Мягкий путь: упоминания уже отвалидированы при создании черновика, к моменту публикации
app/src/Modules/Posts/Application/Post/PostContentComposer.php-168-        // кого-то могло не стать -> недоступные тихо пропускаются, без строгой проверки полноты.
app/src/Modules/Posts/Application/Post/PostContentComposer.php-169-        $this->notifyMentions(
app/src/Modules/Posts/Application/Post/PostContentComposer.php-170-            post: $post,
app/src/Modules/Posts/Application/Post/PostContentComposer.php:171:            recipients: $this->mentionRecipientResolver->resolveExisting($recipientIds),
app/src/Modules/Posts/Application/Post/PostContentComposer.php-172-            actorUserId: $actorUserId,
app/src/Modules/Posts/Application/Post/PostContentComposer.php-173-        );
app/src/Modules/Posts/Application/Post/PostContentComposer.php-174-    }
app/src/Modules/Posts/Application/Post/PostContentComposer.php-175-
app/src/Modules/Posts/Application/Post/PostContentComposer.php-176-    private function notifyMentions(Post $post, UserPublicProfileCollection $recipients, string $actorUserId): void
--
app/src/Modules/Posts/Application/Post/CommentComposer.php-19- * комментария ведут на сам комментарий.
app/src/Modules/Posts/Application/Post/CommentComposer.php-20- */
app/src/Modules/Posts/Application/Post/CommentComposer.php-21-final readonly class CommentComposer
app/src/Modules/Posts/Application/Post/CommentComposer.php-22-{
app/src/Modules/Posts/Application/Post/CommentComposer.php-23-    public function __construct(
app/src/Modules/Posts/Application/Post/CommentComposer.php:24:        private MentionRecipientResolver $mentionRecipientResolver,
app/src/Modules/Posts/Application/Post/CommentComposer.php-25-        private PostNotifier $postNotifier,
app/src/Modules/Posts/Application/Post/CommentComposer.php-26-        private EntityManagerInterface $entityManager,
app/src/Modules/Posts/Application/Post/CommentComposer.php-27-    ) {}
app/src/Modules/Posts/Application/Post/CommentComposer.php-28-
app/src/Modules/Posts/Application/Post/CommentComposer.php-29-    /**
--
app/src/Modules/Posts/Application/Post/CommentComposer.php-44-
app/src/Modules/Posts/Application/Post/CommentComposer.php-45-        foreach ($uniqueMentions as $mentionId) {
app/src/Modules/Posts/Application/Post/CommentComposer.php-46-            $this->entityManager->persist(CommentMention::create(commentId: $comment->id, userId: UserId::fromString($mentionId)));
app/src/Modules/Posts/Application/Post/CommentComposer.php-47-        }
app/src/Modules/Posts/Application/Post/CommentComposer.php-48-
app/src/Modules/Posts/Application/Post/CommentComposer.php:49:        $mentionProfiles = $this->mentionRecipientResolver->resolveRequired($uniqueMentions);
app/src/Modules/Posts/Application/Post/CommentComposer.php-50-
app/src/Modules/Posts/Application/Post/CommentComposer.php-51-        $actor = $this->mentionRecipientResolver->profile($actorUserId);
app/src/Modules/Posts/Application/Post/CommentComposer.php-52-
app/src/Modules/Posts/Application/Post/CommentComposer.php-53-        /** @var array<string, CommentNotificationTarget> $targets */
app/src/Modules/Posts/Application/Post/CommentComposer.php-54-        $targets = [];
--
app/src/Modules/Posts/Application/Notification/PostNotificationType.php-15- * PostsBootloader. Enum реализует контракт NotificationTypeDefinition, поэтому живёт в Application
app/src/Modules/Posts/Application/Notification/PostNotificationType.php-16- * (его использует и Handler сборки контента, Application не должен зависеть от Infrastructure).
app/src/Modules/Posts/Application/Notification/PostNotificationType.php-17- */
app/src/Modules/Posts/Application/Notification/PostNotificationType.php-18-enum PostNotificationType: string implements NotificationTypeDefinition
app/src/Modules/Posts/Application/Notification/PostNotificationType.php-19-{
app/src/Modules/Posts/Application/Notification/PostNotificationType.php:20:    case PostMention = 'posts.post_mention';
app/src/Modules/Posts/Application/Notification/PostNotificationType.php-21-    case CommentMention = 'posts.comment_mention';
app/src/Modules/Posts/Application/Notification/PostNotificationType.php-22-    case PostCommented = 'posts.post_commented';
app/src/Modules/Posts/Application/Notification/PostNotificationType.php-23-    case CommentReply = 'posts.comment_reply';
app/src/Modules/Posts/Application/Notification/PostNotificationType.php-24-    case PostLike = 'posts.post_like';
app/src/Modules/Posts/Application/Notification/PostNotificationType.php-25-    case PostRepost = 'posts.post_repost';
--
app/src/Modules/Posts/Application/Post/MentionRecipientResolver.php-19- * Разрешение получателей упоминаний и сборка профилей для сценариев записей и комментариев.
app/src/Modules/Posts/Application/Post/MentionRecipientResolver.php-20- * Сводит воедино две роли, которые раньше дублировались в обоих Composer-ах, и разводит их по
app/src/Modules/Posts/Application/Post/MentionRecipientResolver.php-21- * назначению:
app/src/Modules/Posts/Application/Post/MentionRecipientResolver.php-22- *
app/src/Modules/Posts/Application/Post/MentionRecipientResolver.php-23- * - строгий путь (валидация входного списка при создании): клиент прислал идентификаторы упоминаний,
app/src/Modules/Posts/Application/Post/MentionRecipientResolver.php:24: *   несуществующий -> 422. requireAllExist() — дешёвая проверка наличия (черновик, рассылки нет),
app/src/Modules/Posts/Application/Post/MentionRecipientResolver.php:25: *   resolveRequired() — та же проверка плюс полные профили получателей для немедленной рассылки;
app/src/Modules/Posts/Application/Post/MentionRecipientResolver.php-26- * - мягкий путь (рассылка по уже сохранённым упоминаниям): упоминания уже отвалидированы при
app/src/Modules/Posts/Application/Post/MentionRecipientResolver.php-27- *   создании, к моменту рассылки кого-то могло не стать -> недоступные тихо пропускаются без 422
app/src/Modules/Posts/Application/Post/MentionRecipientResolver.php:28: *   (resolveExisting()). Так публикация черновика не падает на строгой проверке полноты.
app/src/Modules/Posts/Application/Post/MentionRecipientResolver.php-29- *
app/src/Modules/Posts/Application/Post/MentionRecipientResolver.php-30- * Ключ перевода ошибки упоминания и правило проверки полноты живут здесь, в одном месте.
app/src/Modules/Posts/Application/Post/MentionRecipientResolver.php-31- */
app/src/Modules/Posts/Application/Post/MentionRecipientResolver.php:32:final readonly class MentionRecipientResolver
app/src/Modules/Posts/Application/Post/MentionRecipientResolver.php-33-{
app/src/Modules/Posts/Application/Post/MentionRecipientResolver.php-34-    private const string MENTION_USER_NOT_FOUND_KEY = 'app.posts.mention_user_not_found';
app/src/Modules/Posts/Application/Post/MentionRecipientResolver.php-35-
app/src/Modules/Posts/Application/Post/MentionRecipientResolver.php-36-    public function __construct(
app/src/Modules/Posts/Application/Post/MentionRecipientResolver.php-37-        private QueryBusInterface $queryBus,
--
app/src/Modules/Posts/Application/Post/MentionRecipientResolver.php-45-     * упомянутый -> 422. Используется при создании черновика: упоминания валидируются, но рассылки
app/src/Modules/Posts/Application/Post/MentionRecipientResolver.php-46-     * нет, поэтому полный профиль (с разрешением ссылки на аватар) собирать не нужно.
app/src/Modules/Posts/Application/Post/MentionRecipientResolver.php-47-     *
app/src/Modules/Posts/Application/Post/MentionRecipientResolver.php-48-     * @param list<string> $userIds
app/src/Modules/Posts/Application/Post/MentionRecipientResolver.php-49-     */
app/src/Modules/Posts/Application/Post/MentionRecipientResolver.php:50:    public function requireAllExist(array $userIds): void
app/src/Modules/Posts/Application/Post/MentionRecipientResolver.php-51-    {
app/src/Modules/Posts/Application/Post/MentionRecipientResolver.php-52-        $allExist = $this->queryBus->dispatch(
app/src/Modules/Posts/Application/Post/MentionRecipientResolver.php-53-            query: new CheckUsersExistQuery($userIds),
app/src/Modules/Posts/Application/Post/MentionRecipientResolver.php-54-            handler: $this->checkUsersExistHandler->handle(...),
app/src/Modules/Posts/Application/Post/MentionRecipientResolver.php-55-        );
--
app/src/Modules/Posts/Application/Post/MentionRecipientResolver.php-63-     * Строгое разрешение профилей входного списка упоминаний для немедленной рассылки. Несуществующий
app/src/Modules/Posts/Application/Post/MentionRecipientResolver.php-64-     * упомянутый -> 422 (валидация входа при создании опубликованной записи или комментария).
app/src/Modules/Posts/Application/Post/MentionRecipientResolver.php-65-     *
app/src/Modules/Posts/Application/Post/MentionRecipientResolver.php-66-     * @param list<string> $userIds
app/src/Modules/Posts/Application/Post/MentionRecipientResolver.php-67-     */
app/src/Modules/Posts/Application/Post/MentionRecipientResolver.php:68:    public function resolveRequired(array $userIds): UserPublicProfileCollection
app/src/Modules/Posts/Application/Post/MentionRecipientResolver.php-69-    {
app/src/Modules/Posts/Application/Post/MentionRecipientResolver.php-70-        if ($userIds === []) {
app/src/Modules/Posts/Application/Post/MentionRecipientResolver.php-71-            return new UserPublicProfileCollection();
app/src/Modules/Posts/Application/Post/MentionRecipientResolver.php-72-        }
app/src/Modules/Posts/Application/Post/MentionRecipientResolver.php-73-
--
app/src/Modules/Posts/Application/Post/MentionRecipientResolver.php-85-     * недоступных тихо пропускает без 422. Используется при публикации черновика, когда упоминания
app/src/Modules/Posts/Application/Post/MentionRecipientResolver.php-86-     * уже были отвалидированы при создании.
app/src/Modules/Posts/Application/Post/MentionRecipientResolver.php-87-     *
app/src/Modules/Posts/Application/Post/MentionRecipientResolver.php-88-     * @param list<string> $userIds
app/src/Modules/Posts/Application/Post/MentionRecipientResolver.php-89-     */
app/src/Modules/Posts/Application/Post/MentionRecipientResolver.php:90:    public function resolveExisting(array $userIds): UserPublicProfileCollection
app/src/Modules/Posts/Application/Post/MentionRecipientResolver.php-91-    {
app/src/Modules/Posts/Application/Post/MentionRecipientResolver.php-92-        return $this->profiles($userIds);
app/src/Modules/Posts/Application/Post/MentionRecipientResolver.php-93-    }
app/src/Modules/Posts/Application/Post/MentionRecipientResolver.php-94-
app/src/Modules/Posts/Application/Post/MentionRecipientResolver.php-95-    public function profile(string $userId): UserPublicProfileView
--
app/src/Modules/Posts/Application/Command/PublishPost/PublishPostHandler.php-48-        }
app/src/Modules/Posts/Application/Command/PublishPost/PublishPostHandler.php-49-
app/src/Modules/Posts/Application/Command/PublishPost/PublishPostHandler.php-50-        if ($post->status === PostStatus::Draft) {
app/src/Modules/Posts/Application/Command/PublishPost/PublishPostHandler.php-51-            $post->publish();
app/src/Modules/Posts/Application/Command/PublishPost/PublishPostHandler.php-52-            $this->entityManager->persist($post);
app/src/Modules/Posts/Application/Command/PublishPost/PublishPostHandler.php:53:            $this->composer->notifyPostMentions(post: $post, actorUserId: $command->authUserId);
app/src/Modules/Posts/Application/Command/PublishPost/PublishPostHandler.php-54-            $this->entityManager->run();
app/src/Modules/Posts/Application/Command/PublishPost/PublishPostHandler.php-55-
app/src/Modules/Posts/Application/Command/PublishPost/PublishPostHandler.php-56-            $this->logger->debug(message: 'Запись опубликована.', context: ['postId' => $post->id->value()]);
app/src/Modules/Posts/Application/Command/PublishPost/PublishPostHandler.php-57-        }
app/src/Modules/Posts/Application/Command/PublishPost/PublishPostHandler.php-58-
--
app/src/Modules/Posts/Application/Command/CreatePost/CreatePostHandler.php-22-
app/src/Modules/Posts/Application/Command/CreatePost/CreatePostHandler.php-23-/**
app/src/Modules/Posts/Application/Command/CreatePost/CreatePostHandler.php-24- * Создание записи: сразу опубликованной или как черновик. Теги, вложение медиа (проверка + перевод
app/src/Modules/Posts/Application/Command/CreatePost/CreatePostHandler.php-25- * в permanent) и упоминания выполняются в той же транзакции (вложенный dispatch -> SAVEPOINT,
app/src/Modules/Posts/Application/Command/CreatePost/CreatePostHandler.php-26- * решение 17 — атомарно), поэтому при откате осиротевших тегов/медиа не возникает. Упомянутым
app/src/Modules/Posts/Application/Command/CreatePost/CreatePostHandler.php:27: * (кроме автора) стейджится post_mention.
app/src/Modules/Posts/Application/Command/CreatePost/CreatePostHandler.php-28- */
app/src/Modules/Posts/Application/Command/CreatePost/CreatePostHandler.php-29-final readonly class CreatePostHandler
app/src/Modules/Posts/Application/Command/CreatePost/CreatePostHandler.php-30-{
app/src/Modules/Posts/Application/Command/CreatePost/CreatePostHandler.php-31-    public function __construct(
app/src/Modules/Posts/Application/Command/CreatePost/CreatePostHandler.php-32-        private PostContentComposer $composer,

exec
/bin/zsh -lc "sed -n '1,260p' app/src/Modules/Posts/Application/Post/PostContentComposer.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
exec
/bin/zsh -lc "sed -n '1,180p' app/src/Modules/User/Repository/UserRepository.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\User\Repository;

use App\Modules\User\Domain\Collection\UserCollection;
use App\Modules\User\Domain\Entity\User;
use App\Modules\User\Domain\ValueObject\Email;
use App\Modules\User\Domain\ValueObject\UserNickname;
use App\Shared\Domain\ValueObject\UserId;
use Cycle\Database\Injection\Parameter;
use Cycle\ORM\Select\Repository;

/**
 * @extends Repository<User>
 */
final class UserRepository extends Repository
{
    public function findById(UserId $userId): User|null
    {
        return $this->findByPK($userId->value());
    }

    public function findByIds(UserId ...$userIds): UserCollection
    {
        if ($userIds === []) {
            return new UserCollection();
        }

        return new UserCollection(
            $this->select()
                ->where('id', 'in', new Parameter(\array_map(
                    static fn(UserId $userId): string => $userId->value(),
                    $userIds,
                )))
                ->fetchAll(),
        );
    }

    public function countByIds(UserId ...$userIds): int
    {
        if ($userIds === []) {
            return 0;
        }

        return $this->select()
            ->where('id', 'in', new Parameter(\array_map(
                static fn(UserId $userId): string => $userId->value(),
                $userIds,
            )))
            ->count();
    }

    public function findByEmail(Email $email): User|null
    {
        return $this->findOne(['email' => $email->value()]);
    }

    public function findByNickname(UserNickname $nickname): User|null
    {
        return $this->findOne(['nickname' => $nickname->value()]);
    }

    public function existsByEmail(Email $email): bool
    {
        return $this->findByEmail($email) !== null;
    }

    public function existsByNickname(UserNickname $nickname): bool
    {
        return $this->findByNickname($nickname) !== null;
    }
}

 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Post;

use App\Modules\Media\Application\Command\MakeMediaPermanent\MakeMediaPermanentCommand;
use App\Modules\Media\Application\Command\MakeMediaPermanent\MakeMediaPermanentHandler;
use App\Modules\Media\Application\Query\CheckMediaAttachable\CheckMediaAttachableHandler;
use App\Modules\Media\Application\Query\CheckMediaAttachable\CheckMediaAttachableQuery;
use App\Modules\Posts\Application\Notification\PostNotificationType;
use App\Modules\Posts\Application\Notification\PostNotifier;
use App\Modules\Posts\Domain\Entity\Post;
use App\Modules\Posts\Domain\Entity\PostMedia;
use App\Modules\Posts\Domain\Entity\PostMention;
use App\Modules\Posts\Domain\Entity\PostTag;
use App\Modules\Posts\Domain\Enum\PostStatus;
use App\Modules\Posts\Domain\ValueObject\MediaPosition;
use App\Modules\Posts\Domain\ValueObject\PostMediaReference;
use App\Modules\Posts\Repository\PostMentionRepository;
use App\Modules\Tags\Application\Command\ResolveTags\ResolveTagsCommand;
use App\Modules\Tags\Application\Command\ResolveTags\ResolveTagsHandler;
use App\Modules\User\Application\Dto\UserPublicProfileCollection;
use App\Shared\Domain\ValueObject\TagId;
use App\Shared\Domain\ValueObject\UserId;
use Cycle\ORM\EntityManagerInterface;
use GianTiaga\SpiralCqrs\CommandBusInterface;
use GianTiaga\SpiralCqrs\QueryBusInterface;

/**
 * Общая сборка содержимого записи для сценариев создания и репоста: разрешение тегов, вложение
 * медиа (проверка + перевод в permanent), теги и упоминания, а также стейджинг уведомлений
 * post_mention/post_repost. Все вызовы смежных модулей идут внутри той же транзакции Handler-а
 * (вложенный #[Transactional]-dispatch -> SAVEPOINT), persist выполняет этот сервис, а финальный
 * run() — вызывающий Handler.
 */
final readonly class PostContentComposer
{
    public function __construct(
        private CommandBusInterface $commandBus,
        private QueryBusInterface $queryBus,
        private ResolveTagsHandler $resolveTagsHandler,
        private CheckMediaAttachableHandler $checkMediaAttachableHandler,
        private MakeMediaPermanentHandler $makeMediaPermanentHandler,
        private MentionRecipientResolver $mentionRecipientResolver,
        private PostNotifier $postNotifier,
        private PostMentionRepository $postMentionRepository,
        private EntityManagerInterface $entityManager,
    ) {}

    /**
     * @param list<string> $texts
     *
     * @return list<string>
     */
    public function resolveTags(array $texts, string $creatorUserId): array
    {
        if ($texts === []) {
            return [];
        }

        return $this->commandBus->dispatch(
            command: new ResolveTagsCommand(texts: $texts, creatorUserId: $creatorUserId),
            handler: $this->resolveTagsHandler->handle(...),
        )->tagIds;
    }

    /**
     * Проверяет каждое медиа (существование, владелец, готовность), переводит в permanent и
     * сохраняет строки вложения. Дубликаты в списке схлопываются (уникальный индекс
     * (post_id, media_id)). Порядок вложений — позиция по порядку в списке.
     *
     * @param list<string> $mediaIds
     */
    public function attachMedia(Post $post, array $mediaIds, string $ownerUserId): void
    {
        $position = 0;

        foreach (\array_values(\array_unique($mediaIds)) as $mediaId) {
            $this->queryBus->dispatch(
                query: new CheckMediaAttachableQuery(mediaId: $mediaId, ownerUserId: $ownerUserId),
                handler: $this->checkMediaAttachableHandler->handle(...),
            );
            $this->commandBus->dispatch(
                command: new MakeMediaPermanentCommand(userId: $ownerUserId, mediaId: $mediaId),
                handler: $this->makeMediaPermanentHandler->handle(...),
            );
            $this->entityManager->persist(PostMedia::create(
                post: $post,
                media: PostMediaReference::fromString($mediaId),
                position: MediaPosition::fromInt($position),
            ));
            $position++;
        }
    }

    /**
     * @param list<string> $tagIds
     */
    public function attachTags(Post $post, array $tagIds): void
    {
        foreach ($tagIds as $tagId) {
            $this->entityManager->persist(PostTag::create(postId: $post->id, tagId: TagId::fromString($tagId)));
        }
    }

    /**
     * Сохраняет упоминания записи и стейджит post_mention каждому упомянутому (кроме автора).
     * Дубликаты в списке схлопываются; несуществующий пользователь -> 422. Для черновика
     * упоминания только сохраняются, без рассылки уведомлений: чужой черновик невидим и deep-link
     * вёл бы в 404. Уведомления по сохранённым упоминаниям рассылает publish через notifyPostMentions.
     *
     * @param list<string> $mentionIds
     */
    public function attachPostMentions(Post $post, array $mentionIds, string $actorUserId): void
    {
        $uniqueIds = \array_values(\array_unique($mentionIds));

        if ($uniqueIds === []) {
            return;
        }

        // Для черновика рассылки нет, поэтому достаточно дешёвой проверки существования без сборки
        // профилей с разрешением ссылки на аватар. Уведомления по сохранённым упоминаниям рассылает
        // publish через notifyPostMentions, когда запись станет видимой.
        if ($post->status === PostStatus::Draft) {
            $this->mentionRecipientResolver->requireAllExist($uniqueIds);
            $this->persistMentions(post: $post, mentionIds: $uniqueIds);

            return;
        }

        // Опубликованная запись: строгое разрешение профилей (проверка полноты -> 422) и сразу
        // рассылка post_mention существующим получателям.
        $recipients = $this->mentionRecipientResolver->resolveRequired($uniqueIds);
        $this->persistMentions(post: $post, mentionIds: $uniqueIds);
        $this->notifyMentions(post: $post, recipients: $recipients, actorUserId: $actorUserId);
    }

    /**
     * @param list<string> $mentionIds
     */
    private function persistMentions(Post $post, array $mentionIds): void
    {
        foreach ($mentionIds as $mentionId) {
            $this->entityManager->persist(PostMention::create(postId: $post->id, userId: UserId::fromString($mentionId)));
        }
    }

    /**
     * Рассылает post_mention по уже сохранённым упоминаниям записи. Вызывается при публикации
     * черновика, чтобы упомянутые получили уведомление с рабочей ссылкой только после того, как
     * запись стала видимой.
     */
    public function notifyPostMentions(Post $post, string $actorUserId): void
    {
        /** @var list<string> $recipientIds */
        $recipientIds = $this->postMentionRepository->findByPostId($post->id)
            ->map(static fn(PostMention $postMention): string => $postMention->userId->value())
            ->values()
            ->all();

        if ($recipientIds === []) {
            return;
        }

        // Мягкий путь: упоминания уже отвалидированы при создании черновика, к моменту публикации
        // кого-то могло не стать -> недоступные тихо пропускаются, без строгой проверки полноты.
        $this->notifyMentions(
            post: $post,
            recipients: $this->mentionRecipientResolver->resolveExisting($recipientIds),
            actorUserId: $actorUserId,
        );
    }

    private function notifyMentions(Post $post, UserPublicProfileCollection $recipients, string $actorUserId): void
    {
        $actor = $this->mentionRecipientResolver->profile($actorUserId);

        foreach ($recipients as $recipient) {
            $this->postNotifier->notify(
                type: PostNotificationType::PostMention,
                actor: $actor,
                recipient: $recipient,
                actionType: 'post',
                actionId: $post->id->value(),
            );
        }
    }

    /**
     * Стейджит уведомление автору записи о действии над ней (репост, лайк). Самодействие
     * (автор == инициатор) не уведомляет.
     */
    public function notifyPostAuthor(
        PostNotificationType $type,
        UserId $postAuthor,
        string $actorUserId,
        string $postId,
    ): void {
        if ($postAuthor->value() === $actorUserId) {
            return;
        }

        $this->postNotifier->notify(
            type: $type,
            actor: $this->mentionRecipientResolver->profile($actorUserId),
            recipient: $this->mentionRecipientResolver->profile($postAuthor->value()),
            actionType: 'post',
            actionId: $postId,
        );
    }
}

exec
/bin/zsh -lc "sed -n '1,140p' app/src/Modules/Posts/Application/Command/PublishPost/PublishPostHandler.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Command\PublishPost;

use App\Modules\Posts\Application\Post\PostContentComposer;
use App\Modules\Posts\Application\View\PostView;
use App\Modules\Posts\Application\View\PostViewAssembler;
use App\Modules\Posts\Domain\Enum\PostStatus;
use App\Modules\Posts\Domain\ValueObject\PostId;
use App\Modules\Posts\Repository\PostRepository;
use App\Shared\Domain\Exception\ForbiddenException;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\ValueObject\UserId;
use Cycle\ORM\EntityManagerInterface;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;
use GianTiaga\SpiralCqrs\Attribute\Transactional;
use Psr\Log\LoggerInterface;

/**
 * Публикация черновика владельцем. Удалённая/заблокированная/несуществующая запись -> 404, чужая
 * -> 403, уже опубликованная -> идемпотентный успешный no-op.
 */
final readonly class PublishPostHandler
{
    public function __construct(
        private PostRepository $postRepository,
        private PostContentComposer $composer,
        private PostViewAssembler $postViewAssembler,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}

    #[Transactional]
    #[LogOperation]
    public function handle(PublishPostCommand $command): PostView
    {
        $post = $this->postRepository->findById(PostId::fromString($command->postId))
            ?? throw new NotFoundException('app.posts.not_found');

        if ($post->deletion->isDeleted() || $post->status === PostStatus::Blocked) {
            throw new NotFoundException('app.posts.not_found');
        }

        if (!$post->userId->equals(UserId::fromString($command->authUserId))) {
            throw new ForbiddenException('app.posts.forbidden');
        }

        if ($post->status === PostStatus::Draft) {
            $post->publish();
            $this->entityManager->persist($post);
            $this->composer->notifyPostMentions(post: $post, actorUserId: $command->authUserId);
            $this->entityManager->run();

            $this->logger->debug(message: 'Запись опубликована.', context: ['postId' => $post->id->value()]);
        }

        return $this->postViewAssembler->fromPost(post: $post, viewer: UserId::fromString($command->authUserId));
    }
}

exec
/bin/zsh -lc "sed -n '1,220p' app/src/Modules/Posts/Application/Post/CommentComposer.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Post;

use App\Modules\Posts\Application\Notification\CommentNotificationTarget;
use App\Modules\Posts\Application\Notification\PostNotificationType;
use App\Modules\Posts\Application\Notification\PostNotifier;
use App\Modules\Posts\Domain\Entity\Comment;
use App\Modules\Posts\Domain\Entity\CommentMention;
use App\Shared\Domain\ValueObject\UserId;
use Cycle\ORM\EntityManagerInterface;

/**
 * Сборка содержимого комментария: сохранение упоминаний и стейджинг уведомлений с дедупликацией по
 * получателю. На одного получателя уходит ровно одно уведомление с наибольшим приоритетом
 * (mention > comment_reply > post_commented), self-получатель исключается. Все deep-link
 * комментария ведут на сам комментарий.
 */
final readonly class CommentComposer
{
    public function __construct(
        private MentionRecipientResolver $mentionRecipientResolver,
        private PostNotifier $postNotifier,
        private EntityManagerInterface $entityManager,
    ) {}

    /**
     * Сохраняет упоминания комментария и стейджит уведомления. primaryRecipient — автор записи
     * (для post_commented у комментария верхнего уровня) либо автор родителя (для comment_reply у
     * ответа); его вид задаёт primaryType. Упоминание перебивает primary при совпадении получателя.
     *
     * @param list<string> $mentionIds
     */
    public function attachMentionsAndNotify(
        Comment $comment,
        array $mentionIds,
        string $actorUserId,
        string $primaryRecipientUserId,
        PostNotificationType $primaryType,
    ): void {
        $uniqueMentions = \array_values(\array_unique($mentionIds));

        foreach ($uniqueMentions as $mentionId) {
            $this->entityManager->persist(CommentMention::create(commentId: $comment->id, userId: UserId::fromString($mentionId)));
        }

        $mentionProfiles = $this->mentionRecipientResolver->resolveRequired($uniqueMentions);

        $actor = $this->mentionRecipientResolver->profile($actorUserId);

        /** @var array<string, CommentNotificationTarget> $targets */
        $targets = [];

        foreach ($mentionProfiles as $mentionProfile) {
            $targets[$mentionProfile->userId] = new CommentNotificationTarget(
                type: PostNotificationType::CommentMention,
                recipient: $mentionProfile,
            );
        }

        // Упоминание (приоритет выше) перебивает primary: добавляем primary только если получатель
        // ещё не охвачен упоминанием.
        if (!isset($targets[$primaryRecipientUserId])) {
            $targets[$primaryRecipientUserId] = new CommentNotificationTarget(
                type: $primaryType,
                recipient: $this->mentionRecipientResolver->profile($primaryRecipientUserId),
            );
        }

        foreach ($targets as $target) {
            $this->postNotifier->notify(
                type: $target->type,
                actor: $actor,
                recipient: $target->recipient,
                actionType: 'comment',
                actionId: $comment->id->value(),
            );
        }
    }

    /**
     * Стейджит уведомление автору комментария о реакции на него (comment_like). Самодействие не
     * уведомляет.
     */
    public function notifyCommentAuthor(
        PostNotificationType $type,
        UserId $commentAuthor,
        string $actorUserId,
        string $commentId,
    ): void {
        if ($commentAuthor->value() === $actorUserId) {
            return;
        }

        $this->postNotifier->notify(
            type: $type,
            actor: $this->mentionRecipientResolver->profile($actorUserId),
            recipient: $this->mentionRecipientResolver->profile($commentAuthor->value()),
            actionType: 'comment',
            actionId: $commentId,
        );
    }
}

exec
/bin/zsh -lc "sed -n '1,220p' app/src/Modules/Posts/Application/Notification/PostNotifier.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
exec
/bin/zsh -lc "sed -n '130,190p' tests/Feature/Modules/Posts/Http/CreatePostHttpTest.php && sed -n '1,80p' tests/Feature/Modules/Posts/Http/PublishPostHttpTest.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Notification;

use App\Modules\Notifications\Application\Contract\NotificationSenderContract;
use App\Modules\User\Application\Dto\UserPublicProfileView;
use App\Shared\Domain\ValueObject\UserId;
use Psr\Log\LoggerInterface;

/**
 * Стейджит одно уведомление модуля Posts получателю. Самодействие не уведомляет
 * (recipient == actor -> send() не вызывается). Текст рендерится в локали получателя через
 * NotificationContentBuilder, отправка — через NotificationSenderContract (стейджинг в outbox,
 * flush делает вызывающий Handler своим run()).
 */
final readonly class PostNotifier
{
    public function __construct(
        private NotificationSenderContract $notificationSender,
        private NotificationContentBuilder $contentBuilder,
        private LoggerInterface $logger,
    ) {}

    public function notify(
        PostNotificationType $type,
        UserPublicProfileView $actor,
        UserPublicProfileView $recipient,
        string $actionType,
        string $actionId,
    ): void {
        if ($actor->userId === $recipient->userId) {
            return;
        }

        $this->notificationSender->send(
            recipient: UserId::fromString($recipient->userId),
            content: $this->contentBuilder->build(
                type: $type,
                actorUserId: UserId::fromString($actor->userId),
                actorName: $actor->name,
                actorAvatarUrl: $actor->avatarUrl,
                recipientLocale: $recipient->locale,
                actionType: $actionType,
                actionId: $actionId,
            ),
        );

        $this->logger->debug(message: 'Уведомление поставлено в очередь.', context: [
            'typeCode' => $type->value,
            'recipientId' => $recipient->userId,
            'actionId' => $actionId,
        ]);
    }
}

 succeeded in 0ms:
            'mentions' => [$mentionedFirst->id->value(), $mentionedSecond->id->value()],
        ]);

        $response->assertOk();

        $mentions = $this->stagedNotifications('posts.post_mention');
        self::assertCount(2, $mentions);

        $recipients = \array_map(static fn(object $message): string => $message->userId, $mentions);
        self::assertContains($mentionedFirst->id->value(), $recipients);
        self::assertContains($mentionedSecond->id->value(), $recipients);
    }

    public function testDraftMentionsArePersistedButNotNotified(): void
    {
        $author = $this->createUser();
        $mentioned = $this->createUser();

        $response = $this->authedJson('POST', '/api/v1/posts', $author->id, [
            'text' => 'Черновик с упоминанием',
            'draft' => true,
            'mentions' => [$mentioned->id->value()],
        ]);

        $response->assertOk();
        $postId = $this->json($response)['data']['id'];

        self::assertCount(0, $this->stagedNotifications('posts.post_mention'));
        self::assertCount(1, $this->postMentionRepository()->findByPostId(PostId::fromString($postId)));
    }

    public function testDoesNotNotifySelfMention(): void
    {
        $user = $this->createUser();

        $this->authedJson('POST', '/api/v1/posts', $user->id, [
            'text' => 'Сам себя упомянул',
            'mentions' => [$user->id->value()],
        ])->assertOk();

        self::assertCount(0, $this->stagedNotifications('posts.post_mention'));
    }

    public function testRejectsNonexistentMention(): void
    {
        $user = $this->createUser();

        $this->authedJson('POST', '/api/v1/posts', $user->id, [
            'mentions' => [UserId::generate()->value()],
        ])->assertUnprocessable();
    }

    public function testRejectsNonexistentMentionInDraft(): void
    {
        $user = $this->createUser();

        $this->authedJson('POST', '/api/v1/posts', $user->id, [
            'text' => 'Черновик с несуществующим упоминанием',
            'draft' => true,
            'mentions' => [UserId::generate()->value()],
        ])->assertUnprocessable();
<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Posts\Http;

use App\Modules\Posts\Domain\Enum\PostStatus;
use App\Shared\Domain\ValueObject\UserId;

final class PublishPostHttpTest extends PostsHttpTestCase
{
    public function testPublishesOwnDraft(): void
    {
        $user = $this->createUser();
        $post = $this->persistPost($user->id, PostStatus::Draft);

        $response = $this->authedJson('POST', \sprintf('/api/v1/posts/%s/publish', $post->id->value()), $user->id);

        $response->assertOk();
        self::assertSame('published', $this->json($response)['data']['status']);
        self::assertSame(PostStatus::Published, $this->reloadPost($post)->status);
    }

    public function testPublishingDraftNotifiesMentionedUsers(): void
    {
        $author = $this->createUser();
        $mentioned = $this->createUser();

        $createResponse = $this->authedJson('POST', '/api/v1/posts', $author->id, [
            'text' => 'Черновик с упоминанием',
            'draft' => true,
            'mentions' => [$mentioned->id->value()],
        ]);
        $createResponse->assertOk();
        $postId = $this->json($createResponse)['data']['id'];

        self::assertCount(0, $this->stagedNotifications('posts.post_mention'));

        $this->authedJson('POST', \sprintf('/api/v1/posts/%s/publish', $postId), $author->id)->assertOk();

        $mentions = $this->stagedNotifications('posts.post_mention');
        self::assertCount(1, $mentions);
        self::assertSame($mentioned->id->value(), $mentions[0]->userId);
    }

    public function testAlreadyPublishedIsIdempotentNoOp(): void
    {
        $user = $this->createUser();
        $post = $this->persistPost($user->id, PostStatus::Published);

        $response = $this->authedJson('POST', \sprintf('/api/v1/posts/%s/publish', $post->id->value()), $user->id);

        $response->assertOk();
        self::assertSame('published', $this->json($response)['data']['status']);
    }

    public function testRejectsPublishByNonOwner(): void
    {
        $owner = $this->createUser();
        $stranger = $this->createUser();
        $post = $this->persistPost($owner->id, PostStatus::Draft);

        $this->authedJson('POST', \sprintf('/api/v1/posts/%s/publish', $post->id->value()), $stranger->id)
            ->assertStatus(403);
    }

    public function testRejectsPublishOfDeletedPost(): void
    {
        $user = $this->createUser();
        $post = $this->persistPost($user->id, PostStatus::Draft, deleted: true);

        $this->authedJson('POST', \sprintf('/api/v1/posts/%s/publish', $post->id->value()), $user->id)
            ->assertNotFound();
    }

    public function testRejectsPublishOfBlockedPost(): void
    {
        $user = $this->createUser();
        $post = $this->persistPost($user->id, PostStatus::Blocked);


exec
/bin/zsh -lc "sed -n '1,140p' app/src/Modules/User/Application/Query/GetUserPublicProfiles/GetUserPublicProfilesHandler.php && sed -n '1,120p' app/src/Modules/User/Application/Dto/UserPublicProfileCollection.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\User\Application\Query\GetUserPublicProfiles;

use App\Modules\User\Application\Dto\UserPublicProfileCollection;
use App\Modules\User\Application\Dto\UserPublicProfileView;
use App\Modules\User\Application\Profile\UserPublicProfileAssembler;
use App\Modules\User\Domain\Collection\UserCollection;
use App\Modules\User\Domain\Entity\User;
use App\Modules\User\Repository\UserRepository;
use App\Shared\Domain\ValueObject\UserId;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;

/**
 * Публичные профили нескольких пользователей (авторы ленты/комментариев, проверка упоминаний).
 * Несуществующие идентификаторы просто отсутствуют в результате — вызывающий сам сверяет
 * запрошенные и найденные, чтобы при необходимости вернуть 422 на несуществующее упоминание.
 */
final readonly class GetUserPublicProfilesHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private UserPublicProfileAssembler $assembler,
    ) {}

    #[LogOperation]
    public function handle(GetUserPublicProfilesQuery $query): UserPublicProfileCollection
    {
        $userIds = \array_map(
            static fn(string $userId): UserId => UserId::fromString($userId),
            $query->userIds,
        );

        /** @var UserCollection $users */
        $users = $this->userRepository->findByIds(...$userIds);

        /** @var array<int, UserPublicProfileView> $profiles */
        $profiles = $users
            ->map(fn(User $user): UserPublicProfileView => $this->assembler->fromUser($user))
            ->all();

        return new UserPublicProfileCollection($profiles);
    }
}
<?php

declare(strict_types=1);

namespace App\Modules\User\Application\Dto;

use Illuminate\Support\Collection;

/**
 * @extends Collection<int, UserPublicProfileView>
 */
final class UserPublicProfileCollection extends Collection {}

exec
/bin/zsh -lc "rg -n \"function count\\(|class .*Collection|map\\(|values\\(|all\\(\" app/src/Modules/User app/src/Modules/Posts/Domain/Collection app/src/Shared -g '*Collection.php' -C 3" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
app/src/Modules/Posts/Domain/Collection/CommentMentionCollection.php-10-/**
app/src/Modules/Posts/Domain/Collection/CommentMentionCollection.php-11- * @extends Collection<int, CommentMention>
app/src/Modules/Posts/Domain/Collection/CommentMentionCollection.php-12- */
app/src/Modules/Posts/Domain/Collection/CommentMentionCollection.php:13:final class CommentMentionCollection extends Collection {}
--
app/src/Modules/Posts/Domain/Collection/PostBlockCollection.php-10-/**
app/src/Modules/Posts/Domain/Collection/PostBlockCollection.php-11- * @extends Collection<int, PostBlock>
app/src/Modules/Posts/Domain/Collection/PostBlockCollection.php-12- */
app/src/Modules/Posts/Domain/Collection/PostBlockCollection.php:13:final class PostBlockCollection extends Collection {}
--
app/src/Modules/Posts/Domain/Collection/PostMediaCollection.php-10-/**
app/src/Modules/Posts/Domain/Collection/PostMediaCollection.php-11- * @extends Collection<int, PostMedia>
app/src/Modules/Posts/Domain/Collection/PostMediaCollection.php-12- */
app/src/Modules/Posts/Domain/Collection/PostMediaCollection.php:13:final class PostMediaCollection extends Collection {}
--
app/src/Modules/User/Domain/Collection/UserCollection.php-10-/**
app/src/Modules/User/Domain/Collection/UserCollection.php-11- * @extends Collection<int, User>
app/src/Modules/User/Domain/Collection/UserCollection.php-12- */
app/src/Modules/User/Domain/Collection/UserCollection.php:13:final class UserCollection extends Collection {}
--
app/src/Modules/Posts/Domain/Collection/PostMentionCollection.php-10-/**
app/src/Modules/Posts/Domain/Collection/PostMentionCollection.php-11- * @extends Collection<int, PostMention>
app/src/Modules/Posts/Domain/Collection/PostMentionCollection.php-12- */
app/src/Modules/Posts/Domain/Collection/PostMentionCollection.php:13:final class PostMentionCollection extends Collection {}
--
app/src/Modules/Posts/Domain/Collection/PostTagCollection.php-10-/**
app/src/Modules/Posts/Domain/Collection/PostTagCollection.php-11- * @extends Collection<int, PostTag>
app/src/Modules/Posts/Domain/Collection/PostTagCollection.php-12- */
app/src/Modules/Posts/Domain/Collection/PostTagCollection.php:13:final class PostTagCollection extends Collection {}
--
app/src/Modules/Posts/Domain/Collection/PostLikeCollection.php-10-/**
app/src/Modules/Posts/Domain/Collection/PostLikeCollection.php-11- * @extends Collection<int, PostLike>
app/src/Modules/Posts/Domain/Collection/PostLikeCollection.php-12- */
app/src/Modules/Posts/Domain/Collection/PostLikeCollection.php:13:final class PostLikeCollection extends Collection {}
--
app/src/Modules/Posts/Domain/Collection/CommentCollection.php-10-/**
app/src/Modules/Posts/Domain/Collection/CommentCollection.php-11- * @extends Collection<int, Comment>
app/src/Modules/Posts/Domain/Collection/CommentCollection.php-12- */
app/src/Modules/Posts/Domain/Collection/CommentCollection.php:13:final class CommentCollection extends Collection {}
--
app/src/Modules/Posts/Domain/Collection/CommentLikeCollection.php-10-/**
app/src/Modules/Posts/Domain/Collection/CommentLikeCollection.php-11- * @extends Collection<int, CommentLike>
app/src/Modules/Posts/Domain/Collection/CommentLikeCollection.php-12- */
app/src/Modules/Posts/Domain/Collection/CommentLikeCollection.php:13:final class CommentLikeCollection extends Collection {}
--
app/src/Shared/Infrastructure/Cycle/LazyGhostPendingRelationReferenceCollection.php-4-
app/src/Shared/Infrastructure/Cycle/LazyGhostPendingRelationReferenceCollection.php-5-namespace App\Shared\Infrastructure\Cycle;
app/src/Shared/Infrastructure/Cycle/LazyGhostPendingRelationReferenceCollection.php-6-
app/src/Shared/Infrastructure/Cycle/LazyGhostPendingRelationReferenceCollection.php:7:final class LazyGhostPendingRelationReferenceCollection
app/src/Shared/Infrastructure/Cycle/LazyGhostPendingRelationReferenceCollection.php-8-{
app/src/Shared/Infrastructure/Cycle/LazyGhostPendingRelationReferenceCollection.php-9-    /**
app/src/Shared/Infrastructure/Cycle/LazyGhostPendingRelationReferenceCollection.php-10-     * @var array<string, LazyGhostPendingRelationReference>
--
app/src/Shared/Infrastructure/Cycle/LazyGhostPendingRelationReferenceCollection.php-30-    /**
app/src/Shared/Infrastructure/Cycle/LazyGhostPendingRelationReferenceCollection.php-31-     * @return array<string, LazyGhostPendingRelationReference>
app/src/Shared/Infrastructure/Cycle/LazyGhostPendingRelationReferenceCollection.php-32-     */
app/src/Shared/Infrastructure/Cycle/LazyGhostPendingRelationReferenceCollection.php:33:    public function all(): array
app/src/Shared/Infrastructure/Cycle/LazyGhostPendingRelationReferenceCollection.php-34-    {
app/src/Shared/Infrastructure/Cycle/LazyGhostPendingRelationReferenceCollection.php-35-        return $this->items;
app/src/Shared/Infrastructure/Cycle/LazyGhostPendingRelationReferenceCollection.php-36-    }
--
app/src/Modules/Posts/Domain/Collection/PostCollection.php-10-/**
app/src/Modules/Posts/Domain/Collection/PostCollection.php-11- * @extends Collection<int, Post>
app/src/Modules/Posts/Domain/Collection/PostCollection.php-12- */
app/src/Modules/Posts/Domain/Collection/PostCollection.php:13:final class PostCollection extends Collection {}
--
app/src/Modules/User/Application/Dto/UserPublicProfileCollection.php-9-/**
app/src/Modules/User/Application/Dto/UserPublicProfileCollection.php-10- * @extends Collection<int, UserPublicProfileView>
app/src/Modules/User/Application/Dto/UserPublicProfileCollection.php-11- */
app/src/Modules/User/Application/Dto/UserPublicProfileCollection.php:12:final class UserPublicProfileCollection extends Collection {}

exec
/bin/zsh -lc "sed -n '1,260p' docs/reviews/2026-06-19_21-30_posts-application-presentation.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
exec
/bin/zsh -lc "sed -n '260,560p' docs/reviews/2026-06-19_21-30_posts-application-presentation.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
 succeeded in 0ms:
---
title: Модуль Posts/Tags/User — прикладной и презентационный слой (5-й круг, фокус на фиксах 4-го круга)
date: 2026-06-19 21:30
target: git diff HEAD + untracked
plan: docs/plans/2026-06-18_17-42_posts-application-presentation.md
mode: strict
score: 97
status: meta-reviewed
meta_reviewers: [plan-check (sonnet), architecture-check (sonnet), rules-check (sonnet), quality-check (sonnet)]
---

# Ревью: Модуль Posts/Tags/User — прикладной и презентационный слой (5-й круг)

## Оценка

**97/100.** Реализация плана полная, фиксы четвёртого круга корректны и не дали регрессий: новый
общий сервис разрешения упоминаний разведён по трём режимам осмысленно, дешёвая проверка
существования и guard автора покрыты тестами, мягкая рассылка при публикации не ломает прежнее
поведение и не дублирует уведомления. Новых реальных проблем не найдено; остаток до 100 — это
естественный потолок для большого изменения, прошедшего четыре круга, а не конкретный дефект, под
который стоило бы снижать оценку.

## Проблемы сверки с планом

Явных проблем по плану не найдено. План реализован по всем пяти фазам: 15 прикладных сценариев, 7
видов уведомлений, межмодульные публичные сценарии User/Media/Tags, конфиг User с дефолтным аватаром,
read-фильтры репозиториев, каждый HTTP-роут покрыт интеграционным тестом. Решения 1–27 соблюдены, в
том числе спорные (схема B, атомарная транзакция через SAVEPOINT, идемпотентность лайков/удаления,
дедуп уведомлений, лимиты во Filter, `Ready`-проверка медиа до перевода в permanent).

## Замечания

Явных проблем в коде не найдено.

Отдельно по запрошенным зонам фокуса (фиксы четвёртого круга) — проверено и подтверждено как
корректное, без снижения оценки:

- **`MentionRecipientResolver` и его три режима.** `requireAllExist` (черновик, без рассылки),
  `resolveRequired` (создание опубликованной записи и комментарии, строгая проверка полноты -> 422) и
  `resolveExisting` (мягкая рассылка по сохранённым упоминаниям при публикации) разведены по
  назначению и не пересекаются. Все вызывающие передают уже дедуплицированные списки, поэтому проверка
  полноты `count() !== count($userIds)` в `resolveRequired` не даёт ложного 422. Двойной рассылки нет:
  опубликованная запись уведомляет упоминания при создании, черновик — только при публикации, а
  `PublishPost` входит в ветку публикации лишь из статуса `Draft`, поэтому повторная публикация —
  no-op без второго уведомления.

- **`CheckUsersExist` / `UserRepository::countByIds`.** Дешёвая проверка существования согласована с
  «дорогим» путём: `User` использует обычную nullable-колонку `deleted_at` с собственным typecast, а
  не Cycle `SoftDelete` behavior, поэтому глобального скоупа `WHERE deleted_at IS NULL` нет — `count()`,
  `findByIds()` и `findById()` одинаково видят (или не видят) soft-deleted пользователей. Значит,
  валидация черновика (`countByIds`) и валидация опубликованной записи (`findByIds`) дадут один и тот
  же результат на одних и тех же данных — расхождения 422 между путями нет. Хэндлер и репозиторий
  корректно обрабатывают пустой вход и дедуплицируют идентификаторы.

- **Guard `requireAuthor` в ассемблерах.** Отсутствие автора в пакетной карте профилей теперь даёт ту
  же 404 (`app.user.not_found`), что и одиночный путь, вместо неконтролируемого обращения по
  несуществующему ключу массива -> 500. Поведение одиночного и пакетного путей приведено к единому
  контракту во всех трёх местах (`fromPosts`, `originalViewsByPost`, `fromComments`).

- **Мягкая рассылка при публикации (`notifyPostMentions` -> `resolveExisting`).** Читает сохранённые
  упоминания из своего репозитория, тихо пропускает исчезнувших получателей и не падает на строгой
  проверке полноты. Актор (публикующий владелец) существует по определению, поэтому строгий
  `profile()` для актора безопасен. Подтверждено интеграционным тестом
  `PublishPostHttpTest::testPublishingDraftNotifiesMentionedUsers` (0 уведомлений на черновике, ровно 1
  после публикации).

## Рекомендации

- **Править обязательно:** нет.
- **На усмотрение автора:** нет.

## Изменения после мета-ревью

### После plan-check / architecture-check / rules-check / quality-check

- **+ Добавлено:** ничего. Реальных новых проблем не подтверждено.
- **~ Изменено:** ничего.
- **− Убрано:** ничего.
- **Отклонено:**
  - «Добавить пункт: `MentionRecipientResolver::resolveRequired` не защищён от дублей изнутри —
    при будущем вызывающем без `array_unique` метод даст ложный 422; добавить
    `array_unique` в начало метода» (quality-check `+`, тип robustness, «на усмотрение») —
    отклонено. Проверено по коду: оба текущих вызывающих (`PostContentComposer:117` и
    `CommentComposer:43`) дедуплицируют список через `\array_values(\array_unique(...))` до
    вызова; контракт зафиксирован `@param list<string>`. Текущего дефекта нет, это
    спекулятивная защита под несуществующего будущего вызывающего — искусственный пункт для
    финального круга, ревью ведётся только по проблемам.
  - «Переформулировать тезис строки 42 (про дедуплицированные списки), т.к. он создаёт
    впечатление полного отсутствия проблемы» (quality-check `~`) — отклонено. Формулировка
    фактически верна и проверена по коду: оба вызывающих действительно передают
    дедуплицированные списки. Тезис не утверждает внутреннюю защиту метода и не вводит в
    заблуждение.
  - «`RepostPostHandler::handle()` ~47 строк, формально превышает ориентир ~40»
    (quality-check) — отклонено. Метод плотно структурирован, без вложенности, максимально
    делегирован в `PostContentComposer`; правило задаёт ориентир «~40», искусственное дробление
    ухудшит читаемость. Не дефект.
  - Англицизмы «стейджинг/стейджит/батч» в комментариях (rules-check) — не восстановлено:
    осознанно отклонено в 1-м круге как устоявшийся технический жаргон по всему проекту
    (Notifications/Outbox/Shared), материально новых аргументов нет.
- **plan-check запускался:** план указан в front matter (`docs/plans/2026-06-18_17-42_...`);
  все 5 фаз, 15 роутов схемы B, 7 видов уведомлений, межмодульные сценарии, конфиг User и
  read-фильтры репозиториев подтверждены реализованными; каждый HTTP-роут покрыт интеграционным
  тестом. Фиксы 4-го круга (три режима `MentionRecipientResolver`, `CheckUsersExist`/`countByIds`,
  guard `requireAuthor`, мягкая рассылка `post_mention` при публикации) проверены по коду всеми
  четырьмя ролями как корректные, без регрессий.

exec
/bin/zsh -lc 'rg -n "''^(##|###|####|'"\\+|−|- \\*\\*|\\*)|править обязательно|на усмотрение|mention|author|countByIds|requireAuthor|notifyPostMentions|post_mention|CheckUsersExist|Оценка\" docs/reviews/2026-06-19_21-30_posts-application-presentation.md -C 2" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
12-# Ревью: Модуль Posts/Tags/User — прикладной и презентационный слой (5-й круг)
13-
14:## Оценка
15-
16:**97/100.** Реализация плана полная, фиксы четвёртого круга корректны и не дали регрессий: новый
17-общий сервис разрешения упоминаний разведён по трём режимам осмысленно, дешёвая проверка
18-существования и guard автора покрыты тестами, мягкая рассылка при публикации не ломает прежнее
--
21-который стоило бы снижать оценку.
22-
23:## Проблемы сверки с планом
24-
25-Явных проблем по плану не найдено. План реализован по всем пяти фазам: 15 прикладных сценариев, 7
--
29-дедуп уведомлений, лимиты во Filter, `Ready`-проверка медиа до перевода в permanent).
30-
31:## Замечания
32-
33-Явных проблем в коде не найдено.
--
36-корректное, без снижения оценки:
37-
38:- **`MentionRecipientResolver` и его три режима.** `requireAllExist` (черновик, без рассылки),
39-  `resolveRequired` (создание опубликованной записи и комментарии, строгая проверка полноты -> 422) и
40-  `resolveExisting` (мягкая рассылка по сохранённым упоминаниям при публикации) разведены по
--
45-  no-op без второго уведомления.
46-
47:- **`CheckUsersExist` / `UserRepository::countByIds`.** Дешёвая проверка существования согласована с
48-  «дорогим» путём: `User` использует обычную nullable-колонку `deleted_at` с собственным typecast, а
49-  не Cycle `SoftDelete` behavior, поэтому глобального скоупа `WHERE deleted_at IS NULL` нет — `count()`,
50-  `findByIds()` и `findById()` одинаково видят (или не видят) soft-deleted пользователей. Значит,
51:  валидация черновика (`countByIds`) и валидация опубликованной записи (`findByIds`) дадут один и тот
52-  же результат на одних и тех же данных — расхождения 422 между путями нет. Хэндлер и репозиторий
53-  корректно обрабатывают пустой вход и дедуплицируют идентификаторы.
54-
55:- **Guard `requireAuthor` в ассемблерах.** Отсутствие автора в пакетной карте профилей теперь даёт ту
56-  же 404 (`app.user.not_found`), что и одиночный путь, вместо неконтролируемого обращения по
57-  несуществующему ключу массива -> 500. Поведение одиночного и пакетного путей приведено к единому
58-  контракту во всех трёх местах (`fromPosts`, `originalViewsByPost`, `fromComments`).
59-
60:- **Мягкая рассылка при публикации (`notifyPostMentions` -> `resolveExisting`).** Читает сохранённые
61-  упоминания из своего репозитория, тихо пропускает исчезнувших получателей и не падает на строгой
62-  проверке полноты. Актор (публикующий владелец) существует по определению, поэтому строгий
--
65-  после публикации).
66-
67:## Рекомендации
68-
69:- **Править обязательно:** нет.
70:- **На усмотрение автора:** нет.
71-
72:## Изменения после мета-ревью
73-
74:### После plan-check / architecture-check / rules-check / quality-check
75-
76:- **+ Добавлено:** ничего. Реальных новых проблем не подтверждено.
77:- **~ Изменено:** ничего.
78:- **− Убрано:** ничего.
79:- **Отклонено:**
80-  - «Добавить пункт: `MentionRecipientResolver::resolveRequired` не защищён от дублей изнутри —
81-    при будущем вызывающем без `array_unique` метод даст ложный 422; добавить
82:    `array_unique` в начало метода» (quality-check `+`, тип robustness, «на усмотрение») —
83-    отклонено. Проверено по коду: оба текущих вызывающих (`PostContentComposer:117` и
84-    `CommentComposer:43`) дедуплицируют список через `\array_values(\array_unique(...))` до
--
98-    осознанно отклонено в 1-м круге как устоявшийся технический жаргон по всему проекту
99-    (Notifications/Outbox/Shared), материально новых аргументов нет.
100:- **plan-check запускался:** план указан в front matter (`docs/plans/2026-06-18_17-42_...`);
101-  все 5 фаз, 15 роутов схемы B, 7 видов уведомлений, межмодульные сценарии, конфиг User и
102-  read-фильтры репозиториев подтверждены реализованными; каждый HTTP-роут покрыт интеграционным
103:  тестом. Фиксы 4-го круга (три режима `MentionRecipientResolver`, `CheckUsersExist`/`countByIds`,
104:  guard `requireAuthor`, мягкая рассылка `post_mention` при публикации) проверены по коду всеми
105-  четырьмя ролями как корректные, без регрессий.

exec
/bin/zsh -lc "sed -n '560,860p' docs/reviews/2026-06-19_21-30_posts-application-presentation.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
codex
Пока по ручной проверке главный тезис ревью выглядит в основном подтверждённым: три режима resolver-а реально разведены, а `PostNotifier` отдельно гасит self-уведомления. Проверяю теперь смежный риск: публикация черновика внутри транзакции, сохранение упоминаний, порядок flush и тесты на “мягкий” путь.
exec
/bin/zsh -lc "sed -n '1,260p' tests/Feature/Modules/User/Application/CheckUsersExistHandlerTest.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\User\Application;

use App\Modules\User\Application\Query\CheckUsersExist\CheckUsersExistHandler;
use App\Modules\User\Application\Query\CheckUsersExist\CheckUsersExistQuery;
use App\Shared\Domain\ValueObject\UserId;

final class CheckUsersExistHandlerTest extends UserApplicationTestCase
{
    public function testReturnsTrueWhenAllRequestedUsersExist(): void
    {
        $first = $this->persistUser();
        $second = $this->persistUser();

        $allExist = $this->handler()->handle(new CheckUsersExistQuery(
            userIds: [$first->id->value(), $second->id->value(), $first->id->value()],
        ));

        self::assertTrue($allExist);
    }

    public function testReturnsFalseWhenAnyRequestedUserIsMissing(): void
    {
        $existing = $this->persistUser();

        $allExist = $this->handler()->handle(new CheckUsersExistQuery(
            userIds: [$existing->id->value(), UserId::generate()->value()],
        ));

        self::assertFalse($allExist);
    }

    public function testReturnsTrueForEmptyInput(): void
    {
        $allExist = $this->handler()->handle(new CheckUsersExistQuery(userIds: []));

        self::assertTrue($allExist);
    }

    private function handler(): CheckUsersExistHandler
    {
        return new CheckUsersExistHandler(userRepository: $this->userRepository());
    }
}

exec
/bin/zsh -lc "sed -n '1,240p' tests/Feature/Modules/Posts/Http/PublishPostHttpTest.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Posts\Http;

use App\Modules\Posts\Domain\Enum\PostStatus;
use App\Shared\Domain\ValueObject\UserId;

final class PublishPostHttpTest extends PostsHttpTestCase
{
    public function testPublishesOwnDraft(): void
    {
        $user = $this->createUser();
        $post = $this->persistPost($user->id, PostStatus::Draft);

        $response = $this->authedJson('POST', \sprintf('/api/v1/posts/%s/publish', $post->id->value()), $user->id);

        $response->assertOk();
        self::assertSame('published', $this->json($response)['data']['status']);
        self::assertSame(PostStatus::Published, $this->reloadPost($post)->status);
    }

    public function testPublishingDraftNotifiesMentionedUsers(): void
    {
        $author = $this->createUser();
        $mentioned = $this->createUser();

        $createResponse = $this->authedJson('POST', '/api/v1/posts', $author->id, [
            'text' => 'Черновик с упоминанием',
            'draft' => true,
            'mentions' => [$mentioned->id->value()],
        ]);
        $createResponse->assertOk();
        $postId = $this->json($createResponse)['data']['id'];

        self::assertCount(0, $this->stagedNotifications('posts.post_mention'));

        $this->authedJson('POST', \sprintf('/api/v1/posts/%s/publish', $postId), $author->id)->assertOk();

        $mentions = $this->stagedNotifications('posts.post_mention');
        self::assertCount(1, $mentions);
        self::assertSame($mentioned->id->value(), $mentions[0]->userId);
    }

    public function testAlreadyPublishedIsIdempotentNoOp(): void
    {
        $user = $this->createUser();
        $post = $this->persistPost($user->id, PostStatus::Published);

        $response = $this->authedJson('POST', \sprintf('/api/v1/posts/%s/publish', $post->id->value()), $user->id);

        $response->assertOk();
        self::assertSame('published', $this->json($response)['data']['status']);
    }

    public function testRejectsPublishByNonOwner(): void
    {
        $owner = $this->createUser();
        $stranger = $this->createUser();
        $post = $this->persistPost($owner->id, PostStatus::Draft);

        $this->authedJson('POST', \sprintf('/api/v1/posts/%s/publish', $post->id->value()), $stranger->id)
            ->assertStatus(403);
    }

    public function testRejectsPublishOfDeletedPost(): void
    {
        $user = $this->createUser();
        $post = $this->persistPost($user->id, PostStatus::Draft, deleted: true);

        $this->authedJson('POST', \sprintf('/api/v1/posts/%s/publish', $post->id->value()), $user->id)
            ->assertNotFound();
    }

    public function testRejectsPublishOfBlockedPost(): void
    {
        $user = $this->createUser();
        $post = $this->persistPost($user->id, PostStatus::Blocked);

        $this->authedJson('POST', \sprintf('/api/v1/posts/%s/publish', $post->id->value()), $user->id)
            ->assertNotFound();
    }

    public function testRejectsPublishOfMissingPost(): void
    {
        $user = $this->createUser();

        $this->authedJson('POST', \sprintf('/api/v1/posts/%s/publish', UserId::generate()->value()), $user->id)
            ->assertNotFound();
    }

    public function testRejectsInvalidPostId(): void
    {
        $user = $this->createUser();

        $this->authedJson('POST', '/api/v1/posts/not-a-uuid/publish', $user->id)->assertUnprocessable();
    }
}

exec
/bin/zsh -lc "sed -n '1,260p' tests/Feature/Modules/Posts/Http/PostsHttpTestCase.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Posts\Http;

use App\Modules\Media\Application\Contract\MediaFileServiceContract;
use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Modules\Media\Domain\ValueObject\MediaExpiration;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaStorageKey;
use App\Modules\Notifications\Application\Message\NotificationRequested;
use App\Modules\Posts\Domain\Entity\Comment;
use App\Modules\Posts\Domain\Entity\Post;
use App\Modules\Posts\Domain\Enum\AttachmentType;
use App\Modules\Posts\Domain\Enum\PostStatus;
use App\Modules\Posts\Domain\ValueObject\CommentParent;
use App\Modules\Posts\Domain\ValueObject\CommentText;
use App\Modules\Posts\Domain\ValueObject\PostLesson;
use App\Modules\Posts\Domain\ValueObject\PostOriginal;
use App\Modules\Posts\Domain\ValueObject\PostPractice;
use App\Modules\Posts\Domain\ValueObject\PostText;
use App\Modules\Posts\Repository\CommentLikeRepository;
use App\Modules\Posts\Repository\CommentRepository;
use App\Modules\Posts\Repository\PostLikeRepository;
use App\Modules\Posts\Repository\PostMentionRepository;
use App\Modules\Posts\Repository\PostRepository;
use App\Modules\Posts\Repository\PostTagRepository;
use App\Modules\User\Domain\Entity\User;
use App\Modules\User\Domain\ValueObject\Email;
use App\Modules\User\Domain\ValueObject\UserName;
use App\Modules\User\Domain\ValueObject\UserNickname;
use App\Modules\Outbox\Application\Contract\OutboxEventStoreContract;
use App\Shared\Domain\Enum\Locale;
use App\Shared\Domain\ValueObject\UserId;
use Cycle\ORM\EntityManagerInterface;
use Spiral\Testing\Http\TestResponse;
use Tests\DatabaseTestCase;
use Tests\Support\Notifications\RecordingOutboxEventStore;

/**
 * Основа HTTP-тестов модуля Posts. Аутентификация — прямой проброс request-атрибута authUserId
 * (стек auth-middleware его не затирает при отсутствии токена). Outbox подменён записывающим
 * дублёром (проверяем стейджинг уведомлений), а файловый сервис медиа — стабом (URL предсказуем,
 * без обращения к S3).
 */
abstract class PostsHttpTestCase extends DatabaseTestCase
{
    protected RecordingOutboxEventStore $outboxStore;

    private int $userCounter = 0;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->outboxStore = new RecordingOutboxEventStore();
        $this->getContainer()->bindSingleton(OutboxEventStoreContract::class, $this->outboxStore);

        $fileService = $this->createStub(MediaFileServiceContract::class);
        $fileService->method('publicUrl')->willReturn('https://media.test/object.jpg');
        $fileService->method('presignGet')->willReturn('https://media.test/signed.jpg');
        $this->getContainer()->bindSingleton(MediaFileServiceContract::class, $fileService);
    }

    protected function createUser(): User
    {
        $this->userCounter++;

        $user = User::create(
            name: UserName::fromString('Йога Тест'),
            email: Email::fromString(\sprintf('posts.http%d@example.com', $this->userCounter)),
            nickname: UserNickname::fromString(\sprintf('posts.http%d', $this->userCounter)),
            locale: Locale::Ru,
        );
        $this->persist($user);

        return $user;
    }

    protected function createReadyMedia(UserId $owner, MediaVisibility $visibility = MediaVisibility::Private): Media
    {
        $media = $this->newMedia($owner, $visibility);
        $media->markUploaded();
        $targetStorage = $visibility === MediaVisibility::Public ? MediaStorage::Public : MediaStorage::Private;
        $media->markReadyMovedTo(
            $targetStorage,
            MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Image, extension: 'jpg'),
        );
        $this->persist($media);

        return $media;
    }

    protected function createUploadedMedia(UserId $owner): Media
    {
        $media = $this->newMedia($owner, MediaVisibility::Private);
        $media->markUploaded();
        $this->persist($media);

        return $media;
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function authedJson(string $method, string $uri, UserId $userId, array $data = []): TestResponse
    {
        $request = $this->fakeHttp()
            ->createJsonRequest($uri, $method, $data, [], [])
            ->withAttribute('authUserId', $userId->value());

        return $this->fakeHttp()->handleRequest($request);
    }

    protected function authedGet(string $uri, UserId $userId): TestResponse
    {
        return $this->fakeHttp()->getWithAttributes($uri, ['authUserId' => $userId->value()]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function json(TestResponse $response): array
    {
        $decoded = \json_decode(
            json: (string) $response->getOriginalResponse()->getBody(),
            associative: true,
            flags: \JSON_THROW_ON_ERROR,
        );

        self::assertIsArray($decoded);

        return $decoded;
    }

    /**
     * @return list<NotificationRequested>
     */
    protected function stagedNotifications(string $type): array
    {
        return \array_values(\array_filter(
            $this->outboxStore->messages,
            static fn(object $message): bool => $message instanceof NotificationRequested && $message->type === $type,
        ));
    }

    protected function persist(object $entity): void
    {
        $this->entityManager()->persist($entity);
        $this->entityManager()->run();
    }

    protected function entityManager(): EntityManagerInterface
    {
        return $this->getContainer()->get(EntityManagerInterface::class);
    }

    protected function postRepository(): PostRepository
    {
        return $this->getContainer()->get(PostRepository::class);
    }

    protected function postTagRepository(): PostTagRepository
    {
        return $this->getContainer()->get(PostTagRepository::class);
    }

    protected function postMentionRepository(): PostMentionRepository
    {
        return $this->getContainer()->get(PostMentionRepository::class);
    }

    protected function postLikeRepository(): PostLikeRepository
    {
        return $this->getContainer()->get(PostLikeRepository::class);
    }

    protected function persistPost(
        UserId $author,
        PostStatus $status = PostStatus::Published,
        bool $deleted = false,
    ): Post {
        $post = Post::create(
            userId: $author,
            text: PostText::none(),
            status: $status,
            attachmentType: AttachmentType::None,
            lesson: PostLesson::none(),
            practice: PostPractice::none(),
            original: PostOriginal::none(),
        );

        if ($deleted) {
            $post->softDelete(new \DateTimeImmutable());
        }

        $this->persist($post);

        return $post;
    }

    protected function persistRepost(UserId $author, Post $original): Post
    {
        $original->incrementReposts();
        $this->persist($original);

        $repost = Post::create(
            userId: $author,
            text: PostText::none(),
            status: PostStatus::Published,
            attachmentType: AttachmentType::None,
            lesson: PostLesson::none(),
            practice: PostPractice::none(),
            original: PostOriginal::pointingTo($original->id->value()),
        );
        $this->persist($repost);

        return $repost;
    }

    protected function persistComment(UserId $author, Post $post, Comment|null $parent = null): Comment
    {
        $comment = Comment::create(
            postId: $post->id,
            userId: $author,
            text: CommentText::fromString('Комментарий'),
            parent: $parent === null ? CommentParent::none() : CommentParent::pointingTo($parent->id->value()),
        );
        $this->persist($comment);

        if ($parent === null) {
            $post->incrementComments();
            $this->persist($post);
        } else {
            $parent->incrementReplies();
            $this->persist($parent);
        }

        return $comment;
    }

    protected function reloadPost(Post $post): Post
    {
        $this->cleanOrmHeap();
        $reloaded = $this->postRepository()->findById($post->id);
        self::assertInstanceOf(Post::class, $reloaded);

        return $reloaded;
    }

    protected function reloadComment(Comment $comment): Comment
    {
        $this->cleanOrmHeap();

exec
/bin/zsh -lc "sed -n '1,220p' app/src/Modules/Posts/Repository/PostMentionRepository.php && sed -n '1,180p' app/src/Modules/Posts/Domain/Entity/PostMention.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Posts\Repository;

use App\Modules\Posts\Domain\Collection\PostMentionCollection;
use App\Modules\Posts\Domain\Entity\PostMention;
use App\Modules\Posts\Domain\ValueObject\PostId;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Cycle\AbstractRepository;

/**
 * @extends AbstractRepository<PostMention>
 */
final class PostMentionRepository extends AbstractRepository
{
    public function findByPostId(PostId $postId): PostMentionCollection
    {
        return new PostMentionCollection(
            $this->select()
                ->where('post_id', $postId->value())
                ->orderBy(expression: 'id', direction: 'DESC')
                ->fetchAll(),
        );
    }

    public function findByUserId(UserId $userId): PostMentionCollection
    {
        return new PostMentionCollection(
            $this->select()
                ->where('user_id', $userId->value())
                ->orderBy(expression: 'id', direction: 'DESC')
                ->fetchAll(),
        );
    }
}
<?php

declare(strict_types=1);

namespace App\Modules\Posts\Domain\Entity;

use App\Modules\Posts\Domain\ValueObject\PostId;
use App\Modules\Posts\Domain\ValueObject\PostMentionId;
use App\Modules\Posts\Repository\PostMentionRepository;
use App\Shared\Domain\Trait\HasTimestamps;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Cycle\ValueObjectCast;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\ORM\Parser\Typecast;

#[Entity(
    role: 'post_mention',
    table: 'post_mentions',
    repository: PostMentionRepository::class,
    typecast: [Typecast::class, ValueObjectCast::class],
)]
final class PostMention
{
    use HasTimestamps;

    #[Column(type: 'uuid', primary: true, typecast: PostMentionId::class)]
    public private(set) PostMentionId $id;

    #[Column(type: 'uuid', name: 'post_id', typecast: PostId::class)]
    public private(set) PostId $postId;

    #[Column(type: 'uuid', name: 'user_id', typecast: UserId::class)]
    public private(set) UserId $userId;

    public static function create(PostId $postId, UserId $userId): self
    {
        $postMention = new self();
        $postMention->id = PostMentionId::generate();
        $postMention->postId = $postId;
        $postMention->userId = $userId;
        $postMention->initializeTimestamps();

        return $postMention;
    }
}

exec
/bin/zsh -lc 'rg -n "function softDelete|deleted|delete|UserDeletion|deleted_at" app/src/Modules/User tests/Feature/Modules/User -C 4' in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
tests/Feature/Modules/User/Application/FindUserForAuthHandlerTest.php-45-    }
tests/Feature/Modules/User/Application/FindUserForAuthHandlerTest.php-46-
tests/Feature/Modules/User/Application/FindUserForAuthHandlerTest.php-47-    public function testReturnsViewWithSignInDeniedForDeletedUser(): void
tests/Feature/Modules/User/Application/FindUserForAuthHandlerTest.php-48-    {
tests/Feature/Modules/User/Application/FindUserForAuthHandlerTest.php:49:        $deletedUser = $this->createUser(email: 'deleted@example.com', nickname: 'deleted.user');
tests/Feature/Modules/User/Application/FindUserForAuthHandlerTest.php:50:        $deletedUser->confirmEmail();
tests/Feature/Modules/User/Application/FindUserForAuthHandlerTest.php:51:        $deletedUser->markDeleted(new \DateTimeImmutable());
tests/Feature/Modules/User/Application/FindUserForAuthHandlerTest.php:52:        $this->persist($deletedUser);
tests/Feature/Modules/User/Application/FindUserForAuthHandlerTest.php-53-
tests/Feature/Modules/User/Application/FindUserForAuthHandlerTest.php:54:        $view = $this->handler()->handle(new FindUserForAuthQuery(email: 'deleted@example.com'));
tests/Feature/Modules/User/Application/FindUserForAuthHandlerTest.php-55-
tests/Feature/Modules/User/Application/FindUserForAuthHandlerTest.php-56-        self::assertInstanceOf(UserAuthView::class, $view);
tests/Feature/Modules/User/Application/FindUserForAuthHandlerTest.php-57-        self::assertFalse($view->canSignIn);
tests/Feature/Modules/User/Application/FindUserForAuthHandlerTest.php-58-    }
--
app/src/Modules/User/Infrastructure/Cycle/UserDeletionTypecast.php-3-declare(strict_types=1);
app/src/Modules/User/Infrastructure/Cycle/UserDeletionTypecast.php-4-
app/src/Modules/User/Infrastructure/Cycle/UserDeletionTypecast.php-5-namespace App\Modules\User\Infrastructure\Cycle;
app/src/Modules/User/Infrastructure/Cycle/UserDeletionTypecast.php-6-
app/src/Modules/User/Infrastructure/Cycle/UserDeletionTypecast.php:7:use App\Modules\User\Domain\ValueObject\UserDeletion;
app/src/Modules/User/Infrastructure/Cycle/UserDeletionTypecast.php-8-use App\Shared\Infrastructure\Cycle\ColumnValueTypecast;
app/src/Modules/User/Infrastructure/Cycle/UserDeletionTypecast.php-9-
app/src/Modules/User/Infrastructure/Cycle/UserDeletionTypecast.php:10:final class UserDeletionTypecast implements ColumnValueTypecast
app/src/Modules/User/Infrastructure/Cycle/UserDeletionTypecast.php-11-{
app/src/Modules/User/Infrastructure/Cycle/UserDeletionTypecast.php:12:    public static function castDatabaseValue(string|\DateTimeInterface|null $value): UserDeletion
app/src/Modules/User/Infrastructure/Cycle/UserDeletionTypecast.php-13-    {
app/src/Modules/User/Infrastructure/Cycle/UserDeletionTypecast.php-14-        if ($value === null) {
app/src/Modules/User/Infrastructure/Cycle/UserDeletionTypecast.php:15:            return UserDeletion::active();
app/src/Modules/User/Infrastructure/Cycle/UserDeletionTypecast.php-16-        }
app/src/Modules/User/Infrastructure/Cycle/UserDeletionTypecast.php-17-
app/src/Modules/User/Infrastructure/Cycle/UserDeletionTypecast.php-18-        if ($value instanceof \DateTimeImmutable) {
app/src/Modules/User/Infrastructure/Cycle/UserDeletionTypecast.php:19:            return UserDeletion::at($value);
app/src/Modules/User/Infrastructure/Cycle/UserDeletionTypecast.php-20-        }
app/src/Modules/User/Infrastructure/Cycle/UserDeletionTypecast.php-21-
app/src/Modules/User/Infrastructure/Cycle/UserDeletionTypecast.php-22-        if ($value instanceof \DateTimeInterface) {
app/src/Modules/User/Infrastructure/Cycle/UserDeletionTypecast.php:23:            return UserDeletion::at(\DateTimeImmutable::createFromInterface($value));
app/src/Modules/User/Infrastructure/Cycle/UserDeletionTypecast.php-24-        }
app/src/Modules/User/Infrastructure/Cycle/UserDeletionTypecast.php-25-
app/src/Modules/User/Infrastructure/Cycle/UserDeletionTypecast.php:26:        return UserDeletion::at(new \DateTimeImmutable($value));
app/src/Modules/User/Infrastructure/Cycle/UserDeletionTypecast.php-27-    }
app/src/Modules/User/Infrastructure/Cycle/UserDeletionTypecast.php-28-
app/src/Modules/User/Infrastructure/Cycle/UserDeletionTypecast.php:29:    public static function uncastValue(UserDeletion|null $value): \DateTimeImmutable|null
app/src/Modules/User/Infrastructure/Cycle/UserDeletionTypecast.php-30-    {
app/src/Modules/User/Infrastructure/Cycle/UserDeletionTypecast.php-31-        if ($value === null) {
app/src/Modules/User/Infrastructure/Cycle/UserDeletionTypecast.php-32-            return null;
app/src/Modules/User/Infrastructure/Cycle/UserDeletionTypecast.php-33-        }
--
app/src/Modules/User/Domain/Enum/UserStatus.php-8-{
app/src/Modules/User/Domain/Enum/UserStatus.php-9-    case WaitingEmailConfirmation = 'waiting_email_confirmation';
app/src/Modules/User/Domain/Enum/UserStatus.php-10-    case Active = 'active';
app/src/Modules/User/Domain/Enum/UserStatus.php-11-    case Banned = 'banned';
app/src/Modules/User/Domain/Enum/UserStatus.php:12:    case Deleted = 'deleted';
app/src/Modules/User/Domain/Enum/UserStatus.php-13-}
--
app/src/Modules/User/Domain/Entity/User.php-8-use App\Modules\User\Domain\Enum\UserVerification;
app/src/Modules/User/Domain/Entity/User.php-9-use App\Modules\User\Domain\ValueObject\Email;
app/src/Modules/User/Domain/Entity/User.php-10-use App\Modules\User\Domain\ValueObject\UserAvatar;
app/src/Modules/User/Domain/Entity/User.php-11-use App\Modules\User\Domain\ValueObject\UserBio;
app/src/Modules/User/Domain/Entity/User.php:12:use App\Modules\User\Domain\ValueObject\UserDeletion;
app/src/Modules/User/Domain/Entity/User.php-13-use App\Modules\User\Domain\ValueObject\UserLocation;
app/src/Modules/User/Domain/Entity/User.php-14-use App\Modules\User\Domain\ValueObject\UserName;
app/src/Modules/User/Domain/Entity/User.php-15-use App\Modules\User\Domain\ValueObject\UserNickname;
app/src/Modules/User/Domain/Entity/User.php-16-use App\Modules\User\Domain\ValueObject\UserSpiritualName;
app/src/Modules/User/Domain/Entity/User.php-17-use App\Modules\User\Infrastructure\Cycle\UserAvatarTypecast;
app/src/Modules/User/Domain/Entity/User.php-18-use App\Modules\User\Infrastructure\Cycle\UserBioTypecast;
app/src/Modules/User/Domain/Entity/User.php:19:use App\Modules\User\Infrastructure\Cycle\UserDeletionTypecast;
app/src/Modules/User/Domain/Entity/User.php-20-use App\Modules\User\Infrastructure\Cycle\UserLocationTypecast;
app/src/Modules/User/Domain/Entity/User.php-21-use App\Modules\User\Infrastructure\Cycle\UserSpiritualNameTypecast;
app/src/Modules/User/Domain/Entity/User.php-22-use App\Modules\User\Repository\UserRepository;
app/src/Modules/User/Domain/Entity/User.php-23-use App\Shared\Domain\Enum\Locale;
--
app/src/Modules/User/Domain/Entity/User.php-70-
app/src/Modules/User/Domain/Entity/User.php-71-    #[Column(type: 'string(8)', typecast: Locale::class)]
app/src/Modules/User/Domain/Entity/User.php-72-    public private(set) Locale $locale;
app/src/Modules/User/Domain/Entity/User.php-73-
app/src/Modules/User/Domain/Entity/User.php:74:    #[Column(type: 'datetime', name: 'deleted_at', nullable: true, typecast: UserDeletionTypecast::class)]
app/src/Modules/User/Domain/Entity/User.php:75:    public private(set) UserDeletion $deletion;
app/src/Modules/User/Domain/Entity/User.php-76-
app/src/Modules/User/Domain/Entity/User.php-77-    public static function create(
app/src/Modules/User/Domain/Entity/User.php-78-        UserName $name,
app/src/Modules/User/Domain/Entity/User.php-79-        Email $email,
--
app/src/Modules/User/Domain/Entity/User.php-91-        $user->avatar = UserAvatar::none();
app/src/Modules/User/Domain/Entity/User.php-92-        $user->verification = UserVerification::Unverified;
app/src/Modules/User/Domain/Entity/User.php-93-        $user->status = UserStatus::WaitingEmailConfirmation;
app/src/Modules/User/Domain/Entity/User.php-94-        $user->locale = $locale;
app/src/Modules/User/Domain/Entity/User.php:95:        $user->deletion = UserDeletion::active();
app/src/Modules/User/Domain/Entity/User.php-96-        $user->initializeTimestamps();
app/src/Modules/User/Domain/Entity/User.php-97-
app/src/Modules/User/Domain/Entity/User.php-98-        return $user;
app/src/Modules/User/Domain/Entity/User.php-99-    }
--
app/src/Modules/User/Domain/Entity/User.php-184-    }
app/src/Modules/User/Domain/Entity/User.php-185-
app/src/Modules/User/Domain/Entity/User.php-186-    public function markDeleted(\DateTimeImmutable $now): void
app/src/Modules/User/Domain/Entity/User.php-187-    {
app/src/Modules/User/Domain/Entity/User.php:188:        $this->deletion = UserDeletion::at($now);
app/src/Modules/User/Domain/Entity/User.php-189-        $this->status = UserStatus::Deleted;
app/src/Modules/User/Domain/Entity/User.php-190-        $this->touch(now: $now);
app/src/Modules/User/Domain/Entity/User.php-191-    }
app/src/Modules/User/Domain/Entity/User.php-192-
--
app/src/Modules/User/Domain/ValueObject/UserDeletion.php-3-declare(strict_types=1);
app/src/Modules/User/Domain/ValueObject/UserDeletion.php-4-
app/src/Modules/User/Domain/ValueObject/UserDeletion.php-5-namespace App\Modules\User\Domain\ValueObject;
app/src/Modules/User/Domain/ValueObject/UserDeletion.php-6-
app/src/Modules/User/Domain/ValueObject/UserDeletion.php:7:final readonly class UserDeletion implements \Stringable, \JsonSerializable
app/src/Modules/User/Domain/ValueObject/UserDeletion.php-8-{
app/src/Modules/User/Domain/ValueObject/UserDeletion.php-9-    private function __construct(
app/src/Modules/User/Domain/ValueObject/UserDeletion.php:10:        private \DateTimeImmutable|null $deletedAt,
app/src/Modules/User/Domain/ValueObject/UserDeletion.php-11-    ) {}
app/src/Modules/User/Domain/ValueObject/UserDeletion.php-12-
app/src/Modules/User/Domain/ValueObject/UserDeletion.php-13-    public static function active(): self
app/src/Modules/User/Domain/ValueObject/UserDeletion.php-14-    {
app/src/Modules/User/Domain/ValueObject/UserDeletion.php:15:        return new self(deletedAt: null);
app/src/Modules/User/Domain/ValueObject/UserDeletion.php-16-    }
app/src/Modules/User/Domain/ValueObject/UserDeletion.php-17-
app/src/Modules/User/Domain/ValueObject/UserDeletion.php:18:    public static function at(\DateTimeImmutable $deletedAt): self
app/src/Modules/User/Domain/ValueObject/UserDeletion.php-19-    {
app/src/Modules/User/Domain/ValueObject/UserDeletion.php:20:        return new self(deletedAt: $deletedAt);
app/src/Modules/User/Domain/ValueObject/UserDeletion.php-21-    }
app/src/Modules/User/Domain/ValueObject/UserDeletion.php-22-
app/src/Modules/User/Domain/ValueObject/UserDeletion.php-23-    public function value(): \DateTimeImmutable|null
app/src/Modules/User/Domain/ValueObject/UserDeletion.php-24-    {
app/src/Modules/User/Domain/ValueObject/UserDeletion.php:25:        return $this->deletedAt;
app/src/Modules/User/Domain/ValueObject/UserDeletion.php-26-    }
app/src/Modules/User/Domain/ValueObject/UserDeletion.php-27-
app/src/Modules/User/Domain/ValueObject/UserDeletion.php-28-    public function isDeleted(): bool
app/src/Modules/User/Domain/ValueObject/UserDeletion.php-29-    {
app/src/Modules/User/Domain/ValueObject/UserDeletion.php:30:        return $this->deletedAt !== null;
app/src/Modules/User/Domain/ValueObject/UserDeletion.php-31-    }
app/src/Modules/User/Domain/ValueObject/UserDeletion.php-32-
app/src/Modules/User/Domain/ValueObject/UserDeletion.php-33-    public function equals(self $deletion): bool
app/src/Modules/User/Domain/ValueObject/UserDeletion.php-34-    {
app/src/Modules/User/Domain/ValueObject/UserDeletion.php:35:        return $this->deletedAt?->format('U.u') === $deletion->deletedAt?->format('U.u');
app/src/Modules/User/Domain/ValueObject/UserDeletion.php-36-    }
app/src/Modules/User/Domain/ValueObject/UserDeletion.php-37-
app/src/Modules/User/Domain/ValueObject/UserDeletion.php-38-    #[\Override]
app/src/Modules/User/Domain/ValueObject/UserDeletion.php-39-    public function __toString(): string
app/src/Modules/User/Domain/ValueObject/UserDeletion.php-40-    {
app/src/Modules/User/Domain/ValueObject/UserDeletion.php:41:        return $this->deletedAt?->format(\DateTimeInterface::ATOM) ?? '';
app/src/Modules/User/Domain/ValueObject/UserDeletion.php-42-    }
app/src/Modules/User/Domain/ValueObject/UserDeletion.php-43-
app/src/Modules/User/Domain/ValueObject/UserDeletion.php-44-    #[\Override]
app/src/Modules/User/Domain/ValueObject/UserDeletion.php-45-    public function jsonSerialize(): string|null
app/src/Modules/User/Domain/ValueObject/UserDeletion.php-46-    {
app/src/Modules/User/Domain/ValueObject/UserDeletion.php:47:        return $this->deletedAt?->format(\DateTimeInterface::ATOM);
app/src/Modules/User/Domain/ValueObject/UserDeletion.php-48-    }
app/src/Modules/User/Domain/ValueObject/UserDeletion.php-49-}

exec
/bin/zsh -lc 'rg -n "test.*Author|requireAuthor|user_not_found|soft.*user|missing.*author|deleted.*author" tests/Feature/Modules/Posts tests/Feature/Modules/User app/src/Modules/Posts/Application/View -C 4' in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
app/src/Modules/Posts/Application/View/PostViewAssembler.php-97-            $postId = $post->id->value();
app/src/Modules/Posts/Application/View/PostViewAssembler.php-98-            $originalId = $post->original->value();
app/src/Modules/Posts/Application/View/PostViewAssembler.php-99-            $views[] = $this->build(
app/src/Modules/Posts/Application/View/PostViewAssembler.php-100-                post: $post,
app/src/Modules/Posts/Application/View/PostViewAssembler.php:101:                author: $this->requireAuthor(authors: $authors, userId: $post->userId->value()),
app/src/Modules/Posts/Application/View/PostViewAssembler.php-102-                likedByMe: isset($likedPostIds[$postId]),
app/src/Modules/Posts/Application/View/PostViewAssembler.php-103-                media: $this->mediaItems($mediaByPost[$postId] ?? new PostMediaCollection()),
app/src/Modules/Posts/Application/View/PostViewAssembler.php-104-                tags: $this->tagViews(tags: $tagsByPost[$postId] ?? new PostTagCollection(), texts: $tagTexts),
app/src/Modules/Posts/Application/View/PostViewAssembler.php-105-                original: $originalId !== null ? ($originals[$originalId] ?? null) : null,
--
app/src/Modules/Posts/Application/View/PostViewAssembler.php-194-     * (fromPost -> GetUserPublicProfile), а не неконтролируемым undefined array key -> 500.
app/src/Modules/Posts/Application/View/PostViewAssembler.php-195-     *
app/src/Modules/Posts/Application/View/PostViewAssembler.php-196-     * @param array<string, AuthorView> $authors
app/src/Modules/Posts/Application/View/PostViewAssembler.php-197-     */
app/src/Modules/Posts/Application/View/PostViewAssembler.php:198:    private function requireAuthor(array $authors, string $userId): AuthorView
app/src/Modules/Posts/Application/View/PostViewAssembler.php-199-    {
app/src/Modules/Posts/Application/View/PostViewAssembler.php-200-        return $authors[$userId] ?? throw new NotFoundException('app.user.not_found');
app/src/Modules/Posts/Application/View/PostViewAssembler.php-201-    }
app/src/Modules/Posts/Application/View/PostViewAssembler.php-202-
--
app/src/Modules/Posts/Application/View/PostViewAssembler.php-443-        foreach ($entities as $original) {
app/src/Modules/Posts/Application/View/PostViewAssembler.php-444-            $originalId = $original->id->value();
app/src/Modules/Posts/Application/View/PostViewAssembler.php-445-            $views[$originalId] = $this->build(
app/src/Modules/Posts/Application/View/PostViewAssembler.php-446-                post: $original,
app/src/Modules/Posts/Application/View/PostViewAssembler.php:447:                author: $this->requireAuthor(authors: $authors, userId: $original->userId->value()),
app/src/Modules/Posts/Application/View/PostViewAssembler.php-448-                likedByMe: isset($likedPostIds[$originalId]),
app/src/Modules/Posts/Application/View/PostViewAssembler.php-449-                media: $this->mediaItems($mediaByPost[$originalId] ?? new PostMediaCollection()),
app/src/Modules/Posts/Application/View/PostViewAssembler.php-450-                tags: $this->tagViews(tags: $tagsByPost[$originalId] ?? new PostTagCollection(), texts: $tagTexts),
app/src/Modules/Posts/Application/View/PostViewAssembler.php-451-                original: null,
--
tests/Feature/Modules/Posts/Http/CommentHttpTest.php-8-use App\Shared\Domain\ValueObject\UserId;
tests/Feature/Modules/Posts/Http/CommentHttpTest.php-9-
tests/Feature/Modules/Posts/Http/CommentHttpTest.php-10-final class CommentHttpTest extends PostsHttpTestCase
tests/Feature/Modules/Posts/Http/CommentHttpTest.php-11-{
tests/Feature/Modules/Posts/Http/CommentHttpTest.php:12:    public function testCommentsOnPostAndNotifiesAuthor(): void
tests/Feature/Modules/Posts/Http/CommentHttpTest.php-13-    {
tests/Feature/Modules/Posts/Http/CommentHttpTest.php-14-        $author = $this->createUser();
tests/Feature/Modules/Posts/Http/CommentHttpTest.php-15-        $commenter = $this->createUser();
tests/Feature/Modules/Posts/Http/CommentHttpTest.php-16-        $post = $this->persistPost($author->id, PostStatus::Published);
--
tests/Feature/Modules/Posts/Http/CommentHttpTest.php-54-        self::assertSame($mentioned->id->value(), $mentions[0]->userId);
tests/Feature/Modules/Posts/Http/CommentHttpTest.php-55-        self::assertCount(1, $this->stagedNotifications('posts.post_commented'));
tests/Feature/Modules/Posts/Http/CommentHttpTest.php-56-    }
tests/Feature/Modules/Posts/Http/CommentHttpTest.php-57-
tests/Feature/Modules/Posts/Http/CommentHttpTest.php:58:    public function testMentionDedupBeatsPostCommentedForAuthor(): void
tests/Feature/Modules/Posts/Http/CommentHttpTest.php-59-    {
tests/Feature/Modules/Posts/Http/CommentHttpTest.php-60-        $author = $this->createUser();
tests/Feature/Modules/Posts/Http/CommentHttpTest.php-61-        $commenter = $this->createUser();
tests/Feature/Modules/Posts/Http/CommentHttpTest.php-62-        $post = $this->persistPost($author->id, PostStatus::Published);
--
tests/Feature/Modules/Posts/Http/CommentHttpTest.php-118-            ['text' => ''],
tests/Feature/Modules/Posts/Http/CommentHttpTest.php-119-        )->assertUnprocessable();
tests/Feature/Modules/Posts/Http/CommentHttpTest.php-120-    }
tests/Feature/Modules/Posts/Http/CommentHttpTest.php-121-
tests/Feature/Modules/Posts/Http/CommentHttpTest.php:122:    public function testRepliesToCommentNotifiesParentAuthorOnly(): void
tests/Feature/Modules/Posts/Http/CommentHttpTest.php-123-    {
tests/Feature/Modules/Posts/Http/CommentHttpTest.php-124-        $postAuthor = $this->createUser();
tests/Feature/Modules/Posts/Http/CommentHttpTest.php-125-        $parentAuthor = $this->createUser();
tests/Feature/Modules/Posts/Http/CommentHttpTest.php-126-        $replier = $this->createUser();
--
app/src/Modules/Posts/Application/View/CommentViewAssembler.php-56-
app/src/Modules/Posts/Application/View/CommentViewAssembler.php-57-        foreach ($entities as $comment) {
app/src/Modules/Posts/Application/View/CommentViewAssembler.php-58-            $views[] = $this->build(
app/src/Modules/Posts/Application/View/CommentViewAssembler.php-59-                comment: $comment,
app/src/Modules/Posts/Application/View/CommentViewAssembler.php:60:                author: $this->requireAuthor(authors: $authors, userId: $comment->userId->value()),
app/src/Modules/Posts/Application/View/CommentViewAssembler.php-61-                likedByMe: isset($likedCommentIds[$comment->id->value()]),
app/src/Modules/Posts/Application/View/CommentViewAssembler.php-62-            );
app/src/Modules/Posts/Application/View/CommentViewAssembler.php-63-        }
app/src/Modules/Posts/Application/View/CommentViewAssembler.php-64-
--
app/src/Modules/Posts/Application/View/CommentViewAssembler.php-126-     * (fromComment -> GetUserPublicProfile), а не неконтролируемым undefined array key -> 500.
app/src/Modules/Posts/Application/View/CommentViewAssembler.php-127-     *
app/src/Modules/Posts/Application/View/CommentViewAssembler.php-128-     * @param array<string, AuthorView> $authors
app/src/Modules/Posts/Application/View/CommentViewAssembler.php-129-     */
app/src/Modules/Posts/Application/View/CommentViewAssembler.php:130:    private function requireAuthor(array $authors, string $userId): AuthorView
app/src/Modules/Posts/Application/View/CommentViewAssembler.php-131-    {
app/src/Modules/Posts/Application/View/CommentViewAssembler.php-132-        return $authors[$userId] ?? throw new NotFoundException('app.user.not_found');
app/src/Modules/Posts/Application/View/CommentViewAssembler.php-133-    }
app/src/Modules/Posts/Application/View/CommentViewAssembler.php-134-
--
tests/Feature/Modules/Posts/Http/PostReactionHttpTest.php-8-use App\Shared\Domain\ValueObject\UserId;
tests/Feature/Modules/Posts/Http/PostReactionHttpTest.php-9-
tests/Feature/Modules/Posts/Http/PostReactionHttpTest.php-10-final class PostReactionHttpTest extends PostsHttpTestCase
tests/Feature/Modules/Posts/Http/PostReactionHttpTest.php-11-{
tests/Feature/Modules/Posts/Http/PostReactionHttpTest.php:12:    public function testLikesPostAndNotifiesAuthor(): void
tests/Feature/Modules/Posts/Http/PostReactionHttpTest.php-13-    {
tests/Feature/Modules/Posts/Http/PostReactionHttpTest.php-14-        $author = $this->createUser();
tests/Feature/Modules/Posts/Http/PostReactionHttpTest.php-15-        $liker = $this->createUser();
tests/Feature/Modules/Posts/Http/PostReactionHttpTest.php-16-        $post = $this->persistPost($author->id, PostStatus::Published);
--
tests/Feature/Modules/Posts/Http/CommentReactionHttpTest.php-8-use App\Shared\Domain\ValueObject\UserId;
tests/Feature/Modules/Posts/Http/CommentReactionHttpTest.php-9-
tests/Feature/Modules/Posts/Http/CommentReactionHttpTest.php-10-final class CommentReactionHttpTest extends PostsHttpTestCase
tests/Feature/Modules/Posts/Http/CommentReactionHttpTest.php-11-{
tests/Feature/Modules/Posts/Http/CommentReactionHttpTest.php:12:    public function testLikesCommentAndNotifiesAuthor(): void
tests/Feature/Modules/Posts/Http/CommentReactionHttpTest.php-13-    {
tests/Feature/Modules/Posts/Http/CommentReactionHttpTest.php-14-        $author = $this->createUser();
tests/Feature/Modules/Posts/Http/CommentReactionHttpTest.php-15-        $liker = $this->createUser();
tests/Feature/Modules/Posts/Http/CommentReactionHttpTest.php-16-        $post = $this->persistPost($author->id, PostStatus::Published);
--
tests/Feature/Modules/Posts/Http/RepostPostHttpTest.php-9-use App\Shared\Domain\ValueObject\UserId;
tests/Feature/Modules/Posts/Http/RepostPostHttpTest.php-10-
tests/Feature/Modules/Posts/Http/RepostPostHttpTest.php-11-final class RepostPostHttpTest extends PostsHttpTestCase
tests/Feature/Modules/Posts/Http/RepostPostHttpTest.php-12-{
tests/Feature/Modules/Posts/Http/RepostPostHttpTest.php:13:    public function testCreatesRepostIncrementsOriginalAndNotifiesAuthor(): void
tests/Feature/Modules/Posts/Http/RepostPostHttpTest.php-14-    {
tests/Feature/Modules/Posts/Http/RepostPostHttpTest.php-15-        $author = $this->createUser();
tests/Feature/Modules/Posts/Http/RepostPostHttpTest.php-16-        $reposter = $this->createUser();
tests/Feature/Modules/Posts/Http/RepostPostHttpTest.php-17-        $original = $this->persistPost($author->id, PostStatus::Published);
--
tests/Feature/Modules/Posts/Repository/PostRepositoryTest.php-175-        $this->persist($published);
tests/Feature/Modules/Posts/Repository/PostRepositoryTest.php-176-        $draft = $this->newPost($user->id, PostStatus::Draft);
tests/Feature/Modules/Posts/Repository/PostRepositoryTest.php-177-        $this->persist($draft);
tests/Feature/Modules/Posts/Repository/PostRepositoryTest.php-178-        $this->persist($this->newPost($user->id, PostStatus::Blocked));
tests/Feature/Modules/Posts/Repository/PostRepositoryTest.php:179:        $softDeleted = $this->newPost($user->id, PostStatus::Published);
tests/Feature/Modules/Posts/Repository/PostRepositoryTest.php-180-        $softDeleted->softDelete(new \DateTimeImmutable());
tests/Feature/Modules/Posts/Repository/PostRepositoryTest.php-181-        $this->persist($softDeleted);
tests/Feature/Modules/Posts/Repository/PostRepositoryTest.php-182-        $this->cleanOrmHeap();
tests/Feature/Modules/Posts/Repository/PostRepositoryTest.php-183-

exec
/bin/zsh -lc 'rg -n "deleted_at|UserDeletion|deletion" app/src/Modules/User/Domain/Entity/User.php app/src/Modules/User/Application/Profile/UserPublicProfileAssembler.php app/src/Modules/User/Application/Query -C 5' in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
app/src/Modules/User/Domain/Entity/User.php-7-use App\Modules\User\Domain\Enum\UserStatus;
app/src/Modules/User/Domain/Entity/User.php-8-use App\Modules\User\Domain\Enum\UserVerification;
app/src/Modules/User/Domain/Entity/User.php-9-use App\Modules\User\Domain\ValueObject\Email;
app/src/Modules/User/Domain/Entity/User.php-10-use App\Modules\User\Domain\ValueObject\UserAvatar;
app/src/Modules/User/Domain/Entity/User.php-11-use App\Modules\User\Domain\ValueObject\UserBio;
app/src/Modules/User/Domain/Entity/User.php:12:use App\Modules\User\Domain\ValueObject\UserDeletion;
app/src/Modules/User/Domain/Entity/User.php-13-use App\Modules\User\Domain\ValueObject\UserLocation;
app/src/Modules/User/Domain/Entity/User.php-14-use App\Modules\User\Domain\ValueObject\UserName;
app/src/Modules/User/Domain/Entity/User.php-15-use App\Modules\User\Domain\ValueObject\UserNickname;
app/src/Modules/User/Domain/Entity/User.php-16-use App\Modules\User\Domain\ValueObject\UserSpiritualName;
app/src/Modules/User/Domain/Entity/User.php-17-use App\Modules\User\Infrastructure\Cycle\UserAvatarTypecast;
app/src/Modules/User/Domain/Entity/User.php-18-use App\Modules\User\Infrastructure\Cycle\UserBioTypecast;
app/src/Modules/User/Domain/Entity/User.php:19:use App\Modules\User\Infrastructure\Cycle\UserDeletionTypecast;
app/src/Modules/User/Domain/Entity/User.php-20-use App\Modules\User\Infrastructure\Cycle\UserLocationTypecast;
app/src/Modules/User/Domain/Entity/User.php-21-use App\Modules\User\Infrastructure\Cycle\UserSpiritualNameTypecast;
app/src/Modules/User/Domain/Entity/User.php-22-use App\Modules\User\Repository\UserRepository;
app/src/Modules/User/Domain/Entity/User.php-23-use App\Shared\Domain\Enum\Locale;
app/src/Modules/User/Domain/Entity/User.php-24-use App\Shared\Domain\Trait\HasTimestamps;
--
app/src/Modules/User/Domain/Entity/User.php-69-    public private(set) UserStatus $status;
app/src/Modules/User/Domain/Entity/User.php-70-
app/src/Modules/User/Domain/Entity/User.php-71-    #[Column(type: 'string(8)', typecast: Locale::class)]
app/src/Modules/User/Domain/Entity/User.php-72-    public private(set) Locale $locale;
app/src/Modules/User/Domain/Entity/User.php-73-
app/src/Modules/User/Domain/Entity/User.php:74:    #[Column(type: 'datetime', name: 'deleted_at', nullable: true, typecast: UserDeletionTypecast::class)]
app/src/Modules/User/Domain/Entity/User.php:75:    public private(set) UserDeletion $deletion;
app/src/Modules/User/Domain/Entity/User.php-76-
app/src/Modules/User/Domain/Entity/User.php-77-    public static function create(
app/src/Modules/User/Domain/Entity/User.php-78-        UserName $name,
app/src/Modules/User/Domain/Entity/User.php-79-        Email $email,
app/src/Modules/User/Domain/Entity/User.php-80-        UserNickname $nickname,
--
app/src/Modules/User/Domain/Entity/User.php-90-        $user->nickname = $nickname;
app/src/Modules/User/Domain/Entity/User.php-91-        $user->avatar = UserAvatar::none();
app/src/Modules/User/Domain/Entity/User.php-92-        $user->verification = UserVerification::Unverified;
app/src/Modules/User/Domain/Entity/User.php-93-        $user->status = UserStatus::WaitingEmailConfirmation;
app/src/Modules/User/Domain/Entity/User.php-94-        $user->locale = $locale;
app/src/Modules/User/Domain/Entity/User.php:95:        $user->deletion = UserDeletion::active();
app/src/Modules/User/Domain/Entity/User.php-96-        $user->initializeTimestamps();
app/src/Modules/User/Domain/Entity/User.php-97-
app/src/Modules/User/Domain/Entity/User.php-98-        return $user;
app/src/Modules/User/Domain/Entity/User.php-99-    }
app/src/Modules/User/Domain/Entity/User.php-100-
--
app/src/Modules/User/Domain/Entity/User.php-183-        $this->touch();
app/src/Modules/User/Domain/Entity/User.php-184-    }
app/src/Modules/User/Domain/Entity/User.php-185-
app/src/Modules/User/Domain/Entity/User.php-186-    public function markDeleted(\DateTimeImmutable $now): void
app/src/Modules/User/Domain/Entity/User.php-187-    {
app/src/Modules/User/Domain/Entity/User.php:188:        $this->deletion = UserDeletion::at($now);
app/src/Modules/User/Domain/Entity/User.php-189-        $this->status = UserStatus::Deleted;
app/src/Modules/User/Domain/Entity/User.php-190-        $this->touch(now: $now);
app/src/Modules/User/Domain/Entity/User.php-191-    }
app/src/Modules/User/Domain/Entity/User.php-192-
app/src/Modules/User/Domain/Entity/User.php-193-    public function isActive(): bool
--
app/src/Modules/User/Domain/Entity/User.php-205-        return $this->status === UserStatus::Banned;
app/src/Modules/User/Domain/Entity/User.php-206-    }
app/src/Modules/User/Domain/Entity/User.php-207-
app/src/Modules/User/Domain/Entity/User.php-208-    public function isDeleted(): bool
app/src/Modules/User/Domain/Entity/User.php-209-    {
app/src/Modules/User/Domain/Entity/User.php:210:        return $this->status === UserStatus::Deleted || $this->deletion->isDeleted();
app/src/Modules/User/Domain/Entity/User.php-211-    }
app/src/Modules/User/Domain/Entity/User.php-212-}

exec
/bin/zsh -lc "sed -n '1,220p' app/src/Modules/User/Domain/Entity/User.php && sed -n '1,120p' app/src/Modules/User/Application/Profile/UserPublicProfileAssembler.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\User\Domain\Entity;

use App\Modules\User\Domain\Enum\UserStatus;
use App\Modules\User\Domain\Enum\UserVerification;
use App\Modules\User\Domain\ValueObject\Email;
use App\Modules\User\Domain\ValueObject\UserAvatar;
use App\Modules\User\Domain\ValueObject\UserBio;
use App\Modules\User\Domain\ValueObject\UserDeletion;
use App\Modules\User\Domain\ValueObject\UserLocation;
use App\Modules\User\Domain\ValueObject\UserName;
use App\Modules\User\Domain\ValueObject\UserNickname;
use App\Modules\User\Domain\ValueObject\UserSpiritualName;
use App\Modules\User\Infrastructure\Cycle\UserAvatarTypecast;
use App\Modules\User\Infrastructure\Cycle\UserBioTypecast;
use App\Modules\User\Infrastructure\Cycle\UserDeletionTypecast;
use App\Modules\User\Infrastructure\Cycle\UserLocationTypecast;
use App\Modules\User\Infrastructure\Cycle\UserSpiritualNameTypecast;
use App\Modules\User\Repository\UserRepository;
use App\Shared\Domain\Enum\Locale;
use App\Shared\Domain\Trait\HasTimestamps;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Cycle\ValueObjectCast;
use Cycle\Annotated\Annotation\Column;
use Cycle\Annotated\Annotation\Entity;
use Cycle\ORM\Parser\Typecast;

#[Entity(
    role: 'user',
    table: 'users',
    repository: UserRepository::class,
    typecast: [Typecast::class, ValueObjectCast::class],
)]
final class User
{
    use HasTimestamps;

    #[Column(type: 'uuid', primary: true, typecast: UserId::class)]
    public private(set) UserId $id;

    #[Column(type: 'string(100)', typecast: UserName::class)]
    public private(set) UserName $name;

    #[Column(type: 'string(100)', name: 'spiritual_name', nullable: true, typecast: UserSpiritualNameTypecast::class)]
    public private(set) UserSpiritualName $spiritualName;

    #[Column(type: 'text', nullable: true, typecast: UserBioTypecast::class)]
    public private(set) UserBio $bio;

    #[Column(type: 'string(100)', nullable: true, typecast: UserLocationTypecast::class)]
    public private(set) UserLocation $location;

    #[Column(type: 'string(254)', typecast: Email::class)]
    public private(set) Email $email;

    #[Column(type: 'string(30)', typecast: UserNickname::class)]
    public private(set) UserNickname $nickname;

    #[Column(type: 'uuid', name: 'avatar_media_id', nullable: true, typecast: UserAvatarTypecast::class)]
    public private(set) UserAvatar $avatar;

    #[Column(type: 'string(32)', typecast: UserVerification::class)]
    public private(set) UserVerification $verification;

    #[Column(type: 'string(32)', typecast: UserStatus::class)]
    public private(set) UserStatus $status;

    #[Column(type: 'string(8)', typecast: Locale::class)]
    public private(set) Locale $locale;

    #[Column(type: 'datetime', name: 'deleted_at', nullable: true, typecast: UserDeletionTypecast::class)]
    public private(set) UserDeletion $deletion;

    public static function create(
        UserName $name,
        Email $email,
        UserNickname $nickname,
        Locale $locale,
    ): self {
        $user = new self();
        $user->id = UserId::generate();
        $user->name = $name;
        $user->spiritualName = UserSpiritualName::none();
        $user->bio = UserBio::none();
        $user->location = UserLocation::none();
        $user->email = $email;
        $user->nickname = $nickname;
        $user->avatar = UserAvatar::none();
        $user->verification = UserVerification::Unverified;
        $user->status = UserStatus::WaitingEmailConfirmation;
        $user->locale = $locale;
        $user->deletion = UserDeletion::active();
        $user->initializeTimestamps();

        return $user;
    }

    public function confirmEmail(): void
    {
        $this->status = UserStatus::Active;
        $this->touch();
    }

    public function changeEmail(Email $email): void
    {
        $this->email = $email;
        $this->status = UserStatus::WaitingEmailConfirmation;
        $this->touch();
    }

    public function changeNickname(UserNickname $nickname): void
    {
        $this->nickname = $nickname;
        $this->touch();
    }

    public function rename(UserName $name): void
    {
        $this->name = $name;
        $this->touch();
    }

    public function changeSpiritualName(UserSpiritualName $spiritualName): void
    {
        $this->spiritualName = $spiritualName;
        $this->touch();
    }

    public function changeBio(UserBio $bio): void
    {
        $this->bio = $bio;
        $this->touch();
    }

    public function changeLocation(UserLocation $location): void
    {
        $this->location = $location;
        $this->touch();
    }

    public function changeLocale(Locale $locale): void
    {
        $this->locale = $locale;
        $this->touch();
    }

    public function setAvatar(UserAvatar $avatar): void
    {
        $this->avatar = $avatar;
        $this->touch();
    }

    public function removeAvatar(): void
    {
        $this->avatar = UserAvatar::none();
        $this->touch();
    }

    public function verify(): void
    {
        $this->verification = UserVerification::Verified;
        $this->touch();
    }

    public function unverify(): void
    {
        $this->verification = UserVerification::Unverified;
        $this->touch();
    }

    public function ban(): void
    {
        $this->status = UserStatus::Banned;
        $this->touch();
    }

    public function unban(): void
    {
        $this->status = UserStatus::Active;
        $this->touch();
    }

    public function markDeleted(\DateTimeImmutable $now): void
    {
        $this->deletion = UserDeletion::at($now);
        $this->status = UserStatus::Deleted;
        $this->touch(now: $now);
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    public function isVerified(): bool
    {
        return $this->verification === UserVerification::Verified;
    }

    public function isBanned(): bool
    {
        return $this->status === UserStatus::Banned;
    }

    public function isDeleted(): bool
    {
        return $this->status === UserStatus::Deleted || $this->deletion->isDeleted();
    }
}
<?php

declare(strict_types=1);

namespace App\Modules\User\Application\Profile;

use App\Modules\Media\Application\Query\FindMediaUrl\FindMediaUrlHandler;
use App\Modules\Media\Application\Query\FindMediaUrl\FindMediaUrlQuery;
use App\Modules\User\Application\Dto\UserPublicProfileView;
use App\Modules\User\Domain\Entity\User;
use App\Shared\Infrastructure\Configuration\User\UserConfig;

/**
 * Собирает публичный профиль из доменной сущности User, разрешая ссылку на аватар через модуль
 * Media. Аватар всегда непустой: если у пользователя нет аватара или его медиа недоступно
 * (удалено/не готово) — подставляется значение по умолчанию из UserConfig.
 *
 * FindMediaUrl вызывается напрямую (а не через QueryBus), потому что это единственный сценарий,
 * возвращающий nullable, и обёртка шины теряет null из вывода типов — прямой вызов сохраняет
 * контракт «медиа недоступно -> null -> дефолт» без try-catch и без подавления статанализа.
 */
final readonly class UserPublicProfileAssembler
{
    // Аватары публичны (прямой URL без срока), поэтому TTL фактически не используется; значение
    // нужно только для приватной ветки FindMediaUrl и берётся техническим дефолтом рядом с местом.
    private const int AVATAR_URL_TTL_SECONDS = 3600;

    public function __construct(
        private FindMediaUrlHandler $findMediaUrlHandler,
        private UserConfig $userConfig,
    ) {}

    public function fromUser(User $user): UserPublicProfileView
    {
        return new UserPublicProfileView(
            userId: $user->id->value(),
            name: $user->name->value(),
            avatarUrl: $this->resolveAvatarUrl($user),
            locale: $user->locale->value,
        );
    }

    private function resolveAvatarUrl(User $user): string
    {
        $mediaId = $user->avatar->value();

        if ($mediaId === null) {
            return $this->userConfig->defaultAvatarUrl;
        }

        $mediaUrl = $this->findMediaUrlHandler->handle(
            new FindMediaUrlQuery(mediaId: $mediaId, presignedTtlSeconds: self::AVATAR_URL_TTL_SECONDS),
        );

        if ($mediaUrl === null) {
            return $this->userConfig->defaultAvatarUrl;
        }

        return $mediaUrl->url;
    }
}

exec
/bin/zsh -lc "sed -n '1,220p' app/src/Modules/Posts/Presentation/Http/Controller/PostController.php && sed -n '1,220p' app/src/Modules/Posts/Presentation/Http/Controller/CommentController.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Posts\Presentation\Http\Controller;

use App\Modules\Auth\Presentation\Http\Middleware\AuthContextAttributeMiddleware;
use App\Modules\Auth\Presentation\Http\Middleware\RequireAuthenticatedMiddleware;
use App\Modules\Posts\Application\Command\CreatePost\CreatePostCommand;
use App\Modules\Posts\Application\Command\CreatePost\CreatePostHandler;
use App\Modules\Posts\Application\Command\DeletePost\DeletePostCommand;
use App\Modules\Posts\Application\Command\DeletePost\DeletePostHandler;
use App\Modules\Posts\Application\Command\LikePost\LikePostCommand;
use App\Modules\Posts\Application\Command\LikePost\LikePostHandler;
use App\Modules\Posts\Application\Command\PublishPost\PublishPostCommand;
use App\Modules\Posts\Application\Command\PublishPost\PublishPostHandler;
use App\Modules\Posts\Application\Command\RepostPost\RepostPostCommand;
use App\Modules\Posts\Application\Command\RepostPost\RepostPostHandler;
use App\Modules\Posts\Application\Command\UnlikePost\UnlikePostCommand;
use App\Modules\Posts\Application\Command\UnlikePost\UnlikePostHandler;
use App\Modules\Posts\Application\Query\GetPost\GetPostHandler;
use App\Modules\Posts\Application\Query\GetPost\GetPostQuery;
use App\Modules\Posts\Application\Query\GetUserFeed\GetUserFeedHandler;
use App\Modules\Posts\Application\Query\GetUserFeed\GetUserFeedQuery;
use App\Modules\Posts\Application\View\PostView;
use App\Modules\Posts\Presentation\Http\Filter\CreatePostFilter;
use App\Modules\Posts\Presentation\Http\Filter\DeletePostFilter;
use App\Modules\Posts\Presentation\Http\Filter\GetPostFilter;
use App\Modules\Posts\Presentation\Http\Filter\GetUserFeedFilter;
use App\Modules\Posts\Presentation\Http\Filter\LikePostFilter;
use App\Modules\Posts\Presentation\Http\Filter\PublishPostFilter;
use App\Modules\Posts\Presentation\Http\Filter\RepostPostFilter;
use App\Modules\Posts\Presentation\Http\Filter\UnlikePostFilter;
use App\Modules\Posts\Presentation\Http\Resource\PostResource;
use GianTiaga\SpiralCqrs\CommandBusInterface;
use GianTiaga\SpiralCqrs\QueryBusInterface;
use GianTiaga\SpiralOpenApi\Response\DataResponse;
use GianTiaga\SpiralOpenApi\Response\EmptySuccessResponse;
use GianTiaga\SpiralOpenApi\Response\PaginationMetaResponse;
use GianTiaga\SpiralOpenApi\Response\PaginationResponse;
use Spiral\Auth\Middleware\AuthTransportWithStorageMiddleware;
use Spiral\Core\Container\Autowire;
use Spiral\Router\Annotation\Route;

final readonly class PostController
{
    /**
     * @return DataResponse<PostResource>
     */
    #[Route(
        route: '/api/v1/posts',
        name: 'api.v1.posts.create',
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
    public function create(
        CreatePostFilter $createPostFilter,
        CommandBusInterface $commandBus,
        CreatePostHandler $createPostHandler,
    ): DataResponse {
        $post = $commandBus->dispatch(
            command: new CreatePostCommand(
                authUserId: $createPostFilter->authUserId,
                text: $createPostFilter->text,
                draft: $createPostFilter->draft,
                mediaIds: $createPostFilter->mediaIds,
                tags: $createPostFilter->tags,
                mentions: $createPostFilter->mentions,
            ),
            handler: $createPostHandler->handle(...),
        );

        return new DataResponse(PostResource::fromView($post));
    }

    /**
     * @return DataResponse<PostResource>
     */
    #[Route(
        route: '/api/v1/posts/<id>/publish',
        name: 'api.v1.posts.publish',
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
    public function publish(
        string $id,
        PublishPostFilter $publishPostFilter,
        CommandBusInterface $commandBus,
        PublishPostHandler $publishPostHandler,
    ): DataResponse {
        $post = $commandBus->dispatch(
            command: new PublishPostCommand(
                authUserId: $publishPostFilter->authUserId,
                postId: $publishPostFilter->id,
            ),
            handler: $publishPostHandler->handle(...),
        );

        return new DataResponse(PostResource::fromView($post));
    }

    /**
     * @return DataResponse<PostResource>
     */
    #[Route(
        route: '/api/v1/posts/<id>/repost',
        name: 'api.v1.posts.repost',
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
    public function repost(
        string $id,
        RepostPostFilter $repostPostFilter,
        CommandBusInterface $commandBus,
        RepostPostHandler $repostPostHandler,
    ): DataResponse {
        $post = $commandBus->dispatch(
            command: new RepostPostCommand(
                authUserId: $repostPostFilter->authUserId,
                postId: $repostPostFilter->id,
                text: $repostPostFilter->text,
                mediaIds: $repostPostFilter->mediaIds,
                tags: $repostPostFilter->tags,
                mentions: $repostPostFilter->mentions,
            ),
            handler: $repostPostHandler->handle(...),
        );

        return new DataResponse(PostResource::fromView($post));
    }

    #[Route(
        route: '/api/v1/posts/<id>',
        name: 'api.v1.posts.delete',
        methods: ['DELETE'],
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
    public function delete(
        string $id,
        DeletePostFilter $deletePostFilter,
        CommandBusInterface $commandBus,
        DeletePostHandler $deletePostHandler,
    ): EmptySuccessResponse {
        $commandBus->dispatch(
            command: new DeletePostCommand(
                authUserId: $deletePostFilter->authUserId,
                postId: $deletePostFilter->id,
            ),
            handler: $deletePostHandler->handle(...),
        );

        return new EmptySuccessResponse();
    }

    #[Route(
        route: '/api/v1/posts/<id>/like',
        name: 'api.v1.posts.like',
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
    public function like(
        string $id,
        LikePostFilter $likePostFilter,
        CommandBusInterface $commandBus,
        LikePostHandler $likePostHandler,
    ): EmptySuccessResponse {
        $commandBus->dispatch(
            command: new LikePostCommand(
                authUserId: $likePostFilter->authUserId,
                postId: $likePostFilter->id,
            ),
            handler: $likePostHandler->handle(...),
        );

        return new EmptySuccessResponse();
    }

    #[Route(
        route: '/api/v1/posts/<id>/like',
        name: 'api.v1.posts.unlike',
<?php

declare(strict_types=1);

namespace App\Modules\Posts\Presentation\Http\Controller;

use App\Modules\Auth\Presentation\Http\Middleware\AuthContextAttributeMiddleware;
use App\Modules\Auth\Presentation\Http\Middleware\RequireAuthenticatedMiddleware;
use App\Modules\Posts\Application\Command\CommentPost\CommentPostCommand;
use App\Modules\Posts\Application\Command\CommentPost\CommentPostHandler;
use App\Modules\Posts\Application\Command\DeleteComment\DeleteCommentCommand;
use App\Modules\Posts\Application\Command\DeleteComment\DeleteCommentHandler;
use App\Modules\Posts\Application\Command\LikeComment\LikeCommentCommand;
use App\Modules\Posts\Application\Command\LikeComment\LikeCommentHandler;
use App\Modules\Posts\Application\Command\ReplyComment\ReplyCommentCommand;
use App\Modules\Posts\Application\Command\ReplyComment\ReplyCommentHandler;
use App\Modules\Posts\Application\Command\UnlikeComment\UnlikeCommentCommand;
use App\Modules\Posts\Application\Command\UnlikeComment\UnlikeCommentHandler;
use App\Modules\Posts\Application\Query\GetCommentReplies\GetCommentRepliesHandler;
use App\Modules\Posts\Application\Query\GetCommentReplies\GetCommentRepliesQuery;
use App\Modules\Posts\Application\Query\GetPostComments\GetPostCommentsHandler;
use App\Modules\Posts\Application\Query\GetPostComments\GetPostCommentsQuery;
use App\Modules\Posts\Application\View\CommentView;
use App\Modules\Posts\Presentation\Http\Filter\CommentPostFilter;
use App\Modules\Posts\Presentation\Http\Filter\DeleteCommentFilter;
use App\Modules\Posts\Presentation\Http\Filter\GetCommentRepliesFilter;
use App\Modules\Posts\Presentation\Http\Filter\GetPostCommentsFilter;
use App\Modules\Posts\Presentation\Http\Filter\LikeCommentFilter;
use App\Modules\Posts\Presentation\Http\Filter\ReplyCommentFilter;
use App\Modules\Posts\Presentation\Http\Filter\UnlikeCommentFilter;
use App\Modules\Posts\Presentation\Http\Resource\CommentResource;
use GianTiaga\SpiralCqrs\CommandBusInterface;
use GianTiaga\SpiralCqrs\QueryBusInterface;
use GianTiaga\SpiralOpenApi\Response\DataResponse;
use GianTiaga\SpiralOpenApi\Response\EmptySuccessResponse;
use GianTiaga\SpiralOpenApi\Response\PaginationMetaResponse;
use GianTiaga\SpiralOpenApi\Response\PaginationResponse;
use Spiral\Auth\Middleware\AuthTransportWithStorageMiddleware;
use Spiral\Core\Container\Autowire;
use Spiral\Router\Annotation\Route;

final readonly class CommentController
{
    /**
     * @return DataResponse<CommentResource>
     */
    #[Route(
        route: '/api/v1/posts/<id>/comments',
        name: 'api.v1.posts.comments.create',
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
    public function comment(
        string $id,
        CommentPostFilter $commentPostFilter,
        CommandBusInterface $commandBus,
        CommentPostHandler $commentPostHandler,
    ): DataResponse {
        $comment = $commandBus->dispatch(
            command: new CommentPostCommand(
                authUserId: $commentPostFilter->authUserId,
                postId: $commentPostFilter->id,
                text: $commentPostFilter->text,
                mentions: $commentPostFilter->mentions,
            ),
            handler: $commentPostHandler->handle(...),
        );

        return new DataResponse(CommentResource::fromView($comment));
    }

    /**
     * @return DataResponse<CommentResource>
     */
    #[Route(
        route: '/api/v1/posts/comments/<id>/replies',
        name: 'api.v1.posts.comments.reply',
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
    public function reply(
        string $id,
        ReplyCommentFilter $replyCommentFilter,
        CommandBusInterface $commandBus,
        ReplyCommentHandler $replyCommentHandler,
    ): DataResponse {
        $comment = $commandBus->dispatch(
            command: new ReplyCommentCommand(
                authUserId: $replyCommentFilter->authUserId,
                commentId: $replyCommentFilter->id,
                text: $replyCommentFilter->text,
                mentions: $replyCommentFilter->mentions,
            ),
            handler: $replyCommentHandler->handle(...),
        );

        return new DataResponse(CommentResource::fromView($comment));
    }

    #[Route(
        route: '/api/v1/posts/comments/<id>',
        name: 'api.v1.posts.comments.delete',
        methods: ['DELETE'],
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
    public function delete(
        string $id,
        DeleteCommentFilter $deleteCommentFilter,
        CommandBusInterface $commandBus,
        DeleteCommentHandler $deleteCommentHandler,
    ): EmptySuccessResponse {
        $commandBus->dispatch(
            command: new DeleteCommentCommand(
                authUserId: $deleteCommentFilter->authUserId,
                commentId: $deleteCommentFilter->id,
            ),
            handler: $deleteCommentHandler->handle(...),
        );

        return new EmptySuccessResponse();
    }

    #[Route(
        route: '/api/v1/posts/comments/<id>/like',
        name: 'api.v1.posts.comments.like',
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
    public function like(
        string $id,
        LikeCommentFilter $likeCommentFilter,
        CommandBusInterface $commandBus,
        LikeCommentHandler $likeCommentHandler,
    ): EmptySuccessResponse {
        $commandBus->dispatch(
            command: new LikeCommentCommand(
                authUserId: $likeCommentFilter->authUserId,
                commentId: $likeCommentFilter->id,
            ),
            handler: $likeCommentHandler->handle(...),
        );

        return new EmptySuccessResponse();
    }

    #[Route(
        route: '/api/v1/posts/comments/<id>/like',
        name: 'api.v1.posts.comments.unlike',
        methods: ['DELETE'],
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
    public function unlike(
        string $id,
        UnlikeCommentFilter $unlikeCommentFilter,
        CommandBusInterface $commandBus,
        UnlikeCommentHandler $unlikeCommentHandler,
    ): EmptySuccessResponse {
        $commandBus->dispatch(
            command: new UnlikeCommentCommand(
                authUserId: $unlikeCommentFilter->authUserId,
                commentId: $unlikeCommentFilter->id,
            ),
            handler: $unlikeCommentHandler->handle(...),
        );

        return new EmptySuccessResponse();
    }

    /**
     * @return PaginationResponse<CommentResource>
     */
    #[Route(
        route: '/api/v1/posts/<id>/comments',
        name: 'api.v1.posts.comments.list',
        methods: ['GET'],
        group: 'api',
        middleware: [
            new Autowire(
                alias: AuthTransportWithStorageMiddleware::class,
                parameters: ['transportName' => 'header', 'storage' => 'cycle'],

exec
/bin/zsh -lc "sed -n '1,140p' app/src/Modules/Posts/Presentation/Http/Filter/CreatePostFilter.php && sed -n '1,100p' app/src/Modules/Posts/Presentation/Http/Filter/CommentPostFilter.php && sed -n '1,100p' app/src/Modules/Posts/Presentation/Http/Filter/ReplyCommentFilter.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Posts\Presentation\Http\Filter;

use Spiral\Filters\Attribute\Input\Attribute;
use Spiral\Filters\Attribute\Input\Post;
use Spiral\Validation\Symfony\AttributesFilter;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Создание записи. Лимиты применяются на HTTP-границе (422), чтобы превышение длины/количества и
 * кривой uuid медиа/упоминания не доходили до доменных VO (500): текст <=5000, медиа <=10 (uuid),
 * теги <=20 (длина <=50), упоминания <=50 (uuid).
 */
final class CreatePostFilter extends AttributesFilter
{
    #[Attribute(key: 'authUserId')]
    #[Assert\NotBlank]
    public string $authUserId;

    #[Post]
    #[Assert\Length(max: 5000)]
    public string|null $text = null;

    #[Post]
    public bool $draft = false;

    /**
     * @var list<string>
     */
    #[Post]
    #[Assert\Count(max: 10)]
    #[Assert\All([new Assert\Uuid()])]
    public array $mediaIds = [];

    /**
     * @var list<string>
     */
    #[Post]
    #[Assert\Count(max: 20)]
    #[Assert\All([new Assert\NotBlank(), new Assert\Length(max: 50)])]
    public array $tags = [];

    /**
     * @var list<string>
     */
    #[Post]
    #[Assert\Count(max: 50)]
    #[Assert\All([new Assert\Uuid()])]
    public array $mentions = [];
}
<?php

declare(strict_types=1);

namespace App\Modules\Posts\Presentation\Http\Filter;

use Spiral\Filters\Attribute\Input\Attribute;
use Spiral\Filters\Attribute\Input\Post;
use Spiral\Filters\Attribute\Input\Route;
use Spiral\Validation\Symfony\AttributesFilter;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Комментарий к записи: <id> записи из пути (uuid), обязательный текст (<=2000) и упоминания
 * (<=50 uuid). Лимиты на HTTP-границе дают 422 вместо 500 из доменных VO.
 */
final class CommentPostFilter extends AttributesFilter
{
    #[Attribute(key: 'authUserId')]
    #[Assert\NotBlank]
    public string $authUserId;

    #[Route(key: 'id')]
    #[Assert\NotBlank]
    #[Assert\Uuid]
    public string $id;

    #[Post]
    #[Assert\NotBlank]
    #[Assert\Length(max: 2000)]
    public string $text;

    /**
     * @var list<string>
     */
    #[Post]
    #[Assert\Count(max: 50)]
    #[Assert\All([new Assert\Uuid()])]
    public array $mentions = [];
}
<?php

declare(strict_types=1);

namespace App\Modules\Posts\Presentation\Http\Filter;

use Spiral\Filters\Attribute\Input\Attribute;
use Spiral\Filters\Attribute\Input\Post;
use Spiral\Filters\Attribute\Input\Route;
use Spiral\Validation\Symfony\AttributesFilter;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Ответ на комментарий: <id> родительского комментария из пути (uuid), обязательный текст (<=2000)
 * и упоминания (<=50 uuid).
 */
final class ReplyCommentFilter extends AttributesFilter
{
    #[Attribute(key: 'authUserId')]
    #[Assert\NotBlank]
    public string $authUserId;

    #[Route(key: 'id')]
    #[Assert\NotBlank]
    #[Assert\Uuid]
    public string $id;

    #[Post]
    #[Assert\NotBlank]
    #[Assert\Length(max: 2000)]
    public string $text;

    /**
     * @var list<string>
     */
    #[Post]
    #[Assert\Count(max: 50)]
    #[Assert\All([new Assert\Uuid()])]
    public array $mentions = [];
}

exec
/bin/zsh -lc "sed -n '1,180p' app/src/Modules/Posts/Application/Command/CreatePost/CreatePostHandler.php && sed -n '1,170p' app/src/Modules/Posts/Application/Command/RepostPost/RepostPostHandler.php" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Command\CreatePost;

use App\Modules\Posts\Application\Post\PostContentComposer;
use App\Modules\Posts\Application\View\PostView;
use App\Modules\Posts\Application\View\PostViewAssembler;
use App\Modules\Posts\Domain\Entity\Post;
use App\Modules\Posts\Domain\Enum\AttachmentType;
use App\Modules\Posts\Domain\Enum\PostStatus;
use App\Modules\Posts\Domain\ValueObject\PostLesson;
use App\Modules\Posts\Domain\ValueObject\PostOriginal;
use App\Modules\Posts\Domain\ValueObject\PostPractice;
use App\Modules\Posts\Domain\ValueObject\PostText;
use App\Shared\Domain\ValueObject\UserId;
use Cycle\ORM\EntityManagerInterface;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;
use GianTiaga\SpiralCqrs\Attribute\Transactional;
use Psr\Log\LoggerInterface;

/**
 * Создание записи: сразу опубликованной или как черновик. Теги, вложение медиа (проверка + перевод
 * в permanent) и упоминания выполняются в той же транзакции (вложенный dispatch -> SAVEPOINT,
 * решение 17 — атомарно), поэтому при откате осиротевших тегов/медиа не возникает. Упомянутым
 * (кроме автора) стейджится post_mention.
 */
final readonly class CreatePostHandler
{
    public function __construct(
        private PostContentComposer $composer,
        private PostViewAssembler $postViewAssembler,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}

    #[Transactional]
    #[LogOperation]
    public function handle(CreatePostCommand $command): PostView
    {
        $authUserId = UserId::fromString($command->authUserId);
        $tagIds = $this->composer->resolveTags(texts: $command->tags, creatorUserId: $command->authUserId);

        $post = Post::create(
            userId: $authUserId,
            text: $command->text !== null ? PostText::fromString($command->text) : PostText::none(),
            status: $command->draft ? PostStatus::Draft : PostStatus::Published,
            attachmentType: $command->mediaIds === [] ? AttachmentType::None : AttachmentType::Media,
            lesson: PostLesson::none(),
            practice: PostPractice::none(),
            original: PostOriginal::none(),
        );
        $this->entityManager->persist($post);

        $this->composer->attachMedia(post: $post, mediaIds: $command->mediaIds, ownerUserId: $command->authUserId);
        $this->composer->attachTags(post: $post, tagIds: $tagIds);
        $this->composer->attachPostMentions(post: $post, mentionIds: $command->mentions, actorUserId: $command->authUserId);

        $this->entityManager->run();

        $this->logger->debug(message: 'Запись создана.', context: [
            'postId' => $post->id->value(),
            'status' => $post->status->value,
        ]);

        return $this->postViewAssembler->fromPost(post: $post, viewer: $authUserId);
    }
}
<?php

declare(strict_types=1);

namespace App\Modules\Posts\Application\Command\RepostPost;

use App\Modules\Posts\Application\Notification\PostNotificationType;
use App\Modules\Posts\Application\Post\PostContentComposer;
use App\Modules\Posts\Application\Post\PostVisibilityPolicy;
use App\Modules\Posts\Application\View\PostView;
use App\Modules\Posts\Application\View\PostViewAssembler;
use App\Modules\Posts\Domain\Entity\Post;
use App\Modules\Posts\Domain\Enum\AttachmentType;
use App\Modules\Posts\Domain\Enum\PostStatus;
use App\Modules\Posts\Domain\ValueObject\PostId;
use App\Modules\Posts\Domain\ValueObject\PostLesson;
use App\Modules\Posts\Domain\ValueObject\PostOriginal;
use App\Modules\Posts\Domain\ValueObject\PostPractice;
use App\Modules\Posts\Domain\ValueObject\PostText;
use App\Modules\Posts\Repository\PostRepository;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\ValueObject\UserId;
use Cycle\ORM\EntityManagerInterface;
use GianTiaga\SpiralCqrs\Attribute\LogOperation;
use GianTiaga\SpiralCqrs\Attribute\Transactional;
use Psr\Log\LoggerInterface;

/**
 * Репост = цитата: новая опубликованная запись со ссылкой на оригинал и своим текстом/медиа/тегами.
 * Цель должна быть видимой и доступной для действия (опубликована, не удалена) — иначе 404. У
 * оригинала увеличивается счётчик репостов; автору оригинала (кроме self) стейджится post_repost.
 */
final readonly class RepostPostHandler
{
    public function __construct(
        private PostRepository $postRepository,
        private PostContentComposer $composer,
        private PostViewAssembler $postViewAssembler,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}

    #[Transactional]
    #[LogOperation]
    public function handle(RepostPostCommand $command): PostView
    {
        $authUserId = UserId::fromString($command->authUserId);

        $original = $this->postRepository->findById(PostId::fromString($command->postId))
            ?? throw new NotFoundException('app.posts.not_found');

        if (!PostVisibilityPolicy::isActionable($original)) {
            throw new NotFoundException('app.posts.not_found');
        }

        $tagIds = $this->composer->resolveTags(texts: $command->tags, creatorUserId: $command->authUserId);

        $post = Post::create(
            userId: $authUserId,
            text: $command->text !== null ? PostText::fromString($command->text) : PostText::none(),
            status: PostStatus::Published,
            attachmentType: $command->mediaIds === [] ? AttachmentType::None : AttachmentType::Media,
            lesson: PostLesson::none(),
            practice: PostPractice::none(),
            original: PostOriginal::pointingTo($original->id->value()),
        );
        $this->entityManager->persist($post);

        $this->composer->attachMedia(post: $post, mediaIds: $command->mediaIds, ownerUserId: $command->authUserId);
        $this->composer->attachTags(post: $post, tagIds: $tagIds);
        $this->composer->attachPostMentions(post: $post, mentionIds: $command->mentions, actorUserId: $command->authUserId);

        $original->incrementReposts();
        $this->entityManager->persist($original);

        $this->composer->notifyPostAuthor(
            type: PostNotificationType::PostRepost,
            postAuthor: $original->userId,
            actorUserId: $command->authUserId,
            postId: $post->id->value(),
        );

        $this->entityManager->run();

        $this->logger->debug(message: 'Создан репост.', context: [
            'postId' => $post->id->value(),
            'originalPostId' => $original->id->value(),
        ]);

        return $this->postViewAssembler->fromPost(post: $post, viewer: $authUserId);
    }
}

codex
Пока нашёл скорее пробел в доказательстве, чем подтверждённый дефект: тест есть на обычную рассылку после публикации, но не на сценарий “упомянутый пользователь исчез между созданием черновика и публикацией”. Это важно, потому что ревью прямо утверждает мягкое пропускание исчезнувших получателей.
exec
/bin/zsh -lc "sed -n '1,260p' docs/reviews/2026-06-19_21-30_posts-application-presentation_cli_meta.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
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
session id: 019ee0a3-d93d-7933-ad45-fdeef5c7dc9f
--------
user
Прочитай docs/reviews/2026-06-19_21-30_posts-application-presentation.md, его target (git diff HEAD + untracked-файлы Posts/User/Media/Tags), docs/plans/2026-06-18_17-42_posts-application-presentation.md, docs/rules.md, docs/arch.md. Затем глубоко проверь ревью по плану, правилам, архитектуре и релевантному коду: открой файлы, модули, API и тесты, которые следуют из diff и замечаний, и оцени фактическую корректность.

Это пятый (финальный) круг доводки, score 97/100. Особое внимание — возможным регрессиям от фиксов 4-го круга: новый app/src/Modules/Posts/Application/Post/MentionRecipientResolver.php (три режима requireAllExist/resolveRequired/resolveExisting), query app/src/Modules/User/Application/Query/CheckUsersExist/ + UserRepository::countByIds, guard requireAuthor() в PostViewAssembler/CommentViewAssembler, мягкая рассылка post_mention при публикации (PublishPostHandler + PostContentComposer::notifyPostMentions). Многие optional-замечания осознанно отклонены в прошлых кругах — не возвращай их без материально новых аргументов. Не подтверждай искусственных пунктов и не занижай оценку без обоснования.

Мета-ревью на русском: какие проблемы добавить, убрать, переформулировать; какие баги, риски для смежного кода и недостающие тесты пропущены. Не предлагай перечислять выполненную работу. Формат: '+', '−', '~', '?'.
codex
Использую `eda-review-check`, потому что задача именно на проверку готового ревью по плану, правилам, архитектуре и коду. Сначала прочитаю инструкции скилла, затем обязательные документы и сам target.
exec
/bin/zsh -lc "sed -n '1,240p' /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1/.codex/skills/eda-review-check/SKILL.md && sed -n '1,260p' docs/reviews/2026-06-19_21-30_posts-application-presentation.md && sed -n '1,260p' docs/plans/2026-06-18_17-42_posts-application-presentation.md && sed -n '1,260p' docs/rules.md && sed -n '1,260p' docs/arch.md && sed -n '1,220p' docs/code-examples.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
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
---
title: Модуль Posts/Tags/User — прикладной и презентационный слой (5-й круг, фокус на фиксах 4-го круга)
date: 2026-06-19 21:30
target: git diff HEAD + untracked
plan: docs/plans/2026-06-18_17-42_posts-application-presentation.md
mode: strict
score: 97
status: meta-reviewed
meta_reviewers: [plan-check (sonnet), architecture-check (sonnet), rules-check (sonnet), quality-check (sonnet)]
---

# Ревью: Модуль Posts/Tags/User — прикладной и презентационный слой (5-й круг)

## Оценка

**97/100.** Реализация плана полная, фиксы четвёртого круга корректны и не дали регрессий: новый
общий сервис разрешения упоминаний разведён по трём режимам осмысленно, дешёвая проверка
существования и guard автора покрыты тестами, мягкая рассылка при публикации не ломает прежнее
поведение и не дублирует уведомления. Новых реальных проблем не найдено; остаток до 100 — это
естественный потолок для большого изменения, прошедшего четыре круга, а не конкретный дефект, под
который стоило бы снижать оценку.

## Проблемы сверки с планом

Явных проблем по плану не найдено. План реализован по всем пяти фазам: 15 прикладных сценариев, 7
видов уведомлений, межмодульные публичные сценарии User/Media/Tags, конфиг User с дефолтным аватаром,
read-фильтры репозиториев, каждый HTTP-роут покрыт интеграционным тестом. Решения 1–27 соблюдены, в
том числе спорные (схема B, атомарная транзакция через SAVEPOINT, идемпотентность лайков/удаления,
дедуп уведомлений, лимиты во Filter, `Ready`-проверка медиа до перевода в permanent).

## Замечания

Явных проблем в коде не найдено.

Отдельно по запрошенным зонам фокуса (фиксы четвёртого круга) — проверено и подтверждено как
корректное, без снижения оценки:

- **`MentionRecipientResolver` и его три режима.** `requireAllExist` (черновик, без рассылки),
  `resolveRequired` (создание опубликованной записи и комментарии, строгая проверка полноты -> 422) и
  `resolveExisting` (мягкая рассылка по сохранённым упоминаниям при публикации) разведены по
  назначению и не пересекаются. Все вызывающие передают уже дедуплицированные списки, поэтому проверка
  полноты `count() !== count($userIds)` в `resolveRequired` не даёт ложного 422. Двойной рассылки нет:
  опубликованная запись уведомляет упоминания при создании, черновик — только при публикации, а
  `PublishPost` входит в ветку публикации лишь из статуса `Draft`, поэтому повторная публикация —
  no-op без второго уведомления.

- **`CheckUsersExist` / `UserRepository::countByIds`.** Дешёвая проверка существования согласована с
  «дорогим» путём: `User` использует обычную nullable-колонку `deleted_at` с собственным typecast, а
  не Cycle `SoftDelete` behavior, поэтому глобального скоупа `WHERE deleted_at IS NULL` нет — `count()`,
  `findByIds()` и `findById()` одинаково видят (или не видят) soft-deleted пользователей. Значит,
  валидация черновика (`countByIds`) и валидация опубликованной записи (`findByIds`) дадут один и тот
  же результат на одних и тех же данных — расхождения 422 между путями нет. Хэндлер и репозиторий
  корректно обрабатывают пустой вход и дедуплицируют идентификаторы.

- **Guard `requireAuthor` в ассемблерах.** Отсутствие автора в пакетной карте профилей теперь даёт ту
  же 404 (`app.user.not_found`), что и одиночный путь, вместо неконтролируемого обращения по
  несуществующему ключу массива -> 500. Поведение одиночного и пакетного путей приведено к единому
  контракту во всех трёх местах (`fromPosts`, `originalViewsByPost`, `fromComments`).

- **Мягкая рассылка при публикации (`notifyPostMentions` -> `resolveExisting`).** Читает сохранённые
  упоминания из своего репозитория, тихо пропускает исчезнувших получателей и не падает на строгой
  проверке полноты. Актор (публикующий владелец) существует по определению, поэтому строгий
  `profile()` для актора безопасен. Подтверждено интеграционным тестом
  `PublishPostHttpTest::testPublishingDraftNotifiesMentionedUsers` (0 уведомлений на черновике, ровно 1
  после публикации).

## Рекомендации

- **Править обязательно:** нет.
- **На усмотрение автора:** нет.

## Изменения после мета-ревью

### После plan-check / architecture-check / rules-check / quality-check

- **+ Добавлено:** ничего. Реальных новых проблем не подтверждено.
- **~ Изменено:** ничего.
- **− Убрано:** ничего.
- **Отклонено:**
  - «Добавить пункт: `MentionRecipientResolver::resolveRequired` не защищён от дублей изнутри —
    при будущем вызывающем без `array_unique` метод даст ложный 422; добавить
    `array_unique` в начало метода» (quality-check `+`, тип robustness, «на усмотрение») —
    отклонено. Проверено по коду: оба текущих вызывающих (`PostContentComposer:117` и
    `CommentComposer:43`) дедуплицируют список через `\array_values(\array_unique(...))` до
    вызова; контракт зафиксирован `@param list<string>`. Текущего дефекта нет, это
    спекулятивная защита под несуществующего будущего вызывающего — искусственный пункт для
    финального круга, ревью ведётся только по проблемам.
  - «Переформулировать тезис строки 42 (про дедуплицированные списки), т.к. он создаёт

exec
/bin/zsh -lc "rg -n \"foreign|post_mentions|comment_mentions|users|post_likes|comments|posts\" app/database app/src/Modules/Posts app/src/Modules/User -g '*.php' -C 2" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
app/database/migrations/20260613.143902_0_create_access_domain_tables.php-58-            ->addForeignKey(
app/database/migrations/20260613.143902_0_create_access_domain_tables.php-59-                ['user_id'],
app/database/migrations/20260613.143902_0_create_access_domain_tables.php:60:                'users',
app/database/migrations/20260613.143902_0_create_access_domain_tables.php-61-                ['id'],
app/database/migrations/20260613.143902_0_create_access_domain_tables.php-62-                ['delete' => 'CASCADE', 'update' => 'CASCADE', 'indexCreate' => false],
--
app/src/Modules/Posts/Presentation/Http/Resource/PostResource.php-22-        public string $attachmentType,
app/src/Modules/Posts/Presentation/Http/Resource/PostResource.php-23-        public int $likesCount,
app/src/Modules/Posts/Presentation/Http/Resource/PostResource.php:24:        public int $repostsCount,
app/src/Modules/Posts/Presentation/Http/Resource/PostResource.php:25:        public int $commentsCount,
app/src/Modules/Posts/Presentation/Http/Resource/PostResource.php-26-        public bool $likedByMe,
app/src/Modules/Posts/Presentation/Http/Resource/PostResource.php-27-        public string $createdAt,
--
app/src/Modules/Posts/Presentation/Http/Resource/PostResource.php-40-            attachmentType: $post->attachmentType,
app/src/Modules/Posts/Presentation/Http/Resource/PostResource.php-41-            likesCount: $post->likesCount,
app/src/Modules/Posts/Presentation/Http/Resource/PostResource.php:42:            repostsCount: $post->repostsCount,
app/src/Modules/Posts/Presentation/Http/Resource/PostResource.php:43:            commentsCount: $post->commentsCount,
app/src/Modules/Posts/Presentation/Http/Resource/PostResource.php-44-            likedByMe: $post->likedByMe,
app/src/Modules/Posts/Presentation/Http/Resource/PostResource.php-45-            createdAt: $post->createdAt,
--
app/database/migrations/20260613.143901_0_create_user_domain_tables.php-13-    public function up(): void
app/database/migrations/20260613.143901_0_create_user_domain_tables.php-14-    {
app/database/migrations/20260613.143901_0_create_user_domain_tables.php:15:        $this->table('users')
app/database/migrations/20260613.143901_0_create_user_domain_tables.php-16-            ->addColumn('id', 'uuid', ['nullable' => false])
app/database/migrations/20260613.143901_0_create_user_domain_tables.php-17-            ->addColumn('name', 'string', ['length' => 100, 'nullable' => false])
--
app/database/migrations/20260613.143901_0_create_user_domain_tables.php-54-            ->addForeignKey(
app/database/migrations/20260613.143901_0_create_user_domain_tables.php-55-                ['user_id'],
app/database/migrations/20260613.143901_0_create_user_domain_tables.php:56:                'users',
app/database/migrations/20260613.143901_0_create_user_domain_tables.php-57-                ['id'],
app/database/migrations/20260613.143901_0_create_user_domain_tables.php-58-                ['delete' => 'CASCADE', 'update' => 'CASCADE', 'indexCreate' => false],
--
app/database/migrations/20260613.143901_0_create_user_domain_tables.php-71-            ->addForeignKey(
app/database/migrations/20260613.143901_0_create_user_domain_tables.php-72-                ['assigned_user_id'],
app/database/migrations/20260613.143901_0_create_user_domain_tables.php:73:                'users',
app/database/migrations/20260613.143901_0_create_user_domain_tables.php-74-                ['id'],
app/database/migrations/20260613.143901_0_create_user_domain_tables.php-75-                ['delete' => 'SET NULL', 'update' => 'CASCADE', 'indexCreate' => false],
--
app/database/migrations/20260613.143901_0_create_user_domain_tables.php-83-        $this->table('reserved_nicknames')->drop();
app/database/migrations/20260613.143901_0_create_user_domain_tables.php-84-        $this->table('user_bans')->drop();
app/database/migrations/20260613.143901_0_create_user_domain_tables.php:85:        $this->table('users')->drop();
app/database/migrations/20260613.143901_0_create_user_domain_tables.php-86-    }
app/database/migrations/20260613.143901_0_create_user_domain_tables.php-87-}
--
app/src/Modules/User/Domain/Entity/User.php-31-#[Entity(
app/src/Modules/User/Domain/Entity/User.php-32-    role: 'user',
app/src/Modules/User/Domain/Entity/User.php:33:    table: 'users',
app/src/Modules/User/Domain/Entity/User.php-34-    repository: UserRepository::class,
app/src/Modules/User/Domain/Entity/User.php-35-    typecast: [Typecast::class, ValueObjectCast::class],
--
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-25-            ->addForeignKey(
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-26-                ['created_by_id'],
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php:27:                'users',
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-28-                ['id'],
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-29-                ['delete' => 'RESTRICT', 'update' => 'CASCADE', 'indexCreate' => false],
--
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-31-            ->create();
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-32-
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php:33:        $this->table('posts')
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-34-            ->addColumn('id', 'uuid', ['nullable' => false])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-35-            ->addColumn('user_id', 'uuid', ['nullable' => false])
--
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-41-            ->addColumn('parent_post_id', 'uuid', ['nullable' => true])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-42-            ->addColumn('likes_count', 'integer', ['nullable' => false, 'default' => 0])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php:43:            ->addColumn('reposts_count', 'integer', ['nullable' => false, 'default' => 0])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php:44:            ->addColumn('comments_count', 'integer', ['nullable' => false, 'default' => 0])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-45-            ->addColumn('deleted_at', 'datetime', ['nullable' => true])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-46-            ->addColumn('created_at', 'datetime', ['nullable' => false])
--
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-52-            ->addForeignKey(
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-53-                ['user_id'],
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php:54:                'users',
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-55-                ['id'],
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-56-                ['delete' => 'RESTRICT', 'update' => 'CASCADE', 'indexCreate' => false],
--
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-60-        // Self-FK добавляем отдельным ALTER после ->create(): индекс parent_post_id уже создан выше,
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-61-        // поэтому 'indexCreate' => false, иначе появился бы дублирующий индекс.
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php:62:        $this->table('posts')
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-63-            ->addForeignKey(
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-64-                ['parent_post_id'],
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php:65:                'posts',
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-66-                ['id'],
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-67-                ['delete' => 'SET NULL', 'update' => 'CASCADE', 'indexCreate' => false],
--
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-81-            ->addForeignKey(
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-82-                ['post_id'],
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php:83:                'posts',
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-84-                ['id'],
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-85-                ['delete' => 'CASCADE', 'update' => 'CASCADE', 'indexCreate' => false],
--
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-93-            ->create();
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-94-
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php:95:        $this->table('post_likes')
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-96-            ->addColumn('id', 'uuid', ['nullable' => false])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-97-            ->addColumn('post_id', 'uuid', ['nullable' => false])
--
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-104-            ->addForeignKey(
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-105-                ['post_id'],
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php:106:                'posts',
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-107-                ['id'],
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-108-                ['delete' => 'CASCADE', 'update' => 'CASCADE', 'indexCreate' => false],
--
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-110-            ->addForeignKey(
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-111-                ['user_id'],
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php:112:                'users',
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-113-                ['id'],
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-114-                ['delete' => 'CASCADE', 'update' => 'CASCADE', 'indexCreate' => false],
--
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-116-            ->create();
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-117-
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php:118:        $this->table('post_mentions')
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-119-            ->addColumn('id', 'uuid', ['nullable' => false])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-120-            ->addColumn('post_id', 'uuid', ['nullable' => false])
--
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-127-            ->addForeignKey(
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-128-                ['post_id'],
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php:129:                'posts',
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-130-                ['id'],
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-131-                ['delete' => 'CASCADE', 'update' => 'CASCADE', 'indexCreate' => false],
--
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-133-            ->addForeignKey(
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-134-                ['user_id'],
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php:135:                'users',
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-136-                ['id'],
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-137-                ['delete' => 'CASCADE', 'update' => 'CASCADE', 'indexCreate' => false],
--
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-150-            ->addForeignKey(
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-151-                ['post_id'],
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php:152:                'posts',
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-153-                ['id'],
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-154-                ['delete' => 'CASCADE', 'update' => 'CASCADE', 'indexCreate' => false],
--
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-176-            ->addForeignKey(
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-177-                ['post_id'],
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php:178:                'posts',
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-179-                ['id'],
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-180-                ['delete' => 'CASCADE', 'update' => 'CASCADE', 'indexCreate' => false],
--
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-182-            ->addForeignKey(
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-183-                ['blocked_by_id'],
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php:184:                'users',
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-185-                ['id'],
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-186-                ['delete' => 'RESTRICT', 'update' => 'CASCADE', 'indexCreate' => false],
--
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-188-            ->addForeignKey(
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-189-                ['unblocked_by_id'],
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php:190:                'users',
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-191-                ['id'],
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-192-                ['delete' => 'SET NULL', 'update' => 'CASCADE', 'indexCreate' => false],
--
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-194-            ->create();
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-195-
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php:196:        $this->table('comments')
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-197-            ->addColumn('id', 'uuid', ['nullable' => false])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-198-            ->addColumn('post_id', 'uuid', ['nullable' => false])
--
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-213-            ->addForeignKey(
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-214-                ['post_id'],
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php:215:                'posts',
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-216-                ['id'],
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-217-                ['delete' => 'CASCADE', 'update' => 'CASCADE', 'indexCreate' => false],
--
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-219-            ->addForeignKey(
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-220-                ['user_id'],
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php:221:                'users',
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-222-                ['id'],
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-223-                ['delete' => 'RESTRICT', 'update' => 'CASCADE', 'indexCreate' => false],
--
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-225-            ->addForeignKey(
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-226-                ['deleted_by_id'],
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php:227:                'users',
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-228-                ['id'],
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-229-                ['delete' => 'SET NULL', 'update' => 'CASCADE', 'indexCreate' => false],
--
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-232-
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-233-        // Self-FK дерева комментариев — отдельным ALTER, индекс parent_comment_id уже создан выше.
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php:234:        $this->table('comments')
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-235-            ->addForeignKey(
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-236-                ['parent_comment_id'],
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php:237:                'comments',
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-238-                ['id'],
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-239-                ['delete' => 'SET NULL', 'update' => 'CASCADE', 'indexCreate' => false],
--
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-252-            ->addForeignKey(
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-253-                ['comment_id'],
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php:254:                'comments',
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-255-                ['id'],
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-256-                ['delete' => 'CASCADE', 'update' => 'CASCADE', 'indexCreate' => false],
--
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-258-            ->addForeignKey(
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-259-                ['user_id'],
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php:260:                'users',
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-261-                ['id'],
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-262-                ['delete' => 'CASCADE', 'update' => 'CASCADE', 'indexCreate' => false],
--
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-264-            ->create();
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-265-
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php:266:        $this->table('comment_mentions')
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-267-            ->addColumn('id', 'uuid', ['nullable' => false])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-268-            ->addColumn('comment_id', 'uuid', ['nullable' => false])
--
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-275-            ->addForeignKey(
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-276-                ['comment_id'],
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php:277:                'comments',
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-278-                ['id'],
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-279-                ['delete' => 'CASCADE', 'update' => 'CASCADE', 'indexCreate' => false],
--
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-281-            ->addForeignKey(
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-282-                ['user_id'],
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php:283:                'users',
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-284-                ['id'],
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-285-                ['delete' => 'CASCADE', 'update' => 'CASCADE', 'indexCreate' => false],
--
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-290-    public function down(): void
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-291-    {
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php:292:        $this->table('comment_mentions')->drop();
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-293-        $this->table('comment_likes')->drop();
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php:294:        $this->table('comments')->drop();
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-295-        $this->table('post_blocks')->drop();
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-296-        $this->table('post_tags')->drop();
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php:297:        $this->table('post_mentions')->drop();
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php:298:        $this->table('post_likes')->drop();
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-299-        $this->table('post_media')->drop();
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-300-        $this->table('tags')->drop();
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php:301:        $this->table('posts')->drop();
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-302-    }
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-303-}
--
app/src/Modules/Posts/Domain/Entity/PostMention.php-17-#[Entity(
app/src/Modules/Posts/Domain/Entity/PostMention.php-18-    role: 'post_mention',
app/src/Modules/Posts/Domain/Entity/PostMention.php:19:    table: 'post_mentions',
app/src/Modules/Posts/Domain/Entity/PostMention.php-20-    repository: PostMentionRepository::class,
app/src/Modules/Posts/Domain/Entity/PostMention.php-21-    typecast: [Typecast::class, ValueObjectCast::class],
--
app/src/Modules/Posts/Repository/PostRepository.php-123-    }
app/src/Modules/Posts/Repository/PostRepository.php-124-
app/src/Modules/Posts/Repository/PostRepository.php:125:    public function findRepostsOf(PostId $postId): PostCollection
app/src/Modules/Posts/Repository/PostRepository.php-126-    {
app/src/Modules/Posts/Repository/PostRepository.php-127-        return new PostCollection(
--
app/src/Modules/Posts/Domain/Entity/Comment.php-29-#[Entity(
app/src/Modules/Posts/Domain/Entity/Comment.php-30-    role: 'comment',
app/src/Modules/Posts/Domain/Entity/Comment.php:31:    table: 'comments',
app/src/Modules/Posts/Domain/Entity/Comment.php-32-    repository: CommentRepository::class,
app/src/Modules/Posts/Domain/Entity/Comment.php-33-    typecast: [Typecast::class, ValueObjectCast::class],
--
app/src/Modules/User/Application/Query/GetUserPublicProfiles/GetUserPublicProfilesHandler.php-34-        );
app/src/Modules/User/Application/Query/GetUserPublicProfiles/GetUserPublicProfilesHandler.php-35-
app/src/Modules/User/Application/Query/GetUserPublicProfiles/GetUserPublicProfilesHandler.php:36:        /** @var UserCollection $users */
app/src/Modules/User/Application/Query/GetUserPublicProfiles/GetUserPublicProfilesHandler.php:37:        $users = $this->userRepository->findByIds(...$userIds);
app/src/Modules/User/Application/Query/GetUserPublicProfiles/GetUserPublicProfilesHandler.php-38-
app/src/Modules/User/Application/Query/GetUserPublicProfiles/GetUserPublicProfilesHandler.php-39-        /** @var array<int, UserPublicProfileView> $profiles */
app/src/Modules/User/Application/Query/GetUserPublicProfiles/GetUserPublicProfilesHandler.php:40:        $profiles = $users
app/src/Modules/User/Application/Query/GetUserPublicProfiles/GetUserPublicProfilesHandler.php-41-            ->map(fn(User $user): UserPublicProfileView => $this->assembler->fromUser($user))
app/src/Modules/User/Application/Query/GetUserPublicProfiles/GetUserPublicProfilesHandler.php-42-            ->all();
--
app/src/Modules/Posts/Domain/Entity/CommentMention.php-17-#[Entity(
app/src/Modules/Posts/Domain/Entity/CommentMention.php-18-    role: 'comment_mention',
app/src/Modules/Posts/Domain/Entity/CommentMention.php:19:    table: 'comment_mentions',
app/src/Modules/Posts/Domain/Entity/CommentMention.php-20-    repository: CommentMentionRepository::class,
app/src/Modules/Posts/Domain/Entity/CommentMention.php-21-    typecast: [Typecast::class, ValueObjectCast::class],
--
app/src/Modules/Posts/Domain/Entity/PostLike.php-17-#[Entity(
app/src/Modules/Posts/Domain/Entity/PostLike.php-18-    role: 'post_like',
app/src/Modules/Posts/Domain/Entity/PostLike.php:19:    table: 'post_likes',
app/src/Modules/Posts/Domain/Entity/PostLike.php-20-    repository: PostLikeRepository::class,
app/src/Modules/Posts/Domain/Entity/PostLike.php-21-    typecast: [Typecast::class, ValueObjectCast::class],
--
app/src/Modules/Posts/Domain/Entity/Post.php-17-use App\Modules\Posts\Domain\ValueObject\PostPractice;
app/src/Modules/Posts/Domain/Entity/Post.php-18-use App\Modules\Posts\Domain\ValueObject\PostText;
app/src/Modules/Posts/Domain/Entity/Post.php:19:use App\Modules\Posts\Domain\ValueObject\RepostsCount;
app/src/Modules/Posts/Domain/Entity/Post.php-20-use App\Modules\Posts\Infrastructure\Cycle\PostDeletionTypecast;
app/src/Modules/Posts/Domain/Entity/Post.php-21-use App\Modules\Posts\Infrastructure\Cycle\PostLessonTypecast;
--
app/src/Modules/Posts/Domain/Entity/Post.php-35-#[Entity(
app/src/Modules/Posts/Domain/Entity/Post.php-36-    role: 'post',
app/src/Modules/Posts/Domain/Entity/Post.php:37:    table: 'posts',
app/src/Modules/Posts/Domain/Entity/Post.php-38-    repository: PostRepository::class,
app/src/Modules/Posts/Domain/Entity/Post.php-39-    typecast: [Typecast::class, ValueObjectCast::class],
--
app/src/Modules/Posts/Domain/Entity/Post.php-70-    public private(set) LikesCount $likesCount;
app/src/Modules/Posts/Domain/Entity/Post.php-71-
app/src/Modules/Posts/Domain/Entity/Post.php:72:    #[Column(type: 'integer', name: 'reposts_count', typecast: RepostsCount::class)]
app/src/Modules/Posts/Domain/Entity/Post.php:73:    public private(set) RepostsCount $repostsCount;
app/src/Modules/Posts/Domain/Entity/Post.php-74-
app/src/Modules/Posts/Domain/Entity/Post.php:75:    #[Column(type: 'integer', name: 'comments_count', typecast: CommentsCount::class)]
app/src/Modules/Posts/Domain/Entity/Post.php:76:    public private(set) CommentsCount $commentsCount;
app/src/Modules/Posts/Domain/Entity/Post.php-77-
app/src/Modules/Posts/Domain/Entity/Post.php-78-    #[Column(type: 'datetime', name: 'deleted_at', nullable: true, typecast: PostDeletionTypecast::class)]
--
app/src/Modules/Posts/Domain/Entity/Post.php-121-        $post->original = $original;
app/src/Modules/Posts/Domain/Entity/Post.php-122-        $post->likesCount = LikesCount::zero();
app/src/Modules/Posts/Domain/Entity/Post.php:123:        $post->repostsCount = RepostsCount::zero();
app/src/Modules/Posts/Domain/Entity/Post.php:124:        $post->commentsCount = CommentsCount::zero();
app/src/Modules/Posts/Domain/Entity/Post.php-125-        $post->deletion = PostDeletion::notDeleted();
app/src/Modules/Posts/Domain/Entity/Post.php-126-        $post->media = new PostMediaCollection();
--
app/src/Modules/Posts/Domain/Entity/Post.php-173-    }
app/src/Modules/Posts/Domain/Entity/Post.php-174-
app/src/Modules/Posts/Domain/Entity/Post.php:175:    public function incrementReposts(): void
app/src/Modules/Posts/Domain/Entity/Post.php-176-    {
app/src/Modules/Posts/Domain/Entity/Post.php:177:        $this->repostsCount = $this->repostsCount->increment();
app/src/Modules/Posts/Domain/Entity/Post.php-178-        $this->touch();
app/src/Modules/Posts/Domain/Entity/Post.php-179-    }
app/src/Modules/Posts/Domain/Entity/Post.php-180-
app/src/Modules/Posts/Domain/Entity/Post.php:181:    public function decrementReposts(): void
app/src/Modules/Posts/Domain/Entity/Post.php-182-    {
app/src/Modules/Posts/Domain/Entity/Post.php:183:        $this->repostsCount = $this->repostsCount->decrement();
app/src/Modules/Posts/Domain/Entity/Post.php-184-        $this->touch();
app/src/Modules/Posts/Domain/Entity/Post.php-185-    }
--
app/src/Modules/Posts/Domain/Entity/Post.php-187-    public function incrementComments(): void
app/src/Modules/Posts/Domain/Entity/Post.php-188-    {
app/src/Modules/Posts/Domain/Entity/Post.php:189:        $this->commentsCount = $this->commentsCount->increment();
app/src/Modules/Posts/Domain/Entity/Post.php-190-        $this->touch();
app/src/Modules/Posts/Domain/Entity/Post.php-191-    }
--
app/src/Modules/Posts/Domain/Entity/Post.php-193-    public function decrementComments(): void
app/src/Modules/Posts/Domain/Entity/Post.php-194-    {
app/src/Modules/Posts/Domain/Entity/Post.php:195:        $this->commentsCount = $this->commentsCount->decrement();
app/src/Modules/Posts/Domain/Entity/Post.php-196-        $this->touch();
app/src/Modules/Posts/Domain/Entity/Post.php-197-    }
--
app/src/Modules/Posts/Domain/ValueObject/RepostsCount.php-8-use App\Shared\Domain\ValueObject\AbstractIntegerValue;
app/src/Modules/Posts/Domain/ValueObject/RepostsCount.php-9-
app/src/Modules/Posts/Domain/ValueObject/RepostsCount.php:10:final readonly class RepostsCount extends AbstractIntegerValue
app/src/Modules/Posts/Domain/ValueObject/RepostsCount.php-11-{
app/src/Modules/Posts/Domain/ValueObject/RepostsCount.php-12-    protected const int MIN = 0;
--
app/src/Modules/Posts/Application/Notification/PostNotificationType.php-18-enum PostNotificationType: string implements NotificationTypeDefinition
app/src/Modules/Posts/Application/Notification/PostNotificationType.php-19-{
app/src/Modules/Posts/Application/Notification/PostNotificationType.php:20:    case PostMention = 'posts.post_mention';
app/src/Modules/Posts/Application/Notification/PostNotificationType.php:21:    case CommentMention = 'posts.comment_mention';
app/src/Modules/Posts/Application/Notification/PostNotificationType.php:22:    case PostCommented = 'posts.post_commented';
app/src/Modules/Posts/Application/Notification/PostNotificationType.php:23:    case CommentReply = 'posts.comment_reply';
app/src/Modules/Posts/Application/Notification/PostNotificationType.php:24:    case PostLike = 'posts.post_like';
app/src/Modules/Posts/Application/Notification/PostNotificationType.php:25:    case PostRepost = 'posts.post_repost';
app/src/Modules/Posts/Application/Notification/PostNotificationType.php:26:    case CommentLike = 'posts.comment_like';
app/src/Modules/Posts/Application/Notification/PostNotificationType.php-27-
app/src/Modules/Posts/Application/Notification/PostNotificationType.php-28-    #[\Override]
--
app/src/Modules/Posts/Application/Notification/PostNotificationType.php-56-
app/src/Modules/Posts/Application/Notification/PostNotificationType.php-57-    /**
app/src/Modules/Posts/Application/Notification/PostNotificationType.php:58:     * Action-сегмент кода вида (часть после `posts.`) — основа ключа перевода
app/src/Modules/Posts/Application/Notification/PostNotificationType.php:59:     * `app.posts.notification.<action>.{title|body}`.
app/src/Modules/Posts/Application/Notification/PostNotificationType.php-60-     */
app/src/Modules/Posts/Application/Notification/PostNotificationType.php-61-    public function notificationKey(): string
--
app/src/Modules/Posts/Application/Notification/NotificationContentBuilder.php-15-/**
app/src/Modules/Posts/Application/Notification/NotificationContentBuilder.php-16- * Сборка готового содержимого уведомления для модуля Posts. Текст (заголовок и тело) рендерится в
app/src/Modules/Posts/Application/Notification/NotificationContentBuilder.php:17: * локали получателя по ключам `app.posts.notification.<вид>.{title|body}` домена `posts`; автор
app/src/Modules/Posts/Application/Notification/NotificationContentBuilder.php-18- * (actor) — снимок инициатора действия с непустыми именем и ссылкой на аватар (гарантирует
app/src/Modules/Posts/Application/Notification/NotificationContentBuilder.php-19- * вызывающий через профиль), action — deep-link на запись или комментарий.
--
app/src/Modules/Posts/Application/Notification/NotificationContentBuilder.php-21-final readonly class NotificationContentBuilder
app/src/Modules/Posts/Application/Notification/NotificationContentBuilder.php-22-{
app/src/Modules/Posts/Application/Notification/NotificationContentBuilder.php:23:    private const string TRANSLATION_DOMAIN = 'posts';
app/src/Modules/Posts/Application/Notification/NotificationContentBuilder.php-24-
app/src/Modules/Posts/Application/Notification/NotificationContentBuilder.php-25-    public function __construct(
--
app/src/Modules/Posts/Application/Notification/NotificationContentBuilder.php-41-            type: $type,
app/src/Modules/Posts/Application/Notification/NotificationContentBuilder.php-42-            title: NotificationTitle::fromString($this->translate(
app/src/Modules/Posts/Application/Notification/NotificationContentBuilder.php:43:                key: \sprintf('app.posts.notification.%s.title', $type->notificationKey()),
app/src/Modules/Posts/Application/Notification/NotificationContentBuilder.php-44-                parameters: $parameters,
app/src/Modules/Posts/Application/Notification/NotificationContentBuilder.php-45-                locale: $recipientLocale,
app/src/Modules/Posts/Application/Notification/NotificationContentBuilder.php-46-            )),
app/src/Modules/Posts/Application/Notification/NotificationContentBuilder.php-47-            body: NotificationBody::fromString($this->translate(
app/src/Modules/Posts/Application/Notification/NotificationContentBuilder.php:48:                key: \sprintf('app.posts.notification.%s.body', $type->notificationKey()),
app/src/Modules/Posts/Application/Notification/NotificationContentBuilder.php-49-                parameters: $parameters,
app/src/Modules/Posts/Application/Notification/NotificationContentBuilder.php-50-                locale: $recipientLocale,
--
app/src/Modules/Posts/Application/Query/GetPost/GetPostHandler.php-30-
app/src/Modules/Posts/Application/Query/GetPost/GetPostHandler.php-31-        $post = $this->postRepository->findById(PostId::fromString($query->postId))
app/src/Modules/Posts/Application/Query/GetPost/GetPostHandler.php:32:            ?? throw new NotFoundException('app.posts.not_found');
app/src/Modules/Posts/Application/Query/GetPost/GetPostHandler.php-33-
app/src/Modules/Posts/Application/Query/GetPost/GetPostHandler.php-34-        if (!PostVisibilityPolicy::isVisibleTo(post: $post, viewer: $viewer)) {
app/src/Modules/Posts/Application/Query/GetPost/GetPostHandler.php:35:            throw new NotFoundException('app.posts.not_found');
app/src/Modules/Posts/Application/Query/GetPost/GetPostHandler.php-36-        }
app/src/Modules/Posts/Application/Query/GetPost/GetPostHandler.php-37-
--
app/src/Modules/Posts/Application/Post/MentionRecipientResolver.php-32-final readonly class MentionRecipientResolver
app/src/Modules/Posts/Application/Post/MentionRecipientResolver.php-33-{
app/src/Modules/Posts/Application/Post/MentionRecipientResolver.php:34:    private const string MENTION_USER_NOT_FOUND_KEY = 'app.posts.mention_user_not_found';
app/src/Modules/Posts/Application/Post/MentionRecipientResolver.php-35-
app/src/Modules/Posts/Application/Post/MentionRecipientResolver.php-36-    public function __construct(
--
app/src/Modules/Posts/Application/Query/GetCommentReplies/GetCommentRepliesHandler.php-35-
app/src/Modules/Posts/Application/Query/GetCommentReplies/GetCommentRepliesHandler.php-36-        $parent = $this->commentRepository->findById(CommentId::fromString($query->commentId))
app/src/Modules/Posts/Application/Query/GetCommentReplies/GetCommentRepliesHandler.php:37:            ?? throw new NotFoundException('app.posts.comment_not_found');
app/src/Modules/Posts/Application/Query/GetCommentReplies/GetCommentRepliesHandler.php-38-
app/src/Modules/Posts/Application/Query/GetCommentReplies/GetCommentRepliesHandler.php-39-        if ($parent->isDeleted()) {
app/src/Modules/Posts/Application/Query/GetCommentReplies/GetCommentRepliesHandler.php:40:            throw new NotFoundException('app.posts.comment_not_found');
app/src/Modules/Posts/Application/Query/GetCommentReplies/GetCommentRepliesHandler.php-41-        }
app/src/Modules/Posts/Application/Query/GetCommentReplies/GetCommentRepliesHandler.php-42-
--
app/src/Modules/Posts/Application/Query/GetCommentReplies/GetCommentRepliesHandler.php-46-
app/src/Modules/Posts/Application/Query/GetCommentReplies/GetCommentRepliesHandler.php-47-        if ($post === null || !PostVisibilityPolicy::isVisibleTo(post: $post, viewer: $viewer)) {
app/src/Modules/Posts/Application/Query/GetCommentReplies/GetCommentRepliesHandler.php:48:            throw new NotFoundException('app.posts.not_found');
app/src/Modules/Posts/Application/Query/GetCommentReplies/GetCommentRepliesHandler.php-49-        }
app/src/Modules/Posts/Application/Query/GetCommentReplies/GetCommentRepliesHandler.php-50-
--
app/src/Modules/Posts/Application/Query/GetCommentReplies/GetCommentRepliesHandler.php-59-        if (\count($page) <= $query->limit) {
app/src/Modules/Posts/Application/Query/GetCommentReplies/GetCommentRepliesHandler.php-60-            return new GetCommentRepliesResult(
app/src/Modules/Posts/Application/Query/GetCommentReplies/GetCommentRepliesHandler.php:61:                replies: $this->commentViewAssembler->fromComments(comments: new CommentCollection($page), viewer: $viewer),
app/src/Modules/Posts/Application/Query/GetCommentReplies/GetCommentRepliesHandler.php-62-                nextCursor: null,
app/src/Modules/Posts/Application/Query/GetCommentReplies/GetCommentRepliesHandler.php-63-            );
--
app/src/Modules/Posts/Application/Query/GetCommentReplies/GetCommentRepliesHandler.php-67-
app/src/Modules/Posts/Application/Query/GetCommentReplies/GetCommentRepliesHandler.php-68-        return new GetCommentRepliesResult(
app/src/Modules/Posts/Application/Query/GetCommentReplies/GetCommentRepliesHandler.php:69:            replies: $this->commentViewAssembler->fromComments(comments: new CommentCollection($visible), viewer: $viewer),
app/src/Modules/Posts/Application/Query/GetCommentReplies/GetCommentRepliesHandler.php-70-            nextCursor: $visible[\count($visible) - 1]->id->value(),
app/src/Modules/Posts/Application/Query/GetCommentReplies/GetCommentRepliesHandler.php-71-        );
--
app/src/Modules/Posts/Application/Query/GetPostComments/GetPostCommentsResult.php-8-
app/src/Modules/Posts/Application/Query/GetPostComments/GetPostCommentsResult.php-9-/**
app/src/Modules/Posts/Application/Query/GetPostComments/GetPostCommentsResult.php:10: * @param list<CommentView> $comments
app/src/Modules/Posts/Application/Query/GetPostComments/GetPostCommentsResult.php-11- */
app/src/Modules/Posts/Application/Query/GetPostComments/GetPostCommentsResult.php-12-final readonly class GetPostCommentsResult
app/src/Modules/Posts/Application/Query/GetPostComments/GetPostCommentsResult.php-13-{
app/src/Modules/Posts/Application/Query/GetPostComments/GetPostCommentsResult.php-14-    /**
app/src/Modules/Posts/Application/Query/GetPostComments/GetPostCommentsResult.php:15:     * @param list<CommentView> $comments
app/src/Modules/Posts/Application/Query/GetPostComments/GetPostCommentsResult.php-16-     */
app/src/Modules/Posts/Application/Query/GetPostComments/GetPostCommentsResult.php-17-    public function __construct(
app/src/Modules/Posts/Application/Query/GetPostComments/GetPostCommentsResult.php:18:        public array $comments,
app/src/Modules/Posts/Application/Query/GetPostComments/GetPostCommentsResult.php-19-        public string|null $nextCursor,
app/src/Modules/Posts/Application/Query/GetPostComments/GetPostCommentsResult.php-20-    ) {}
--
app/src/Modules/Posts/Application/Query/GetUserFeed/GetUserFeedResult.php-8-
app/src/Modules/Posts/Application/Query/GetUserFeed/GetUserFeedResult.php-9-/**
app/src/Modules/Posts/Application/Query/GetUserFeed/GetUserFeedResult.php:10: * @param list<PostView> $posts
app/src/Modules/Posts/Application/Query/GetUserFeed/GetUserFeedResult.php-11- */
app/src/Modules/Posts/Application/Query/GetUserFeed/GetUserFeedResult.php-12-final readonly class GetUserFeedResult
app/src/Modules/Posts/Application/Query/GetUserFeed/GetUserFeedResult.php-13-{
app/src/Modules/Posts/Application/Query/GetUserFeed/GetUserFeedResult.php-14-    /**
app/src/Modules/Posts/Application/Query/GetUserFeed/GetUserFeedResult.php:15:     * @param list<PostView> $posts
app/src/Modules/Posts/Application/Query/GetUserFeed/GetUserFeedResult.php-16-     */
app/src/Modules/Posts/Application/Query/GetUserFeed/GetUserFeedResult.php-17-    public function __construct(
app/src/Modules/Posts/Application/Query/GetUserFeed/GetUserFeedResult.php:18:        public array $posts,
app/src/Modules/Posts/Application/Query/GetUserFeed/GetUserFeedResult.php-19-        public string|null $nextCursor,
app/src/Modules/Posts/Application/Query/GetUserFeed/GetUserFeedResult.php-20-    ) {}
--
app/src/Modules/Posts/Application/Query/GetPostComments/GetPostCommentsHandler.php-34-
app/src/Modules/Posts/Application/Query/GetPostComments/GetPostCommentsHandler.php-35-        $post = $this->postRepository->findById(PostId::fromString($query->postId))
app/src/Modules/Posts/Application/Query/GetPostComments/GetPostCommentsHandler.php:36:            ?? throw new NotFoundException('app.posts.not_found');
app/src/Modules/Posts/Application/Query/GetPostComments/GetPostCommentsHandler.php-37-
app/src/Modules/Posts/Application/Query/GetPostComments/GetPostCommentsHandler.php-38-        if (!PostVisibilityPolicy::isVisibleTo(post: $post, viewer: $viewer)) {
app/src/Modules/Posts/Application/Query/GetPostComments/GetPostCommentsHandler.php:39:            throw new NotFoundException('app.posts.not_found');
app/src/Modules/Posts/Application/Query/GetPostComments/GetPostCommentsHandler.php-40-        }
app/src/Modules/Posts/Application/Query/GetPostComments/GetPostCommentsHandler.php-41-
--
app/src/Modules/Posts/Application/Query/GetPostComments/GetPostCommentsHandler.php-50-        if (\count($page) <= $query->limit) {
app/src/Modules/Posts/Application/Query/GetPostComments/GetPostCommentsHandler.php-51-            return new GetPostCommentsResult(
app/src/Modules/Posts/Application/Query/GetPostComments/GetPostCommentsHandler.php:52:                comments: $this->commentViewAssembler->fromComments(comments: new CommentCollection($page), viewer: $viewer),
app/src/Modules/Posts/Application/Query/GetPostComments/GetPostCommentsHandler.php-53-                nextCursor: null,
app/src/Modules/Posts/Application/Query/GetPostComments/GetPostCommentsHandler.php-54-            );
--
app/src/Modules/Posts/Application/Query/GetPostComments/GetPostCommentsHandler.php-58-
app/src/Modules/Posts/Application/Query/GetPostComments/GetPostCommentsHandler.php-59-        return new GetPostCommentsResult(
app/src/Modules/Posts/Application/Query/GetPostComments/GetPostCommentsHandler.php:60:            comments: $this->commentViewAssembler->fromComments(comments: new CommentCollection($visible), viewer: $viewer),
app/src/Modules/Posts/Application/Query/GetPostComments/GetPostCommentsHandler.php-61-            nextCursor: $visible[\count($visible) - 1]->id->value(),
app/src/Modules/Posts/Application/Query/GetPostComments/GetPostCommentsHandler.php-62-        );
--
app/src/Modules/Posts/Application/Query/GetUserFeed/GetUserFeedHandler.php-45-        if (\count($page) <= $query->limit) {
app/src/Modules/Posts/Application/Query/GetUserFeed/GetUserFeedHandler.php-46-            return new GetUserFeedResult(
app/src/Modules/Posts/Application/Query/GetUserFeed/GetUserFeedHandler.php:47:                posts: $this->postViewAssembler->fromPosts(posts: new PostCollection($page), viewer: $viewer),
app/src/Modules/Posts/Application/Query/GetUserFeed/GetUserFeedHandler.php-48-                nextCursor: null,
app/src/Modules/Posts/Application/Query/GetUserFeed/GetUserFeedHandler.php-49-            );
--
app/src/Modules/Posts/Application/Query/GetUserFeed/GetUserFeedHandler.php-53-
app/src/Modules/Posts/Application/Query/GetUserFeed/GetUserFeedHandler.php-54-        return new GetUserFeedResult(
app/src/Modules/Posts/Application/Query/GetUserFeed/GetUserFeedHandler.php:55:            posts: $this->postViewAssembler->fromPosts(posts: new PostCollection($visible), viewer: $viewer),
app/src/Modules/Posts/Application/Query/GetUserFeed/GetUserFeedHandler.php-56-            nextCursor: $visible[\count($visible) - 1]->id->value(),
app/src/Modules/Posts/Application/Query/GetUserFeed/GetUserFeedHandler.php-57-        );
--
app/src/Modules/Posts/Application/Command/UnlikePost/UnlikePostHandler.php-35-
app/src/Modules/Posts/Application/Command/UnlikePost/UnlikePostHandler.php-36-        $post = $this->postRepository->findById(PostId::fromString($command->postId))
app/src/Modules/Posts/Application/Command/UnlikePost/UnlikePostHandler.php:37:            ?? throw new NotFoundException('app.posts.not_found');
app/src/Modules/Posts/Application/Command/UnlikePost/UnlikePostHandler.php-38-
app/src/Modules/Posts/Application/Command/UnlikePost/UnlikePostHandler.php-39-        $like = $this->postLikeRepository->findByPostAndUser(postId: $post->id, userId: $userId);
--
app/src/Modules/Posts/Application/Command/LikeComment/LikeCommentHandler.php-39-
app/src/Modules/Posts/Application/Command/LikeComment/LikeCommentHandler.php-40-        $comment = $this->commentRepository->findById(CommentId::fromString($command->commentId))
app/src/Modules/Posts/Application/Command/LikeComment/LikeCommentHandler.php:41:            ?? throw new NotFoundException('app.posts.comment_not_found');
app/src/Modules/Posts/Application/Command/LikeComment/LikeCommentHandler.php-42-
app/src/Modules/Posts/Application/Command/LikeComment/LikeCommentHandler.php-43-        if ($comment->isDeleted()) {
app/src/Modules/Posts/Application/Command/LikeComment/LikeCommentHandler.php:44:            throw new NotFoundException('app.posts.comment_not_found');
app/src/Modules/Posts/Application/Command/LikeComment/LikeCommentHandler.php-45-        }
app/src/Modules/Posts/Application/Command/LikeComment/LikeCommentHandler.php-46-
--
app/src/Modules/Posts/Application/Command/UnlikeComment/UnlikeCommentHandler.php-35-
app/src/Modules/Posts/Application/Command/UnlikeComment/UnlikeCommentHandler.php-36-        $comment = $this->commentRepository->findById(CommentId::fromString($command->commentId))
app/src/Modules/Posts/Application/Command/UnlikeComment/UnlikeCommentHandler.php:37:            ?? throw new NotFoundException('app.posts.comment_not_found');
app/src/Modules/Posts/Application/Command/UnlikeComment/UnlikeCommentHandler.php-38-
app/src/Modules/Posts/Application/Command/UnlikeComment/UnlikeCommentHandler.php-39-        $like = $this->commentLikeRepository->findByCommentAndUser(commentId: $comment->id, userId: $userId);
--
app/src/Modules/Posts/Application/Command/ReplyComment/ReplyCommentHandler.php-44-
app/src/Modules/Posts/Application/Command/ReplyComment/ReplyCommentHandler.php-45-        $parent = $this->commentRepository->findById(CommentId::fromString($command->commentId))
app/src/Modules/Posts/Application/Command/ReplyComment/ReplyCommentHandler.php:46:            ?? throw new NotFoundException('app.posts.comment_not_found');
app/src/Modules/Posts/Application/Command/ReplyComment/ReplyCommentHandler.php-47-
app/src/Modules/Posts/Application/Command/ReplyComment/ReplyCommentHandler.php-48-        if ($parent->isDeleted()) {
app/src/Modules/Posts/Application/Command/ReplyComment/ReplyCommentHandler.php:49:            throw new NotFoundException('app.posts.comment_not_found');
app/src/Modules/Posts/Application/Command/ReplyComment/ReplyCommentHandler.php-50-        }
app/src/Modules/Posts/Application/Command/ReplyComment/ReplyCommentHandler.php-51-
--
app/src/Modules/Posts/Application/Command/ReplyComment/ReplyCommentHandler.php-55-
app/src/Modules/Posts/Application/Command/ReplyComment/ReplyCommentHandler.php-56-        if ($post === null || !PostVisibilityPolicy::isActionable($post)) {
app/src/Modules/Posts/Application/Command/ReplyComment/ReplyCommentHandler.php:57:            throw new NotFoundException('app.posts.not_found');
app/src/Modules/Posts/Application/Command/ReplyComment/ReplyCommentHandler.php-58-        }
app/src/Modules/Posts/Application/Command/ReplyComment/ReplyCommentHandler.php-59-
--
app/src/Modules/Posts/Application/Command/LikePost/LikePostHandler.php-41-
app/src/Modules/Posts/Application/Command/LikePost/LikePostHandler.php-42-        $post = $this->postRepository->findById(PostId::fromString($command->postId))
app/src/Modules/Posts/Application/Command/LikePost/LikePostHandler.php:43:            ?? throw new NotFoundException('app.posts.not_found');
app/src/Modules/Posts/Application/Command/LikePost/LikePostHandler.php-44-
app/src/Modules/Posts/Application/Command/LikePost/LikePostHandler.php-45-        if (!PostVisibilityPolicy::isActionable($post)) {
app/src/Modules/Posts/Application/Command/LikePost/LikePostHandler.php:46:            throw new NotFoundException('app.posts.not_found');
app/src/Modules/Posts/Application/Command/LikePost/LikePostHandler.php-47-        }
app/src/Modules/Posts/Application/Command/LikePost/LikePostHandler.php-48-
--
app/src/Modules/Posts/Application/Command/DeleteComment/DeleteCommentHandler.php-39-    {
app/src/Modules/Posts/Application/Command/DeleteComment/DeleteCommentHandler.php-40-        $comment = $this->commentRepository->findById(CommentId::fromString($command->commentId))
app/src/Modules/Posts/Application/Command/DeleteComment/DeleteCommentHandler.php:41:            ?? throw new NotFoundException('app.posts.comment_not_found');
app/src/Modules/Posts/Application/Command/DeleteComment/DeleteCommentHandler.php-42-
app/src/Modules/Posts/Application/Command/DeleteComment/DeleteCommentHandler.php-43-        if (!$comment->userId->equals(UserId::fromString($command->authUserId))) {
app/src/Modules/Posts/Application/Command/DeleteComment/DeleteCommentHandler.php:44:            throw new ForbiddenException('app.posts.forbidden');
app/src/Modules/Posts/Application/Command/DeleteComment/DeleteCommentHandler.php-45-        }
app/src/Modules/Posts/Application/Command/DeleteComment/DeleteCommentHandler.php-46-
--
app/src/Modules/Posts/Application/Command/DeleteComment/DeleteCommentHandler.php-71-            $post = $this->postRepository->findById(PostId::fromString($postId));
app/src/Modules/Posts/Application/Command/DeleteComment/DeleteCommentHandler.php-72-
app/src/Modules/Posts/Application/Command/DeleteComment/DeleteCommentHandler.php:73:            if ($post !== null && $post->commentsCount->value() > 0) {
app/src/Modules/Posts/Application/Command/DeleteComment/DeleteCommentHandler.php-74-                $post->decrementComments();
app/src/Modules/Posts/Application/Command/DeleteComment/DeleteCommentHandler.php-75-                $this->entityManager->persist($post);
--
app/src/Modules/Posts/Application/Command/DeletePost/DeletePostHandler.php-33-    {
app/src/Modules/Posts/Application/Command/DeletePost/DeletePostHandler.php-34-        $post = $this->postRepository->findById(PostId::fromString($command->postId))
app/src/Modules/Posts/Application/Command/DeletePost/DeletePostHandler.php:35:            ?? throw new NotFoundException('app.posts.not_found');
app/src/Modules/Posts/Application/Command/DeletePost/DeletePostHandler.php-36-
app/src/Modules/Posts/Application/Command/DeletePost/DeletePostHandler.php-37-        if (!$post->userId->equals(UserId::fromString($command->authUserId))) {
app/src/Modules/Posts/Application/Command/DeletePost/DeletePostHandler.php:38:            throw new ForbiddenException('app.posts.forbidden');
app/src/Modules/Posts/Application/Command/DeletePost/DeletePostHandler.php-39-        }
app/src/Modules/Posts/Application/Command/DeletePost/DeletePostHandler.php-40-
--
app/src/Modules/Posts/Application/Command/DeletePost/DeletePostHandler.php-53-            $original = $this->postRepository->findById(PostId::fromString($originalId));
app/src/Modules/Posts/Application/Command/DeletePost/DeletePostHandler.php-54-
app/src/Modules/Posts/Application/Command/DeletePost/DeletePostHandler.php:55:            if ($original !== null && $original->repostsCount->value() > 0) {
app/src/Modules/Posts/Application/Command/DeletePost/DeletePostHandler.php:56:                $original->decrementReposts();
app/src/Modules/Posts/Application/Command/DeletePost/DeletePostHandler.php-57-                $this->entityManager->persist($original);
app/src/Modules/Posts/Application/Command/DeletePost/DeletePostHandler.php-58-            }
--
app/src/Modules/Posts/Application/Command/RepostPost/RepostPostHandler.php-48-
app/src/Modules/Posts/Application/Command/RepostPost/RepostPostHandler.php-49-        $original = $this->postRepository->findById(PostId::fromString($command->postId))
app/src/Modules/Posts/Application/Command/RepostPost/RepostPostHandler.php:50:            ?? throw new NotFoundException('app.posts.not_found');
app/src/Modules/Posts/Application/Command/RepostPost/RepostPostHandler.php-51-
app/src/Modules/Posts/Application/Command/RepostPost/RepostPostHandler.php-52-        if (!PostVisibilityPolicy::isActionable($original)) {
app/src/Modules/Posts/Application/Command/RepostPost/RepostPostHandler.php:53:            throw new NotFoundException('app.posts.not_found');
app/src/Modules/Posts/Application/Command/RepostPost/RepostPostHandler.php-54-        }
app/src/Modules/Posts/Application/Command/RepostPost/RepostPostHandler.php-55-
--
app/src/Modules/Posts/Application/Command/RepostPost/RepostPostHandler.php-71-        $this->composer->attachPostMentions(post: $post, mentionIds: $command->mentions, actorUserId: $command->authUserId);
app/src/Modules/Posts/Application/Command/RepostPost/RepostPostHandler.php-72-
app/src/Modules/Posts/Application/Command/RepostPost/RepostPostHandler.php:73:        $original->incrementReposts();
app/src/Modules/Posts/Application/Command/RepostPost/RepostPostHandler.php-74-        $this->entityManager->persist($original);
app/src/Modules/Posts/Application/Command/RepostPost/RepostPostHandler.php-75-
--
app/src/Modules/Posts/Application/Command/CommentPost/CommentPostHandler.php-42-
app/src/Modules/Posts/Application/Command/CommentPost/CommentPostHandler.php-43-        $post = $this->postRepository->findById(PostId::fromString($command->postId))
app/src/Modules/Posts/Application/Command/CommentPost/CommentPostHandler.php:44:            ?? throw new NotFoundException('app.posts.not_found');
app/src/Modules/Posts/Application/Command/CommentPost/CommentPostHandler.php-45-
app/src/Modules/Posts/Application/Command/CommentPost/CommentPostHandler.php-46-        if (!PostVisibilityPolicy::isActionable($post)) {
app/src/Modules/Posts/Application/Command/CommentPost/CommentPostHandler.php:47:            throw new NotFoundException('app.posts.not_found');
app/src/Modules/Posts/Application/Command/CommentPost/CommentPostHandler.php-48-        }
app/src/Modules/Posts/Application/Command/CommentPost/CommentPostHandler.php-49-
--
app/src/Modules/Posts/Application/View/PostView.php-21-        public string $attachmentType,
app/src/Modules/Posts/Application/View/PostView.php-22-        public int $likesCount,
app/src/Modules/Posts/Application/View/PostView.php:23:        public int $repostsCount,
app/src/Modules/Posts/Application/View/PostView.php:24:        public int $commentsCount,
app/src/Modules/Posts/Application/View/PostView.php-25-        public bool $likedByMe,
app/src/Modules/Posts/Application/View/PostView.php-26-        public string $createdAt,
--
app/src/Modules/Posts/Application/View/CommentViewAssembler.php-42-     * @return list<CommentView>
app/src/Modules/Posts/Application/View/CommentViewAssembler.php-43-     */
app/src/Modules/Posts/Application/View/CommentViewAssembler.php:44:    public function fromComments(CommentCollection $comments, UserId $viewer): array
app/src/Modules/Posts/Application/View/CommentViewAssembler.php-45-    {
app/src/Modules/Posts/Application/View/CommentViewAssembler.php:46:        $entities = $comments->all();
app/src/Modules/Posts/Application/View/CommentViewAssembler.php-47-
app/src/Modules/Posts/Application/View/CommentViewAssembler.php-48-        if ($entities === []) {
--
app/src/Modules/Posts/Application/View/CommentViewAssembler.php-51-
app/src/Modules/Posts/Application/View/CommentViewAssembler.php-52-        $authors = $this->authorViews($entities);
app/src/Modules/Posts/Application/View/CommentViewAssembler.php:53:        $likedCommentIds = $this->likedCommentIds(comments: $entities, viewer: $viewer);
app/src/Modules/Posts/Application/View/CommentViewAssembler.php-54-
app/src/Modules/Posts/Application/View/CommentViewAssembler.php-55-        $views = [];
--
app/src/Modules/Posts/Application/View/CommentViewAssembler.php-92-
app/src/Modules/Posts/Application/View/CommentViewAssembler.php-93-    /**
app/src/Modules/Posts/Application/View/CommentViewAssembler.php:94:     * @param array<int, Comment> $comments
app/src/Modules/Posts/Application/View/CommentViewAssembler.php-95-     *
app/src/Modules/Posts/Application/View/CommentViewAssembler.php-96-     * @return array<string, AuthorView>
app/src/Modules/Posts/Application/View/CommentViewAssembler.php-97-     */
app/src/Modules/Posts/Application/View/CommentViewAssembler.php:98:    private function authorViews(array $comments): array
app/src/Modules/Posts/Application/View/CommentViewAssembler.php-99-    {
app/src/Modules/Posts/Application/View/CommentViewAssembler.php-100-        $userIds = \array_values(\array_unique(\array_map(
app/src/Modules/Posts/Application/View/CommentViewAssembler.php-101-            static fn(Comment $comment): string => $comment->userId->value(),
app/src/Modules/Posts/Application/View/CommentViewAssembler.php:102:            $comments,
app/src/Modules/Posts/Application/View/CommentViewAssembler.php-103-        )));
app/src/Modules/Posts/Application/View/CommentViewAssembler.php-104-
--
app/src/Modules/Posts/Application/View/CommentViewAssembler.php-134-
app/src/Modules/Posts/Application/View/CommentViewAssembler.php-135-    /**
app/src/Modules/Posts/Application/View/CommentViewAssembler.php:136:     * @param array<int, Comment> $comments
app/src/Modules/Posts/Application/View/CommentViewAssembler.php-137-     *
app/src/Modules/Posts/Application/View/CommentViewAssembler.php-138-     * @return array<string, true>
app/src/Modules/Posts/Application/View/CommentViewAssembler.php-139-     */
app/src/Modules/Posts/Application/View/CommentViewAssembler.php:140:    private function likedCommentIds(array $comments, UserId $viewer): array
app/src/Modules/Posts/Application/View/CommentViewAssembler.php-141-    {
app/src/Modules/Posts/Application/View/CommentViewAssembler.php:142:        $commentIds = \array_map(static fn(Comment $comment): CommentId => $comment->id, $comments);
app/src/Modules/Posts/Application/View/CommentViewAssembler.php-143-        $liked = [];
app/src/Modules/Posts/Application/View/CommentViewAssembler.php-144-
--
app/src/Modules/Posts/Presentation/Http/Controller/PostController.php-49-     */
app/src/Modules/Posts/Presentation/Http/Controller/PostController.php-50-    #[Route(
app/src/Modules/Posts/Presentation/Http/Controller/PostController.php:51:        route: '/api/v1/posts',
app/src/Modules/Posts/Presentation/Http/Controller/PostController.php:52:        name: 'api.v1.posts.create',
app/src/Modules/Posts/Presentation/Http/Controller/PostController.php-53-        methods: ['POST'],
app/src/Modules/Posts/Presentation/Http/Controller/PostController.php-54-        group: 'api',
--
app/src/Modules/Posts/Presentation/Http/Controller/PostController.php-86-     */
app/src/Modules/Posts/Presentation/Http/Controller/PostController.php-87-    #[Route(
app/src/Modules/Posts/Presentation/Http/Controller/PostController.php:88:        route: '/api/v1/posts/<id>/publish',
app/src/Modules/Posts/Presentation/Http/Controller/PostController.php:89:        name: 'api.v1.posts.publish',
app/src/Modules/Posts/Presentation/Http/Controller/PostController.php-90-        methods: ['POST'],
app/src/Modules/Posts/Presentation/Http/Controller/PostController.php-91-        group: 'api',
--
app/src/Modules/Posts/Presentation/Http/Controller/PostController.php-120-     */
app/src/Modules/Posts/Presentation/Http/Controller/PostController.php-121-    #[Route(
app/src/Modules/Posts/Presentation/Http/Controller/PostController.php:122:        route: '/api/v1/posts/<id>/repost',
app/src/Modules/Posts/Presentation/Http/Controller/PostController.php:123:        name: 'api.v1.posts.repost',
app/src/Modules/Posts/Presentation/Http/Controller/PostController.php-124-        methods: ['POST'],
app/src/Modules/Posts/Presentation/Http/Controller/PostController.php-125-        group: 'api',
--
app/src/Modules/Posts/Presentation/Http/Controller/PostController.php-155-
app/src/Modules/Posts/Presentation/Http/Controller/PostController.php-156-    #[Route(
app/src/Modules/Posts/Presentation/Http/Controller/PostController.php:157:        route: '/api/v1/posts/<id>',
app/src/Modules/Posts/Presentation/Http/Controller/PostController.php:158:        name: 'api.v1.posts.delete',
app/src/Modules/Posts/Presentation/Http/Controller/PostController.php-159-        methods: ['DELETE'],
app/src/Modules/Posts/Presentation/Http/Controller/PostController.php-160-        group: 'api',
--
app/src/Modules/Posts/Presentation/Http/Controller/PostController.php-186-
app/src/Modules/Posts/Presentation/Http/Controller/PostController.php-187-    #[Route(
app/src/Modules/Posts/Presentation/Http/Controller/PostController.php:188:        route: '/api/v1/posts/<id>/like',
app/src/Modules/Posts/Presentation/Http/Controller/PostController.php:189:        name: 'api.v1.posts.like',
app/src/Modules/Posts/Presentation/Http/Controller/PostController.php-190-        methods: ['POST'],
app/src/Modules/Posts/Presentation/Http/Controller/PostController.php-191-        group: 'api',
--
app/src/Modules/Posts/Presentation/Http/Controller/PostController.php-217-
app/src/Modules/Posts/Presentation/Http/Controller/PostController.php-218-    #[Route(
app/src/Modules/Posts/Presentation/Http/Controller/PostController.php:219:        route: '/api/v1/posts/<id>/like',
app/src/Modules/Posts/Presentation/Http/Controller/PostController.php:220:        name: 'api.v1.posts.unlike',
app/src/Modules/Posts/Presentation/Http/Controller/PostController.php-221-        methods: ['DELETE'],
app/src/Modules/Posts/Presentation/Http/Controller/PostController.php-222-        group: 'api',
--
app/src/Modules/Posts/Presentation/Http/Controller/PostController.php-251-     */
app/src/Modules/Posts/Presentation/Http/Controller/PostController.php-252-    #[Route(
app/src/Modules/Posts/Presentation/Http/Controller/PostController.php:253:        route: '/api/v1/posts/<id>',
app/src/Modules/Posts/Presentation/Http/Controller/PostController.php:254:        name: 'api.v1.posts.show',
app/src/Modules/Posts/Presentation/Http/Controller/PostController.php-255-        methods: ['GET'],
app/src/Modules/Posts/Presentation/Http/Controller/PostController.php-256-        group: 'api',
--
app/src/Modules/Posts/Presentation/Http/Controller/PostController.php-285-     */
app/src/Modules/Posts/Presentation/Http/Controller/PostController.php-286-    #[Route(
app/src/Modules/Posts/Presentation/Http/Controller/PostController.php:287:        route: '/api/v1/posts/user/<id>',
app/src/Modules/Posts/Presentation/Http/Controller/PostController.php:288:        name: 'api.v1.posts.user_feed',
app/src/Modules/Posts/Presentation/Http/Controller/PostController.php-289-        methods: ['GET'],
app/src/Modules/Posts/Presentation/Http/Controller/PostController.php-290-        group: 'api',
--
app/src/Modules/Posts/Presentation/Http/Controller/PostController.php-316-        $resources = \array_map(
app/src/Modules/Posts/Presentation/Http/Controller/PostController.php-317-            static fn(PostView $post): PostResource => PostResource::fromView($post),
app/src/Modules/Posts/Presentation/Http/Controller/PostController.php:318:            $result->posts,
app/src/Modules/Posts/Presentation/Http/Controller/PostController.php-319-        );
app/src/Modules/Posts/Presentation/Http/Controller/PostController.php-320-
--
app/src/Modules/Posts/Presentation/Http/Controller/CommentController.php-46-     */
app/src/Modules/Posts/Presentation/Http/Controller/CommentController.php-47-    #[Route(
app/src/Modules/Posts/Presentation/Http/Controller/CommentController.php:48:        route: '/api/v1/posts/<id>/comments',
app/src/Modules/Posts/Presentation/Http/Controller/CommentController.php:49:        name: 'api.v1.posts.comments.create',
app/src/Modules/Posts/Presentation/Http/Controller/CommentController.php-50-        methods: ['POST'],
app/src/Modules/Posts/Presentation/Http/Controller/CommentController.php-51-        group: 'api',
--
app/src/Modules/Posts/Presentation/Http/Controller/CommentController.php-82-     */
app/src/Modules/Posts/Presentation/Http/Controller/CommentController.php-83-    #[Route(
app/src/Modules/Posts/Presentation/Http/Controller/CommentController.php:84:        route: '/api/v1/posts/comments/<id>/replies',
app/src/Modules/Posts/Presentation/Http/Controller/CommentController.php:85:        name: 'api.v1.posts.comments.reply',
app/src/Modules/Posts/Presentation/Http/Controller/CommentController.php-86-        methods: ['POST'],
app/src/Modules/Posts/Presentation/Http/Controller/CommentController.php-87-        group: 'api',
--
app/src/Modules/Posts/Presentation/Http/Controller/CommentController.php-115-
app/src/Modules/Posts/Presentation/Http/Controller/CommentController.php-116-    #[Route(
app/src/Modules/Posts/Presentation/Http/Controller/CommentController.php:117:        route: '/api/v1/posts/comments/<id>',
app/src/Modules/Posts/Presentation/Http/Controller/CommentController.php:118:        name: 'api.v1.posts.comments.delete',
app/src/Modules/Posts/Presentation/Http/Controller/CommentController.php-119-        methods: ['DELETE'],
app/src/Modules/Posts/Presentation/Http/Controller/CommentController.php-120-        group: 'api',
--
app/src/Modules/Posts/Presentation/Http/Controller/CommentController.php-146-
app/src/Modules/Posts/Presentation/Http/Controller/CommentController.php-147-    #[Route(
app/src/Modules/Posts/Presentation/Http/Controller/CommentController.php:148:        route: '/api/v1/posts/comments/<id>/like',
app/src/Modules/Posts/Presentation/Http/Controller/CommentController.php:149:        name: 'api.v1.posts.comments.like',
app/src/Modules/Posts/Presentation/Http/Controller/CommentController.php-150-        methods: ['POST'],
app/src/Modules/Posts/Presentation/Http/Controller/CommentController.php-151-        group: 'api',
--
app/src/Modules/Posts/Presentation/Http/Controller/CommentController.php-177-
app/src/Modules/Posts/Presentation/Http/Controller/CommentController.php-178-    #[Route(
app/src/Modules/Posts/Presentation/Http/Controller/CommentController.php:179:        route: '/api/v1/posts/comments/<id>/like',
app/src/Modules/Posts/Presentation/Http/Controller/CommentController.php:180:        name: 'api.v1.posts.comments.unlike',
app/src/Modules/Posts/Presentation/Http/Controller/CommentController.php-181-        methods: ['DELETE'],
app/src/Modules/Posts/Presentation/Http/Controller/CommentController.php-182-        group: 'api',
--
app/src/Modules/Posts/Presentation/Http/Controller/CommentController.php-211-     */
app/src/Modules/Posts/Presentation/Http/Controller/CommentController.php-212-    #[Route(
app/src/Modules/Posts/Presentation/Http/Controller/CommentController.php:213:        route: '/api/v1/posts/<id>/comments',
app/src/Modules/Posts/Presentation/Http/Controller/CommentController.php:214:        name: 'api.v1.posts.comments.list',
app/src/Modules/Posts/Presentation/Http/Controller/CommentController.php-215-        methods: ['GET'],
app/src/Modules/Posts/Presentation/Http/Controller/CommentController.php-216-        group: 'api',
--
app/src/Modules/Posts/Presentation/Http/Controller/CommentController.php-242-        $resources = \array_map(
app/src/Modules/Posts/Presentation/Http/Controller/CommentController.php-243-            static fn(CommentView $comment): CommentResource => CommentResource::fromView($comment),
app/src/Modules/Posts/Presentation/Http/Controller/CommentController.php:244:            $result->comments,
app/src/Modules/Posts/Presentation/Http/Controller/CommentController.php-245-        );
app/src/Modules/Posts/Presentation/Http/Controller/CommentController.php-246-
--
app/src/Modules/Posts/Presentation/Http/Controller/CommentController.php-255-     */
app/src/Modules/Posts/Presentation/Http/Controller/CommentController.php-256-    #[Route(
app/src/Modules/Posts/Presentation/Http/Controller/CommentController.php:257:        route: '/api/v1/posts/comments/<id>/replies',
app/src/Modules/Posts/Presentation/Http/Controller/CommentController.php:258:        name: 'api.v1.posts.comments.replies',
app/src/Modules/Posts/Presentation/Http/Controller/CommentController.php-259-        methods: ['GET'],
app/src/Modules/Posts/Presentation/Http/Controller/CommentController.php-260-        group: 'api',
--
app/src/Modules/Posts/Application/View/PostViewAssembler.php-76-     * @return list<PostView>
app/src/Modules/Posts/Application/View/PostViewAssembler.php-77-     */
app/src/Modules/Posts/Application/View/PostViewAssembler.php:78:    public function fromPosts(PostCollection $posts, UserId $viewer): array
app/src/Modules/Posts/Application/View/PostViewAssembler.php-79-    {
app/src/Modules/Posts/Application/View/PostViewAssembler.php:80:        $entities = $posts->all();
app/src/Modules/Posts/Application/View/PostViewAssembler.php-81-
app/src/Modules/Posts/Application/View/PostViewAssembler.php-82-        if ($entities === []) {
--
app/src/Modules/Posts/Application/View/PostViewAssembler.php-86-        $postIds = $this->postIds($entities);
app/src/Modules/Posts/Application/View/PostViewAssembler.php-87-        $authors = $this->authorViews($entities);
app/src/Modules/Posts/Application/View/PostViewAssembler.php:88:        $likedPostIds = $this->likedPostIds(posts: $entities, viewer: $viewer);
app/src/Modules/Posts/Application/View/PostViewAssembler.php-89-        $mediaByPost = $this->mediaCollectionsByPost($postIds);
app/src/Modules/Posts/Application/View/PostViewAssembler.php-90-        $tagsByPost = $this->tagCollectionsByPost($postIds);
app/src/Modules/Posts/Application/View/PostViewAssembler.php-91-        $tagTexts = $this->tagTexts(...\array_values($tagsByPost));
app/src/Modules/Posts/Application/View/PostViewAssembler.php:92:        $originals = $this->originalViewsByPost(posts: $entities, viewer: $viewer);
app/src/Modules/Posts/Application/View/PostViewAssembler.php-93-
app/src/Modules/Posts/Application/View/PostViewAssembler.php-94-        $views = [];
--
app/src/Modules/Posts/Application/View/PostViewAssembler.php-122-            attachmentType: $this->attachmentType(post: $post, media: $media)->value,
app/src/Modules/Posts/Application/View/PostViewAssembler.php-123-            likesCount: $post->likesCount->value(),
app/src/Modules/Posts/Application/View/PostViewAssembler.php:124:            repostsCount: $post->repostsCount->value(),
app/src/Modules/Posts/Application/View/PostViewAssembler.php:125:            commentsCount: $post->commentsCount->value(),
app/src/Modules/Posts/Application/View/PostViewAssembler.php-126-            likedByMe: $likedByMe,
app/src/Modules/Posts/Application/View/PostViewAssembler.php-127-            createdAt: $post->createdAt->format(\DateTimeInterface::ATOM),
--
app/src/Modules/Posts/Application/View/PostViewAssembler.php-160-
app/src/Modules/Posts/Application/View/PostViewAssembler.php-161-    /**
app/src/Modules/Posts/Application/View/PostViewAssembler.php:162:     * @param array<int, Post> $posts
app/src/Modules/Posts/Application/View/PostViewAssembler.php-163-     *
app/src/Modules/Posts/Application/View/PostViewAssembler.php-164-     * @return array<string, AuthorView>
app/src/Modules/Posts/Application/View/PostViewAssembler.php-165-     */
app/src/Modules/Posts/Application/View/PostViewAssembler.php:166:    private function authorViews(array $posts): array
app/src/Modules/Posts/Application/View/PostViewAssembler.php-167-    {
app/src/Modules/Posts/Application/View/PostViewAssembler.php-168-        $userIds = \array_values(\array_unique(\array_map(
app/src/Modules/Posts/Application/View/PostViewAssembler.php-169-            static fn(Post $post): string => $post->userId->value(),
app/src/Modules/Posts/Application/View/PostViewAssembler.php:170:            $posts,
app/src/Modules/Posts/Application/View/PostViewAssembler.php-171-        )));
app/src/Modules/Posts/Application/View/PostViewAssembler.php-172-
--
app/src/Modules/Posts/Application/View/PostViewAssembler.php-202-
app/src/Modules/Posts/Application/View/PostViewAssembler.php-203-    /**
app/src/Modules/Posts/Application/View/PostViewAssembler.php:204:     * @param array<int, Post> $posts
app/src/Modules/Posts/Application/View/PostViewAssembler.php-205-     *
app/src/Modules/Posts/Application/View/PostViewAssembler.php-206-     * @return array<string, true>
app/src/Modules/Posts/Application/View/PostViewAssembler.php-207-     */
app/src/Modules/Posts/Application/View/PostViewAssembler.php:208:    private function likedPostIds(array $posts, UserId $viewer): array
app/src/Modules/Posts/Application/View/PostViewAssembler.php-209-    {
app/src/Modules/Posts/Application/View/PostViewAssembler.php:210:        $postIds = \array_map(static fn(Post $post): PostId => $post->id, $posts);
app/src/Modules/Posts/Application/View/PostViewAssembler.php-211-        $liked = [];
app/src/Modules/Posts/Application/View/PostViewAssembler.php-212-
--
app/src/Modules/Posts/Application/View/PostViewAssembler.php-362-
app/src/Modules/Posts/Application/View/PostViewAssembler.php-363-    /**
app/src/Modules/Posts/Application/View/PostViewAssembler.php:364:     * @param array<int, Post> $posts
app/src/Modules/Posts/Application/View/PostViewAssembler.php-365-     *
app/src/Modules/Posts/Application/View/PostViewAssembler.php-366-     * @return list<PostId>
app/src/Modules/Posts/Application/View/PostViewAssembler.php-367-     */
app/src/Modules/Posts/Application/View/PostViewAssembler.php:368:    private function postIds(array $posts): array
app/src/Modules/Posts/Application/View/PostViewAssembler.php-369-    {
app/src/Modules/Posts/Application/View/PostViewAssembler.php:370:        return \array_values(\array_map(static fn(Post $post): PostId => $post->id, $posts));
app/src/Modules/Posts/Application/View/PostViewAssembler.php-371-    }
app/src/Modules/Posts/Application/View/PostViewAssembler.php-372-
--
app/src/Modules/Posts/Application/View/PostViewAssembler.php-403-     * попадает — у репоста original будет null.
app/src/Modules/Posts/Application/View/PostViewAssembler.php-404-     *
app/src/Modules/Posts/Application/View/PostViewAssembler.php:405:     * @param array<int, Post> $posts
app/src/Modules/Posts/Application/View/PostViewAssembler.php-406-     *
app/src/Modules/Posts/Application/View/PostViewAssembler.php-407-     * @return array<string, PostView>
app/src/Modules/Posts/Application/View/PostViewAssembler.php-408-     */
app/src/Modules/Posts/Application/View/PostViewAssembler.php:409:    private function originalViewsByPost(array $posts, UserId $viewer): array
app/src/Modules/Posts/Application/View/PostViewAssembler.php-410-    {
app/src/Modules/Posts/Application/View/PostViewAssembler.php-411-        $originalIds = [];
app/src/Modules/Posts/Application/View/PostViewAssembler.php-412-
app/src/Modules/Posts/Application/View/PostViewAssembler.php:413:        foreach ($posts as $post) {
app/src/Modules/Posts/Application/View/PostViewAssembler.php-414-            $originalId = $post->original->value();
app/src/Modules/Posts/Application/View/PostViewAssembler.php-415-
--
app/src/Modules/Posts/Application/View/PostViewAssembler.php-434-        $originalPostIds = $this->postIds($entities);
app/src/Modules/Posts/Application/View/PostViewAssembler.php-435-        $authors = $this->authorViews($entities);
app/src/Modules/Posts/Application/View/PostViewAssembler.php:436:        $likedPostIds = $this->likedPostIds(posts: $entities, viewer: $viewer);
app/src/Modules/Posts/Application/View/PostViewAssembler.php-437-        $mediaByPost = $this->mediaCollectionsByPost($originalPostIds);
app/src/Modules/Posts/Application/View/PostViewAssembler.php-438-        $tagsByPost = $this->tagCollectionsByPost($originalPostIds);
--
app/src/Modules/Posts/Application/Command/PublishPost/PublishPostHandler.php-38-    {
app/src/Modules/Posts/Application/Command/PublishPost/PublishPostHandler.php-39-        $post = $this->postRepository->findById(PostId::fromString($command->postId))
app/src/Modules/Posts/Application/Command/PublishPost/PublishPostHandler.php:40:            ?? throw new NotFoundException('app.posts.not_found');
app/src/Modules/Posts/Application/Command/PublishPost/PublishPostHandler.php-41-
app/src/Modules/Posts/Application/Command/PublishPost/PublishPostHandler.php-42-        if ($post->deletion->isDeleted() || $post->status === PostStatus::Blocked) {
app/src/Modules/Posts/Application/Command/PublishPost/PublishPostHandler.php:43:            throw new NotFoundException('app.posts.not_found');
app/src/Modules/Posts/Application/Command/PublishPost/PublishPostHandler.php-44-        }
app/src/Modules/Posts/Application/Command/PublishPost/PublishPostHandler.php-45-
app/src/Modules/Posts/Application/Command/PublishPost/PublishPostHandler.php-46-        if (!$post->userId->equals(UserId::fromString($command->authUserId))) {
app/src/Modules/Posts/Application/Command/PublishPost/PublishPostHandler.php:47:            throw new ForbiddenException('app.posts.forbidden');
app/src/Modules/Posts/Application/Command/PublishPost/PublishPostHandler.php-48-        }
app/src/Modules/Posts/Application/Command/PublishPost/PublishPostHandler.php-49-

exec
/bin/zsh -lc "rg -n \"post_mentions|comment_mentions|createTable\\('post|foreignKey|user_id\" app/database/migrations -C 4" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
app/database/migrations/20260613.130000_0_create_notification_domain_tables.php-14-    {
app/database/migrations/20260613.130000_0_create_notification_domain_tables.php-15-        $this->table('notifications')
app/database/migrations/20260613.130000_0_create_notification_domain_tables.php-16-            ->addColumn('id', 'uuid', ['nullable' => false])
app/database/migrations/20260613.130000_0_create_notification_domain_tables.php-17-            ->addColumn('outbox_id', 'uuid', ['nullable' => false])
app/database/migrations/20260613.130000_0_create_notification_domain_tables.php:18:            ->addColumn('user_id', 'uuid', ['nullable' => false])
app/database/migrations/20260613.130000_0_create_notification_domain_tables.php-19-            ->addColumn('type', 'string', ['length' => 255, 'nullable' => false])
app/database/migrations/20260613.130000_0_create_notification_domain_tables.php-20-            ->addColumn('title', 'string', ['length' => 255, 'nullable' => false])
app/database/migrations/20260613.130000_0_create_notification_domain_tables.php-21-            ->addColumn('body', 'text', ['nullable' => false])
app/database/migrations/20260613.130000_0_create_notification_domain_tables.php-22-            ->addColumn('action_type', 'string', ['length' => 255, 'nullable' => true])
--
app/database/migrations/20260613.130000_0_create_notification_domain_tables.php-26-            ->addColumn('created_at', 'datetime', ['nullable' => false])
app/database/migrations/20260613.130000_0_create_notification_domain_tables.php-27-            ->addColumn('updated_at', 'datetime', ['nullable' => false])
app/database/migrations/20260613.130000_0_create_notification_domain_tables.php-28-            ->setPrimaryKeys(['id'])
app/database/migrations/20260613.130000_0_create_notification_domain_tables.php-29-            ->addIndex(['outbox_id'], ['unique' => true])
app/database/migrations/20260613.130000_0_create_notification_domain_tables.php:30:            ->addIndex(['user_id', 'id'])
app/database/migrations/20260613.130000_0_create_notification_domain_tables.php:31:            ->addIndex(['user_id', 'read_at'])
app/database/migrations/20260613.130000_0_create_notification_domain_tables.php-32-            ->create();
app/database/migrations/20260613.130000_0_create_notification_domain_tables.php-33-
app/database/migrations/20260613.130000_0_create_notification_domain_tables.php-34-        $this->table('notification_settings')
app/database/migrations/20260613.130000_0_create_notification_domain_tables.php-35-            ->addColumn('id', 'uuid', ['nullable' => false])
app/database/migrations/20260613.130000_0_create_notification_domain_tables.php:36:            ->addColumn('user_id', 'uuid', ['nullable' => false])
app/database/migrations/20260613.130000_0_create_notification_domain_tables.php-37-            ->addColumn('type', 'string', ['length' => 255, 'nullable' => false])
app/database/migrations/20260613.130000_0_create_notification_domain_tables.php-38-            ->addColumn('channel', 'string', ['length' => 32, 'nullable' => false])
app/database/migrations/20260613.130000_0_create_notification_domain_tables.php-39-            ->addColumn('enabled', 'boolean', ['nullable' => false])
app/database/migrations/20260613.130000_0_create_notification_domain_tables.php-40-            ->addColumn('created_at', 'datetime', ['nullable' => false])
app/database/migrations/20260613.130000_0_create_notification_domain_tables.php-41-            ->addColumn('updated_at', 'datetime', ['nullable' => false])
app/database/migrations/20260613.130000_0_create_notification_domain_tables.php-42-            ->setPrimaryKeys(['id'])
app/database/migrations/20260613.130000_0_create_notification_domain_tables.php:43:            ->addIndex(['user_id', 'type', 'channel'], ['unique' => true])
app/database/migrations/20260613.130000_0_create_notification_domain_tables.php-44-            ->create();
app/database/migrations/20260613.130000_0_create_notification_domain_tables.php-45-
app/database/migrations/20260613.130000_0_create_notification_domain_tables.php-46-        $this->table('notification_device_tokens')
app/database/migrations/20260613.130000_0_create_notification_domain_tables.php-47-            ->addColumn('id', 'uuid', ['nullable' => false])
app/database/migrations/20260613.130000_0_create_notification_domain_tables.php:48:            ->addColumn('user_id', 'uuid', ['nullable' => false])
app/database/migrations/20260613.130000_0_create_notification_domain_tables.php-49-            ->addColumn('token', 'string', ['length' => 1024, 'nullable' => false])
app/database/migrations/20260613.130000_0_create_notification_domain_tables.php-50-            ->addColumn('platform', 'string', ['length' => 32, 'nullable' => false])
app/database/migrations/20260613.130000_0_create_notification_domain_tables.php-51-            ->addColumn('created_at', 'datetime', ['nullable' => false])
app/database/migrations/20260613.130000_0_create_notification_domain_tables.php-52-            ->addColumn('updated_at', 'datetime', ['nullable' => false])
app/database/migrations/20260613.130000_0_create_notification_domain_tables.php-53-            ->setPrimaryKeys(['id'])
app/database/migrations/20260613.130000_0_create_notification_domain_tables.php-54-            ->addIndex(['token'], ['unique' => true])
app/database/migrations/20260613.130000_0_create_notification_domain_tables.php:55:            ->addIndex(['user_id'])
app/database/migrations/20260613.130000_0_create_notification_domain_tables.php-56-            ->create();
app/database/migrations/20260613.130000_0_create_notification_domain_tables.php-57-    }
app/database/migrations/20260613.130000_0_create_notification_domain_tables.php-58-
app/database/migrations/20260613.130000_0_create_notification_domain_tables.php-59-    public function down(): void
--
app/database/migrations/20260613.143902_0_create_access_domain_tables.php-51-            ->create();
app/database/migrations/20260613.143902_0_create_access_domain_tables.php-52-
app/database/migrations/20260613.143902_0_create_access_domain_tables.php-53-        $this->table('user_roles')
app/database/migrations/20260613.143902_0_create_access_domain_tables.php-54-            ->addColumn('id', 'uuid', ['nullable' => false])
app/database/migrations/20260613.143902_0_create_access_domain_tables.php:55:            ->addColumn('user_id', 'uuid', ['nullable' => false])
app/database/migrations/20260613.143902_0_create_access_domain_tables.php-56-            ->addColumn('role_id', 'uuid', ['nullable' => false])
app/database/migrations/20260613.143902_0_create_access_domain_tables.php-57-            ->setPrimaryKeys(['id'])
app/database/migrations/20260613.143902_0_create_access_domain_tables.php-58-            ->addForeignKey(
app/database/migrations/20260613.143902_0_create_access_domain_tables.php:59:                ['user_id'],
app/database/migrations/20260613.143902_0_create_access_domain_tables.php-60-                'users',
app/database/migrations/20260613.143902_0_create_access_domain_tables.php-61-                ['id'],
app/database/migrations/20260613.143902_0_create_access_domain_tables.php-62-                ['delete' => 'CASCADE', 'update' => 'CASCADE', 'indexCreate' => false],
app/database/migrations/20260613.143902_0_create_access_domain_tables.php-63-            )
--
app/database/migrations/20260613.143902_0_create_access_domain_tables.php-66-                'roles',
app/database/migrations/20260613.143902_0_create_access_domain_tables.php-67-                ['id'],
app/database/migrations/20260613.143902_0_create_access_domain_tables.php-68-                ['delete' => 'CASCADE', 'update' => 'CASCADE', 'indexCreate' => false],
app/database/migrations/20260613.143902_0_create_access_domain_tables.php-69-            )
app/database/migrations/20260613.143902_0_create_access_domain_tables.php:70:            ->addIndex(['user_id', 'role_id'], ['unique' => true])
app/database/migrations/20260613.143902_0_create_access_domain_tables.php-71-            ->create();
app/database/migrations/20260613.143902_0_create_access_domain_tables.php-72-    }
app/database/migrations/20260613.143902_0_create_access_domain_tables.php-73-
app/database/migrations/20260613.143902_0_create_access_domain_tables.php-74-    public function down(): void
--
app/database/migrations/20260613.143901_0_create_user_domain_tables.php-40-            ->create();
app/database/migrations/20260613.143901_0_create_user_domain_tables.php-41-
app/database/migrations/20260613.143901_0_create_user_domain_tables.php-42-        $this->table('user_bans')
app/database/migrations/20260613.143901_0_create_user_domain_tables.php-43-            ->addColumn('id', 'uuid', ['nullable' => false])
app/database/migrations/20260613.143901_0_create_user_domain_tables.php:44:            ->addColumn('user_id', 'uuid', ['nullable' => false])
app/database/migrations/20260613.143901_0_create_user_domain_tables.php-45-            ->addColumn('banned_by_id', 'uuid', ['nullable' => false])
app/database/migrations/20260613.143901_0_create_user_domain_tables.php-46-            ->addColumn('reason', 'string', ['length' => 500, 'nullable' => false])
app/database/migrations/20260613.143901_0_create_user_domain_tables.php-47-            ->addColumn('expires_at', 'datetime', ['nullable' => true])
app/database/migrations/20260613.143901_0_create_user_domain_tables.php-48-            ->addColumn('unbanned_at', 'datetime', ['nullable' => true])
--
app/database/migrations/20260613.143901_0_create_user_domain_tables.php-51-            ->addColumn('created_at', 'datetime', ['nullable' => false])
app/database/migrations/20260613.143901_0_create_user_domain_tables.php-52-            ->addColumn('updated_at', 'datetime', ['nullable' => false])
app/database/migrations/20260613.143901_0_create_user_domain_tables.php-53-            ->setPrimaryKeys(['id'])
app/database/migrations/20260613.143901_0_create_user_domain_tables.php-54-            ->addForeignKey(
app/database/migrations/20260613.143901_0_create_user_domain_tables.php:55:                ['user_id'],
app/database/migrations/20260613.143901_0_create_user_domain_tables.php-56-                'users',
app/database/migrations/20260613.143901_0_create_user_domain_tables.php-57-                ['id'],
app/database/migrations/20260613.143901_0_create_user_domain_tables.php-58-                ['delete' => 'CASCADE', 'update' => 'CASCADE', 'indexCreate' => false],
app/database/migrations/20260613.143901_0_create_user_domain_tables.php-59-            )
app/database/migrations/20260613.143901_0_create_user_domain_tables.php:60:            ->addIndex(['user_id'])
app/database/migrations/20260613.143901_0_create_user_domain_tables.php-61-            ->create();
app/database/migrations/20260613.143901_0_create_user_domain_tables.php-62-
app/database/migrations/20260613.143901_0_create_user_domain_tables.php-63-        $this->table('reserved_nicknames')
app/database/migrations/20260613.143901_0_create_user_domain_tables.php-64-            ->addColumn('id', 'uuid', ['nullable' => false])
app/database/migrations/20260613.143901_0_create_user_domain_tables.php-65-            ->addColumn('nickname', 'string', ['length' => 30, 'nullable' => false])
app/database/migrations/20260613.143901_0_create_user_domain_tables.php:66:            ->addColumn('assigned_user_id', 'uuid', ['nullable' => true])
app/database/migrations/20260613.143901_0_create_user_domain_tables.php-67-            ->addColumn('created_at', 'datetime', ['nullable' => false])
app/database/migrations/20260613.143901_0_create_user_domain_tables.php-68-            ->addColumn('updated_at', 'datetime', ['nullable' => false])
app/database/migrations/20260613.143901_0_create_user_domain_tables.php-69-            ->setPrimaryKeys(['id'])
app/database/migrations/20260613.143901_0_create_user_domain_tables.php-70-            ->addIndex(['nickname'], ['unique' => true])
app/database/migrations/20260613.143901_0_create_user_domain_tables.php-71-            ->addForeignKey(
app/database/migrations/20260613.143901_0_create_user_domain_tables.php:72:                ['assigned_user_id'],
app/database/migrations/20260613.143901_0_create_user_domain_tables.php-73-                'users',
app/database/migrations/20260613.143901_0_create_user_domain_tables.php-74-                ['id'],
app/database/migrations/20260613.143901_0_create_user_domain_tables.php-75-                ['delete' => 'SET NULL', 'update' => 'CASCADE', 'indexCreate' => false],
app/database/migrations/20260613.143901_0_create_user_domain_tables.php-76-            )
app/database/migrations/20260613.143901_0_create_user_domain_tables.php:77:            ->addIndex(['assigned_user_id'])
app/database/migrations/20260613.143901_0_create_user_domain_tables.php-78-            ->create();
app/database/migrations/20260613.143901_0_create_user_domain_tables.php-79-    }
app/database/migrations/20260613.143901_0_create_user_domain_tables.php-80-
app/database/migrations/20260613.143901_0_create_user_domain_tables.php-81-    public function down(): void
--
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-31-            ->create();
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-32-
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-33-        $this->table('posts')
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-34-            ->addColumn('id', 'uuid', ['nullable' => false])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php:35:            ->addColumn('user_id', 'uuid', ['nullable' => false])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-36-            ->addColumn('text', 'text', ['nullable' => true])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-37-            ->addColumn('status', 'string', ['length' => 32, 'nullable' => false])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-38-            ->addColumn('attachment_type', 'string', ['length' => 16, 'nullable' => false])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-39-            ->addColumn('lesson_id', 'uuid', ['nullable' => true])
--
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-45-            ->addColumn('deleted_at', 'datetime', ['nullable' => true])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-46-            ->addColumn('created_at', 'datetime', ['nullable' => false])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-47-            ->addColumn('updated_at', 'datetime', ['nullable' => false])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-48-            ->setPrimaryKeys(['id'])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php:49:            ->addIndex(['user_id', 'id'])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-50-            ->addIndex(['status'])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-51-            ->addIndex(['parent_post_id'])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-52-            ->addForeignKey(
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php:53:                ['user_id'],
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-54-                'users',
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-55-                ['id'],
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-56-                ['delete' => 'RESTRICT', 'update' => 'CASCADE', 'indexCreate' => false],
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-57-            )
--
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-94-
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-95-        $this->table('post_likes')
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-96-            ->addColumn('id', 'uuid', ['nullable' => false])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-97-            ->addColumn('post_id', 'uuid', ['nullable' => false])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php:98:            ->addColumn('user_id', 'uuid', ['nullable' => false])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-99-            ->addColumn('created_at', 'datetime', ['nullable' => false])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-100-            ->addColumn('updated_at', 'datetime', ['nullable' => false])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-101-            ->setPrimaryKeys(['id'])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php:102:            ->addIndex(['post_id', 'user_id'], ['unique' => true])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php:103:            ->addIndex(['user_id'])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-104-            ->addForeignKey(
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-105-                ['post_id'],
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-106-                'posts',
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-107-                ['id'],
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-108-                ['delete' => 'CASCADE', 'update' => 'CASCADE', 'indexCreate' => false],
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-109-            )
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-110-            ->addForeignKey(
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php:111:                ['user_id'],
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-112-                'users',
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-113-                ['id'],
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-114-                ['delete' => 'CASCADE', 'update' => 'CASCADE', 'indexCreate' => false],
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-115-            )
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-116-            ->create();
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-117-
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php:118:        $this->table('post_mentions')
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-119-            ->addColumn('id', 'uuid', ['nullable' => false])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-120-            ->addColumn('post_id', 'uuid', ['nullable' => false])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php:121:            ->addColumn('user_id', 'uuid', ['nullable' => false])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-122-            ->addColumn('created_at', 'datetime', ['nullable' => false])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-123-            ->addColumn('updated_at', 'datetime', ['nullable' => false])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-124-            ->setPrimaryKeys(['id'])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php:125:            ->addIndex(['post_id', 'user_id'], ['unique' => true])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php:126:            ->addIndex(['user_id'])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-127-            ->addForeignKey(
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-128-                ['post_id'],
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-129-                'posts',
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-130-                ['id'],
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-131-                ['delete' => 'CASCADE', 'update' => 'CASCADE', 'indexCreate' => false],
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-132-            )
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-133-            ->addForeignKey(
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php:134:                ['user_id'],
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-135-                'users',
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-136-                ['id'],
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-137-                ['delete' => 'CASCADE', 'update' => 'CASCADE', 'indexCreate' => false],
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-138-            )
--
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-195-
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-196-        $this->table('comments')
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-197-            ->addColumn('id', 'uuid', ['nullable' => false])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-198-            ->addColumn('post_id', 'uuid', ['nullable' => false])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php:199:            ->addColumn('user_id', 'uuid', ['nullable' => false])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-200-            ->addColumn('text', 'text', ['nullable' => false])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-201-            ->addColumn('parent_comment_id', 'uuid', ['nullable' => true])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-202-            ->addColumn('likes_count', 'integer', ['nullable' => false, 'default' => 0])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-203-            ->addColumn('replies_count', 'integer', ['nullable' => false, 'default' => 0])
--
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-208-            ->addColumn('updated_at', 'datetime', ['nullable' => false])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-209-            ->setPrimaryKeys(['id'])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-210-            ->addIndex(['post_id', 'id'])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-211-            ->addIndex(['parent_comment_id'])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php:212:            ->addIndex(['user_id'])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-213-            ->addForeignKey(
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-214-                ['post_id'],
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-215-                'posts',
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-216-                ['id'],
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-217-                ['delete' => 'CASCADE', 'update' => 'CASCADE', 'indexCreate' => false],
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-218-            )
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-219-            ->addForeignKey(
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php:220:                ['user_id'],
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-221-                'users',
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-222-                ['id'],
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-223-                ['delete' => 'RESTRICT', 'update' => 'CASCADE', 'indexCreate' => false],
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-224-            )
--
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-242-
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-243-        $this->table('comment_likes')
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-244-            ->addColumn('id', 'uuid', ['nullable' => false])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-245-            ->addColumn('comment_id', 'uuid', ['nullable' => false])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php:246:            ->addColumn('user_id', 'uuid', ['nullable' => false])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-247-            ->addColumn('created_at', 'datetime', ['nullable' => false])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-248-            ->addColumn('updated_at', 'datetime', ['nullable' => false])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-249-            ->setPrimaryKeys(['id'])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php:250:            ->addIndex(['comment_id', 'user_id'], ['unique' => true])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php:251:            ->addIndex(['user_id'])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-252-            ->addForeignKey(
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-253-                ['comment_id'],
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-254-                'comments',
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-255-                ['id'],
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-256-                ['delete' => 'CASCADE', 'update' => 'CASCADE', 'indexCreate' => false],
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-257-            )
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-258-            ->addForeignKey(
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php:259:                ['user_id'],
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-260-                'users',
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-261-                ['id'],
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-262-                ['delete' => 'CASCADE', 'update' => 'CASCADE', 'indexCreate' => false],
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-263-            )
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-264-            ->create();
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-265-
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php:266:        $this->table('comment_mentions')
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-267-            ->addColumn('id', 'uuid', ['nullable' => false])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-268-            ->addColumn('comment_id', 'uuid', ['nullable' => false])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php:269:            ->addColumn('user_id', 'uuid', ['nullable' => false])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-270-            ->addColumn('created_at', 'datetime', ['nullable' => false])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-271-            ->addColumn('updated_at', 'datetime', ['nullable' => false])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-272-            ->setPrimaryKeys(['id'])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php:273:            ->addIndex(['comment_id', 'user_id'], ['unique' => true])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php:274:            ->addIndex(['user_id'])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-275-            ->addForeignKey(
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-276-                ['comment_id'],
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-277-                'comments',
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-278-                ['id'],
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-279-                ['delete' => 'CASCADE', 'update' => 'CASCADE', 'indexCreate' => false],
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-280-            )
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-281-            ->addForeignKey(
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php:282:                ['user_id'],
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-283-                'users',
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-284-                ['id'],
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-285-                ['delete' => 'CASCADE', 'update' => 'CASCADE', 'indexCreate' => false],
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-286-            )
--
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-288-    }
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-289-
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-290-    public function down(): void
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-291-    {
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php:292:        $this->table('comment_mentions')->drop();
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-293-        $this->table('comment_likes')->drop();
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-294-        $this->table('comments')->drop();
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-295-        $this->table('post_blocks')->drop();
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-296-        $this->table('post_tags')->drop();
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php:297:        $this->table('post_mentions')->drop();
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-298-        $this->table('post_likes')->drop();
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-299-        $this->table('post_media')->drop();
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-300-        $this->table('tags')->drop();
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-301-        $this->table('posts')->drop();
--
app/database/migrations/20260615.141700_0_create_auth_domain_tables.php-38-            ->create();
app/database/migrations/20260615.141700_0_create_auth_domain_tables.php-39-
app/database/migrations/20260615.141700_0_create_auth_domain_tables.php-40-        $this->table('auth_tokens')
app/database/migrations/20260615.141700_0_create_auth_domain_tables.php-41-            ->addColumn('id', 'uuid', ['nullable' => false])
app/database/migrations/20260615.141700_0_create_auth_domain_tables.php:42:            ->addColumn('user_id', 'uuid', ['nullable' => false])
app/database/migrations/20260615.141700_0_create_auth_domain_tables.php-43-            ->addColumn('session_id', 'uuid', ['nullable' => false])
app/database/migrations/20260615.141700_0_create_auth_domain_tables.php-44-            ->addColumn('type', 'string', ['length' => 16, 'nullable' => false])
app/database/migrations/20260615.141700_0_create_auth_domain_tables.php-45-            ->addColumn('token_hash', 'text', ['nullable' => false])
app/database/migrations/20260615.141700_0_create_auth_domain_tables.php-46-            ->addColumn('expires_at', 'datetime', ['nullable' => false])
app/database/migrations/20260615.141700_0_create_auth_domain_tables.php-47-            ->addColumn('created_at', 'datetime', ['nullable' => false])
app/database/migrations/20260615.141700_0_create_auth_domain_tables.php-48-            ->addColumn('updated_at', 'datetime', ['nullable' => false])
app/database/migrations/20260615.141700_0_create_auth_domain_tables.php-49-            ->setPrimaryKeys(['id'])
app/database/migrations/20260615.141700_0_create_auth_domain_tables.php-50-            ->addIndex(['token_hash'], ['unique' => true])
app/database/migrations/20260615.141700_0_create_auth_domain_tables.php:51:            ->addIndex(['user_id'])
app/database/migrations/20260615.141700_0_create_auth_domain_tables.php-52-            ->addIndex(['session_id'])
app/database/migrations/20260615.141700_0_create_auth_domain_tables.php-53-            ->create();
app/database/migrations/20260615.141700_0_create_auth_domain_tables.php-54-    }
app/database/migrations/20260615.141700_0_create_auth_domain_tables.php-55-

exec
/bin/zsh -lc "rg -n \"function users|table\\('users|deleted_at|createTable\\('users\" app/database/migrations -C 4" in /Users/gian_tiaga/Code/yoga-loka-spiral-2-work-1
 succeeded in 0ms:
app/database/migrations/20260613.143901_0_create_user_domain_tables.php-11-    protected const DATABASE = null;
app/database/migrations/20260613.143901_0_create_user_domain_tables.php-12-
app/database/migrations/20260613.143901_0_create_user_domain_tables.php-13-    public function up(): void
app/database/migrations/20260613.143901_0_create_user_domain_tables.php-14-    {
app/database/migrations/20260613.143901_0_create_user_domain_tables.php:15:        $this->table('users')
app/database/migrations/20260613.143901_0_create_user_domain_tables.php-16-            ->addColumn('id', 'uuid', ['nullable' => false])
app/database/migrations/20260613.143901_0_create_user_domain_tables.php-17-            ->addColumn('name', 'string', ['length' => 100, 'nullable' => false])
app/database/migrations/20260613.143901_0_create_user_domain_tables.php-18-            ->addColumn('spiritual_name', 'string', ['length' => 100, 'nullable' => true])
app/database/migrations/20260613.143901_0_create_user_domain_tables.php-19-            ->addColumn('bio', 'text', ['nullable' => true])
--
app/database/migrations/20260613.143901_0_create_user_domain_tables.php-23-            ->addColumn('avatar_media_id', 'uuid', ['nullable' => true])
app/database/migrations/20260613.143901_0_create_user_domain_tables.php-24-            ->addColumn('verification', 'string', ['length' => 32, 'nullable' => false])
app/database/migrations/20260613.143901_0_create_user_domain_tables.php-25-            ->addColumn('status', 'string', ['length' => 32, 'nullable' => false])
app/database/migrations/20260613.143901_0_create_user_domain_tables.php-26-            ->addColumn('locale', 'string', ['length' => 8, 'nullable' => false])
app/database/migrations/20260613.143901_0_create_user_domain_tables.php:27:            ->addColumn('deleted_at', 'datetime', ['nullable' => true])
app/database/migrations/20260613.143901_0_create_user_domain_tables.php-28-            ->addColumn('created_at', 'datetime', ['nullable' => false])
app/database/migrations/20260613.143901_0_create_user_domain_tables.php-29-            ->addColumn('updated_at', 'datetime', ['nullable' => false])
app/database/migrations/20260613.143901_0_create_user_domain_tables.php-30-            ->setPrimaryKeys(['id'])
app/database/migrations/20260613.143901_0_create_user_domain_tables.php-31-            ->addIndex(['email'], ['unique' => true])
--
app/database/migrations/20260613.143901_0_create_user_domain_tables.php-81-    public function down(): void
app/database/migrations/20260613.143901_0_create_user_domain_tables.php-82-    {
app/database/migrations/20260613.143901_0_create_user_domain_tables.php-83-        $this->table('reserved_nicknames')->drop();
app/database/migrations/20260613.143901_0_create_user_domain_tables.php-84-        $this->table('user_bans')->drop();
app/database/migrations/20260613.143901_0_create_user_domain_tables.php:85:        $this->table('users')->drop();
app/database/migrations/20260613.143901_0_create_user_domain_tables.php-86-    }
app/database/migrations/20260613.143901_0_create_user_domain_tables.php-87-}
--
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-41-            ->addColumn('parent_post_id', 'uuid', ['nullable' => true])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-42-            ->addColumn('likes_count', 'integer', ['nullable' => false, 'default' => 0])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-43-            ->addColumn('reposts_count', 'integer', ['nullable' => false, 'default' => 0])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-44-            ->addColumn('comments_count', 'integer', ['nullable' => false, 'default' => 0])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php:45:            ->addColumn('deleted_at', 'datetime', ['nullable' => true])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-46-            ->addColumn('created_at', 'datetime', ['nullable' => false])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-47-            ->addColumn('updated_at', 'datetime', ['nullable' => false])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-48-            ->setPrimaryKeys(['id'])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-49-            ->addIndex(['user_id', 'id'])
--
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-200-            ->addColumn('text', 'text', ['nullable' => false])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-201-            ->addColumn('parent_comment_id', 'uuid', ['nullable' => true])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-202-            ->addColumn('likes_count', 'integer', ['nullable' => false, 'default' => 0])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-203-            ->addColumn('replies_count', 'integer', ['nullable' => false, 'default' => 0])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php:204:            ->addColumn('deleted_at', 'datetime', ['nullable' => true])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-205-            ->addColumn('deleted_by_id', 'uuid', ['nullable' => true])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-206-            ->addColumn('deletion_reason', 'string', ['length' => 500, 'nullable' => true])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-207-            ->addColumn('created_at', 'datetime', ['nullable' => false])
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php-208-            ->addColumn('updated_at', 'datetime', ['nullable' => false])

codex
Ещё один нюанс: в review-файле указан `mode: strict`, но статус всё ещё `meta-reviewed`, а не `final`; соседний CLI-файл при этом выглядит как сырая запись текущего запуска, не как законченный итог. Это не дефект бизнес-кода, но пункт ревью стоит переформулировать как процессный.
collab: Wait
codex
Первый агент подтвердил отсутствие новых плановых дефектов, но указал, что ревью разрослось в отчёт о подтверждениях. Я дождусь остальных двух проверок и затем дам итог только в формате `+`, `−`, `~`, `?`, как просили.
collab: Wait
collab: Wait
collab: CloseAgent
collab: CloseAgent
collab: CloseAgent
codex
+ Добавить как небольшой тестовый пробел: `PublishPostHttpTest` проверяет обычную рассылку после публикации черновика, но не проверяет именно “мягкий” путь, когда сохранённый получатель больше не находится к моменту публикации. Это не найденный баг, но ревью прямо утверждает это поведение для `resolveExisting`.

− Убирать как неверные кодовые выводы не нужно. По проверенным файлам `MentionRecipientResolver`, `CheckUsersExist`, `UserRepository::countByIds`, `requireAuthor()` и `notifyPostMentions` замечания ревью в целом подтверждаются.

~ Сузить фразу про soft-deleted пользователей. Код действительно одинаково видит их через `countByIds()` и `findByIds()`, но из правил и архитектуры не следует, что упоминать удалённых пользователей бизнесово правильно. Лучше писать только про техническую согласованность двух путей.

~ Смягчить “актор существует по определению”. Точнее: в обычном HTTP-сценарии актор уже прошёл авторизацию; при прямом вызове handler-а с несуществующим `authUserId` сборка профиля актора может закончиться 404.

~ Убрать из ревью длинные подтверждающие блоки “всё реализовано/проверено”. Это не проблемы ревью, а отчёт о выполненной работе. Раздел “Проблемы сверки с планом” можно оставить одной короткой фразой: проблем по плану не найдено.

~ Метаданные ревью не до конца соответствуют strict/final: в front matter `mode: strict`, но `status: meta-reviewed`, а не `final`, и соседний CLI не указан в `meta_reviewers`.

? Я не запускал `make test` и `make phpstan`; по diff можно подтвердить наличие тестов, но не факт зелёного прогона.
tokens used
121 657
+ Добавить как небольшой тестовый пробел: `PublishPostHttpTest` проверяет обычную рассылку после публикации черновика, но не проверяет именно “мягкий” путь, когда сохранённый получатель больше не находится к моменту публикации. Это не найденный баг, но ревью прямо утверждает это поведение для `resolveExisting`.

− Убирать как неверные кодовые выводы не нужно. По проверенным файлам `MentionRecipientResolver`, `CheckUsersExist`, `UserRepository::countByIds`, `requireAuthor()` и `notifyPostMentions` замечания ревью в целом подтверждаются.

~ Сузить фразу про soft-deleted пользователей. Код действительно одинаково видит их через `countByIds()` и `findByIds()`, но из правил и архитектуры не следует, что упоминать удалённых пользователей бизнесово правильно. Лучше писать только про техническую согласованность двух путей.

~ Смягчить “актор существует по определению”. Точнее: в обычном HTTP-сценарии актор уже прошёл авторизацию; при прямом вызове handler-а с несуществующим `authUserId` сборка профиля актора может закончиться 404.

~ Убрать из ревью длинные подтверждающие блоки “всё реализовано/проверено”. Это не проблемы ревью, а отчёт о выполненной работе. Раздел “Проблемы сверки с планом” можно оставить одной короткой фразой: проблем по плану не найдено.

~ Метаданные ревью не до конца соответствуют strict/final: в front matter `mode: strict`, но `status: meta-reviewed`, а не `final`, и соседний CLI не указан в `meta_reviewers`.

? Я не запускал `make test` и `make phpstan`; по diff можно подтвердить наличие тестов, но не факт зелёного прогона.
