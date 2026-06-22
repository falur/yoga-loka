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
session id: 019eec41-2eae-7893-8dfe-d0b27ebccfac
--------
user
Ты делаешь кросс-CLI мета-проверку готового ревью кода. НЕ делай первичное ревью и НЕ правь код. Прочитай целиком: docs/reviews/2026-06-22_01-01_media-video-audio-ffmpeg.md (это ревью), его target — рабочее дерево (git diff HEAD + untracked файлы модуля Media), план docs/plans/2026-06-20_21-48_media-video-audio-ffmpeg.md, docs/rules.md, docs/arch.md, docs/settings.yaml, связанный research docs/researches/2026-06-20_11-14_media-video-audio-conversions.md. Затем глубоко проверь ревью по плану, правилам, архитектуре и релевантному коду: открой файлы модуля Media (app/src/Modules/Media/**, особенно Infrastructure/FileService/*Ffmpeg*, Application/Command/*, Application/Query/*, Infrastructure/Cycle/MediaWaveformTypecast.php, Domain), миграцию, тесты в tests/**Media** — и оцени ФАКТИЧЕСКУЮ корректность замечаний ревью. Учти, что ревью уже прошло внутреннее мета-ревью и в нём 13 пунктов (план 1, план 2, замечания 1, 2, 2a, 3, 4, 5, 6, 7, 8, 9, 10, 11) + раздел 'Изменения после мета-ревью'. Дай мета-ревью на русском строго в формате: '+' какую РЕАЛЬНУЮ проблему/баг/риск/недостающий тест добавить (с файлом:строкой), '−' что убрать как неверное или недоказанное, '~' что переформулировать или где неверная ссылка/номер строки, '?' что невозможно проверить и почему. Не предлагай перечислять выполненную работу или хвалить. Будь конкретным и опирайся на фактический код.
codex
Использую скилл `eda-review-check`: он как раз про проверку уже готового ревью без правок кода. Сначала прочитаю его инструкции, затем открою ревью, план, правила, архитектуру, настройки, research и релевантный код.
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
Важное уточнение по режиму: пользователь прямо запретил править код и попросил именно выдать мета-ревью в ответе, поэтому файл ревью менять не буду. Дальше проверяю фактические замечания по коду и документам.
codex
Сейчас собираю базовый контекст: само ревью, план, правила, архитектуру, настройки и research. После этого открою diff и файлы Media, чтобы проверять не текст ревью сам по себе, а его соответствие реальному коду.
exec
/bin/zsh -lc "sed -n '1,260p' docs/plans/2026-06-20_21-48_media-video-audio-ffmpeg.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
---
title: Media — реальная нарезка видео и аудио на ffmpeg (профиль конверсии на тип)
date: 2026-06-20 21:48
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
  research: docs/researches/2026-06-20_11-14_media-video-audio-conversions.md
---

# План реализации

## Задача

Модуль `Media` умеет реально нарезать только картинки. Нужно довести его до единой модели
«профиль конверсии на тип»: на каждый `MediaType` — свой каталог профилей (enum) и свой
процессор; потребитель задаёт списки профилей на тип; видео и аудио режутся реальным ffmpeg;
результаты (включая URL и волну аудио) доступны потребителю. Документы — вне работ (остаются 422).

Готово, когда:
- аудио принимается (`audio/*` → `MediaType::Audio`), есть полный доменный стек аудио + таблица
  `media_audio_conversions`;
- видео реально транскодируется в mp4/H.264+AAC и даёт кадр-постер (как `MediaImageConversion`
  type=Poster); аудио — в m4a/AAC и даёт массив амплитуд (волну);
- `ProcessMediaHandler` ветвится исчерпывающим `match` по `MediaType`;
- ffmpeg работает с локальными файлами; их владелец и уборщик — инфраструктурный процессор;
- ошибки ffmpeg классифицируются контрактным `MediaProcessorFailedException` с `isTransient()`;
- `GetMediaUrl` отдаёт URL image/video/audio-конверсий; волна — отдельным `GetAudioWaveform`;
- `php-ffmpeg/php-ffmpeg ^1.4` в composer, бинарь ffmpeg в Docker, ffmpeg-настройки в `MediaConfig`;
- `make qa` зелёный, покрытие 100%, все новые сценарии покрыты тестами.

## Контекст

Факты из кода (проверено чтением):

- `ProcessMediaHandler` (`app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php`)
  — без `#[Transactional]`: все S3/Imagick-операции вне транзакции, затем один атомарный
  `persist(media + conversions); run()` с `markReadyMovedTo`. Идемпотентно: на `ready` — no-op;
  конверсии создаются только в финальном flush; детерминированные ключи от `storageKey`,
  unique `(media_id, type)`.
- Сейчас обрабатываются только картинки: `buildConversions()` читает оригинал в строку
  (`getObjectContents`), гоняет `MediaImageProcessorContract::resize`, льёт `putObject`, создаёт
  `MediaImageConversion`. Видео сегодня = только `copyObject` оригинала в `videos/.../source.<ext>`.
- Сообщение `MediaUploaded{mediaId, conversions: list<MediaConversionSpec>}` →
  `ProcessMediaCommand{mediaId, conversions}`. `CompleteMediaUploadHandler` кладёт его в outbox в
  одной транзакции с `markUploaded` и валидирует диапазон пикселей через `MediaPixelDimension`.
- Видео-каркас уже есть: enum `MediaVideoConversionType::NormalizedMp4H264`, сущность
  `MediaVideoConversion` (колонки + `duration_ms`, `bitrate`), `MediaVideoConversionRepository::findByMediaId`
  → `MediaVideoConversionCollection`, связь `Media::videoConversions`, таблица `media_video_conversions`.
  Нет: процессора, постера, создания конверсий, выдачи URL видео.
- Аудио нет нигде. `MediaTypeResolver` (`Application/Service/MediaTypeResolver.php`) отклоняет всё
  кроме `image/*` и `video/*` как 422. `MediaPath::assertValid` regex допускает только
  `uploads|images|videos`; `originalReady` для Audio/Document бросает; нет фабрик
  `videoConversion()`/`audioConversion()`.
- `MediaFileServiceContract` (`Application/Contract/MediaFileServiceContract.php`): только
  `getObjectContents(): string` и `putObject(string)` — файловых методов нет.
- `GetMediaUrlQuery.conversionType: MediaImageConversionType|null`; `GetMediaUrlHandler` ходит только
  в `MediaImageConversionRepository`.
- `ProcessMediaJob` (`Presentation/Job/ProcessMediaJob.php`): признак временной ошибки = только
  `MediaFileServiceFailedException::isTransient()`; ошибки процессора станут терминальными.
- VO (`Domain/ValueObject`): `MediaPixelDimension` (1..100000, `supports()`), `MediaDuration`
  (1..604_800_000 мс), `MediaBitrate` (1..1_000_000_000), `MediaFileSize`, `MediaMimeType`,
  `MediaProcessingError` (null-object `none()`). Нет `MediaSampleRate`, `MediaWaveform`,
  `MediaWaveformPeakCount`, `MediaAudioConversionId`, `MediaAudioConversionType`,
  `MediaAudioConversion`, `MediaAudioConversionCollection`, `MediaAudioConversionRepository`.
- `MediaConfig` (`app/src/Shared/Infrastructure/Configuration/Media/MediaConfig.php`):
  `stagingTtlSeconds, multipartThresholdBytes, multipartPartSizeBytes, imageProcessingDriver`;
  `configName()='media'`; `app/config/media.php` через `env()`; маппинг покрыт `MediaConfigTest`.
- Docker (`docker/Dockerfile`, `ubuntu:26.04`) ставит php8.5 + `imagick`/`gd`, бинаря ffmpeg нет, в
  блоке самопроверки `php -m | grep ...`. `composer.json`: `intervention/image: ^4`, php-ffmpeg нет.
- Тесты: suites `Unit/Kernel/Feature` (`phpunit.xml`). Дублёры: `tests/.../Fixture/FakeS3Client`,
  `FakeS3ClientProvider`, `tests/.../Flow/Fixture/RecordingMediaLogger`,
  `ThrowingProcessMediaCommandBus`. База `MediaApplicationTestCase` (`createMedia`, `conversionSpec`,
  доступ к репозиториям). `ProcessMediaHandlerTest` мокает контракты; `ProcessMediaJobTest`
  проверяет классификацию ошибок и логирование уровней.
- `Media` (`Domain/Entity/Media.php`): `recordTemporaryProcessingError`/`recordPermanentProcessingError`,
  `markReadyMovedTo` (Ready→no-op; допускает переход из Uploaded/Processing/ProcessingFailed),
  `markReady`, `isReady`; связи `imageConversions`, `videoConversions` (нужна `audioConversions`).
- `php-ffmpeg/php-ffmpeg` 1.4.0 (релиз 2026-01-19), поддержка PHP 8.5, требует бинарей
  `ffmpeg`/`ffprobe`. Источник — Packagist; версия подтверждена в research и кросс-ревью Codex.

## Принятые решения

1. **Профиль конверсии на тип**: на каждый `MediaType` — свой enum профилей и свой процессор-контракт;
   потребитель задаёт списки профилей на тип в едином `MediaConversionPlan`. Объединять в один спек
   нельзя — у аудио нет width/height, общий объект потянул бы nullable-примитивы (нарушение rules.md
   «Явные типы вместо null»). *Источник: ответ пользователя (research, развилки 1 и 2).*
2. **Инструмент и среда**: `php-ffmpeg/php-ffmpeg ^1.4` в текущем пути outbox→RabbitMQ-Job (без
   Temporal). *Источник: ответ пользователя (research, развилка 3); версия подтверждена Packagist.*
3. **Видео**: транскод mp4/H.264+AAC + кадр-постер как `MediaImageConversion` type=Poster; размер
   постера = размер транскода; отдельный картиночный профиль для видео потребитель не передаёт
   (картиночные профили для видео → 422). *Источник: ответ пользователя (research, развилка 4а).*
4. **Аудио**: транскод m4a/AAC + волна амплитуд (массив малых целых для прогресса воспроизведения «как
   в Telegram», не картинка). *Источник: ответ пользователя (research, развилка 4б).*
5. **БД**: отдельная таблица на тип конверсии (как существующие `media_image_conversions`,
   `media_video_conversions`); новая `media_audio_conversions` со своими полями; волна — JSON-колонка
   `waveform` на строке аудио-конверсии. Общая таблица отклонена: дала бы массу пустых NULL-колонок
   (нарушение «Явные типы вместо null»). *Источник: ответ пользователя в текущем сообщении.*
6. **Размер плана**: normal. *Источник: ответ пользователя.*
7. **Контракт процессора — крупный**: `MediaVideoProcessorContract::process(...)` /
   `MediaAudioProcessorContract::process(...)` возвращают только метаданные результата. Скачивание
   оригинала, ffmpeg (probe/транскод/постер/волна), загрузка результатов и удаление временных файлов в
   `finally` — внутри Infrastructure-реализации; Application-Handler локальных путей не видит. Это
   закрывает внутреннее противоречие research (granular-сигнатуры с локальными путями vs «процессор
   владеет временными файлами и чистит их в finally») в пользу формулировки research про владельца
   очистки. *Источник: autonomous (decision_mode recommend_and_ask, внутренний технический контракт;
   обоснование — rules.md «handler не управляет путями», единый владелец и очистка).*
8. **Смена содержимого `MediaUploaded`** (`conversions` → `plan: MediaConversionPlan`) — несовместимое
   изменение. Сквозного потребителя в рабочей среде нет: HTTP-входа Media не существует, `Posts`
   привязывает уже загруженные медиа по id (`PostContentComposer.php`). Предусловие выката: слить
   очередь RabbitMQ и outbox. *Источник: research, подтверждено чтением кода (у модуля Media нет
   HTTP-контроллеров).*

## Целевой алгоритм

**Подтверждение загрузки (`CompleteMediaUpload`).** Принимает `MediaConversionPlan` (три списка:
image/video/audio). Валидирует **только** список, относящийся к `media.type`; список «не своего» типа
непуст → `ValidationException` 422 (видео-файл со списком картинок → 422, и наоборот). Для video и audio
требуется ровно один профиль (`NormalizedMp4H264` / `NormalizedAacM4a`): пустой список или больше одного
→ 422. Дубли типов в любом списке → 422 (иначе падение по unique `(media_id, type)` уже после S3-записей).
Диапазоны: image
и video — `MediaPixelDimension::supports(width/height)`; video — `MediaBitrate::supports(videoBitrate,
audioBitrate)`; audio — `MediaBitrate::supports(bitrate)`, `MediaSampleRate::supports(sampleRate)`,
`MediaWaveformPeakCount::supports(waveformPeaks)`. Кладёт `MediaUploaded{mediaId, plan}` в outbox в одной
транзакции с `markUploaded`.

**Обработка (`ProcessMediaJob` → `ProcessMediaCommand{mediaId, plan}` → `ProcessMediaHandler`).**
Идемпотентность: `media.isReady()` → no-op. Затем исчерпывающий `match (media.type)`:
- `Image` — как сейчас: `getObjectContents` → `imageProcessor.resize` → `putObject`; конверсии из
  `plan.image`; оригинал `copyObject` → `images/.../source.<ext>`.
- `Video` — `videoProcessor.process(sourceStorage, sourcePath, spec, targetStorage, normalizedPath,
  posterPath)` → метаданные (нормализованное видео + постер). Создаёт `MediaVideoConversion`
  (`NormalizedMp4H264`) и `MediaImageConversion` (`Poster`, размер = размер транскода). Оригинал
  `copyObject` → `videos/.../source.<ext>`.
- `Audio` — `audioProcessor.process(sourceStorage, sourcePath, spec, targetStorage, normalizedPath)` →
  метаданные (нормализованное аудио + `MediaWaveform`). Создаёт `MediaAudioConversion`
  (`NormalizedAacM4a`) с волной. Оригинал `copyObject` → `audios/.../source.<ext>`.
- `Document` — недостижимо: `MediaTypeResolver` отклоняет 422 на приёме (ветка `match` бросает
  `InvalidDomainValueException` как защита инварианта).

Финал — атомарный `persist(media + все конверсии); run()` + `markReadyMovedTo`. Внутри процессора:
`downloadToFile(оригинал)` → ffmpeg → `uploadFromFile(результаты)` → `finally` удаляет вход и все
выходы при любом исходе (успех, ошибка ffmpeg, сбой загрузки).

**Выдача URL (`GetMediaUrl`).** `conversionType:
MediaImageConversionType|MediaVideoConversionType|MediaAudioConversionType|null`. По классу enum —
исчерпывающий `match`, выбор нужного репозитория конверсий, дальше та же логика `publicUrl`/`presignGet`.
Без `conversionType` — оригинал. Волна — не через URL.

**Волна (`GetAudioWaveform`).** `GetAudioWaveformQuery{mediaId}` → handler читает нормализованную
`MediaAudioConversion` через `MediaAudioConversionRepository`, возвращает её `MediaWaveform` (числа
встроенно). Медиа не ready / не аудио / нет конверсии → `NotFoundException`.

**Ошибки ffmpeg.** Процессоры Infrastructure оборачивают исключения php-ffmpeg в
`MediaProcessorFailedException` (`Application/Exception`) с `isTransient()`: битый/неподдерживаемый
вход, нештатный код выхода ffmpeg на валидном вызове → постоянная (terminal, ERROR); таймаут
транскодирования, нехватка ресурсов процесса → временная (`RetryException`, WARN). `ProcessMediaJob`
дополняет признак временной ошибки веткой `|| (MediaProcessorFailedException && isTransient())`;
`safeMessage()` не меняется (уже покрывает новый тип), а текст `RetryException.reason` выбирается по типу
ошибки (для процессора — сообщение о повторе обработки, не «ошибка хранилища»). Текст — из безопасного
набора, сырой вывод ffmpeg наружу не уходит (`MediaProcessingError` отклоняет пути/`etag`).

## Контракты реализации

### Данные и БД

Меняется: добавляется таблица `media_audio_conversions`. Существующие `media`,
`media_image_conversions`, `media_video_conversions` не меняются.

Новая миграция `app/database/migrations/{timestamp}_0_create_media_audio_conversions_table.php` в стиле
`20260521.184100_0_create_media_domain_tables.php`:

```text
media_audio_conversions
  id           uuid          NOT NULL  PK
  media_id     uuid          NOT NULL  FK -> media(id) ON DELETE CASCADE ON UPDATE CASCADE (indexCreate=false)
  type         string(64)    NOT NULL
  status       string(32)    NOT NULL
  storage      string(64)    NOT NULL
  path         string(1024)  NOT NULL
  mime_type    string(255)   NOT NULL
  size         bigInteger    NOT NULL
  duration_ms  bigInteger    NOT NULL
  bitrate      integer       NOT NULL
  sample_rate  integer       NOT NULL
  waveform     json          NOT NULL
  created_at   datetime      NOT NULL
  updated_at   datetime      NOT NULL
  unique (media_id, type); index (media_id); index (status)
down(): drop media_audio_conversions
```

Совместимость: таблица новая, аудио-строк ещё нет, backfill не нужен; rollback = `drop`.

### API и внешние контракты

Публичных HTTP-роутов/webhooks у модуля Media нет — меняются **внутренние Application-контракты**
(CQRS DTO, outbox-сообщение, контракты процессоров и файлового сервиса). Внешних API/очередей третьих
сторон задача не затрагивает.

Спеки профилей (`Application/Dto`, публичные `readonly`, примитив-дружественные для Valinor):
```text
MediaImageConversionSpec{ MediaImageConversionType type, int width, int height }   // = переименование MediaConversionSpec
MediaVideoConversionSpec{ MediaVideoConversionType type, int width, int height, int videoBitrate, int audioBitrate }
MediaAudioConversionSpec{ MediaAudioConversionType type, int bitrate, int sampleRate, int waveformPeaks }
MediaConversionPlan{ list<MediaImageConversionSpec> image, list<MediaVideoConversionSpec> video, list<MediaAudioConversionSpec> audio }
```

Все три списка `MediaConversionPlan` несут точные PHPDoc-типы `@param/@var list<...Spec>` — это условие
восстановления вложенного DTO через Valinor (`ValinorOutboxMessageSerializer`); покрыть round-trip тестом.

Сообщение и команды: `MediaUploaded.conversions` → `plan: MediaConversionPlan`;
`ProcessMediaCommand.conversions` → `plan`; `CompleteMediaUploadCommand.conversions` → `plan`.

Контракты процессоров (`Application/Contract`):
```text
MediaVideoProcessorContract::process(
  MediaStorage sourceStorage, MediaPath sourcePath, MediaVideoConversionSpec spec,
  MediaStorage targetStorage, MediaPath normalizedPath, MediaPath posterPath
): MediaVideoProcessingResult
MediaAudioProcessorContract::process(
  MediaStorage sourceStorage, MediaPath sourcePath, MediaAudioConversionSpec spec,
  MediaStorage targetStorage, MediaPath normalizedPath
): MediaAudioProcessingResult
```

Целевые `MediaPath` (`normalizedPath`, `posterPath`) строит **Handler (Application)** через доменные
фабрики `MediaPath::videoConversion`/`audioConversion`/`imageConversion(Poster)` ДО вызова `process()`
и передаёт готовыми; Infrastructure-процессор доменные пути не строит (граница arch.md: Infrastructure
→ только Contract + runtime). Процессоры зависят от `MediaFileServiceContract` (download/upload) и
`MediaConfig` (бинарь/таймаут/threads) — autowire в `MediaBootloader`.

Result-DTO (`Application/Dto`, несут доменные VO — потребляются handler-ом сразу, не сериализуются;
как существующий `MediaConversionResult`):
```text
MediaVideoProcessingResult{
  MediaMimeType normalizedMimeType, MediaFileSize normalizedSize,
  MediaPixelDimension width, MediaPixelDimension height, MediaDuration duration, MediaBitrate bitrate,
  MediaMimeType posterMimeType, MediaFileSize posterSize   // пиксельный размер постера = width/height транскода (решение 3), отдельных полей нет
}
MediaAudioProcessingResult{
  MediaMimeType normalizedMimeType, MediaFileSize normalizedSize,
  MediaDuration duration, MediaBitrate bitrate, MediaSampleRate sampleRate, MediaWaveform waveform
}
```

Файловый сервис (`MediaFileServiceContract` + `S3MediaFileService`): добавить файловые методы (стиль
контракта уже строковый — `getObjectContents`/`putObject` работают со строками, локальный путь —
такой же технический скаляр):
```text
downloadToFile(MediaStorage storage, MediaPath path): string            // локальный временный путь, владелец-вызыватель (процессор) чистит
uploadFromFile(MediaStorage storage, MediaPath path, string localFile, MediaMimeType mimeType): void
```

exec
/bin/zsh -lc "sed -n '1,220p' docs/settings.yaml" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
version: 1

defaults:
  # Включает strict-режим по умолчанию для eda-explore, eda-plan и eda-review.
  # true | false
  strict: true
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
/bin/zsh -lc "sed -n '1,260p' docs/researches/2026-06-20_11-14_media-video-audio-conversions.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
---
title: Media — обработка картинок, видео и аудио (как режем каждый тип)
date: 2026-06-20 11:14
mode: strict
decision_mode: recommend_and_ask
status: reviewed
reviewer: codex
sources:
  rules: docs/rules.md
  arch: docs/arch.md
---

# Media — обработка картинок, видео и аудио (как режем каждый тип)

## Суть

Модуль `Media` должен уметь работать с картинками, видео и аудио (документы — вне работ по
прямому указанию). Картинки уже полностью обрабатываются: проверяем, как именно потребитель
управляет ими сейчас, и распространяем тот же подход на видео и аудио так, чтобы модуль мог
явно «сказать», как он режет каждый тип. Под «режет» понимается набор конверсий: для картинки —
ресайз и кроп; для видео — транскодирование в нормализованный формат и кадр-постер; для аудио —
транскодирование в нормализованный формат и волна амплитуд для показа прогресса воспроизведения
(как в Telegram).

Проблема: текущая модель конверсий заточена под картинку и в исполнении работает только для
неё. Для видео доменный каркас уже есть, но реальной нарезки нет (выполняется только серверное
копирование оригинала); аудио не принимается вообще. Нужна единая модель «профиль конверсии на
тип» плюс реальные процессоры видео и аудио на ffmpeg, а также способ отдать потребителю
результаты этих конверсий.

## Решение

### Как картинки контролируются из других модулей сейчас (проверка)

У потребителя две точки контроля, обе — параметрами вызова, ничего не зашито в общий конфиг:

```text
RequestMediaUpload(userId, MediaUploadSpec, MediaFileMeta)
  MediaUploadSpec{ allowedMimeTypes, maxSize, visibility, presignedTtl }
  -> потребитель решает: какие MIME и размер разрешены под этот случай.

CompleteMediaUpload(userId, mediaId, list<MediaConversionSpec>, parts?)
  MediaConversionSpec{ type: MediaImageConversionType, width:int, height:int }
  -> потребитель решает: какие профили картинки сгенерировать и в каком размере.
```

Источники: `MediaUploadSpec` и проверка — `RequestMediaUploadHandler.php:125-137`; список конверсий
и его проверка диапазона пикселей (1..100000) — `CompleteMediaUploadHandler.php:55,107-121`; список
доезжает до обработки через сообщение `MediaUploaded` (`MediaUploaded.php:21-24`) →
`ProcessMediaCommand.php:14-17`. Модуль «говорит, как режет» картинку через перечисление
`MediaImageConversionType` (thumbnail/preview/large/poster — `MediaImageConversionType.php:9-12`) и
контракт `MediaImageProcessorContract::resize` (cover-crop до width×height, формат = исходный mime —
`MediaImageProcessorContract.php:19-24`, `ProcessMediaHandler.php:104-138`).

Вывод: потребитель полностью управляет нарезкой картинки, а модуль декларирует доступные профили и
семантику ресайза. Эту же двойную поверхность (политика приёма + список профилей) переносим на видео
и аудио, плюс закрываем недостающую часть — получение URL результатов всех типов.

### Что уже есть, а что добавляем

```text
Image  ГОТОВО ПОЛНОСТЬЮ: enum, сущность, репозиторий, коллекция, процессор (Imagick),
       приём (MediaTypeResolver image/*), путь (images/), создание конверсий, выдача URL.

Video  ЕСТЬ КАРКАС, НЕТ НАРЕЗКИ: enum MediaVideoConversionType{NormalizedMp4H264},
       сущность MediaVideoConversion, MediaVideoConversionRepository, коллекция,
       связь Media::videoConversions, таблица media_video_conversions, приём video/*,
       путь videos/ для оригинала. НЕТ: процессора (ffmpeg), создания конверсий в
       ProcessMediaHandler, фабрики пути videoConversion(), постера, выдачи URL видео-конверсий.

Audio  НЕТ НИЧЕГО: ни приёма (MediaTypeResolver отклоняет audio/* как 422 —
       MediaTypeResolver.php:30-34), ни префикса пути audios/ (MediaPath.php:120),
       ни enum/сущности/репозитория/коллекции/процессора/волны.

Document  ВНЕ РАБОТ по указанию пользователя: остаётся 422, MediaPath для Document бросает.
```

### Целевая модель: конверсия на тип (как режем каждый тип)

Единый принцип: на каждый `MediaType` — каталог профилей (enum) и свой процессор (контракт),
который делает реальную нарезку; потребитель управляет списком профилей на тип.

```text
Тип     Профили (enum)                 Процессор (контракт)           Инструмент
----    --------------                 --------------------           ----------
Image   MediaImageConversionType       MediaImageProcessorContract    Imagick (Intervention v4)
        thumbnail/preview/large/poster resize: cover-crop W×H

Video   MediaVideoConversionType       MediaVideoProcessorContract    ffmpeg (php-ffmpeg) [новый]
        normalizedMp4H264              transcode + posterFrame
        (+ постер -> image-конверсия)

Audio   MediaAudioConversionType       MediaAudioProcessorContract    ffmpeg (php-ffmpeg) [новый]
        normalizedAacM4a               transcode + extractWaveform
        (+ волна амплитуд)
```

Профили (перечисления) на тип со своими параметрами (решение развилки 2 — отдельные на тип):
объединять в один `MediaConversionSpec` нельзя, потому что у аудио нет width/height, а у видео есть
битрейт и кодек; общий объект потянул бы nullable-примитивы и слишком широкие типы — прямое
нарушение `rules.md` («Явные типы вместо null», «Entity без примитивов», «Не заменять типизацию
ручными проверками»).

### Профили-объекты (примитив-дружественные, для сериализации сообщения)

Все профили — публичные `readonly`-DTO из примитивов и enum, чтобы `ValinorOutboxMessageSerializer`
восстанавливал их без приватных фабрик VO (так же, как нынешний `MediaConversionSpec` —
`MediaConversionSpec.php:9-22`):

```text
MediaImageConversionSpec{ type: MediaImageConversionType, width:int, height:int }   // = нынешний, переименовать
MediaVideoConversionSpec{ type: MediaVideoConversionType, width:int, height:int, videoBitrate:int, audioBitrate:int }
MediaAudioConversionSpec{ type: MediaAudioConversionType, bitrate:int, sampleRate:int, waveformPeaks:int }
```

Контейнер плана вместо голого `list<MediaConversionSpec>`:

```text
MediaConversionPlan{ image: list<...ImageSpec>, video: list<...VideoSpec>, audio: list<...AudioSpec> }
```

`MediaUploaded` и `ProcessMediaCommand` несут `MediaConversionPlan` (три примитивных списка) вместо
одного списка картинок. Это и есть точка, где модель «как режем» становится общей для трёх типов;
содержимое сообщения остаётся пригодным для Valinor (тот же контракт, что сегодня). В
`CompleteMediaUpload` проверяется только список, относящийся к типу медиа: видео-файл со списком
картинок → 422.

### Профили: точные форматы, расширения и режимы нарезки

Нормализация меняет контейнер и кодек, поэтому путь конверсии берёт расширение из профиля, а не из
расширения оригинала (в отличие от картинок, где формат сохраняется). Каждый профиль декларирует
своё расширение и MIME:

```text
normalizedMp4H264  -> контейнер mp4, видео H.264 + звук AAC, MIME video/mp4, расширение .mp4
normalizedAacM4a   -> контейнер mp4 (m4a), звук AAC,          MIME audio/mp4, расширение .m4a
постер видео       -> кадр JPEG/WebP, хранится как MediaImageConversion type=Poster
волна аудио        -> массив амплитуд (не файл), см. ниже
```

Режим нарезки видео (закрытие замечания о crop/contain/padding/повороте): вписываем кадр в рамку
W×H **с сохранением пропорций, без обрезки**, выравниваем стороны до чётных (H.264 требует чётные
размеры), применяем поворот по метаданным и очищаем флаг поворота. Кроп не используем, чтобы не
терять содержимое кадра; рамку-паддинг не добавляем, чтобы не зашивать цвет фона.

### Процессоры: сигнатуры и результаты

Контракты в `Application/Contract`, реализации на php-ffmpeg в `Infrastructure/FileService`. Вход —
локальный путь к файлу (не строка в памяти, см. ниже). Результат несёт фактические метаданные,
которые требуют конструкторы доменных сущностей конверсий.

```text
interface MediaVideoProcessorContract {
    transcode(string $sourceFile, MediaVideoConversionSpec $spec): MediaVideoConversionResult
    extractPoster(string $sourceFile, MediaPixelDimension $width, MediaPixelDimension $height): MediaConversionResult
    probe(string $sourceFile): MediaVideoProbe   // ffprobe: width,height,duration,bitrate
}
interface MediaAudioProcessorContract {
    transcode(string $sourceFile, MediaAudioConversionSpec $spec): MediaAudioConversionResult
    extractWaveform(string $sourceFile, int $peaks): MediaWaveform
    probe(string $sourceFile): MediaAudioProbe   // ffprobe: duration,bitrate,sampleRate
}

MediaVideoConversionResult{ localFile, mimeType, size, width, height, duration, bitrate }
MediaAudioConversionResult{ localFile, mimeType, size, duration, bitrate, sampleRate }
```

`extractPoster` возвращает тот же `MediaConversionResult`, что и картиночный процессор, поэтому кадр
кладётся как `MediaImageConversion` без отдельной сущности. Размер постера берётся от транскода видео
(решение развилки 4а): потребитель не передаёт картиночный профиль для видео, правило «картиночные
профили для видео → 422» остаётся в силе; постер — фиксированный побочный выход видео-профиля.

### Волна аудио «как в Telegram»

Решение развилки 4б: аудио в этой итерации — обязательный нормализованный транскод плюс **волна
амплитуд для показа прогресса воспроизведения**. «Как в Telegram» — это не картинка, а компактный
массив амплитуд (например, ~100 значений), по которому клиент сам рисует полоски и заполняет их по
ходу проигрывания. Статичный PNG этого не даёт, поэтому именно массив, а не `showwavespic`-картинка.

```text
MediaWaveform — value object: список малых целых (амплитуды), JsonSerializable.
Построение: ffmpeg декодирует оригинал в моно-PCM низкой частоты -> делим на N интервалов ->
            пик амплитуды каждого -> нормализуем в 0..максимум. N = waveformPeaks из профиля.
Хранение:   JSON-колонка waveform на строке нормализованной аудио-конверсии (MediaAudioConversion),
            через JSON-typecast (образец MediaMultipartPartCollectionTypecast).
Выдача:     встроенно числами (клиенту нужны сами значения для отрисовки), отдельным запросом
            GetAudioWaveform(mediaId) -> MediaWaveform; presigned-URL тут не подходит.
```

Компромисс: волна логически — характеристика исходного аудио, но привязка к строке нормализованной
конверсии приемлема, потому что та всегда создаётся для аудио; отдельная сущность под один список
была бы лишней.

### Получение URL результатов всех типов (закрытие пробела)

Сейчас выдача URL умеет только картинки: `GetMediaUrlQuery.conversionType` имеет тип
`MediaImageConversionType|null` (`GetMediaUrlQuery.php:14`), а `GetMediaUrlHandler` ходит только в
`MediaImageConversionRepository` (`GetMediaUrlHandler.php:24,57-62`). Значит результаты видео- и
аудио-конверсий потребителю недостижимы. Расширяем:

```text
GetMediaUrlQuery.conversionType: MediaImageConversionType|MediaVideoConversionType|MediaAudioConversionType|null
GetMediaUrlHandler: по классу enum выбирает нужный репозиторий конверсий (исчерпывающий match),
                    дальше та же логика public/presignGet, что и сейчас.
Волна отдаётся не через URL, а запросом GetAudioWaveform (числа встроенно).
```

Так модуль «говорит, как нарезал», и одновременно даёт ссылку на каждый нарезанный объект.

### Ветвление обработки по типу

`ProcessMediaHandler` (`ProcessMediaHandler.php:41-84`) получает исчерпывающий `match` по
`MediaType` (домен уже знает все 4 значения — `MediaType.php:9-12`):

```text
match (media.type):
  Image    -> картиночный процессор (Imagick); оригинал copyObject -> images/.../source.<ext>
  Video    -> probe(ffprobe) -> транскоды (ffmpeg) + кадр-постер; оригинал -> videos/.../source.<ext>
  Audio    -> probe -> транскоды (ffmpeg) + волна; оригинал -> audios/.../source.<ext>
  Document -> вне работ (MediaTypeResolver отклоняет 422)
```

Идемпотентность сохраняется по образцу картинок: детерминированные ключи от `storageKey`, конверсии
создаются только в финальном `persist+run()`, на статусе `ready` — повторный вызов ничего не делает
(`ProcessMediaHandler.php:46-52`, README модуля «Транзакционная дисциплина S3»).

### Файловый сервис: ffmpeg работает с файлами, не со строкой в памяти

Текущий контракт читает оригинал целиком в строку (`getObjectContents(): string`) и пишет строку
(`putObject(string $contents)` — `MediaFileServiceContract.php:85,90-95`). Для ffmpeg/ffprobe нужен
локальный путь к файлу, а видео может быть большим — держать его строкой в памяти неприемлемо.
Добавляем файловые методы и используем их только для видео и аудио (картинка остаётся на чтении в
память):

```text
downloadToFile(storage, path): string                       // вернуть локальный временный путь
uploadFromFile(storage, path, localFile, mimeType): void
```

Ответственность за временные файлы (закрытие замечания о владельце и очистке): владелец временных
путей — инфраструктурный ffmpeg-процессор. Он сам через `downloadToFile` получает входной файл,
создаёт выходные временные файлы и в блоке `finally` удаляет и входной, и выходные при любом исходе
(успех, ошибка ffmpeg, сбой загрузки результата). Application-Handler локальных путей не видит и за
очистку не отвечает.

### Классификация ошибок ffmpeg

Сейчас `ProcessMediaJob` считает повторяемой только ошибку `MediaFileServiceFailedException` и читает
её признак `isTransient()` (`ProcessMediaJob.php:57`). Ошибка ffmpeg-процессора в эту ветку не
попадёт и всегда станет терминальной. Вводим контрактное исключение процессора:

```text
MediaProcessorFailedException (App\Modules\Media\Application\Exception) с isTransient():
  битый/неподдерживаемый вход, нештатный код выхода ffmpeg на валидном вызове -> постоянная (terminal, ERROR)
  таймаут транскодирования, нехватка ресурсов процесса                        -> временная (RetryException, WARN)
Реализации процессоров в Infrastructure оборачивают исключения php-ffmpeg в этот тип.
ProcessMediaJob расширяет проверку: признак временной ошибки = MediaFileServiceFailedException
  ИЛИ MediaProcessorFailedException с isTransient().
```


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
/bin/zsh -lc "sed -n '1,260p' docs/arch.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc "sed -n '1,260p' docs/reviews/2026-06-22_01-01_media-video-audio-ffmpeg.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
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

 succeeded in 0ms:
---
title: Media — реальная нарезка видео и аудио на ffmpeg
date: 2026-06-22 01:01
target: git diff HEAD + untracked (рабочее дерево)
plan: docs/plans/2026-06-20_21-48_media-video-audio-ffmpeg.md
mode: strict
score: 84
status: meta-reviewed
meta_reviewers: [plan-check (sonnet), architecture-check (sonnet), rules-check (sonnet), quality-check (sonnet)]
---

# Ревью: Media — реальная нарезка видео и аудио на ffmpeg

## Оценка

**84/100.** Реализация плотно следует плану: появился полный аудио-доменный стек, таблица
`media_audio_conversions`, реальные ffmpeg-процессоры видео и аудио, ветвление обработки по типу,
выдача URL для трёх типов и отдельная волна. Код аккуратный, в основном соответствует правилам (VO,
enum, исчерпывающие `match`, ранние возвраты, typecast-слой). Снижение оценки — за один реальный риск
утечки временного файла при сбое скачивания, несколько нарушений правил (голый `\RuntimeException` в
guard-clause процессоров, англицизмы в русских докблоках/README, `handle()` длиннее лимита), отклонение
от стратегии логирования плана, расхождение CQRS-контракта Query Handler с конвенцией возврата и пару
мест, которые стоит перепроверить вручную (внешнее поведение библиотеки, граничные случаи арифметики
волны).

## Проблемы сверки с планом

### 1. Поведение «решателя» временной ошибки уже по плана

План (фаза 3, раздел «Ошибки ffmpeg») перечисляет как временные (ретраябельные) ошибки не только
таймаут транскодирования, но и «нехватку ресурсов процесса». Это же повторено в докблоке нового
исключения. Фактически реализованный классификатор считает временной ошибкой исключительно таймаут —
любая нехватка ресурсов, нештатное завершение процесса по нехватке памяти и т.п. попадут в «постоянную»
ветку и уедут в `failed` без повтора.

Само по себе это может быть осознанным сужением (надёжно отличить «временную нехватку ресурсов» от
«битого входа» по исключению php-ffmpeg тяжело). Но тогда расхождение стоит зафиксировать: либо
поправить формулировку в докблоке `MediaProcessorFailedException`, чтобы она не обещала поведение,
которого нет, либо явно расширить классификатор. Сейчас докблок и код расходятся, и при эксплуатации
это введёт в заблуждение. (Уточнение мета-ревью: README такого обещания НЕ содержит — его текст
описывает только «таймаут транскодирования → временная», поэтому README в правке не нуждается; проблема
строго в докблоке `MediaProcessorFailedException.php:28`.)

Технические детали:

- **Статус:** отклонение
- **Где:** `app/src/Modules/Media/Infrastructure/FileService/AbstractFfmpegMediaProcessor.php:82` —
  `isTransientFailure()` ловит только `ProcessTimedOutException`; докблок
  `app/src/Modules/Media/Application/Exception/MediaProcessorFailedException.php:28` обещает
  «нехватку ресурсов процесса» как временную.
- **Что подтверждает проблему:** цикл по `getPrevious()` возвращает `true` только для
  `ProcessTimedOutException`; ветки для OOM/ресурсов нет.
- **Как исправить:** выбрать одно из двух — либо привести текст докблока к фактическому поведению
  («временная только при таймауте»), либо добавить распознавание сигнала нехватки ресурсов и юнит-тест
  на него. Минимально достаточно правки текста докблока.

### 2. README описывает дубли типов только для image, но в коде защита есть только у image

В README сказано: «дубли типов в списке image → 422». Для video и audio дубли невозможны, потому что
там требуется ровно один профиль, так что отдельная проверка дублей им не нужна — это корректно. Но в
плане (раздел «Целевой алгоритм») написано «Дубли типов в любом списке → 422». Формулировка README
точнее отражает реализацию, чем план; это не баг, а лишь напоминание перепроверить, что именно такого
поведения и хотели: для image дубли действительно отлавливаются до S3-записей, для остальных типов —
через ограничение «ровно один».

Технические детали:

- **Статус:** не проверялось (на усмотрение)
- **Где:** `app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php:139`
  (`assertImagePlan`, строки 130–148 — единственная проверка `unique`), README таблица плана.
- **Что подтверждает проблему:** `assertVideoPlan`/`assertAudioPlan` не проверяют дубли, а гарантируют
  единственный элемент через `count() !== 1`.
- **Как исправить:** ничего в коде менять не нужно; при желании синхронизировать формулировку плана с
  README.

## Замечания

### 1. Временный файл оригинала может остаться на диске при сбое скачивания

Когда видео/аудио процессор скачивает оригинал, частичный файл может остаться внутри самого
`downloadToFile` при сбое скачивания. Механика (уточнена мета-ревью): процессор вызывает
`downloadToFile` ПЕРВОЙ строкой, ещё до входа в свой `try`. Если скачивание упадёт (штатный сценарий —
объект конкурентно удалили или кратковременный сбой сети), S3-клиент уже мог записать частичный файл на
диск через `SaveAs`. `downloadToFile` бросает исключение, не удалив этот файл, и путь наружу не
возвращается. При этом переменная `$sourceFile` в процессоре не присваивается (оператор `=` не
завершился), поэтому `finally` процессора к ней обратиться не может в принципе — то есть очистить
частичный файл некому ни в процессоре, ни в `downloadToFile`. Файл останется в системном temp-каталоге
навсегда. Источник проблемы и место исправления — строго внутри `downloadToFile`.

На горячем пути обработки медиа это медленно, но верно засоряет диск worker-а временными файлами,
особенно при всплесках сетевых ошибок S3. Риск проявится не сразу, а как постепенное заполнение
`/tmp` под нагрузкой с ретраями.

Технические детали:

- **Тип:** `bug`
- **Рекомендация:** на усмотрение автора
- **Где:** `app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:216`
  (метод `downloadToFile`, тело `try/catch` ниже): создаёт `$localFile`, при `AwsException` бросает, не
  удаляя файл. Процессоры (`FfmpegMediaVideoProcessor.php:44`, `FfmpegMediaAudioProcessor.php:45`)
  вызывают `downloadToFile` до своего `try`, поэтому их `finally` тут не помогает.
- **Что подтверждает проблему:** `getObject(['SaveAs' => $localFile])` может создать частичный файл до
  ошибки; в `catch (AwsException)` нет очистки `$localFile`, а процессор получит исключение до того, как
  узнает путь.
- **Как исправить:** в `downloadToFile` обернуть скачивание в `try { ... } catch (AwsException) { удалить
  частичный файл, если создан; перебросить }` — это законная очистка ресурса на границе Infrastructure
  (rules.md разрешает try-catch в инфраструктуре, если он реально освобождает ресурс). Без выноса наружу
  нового типа исключения.
- **Тесты:** Feature/Infrastructure против MinIO — скачивание несуществующего объекта не оставляет файла
  в temp-каталоге (снять snapshot списка temp-файлов до/после).

### 2. Прямые вызовы ffmpeg через Process не покрыты юнит-тестами и зависят от деталей библиотеки

Извлечение постера видео и волны аудио сделано прямыми вызовами бинаря ffmpeg через
`Symfony\Component\Process\Process` внутри `runEncoding()`. Юнит-тесты подменяют весь `runEncoding()`
целиком, поэтому эти куски проверяются только интеграционным тестом в Docker. Это допустимо по плану, но
стоит держать в голове: логика разбора PCM (перевод беззнаковых сэмплов в знаковые, нормализация в
0..255, деление на интервалы) — нетривиальная и сейчас не имеет отдельного быстрого теста; её корректность
держится на одном интеграционном прогоне с сигналом 440 Гц.

Отдельный граничный риск той же арифметики (отмечен мета-ревью): при `$sampleCount < $peaks` часть
интервалов получает `$start === $end`, внутренний цикл не выполняется ни разу, и `$maxAmplitude`
остаётся 0 — пустые интервалы молча дают амплитуду 0. На сигнале 440 Гц (`WAVEFORM_SAMPLE_RATE = 8000`,
64 пика) `sampleCount` всегда много больше `peaks`, поэтому проблема не проявляется; но для очень
короткого аудио или большого `waveformPeaks` результат будет тихо некорректным. Это скрытое предусловие
`$sampleCount >= $peaks` без guard и без комментария.

Технические детали:

- **Тип:** `tests`
- **Рекомендация:** на усмотрение автора
- **Где:** `app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaAudioProcessor.php:137`
  (`buildWaveform` — разбор PCM); `:152–167` (деление на интервалы, незащищённое от `$peaks > $sampleCount`);
  `FfmpegMediaVideoProcessor.php:144` (`extractPoster` прямым Process).
- **Что подтверждает проблему:** юнит-тест `FfmpegMediaProcessorTest` подменяет `runEncoding()`, значит
  `buildWaveform`/`extractWaveform`/`extractPoster` в нём не исполняются; покрытие даёт только
  `FfmpegMediaProcessorIntegrationTest`, проверяющий лишь длину волны и диапазон, но не сами амплитуды и
  не граничные случаи.
- **Как исправить:** опционально вынести чистый `buildWaveform(string $pcm, int $peaks)` под отдельный
  быстрый юнит-тест с заранее известным PCM-буфером (граничные сэмплы −32768/32767, пустой буфер,
  `$peaks > $sampleCount`, неравномерное деление) — это покроет арифметику без бинаря и зафиксирует
  контракт нормализации. При желании — комментарий или guard о предусловии `$sampleCount >= $peaks`.
- **Тесты:** добавить unit на `buildWaveform` (если метод сделать тестируемым) с детерминированными
  входами, включая короткий буфер.

### 2a. Зависимость от поведения php-ffmpeg: `instanceof Video` как признак не-аудио

Аудио-процессор отличает аудио от видео проверкой `instanceof Video`, опираясь на то, что `open()`
возвращает `Video` для файлов с видеодорожкой и `Audio` для аудио-файлов. Это поведение библиотеки, не
её документированный контракт; при обновлении php-ffmpeg его стоит перепроверить. Текущее поведение
зафиксировано только интеграционным тестом `testAudioProcessorRejectsVideoSource`.

Технические детали:

- **Тип:** `quality`
- **Рекомендация:** на усмотрение автора
- **Где:** `app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaAudioProcessor.php:86`
  (`if ($audio instanceof Video)`); симметрично `FfmpegMediaVideoProcessor.php:103`.
- **Что подтверждает проблему:** проверка типа возвращаемого `open()` объекта — деталь реализации
  php-ffmpeg, не часть публичного контракта библиотеки.
- **Как исправить:** ничего обязательного; держать в чек-листе при апгрейде php-ffmpeg, тест уже фиксирует
  ожидаемое поведение.

### 3. Типизированное исключение в typecast волны — `\InvalidArgumentException`, а не доменное

Новый typecast волны при встрече битого JSON или нецелого значения бросает
`\InvalidArgumentException`. Правила проекта требуют, чтобы guard-clause бросал типизированное доменное
исключение, а не generic-заглушку. (Уточнение мета-ревью по источнику правила: специфическое правило
«VO бросают `InvalidDomainValueException`» относится к самим VO и доменным коллекциям, а не к
инфраструктурному typecast-слою; применимое здесь правило — общее «если ошибку код обнаруживает сам
(guard-clause, проверка предусловия), он сразу бросает конкретное типизированное доменное исключение, а
не generic-заглушку вроде `\UnexpectedValueException`/`\RuntimeException`».) Для typecast подходящий
выбор — `InvalidDomainValueException` (данные в БД несовместимы с доменной моделью → 500). Итоговый
вывод не меняется: голый SPL-тип здесь нежелателен.

Это сознательно скопировано из соседнего `MediaMultipartPartCollectionTypecast`, который делает ровно то
же самое, так что новый код просто следует уже сложившемуся в модуле стилю typecast-слоя. Поэтому это не
регресс, а консистентность; но если команда считает существующий паттерн допустимым отклонением для
инфраструктурного typecast (это ветка «битые данные в БД», 500 в любом случае), стоит зафиксировать это
решение, а не молча тиражировать.

Технические детали:

- **Тип:** `rules`
- **Рекомендация:** на усмотрение автора
- **Где:** `app/src/Modules/Media/Infrastructure/Cycle/MediaWaveformTypecast.php:21,27`; образец —
  `MediaMultipartPartCollectionTypecast.php:23,29,36`.
- **Что подтверждает проблему:** rules.md «если ошибку код обнаруживает сам (guard-clause)… бросает
  конкретное типизированное доменное исключение, а не generic-заглушку» и «Типизированные доменные
  исключения вместо `RuntimeException`» ориентируют на `InvalidDomainValueException`; здесь и в образце —
  голый SPL-тип.
- **Как исправить:** либо заменить на `InvalidDomainValueException` (тогда заодно стоит выровнять и
  существующий `MediaMultipartPartCollectionTypecast`, чтобы модуль был единообразен), либо оставить как
  есть, но осознанно — это решение для всего typecast-слоя, а не для одного класса.

### 4. `downloadToFile` создаёт файл без расширения — перепроверить probe ffmpeg

Файловый сервис скачивает оригинал во временный файл со случайным именем без расширения. ffmpeg/ffprobe
определяют контейнер по содержимому, а не по расширению, поэтому в норме это работает (и подтверждено
интеграционным тестом). Это лишь умозрительная точка для ручной перепроверки: набор входных форматов
ограничен потребителем и `MediaTypeResolver`, а для целевых форматов (mp4/wav/mpeg) проблем нет — поэтому
замечание низкоприоритетное и в текущем контексте реальной проблемой не является. Оставлено как памятка
на случай расширения списка допустимых форматов на редкие контейнеры, которые ffmpeg различает с
подсказкой по расширению.

Технические детали:

- **Тип:** `quality`
- **Рекомендация:** на усмотрение автора
- **Где:** `app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:220` — имя
  `media_download_<hex>` без расширения.
- **Что подтверждает проблему:** ffmpeg `open()` вызывается на пути без расширения; для редких форматов
  это может влиять на автоопределение демультиплексора.
- **Как исправить:** при желании пробрасывать в `downloadToFile` расширение оригинала
  (`$media->path->extension()`) и формировать temp-файл с ним; либо оставить как есть, если набор
  входных форматов ограничен потребителем. Текущий интеграционный тест покрывает mp4/wav.

### 5. Документ-ветка в `assertPlanValid` недостижима — проверить, что guard оправдан

Валидация плана при подтверждении загрузки имеет ветку для `MediaType::Document`, которая бросает 422.
По текущей логике документы отсекаются ещё на приёме (`MediaTypeResolver` не пропускает не-image/video/
audio), поэтому до этой ветки исполнение дойти не должно. Это нормальный защитный guard для исчерпывающего
`match`, но он непокрываем штатным сценарием — важно убедиться, что покрытие 100% достигается не за счёт
искусственного теста, дергающего недостижимый путь, а сама ветка осмыслена как защита инварианта.

Технические детали:

- **Тип:** `quality`
- **Рекомендация:** на усмотрение автора
- **Где:** `app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php:123`
  (Document → `ValidationException`); аналогичный guard в
  `ProcessMediaHandler.php:89` (Document → `InvalidDomainValueException`).
- **Что подтверждает проблему:** `MediaTypeResolver` не присваивает `MediaType::Document`, значит медиа с
  типом Document в нормальном потоке не создаётся.
- **Как исправить:** ничего; при желании пометить ветку как защиту инварианта комментарием. (Уточнение
  мета-ревью: ни в одном из двух мест осмысленного комментария-маркера «защита инварианта» сейчас НЕТ —
  в `ProcessMediaHandler:89` стоит только `throw` с сообщением, без поясняющего комментария; ранее в
  ревью было ошибочно сказано, что там комментарий уже есть. При желании комментарий стоит добавить в
  оба места.)

### 6. Голый `\RuntimeException` в guard-clause процессоров вместо типизированного исключения

Процессоры внутри `runEncoding()` отбрасывают вход неверного типа голым `\RuntimeException`:
аудио-процессор — для файла с видеодорожкой, видео-процессор — для файла без видеодорожки. Это
достижимые guard-clause (проверка предусловия, которую код обнаруживает сам), а rules.md прямо
запрещает для них generic-заглушки: «если ошибку код обнаруживает сам (guard-clause, проверка
предусловия), он сразу бросает конкретное типизированное доменное исключение в источнике, а не
generic-заглушку вроде `\UnexpectedValueException`/`\RuntimeException`».

Смягчающий фактор: оба `\RuntimeException` ловятся в `encode()` через `catch (\Throwable)` и
переупаковываются в `MediaProcessorFailedException::permanent()`, поэтому наружу голый тип не уходит и
поток корректен. Но правило относится к источнику выброса, а не к итоговому типу, — значит нарушение
формально остаётся. Те же недостижимые ветки (нет потока/нет PCM) тоже на голом `\RuntimeException`, но
они менее критичны (помечены `@codeCoverageIgnore`).

Технические детали:

- **Тип:** `rules`
- **Рекомендация:** на усмотрение автора
- **Где:** достижимые — `FfmpegMediaAudioProcessor.php:87` («Загруженный файл не является аудио.»),

codex
Первый проход показал, что ревью длинное и часть вывода обрезалась. Дочитываю оставшиеся участки с номерами строк, чтобы потом точно указать, что добавить, убрать или переформулировать.
exec
/bin/zsh -lc "nl -ba docs/reviews/2026-06-22_01-01_media-video-audio-ffmpeg.md | sed -n '1,260p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	---
     2	title: Media — реальная нарезка видео и аудио на ffmpeg
     3	date: 2026-06-22 01:01
     4	target: git diff HEAD + untracked (рабочее дерево)
     5	plan: docs/plans/2026-06-20_21-48_media-video-audio-ffmpeg.md
     6	mode: strict
     7	score: 84
     8	status: meta-reviewed
     9	meta_reviewers: [plan-check (sonnet), architecture-check (sonnet), rules-check (sonnet), quality-check (sonnet)]
    10	---
    11	
    12	# Ревью: Media — реальная нарезка видео и аудио на ffmpeg
    13	
    14	## Оценка
    15	
    16	**84/100.** Реализация плотно следует плану: появился полный аудио-доменный стек, таблица
    17	`media_audio_conversions`, реальные ffmpeg-процессоры видео и аудио, ветвление обработки по типу,
    18	выдача URL для трёх типов и отдельная волна. Код аккуратный, в основном соответствует правилам (VO,
    19	enum, исчерпывающие `match`, ранние возвраты, typecast-слой). Снижение оценки — за один реальный риск
    20	утечки временного файла при сбое скачивания, несколько нарушений правил (голый `\RuntimeException` в
    21	guard-clause процессоров, англицизмы в русских докблоках/README, `handle()` длиннее лимита), отклонение
    22	от стратегии логирования плана, расхождение CQRS-контракта Query Handler с конвенцией возврата и пару
    23	мест, которые стоит перепроверить вручную (внешнее поведение библиотеки, граничные случаи арифметики
    24	волны).
    25	
    26	## Проблемы сверки с планом
    27	
    28	### 1. Поведение «решателя» временной ошибки уже по плана
    29	
    30	План (фаза 3, раздел «Ошибки ffmpeg») перечисляет как временные (ретраябельные) ошибки не только
    31	таймаут транскодирования, но и «нехватку ресурсов процесса». Это же повторено в докблоке нового
    32	исключения. Фактически реализованный классификатор считает временной ошибкой исключительно таймаут —
    33	любая нехватка ресурсов, нештатное завершение процесса по нехватке памяти и т.п. попадут в «постоянную»
    34	ветку и уедут в `failed` без повтора.
    35	
    36	Само по себе это может быть осознанным сужением (надёжно отличить «временную нехватку ресурсов» от
    37	«битого входа» по исключению php-ffmpeg тяжело). Но тогда расхождение стоит зафиксировать: либо
    38	поправить формулировку в докблоке `MediaProcessorFailedException`, чтобы она не обещала поведение,
    39	которого нет, либо явно расширить классификатор. Сейчас докблок и код расходятся, и при эксплуатации
    40	это введёт в заблуждение. (Уточнение мета-ревью: README такого обещания НЕ содержит — его текст
    41	описывает только «таймаут транскодирования → временная», поэтому README в правке не нуждается; проблема
    42	строго в докблоке `MediaProcessorFailedException.php:28`.)
    43	
    44	Технические детали:
    45	
    46	- **Статус:** отклонение
    47	- **Где:** `app/src/Modules/Media/Infrastructure/FileService/AbstractFfmpegMediaProcessor.php:82` —
    48	  `isTransientFailure()` ловит только `ProcessTimedOutException`; докблок
    49	  `app/src/Modules/Media/Application/Exception/MediaProcessorFailedException.php:28` обещает
    50	  «нехватку ресурсов процесса» как временную.
    51	- **Что подтверждает проблему:** цикл по `getPrevious()` возвращает `true` только для
    52	  `ProcessTimedOutException`; ветки для OOM/ресурсов нет.
    53	- **Как исправить:** выбрать одно из двух — либо привести текст докблока к фактическому поведению
    54	  («временная только при таймауте»), либо добавить распознавание сигнала нехватки ресурсов и юнит-тест
    55	  на него. Минимально достаточно правки текста докблока.
    56	
    57	### 2. README описывает дубли типов только для image, но в коде защита есть только у image
    58	
    59	В README сказано: «дубли типов в списке image → 422». Для video и audio дубли невозможны, потому что
    60	там требуется ровно один профиль, так что отдельная проверка дублей им не нужна — это корректно. Но в
    61	плане (раздел «Целевой алгоритм») написано «Дубли типов в любом списке → 422». Формулировка README
    62	точнее отражает реализацию, чем план; это не баг, а лишь напоминание перепроверить, что именно такого
    63	поведения и хотели: для image дубли действительно отлавливаются до S3-записей, для остальных типов —
    64	через ограничение «ровно один».
    65	
    66	Технические детали:
    67	
    68	- **Статус:** не проверялось (на усмотрение)
    69	- **Где:** `app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php:139`
    70	  (`assertImagePlan`, строки 130–148 — единственная проверка `unique`), README таблица плана.
    71	- **Что подтверждает проблему:** `assertVideoPlan`/`assertAudioPlan` не проверяют дубли, а гарантируют
    72	  единственный элемент через `count() !== 1`.
    73	- **Как исправить:** ничего в коде менять не нужно; при желании синхронизировать формулировку плана с
    74	  README.
    75	
    76	## Замечания
    77	
    78	### 1. Временный файл оригинала может остаться на диске при сбое скачивания
    79	
    80	Когда видео/аудио процессор скачивает оригинал, частичный файл может остаться внутри самого
    81	`downloadToFile` при сбое скачивания. Механика (уточнена мета-ревью): процессор вызывает
    82	`downloadToFile` ПЕРВОЙ строкой, ещё до входа в свой `try`. Если скачивание упадёт (штатный сценарий —
    83	объект конкурентно удалили или кратковременный сбой сети), S3-клиент уже мог записать частичный файл на
    84	диск через `SaveAs`. `downloadToFile` бросает исключение, не удалив этот файл, и путь наружу не
    85	возвращается. При этом переменная `$sourceFile` в процессоре не присваивается (оператор `=` не
    86	завершился), поэтому `finally` процессора к ней обратиться не может в принципе — то есть очистить
    87	частичный файл некому ни в процессоре, ни в `downloadToFile`. Файл останется в системном temp-каталоге
    88	навсегда. Источник проблемы и место исправления — строго внутри `downloadToFile`.
    89	
    90	На горячем пути обработки медиа это медленно, но верно засоряет диск worker-а временными файлами,
    91	особенно при всплесках сетевых ошибок S3. Риск проявится не сразу, а как постепенное заполнение
    92	`/tmp` под нагрузкой с ретраями.
    93	
    94	Технические детали:
    95	
    96	- **Тип:** `bug`
    97	- **Рекомендация:** на усмотрение автора
    98	- **Где:** `app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:216`
    99	  (метод `downloadToFile`, тело `try/catch` ниже): создаёт `$localFile`, при `AwsException` бросает, не
   100	  удаляя файл. Процессоры (`FfmpegMediaVideoProcessor.php:44`, `FfmpegMediaAudioProcessor.php:45`)
   101	  вызывают `downloadToFile` до своего `try`, поэтому их `finally` тут не помогает.
   102	- **Что подтверждает проблему:** `getObject(['SaveAs' => $localFile])` может создать частичный файл до
   103	  ошибки; в `catch (AwsException)` нет очистки `$localFile`, а процессор получит исключение до того, как
   104	  узнает путь.
   105	- **Как исправить:** в `downloadToFile` обернуть скачивание в `try { ... } catch (AwsException) { удалить
   106	  частичный файл, если создан; перебросить }` — это законная очистка ресурса на границе Infrastructure
   107	  (rules.md разрешает try-catch в инфраструктуре, если он реально освобождает ресурс). Без выноса наружу
   108	  нового типа исключения.
   109	- **Тесты:** Feature/Infrastructure против MinIO — скачивание несуществующего объекта не оставляет файла
   110	  в temp-каталоге (снять snapshot списка temp-файлов до/после).
   111	
   112	### 2. Прямые вызовы ffmpeg через Process не покрыты юнит-тестами и зависят от деталей библиотеки
   113	
   114	Извлечение постера видео и волны аудио сделано прямыми вызовами бинаря ffmpeg через
   115	`Symfony\Component\Process\Process` внутри `runEncoding()`. Юнит-тесты подменяют весь `runEncoding()`
   116	целиком, поэтому эти куски проверяются только интеграционным тестом в Docker. Это допустимо по плану, но
   117	стоит держать в голове: логика разбора PCM (перевод беззнаковых сэмплов в знаковые, нормализация в
   118	0..255, деление на интервалы) — нетривиальная и сейчас не имеет отдельного быстрого теста; её корректность
   119	держится на одном интеграционном прогоне с сигналом 440 Гц.
   120	
   121	Отдельный граничный риск той же арифметики (отмечен мета-ревью): при `$sampleCount < $peaks` часть
   122	интервалов получает `$start === $end`, внутренний цикл не выполняется ни разу, и `$maxAmplitude`
   123	остаётся 0 — пустые интервалы молча дают амплитуду 0. На сигнале 440 Гц (`WAVEFORM_SAMPLE_RATE = 8000`,
   124	64 пика) `sampleCount` всегда много больше `peaks`, поэтому проблема не проявляется; но для очень
   125	короткого аудио или большого `waveformPeaks` результат будет тихо некорректным. Это скрытое предусловие
   126	`$sampleCount >= $peaks` без guard и без комментария.
   127	
   128	Технические детали:
   129	
   130	- **Тип:** `tests`
   131	- **Рекомендация:** на усмотрение автора
   132	- **Где:** `app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaAudioProcessor.php:137`
   133	  (`buildWaveform` — разбор PCM); `:152–167` (деление на интервалы, незащищённое от `$peaks > $sampleCount`);
   134	  `FfmpegMediaVideoProcessor.php:144` (`extractPoster` прямым Process).
   135	- **Что подтверждает проблему:** юнит-тест `FfmpegMediaProcessorTest` подменяет `runEncoding()`, значит
   136	  `buildWaveform`/`extractWaveform`/`extractPoster` в нём не исполняются; покрытие даёт только
   137	  `FfmpegMediaProcessorIntegrationTest`, проверяющий лишь длину волны и диапазон, но не сами амплитуды и
   138	  не граничные случаи.
   139	- **Как исправить:** опционально вынести чистый `buildWaveform(string $pcm, int $peaks)` под отдельный
   140	  быстрый юнит-тест с заранее известным PCM-буфером (граничные сэмплы −32768/32767, пустой буфер,
   141	  `$peaks > $sampleCount`, неравномерное деление) — это покроет арифметику без бинаря и зафиксирует
   142	  контракт нормализации. При желании — комментарий или guard о предусловии `$sampleCount >= $peaks`.
   143	- **Тесты:** добавить unit на `buildWaveform` (если метод сделать тестируемым) с детерминированными
   144	  входами, включая короткий буфер.
   145	
   146	### 2a. Зависимость от поведения php-ffmpeg: `instanceof Video` как признак не-аудио
   147	
   148	Аудио-процессор отличает аудио от видео проверкой `instanceof Video`, опираясь на то, что `open()`
   149	возвращает `Video` для файлов с видеодорожкой и `Audio` для аудио-файлов. Это поведение библиотеки, не
   150	её документированный контракт; при обновлении php-ffmpeg его стоит перепроверить. Текущее поведение
   151	зафиксировано только интеграционным тестом `testAudioProcessorRejectsVideoSource`.
   152	
   153	Технические детали:
   154	
   155	- **Тип:** `quality`
   156	- **Рекомендация:** на усмотрение автора
   157	- **Где:** `app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaAudioProcessor.php:86`
   158	  (`if ($audio instanceof Video)`); симметрично `FfmpegMediaVideoProcessor.php:103`.
   159	- **Что подтверждает проблему:** проверка типа возвращаемого `open()` объекта — деталь реализации
   160	  php-ffmpeg, не часть публичного контракта библиотеки.
   161	- **Как исправить:** ничего обязательного; держать в чек-листе при апгрейде php-ffmpeg, тест уже фиксирует
   162	  ожидаемое поведение.
   163	
   164	### 3. Типизированное исключение в typecast волны — `\InvalidArgumentException`, а не доменное
   165	
   166	Новый typecast волны при встрече битого JSON или нецелого значения бросает
   167	`\InvalidArgumentException`. Правила проекта требуют, чтобы guard-clause бросал типизированное доменное
   168	исключение, а не generic-заглушку. (Уточнение мета-ревью по источнику правила: специфическое правило
   169	«VO бросают `InvalidDomainValueException`» относится к самим VO и доменным коллекциям, а не к
   170	инфраструктурному typecast-слою; применимое здесь правило — общее «если ошибку код обнаруживает сам
   171	(guard-clause, проверка предусловия), он сразу бросает конкретное типизированное доменное исключение, а
   172	не generic-заглушку вроде `\UnexpectedValueException`/`\RuntimeException`».) Для typecast подходящий
   173	выбор — `InvalidDomainValueException` (данные в БД несовместимы с доменной моделью → 500). Итоговый
   174	вывод не меняется: голый SPL-тип здесь нежелателен.
   175	
   176	Это сознательно скопировано из соседнего `MediaMultipartPartCollectionTypecast`, который делает ровно то
   177	же самое, так что новый код просто следует уже сложившемуся в модуле стилю typecast-слоя. Поэтому это не
   178	регресс, а консистентность; но если команда считает существующий паттерн допустимым отклонением для
   179	инфраструктурного typecast (это ветка «битые данные в БД», 500 в любом случае), стоит зафиксировать это
   180	решение, а не молча тиражировать.
   181	
   182	Технические детали:
   183	
   184	- **Тип:** `rules`
   185	- **Рекомендация:** на усмотрение автора
   186	- **Где:** `app/src/Modules/Media/Infrastructure/Cycle/MediaWaveformTypecast.php:21,27`; образец —
   187	  `MediaMultipartPartCollectionTypecast.php:23,29,36`.
   188	- **Что подтверждает проблему:** rules.md «если ошибку код обнаруживает сам (guard-clause)… бросает
   189	  конкретное типизированное доменное исключение, а не generic-заглушку» и «Типизированные доменные
   190	  исключения вместо `RuntimeException`» ориентируют на `InvalidDomainValueException`; здесь и в образце —
   191	  голый SPL-тип.
   192	- **Как исправить:** либо заменить на `InvalidDomainValueException` (тогда заодно стоит выровнять и
   193	  существующий `MediaMultipartPartCollectionTypecast`, чтобы модуль был единообразен), либо оставить как
   194	  есть, но осознанно — это решение для всего typecast-слоя, а не для одного класса.
   195	
   196	### 4. `downloadToFile` создаёт файл без расширения — перепроверить probe ffmpeg
   197	
   198	Файловый сервис скачивает оригинал во временный файл со случайным именем без расширения. ffmpeg/ffprobe
   199	определяют контейнер по содержимому, а не по расширению, поэтому в норме это работает (и подтверждено
   200	интеграционным тестом). Это лишь умозрительная точка для ручной перепроверки: набор входных форматов
   201	ограничен потребителем и `MediaTypeResolver`, а для целевых форматов (mp4/wav/mpeg) проблем нет — поэтому
   202	замечание низкоприоритетное и в текущем контексте реальной проблемой не является. Оставлено как памятка
   203	на случай расширения списка допустимых форматов на редкие контейнеры, которые ffmpeg различает с
   204	подсказкой по расширению.
   205	
   206	Технические детали:
   207	
   208	- **Тип:** `quality`
   209	- **Рекомендация:** на усмотрение автора
   210	- **Где:** `app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:220` — имя
   211	  `media_download_<hex>` без расширения.
   212	- **Что подтверждает проблему:** ffmpeg `open()` вызывается на пути без расширения; для редких форматов
   213	  это может влиять на автоопределение демультиплексора.
   214	- **Как исправить:** при желании пробрасывать в `downloadToFile` расширение оригинала
   215	  (`$media->path->extension()`) и формировать temp-файл с ним; либо оставить как есть, если набор
   216	  входных форматов ограничен потребителем. Текущий интеграционный тест покрывает mp4/wav.
   217	
   218	### 5. Документ-ветка в `assertPlanValid` недостижима — проверить, что guard оправдан
   219	
   220	Валидация плана при подтверждении загрузки имеет ветку для `MediaType::Document`, которая бросает 422.
   221	По текущей логике документы отсекаются ещё на приёме (`MediaTypeResolver` не пропускает не-image/video/
   222	audio), поэтому до этой ветки исполнение дойти не должно. Это нормальный защитный guard для исчерпывающего
   223	`match`, но он непокрываем штатным сценарием — важно убедиться, что покрытие 100% достигается не за счёт
   224	искусственного теста, дергающего недостижимый путь, а сама ветка осмыслена как защита инварианта.
   225	
   226	Технические детали:
   227	
   228	- **Тип:** `quality`
   229	- **Рекомендация:** на усмотрение автора
   230	- **Где:** `app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php:123`
   231	  (Document → `ValidationException`); аналогичный guard в
   232	  `ProcessMediaHandler.php:89` (Document → `InvalidDomainValueException`).
   233	- **Что подтверждает проблему:** `MediaTypeResolver` не присваивает `MediaType::Document`, значит медиа с
   234	  типом Document в нормальном потоке не создаётся.
   235	- **Как исправить:** ничего; при желании пометить ветку как защиту инварианта комментарием. (Уточнение
   236	  мета-ревью: ни в одном из двух мест осмысленного комментария-маркера «защита инварианта» сейчас НЕТ —
   237	  в `ProcessMediaHandler:89` стоит только `throw` с сообщением, без поясняющего комментария; ранее в
   238	  ревью было ошибочно сказано, что там комментарий уже есть. При желании комментарий стоит добавить в
   239	  оба места.)
   240	
   241	### 6. Голый `\RuntimeException` в guard-clause процессоров вместо типизированного исключения
   242	
   243	Процессоры внутри `runEncoding()` отбрасывают вход неверного типа голым `\RuntimeException`:
   244	аудио-процессор — для файла с видеодорожкой, видео-процессор — для файла без видеодорожки. Это
   245	достижимые guard-clause (проверка предусловия, которую код обнаруживает сам), а rules.md прямо
   246	запрещает для них generic-заглушки: «если ошибку код обнаруживает сам (guard-clause, проверка
   247	предусловия), он сразу бросает конкретное типизированное доменное исключение в источнике, а не
   248	generic-заглушку вроде `\UnexpectedValueException`/`\RuntimeException`».
   249	
   250	Смягчающий фактор: оба `\RuntimeException` ловятся в `encode()` через `catch (\Throwable)` и
   251	переупаковываются в `MediaProcessorFailedException::permanent()`, поэтому наружу голый тип не уходит и
   252	поток корректен. Но правило относится к источнику выброса, а не к итоговому типу, — значит нарушение
   253	формально остаётся. Те же недостижимые ветки (нет потока/нет PCM) тоже на голом `\RuntimeException`, но
   254	они менее критичны (помечены `@codeCoverageIgnore`).
   255	
   256	Технические детали:
   257	
   258	- **Тип:** `rules`
   259	- **Рекомендация:** на усмотрение автора
   260	- **Где:** достижимые — `FfmpegMediaAudioProcessor.php:87` («Загруженный файл не является аудио.»),

exec
/bin/zsh -lc "nl -ba docs/reviews/2026-06-22_01-01_media-video-audio-ffmpeg.md | sed -n '261,560p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
   261	  `FfmpegMediaVideoProcessor.php:104` («Загруженный файл не является видео.»); недостижимые —
   262	  `FfmpegMediaAudioProcessor.php:99,144`, `FfmpegMediaVideoProcessor.php:123`.
   263	- **Что подтверждает проблему:** строки 87/104 исполняются в интеграционных тестах
   264	  (`testAudioProcessorRejectsVideoSource`/симметричный для видео) — это реальный, не теоретический путь.
   265	- **Как исправить:** бросать в этих guard сразу `MediaProcessorFailedException::permanent(...)` (или
   266	  `InvalidDomainValueException`), а не голый `\RuntimeException` с последующей переупаковкой.
   267	
   268	### 7. Англицизмы-кальки в русских докблоках, комментариях и README
   269	
   270	rules.md явно запрещает кальки с английского в человекочитаемом русском тексте и приводит образец
   271	«транзиентный» → «временный». В коде модуля рассыпаны «транзиентный/транзиентности/ретраябельный» и
   272	«throttling» в русском докблоке — это та же калька, которую правило называет запрещённой. Технические
   273	идентификаторы (имя поля `payload`, AWS-коды `Throttling`/`ThrottlingException`/`RequestThrottled` как
   274	строковые литералы) под правило НЕ попадают и трогать их не нужно — речь только о русском прозаическом
   275	тексте.
   276	
   277	Технические детали:
   278	
   279	- **Тип:** `rules`
   280	- **Рекомендация:** на усмотрение автора
   281	- **Где:** `MediaFileServiceException`-докблоки —
   282	  `MediaFileServiceFailedException.php:9` («признак транзиентности»), `:27` («Транзиентный
   283	  (ретраябельный) сбой… throttling»); `MediaProcessorFailedException.php:28` («(ретраябельный)»);
   284	  `AbstractFfmpegMediaProcessor.php:84` («(ретраябельный)»);
   285	  `RecordMediaProcessingFailureHandler.php:15` («транзиентной»); `Media.php:219` («после транзиентной
   286	  ошибки»); `ProcessMediaJob.php:24` («транзиентную»); `README.md:80,81,94` («транзиентности»,
   287	  «транзиентная», «throttling»).
   288	- **Что подтверждает проблему:** rules.md (раздел «Язык», «Без англицизмов») приводит ровно пример
   289	  «транзиентный» → «временный».
   290	- **Как исправить:** заменить на «временный»/«повторяемый»; «throttling» в русском докблоке — на
   291	  «ограничение скорости» (английское слово оставить, если оно строковый литерал AWS-кода).
   292	
   293	### 8. `ProcessMediaHandler::handle()` длиннее лимита коротких методов
   294	
   295	rules.md: «`handle()` в Handler — не более ~40 строк». Метод `handle()` в `ProcessMediaHandler`
   296	(строки 51–112) — около 62 строк. Декомпозиция частично есть (`buildImageConversions`/
   297	`buildVideoConversions`/`buildAudioConversions`), но сам `handle()` всё равно за пределом: помимо
   298	`match` он несёт логирование, перекладку оригинала, `markReadyMovedTo`, цикл `persist` и финальный
   299	лог. Это менее строгое замечание (метод читаемый), но формально лимит превышен.
   300	
   301	Технические детали:
   302	
   303	- **Тип:** `rules`
   304	- **Рекомендация:** на усмотрение автора
   305	- **Где:** `app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:51-112`.
   306	- **Что подтверждает проблему:** ~62 строки тела против лимита ~40.
   307	- **Как исправить:** при желании вынести финальную часть (перекладка оригинала + `markReadyMovedTo` +
   308	  flush + итоговый лог) в приватный метод `persistReady(...)`, оставив `handle()` тонким.
   309	
   310	### 9. `GetAudioWaveformHandler` возвращает доменный VO напрямую, минуя конвенцию возврата Query
   311	
   312	arch.md (раздел «Правила CQRS»): «Query Handler возвращает Entity, типизированную коллекцию, Result
   313	DTO или `PaginatedResult<T>`». `GetAudioWaveformHandler::handle()` возвращает `MediaWaveform` —
   314	доменный VO, который в этот список не входит. План сам предписал такой возврат (разделы «Целевой
   315	алгоритм»/«Контракты реализации»: `GetAudioWaveformHandler → MediaWaveform`), поэтому это не
   316	самовольное отклонение; но отклонение от CQRS-конвенции в коде осталось без явного обоснования, а
   317	Presentation в итоге получит сырой Domain-VO вместо Result DTO.
   318	
   319	Технические детали:
   320	
   321	- **Тип:** `arch`
   322	- **Рекомендация:** на усмотрение автора
   323	- **Где:** `app/src/Modules/Media/Application/Query/GetAudioWaveform/GetAudioWaveformHandler.php:25,43`
   324	  (`: MediaWaveform` и `return $conversion->waveform`).
   325	- **Что подтверждает проблему:** возвращаемый тип — `App\Modules\Media\Domain\ValueObject\MediaWaveform`,
   326	  это не Entity/коллекция/Result DTO/`PaginatedResult`.
   327	- **Как исправить:** либо завернуть в `GetAudioWaveformResult` (Result DTO), либо зафиксировать в плане/
   328	  доке осознанное исключение из конвенции для этого Query.
   329	
   330	### 10. Дублирование шаблона и необъяснённые «магические» числа в ffmpeg-процессорах
   331	
   332	Видео- и аудио-процессоры повторяют одинаковую обёртку `encode()` (`try { runEncoding } catch
   333	(\Throwable) { classifyFailure }`) и одинаковую конвертацию `(int) \round(probeFloat(..., 'duration') *
   334	1000)` (секунды → миллисекунды) без поясняющего комментария об единицах. В аудио-процессоре PCM-константы
   335	(`PCM_SIGNED_OFFSET = 65536`, `PCM_FULL_SCALE = 32767`, `PCM_SIGNED_THRESHOLD = 32768`) даны без
   336	комментария об их происхождении (ширина диапазона u16 / максимум s16). При добавлении третьего процессора
   337	обёртка и конвертация скопируются ещё раз.
   338	
   339	Технические детали:
   340	
   341	- **Тип:** `quality`
   342	- **Рекомендация:** на усмотрение автора
   343	- **Где:** обёртка `encode()` — `FfmpegMediaAudioProcessor.php:64-74`, `FfmpegMediaVideoProcessor.php:76-92`;
   344	  конвертация длительности — `FfmpegMediaAudioProcessor.php:107`, `FfmpegMediaVideoProcessor.php:133`;
   345	  PCM-константы — `FfmpegMediaAudioProcessor.php:32-34`.
   346	- **Что подтверждает проблему:** идентичная структура обёртки и одинаковый множитель `* 1000` в двух
   347	  классах; PCM-константы без комментария о смысле значений.
   348	- **Как исправить:** поднять шаблонный `encode()` и хелпер `probeDurationMs()` в
   349	  `AbstractFfmpegMediaProcessor`; добавить однострочные комментарии к PCM-константам.
   350	
   351	### 11. Итоговый DEBUG в `ProcessMediaHandler` не разбивает конверсии по типам — отклонение от плана логирования
   352	
   353	План (раздел «Логирование», стратегия `debug_precise`) требует итоговый DEBUG «медиа готово» «со
   354	счётчиками конверсий по типам». Фактически финальный лог содержит одно поле `'conversions' =>
   355	\count($conversions)` без разбивки по image/video/audio. Для сравнения `CompleteMediaUploadHandler`
   356	логирует счётчики по типам корректно (`imageConversions`/`videoConversions`/`audioConversions`).
   357	
   358	Технические детали:
   359	
   360	- **Тип:** `rules`
   361	- **Рекомендация:** на усмотрение автора
   362	- **Где:** `app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:107-111`.
   363	- **Что подтверждает проблему:** контекст лога — единое число `\count($conversions)`, в плане заявлены
   364	  «счётчики конверсий по типам»; образец правильной формы рядом в `CompleteMediaUploadHandler.php:79-81`.
   365	- **Как исправить:** разбить итоговый счётчик по типам в контексте лога, либо скорректировать план, если
   366	  единого числа достаточно.
   367	
   368	## Рекомендации
   369	
   370	- **Править обязательно:** —
   371	- **На усмотрение автора:** план 1, план 2, замечания 1, 2, 2a, 3, 4, 5, 6, 7, 8, 9, 10, 11
   372	
   373	## Изменения после мета-ревью
   374	
   375	Режим: strict. Запущены subагенты plan-check, architecture-check, rules-check, quality-check (sonnet).
   376	
   377	### После plan-check / architecture-check / rules-check / quality-check
   378	
   379	- **+ Добавлено:**
   380	  - Замечание 6 (rules): голый `\RuntimeException` в достижимых guard-clause процессоров
   381	    (`FfmpegMediaAudioProcessor.php:87`, `FfmpegMediaVideoProcessor.php:104`) вместо типизированного
   382	    исключения — нарушение rules.md про guard-clause (подтверждено rules-check и quality-check).
   383	  - Замечание 7 (rules): англицизмы-кальки «транзиентный/ретраябельный/throttling» в русских
   384	    докблоках/комментариях/README — rules.md прямо запрещает (подтверждено grep).
   385	  - Замечание 8 (rules): `ProcessMediaHandler::handle()` ~62 строки > лимита ~40 (rules-check).
   386	  - Замечание 9 (arch): `GetAudioWaveformHandler` возвращает доменный VO напрямую, минуя CQRS-конвенцию
   387	    возврата Query (architecture-check; план это санкционировал, но обоснования в коде нет).
   388	  - Замечание 10 (quality): дублирование обёртки `encode()` и конвертации длительности между
   389	    процессорами + PCM-константы без комментария о происхождении (quality-check).
   390	  - Замечание 11 (rules): итоговый DEBUG в `ProcessMediaHandler` не разбивает конверсии по типам —
   391	    отклонение от плана `debug_precise` (plan-check).
   392	  - Замечание 2a (quality): зависимость от `instanceof Video` вынесена в отдельный пункт.
   393	- **~ Изменено:**
   394	  - План 1: убрано ложное обвинение README — README обещания «нехватка ресурсов процесса» НЕ содержит,
   395	    проблема строго в докблоке `MediaProcessorFailedException.php:28` (plan-check).
   396	  - План 2: исправлен номер строки `CompleteMediaUploadHandler` 184 → 139 (`assertImagePlan`).
   397	  - Замечание 1: уточнена механика утечки (при сбое `downloadToFile` `$sourceFile` в процессоре не
   398	    присваивается, `finally` процессора неприменим, очистка возможна только внутри `downloadToFile`);
   399	    исправлен номер строки 948 → 216/220 (plan-check, rules-check, architecture-check).
   400	  - Замечание 2: добавлен граничный риск арифметики волны (`$peaks > $sampleCount` → пустые интервалы
   401	    молча дают 0), уточнены тест-кейсы (quality-check).
   402	  - Замечание 3: уточнён источник правила — применимо общее правило про guard-clause, а не VO-специфичное;
   403	    вывод не изменился (rules-check, architecture-check).
   404	  - Замечание 4: понижена значимость — риск умозрительный, входные форматы ограничены; исправлен номер
   405	    строки 952 → 220 (quality-check).
   406	  - Замечание 5: исправлен номер строки 177 → 123 и устранена фактическая ошибка — комментарий-маркер
   407	    «защита инварианта» отсутствует в ОБОИХ местах (в `ProcessMediaHandler` его тоже нет) (quality-check).
   408	- **− Убрано:** —
   409	- **Отклонено:**
   410	  - Снять замечание 4 полностью (quality-check предлагал): оставлено как низкоприоритетная памятка —
   411	    это честная «точка для ручной перепроверки», а не ложное замечание.
   412	  - `MediaConfig` инжектится в `AbstractFfmpegMediaProcessor` (architecture-check `?`): не нарушение —
   413	    Infrastructure свободно использует config/runtime-библиотеки; ограничение arch.md касается инъекции
   414	    `TypedConfig` в Application, а не в Infrastructure.
   415	  - `array`-свойства в `MediaConversionPlan` как нарушение (architecture-check сам снял): это допустимый
   416	    `list<...Spec>` по rules.md, не нарушение.
   417	  - `Collection::make($plan->image)` как untyped в `CompleteMediaUploadHandler:136` (quality-check):
   418	    маргинально, PHPStan выводит тип из типизированного `list`; в ревью не вносим.

exec
/bin/zsh -lc "nl -ba docs/plans/2026-06-20_21-48_media-video-audio-ffmpeg.md | sed -n '1,260p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	---
     2	title: Media — реальная нарезка видео и аудио на ffmpeg (профиль конверсии на тип)
     3	date: 2026-06-20 21:48
     4	mode: strict
     5	plan_size: normal
     6	decision_mode: recommend_and_ask
     7	status: reviewed
     8	reviewer: codex
     9	meta_reviewers: [haiku, sonnet, opus]
    10	test_strategy: after_each_phase
    11	logging_strategy: debug_precise
    12	sources:
    13	  rules: docs/rules.md
    14	  arch: docs/arch.md
    15	  research: docs/researches/2026-06-20_11-14_media-video-audio-conversions.md
    16	---
    17	
    18	# План реализации
    19	
    20	## Задача
    21	
    22	Модуль `Media` умеет реально нарезать только картинки. Нужно довести его до единой модели
    23	«профиль конверсии на тип»: на каждый `MediaType` — свой каталог профилей (enum) и свой
    24	процессор; потребитель задаёт списки профилей на тип; видео и аудио режутся реальным ffmpeg;
    25	результаты (включая URL и волну аудио) доступны потребителю. Документы — вне работ (остаются 422).
    26	
    27	Готово, когда:
    28	- аудио принимается (`audio/*` → `MediaType::Audio`), есть полный доменный стек аудио + таблица
    29	  `media_audio_conversions`;
    30	- видео реально транскодируется в mp4/H.264+AAC и даёт кадр-постер (как `MediaImageConversion`
    31	  type=Poster); аудио — в m4a/AAC и даёт массив амплитуд (волну);
    32	- `ProcessMediaHandler` ветвится исчерпывающим `match` по `MediaType`;
    33	- ffmpeg работает с локальными файлами; их владелец и уборщик — инфраструктурный процессор;
    34	- ошибки ffmpeg классифицируются контрактным `MediaProcessorFailedException` с `isTransient()`;
    35	- `GetMediaUrl` отдаёт URL image/video/audio-конверсий; волна — отдельным `GetAudioWaveform`;
    36	- `php-ffmpeg/php-ffmpeg ^1.4` в composer, бинарь ffmpeg в Docker, ffmpeg-настройки в `MediaConfig`;
    37	- `make qa` зелёный, покрытие 100%, все новые сценарии покрыты тестами.
    38	
    39	## Контекст
    40	
    41	Факты из кода (проверено чтением):
    42	
    43	- `ProcessMediaHandler` (`app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php`)
    44	  — без `#[Transactional]`: все S3/Imagick-операции вне транзакции, затем один атомарный
    45	  `persist(media + conversions); run()` с `markReadyMovedTo`. Идемпотентно: на `ready` — no-op;
    46	  конверсии создаются только в финальном flush; детерминированные ключи от `storageKey`,
    47	  unique `(media_id, type)`.
    48	- Сейчас обрабатываются только картинки: `buildConversions()` читает оригинал в строку
    49	  (`getObjectContents`), гоняет `MediaImageProcessorContract::resize`, льёт `putObject`, создаёт
    50	  `MediaImageConversion`. Видео сегодня = только `copyObject` оригинала в `videos/.../source.<ext>`.
    51	- Сообщение `MediaUploaded{mediaId, conversions: list<MediaConversionSpec>}` →
    52	  `ProcessMediaCommand{mediaId, conversions}`. `CompleteMediaUploadHandler` кладёт его в outbox в
    53	  одной транзакции с `markUploaded` и валидирует диапазон пикселей через `MediaPixelDimension`.
    54	- Видео-каркас уже есть: enum `MediaVideoConversionType::NormalizedMp4H264`, сущность
    55	  `MediaVideoConversion` (колонки + `duration_ms`, `bitrate`), `MediaVideoConversionRepository::findByMediaId`
    56	  → `MediaVideoConversionCollection`, связь `Media::videoConversions`, таблица `media_video_conversions`.
    57	  Нет: процессора, постера, создания конверсий, выдачи URL видео.
    58	- Аудио нет нигде. `MediaTypeResolver` (`Application/Service/MediaTypeResolver.php`) отклоняет всё
    59	  кроме `image/*` и `video/*` как 422. `MediaPath::assertValid` regex допускает только
    60	  `uploads|images|videos`; `originalReady` для Audio/Document бросает; нет фабрик
    61	  `videoConversion()`/`audioConversion()`.
    62	- `MediaFileServiceContract` (`Application/Contract/MediaFileServiceContract.php`): только
    63	  `getObjectContents(): string` и `putObject(string)` — файловых методов нет.
    64	- `GetMediaUrlQuery.conversionType: MediaImageConversionType|null`; `GetMediaUrlHandler` ходит только
    65	  в `MediaImageConversionRepository`.
    66	- `ProcessMediaJob` (`Presentation/Job/ProcessMediaJob.php`): признак временной ошибки = только
    67	  `MediaFileServiceFailedException::isTransient()`; ошибки процессора станут терминальными.
    68	- VO (`Domain/ValueObject`): `MediaPixelDimension` (1..100000, `supports()`), `MediaDuration`
    69	  (1..604_800_000 мс), `MediaBitrate` (1..1_000_000_000), `MediaFileSize`, `MediaMimeType`,
    70	  `MediaProcessingError` (null-object `none()`). Нет `MediaSampleRate`, `MediaWaveform`,
    71	  `MediaWaveformPeakCount`, `MediaAudioConversionId`, `MediaAudioConversionType`,
    72	  `MediaAudioConversion`, `MediaAudioConversionCollection`, `MediaAudioConversionRepository`.
    73	- `MediaConfig` (`app/src/Shared/Infrastructure/Configuration/Media/MediaConfig.php`):
    74	  `stagingTtlSeconds, multipartThresholdBytes, multipartPartSizeBytes, imageProcessingDriver`;
    75	  `configName()='media'`; `app/config/media.php` через `env()`; маппинг покрыт `MediaConfigTest`.
    76	- Docker (`docker/Dockerfile`, `ubuntu:26.04`) ставит php8.5 + `imagick`/`gd`, бинаря ffmpeg нет, в
    77	  блоке самопроверки `php -m | grep ...`. `composer.json`: `intervention/image: ^4`, php-ffmpeg нет.
    78	- Тесты: suites `Unit/Kernel/Feature` (`phpunit.xml`). Дублёры: `tests/.../Fixture/FakeS3Client`,
    79	  `FakeS3ClientProvider`, `tests/.../Flow/Fixture/RecordingMediaLogger`,
    80	  `ThrowingProcessMediaCommandBus`. База `MediaApplicationTestCase` (`createMedia`, `conversionSpec`,
    81	  доступ к репозиториям). `ProcessMediaHandlerTest` мокает контракты; `ProcessMediaJobTest`
    82	  проверяет классификацию ошибок и логирование уровней.
    83	- `Media` (`Domain/Entity/Media.php`): `recordTemporaryProcessingError`/`recordPermanentProcessingError`,
    84	  `markReadyMovedTo` (Ready→no-op; допускает переход из Uploaded/Processing/ProcessingFailed),
    85	  `markReady`, `isReady`; связи `imageConversions`, `videoConversions` (нужна `audioConversions`).
    86	- `php-ffmpeg/php-ffmpeg` 1.4.0 (релиз 2026-01-19), поддержка PHP 8.5, требует бинарей
    87	  `ffmpeg`/`ffprobe`. Источник — Packagist; версия подтверждена в research и кросс-ревью Codex.
    88	
    89	## Принятые решения
    90	
    91	1. **Профиль конверсии на тип**: на каждый `MediaType` — свой enum профилей и свой процессор-контракт;
    92	   потребитель задаёт списки профилей на тип в едином `MediaConversionPlan`. Объединять в один спек
    93	   нельзя — у аудио нет width/height, общий объект потянул бы nullable-примитивы (нарушение rules.md
    94	   «Явные типы вместо null»). *Источник: ответ пользователя (research, развилки 1 и 2).*
    95	2. **Инструмент и среда**: `php-ffmpeg/php-ffmpeg ^1.4` в текущем пути outbox→RabbitMQ-Job (без
    96	   Temporal). *Источник: ответ пользователя (research, развилка 3); версия подтверждена Packagist.*
    97	3. **Видео**: транскод mp4/H.264+AAC + кадр-постер как `MediaImageConversion` type=Poster; размер
    98	   постера = размер транскода; отдельный картиночный профиль для видео потребитель не передаёт
    99	   (картиночные профили для видео → 422). *Источник: ответ пользователя (research, развилка 4а).*
   100	4. **Аудио**: транскод m4a/AAC + волна амплитуд (массив малых целых для прогресса воспроизведения «как
   101	   в Telegram», не картинка). *Источник: ответ пользователя (research, развилка 4б).*
   102	5. **БД**: отдельная таблица на тип конверсии (как существующие `media_image_conversions`,
   103	   `media_video_conversions`); новая `media_audio_conversions` со своими полями; волна — JSON-колонка
   104	   `waveform` на строке аудио-конверсии. Общая таблица отклонена: дала бы массу пустых NULL-колонок
   105	   (нарушение «Явные типы вместо null»). *Источник: ответ пользователя в текущем сообщении.*
   106	6. **Размер плана**: normal. *Источник: ответ пользователя.*
   107	7. **Контракт процессора — крупный**: `MediaVideoProcessorContract::process(...)` /
   108	   `MediaAudioProcessorContract::process(...)` возвращают только метаданные результата. Скачивание
   109	   оригинала, ffmpeg (probe/транскод/постер/волна), загрузка результатов и удаление временных файлов в
   110	   `finally` — внутри Infrastructure-реализации; Application-Handler локальных путей не видит. Это
   111	   закрывает внутреннее противоречие research (granular-сигнатуры с локальными путями vs «процессор
   112	   владеет временными файлами и чистит их в finally») в пользу формулировки research про владельца
   113	   очистки. *Источник: autonomous (decision_mode recommend_and_ask, внутренний технический контракт;
   114	   обоснование — rules.md «handler не управляет путями», единый владелец и очистка).*
   115	8. **Смена содержимого `MediaUploaded`** (`conversions` → `plan: MediaConversionPlan`) — несовместимое
   116	   изменение. Сквозного потребителя в рабочей среде нет: HTTP-входа Media не существует, `Posts`
   117	   привязывает уже загруженные медиа по id (`PostContentComposer.php`). Предусловие выката: слить
   118	   очередь RabbitMQ и outbox. *Источник: research, подтверждено чтением кода (у модуля Media нет
   119	   HTTP-контроллеров).*
   120	
   121	## Целевой алгоритм
   122	
   123	**Подтверждение загрузки (`CompleteMediaUpload`).** Принимает `MediaConversionPlan` (три списка:
   124	image/video/audio). Валидирует **только** список, относящийся к `media.type`; список «не своего» типа
   125	непуст → `ValidationException` 422 (видео-файл со списком картинок → 422, и наоборот). Для video и audio
   126	требуется ровно один профиль (`NormalizedMp4H264` / `NormalizedAacM4a`): пустой список или больше одного
   127	→ 422. Дубли типов в любом списке → 422 (иначе падение по unique `(media_id, type)` уже после S3-записей).
   128	Диапазоны: image
   129	и video — `MediaPixelDimension::supports(width/height)`; video — `MediaBitrate::supports(videoBitrate,
   130	audioBitrate)`; audio — `MediaBitrate::supports(bitrate)`, `MediaSampleRate::supports(sampleRate)`,
   131	`MediaWaveformPeakCount::supports(waveformPeaks)`. Кладёт `MediaUploaded{mediaId, plan}` в outbox в одной
   132	транзакции с `markUploaded`.
   133	
   134	**Обработка (`ProcessMediaJob` → `ProcessMediaCommand{mediaId, plan}` → `ProcessMediaHandler`).**
   135	Идемпотентность: `media.isReady()` → no-op. Затем исчерпывающий `match (media.type)`:
   136	- `Image` — как сейчас: `getObjectContents` → `imageProcessor.resize` → `putObject`; конверсии из
   137	  `plan.image`; оригинал `copyObject` → `images/.../source.<ext>`.
   138	- `Video` — `videoProcessor.process(sourceStorage, sourcePath, spec, targetStorage, normalizedPath,
   139	  posterPath)` → метаданные (нормализованное видео + постер). Создаёт `MediaVideoConversion`
   140	  (`NormalizedMp4H264`) и `MediaImageConversion` (`Poster`, размер = размер транскода). Оригинал
   141	  `copyObject` → `videos/.../source.<ext>`.
   142	- `Audio` — `audioProcessor.process(sourceStorage, sourcePath, spec, targetStorage, normalizedPath)` →
   143	  метаданные (нормализованное аудио + `MediaWaveform`). Создаёт `MediaAudioConversion`
   144	  (`NormalizedAacM4a`) с волной. Оригинал `copyObject` → `audios/.../source.<ext>`.
   145	- `Document` — недостижимо: `MediaTypeResolver` отклоняет 422 на приёме (ветка `match` бросает
   146	  `InvalidDomainValueException` как защита инварианта).
   147	
   148	Финал — атомарный `persist(media + все конверсии); run()` + `markReadyMovedTo`. Внутри процессора:
   149	`downloadToFile(оригинал)` → ffmpeg → `uploadFromFile(результаты)` → `finally` удаляет вход и все
   150	выходы при любом исходе (успех, ошибка ffmpeg, сбой загрузки).
   151	
   152	**Выдача URL (`GetMediaUrl`).** `conversionType:
   153	MediaImageConversionType|MediaVideoConversionType|MediaAudioConversionType|null`. По классу enum —
   154	исчерпывающий `match`, выбор нужного репозитория конверсий, дальше та же логика `publicUrl`/`presignGet`.
   155	Без `conversionType` — оригинал. Волна — не через URL.
   156	
   157	**Волна (`GetAudioWaveform`).** `GetAudioWaveformQuery{mediaId}` → handler читает нормализованную
   158	`MediaAudioConversion` через `MediaAudioConversionRepository`, возвращает её `MediaWaveform` (числа
   159	встроенно). Медиа не ready / не аудио / нет конверсии → `NotFoundException`.
   160	
   161	**Ошибки ffmpeg.** Процессоры Infrastructure оборачивают исключения php-ffmpeg в
   162	`MediaProcessorFailedException` (`Application/Exception`) с `isTransient()`: битый/неподдерживаемый
   163	вход, нештатный код выхода ffmpeg на валидном вызове → постоянная (terminal, ERROR); таймаут
   164	транскодирования, нехватка ресурсов процесса → временная (`RetryException`, WARN). `ProcessMediaJob`
   165	дополняет признак временной ошибки веткой `|| (MediaProcessorFailedException && isTransient())`;
   166	`safeMessage()` не меняется (уже покрывает новый тип), а текст `RetryException.reason` выбирается по типу
   167	ошибки (для процессора — сообщение о повторе обработки, не «ошибка хранилища»). Текст — из безопасного
   168	набора, сырой вывод ffmpeg наружу не уходит (`MediaProcessingError` отклоняет пути/`etag`).
   169	
   170	## Контракты реализации
   171	
   172	### Данные и БД
   173	
   174	Меняется: добавляется таблица `media_audio_conversions`. Существующие `media`,
   175	`media_image_conversions`, `media_video_conversions` не меняются.
   176	
   177	Новая миграция `app/database/migrations/{timestamp}_0_create_media_audio_conversions_table.php` в стиле
   178	`20260521.184100_0_create_media_domain_tables.php`:
   179	
   180	```text
   181	media_audio_conversions
   182	  id           uuid          NOT NULL  PK
   183	  media_id     uuid          NOT NULL  FK -> media(id) ON DELETE CASCADE ON UPDATE CASCADE (indexCreate=false)
   184	  type         string(64)    NOT NULL
   185	  status       string(32)    NOT NULL
   186	  storage      string(64)    NOT NULL
   187	  path         string(1024)  NOT NULL
   188	  mime_type    string(255)   NOT NULL
   189	  size         bigInteger    NOT NULL
   190	  duration_ms  bigInteger    NOT NULL
   191	  bitrate      integer       NOT NULL
   192	  sample_rate  integer       NOT NULL
   193	  waveform     json          NOT NULL
   194	  created_at   datetime      NOT NULL
   195	  updated_at   datetime      NOT NULL
   196	  unique (media_id, type); index (media_id); index (status)
   197	down(): drop media_audio_conversions
   198	```
   199	
   200	Совместимость: таблица новая, аудио-строк ещё нет, backfill не нужен; rollback = `drop`.
   201	
   202	### API и внешние контракты
   203	
   204	Публичных HTTP-роутов/webhooks у модуля Media нет — меняются **внутренние Application-контракты**
   205	(CQRS DTO, outbox-сообщение, контракты процессоров и файлового сервиса). Внешних API/очередей третьих
   206	сторон задача не затрагивает.
   207	
   208	Спеки профилей (`Application/Dto`, публичные `readonly`, примитив-дружественные для Valinor):
   209	```text
   210	MediaImageConversionSpec{ MediaImageConversionType type, int width, int height }   // = переименование MediaConversionSpec
   211	MediaVideoConversionSpec{ MediaVideoConversionType type, int width, int height, int videoBitrate, int audioBitrate }
   212	MediaAudioConversionSpec{ MediaAudioConversionType type, int bitrate, int sampleRate, int waveformPeaks }
   213	MediaConversionPlan{ list<MediaImageConversionSpec> image, list<MediaVideoConversionSpec> video, list<MediaAudioConversionSpec> audio }
   214	```
   215	
   216	Все три списка `MediaConversionPlan` несут точные PHPDoc-типы `@param/@var list<...Spec>` — это условие
   217	восстановления вложенного DTO через Valinor (`ValinorOutboxMessageSerializer`); покрыть round-trip тестом.
   218	
   219	Сообщение и команды: `MediaUploaded.conversions` → `plan: MediaConversionPlan`;
   220	`ProcessMediaCommand.conversions` → `plan`; `CompleteMediaUploadCommand.conversions` → `plan`.
   221	
   222	Контракты процессоров (`Application/Contract`):
   223	```text
   224	MediaVideoProcessorContract::process(
   225	  MediaStorage sourceStorage, MediaPath sourcePath, MediaVideoConversionSpec spec,
   226	  MediaStorage targetStorage, MediaPath normalizedPath, MediaPath posterPath
   227	): MediaVideoProcessingResult
   228	MediaAudioProcessorContract::process(
   229	  MediaStorage sourceStorage, MediaPath sourcePath, MediaAudioConversionSpec spec,
   230	  MediaStorage targetStorage, MediaPath normalizedPath
   231	): MediaAudioProcessingResult
   232	```
   233	
   234	Целевые `MediaPath` (`normalizedPath`, `posterPath`) строит **Handler (Application)** через доменные
   235	фабрики `MediaPath::videoConversion`/`audioConversion`/`imageConversion(Poster)` ДО вызова `process()`
   236	и передаёт готовыми; Infrastructure-процессор доменные пути не строит (граница arch.md: Infrastructure
   237	→ только Contract + runtime). Процессоры зависят от `MediaFileServiceContract` (download/upload) и
   238	`MediaConfig` (бинарь/таймаут/threads) — autowire в `MediaBootloader`.
   239	
   240	Result-DTO (`Application/Dto`, несут доменные VO — потребляются handler-ом сразу, не сериализуются;
   241	как существующий `MediaConversionResult`):
   242	```text
   243	MediaVideoProcessingResult{
   244	  MediaMimeType normalizedMimeType, MediaFileSize normalizedSize,
   245	  MediaPixelDimension width, MediaPixelDimension height, MediaDuration duration, MediaBitrate bitrate,
   246	  MediaMimeType posterMimeType, MediaFileSize posterSize   // пиксельный размер постера = width/height транскода (решение 3), отдельных полей нет
   247	}
   248	MediaAudioProcessingResult{
   249	  MediaMimeType normalizedMimeType, MediaFileSize normalizedSize,
   250	  MediaDuration duration, MediaBitrate bitrate, MediaSampleRate sampleRate, MediaWaveform waveform
   251	}
   252	```
   253	
   254	Файловый сервис (`MediaFileServiceContract` + `S3MediaFileService`): добавить файловые методы (стиль
   255	контракта уже строковый — `getObjectContents`/`putObject` работают со строками, локальный путь —
   256	такой же технический скаляр):
   257	```text
   258	downloadToFile(MediaStorage storage, MediaPath path): string            // локальный временный путь, владелец-вызыватель (процессор) чистит
   259	uploadFromFile(MediaStorage storage, MediaPath path, string localFile, MediaMimeType mimeType): void
   260	```

exec
/bin/zsh -lc "nl -ba docs/researches/2026-06-20_11-14_media-video-audio-conversions.md | sed -n '1,320p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc "nl -ba docs/plans/2026-06-20_21-48_media-video-audio-ffmpeg.md | sed -n '261,620p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	---
     2	title: Media — обработка картинок, видео и аудио (как режем каждый тип)
     3	date: 2026-06-20 11:14
     4	mode: strict
     5	decision_mode: recommend_and_ask
     6	status: reviewed
     7	reviewer: codex
     8	sources:
     9	  rules: docs/rules.md
    10	  arch: docs/arch.md
    11	---
    12	
    13	# Media — обработка картинок, видео и аудио (как режем каждый тип)
    14	
    15	## Суть
    16	
    17	Модуль `Media` должен уметь работать с картинками, видео и аудио (документы — вне работ по
    18	прямому указанию). Картинки уже полностью обрабатываются: проверяем, как именно потребитель
    19	управляет ими сейчас, и распространяем тот же подход на видео и аудио так, чтобы модуль мог
    20	явно «сказать», как он режет каждый тип. Под «режет» понимается набор конверсий: для картинки —
    21	ресайз и кроп; для видео — транскодирование в нормализованный формат и кадр-постер; для аудио —
    22	транскодирование в нормализованный формат и волна амплитуд для показа прогресса воспроизведения
    23	(как в Telegram).
    24	
    25	Проблема: текущая модель конверсий заточена под картинку и в исполнении работает только для
    26	неё. Для видео доменный каркас уже есть, но реальной нарезки нет (выполняется только серверное
    27	копирование оригинала); аудио не принимается вообще. Нужна единая модель «профиль конверсии на
    28	тип» плюс реальные процессоры видео и аудио на ffmpeg, а также способ отдать потребителю
    29	результаты этих конверсий.
    30	
    31	## Решение
    32	
    33	### Как картинки контролируются из других модулей сейчас (проверка)
    34	
    35	У потребителя две точки контроля, обе — параметрами вызова, ничего не зашито в общий конфиг:
    36	
    37	```text
    38	RequestMediaUpload(userId, MediaUploadSpec, MediaFileMeta)
    39	  MediaUploadSpec{ allowedMimeTypes, maxSize, visibility, presignedTtl }
    40	  -> потребитель решает: какие MIME и размер разрешены под этот случай.
    41	
    42	CompleteMediaUpload(userId, mediaId, list<MediaConversionSpec>, parts?)
    43	  MediaConversionSpec{ type: MediaImageConversionType, width:int, height:int }
    44	  -> потребитель решает: какие профили картинки сгенерировать и в каком размере.
    45	```
    46	
    47	Источники: `MediaUploadSpec` и проверка — `RequestMediaUploadHandler.php:125-137`; список конверсий
    48	и его проверка диапазона пикселей (1..100000) — `CompleteMediaUploadHandler.php:55,107-121`; список
    49	доезжает до обработки через сообщение `MediaUploaded` (`MediaUploaded.php:21-24`) →
    50	`ProcessMediaCommand.php:14-17`. Модуль «говорит, как режет» картинку через перечисление
    51	`MediaImageConversionType` (thumbnail/preview/large/poster — `MediaImageConversionType.php:9-12`) и
    52	контракт `MediaImageProcessorContract::resize` (cover-crop до width×height, формат = исходный mime —
    53	`MediaImageProcessorContract.php:19-24`, `ProcessMediaHandler.php:104-138`).
    54	
    55	Вывод: потребитель полностью управляет нарезкой картинки, а модуль декларирует доступные профили и
    56	семантику ресайза. Эту же двойную поверхность (политика приёма + список профилей) переносим на видео
    57	и аудио, плюс закрываем недостающую часть — получение URL результатов всех типов.
    58	
    59	### Что уже есть, а что добавляем
    60	
    61	```text
    62	Image  ГОТОВО ПОЛНОСТЬЮ: enum, сущность, репозиторий, коллекция, процессор (Imagick),
    63	       приём (MediaTypeResolver image/*), путь (images/), создание конверсий, выдача URL.
    64	
    65	Video  ЕСТЬ КАРКАС, НЕТ НАРЕЗКИ: enum MediaVideoConversionType{NormalizedMp4H264},
    66	       сущность MediaVideoConversion, MediaVideoConversionRepository, коллекция,
    67	       связь Media::videoConversions, таблица media_video_conversions, приём video/*,
    68	       путь videos/ для оригинала. НЕТ: процессора (ffmpeg), создания конверсий в
    69	       ProcessMediaHandler, фабрики пути videoConversion(), постера, выдачи URL видео-конверсий.
    70	
    71	Audio  НЕТ НИЧЕГО: ни приёма (MediaTypeResolver отклоняет audio/* как 422 —
    72	       MediaTypeResolver.php:30-34), ни префикса пути audios/ (MediaPath.php:120),
    73	       ни enum/сущности/репозитория/коллекции/процессора/волны.
    74	
    75	Document  ВНЕ РАБОТ по указанию пользователя: остаётся 422, MediaPath для Document бросает.
    76	```
    77	
    78	### Целевая модель: конверсия на тип (как режем каждый тип)
    79	
    80	Единый принцип: на каждый `MediaType` — каталог профилей (enum) и свой процессор (контракт),
    81	который делает реальную нарезку; потребитель управляет списком профилей на тип.
    82	
    83	```text
    84	Тип     Профили (enum)                 Процессор (контракт)           Инструмент
    85	----    --------------                 --------------------           ----------
    86	Image   MediaImageConversionType       MediaImageProcessorContract    Imagick (Intervention v4)
    87	        thumbnail/preview/large/poster resize: cover-crop W×H
    88	
    89	Video   MediaVideoConversionType       MediaVideoProcessorContract    ffmpeg (php-ffmpeg) [новый]
    90	        normalizedMp4H264              transcode + posterFrame
    91	        (+ постер -> image-конверсия)
    92	
    93	Audio   MediaAudioConversionType       MediaAudioProcessorContract    ffmpeg (php-ffmpeg) [новый]
    94	        normalizedAacM4a               transcode + extractWaveform
    95	        (+ волна амплитуд)
    96	```
    97	
    98	Профили (перечисления) на тип со своими параметрами (решение развилки 2 — отдельные на тип):
    99	объединять в один `MediaConversionSpec` нельзя, потому что у аудио нет width/height, а у видео есть
   100	битрейт и кодек; общий объект потянул бы nullable-примитивы и слишком широкие типы — прямое
   101	нарушение `rules.md` («Явные типы вместо null», «Entity без примитивов», «Не заменять типизацию
   102	ручными проверками»).
   103	
   104	### Профили-объекты (примитив-дружественные, для сериализации сообщения)
   105	
   106	Все профили — публичные `readonly`-DTO из примитивов и enum, чтобы `ValinorOutboxMessageSerializer`
   107	восстанавливал их без приватных фабрик VO (так же, как нынешний `MediaConversionSpec` —
   108	`MediaConversionSpec.php:9-22`):
   109	
   110	```text
   111	MediaImageConversionSpec{ type: MediaImageConversionType, width:int, height:int }   // = нынешний, переименовать
   112	MediaVideoConversionSpec{ type: MediaVideoConversionType, width:int, height:int, videoBitrate:int, audioBitrate:int }
   113	MediaAudioConversionSpec{ type: MediaAudioConversionType, bitrate:int, sampleRate:int, waveformPeaks:int }
   114	```
   115	
   116	Контейнер плана вместо голого `list<MediaConversionSpec>`:
   117	
   118	```text
   119	MediaConversionPlan{ image: list<...ImageSpec>, video: list<...VideoSpec>, audio: list<...AudioSpec> }
   120	```
   121	
   122	`MediaUploaded` и `ProcessMediaCommand` несут `MediaConversionPlan` (три примитивных списка) вместо
   123	одного списка картинок. Это и есть точка, где модель «как режем» становится общей для трёх типов;
   124	содержимое сообщения остаётся пригодным для Valinor (тот же контракт, что сегодня). В
   125	`CompleteMediaUpload` проверяется только список, относящийся к типу медиа: видео-файл со списком
   126	картинок → 422.
   127	
   128	### Профили: точные форматы, расширения и режимы нарезки
   129	
   130	Нормализация меняет контейнер и кодек, поэтому путь конверсии берёт расширение из профиля, а не из
   131	расширения оригинала (в отличие от картинок, где формат сохраняется). Каждый профиль декларирует
   132	своё расширение и MIME:
   133	
   134	```text
   135	normalizedMp4H264  -> контейнер mp4, видео H.264 + звук AAC, MIME video/mp4, расширение .mp4
   136	normalizedAacM4a   -> контейнер mp4 (m4a), звук AAC,          MIME audio/mp4, расширение .m4a
   137	постер видео       -> кадр JPEG/WebP, хранится как MediaImageConversion type=Poster
   138	волна аудио        -> массив амплитуд (не файл), см. ниже
   139	```
   140	
   141	Режим нарезки видео (закрытие замечания о crop/contain/padding/повороте): вписываем кадр в рамку
   142	W×H **с сохранением пропорций, без обрезки**, выравниваем стороны до чётных (H.264 требует чётные
   143	размеры), применяем поворот по метаданным и очищаем флаг поворота. Кроп не используем, чтобы не
   144	терять содержимое кадра; рамку-паддинг не добавляем, чтобы не зашивать цвет фона.
   145	
   146	### Процессоры: сигнатуры и результаты
   147	
   148	Контракты в `Application/Contract`, реализации на php-ffmpeg в `Infrastructure/FileService`. Вход —
   149	локальный путь к файлу (не строка в памяти, см. ниже). Результат несёт фактические метаданные,
   150	которые требуют конструкторы доменных сущностей конверсий.
   151	
   152	```text
   153	interface MediaVideoProcessorContract {
   154	    transcode(string $sourceFile, MediaVideoConversionSpec $spec): MediaVideoConversionResult
   155	    extractPoster(string $sourceFile, MediaPixelDimension $width, MediaPixelDimension $height): MediaConversionResult
   156	    probe(string $sourceFile): MediaVideoProbe   // ffprobe: width,height,duration,bitrate
   157	}
   158	interface MediaAudioProcessorContract {
   159	    transcode(string $sourceFile, MediaAudioConversionSpec $spec): MediaAudioConversionResult
   160	    extractWaveform(string $sourceFile, int $peaks): MediaWaveform
   161	    probe(string $sourceFile): MediaAudioProbe   // ffprobe: duration,bitrate,sampleRate
   162	}
   163	
   164	MediaVideoConversionResult{ localFile, mimeType, size, width, height, duration, bitrate }
   165	MediaAudioConversionResult{ localFile, mimeType, size, duration, bitrate, sampleRate }
   166	```
   167	
   168	`extractPoster` возвращает тот же `MediaConversionResult`, что и картиночный процессор, поэтому кадр
   169	кладётся как `MediaImageConversion` без отдельной сущности. Размер постера берётся от транскода видео
   170	(решение развилки 4а): потребитель не передаёт картиночный профиль для видео, правило «картиночные
   171	профили для видео → 422» остаётся в силе; постер — фиксированный побочный выход видео-профиля.
   172	
   173	### Волна аудио «как в Telegram»
   174	
   175	Решение развилки 4б: аудио в этой итерации — обязательный нормализованный транскод плюс **волна
   176	амплитуд для показа прогресса воспроизведения**. «Как в Telegram» — это не картинка, а компактный
   177	массив амплитуд (например, ~100 значений), по которому клиент сам рисует полоски и заполняет их по
   178	ходу проигрывания. Статичный PNG этого не даёт, поэтому именно массив, а не `showwavespic`-картинка.
   179	
   180	```text
   181	MediaWaveform — value object: список малых целых (амплитуды), JsonSerializable.
   182	Построение: ffmpeg декодирует оригинал в моно-PCM низкой частоты -> делим на N интервалов ->
   183	            пик амплитуды каждого -> нормализуем в 0..максимум. N = waveformPeaks из профиля.
   184	Хранение:   JSON-колонка waveform на строке нормализованной аудио-конверсии (MediaAudioConversion),
   185	            через JSON-typecast (образец MediaMultipartPartCollectionTypecast).
   186	Выдача:     встроенно числами (клиенту нужны сами значения для отрисовки), отдельным запросом
   187	            GetAudioWaveform(mediaId) -> MediaWaveform; presigned-URL тут не подходит.
   188	```
   189	
   190	Компромисс: волна логически — характеристика исходного аудио, но привязка к строке нормализованной
   191	конверсии приемлема, потому что та всегда создаётся для аудио; отдельная сущность под один список
   192	была бы лишней.
   193	
   194	### Получение URL результатов всех типов (закрытие пробела)
   195	
   196	Сейчас выдача URL умеет только картинки: `GetMediaUrlQuery.conversionType` имеет тип
   197	`MediaImageConversionType|null` (`GetMediaUrlQuery.php:14`), а `GetMediaUrlHandler` ходит только в
   198	`MediaImageConversionRepository` (`GetMediaUrlHandler.php:24,57-62`). Значит результаты видео- и
   199	аудио-конверсий потребителю недостижимы. Расширяем:
   200	
   201	```text
   202	GetMediaUrlQuery.conversionType: MediaImageConversionType|MediaVideoConversionType|MediaAudioConversionType|null
   203	GetMediaUrlHandler: по классу enum выбирает нужный репозиторий конверсий (исчерпывающий match),
   204	                    дальше та же логика public/presignGet, что и сейчас.
   205	Волна отдаётся не через URL, а запросом GetAudioWaveform (числа встроенно).
   206	```
   207	
   208	Так модуль «говорит, как нарезал», и одновременно даёт ссылку на каждый нарезанный объект.
   209	
   210	### Ветвление обработки по типу
   211	
   212	`ProcessMediaHandler` (`ProcessMediaHandler.php:41-84`) получает исчерпывающий `match` по
   213	`MediaType` (домен уже знает все 4 значения — `MediaType.php:9-12`):
   214	
   215	```text
   216	match (media.type):
   217	  Image    -> картиночный процессор (Imagick); оригинал copyObject -> images/.../source.<ext>
   218	  Video    -> probe(ffprobe) -> транскоды (ffmpeg) + кадр-постер; оригинал -> videos/.../source.<ext>
   219	  Audio    -> probe -> транскоды (ffmpeg) + волна; оригинал -> audios/.../source.<ext>
   220	  Document -> вне работ (MediaTypeResolver отклоняет 422)
   221	```
   222	
   223	Идемпотентность сохраняется по образцу картинок: детерминированные ключи от `storageKey`, конверсии
   224	создаются только в финальном `persist+run()`, на статусе `ready` — повторный вызов ничего не делает
   225	(`ProcessMediaHandler.php:46-52`, README модуля «Транзакционная дисциплина S3»).
   226	
   227	### Файловый сервис: ffmpeg работает с файлами, не со строкой в памяти
   228	
   229	Текущий контракт читает оригинал целиком в строку (`getObjectContents(): string`) и пишет строку
   230	(`putObject(string $contents)` — `MediaFileServiceContract.php:85,90-95`). Для ffmpeg/ffprobe нужен
   231	локальный путь к файлу, а видео может быть большим — держать его строкой в памяти неприемлемо.
   232	Добавляем файловые методы и используем их только для видео и аудио (картинка остаётся на чтении в
   233	память):
   234	
   235	```text
   236	downloadToFile(storage, path): string                       // вернуть локальный временный путь
   237	uploadFromFile(storage, path, localFile, mimeType): void
   238	```
   239	
   240	Ответственность за временные файлы (закрытие замечания о владельце и очистке): владелец временных
   241	путей — инфраструктурный ffmpeg-процессор. Он сам через `downloadToFile` получает входной файл,
   242	создаёт выходные временные файлы и в блоке `finally` удаляет и входной, и выходные при любом исходе
   243	(успех, ошибка ffmpeg, сбой загрузки результата). Application-Handler локальных путей не видит и за
   244	очистку не отвечает.
   245	
   246	### Классификация ошибок ffmpeg
   247	
   248	Сейчас `ProcessMediaJob` считает повторяемой только ошибку `MediaFileServiceFailedException` и читает
   249	её признак `isTransient()` (`ProcessMediaJob.php:57`). Ошибка ffmpeg-процессора в эту ветку не
   250	попадёт и всегда станет терминальной. Вводим контрактное исключение процессора:
   251	
   252	```text
   253	MediaProcessorFailedException (App\Modules\Media\Application\Exception) с isTransient():
   254	  битый/неподдерживаемый вход, нештатный код выхода ffmpeg на валидном вызове -> постоянная (terminal, ERROR)
   255	  таймаут транскодирования, нехватка ресурсов процесса                        -> временная (RetryException, WARN)
   256	Реализации процессоров в Infrastructure оборачивают исключения php-ffmpeg в этот тип.
   257	ProcessMediaJob расширяет проверку: признак временной ошибки = MediaFileServiceFailedException
   258	  ИЛИ MediaProcessorFailedException с isTransient().
   259	```
   260	
   261	Текст ошибки — из безопасного набора `MediaProcessingError`, сырой вывод ffmpeg наружу не уходит
   262	(README модуля «Статусы и обработка ошибок»).
   263	
   264	### Инструмент, среда выполнения и Docker (развилка 3)
   265	
   266	```text
   267	Пакет     php-ffmpeg/php-ffmpeg ^1.4 (стабильная 1.4.0 от 19.01.2026; ограничение PHP включает 8.5;
   268	          требует установленных бинарей ffmpeg и ffprobe). Источник версии — Packagist (см. Итог).
   269	Среда     тот же путь outbox -> ProcessMediaJob (RabbitMQ), что и для картинок — без Temporal.
   270	Docker    FROM ubuntu:26.04, установка apt. Добавить пакет `ffmpeg` (даёт ffmpeg и ffprobe) и проверку
   271	          `ffmpeg -version`/`ffprobe -version` в блок самопроверки образа (по образцу imagick/gd —
   272	          docker/Dockerfile:35-36,66-69). Наличие пакета `ffmpeg` в репозитории базового образа и
   273	          фактический путь к бинарям подтверждаются на этапе сборки образа через `command -v ffmpeg`/
   274	          `command -v ffprobe` — это снимает риск неверного абсолютного пути.
   275	Конфиг    app/config/media.php + MediaConfig: путь к бинарям и предел времени транскодирования —
   276	          MEDIA_FFMPEG_BINARY, MEDIA_FFPROBE_BINARY (значения по умолчанию подставляются из найденного
   277	          при сборке пути), MEDIA_FFMPEG_TIMEOUT_SECONDS, MEDIA_FFMPEG_THREADS. Процессор
   278	          (Infrastructure) получает их через типизированный MediaConfig, не через env() (rules.md
   279	          «env() только в конфигах»).
   280	```
   281	
   282	Принятый предел среды выполнения: длинные видео могут упереться в предел времени задачи RoadRunner
   283	или предел видимости сообщения RabbitMQ. `ProcessMedia` намеренно без `#[Transactional]`, поэтому БД
   284	и блокировку строки на время транскодирования не держим (`ProcessMediaHandler` без атрибута, README
   285	«Транзакционная дисциплина S3»). Сдерживаем вход через `MediaUploadSpec.maxSize` от потребителя и
   286	`MEDIA_FFMPEG_TIMEOUT_SECONDS`. Если упрёмся — задокументированный путь отхода: вынести
   287	транскодирование в Temporal (отклонённый вариант B развилки 3), Temporal в проекте уже поднят
   288	(`arch.md` «Поток Temporal»). Берём как осознанный компромисс ради единообразия с картинками.
   289	
   290	### Совместимость сообщения при смене содержимого
   291	
   292	Замена `conversions: list<MediaConversionSpec>` на `plan: MediaConversionPlan` в `MediaUploaded` —
   293	несовместимое изменение содержимого сообщения. В рабочей среде сквозного потребителя ещё нет:
   294	HTTP-входа, вызывающего `RequestMediaUpload`/`CompleteMediaUpload`, не существует, а `Posts` лишь
   295	привязывает уже загруженные медиа по идентификатору
   296	(`app/src/Modules/Posts/Application/Post/PostContentComposer.php:75-95`, README модуля
   297	«Эксплуатация»). Поэтому зависших старых сообщений на момент выката не ожидается; на стенде перед
   298	выкатом — слить очередь и outbox. Зафиксировать как предусловие миграции.
   299	
   300	## Ответы на вопросы
   301	
   302	| Развилка | Вопрос | Ответ пользователя |
   303	|---|---|---|
   304	| 1. Объём | Что покрыть по видео/аудио? | **Полная реализация сразу**: профили/процессоры/контракты + реальное ffmpeg-транскодирование видео и аудио, бинарь ffmpeg в Docker, конфиг профилей. |
   305	| 2. Модель конверсий | Один общий профиль или на тип? | **Отдельные профили и процессоры на тип** (Image/Video/Audio). |
   306	| 3. Инструмент/среда | Чем и где обрабатывать? | **php-ffmpeg в текущем RabbitMQ-Job** (php-ffmpeg/php-ffmpeg ^1.4, обработка в outbox→Job, ffmpeg/ffprobe в Docker). |
   307	| 4а. Постер видео | Откуда параметры постера? | **Часть видео-профиля, размер от транскодирования**: процессор сам извлекает кадр и кладёт его как MediaImageConversion type=Poster; потребитель отдельный картиночный профиль для видео не передаёт. |
   308	| 4б. Объём аудио | Что входит в аудио? | **Нормализованный транскод + волна как в Telegram**: массив амплитуд для показа прогресса воспроизведения (не PNG). |
   309	
   310	Документы — вне работ по прямому указанию («Документы можно не трогать»): `MediaTypeResolver`
   311	продолжает отклонять их 422, `MediaPath`/`originalReady` для Document бросает.
   312	
   313	Проверка версии пакета: `php-ffmpeg/php-ffmpeg` последняя стабильная **1.4.0** (релиз 2026-01-19),
   314	ограничение PHP `^8.0 || … || ^8.5` — совместимо с PHP 8.5 проекта; требует установленных бинарей
   315	ffmpeg и ffprobe. Источник: Packagist (ниже). `intervention/image` остаётся `^4` (composer.json),
   316	для аудио и видео не используется.
   317	
   318	## Итог
   319	
   320	Выбранный вариант: единая модель «профиль конверсии на тип». На каждый тип — своё перечисление

 succeeded in 0ms:
   261	
   262	Выдача: `GetMediaUrlQuery.conversionType` — union трёх enum | null; `GetMediaUrlHandler` инжектит
   263	`MediaVideoConversionRepository` и `MediaAudioConversionRepository`, выбор репозитория `match` по классу
   264	enum. Новый запрос `GetAudioWaveformQuery{string mediaId}` / `GetAudioWaveformHandler` → `MediaWaveform`.
   265	
   266	Профили: фиксированные форматы (путь конверсии берёт расширение из профиля, а не из оригинала):
   267	```text
   268	NormalizedMp4H264  -> mp4 (H.264+AAC),  MIME video/mp4, .mp4
   269	NormalizedAacM4a   -> mp4/m4a (AAC),     MIME audio/mp4, .m4a
   270	постер видео       -> JPEG,              MIME image/jpeg, .jpg  (MediaImageConversion type=Poster)
   271	```
   272	
   273	Новые доменные типы:
   274	```text
   275	enum MediaAudioConversionType: string { NormalizedAacM4a = 'normalizedAacM4a' }
   276	VO  MediaSampleRate        (extends AbstractIntegerValue: MIN 8000, MAX 192000, supports())
   277	VO  MediaWaveformPeakCount (extends AbstractIntegerValue: MIN 1, MAX 4096, supports())
   278	VO  MediaWaveform          (readonly, JsonSerializable, НЕ Stringable; список int 0..255; fromPeaks(list<int>) валидирует
   279	                            количество через MediaWaveformPeakCount и диапазон значений; peaks()/equals())
   280	VO  MediaAudioConversionId (extends AbstractUuidV7Id: generate(), fromString())
   281	Entity MediaAudioConversion (role media_audio_conversion, table media_audio_conversions; колонки как у видео
   282	                            минус width/height плюс sample_rate, waveform; create(media, type, status, storage, path,
   283	                            mimeType, size, duration, bitrate, sampleRate, waveform))
   284	Collection MediaAudioConversionCollection (extends Collection<int, MediaAudioConversion>)
   285	Repository MediaAudioConversionRepository (extends AbstractRepository<MediaAudioConversion>; findByMediaId -> коллекция)
   286	Typecast   MediaWaveformTypecast (Infrastructure/Cycle, ColumnValueTypecast со статическими
   287	                            castDatabaseValue()/uncastValue(): JSON <-> MediaWaveform; без них общий
   288	                            ValueObjectCast его не вызовет; образец MediaMultipartPartCollectionTypecast)
   289	```
   290	
   291	`MediaWaveform::fromPeaks(list<int>)` валидирует И количество (через `MediaWaveformPeakCount`), И диапазон
   292	каждого значения (0..255) — две разные проверки. Это валидация при **построении** (500-путь, в процессоре);
   293	422-проверка запрошенного `waveformPeaks` из спека на приёме — отдельно, через
   294	`MediaWaveformPeakCount::supports()`. `MediaSampleRate`/`MediaWaveformPeakCount` получают `supports()` от
   295	`AbstractIntegerValue`; у `MediaWaveform` `supports()` нет (составной VO).
   296	
   297	Правки переиспользуемых типов:
   298	- `MediaDuration`/`MediaBitrate` переиспользуются для аудио — нейтрализовать их сообщения `NAME`
   299	  («Длительность видео» → «Длительность», «Bitrate видео» → «Битрейт»), иначе аудио-ошибка скажет «видео».
   300	- `MediaProcessingError`: добавить `audios` в regex фильтра путей (консистентно с `MediaPath`), чтобы путь
   301	  аудио-конверсии тоже отсеивался из текста ошибки.
   302	
   303	`Media` Entity: добавить `MediaAudioConversionCollection $audioConversions` + `HasMany`-связь и
   304	инициализацию в `create()`.
   305	
   306	`MediaPath`: в `assertValid` regex добавить `audios`; добавить фабрики
   307	`videoConversion(storageKey, MediaVideoConversionType, ext)` → `videos/{shard}/{key}/{type}.{ext}` и
   308	`audioConversion(storageKey, MediaAudioConversionType, ext)` → `audios/{shard}/{key}/{type}.{ext}`;
   309	в `originalReady` добавить `MediaType::Audio → 'audios'` (Document по-прежнему бросает). Постер видео
   310	кладётся существующей `imageConversion(storageKey, Poster, 'jpg')` → `images/{shard}/{key}/poster.jpg`.
   311	
   312	Конфиг (`app/config/media.php` + `MediaConfig`): добавить `ffmpegBinaryPath` (env
   313	`MEDIA_FFMPEG_BINARY`, default `/usr/bin/ffmpeg`), `ffprobeBinaryPath` (env `MEDIA_FFPROBE_BINARY`,
   314	default `/usr/bin/ffprobe`), `ffmpegTimeoutSeconds` (env `MEDIA_FFMPEG_TIMEOUT_SECONDS`, default 1800),
   315	`ffmpegThreads` (env `MEDIA_FFMPEG_THREADS`, default 0 = авто). Процессоры получают их через
   316	типизированный `MediaConfig` (rules.md «env() только в конфигах»). Дефолтные пути подтверждаются при
   317	сборке образа через `command -v ffmpeg`/`command -v ffprobe`.
   318	
   319	## Фазы выполнения
   320	
   321	Порядок выбран так, чтобы код собирался и тесты были зелёными после каждой фазы (фазы 1–3 — аддитивные;
   322	несовместимая смена контракта и ветвление сведены в одну фазу 4).
   323	
   324	### 1. Среда выполнения ffmpeg: зависимость, Docker, конфиг
   325	
   326	Цель: подготовить фундамент — пакет, бинарь и настройки ffmpeg доступны, без изменения поведения.
   327	
   328	Что сделать:
   329	- `composer.json`: добавить `"php-ffmpeg/php-ffmpeg": "^1.4"` в `require` (тянет `symfony/process`).
   330	- `docker/Dockerfile`: в `apt-get install` добавить пакет `ffmpeg`; в блок самопроверки — `ffmpeg
   331	  -version >/dev/null` и `ffprobe -version >/dev/null` (по образцу проверок imagick/gd).
   332	- `app/config/media.php` + `MediaConfig`: добавить четыре ffmpeg-поля (binary/probe/timeout/threads),
   333	  значения через `env()` с дефолтами.
   334	- `phpunit.xml`: добавить env `MEDIA_FFMPEG_BINARY`, `MEDIA_FFPROBE_BINARY`,
   335	  `MEDIA_FFMPEG_TIMEOUT_SECONDS`, `MEDIA_FFMPEG_THREADS`. Значения `*_BINARY`/`*_PROBE` = фактическим
   336	  путям в образе (`/usr/bin/ffmpeg`, `/usr/bin/ffprobe` из apt), чтобы интеграционные тесты фазы 3
   337	  нашли бинари (иначе процессор упадёт «ffmpeg not found»).
   338	
   339	Результат: пакет установлен, образ имеет ffmpeg/ffprobe, типизированный конфиг несёт ffmpeg-настройки.
   340	
   341	Сценарии тестирования (after_each_phase):
   342	- `MediaConfigTest` расширен: `ConfigMapper` маппит новые поля в `MediaConfig`.
   343	- Сборка Docker-образа проходит самопроверку `ffmpeg -version`/`ffprobe -version`.
   344	
   345	Проверка: `make phpstan`; `make test` (Unit/Kernel) зелёный; `composer.lock` обновлён; сборка образа
   346	успешна.
   347	
   348	### 2. Аудио-доменный стек + БД + расширение MediaPath
   349	
   350	Цель: завести все доменные типы аудио и таблицу; расширить пути; всё аддитивно, существующий поток
   351	картинок/видео не меняется.
   352	
   353	Что сделать:
   354	- `Domain/Enum/MediaAudioConversionType` (`NormalizedAacM4a`).
   355	- `Domain/ValueObject`: `MediaSampleRate`, `MediaWaveformPeakCount`, `MediaWaveform`,
   356	  `MediaAudioConversionId` (формы и границы — см. «Контракты реализации»).
   357	- `Domain/Entity/MediaAudioConversion`, `Domain/Collection/MediaAudioConversionCollection`,
   358	  `Repository/MediaAudioConversionRepository`.
   359	- `Infrastructure/Cycle/MediaWaveformTypecast`; подключить `#[Column(typecast: ...)]` на колонке
   360	  `waveform` сущности (образец `MediaMultipartPartCollectionTypecast`).
   361	- `Domain/Entity/Media`: добавить связь `audioConversions` (`HasMany`) и инициализацию пустой коллекции
   362	  в `create()`.
   363	- `Domain/ValueObject/MediaPath`: `audios` в `assertValid` regex; фабрики `videoConversion()`,
   364	  `audioConversion()`; в `originalReady` ветка `MediaType::Audio → 'audios'` (исчерпывающий `match`:
   365	  Image/Video/Audio, `Document` по-прежнему бросает `InvalidDomainValueException`).
   366	- `Domain/ValueObject/MediaProcessingError`: добавить `audios` в regex фильтра путей (консистентно с
   367	  `MediaPath`).
   368	- `Domain/ValueObject/MediaDuration`, `MediaBitrate`: нейтрализовать `NAME` («…видео» → «Длительность»,
   369	  «Битрейт»), т.к. эти VO переиспользуются аудио-результатом и сообщением об ошибке.
   370	- Миграция отдельным новым файлом в формате `YYYYMMDD.HHMMSS_0_create_media_audio_conversions_table.php`
   371	  (как `20260521.184100_0_create_media_domain_tables.php`), синтаксис через Cycle migration API
   372	  (`->addColumn(...)`, `->addIndex([...], ['unique' => true])`, `->addForeignKey(...)`); существующую
   373	  миграцию не править. Схема — см. «Данные и БД».
   374	
   375	Результат: домен и БД аудио готовы; пути для video/audio-конверсий и аудио-оригинала строятся; чтение
   376	аудио-конверсий из БД работает. Приём аудио и обработка пока не включены.
   377	
   378	Сценарии тестирования:
   379	- Unit: `MediaWaveform`/`MediaSampleRate`/`MediaWaveformPeakCount` (границы, валидация, equals,
   380	  JSON-сериализация волны), `MediaAudioConversionType`, `MediaPath` (новые фабрики, `assertValid` принимает `audios/...`, `originalReady` для Audio →
   381	  `audios/source`, отказ `originalReady` для Document).
   382	- Infrastructure: `MediaWaveformTypecast` (cast/uncast JSON ↔ VO; отказ на битом JSON, не-массиве и
   383	  значении вне 0..255 — по образцу `MediaTypecastTest`).
   384	- Feature/Repository: `MediaAudioConversionRepository::findByMediaId` возвращает коллекцию по
   385	  сохранённой `MediaAudioConversion` (через миграцию).
   386	- Доменный тест сущности `MediaAudioConversion::create`.
   387	
   388	Проверка: `make phpstan`; `make test` зелёный; миграция применяется и откатывается.
   389	
   390	### 3. Контракты и ffmpeg-процессоры + файловые методы S3 + классификация ошибок
   391	
   392	Цель: добавить реальные процессоры видео и аудио и инфраструктуру вокруг них, не трогая текущий поток
   393	обработки (процессоры пока не вызываются handler-ом).
   394	
   395	Что сделать:
   396	- `MediaFileServiceContract` + `S3MediaFileService`: `downloadToFile()`, `uploadFromFile()` (локальный
   397	  временный файл во временном каталоге; стримовое чтение/запись без полного буфера в памяти).
   398	- `Application/Contract`: `MediaVideoProcessorContract`, `MediaAudioProcessorContract`;
   399	  `Application/Dto`: `MediaVideoProcessingResult`, `MediaAudioProcessingResult`.
   400	- `Application/Exception/MediaProcessorFailedException` с `isTransient()` и фабриками
   401	  `transient()`/`permanent()` (образец `MediaFileServiceFailedException`).
   402	- `Infrastructure/FileService/FfmpegMediaVideoProcessor`, `FfmpegMediaAudioProcessor` на php-ffmpeg:
   403	  владеют временными файлами и удаляют их в `finally`; берут бинарь/таймаут/threads из `MediaConfig`;
   404	  оборачивают исключения php-ffmpeg в `MediaProcessorFailedException`; в репозитории не ходят и доменных
   405	  сценариев не строят (только download/upload через `MediaFileServiceContract` и ffmpeg). Видео: probe →
   406	  транскод mp4/H.264+AAC с фильтром масштабирования, сохраняющим пропорции, и округлением сторон до
   407	  чётных (без обрезки и паддинга); опереться на авто-ориентацию ffmpeg (применяет display matrix) и
   408	  обнулить флаг поворота на выходе → кадр-постер JPEG размером транскода. Аудио: probe → транскод m4a/AAC
   409	  → волна — прямым вызовом ffmpeg в сырой PCM (`s16le`, моно, низкая частота), чтение сэмплов, деление на
   410	  `waveformPeaks` интервалов, пик каждого, нормализация в 0..255 (php-ffmpeg готового массива амплитуд не
   411	  даёт — только PNG-волну, поэтому PCM берётся своей обёрткой над процессом ffmpeg). Классификацию
   412	  «временная/постоянная» вынести в приватный статический метод-«решатель» (`private static (\Throwable):
   413	  bool`) для юнит-теста. Процессоры инжектят `MediaFileServiceContract` и `MediaConfig`. Для 100%
   414	  покрытия (порог 100, `composer.json:90`): вызов ffmpeg обернуть в тонкую внутреннюю обёртку
   415	  (метод/интерфейс), подменяемую в тесте, чтобы покрыть ветки скачивания/ffmpeg/загрузки/`finally` без
   416	  реального таймаута; детерминированно нетестируемые строки бинаря (ветка таймаута) — `@codeCoverageIgnore`
   417	  с обоснованием.
   418	- `Infrastructure/Bootloader/MediaBootloader`: биндинги
   419	  `MediaVideoProcessorContract→FfmpegMediaVideoProcessor`,
   420	  `MediaAudioProcessorContract→FfmpegMediaAudioProcessor`.
   421	- `Presentation/Job/ProcessMediaJob`: добавить ТОЛЬКО ИЛИ-ветку в признак временной ошибки (строка
   422	  `$isTransient = ...`): `|| ($exception instanceof MediaProcessorFailedException && $exception->isTransient())`.
   423	  `safeMessage()` НЕ трогать — он уже возвращает `PROCESSING_FAILURE_MESSAGE` для любого
   424	  не-`MediaFileServiceFailedException` (отдельная ветка стала бы мёртвым кодом, rules.md «Нет мёртвого
   425	  кода»). Текст `RetryException.reason` выбирать по типу: для ошибки процессора — сообщение о повторе
   426	  обработки (отдельная константа), не `STORAGE_FAILURE_MESSAGE`.
   427	
   428	Результат: процессоры и файловые методы готовы и протестированы изолированно; Job классифицирует ошибки
   429	процессора; основной поток не изменён.
   430	
   431	Сценарии тестирования:
   432	- Feature/Infrastructure (реальный ffmpeg в Docker): фикстуры генерируются в тесте через ffmpeg `lavfi`
   433	  (`testsrc`/`sine`), без бинарных файлов в репозитории. Обязательные ветки `process()`: успех (видео →
   434	  mp4 + постер с метаданными; аудио → m4a + волна нужной длины), ошибка скачивания, ошибка ffmpeg (битый
   435	  вход → постоянная), ошибка загрузки результата, удаление временных файлов в `finally` при любом исходе,
   436	  классификация временная и постоянная.
   437	- Feature/Infrastructure: `downloadToFile`/`uploadFromFile` против MinIO (round-trip).
   438	- Unit: метод-«решатель» (фабрикуемые исключения → временная/постоянная); постоянная на битом входном
   439	  файле; `MediaProcessorFailedException`.
   440	- Feature/Flow: `ProcessMediaJobTest` дополнен — `MediaProcessorFailedException::transient()` →
   441	  `RetryException` + WARN; `permanent()` → проброс + ERROR.
   442	
   443	Проверка: пересобрать Docker-образ (фаза 1 уже добавила ffmpeg) ДО прогона тестов фазы 3, иначе
   444	ffmpeg-интеграция упадёт «ffmpeg not found». Затем `make phpstan`; `make test` зелёный (включая
   445	ffmpeg-интеграцию в Docker).
   446	
   447	### 4. Профили на тип, MediaConversionPlan, смена контрактов, ветвление обработки
   448	
   449	Цель: переключить конвейер на `MediaConversionPlan` и включить реальную обработку всех трёх типов.
   450	
   451	Что сделать:
   452	- Переименовать `MediaConversionSpec` → `MediaImageConversionSpec`; добавить `MediaVideoConversionSpec`,
   453	  `MediaAudioConversionSpec`, `MediaConversionPlan`. Обновить ВСЕ места старого имени и поля
   454	  `conversions` (иначе сьют не соберётся): `ProcessMediaCommand`, `ProcessMediaHandler` (`@param`),
   455	  `MediaUploaded`, `CompleteMediaUploadCommand`, `CompleteMediaUploadHandler::assertConversionsValid`,
   456	  `MediaApplicationTestCase::conversionSpec`, конструкторы в тестах (`ProcessMediaJobTest`,
   457	  `MediaProcessingFlowTest`, `ProcessMediaHandlerTest`, `CompleteMediaUploadHandlerTest`).
   458	- `MediaUploaded`, `ProcessMediaCommand`, `CompleteMediaUploadCommand`: `conversions` → `plan`.
   459	- `CompleteMediaUploadHandler`: валидация по типу выполняется ПОСЛЕ загрузки `media` из репозитория
   460	  (нужен `media.type`); список «не своего» типа непуст → 422; диапазоны по типу — см. «Целевой алгоритм»
   461	  (`waveformPeaks` — через `MediaWaveformPeakCount::supports()`). Обновить лог-контекст (счётчики плана
   462	  по типам).
   463	- `ProcessMediaHandler`: исчерпывающий `match (media.type)`; целевые `MediaPath` (normalized/poster)
   464	  строит сам handler через `MediaPath`-фабрики до вызова процессора. Каждую ветку вынести в приватный
   465	  метод — `buildImageConversions` (переименовать текущий `buildConversions`), `buildVideoConversions`,
   466	  `buildAudioConversions` — чтобы `handle()` остался ≤ ~40 строк (rules.md «Короткие методы»). image — из
   467	  `plan.image`; video — `videoProcessor.process(...)` → `MediaVideoConversion` + `MediaImageConversion(Poster)`;
   468	  audio — `audioProcessor.process(...)` → `MediaAudioConversion` с волной; оригиналы `copyObject` по типу.
   469	  Инжектить `MediaVideoProcessorContract`, `MediaAudioProcessorContract` (конструктор станет 7
   470	  зависимостей — допустимо, фасад не вводим). Инвариант: `processor.process` идемпотентен по перезаписи
   471	  детерминированных путей и не оставляет видимых эффектов до финального flush.
   472	- `Application/Service/MediaTypeResolver`: принимать `audio/*` → `MediaType::Audio` (Document остаётся
   473	  422). Обновить докстринг. Проверить, что приём аудио зависит только от `MediaUploadSpec.allowedMimeTypes`
   474	  потребителя и резолвера: `MediaMimeType` собственного белого списка не имеет (валидирует формат/длину),
   475	  `audio/mp4`/`audio/mpeg` проходят.
   476	- `DeleteMediaHandler`: инжектить `MediaAudioConversionRepository` и добавить третий цикл удаления
   477	  объектов аудио-конверсий из S3 (сейчас обходятся только image+video). Постер видео — это
   478	  `MediaImageConversion`, он уже попадёт в image-цикл, отдельной чистки постера не нужно (не задваивать).
   479	
   480	Результат: полный конвейер режет картинки, видео (mp4 + постер) и аудио (m4a + волна); приём аудио
   481	включён; удаление чистит все конверсии.
   482	
   483	Сценарии тестирования:
   484	- Feature/Application `ProcessMediaHandlerTest`: image/video/audio (процессоры подменяются `createStub`,
   485	  не `createMock`+`method`) — создаются верные конверсии, постер для видео, волна для аудио;
   486	  идемпотентность на `ready` (no-op) И повторный прогон video/audio после `ProcessingFailed` (перезапись
   487	  детерминированных путей без конфликта); оригинал переложен по верному пути; width/height/duration/
   488	  bitrate берутся из результата процессора.
   489	- Feature/Application `CompleteMediaUploadHandlerTest`: валидный план по типу проходит; кросс-тип → 422;
   490	  выход за диапазон по каждому типу → 422; для video/audio пустой, дублирующий или множественный профиль
   491	  → 422; в outbox кладётся `plan`.
   492	- Unit `MediaTypeResolverTest`: `audio/*` → Audio; документы/прочее → 422.
   493	- Feature/Application `DeleteMediaHandlerTest`: удаляются объекты всех конверсий, включая постер.
   494	- Feature/Flow `MediaProcessingFlowTest`: сквозной поток video и audio.
   495	- Unit (Outbox): round-trip `MediaUploaded{plan}` через `ValinorOutboxMessageSerializer` (образец
   496	  `OutboxMessageSerializerTest`) — вложенный `MediaConversionPlan` из примитивов+enum восстанавливается.
   497	
   498	Проверка: `make phpstan`; `make test` зелёный.
   499	
   500	### 5. Выдача результатов: URL на три типа + волна
   501	
   502	Цель: дать потребителю ссылки на все конверсии и числа волны.
   503	
   504	Что сделать:
   505	- `GetMediaUrlQuery.conversionType` → union трёх enum | null; `GetMediaUrlHandler` — приватный
   506	  `findConversion` через `match` по классу enum выбирает `MediaImageConversionRepository`/
   507	  `MediaVideoConversionRepository`/`MediaAudioConversionRepository`, дальше прежняя логика
   508	  `publicUrl`/`presignGet`. Инжектить новые репозитории. HTTP-входа у Media нет, поэтому выбор enum по
   509	  строке в Spiral Filter (autocast в один BackedEnum) сейчас не нужен — Query создаётся внутренними
   510	  вызовами/тестами с конкретным enum; авторазбор union отложен до появления роута.
   511	- Новый запрос `Application/Query/GetAudioWaveform`: `GetAudioWaveformQuery{mediaId}`,
   512	  `GetAudioWaveformHandler` → `MediaWaveform` (нормализованная аудио-конверсия; не ready/не аудио/нет
   513	  конверсии → `NotFoundException`).
   514	
   515	Результат: URL доступен для image/video/audio-конверсий и оригинала; волна отдаётся отдельным запросом
   516	числами.
   517	
   518	Сценарии тестирования:
   519	- Feature/Application `GetMediaUrlHandlerTest`: URL для image-, video-, audio-конверсии и оригинала;
   520	  public → `publicUrl`, private → `presignGet` с TTL; несуществующая конверсия → 404.
   521	- Feature/Application `GetAudioWaveformHandlerTest`: успех (числа волны); не-аудио/не-ready/нет
   522	  конверсии → 404.
   523	- Покрытие выдачи — Feature/Application-тестами (роут-интеграционных тестов нет, т.к. у Media нет
   524	  HTTP-роутов; правило «каждый роут покрыт» к этим Query не применяется).
   525	
   526	Проверка: `make phpstan`; `make test` зелёный.
   527	
   528	## Тесты
   529	
   530	Стратегия: **after_each_phase** — каждая фаза 1–5 завершается своими unit/feature/integration-тестами
   531	(перечислены в фазах). Дублёры берём из существующих: моки контрактов (`MediaFileServiceContract`,
   532	процессоры), `FakeS3Client`/`FakeS3ClientProvider` для S3-интеграции, `RecordingMediaLogger` для
   533	проверки уровней логов, `ThrowingProcessMediaCommandBus` для классификации в Job; база
   534	`MediaApplicationTestCase`. Реальные ffmpeg-интеграционные тесты (фаза 3) гоняются в Docker, где есть
   535	бинарь; нужны маленькие медиа-фикстуры (короткий mp4 и короткий аудио). Новые дублёры контрактов
   536	процессоров — через `createStub()` + `willReturn*()`, не `createMock()`+`method()` (rules.md «Стаб вместо
   537	expects()»; gate `failOnPhpunitDeprecation`). Классификацию ошибок ffmpeg проверяем юнит-тестом приватного
   538	статического метода-«решателя» (временная/постоянная) и интеграционно постоянная на битом входе;
   539	детерминированно нетестируемые строки вызова бинаря закрыты тонкой обёрткой или `@codeCoverageIgnore`.
   540	Раунд-трип нового `MediaUploaded{plan}` через `ValinorOutboxMessageSerializer` — отдельным unit-тестом.
   541	Финальный гейт — `make qa` (`cs` + `phpstan` + `test-coverage` с порогом 100%): покрыть все новые ветки,
   542	включая `match`-ветки по типу, валидацию по типу в `CompleteMediaUpload`, выбор репозитория в
   543	`GetMediaUrl` и оба исхода классификации ошибок.
   544	
   545	## Логирование
   546	
   547	Стратегия: **debug_precise**. Точные DEBUG-логи на каждом значимом шаге с camelCase-контекстом:
   548	- `ProcessMediaHandler`: DEBUG в начале каждой ветки `match` (`mediaId`, `type`), DEBUG на каждую
   549	  созданную конверсию (`type`, `path`, `size`), итоговый DEBUG «медиа готово» со счётчиками конверсий
   550	  по типам.
   551	- Процессоры (Infrastructure): DEBUG «оригинал скачан», DEBUG результата probe (`durationMs`,
   552	  `bitrate`, для видео `width`/`height`), DEBUG «транскод готов» (`outputSize`), DEBUG
   553	  «постер/волна извлечены», DEBUG «временные файлы удалены». Технический ffmpeg-контекст допустим во
   554	  внутреннем DEBUG, но не уходит пользователю.
   555	- `CompleteMediaUploadHandler`: существующий DEBUG + счётчики плана по типам.
   556	- `GetMediaUrlHandler`/`GetAudioWaveformHandler`: DEBUG на резолв (`mediaId`, выбранный тип).
   557	- Ошибки ffmpeg не логируются внутри процессора (он бросает типизированное исключение): WARN
   558	  (временная ошибка, повтор ожидаем) / ERROR (terminal) пишет `ProcessMediaJob` на границе — как сейчас.
   559	  INFO не используется (это не ключевые бизнес-события). Пользователю — только безопасный текст из
   560	  набора `MediaProcessingError`.
   561	
   562	## Документация и эксплуатация
   563	
   564	- README модуля Media (`app/src/Modules/Media/README.md`): добавить video/audio в таблицу профилей и
   565	  процессоров, выдачу URL для трёх типов, `GetAudioWaveform`, ffmpeg-обработку, классификацию ошибок
   566	  процессора, новые ffmpeg-настройки конфига.
   567	- `.env`-пример/документация окружения: `MEDIA_FFMPEG_BINARY`, `MEDIA_FFPROBE_BINARY`,
   568	  `MEDIA_FFMPEG_TIMEOUT_SECONDS`, `MEDIA_FFMPEG_THREADS`.
   569	- Runbook выката (предусловие миграции/релиза): несовместимая смена содержимого `MediaUploaded`
   570	  (`conversions` → `plan`). Перед выкатом **слить очередь RabbitMQ и outbox** — зависших старых
   571	  сообщений быть не должно (сквозного потребителя в рабочей среде нет). Зафиксировать как предусловие.
   572	- Принятое ограничение среды: длинные видео ограничиваются `MediaUploadSpec.maxSize` (потребитель) и
   573	  `MEDIA_FFMPEG_TIMEOUT_SECONDS`. Путь отхода при упоре — вынести транскодирование в Temporal
   574	  (отклонённый вариант research, Temporal в проекте уже поднят). Берётся как осознанный компромисс ради
   575	  единообразия с картинками.
   576	- Осиротевшие объекты при терминальной ошибке: video/audio-процессор грузит результаты в целевой бакет
   577	  ДО финального flush (как и текущий поток картинок). При постоянной ошибке после загрузки строк
   578	  конверсий в БД нет, поэтому `DeleteMedia` их не видит. Осознанное ограничение, совпадающее с поведением
   579	  картинок: пути детерминированы (повтор перезаписывает), реклейм терминальных сирот — будущий
   580	  storage-sweep (сверка префикса бакета с БД); try-catch в Handler не вводим (rules.md).
   581	- Вне этой задачи (решение пользователя): переключение ленты `Posts` на нормализованные video/audio
   582	  (mp4 + постер, m4a + волна). Сейчас лента через `FindMediaUrl` показывает оригинал; Media отдаёт
   583	  результаты своими запросами (`GetMediaUrl`/`GetAudioWaveform`), а подключение ленты — отдельная задача
   584	  через границу Media→Application. `FindMediaUrl`/`PostViewAssembler` в этой задаче не трогаем.
   585	
   586	## Изменения после мета-ревью
   587	
   588	### После моделей (haiku, sonnet, opus)
   589	
   590	- **+ Добавлено:**
   591	  - Стратегия 100% покрытия ffmpeg-процессоров: приватный статический «решатель» transient/permanent +
   592	    тонкая обёртка над php-ffmpeg для подмены в тесте + `@codeCoverageIgnore` на детерминированно
   593	    нетестируемой ветке таймаута бинаря (иначе `make qa` с порогом 100 — красный, блокер).
   594	  - Round-trip unit-тест нового `MediaUploaded{plan}` через `ValinorOutboxMessageSerializer`.
   595	  - `DeleteMediaHandler` инжектит `MediaAudioConversionRepository` и чистит аудио-объекты из S3 (постер
   596	    уже покрыт image-циклом, не задваивать).
   597	  - Предусловие: пересобрать Docker-образ с ffmpeg ДО тестов фазы 3; `MEDIA_FFMPEG_*` в `phpunit.xml` =
   598	    фактическим путям бинарей в образе.
   599	  - Тест повторного прогона video/audio после `ProcessingFailed` (идемпотентность по перезаписи) и
   600	    проверка, что `MediaMimeType` не имеет собственного аудио-whitelist.
   601	- **~ Изменено:**
   602	  - `ProcessMediaJob`: меняется ТОЛЬКО ИЛИ-ветка признака временной ошибки; `safeMessage()` не трогаем (иначе
   603	    мёртвый код).
   604	  - `ProcessMediaHandler.handle()` дробится на `buildImageConversions`/`buildVideoConversions`/
   605	    `buildAudioConversions` (≤ ~40 строк); целевые `MediaPath` строит handler, не Infrastructure-процессор.
   606	  - Перечислены ВСЕ точки переименования `MediaConversionSpec` и смены `conversions → plan` (команды,
   607	    сообщение, handler, конкретные тесты).
   608	  - `MediaDuration`/`MediaBitrate`: нейтрализовать `NAME` при переиспользовании аудио; `MediaProcessingError`
   609	    regex дополнить `audios`. Формат имени миграции — `YYYYMMDD.HHMMSS_0_`.
   610	  - Новые дублёры контрактов — через `createStub()` (gate `failOnPhpunitDeprecation`).
   611	- **− Убрано:**
   612	  - Дублирующие поля `posterWidth`/`posterHeight` из `MediaVideoProcessingResult` (= `width`/`height`
   613	    транскода по решению 3).
   614	- **Отклонено:**
   615	  - Ужесточение Docker-самопроверки до `command -v ... || exit 1` — не нужно: блок под `set -eux`,
   616	    падение `ffmpeg -version`/`ffprobe -version` и так прерывает сборку (консистентно с `php -m | grep`).
   617	  - Отдельный per-type тест unique `(media_id, type)` для idempotency insert — избыточно: ограничение
   618	    задаёт уникальный индекс схемы, повтор уже покрыт тестом повторного прогона.
   619	
   620	## Реакция на ревью

exec
/bin/zsh -lc "nl -ba docs/arch.md | sed -n '1,260p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
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
   221	          Message/                # Event store, message loader, message serializer
   222	          Registry/               # Реестр пары message -> Job
   223	          Cycle/                  # Typecast outbox-полей
   224	        Presentation/
   225	          Console/                # outbox:relay
   226	          Job/                    # Технические Job outbox
   227	
   228	      System/
   229	        Infrastructure/
   230	          Bootloader/             # SystemBootloader: регистрация namespace `system` для view
   231	        Presentation/
   232	          Http/                   # Health, Swagger UI, OpenAPI YAML route
   233	          Console/                # openapi:* команды
   234	          Exception/              # Исключения слоя Presentation (публикация ассетов OpenAPI)
   235	          Temporal/               # технические workflow
   236	          views/                  # twig-шаблоны модуля (swagger/index), namespace `system`
   237	
   238	    Shared/
   239	      Domain/
   240	        Exception/                # Общие доменные исключения
   241	        Trait/                    # Общие доменные трейты
   242	        ValueObject/              # Общие базовые VO и общие идентификаторы
   243	      Presentation/
   244	        Http/
   245	          Resource/               # Общие базовые API-ресурсы
   246	      Infrastructure/
   247	        Cache/
   248	        Configuration/            # Типизированные config DTO и ConfigMapper
   249	        Cycle/                    # Общие typecast-классы
   250	        Exception/                # Общие инфраструктурные исключения (config mapping)
   251	        Framework/                # Spiral Kernel, bootloaders, routes
   252	```
   253	
   254	## Правила зависимостей
   255	
   256	Внешние слои могут зависеть от внутренних, внутренние не зависят от внешних.
   257	
   258	```text
   259	Presentation   -> Application своего модуля, Response/Resource, framework attributes
   260	Application    -> Domain, Repository своего модуля, Contract своего модуля

codex
Контекст документов собран. Теперь смотрю фактическое рабочее дерево: изменённые файлы, новые файлы Media, миграцию и тесты, чтобы проверить пункты ревью по строкам.
exec
/bin/zsh -lc "nl -ba docs/arch.md | sed -n '260,520p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
   260	Application    -> Domain, Repository своего модуля, Contract своего модуля
   261	Repository     -> Domain, Cycle ORM
   262	Infrastructure -> Contract своего модуля, framework/runtime libraries, external libraries
   263	Domain         -> PHP standard library, свой Domain, Shared/Domain
   264	Shared         -> общий доменный и инфраструктурный код без привязки к одному модулю
   265	```
   266	
   267	Осознанное исключение: типизированный `TypedConfig` из
   268	`Shared/Infrastructure/Configuration` может инжектиться напрямую в Application-Handler,
   269	когда сценарию нужны инфра-дефолты (staging-TTL, пороги, размеры, драйвер). Пример —
   270	`MediaConfig` в `RequestMediaUploadHandler` модуля `Media`.
   271	Формально `Shared/Infrastructure` не входит в список зависимостей Application выше, но
   272	`TypedConfig` — это не технический сервис с поведением и не зависимость от чужого модуля:
   273	правила (`rules.md` «Typed config для каждого config-файла») и эта же `arch.md`
   274	(«Configuration → app/config → Shared/Infrastructure/Configuration») предписывают единое
   275	размещение всех config-DTO в `Shared/Infrastructure/Configuration`. Оборачивать такой
   276	config-DTO в Application-`*Contract` и привязывать его в бутлоадере означало бы создать
   277	pass-through-обёртку над `TypedConfig` ради формального списка — это запрещённый паттерн
   278	(ср. «Без pass-through typecast-обёрток») и сделало бы модуль единственным, кто прячет
   279	собственный typed-config за контрактом. Поэтому прямая инъекция `TypedConfig` в Application
   280	допускается явно. Если Application нужен именно технический сервис с поведением (S3,
   281	процессор, внешний клиент) — он по-прежнему идёт через `Application/Contract` + реализацию
   282	в `Infrastructure`, без исключений.
   283	
   284	## Взаимодействие слоёв
   285	
   286	### Поток HTTP-команды
   287	
   288	```text
   289	HTTP Request
   290	  -> RoadRunner
   291	    -> Spiral HTTP middleware
   292	      -> Modules/{Module}/Presentation/Http/Controller
   293	        -> Modules/{Module}/Presentation/Http/Filter
   294	        -> Modules/{Module}/Application/Command DTO
   295	        -> CommandBus::dispatch(command, handler)
   296	          -> Handler::handle(Command)
   297	            -> Modules/{Module}/Domain ValueObject
   298	            -> Modules/{Module}/Domain Entity
   299	            -> Modules/{Module}/Repository
   300	            -> EntityManager::run()
   301	        -> Modules/{Module}/Presentation/Http/Resource
   302	        -> Response
   303	      -> JSON Response
   304	```
   305	
   306	### Поток HTTP-запроса
   307	
   308	```text
   309	HTTP Request
   310	  -> RoadRunner
   311	    -> Spiral HTTP middleware
   312	      -> Modules/{Module}/Presentation/Http/Controller
   313	        -> Modules/{Module}/Presentation/Http/Filter
   314	        -> Modules/{Module}/Application/Query DTO
   315	        -> QueryBus::dispatch(query, handler)
   316	          -> Handler::handle(Query)
   317	            -> Modules/{Module}/Repository read method
   318	        -> Modules/{Module}/Presentation/Http/Resource
   319	        -> Response
   320	      -> JSON Response
   321	```
   322	
   323	## API-документация
   324	
   325	API-документация генерируется автоматически из типизированного HTTP-слоя:
   326	контроллеров, Filter DTO, Response DTO, Resource-классов, enum-ов и API
   327	attributes в `Modules/{Module}/Presentation/Http`. OpenAPI-спецификация строится из кода, а
   328	Swagger используется как UI для её просмотра.
   329	
   330	Генератор OpenAPI живёт в переносимом Composer-пакете `packages/spiral-openapi` с
   331	namespace `GianTiaga\SpiralOpenApi`. Приложение не содержит логики статического разбора:
   332	оно только собирает типизированный `OpenApiConfig`, задаёт mapping базовых
   333	response wrappers и вызывает пакет через команду `openapi:generate`. YAML
   334	записывается в `public/openapi/openapi.yml`, а Swagger UI по `/api/docs` читает
   335	тот же файл через route `/api/docs/openapi.yml`.
   336	
   337	Генератор сканирует все модули приложения: `openapi.sourcePath` = `app/src/Modules`,
   338	`openapi.apiNamespace` = `App\Modules`. Он отбирает классы по префиксу namespace и строит
   339	операции только из публичных методов с атрибутом `#[Route]`. Поэтому любой модуль с
   340	`#[Route]`-контроллером автоматически попадает в публичную спецификацию. Если контроллер не
   341	должен публиковаться (внутренний/служебный эндпоинт), его исключают через
   342	`#[OpenApi(ignore: true)]`, а не через сужение области сканирования.
   343	
   344	`packages/spiral-openapi` использует Spiral translator только для стандартных описаний
   345	response по ключам `gian_tiaga.spiral_openapi.successful_response` и
   346	`gian_tiaga.spiral_openapi.api_error`. Описания из `#[OpenApi(description: ...)]` и
   347	PHPDoc остаются текстом приложения. YAML статический, поэтому язык выбирается во
   348	время команды генерации.
   349	
   350	## API-ошибки
   351	
   352	Общая обработка API-ошибок живёт в переносимом Composer-пакете
   353	`packages/spiral-api-errors` с namespace `GianTiaga\SpiralApiErrors`. Пакет зависит от
   354	`packages/spiral-openapi`, потому что возвращает `ErrorResponse` и
   355	`ValidationErrorResponse`.
   356	
   357	Общие доменные исключения остаются в приложении в `App\Shared\Domain\Exception` и
   358	расширяют `\DomainException`. Переводимые 4xx (`NotFoundException`, `ForbiddenException`,
   359	`ValidationException`, `AuthenticationException`) расширяют его через общий абстрактный
   360	`DomainTranslatableException`, который реализует
   361	`GianTiaga\SpiralApiErrors\Exception\TranslatableException` и несёт ключ перевода с
   362	параметрами вместо готовой строки. `InvalidDomainValueException` (500) переводимым не
   363	является. Ожидаемые клиентские ошибки с кодами 4xx
   364	`GianTiaga\SpiralApiErrors\Interceptor\ApiExceptionInterceptor` превращает в JSON
   365	`{"message":"...","code":...}` без логирования; для `TranslatableException` сообщение
   366	переводится на границе в локали текущего запроса (`translationKey()` в домене
   367	`translationDomain()`), иначе берётся `getMessage()`. Каталоги переводов приложения
   368	разбиты по модулям: `translationDomain()` выводит домен (= файл `app/locale/{lang}/{domain}.php`)
   369	из второго сегмента ключа — `app.media.*` → `media.php`, `app.system.*` → `system.php`,
   370	ключи без модульного сегмента — домен `messages`. Доменные исключения без
   371	поддерживаемого 4xx-кода, включая `InvalidDomainValueException`, считаются
   372	внутренними ошибками: HTTP-ответ получает обычное сообщение 500, а исходное
   373	сообщение исключения пользователю не отдаётся. Ошибки Spiral Filter рендерятся через
   374	`GianTiaga\SpiralApiErrors\Filter\ApiValidationErrorsRenderer` по ключу
   375	`gian_tiaga.spiral_api_errors.validation_error` в JSON
   376	`{"message":"Validation error","code":422,"errors":[...]}` или
   377	`{"message":"Ошибка валидации","code":422,"errors":[...]}` в зависимости от
   378	текущего locale.
   379	
   380	`ApiExceptionInterceptor` работает только внутри цепочки controller/action.
   381	Ненайденные маршруты возникают раньше controller/action, поэтому их обрабатывает
   382	`GianTiaga\SpiralApiErrors\Middleware\RouteNotFoundMiddleware` в глобальной HTTP-цепочке.
   383	Он переводит ключ `gian_tiaga.spiral_api_errors.route_not_found` и возвращает JSON
   384	`{"message":"Route not found.","code":404}` или
   385	`{"message":"Маршрут не найден.","code":404}` в зависимости от текущего locale.
   386	Ошибки bootstrap и middleware, не связанные с router 404, остаются в зоне
   387	стандартного Spiral error handler.
   388	
   389	`packages/spiral-api-errors` и `packages/spiral-openapi` только читают текущий locale Spiral
   390	translator. Выбор языка пользователя реализован в request-слое приложения:
   391	`App\Shared\Infrastructure\Framework\Middleware\LocaleMiddleware` определяет локаль запроса
   392	из заголовка `Accept-Language`, пересечённого с белым списком `LocaleConfig.supported`
   393	(иначе `LocaleConfig.default`), и выставляет её в Spiral translator до контроллера и до
   394	`RouteNotFoundMiddleware`. Для не-HTTP контекстов (queue/console/Temporal) per-request
   395	локали нет — там перевод на границе не выполняется, исключение несёт ключ.
   396	
   397	### Поток консольной команды
   398	
   399	```text
   400	Console command
   401	  -> input arguments/options
   402	  -> Modules/{Module}/Application/Command DTO
   403	  -> CommandBus::dispatch(command, handler)
   404	  -> Handler::handle(Command)
   405	  -> console output
   406	```
   407	
   408	Console command не содержит бизнес-логику. Он только читает CLI-ввод,
   409	создаёт Command/Query и выводит результат.
   410	
   411	### Поток задачи очереди
   412	
   413	```text
   414	Queue payload
   415	  -> Modules/{Module}/Presentation/Job handler
   416	  -> Payload DTO
   417	  -> Modules/{Module}/Application/Command DTO
   418	  -> CommandBus::dispatch(command, handler)
   419	  -> Handler::handle(Command)
   420	```
   421	
   422	Job handler - технический адаптер очереди. Бизнес-сценарий находится в
   423	Application Handler.
   424	
   425	### Поток Temporal
   426	
   427	```text
   428	Temporal Workflow / Activity
   429	  -> Modules/{Module}/Presentation/Temporal adapter
   430	  -> Modules/{Module}/Application Command или Query
   431	  -> Bus
   432	  -> Handler
   433	```
   434	
   435	Temporal workflow/activity не становится доменным слоем. Это внешний runtime
   436	adapter, как HTTP controller или queue job.
   437	
   438	## Правила CQRS
   439	
   440	CQRS используется на уровне use-case-ов: Command изменяет состояние, Query читает
   441	данные. Каждый use-case живёт в отдельной папке действия. Пока в модуле одна
   442	область (Area), действие лежит прямо в корне:
   443	`Modules/{Module}/Application/Command/{Action}` или
   444	`Modules/{Module}/Application/Query/{Action}`. Уровень области добавляется только
   445	когда областей становится несколько — тогда действия группируются по областям:
   446	`Modules/{Module}/Application/Command/{Area}/{Action}` или
   447	`Modules/{Module}/Application/Query/{Area}/{Action}`.
   448	
   449	Command flow:
   450	
   451	```text
   452	Command DTO
   453	  -> CommandBus::dispatch(command, handler)
   454	  -> Handler::handle(Command)
   455	  -> Domain types
   456	  -> Entity
   457	  -> Repository
   458	  -> EntityManager::run()
   459	```
   460	
   461	Query flow:
   462	
   463	```text
   464	Query DTO
   465	  -> QueryBus::dispatch(query, handler)
   466	  -> Handler::handle(Query)
   467	  -> Repository read method
   468	  -> Entity / typed collection / Result DTO / PaginatedResult<T>
   469	```
   470	
   471	Command Handler создаёт VO, enum и доменные коллекции до передачи данных в
   472	Entity. Примитивы не передаются в `Entity::create()` и доменные методы Entity.
   473	
   474	Command Handler возвращает Entity, Result DTO или `void`. Query Handler
   475	возвращает Entity, типизированную коллекцию, Result DTO или `PaginatedResult<T>`.
   476	Query не изменяет состояние и не оборачивается в транзакцию. Если Query
   477	Handler помечен `#[Transactional]`, это ошибка контракта.
   478	
   479	Data Grid не используется как бизнесовый Query-слой. Фильтрация и сортировка в
   480	Query Handler должны быть явными.
   481	
   482	## Архитектура шины
   483	
   484	Инфраструктура шины живёт в локальном Composer-пакете `packages/spiral-cqrs` с namespace
   485	`GianTiaga\SpiralCqrs`. Приложение подключает `GianTiaga\SpiralCqrs\CommandBusInterface` и
   486	`GianTiaga\SpiralCqrs\QueryBusInterface` через Spiral DI-контейнер. Прикладные Command,
   487	Query и Handler остаются в модулях приложения.
   488	
   489	Bus принимает DTO и first-class callable на `Handler::handle(...)`. Return type
   490	берётся из `Handler::handle()`, поэтому PHPStan и IDE видят точный тип
   491	результата без ручного приведения.
   492	
   493	```php
   494	/**
   495	 * @template TCommand of object
   496	 * @template TResult
   497	 * @param TCommand $command
   498	 * @param callable(TCommand): TResult $handler
   499	 * @return TResult
   500	 */
   501	public function dispatch(object $command, callable $handler);
   502	```
   503	
   504	```text
   505	CommandBus: Command DTO -> Handler::handle(Command)
   506	QueryBus:   Query DTO -> Handler::handle(Query)
   507	```
   508	
   509	`#[Transactional]` на `Handler::handle()` включает транзакцию для Command. Если
   510	атрибута нет, Command выполняется без транзакции. `#[NonTransactional]` не
   511	используется.
   512	
   513	`#[LogOperation]` на `Handler::handle()` включает debug-лог старта и времени
   514	выполнения операции. Если атрибута нет, operation-log не пишется.
   515	
   516	CQRS-bus не проверяет конкретные классы атрибутов. Он собирает все атрибуты
   517	`Handler::handle()`, которые наследуются от
   518	`GianTiaga\SpiralCqrs\Attribute\HandlerMiddlewareAttribute`, берёт из них middleware-класс
   519	и создаёт middleware через контейнер. Зависимости вроде `LoggerInterface` или
   520	`DatabaseInterface` получает сам middleware через constructor injection, а не

exec
/bin/zsh -lc 'git diff --stat HEAD' in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
 .env.sample                                        |   5 +
 app/config/media.php                               |  12 +
 app/locale/en/media.php                            |   7 +
 app/locale/ru/media.php                            |   7 +
 .../CompleteMediaUploadCommand.php                 |   7 +-
 .../CompleteMediaUploadHandler.php                 |  99 +++++-
 .../Command/DeleteMedia/DeleteMediaHandler.php     |   8 +
 .../Command/ProcessMedia/ProcessMediaCommand.php   |   7 +-
 .../Command/ProcessMedia/ProcessMediaHandler.php   | 176 +++++++++--
 .../Contract/MediaFileServiceContract.php          |  18 ++
 .../Application/Dto/MediaConversionResult.php      |   2 +-
 ...ersionSpec.php => MediaImageConversionSpec.php} |   8 +-
 .../Media/Application/Message/MediaUploaded.php    |  11 +-
 .../Query/GetMediaUrl/GetMediaUrlHandler.php       |  27 +-
 .../Query/GetMediaUrl/GetMediaUrlQuery.php         |   4 +-
 .../Application/Service/MediaTypeResolver.php      |  10 +-
 app/src/Modules/Media/Domain/Entity/Media.php      |  11 +
 .../Media/Domain/ValueObject/MediaBitrate.php      |   2 +-
 .../Media/Domain/ValueObject/MediaDuration.php     |   2 +-
 .../Modules/Media/Domain/ValueObject/MediaPath.php |  41 ++-
 .../Domain/ValueObject/MediaProcessingError.php    |   2 +-
 .../Infrastructure/Bootloader/MediaBootloader.php  |   6 +
 .../FileService/S3MediaFileService.php             |  39 +++
 .../Media/Presentation/Job/ProcessMediaJob.php     |  23 +-
 app/src/Modules/Media/README.md                    |  85 +++--
 .../Configuration/Media/MediaConfig.php            |   4 +
 composer.json                                      |   1 +
 composer.lock                                      | 346 +++++++++++++++++++--
 docker/Dockerfile                                  |   3 +
 phpunit.xml                                        |   4 +
 .../Application/CompleteMediaUploadHandlerTest.php | 290 ++++++++++++++++-
 .../Media/Application/DeleteMediaHandlerTest.php   |  62 ++++
 .../Media/Application/GetMediaUrlHandlerTest.php   | 105 +++++++
 .../Media/Application/MediaApplicationTestCase.php |  69 +++-
 .../Media/Application/ProcessMediaHandlerTest.php  | 197 ++++++++++--
 .../Application/RequestMediaUploadHandlerTest.php  |   4 +
 .../Modules/Media/Flow/MediaProcessingFlowTest.php | 141 ++++++++-
 .../Modules/Media/Flow/ProcessMediaJobTest.php     |  25 +-
 .../Infrastructure/S3MediaFileServiceTest.php      |  32 ++
 .../Media/Repository/MediaRepositoryTest.php       |  41 +++
 .../Configuration/MediaConfigTest.php              |  16 +
 .../Media/Application/MediaTypeResolverTest.php    |   6 +-
 .../Media/Domain/Entity/MediaEntityTest.php        |  38 +++
 .../Modules/Media/Domain/Enum/MediaEnumTest.php    |   6 +
 .../Domain/ValueObject/MediaValueObjectTest.php    |  61 +++-
 .../Infrastructure/Cycle/MediaTypecastTest.php     |  33 ++
 .../ImagickMediaImageProcessorTest.php             |   4 +
 .../Infrastructure/S3MediaFileServiceErrorTest.php |  22 ++
 todo                                               |   4 +-
 49 files changed, 1947 insertions(+), 186 deletions(-)

exec
/bin/zsh -lc 'git status --short' in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
 M .env.sample
 M app/config/media.php
 M app/locale/en/media.php
 M app/locale/ru/media.php
 M app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadCommand.php
 M app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php
 M app/src/Modules/Media/Application/Command/DeleteMedia/DeleteMediaHandler.php
 M app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaCommand.php
 M app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php
 M app/src/Modules/Media/Application/Contract/MediaFileServiceContract.php
 M app/src/Modules/Media/Application/Dto/MediaConversionResult.php
RM app/src/Modules/Media/Application/Dto/MediaConversionSpec.php -> app/src/Modules/Media/Application/Dto/MediaImageConversionSpec.php
 M app/src/Modules/Media/Application/Message/MediaUploaded.php
 M app/src/Modules/Media/Application/Query/GetMediaUrl/GetMediaUrlHandler.php
 M app/src/Modules/Media/Application/Query/GetMediaUrl/GetMediaUrlQuery.php
 M app/src/Modules/Media/Application/Service/MediaTypeResolver.php
 M app/src/Modules/Media/Domain/Entity/Media.php
 M app/src/Modules/Media/Domain/ValueObject/MediaBitrate.php
 M app/src/Modules/Media/Domain/ValueObject/MediaDuration.php
 M app/src/Modules/Media/Domain/ValueObject/MediaPath.php
 M app/src/Modules/Media/Domain/ValueObject/MediaProcessingError.php
 M app/src/Modules/Media/Infrastructure/Bootloader/MediaBootloader.php
 M app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php
 M app/src/Modules/Media/Presentation/Job/ProcessMediaJob.php
 M app/src/Modules/Media/README.md
 M app/src/Shared/Infrastructure/Configuration/Media/MediaConfig.php
 M composer.json
 M composer.lock
 M docker/Dockerfile
 M phpunit.xml
 M tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php
 M tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php
 M tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php
 M tests/Feature/Modules/Media/Application/MediaApplicationTestCase.php
 M tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php
 M tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php
 M tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php
 M tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php
 M tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php
 M tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php
 M tests/Kernel/Shared/Infrastructure/Configuration/MediaConfigTest.php
 M tests/Unit/Modules/Media/Application/MediaTypeResolverTest.php
 M tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php
 M tests/Unit/Modules/Media/Domain/Enum/MediaEnumTest.php
 M tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php
 M tests/Unit/Modules/Media/Infrastructure/Cycle/MediaTypecastTest.php
 M tests/Unit/Modules/Media/Infrastructure/ImagickMediaImageProcessorTest.php
 M tests/Unit/Modules/Media/Infrastructure/S3MediaFileServiceErrorTest.php
 M todo
?? app/database/migrations/20260620.222100_0_create_media_audio_conversions_table.php
?? app/src/Modules/Media/Application/Contract/MediaAudioProcessorContract.php
?? app/src/Modules/Media/Application/Contract/MediaVideoProcessorContract.php
?? app/src/Modules/Media/Application/Dto/MediaAudioConversionSpec.php
?? app/src/Modules/Media/Application/Dto/MediaAudioProcessingResult.php
?? app/src/Modules/Media/Application/Dto/MediaConversionPlan.php
?? app/src/Modules/Media/Application/Dto/MediaVideoConversionSpec.php
?? app/src/Modules/Media/Application/Dto/MediaVideoProcessingResult.php
?? app/src/Modules/Media/Application/Exception/MediaProcessorFailedException.php
?? app/src/Modules/Media/Application/Query/GetAudioWaveform/
?? app/src/Modules/Media/Domain/Collection/MediaAudioConversionCollection.php
?? app/src/Modules/Media/Domain/Entity/MediaAudioConversion.php
?? app/src/Modules/Media/Domain/Enum/MediaAudioConversionType.php
?? app/src/Modules/Media/Domain/ValueObject/MediaAudioConversionId.php
?? app/src/Modules/Media/Domain/ValueObject/MediaSampleRate.php
?? app/src/Modules/Media/Domain/ValueObject/MediaWaveform.php
?? app/src/Modules/Media/Domain/ValueObject/MediaWaveformPeakCount.php
?? app/src/Modules/Media/Infrastructure/Cycle/MediaWaveformTypecast.php
?? app/src/Modules/Media/Infrastructure/FileService/AbstractFfmpegMediaProcessor.php
?? app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaAudioProcessor.php
?? app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaVideoProcessor.php
?? app/src/Modules/Media/Infrastructure/FileService/NativeAacAudioFormat.php
?? app/src/Modules/Media/Repository/MediaAudioConversionRepository.php
?? docs/executions/2026-06-20_22-19_media-video-audio-ffmpeg.md
?? docs/plans/2026-06-20_21-48_media-video-audio-ffmpeg.md
?? docs/plans/2026-06-20_21-48_media-video-audio-ffmpeg_review.md
?? docs/researches/2026-06-20_11-14_media-video-audio-conversions.md
?? docs/researches/2026-06-20_11-14_media-video-audio-conversions_review.md
?? docs/reviews/2026-06-22_01-01_media-video-audio-ffmpeg.md
?? docs/reviews/2026-06-22_01-01_media-video-audio-ffmpeg_cli_meta.md
?? tests/Feature/Modules/Media/Application/GetAudioWaveformHandlerTest.php
?? tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php
?? tests/Unit/Modules/Media/Application/MediaUploadedSerializationTest.php
?? tests/Unit/Modules/Media/Infrastructure/FfmpegMediaProcessorTest.php
?? tests/Unit/Modules/Media/Infrastructure/Fixture/RecordingAudioProcessor.php
?? tests/Unit/Modules/Media/Infrastructure/Fixture/RecordingVideoProcessor.php

exec
/bin/zsh -lc "rg -n \"Query Handler|Result DTO|PaginatedResult|CQRS|handle\\(\\)\" docs/arch.md docs/rules.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc "rg --files app/src/Modules/Media app/database/migrations tests | rg 'Media|media|ffmpeg|audio|video|2026'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
docs/rules.md:16:- **Короткие методы**: `handle()` в Handler — не более ~40 строк. Длиннее — выносить в приватные методы.
docs/rules.md:43:- **ValueObject для доменных примитивов**: email, пароль, slug, subdomain, имена, идентификаторы, счётчики, флаги состояния и другие одиночные значения внутри Entity — не голые примитивы, а `readonly class` в `Modules/{Module}/Domain/ValueObject` или enum для закрытого набора вариантов. Общие базовые VO и общие идентификаторы, которые не принадлежат одному модулю, лежат в `Shared/Domain/ValueObject`. Простой скалярный VO: `readonly`, `Stringable`, `JsonSerializable`, приватный конструктор, фабричный метод с валидацией, метод для чтения значения (`value()`, `toString()` или другой явный доменный метод) и `equals()`. Составной VO для JSON-значения может не быть `Stringable`, если у него нет естественного безопасного строкового представления; при этом он всё равно должен быть `readonly`, `JsonSerializable`, создаваться через фабрику, валидировать входные значения и иметь `equals()`. Вспомогательные payload/DTO для JSON не кладём в `Domain/ValueObject`, если они сами не являются полноценными VO. VO не реализует инфраструктурные интерфейсы и не зависит от Cycle ORM. Гидрация и запись VO в БД настраиваются в инфраструктурном typecast-слое: для простых VO общий `ValueObjectCast` может использовать публичные методы VO по соглашению, для сложных VO создаётся отдельный typecast-класс в `Infrastructure`. Entity хранит VO-свойства нативно (`public private(set) Email $email`) с `#[Column(typecast: Email::class)]` или с отдельным typecast-классом, без промежуточных raw-полей. CQRS Command принимает примитивы на внешней границе, Handler создаёт VO до вызова `Entity::create()` или доменных методов Entity.
docs/rules.md:54:- **Консольные команды — тонкие обёртки**: консольная команда только собирает ввод (аргументы, опции) и делегирует в соответствующий CQRS Command+Handler. Вся бизнес-логика — в Handler, консольная команда не содержит логики кроме маппинга ввода в Command DTO и вывода результата. Формат атрибута `#[AsCommand]` см. в примере консольной команды в `docs/code-examples.md`.
docs/rules.md:71:- **Контроллеры — тонкие обёртки**: контроллер не содержит бизнес-логики и логики выборки. Контроллер только: принимает Filter, вызывает Command Handler (для записи) или Query Handler (для чтения), маппит результат в Resource и оборачивает в Response-класс. Никаких `WHERE`-условий, `if-else`, подсчётов, ручной пагинации — вся логика выборки в Query Handler.
docs/rules.md:72:- **CQRS: Command + Query**: запись через Command, чтение через Query; каждая операция — DTO + Handler. Выборку пишем явно в репозиториях, без Data Grid.
docs/rules.md:100:- **`entityManager->run()` вызывается в Handler-е**: каждый Handler сам вызывает `$this->entityManager->persist()` / `$this->entityManager->delete()` и затем `$this->entityManager->run()` для flush. Транзакцию вокруг dispatch включает только `#[Transactional]` на `Handler::handle()`. Если атрибута нет, dispatch выполняется без транзакции. `#[NonTransactional]` не используется. `run()` остаётся внутри Handler-а, чтобы сценарий явно управлял моментом flush. Исключение — технический `outbox:relay`: это инфраструктурный orchestrator после commit-а бизнес-транзакции, поэтому он сам фиксирует claim, publish-failure и queue-status переходы.
docs/rules.md:104:- **CQRS: группировка по действию**: внутри `Modules/{Module}/Application/Command/` и `Modules/{Module}/Application/Query/` каждое действие выносится в отдельную подпапку. Папка называется по действию (без суффикса Command/Query). Пока в модуле одна область (Area), подпапка действия лежит прямо в корне `Command/`/`Query/`; уровень области `{Area}` добавляется только когда областей становится несколько. Пример: `Modules/Auth/Application/Command/Login/LoginCommand.php`, `LoginHandler.php`, `LoginResult.php`. Это даёт чёткую изоляцию: все файлы одного use-case лежат рядом. Namespace соответствует: `App\Modules\Auth\Application\Command\Login`.
docs/rules.md:105:- **CQRS: полное имя действия в имени класса**: имя Command/Query/Handler/Filter должно содержать полный контекст действия, включая доменную сущность, даже если namespace уже содержит домен. Пример: `UpdateUserProfileCommand` (не `UpdateProfileCommand`), `GetUserProfileQuery` (не `GetProfileQuery`), `UpdateUserProfileFilter` (не `UpdateProfileFilter`). Папка действия совпадает: `Application/Command/UpdateUserProfile/`, `Application/Query/GetUserProfile/`. Это делает класс самодокументируемым — по имени сразу понятно, что он делает, без заглядывания в namespace.
docs/arch.md:1:# Архитектура: модульный монолит на Spiral + CQRS + тактический DDD
docs/arch.md:438:## Правила CQRS
docs/arch.md:440:CQRS используется на уровне use-case-ов: Command изменяет состояние, Query читает
docs/arch.md:468:  -> Entity / typed collection / Result DTO / PaginatedResult<T>
docs/arch.md:474:Command Handler возвращает Entity, Result DTO или `void`. Query Handler
docs/arch.md:475:возвращает Entity, типизированную коллекцию, Result DTO или `PaginatedResult<T>`.
docs/arch.md:480:Query Handler должны быть явными.
docs/arch.md:490:берётся из `Handler::handle()`, поэтому PHPStan и IDE видят точный тип
docs/arch.md:509:`#[Transactional]` на `Handler::handle()` включает транзакцию для Command. Если
docs/arch.md:513:`#[LogOperation]` на `Handler::handle()` включает debug-лог старта и времени
docs/arch.md:516:CQRS-bus не проверяет конкретные классы атрибутов. Он собирает все атрибуты
docs/arch.md:517:`Handler::handle()`, которые наследуются от
docs/arch.md:523:Lifecycle hooks и after-commit callbacks не входят в CQRS-bus. Внешние
docs/arch.md:657:Logging       -> CQRS attributes / infrastructure adapters

 succeeded in 0ms:
app/src/Modules/Media/Application/Exception/MediaFileServiceFailedException.php
app/src/Modules/Media/Application/Exception/MediaProcessorFailedException.php
tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php
app/src/Modules/Media/Application/Query/CheckMediaIsImage/CheckMediaIsImageHandler.php
app/src/Modules/Media/Application/Query/CheckMediaIsImage/CheckMediaIsImageQuery.php
tests/Feature/Modules/Media/Flow/Fixture/RecordingMediaLogger.php
tests/Feature/Modules/Media/Flow/Fixture/ThrowingProcessMediaCommandBus.php
app/database/migrations/20260613.130000_0_create_notification_domain_tables.php
app/database/migrations/20260613.143902_0_create_access_domain_tables.php
app/database/migrations/20260616.180010_0_add_device_to_auth_tokens.php
app/database/migrations/20260525.153700_0_create_outbox_events_table.php
app/database/migrations/20260620.222100_0_create_media_audio_conversions_table.php
app/database/migrations/20260613.143901_0_create_user_domain_tables.php
app/database/migrations/20260521.184100_0_create_media_domain_tables.php
app/database/migrations/20260617.160942_0_create_posts_domain_tables.php
app/database/migrations/20260615.141700_0_create_auth_domain_tables.php
app/src/Modules/Media/Application/Query/GetAudioWaveform/GetAudioWaveformHandler.php
app/src/Modules/Media/Application/Query/GetAudioWaveform/GetAudioWaveformQuery.php
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php
app/src/Modules/Media/Presentation/Job/ProcessMediaJob.php
app/src/Modules/Media/Application/Service/MediaTypeResolver.php
app/src/Modules/Media/Application/Query/GetMediaUrl/GetMediaUrlQuery.php
app/src/Modules/Media/Application/Query/GetMediaUrl/GetMediaUrlHandler.php
tests/Feature/Modules/Media/Application/MakeMediaPermanentHandlerTest.php
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php
tests/Feature/Modules/Media/Application/GetAudioWaveformHandlerTest.php
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php
tests/Feature/Modules/Media/Application/MediaApplicationTestCase.php
tests/Feature/Modules/Media/Application/RecordMediaProcessingFailureHandlerTest.php
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php
tests/Feature/Modules/Media/Application/CheckMediaAttachableHandlerTest.php
tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php
app/src/Modules/Media/Application/Query/FindMediaUrl/FindMediaUrlHandler.php
app/src/Modules/Media/Application/Query/FindMediaUrl/FindMediaUrlQuery.php
tests/Kernel/Shared/Infrastructure/Configuration/MediaConfigTest.php
tests/Kernel/Shared/Infrastructure/Configuration/MediaStorageConfigTest.php
app/src/Modules/Media/Application/Message/MediaUploaded.php
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadCommand.php
app/src/Modules/Media/Application/Query/CheckMediaExists/CheckMediaExistsHandler.php
app/src/Modules/Media/Application/Query/CheckMediaExists/CheckMediaExistsQuery.php
app/src/Modules/Media/Application/Contract/MediaImageProcessorContract.php
app/src/Modules/Media/Application/Contract/MediaVideoProcessorContract.php
app/src/Modules/Media/Application/Contract/MediaAudioProcessorContract.php
app/src/Modules/Media/Application/Contract/MediaFileServiceContract.php
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadCommand.php
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php
app/src/Modules/Media/Application/Query/CheckMediaAttachable/CheckMediaAttachableQuery.php
app/src/Modules/Media/Application/Query/CheckMediaAttachable/MediaAttachableResult.php
app/src/Modules/Media/Application/Query/CheckMediaAttachable/CheckMediaAttachableHandler.php
app/src/Modules/Media/Application/Dto/MediaVideoProcessingResult.php
app/src/Modules/Media/Application/Dto/MediaUploadSpec.php
app/src/Modules/Media/Application/Dto/MediaPresignedPart.php
app/src/Modules/Media/Application/Dto/MediaObjectHead.php
app/src/Modules/Media/Application/Dto/MediaUploadMode.php
app/src/Modules/Media/Application/Dto/MediaUrlResult.php
app/src/Modules/Media/Application/Dto/MediaFileMeta.php
app/src/Modules/Media/Application/Dto/MediaImageConversionSpec.php
app/src/Modules/Media/Application/Dto/MediaConversionResult.php
app/src/Modules/Media/Application/Dto/MediaResult.php
app/src/Modules/Media/Application/Dto/MediaAudioConversionSpec.php
app/src/Modules/Media/Application/Dto/MediaVideoConversionSpec.php
app/src/Modules/Media/Application/Dto/MediaPresignedPartCollection.php
app/src/Modules/Media/Application/Dto/MediaAudioProcessingResult.php
app/src/Modules/Media/Application/Dto/MediaConversionPlan.php
app/src/Modules/Media/Application/Dto/RequestMediaUploadResult.php
app/src/Modules/Media/README.md
tests/Unit/Modules/Media/Domain/Enum/MediaEnumTest.php
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaCommand.php
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php
app/src/Modules/Media/Domain/Enum/MediaStatus.php
app/src/Modules/Media/Domain/Enum/MediaConversionStatus.php
app/src/Modules/Media/Domain/Enum/MediaStorage.php
app/src/Modules/Media/Domain/Enum/MediaType.php
app/src/Modules/Media/Domain/Enum/MediaVideoConversionType.php
app/src/Modules/Media/Domain/Enum/MediaAudioConversionType.php
app/src/Modules/Media/Domain/Enum/MediaVisibility.php
app/src/Modules/Media/Domain/Enum/MediaImageConversionType.php
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
app/src/Modules/Media/Domain/ValueObject/MediaWaveform.php
app/src/Modules/Media/Domain/ValueObject/MediaPixelDimension.php
app/src/Modules/Media/Domain/ValueObject/MediaExpiration.php
app/src/Modules/Media/Domain/ValueObject/MediaId.php
app/src/Modules/Media/Domain/ValueObject/MediaMultipartPartSize.php
app/src/Modules/Media/Domain/ValueObject/MediaWaveformPeakCount.php
app/src/Modules/Media/Domain/ValueObject/MediaMultipartPartsCount.php
app/src/Modules/Media/Domain/ValueObject/MediaSampleRate.php
app/src/Modules/Media/Domain/ValueObject/MediaPresignedTtl.php
app/src/Modules/Media/Domain/ValueObject/MediaPath.php
app/src/Modules/Media/Domain/ValueObject/MediaAudioConversionId.php
app/src/Modules/Media/Domain/ValueObject/MediaImageConversionId.php
app/src/Modules/Media/Domain/ValueObject/MediaMultipartPartNumber.php
app/src/Modules/Media/Domain/ValueObject/MediaMimeType.php
app/src/Modules/Media/Repository/MediaMultipartUploadRepository.php
app/src/Modules/Media/Repository/MediaRepository.php
app/src/Modules/Media/Repository/MediaVideoConversionRepository.php
app/src/Modules/Media/Repository/MediaAudioConversionRepository.php
app/src/Modules/Media/Repository/MediaImageConversionRepository.php
tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php
app/src/Modules/Media/Application/Command/MakeMediaPermanent/MakeMediaPermanentHandler.php
app/src/Modules/Media/Application/Command/MakeMediaPermanent/MakeMediaPermanentCommand.php
app/src/Modules/Media/Application/Command/DeleteMedia/DeleteMediaHandler.php
app/src/Modules/Media/Application/Command/DeleteMedia/DeleteMediaCommand.php
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php
app/src/Modules/Media/Domain/Entity/MediaMultipartUpload.php
app/src/Modules/Media/Application/Command/RecordMediaProcessingFailure/RecordMediaProcessingFailureHandler.php
app/src/Modules/Media/Application/Command/RecordMediaProcessingFailure/RecordMediaProcessingFailureCommand.php
app/src/Modules/Media/Domain/Entity/Media.php
app/src/Modules/Media/Domain/Entity/MediaImageConversion.php
app/src/Modules/Media/Domain/Entity/MediaVideoConversion.php
app/src/Modules/Media/Domain/Entity/MediaAudioConversion.php
app/src/Modules/Media/Infrastructure/FileService/NativeAacAudioFormat.php
app/src/Modules/Media/Infrastructure/FileService/ImagickMediaImageProcessor.php
app/src/Modules/Media/Infrastructure/FileService/ConfiguredS3ClientProvider.php
app/src/Modules/Media/Infrastructure/FileService/AbstractFfmpegMediaProcessor.php
app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaAudioProcessor.php
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php
app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaVideoProcessor.php
app/src/Modules/Media/Infrastructure/FileService/S3ClientProvider.php
app/src/Modules/Media/Infrastructure/Cycle/MediaMultipartPartCollectionTypecast.php
app/src/Modules/Media/Infrastructure/Cycle/MediaWaveformTypecast.php
app/src/Modules/Media/Infrastructure/Cycle/MediaExpirationTypecast.php
app/src/Modules/Media/Infrastructure/Cycle/MediaProcessingErrorTypecast.php
tests/Unit/Modules/Media/Infrastructure/FfmpegMediaProcessorTest.php
tests/Unit/Modules/Media/Infrastructure/S3MediaFileServiceErrorTest.php
tests/Unit/Modules/Media/Infrastructure/ImagickMediaImageProcessorTest.php
app/src/Modules/Media/Infrastructure/Exception/MediaImageProcessorException.php
app/src/Modules/Media/Infrastructure/Exception/MediaStorageNotConfiguredException.php
app/src/Modules/Media/Domain/Collection/MediaMimeTypeCollection.php
app/src/Modules/Media/Domain/Collection/MediaCollection.php
app/src/Modules/Media/Domain/Collection/MediaMultipartPartCollection.php
app/src/Modules/Media/Domain/Collection/MediaImageConversionCollection.php
app/src/Modules/Media/Domain/Collection/MediaAudioConversionCollection.php
app/src/Modules/Media/Domain/Collection/MediaVideoConversionCollection.php
tests/Unit/Modules/Media/Infrastructure/Cycle/MediaTypecastTest.php
app/src/Modules/Media/Infrastructure/Bootloader/MediaBootloader.php
tests/Unit/Modules/Media/Infrastructure/Fixture/FakeS3ClientProvider.php
tests/Unit/Modules/Media/Infrastructure/Fixture/RecordingVideoProcessor.php
tests/Unit/Modules/Media/Infrastructure/Fixture/RecordingAudioProcessor.php
tests/Unit/Modules/Media/Infrastructure/Fixture/FakeS3Client.php
tests/Unit/Modules/Media/Application/MediaUploadedSerializationTest.php
tests/Unit/Modules/Media/Application/MediaTypeResolverTest.php
tests/Feature/Modules/Posts/Repository/PostMediaRepositoryTest.php

exec
/bin/zsh -lc 'rg -n "транзиент|ретра|throttling|RuntimeException|InvalidArgumentException|ProcessTimedOutException|function handle|conversions|downloadToFile|buildWaveform|instanceof Video|class GetAudioWaveform|MediaWaveform|ffmpeg|ffprobe" app/src/Modules/Media app/src/Shared/Infrastructure/Configuration/Media app/config/media.php app/database/migrations tests | head -n 400' in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php:94:            // Job всегда пробрасывает: транзиентный -> RetryException, постоянный -> исходное.
tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php:106:        // для транзиентного исходного сбоя Job всё равно бросает RetryException.
tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php:112:        $storageError = new \RuntimeException('сырой AWS-сбой');
tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php:138:        // Исходная причина не подменяется: транзиентный сбой -> RetryException, а не NotFoundException.
tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php:141:        // WARN транзиентного ретрая.
tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php:171:        $storageError = new \RuntimeException('сырой AWS-сбой');
tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php:174:            'транзиентный -> WARNING' => [
tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php:204:        $storageError = new \RuntimeException('сырой AWS-сбой');
tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php:207:            'транзиентный сбой хранилища -> повтор' => [
tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php:224:                new \RuntimeException('повреждённое изображение'),
tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php:225:                \RuntimeException::class,
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:68:        $conversions = $this->imageConversionRepository()->findByMediaId($media->id);
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:69:        self::assertCount(1, $conversions);
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:96:        // Имитируем зафиксированный транзиентный сбой: медиа осталось в processingFailed.
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:115:        $conversions = $this->imageConversionRepository()->findByMediaId($media->id);
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:116:        self::assertCount(1, $conversions);
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:305:        return $this->ffmpegFixture('mp4', [
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:314:        return $this->ffmpegFixture('wav', ['-f', 'lavfi', '-i', 'sine=frequency=440:duration=1']);
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:320:    private function ffmpegFixture(string $extension, array $inputArgs): string
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:322:        $ffmpegBinary = $this->getContainer()->get(MediaConfig::class)->ffmpegBinaryPath;
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:325:        $process = new Process([$ffmpegBinary, ...$inputArgs, '-y', $path]);
tests/Storage/FakeStorage.php:10:use Spiral\Storage\Exception\InvalidArgumentException;
tests/Storage/FakeStorage.php:51:        } catch (InvalidArgumentException) {
tests/Feature/Modules/Media/Flow/Fixture/RecordingMediaLogger.php:10: * Логгер-спай для проверки логов Media-сценариев: уровня (транзиентный сбой -> WARNING,
app/src/Modules/Media/Presentation/Job/ProcessMediaJob.php:24: * разрешён): фиксируем безопасную ошибку на Media и классифицируем — транзиентную просим
app/src/Modules/Media/Presentation/Job/ProcessMediaJob.php:58:            // Infrastructure (AWS — файловый сервис, ffmpeg — процессоры), Presentation не знает про
app/src/Modules/Media/Presentation/Job/ProcessMediaJob.php:95:                // Временный ретраябельный сбой инфраструктуры -> WARN (rules.md:84), повтор ожидаем.
app/src/Shared/Infrastructure/Configuration/Media/MediaConfig.php:22:        public string $ffmpegBinaryPath,
app/src/Shared/Infrastructure/Configuration/Media/MediaConfig.php:23:        public string $ffprobeBinaryPath,
app/src/Shared/Infrastructure/Configuration/Media/MediaConfig.php:24:        public int $ffmpegTimeoutSeconds,
app/src/Shared/Infrastructure/Configuration/Media/MediaConfig.php:25:        public int $ffmpegThreads,
app/config/media.php:26:    // Путь к бинарю ffmpeg для транскодирования видео и аудио.
app/config/media.php:27:    'ffmpegBinaryPath' => (string) \env('MEDIA_FFMPEG_BINARY', '/usr/bin/ffmpeg'),
app/config/media.php:29:    // Путь к бинарю ffprobe для чтения метаданных медиа.
app/config/media.php:30:    'ffprobeBinaryPath' => (string) \env('MEDIA_FFPROBE_BINARY', '/usr/bin/ffprobe'),
app/config/media.php:32:    // Таймаут одного вызова ffmpeg (транскод/probe), секунды.
app/config/media.php:33:    'ffmpegTimeoutSeconds' => \max(1, (int) \env('MEDIA_FFMPEG_TIMEOUT_SECONDS', 1800)),
app/config/media.php:35:    // Число потоков ffmpeg: 0 = выбирает сам ffmpeg по числу ядер.
app/config/media.php:36:    'ffmpegThreads' => \max(0, (int) \env('MEDIA_FFMPEG_THREADS', 0)),
app/src/Modules/Media/Application/Exception/MediaFileServiceFailedException.php:9: * признак транзиентности (`isTransient()`), чтобы потребитель контракта (ProcessMediaJob) выбирал
app/src/Modules/Media/Application/Exception/MediaFileServiceFailedException.php:27:     * Транзиентный (ретраябельный) сбой хранилища: сетевые ошибки, 5xx, throttling.
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:26: * Интеграционный тест ffmpeg-процессоров на реальном бинаре (Docker) и MinIO. Фикстуры медиа
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:27: * генерируются прямо в тесте через ffmpeg lavfi (testsrc/sine), бинарных файлов в репозитории нет.
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:275:        $path = \sprintf('%s/ffmpeg_fixture_%s.%s', \sys_get_temp_dir(), \bin2hex(\random_bytes(8)), $extension);
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:277:        $process = new Process([$this->mediaConfig()->ffmpegBinaryPath, ...$inputArgs, '-y', $path]);
app/src/Modules/Media/Domain/Entity/Media.php:219:     * processing или processingFailed (ретрай обработки после транзиентной ошибки).
tests/Feature/Modules/Auth/Presentation/SendLoginCodeJobTest.php:46:        $failingMailer->method('send')->willThrowException(new \RuntimeException('smtp недоступен'));
tests/Feature/Modules/Auth/Presentation/SendLoginCodeJobTest.php:72:    private function handler(LoginCodeMailerContract $loginCodeMailer): SendLoginCodeHandler
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:211:        $localFile = $fileService->downloadToFile(MediaStorage::Upload, $sourcePath);
app/database/migrations/20260620.222100_0_create_media_audio_conversions_table.php:15:        $this->table('media_audio_conversions')
app/database/migrations/20260620.222100_0_create_media_audio_conversions_table.php:45:        $this->table('media_audio_conversions')->drop();
app/src/Modules/Media/Application/Exception/MediaProcessorFailedException.php:8: * Сбой обработки медиа (ffmpeg) как часть контрактов MediaVideoProcessorContract /
app/src/Modules/Media/Application/Exception/MediaProcessorFailedException.php:11: * (php-ffmpeg, бинарь ffmpeg).
app/src/Modules/Media/Application/Exception/MediaProcessorFailedException.php:14: * а не местом выброса: бросают его инфраструктурные ffmpeg-процессоры. Сообщения —
app/src/Modules/Media/Application/Exception/MediaProcessorFailedException.php:15: * предопределённые безопасные строки; сырой вывод ffmpeg наружу не прокидывается.
app/src/Modules/Media/Application/Exception/MediaProcessorFailedException.php:28:     * Временный (ретраябельный) сбой обработки: таймаут транскодирования, нехватка ресурсов процесса.
app/src/Modules/Media/Application/Exception/MediaProcessorFailedException.php:36:     * Постоянный сбой обработки: битый/неподдерживаемый вход, нештатный код выхода ffmpeg.
app/src/Modules/Media/Domain/Entity/MediaImageConversion.php:26:    table: 'media_image_conversions',
tests/Feature/Modules/Auth/Infrastructure/SpiralLoginCodeMailerTest.php:53:        $failingMailer->method('send')->willThrowException(new \RuntimeException('smtp недоступен'));
tests/Feature/Modules/Auth/Infrastructure/SpiralLoginCodeMailerTest.php:55:        $this->expectException(\RuntimeException::class);
tests/Feature/Modules/Media/Application/MakeMediaPermanentHandlerTest.php:71:    private function handler(): MakeMediaPermanentHandler
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:470:    private function handler(
app/src/Modules/Media/Application/Query/CheckMediaIsImage/CheckMediaIsImageHandler.php:17:    public function handle(CheckMediaIsImageQuery $query): bool
app/src/Modules/Media/Domain/Entity/MediaVideoConversion.php:28:    table: 'media_video_conversions',
app/database/migrations/20260521.184100_0_create_media_domain_tables.php:38:        $this->table('media_image_conversions')
app/database/migrations/20260521.184100_0_create_media_domain_tables.php:63:        $this->table('media_video_conversions')
app/database/migrations/20260521.184100_0_create_media_domain_tables.php:115:        $this->table('media_video_conversions')->drop();
app/database/migrations/20260521.184100_0_create_media_domain_tables.php:116:        $this->table('media_image_conversions')->drop();
tests/Feature/Modules/Media/Application/GetAudioWaveformHandlerTest.php:22:use App\Modules\Media\Domain\ValueObject\MediaWaveform;
tests/Feature/Modules/Media/Application/GetAudioWaveformHandlerTest.php:26:final class GetAudioWaveformHandlerTest extends MediaApplicationTestCase
tests/Feature/Modules/Media/Application/GetAudioWaveformHandlerTest.php:80:    private function handler(): GetAudioWaveformHandler
tests/Feature/Modules/Media/Application/GetAudioWaveformHandlerTest.php:123:            waveform: MediaWaveform::fromPeaks([0, 64, 128, 255]),
tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php:25:use App\Modules\Media\Domain\ValueObject\MediaWaveform;
tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php:85:        // Готовое медиа не «ломается» задним числом: фиксация ошибки (транзиентной и постоянной)
tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php:185:            waveform: MediaWaveform::fromPeaks([0, 64, 128, 255]),
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:28:use App\Modules\Media\Domain\ValueObject\MediaWaveform;
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:235:    private function handler(MediaFileServiceContract $fileService): GetMediaUrlHandler
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:297:            waveform: MediaWaveform::fromPeaks([0, 64, 128, 255]),
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:32:use App\Modules\Media\Domain\ValueObject\MediaWaveform;
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:33:use App\Modules\Media\Domain\ValueObject\MediaWaveformPeakCount;
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:145:        $waveform = MediaWaveform::fromPeaks([0, 128, 255]);
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:149:        self::assertTrue($waveform->equals(MediaWaveform::fromPeaks([0, 128, 255])));
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:150:        self::assertFalse($waveform->equals(MediaWaveform::fromPeaks([0, 128, 254])));
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:157:        MediaWaveform::fromPeaks([]);
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:164:        MediaWaveform::fromPeaks([0, 256]);
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:376:        yield MediaWaveformPeakCount::class => [MediaWaveformPeakCount::class, 4096, 4097];
app/src/Modules/Media/Domain/Entity/MediaAudioConversion.php:19:use App\Modules\Media\Domain\ValueObject\MediaWaveform;
app/src/Modules/Media/Domain/Entity/MediaAudioConversion.php:20:use App\Modules\Media\Infrastructure\Cycle\MediaWaveformTypecast;
app/src/Modules/Media/Domain/Entity/MediaAudioConversion.php:30:    table: 'media_audio_conversions',
app/src/Modules/Media/Domain/Entity/MediaAudioConversion.php:71:    #[Column(type: 'json', typecast: MediaWaveformTypecast::class)]
app/src/Modules/Media/Domain/Entity/MediaAudioConversion.php:72:    public private(set) MediaWaveform $waveform;
app/src/Modules/Media/Domain/Entity/MediaAudioConversion.php:88:        MediaWaveform $waveform,
app/src/Modules/Media/Application/Query/GetAudioWaveform/GetAudioWaveformHandler.php:9:use App\Modules\Media\Domain\ValueObject\MediaWaveform;
app/src/Modules/Media/Application/Query/GetAudioWaveform/GetAudioWaveformHandler.php:18:final readonly class GetAudioWaveformHandler
app/src/Modules/Media/Application/Query/GetAudioWaveform/GetAudioWaveformHandler.php:25:    public function handle(GetAudioWaveformQuery $query): MediaWaveform
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:75:    private function handler(MediaFileServiceContract $fileService): FindMediaUrlHandler
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:29:use App\Modules\Media\Domain\ValueObject\MediaWaveform;
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:55:        $conversions = $this->imageConversionRepository()->findByMediaId($media->id);
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:56:        self::assertCount(1, $conversions);
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:57:        self::assertSame(MediaStorage::Private, $conversions->first()->storage);
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:154:            waveform: MediaWaveform::fromPeaks([0, 64, 128, 255]),
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:263:    private function handler(
tests/Feature/Modules/Auth/Application/SendLoginCodeHandlerTest.php:50:    private function handler(RecordingLoginCodeMailer $loginCodeMailer): SendLoginCodeHandler
app/src/Modules/Media/Application/Query/GetAudioWaveform/GetAudioWaveformQuery.php:7:final readonly class GetAudioWaveformQuery
tests/Unit/Modules/Media/Infrastructure/FfmpegMediaProcessorTest.php:26:use App\Modules\Media\Domain\ValueObject\MediaWaveform;
tests/Unit/Modules/Media/Infrastructure/FfmpegMediaProcessorTest.php:30:use Symfony\Component\Process\Exception\ProcessTimedOutException;
tests/Unit/Modules/Media/Infrastructure/FfmpegMediaProcessorTest.php:39:        $previous = new \RuntimeException('сырой вывод ffmpeg');
tests/Unit/Modules/Media/Infrastructure/FfmpegMediaProcessorTest.php:78:        $processor->failWith(new \RuntimeException('битый файл'));
tests/Unit/Modules/Media/Infrastructure/FfmpegMediaProcessorTest.php:110:        $fileService->method('downloadToFile')->willReturn($sourceFile);
tests/Unit/Modules/Media/Infrastructure/FfmpegMediaProcessorTest.php:151:        $processor->failWith(new \RuntimeException('битый файл'));
tests/Unit/Modules/Media/Infrastructure/FfmpegMediaProcessorTest.php:183:        $fileService->method('downloadToFile')->willReturn($sourceFile);
tests/Unit/Modules/Media/Infrastructure/FfmpegMediaProcessorTest.php:276:            waveform: MediaWaveform::fromPeaks([0, 128, 255]),
tests/Unit/Modules/Media/Infrastructure/FfmpegMediaProcessorTest.php:283:        $fileService->method('downloadToFile')->willReturn($sourceFile);
tests/Unit/Modules/Media/Infrastructure/FfmpegMediaProcessorTest.php:290:        $sourceFile = (string) \tempnam(\sys_get_temp_dir(), 'ffmpeg_src_');
tests/Unit/Modules/Media/Infrastructure/FfmpegMediaProcessorTest.php:296:    private function timeout(): ProcessTimedOutException
tests/Unit/Modules/Media/Infrastructure/FfmpegMediaProcessorTest.php:298:        return new ProcessTimedOutException(new Process(['true']), ProcessTimedOutException::TYPE_GENERAL);
tests/Unit/Modules/Media/Infrastructure/FfmpegMediaProcessorTest.php:308:            ffmpegBinaryPath: '/usr/bin/ffmpeg',
tests/Unit/Modules/Media/Infrastructure/FfmpegMediaProcessorTest.php:309:            ffprobeBinaryPath: '/usr/bin/ffprobe',
tests/Unit/Modules/Media/Infrastructure/FfmpegMediaProcessorTest.php:310:            ffmpegTimeoutSeconds: 1800,
tests/Unit/Modules/Media/Infrastructure/FfmpegMediaProcessorTest.php:311:            ffmpegThreads: 0,
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php:20:use App\Modules\Media\Domain\ValueObject\MediaWaveformPeakCount;
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php:47:    public function handle(CompleteMediaUploadCommand $command): MediaResult
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php:189:        if (!MediaWaveformPeakCount::supports($spec->waveformPeaks)) {
tests/Feature/Modules/Media/Application/RecordMediaProcessingFailureHandlerTest.php:70:    private function handler(): RecordMediaProcessingFailureHandler
tests/Unit/Modules/Media/Infrastructure/S3MediaFileServiceErrorTest.php:197:        $fileService->downloadToFile(MediaStorage::Upload, $this->path());
tests/Unit/Modules/Media/Infrastructure/S3MediaFileServiceErrorTest.php:287:            'сетевая ошибка -> транзиентная' => [
tests/Unit/Modules/Media/Infrastructure/S3MediaFileServiceErrorTest.php:291:            '5xx -> транзиентная' => [
tests/Unit/Modules/Media/Infrastructure/S3MediaFileServiceErrorTest.php:295:            'throttling -> транзиентная' => [
tests/Unit/Modules/Media/Infrastructure/S3MediaFileServiceErrorTest.php:296:                new S3Exception('throttling', $command, ['code' => 'Throttling']),
tests/Unit/Modules/Media/Infrastructure/ImagickMediaImageProcessorTest.php:72:            ffmpegBinaryPath: '/usr/bin/ffmpeg',
tests/Unit/Modules/Media/Infrastructure/ImagickMediaImageProcessorTest.php:73:            ffprobeBinaryPath: '/usr/bin/ffprobe',
tests/Unit/Modules/Media/Infrastructure/ImagickMediaImageProcessorTest.php:74:            ffmpegTimeoutSeconds: 1800,
tests/Unit/Modules/Media/Infrastructure/ImagickMediaImageProcessorTest.php:75:            ffmpegThreads: 0,
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:163:    private function handler(
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:177:                ffmpegBinaryPath: '/usr/bin/ffmpeg',
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:178:                ffprobeBinaryPath: '/usr/bin/ffprobe',
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:179:                ffmpegTimeoutSeconds: 1800,
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:180:                ffmpegThreads: 0,
app/src/Modules/Media/Application/Query/GetMediaUrl/GetMediaUrlHandler.php:36:    public function handle(GetMediaUrlQuery $query): MediaUrlResult
tests/Kernel/Shared/Infrastructure/Configuration/MediaConfigTest.php:27:        self::assertSame('/usr/bin/ffmpeg', $mediaConfig->ffmpegBinaryPath);
tests/Kernel/Shared/Infrastructure/Configuration/MediaConfigTest.php:28:        self::assertSame('/usr/bin/ffprobe', $mediaConfig->ffprobeBinaryPath);
tests/Kernel/Shared/Infrastructure/Configuration/MediaConfigTest.php:29:        self::assertSame(1800, $mediaConfig->ffmpegTimeoutSeconds);
tests/Kernel/Shared/Infrastructure/Configuration/MediaConfigTest.php:30:        self::assertSame(0, $mediaConfig->ffmpegThreads);
tests/Kernel/Shared/Infrastructure/Configuration/MediaConfigTest.php:40:            'ffmpegBinaryPath' => '/opt/ffmpeg',
tests/Kernel/Shared/Infrastructure/Configuration/MediaConfigTest.php:41:            'ffprobeBinaryPath' => '/opt/ffprobe',
tests/Kernel/Shared/Infrastructure/Configuration/MediaConfigTest.php:42:            'ffmpegTimeoutSeconds' => 600,
tests/Kernel/Shared/Infrastructure/Configuration/MediaConfigTest.php:43:            'ffmpegThreads' => 4,
tests/Kernel/Shared/Infrastructure/Configuration/MediaConfigTest.php:51:        self::assertSame('/opt/ffmpeg', $mediaConfig->ffmpegBinaryPath);
tests/Kernel/Shared/Infrastructure/Configuration/MediaConfigTest.php:52:        self::assertSame('/opt/ffprobe', $mediaConfig->ffprobeBinaryPath);
tests/Kernel/Shared/Infrastructure/Configuration/MediaConfigTest.php:53:        self::assertSame(600, $mediaConfig->ffmpegTimeoutSeconds);
tests/Kernel/Shared/Infrastructure/Configuration/MediaConfigTest.php:54:        self::assertSame(4, $mediaConfig->ffmpegThreads);
tests/Kernel/Shared/Infrastructure/Configuration/MediaConfigTest.php:67:            ffmpegBinaryPath: '/usr/bin/ffmpeg',
tests/Kernel/Shared/Infrastructure/Configuration/MediaConfigTest.php:68:            ffprobeBinaryPath: '/usr/bin/ffprobe',
tests/Kernel/Shared/Infrastructure/Configuration/MediaConfigTest.php:69:            ffmpegTimeoutSeconds: 1800,
tests/Kernel/Shared/Infrastructure/Configuration/MediaConfigTest.php:70:            ffmpegThreads: 0,
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:26:use App\Modules\Media\Domain\ValueObject\MediaWaveform;
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:195:    private function handler(MediaFileServiceContract $fileService): DeleteMediaHandler
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:292:            waveform: MediaWaveform::fromPeaks([0, 64, 128, 255]),
tests/Unit/Modules/Media/Infrastructure/Cycle/MediaTypecastTest.php:13:use App\Modules\Media\Domain\ValueObject\MediaWaveform;
tests/Unit/Modules/Media/Infrastructure/Cycle/MediaTypecastTest.php:17:use App\Modules\Media\Infrastructure\Cycle\MediaWaveformTypecast;
tests/Unit/Modules/Media/Infrastructure/Cycle/MediaTypecastTest.php:56:        $this->expectException(\InvalidArgumentException::class);
tests/Unit/Modules/Media/Infrastructure/Cycle/MediaTypecastTest.php:63:        $this->expectException(\InvalidArgumentException::class);
tests/Unit/Modules/Media/Infrastructure/Cycle/MediaTypecastTest.php:70:        $this->expectException(\InvalidArgumentException::class);
tests/Unit/Modules/Media/Infrastructure/Cycle/MediaTypecastTest.php:102:        $waveform = MediaWaveformTypecast::castDatabaseValue('[0,128,255]');
tests/Unit/Modules/Media/Infrastructure/Cycle/MediaTypecastTest.php:105:        self::assertSame('[0,128,255]', MediaWaveformTypecast::uncastValue($waveform));
tests/Unit/Modules/Media/Infrastructure/Cycle/MediaTypecastTest.php:106:        self::assertSame('[0,128,255]', MediaWaveformTypecast::uncastValue(MediaWaveform::fromPeaks([0, 128, 255])));
tests/Unit/Modules/Media/Infrastructure/Cycle/MediaTypecastTest.php:111:        $this->expectException(\InvalidArgumentException::class);
tests/Unit/Modules/Media/Infrastructure/Cycle/MediaTypecastTest.php:113:        MediaWaveformTypecast::castDatabaseValue('123');
tests/Unit/Modules/Media/Infrastructure/Cycle/MediaTypecastTest.php:118:        $this->expectException(\InvalidArgumentException::class);
tests/Unit/Modules/Media/Infrastructure/Cycle/MediaTypecastTest.php:120:        MediaWaveformTypecast::castDatabaseValue('[1, "two"]');
tests/Unit/Modules/Media/Infrastructure/Cycle/MediaTypecastTest.php:127:        MediaWaveformTypecast::castDatabaseValue('[0, 256]');
tests/Feature/Modules/Auth/Application/GetUserSessionsHandlerTest.php:96:    private function handler(): GetUserSessionsHandler
app/src/Modules/Media/Application/Query/FindMediaUrl/FindMediaUrlHandler.php:28:    public function handle(FindMediaUrlQuery $query): MediaUrlResult|null
tests/Feature/Modules/Media/Application/CheckMediaAttachableHandlerTest.php:72:    private function handler(): CheckMediaAttachableHandler
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php:38:    public function handle(RequestMediaUploadCommand $command): RequestMediaUploadResult
tests/Unit/Modules/Media/Infrastructure/Fixture/RecordingVideoProcessor.php:12: * Подменяет реальный вызов ffmpeg (runEncoding) в видео-процессоре: либо бросает заданный сбой
tests/Unit/Modules/Media/Infrastructure/Fixture/RecordingVideoProcessor.php:14: * (для проверки загрузки и очистки временных файлов в finally). Сам ffmpeg тут не вызывается —
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:32: * Без #[Transactional]: все S3/ffmpeg/Imagick-операции выполняются вне транзакции, затем один
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:51:    public function handle(ProcessMediaCommand $command): void
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:72:        $conversions = match ($media->type) {
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:102:        foreach ($conversions as $conversion) {
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:110:            'conversions' => \count($conversions),
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:129:        $conversions = [];
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:158:            $conversions[] = MediaImageConversion::create(
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:171:        return $conversions;
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:182:        $conversions = [];
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:201:            $conversions[] = MediaVideoConversion::create(
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:215:            $conversions[] = MediaImageConversion::create(
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:234:        return $conversions;
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:245:        $conversions = [];
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:258:            $conversions[] = MediaAudioConversion::create(
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:279:        return $conversions;
tests/Unit/Modules/Media/Infrastructure/Fixture/RecordingAudioProcessor.php:12: * Подменяет реальный вызов ffmpeg (runEncoding) в аудио-процессоре: либо бросает заданный сбой,
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:39:use App\Modules\Media\Domain\ValueObject\MediaWaveform;
tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php:325:            waveform: MediaWaveform::fromPeaks([0, 64, 128, 255]),
app/src/Modules/Media/Application/Query/CheckMediaExists/CheckMediaExistsHandler.php:16:    public function handle(CheckMediaExistsQuery $query): bool
tests/Feature/Modules/Auth/Application/RequestLoginCodeHandlerTest.php:63:    private function handler(RecordingOutboxEventStore $outbox): RequestLoginCodeHandler
app/src/Modules/Media/Application/Command/MakeMediaPermanent/MakeMediaPermanentHandler.php:26:    public function handle(MakeMediaPermanentCommand $command): MediaResult
tests/Feature/Modules/Auth/Application/RevokeUserSessionHandlerTest.php:69:    private function handler(): RevokeUserSessionHandler
tests/Feature/Modules/Notifications/Presentation/DispatchNotificationJobTest.php:77:        $failingEntityManager->method('run')->willThrowException(new \RuntimeException('БД недоступна.'));
tests/Feature/Modules/Notifications/Presentation/DeliveryJobTest.php:45:        $fcmPushSender->method('send')->willThrowException(FcmPushFailedException::transient(new \RuntimeException('down')));
tests/Feature/Modules/Notifications/Presentation/DeliveryJobTest.php:58:        $fcmPushSender->method('send')->willThrowException(new \RuntimeException('terminal'));
tests/Feature/Modules/Notifications/Presentation/DeliveryJobTest.php:60:        $this->expectException(\RuntimeException::class);
tests/Feature/Modules/Notifications/Presentation/DeliveryJobTest.php:78:        $centrifugoService->method('publish')->willThrowException(new \RuntimeException('terminal'));
tests/Feature/Modules/Notifications/Presentation/DeliveryJobTest.php:80:        $this->expectException(\RuntimeException::class);
app/src/Modules/Media/Application/Command/RecordMediaProcessingFailure/RecordMediaProcessingFailureHandler.php:15: * Фиксирует ошибку обработки на Media. Доменные методы записи транзиентной и постоянной
app/src/Modules/Media/Application/Command/RecordMediaProcessingFailure/RecordMediaProcessingFailureHandler.php:17: * isTransient влияет только на решение ProcessMediaJob о ретрае и идёт сюда лишь как контекст
app/src/Modules/Media/Application/Command/RecordMediaProcessingFailure/RecordMediaProcessingFailureHandler.php:28:    public function handle(RecordMediaProcessingFailureCommand $command): void
app/src/Modules/Media/Application/Query/CheckMediaAttachable/CheckMediaAttachableHandler.php:27:    public function handle(CheckMediaAttachableQuery $query): MediaAttachableResult
app/src/Modules/Media/Application/Command/DeleteMedia/DeleteMediaHandler.php:37:    public function handle(DeleteMediaCommand $command): void
tests/Feature/Modules/Notifications/Application/SendPushNotificationHandlerTest.php:146:    private function handler(
app/src/Modules/Media/Application/Contract/MediaVideoProcessorContract.php:15: * Реализация владеет временными файлами: скачивает оригинал в локальный файл, гоняет ffmpeg
app/src/Modules/Media/Application/Contract/MediaAudioProcessorContract.php:15: * Реализация владеет временными файлами: скачивает оригинал в локальный файл, гоняет ffmpeg
tests/Feature/Modules/Notifications/Application/DispatchNotificationHandlerTest.php:200:    private function handler(
tests/Feature/Modules/Notifications/Application/DispatchNotificationHandlerTest.php:210:    private function handlerWithRegistry(
app/src/Modules/Media/Application/Contract/MediaFileServiceContract.php:89:     * полного буфера в памяти (для ffmpeg-обработки видео/аудио). Владелец файла — вызыватель:
app/src/Modules/Media/Application/Contract/MediaFileServiceContract.php:92:    public function downloadToFile(MediaStorage $storage, MediaPath $path): string;
app/src/Modules/Media/Application/Contract/MediaFileServiceContract.php:95:     * Заливает локальный файл (результат ffmpeg-обработки) в хранилище стримом, без полного
tests/Unit/Modules/Auth/Presentation/Http/Middleware/AuthContextAttributeMiddlewareTest.php:103:            public function handle(ServerRequestInterface $request): ResponseInterface
tests/Unit/Modules/Auth/Presentation/Http/Middleware/AuthContextAttributeMiddlewareTest.php:114:        return $captureHandler->request ?? throw new \RuntimeException('Обработчик не был вызван.');
app/src/Modules/Media/Domain/ValueObject/MediaWaveform.php:13: * JSON-колонкой через MediaWaveformTypecast. Каждая амплитуда — целое 0..255, нормализованное
app/src/Modules/Media/Domain/ValueObject/MediaWaveform.php:16:final readonly class MediaWaveform implements \JsonSerializable
app/src/Modules/Media/Domain/ValueObject/MediaWaveform.php:33:        // Количество пиков валидируется доменным диапазоном MediaWaveformPeakCount (1..4096):
app/src/Modules/Media/Domain/ValueObject/MediaWaveform.php:35:        MediaWaveformPeakCount::fromInt(\count($peaks));
tests/Feature/Modules/Auth/Application/CompleteRegistrationHandlerTest.php:99:    private function handler(): CompleteRegistrationHandler
tests/Unit/Modules/Notifications/Infrastructure/Centrifugo/CentrifugoClientTest.php:68:            new class ('boom') extends \RuntimeException implements ClientExceptionInterface {},
tests/Unit/Modules/Notifications/Infrastructure/Centrifugo/CentrifugoClientTest.php:146:            new class ('boom') extends \RuntimeException implements ClientExceptionInterface {},
tests/Unit/Modules/Notifications/Infrastructure/Centrifugo/CentrifugoOnlinePresenceTest.php:59:            new class ('boom') extends \RuntimeException implements ClientExceptionInterface {},
app/src/Modules/Media/Domain/ValueObject/MediaWaveformPeakCount.php:9:final readonly class MediaWaveformPeakCount extends AbstractIntegerValue
app/src/Modules/Media/Infrastructure/FileService/NativeAacAudioFormat.php:10: * AAC-формат на нативном кодере ffmpeg «aac» вместо libfdk_aac из FFMpeg\Format\Audio\Aac:
app/src/Modules/Media/Infrastructure/FileService/NativeAacAudioFormat.php:11: * libfdk_aac не входит в сборку ffmpeg Ubuntu (несвободный), а нативный «aac» доступен всегда.
tests/Unit/Modules/Notifications/Infrastructure/Push/KreaitFcmPushSenderTest.php:69:            new class ('FCM down') extends \RuntimeException implements MessagingException {
tests/Unit/Modules/Notifications/Infrastructure/Cycle/NotificationTypecastTest.php:72:        $this->expectException(\InvalidArgumentException::class);
tests/Unit/Modules/Notifications/Infrastructure/Cycle/NotificationTypecastTest.php:79:        $this->expectException(\InvalidArgumentException::class);
app/src/Modules/Media/Infrastructure/FileService/AbstractFfmpegMediaProcessor.php:12:use Symfony\Component\Process\Exception\ProcessTimedOutException;
app/src/Modules/Media/Infrastructure/FileService/AbstractFfmpegMediaProcessor.php:15: * Общая инфраструктура ffmpeg-процессоров видео и аудио: сборка клиента php-ffmpeg из MediaConfig,
app/src/Modules/Media/Infrastructure/FileService/AbstractFfmpegMediaProcessor.php:16: * управление временными файлами и классификация сбоев ffmpeg во временную/постоянную ошибку.
app/src/Modules/Media/Infrastructure/FileService/AbstractFfmpegMediaProcessor.php:18: * Класс не final намеренно: конкретные процессоры выносят сам вызов ffmpeg в защищённый метод
app/src/Modules/Media/Infrastructure/FileService/AbstractFfmpegMediaProcessor.php:20: * реального таймаута бинаря (реальный ffmpeg проверяется интеграционными тестами).
app/src/Modules/Media/Infrastructure/FileService/AbstractFfmpegMediaProcessor.php:29:    protected function ffmpeg(): FFMpeg
app/src/Modules/Media/Infrastructure/FileService/AbstractFfmpegMediaProcessor.php:32:            'ffmpeg.binaries' => $this->mediaConfig->ffmpegBinaryPath,
app/src/Modules/Media/Infrastructure/FileService/AbstractFfmpegMediaProcessor.php:33:            'ffprobe.binaries' => $this->mediaConfig->ffprobeBinaryPath,
app/src/Modules/Media/Infrastructure/FileService/AbstractFfmpegMediaProcessor.php:34:            'ffmpeg.threads' => $this->mediaConfig->ffmpegThreads,
app/src/Modules/Media/Infrastructure/FileService/AbstractFfmpegMediaProcessor.php:35:            'timeout' => $this->mediaConfig->ffmpegTimeoutSeconds,
app/src/Modules/Media/Infrastructure/FileService/AbstractFfmpegMediaProcessor.php:40:     * Уникальный путь во временном каталоге с нужным расширением (ffmpeg определяет контейнер по
app/src/Modules/Media/Infrastructure/FileService/AbstractFfmpegMediaProcessor.php:46:        return \sprintf('%s/media_ffmpeg_%s.%s', \sys_get_temp_dir(), \bin2hex(\random_bytes(12)), $extension);
app/src/Modules/Media/Infrastructure/FileService/AbstractFfmpegMediaProcessor.php:57:     * Читает числовое поле ffprobe (get() возвращает mixed на границе с библиотекой) как int.
app/src/Modules/Media/Infrastructure/FileService/AbstractFfmpegMediaProcessor.php:84:        // Таймаут транскодирования — единственный временный (ретраябельный) сбой: ищем
app/src/Modules/Media/Infrastructure/FileService/AbstractFfmpegMediaProcessor.php:85:        // ProcessTimedOutException в цепочке причин (php-ffmpeg оборачивает исключения процесса).
app/src/Modules/Media/Infrastructure/FileService/AbstractFfmpegMediaProcessor.php:87:            if ($current instanceof ProcessTimedOutException) {
app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaAudioProcessor.php:17:use App\Modules\Media\Domain\ValueObject\MediaWaveform;
app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaAudioProcessor.php:22: * Процессор аудио на php-ffmpeg: транскод оригинала в m4a/AAC и извлечение волны амплитуд.
app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaAudioProcessor.php:23: * Волну php-ffmpeg готовым массивом не отдаёт (только PNG), поэтому берём сырой PCM прямым
app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaAudioProcessor.php:24: * вызовом ffmpeg (s16le, моно, низкая частота), делим на waveformPeaks интервалов и нормализуем
app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaAudioProcessor.php:45:        $sourceFile = $this->mediaFileService->downloadToFile(storage: $sourceStorage, path: $sourcePath);
app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaAudioProcessor.php:81:        $ffmpeg = $this->ffmpeg();
app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaAudioProcessor.php:83:        // php-ffmpeg open() возвращает Video (наследник Audio) для файлов с видеодорожкой и Audio
app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaAudioProcessor.php:85:        $audio = $ffmpeg->open($sourceFile);
app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaAudioProcessor.php:86:        if ($audio instanceof Video) {
app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaAudioProcessor.php:87:            throw new \RuntimeException('Загруженный файл не является аудио.');
app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaAudioProcessor.php:94:        $probe = $ffmpeg->getFFProbe();
app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaAudioProcessor.php:99:            throw new \RuntimeException('В транскодированном аудио нет аудиопотока.');
app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaAudioProcessor.php:114:    private function extractWaveform(string $sourceFile, int $peaks): MediaWaveform
app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaAudioProcessor.php:120:                $this->mediaConfig->ffmpegBinaryPath,
app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaAudioProcessor.php:128:            $process->setTimeout((float) $this->mediaConfig->ffmpegTimeoutSeconds);
app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaAudioProcessor.php:131:            return $this->buildWaveform(pcm: (string) \file_get_contents($pcmFile), peaks: $peaks);
app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaAudioProcessor.php:137:    private function buildWaveform(string $pcm, int $peaks): MediaWaveform
app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaAudioProcessor.php:144:            throw new \RuntimeException('Не удалось прочитать PCM-данные аудио.');
app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaAudioProcessor.php:174:        return MediaWaveform::fromPeaks($amplitudes);
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:216:    public function downloadToFile(MediaStorage $storage, MediaPath $path): string
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:375:     * транзиентности. Знание об AWS остаётся в Infrastructure; наружу уходит только контрактный
tests/Feature/Modules/Tags/Application/ResolveTagsHandlerTest.php:89:    private function handler(): ResolveTagsHandler
app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaVideoProcessor.php:24: * Процессор видео на php-ffmpeg: транскод оригинала в mp4/H.264+AAC с сохранением пропорций
app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaVideoProcessor.php:26: * Владеет временными файлами и удаляет их в finally при любом исходе. Ориентацию ffmpeg
app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaVideoProcessor.php:44:        $sourceFile = $this->mediaFileService->downloadToFile(storage: $sourceStorage, path: $sourcePath);
app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaVideoProcessor.php:100:        $ffmpeg = $this->ffmpeg();
app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaVideoProcessor.php:102:        $video = $ffmpeg->open($sourceFile);
app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaVideoProcessor.php:103:        if (!$video instanceof Video) {
app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaVideoProcessor.php:104:            throw new \RuntimeException('Загруженный файл не является видео.');
app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaVideoProcessor.php:118:        $probe = $ffmpeg->getFFProbe();
app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaVideoProcessor.php:123:            throw new \RuntimeException('В транскодированном видео нет видеопотока.');
app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaVideoProcessor.php:141:     * Первый кадр транскода как постер JPEG прямым вызовом ffmpeg: читает из нормализованного
app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaVideoProcessor.php:147:            $this->mediaConfig->ffmpegBinaryPath,
app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaVideoProcessor.php:152:        $process->setTimeout((float) $this->mediaConfig->ffmpegTimeoutSeconds);
tests/Feature/Modules/Tags/Application/GetTagsHandlerTest.php:54:    private function handler(): GetTagsHandler
app/src/Modules/Media/Application/Dto/MediaAudioProcessingResult.php:12:use App\Modules\Media\Domain\ValueObject\MediaWaveform;
app/src/Modules/Media/Application/Dto/MediaAudioProcessingResult.php:27:        public MediaWaveform $waveform,
tests/Unit/Shared/Infrastructure/Cycle/ValueObjectCastTest.php:74:        $this->expectException(\InvalidArgumentException::class);
app/src/Modules/Media/README.md:26:| `GetAudioWaveform(mediaId)` | Query | `MediaWaveform` (числа амплитуд аудио-конверсии; не через URL; не ready/не аудио/нет конверсии → 404) |
app/src/Modules/Media/README.md:64:5. ProcessMedia (без #[Transactional]): S3/ffmpeg/Imagick вне транзакции. Исчерпывающий match по
app/src/Modules/Media/README.md:65:   media.type: image -> ресайз (Imagick); video -> транскод mp4/H.264+AAC + кадр-постер (ffmpeg);
app/src/Modules/Media/README.md:66:   audio -> транскод m4a/AAC + волна амплитуд (ffmpeg). Затем перекладка оригинала в целевой бакет
app/src/Modules/Media/README.md:80:стратегию повтора по **контрактному сигналу** транзиентности, а не по типу хранилища:
app/src/Modules/Media/README.md:81:`MediaFileServiceFailedException::isTransient()` → транзиентная (сетевые/5xx/throttling) даёт
app/src/Modules/Media/README.md:83:нештатный ответ S3) → терминальный проброс (outbox → `failed`) и лог уровня ERROR. Ошибки ffmpeg
app/src/Modules/Media/README.md:87:хранилища). Классификацию сырого `AwsException`/исключений php-ffmpeg делает Infrastructure
app/src/Modules/Media/README.md:88:(`S3MediaFileService` / ffmpeg-процессоры → контрактные исключения), поэтому `Presentation/Job` не
app/src/Modules/Media/README.md:94:выбирает retry/terminal по исходному исключению (транзиентное → `RetryException`).
app/src/Modules/Media/README.md:107:  `persist(media + conversions); run()` с `markReadyMovedTo`. Операции идемпотентны
app/src/Modules/Media/README.md:113:- Набор `conversions` фиксируется один раз в `CompleteMediaUpload` и доезжает до обработки в
app/src/Modules/Media/README.md:148:  порог и размер части multipart, драйвер обработки изображений, ffmpeg-настройки
app/src/Modules/Media/README.md:149:  (`ffmpegBinaryPath`, `ffprobeBinaryPath`, `ffmpegTimeoutSeconds`, `ffmpegThreads` —
app/src/Modules/Media/README.md:164:  `php-ffmpeg/php-ffmpeg` (бинарь ffmpeg в образе). Процессор владеет временными файлами:
app/src/Modules/Media/README.md:165:  `downloadToFile` оригинала → ffmpeg (probe/транскод/постер/волна) → `uploadFromFile` результатов
app/src/Modules/Media/README.md:168:  нативным кодером ffmpeg (`NativeAacAudioFormat`), т.к. `libfdk_aac` в сборку не входит. Волну
app/src/Modules/Media/README.md:169:  амплитуд снимаем сырым PCM (s16le, моно) прямым вызовом ffmpeg и нормализуем в 0..255.
app/src/Modules/Media/README.md:173:- Миграция `*_create_media_audio_conversions_table` добавляет таблицу `media_audio_conversions`
app/src/Modules/Media/README.md:175:- В образе нужен бинарь `ffmpeg`/`ffprobe` (ставится в `docker/Dockerfile`, самопроверка
app/src/Modules/Media/README.md:176:  `ffmpeg -version`/`ffprobe -version` при сборке).
app/src/Modules/Media/README.md:177:- **Предусловие выката**: смена содержимого `MediaUploaded` (`conversions` → `plan`) несовместима.
tests/Feature/Modules/User/Application/GetUserPublicProfileHandlerTest.php:56:    private function handler(): GetUserPublicProfileHandler
app/src/Modules/Media/Infrastructure/Cycle/MediaMultipartPartCollectionTypecast.php:23:            throw new \InvalidArgumentException('Список частей загрузки должен быть JSON-массивом.');
app/src/Modules/Media/Infrastructure/Cycle/MediaMultipartPartCollectionTypecast.php:29:                throw new \InvalidArgumentException('Часть загрузки имеет неверный формат.');
app/src/Modules/Media/Infrastructure/Cycle/MediaMultipartPartCollectionTypecast.php:36:                throw new \InvalidArgumentException('Часть загрузки имеет неверный формат.');
tests/Feature/CqrsContainerTest.php:55:    public function handle(CqrsContainerCommand $command): string
tests/Feature/CqrsContainerTest.php:63:    public function handle(CqrsContainerQuery $query): string
tests/Unit/Shared/Infrastructure/Cycle/ValueObjectCastDefensiveTest.php:55:        $this->expectException(\InvalidArgumentException::class);
tests/Unit/Shared/Infrastructure/Cycle/ValueObjectCastDefensiveTest.php:65:        $this->expectException(\InvalidArgumentException::class);
tests/Unit/Shared/Infrastructure/Cycle/ValueObjectCastDefensiveTest.php:75:        $this->expectException(\InvalidArgumentException::class);
tests/Unit/Shared/Infrastructure/Cycle/ValueObjectCastDefensiveTest.php:85:        $this->expectException(\InvalidArgumentException::class);
tests/Unit/Shared/Infrastructure/Cycle/ValueObjectCastDefensiveTest.php:95:        $this->expectException(\InvalidArgumentException::class);
tests/Unit/Shared/Infrastructure/Cycle/ValueObjectCastDefensiveTest.php:105:        $this->expectException(\InvalidArgumentException::class);
tests/Unit/Shared/Infrastructure/Cycle/ValueObjectCastDefensiveTest.php:144:        $this->expectException(\InvalidArgumentException::class);
tests/Unit/Shared/Infrastructure/Cycle/ValueObjectCastDefensiveTest.php:154:        $this->expectException(\InvalidArgumentException::class);
tests/Unit/Shared/Infrastructure/Cycle/ValueObjectCastDefensiveTest.php:164:        $this->expectException(\InvalidArgumentException::class);
app/src/Modules/Media/Infrastructure/Cycle/MediaWaveformTypecast.php:7:use App\Modules\Media\Domain\ValueObject\MediaWaveform;
app/src/Modules/Media/Infrastructure/Cycle/MediaWaveformTypecast.php:10:final class MediaWaveformTypecast implements ColumnValueTypecast
app/src/Modules/Media/Infrastructure/Cycle/MediaWaveformTypecast.php:12:    public static function castDatabaseValue(string $value): MediaWaveform
app/src/Modules/Media/Infrastructure/Cycle/MediaWaveformTypecast.php:21:            throw new \InvalidArgumentException('Волна аудио должна быть JSON-массивом.');
app/src/Modules/Media/Infrastructure/Cycle/MediaWaveformTypecast.php:27:                throw new \InvalidArgumentException('Амплитуда волны должна быть целым числом.');
app/src/Modules/Media/Infrastructure/Cycle/MediaWaveformTypecast.php:33:        return MediaWaveform::fromPeaks($peaks);
app/src/Modules/Media/Infrastructure/Cycle/MediaWaveformTypecast.php:36:    public static function uncastValue(MediaWaveform $value): string
tests/Feature/Modules/User/Application/CreateUserHandlerTest.php:113:    private function handler(): CreateUserHandler
tests/Unit/Modules/Outbox/Domain/OutboxValueObjectTest.php:133:            \mb_strlen(OutboxLastError::fromThrowable(new \RuntimeException(\str_repeat('x', 3000)))->value()),
tests/Feature/Modules/User/Application/CheckUsersExistHandlerTest.php:43:    private function handler(): CheckUsersExistHandler
tests/Feature/Modules/User/Application/GetUserPublicProfilesHandlerTest.php:40:    private function handler(): GetUserPublicProfilesHandler
tests/Unit/Shared/Infrastructure/Configuration/ConfigMapperTest.php:146:        $treeMapper->method('map')->willThrowException(new \RuntimeException('Непредвиденная ошибка маппинга.'));
tests/Unit/Shared/Infrastructure/Configuration/ConfigMapperTest.php:154:        $this->expectException(\RuntimeException::class);
tests/Unit/Shared/Infrastructure/Framework/Middleware/RateLimitMiddlewareTest.php:116:    private function handler(): RequestHandlerInterface
tests/Unit/Shared/Infrastructure/Framework/Middleware/RateLimitMiddlewareTest.php:120:            public function handle(ServerRequestInterface $request): ResponseInterface
tests/Unit/Modules/Outbox/Infrastructure/OutboxRelayWorkerTest.php:32:                    throw new \RuntimeException('Временная ошибка relay для проверки устойчивости цикла');
tests/Unit/Modules/Outbox/Infrastructure/OutboxRelayWorkerTest.php:66:            ->willThrowException(new \RuntimeException('Постоянная ошибка relay.'));
tests/Unit/Modules/Outbox/Infrastructure/OutboxRelayWorkerTest.php:78:        $this->expectException(\RuntimeException::class);
tests/Unit/Modules/Outbox/Infrastructure/OutboxRelayWorkerTest.php:103:                    throw new \RuntimeException('Временная ошибка relay с паузой.');
tests/Unit/Modules/Outbox/Infrastructure/OutboxRelayWorkerTest.php:139:            ->willThrowException(new \RuntimeException('Постоянная ошибка relay с настроенным порогом.'));
tests/Unit/Modules/Outbox/Infrastructure/OutboxRelayWorkerTest.php:151:        $this->expectException(\RuntimeException::class);
tests/Feature/Modules/User/Application/FindUserForAuthHandlerTest.php:65:    private function handler(): FindUserForAuthHandler
tests/Feature/Modules/Outbox/Console/OutboxRelayCommandTest.php:140:final class LoopModeStartedException extends \RuntimeException {}
tests/Unit/Modules/Outbox/Application/Message/OutboxMessageSerializerTest.php:74:        $this->expectException(\RuntimeException::class);
tests/Feature/Modules/Outbox/Application/OutboxEventStoreTransactionTest.php:84:    public function handle(StoreMediaAndOutboxCommand $storeMediaAndOutboxCommand): StoreMediaAndOutboxResult
tests/Feature/Modules/Outbox/Infrastructure/OutboxRelayPublishTest.php:255:            ?? throw new \RuntimeException('Тестовое outbox-событие не найдено.');
tests/Feature/Modules/Outbox/Infrastructure/OutboxRelayPublishTest.php:358:            ?? throw new \RuntimeException('Тестовое outbox-событие не найдено.');
tests/Feature/Modules/Outbox/Infrastructure/OutboxRelayPublishTest.php:390:            ?? throw new \RuntimeException('Тестовое outbox-событие не найдено.');
tests/Feature/Modules/Outbox/Infrastructure/Fixture/ThrowingQueue.php:22:        throw new \RuntimeException($this->exceptionMessage);
tests/Feature/Modules/Outbox/Infrastructure/OutboxQueueStatusInterceptorFailureTest.php:105:        $this->expectException(\RuntimeException::class);
tests/Feature/Modules/Outbox/Infrastructure/OutboxQueueStatusInterceptorFailureTest.php:115:                    exception: new \RuntimeException('Job упал после перевода события в финальный статус.'),
tests/Feature/Modules/Outbox/Infrastructure/OutboxQueueStatusInterceptorFailureTest.php:128:        $this->expectException(\RuntimeException::class);
tests/Feature/Modules/Outbox/Infrastructure/OutboxQueueStatusInterceptorFailureTest.php:135:                core: new QueueStatusTestCore(new \RuntimeException('Job упал.')),
tests/Feature/Modules/Outbox/Infrastructure/OutboxQueueStatusInterceptorFailureTest.php:169:        $this->expectException(\RuntimeException::class);
tests/Feature/Modules/Outbox/Infrastructure/OutboxQueueStatusInterceptorFailureTest.php:176:                core: new QueueStatusTestCore(new \RuntimeException(\str_repeat('x', 3000))),
tests/Feature/Modules/Outbox/Infrastructure/OutboxQueueStatusInterceptorFailureTest.php:236:        $this->expectException(\RuntimeException::class);
tests/Feature/Modules/Outbox/Infrastructure/OutboxQueueStatusInterceptorFailureTest.php:243:                core: new QueueStatusTestCore(new \RuntimeException('Job окончательно упал.')),
tests/Feature/Modules/Outbox/Infrastructure/Fixture/OutboxQueueStatusInterceptorTestHelpers.php:37:            ?? throw new \RuntimeException('Тестовое outbox-событие не найдено.');
tests/Feature/Modules/Outbox/Infrastructure/Fixture/RetryingOutboxRelayJob.php:16:    public function handle(string $name, string $id, array $payload): void
tests/Feature/Modules/Outbox/Infrastructure/Fixture/MarkHandledDuringPushQueue.php:31:            ?? throw new \RuntimeException('Тестовое outbox-событие не найдено.');
tests/Feature/Modules/Outbox/Infrastructure/Fixture/FailingOutboxRelayJob.php:15:    public function handle(string $name, string $id, array $payload): void
tests/Feature/Modules/Outbox/Infrastructure/Fixture/FailingOutboxRelayJob.php:17:        throw new \RuntimeException('Sync Job упал.');

codex
Нашёл, что рядом уже есть файл `docs/reviews/2026-06-22_01-01_media-video-audio-ffmpeg_cli_meta.md`; он относится к этой же проверке. Прочитаю его тоже, но итог буду давать по фактическому коду, а не просто пересказывать тот файл.
exec
/bin/zsh -lc "nl -ba docs/reviews/2026-06-22_01-01_media-video-audio-ffmpeg_cli_meta.md | sed -n '1,260p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	Reading additional input from stdin...
     2	OpenAI Codex v0.141.0
     3	--------
     4	workdir: /Users/gian_tiaga/Code/yoga-loka-spiral-2
     5	model: gpt-5.5
     6	provider: openai
     7	approval: never
     8	sandbox: workspace-write [workdir, /tmp, $TMPDIR]
     9	reasoning effort: high
    10	reasoning summaries: none
    11	session id: 019eec41-2eae-7893-8dfe-d0b27ebccfac
    12	--------
    13	user
    14	Ты делаешь кросс-CLI мета-проверку готового ревью кода. НЕ делай первичное ревью и НЕ правь код. Прочитай целиком: docs/reviews/2026-06-22_01-01_media-video-audio-ffmpeg.md (это ревью), его target — рабочее дерево (git diff HEAD + untracked файлы модуля Media), план docs/plans/2026-06-20_21-48_media-video-audio-ffmpeg.md, docs/rules.md, docs/arch.md, docs/settings.yaml, связанный research docs/researches/2026-06-20_11-14_media-video-audio-conversions.md. Затем глубоко проверь ревью по плану, правилам, архитектуре и релевантному коду: открой файлы модуля Media (app/src/Modules/Media/**, особенно Infrastructure/FileService/*Ffmpeg*, Application/Command/*, Application/Query/*, Infrastructure/Cycle/MediaWaveformTypecast.php, Domain), миграцию, тесты в tests/**Media** — и оцени ФАКТИЧЕСКУЮ корректность замечаний ревью. Учти, что ревью уже прошло внутреннее мета-ревью и в нём 13 пунктов (план 1, план 2, замечания 1, 2, 2a, 3, 4, 5, 6, 7, 8, 9, 10, 11) + раздел 'Изменения после мета-ревью'. Дай мета-ревью на русском строго в формате: '+' какую РЕАЛЬНУЮ проблему/баг/риск/недостающий тест добавить (с файлом:строкой), '−' что убрать как неверное или недоказанное, '~' что переформулировать или где неверная ссылка/номер строки, '?' что невозможно проверить и почему. Не предлагай перечислять выполненную работу или хвалить. Будь конкретным и опирайся на фактический код.
    15	codex
    16	Использую скилл `eda-review-check`: он как раз про проверку уже готового ревью без правок кода. Сначала прочитаю его инструкции, затем открою ревью, план, правила, архитектуру, настройки, research и релевантный код.
    17	exec
    18	/bin/zsh -lc "sed -n '1,240p' /Users/gian_tiaga/Code/yoga-loka-spiral-2/.codex/skills/eda-review-check/SKILL.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
    19	 succeeded in 0ms:
    20	---
    21	name: eda-review-check
    22	description: 'Проверяет готовое ревью из `docs/reviews/...` специализированными субагентами и, в strict-режиме, соседним CLI. Не делает первичное ревью и не правит код. Читает ревью, target/plan из front matter, `docs/rules.md`, `docs/arch.md`, `docs/settings.yaml` и связанный research при наличии. Запускает architecture-check, rules-check, plan-check только при указанном plan-файле и опциональный quality-check, затем сам применяет подтверждённые замечания к файлу ревью: добавляет, убирает или переформулирует пункты, обновляет score/status/meta_reviewers и раздел изменений после мета-ревью.'
    23	---
    24	
    25	# Скил: Проверка ревью (eda-review-check)
    26	
    27	Берёшь уже сохранённое ревью из `docs/reviews/`, проверяешь его специализированными субагентами, затем сам правишь файл ревью по подтверждённым замечаниям. Этот скил переиспользуется обычным `eda-review` после draft-этапа и может запускаться отдельно.
    28	
    29	## Режим запуска
    30	
    31	Прочитай `docs/settings.yaml`, если файл есть. Прямое указание пользователя в текущем сообщении важнее настроек.
    32	
    33	Дефолты: `defaults.strict: false`, `review.include_code_quality: true`.
    34	
    35	| Настройка | Значения | Аргументы в сообщении |
    36	|---|---|---|
    37	| `defaults.strict` | `true`, `false` | `strict`; выключение: `normal`, `без strict`, `без строгого режима` |
    38	| `review.include_code_quality` | `true`, `false` | «проверь качество кода»; выключение: «без quality», «без проверки качества» |
    39	
    40	`strict` добавляет кросс-CLI соседним агентом. `review.include_code_quality: true` добавляет `quality-check` в обычное мета-ревью.
    41	
    42	## Вход из сообщения пользователя
    43	
    44	Текст рядом с вызовом скилла в текущем сообщении пользователя — главный вход. Сначала разбери именно его: путь к ревью, название или часть названия, «последнее ревью», режим, ограничения и прямые указания.
    45	
    46	Если этого входа достаточно, продолжай без вопроса. `AskUserQuestion` задавай только если входа нет, он противоречивый, найдено несколько равных вариантов или есть риск проверить не то ревью. Старое обсуждение в истории не считай входом, если пользователь не связал его с текущим вызовом явно.
    47	
    48	## Главные правила
    49	
    50	1. **Не делаешь первичное ревью.** Входом всегда является готовый файл `docs/reviews/...`.
    51	2. **Не правишь код.** Можно менять только выбранный файл ревью и вспомогательный файл `${REVIEW_FILE%.md}_cli_meta.md` в strict-режиме.
    52	3. **Мета-ревью специализированными агентами обязательно.** Базовые роли: `architecture-check`, `rules-check`. `plan-check` запускай только если в front matter ревью `plan` указывает на конкретный файл. Если включена проверка качества кода — добавь `quality-check` в тот же batch.
    53	4. **Главный редактор ревью — ты.** Субагенты дают предложения; решение добавить, убрать или переформулировать пункт принимаешь сам.
    54	5. **Ревью остаётся ревью только по проблемам.** Не добавляй выполненные пункты, похвалу, нейтральный отчёт о diff или общие рассуждения.
    55	6. **Каждое принятое изменение фиксируй в `## Изменения после мета-ревью`.** Отклонённые предложения тоже запиши коротко с причиной.
    56	7. **Оценку пересчитывай после правок.** Если изменился набор или тяжесть замечаний, обнови `score` в front matter и текст в разделе `## Оценка`.
    57	
    58	## Интерактивные вопросы
    59	
    60	Когда инструкция говорит `AskUserQuestion`, это означает блокирующий интерактивный вопрос.
    61	- Claude Code: используй `AskUserQuestion`.
    62	- Codex interactive: если доступен `request_user_input`, используй его. Если tool недоступен, задай один короткий вопрос в чат, дай варианты 1–3, напиши «Ответь номером или своим вариантом. Я продолжу только после ответа.» и остановись.
    63	- Codex exec / неинтерактивный запуск: не задавай вопросы и не пытайся читать stdin. Если без ответа нельзя безопасно продолжать, заверши работу со статусом `blocked: нужен ответ пользователя`, перечисли вопросы и варианты, не выполняй рискованные действия.
    64	- Не запускай команды, которые ждут интерактивного ввода в терминале; перед такими командами спроси в хост-интерфейсе или остановись с `blocked`.
    65	
    66	## Этапы
    67	
    68	### 1. Выбрать ревью и прочитать контекст
    69	
    70	Определи `$REVIEW_FILE`:
    71	- если пользователь указал путь к ревью — используй его;
    72	- если указал название или часть названия — найди совпадение в `docs/reviews/`;
    73	- если написал «последнее ревью» — возьми самый новый файл из `docs/reviews/`;
    74	- если входа нет — `AskUserQuestion` со списком последних файлов из `docs/reviews/`;
    75	- если `docs/reviews/` отсутствует или пуста — остановись со статусом `blocked: нужно ревью`.
    76	
    77	Прочитай `$REVIEW_FILE` целиком. Из front matter и текста извлеки `$TARGET`, `$PLAN_FILE`, текущие `mode`, `score`, `status`, `meta_reviewers`. Если `plan: none` или план явно помечен как пропущенный, установи `$PLAN_FILE=none`. Если target указывает на diff-команду, получи актуальный diff. Если target указывает на файл, папку или PR — прочитай достаточно кода, чтобы проверить замечания. Прочитай `docs/rules.md`, `docs/arch.md`, `docs/settings.yaml`, если они есть. Если ревью ссылается на `docs/researches/...`, прочитай связанный research.
    78	
    79	Если `$PLAN_FILE=none` или отсутствует, не угадывай план и не запускай `plan-check`. В разделе изменений после мета-ревью коротко зафиксируй: «plan-check не запускался: план не указан в ревью».
    80	
    81	### 2. Запустить специализированных субагентов
    82	
    83	Запусти **в одном сообщении и одним batch** агентов с выбранными ролями. В интерактивном Codex это означает субагентов, а не отдельные процессы `codex exec`. Не запускай сначала часть агентов, не жди их ответы и не запускай остальных позже: все выбранные проверки должны стартовать до чтения любого результата.
    84	
    85	Роли агентов:
    86	- `plan-check`: запускай только если `$PLAN_FILE` указывает на существующий файл. Проверяет, что реализация действительно выполнила `$PLAN_FILE`, включая частичные пункты, пропущенные требования, лишние отклонения и недостающие проверки.
    87	- `architecture-check`: проверяет следование `docs/arch.md`, границы модулей, контракты, зависимости и соответствие существующим архитектурным решениям.
    88	- `rules-check`: проверяет следование `docs/rules.md`, локальным запретам, обязательным командам, требованиям к тестам, миграциям, документации и процессу.
    89	- `quality-check`: запускай только если `review.include_code_quality: true` или пользователь явно попросил качество кода. Проверяет качество реализации: читаемость, сложность, дублирование, имена, размер и ответственность функций/классов, сцепление модулей, поддерживаемость и соответствие локальному стилю. Quality-замечания помечай типом `quality`; по умолчанию рекомендация `на усмотрение автора`, кроме случаев, где сложность, дублирование или неясная структура реально создают риск багов, тестовых пробелов или дорогого сопровождения.
    90	
    91	Модели:
    92	- Claude Code: запускай все роли через `Agent` tool на `model: "sonnet"`.
    93	- Codex interactive: если доступен инструмент субагентов (`spawn_agent` или аналог), запускай все роли через него на `gpt-5.3-codex`; не используй отдельные `codex exec` для обычного мета-ревью, когда субагенты доступны.
    94	- Codex exec / неинтерактивный fallback: если инструмента субагентов нет, запускай роли через параллельные `codex exec --model gpt-5.3-codex` одной Bash-командой с `&` и общим `wait`.
    95	- Если нужный идентификатор недоступен, возьми ближайшую code-capable модель того же уровня. Не используй быструю слабую модель для этих проверок: они должны читать diff, план при наличии, правила, архитектуру и релевантный код.
    96	
    97	Каждому агенту дай путь к `$REVIEW_FILE`, `$TARGET`, `docs/rules.md`, `docs/arch.md`, `docs/settings.yaml`, связанный research при наличии. `plan-check` дополнительно получает `$PLAN_FILE`, если он есть. Каждый агент должен читать не только ревью, но и фактический diff/код, достаточный для проверки своей роли.
    98	
    99	Формат ответа агентов:
   100	- `+` какую проблему добавить в ревью;
   101	- `−` что убрать как неверное или недоказанное;
   102	- `~` что переформулировать;
   103	- `?` что невозможно проверить и почему.
   104	
   105	Если `$PLAN_FILE` не передан, равен `none` или файл не найден, `plan-check` не запускай.
   106	
   107	### 3. Применить результаты обычного мета-ревью
   108	
   109	Когда все агенты ответили: прочитай ответы, **сам реши**, что применять. Добавляй только подтверждённые проблемы; предложения вида «добавить, что пункт выполнен» отклоняй как неформат ревью. Внеси правки в файл ревью.
   110	
   111	В разделе `## Изменения после мета-ревью` запиши:
   112	
   113	```markdown
   114	### После plan-check / architecture-check / rules-check / quality-check
   115	- **+ Добавлено:** <короткий список>
   116	- **~ Изменено:** <короткий список>
   117	- **− Убрано:** <короткий список>
   118	- **Отклонено:** <короткий список с причиной>
   119	```
   120	
   121	Поменяй `status: draft` → `meta-reviewed`, если статус был draft. В `meta_reviewers` добавь только фактически запущенные роли: `architecture-check`, `rules-check`, `plan-check` при наличии `$PLAN_FILE`, а если запускался — `quality-check`, и фактические модели, например `gpt-5.3-codex` или `sonnet`. Если оценка после корректировок изменилась — обнови `score`.
   122	
   123	### 4. Кросс-CLI: отдать соседнему агенту — только в `strict`
   124	
   125	Обычный режим — пропусти этап 4, иди на финал. `status: meta-reviewed` оставь как есть.
   126	
   127	В `strict`: отдай файл соседнему агенту через `Bash`. Если ты в Claude Code — запускай Codex CLI:
   128	
   129	```bash
   130	codex exec "Прочитай $REVIEW_FILE, $TARGET, $PLAN_FILE, docs/rules.md, docs/arch.md, связанный research при наличии. Затем глубоко проверь ревью по плану, правилам, архитектуре и релевантному коду: открой файлы, модули, API и тесты, которые следуют из diff и замечаний, и оцени фактическую корректность. Мета-ревью на русском: какие проблемы добавить, убрать, переформулировать; какие баги, риски для смежного кода и недостающие тесты пропущены. Не предлагай перечислять выполненную работу. Формат: '+', '−', '~', '?'." > "${REVIEW_FILE%.md}_cli_meta.md"
   131	```
   132	
   133	Если ты в Codex — запускай Claude CLI с теми же требованиями глубины:
   134	
   135	```bash
   136	claude -p "Прочитай $REVIEW_FILE, $TARGET, $PLAN_FILE, docs/rules.md, docs/arch.md, связанный research при наличии. Затем глубоко проверь ревью по плану, правилам, архитектуре и релевантному коду: открой файлы, модули, API и тесты, которые следуют из diff и замечаний, и оцени фактическую корректность. Мета-ревью на русском: какие проблемы добавить, убрать, переформулировать; какие баги, риски для смежного кода и недостающие тесты пропущены. Не предлагай перечислять выполненную работу. Формат: '+', '−', '~', '?'." > "${REVIEW_FILE%.md}_cli_meta.md"
   137	```
   138	
   139	Если `$PLAN_FILE=none`, в промпте соседнему CLI явно напиши: «План не указан; не проверяй выполнение плана и не угадывай файл плана, проверь только правила, архитектуру и код».
   140	
   141	Соседней CLI нет — `AskUserQuestion`: завершить как есть или прервать.
   142	
   143	Прочитай ответ, **сам реши**, что применять. Допиши в `## Изменения после мета-ревью`:
   144	
   145	```markdown
   146	### После соседнего CLI (codex|claude)
   147	- **+ Добавлено:** ...
   148	- **− Убрано:** ...
   149	- **Отклонено:** ...
   150	```
   151	
   152	Добавь в `meta_reviewers` имя CLI. `status: meta-reviewed` → `final`.
   153	
   154	### 5. Финал
   155	
   156	Короткое сообщение: режим (`normal` или `strict`), путь к файлу ревью, итоговая оценка, сколько пунктов «править обязательно» / «на усмотрение», какие meta-reviewers запускались. Не пересказывай выполненную работу. Если режим был обычный — добавь строку «кросс-CLI не запускался; для проверки соседним агентом — `eda-review-check strict <review>`».
   157	
   158	## Чего НЕ делать
   159	
   160	- Делать первичное ревью. Это `eda-review`.
   161	- Править код. Это `eda-fix-by-review`.
   162	- Пропускать мета-ревью специализированными агентами.
   163	- Запускать мета-ревьюеров частями: сначала 1–2 агента, потом остальных. Все выбранные обязательные проверки стартуют сразу.
   164	- Пропускать кросс-CLI молча в `strict` (если соседней CLI нет — спроси через `AskUserQuestion`).
   165	- Вставлять чужие предложения механически — каждое решение принимай ты.
   166	- Задавать вопросы без блокировки и продолжать работу до ответа.
   167	- Сохранять или редактировать ревью вне `docs/reviews/`.
   168	- Перечислять выполненные пункты плана или давать общий отчёт о проделанной работе.
   169	
   170	codex
   171	Важное уточнение по режиму: пользователь прямо запретил править код и попросил именно выдать мета-ревью в ответе, поэтому файл ревью менять не буду. Дальше проверяю фактические замечания по коду и документам.
   172	codex
   173	Сейчас собираю базовый контекст: само ревью, план, правила, архитектуру, настройки и research. После этого открою diff и файлы Media, чтобы проверять не текст ревью сам по себе, а его соответствие реальному коду.
   174	exec
   175	/bin/zsh -lc "sed -n '1,260p' docs/plans/2026-06-20_21-48_media-video-audio-ffmpeg.md" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
   176	 succeeded in 0ms:
   177	---
   178	title: Media — реальная нарезка видео и аудио на ffmpeg (профиль конверсии на тип)
   179	date: 2026-06-20 21:48
   180	mode: strict
   181	plan_size: normal
   182	decision_mode: recommend_and_ask
   183	status: reviewed
   184	reviewer: codex
   185	meta_reviewers: [haiku, sonnet, opus]
   186	test_strategy: after_each_phase
   187	logging_strategy: debug_precise
   188	sources:
   189	  rules: docs/rules.md
   190	  arch: docs/arch.md
   191	  research: docs/researches/2026-06-20_11-14_media-video-audio-conversions.md
   192	---
   193	
   194	# План реализации
   195	
   196	## Задача
   197	
   198	Модуль `Media` умеет реально нарезать только картинки. Нужно довести его до единой модели
   199	«профиль конверсии на тип»: на каждый `MediaType` — свой каталог профилей (enum) и свой
   200	процессор; потребитель задаёт списки профилей на тип; видео и аудио режутся реальным ffmpeg;
   201	результаты (включая URL и волну аудио) доступны потребителю. Документы — вне работ (остаются 422).
   202	
   203	Готово, когда:
   204	- аудио принимается (`audio/*` → `MediaType::Audio`), есть полный доменный стек аудио + таблица
   205	  `media_audio_conversions`;
   206	- видео реально транскодируется в mp4/H.264+AAC и даёт кадр-постер (как `MediaImageConversion`
   207	  type=Poster); аудио — в m4a/AAC и даёт массив амплитуд (волну);
   208	- `ProcessMediaHandler` ветвится исчерпывающим `match` по `MediaType`;
   209	- ffmpeg работает с локальными файлами; их владелец и уборщик — инфраструктурный процессор;
   210	- ошибки ffmpeg классифицируются контрактным `MediaProcessorFailedException` с `isTransient()`;
   211	- `GetMediaUrl` отдаёт URL image/video/audio-конверсий; волна — отдельным `GetAudioWaveform`;
   212	- `php-ffmpeg/php-ffmpeg ^1.4` в composer, бинарь ffmpeg в Docker, ffmpeg-настройки в `MediaConfig`;
   213	- `make qa` зелёный, покрытие 100%, все новые сценарии покрыты тестами.
   214	
   215	## Контекст
   216	
   217	Факты из кода (проверено чтением):
   218	
   219	- `ProcessMediaHandler` (`app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php`)
   220	  — без `#[Transactional]`: все S3/Imagick-операции вне транзакции, затем один атомарный
   221	  `persist(media + conversions); run()` с `markReadyMovedTo`. Идемпотентно: на `ready` — no-op;
   222	  конверсии создаются только в финальном flush; детерминированные ключи от `storageKey`,
   223	  unique `(media_id, type)`.
   224	- Сейчас обрабатываются только картинки: `buildConversions()` читает оригинал в строку
   225	  (`getObjectContents`), гоняет `MediaImageProcessorContract::resize`, льёт `putObject`, создаёт
   226	  `MediaImageConversion`. Видео сегодня = только `copyObject` оригинала в `videos/.../source.<ext>`.
   227	- Сообщение `MediaUploaded{mediaId, conversions: list<MediaConversionSpec>}` →
   228	  `ProcessMediaCommand{mediaId, conversions}`. `CompleteMediaUploadHandler` кладёт его в outbox в
   229	  одной транзакции с `markUploaded` и валидирует диапазон пикселей через `MediaPixelDimension`.
   230	- Видео-каркас уже есть: enum `MediaVideoConversionType::NormalizedMp4H264`, сущность
   231	  `MediaVideoConversion` (колонки + `duration_ms`, `bitrate`), `MediaVideoConversionRepository::findByMediaId`
   232	  → `MediaVideoConversionCollection`, связь `Media::videoConversions`, таблица `media_video_conversions`.
   233	  Нет: процессора, постера, создания конверсий, выдачи URL видео.
   234	- Аудио нет нигде. `MediaTypeResolver` (`Application/Service/MediaTypeResolver.php`) отклоняет всё
   235	  кроме `image/*` и `video/*` как 422. `MediaPath::assertValid` regex допускает только
   236	  `uploads|images|videos`; `originalReady` для Audio/Document бросает; нет фабрик
   237	  `videoConversion()`/`audioConversion()`.
   238	- `MediaFileServiceContract` (`Application/Contract/MediaFileServiceContract.php`): только
   239	  `getObjectContents(): string` и `putObject(string)` — файловых методов нет.
   240	- `GetMediaUrlQuery.conversionType: MediaImageConversionType|null`; `GetMediaUrlHandler` ходит только
   241	  в `MediaImageConversionRepository`.
   242	- `ProcessMediaJob` (`Presentation/Job/ProcessMediaJob.php`): признак временной ошибки = только
   243	  `MediaFileServiceFailedException::isTransient()`; ошибки процессора станут терминальными.
   244	- VO (`Domain/ValueObject`): `MediaPixelDimension` (1..100000, `supports()`), `MediaDuration`
   245	  (1..604_800_000 мс), `MediaBitrate` (1..1_000_000_000), `MediaFileSize`, `MediaMimeType`,
   246	  `MediaProcessingError` (null-object `none()`). Нет `MediaSampleRate`, `MediaWaveform`,
   247	  `MediaWaveformPeakCount`, `MediaAudioConversionId`, `MediaAudioConversionType`,
   248	  `MediaAudioConversion`, `MediaAudioConversionCollection`, `MediaAudioConversionRepository`.
   249	- `MediaConfig` (`app/src/Shared/Infrastructure/Configuration/Media/MediaConfig.php`):
   250	  `stagingTtlSeconds, multipartThresholdBytes, multipartPartSizeBytes, imageProcessingDriver`;
   251	  `configName()='media'`; `app/config/media.php` через `env()`; маппинг покрыт `MediaConfigTest`.
   252	- Docker (`docker/Dockerfile`, `ubuntu:26.04`) ставит php8.5 + `imagick`/`gd`, бинаря ffmpeg нет, в
   253	  блоке самопроверки `php -m | grep ...`. `composer.json`: `intervention/image: ^4`, php-ffmpeg нет.
   254	- Тесты: suites `Unit/Kernel/Feature` (`phpunit.xml`). Дублёры: `tests/.../Fixture/FakeS3Client`,
   255	  `FakeS3ClientProvider`, `tests/.../Flow/Fixture/RecordingMediaLogger`,
   256	  `ThrowingProcessMediaCommandBus`. База `MediaApplicationTestCase` (`createMedia`, `conversionSpec`,
   257	  доступ к репозиториям). `ProcessMediaHandlerTest` мокает контракты; `ProcessMediaJobTest`
   258	  проверяет классификацию ошибок и логирование уровней.
   259	- `Media` (`Domain/Entity/Media.php`): `recordTemporaryProcessingError`/`recordPermanentProcessingError`,
   260	  `markReadyMovedTo` (Ready→no-op; допускает переход из Uploaded/Processing/ProcessingFailed),

exec
/bin/zsh -lc "nl -ba app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php | sed -n '180,260p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc "nl -ba app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaVideoProcessor.php | sed -n '1,230p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
   180	            ])['ContentLength'] ?? null;
   181	        } catch (S3Exception $exception) {
   182	            if ($this->isNotFound($exception)) {
   183	                return null;
   184	            }
   185	
   186	            throw $this->awsFailure($exception);
   187	        }
   188	
   189	        if (!\is_int($contentLength) && !(\is_string($contentLength) && \ctype_digit($contentLength))) {
   190	            throw MediaFileServiceFailedException::permanent('S3 не вернул корректный ContentLength для объекта.');
   191	        }
   192	
   193	        return new MediaObjectHead(contentLength: MediaFileSize::fromInt((int) $contentLength));
   194	    }
   195	
   196	    #[\Override]
   197	    public function getObjectContents(MediaStorage $storage, MediaPath $path): string
   198	    {
   199	        try {
   200	            $body = $this->client($storage)->getObject([
   201	                'Bucket' => $this->bucketName($storage),
   202	                'Key' => $this->objectKey(storage: $storage, path: $path),
   203	            ])['Body'] ?? null;
   204	        } catch (AwsException $exception) {
   205	            throw $this->awsFailure($exception);
   206	        }
   207	
   208	        if (!$body instanceof StreamInterface) {
   209	            throw MediaFileServiceFailedException::permanent('S3 вернул объект без читаемого тела.');
   210	        }
   211	
   212	        return (string) $body;
   213	    }
   214	
   215	    #[\Override]
   216	    public function downloadToFile(MediaStorage $storage, MediaPath $path): string
   217	    {
   218	        // Уникальный путь без предварительного создания файла: 'SaveAs' создаёт его сам,
   219	        // стримя тело из S3 на диск без полного буфера в памяти. Владелец файла — вызыватель.
   220	        $localFile = \sprintf('%s/media_download_%s', \sys_get_temp_dir(), \bin2hex(\random_bytes(16)));
   221	
   222	        try {
   223	            $this->client($storage)->getObject([
   224	                'Bucket' => $this->bucketName($storage),
   225	                'Key' => $this->objectKey(storage: $storage, path: $path),
   226	                'SaveAs' => $localFile,
   227	            ]);
   228	        } catch (AwsException $exception) {
   229	            throw $this->awsFailure($exception);
   230	        }
   231	
   232	        return $localFile;
   233	    }
   234	
   235	    #[\Override]
   236	    public function uploadFromFile(
   237	        MediaStorage $storage,
   238	        MediaPath $path,
   239	        string $localFile,
   240	        MediaMimeType $mimeType,
   241	    ): void {
   242	        try {
   243	            $this->client($storage)->putObject([
   244	                'Bucket' => $this->bucketName($storage),
   245	                'Key' => $this->objectKey(storage: $storage, path: $path),
   246	                'SourceFile' => $localFile,
   247	                'ContentType' => $mimeType->value(),
   248	            ]);
   249	        } catch (AwsException $exception) {
   250	            throw $this->awsFailure($exception);
   251	        }
   252	    }
   253	
   254	    #[\Override]
   255	    public function putObject(
   256	        MediaStorage $storage,
   257	        MediaPath $path,
   258	        string $contents,
   259	        MediaMimeType $mimeType,
   260	    ): void {

 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Media\Infrastructure\FileService;
     6	
     7	use App\Modules\Media\Application\Contract\MediaVideoProcessorContract;
     8	use App\Modules\Media\Application\Dto\MediaVideoConversionSpec;
     9	use App\Modules\Media\Application\Dto\MediaVideoProcessingResult;
    10	use App\Modules\Media\Domain\Enum\MediaStorage;
    11	use App\Modules\Media\Domain\ValueObject\MediaBitrate;
    12	use App\Modules\Media\Domain\ValueObject\MediaDuration;
    13	use App\Modules\Media\Domain\ValueObject\MediaFileSize;
    14	use App\Modules\Media\Domain\ValueObject\MediaMimeType;
    15	use App\Modules\Media\Domain\ValueObject\MediaPath;
    16	use App\Modules\Media\Domain\ValueObject\MediaPixelDimension;
    17	use FFMpeg\Coordinate\Dimension;
    18	use FFMpeg\Filters\Video\ResizeFilter;
    19	use FFMpeg\Format\Video\X264;
    20	use FFMpeg\Media\Video;
    21	use Symfony\Component\Process\Process;
    22	
    23	/**
    24	 * Процессор видео на php-ffmpeg: транскод оригинала в mp4/H.264+AAC с сохранением пропорций
    25	 * (масштаб «inset», стороны округляются до чётных) и кадр-постер JPEG размером транскода.
    26	 * Владеет временными файлами и удаляет их в finally при любом исходе. Ориентацию ffmpeg
    27	 * применяет по display matrix автоматически при декодировании.
    28	 */
    29	class FfmpegMediaVideoProcessor extends AbstractFfmpegMediaProcessor implements MediaVideoProcessorContract
    30	{
    31	    private const string NORMALIZED_MIME = 'video/mp4';
    32	    private const string POSTER_MIME = 'image/jpeg';
    33	    private const string FAILURE_MESSAGE = 'Не удалось обработать видео.';
    34	
    35	    #[\Override]
    36	    public function process(
    37	        MediaStorage $sourceStorage,
    38	        MediaPath $sourcePath,
    39	        MediaVideoConversionSpec $spec,
    40	        MediaStorage $targetStorage,
    41	        MediaPath $normalizedPath,
    42	        MediaPath $posterPath,
    43	    ): MediaVideoProcessingResult {
    44	        $sourceFile = $this->mediaFileService->downloadToFile(storage: $sourceStorage, path: $sourcePath);
    45	        $normalizedFile = $this->createTempFile('mp4');
    46	        $posterFile = $this->createTempFile('jpg');
    47	
    48	        try {
    49	            $result = $this->encode(
    50	                sourceFile: $sourceFile,
    51	                spec: $spec,
    52	                normalizedFile: $normalizedFile,
    53	                posterFile: $posterFile,
    54	            );
    55	            $this->mediaFileService->uploadFromFile(
    56	                storage: $targetStorage,
    57	                path: $normalizedPath,
    58	                localFile: $normalizedFile,
    59	                mimeType: $result->normalizedMimeType,
    60	            );
    61	            $this->mediaFileService->uploadFromFile(
    62	                storage: $targetStorage,
    63	                path: $posterPath,
    64	                localFile: $posterFile,
    65	                mimeType: $result->posterMimeType,
    66	            );
    67	
    68	            return $result;
    69	        } finally {
    70	            $this->deleteTempFile($sourceFile);
    71	            $this->deleteTempFile($normalizedFile);
    72	            $this->deleteTempFile($posterFile);
    73	        }
    74	    }
    75	
    76	    private function encode(
    77	        string $sourceFile,
    78	        MediaVideoConversionSpec $spec,
    79	        string $normalizedFile,
    80	        string $posterFile,
    81	    ): MediaVideoProcessingResult {
    82	        try {
    83	            return $this->runEncoding(
    84	                sourceFile: $sourceFile,
    85	                spec: $spec,
    86	                normalizedFile: $normalizedFile,
    87	                posterFile: $posterFile,
    88	            );
    89	        } catch (\Throwable $exception) {
    90	            throw self::classifyFailure(exception: $exception, message: self::FAILURE_MESSAGE);
    91	        }
    92	    }
    93	
    94	    protected function runEncoding(
    95	        string $sourceFile,
    96	        MediaVideoConversionSpec $spec,
    97	        string $normalizedFile,
    98	        string $posterFile,
    99	    ): MediaVideoProcessingResult {
   100	        $ffmpeg = $this->ffmpeg();
   101	
   102	        $video = $ffmpeg->open($sourceFile);
   103	        if (!$video instanceof Video) {
   104	            throw new \RuntimeException('Загруженный файл не является видео.');
   105	        }
   106	
   107	        $format = new X264();
   108	        $format->setKiloBitrate(\intdiv(num1: $spec->videoBitrate, num2: 1000));
   109	        $format->setAudioKiloBitrate(\intdiv(num1: $spec->audioBitrate, num2: 1000));
   110	        $video->addFilter(new ResizeFilter(
   111	            dimension: new Dimension(width: $spec->width, height: $spec->height),
   112	            mode: ResizeFilter::RESIZEMODE_INSET,
   113	        ));
   114	        $video->save(format: $format, outputPathfile: $normalizedFile);
   115	
   116	        $this->extractPoster(normalizedFile: $normalizedFile, posterFile: $posterFile);
   117	
   118	        $probe = $ffmpeg->getFFProbe();
   119	        $videoStream = $probe->streams($normalizedFile)->videos()->first();
   120	        if ($videoStream === null) {
   121	            // @codeCoverageIgnoreStart
   122	            // Недостижимо после успешного транскода: в готовом mp4 всегда есть видеопоток.
   123	            throw new \RuntimeException('В транскодированном видео нет видеопотока.');
   124	            // @codeCoverageIgnoreEnd
   125	        }
   126	        $formatInfo = $probe->format($normalizedFile);
   127	
   128	        return new MediaVideoProcessingResult(
   129	            normalizedMimeType: MediaMimeType::fromString(self::NORMALIZED_MIME),
   130	            normalizedSize: MediaFileSize::fromInt((int) \filesize($normalizedFile)),
   131	            width: MediaPixelDimension::fromInt(self::probeInt(data: $videoStream, key: 'width')),
   132	            height: MediaPixelDimension::fromInt(self::probeInt(data: $videoStream, key: 'height')),
   133	            duration: MediaDuration::fromInt((int) \round(self::probeFloat(data: $formatInfo, key: 'duration') * 1000)),
   134	            bitrate: MediaBitrate::fromInt(self::probeInt(data: $formatInfo, key: 'bit_rate')),
   135	            posterMimeType: MediaMimeType::fromString(self::POSTER_MIME),
   136	            posterSize: MediaFileSize::fromInt((int) \filesize($posterFile)),
   137	        );
   138	    }
   139	
   140	    /**
   141	     * Первый кадр транскода как постер JPEG прямым вызовом ffmpeg: читает из нормализованного
   142	     * файла, поэтому постер выходит размером транскода (решение плана), без повторного open().
   143	     */
   144	    private function extractPoster(string $normalizedFile, string $posterFile): void
   145	    {
   146	        $process = new Process([
   147	            $this->mediaConfig->ffmpegBinaryPath,
   148	            '-i', $normalizedFile,
   149	            '-frames:v', '1',
   150	            '-y', $posterFile,
   151	        ]);
   152	        $process->setTimeout((float) $this->mediaConfig->ffmpegTimeoutSeconds);
   153	        $process->mustRun();
   154	    }
   155	}

exec
/bin/zsh -lc "nl -ba app/src/Modules/Media/Infrastructure/FileService/AbstractFfmpegMediaProcessor.php | sed -n '1,130p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc "nl -ba app/src/Modules/Media/Infrastructure/FileService/FfmpegMediaAudioProcessor.php | sed -n '1,240p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Media\Infrastructure\FileService;
     6	
     7	use App\Modules\Media\Application\Contract\MediaFileServiceContract;
     8	use App\Modules\Media\Application\Exception\MediaProcessorFailedException;
     9	use App\Shared\Infrastructure\Configuration\Media\MediaConfig;
    10	use FFMpeg\FFMpeg;
    11	use FFMpeg\FFProbe\DataMapping\AbstractData;
    12	use Symfony\Component\Process\Exception\ProcessTimedOutException;
    13	
    14	/**
    15	 * Общая инфраструктура ffmpeg-процессоров видео и аудио: сборка клиента php-ffmpeg из MediaConfig,
    16	 * управление временными файлами и классификация сбоев ffmpeg во временную/постоянную ошибку.
    17	 *
    18	 * Класс не final намеренно: конкретные процессоры выносят сам вызов ffmpeg в защищённый метод
    19	 * runEncoding(), который тест подменяет в наследнике, чтобы покрыть ветки обработки ошибок без
    20	 * реального таймаута бинаря (реальный ffmpeg проверяется интеграционными тестами).
    21	 */
    22	abstract class AbstractFfmpegMediaProcessor
    23	{
    24	    public function __construct(
    25	        protected readonly MediaFileServiceContract $mediaFileService,
    26	        protected readonly MediaConfig $mediaConfig,
    27	    ) {}
    28	
    29	    protected function ffmpeg(): FFMpeg
    30	    {
    31	        return FFMpeg::create([
    32	            'ffmpeg.binaries' => $this->mediaConfig->ffmpegBinaryPath,
    33	            'ffprobe.binaries' => $this->mediaConfig->ffprobeBinaryPath,
    34	            'ffmpeg.threads' => $this->mediaConfig->ffmpegThreads,
    35	            'timeout' => $this->mediaConfig->ffmpegTimeoutSeconds,
    36	        ]);
    37	    }
    38	
    39	    /**
    40	     * Уникальный путь во временном каталоге с нужным расширением (ffmpeg определяет контейнер по
    41	     * расширению и сам создаёт файл с флагом -y). Без предварительного создания — чтобы не было
    42	     * непокрываемой ветки на ошибку tempnam.
    43	     */
    44	    protected function createTempFile(string $extension): string
    45	    {
    46	        return \sprintf('%s/media_ffmpeg_%s.%s', \sys_get_temp_dir(), \bin2hex(\random_bytes(12)), $extension);
    47	    }
    48	
    49	    protected function deleteTempFile(string $path): void
    50	    {
    51	        if (\is_file($path)) {
    52	            \unlink($path);
    53	        }
    54	    }
    55	
    56	    /**
    57	     * Читает числовое поле ffprobe (get() возвращает mixed на границе с библиотекой) как int.
    58	     * Нечисловое значение для валидного медиа недостижимо; на всякий случай даёт 0 — границы
    59	     * доменных VO (MediaPixelDimension/MediaBitrate) отклонят 0 как невалидный результат.
    60	     */
    61	    protected static function probeInt(AbstractData $data, string $key): int
    62	    {
    63	        $value = $data->get($key);
    64	
    65	        return (int) (\is_numeric($value) ? $value : 0);
    66	    }
    67	
    68	    protected static function probeFloat(AbstractData $data, string $key): float
    69	    {
    70	        $value = $data->get($key);
    71	
    72	        return (float) (\is_numeric($value) ? $value : 0);
    73	    }
    74	
    75	    protected static function classifyFailure(\Throwable $exception, string $message): MediaProcessorFailedException
    76	    {
    77	        return self::isTransientFailure($exception)
    78	            ? MediaProcessorFailedException::transient(message: $message, previous: $exception)
    79	            : MediaProcessorFailedException::permanent(message: $message, previous: $exception);
    80	    }
    81	
    82	    private static function isTransientFailure(\Throwable $exception): bool
    83	    {
    84	        // Таймаут транскодирования — единственный временный (ретраябельный) сбой: ищем
    85	        // ProcessTimedOutException в цепочке причин (php-ffmpeg оборачивает исключения процесса).
    86	        for ($current = $exception; $current !== null; $current = $current->getPrevious()) {
    87	            if ($current instanceof ProcessTimedOutException) {
    88	                return true;
    89	            }
    90	        }
    91	
    92	        return false;
    93	    }
    94	}

 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Media\Infrastructure\FileService;
     6	
     7	use App\Modules\Media\Application\Contract\MediaAudioProcessorContract;
     8	use App\Modules\Media\Application\Dto\MediaAudioConversionSpec;
     9	use App\Modules\Media\Application\Dto\MediaAudioProcessingResult;
    10	use App\Modules\Media\Domain\Enum\MediaStorage;
    11	use App\Modules\Media\Domain\ValueObject\MediaBitrate;
    12	use App\Modules\Media\Domain\ValueObject\MediaDuration;
    13	use App\Modules\Media\Domain\ValueObject\MediaFileSize;
    14	use App\Modules\Media\Domain\ValueObject\MediaMimeType;
    15	use App\Modules\Media\Domain\ValueObject\MediaPath;
    16	use App\Modules\Media\Domain\ValueObject\MediaSampleRate;
    17	use App\Modules\Media\Domain\ValueObject\MediaWaveform;
    18	use FFMpeg\Media\Video;
    19	use Symfony\Component\Process\Process;
    20	
    21	/**
    22	 * Процессор аудио на php-ffmpeg: транскод оригинала в m4a/AAC и извлечение волны амплитуд.
    23	 * Волну php-ffmpeg готовым массивом не отдаёт (только PNG), поэтому берём сырой PCM прямым
    24	 * вызовом ffmpeg (s16le, моно, низкая частота), делим на waveformPeaks интервалов и нормализуем
    25	 * пик каждого в 0..255. Владеет временными файлами и удаляет их в finally при любом исходе.
    26	 */
    27	class FfmpegMediaAudioProcessor extends AbstractFfmpegMediaProcessor implements MediaAudioProcessorContract
    28	{
    29	    private const string NORMALIZED_MIME = 'audio/mp4';
    30	    private const string FAILURE_MESSAGE = 'Не удалось обработать аудио.';
    31	    private const int WAVEFORM_SAMPLE_RATE = 8000;
    32	    private const int PCM_FULL_SCALE = 32767;
    33	    private const int PCM_SIGNED_OFFSET = 65536;
    34	    private const int PCM_SIGNED_THRESHOLD = 32768;
    35	    private const int WAVEFORM_PEAK_MAX = 255;
    36	
    37	    #[\Override]
    38	    public function process(
    39	        MediaStorage $sourceStorage,
    40	        MediaPath $sourcePath,
    41	        MediaAudioConversionSpec $spec,
    42	        MediaStorage $targetStorage,
    43	        MediaPath $normalizedPath,
    44	    ): MediaAudioProcessingResult {
    45	        $sourceFile = $this->mediaFileService->downloadToFile(storage: $sourceStorage, path: $sourcePath);
    46	        $normalizedFile = $this->createTempFile('m4a');
    47	
    48	        try {
    49	            $result = $this->encode(sourceFile: $sourceFile, spec: $spec, normalizedFile: $normalizedFile);
    50	            $this->mediaFileService->uploadFromFile(
    51	                storage: $targetStorage,
    52	                path: $normalizedPath,
    53	                localFile: $normalizedFile,
    54	                mimeType: $result->normalizedMimeType,
    55	            );
    56	
    57	            return $result;
    58	        } finally {
    59	            $this->deleteTempFile($sourceFile);
    60	            $this->deleteTempFile($normalizedFile);
    61	        }
    62	    }
    63	
    64	    private function encode(
    65	        string $sourceFile,
    66	        MediaAudioConversionSpec $spec,
    67	        string $normalizedFile,
    68	    ): MediaAudioProcessingResult {
    69	        try {
    70	            return $this->runEncoding(sourceFile: $sourceFile, spec: $spec, normalizedFile: $normalizedFile);
    71	        } catch (\Throwable $exception) {
    72	            throw self::classifyFailure(exception: $exception, message: self::FAILURE_MESSAGE);
    73	        }
    74	    }
    75	
    76	    protected function runEncoding(
    77	        string $sourceFile,
    78	        MediaAudioConversionSpec $spec,
    79	        string $normalizedFile,
    80	    ): MediaAudioProcessingResult {
    81	        $ffmpeg = $this->ffmpeg();
    82	
    83	        // php-ffmpeg open() возвращает Video (наследник Audio) для файлов с видеодорожкой и Audio
    84	        // для аудио-файлов. Файл с видеопотоком — не аудио, отклоняем (защита инварианта приёма).
    85	        $audio = $ffmpeg->open($sourceFile);
    86	        if ($audio instanceof Video) {
    87	            throw new \RuntimeException('Загруженный файл не является аудио.');
    88	        }
    89	
    90	        $format = new NativeAacAudioFormat();
    91	        $format->setAudioKiloBitrate(\intdiv(num1: $spec->bitrate, num2: 1000));
    92	        $audio->save(format: $format, outputPathfile: $normalizedFile);
    93	
    94	        $probe = $ffmpeg->getFFProbe();
    95	        $audioStream = $probe->streams($normalizedFile)->audios()->first();
    96	        if ($audioStream === null) {
    97	            // @codeCoverageIgnoreStart
    98	            // Недостижимо после успешного транскода: в готовом m4a всегда есть аудиопоток.
    99	            throw new \RuntimeException('В транскодированном аудио нет аудиопотока.');
   100	            // @codeCoverageIgnoreEnd
   101	        }
   102	        $formatInfo = $probe->format($normalizedFile);
   103	
   104	        return new MediaAudioProcessingResult(
   105	            normalizedMimeType: MediaMimeType::fromString(self::NORMALIZED_MIME),
   106	            normalizedSize: MediaFileSize::fromInt((int) \filesize($normalizedFile)),
   107	            duration: MediaDuration::fromInt((int) \round(self::probeFloat(data: $formatInfo, key: 'duration') * 1000)),
   108	            bitrate: MediaBitrate::fromInt(self::probeInt(data: $formatInfo, key: 'bit_rate')),
   109	            sampleRate: MediaSampleRate::fromInt(self::probeInt(data: $audioStream, key: 'sample_rate')),
   110	            waveform: $this->extractWaveform(sourceFile: $sourceFile, peaks: $spec->waveformPeaks),
   111	        );
   112	    }
   113	
   114	    private function extractWaveform(string $sourceFile, int $peaks): MediaWaveform
   115	    {
   116	        $pcmFile = $this->createTempFile('pcm');
   117	
   118	        try {
   119	            $process = new Process([
   120	                $this->mediaConfig->ffmpegBinaryPath,
   121	                '-i', $sourceFile,
   122	                '-ac', '1',
   123	                '-ar', (string) self::WAVEFORM_SAMPLE_RATE,
   124	                '-f', 's16le',
   125	                '-acodec', 'pcm_s16le',
   126	                '-y', $pcmFile,
   127	            ]);
   128	            $process->setTimeout((float) $this->mediaConfig->ffmpegTimeoutSeconds);
   129	            $process->mustRun();
   130	
   131	            return $this->buildWaveform(pcm: (string) \file_get_contents($pcmFile), peaks: $peaks);
   132	        } finally {
   133	            $this->deleteTempFile($pcmFile);
   134	        }
   135	    }
   136	
   137	    private function buildWaveform(string $pcm, int $peaks): MediaWaveform
   138	    {
   139	        /** @var array<int, int>|false $unpacked */
   140	        $unpacked = \unpack(format: 'v*', string: $pcm);
   141	        if ($unpacked === false) {
   142	            // @codeCoverageIgnoreStart
   143	            // Недостижимо: unpack('v*', ...) на любой строке возвращает массив (пустой для пустой).
   144	            throw new \RuntimeException('Не удалось прочитать PCM-данные аудио.');
   145	            // @codeCoverageIgnoreEnd
   146	        }
   147	
   148	        $samples = \array_values($unpacked);
   149	        $sampleCount = \count($samples);
   150	
   151	        $amplitudes = [];
   152	        for ($peakIndex = 0; $peakIndex < $peaks; $peakIndex++) {
   153	            $start = \intdiv(num1: $peakIndex * $sampleCount, num2: $peaks);
   154	            $end = \intdiv(num1: ($peakIndex + 1) * $sampleCount, num2: $peaks);
   155	
   156	            $maxAmplitude = 0;
   157	            for ($sampleIndex = $start; $sampleIndex < $end; $sampleIndex++) {
   158	                $signed = $samples[$sampleIndex];
   159	                if ($signed >= self::PCM_SIGNED_THRESHOLD) {
   160	                    $signed -= self::PCM_SIGNED_OFFSET;
   161	                }
   162	
   163	                $magnitude = \abs($signed);
   164	                if ($magnitude > $maxAmplitude) {
   165	                    $maxAmplitude = $magnitude;
   166	                }
   167	            }
   168	
   169	            // Максимум magnitude = 32768 (для -32768): 32768/32767*255 округляется до 255, поэтому
   170	            // значение всегда укладывается в 0..255 без дополнительного ограничения.
   171	            $amplitudes[] = (int) \round($maxAmplitude / self::PCM_FULL_SCALE * self::WAVEFORM_PEAK_MAX);
   172	        }
   173	
   174	        return MediaWaveform::fromPeaks($amplitudes);
   175	    }
   176	}

exec
/bin/zsh -lc "nl -ba app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php | sed -n '1,240p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
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
    25	use App\Shared\Domain\Exception\InvalidDomainValueException;
    26	use App\Shared\Domain\Exception\NotFoundException;
    27	use Cycle\ORM\EntityManagerInterface;
    28	use GianTiaga\SpiralCqrs\Attribute\LogOperation;
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
    89	            MediaType::Document => throw new InvalidDomainValueException('Обработка документов не поддержана.'),
    90	        };
    91	
    92	        $targetPath = MediaPath::originalReady(storageKey: $media->storageKey, type: $media->type, extension: $extension);
    93	        $this->mediaFileService->copyObject(
    94	            fromStorage: $media->storage,
    95	            fromPath: $media->path,
    96	            toStorage: $targetStorage,
    97	            toPath: $targetPath,
    98	        );
    99	        $media->markReadyMovedTo(storage: $targetStorage, path: $targetPath);
   100	
   101	        $this->entityManager->persist($media);
   102	        foreach ($conversions as $conversion) {
   103	            $this->entityManager->persist($conversion);
   104	        }
   105	        $this->entityManager->run();
   106	
   107	        $this->logger->debug(message: 'Медиа готово.', context: [
   108	            'mediaId' => $media->id->value(),
   109	            'targetStorage' => $targetStorage->value,
   110	            'conversions' => \count($conversions),
   111	        ]);
   112	    }
   113	
   114	    /**
   115	     * @return list<MediaImageConversion>
   116	     */
   117	    private function buildImageConversions(
   118	        Media $media,
   119	        MediaConversionPlan $plan,
   120	        MediaStorage $targetStorage,
   121	        string $extension,
   122	    ): array {
   123	        if ($plan->image === []) {
   124	            return [];
   125	        }
   126	
   127	        $originalContents = $this->mediaFileService->getObjectContents(storage: $media->storage, path: $media->path);
   128	
   129	        $conversions = [];
   130	
   131	        foreach ($plan->image as $spec) {
   132	            $conversionResult = $this->mediaImageProcessor->resize(
   133	                originalContents: $originalContents,
   134	                width: MediaPixelDimension::fromInt($spec->width),
   135	                height: MediaPixelDimension::fromInt($spec->height),
   136	                targetMimeType: $media->mimeType,
   137	            );
   138	            $conversionPath = MediaPath::imageConversion(
   139	                storageKey: $media->storageKey,
   140	                type: $spec->type,
   141	                extension: $extension,
   142	            );
   143	            $this->mediaFileService->putObject(
   144	                storage: $targetStorage,
   145	                path: $conversionPath,
   146	                contents: $conversionResult->contents,
   147	                mimeType: $conversionResult->mimeType,
   148	            );
   149	
   150	            $this->logger->debug(message: 'Создана конверсия изображения.', context: [
   151	                'type' => $spec->type->value,
   152	                'path' => $conversionPath->value(),
   153	                'size' => $conversionResult->size->value(),
   154	            ]);
   155	
   156	            // width/height берём из результата процессора (размер реально записанного объекта),
   157	            // а не из spec — устойчиво к смене режима ресайза. См. README.
   158	            $conversions[] = MediaImageConversion::create(
   159	                media: $media,
   160	                type: $spec->type,
   161	                status: MediaConversionStatus::Ready,
   162	                storage: $targetStorage,
   163	                path: $conversionPath,
   164	                mimeType: $conversionResult->mimeType,
   165	                size: $conversionResult->size,
   166	                width: $conversionResult->width,
   167	                height: $conversionResult->height,
   168	            );
   169	        }
   170	
   171	        return $conversions;
   172	    }
   173	
   174	    /**
   175	     * @return list<MediaVideoConversion|MediaImageConversion>
   176	     */
   177	    private function buildVideoConversions(
   178	        Media $media,
   179	        MediaConversionPlan $plan,
   180	        MediaStorage $targetStorage,
   181	    ): array {
   182	        $conversions = [];
   183	
   184	        foreach ($plan->video as $spec) {
   185	            $normalizedPath = MediaPath::videoConversion(storageKey: $media->storageKey, type: $spec->type, extension: 'mp4');
   186	            $posterPath = MediaPath::imageConversion(
   187	                storageKey: $media->storageKey,
   188	                type: MediaImageConversionType::Poster,
   189	                extension: 'jpg',
   190	            );
   191	
   192	            $result = $this->mediaVideoProcessor->process(
   193	                sourceStorage: $media->storage,
   194	                sourcePath: $media->path,
   195	                spec: $spec,
   196	                targetStorage: $targetStorage,
   197	                normalizedPath: $normalizedPath,
   198	                posterPath: $posterPath,
   199	            );
   200	
   201	            $conversions[] = MediaVideoConversion::create(
   202	                media: $media,
   203	                type: $spec->type,
   204	                status: MediaConversionStatus::Ready,
   205	                storage: $targetStorage,
   206	                path: $normalizedPath,
   207	                mimeType: $result->normalizedMimeType,
   208	                size: $result->normalizedSize,
   209	                width: $result->width,
   210	                height: $result->height,
   211	                duration: $result->duration,
   212	                bitrate: $result->bitrate,
   213	            );
   214	            // Постер видео — MediaImageConversion type=Poster размером транскода (width/height).
   215	            $conversions[] = MediaImageConversion::create(
   216	                media: $media,
   217	                type: MediaImageConversionType::Poster,
   218	                status: MediaConversionStatus::Ready,
   219	                storage: $targetStorage,
   220	                path: $posterPath,
   221	                mimeType: $result->posterMimeType,
   222	                size: $result->posterSize,
   223	                width: $result->width,
   224	                height: $result->height,
   225	            );
   226	
   227	            $this->logger->debug(message: 'Создана конверсия видео и постер.', context: [
   228	                'type' => $spec->type->value,
   229	                'path' => $normalizedPath->value(),
   230	                'size' => $result->normalizedSize->value(),
   231	            ]);
   232	        }
   233	
   234	        return $conversions;
   235	    }
   236	
   237	    /**
   238	     * @return list<MediaAudioConversion>
   239	     */
   240	    private function buildAudioConversions(

codex
Ключевые замечания ревью по ffmpeg и `ProcessMediaHandler` уже подтверждаются кодом: часть ссылок точная, часть требует сужения. Дальше проверяю оставшиеся участки: валидацию плана, Query, typecast, домен, миграцию и тесты.
exec
/bin/zsh -lc "nl -ba app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php | sed -n '1,260p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
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
   123	            MediaType::Document => throw new ValidationException(
   124	                translationKey: 'app.media.unsupported_file_type',
   125	                translationParameters: ['type' => $type->value],
   126	            ),
   127	        };
   128	    }
   129	
   130	    private function assertImagePlan(MediaConversionPlan $plan): void
   131	    {
   132	        if ($plan->video !== [] || $plan->audio !== []) {
   133	            throw new ValidationException('app.media.conversion_plan_type_mismatch');
   134	        }
   135	
   136	        $types = Collection::make($plan->image)->map(
   137	            static fn(MediaImageConversionSpec $spec): string => $spec->type->value,
   138	        );
   139	        if ($types->count() !== $types->unique()->count()) {
   140	            throw new ValidationException('app.media.conversion_duplicate_type');
   141	        }
   142	
   143	        foreach ($plan->image as $spec) {
   144	            if (!MediaPixelDimension::supports($spec->width) || !MediaPixelDimension::supports($spec->height)) {
   145	                throw new ValidationException('app.media.conversion_dimensions_out_of_range');
   146	            }
   147	        }
   148	    }
   149	
   150	    private function assertVideoPlan(MediaConversionPlan $plan): void
   151	    {
   152	        if ($plan->image !== [] || $plan->audio !== []) {
   153	            throw new ValidationException('app.media.conversion_plan_type_mismatch');
   154	        }
   155	
   156	        if (\count($plan->video) !== 1) {
   157	            throw new ValidationException('app.media.video_conversion_profile_required');
   158	        }
   159	
   160	        $spec = $plan->video[0];
   161	        if (!MediaPixelDimension::supports($spec->width) || !MediaPixelDimension::supports($spec->height)) {
   162	            throw new ValidationException('app.media.conversion_dimensions_out_of_range');
   163	        }
   164	
   165	        if (!MediaBitrate::supports($spec->videoBitrate) || !MediaBitrate::supports($spec->audioBitrate)) {
   166	            throw new ValidationException('app.media.conversion_bitrate_out_of_range');
   167	        }
   168	    }
   169	
   170	    private function assertAudioPlan(MediaConversionPlan $plan): void
   171	    {
   172	        if ($plan->image !== [] || $plan->video !== []) {
   173	            throw new ValidationException('app.media.conversion_plan_type_mismatch');
   174	        }
   175	
   176	        if (\count($plan->audio) !== 1) {
   177	            throw new ValidationException('app.media.audio_conversion_profile_required');
   178	        }
   179	
   180	        $spec = $plan->audio[0];
   181	        if (!MediaBitrate::supports($spec->bitrate)) {
   182	            throw new ValidationException('app.media.conversion_bitrate_out_of_range');
   183	        }
   184	
   185	        if (!MediaSampleRate::supports($spec->sampleRate)) {
   186	            throw new ValidationException('app.media.conversion_sample_rate_out_of_range');
   187	        }
   188	
   189	        if (!MediaWaveformPeakCount::supports($spec->waveformPeaks)) {
   190	            throw new ValidationException('app.media.conversion_waveform_peaks_out_of_range');
   191	        }
   192	    }
   193	}

exec
/bin/zsh -lc "nl -ba app/database/migrations/20260620.222100_0_create_media_audio_conversions_table.php | sed -n '1,120p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace Migration;
     6	
     7	use Cycle\Migrations\Migration;
     8	
     9	class CreateMediaAudioConversionsTable extends Migration
    10	{
    11	    protected const DATABASE = null;
    12	
    13	    public function up(): void
    14	    {
    15	        $this->table('media_audio_conversions')
    16	            ->addColumn('id', 'uuid', ['nullable' => false])
    17	            ->addColumn('media_id', 'uuid', ['nullable' => false])
    18	            ->addColumn('type', 'string', ['length' => 64, 'nullable' => false])
    19	            ->addColumn('status', 'string', ['length' => 32, 'nullable' => false])
    20	            ->addColumn('storage', 'string', ['length' => 64, 'nullable' => false])
    21	            ->addColumn('path', 'string', ['length' => 1024, 'nullable' => false])
    22	            ->addColumn('mime_type', 'string', ['length' => 255, 'nullable' => false])
    23	            ->addColumn('size', 'bigInteger', ['nullable' => false])
    24	            ->addColumn('duration_ms', 'bigInteger', ['nullable' => false])
    25	            ->addColumn('bitrate', 'integer', ['nullable' => false])
    26	            ->addColumn('sample_rate', 'integer', ['nullable' => false])
    27	            ->addColumn('waveform', 'json', ['nullable' => false])
    28	            ->addColumn('created_at', 'datetime', ['nullable' => false])
    29	            ->addColumn('updated_at', 'datetime', ['nullable' => false])
    30	            ->setPrimaryKeys(['id'])
    31	            ->addForeignKey(
    32	                ['media_id'],
    33	                'media',
    34	                ['id'],
    35	                ['delete' => 'CASCADE', 'update' => 'CASCADE', 'indexCreate' => false],
    36	            )
    37	            ->addIndex(['media_id', 'type'], ['unique' => true])
    38	            ->addIndex(['media_id'])
    39	            ->addIndex(['status'])
    40	            ->create();
    41	    }
    42	
    43	    public function down(): void
    44	    {
    45	        $this->table('media_audio_conversions')->drop();
    46	    }
    47	}

exec
/bin/zsh -lc "nl -ba app/src/Modules/Media/Application/Query/GetMediaUrl/GetMediaUrlHandler.php | sed -n '1,180p' && nl -ba app/src/Modules/Media/Application/Query/GetAudioWaveform/GetAudioWaveformHandler.php | sed -n '1,120p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
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
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Media\Application\Query\GetAudioWaveform;
     6	
     7	use App\Modules\Media\Domain\Enum\MediaType;
     8	use App\Modules\Media\Domain\ValueObject\MediaId;
     9	use App\Modules\Media\Domain\ValueObject\MediaWaveform;
    10	use App\Modules\Media\Repository\MediaAudioConversionRepository;
    11	use App\Modules\Media\Repository\MediaRepository;
    12	use App\Shared\Domain\Exception\NotFoundException;
    13	
    14	/**
    15	 * Возвращает волну амплитуд нормализованной аудио-конверсии (числа для прогресса воспроизведения).
    16	 * Волна не отдаётся через URL — это отдельный запрос. Медиа не ready / не аудио / без конверсии — 404.
    17	 */
    18	final readonly class GetAudioWaveformHandler
    19	{
    20	    public function __construct(
    21	        private MediaRepository $mediaRepository,
    22	        private MediaAudioConversionRepository $mediaAudioConversionRepository,
    23	    ) {}
    24	
    25	    public function handle(GetAudioWaveformQuery $query): MediaWaveform
    26	    {
    27	        $media = $this->mediaRepository->findById(MediaId::fromString($query->mediaId))
    28	            ?? throw new NotFoundException('app.media.not_found');
    29	
    30	        if (!$media->isReady()) {
    31	            throw new NotFoundException('app.media.not_ready');
    32	        }
    33	
    34	        if ($media->type !== MediaType::Audio) {
    35	            throw new NotFoundException('app.media.conversion_not_found');
    36	        }
    37	
    38	        // Каталог аудио-типов содержит ровно один профиль (NormalizedAacM4a) и на медиа не больше
    39	        // одной такой конверсии, поэтому берём первую без фильтра по единственному типу.
    40	        $conversion = $this->mediaAudioConversionRepository->findByMediaId($media->id)->first()
    41	            ?? throw new NotFoundException('app.media.conversion_not_found');
    42	
    43	        return $conversion->waveform;
    44	    }
    45	}

exec
/bin/zsh -lc "nl -ba app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php | sed -n '240,380p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc "nl -ba app/src/Modules/Media/Application/Exception/MediaProcessorFailedException.php | sed -n '1,90p' && nl -ba app/src/Modules/Media/Application/Exception/MediaFileServiceFailedException.php | sed -n '1,60p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
   240	    private function buildAudioConversions(
   241	        Media $media,
   242	        MediaConversionPlan $plan,
   243	        MediaStorage $targetStorage,
   244	    ): array {
   245	        $conversions = [];
   246	
   247	        foreach ($plan->audio as $spec) {
   248	            $normalizedPath = MediaPath::audioConversion(storageKey: $media->storageKey, type: $spec->type, extension: 'm4a');
   249	
   250	            $result = $this->mediaAudioProcessor->process(
   251	                sourceStorage: $media->storage,
   252	                sourcePath: $media->path,
   253	                spec: $spec,
   254	                targetStorage: $targetStorage,
   255	                normalizedPath: $normalizedPath,
   256	            );
   257	
   258	            $conversions[] = MediaAudioConversion::create(
   259	                media: $media,
   260	                type: $spec->type,
   261	                status: MediaConversionStatus::Ready,
   262	                storage: $targetStorage,
   263	                path: $normalizedPath,
   264	                mimeType: $result->normalizedMimeType,
   265	                size: $result->normalizedSize,
   266	                duration: $result->duration,
   267	                bitrate: $result->bitrate,
   268	                sampleRate: $result->sampleRate,
   269	                waveform: $result->waveform,
   270	            );
   271	
   272	            $this->logger->debug(message: 'Создана конверсия аудио с волной.', context: [
   273	                'type' => $spec->type->value,
   274	                'path' => $normalizedPath->value(),
   275	                'size' => $result->normalizedSize->value(),
   276	            ]);
   277	        }
   278	
   279	        return $conversions;
   280	    }
   281	
   282	    private function targetStorage(MediaVisibility $visibility): MediaStorage
   283	    {
   284	        return match ($visibility) {
   285	            MediaVisibility::Public => MediaStorage::Public,
   286	            MediaVisibility::Private => MediaStorage::Private,
   287	        };
   288	    }
   289	}

 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Media\Application\Exception;
     6	
     7	/**
     8	 * Сбой обработки медиа (ffmpeg) как часть контрактов MediaVideoProcessorContract /
     9	 * MediaAudioProcessorContract. Несёт типизированный признак временной ошибки (`isTransient()`),
    10	 * чтобы потребитель (ProcessMediaJob) выбирал стратегию повтора, не зная о конкретной реализации
    11	 * (php-ffmpeg, бинарь ffmpeg).
    12	 *
    13	 * Слой исключения определяется контрактом, к которому оно относится (Application/Contract),
    14	 * а не местом выброса: бросают его инфраструктурные ffmpeg-процессоры. Сообщения —
    15	 * предопределённые безопасные строки; сырой вывод ffmpeg наружу не прокидывается.
    16	 */
    17	final class MediaProcessorFailedException extends \DomainException
    18	{
    19	    private function __construct(
    20	        string $message,
    21	        private readonly bool $transient,
    22	        \Throwable|null $previous = null,
    23	    ) {
    24	        parent::__construct(message: $message, previous: $previous);
    25	    }
    26	
    27	    /**
    28	     * Временный (ретраябельный) сбой обработки: таймаут транскодирования, нехватка ресурсов процесса.
    29	     */
    30	    public static function transient(string $message, \Throwable $previous): self
    31	    {
    32	        return new self(message: $message, transient: true, previous: $previous);
    33	    }
    34	
    35	    /**
    36	     * Постоянный сбой обработки: битый/неподдерживаемый вход, нештатный код выхода ffmpeg.
    37	     */
    38	    public static function permanent(string $message, \Throwable|null $previous = null): self
    39	    {
    40	        return new self(message: $message, transient: false, previous: $previous);
    41	    }
    42	
    43	    public function isTransient(): bool
    44	    {
    45	        return $this->transient;
    46	    }
    47	}
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Media\Application\Exception;
     6	
     7	/**
     8	 * Сбой файлового сервиса как часть контракта MediaFileServiceContract. Несёт типизированный
     9	 * признак транзиентности (`isTransient()`), чтобы потребитель контракта (ProcessMediaJob) выбирал
    10	 * стратегию повтора, не зная о конкретной реализации хранилища (S3/AWS, локальный драйвер).
    11	 *
    12	 * Слой исключения определяется контрактом, к которому оно относится (Application/Contract),
    13	 * а не местом выброса: бросает его инфраструктурная реализация S3MediaFileService.
    14	 * Сообщения — предопределённые безопасные строки; сырой текст хранилища наружу не прокидывается.
    15	 */
    16	final class MediaFileServiceFailedException extends \DomainException
    17	{
    18	    private function __construct(
    19	        string $message,
    20	        private readonly bool $transient,
    21	        \Throwable|null $previous = null,
    22	    ) {
    23	        parent::__construct(message: $message, previous: $previous);
    24	    }
    25	
    26	    /**
    27	     * Транзиентный (ретраябельный) сбой хранилища: сетевые ошибки, 5xx, throttling.
    28	     */
    29	    public static function transient(string $message, \Throwable $previous): self
    30	    {
    31	        return new self(message: $message, transient: true, previous: $previous);
    32	    }
    33	
    34	    /**
    35	     * Постоянный сбой хранилища: нештатный ответ S3 или ошибка, не подлежащая повтору.
    36	     */
    37	    public static function permanent(string $message, \Throwable|null $previous = null): self
    38	    {
    39	        return new self(message: $message, transient: false, previous: $previous);
    40	    }
    41	
    42	    public function isTransient(): bool
    43	    {
    44	        return $this->transient;
    45	    }
    46	}

exec
/bin/zsh -lc "nl -ba app/src/Modules/Media/Infrastructure/Cycle/MediaWaveformTypecast.php | sed -n '1,120p' && nl -ba app/src/Modules/Media/Infrastructure/Cycle/MediaMultipartPartCollectionTypecast.php | sed -n '1,80p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Media\Infrastructure\Cycle;
     6	
     7	use App\Modules\Media\Domain\ValueObject\MediaWaveform;
     8	use App\Shared\Infrastructure\Cycle\ColumnValueTypecast;
     9	
    10	final class MediaWaveformTypecast implements ColumnValueTypecast
    11	{
    12	    public static function castDatabaseValue(string $value): MediaWaveform
    13	    {
    14	        $payload = \json_decode(
    15	            json: $value,
    16	            associative: true,
    17	            flags: \JSON_THROW_ON_ERROR | \JSON_INVALID_UTF8_SUBSTITUTE,
    18	        );
    19	
    20	        if (!\is_array($payload)) {
    21	            throw new \InvalidArgumentException('Волна аудио должна быть JSON-массивом.');
    22	        }
    23	
    24	        $peaks = [];
    25	        foreach ($payload as $peak) {
    26	            if (!\is_int($peak)) {
    27	                throw new \InvalidArgumentException('Амплитуда волны должна быть целым числом.');
    28	            }
    29	
    30	            $peaks[] = $peak;
    31	        }
    32	
    33	        return MediaWaveform::fromPeaks($peaks);
    34	    }
    35	
    36	    public static function uncastValue(MediaWaveform $value): string
    37	    {
    38	        return \json_encode(
    39	            value: $value->jsonSerialize(),
    40	            flags: \JSON_THROW_ON_ERROR | \JSON_INVALID_UTF8_SUBSTITUTE,
    41	        );
    42	    }
    43	}
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Media\Infrastructure\Cycle;
     6	
     7	use App\Modules\Media\Domain\Collection\MediaMultipartPartCollection;
     8	use App\Modules\Media\Domain\ValueObject\MediaMultipartPart;
     9	use App\Shared\Infrastructure\Cycle\ColumnValueTypecast;
    10	
    11	final class MediaMultipartPartCollectionTypecast implements ColumnValueTypecast
    12	{
    13	    public static function castDatabaseValue(
    14	        string $value,
    15	    ): MediaMultipartPartCollection {
    16	        $payload = \json_decode(
    17	            json: $value,
    18	            associative: true,
    19	            flags: \JSON_THROW_ON_ERROR | \JSON_INVALID_UTF8_SUBSTITUTE,
    20	        );
    21	
    22	        if (!\is_array($payload)) {
    23	            throw new \InvalidArgumentException('Список частей загрузки должен быть JSON-массивом.');
    24	        }
    25	
    26	        $parts = [];
    27	        foreach ($payload as $partPayload) {
    28	            if (!\is_array($partPayload)) {
    29	                throw new \InvalidArgumentException('Часть загрузки имеет неверный формат.');
    30	            }
    31	
    32	            $partNumber = $partPayload['partNumber'] ?? null;
    33	            $eTag = $partPayload['eTag'] ?? null;
    34	
    35	            if (!\is_int($partNumber) || !\is_string($eTag)) {
    36	                throw new \InvalidArgumentException('Часть загрузки имеет неверный формат.');
    37	            }
    38	
    39	            $parts[] = MediaMultipartPart::fromValues(partNumber: $partNumber, eTag: $eTag);
    40	        }
    41	
    42	        return new MediaMultipartPartCollection($parts);
    43	    }
    44	
    45	    public static function uncastValue(
    46	        MediaMultipartPartCollection $value,
    47	    ): string {
    48	        return \json_encode(
    49	            value: $value->jsonSerialize(),
    50	            flags: \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR | \JSON_INVALID_UTF8_SUBSTITUTE,
    51	        );
    52	    }
    53	}

codex
Проверка показывает один возможный новый риск вне текущего ревью: обработка `$peaks > $sampleCount` в волне описана как тестовый пробел, но это уже фактический баг результата для очень короткого аудио. Проверяю тесты, чтобы понять, есть ли покрытие или это нужно повысить до добавляемого замечания.
exec
/bin/zsh -lc "nl -ba tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php | sed -n '1,360p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc "nl -ba tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php | sed -n '1,560p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace Tests\Feature\Modules\Media\Infrastructure;
     6	
     7	use App\Modules\Media\Application\Contract\MediaFileServiceContract;
     8	use App\Modules\Media\Application\Dto\MediaAudioConversionSpec;
     9	use App\Modules\Media\Application\Dto\MediaVideoConversionSpec;
    10	use App\Modules\Media\Application\Exception\MediaFileServiceFailedException;
    11	use App\Modules\Media\Application\Exception\MediaProcessorFailedException;
    12	use App\Modules\Media\Domain\Enum\MediaAudioConversionType;
    13	use App\Modules\Media\Domain\Enum\MediaImageConversionType;
    14	use App\Modules\Media\Domain\Enum\MediaStorage;
    15	use App\Modules\Media\Domain\Enum\MediaVideoConversionType;
    16	use App\Modules\Media\Domain\ValueObject\MediaMimeType;
    17	use App\Modules\Media\Domain\ValueObject\MediaPath;
    18	use App\Modules\Media\Domain\ValueObject\MediaStorageKey;
    19	use App\Modules\Media\Infrastructure\FileService\FfmpegMediaAudioProcessor;
    20	use App\Modules\Media\Infrastructure\FileService\FfmpegMediaVideoProcessor;
    21	use App\Shared\Infrastructure\Configuration\Media\MediaConfig;
    22	use Symfony\Component\Process\Process;
    23	use Tests\TestCase;
    24	
    25	/**
    26	 * Интеграционный тест ffmpeg-процессоров на реальном бинаре (Docker) и MinIO. Фикстуры медиа
    27	 * генерируются прямо в тесте через ffmpeg lavfi (testsrc/sine), бинарных файлов в репозитории нет.
    28	 */
    29	final class FfmpegMediaProcessorIntegrationTest extends TestCase
    30	{
    31	    /**
    32	     * @var list<array{storage: MediaStorage, path: MediaPath}>
    33	     */
    34	    private array $createdObjects = [];
    35	
    36	    public function testVideoProcessorTranscodesToMp4WithPoster(): void
    37	    {
    38	        $storageKey = MediaStorageKey::generate();
    39	        $sourcePath = MediaPath::originalUpload(storageKey: $storageKey, extension: 'mp4');
    40	        $this->putSource($sourcePath, $this->generateVideoBytes(), 'video/mp4');
    41	
    42	        $normalizedPath = MediaPath::videoConversion(
    43	            storageKey: $storageKey,
    44	            type: MediaVideoConversionType::NormalizedMp4H264,
    45	            extension: 'mp4',
    46	        );
    47	        $posterPath = MediaPath::imageConversion(
    48	            storageKey: $storageKey,
    49	            type: MediaImageConversionType::Poster,
    50	            extension: 'jpg',
    51	        );
    52	
    53	        $result = $this->videoProcessor()->process(
    54	            sourceStorage: MediaStorage::Upload,
    55	            sourcePath: $sourcePath,
    56	            spec: $this->videoSpec(),
    57	            targetStorage: MediaStorage::Public,
    58	            normalizedPath: $normalizedPath,
    59	            posterPath: $posterPath,
    60	        );
    61	        $this->track(MediaStorage::Public, $normalizedPath);
    62	        $this->track(MediaStorage::Public, $posterPath);
    63	
    64	        self::assertSame('video/mp4', $result->normalizedMimeType->value());
    65	        self::assertSame('image/jpeg', $result->posterMimeType->value());
    66	        self::assertGreaterThan(0, $result->width->value());
    67	        self::assertGreaterThan(0, $result->height->value());
    68	        self::assertGreaterThanOrEqual(1, $result->duration->value());
    69	        self::assertGreaterThanOrEqual(1, $result->bitrate->value());
    70	        self::assertGreaterThan(0, $result->normalizedSize->value());
    71	        self::assertGreaterThan(0, $result->posterSize->value());
    72	        self::assertNotNull($this->fileService()->headObject(MediaStorage::Public, $normalizedPath));
    73	        self::assertNotNull($this->fileService()->headObject(MediaStorage::Public, $posterPath));
    74	    }
    75	
    76	    public function testAudioProcessorTranscodesToM4aWithWaveform(): void
    77	    {
    78	        $storageKey = MediaStorageKey::generate();
    79	        $sourcePath = MediaPath::originalUpload(storageKey: $storageKey, extension: 'wav');
    80	        $this->putSource($sourcePath, $this->generateAudioBytes(), 'audio/wav');
    81	
    82	        $normalizedPath = MediaPath::audioConversion(
    83	            storageKey: $storageKey,
    84	            type: MediaAudioConversionType::NormalizedAacM4a,
    85	            extension: 'm4a',
    86	        );
    87	
    88	        $result = $this->audioProcessor()->process(
    89	            sourceStorage: MediaStorage::Upload,
    90	            sourcePath: $sourcePath,
    91	            spec: $this->audioSpec(),
    92	            targetStorage: MediaStorage::Public,
    93	            normalizedPath: $normalizedPath,
    94	        );
    95	        $this->track(MediaStorage::Public, $normalizedPath);
    96	
    97	        $peaks = $result->waveform->peaks();
    98	
    99	        self::assertSame('audio/mp4', $result->normalizedMimeType->value());
   100	        self::assertCount(64, $peaks);
   101	        self::assertGreaterThan(0, \max($peaks));
   102	        self::assertLessThanOrEqual(255, \max($peaks));
   103	        self::assertGreaterThanOrEqual(0, \min($peaks));
   104	        self::assertGreaterThanOrEqual(1, $result->duration->value());
   105	        self::assertGreaterThanOrEqual(8000, $result->sampleRate->value());
   106	        self::assertGreaterThanOrEqual(1, $result->bitrate->value());
   107	        self::assertNotNull($this->fileService()->headObject(MediaStorage::Public, $normalizedPath));
   108	    }
   109	
   110	    public function testVideoProcessorRejectsAudioSource(): void
   111	    {
   112	        $storageKey = MediaStorageKey::generate();
   113	        $sourcePath = MediaPath::originalUpload(storageKey: $storageKey, extension: 'wav');
   114	        $this->putSource($sourcePath, $this->generateAudioBytes(), 'audio/wav');
   115	
   116	        $this->expectException(MediaProcessorFailedException::class);
   117	
   118	        $this->videoProcessor()->process(
   119	            sourceStorage: MediaStorage::Upload,
   120	            sourcePath: $sourcePath,
   121	            spec: $this->videoSpec(),
   122	            targetStorage: MediaStorage::Public,
   123	            normalizedPath: MediaPath::videoConversion(
   124	                storageKey: $storageKey,
   125	                type: MediaVideoConversionType::NormalizedMp4H264,
   126	                extension: 'mp4',
   127	            ),
   128	            posterPath: MediaPath::imageConversion(
   129	                storageKey: $storageKey,
   130	                type: MediaImageConversionType::Poster,
   131	                extension: 'jpg',
   132	            ),
   133	        );
   134	    }
   135	
   136	    public function testAudioProcessorRejectsVideoSource(): void
   137	    {
   138	        $storageKey = MediaStorageKey::generate();
   139	        $sourcePath = MediaPath::originalUpload(storageKey: $storageKey, extension: 'mp4');
   140	        $this->putSource($sourcePath, $this->generateVideoBytes(), 'video/mp4');
   141	
   142	        $this->expectException(MediaProcessorFailedException::class);
   143	
   144	        $this->audioProcessor()->process(
   145	            sourceStorage: MediaStorage::Upload,
   146	            sourcePath: $sourcePath,
   147	            spec: $this->audioSpec(),
   148	            targetStorage: MediaStorage::Public,
   149	            normalizedPath: MediaPath::audioConversion(
   150	                storageKey: $storageKey,
   151	                type: MediaAudioConversionType::NormalizedAacM4a,
   152	                extension: 'm4a',
   153	            ),
   154	        );
   155	    }
   156	
   157	    public function testVideoProcessorClassifiesBrokenInputAsPermanentFailure(): void
   158	    {
   159	        $storageKey = MediaStorageKey::generate();
   160	        $sourcePath = MediaPath::originalUpload(storageKey: $storageKey, extension: 'mp4');
   161	        $this->putSource($sourcePath, \random_bytes(2048), 'video/mp4');
   162	
   163	        try {
   164	            $this->videoProcessor()->process(
   165	                sourceStorage: MediaStorage::Upload,
   166	                sourcePath: $sourcePath,
   167	                spec: $this->videoSpec(),
   168	                targetStorage: MediaStorage::Public,
   169	                normalizedPath: MediaPath::videoConversion(
   170	                    storageKey: $storageKey,
   171	                    type: MediaVideoConversionType::NormalizedMp4H264,
   172	                    extension: 'mp4',
   173	                ),
   174	                posterPath: MediaPath::imageConversion(
   175	                    storageKey: $storageKey,
   176	                    type: MediaImageConversionType::Poster,
   177	                    extension: 'jpg',
   178	                ),
   179	            );
   180	            self::fail('Ожидалось MediaProcessorFailedException.');
   181	        } catch (MediaProcessorFailedException $exception) {
   182	            self::assertFalse($exception->isTransient());
   183	        }
   184	    }
   185	
   186	    public function testVideoProcessorFailsWhenSourceMissing(): void
   187	    {
   188	        $storageKey = MediaStorageKey::generate();
   189	
   190	        $this->expectException(MediaFileServiceFailedException::class);
   191	
   192	        $this->videoProcessor()->process(
   193	            sourceStorage: MediaStorage::Upload,
   194	            sourcePath: MediaPath::originalUpload(storageKey: $storageKey, extension: 'mp4'),
   195	            spec: $this->videoSpec(),
   196	            targetStorage: MediaStorage::Public,
   197	            normalizedPath: MediaPath::videoConversion(
   198	                storageKey: $storageKey,
   199	                type: MediaVideoConversionType::NormalizedMp4H264,
   200	                extension: 'mp4',
   201	            ),
   202	            posterPath: MediaPath::imageConversion(
   203	                storageKey: $storageKey,
   204	                type: MediaImageConversionType::Poster,
   205	                extension: 'jpg',
   206	            ),
   207	        );
   208	    }
   209	
   210	    #[\Override]
   211	    protected function tearDown(): void
   212	    {
   213	        foreach ($this->createdObjects as $createdObject) {
   214	            $this->fileService()->deleteObject($createdObject['storage'], $createdObject['path']);
   215	        }
   216	
   217	        $this->createdObjects = [];
   218	
   219	        parent::tearDown();
   220	    }
   221	
   222	    private function videoSpec(): MediaVideoConversionSpec
   223	    {
   224	        return new MediaVideoConversionSpec(
   225	            type: MediaVideoConversionType::NormalizedMp4H264,
   226	            width: 640,
   227	            height: 480,
   228	            videoBitrate: 1_000_000,
   229	            audioBitrate: 128_000,
   230	        );
   231	    }
   232	
   233	    private function audioSpec(): MediaAudioConversionSpec
   234	    {
   235	        return new MediaAudioConversionSpec(
   236	            type: MediaAudioConversionType::NormalizedAacM4a,
   237	            bitrate: 128_000,
   238	            sampleRate: 44_100,
   239	            waveformPeaks: 64,
   240	        );
   241	    }
   242	
   243	    private function putSource(MediaPath $path, string $bytes, string $mime): void
   244	    {
   245	        $this->fileService()->putObject(
   246	            storage: MediaStorage::Upload,
   247	            path: $path,
   248	            contents: $bytes,
   249	            mimeType: MediaMimeType::fromString($mime),
   250	        );
   251	        $this->track(MediaStorage::Upload, $path);
   252	    }
   253	
   254	    private function generateVideoBytes(): string
   255	    {
   256	        return $this->generateFixture('mp4', [
   257	            '-f', 'lavfi', '-i', 'testsrc=duration=1:size=320x240:rate=15',
   258	            '-f', 'lavfi', '-i', 'sine=frequency=440:duration=1',
   259	            '-shortest', '-pix_fmt', 'yuv420p',
   260	        ]);
   261	    }
   262	
   263	    private function generateAudioBytes(): string
   264	    {
   265	        return $this->generateFixture('wav', [
   266	            '-f', 'lavfi', '-i', 'sine=frequency=440:duration=1',
   267	        ]);
   268	    }
   269	
   270	    /**
   271	     * @param list<string> $inputArgs
   272	     */
   273	    private function generateFixture(string $extension, array $inputArgs): string
   274	    {
   275	        $path = \sprintf('%s/ffmpeg_fixture_%s.%s', \sys_get_temp_dir(), \bin2hex(\random_bytes(8)), $extension);
   276	
   277	        $process = new Process([$this->mediaConfig()->ffmpegBinaryPath, ...$inputArgs, '-y', $path]);
   278	        $process->mustRun();
   279	
   280	        $bytes = (string) \file_get_contents($path);
   281	        \unlink($path);
   282	
   283	        return $bytes;
   284	    }
   285	
   286	    private function videoProcessor(): FfmpegMediaVideoProcessor
   287	    {
   288	        return new FfmpegMediaVideoProcessor(
   289	            mediaFileService: $this->fileService(),
   290	            mediaConfig: $this->mediaConfig(),
   291	        );
   292	    }
   293	
   294	    private function audioProcessor(): FfmpegMediaAudioProcessor
   295	    {
   296	        return new FfmpegMediaAudioProcessor(
   297	            mediaFileService: $this->fileService(),
   298	            mediaConfig: $this->mediaConfig(),
   299	        );
   300	    }
   301	
   302	    private function fileService(): MediaFileServiceContract
   303	    {
   304	        return $this->getContainer()->get(MediaFileServiceContract::class);
   305	    }
   306	
   307	    private function mediaConfig(): MediaConfig
   308	    {
   309	        return $this->getContainer()->get(MediaConfig::class);
   310	    }
   311	
   312	    private function track(MediaStorage $storage, MediaPath $path): void
   313	    {
   314	        $this->createdObjects[] = ['storage' => $storage, 'path' => $path];
   315	    }
   316	}

 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace Tests\Feature\Modules\Media\Application;
     6	
     7	use App\Modules\Media\Application\Command\CompleteMediaUpload\CompleteMediaUploadCommand;
     8	use App\Modules\Media\Application\Command\CompleteMediaUpload\CompleteMediaUploadHandler;
     9	use App\Modules\Media\Application\Contract\MediaFileServiceContract;
    10	use App\Modules\Media\Application\Dto\MediaConversionPlan;
    11	use App\Modules\Media\Application\Dto\MediaObjectHead;
    12	use App\Modules\Media\Application\Message\MediaUploaded;
    13	use App\Modules\Media\Domain\Collection\MediaMultipartPartCollection;
    14	use App\Modules\Media\Domain\Entity\Media;
    15	use App\Modules\Media\Domain\Entity\MediaMultipartUpload;
    16	use App\Modules\Media\Domain\Enum\MediaStatus;
    17	use App\Modules\Media\Domain\Enum\MediaType;
    18	use App\Modules\Media\Domain\ValueObject\MediaFileSize;
    19	use App\Modules\Media\Domain\ValueObject\MediaMultipartPart;
    20	use App\Modules\Media\Domain\ValueObject\MediaMultipartPartETag;
    21	use App\Modules\Media\Domain\ValueObject\MediaMultipartPartNumber;
    22	use App\Modules\Media\Domain\ValueObject\MediaMultipartPartsCount;
    23	use App\Modules\Media\Domain\ValueObject\MediaMultipartPartSize;
    24	use App\Modules\Media\Domain\ValueObject\MediaMultipartUploadIdValue;
    25	use App\Modules\Outbox\Application\Contract\OutboxEventStoreContract;
    26	use App\Modules\Outbox\Application\Message\StoredOutboxEventId;
    27	use App\Shared\Domain\Exception\ForbiddenException;
    28	use App\Shared\Domain\Exception\NotFoundException;
    29	use App\Shared\Domain\Exception\ValidationException;
    30	use App\Shared\Domain\ValueObject\UserId;
    31	use PHPUnit\Framework\Attributes\DataProvider;
    32	use Psr\Log\NullLogger;
    33	
    34	final class CompleteMediaUploadHandlerTest extends MediaApplicationTestCase
    35	{
    36	    public function testCompletesSingleUploadAndQueuesProcessing(): void
    37	    {
    38	        $userId = UserId::generate();
    39	        $media = $this->createMedia(userId: $userId, size: MediaFileSize::fromInt(2048));
    40	        $this->persist($media);
    41	
    42	        $captured = null;
    43	        $outboxStore = $this->createMock(OutboxEventStoreContract::class);
    44	        $outboxStore->expects(self::once())->method('add')->willReturnCallback(
    45	            function (MediaUploaded $message) use (&$captured): StoredOutboxEventId {
    46	                $captured = $message;
    47	
    48	                return StoredOutboxEventId::fromString('outbox-1');
    49	            },
    50	        );
    51	
    52	        $result = $this->handler($this->fileServiceWithHead(2048), $outboxStore)->handle(
    53	            new CompleteMediaUploadCommand(
    54	                userId: $userId->value(),
    55	                mediaId: $media->id->value(),
    56	                plan: $this->imagePlan($this->imageConversionSpec()),
    57	                parts: null,
    58	            ),
    59	        );
    60	
    61	        self::assertSame(MediaStatus::Uploaded, $result->status);
    62	        self::assertSame(MediaStatus::Uploaded, $media->status);
    63	        self::assertInstanceOf(MediaUploaded::class, $captured);
    64	        self::assertInstanceOf(MediaConversionPlan::class, $captured->plan);
    65	        self::assertCount(1, $captured->plan->image);
    66	    }
    67	
    68	    public function testCompletesMultipartUpload(): void
    69	    {
    70	        $userId = UserId::generate();
    71	        $media = $this->createMedia(userId: $userId, size: MediaFileSize::fromInt(2048));
    72	        $multipartUpload = $this->multipartUploadFor($media);
    73	        $this->persist($media, $multipartUpload);
    74	
    75	        $fileService = $this->createMock(MediaFileServiceContract::class);
    76	        $fileService->method('headObject')->willReturn(new MediaObjectHead(contentLength: MediaFileSize::fromInt(2048)));
    77	        $fileService->expects(self::once())->method('completeMultipartUpload');
    78	
    79	        $result = $this->handler($fileService, $this->outboxStore())->handle(new CompleteMediaUploadCommand(
    80	            userId: $userId->value(),
    81	            mediaId: $media->id->value(),
    82	            plan: $this->emptyPlan(),
    83	            parts: $this->parts(),
    84	        ));
    85	
    86	        self::assertSame(MediaStatus::Uploaded, $result->status);
    87	    }
    88	
    89	    public function testCompletesVideoUploadWithVideoPlan(): void
    90	    {
    91	        $userId = UserId::generate();
    92	        $media = $this->createMedia(
    93	            userId: $userId,
    94	            type: MediaType::Video,
    95	            size: MediaFileSize::fromInt(2048),
    96	            extension: 'mp4',
    97	            mimeType: 'video/mp4',
    98	        );
    99	        $this->persist($media);
   100	
   101	        $result = $this->handler($this->fileServiceWithHead(2048), $this->outboxStore())->handle(
   102	            new CompleteMediaUploadCommand(
   103	                userId: $userId->value(),
   104	                mediaId: $media->id->value(),
   105	                plan: $this->videoPlan($this->videoConversionSpec()),
   106	                parts: null,
   107	            ),
   108	        );
   109	
   110	        self::assertSame(MediaStatus::Uploaded, $result->status);
   111	    }
   112	
   113	    public function testCompletesAudioUploadWithAudioPlan(): void
   114	    {
   115	        $userId = UserId::generate();
   116	        $media = $this->createMedia(
   117	            userId: $userId,
   118	            type: MediaType::Audio,
   119	            size: MediaFileSize::fromInt(2048),
   120	            extension: 'mp3',
   121	            mimeType: 'audio/mpeg',
   122	        );
   123	        $this->persist($media);
   124	
   125	        $result = $this->handler($this->fileServiceWithHead(2048), $this->outboxStore())->handle(
   126	            new CompleteMediaUploadCommand(
   127	                userId: $userId->value(),
   128	                mediaId: $media->id->value(),
   129	                plan: $this->audioPlan($this->audioConversionSpec()),
   130	                parts: null,
   131	            ),
   132	        );
   133	
   134	        self::assertSame(MediaStatus::Uploaded, $result->status);
   135	    }
   136	
   137	    public function testRejectsCrossTypePlan(): void
   138	    {
   139	        $userId = UserId::generate();
   140	        $media = $this->createMedia(userId: $userId);
   141	        $this->persist($media);
   142	
   143	        $this->expectException(ValidationException::class);
   144	
   145	        $this->handler($this->fileServiceWithHead(1024), $this->outboxStore())->handle(new CompleteMediaUploadCommand(
   146	            userId: $userId->value(),
   147	            mediaId: $media->id->value(),
   148	            plan: $this->videoPlan($this->videoConversionSpec()),
   149	            parts: null,
   150	        ));
   151	    }
   152	
   153	    public function testRejectsDuplicateImageConversionTypes(): void
   154	    {
   155	        $userId = UserId::generate();
   156	        $media = $this->createMedia(userId: $userId);
   157	        $this->persist($media);
   158	
   159	        $this->expectException(ValidationException::class);
   160	
   161	        $this->handler($this->fileServiceWithHead(1024), $this->outboxStore())->handle(new CompleteMediaUploadCommand(
   162	            userId: $userId->value(),
   163	            mediaId: $media->id->value(),
   164	            plan: $this->imagePlan($this->imageConversionSpec(), $this->imageConversionSpec()),
   165	            parts: null,
   166	        ));
   167	    }
   168	
   169	    public function testRejectsEmptyVideoPlanForVideoMedia(): void
   170	    {
   171	        $userId = UserId::generate();
   172	        $media = $this->createMedia(userId: $userId, type: MediaType::Video, extension: 'mp4', mimeType: 'video/mp4');
   173	        $this->persist($media);
   174	
   175	        $this->expectException(ValidationException::class);
   176	
   177	        $this->handler($this->fileServiceWithHead(1024), $this->outboxStore())->handle(new CompleteMediaUploadCommand(
   178	            userId: $userId->value(),
   179	            mediaId: $media->id->value(),
   180	            plan: $this->emptyPlan(),
   181	            parts: null,
   182	        ));
   183	    }
   184	
   185	    public function testRejectsMultipleVideoProfiles(): void
   186	    {
   187	        $userId = UserId::generate();
   188	        $media = $this->createMedia(userId: $userId, type: MediaType::Video, extension: 'mp4', mimeType: 'video/mp4');
   189	        $this->persist($media);
   190	
   191	        $this->expectException(ValidationException::class);
   192	
   193	        $this->handler($this->fileServiceWithHead(1024), $this->outboxStore())->handle(new CompleteMediaUploadCommand(
   194	            userId: $userId->value(),
   195	            mediaId: $media->id->value(),
   196	            plan: $this->videoPlan($this->videoConversionSpec(), $this->videoConversionSpec()),
   197	            parts: null,
   198	        ));
   199	    }
   200	
   201	    public function testRejectsVideoBitrateOutOfRange(): void
   202	    {
   203	        $userId = UserId::generate();
   204	        $media = $this->createMedia(userId: $userId, type: MediaType::Video, extension: 'mp4', mimeType: 'video/mp4');
   205	        $this->persist($media);
   206	
   207	        $this->expectException(ValidationException::class);
   208	
   209	        $this->handler($this->fileServiceWithHead(1024), $this->outboxStore())->handle(new CompleteMediaUploadCommand(
   210	            userId: $userId->value(),
   211	            mediaId: $media->id->value(),
   212	            plan: $this->videoPlan($this->videoConversionSpec(videoBitrate: 0)),
   213	            parts: null,
   214	        ));
   215	    }
   216	
   217	    public function testRejectsAudioSampleRateOutOfRange(): void
   218	    {
   219	        $userId = UserId::generate();
   220	        $media = $this->createMedia(userId: $userId, type: MediaType::Audio, extension: 'mp3', mimeType: 'audio/mpeg');
   221	        $this->persist($media);
   222	
   223	        $this->expectException(ValidationException::class);
   224	
   225	        $this->handler($this->fileServiceWithHead(1024), $this->outboxStore())->handle(new CompleteMediaUploadCommand(
   226	            userId: $userId->value(),
   227	            mediaId: $media->id->value(),
   228	            plan: $this->audioPlan($this->audioConversionSpec(sampleRate: 1)),
   229	            parts: null,
   230	        ));
   231	    }
   232	
   233	    public function testRejectsAudioWaveformPeaksOutOfRange(): void
   234	    {
   235	        $userId = UserId::generate();
   236	        $media = $this->createMedia(userId: $userId, type: MediaType::Audio, extension: 'mp3', mimeType: 'audio/mpeg');
   237	        $this->persist($media);
   238	
   239	        $this->expectException(ValidationException::class);
   240	
   241	        $this->handler($this->fileServiceWithHead(1024), $this->outboxStore())->handle(new CompleteMediaUploadCommand(
   242	            userId: $userId->value(),
   243	            mediaId: $media->id->value(),
   244	            plan: $this->audioPlan($this->audioConversionSpec(waveformPeaks: 100_000)),
   245	            parts: null,
   246	        ));
   247	    }
   248	
   249	    public function testRejectsForeignListForVideoMedia(): void
   250	    {
   251	        $userId = UserId::generate();
   252	        $media = $this->createMedia(userId: $userId, type: MediaType::Video, extension: 'mp4', mimeType: 'video/mp4');
   253	        $this->persist($media);
   254	
   255	        $this->expectException(ValidationException::class);
   256	
   257	        $this->handler($this->fileServiceWithHead(1024), $this->outboxStore())->handle(new CompleteMediaUploadCommand(
   258	            userId: $userId->value(),
   259	            mediaId: $media->id->value(),
   260	            plan: $this->audioPlan($this->audioConversionSpec()),
   261	            parts: null,
   262	        ));
   263	    }
   264	
   265	    public function testRejectsVideoDimensionOutOfRange(): void
   266	    {
   267	        $userId = UserId::generate();
   268	        $media = $this->createMedia(userId: $userId, type: MediaType::Video, extension: 'mp4', mimeType: 'video/mp4');
   269	        $this->persist($media);
   270	
   271	        $this->expectException(ValidationException::class);
   272	
   273	        $this->handler($this->fileServiceWithHead(1024), $this->outboxStore())->handle(new CompleteMediaUploadCommand(
   274	            userId: $userId->value(),
   275	            mediaId: $media->id->value(),
   276	            plan: $this->videoPlan($this->videoConversionSpec(width: 0)),
   277	            parts: null,
   278	        ));
   279	    }
   280	
   281	    public function testRejectsForeignListForAudioMedia(): void
   282	    {
   283	        $userId = UserId::generate();
   284	        $media = $this->createMedia(userId: $userId, type: MediaType::Audio, extension: 'mp3', mimeType: 'audio/mpeg');
   285	        $this->persist($media);
   286	
   287	        $this->expectException(ValidationException::class);
   288	
   289	        $this->handler($this->fileServiceWithHead(1024), $this->outboxStore())->handle(new CompleteMediaUploadCommand(
   290	            userId: $userId->value(),
   291	            mediaId: $media->id->value(),
   292	            plan: $this->videoPlan($this->videoConversionSpec()),
   293	            parts: null,
   294	        ));
   295	    }
   296	
   297	    public function testRejectsEmptyAudioPlanForAudioMedia(): void
   298	    {
   299	        $userId = UserId::generate();
   300	        $media = $this->createMedia(userId: $userId, type: MediaType::Audio, extension: 'mp3', mimeType: 'audio/mpeg');
   301	        $this->persist($media);
   302	
   303	        $this->expectException(ValidationException::class);
   304	
   305	        $this->handler($this->fileServiceWithHead(1024), $this->outboxStore())->handle(new CompleteMediaUploadCommand(
   306	            userId: $userId->value(),
   307	            mediaId: $media->id->value(),
   308	            plan: $this->emptyPlan(),
   309	            parts: null,
   310	        ));
   311	    }
   312	
   313	    public function testRejectsAudioBitrateOutOfRange(): void
   314	    {
   315	        $userId = UserId::generate();
   316	        $media = $this->createMedia(userId: $userId, type: MediaType::Audio, extension: 'mp3', mimeType: 'audio/mpeg');
   317	        $this->persist($media);
   318	
   319	        $this->expectException(ValidationException::class);
   320	
   321	        $this->handler($this->fileServiceWithHead(1024), $this->outboxStore())->handle(new CompleteMediaUploadCommand(
   322	            userId: $userId->value(),
   323	            mediaId: $media->id->value(),
   324	            plan: $this->audioPlan($this->audioConversionSpec(bitrate: 0)),
   325	            parts: null,
   326	        ));
   327	    }
   328	
   329	    public function testRejectsDocumentMedia(): void
   330	    {
   331	        $userId = UserId::generate();
   332	        $media = $this->createMedia(userId: $userId, type: MediaType::Document, extension: 'pdf', mimeType: 'application/pdf');
   333	        $this->persist($media);
   334	
   335	        $this->expectException(ValidationException::class);
   336	
   337	        $this->handler($this->fileServiceWithHead(1024), $this->outboxStore())->handle(new CompleteMediaUploadCommand(
   338	            userId: $userId->value(),
   339	            mediaId: $media->id->value(),
   340	            plan: $this->emptyPlan(),
   341	            parts: null,
   342	        ));
   343	    }
   344	
   345	    public function testRejectsMissingMedia(): void
   346	    {
   347	        $this->expectException(NotFoundException::class);
   348	
   349	        $this->handler($this->fileServiceWithHead(2048), $this->outboxStore())->handle(new CompleteMediaUploadCommand(
   350	            userId: UserId::generate()->value(),
   351	            mediaId: UserId::generate()->value(),
   352	            plan: $this->emptyPlan(),
   353	            parts: null,
   354	        ));
   355	    }
   356	
   357	    public function testRejectsForeignOwner(): void
   358	    {
   359	        $media = $this->createMedia(userId: UserId::generate());
   360	        $this->persist($media);
   361	
   362	        $this->expectException(ForbiddenException::class);
   363	
   364	        $this->handler($this->fileServiceWithHead(1024), $this->outboxStore())->handle(new CompleteMediaUploadCommand(
   365	            userId: UserId::generate()->value(),
   366	            mediaId: $media->id->value(),
   367	            plan: $this->emptyPlan(),
   368	            parts: null,
   369	        ));
   370	    }
   371	
   372	    public function testRejectsMediaNotWaitingUpload(): void
   373	    {
   374	        $userId = UserId::generate();
   375	        $media = $this->createMedia(userId: $userId);
   376	        $media->markUploaded();
   377	        $this->persist($media);
   378	
   379	        $this->expectException(ValidationException::class);
   380	
   381	        $this->handler($this->fileServiceWithHead(1024), $this->outboxStore())->handle(new CompleteMediaUploadCommand(
   382	            userId: $userId->value(),
   383	            mediaId: $media->id->value(),
   384	            plan: $this->emptyPlan(),
   385	            parts: null,
   386	        ));
   387	    }
   388	
   389	    #[DataProvider('outOfRangeConversionDimensionProvider')]
   390	    public function testRejectsConversionDimensionsOutOfDomainRange(int $width, int $height): void
   391	    {
   392	        $userId = UserId::generate();
   393	        $media = $this->createMedia(userId: $userId);
   394	        $this->persist($media);
   395	
   396	        $this->expectException(ValidationException::class);
   397	
   398	        $this->handler($this->fileServiceWithHead(1024), $this->outboxStore())->handle(new CompleteMediaUploadCommand(
   399	            userId: $userId->value(),
   400	            mediaId: $media->id->value(),
   401	            plan: $this->imagePlan($this->imageConversionSpec(width: $width, height: $height)),
   402	            parts: null,
   403	        ));
   404	    }
   405	
   406	    /**
   407	     * @return array<string, array{int, int}>
   408	     */
   409	    public static function outOfRangeConversionDimensionProvider(): array
   410	    {
   411	        return [
   412	            'нулевая ширина (нижняя граница)' => [0, 100],
   413	            'нулевая высота (нижняя граница)' => [100, 0],
   414	            'ширина выше максимума (верхняя граница)' => [200_000, 100],
   415	            'высота выше максимума (верхняя граница)' => [100, 200_000],
   416	        ];
   417	    }
   418	
   419	    public function testRejectsMultipartWithoutUploadRecord(): void
   420	    {
   421	        $userId = UserId::generate();
   422	        $media = $this->createMedia(userId: $userId);
   423	        $this->persist($media);
   424	
   425	        $this->expectException(ValidationException::class);
   426	
   427	        $this->handler($this->fileServiceWithHead(1024), $this->outboxStore())->handle(new CompleteMediaUploadCommand(
   428	            userId: $userId->value(),
   429	            mediaId: $media->id->value(),
   430	            plan: $this->emptyPlan(),
   431	            parts: $this->parts(),
   432	        ));
   433	    }
   434	
   435	    public function testRejectsWhenUploadedObjectMissing(): void
   436	    {
   437	        $userId = UserId::generate();
   438	        $media = $this->createMedia(userId: $userId);
   439	        $this->persist($media);
   440	
   441	        $fileService = $this->createStub(MediaFileServiceContract::class);
   442	        $fileService->method('headObject')->willReturn(null);
   443	
   444	        $this->expectException(ValidationException::class);
   445	
   446	        $this->handler($fileService, $this->outboxStore())->handle(new CompleteMediaUploadCommand(
   447	            userId: $userId->value(),
   448	            mediaId: $media->id->value(),
   449	            plan: $this->emptyPlan(),
   450	            parts: null,
   451	        ));
   452	    }
   453	
   454	    public function testRejectsWhenUploadedObjectSizeMismatches(): void
   455	    {
   456	        $userId = UserId::generate();
   457	        $media = $this->createMedia(userId: $userId, size: MediaFileSize::fromInt(2048));
   458	        $this->persist($media);
   459	
   460	        $this->expectException(ValidationException::class);
   461	
   462	        $this->handler($this->fileServiceWithHead(999), $this->outboxStore())->handle(new CompleteMediaUploadCommand(
   463	            userId: $userId->value(),
   464	            mediaId: $media->id->value(),
   465	            plan: $this->emptyPlan(),
   466	            parts: null,
   467	        ));
   468	    }
   469	
   470	    private function handler(
   471	        MediaFileServiceContract $fileService,
   472	        OutboxEventStoreContract $outboxStore,
   473	    ): CompleteMediaUploadHandler {
   474	        return new CompleteMediaUploadHandler(
   475	            mediaRepository: $this->mediaRepository(),
   476	            mediaMultipartUploadRepository: $this->multipartUploadRepository(),
   477	            mediaFileService: $fileService,
   478	            outboxEventStore: $outboxStore,
   479	            entityManager: $this->entityManager(),
   480	            logger: new NullLogger(),
   481	        );
   482	    }
   483	
   484	    private function fileServiceWithHead(int $contentLength): MediaFileServiceContract
   485	    {
   486	        $fileService = $this->createStub(MediaFileServiceContract::class);
   487	        $fileService->method('headObject')->willReturn(
   488	            new MediaObjectHead(contentLength: MediaFileSize::fromInt($contentLength)),
   489	        );
   490	
   491	        return $fileService;
   492	    }
   493	
   494	    private function outboxStore(): OutboxEventStoreContract
   495	    {
   496	        $outboxStore = $this->createStub(OutboxEventStoreContract::class);
   497	        $outboxStore->method('add')->willReturn(StoredOutboxEventId::fromString('outbox-1'));
   498	
   499	        return $outboxStore;
   500	    }
   501	
   502	    private function multipartUploadFor(Media $media): MediaMultipartUpload
   503	    {
   504	        return MediaMultipartUpload::create(
   505	            media: $media,
   506	            uploadId: MediaMultipartUploadIdValue::fromString('upload-1'),
   507	            partsCount: MediaMultipartPartsCount::fromInt(1),
   508	            partSize: MediaMultipartPartSize::fromInt(5_242_880),
   509	            fileSize: $media->size,
   510	        );
   511	    }
   512	
   513	    private function parts(): MediaMultipartPartCollection
   514	    {
   515	        return new MediaMultipartPartCollection([
   516	            MediaMultipartPart::create(
   517	                partNumber: MediaMultipartPartNumber::fromInt(1),
   518	                eTag: MediaMultipartPartETag::fromString('etag-1'),
   519	            ),
   520	        ]);
   521	    }
   522	}

exec
/bin/zsh -lc "nl -ba tests/Unit/Modules/Media/Infrastructure/Fixture/RecordingAudioProcessor.php | sed -n '1,180p' && nl -ba tests/Unit/Modules/Media/Infrastructure/Fixture/RecordingVideoProcessor.php | sed -n '1,180p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace Tests\Unit\Modules\Media\Infrastructure\Fixture;
     6	
     7	use App\Modules\Media\Application\Dto\MediaAudioConversionSpec;
     8	use App\Modules\Media\Application\Dto\MediaAudioProcessingResult;
     9	use App\Modules\Media\Infrastructure\FileService\FfmpegMediaAudioProcessor;
    10	
    11	/**
    12	 * Подменяет реальный вызов ffmpeg (runEncoding) в аудио-процессоре: либо бросает заданный сбой,
    13	 * либо создаёт выходной файл и возвращает заданный результат. Реальная обработка покрывается
    14	 * интеграционным тестом.
    15	 */
    16	final class RecordingAudioProcessor extends FfmpegMediaAudioProcessor
    17	{
    18	    private \Throwable|null $failure = null;
    19	    private MediaAudioProcessingResult|null $result = null;
    20	
    21	    /**
    22	     * @var list<string>
    23	     */
    24	    public array $touchedFiles = [];
    25	
    26	    public function failWith(\Throwable $failure): void
    27	    {
    28	        $this->failure = $failure;
    29	    }
    30	
    31	    public function succeedWith(MediaAudioProcessingResult $result): void
    32	    {
    33	        $this->result = $result;
    34	    }
    35	
    36	    #[\Override]
    37	    protected function runEncoding(
    38	        string $sourceFile,
    39	        MediaAudioConversionSpec $spec,
    40	        string $normalizedFile,
    41	    ): MediaAudioProcessingResult {
    42	        if ($this->failure !== null) {
    43	            throw $this->failure;
    44	        }
    45	
    46	        \file_put_contents($normalizedFile, 'normalized');
    47	        $this->touchedFiles = [$sourceFile, $normalizedFile];
    48	
    49	        return $this->result ?? throw new \LogicException('Результат обработки не задан.');
    50	    }
    51	}
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace Tests\Unit\Modules\Media\Infrastructure\Fixture;
     6	
     7	use App\Modules\Media\Application\Dto\MediaVideoConversionSpec;
     8	use App\Modules\Media\Application\Dto\MediaVideoProcessingResult;
     9	use App\Modules\Media\Infrastructure\FileService\FfmpegMediaVideoProcessor;
    10	
    11	/**
    12	 * Подменяет реальный вызов ffmpeg (runEncoding) в видео-процессоре: либо бросает заданный сбой
    13	 * (для проверки классификации), либо создаёт выходные файлы и возвращает заданный результат
    14	 * (для проверки загрузки и очистки временных файлов в finally). Сам ffmpeg тут не вызывается —
    15	 * реальная обработка покрывается интеграционным тестом.
    16	 */
    17	final class RecordingVideoProcessor extends FfmpegMediaVideoProcessor
    18	{
    19	    private \Throwable|null $failure = null;
    20	    private MediaVideoProcessingResult|null $result = null;
    21	
    22	    /**
    23	     * @var list<string>
    24	     */
    25	    public array $touchedFiles = [];
    26	
    27	    public function failWith(\Throwable $failure): void
    28	    {
    29	        $this->failure = $failure;
    30	    }
    31	
    32	    public function succeedWith(MediaVideoProcessingResult $result): void
    33	    {
    34	        $this->result = $result;
    35	    }
    36	
    37	    #[\Override]
    38	    protected function runEncoding(
    39	        string $sourceFile,
    40	        MediaVideoConversionSpec $spec,
    41	        string $normalizedFile,
    42	        string $posterFile,
    43	    ): MediaVideoProcessingResult {
    44	        if ($this->failure !== null) {
    45	            // До создания выходных файлов: finally удалит только скачанный оригинал, а выходов нет.
    46	            throw $this->failure;
    47	        }
    48	
    49	        \file_put_contents($normalizedFile, 'normalized');
    50	        \file_put_contents($posterFile, 'poster');
    51	        $this->touchedFiles = [$sourceFile, $normalizedFile, $posterFile];
    52	
    53	        return $this->result ?? throw new \LogicException('Результат обработки не задан.');
    54	    }
    55	}

exec
/bin/zsh -lc "nl -ba tests/Unit/Modules/Media/Infrastructure/FfmpegMediaProcessorTest.php | sed -n '1,360p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace Tests\Unit\Modules\Media\Infrastructure;
     6	
     7	use App\Modules\Media\Application\Contract\MediaFileServiceContract;
     8	use App\Modules\Media\Application\Dto\MediaAudioConversionSpec;
     9	use App\Modules\Media\Application\Dto\MediaAudioProcessingResult;
    10	use App\Modules\Media\Application\Dto\MediaVideoConversionSpec;
    11	use App\Modules\Media\Application\Dto\MediaVideoProcessingResult;
    12	use App\Modules\Media\Application\Exception\MediaFileServiceFailedException;
    13	use App\Modules\Media\Application\Exception\MediaProcessorFailedException;
    14	use App\Modules\Media\Domain\Enum\MediaAudioConversionType;
    15	use App\Modules\Media\Domain\Enum\MediaImageConversionType;
    16	use App\Modules\Media\Domain\Enum\MediaStorage;
    17	use App\Modules\Media\Domain\Enum\MediaVideoConversionType;
    18	use App\Modules\Media\Domain\ValueObject\MediaBitrate;
    19	use App\Modules\Media\Domain\ValueObject\MediaDuration;
    20	use App\Modules\Media\Domain\ValueObject\MediaFileSize;
    21	use App\Modules\Media\Domain\ValueObject\MediaMimeType;
    22	use App\Modules\Media\Domain\ValueObject\MediaPath;
    23	use App\Modules\Media\Domain\ValueObject\MediaPixelDimension;
    24	use App\Modules\Media\Domain\ValueObject\MediaSampleRate;
    25	use App\Modules\Media\Domain\ValueObject\MediaStorageKey;
    26	use App\Modules\Media\Domain\ValueObject\MediaWaveform;
    27	use App\Modules\Media\Infrastructure\FileService\NativeAacAudioFormat;
    28	use App\Shared\Infrastructure\Configuration\Media\MediaConfig;
    29	use PHPUnit\Framework\TestCase;
    30	use Symfony\Component\Process\Exception\ProcessTimedOutException;
    31	use Symfony\Component\Process\Process;
    32	use Tests\Unit\Modules\Media\Infrastructure\Fixture\RecordingAudioProcessor;
    33	use Tests\Unit\Modules\Media\Infrastructure\Fixture\RecordingVideoProcessor;
    34	
    35	final class FfmpegMediaProcessorTest extends TestCase
    36	{
    37	    public function testMediaProcessorFailedExceptionExposesTransience(): void
    38	    {
    39	        $previous = new \RuntimeException('сырой вывод ffmpeg');
    40	
    41	        $transient = MediaProcessorFailedException::transient(message: 'Не удалось обработать видео.', previous: $previous);
    42	        $permanent = MediaProcessorFailedException::permanent(message: 'Не удалось обработать аудио.', previous: $previous);
    43	
    44	        self::assertTrue($transient->isTransient());
    45	        self::assertFalse($permanent->isTransient());
    46	        self::assertSame($previous, $transient->getPrevious());
    47	        self::assertSame('Не удалось обработать видео.', $transient->getMessage());
    48	    }
    49	
    50	    public function testNativeAacAudioFormatUsesNativeCodec(): void
    51	    {
    52	        $format = new NativeAacAudioFormat();
    53	
    54	        self::assertSame('aac', $format->getAudioCodec());
    55	        self::assertSame(['aac'], $format->getAvailableAudioCodecs());
    56	    }
    57	
    58	    public function testVideoProcessorClassifiesTimeoutAsTransient(): void
    59	    {
    60	        $sourceFile = $this->trackedSourceFile();
    61	        $processor = new RecordingVideoProcessor($this->fileServiceReturning($sourceFile), $this->mediaConfig());
    62	        $processor->failWith($this->timeout());
    63	
    64	        try {
    65	            $this->processVideo($processor);
    66	            self::fail('Ожидалось MediaProcessorFailedException.');
    67	        } catch (MediaProcessorFailedException $exception) {
    68	            self::assertTrue($exception->isTransient());
    69	        }
    70	
    71	        self::assertFileDoesNotExist($sourceFile);
    72	    }
    73	
    74	    public function testVideoProcessorClassifiesGenericFailureAsPermanent(): void
    75	    {
    76	        $sourceFile = $this->trackedSourceFile();
    77	        $processor = new RecordingVideoProcessor($this->fileServiceReturning($sourceFile), $this->mediaConfig());
    78	        $processor->failWith(new \RuntimeException('битый файл'));
    79	
    80	        try {
    81	            $this->processVideo($processor);
    82	            self::fail('Ожидалось MediaProcessorFailedException.');
    83	        } catch (MediaProcessorFailedException $exception) {
    84	            self::assertFalse($exception->isTransient());
    85	        }
    86	
    87	        self::assertFileDoesNotExist($sourceFile);
    88	    }
    89	
    90	    public function testVideoProcessorUploadsResultAndCleansTempFilesOnSuccess(): void
    91	    {
    92	        $sourceFile = $this->trackedSourceFile();
    93	        $processor = new RecordingVideoProcessor($this->fileServiceReturning($sourceFile), $this->mediaConfig());
    94	        $processor->succeedWith($this->videoResult());
    95	
    96	        $result = $this->processVideo($processor);
    97	
    98	        self::assertSame('video/mp4', $result->normalizedMimeType->value());
    99	        self::assertSame(1280, $result->width->value());
   100	        self::assertNotSame([], $processor->touchedFiles);
   101	        foreach ($processor->touchedFiles as $touchedFile) {
   102	            self::assertFileDoesNotExist($touchedFile);
   103	        }
   104	    }
   105	
   106	    public function testVideoProcessorCleansTempFilesWhenUploadFails(): void
   107	    {
   108	        $sourceFile = $this->trackedSourceFile();
   109	        $fileService = $this->createStub(MediaFileServiceContract::class);
   110	        $fileService->method('downloadToFile')->willReturn($sourceFile);
   111	        $fileService->method('uploadFromFile')->willThrowException(
   112	            MediaFileServiceFailedException::permanent('Ошибка хранилища.'),
   113	        );
   114	
   115	        $processor = new RecordingVideoProcessor($fileService, $this->mediaConfig());
   116	        $processor->succeedWith($this->videoResult());
   117	
   118	        try {
   119	            $this->processVideo($processor);
   120	            self::fail('Ожидалось MediaFileServiceFailedException.');
   121	        } catch (MediaFileServiceFailedException) {
   122	            // ожидаемо
   123	        }
   124	
   125	        self::assertNotSame([], $processor->touchedFiles);
   126	        foreach ($processor->touchedFiles as $touchedFile) {
   127	            self::assertFileDoesNotExist($touchedFile);
   128	        }
   129	    }
   130	
   131	    public function testAudioProcessorClassifiesTimeoutAsTransient(): void
   132	    {
   133	        $sourceFile = $this->trackedSourceFile();
   134	        $processor = new RecordingAudioProcessor($this->fileServiceReturning($sourceFile), $this->mediaConfig());
   135	        $processor->failWith($this->timeout());
   136	
   137	        try {
   138	            $this->processAudio($processor);
   139	            self::fail('Ожидалось MediaProcessorFailedException.');
   140	        } catch (MediaProcessorFailedException $exception) {
   141	            self::assertTrue($exception->isTransient());
   142	        }
   143	
   144	        self::assertFileDoesNotExist($sourceFile);
   145	    }
   146	
   147	    public function testAudioProcessorClassifiesGenericFailureAsPermanent(): void
   148	    {
   149	        $sourceFile = $this->trackedSourceFile();
   150	        $processor = new RecordingAudioProcessor($this->fileServiceReturning($sourceFile), $this->mediaConfig());
   151	        $processor->failWith(new \RuntimeException('битый файл'));
   152	
   153	        try {
   154	            $this->processAudio($processor);
   155	            self::fail('Ожидалось MediaProcessorFailedException.');
   156	        } catch (MediaProcessorFailedException $exception) {
   157	            self::assertFalse($exception->isTransient());
   158	        }
   159	
   160	        self::assertFileDoesNotExist($sourceFile);
   161	    }
   162	
   163	    public function testAudioProcessorUploadsResultAndCleansTempFilesOnSuccess(): void
   164	    {
   165	        $sourceFile = $this->trackedSourceFile();
   166	        $processor = new RecordingAudioProcessor($this->fileServiceReturning($sourceFile), $this->mediaConfig());
   167	        $processor->succeedWith($this->audioResult());
   168	
   169	        $result = $this->processAudio($processor);
   170	
   171	        self::assertSame('audio/mp4', $result->normalizedMimeType->value());
   172	        self::assertSame([0, 128, 255], $result->waveform->peaks());
   173	        self::assertNotSame([], $processor->touchedFiles);
   174	        foreach ($processor->touchedFiles as $touchedFile) {
   175	            self::assertFileDoesNotExist($touchedFile);
   176	        }
   177	    }
   178	
   179	    public function testAudioProcessorCleansTempFilesWhenUploadFails(): void
   180	    {
   181	        $sourceFile = $this->trackedSourceFile();
   182	        $fileService = $this->createStub(MediaFileServiceContract::class);
   183	        $fileService->method('downloadToFile')->willReturn($sourceFile);
   184	        $fileService->method('uploadFromFile')->willThrowException(
   185	            MediaFileServiceFailedException::permanent('Ошибка хранилища.'),
   186	        );
   187	
   188	        $processor = new RecordingAudioProcessor($fileService, $this->mediaConfig());
   189	        $processor->succeedWith($this->audioResult());
   190	
   191	        try {
   192	            $this->processAudio($processor);
   193	            self::fail('Ожидалось MediaFileServiceFailedException.');
   194	        } catch (MediaFileServiceFailedException) {
   195	            // ожидаемо
   196	        }
   197	
   198	        self::assertNotSame([], $processor->touchedFiles);
   199	        foreach ($processor->touchedFiles as $touchedFile) {
   200	            self::assertFileDoesNotExist($touchedFile);
   201	        }
   202	    }
   203	
   204	    private function processVideo(RecordingVideoProcessor $processor): MediaVideoProcessingResult
   205	    {
   206	        $storageKey = MediaStorageKey::generate();
   207	
   208	        return $processor->process(
   209	            sourceStorage: MediaStorage::Upload,
   210	            sourcePath: MediaPath::originalUpload(storageKey: $storageKey, extension: 'mp4'),
   211	            spec: new MediaVideoConversionSpec(
   212	                type: MediaVideoConversionType::NormalizedMp4H264,
   213	                width: 1280,
   214	                height: 720,
   215	                videoBitrate: 1_000_000,
   216	                audioBitrate: 128_000,
   217	            ),
   218	            targetStorage: MediaStorage::Public,
   219	            normalizedPath: MediaPath::videoConversion(
   220	                storageKey: $storageKey,
   221	                type: MediaVideoConversionType::NormalizedMp4H264,
   222	                extension: 'mp4',
   223	            ),
   224	            posterPath: MediaPath::imageConversion(
   225	                storageKey: $storageKey,
   226	                type: MediaImageConversionType::Poster,
   227	                extension: 'jpg',
   228	            ),
   229	        );
   230	    }
   231	
   232	    private function processAudio(RecordingAudioProcessor $processor): MediaAudioProcessingResult
   233	    {
   234	        $storageKey = MediaStorageKey::generate();
   235	
   236	        return $processor->process(
   237	            sourceStorage: MediaStorage::Upload,
   238	            sourcePath: MediaPath::originalUpload(storageKey: $storageKey, extension: 'wav'),
   239	            spec: new MediaAudioConversionSpec(
   240	                type: MediaAudioConversionType::NormalizedAacM4a,
   241	                bitrate: 128_000,
   242	                sampleRate: 44_100,
   243	                waveformPeaks: 64,
   244	            ),
   245	            targetStorage: MediaStorage::Public,
   246	            normalizedPath: MediaPath::audioConversion(
   247	                storageKey: $storageKey,
   248	                type: MediaAudioConversionType::NormalizedAacM4a,
   249	                extension: 'm4a',
   250	            ),
   251	        );
   252	    }
   253	
   254	    private function videoResult(): MediaVideoProcessingResult
   255	    {
   256	        return new MediaVideoProcessingResult(
   257	            normalizedMimeType: MediaMimeType::fromString('video/mp4'),
   258	            normalizedSize: MediaFileSize::fromInt(2048),
   259	            width: MediaPixelDimension::fromInt(1280),
   260	            height: MediaPixelDimension::fromInt(720),
   261	            duration: MediaDuration::fromInt(1000),
   262	            bitrate: MediaBitrate::fromInt(1_000_000),
   263	            posterMimeType: MediaMimeType::fromString('image/jpeg'),
   264	            posterSize: MediaFileSize::fromInt(512),
   265	        );
   266	    }
   267	
   268	    private function audioResult(): MediaAudioProcessingResult
   269	    {
   270	        return new MediaAudioProcessingResult(
   271	            normalizedMimeType: MediaMimeType::fromString('audio/mp4'),
   272	            normalizedSize: MediaFileSize::fromInt(1024),
   273	            duration: MediaDuration::fromInt(1000),
   274	            bitrate: MediaBitrate::fromInt(128_000),
   275	            sampleRate: MediaSampleRate::fromInt(44_100),
   276	            waveform: MediaWaveform::fromPeaks([0, 128, 255]),
   277	        );
   278	    }
   279	
   280	    private function fileServiceReturning(string $sourceFile): MediaFileServiceContract
   281	    {
   282	        $fileService = $this->createStub(MediaFileServiceContract::class);
   283	        $fileService->method('downloadToFile')->willReturn($sourceFile);
   284	
   285	        return $fileService;
   286	    }
   287	
   288	    private function trackedSourceFile(): string
   289	    {
   290	        $sourceFile = (string) \tempnam(\sys_get_temp_dir(), 'ffmpeg_src_');
   291	        \file_put_contents($sourceFile, 'source');
   292	
   293	        return $sourceFile;
   294	    }
   295	
   296	    private function timeout(): ProcessTimedOutException
   297	    {
   298	        return new ProcessTimedOutException(new Process(['true']), ProcessTimedOutException::TYPE_GENERAL);
   299	    }
   300	
   301	    private function mediaConfig(): MediaConfig
   302	    {
   303	        return new MediaConfig(
   304	            stagingTtlSeconds: 86_400,
   305	            multipartThresholdBytes: 16_777_216,
   306	            multipartPartSizeBytes: 8_388_608,
   307	            imageProcessingDriver: 'imagick',
   308	            ffmpegBinaryPath: '/usr/bin/ffmpeg',
   309	            ffprobeBinaryPath: '/usr/bin/ffprobe',
   310	            ffmpegTimeoutSeconds: 1800,
   311	            ffmpegThreads: 0,
   312	        );
   313	    }
   314	}

exec
/bin/zsh -lc "nl -ba tests/Feature/Modules/Media/Application/GetAudioWaveformHandlerTest.php | sed -n '1,180p' && nl -ba tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php | sed -n '1,360p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace Tests\Feature\Modules\Media\Application;
     6	
     7	use App\Modules\Media\Application\Query\GetAudioWaveform\GetAudioWaveformHandler;
     8	use App\Modules\Media\Application\Query\GetAudioWaveform\GetAudioWaveformQuery;
     9	use App\Modules\Media\Domain\Entity\Media;
    10	use App\Modules\Media\Domain\Entity\MediaAudioConversion;
    11	use App\Modules\Media\Domain\Enum\MediaAudioConversionType;
    12	use App\Modules\Media\Domain\Enum\MediaConversionStatus;
    13	use App\Modules\Media\Domain\Enum\MediaStorage;
    14	use App\Modules\Media\Domain\Enum\MediaType;
    15	use App\Modules\Media\Domain\Enum\MediaVisibility;
    16	use App\Modules\Media\Domain\ValueObject\MediaBitrate;
    17	use App\Modules\Media\Domain\ValueObject\MediaDuration;
    18	use App\Modules\Media\Domain\ValueObject\MediaFileSize;
    19	use App\Modules\Media\Domain\ValueObject\MediaMimeType;
    20	use App\Modules\Media\Domain\ValueObject\MediaPath;
    21	use App\Modules\Media\Domain\ValueObject\MediaSampleRate;
    22	use App\Modules\Media\Domain\ValueObject\MediaWaveform;
    23	use App\Shared\Domain\Exception\NotFoundException;
    24	use App\Shared\Domain\ValueObject\UserId;
    25	
    26	final class GetAudioWaveformHandlerTest extends MediaApplicationTestCase
    27	{
    28	    public function testReturnsWaveformForReadyAudioMedia(): void
    29	    {
    30	        $media = $this->readyAudioMedia();
    31	        $this->persist($media, $this->audioConversion($media));
    32	
    33	        $waveform = $this->handler()->handle(new GetAudioWaveformQuery(mediaId: $media->id->value()));
    34	
    35	        self::assertSame([0, 64, 128, 255], $waveform->peaks());
    36	    }
    37	
    38	    public function testRejectsNonReadyMedia(): void
    39	    {
    40	        $media = $this->createMedia(userId: UserId::generate(), type: MediaType::Audio, extension: 'mp3', mimeType: 'audio/mpeg');
    41	        $this->persist($media);
    42	
    43	        $this->expectException(NotFoundException::class);
    44	
    45	        $this->handler()->handle(new GetAudioWaveformQuery(mediaId: $media->id->value()));
    46	    }
    47	
    48	    public function testRejectsNonAudioMedia(): void
    49	    {
    50	        $media = $this->createMedia(userId: UserId::generate());
    51	        $media->markUploaded();
    52	        $media->markReadyMovedTo(
    53	            MediaStorage::Public,
    54	            MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Image, extension: 'jpg'),
    55	        );
    56	        $this->persist($media);
    57	
    58	        $this->expectException(NotFoundException::class);
    59	
    60	        $this->handler()->handle(new GetAudioWaveformQuery(mediaId: $media->id->value()));
    61	    }
    62	
    63	    public function testRejectsMissingConversion(): void
    64	    {
    65	        $media = $this->readyAudioMedia();
    66	        $this->persist($media);
    67	
    68	        $this->expectException(NotFoundException::class);
    69	
    70	        $this->handler()->handle(new GetAudioWaveformQuery(mediaId: $media->id->value()));
    71	    }
    72	
    73	    public function testRejectsMissingMedia(): void
    74	    {
    75	        $this->expectException(NotFoundException::class);
    76	
    77	        $this->handler()->handle(new GetAudioWaveformQuery(mediaId: UserId::generate()->value()));
    78	    }
    79	
    80	    private function handler(): GetAudioWaveformHandler
    81	    {
    82	        return new GetAudioWaveformHandler(
    83	            mediaRepository: $this->mediaRepository(),
    84	            mediaAudioConversionRepository: $this->audioConversionRepository(),
    85	        );
    86	    }
    87	
    88	    private function readyAudioMedia(): Media
    89	    {
    90	        $media = $this->createMedia(
    91	            userId: UserId::generate(),
    92	            visibility: MediaVisibility::Public,
    93	            type: MediaType::Audio,
    94	            extension: 'mp3',
    95	            mimeType: 'audio/mpeg',
    96	        );
    97	        $media->markUploaded();
    98	        $media->markReadyMovedTo(
    99	            MediaStorage::Public,
   100	            MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Audio, extension: 'mp3'),
   101	        );
   102	
   103	        return $media;
   104	    }
   105	
   106	    private function audioConversion(Media $media): MediaAudioConversion
   107	    {
   108	        return MediaAudioConversion::create(
   109	            media: $media,
   110	            type: MediaAudioConversionType::NormalizedAacM4a,
   111	            status: MediaConversionStatus::Ready,
   112	            storage: MediaStorage::Public,
   113	            path: MediaPath::audioConversion(
   114	                storageKey: $media->storageKey,
   115	                type: MediaAudioConversionType::NormalizedAacM4a,
   116	                extension: 'm4a',
   117	            ),
   118	            mimeType: MediaMimeType::fromString('audio/mp4'),
   119	            size: MediaFileSize::fromInt(2048),
   120	            duration: MediaDuration::fromInt(3000),
   121	            bitrate: MediaBitrate::fromInt(128_000),
   122	            sampleRate: MediaSampleRate::fromInt(44_100),
   123	            waveform: MediaWaveform::fromPeaks([0, 64, 128, 255]),
   124	        );
   125	    }
   126	}
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace Tests\Feature\Modules\Media\Application;
     6	
     7	use App\Modules\Media\Application\Contract\MediaFileServiceContract;
     8	use App\Modules\Media\Application\Query\GetMediaUrl\GetMediaUrlHandler;
     9	use App\Modules\Media\Application\Query\GetMediaUrl\GetMediaUrlQuery;
    10	use App\Modules\Media\Domain\Entity\Media;
    11	use App\Modules\Media\Domain\Entity\MediaAudioConversion;
    12	use App\Modules\Media\Domain\Entity\MediaImageConversion;
    13	use App\Modules\Media\Domain\Entity\MediaVideoConversion;
    14	use App\Modules\Media\Domain\Enum\MediaAudioConversionType;
    15	use App\Modules\Media\Domain\Enum\MediaConversionStatus;
    16	use App\Modules\Media\Domain\Enum\MediaImageConversionType;
    17	use App\Modules\Media\Domain\Enum\MediaStorage;
    18	use App\Modules\Media\Domain\Enum\MediaType;
    19	use App\Modules\Media\Domain\Enum\MediaVideoConversionType;
    20	use App\Modules\Media\Domain\Enum\MediaVisibility;
    21	use App\Modules\Media\Domain\ValueObject\MediaBitrate;
    22	use App\Modules\Media\Domain\ValueObject\MediaDuration;
    23	use App\Modules\Media\Domain\ValueObject\MediaFileSize;
    24	use App\Modules\Media\Domain\ValueObject\MediaMimeType;
    25	use App\Modules\Media\Domain\ValueObject\MediaPath;
    26	use App\Modules\Media\Domain\ValueObject\MediaPixelDimension;
    27	use App\Modules\Media\Domain\ValueObject\MediaSampleRate;
    28	use App\Modules\Media\Domain\ValueObject\MediaWaveform;
    29	use App\Shared\Domain\Exception\NotFoundException;
    30	use App\Shared\Domain\ValueObject\UserId;
    31	
    32	final class GetMediaUrlHandlerTest extends MediaApplicationTestCase
    33	{
    34	    public function testReturnsDirectPublicUrlForPublicMedia(): void
    35	    {
    36	        $media = $this->readyMedia(MediaVisibility::Public);
    37	        $this->persist($media);
    38	
    39	        $fileService = $this->createMock(MediaFileServiceContract::class);
    40	        $fileService->method('publicUrl')->willReturn('http://minio/media-public/object');
    41	        $fileService->expects(self::never())->method('presignGet');
    42	
    43	        $result = $this->handler($fileService)->handle(new GetMediaUrlQuery(
    44	            mediaId: $media->id->value(),
    45	            presignedTtlSeconds: 300,
    46	        ));
    47	
    48	        // Для public TTL не применяется и VO не строится: прямой URL без срока.
    49	        self::assertSame('http://minio/media-public/object', $result->url);
    50	        self::assertNull($result->expiresAt);
    51	    }
    52	
    53	    public function testIgnoresInvalidTtlForPublicMedia(): void
    54	    {
    55	        // Для public-медиа срок не применяется и VO не строится, поэтому заведомо
    56	        // невалидный TTL (вне диапазона MediaPresignedTtl) проходит как успех без presignGet.
    57	        $media = $this->readyMedia(MediaVisibility::Public);
    58	        $this->persist($media);
    59	
    60	        $fileService = $this->createMock(MediaFileServiceContract::class);
    61	        $fileService->method('publicUrl')->willReturn('http://minio/media-public/object');
    62	        $fileService->expects(self::never())->method('presignGet');
    63	
    64	        $result = $this->handler($fileService)->handle(new GetMediaUrlQuery(
    65	            mediaId: $media->id->value(),
    66	            presignedTtlSeconds: 0,
    67	        ));
    68	
    69	        self::assertSame('http://minio/media-public/object', $result->url);
    70	        self::assertNull($result->expiresAt);
    71	    }
    72	
    73	    public function testReturnsPresignedUrlForPrivateMedia(): void
    74	    {
    75	        $media = $this->readyMedia(MediaVisibility::Private);
    76	        $this->persist($media);
    77	
    78	        $capturedExpiresAt = null;
    79	        $fileService = $this->createStub(MediaFileServiceContract::class);
    80	        $fileService->method('presignGet')->willReturnCallback(
    81	            static function (
    82	                MediaStorage $storage,
    83	                MediaPath $path,
    84	                \DateTimeImmutable $expiresAt,
    85	            ) use (&$capturedExpiresAt): string {
    86	                $capturedExpiresAt = $expiresAt;
    87	
    88	                return 'http://minio/signed';
    89	            },
    90	        );
    91	
    92	        $result = $this->handler($fileService)->handle(new GetMediaUrlQuery(
    93	            mediaId: $media->id->value(),
    94	            presignedTtlSeconds: 300,
    95	        ));
    96	
    97	        self::assertSame('http://minio/signed', $result->url);
    98	        self::assertNotNull($result->expiresAt);
    99	
   100	        // TTL presigned GET берётся из запроса (300), а не из конфиг-дефолта 900.
   101	        self::assertNotNull($capturedExpiresAt);
   102	        self::assertEqualsWithDelta(
   103	            new \DateTimeImmutable('+300 seconds')->getTimestamp(),
   104	            $capturedExpiresAt->getTimestamp(),
   105	            5,
   106	        );
   107	    }
   108	
   109	    public function testReturnsUrlForRequestedConversion(): void
   110	    {
   111	        $media = $this->readyMedia(MediaVisibility::Private);
   112	        $conversion = $this->thumbnailConversion($media);
   113	        $this->persist($media, $conversion);
   114	
   115	        $capturedPath = null;
   116	        $capturedExpiresAt = null;
   117	        $fileService = $this->createStub(MediaFileServiceContract::class);
   118	        $fileService->method('presignGet')->willReturnCallback(
   119	            static function (
   120	                MediaStorage $storage,
   121	                MediaPath $path,
   122	                \DateTimeImmutable $expiresAt,
   123	            ) use (&$capturedPath, &$capturedExpiresAt): string {
   124	                $capturedPath = $path->value();
   125	                $capturedExpiresAt = $expiresAt;
   126	
   127	                return 'http://minio/signed-thumbnail';
   128	            },
   129	        );
   130	
   131	        $result = $this->handler($fileService)->handle(new GetMediaUrlQuery(
   132	            mediaId: $media->id->value(),
   133	            presignedTtlSeconds: 450,
   134	            conversionType: MediaImageConversionType::Thumbnail,
   135	        ));
   136	
   137	        self::assertSame('http://minio/signed-thumbnail', $result->url);
   138	        self::assertSame($conversion->path->value(), $capturedPath);
   139	
   140	        // Конверсия private отдаёт presigned URL с тем же сроком из запроса (450).
   141	        self::assertNotNull($capturedExpiresAt);
   142	        self::assertEqualsWithDelta(
   143	            new \DateTimeImmutable('+450 seconds')->getTimestamp(),
   144	            $capturedExpiresAt->getTimestamp(),
   145	            5,
   146	        );
   147	    }
   148	
   149	    public function testReturnsUrlForVideoConversion(): void
   150	    {
   151	        $media = $this->readyMedia(MediaVisibility::Public);
   152	        $conversion = $this->videoConversion($media);
   153	        $this->persist($media, $conversion);
   154	
   155	        $capturedPath = null;
   156	        $fileService = $this->createStub(MediaFileServiceContract::class);
   157	        $fileService->method('publicUrl')->willReturnCallback(
   158	            static function (MediaStorage $storage, MediaPath $path) use (&$capturedPath): string {
   159	                $capturedPath = $path->value();
   160	
   161	                return 'http://minio/media-public/normalized.mp4';
   162	            },
   163	        );
   164	
   165	        $result = $this->handler($fileService)->handle(new GetMediaUrlQuery(
   166	            mediaId: $media->id->value(),
   167	            presignedTtlSeconds: 300,
   168	            conversionType: MediaVideoConversionType::NormalizedMp4H264,
   169	        ));
   170	
   171	        self::assertSame('http://minio/media-public/normalized.mp4', $result->url);
   172	        self::assertSame($conversion->path->value(), $capturedPath);
   173	        self::assertNull($result->expiresAt);
   174	    }
   175	
   176	    public function testReturnsUrlForAudioConversion(): void
   177	    {
   178	        $media = $this->readyMedia(MediaVisibility::Public);
   179	        $conversion = $this->audioConversion($media);
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
   202	    public function testRejectsMissingConversion(): void
   203	    {
   204	        $media = $this->readyMedia(MediaVisibility::Private);
   205	        $this->persist($media);
   206	
   207	        $this->expectException(NotFoundException::class);
   208	
   209	        $this->handler($this->createStub(MediaFileServiceContract::class))->handle(new GetMediaUrlQuery(
   210	            mediaId: $media->id->value(),
   211	            presignedTtlSeconds: 300,
   212	            conversionType: MediaImageConversionType::Poster,
   213	        ));
   214	    }
   215	
   216	    public function testRejectsMediaThatIsNotReady(): void
   217	    {
   218	        $media = $this->createMedia(userId: UserId::generate());
   219	        $this->persist($media);
   220	
   221	        $this->expectException(NotFoundException::class);
   222	
   223	        $this->handler($this->createStub(MediaFileServiceContract::class))
   224	            ->handle(new GetMediaUrlQuery(mediaId: $media->id->value(), presignedTtlSeconds: 300));
   225	    }
   226	
   227	    public function testRejectsMissingMedia(): void
   228	    {
   229	        $this->expectException(NotFoundException::class);
   230	
   231	        $this->handler($this->createStub(MediaFileServiceContract::class))
   232	            ->handle(new GetMediaUrlQuery(mediaId: UserId::generate()->value(), presignedTtlSeconds: 300));
   233	    }
   234	
   235	    private function handler(MediaFileServiceContract $fileService): GetMediaUrlHandler
   236	    {
   237	        return new GetMediaUrlHandler(
   238	            mediaRepository: $this->mediaRepository(),
   239	            mediaImageConversionRepository: $this->imageConversionRepository(),
   240	            mediaVideoConversionRepository: $this->videoConversionRepository(),
   241	            mediaAudioConversionRepository: $this->audioConversionRepository(),
   242	            mediaFileService: $fileService,
   243	        );
   244	    }
   245	
   246	    private function readyMedia(MediaVisibility $visibility): Media
   247	    {
   248	        $media = $this->createMedia(userId: UserId::generate(), visibility: $visibility);
   249	        $media->markUploaded();
   250	        $targetStorage = $visibility === MediaVisibility::Public ? MediaStorage::Public : MediaStorage::Private;
   251	        $media->markReadyMovedTo(
   252	            $targetStorage,
   253	            MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Image, extension: 'jpg'),
   254	        );
   255	
   256	        return $media;
   257	    }
   258	
   259	    private function videoConversion(Media $media): MediaVideoConversion
   260	    {
   261	        return MediaVideoConversion::create(
   262	            media: $media,
   263	            type: MediaVideoConversionType::NormalizedMp4H264,
   264	            status: MediaConversionStatus::Ready,
   265	            storage: $media->storage,
   266	            path: MediaPath::videoConversion(
   267	                storageKey: $media->storageKey,
   268	                type: MediaVideoConversionType::NormalizedMp4H264,
   269	                extension: 'mp4',
   270	            ),
   271	            mimeType: MediaMimeType::fromString('video/mp4'),
   272	            size: MediaFileSize::fromInt(4096),
   273	            width: MediaPixelDimension::fromInt(1280),
   274	            height: MediaPixelDimension::fromInt(720),
   275	            duration: MediaDuration::fromInt(2000),
   276	            bitrate: MediaBitrate::fromInt(900_000),
   277	        );
   278	    }
   279	
   280	    private function audioConversion(Media $media): MediaAudioConversion
   281	    {
   282	        return MediaAudioConversion::create(
   283	            media: $media,
   284	            type: MediaAudioConversionType::NormalizedAacM4a,
   285	            status: MediaConversionStatus::Ready,
   286	            storage: $media->storage,
   287	            path: MediaPath::audioConversion(
   288	                storageKey: $media->storageKey,
   289	                type: MediaAudioConversionType::NormalizedAacM4a,
   290	                extension: 'm4a',
   291	            ),
   292	            mimeType: MediaMimeType::fromString('audio/mp4'),
   293	            size: MediaFileSize::fromInt(2048),
   294	            duration: MediaDuration::fromInt(3000),
   295	            bitrate: MediaBitrate::fromInt(128_000),
   296	            sampleRate: MediaSampleRate::fromInt(44_100),
   297	            waveform: MediaWaveform::fromPeaks([0, 64, 128, 255]),
   298	        );
   299	    }
   300	
   301	    private function thumbnailConversion(Media $media): MediaImageConversion
   302	    {
   303	        return MediaImageConversion::create(
   304	            media: $media,
   305	            type: MediaImageConversionType::Thumbnail,
   306	            status: MediaConversionStatus::Ready,
   307	            storage: $media->storage,
   308	            path: MediaPath::imageConversion(
   309	                storageKey: $media->storageKey,
   310	                type: MediaImageConversionType::Thumbnail,
   311	                extension: 'jpg',
   312	            ),
   313	            mimeType: MediaMimeType::fromString('image/jpeg'),
   314	            size: MediaFileSize::fromInt(256),
   315	            width: MediaPixelDimension::fromInt(100),
   316	            height: MediaPixelDimension::fromInt(100),
   317	        );
   318	    }
   319	}

exec
/bin/zsh -lc "nl -ba tests/Unit/Modules/Media/Infrastructure/Cycle/MediaTypecastTest.php | sed -n '1,260p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace Tests\Unit\Modules\Media\Infrastructure\Cycle;
     6	
     7	use App\Modules\Media\Domain\Collection\MediaMultipartPartCollection;
     8	use App\Modules\Media\Domain\ValueObject\MediaExpiration;
     9	use App\Modules\Media\Domain\ValueObject\MediaMultipartPart;
    10	use App\Modules\Media\Domain\ValueObject\MediaMultipartPartETag;
    11	use App\Modules\Media\Domain\ValueObject\MediaMultipartPartNumber;
    12	use App\Modules\Media\Domain\ValueObject\MediaProcessingError;
    13	use App\Modules\Media\Domain\ValueObject\MediaWaveform;
    14	use App\Modules\Media\Infrastructure\Cycle\MediaExpirationTypecast;
    15	use App\Modules\Media\Infrastructure\Cycle\MediaMultipartPartCollectionTypecast;
    16	use App\Modules\Media\Infrastructure\Cycle\MediaProcessingErrorTypecast;
    17	use App\Modules\Media\Infrastructure\Cycle\MediaWaveformTypecast;
    18	use App\Shared\Domain\Exception\InvalidDomainValueException;
    19	use PHPUnit\Framework\TestCase;
    20	
    21	final class MediaTypecastTest extends TestCase
    22	{
    23	    public function testExpirationTypecastHandlesNullableDate(): void
    24	    {
    25	        $expiresAt = new \DateTimeImmutable('2026-05-22 15:00:00');
    26	
    27	        self::assertTrue(MediaExpirationTypecast::castDatabaseValue(null)->isPermanent());
    28	        self::assertSame($expiresAt, MediaExpirationTypecast::castDatabaseValue($expiresAt)->value());
    29	        self::assertSame($expiresAt, MediaExpirationTypecast::uncastValue(MediaExpiration::temporaryUntil($expiresAt)));
    30	    }
    31	
    32	    public function testExpirationTypecastConvertsMutableDateAndUncastsNull(): void
    33	    {
    34	        $mutableDate = new \DateTime('2026-05-22 15:00:00');
    35	
    36	        self::assertEquals(
    37	            \DateTimeImmutable::createFromInterface($mutableDate),
    38	            MediaExpirationTypecast::castDatabaseValue($mutableDate)->value(),
    39	        );
    40	        self::assertNull(MediaExpirationTypecast::uncastValue(null));
    41	    }
    42	
    43	    public function testProcessingErrorTypecastHandlesNullableString(): void
    44	    {
    45	        $processingError = MediaProcessingErrorTypecast::castDatabaseValue('Не удалось обработать файл');
    46	
    47	        self::assertFalse($processingError->isEmpty());
    48	        self::assertTrue(MediaProcessingErrorTypecast::castDatabaseValue(null)->isEmpty());
    49	        self::assertSame('Не удалось обработать файл', MediaProcessingErrorTypecast::uncastValue($processingError));
    50	        self::assertNull(MediaProcessingErrorTypecast::uncastValue(MediaProcessingError::none()));
    51	        self::assertNull(MediaProcessingErrorTypecast::uncastValue(null));
    52	    }
    53	
    54	    public function testMultipartPartCollectionTypecastRejectsNonArrayJson(): void
    55	    {
    56	        $this->expectException(\InvalidArgumentException::class);
    57	
    58	        MediaMultipartPartCollectionTypecast::castDatabaseValue('123');
    59	    }
    60	
    61	    public function testMultipartPartCollectionTypecastRejectsNonArrayElement(): void
    62	    {
    63	        $this->expectException(\InvalidArgumentException::class);
    64	
    65	        MediaMultipartPartCollectionTypecast::castDatabaseValue('[1, 2]');
    66	    }
    67	
    68	    public function testMultipartPartCollectionTypecastRejectsInvalidPartShape(): void
    69	    {
    70	        $this->expectException(\InvalidArgumentException::class);
    71	
    72	        MediaMultipartPartCollectionTypecast::castDatabaseValue('[{"partNumber":"x","eTag":"y"}]');
    73	    }
    74	
    75	    public function testMultipartPartCollectionTypecastHandlesJsonCollection(): void
    76	    {
    77	        $parts = MediaMultipartPartCollectionTypecast::castDatabaseValue(
    78	            '[{"partNumber":2,"eTag":"second"},{"partNumber":1,"eTag":"first"}]',
    79	        );
    80	
    81	        self::assertSame(1, $parts->first()->partNumber->value());
    82	        self::assertSame(
    83	            '[{"partNumber":1,"eTag":"first"},{"partNumber":2,"eTag":"second"}]',
    84	            MediaMultipartPartCollectionTypecast::uncastValue($parts),
    85	        );
    86	    }
    87	
    88	    public function testMultipartPartCollectionTypecastStoresDomainCollection(): void
    89	    {
    90	        $parts = new MediaMultipartPartCollection([
    91	            MediaMultipartPart::create(
    92	                partNumber: MediaMultipartPartNumber::fromInt(1),
    93	                eTag: MediaMultipartPartETag::fromString('first'),
    94	            ),
    95	        ]);
    96	
    97	        self::assertSame('[{"partNumber":1,"eTag":"first"}]', MediaMultipartPartCollectionTypecast::uncastValue($parts));
    98	    }
    99	
   100	    public function testWaveformTypecastRoundTripsJson(): void
   101	    {
   102	        $waveform = MediaWaveformTypecast::castDatabaseValue('[0,128,255]');
   103	
   104	        self::assertSame([0, 128, 255], $waveform->peaks());
   105	        self::assertSame('[0,128,255]', MediaWaveformTypecast::uncastValue($waveform));
   106	        self::assertSame('[0,128,255]', MediaWaveformTypecast::uncastValue(MediaWaveform::fromPeaks([0, 128, 255])));
   107	    }
   108	
   109	    public function testWaveformTypecastRejectsNonArrayJson(): void
   110	    {
   111	        $this->expectException(\InvalidArgumentException::class);
   112	
   113	        MediaWaveformTypecast::castDatabaseValue('123');
   114	    }
   115	
   116	    public function testWaveformTypecastRejectsNonIntElement(): void
   117	    {
   118	        $this->expectException(\InvalidArgumentException::class);
   119	
   120	        MediaWaveformTypecast::castDatabaseValue('[1, "two"]');
   121	    }
   122	
   123	    public function testWaveformTypecastRejectsPeakOutOfRange(): void
   124	    {
   125	        $this->expectException(InvalidDomainValueException::class);
   126	
   127	        MediaWaveformTypecast::castDatabaseValue('[0, 256]');
   128	    }
   129	}

exec
/bin/zsh -lc "nl -ba app/src/Modules/Media/Presentation/Job/ProcessMediaJob.php | sed -n '1,160p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Media\Presentation\Job;
     6	
     7	use App\Modules\Media\Application\Command\ProcessMedia\ProcessMediaCommand;
     8	use App\Modules\Media\Application\Command\ProcessMedia\ProcessMediaHandler;
     9	use App\Modules\Media\Application\Command\RecordMediaProcessingFailure\RecordMediaProcessingFailureCommand;
    10	use App\Modules\Media\Application\Command\RecordMediaProcessingFailure\RecordMediaProcessingFailureHandler;
    11	use App\Modules\Media\Application\Exception\MediaFileServiceFailedException;
    12	use App\Modules\Media\Application\Exception\MediaProcessorFailedException;
    13	use App\Modules\Media\Application\Message\MediaUploaded;
    14	use App\Modules\Outbox\Application\Contract\OutboxMessageLoaderContract;
    15	use App\Modules\Outbox\Application\Message\OutboxQueueEnvelope;
    16	use GianTiaga\SpiralCqrs\CommandBusInterface;
    17	use Psr\Log\LoggerInterface;
    18	use Spiral\Queue\Exception\RetryException;
    19	use Spiral\Queue\JobHandler;
    20	
    21	/**
    22	 * Инфраструктурный Job обработки медиа. Грузит MediaUploaded из outbox и запускает
    23	 * ProcessMediaCommand. Ошибки обработки ловятся здесь (Job — граница системы, try-catch
    24	 * разрешён): фиксируем безопасную ошибку на Media и классифицируем — транзиентную просим
    25	 * повторить (RetryException, его читает OutboxQueueStatusInterceptor), постоянную пробрасываем
    26	 * терминально (outbox -> failed). Статусы outbox Job сам не трогает.
    27	 */
    28	final class ProcessMediaJob extends JobHandler
    29	{
    30	    private const string STORAGE_FAILURE_MESSAGE = 'Ошибка хранилища при обработке медиа.';
    31	    private const string PROCESSING_RETRY_MESSAGE = 'Повторная обработка медиа запланирована.';
    32	    private const string PROCESSING_FAILURE_MESSAGE = 'Не удалось обработать медиа.';
    33	
    34	    public function invoke(
    35	        OutboxQueueEnvelope $payload,
    36	        string $id,
    37	        OutboxMessageLoaderContract $outboxMessageLoader,
    38	        CommandBusInterface $commandBus,
    39	        ProcessMediaHandler $processMediaHandler,
    40	        RecordMediaProcessingFailureHandler $recordMediaProcessingFailureHandler,
    41	        LoggerInterface $logger,
    42	    ): void {
    43	        $mediaUploaded = $outboxMessageLoader->load(
    44	            outboxEventId: $payload->outboxEventId,
    45	            expectedMessageClass: MediaUploaded::class,
    46	        );
    47	
    48	        try {
    49	            $commandBus->dispatch(
    50	                command: new ProcessMediaCommand(
    51	                    mediaId: $mediaUploaded->mediaId,
    52	                    plan: $mediaUploaded->plan,
    53	                ),
    54	                handler: $processMediaHandler->handle(...),
    55	            );
    56	        } catch (\Throwable $exception) {
    57	            // Временность приходит контрактным сигналом isTransient(): классификацию делает
    58	            // Infrastructure (AWS — файловый сервис, ffmpeg — процессоры), Presentation не знает про
    59	            // конкретные реализации хранилища и обработки.
    60	            $isTransient = ($exception instanceof MediaFileServiceFailedException && $exception->isTransient())
    61	                || ($exception instanceof MediaProcessorFailedException && $exception->isTransient());
    62	
    63	            // Запись ошибки на Media — отдельный сбойный путь: медиа могли конкурентно удалить
    64	            // (NotFoundException) или короткий сбой БД. Защищаем только этот вызов локальным guard,
    65	            // чтобы вторичный сбой записи не подменил исходную причину и решение retry/terminal:
    66	            // логируем его как вторичный сбой (ERROR — реальная инфра/инвариант-проблема, rules.md:84)
    67	            // и продолжаем классифицировать по исходному $exception.
    68	            try {
    69	                $commandBus->dispatch(
    70	                    command: new RecordMediaProcessingFailureCommand(
    71	                        mediaId: $mediaUploaded->mediaId,
    72	                        error: $this->safeMessage($exception),
    73	                        isTransient: $isTransient,
    74	                    ),
    75	                    handler: $recordMediaProcessingFailureHandler->handle(...),
    76	                );
    77	            } catch (\Throwable $recordFailure) {
    78	                $logger->error(message: 'Не удалось записать ошибку обработки на медиа.', context: [
    79	                    'mediaId' => $mediaUploaded->mediaId,
    80	                    'jobId' => $id,
    81	                    'recordErrorClass' => $recordFailure::class,
    82	                    'originalErrorClass' => $exception::class,
    83	                    'errorMessage' => $recordFailure->getMessage(),
    84	                ]);
    85	            }
    86	
    87	            $logContext = [
    88	                'mediaId' => $mediaUploaded->mediaId,
    89	                'jobId' => $id,
    90	                'isTransient' => $isTransient,
    91	                'errorClass' => $exception::class,
    92	            ];
    93	
    94	            if ($isTransient) {
    95	                // Временный ретраябельный сбой инфраструктуры -> WARN (rules.md:84), повтор ожидаем.
    96	                $logger->warning(
    97	                    message: 'Временная ошибка обработки медиа, запланирован повтор.',
    98	                    context: $logContext,
    99	                );
   100	
   101	                // Причина повтора по типу сбоя: ошибка процессора != ошибка хранилища.
   102	                $retryReason = $exception instanceof MediaProcessorFailedException
   103	                    ? self::PROCESSING_RETRY_MESSAGE
   104	                    : self::STORAGE_FAILURE_MESSAGE;
   105	
   106	                throw new RetryException(reason: $retryReason);
   107	            }
   108	
   109	            // Постоянный сбой (битый файл/нарушение инварианта) -> ERROR, повтора не будет.
   110	            $logger->error(message: 'Ошибка обработки медиа.', context: $logContext);
   111	
   112	            throw $exception;
   113	        }
   114	    }
   115	
   116	    private function safeMessage(\Throwable $exception): string
   117	    {
   118	        return $exception instanceof MediaFileServiceFailedException
   119	            ? self::STORAGE_FAILURE_MESSAGE
   120	            : self::PROCESSING_FAILURE_MESSAGE;
   121	    }
   122	}

exec
/bin/zsh -lc "nl -ba app/src/Modules/Media/Application/Command/RecordMediaProcessingFailure/RecordMediaProcessingFailureHandler.php | sed -n '1,40p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc "nl -ba app/src/Modules/Media/Domain/Entity/Media.php | sed -n '190,235p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
   190	        $this->touch();
   191	    }
   192	
   193	    /**
   194	     * См. recordTemporaryProcessingError: тот же инвариант «ready без ошибки» — на уже
   195	     * ready-медиа фиксация постоянной ошибки также no-op.
   196	     */
   197	    public function recordPermanentProcessingError(MediaProcessingError $processingError): void
   198	    {
   199	        if ($this->status === MediaStatus::Ready) {
   200	            return;
   201	        }
   202	
   203	        $this->status = MediaStatus::ProcessingFailed;
   204	        $this->processingAttempts = $this->processingAttempts->increment();
   205	        $this->processingError = $processingError;
   206	        $this->touch();
   207	    }
   208	
   209	    public function markReady(): void
   210	    {
   211	        $this->status = MediaStatus::Ready;
   212	        $this->processingError = MediaProcessingError::none();
   213	        $this->touch();
   214	    }
   215	
   216	    /**
   217	     * Перевод в ready с переназначением целевого хранилища и пути (после перекладки оригинала
   218	     * из staging). Идемпотентен: повторная доставка на ready — no-op. Допустим из uploaded,
   219	     * processing или processingFailed (ретрай обработки после транзиентной ошибки).
   220	     */
   221	    public function markReadyMovedTo(MediaStorage $storage, MediaPath $path): void
   222	    {
   223	        if ($this->status === MediaStatus::Ready) {
   224	            return;
   225	        }
   226	
   227	        if (
   228	            $this->status !== MediaStatus::Uploaded
   229	            && $this->status !== MediaStatus::Processing
   230	            && $this->status !== MediaStatus::ProcessingFailed
   231	        ) {
   232	            throw new InvalidDomainValueException(
   233	                'Перевод медиа в ready допустим только из uploaded, processing или processingFailed.',
   234	            );
   235	        }

 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Media\Application\Command\RecordMediaProcessingFailure;
     6	
     7	use App\Modules\Media\Domain\ValueObject\MediaId;
     8	use App\Modules\Media\Domain\ValueObject\MediaProcessingError;
     9	use App\Modules\Media\Repository\MediaRepository;
    10	use App\Shared\Domain\Exception\NotFoundException;
    11	use Cycle\ORM\EntityManagerInterface;
    12	use Psr\Log\LoggerInterface;
    13	
    14	/**
    15	 * Фиксирует ошибку обработки на Media. Доменные методы записи транзиентной и постоянной
    16	 * ошибки идентичны (ProcessingFailed + инкремент попыток), поэтому вызывается один; флаг
    17	 * isTransient влияет только на решение ProcessMediaJob о ретрае и идёт сюда лишь как контекст
    18	 * лога.
    19	 */
    20	final readonly class RecordMediaProcessingFailureHandler
    21	{
    22	    public function __construct(
    23	        private MediaRepository $mediaRepository,
    24	        private EntityManagerInterface $entityManager,
    25	        private LoggerInterface $logger,
    26	    ) {}
    27	
    28	    public function handle(RecordMediaProcessingFailureCommand $command): void
    29	    {
    30	        $media = $this->mediaRepository->findById(MediaId::fromString($command->mediaId))
    31	            ?? throw new NotFoundException('app.media.not_found');
    32	
    33	        $media->recordTemporaryProcessingError(MediaProcessingError::fromString($command->error));
    34	        $this->entityManager->persist($media);
    35	        $this->entityManager->run();
    36	
    37	        $this->logger->debug(message: 'Зафиксирована ошибка обработки медиа.', context: [
    38	            'mediaId' => $media->id->value(),
    39	            'isTransient' => $command->isTransient,
    40	            'attempts' => $media->processingAttempts->value(),

exec
/bin/zsh -lc "nl -ba app/src/Modules/Media/Domain/ValueObject/MediaPath.php | sed -n '1,180p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
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
    83	            MediaType::Document => throw new InvalidDomainValueException(
    84	                'Перекладка готового оригинала для документов не поддержана.',
    85	            ),
    86	        };
    87	
    88	        return self::fromString(
    89	            \sprintf('%s/%s/%s/source.%s', $prefix, $storageKey->shard(), $storageKey, self::sanitizeExtension($extension)),
    90	        );
    91	    }
    92	
    93	    public static function fromString(string $value): self
    94	    {
    95	        $value = \trim($value);
    96	        self::assertValid($value);
    97	
    98	        return new self(value: $value);
    99	    }
   100	
   101	    public function value(): string
   102	    {
   103	        return $this->value;
   104	    }
   105	
   106	    /**
   107	     * Расширение файла из последнего сегмента пути (без точки). Используется для построения
   108	     * путей конверсий и готового оригинала с тем же форматом, что у загруженного файла.
   109	     */
   110	    public function extension(): string
   111	    {
   112	        $segments = \explode(separator: '/', string: $this->value);
   113	        $fileName = (string) \end($segments);
   114	        $dotPosition = \strrpos(haystack: $fileName, needle: '.');
   115	
   116	        return $dotPosition === false ? '' : \substr(string: $fileName, offset: $dotPosition + 1);
   117	    }
   118	
   119	    public function equals(self $other): bool
   120	    {
   121	        return $this->value === $other->value;
   122	    }
   123	
   124	    #[\Override]
   125	    public function __toString(): string
   126	    {
   127	        return $this->value;
   128	    }
   129	
   130	    #[\Override]
   131	    public function jsonSerialize(): string
   132	    {
   133	        return $this->value;
   134	    }
   135	
   136	    private static function sanitizeExtension(string $extension): string
   137	    {
   138	        $safeExtension = \strtolower(\trim($extension));
   139	
   140	        if ($safeExtension === '' || \preg_match(pattern: '/^[a-z0-9]+$/', subject: $safeExtension) !== 1) {
   141	            throw new InvalidDomainValueException('Расширение файла имеет неверный формат.');
   142	        }
   143	
   144	        return $safeExtension;
   145	    }
   146	
   147	    private static function assertValid(string $value): void
   148	    {
   149	        if (\strlen($value) > self::MAX_LENGTH) {
   150	            throw new InvalidDomainValueException('Путь файла слишком длинный.');
   151	        }
   152	
   153	        $matches = [];
   154	        if (\preg_match(
   155	            pattern: '/^(uploads|images|videos|audios)\/([0-9a-f]{2})\/([0-9a-f-]{36})\/([^\/]+)$/',
   156	            subject: $value,
   157	            matches: $matches,
   158	        ) !== 1) {
   159	            throw new InvalidDomainValueException('Путь файла имеет неверный формат.');
   160	        }
   161	
   162	        if (!Uuid::isValid($matches[3]) || $matches[2] !== \substr(string: $matches[3], offset: 0, length: 2)) {
   163	            throw new InvalidDomainValueException('Путь файла имеет неверный раздел.');
   164	        }
   165	    }
   166	}

exec
/bin/zsh -lc "nl -ba app/src/Modules/Media/README.md | sed -n '70,100p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
    70	```
    71	
    72	`uploadMode` (single/multipart) выбирается по порогу `MediaConfig.multipartThresholdBytes`;
    73	размер части — `MediaConfig.multipartPartSizeBytes` (S3 требует ≥ 5 MiB на часть, кроме последней).
    74	
    75	## Статусы и обработка ошибок
    76	
    77	`MediaStatus`: `waitingUpload → uploaded → ready`. Промежуточный `processing` намеренно не
    78	используется (атомарный переход `uploaded → ready` одним flush). При ошибке обработки
    79	`ProcessMediaJob` фиксирует `ProcessingFailed` через `RecordMediaProcessingFailure` и выбирает
    80	стратегию повтора по **контрактному сигналу** транзиентности, а не по типу хранилища:
    81	`MediaFileServiceFailedException::isTransient()` → транзиентная (сетевые/5xx/throttling) даёт
    82	`RetryException` (событие остаётся на повтор) и лог уровня WARN; постоянная (Imagick/битый файл,
    83	нештатный ответ S3) → терминальный проброс (outbox → `failed`) и лог уровня ERROR. Ошибки ffmpeg
    84	классифицируются так же контрактным `MediaProcessorFailedException::isTransient()` (таймаут
    85	транскодирования → временная/`RetryException`; битый/неподдерживаемый вход, нештатный код выхода →
    86	постоянная); причина повтора в `RetryException` выбирается по типу (ошибка процессора ≠ ошибка
    87	хранилища). Классификацию сырого `AwsException`/исключений php-ffmpeg делает Infrastructure
    88	(`S3MediaFileService` / ffmpeg-процессоры → контрактные исключения), поэтому `Presentation/Job` не
    89	импортирует `Aws\*`/`FFMpeg\*` и не знает про реализации хранилища и обработки. Текст ошибки —
    90	из предопределённого набора безопасных сообщений; сырой текст AWS не прокидывается (VO
    91	`MediaProcessingError` отклоняет пути и слово `etag`). Сама запись ошибки на Media обёрнута
    92	локальным guard: если она падает (медиа конкурентно удалили → `NotFoundException`, короткий сбой
    93	БД), вторичный сбой логируется уровнем ERROR и не подменяет исходную причину — Job всё равно
    94	выбирает retry/terminal по исходному исключению (транзиентное → `RetryException`).
    95	
    96	## Транзакционная дисциплина S3
    97	
    98	- `CompleteMediaUpload` — `#[Transactional]`: переход `uploaded` и запись `MediaUploaded` в
    99	  outbox происходят в одной транзакции. Trade-off: для multipart-ветки внешние S3-операции
   100	  (`completeMultipartUpload`, `headObject`) выполняются под открытой транзакцией БД. Это

exec
/bin/zsh -lc "nl -ba app/src/Modules/Media/Domain/ValueObject/MediaWaveform.php | sed -n '1,130p' && nl -ba app/src/Modules/Media/Domain/ValueObject/MediaWaveformPeakCount.php | sed -n '1,80p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Media\Domain\ValueObject;
     6	
     7	use App\Shared\Domain\Exception\InvalidDomainValueException;
     8	
     9	/**
    10	 * Волна аудио — массив амплитуд для отрисовки прогресса воспроизведения «как в Telegram».
    11	 *
    12	 * Составной VO без естественного строкового представления, поэтому не Stringable. Хранится
    13	 * JSON-колонкой через MediaWaveformTypecast. Каждая амплитуда — целое 0..255, нормализованное
    14	 * процессором при извлечении волны.
    15	 */
    16	final readonly class MediaWaveform implements \JsonSerializable
    17	{
    18	    private const int MIN_PEAK = 0;
    19	    private const int MAX_PEAK = 255;
    20	
    21	    /**
    22	     * @param list<int> $peaks
    23	     */
    24	    private function __construct(
    25	        private array $peaks,
    26	    ) {}
    27	
    28	    /**
    29	     * @param list<int> $peaks
    30	     */
    31	    public static function fromPeaks(array $peaks): self
    32	    {
    33	        // Количество пиков валидируется доменным диапазоном MediaWaveformPeakCount (1..4096):
    34	        // отсутствие/перебор пиков — невалидная волна.
    35	        MediaWaveformPeakCount::fromInt(\count($peaks));
    36	
    37	        foreach ($peaks as $peak) {
    38	            if ($peak < self::MIN_PEAK || $peak > self::MAX_PEAK) {
    39	                throw new InvalidDomainValueException(
    40	                    \sprintf('Амплитуда волны должна быть от %d до %d.', self::MIN_PEAK, self::MAX_PEAK),
    41	                );
    42	            }
    43	        }
    44	
    45	        return new self(peaks: $peaks);
    46	    }
    47	
    48	    /**
    49	     * @return list<int>
    50	     */
    51	    public function peaks(): array
    52	    {
    53	        return $this->peaks;
    54	    }
    55	
    56	    public function equals(self $other): bool
    57	    {
    58	        return $this->peaks === $other->peaks;
    59	    }
    60	
    61	    /**
    62	     * @return list<int>
    63	     */
    64	    #[\Override]
    65	    public function jsonSerialize(): array
    66	    {
    67	        return $this->peaks;
    68	    }
    69	}
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Media\Domain\ValueObject;
     6	
     7	use App\Shared\Domain\ValueObject\AbstractIntegerValue;
     8	
     9	final readonly class MediaWaveformPeakCount extends AbstractIntegerValue
    10	{
    11	    protected const int MIN = 1;
    12	    protected const int MAX = 4096;
    13	    protected const string NAME = 'Количество пиков волны';
    14	}

codex
По `plan 2` вижу фактическую проблему самого ревью: план требовал 422 на дубли в любом списке, и видео/аудио действительно дают 422 через правило «ровно один профиль». Это не расхождение и не должно быть пунктом проблемы.
exec
/bin/zsh -lc "nl -ba vendor/php-ffmpeg/php-ffmpeg/src/FFMpeg/FFMpeg.php | sed -n '1,160p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	/*
     4	 * This file is part of PHP-FFmpeg.
     5	 *
     6	 * (c) Alchemy <info@alchemy.fr>
     7	 *
     8	 * For the full copyright and license information, please view the LICENSE
     9	 * file that was distributed with this source code.
    10	 */
    11	
    12	namespace FFMpeg;
    13	
    14	use Alchemy\BinaryDriver\ConfigurationInterface;
    15	use FFMpeg\Driver\FFMpegDriver;
    16	use FFMpeg\Exception\InvalidArgumentException;
    17	use FFMpeg\Exception\RuntimeException;
    18	use FFMpeg\Media\AdvancedMedia;
    19	use FFMpeg\Media\Audio;
    20	use FFMpeg\Media\Video;
    21	use Psr\Log\LoggerInterface;
    22	
    23	class FFMpeg
    24	{
    25	    /** @var FFMpegDriver */
    26	    private $driver;
    27	    /** @var FFProbe */
    28	    private $ffprobe;
    29	
    30	    public function __construct(FFMpegDriver $ffmpeg, FFProbe $ffprobe)
    31	    {
    32	        $this->driver = $ffmpeg;
    33	        $this->ffprobe = $ffprobe;
    34	    }
    35	
    36	    /**
    37	     * Sets FFProbe.
    38	     *
    39	     * @param FFProbe
    40	     *
    41	     * @return FFMpeg
    42	     */
    43	    public function setFFProbe(FFProbe $ffprobe)
    44	    {
    45	        $this->ffprobe = $ffprobe;
    46	
    47	        return $this;
    48	    }
    49	
    50	    /**
    51	     * Gets FFProbe.
    52	     *
    53	     * @return FFProbe
    54	     */
    55	    public function getFFProbe()
    56	    {
    57	        return $this->ffprobe;
    58	    }
    59	
    60	    /**
    61	     * Sets the ffmpeg driver.
    62	     *
    63	     * @return FFMpeg
    64	     */
    65	    public function setFFMpegDriver(FFMpegDriver $ffmpeg)
    66	    {
    67	        $this->driver = $ffmpeg;
    68	
    69	        return $this;
    70	    }
    71	
    72	    /**
    73	     * Gets the ffmpeg driver.
    74	     *
    75	     * @return FFMpegDriver
    76	     */
    77	    public function getFFMpegDriver()
    78	    {
    79	        return $this->driver;
    80	    }
    81	
    82	    /**
    83	     * Opens a file in order to be processed.
    84	     *
    85	     * @param string $pathfile A pathfile
    86	     *
    87	     * @return Audio|Video
    88	     *
    89	     * @throws InvalidArgumentException
    90	     */
    91	    public function open($pathfile)
    92	    {
    93	        if (null === $streams = $this->ffprobe->streams($pathfile)) {
    94	            throw new RuntimeException(sprintf('Unable to probe "%s".', $pathfile));
    95	        }
    96	
    97	        if (0 < count($streams->videos())) {
    98	            return new Video($pathfile, $this->driver, $this->ffprobe);
    99	        } elseif (0 < count($streams->audios())) {
   100	            return new Audio($pathfile, $this->driver, $this->ffprobe);
   101	        }
   102	
   103	        throw new InvalidArgumentException('Unable to detect file format, only audio and video supported');
   104	    }
   105	
   106	    /**
   107	     * Opens multiple input sources.
   108	     *
   109	     * @param string[] $inputs array of files to be opened
   110	     *
   111	     * @return AdvancedMedia
   112	     */
   113	    public function openAdvanced($inputs)
   114	    {
   115	        return new AdvancedMedia($inputs, $this->driver, $this->ffprobe);
   116	    }
   117	
   118	    /**
   119	     * Creates a new FFMpeg instance.
   120	     *
   121	     * @param array|ConfigurationInterface $configuration
   122	     * @param LoggerInterface              $logger
   123	     * @param FFProbe                      $probe
   124	     *
   125	     * @return FFMpeg
   126	     */
   127	    public static function create($configuration = [], ?LoggerInterface $logger = null, ?FFProbe $probe = null)
   128	    {
   129	        if (null === $probe) {
   130	            $probe = FFProbe::create($configuration, $logger, null);
   131	        }
   132	
   133	        return new static(FFMpegDriver::create($logger, $configuration), $probe);
   134	    }
   135	}

exec
/bin/zsh -lc "rg -n \"returns.*Video|open\\(|Audio\" vendor/php-ffmpeg/php-ffmpeg/README.md vendor/php-ffmpeg/php-ffmpeg -g '*.md' | head -n 80" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
vendor/php-ffmpeg/php-ffmpeg/README.md:41:$video = $ffmpeg->open('video.mpg');
vendor/php-ffmpeg/php-ffmpeg/README.md:105:$ffmpeg->open('video.mpeg');
vendor/php-ffmpeg/php-ffmpeg/README.md:108:Two types of media can be resolved: `FFMpeg\Media\Audio` and `FFMpeg\Media\Video`.
vendor/php-ffmpeg/php-ffmpeg/README.md:131:    ->setAudioChannels(2)
vendor/php-ffmpeg/php-ffmpeg/README.md:132:    ->setAudioKiloBitrate(256);
vendor/php-ffmpeg/php-ffmpeg/README.md:200:You can generate a waveform of an audio file using the `FFMpeg\Media\Audio::waveform`
vendor/php-ffmpeg/php-ffmpeg/README.md:218:$video = $ffmpeg->open( 'video.mp4' );
vendor/php-ffmpeg/php-ffmpeg/README.md:221:$audio_format = new FFMpeg\Format\Audio\Mp3();
vendor/php-ffmpeg/php-ffmpeg/README.md:227:$audio = $ffmpeg->open( 'audio.mp3' );
vendor/php-ffmpeg/php-ffmpeg/README.md:237:method. Video accepts Audio and Video filters.
vendor/php-ffmpeg/php-ffmpeg/README.md:374:### Audio
vendor/php-ffmpeg/php-ffmpeg/README.md:376:`FFMpeg\Media\Audio` can be transcoded too, ie: change codec, isolate audio or
vendor/php-ffmpeg/php-ffmpeg/README.md:381:You can transcode audios using the `FFMpeg\Media\Audio:save` method. You will
vendor/php-ffmpeg/php-ffmpeg/README.md:388:$audio = $ffmpeg->open('track.mp3');
vendor/php-ffmpeg/php-ffmpeg/README.md:390:$format = new FFMpeg\Format\Audio\Flac();
vendor/php-ffmpeg/php-ffmpeg/README.md:396:    ->setAudioChannels(2)
vendor/php-ffmpeg/php-ffmpeg/README.md:397:    ->setAudioKiloBitrate(256);
vendor/php-ffmpeg/php-ffmpeg/README.md:407:You can apply filters on `FFMpeg\Media\Audio` with the `FFMpeg\Media\Audio::addFilter`
vendor/php-ffmpeg/php-ffmpeg/README.md:411:accessible through the `FFMpeg\Media\Audio::filters` method.
vendor/php-ffmpeg/php-ffmpeg/README.md:475:$video = $ffmpeg->open( '/path/to/video' );
vendor/php-ffmpeg/php-ffmpeg/README.md:505:$video = $ffmpeg->open( '/path/to/video' );
vendor/php-ffmpeg/php-ffmpeg/README.md:518:$video = $ffmpeg->open( '/path/to/video' );
vendor/php-ffmpeg/php-ffmpeg/README.md:521:$format->setAudioCodec("libmp3lame");
vendor/php-ffmpeg/php-ffmpeg/README.md:609:use `FFMpeg\Format\VideoInterface`, and `FFMpeg\Format\AudioInterface` for
vendor/php-ffmpeg/php-ffmpeg/README.md:655:`FFMpeg\Format\Video\DefaultVideo` and `FFMpeg\Format\Audio\DefaultAudio`.
vendor/php-ffmpeg/php-ffmpeg/README.md:664:            ->setAudioCodec($audioCodec)
vendor/php-ffmpeg/php-ffmpeg/README.md:673:    public function getAvailableAudioCodecs()
vendor/php-ffmpeg/php-ffmpeg/README.md:41:$video = $ffmpeg->open('video.mpg');
vendor/php-ffmpeg/php-ffmpeg/README.md:105:$ffmpeg->open('video.mpeg');
vendor/php-ffmpeg/php-ffmpeg/README.md:108:Two types of media can be resolved: `FFMpeg\Media\Audio` and `FFMpeg\Media\Video`.
vendor/php-ffmpeg/php-ffmpeg/README.md:131:    ->setAudioChannels(2)
vendor/php-ffmpeg/php-ffmpeg/README.md:132:    ->setAudioKiloBitrate(256);
vendor/php-ffmpeg/php-ffmpeg/README.md:200:You can generate a waveform of an audio file using the `FFMpeg\Media\Audio::waveform`
vendor/php-ffmpeg/php-ffmpeg/README.md:218:$video = $ffmpeg->open( 'video.mp4' );
vendor/php-ffmpeg/php-ffmpeg/README.md:221:$audio_format = new FFMpeg\Format\Audio\Mp3();
vendor/php-ffmpeg/php-ffmpeg/README.md:227:$audio = $ffmpeg->open( 'audio.mp3' );
vendor/php-ffmpeg/php-ffmpeg/README.md:237:method. Video accepts Audio and Video filters.
vendor/php-ffmpeg/php-ffmpeg/README.md:374:### Audio
vendor/php-ffmpeg/php-ffmpeg/README.md:376:`FFMpeg\Media\Audio` can be transcoded too, ie: change codec, isolate audio or
vendor/php-ffmpeg/php-ffmpeg/README.md:381:You can transcode audios using the `FFMpeg\Media\Audio:save` method. You will
vendor/php-ffmpeg/php-ffmpeg/README.md:388:$audio = $ffmpeg->open('track.mp3');
vendor/php-ffmpeg/php-ffmpeg/README.md:390:$format = new FFMpeg\Format\Audio\Flac();
vendor/php-ffmpeg/php-ffmpeg/README.md:396:    ->setAudioChannels(2)
vendor/php-ffmpeg/php-ffmpeg/README.md:397:    ->setAudioKiloBitrate(256);
vendor/php-ffmpeg/php-ffmpeg/README.md:407:You can apply filters on `FFMpeg\Media\Audio` with the `FFMpeg\Media\Audio::addFilter`
vendor/php-ffmpeg/php-ffmpeg/README.md:411:accessible through the `FFMpeg\Media\Audio::filters` method.
vendor/php-ffmpeg/php-ffmpeg/README.md:475:$video = $ffmpeg->open( '/path/to/video' );
vendor/php-ffmpeg/php-ffmpeg/README.md:505:$video = $ffmpeg->open( '/path/to/video' );
vendor/php-ffmpeg/php-ffmpeg/README.md:518:$video = $ffmpeg->open( '/path/to/video' );
vendor/php-ffmpeg/php-ffmpeg/README.md:521:$format->setAudioCodec("libmp3lame");
vendor/php-ffmpeg/php-ffmpeg/README.md:609:use `FFMpeg\Format\VideoInterface`, and `FFMpeg\Format\AudioInterface` for
vendor/php-ffmpeg/php-ffmpeg/README.md:655:`FFMpeg\Format\Video\DefaultVideo` and `FFMpeg\Format\Audio\DefaultAudio`.
vendor/php-ffmpeg/php-ffmpeg/README.md:664:            ->setAudioCodec($audioCodec)
vendor/php-ffmpeg/php-ffmpeg/README.md:673:    public function getAvailableAudioCodecs()

exec
/bin/zsh -lc "rg -n \"class FFMpeg|function open|interface MediaTypeInterface|class Audio|class Video\" vendor/php-ffmpeg vendor -g '*.php' | head -n 80" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
exec
/bin/zsh -lc "nl -ba vendor/php-ffmpeg/php-ffmpeg/src/FFMpeg/Media/Audio.php | sed -n '1,80p' && nl -ba vendor/php-ffmpeg/php-ffmpeg/src/FFMpeg/Media/Video.php | sed -n '1,80p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	/*
     4	 * This file is part of PHP-FFmpeg.
     5	 *
     6	 * (c) Alchemy <info@alchemy.fr>
     7	 *
     8	 * For the full copyright and license information, please view the LICENSE
     9	 * file that was distributed with this source code.
    10	 */
    11	
    12	namespace FFMpeg\Media;
    13	
    14	use Alchemy\BinaryDriver\Exception\ExecutionFailureException;
    15	use FFMpeg\Exception\InvalidArgumentException;
    16	use FFMpeg\Exception\RuntimeException;
    17	use FFMpeg\Filters\Audio\AudioFilterInterface;
    18	use FFMpeg\Filters\Audio\AudioFilters;
    19	use FFMpeg\Filters\Audio\SimpleFilter;
    20	use FFMpeg\Filters\FilterInterface;
    21	use FFMpeg\Format\FormatInterface;
    22	use FFMpeg\Format\ProgressableInterface;
    23	
    24	class Audio extends AbstractStreamableMedia
    25	{
    26	    /**
    27	     * {@inheritdoc}
    28	     *
    29	     * @return AudioFilters
    30	     */
    31	    public function filters()
    32	    {
    33	        return new AudioFilters($this);
    34	    }
    35	
    36	    /**
    37	     * {@inheritdoc}
    38	     *
    39	     * @return Audio
    40	     */
    41	    public function addFilter(FilterInterface $filter)
    42	    {
    43	        if (!$filter instanceof AudioFilterInterface) {
    44	            throw new InvalidArgumentException('Audio only accepts AudioFilterInterface filters');
    45	        }
    46	
    47	        $this->filters->add($filter);
    48	
    49	        return $this;
    50	    }
    51	
    52	    /**
    53	     * Exports the audio in the desired format, applies registered filters.
    54	     *
    55	     * @param string $outputPathfile
    56	     *
    57	     * @return Audio
    58	     *
    59	     * @throws RuntimeException
    60	     */
    61	    public function save(FormatInterface $format, $outputPathfile)
    62	    {
    63	        $listeners = null;
    64	
    65	        if ($format instanceof ProgressableInterface) {
    66	            $listeners = $format->createProgressListener($this, $this->ffprobe, 1, 1, 0);
    67	        }
    68	
    69	        $commands = $this->buildCommand($format, $outputPathfile);
    70	
    71	        try {
    72	            $this->driver->command($commands, false, $listeners);
    73	        } catch (ExecutionFailureException $e) {
    74	            $this->cleanupTemporaryFile($outputPathfile);
    75	            throw new RuntimeException('Encoding failed', $e->getCode(), $e);
    76	        }
    77	
    78	        return $this;
    79	    }
    80	
     1	<?php
     2	
     3	/*
     4	 * This file is part of PHP-FFmpeg.
     5	 *
     6	 * (c) Alchemy <info@alchemy.fr>
     7	 *
     8	 * For the full copyright and license information, please view the LICENSE
     9	 * file that was distributed with this source code.
    10	 */
    11	
    12	namespace FFMpeg\Media;
    13	
    14	use FFMpeg\Coordinate\Dimension;
    15	use FFMpeg\Coordinate\TimeCode;
    16	
    17	class Video extends AbstractVideo
    18	{
    19	    /**
    20	     * Gets the frame at timecode.
    21	     *
    22	     * @return Frame
    23	     */
    24	    public function frame(TimeCode $at)
    25	    {
    26	        return new Frame($this, $this->driver, $this->ffprobe, $at);
    27	    }
    28	
    29	    /**
    30	     * Extracts a gif from a sequence of the video.
    31	     *
    32	     * @param int $duration
    33	     *
    34	     * @return Gif
    35	     */
    36	    public function gif(TimeCode $at, Dimension $dimension, $duration = null)
    37	    {
    38	        return new Gif($this, $this->driver, $this->ffprobe, $at, $dimension, $duration);
    39	    }
    40	
    41	    /**
    42	     * Concatenates a list of videos into one unique video.
    43	     *
    44	     * @param array $sources
    45	     *
    46	     * @return Concat
    47	     */
    48	    public function concat($sources)
    49	    {
    50	        return new Concat($sources, $this->driver, $this->ffprobe);
    51	    }
    52	
    53	    /**
    54	     * Clips the video at the given time(s).
    55	     *
    56	     * @param TimeCode $start    Start time
    57	     * @param TimeCode $duration Duration
    58	     *
    59	     * @return \FFMpeg\Media\Clip
    60	     */
    61	    public function clip(TimeCode $start, ?TimeCode $duration = null)
    62	    {
    63	        return new Clip($this, $this->driver, $this->ffprobe, $start, $duration);
    64	    }
    65	}

 succeeded in 483ms:
vendor/php-ffmpeg/php-ffmpeg/src/FFMpeg/Media/Audio.php:24:class Audio extends AbstractStreamableMedia
vendor/php-ffmpeg/php-ffmpeg/src/FFMpeg/Media/Video.php:17:class Video extends AbstractVideo
vendor/php-ffmpeg/php-ffmpeg/src/FFMpeg/Media/MediaTypeInterface.php:14:interface MediaTypeInterface
vendor/php-ffmpeg/php-ffmpeg/src/FFMpeg/Format/ProgressListener/VideoProgressListener.php:23:class VideoProgressListener extends AbstractProgressListener
vendor/php-ffmpeg/php-ffmpeg/src/FFMpeg/Format/ProgressListener/AudioProgressListener.php:23:class AudioProgressListener extends AbstractProgressListener
vendor/php-ffmpeg/php-ffmpeg/src/FFMpeg/FFMpeg.php:23:class FFMpeg
vendor/php-ffmpeg/php-ffmpeg/src/FFMpeg/FFMpeg.php:91:    public function open($pathfile)
vendor/php-ffmpeg/php-ffmpeg/src/FFMpeg/FFMpeg.php:113:    public function openAdvanced($inputs)
vendor/php-ffmpeg/php-ffmpeg/src/FFMpeg/Driver/FFMpegDriver.php:22:class FFMpegDriver extends AbstractBinary
vendor/php-ffmpeg/php-ffmpeg/src/FFMpeg/Filters/Audio/AudioResamplableFilter.php:17:class AudioResamplableFilter implements AudioFilterInterface
vendor/php-ffmpeg/php-ffmpeg/src/FFMpeg/Filters/Audio/AudioClipFilter.php:18:class AudioClipFilter implements AudioFilterInterface
vendor/php-ffmpeg/php-ffmpeg/src/FFMpeg/Filters/Audio/AudioFilters.php:17:class AudioFilters
vendor/php-ffmpeg/php-ffmpeg/src/FFMpeg/Filters/Video/VideoFilters.php:22:class VideoFilters extends AudioFilters
vendor/symfony/console/Output/ConsoleOutput.php:131:    private function openOutputStream()
vendor/symfony/console/Output/ConsoleOutput.php:159:    private function openErrorStream()
vendor/lcobucci/jwt/src/Signer/OpenSSL.php:15:use function openssl_error_string;
vendor/lcobucci/jwt/src/Signer/OpenSSL.php:16:use function openssl_pkey_get_details;
vendor/lcobucci/jwt/src/Signer/OpenSSL.php:17:use function openssl_pkey_get_private;
vendor/lcobucci/jwt/src/Signer/OpenSSL.php:18:use function openssl_pkey_get_public;
vendor/lcobucci/jwt/src/Signer/OpenSSL.php:19:use function openssl_sign;
vendor/lcobucci/jwt/src/Signer/OpenSSL.php:20:use function openssl_verify;
vendor/cycle/orm/src/Transaction/Pool.php:116:    public function openIterator(): \Traversable
vendor/symfony/http-foundation/Session/Storage/Proxy/SessionHandlerProxy.php:37:    public function open(string $savePath, string $sessionName): bool
vendor/symfony/http-foundation/Session/Storage/Handler/StrictSessionHandler.php:43:    public function open(string $savePath, string $sessionName): bool
vendor/symfony/http-foundation/Session/Storage/Handler/MigratingSessionHandler.php:65:    public function open(string $savePath, string $sessionName): bool
vendor/symfony/http-foundation/Session/Storage/Handler/MarshallingSessionHandler.php:30:    public function open(string $savePath, string $name): bool
vendor/symfony/http-foundation/Session/Storage/Handler/PdoSessionHandler.php:282:    public function open(string $savePath, string $sessionName): bool
vendor/symfony/http-foundation/Session/Storage/Handler/AbstractSessionHandler.php:31:    public function open(string $savePath, string $sessionName): bool
vendor/symfony/http-client/Response/AsyncResponse.php:469:    private function openBuffer(): ?\Throwable
vendor/symfony/http-client/Response/NativeResponse.php:118:    private function open(): void
vendor/aws/aws-sdk-php/src/S3/StreamWrapper.php:730:    private function openReadStream()
vendor/aws/aws-sdk-php/src/S3/StreamWrapper.php:747:    private function openWriteStream()
vendor/aws/aws-sdk-php/src/S3/StreamWrapper.php:753:    private function openAppendStream()
vendor/aws/aws-sdk-php/src/S3/S3Transfer/Utils/FileDownloadHandler.php:101:    private function openExistingFile(): void
vendor/nyholm/psr7/src/Stream.php:300:    private static function openZvalStream(string $body)
vendor/php-ffmpeg/php-ffmpeg/src/FFMpeg/Media/Audio.php:24:class Audio extends AbstractStreamableMedia
vendor/php-ffmpeg/php-ffmpeg/src/FFMpeg/Media/Video.php:17:class Video extends AbstractVideo
vendor/php-ffmpeg/php-ffmpeg/src/FFMpeg/Media/MediaTypeInterface.php:14:interface MediaTypeInterface
vendor/php-ffmpeg/php-ffmpeg/src/FFMpeg/Format/ProgressListener/VideoProgressListener.php:23:class VideoProgressListener extends AbstractProgressListener
vendor/php-ffmpeg/php-ffmpeg/src/FFMpeg/Format/ProgressListener/AudioProgressListener.php:23:class AudioProgressListener extends AbstractProgressListener
vendor/php-ffmpeg/php-ffmpeg/src/FFMpeg/FFMpeg.php:23:class FFMpeg
vendor/php-ffmpeg/php-ffmpeg/src/FFMpeg/FFMpeg.php:91:    public function open($pathfile)
vendor/php-ffmpeg/php-ffmpeg/src/FFMpeg/FFMpeg.php:113:    public function openAdvanced($inputs)
vendor/php-ffmpeg/php-ffmpeg/src/FFMpeg/Driver/FFMpegDriver.php:22:class FFMpegDriver extends AbstractBinary
vendor/php-ffmpeg/php-ffmpeg/src/FFMpeg/Filters/Audio/AudioResamplableFilter.php:17:class AudioResamplableFilter implements AudioFilterInterface
vendor/php-ffmpeg/php-ffmpeg/src/FFMpeg/Filters/Audio/AudioClipFilter.php:18:class AudioClipFilter implements AudioFilterInterface
vendor/php-ffmpeg/php-ffmpeg/src/FFMpeg/Filters/Audio/AudioFilters.php:17:class AudioFilters
vendor/google/cloud-storage/src/StreamWrapper.php:685:    private function openPath($path)
vendor/php-ffmpeg/php-ffmpeg/src/FFMpeg/Filters/Video/VideoFilters.php:22:class VideoFilters extends AudioFilters
vendor/symfony/cache/LockRegistry.php:178:    private static function open(int $key)
vendor/symfony/cache/Traits/RedisProxy.php:668:    public function open($host, $port = 6379, $timeout = 0, $persistent_id = null, $retry_interval = 0, $read_timeout = 0, $context = null): bool
vendor/symfony/validator/Constraints/Video.php:24:class Video extends File
vendor/nette/utils/src/Utils/FileSystem.php:85:	public static function open(string $path, string $mode)
vendor/aws/aws-sdk-php/src/DynamoDb/SessionHandler.php:105:    public function open($savePath, $sessionName)
vendor/symfony/validator/Constraints/VideoValidator.php:23:class VideoValidator extends FileValidator
vendor/spiral/goridge/src/Relay.php:75:    private static function openIn(string $input)
vendor/spiral/goridge/src/Relay.php:90:    private static function openOut(string $output)
vendor/spiral/roadrunner-cli/src/Archive/PharAwareArchive.php:47:    abstract protected function open(\SplFileInfo $file): \PharData;
vendor/spiral/roadrunner-cli/src/Archive/ZipPharArchive.php:16:    protected function open(\SplFileInfo $file): \PharData
vendor/spiral/roadrunner-cli/src/Archive/TarPharArchive.php:16:    protected function open(\SplFileInfo $file): \PharData
vendor/spiral/framework/src/Session/src/Handler/CacheHandler.php:44:    public function open(string $path, string $name): bool
vendor/spiral/framework/src/Session/src/Handler/NullHandler.php:33:    public function open(string $path, string $id): bool
vendor/spiral/roadrunner-cli/src/Archive/PharArchive.php:16:    protected function open(\SplFileInfo $file): \PharData
vendor/spiral/framework/src/Session/src/Handler/FileHandler.php:50:    public function open(string $path, string $id): bool
vendor/spiral/framework/src/Stempler/src/Parser/Assembler.php:53:    public function open(NodeInterface $node, string $path): void
vendor/phpunit/phpunit/src/TextUI/Configuration/Xml/CodeCoverage/CodeCoverage.php:179:    public function openClover(): OpenClover

codex
Пункт `2a` тоже выглядит недоказанным: в установленной библиотеке `FFMpeg::open()` прямо имеет контракт `@return Audio|Video` и код возвращает `Video`, если есть видеопоток. Значит утверждение «не документированный контракт» надо снимать или сильно менять.
exec
/bin/zsh -lc "nl -ba vendor/php-ffmpeg/php-ffmpeg/README.md | sed -n '100,115p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
   100	```
   101	
   102	To open a resource, use the `FFMpeg\FFMpeg::open` method.
   103	
   104	```php
   105	$ffmpeg->open('video.mpeg');
   106	```
   107	
   108	Two types of media can be resolved: `FFMpeg\Media\Audio` and `FFMpeg\Media\Video`.
   109	A third type, `FFMpeg\Media\Frame`, is available through videos.
   110	
   111	### Video
   112	
   113	`FFMpeg\Media\Video` can be transcoded, ie: change codec, isolate audio or
   114	video. Frames can be extracted.
   115	

exec
/bin/zsh -lc "nl -ba vendor/php-ffmpeg/php-ffmpeg/src/FFMpeg/Media/AbstractVideo.php | sed -n '1,80p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	/*
     4	 * This file is part of PHP-FFmpeg.
     5	 *
     6	 * (c) Alchemy <info@alchemy.fr>
     7	 *
     8	 * For the full copyright and license information, please view the LICENSE
     9	 * file that was distributed with this source code.
    10	 */
    11	
    12	namespace FFMpeg\Media;
    13	
    14	use Alchemy\BinaryDriver\Exception\ExecutionFailureException;
    15	use FFMpeg\Exception\InvalidArgumentException;
    16	use FFMpeg\Exception\RuntimeException;
    17	use FFMpeg\Filters\Audio\SimpleFilter;
    18	use FFMpeg\Filters\FilterInterface;
    19	use FFMpeg\Filters\Video\ClipFilter;
    20	use FFMpeg\Filters\Video\VideoFilters;
    21	use FFMpeg\Format\AudioInterface;
    22	use FFMpeg\Format\FormatInterface;
    23	use FFMpeg\Format\ProgressableInterface;
    24	use FFMpeg\Format\VideoInterface;
    25	use Spatie\TemporaryDirectory\TemporaryDirectory;
    26	
    27	abstract class AbstractVideo extends Audio
    28	{
    29	    /**
    30	     * FileSystem Manager instance.
    31	     *
    32	     * @var Manager
    33	     */
    34	    protected $fs;
    35	
    36	    /**
    37	     * FileSystem Manager ID.
    38	     *
    39	     * @var int
    40	     */
    41	    protected $fsId;
    42	
    43	    /**
    44	     * {@inheritDoc}
    45	     *
    46	     * @return VideoFilters
    47	     */
    48	    public function filters()
    49	    {
    50	        return new VideoFilters($this);
    51	    }
    52	
    53	    /**
    54	     * {@inheritDoc}
    55	     *
    56	     * @return Video
    57	     */
    58	    public function addFilter(FilterInterface $filter)
    59	    {
    60	        $this->filters->add($filter);
    61	
    62	        return $this;
    63	    }
    64	
    65	    /**
    66	     * Exports the video in the desired format, applies registered filters.
    67	     *
    68	     * @param string $outputPathfile
    69	     *
    70	     * @return Video
    71	     *
    72	     * @throws RuntimeException
    73	     */
    74	    public function save(FormatInterface $format, $outputPathfile)
    75	    {
    76	        $passes = $this->buildCommand($format, $outputPathfile);
    77	
    78	        $failure = null;
    79	        $totalPasses = $format->getPasses();
    80	

exec
/bin/zsh -lc 'rg -n "class AbstractVideo|extends Audio|extends AbstractStreamableMedia" vendor/php-ffmpeg/php-ffmpeg/src/FFMpeg/Media/*.php' in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
vendor/php-ffmpeg/php-ffmpeg/src/FFMpeg/Media/Audio.php:24:class Audio extends AbstractStreamableMedia
vendor/php-ffmpeg/php-ffmpeg/src/FFMpeg/Media/AbstractVideo.php:27:abstract class AbstractVideo extends Audio

exec
/bin/zsh -lc 'rg -n "make qa|make test|phpstan|coverage|fail" docs/reviews/2026-06-22_01-01_media-video-audio-ffmpeg.md docs/executions/2026-06-20_22-19_media-video-audio-ffmpeg.md' in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
docs/executions/2026-06-20_22-19_media-video-audio-ffmpeg.md:23:- Стратегия проверок: phpstan гоняю после каждой фазы (быстро, без reset БД); полный сьют + покрытие 100% — финальным гейтом `make qa-build` (нужен `--build`, т.к. ffmpeg-бинарь в образе для интеграционных тестов фазы 3). Это согласовано со скиллом: «не гонять полный сьют на каждом шаге», финальная проверка — полный прогон.
docs/executions/2026-06-20_22-19_media-video-audio-ffmpeg.md:24:- Фаза 1: `composer.lock` обновлён внутри Docker (php-ffmpeg/php-ffmpeg v1.4.0 + symfony/process 8.1, spatie/temporary-directory, evenement). Образ пересобран — самопроверка `ffmpeg -version`/`ffprobe -version` прошла. `make phpstan` зелёный.
docs/executions/2026-06-20_22-19_media-video-audio-ffmpeg.md:35:- `make qa` (cs + phpstan + один coverage-run PCOV, ParaTest 4 процесса) — зелёный.
docs/executions/2026-06-20_22-19_media-video-audio-ffmpeg.md:40:  - Покрытие: **100.00%** при пороге 100% (`assert-coverage.php`).
docs/executions/2026-06-20_22-19_media-video-audio-ffmpeg.md:44:  применяется ко всем worker-БД в coverage-run).
docs/reviews/2026-06-22_01-01_media-video-audio-ffmpeg.md:34:ветку и уедут в `failed` без повтора.

exec
/bin/zsh -lc "rg -n \"assertSame\\(\\[|assertCount\\(|waveform|peaks|buildWaveform|AudioProcessor\" tests/Unit/Modules/Media/Infrastructure tests/Feature/Modules/Media/Infrastructure tests/Feature/Modules/Media/Flow tests/Feature/Modules/Media/Application | head -n 180" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php:162:            self::assertSame([], $exception->translationParameters());
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:19:use App\Modules\Media\Infrastructure\FileService\FfmpegMediaAudioProcessor;
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:76:    public function testAudioProcessorTranscodesToM4aWithWaveform(): void
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:97:        $peaks = $result->waveform->peaks();
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:100:        self::assertCount(64, $peaks);
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:101:        self::assertGreaterThan(0, \max($peaks));
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:102:        self::assertLessThanOrEqual(255, \max($peaks));
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:103:        self::assertGreaterThanOrEqual(0, \min($peaks));
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:136:    public function testAudioProcessorRejectsVideoSource(): void
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:239:            waveformPeaks: 64,
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:294:    private function audioProcessor(): FfmpegMediaAudioProcessor
tests/Feature/Modules/Media/Infrastructure/FfmpegMediaProcessorIntegrationTest.php:296:        return new FfmpegMediaAudioProcessor(
tests/Unit/Modules/Media/Infrastructure/FfmpegMediaProcessorTest.php:32:use Tests\Unit\Modules\Media\Infrastructure\Fixture\RecordingAudioProcessor;
tests/Unit/Modules/Media/Infrastructure/FfmpegMediaProcessorTest.php:55:        self::assertSame(['aac'], $format->getAvailableAudioCodecs());
tests/Unit/Modules/Media/Infrastructure/FfmpegMediaProcessorTest.php:131:    public function testAudioProcessorClassifiesTimeoutAsTransient(): void
tests/Unit/Modules/Media/Infrastructure/FfmpegMediaProcessorTest.php:134:        $processor = new RecordingAudioProcessor($this->fileServiceReturning($sourceFile), $this->mediaConfig());
tests/Unit/Modules/Media/Infrastructure/FfmpegMediaProcessorTest.php:147:    public function testAudioProcessorClassifiesGenericFailureAsPermanent(): void
tests/Unit/Modules/Media/Infrastructure/FfmpegMediaProcessorTest.php:150:        $processor = new RecordingAudioProcessor($this->fileServiceReturning($sourceFile), $this->mediaConfig());
tests/Unit/Modules/Media/Infrastructure/FfmpegMediaProcessorTest.php:163:    public function testAudioProcessorUploadsResultAndCleansTempFilesOnSuccess(): void
tests/Unit/Modules/Media/Infrastructure/FfmpegMediaProcessorTest.php:166:        $processor = new RecordingAudioProcessor($this->fileServiceReturning($sourceFile), $this->mediaConfig());
tests/Unit/Modules/Media/Infrastructure/FfmpegMediaProcessorTest.php:172:        self::assertSame([0, 128, 255], $result->waveform->peaks());
tests/Unit/Modules/Media/Infrastructure/FfmpegMediaProcessorTest.php:179:    public function testAudioProcessorCleansTempFilesWhenUploadFails(): void
tests/Unit/Modules/Media/Infrastructure/FfmpegMediaProcessorTest.php:188:        $processor = new RecordingAudioProcessor($fileService, $this->mediaConfig());
tests/Unit/Modules/Media/Infrastructure/FfmpegMediaProcessorTest.php:232:    private function processAudio(RecordingAudioProcessor $processor): MediaAudioProcessingResult
tests/Unit/Modules/Media/Infrastructure/FfmpegMediaProcessorTest.php:243:                waveformPeaks: 64,
tests/Unit/Modules/Media/Infrastructure/FfmpegMediaProcessorTest.php:276:            waveform: MediaWaveform::fromPeaks([0, 128, 255]),
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:69:        self::assertCount(1, $conversions);
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:88:        self::assertCount(1, $this->imageConversionRepository()->findByMediaId($media->id));
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:116:        self::assertCount(1, $conversions);
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:168:        self::assertCount(1, $this->imageConversionRepository()->findByMediaId($media->id));
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:183:        $this->completeUpload($media, $this->audioPlan($this->audioConversionSpec(waveformPeaks: 48)));
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:192:        self::assertCount(48, $audioConversion->waveform->peaks());
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:65:        self::assertCount(1, $captured->plan->image);
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:244:            plan: $this->audioPlan($this->audioConversionSpec(waveformPeaks: 100_000)),
tests/Feature/Modules/Media/Infrastructure/S3MediaFileServiceTest.php:77:        self::assertCount(1, $presignedParts);
tests/Unit/Modules/Media/Infrastructure/Cycle/MediaTypecastTest.php:102:        $waveform = MediaWaveformTypecast::castDatabaseValue('[0,128,255]');
tests/Unit/Modules/Media/Infrastructure/Cycle/MediaTypecastTest.php:104:        self::assertSame([0, 128, 255], $waveform->peaks());
tests/Unit/Modules/Media/Infrastructure/Cycle/MediaTypecastTest.php:105:        self::assertSame('[0,128,255]', MediaWaveformTypecast::uncastValue($waveform));
tests/Feature/Modules/Media/Application/GetAudioWaveformHandlerTest.php:33:        $waveform = $this->handler()->handle(new GetAudioWaveformQuery(mediaId: $media->id->value()));
tests/Feature/Modules/Media/Application/GetAudioWaveformHandlerTest.php:35:        self::assertSame([0, 64, 128, 255], $waveform->peaks());
tests/Feature/Modules/Media/Application/GetAudioWaveformHandlerTest.php:123:            waveform: MediaWaveform::fromPeaks([0, 64, 128, 255]),
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:297:            waveform: MediaWaveform::fromPeaks([0, 64, 128, 255]),
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:9:use App\Modules\Media\Application\Contract\MediaAudioProcessorContract;
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:56:        self::assertCount(1, $conversions);
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:96:        self::assertCount(0, $this->imageConversionRepository()->findByMediaId($media->id));
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:147:        $audioProcessor = $this->createStub(MediaAudioProcessorContract::class);
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:154:            waveform: MediaWaveform::fromPeaks([0, 64, 128, 255]),
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:167:        self::assertSame([0, 64, 128, 255], $audioConversion->waveform->peaks());
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:197:        self::assertCount(1, $this->videoConversionRepository()->findByMediaId($media->id));
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:268:        MediaAudioProcessorContract|null $audioProcessor = null,
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:284:            mediaAudioProcessor: $audioProcessor ?? $this->createStub(MediaAudioProcessorContract::class),
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:99:        self::assertCount(0, $this->imageConversionRepository()->findByMediaId($media->id));
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:125:        self::assertCount(0, $this->videoConversionRepository()->findByMediaId($media->id));
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:151:        self::assertCount(0, $this->audioConversionRepository()->findByMediaId($media->id));
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:292:            waveform: MediaWaveform::fromPeaks([0, 64, 128, 255]),
tests/Feature/Modules/Media/Application/MediaApplicationTestCase.php:92:        int $waveformPeaks = 64,
tests/Feature/Modules/Media/Application/MediaApplicationTestCase.php:98:            waveformPeaks: $waveformPeaks,
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:107:        self::assertCount(1, $result->parts);
tests/Unit/Modules/Media/Infrastructure/Fixture/RecordingAudioProcessor.php:9:use App\Modules\Media\Infrastructure\FileService\FfmpegMediaAudioProcessor;
tests/Unit/Modules/Media/Infrastructure/Fixture/RecordingAudioProcessor.php:16:final class RecordingAudioProcessor extends FfmpegMediaAudioProcessor

exec
/bin/zsh -lc "rg -n \"createMock\\(.*\\)->method|createMock|expects\\(self::any|expects\\(\\"'$this->any|->method'"\\(\" tests/Feature/Modules/Media tests/Unit/Modules/Media | head -n 200" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php:42:        $loader->method('load')->willReturn(new MediaUploaded(mediaId: $media->id->value(), plan: $this->emptyPlan()));
tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php:78:        $loader->method('load')->willReturn(new MediaUploaded(mediaId: $media->id->value(), plan: $this->emptyPlan()));
tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php:110:        $loader->method('load')->willReturn(new MediaUploaded(mediaId: $missingMediaId, plan: $this->emptyPlan()));
tests/Unit/Modules/Media/Infrastructure/FfmpegMediaProcessorTest.php:110:        $fileService->method('downloadToFile')->willReturn($sourceFile);
tests/Unit/Modules/Media/Infrastructure/FfmpegMediaProcessorTest.php:111:        $fileService->method('uploadFromFile')->willThrowException(
tests/Unit/Modules/Media/Infrastructure/FfmpegMediaProcessorTest.php:183:        $fileService->method('downloadToFile')->willReturn($sourceFile);
tests/Unit/Modules/Media/Infrastructure/FfmpegMediaProcessorTest.php:184:        $fileService->method('uploadFromFile')->willThrowException(
tests/Unit/Modules/Media/Infrastructure/FfmpegMediaProcessorTest.php:283:        $fileService->method('downloadToFile')->willReturn($sourceFile);
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:43:        $outboxStore = $this->createMock(OutboxEventStoreContract::class);
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:44:        $outboxStore->expects(self::once())->method('add')->willReturnCallback(
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:75:        $fileService = $this->createMock(MediaFileServiceContract::class);
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:76:        $fileService->method('headObject')->willReturn(new MediaObjectHead(contentLength: MediaFileSize::fromInt(2048)));
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:77:        $fileService->expects(self::once())->method('completeMultipartUpload');
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:442:        $fileService->method('headObject')->willReturn(null);
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:487:        $fileService->method('headObject')->willReturn(
tests/Feature/Modules/Media/Application/CompleteMediaUploadHandlerTest.php:497:        $outboxStore->method('add')->willReturn(StoredOutboxEventId::fromString('outbox-1'));
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:39:        $fileService = $this->createMock(MediaFileServiceContract::class);
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:40:        $fileService->method('publicUrl')->willReturn('http://minio/media-public/object');
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:41:        $fileService->expects(self::never())->method('presignGet');
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:60:        $fileService = $this->createMock(MediaFileServiceContract::class);
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:61:        $fileService->method('publicUrl')->willReturn('http://minio/media-public/object');
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:62:        $fileService->expects(self::never())->method('presignGet');
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:80:        $fileService->method('presignGet')->willReturnCallback(
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:118:        $fileService->method('presignGet')->willReturnCallback(
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:157:        $fileService->method('publicUrl')->willReturnCallback(
tests/Feature/Modules/Media/Application/GetMediaUrlHandlerTest.php:184:        $fileService->method('publicUrl')->willReturnCallback(
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:45:        $fileService = $this->createMock(MediaFileServiceContract::class);
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:46:        $fileService->method('publicUrl')->willReturn('http://minio/media-public/object');
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:47:        $fileService->expects(self::never())->method('presignGet');
tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php:64:        $fileService->method('presignGet')->willReturn('http://minio/signed');
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:42:        $fileService = $this->createMock(MediaFileServiceContract::class);
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:43:        $fileService->method('getObjectContents')->willReturn('original-bytes');
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:44:        $fileService->expects(self::once())->method('putObject');
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:45:        $fileService->expects(self::once())->method('copyObject');
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:66:        $fileService->method('getObjectContents')->willReturn('original-bytes');
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:84:        $fileService = $this->createMock(MediaFileServiceContract::class);
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:85:        $fileService->expects(self::never())->method('getObjectContents');
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:86:        $fileService->expects(self::never())->method('putObject');
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:87:        $fileService->expects(self::once())->method('copyObject');
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:104:        $fileService = $this->createMock(MediaFileServiceContract::class);
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:105:        $fileService->expects(self::never())->method('getObjectContents');
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:106:        $fileService->expects(self::once())->method('copyObject');
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:109:        $videoProcessor->method('process')->willReturn(new MediaVideoProcessingResult(
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:143:        $fileService = $this->createMock(MediaFileServiceContract::class);
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:144:        $fileService->expects(self::never())->method('getObjectContents');
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:145:        $fileService->expects(self::once())->method('copyObject');
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:148:        $audioProcessor->method('process')->willReturn(new MediaAudioProcessingResult(
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:176:        $fileService = $this->createMock(MediaFileServiceContract::class);
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:177:        $fileService->expects(self::once())->method('copyObject');
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:180:        $videoProcessor->method('process')->willReturn(new MediaVideoProcessingResult(
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:209:        $fileService = $this->createMock(MediaFileServiceContract::class);
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:210:        $fileService->expects(self::never())->method('getObjectContents');
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:211:        $fileService->expects(self::never())->method('copyObject');
tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:271:        $imageProcessor->method('resize')->willReturn(new MediaConversionResult(
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:39:        $fileService = $this->createMock(MediaFileServiceContract::class);
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:40:        $fileService->expects(self::once())->method('presignPut')->willReturnCallback(
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:81:        $fileService = $this->createMock(MediaFileServiceContract::class);
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:82:        $fileService->method('createMultipartUpload')->willReturn(MediaMultipartUploadIdValue::fromString('upload-1'));
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:83:        $fileService->method('presignUploadParts')->willReturnCallback(
tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php:96:        $fileService->expects(self::never())->method('presignPut');
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:45:        $fileService = $this->createMock(MediaFileServiceContract::class);
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:46:        $fileService->expects(self::once())->method('abortMultipartUpload');
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:47:        $fileService->expects(self::once())->method('deleteObject');
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:64:        $fileService = $this->createMock(MediaFileServiceContract::class);
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:65:        $fileService->expects(self::never())->method('abortMultipartUpload');
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:66:        $fileService->expects(self::once())->method('deleteObject');
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:83:        $fileService = $this->createMock(MediaFileServiceContract::class);
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:85:        $fileService->expects(self::exactly(2))->method('deleteObject')->willReturnCallback(
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:109:        $fileService = $this->createMock(MediaFileServiceContract::class);
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:111:        $fileService->expects(self::exactly(2))->method('deleteObject')->willReturnCallback(
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:135:        $fileService = $this->createMock(MediaFileServiceContract::class);
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:137:        $fileService->expects(self::exactly(2))->method('deleteObject')->willReturnCallback(
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:160:        $fileService = $this->createMock(MediaFileServiceContract::class);
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:161:        $fileService->expects(self::never())->method('abortMultipartUpload');
tests/Feature/Modules/Media/Application/DeleteMediaHandlerTest.php:162:        $fileService->expects(self::once())->method('deleteObject');

codex
Нашёл ещё одно пропущенное отклонение от плана: стратегия логирования требовала от ffmpeg-процессоров и Query-обработчиков отдельные DEBUG-события, но в этих классах вообще нет логгера. Текущий пункт 11 покрывает только финальный лог `ProcessMediaHandler`.
exec
/bin/zsh -lc "nl -ba app/src/Shared/Infrastructure/Cycle/ValueObjectCast.php | sed -n '1,240p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Shared\Infrastructure\Cycle;
     6	
     7	use BackedEnum;
     8	use Cycle\ORM\Parser\CastableInterface;
     9	use Cycle\ORM\Parser\UncastableInterface;
    10	
    11	final class ValueObjectCast implements CastableInterface, UncastableInterface
    12	{
    13	    /**
    14	     * Правила привязаны к конкретной роли Entity, поэтому класс stateful. Cycle
    15	     * создаёт по одному typecast-обработчику на роль через factory->make().
    16	     * Не биндить как #[Singleton] / общий синглтон — иначе правила разных ролей смешаются.
    17	     *
    18	     * @var array<non-empty-string, class-string>
    19	     */
    20	    private array $rules = [];
    21	
    22	    /**
    23	     * @param array<non-empty-string, mixed> $rules
    24	     * @return array<non-empty-string, mixed>
    25	     */
    26	    #[\Override]
    27	    public function setRules(array $rules): array
    28	    {
    29	        foreach ($rules as $field => $rule) {
    30	            if (!\is_string($rule) || !\class_exists($rule)) {
    31	                continue;
    32	            }
    33	
    34	            if (!$this->supportsRule($rule)) {
    35	                continue;
    36	            }
    37	
    38	            /** @var class-string $rule */
    39	            $this->rules[$field] = $rule;
    40	            unset($rules[$field]);
    41	        }
    42	
    43	        return $rules;
    44	    }
    45	
    46	    /**
    47	     * @param array<int|string, null|bool|int|float|string|\DateTimeInterface|object> $data
    48	     * @return array<int|string, null|bool|int|float|string|\DateTimeInterface|object>
    49	     */
    50	    #[\Override]
    51	    public function cast(array $data): array
    52	    {
    53	        foreach ($this->rules as $field => $rule) {
    54	            if (!\array_key_exists(key: $field, array: $data)) {
    55	                continue;
    56	            }
    57	
    58	            $data[$field] = $this->castField(rule: $rule, value: $data[$field]);
    59	        }
    60	
    61	        return $data;
    62	    }
    63	
    64	    /**
    65	     * @param array<int|string, null|bool|int|float|string|\DateTimeInterface|object> $data
    66	     * @return array<int|string, bool|int|float|string|\DateTimeInterface|null>
    67	     */
    68	    #[\Override]
    69	    public function uncast(array $data): array
    70	    {
    71	        foreach ($data as $field => $value) {
    72	            $data[$field] = $this->uncastField(
    73	                field: (string) $field,
    74	                value: $value,
    75	            );
    76	        }
    77	
    78	        return $data;
    79	    }
    80	
    81	    /**
    82	     * @param class-string $rule
    83	     */
    84	    private function supportsRule(string $rule): bool
    85	    {
    86	        return (
    87	            \is_subclass_of(object_or_class: $rule, class: ColumnValueTypecast::class)
    88	            && \method_exists(object_or_class: $rule, method: 'castDatabaseValue')
    89	            && \method_exists(object_or_class: $rule, method: 'uncastValue')
    90	        )
    91	            || \is_subclass_of(object_or_class: $rule, class: BackedEnum::class)
    92	            || \method_exists(object_or_class: $rule, method: 'fromString')
    93	            || \method_exists(object_or_class: $rule, method: 'fromInt');
    94	    }
    95	
    96	    /**
    97	     * @param class-string $rule
    98	     */
    99	    private function castField(
   100	        string $rule,
   101	        bool|int|float|string|object|null $value,
   102	    ): object|null {
   103	        if (\is_subclass_of(object_or_class: $rule, class: ColumnValueTypecast::class)) {
   104	            return $this->invokeColumnCast(
   105	                rule: $rule,
   106	                value: $this->databaseValueOrFail($value),
   107	            );
   108	        }
   109	
   110	        if ($value === null) {
   111	            return null;
   112	        }
   113	
   114	        if (\is_subclass_of(object_or_class: $rule, class: BackedEnum::class)) {
   115	            if (!\is_string($value) && !\is_int($value)) {
   116	                throw new \InvalidArgumentException('Enum-значение базы должно быть строкой или числом.');
   117	            }
   118	
   119	            return $rule::from($value);
   120	        }
   121	
   122	        if (\method_exists(object_or_class: $rule, method: 'fromString')) {
   123	            if (!\is_string($value)) {
   124	                throw new \InvalidArgumentException('Строковый value object должен восстанавливаться из строки.');
   125	            }
   126	
   127	            return $this->invokeObjectFactory(rule: $rule, method: 'fromString', value: $value);
   128	        }
   129	
   130	        // Инвариант: supportsRule() пропускает ровно четыре вида правил
   131	        // (ColumnValueTypecast, BackedEnum, fromString, fromInt); первые три отсечены выше,
   132	        // поэтому единственное оставшееся правило здесь — фабрика fromInt, и финальный throw
   133	        // означает «не-число для fromInt». Если в supportsRule() добавят новый вид правила,
   134	        // эту хвостовую ветку нужно расширить синхронно, иначе сообщение про «числовой
   135	        // value object» станет вводить в заблуждение.
   136	        if (\is_string($value) && \preg_match(pattern: '/^-?\d+$/', subject: $value) === 1) {
   137	            return $this->invokeObjectFactory(rule: $rule, method: 'fromInt', value: (int) $value);
   138	        }
   139	
   140	        if (\is_int($value)) {
   141	            return $this->invokeObjectFactory(rule: $rule, method: 'fromInt', value: $value);
   142	        }
   143	
   144	        throw new \InvalidArgumentException('Числовой value object должен восстанавливаться из числа.');
   145	    }
   146	
   147	    private function uncastField(
   148	        string $field,
   149	        bool|int|float|string|object|null $value,
   150	    ): bool|int|float|string|\DateTimeInterface|null {
   151	        $rule = $this->rules[$field] ?? null;
   152	
   153	        if ($rule !== null) {
   154	            return $this->uncastFieldByRule(rule: $rule, value: $value);
   155	        }
   156	
   157	        if ($value instanceof BackedEnum) {
   158	            return $value->value;
   159	        }
   160	
   161	        if ($value instanceof \DateTimeInterface) {
   162	            return $value;
   163	        }
   164	
   165	        if (\is_object($value)) {
   166	            throw new \InvalidArgumentException('Объект не поддерживает запись в базу.');
   167	        }
   168	
   169	        return $value;
   170	    }
   171	
   172	    /**
   173	     * @param class-string $rule
   174	     */
   175	    private function uncastFieldByRule(
   176	        string $rule,
   177	        bool|int|float|string|object|null $value,
   178	    ): bool|int|float|string|\DateTimeInterface|null {
   179	        if (\is_subclass_of(object_or_class: $rule, class: ColumnValueTypecast::class)) {
   180	            if ($value !== null && !\is_object($value)) {
   181	                return $value;
   182	            }
   183	
   184	            return $this->invokeColumnUncast(rule: $rule, value: $value);
   185	        }
   186	
   187	        if ($value instanceof BackedEnum) {
   188	            return $value->value;
   189	        }
   190	
   191	        if ($value instanceof \DateTimeInterface) {
   192	            return $value;
   193	        }
   194	
   195	        if (!\is_object($value)) {
   196	            return $value;
   197	        }
   198	
   199	        if (!\method_exists(object_or_class: $value, method: 'value')) {
   200	            throw new \InvalidArgumentException('Value object должен иметь метод value().');
   201	        }
   202	
   203	        return $this->valueObjectDatabaseValue($value);
   204	    }
   205	
   206	    /**
   207	     * @param class-string $rule
   208	     */
   209	    private function invokeObjectFactory(string $rule, string $method, string|int $value): object
   210	    {
   211	        $createdValue = (new \ReflectionMethod(objectOrMethod: $rule, method: $method))->invoke(null, $value);
   212	
   213	        if (!\is_object($createdValue)) {
   214	            throw new \InvalidArgumentException('Фабрика value object должна вернуть объект.');
   215	        }
   216	
   217	        return $createdValue;
   218	    }
   219	
   220	    /**
   221	     * @param class-string $rule
   222	     */
   223	    private function invokeColumnCast(
   224	        string $rule,
   225	        bool|int|float|string|\DateTimeInterface|null $value,
   226	    ): object {
   227	        $castValue = (new \ReflectionMethod(objectOrMethod: $rule, method: 'castDatabaseValue'))->invoke(null, $value);
   228	
   229	        if (!\is_object($castValue)) {
   230	            throw new \InvalidArgumentException('Typecast базы должен вернуть объект.');
   231	        }
   232	
   233	        return $castValue;
   234	    }
   235	
   236	    /**
   237	     * @param class-string $rule
   238	     */
   239	    private function invokeColumnUncast(
   240	        string $rule,

exec
/bin/zsh -lc 'rg -n "InvalidArgumentException|generic|guard-clause|RuntimeException|UnexpectedValueException|InvalidDomainValueException" docs/rules.md' in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
22:- **Типизированные Laravel Collections вместо массивов и Doctrine Collections**: в Entity и Repository не использовать массивы для наборов сущностей или value object. Для каждой доменной коллекции создавать именованный класс на базе `Illuminate\Support\Collection` с generic-типом элемента: например, связь `User -> Role` хранится и возвращается как `RoleCollection`, а результат репозитория со списком пользователей — как `UserCollection`. `Doctrine\Common\Collections\ArrayCollection` запрещена. Все связи Cycle ORM и методы репозиториев, возвращающие несколько элементов, должны возвращать конкретную типизированную коллекцию, а не `array` и не голый `Illuminate\Support\Collection`.
46:- **Исключения из ValueObject — это внутренние доменные ошибки (500)**: VO и доменные коллекции бросают `InvalidDomainValueException` (код 500), не `ValidationException` и не `\InvalidArgumentException`. `ValidationException` используется только на HTTP/API-границе, когда нужно вернуть пользователю ожидаемую ошибку 422. `GianTiaga\SpiralApiErrors\Interceptor\ApiExceptionInterceptor` возвращает для `InvalidDomainValueException` обычную 500-ошибку без исходного сообщения исключения в ответе.
47:- **Типизированные доменные исключения вместо `RuntimeException`**: запрещено использовать голый `\RuntimeException` с HTTP-кодом. Для ожидаемых HTTP-сценариев — свой класс: `NotFoundException` (404), `AuthenticationException` (401), `ForbiddenException` (403), `ValidationException` (422). Такие HTTP-исключения не логируются, а только превращаются в ответ пользователю. Для невалидного доменного значения — `InvalidDomainValueException` (500). Код зашит в конструкторе, передаётся только сообщение.
53:- **Запрет try-catch в контроллерах, Handler-ах и бизнес-логике**: контроллеры и Handler-ы не ловят исключения — ошибки всплывают до `GianTiaga\SpiralApiErrors\Interceptor\ApiExceptionInterceptor`, который конвертирует их в `ErrorResponse` с корректным HTTP-кодом. `try-catch` допустим только в инфраструктурном коде (interceptor-ы, middleware, Job-обработчики) — там, где исключение ловится на границе системы. В Handler-ах вместо try-catch на `ConstrainException` использовать проверку перед записью (check-then-act) — дублирование при конкурентных запросах допустимо как 500, которую `ApiExceptionInterceptor` обработает. Ожидаемые клиентские ошибки бросать типизированными доменными исключениями (`AuthenticationException`, `ForbiddenException` и т.д.) с 4xx-кодом в `$code`; внутренние нарушения доменных значений — через `InvalidDomainValueException`. Даже в инфраструктурном коде запрещён try-catch, который ловит широкий `\Throwable` и лишь перебрасывает его как другой тип исключения, не добавляя реальной обработки (логирование, fallback, retry, изменение состояния, освобождение ресурса) — пусть исходное исключение всплывает к обработчику на границе. Оборачивать чужое исключение в доменный тип оправдано только тогда, когда это добавляет существенный контекст, нужный потребителю, или требуется контрактом границы; перетипизация без пользы — лишний код. Если же ошибку код обнаруживает сам (guard-clause, проверка предусловия), он сразу бросает конкретное типизированное доменное исключение в источнике, а не generic-заглушку вроде `\UnexpectedValueException`/`\RuntimeException`.
77:- **Доменные методы в репозиториях**: запрещены generic-вызовы `findByPK()`, `findOne()`, `select()` в Handler-ах и другом бизнес-коде. Репозиторий предоставляет конкретные доменные методы: `findByEmail()`, `findByTokenHash()`, `findLatestByUserId()`. Базовые методы Repository (`findOne`, `findByPK`) используются только внутри самого Repository для реализации доменных методов.
86:  - Каждый метод set-based writer-а — именованная доменная операция (`markAllReadForRecipient`), а не generic `update(table, set, where)`; writer-классы держатся маленькими и узкими, каждый метод покрыт тестом. Маркер реализуется на классе-реализации в `Infrastructure`, а Application обращается к нему через `*Contract`. Блок «безопасность» PHPStan не проверяет — это зона ревью и докблока.
110:- **Без неявного `mixed`**: generic-параметры указываются явно. Нельзя писать `array`, если контракт на самом деле ожидает `list<Foo>` или `array<int, Foo>`.
112:- **Без явного `mixed`**: `mixed` не используется в проектных контрактах, включая параметры, return type, PHPDoc generic-и и callbacks. Неизвестное значение нужно сузить на границе системы и дальше передавать именованный DTO/VO/enum, конкретный union type или типизированную коллекцию.
113:- **Именованные типы для сложных данных**: вместо сложных generic-контейнеров используйте DTO/value object, enum или коллекции. Допустимы простые `list<T>` и `array<int|string, T>`, где `T` не является массивом, shape или tuple.

exec
/bin/zsh -lc "nl -ba app/src/Modules/Media/Application/Dto/MediaConversionPlan.php | sed -n '1,120p' && nl -ba app/src/Modules/Media/Application/Message/MediaUploaded.php | sed -n '1,90p' && nl -ba tests/Unit/Modules/Media/Application/MediaUploadedSerializationTest.php | sed -n '1,180p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Media\Application\Dto;
     6	
     7	/**
     8	 * План конверсий на тип медиа: отдельный список профилей для каждого MediaType. Потребитель
     9	 * задаёт списки на тип, валидируется только список, относящийся к типу медиа.
    10	 *
    11	 * Все три списка несут точные PHPDoc-типы list<...Spec> — это условие восстановления вложенного
    12	 * DTO через ValinorOutboxMessageSerializer (план кладётся в outbox-сообщение MediaUploaded).
    13	 */
    14	final readonly class MediaConversionPlan
    15	{
    16	    /**
    17	     * @param list<MediaImageConversionSpec> $image
    18	     * @param list<MediaVideoConversionSpec> $video
    19	     * @param list<MediaAudioConversionSpec> $audio
    20	     */
    21	    public function __construct(
    22	        public array $image,
    23	        public array $video,
    24	        public array $audio,
    25	    ) {}
    26	}
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Modules\Media\Application\Message;
     6	
     7	use App\Modules\Media\Application\Dto\MediaConversionPlan;
     8	use App\Modules\Outbox\Application\Message\OutboxMessage;
     9	
    10	/**
    11	 * Outbox-сообщение: загрузка подтверждена, нужна асинхронная обработка. Payload — только
    12	 * примитивы/enum (mediaId — строка, plan — MediaConversionPlan из публичных readonly-DTO с
    13	 * точными list<...Spec>), чтобы ValinorOutboxMessageSerializer восстановил его без кастомных
    14	 * конструкторов VO.
    15	 */
    16	final readonly class MediaUploaded implements OutboxMessage
    17	{
    18	    public function __construct(
    19	        public string $mediaId,
    20	        public MediaConversionPlan $plan,
    21	    ) {}
    22	}
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace Tests\Unit\Modules\Media\Application;
     6	
     7	use App\Modules\Media\Application\Dto\MediaAudioConversionSpec;
     8	use App\Modules\Media\Application\Dto\MediaConversionPlan;
     9	use App\Modules\Media\Application\Dto\MediaImageConversionSpec;
    10	use App\Modules\Media\Application\Dto\MediaVideoConversionSpec;
    11	use App\Modules\Media\Application\Message\MediaUploaded;
    12	use App\Modules\Media\Domain\Enum\MediaAudioConversionType;
    13	use App\Modules\Media\Domain\Enum\MediaImageConversionType;
    14	use App\Modules\Media\Domain\Enum\MediaVideoConversionType;
    15	use App\Modules\Outbox\Infrastructure\Message\ValinorOutboxMessageSerializer;
    16	use PHPUnit\Framework\TestCase;
    17	
    18	final class MediaUploadedSerializationTest extends TestCase
    19	{
    20	    public function testRestoresNestedConversionPlanThroughValinor(): void
    21	    {
    22	        $serializer = new ValinorOutboxMessageSerializer();
    23	
    24	        $serialized = $serializer->serialize(new MediaUploaded(
    25	            mediaId: '0192f2a0-0000-7000-8000-000000000001',
    26	            plan: new MediaConversionPlan(
    27	                image: [new MediaImageConversionSpec(type: MediaImageConversionType::Thumbnail, width: 100, height: 80)],
    28	                video: [new MediaVideoConversionSpec(
    29	                    type: MediaVideoConversionType::NormalizedMp4H264,
    30	                    width: 1280,
    31	                    height: 720,
    32	                    videoBitrate: 1_000_000,
    33	                    audioBitrate: 128_000,
    34	                )],
    35	                audio: [new MediaAudioConversionSpec(
    36	                    type: MediaAudioConversionType::NormalizedAacM4a,
    37	                    bitrate: 128_000,
    38	                    sampleRate: 44_100,
    39	                    waveformPeaks: 64,
    40	                )],
    41	            ),
    42	        ));
    43	        $restored = $serializer->deserialize(serializedOutboxMessage: $serialized);
    44	
    45	        self::assertSame(MediaUploaded::class, $serialized->type);
    46	        self::assertInstanceOf(MediaUploaded::class, $restored);
    47	        self::assertSame('0192f2a0-0000-7000-8000-000000000001', $restored->mediaId);
    48	
    49	        self::assertCount(1, $restored->plan->image);
    50	        self::assertSame(MediaImageConversionType::Thumbnail, $restored->plan->image[0]->type);
    51	        self::assertSame(100, $restored->plan->image[0]->width);
    52	        self::assertSame(80, $restored->plan->image[0]->height);
    53	
    54	        self::assertCount(1, $restored->plan->video);
    55	        self::assertSame(MediaVideoConversionType::NormalizedMp4H264, $restored->plan->video[0]->type);
    56	        self::assertSame(1280, $restored->plan->video[0]->width);
    57	        self::assertSame(1_000_000, $restored->plan->video[0]->videoBitrate);
    58	
    59	        self::assertCount(1, $restored->plan->audio);
    60	        self::assertSame(MediaAudioConversionType::NormalizedAacM4a, $restored->plan->audio[0]->type);
    61	        self::assertSame(44_100, $restored->plan->audio[0]->sampleRate);
    62	        self::assertSame(64, $restored->plan->audio[0]->waveformPeaks);
    63	    }
    64	}

exec
/bin/zsh -lc "nl -ba app/src/Shared/Infrastructure/Cycle/ColumnValueTypecast.php | sed -n '1,120p' && rg -n \"ColumnValueTypecast|InvalidArgumentException\" app/src/Shared app/src/Modules -g '*Typecast.php' | head -n 80" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
     1	<?php
     2	
     3	declare(strict_types=1);
     4	
     5	namespace App\Shared\Infrastructure\Cycle;
     6	
     7	interface ColumnValueTypecast {}
app/src/Modules/Media/Infrastructure/Cycle/MediaWaveformTypecast.php:8:use App\Shared\Infrastructure\Cycle\ColumnValueTypecast;
app/src/Modules/Media/Infrastructure/Cycle/MediaWaveformTypecast.php:10:final class MediaWaveformTypecast implements ColumnValueTypecast
app/src/Modules/Media/Infrastructure/Cycle/MediaWaveformTypecast.php:21:            throw new \InvalidArgumentException('Волна аудио должна быть JSON-массивом.');
app/src/Modules/Media/Infrastructure/Cycle/MediaWaveformTypecast.php:27:                throw new \InvalidArgumentException('Амплитуда волны должна быть целым числом.');
app/src/Modules/Media/Infrastructure/Cycle/MediaExpirationTypecast.php:8:use App\Shared\Infrastructure\Cycle\ColumnValueTypecast;
app/src/Modules/Media/Infrastructure/Cycle/MediaExpirationTypecast.php:10:final class MediaExpirationTypecast implements ColumnValueTypecast
app/src/Modules/Media/Infrastructure/Cycle/MediaMultipartPartCollectionTypecast.php:9:use App\Shared\Infrastructure\Cycle\ColumnValueTypecast;
app/src/Modules/Media/Infrastructure/Cycle/MediaMultipartPartCollectionTypecast.php:11:final class MediaMultipartPartCollectionTypecast implements ColumnValueTypecast
app/src/Modules/Media/Infrastructure/Cycle/MediaMultipartPartCollectionTypecast.php:23:            throw new \InvalidArgumentException('Список частей загрузки должен быть JSON-массивом.');
app/src/Modules/Media/Infrastructure/Cycle/MediaMultipartPartCollectionTypecast.php:29:                throw new \InvalidArgumentException('Часть загрузки имеет неверный формат.');
app/src/Modules/Media/Infrastructure/Cycle/MediaMultipartPartCollectionTypecast.php:36:                throw new \InvalidArgumentException('Часть загрузки имеет неверный формат.');
app/src/Modules/Media/Infrastructure/Cycle/MediaProcessingErrorTypecast.php:8:use App\Shared\Infrastructure\Cycle\ColumnValueTypecast;
app/src/Modules/Media/Infrastructure/Cycle/MediaProcessingErrorTypecast.php:10:final class MediaProcessingErrorTypecast implements ColumnValueTypecast
app/src/Shared/Infrastructure/Cycle/ColumnValueTypecast.php:7:interface ColumnValueTypecast {}
app/src/Modules/Notifications/Infrastructure/Cycle/NotificationActorTypecast.php:9:use App\Shared\Infrastructure\Cycle\ColumnValueTypecast;
app/src/Modules/Notifications/Infrastructure/Cycle/NotificationActorTypecast.php:16:final class NotificationActorTypecast implements ColumnValueTypecast
app/src/Modules/Notifications/Infrastructure/Cycle/NotificationActorTypecast.php:32:            throw new \InvalidArgumentException('Снимок автора уведомления должен быть JSON-объектом.');
app/src/Modules/Notifications/Infrastructure/Cycle/NotificationActorTypecast.php:40:            throw new \InvalidArgumentException('Снимок автора уведомления имеет неверный формат.');
app/src/Modules/Notifications/Infrastructure/Cycle/NotificationSettingStatusTypecast.php:8:use App\Shared\Infrastructure\Cycle\ColumnValueTypecast;
app/src/Modules/Notifications/Infrastructure/Cycle/NotificationSettingStatusTypecast.php:16:final class NotificationSettingStatusTypecast implements ColumnValueTypecast
app/src/Modules/Notifications/Infrastructure/Cycle/NotificationReadStateTypecast.php:8:use App\Shared\Infrastructure\Cycle\ColumnValueTypecast;
app/src/Modules/Notifications/Infrastructure/Cycle/NotificationReadStateTypecast.php:14:final class NotificationReadStateTypecast implements ColumnValueTypecast
app/src/Modules/Notifications/Infrastructure/Cycle/NotificationActionIdTypecast.php:8:use App\Shared\Infrastructure\Cycle\ColumnValueTypecast;
app/src/Modules/Notifications/Infrastructure/Cycle/NotificationActionIdTypecast.php:14:final class NotificationActionIdTypecast implements ColumnValueTypecast
app/src/Modules/Notifications/Infrastructure/Cycle/NotificationActionTypeTypecast.php:8:use App\Shared\Infrastructure\Cycle\ColumnValueTypecast;
app/src/Modules/Notifications/Infrastructure/Cycle/NotificationActionTypeTypecast.php:14:final class NotificationActionTypeTypecast implements ColumnValueTypecast
app/src/Modules/Posts/Infrastructure/Cycle/PostDeletionTypecast.php:8:use App\Shared\Infrastructure\Cycle\ColumnValueTypecast;
app/src/Modules/Posts/Infrastructure/Cycle/PostDeletionTypecast.php:10:final class PostDeletionTypecast implements ColumnValueTypecast
app/src/Modules/Posts/Infrastructure/Cycle/CommentDeletedAtTypecast.php:8:use App\Shared\Infrastructure\Cycle\ColumnValueTypecast;
app/src/Modules/Posts/Infrastructure/Cycle/CommentDeletedAtTypecast.php:10:final class CommentDeletedAtTypecast implements ColumnValueTypecast
app/src/Modules/User/Infrastructure/Cycle/UserAvatarTypecast.php:8:use App\Shared\Infrastructure\Cycle\ColumnValueTypecast;
app/src/Modules/User/Infrastructure/Cycle/UserAvatarTypecast.php:10:final class UserAvatarTypecast implements ColumnValueTypecast
app/src/Modules/Posts/Infrastructure/Cycle/PostLessonTypecast.php:8:use App\Shared\Infrastructure\Cycle\ColumnValueTypecast;
app/src/Modules/Posts/Infrastructure/Cycle/PostLessonTypecast.php:10:final class PostLessonTypecast implements ColumnValueTypecast
app/src/Modules/Posts/Infrastructure/Cycle/CommentParentTypecast.php:8:use App\Shared\Infrastructure\Cycle\ColumnValueTypecast;
app/src/Modules/Posts/Infrastructure/Cycle/CommentParentTypecast.php:10:final class CommentParentTypecast implements ColumnValueTypecast
app/src/Modules/Posts/Infrastructure/Cycle/PostOriginalTypecast.php:8:use App\Shared\Infrastructure\Cycle\ColumnValueTypecast;
app/src/Modules/Posts/Infrastructure/Cycle/PostOriginalTypecast.php:10:final class PostOriginalTypecast implements ColumnValueTypecast
app/src/Modules/Posts/Infrastructure/Cycle/BlockUnblockedAtTypecast.php:8:use App\Shared\Infrastructure\Cycle\ColumnValueTypecast;
app/src/Modules/Posts/Infrastructure/Cycle/BlockUnblockedAtTypecast.php:10:final class BlockUnblockedAtTypecast implements ColumnValueTypecast
app/src/Modules/Posts/Infrastructure/Cycle/PostTextTypecast.php:8:use App\Shared\Infrastructure\Cycle\ColumnValueTypecast;
app/src/Modules/Posts/Infrastructure/Cycle/PostTextTypecast.php:10:final class PostTextTypecast implements ColumnValueTypecast
app/src/Modules/User/Infrastructure/Cycle/UserBioTypecast.php:8:use App\Shared\Infrastructure\Cycle\ColumnValueTypecast;
app/src/Modules/User/Infrastructure/Cycle/UserBioTypecast.php:10:final class UserBioTypecast implements ColumnValueTypecast
app/src/Modules/Posts/Infrastructure/Cycle/BlockUnblockedByTypecast.php:9:use App\Shared\Infrastructure\Cycle\ColumnValueTypecast;
app/src/Modules/Posts/Infrastructure/Cycle/BlockUnblockedByTypecast.php:11:final class BlockUnblockedByTypecast implements ColumnValueTypecast
app/src/Modules/Posts/Infrastructure/Cycle/CommentDeletionReasonTypecast.php:8:use App\Shared\Infrastructure\Cycle\ColumnValueTypecast;
app/src/Modules/Posts/Infrastructure/Cycle/CommentDeletionReasonTypecast.php:10:final class CommentDeletionReasonTypecast implements ColumnValueTypecast
app/src/Modules/User/Infrastructure/Cycle/UserDeletionTypecast.php:8:use App\Shared\Infrastructure\Cycle\ColumnValueTypecast;
app/src/Modules/User/Infrastructure/Cycle/UserDeletionTypecast.php:10:final class UserDeletionTypecast implements ColumnValueTypecast
app/src/Modules/Posts/Infrastructure/Cycle/CommentDeletedByTypecast.php:9:use App\Shared\Infrastructure\Cycle\ColumnValueTypecast;
app/src/Modules/Posts/Infrastructure/Cycle/CommentDeletedByTypecast.php:11:final class CommentDeletedByTypecast implements ColumnValueTypecast
app/src/Modules/Posts/Infrastructure/Cycle/PostPracticeTypecast.php:8:use App\Shared\Infrastructure\Cycle\ColumnValueTypecast;
app/src/Modules/Posts/Infrastructure/Cycle/PostPracticeTypecast.php:10:final class PostPracticeTypecast implements ColumnValueTypecast
app/src/Modules/User/Infrastructure/Cycle/UserSpiritualNameTypecast.php:8:use App\Shared\Infrastructure\Cycle\ColumnValueTypecast;
app/src/Modules/User/Infrastructure/Cycle/UserSpiritualNameTypecast.php:10:final class UserSpiritualNameTypecast implements ColumnValueTypecast
app/src/Modules/Posts/Infrastructure/Cycle/BlockUnblockedReasonTypecast.php:8:use App\Shared\Infrastructure\Cycle\ColumnValueTypecast;
app/src/Modules/Posts/Infrastructure/Cycle/BlockUnblockedReasonTypecast.php:10:final class BlockUnblockedReasonTypecast implements ColumnValueTypecast
app/src/Modules/User/Infrastructure/Cycle/UserLocationTypecast.php:8:use App\Shared\Infrastructure\Cycle\ColumnValueTypecast;
app/src/Modules/User/Infrastructure/Cycle/UserLocationTypecast.php:10:final class UserLocationTypecast implements ColumnValueTypecast
app/src/Modules/Auth/Infrastructure/Cycle/UserAgentTypecast.php:8:use App\Shared\Infrastructure\Cycle\ColumnValueTypecast;
app/src/Modules/Auth/Infrastructure/Cycle/UserAgentTypecast.php:16:final class UserAgentTypecast implements ColumnValueTypecast
app/src/Modules/Auth/Infrastructure/Cycle/IpTypecast.php:8:use App\Shared\Infrastructure\Cycle\ColumnValueTypecast;
app/src/Modules/Auth/Infrastructure/Cycle/IpTypecast.php:16:final class IpTypecast implements ColumnValueTypecast
app/src/Modules/Auth/Infrastructure/Cycle/ExpirationTypecast.php:8:use App\Shared\Infrastructure\Cycle\ColumnValueTypecast;
app/src/Modules/Auth/Infrastructure/Cycle/ExpirationTypecast.php:14:final class ExpirationTypecast implements ColumnValueTypecast
app/src/Modules/User/Infrastructure/Cycle/ReservedNicknameHolderTypecast.php:9:use App\Shared\Infrastructure\Cycle\ColumnValueTypecast;
app/src/Modules/User/Infrastructure/Cycle/ReservedNicknameHolderTypecast.php:11:final class ReservedNicknameHolderTypecast implements ColumnValueTypecast
app/src/Modules/Auth/Infrastructure/Cycle/ConsumptionTypecast.php:8:use App\Shared\Infrastructure\Cycle\ColumnValueTypecast;
app/src/Modules/Auth/Infrastructure/Cycle/ConsumptionTypecast.php:15:final class ConsumptionTypecast implements ColumnValueTypecast
app/src/Modules/User/Infrastructure/Cycle/BanExpirationTypecast.php:8:use App\Shared\Infrastructure\Cycle\ColumnValueTypecast;
app/src/Modules/User/Infrastructure/Cycle/BanExpirationTypecast.php:10:final class BanExpirationTypecast implements ColumnValueTypecast
app/src/Modules/User/Infrastructure/Cycle/BanUnbannedReasonTypecast.php:8:use App\Shared\Infrastructure\Cycle\ColumnValueTypecast;
app/src/Modules/User/Infrastructure/Cycle/BanUnbannedReasonTypecast.php:10:final class BanUnbannedReasonTypecast implements ColumnValueTypecast
app/src/Modules/User/Infrastructure/Cycle/BanUnbannedByTypecast.php:9:use App\Shared\Infrastructure\Cycle\ColumnValueTypecast;
app/src/Modules/User/Infrastructure/Cycle/BanUnbannedByTypecast.php:11:final class BanUnbannedByTypecast implements ColumnValueTypecast
app/src/Modules/User/Infrastructure/Cycle/BanUnbannedAtTypecast.php:8:use App\Shared\Infrastructure\Cycle\ColumnValueTypecast;
app/src/Modules/User/Infrastructure/Cycle/BanUnbannedAtTypecast.php:10:final class BanUnbannedAtTypecast implements ColumnValueTypecast
app/src/Modules/Outbox/Infrastructure/Cycle/OutboxAvailableAtTypecast.php:8:use App\Shared\Infrastructure\Cycle\ColumnValueTypecast;
app/src/Modules/Outbox/Infrastructure/Cycle/OutboxAvailableAtTypecast.php:10:final class OutboxAvailableAtTypecast implements ColumnValueTypecast

exec
/bin/zsh -lc "nl -ba app/src/Modules/Media/Application/Service/MediaTypeResolver.php | sed -n '1,80p' && nl -ba tests/Unit/Modules/Media/Application/MediaTypeResolverTest.php | sed -n '1,100p'" in /Users/gian_tiaga/Code/yoga-loka-spiral-2
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
    12	 * Резолвер MIME -> MediaType. Намеренно сужающий: поддержаны image/*, video/* и audio/*;
    13	 * прочие типы (документы и т.п.) вне scope пайплайна и отклоняются как ожидаемая клиентская
    14	 * ошибка 422 (MediaPath допускает только префиксы uploads|images|videos|audios).
    15	 */
    16	final readonly class MediaTypeResolver
    17	{
    18	    public function resolve(MediaMimeType $mimeType): MediaType
    19	    {
    20	        $value = $mimeType->value();
    21	
    22	        if (\str_starts_with(haystack: $value, needle: 'image/')) {
    23	            return MediaType::Image;
    24	        }
    25	
    26	        if (\str_starts_with(haystack: $value, needle: 'video/')) {
    27	            return MediaType::Video;
    28	        }
    29	
    30	        if (\str_starts_with(haystack: $value, needle: 'audio/')) {
    31	            return MediaType::Audio;
    32	        }
    33	
    34	        throw new ValidationException(
    35	            translationKey: 'app.media.unsupported_file_type',
    36	            translationParameters: ['type' => $value],
    37	        );
    38	    }
    39	}
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
    26	    #[DataProvider('unsupportedMimeTypeProvider')]
    27	    public function testRejectsUnsupportedMimeTypes(string $mimeType): void
    28	    {
    29	        $this->expectException(ValidationException::class);
    30	
    31	        new MediaTypeResolver()->resolve(MediaMimeType::fromString($mimeType));
    32	    }
    33	
    34	    /**
    35	     * @return list<array{string}>
    36	     */
    37	    public static function unsupportedMimeTypeProvider(): array
    38	    {
    39	        return [['application/pdf'], ['text/plain'], ['font/woff2']];
    40	    }
    41	}

codex
Проверяю пункт про языковые правила точнее: он верный по сути, но список мест неполный. В новых комментариях есть ещё «scope пайплайна», `Trade-off`, `retry/terminal` и отдельный комментарий с «ретраябельный», которые ревью не перечисляет.
exec
/bin/zsh -lc 'rg -n "транзиент|ретра|throttling|scope|пайплайн|Trade-off|retry|terminal|payload|worker|debug|DEBUG|WARN|ERROR" app/src/Modules/Media app/src/Shared/Infrastructure/Configuration/Media app/config/media.php tests/Feature/Modules/Media tests/Unit/Modules/Media | head -n 240' in /Users/gian_tiaga/Code/yoga-loka-spiral-2
 succeeded in 0ms:
app/config/media.php:8: * Только технические параметры пайплайна загрузки: staging-TTL для MediaExpiration при
tests/Unit/Modules/Media/Domain/Entity/MediaEntityTest.php:85:        // Готовое медиа не «ломается» задним числом: фиксация ошибки (транзиентной и постоянной)
app/src/Modules/Media/Presentation/Job/ProcessMediaJob.php:24: * разрешён): фиксируем безопасную ошибку на Media и классифицируем — транзиентную просим
app/src/Modules/Media/Presentation/Job/ProcessMediaJob.php:35:        OutboxQueueEnvelope $payload,
app/src/Modules/Media/Presentation/Job/ProcessMediaJob.php:44:            outboxEventId: $payload->outboxEventId,
app/src/Modules/Media/Presentation/Job/ProcessMediaJob.php:65:            // чтобы вторичный сбой записи не подменил исходную причину и решение retry/terminal:
app/src/Modules/Media/Presentation/Job/ProcessMediaJob.php:66:            // логируем его как вторичный сбой (ERROR — реальная инфра/инвариант-проблема, rules.md:84)
app/src/Modules/Media/Presentation/Job/ProcessMediaJob.php:95:                // Временный ретраябельный сбой инфраструктуры -> WARN (rules.md:84), повтор ожидаем.
app/src/Modules/Media/Presentation/Job/ProcessMediaJob.php:102:                $retryReason = $exception instanceof MediaProcessorFailedException
app/src/Modules/Media/Presentation/Job/ProcessMediaJob.php:106:                throw new RetryException(reason: $retryReason);
app/src/Modules/Media/Presentation/Job/ProcessMediaJob.php:109:            // Постоянный сбой (битый файл/нарушение инварианта) -> ERROR, повтора не будет.
tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php:252:                flags: \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR | \JSON_INVALID_UTF8_SUBSTITUTE,
app/src/Modules/Media/Application/Exception/MediaFileServiceFailedException.php:9: * признак транзиентности (`isTransient()`), чтобы потребитель контракта (ProcessMediaJob) выбирал
app/src/Modules/Media/Application/Exception/MediaFileServiceFailedException.php:27:     * Транзиентный (ретраябельный) сбой хранилища: сетевые ошибки, 5xx, throttling.
tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php:48:                payload: $this->envelope(),
tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php:85:                payload: $this->envelope(),
tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php:94:            // Job всегда пробрасывает: транзиентный -> RetryException, постоянный -> исходное.
tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php:106:        // для транзиентного исходного сбоя Job всё равно бросает RetryException.
tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php:125:                payload: $this->envelope(),
tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php:138:        // Исходная причина не подменяется: транзиентный сбой -> RetryException, а не NotFoundException.
tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php:140:        // Вторичный сбой записи залогирован как ERROR (rules.md:84), плюс ERROR классификации не мешает
tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php:141:        // WARN транзиентного ретрая.
tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php:142:        self::assertTrue($logger->hasLevel(LogLevel::ERROR));
tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php:143:        self::assertTrue($logger->hasLevel(LogLevel::WARNING));
tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php:174:            'транзиентный -> WARNING' => [
tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php:176:                LogLevel::WARNING,
tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php:177:                LogLevel::ERROR,
tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php:179:            'постоянный -> ERROR' => [
tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php:181:                LogLevel::ERROR,
tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php:182:                LogLevel::WARNING,
tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php:184:            'временный сбой процессора -> WARNING' => [
tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php:186:                LogLevel::WARNING,
tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php:187:                LogLevel::ERROR,
tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php:189:            'постоянный сбой процессора -> ERROR' => [
tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php:191:                LogLevel::ERROR,
tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php:192:                LogLevel::WARNING,
tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php:207:            'транзиентный сбой хранилища -> повтор' => [
tests/Unit/Modules/Media/Infrastructure/S3MediaFileServiceErrorTest.php:287:            'сетевая ошибка -> транзиентная' => [
tests/Unit/Modules/Media/Infrastructure/S3MediaFileServiceErrorTest.php:291:            '5xx -> транзиентная' => [
tests/Unit/Modules/Media/Infrastructure/S3MediaFileServiceErrorTest.php:295:            'throttling -> транзиентная' => [
tests/Unit/Modules/Media/Infrastructure/S3MediaFileServiceErrorTest.php:296:                new S3Exception('throttling', $command, ['code' => 'Throttling']),
tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php:96:        // Имитируем зафиксированный транзиентный сбой: медиа осталось в processingFailed.
app/src/Modules/Media/Application/Exception/MediaProcessorFailedException.php:28:     * Временный (ретраябельный) сбой обработки: таймаут транскодирования, нехватка ресурсов процесса.
tests/Feature/Modules/Media/Flow/Fixture/RecordingMediaLogger.php:10: * Логгер-спай для проверки логов Media-сценариев: уровня (транзиентный сбой -> WARNING,
tests/Feature/Modules/Media/Flow/Fixture/RecordingMediaLogger.php:11: * постоянный -> ERROR) и контекста (например, applied presignedTtlSeconds в запросе загрузки).
app/src/Modules/Media/Application/Service/MediaTypeResolver.php:13: * прочие типы (документы и т.п.) вне scope пайплайна и отклоняются как ожидаемая клиентская
app/src/Modules/Media/Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php:76:        $this->logger->debug(message: 'Загрузка медиа подтверждена.', context: [
app/src/Modules/Media/README.md:43:- Профили конверсии на тип — **публичные** примитив-дружественные DTO (идут в outbox-payload),
app/src/Modules/Media/README.md:80:стратегию повтора по **контрактному сигналу** транзиентности, а не по типу хранилища:
app/src/Modules/Media/README.md:81:`MediaFileServiceFailedException::isTransient()` → транзиентная (сетевые/5xx/throttling) даёт
app/src/Modules/Media/README.md:82:`RetryException` (событие остаётся на повтор) и лог уровня WARN; постоянная (Imagick/битый файл,
app/src/Modules/Media/README.md:83:нештатный ответ S3) → терминальный проброс (outbox → `failed`) и лог уровня ERROR. Ошибки ffmpeg
app/src/Modules/Media/README.md:93:БД), вторичный сбой логируется уровнем ERROR и не подменяет исходную причину — Job всё равно
app/src/Modules/Media/README.md:94:выбирает retry/terminal по исходному исключению (транзиентное → `RetryException`).
app/src/Modules/Media/README.md:99:  outbox происходят в одной транзакции. Trade-off: для multipart-ветки внешние S3-операции
app/src/Modules/Media/Application/Command/DeleteMedia/DeleteMediaHandler.php:60:        $this->logger->debug(message: 'Медиа удалено.', context: [
app/src/Modules/Media/Application/Command/MakeMediaPermanent/MakeMediaPermanentHandler.php:44:        $this->logger->debug(message: 'Медиа помечено постоянным.', context: [
app/src/Modules/Media/Application/Command/RequestMediaUpload/RequestMediaUploadHandler.php:62:        $this->logger->debug(message: 'Запрошена загрузка медиа.', context: [
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:57:            $this->logger->debug(message: 'Обработка медиа пропущена: уже ready.', context: [
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:64:        $this->logger->debug(message: 'Начата обработка медиа.', context: [
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:107:        $this->logger->debug(message: 'Медиа готово.', context: [
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:150:            $this->logger->debug(message: 'Создана конверсия изображения.', context: [
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:227:            $this->logger->debug(message: 'Создана конверсия видео и постер.', context: [
app/src/Modules/Media/Application/Command/ProcessMedia/ProcessMediaHandler.php:272:            $this->logger->debug(message: 'Создана конверсия аудио с волной.', context: [
app/src/Modules/Media/Application/Command/RecordMediaProcessingFailure/RecordMediaProcessingFailureHandler.php:15: * Фиксирует ошибку обработки на Media. Доменные методы записи транзиентной и постоянной
app/src/Modules/Media/Application/Command/RecordMediaProcessingFailure/RecordMediaProcessingFailureHandler.php:17: * isTransient влияет только на решение ProcessMediaJob о ретрае и идёт сюда лишь как контекст
app/src/Modules/Media/Application/Command/RecordMediaProcessingFailure/RecordMediaProcessingFailureHandler.php:37:        $this->logger->debug(message: 'Зафиксирована ошибка обработки медиа.', context: [
app/src/Modules/Media/Domain/Entity/Media.php:219:     * processing или processingFailed (ретрай обработки после транзиентной ошибки).
app/src/Modules/Media/Infrastructure/Cycle/MediaMultipartPartCollectionTypecast.php:16:        $payload = \json_decode(
app/src/Modules/Media/Infrastructure/Cycle/MediaMultipartPartCollectionTypecast.php:19:            flags: \JSON_THROW_ON_ERROR | \JSON_INVALID_UTF8_SUBSTITUTE,
app/src/Modules/Media/Infrastructure/Cycle/MediaMultipartPartCollectionTypecast.php:22:        if (!\is_array($payload)) {
app/src/Modules/Media/Infrastructure/Cycle/MediaMultipartPartCollectionTypecast.php:27:        foreach ($payload as $partPayload) {
app/src/Modules/Media/Infrastructure/Cycle/MediaMultipartPartCollectionTypecast.php:50:            flags: \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR | \JSON_INVALID_UTF8_SUBSTITUTE,
app/src/Modules/Media/Infrastructure/Cycle/MediaWaveformTypecast.php:14:        $payload = \json_decode(
app/src/Modules/Media/Infrastructure/Cycle/MediaWaveformTypecast.php:17:            flags: \JSON_THROW_ON_ERROR | \JSON_INVALID_UTF8_SUBSTITUTE,
app/src/Modules/Media/Infrastructure/Cycle/MediaWaveformTypecast.php:20:        if (!\is_array($payload)) {
app/src/Modules/Media/Infrastructure/Cycle/MediaWaveformTypecast.php:25:        foreach ($payload as $peak) {
app/src/Modules/Media/Infrastructure/Cycle/MediaWaveformTypecast.php:40:            flags: \JSON_THROW_ON_ERROR | \JSON_INVALID_UTF8_SUBSTITUTE,
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:33:    private const array TRANSIENT_AWS_ERROR_CODES = [
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:375:     * транзиентности. Знание об AWS остаётся в Infrastructure; наружу уходит только контрактный
app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php:398:            haystack: self::TRANSIENT_AWS_ERROR_CODES,
app/src/Modules/Media/Infrastructure/FileService/AbstractFfmpegMediaProcessor.php:84:        // Таймаут транскодирования — единственный временный (ретраябельный) сбой: ищем

codex
+ Добавить: ревью пропустило более широкое отклонение от плана логирования. План требует DEBUG-логи в ffmpeg-процессорах и Query-обработчиках, но в `FfmpegMediaAudioProcessor.php:37`, `FfmpegMediaVideoProcessor.php:35`, `GetMediaUrlHandler.php:28`, `GetAudioWaveformHandler.php:20` даже нет `LoggerInterface`. Пункт 11 покрывает только финальный лог `ProcessMediaHandler.php:107`

− Убрать план 2. Это не проблема: для video/audio дубли тоже дают 422, потому что `assertVideoPlan()` и `assertAudioPlan()` требуют ровно один профиль: `CompleteMediaUploadHandler.php:156` и `CompleteMediaUploadHandler.php:176`. Плановое требование «дубли типов в любом списке → 422» фактически выполняется, просто не отдельной проверкой `unique`

− Убрать замечание 2a. Утверждение «не документированный контракт php-ffmpeg» неверное: `FFMpeg::open()` имеет `@return Audio|Video` в `vendor/php-ffmpeg/php-ffmpeg/src/FFMpeg/FFMpeg.php:87`, README тоже говорит о двух типах `Audio` и `Video` в `vendor/php-ffmpeg/php-ffmpeg/README.md:108`, а код возвращает `Video` при наличии видеопотока в `FFMpeg.php:97`

− Убрать замечание 4 как недоказанное. Сам текст признаёт, что для текущих форматов проблемы нет, а интеграционный тест гоняет mp4/wav через `downloadToFile` без расширения: `FfmpegMediaProcessorIntegrationTest.php:36` и `FfmpegMediaProcessorIntegrationTest.php:76`

− Убрать или сильно сократить замечание 5. Ветка `Document` в `CompleteMediaUploadHandler.php:123` и `ProcessMediaHandler.php:89` является нормальным защитным `match` по enum и прямо соответствует плану. Само по себе отсутствие комментария не является реальной проблемой

~ Замечание 1: предложенный тест «скачивание несуществующего объекта не оставляет файл» может не поймать баг, потому что S3 может упасть до создания `SaveAs`-файла. Для проверки нужен сценарий, где частичный `$localFile` уже создан, а затем `getObject()` бросает `AwsException`: `S3MediaFileService.php:222`

~ Замечание 2 нужно поднять из «тестового пробела» в реальный баг результата: при `$peaks > $sampleCount` часть интервалов пуста, и код молча пишет амплитуду 0: `FfmpegMediaAudioProcessor.php:151`. Валидный `waveformPeaks` может быть до 4096, а короткое аудио этот случай реально допускает

~ Замечание 3 лучше переформулировать мягче. `\InvalidArgumentException` в typecast-слое — существующий паттерн проекта: `MediaMultipartPartCollectionTypecast.php:23` и общий `ValueObjectCast.php:116`. Это не стоит подавать как однозначное нарушение правил для одного нового класса `MediaWaveformTypecast.php:21`

~ Замечание 7 неполное по фактическим строкам. Кроме перечисленного, есть новые англицизмы/кальки в `MediaTypeResolver.php:13`, `ProcessMediaJob.php:65`, `ProcessMediaJob.php:95`, `README.md:43`, `README.md:99`, а также в тестовых комментариях `ProcessMediaJobTest.php:94` и `MediaProcessingFlowTest.php:96`

~ Замечание 9: фразу «Presentation в итоге получит сырой Domain-VO» надо заменить на более точную. HTTP-входа сейчас нет; фактическое нарушение только в том, что Query Handler возвращает VO, а не Result DTO: `GetAudioWaveformHandler.php:25`

? Не проверял фактический прогон `make test` / `make phpstan`; оценивал корректность ревью по документам и коду.
tokens used
205 720
+ Добавить: ревью пропустило более широкое отклонение от плана логирования. План требует DEBUG-логи в ffmpeg-процессорах и Query-обработчиках, но в `FfmpegMediaAudioProcessor.php:37`, `FfmpegMediaVideoProcessor.php:35`, `GetMediaUrlHandler.php:28`, `GetAudioWaveformHandler.php:20` даже нет `LoggerInterface`. Пункт 11 покрывает только финальный лог `ProcessMediaHandler.php:107`

− Убрать план 2. Это не проблема: для video/audio дубли тоже дают 422, потому что `assertVideoPlan()` и `assertAudioPlan()` требуют ровно один профиль: `CompleteMediaUploadHandler.php:156` и `CompleteMediaUploadHandler.php:176`. Плановое требование «дубли типов в любом списке → 422» фактически выполняется, просто не отдельной проверкой `unique`

− Убрать замечание 2a. Утверждение «не документированный контракт php-ffmpeg» неверное: `FFMpeg::open()` имеет `@return Audio|Video` в `vendor/php-ffmpeg/php-ffmpeg/src/FFMpeg/FFMpeg.php:87`, README тоже говорит о двух типах `Audio` и `Video` в `vendor/php-ffmpeg/php-ffmpeg/README.md:108`, а код возвращает `Video` при наличии видеопотока в `FFMpeg.php:97`

− Убрать замечание 4 как недоказанное. Сам текст признаёт, что для текущих форматов проблемы нет, а интеграционный тест гоняет mp4/wav через `downloadToFile` без расширения: `FfmpegMediaProcessorIntegrationTest.php:36` и `FfmpegMediaProcessorIntegrationTest.php:76`

− Убрать или сильно сократить замечание 5. Ветка `Document` в `CompleteMediaUploadHandler.php:123` и `ProcessMediaHandler.php:89` является нормальным защитным `match` по enum и прямо соответствует плану. Само по себе отсутствие комментария не является реальной проблемой

~ Замечание 1: предложенный тест «скачивание несуществующего объекта не оставляет файл» может не поймать баг, потому что S3 может упасть до создания `SaveAs`-файла. Для проверки нужен сценарий, где частичный `$localFile` уже создан, а затем `getObject()` бросает `AwsException`: `S3MediaFileService.php:222`

~ Замечание 2 нужно поднять из «тестового пробела» в реальный баг результата: при `$peaks > $sampleCount` часть интервалов пуста, и код молча пишет амплитуду 0: `FfmpegMediaAudioProcessor.php:151`. Валидный `waveformPeaks` может быть до 4096, а короткое аудио этот случай реально допускает

~ Замечание 3 лучше переформулировать мягче. `\InvalidArgumentException` в typecast-слое — существующий паттерн проекта: `MediaMultipartPartCollectionTypecast.php:23` и общий `ValueObjectCast.php:116`. Это не стоит подавать как однозначное нарушение правил для одного нового класса `MediaWaveformTypecast.php:21`

~ Замечание 7 неполное по фактическим строкам. Кроме перечисленного, есть новые англицизмы/кальки в `MediaTypeResolver.php:13`, `ProcessMediaJob.php:65`, `ProcessMediaJob.php:95`, `README.md:43`, `README.md:99`, а также в тестовых комментариях `ProcessMediaJobTest.php:94` и `MediaProcessingFlowTest.php:96`

~ Замечание 9: фразу «Presentation в итоге получит сырой Domain-VO» надо заменить на более точную. HTTP-входа сейчас нет; фактическое нарушение только в том, что Query Handler возвращает VO, а не Result DTO: `GetAudioWaveformHandler.php:25`

? Не проверял фактический прогон `make test` / `make phpstan`; оценивал корректность ревью по документам и коду.
