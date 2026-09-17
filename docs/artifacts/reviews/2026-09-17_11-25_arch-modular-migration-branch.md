---
target: ветка arch-modular-migration относительно main (диапазон d0346e5..HEAD, 81 коммит, 1169 файлов)
plan: none
execution: main
score: 74
status: reviewed
result: changes-required
date: 2026-09-17 11:25
checks:
  - name: correctness
    model: claude-sonnet-5
    status: completed
    reason: always — проверены сценарии удаления/согласованности после снятия межмодульных FK и логика ключевых Command/Query handler-ов
  - name: architecture
    model: claude-sonnet-5
    status: completed
    reason: auto — вся ветка меняет границы модулей, направления зависимостей и владение данными
  - name: rules
    model: claude-sonnet-5
    status: completed
    reason: always — сверено с docs/rules.md (именование, коллекции, подавления, тесты)
  - name: references
    model: claude-sonnet-5
    status: skipped
    reason: цель — вся ветка целиком, под неё формально подходят почти все карточки docs/references; вместо неотобранной загрузки всех карточек эквивалентные требования (роли, Mapper, Reader, Repository) проверены напрямую по docs/arch.md в проверке architecture
  - name: business
    model: claude-sonnet-5
    status: completed
    reason: auto — проверено, описывает ли docs/business/posts.md и docs/business/profile.md поведение после удаления медиа; прямого нарушения карточки не найдено, см. раздел «Проблемы бизнес-логики»
  - name: plan_alignment
    model: none
    status: skipped
    reason: единого плана всей ветки нет — 9 отдельных волновых планов (docs/artifacts/plans/2026-09-15…2026-09-17_volna-*), однозначный PLAN_FILE не определяется
  - name: code_quality
    model: claude-sonnet-5
    status: completed
    reason: always — просмотрены Mapper, миграции, Command/Query handler-ы нескольких модулей
  - name: tests
    model: claude-sonnet-5
    status: completed
    reason: always — проверены замены тестов волны C, паттерны stub/mock, отсутствие markTestSkipped/assertTrue(true)
  - name: security
    model: claude-sonnet-5
    status: completed
    reason: auto — атрибуты доступа, отсутствие маршрутов Access, отсутствие утечки секретов в SessionResource
  - name: performance
    model: claude-sonnet-5
    status: completed
    reason: auto — проверена пакетная загрузка URL медиа (FindMediaUrlsHandler) без N+1
  - name: frontend
    model: none
    status: skipped
    reason: not_applicable — API-only backend, фронтенда в репозитории нет
  - name: api
    model: claude-sonnet-5
    status: completed
    reason: auto — проверены формы ответов (PostResource, SessionResource), заявленная побайтовая неизменность OpenAPI подтверждена журналом волны I
  - name: database
    model: claude-sonnet-5
    status: completed
    reason: auto — разобраны все 5 миграций, снимающих межмодульные внешние ключи, и их прикладные последствия
  - name: documentation
    model: claude-sonnet-5
    status: completed
    reason: auto — сверен раздел «Осознанные отступления» docs/arch.md с фактическим кодом и журналами волн
  - name: previous_reviews
    model: none
    status: skipped
    reason: not_applicable — цель не PR/MR, удалённых обсуждений нет
---

# Ревью: переезд на целевую архитектуру (ветка arch-modular-migration)

## Оценка

**74/100.** Структурные и формальные требования переезда выполнены аккуратно и честно задокументированы: deptrac даёт 0 нарушений по невыведенной из-под проверки области, PHPStan level max чист без новых немотивированных подавлений, миграции переносились без правки применённых файлов, а журналы волн прозрачно фиксируют компромиссы. Основная находка — не формальное нарушение, а содержательная: снятие внешнего ключа `post_media.media_id -> media.id` убрало единственную существовавшую гарантию согласованности «медиа, вложенное в публикацию, не может исчезнуть из-под неё», и взамен не появилось ничего — ни синхронной проверки, ни события, как это требует сам `docs/arch.md` («Согласованность обеспечивает синхронный публичный контракт… либо интеграционное событие с идемпотентным потребителем»). Решение принято и подтверждено тестом на уровне журнала волны C, но не поднято в раздел «Осознанные отступления» `docs/arch.md`, поэтому выглядит как формально исчезнувшая, а не осознанно принятая гарантия.

## Покрытие

- **Execution:** main
- **Completed:** correctness, architecture, rules, business, code_quality, tests, security, performance, api, database, documentation (модель claude-sonnet-5 везде)
- **Skipped:** references (цель — вся ветка, отбор карточек не сузить без потери полноты; эквивалент проверен через architecture), plan_alignment (нет единого файла плана на всю ветку — 9 волновых планов), frontend (не применимо, backend-only), previous_reviews (не применимо, не PR/MR)
- **Warnings:** нет
- **Previous reviews:** не применимо

## Проблемы сверки с планом

Проверка плана пропущена: единого плана переезда нет, есть девять отдельных волновых планов (`docs/artifacts/plans/2026-09-15_17-41_volna-a-strukturnyj-pereezd.md` … `2026-09-17_15-00_volna-i-imena-derevo-instrumenty-granicy-status.md`), однозначная цель для сверки не определяется без дробления ревью по волнам, что не запрашивалось.

## Проблемы бизнес-логики

Прямого нарушения бизнес-карточки не найдено: `docs/business/posts.md` описывает прикрепление и показ медиафайлов публикации, но не описывает эффект удаления медиафайла из личной библиотеки после его прикрепления к опубликованной записи — сценарий просто не покрыт картой ни в одну, ни в другую сторону. Найденная проблема (см. «Проблемы в коде», пункт 1) — это архитектурный пробел согласованности, а не рассинхронизация с зафиксированным бизнес-правилом, поэтому основная находка оставлена в разделе кода.

## Проблемы в коде

### 1. Удаление медиафайла не защищено от использования в публикации — заявленная в docs/arch.md гарантия согласованности не реализована

Волна C сняла внешний ключ `post_media.media_id -> media.id`, который раньше был `ON DELETE RESTRICT`: до переезда попытка удалить медиафайл, вложенный в публикацию, отклонялась базой данных. `docs/arch.md` в разделе «Владение данными» формулирует это как общее правило: межмодульные внешние ключи не нужны как основа согласованности, но согласованность обязана обеспечиваться чем-то другим — «синхронным публичным контрактом в общей локальной транзакции либо интеграционным событием с идемпотентным потребителем». Для связи «удаление медиа -> публикация, в которую оно вложено» такого механизма нет вообще: `DeleteMediaHandler` проверяет только владельца (`uploadedById === userId`) и статус `WaitingUpload` для отмены multipart-загрузки, но никак не проверяет и не узнаёт, использует ли медиа хоть одна публикация, и не отправляет никакого события модулю Posts.

Практическое следствие: любой пользователь, опубликовавший пост с картинкой (или установивший медиа как что-то ещё, что ссылается на media_id), может затем зайти в свою медиатеку и удалить тот же файл через `DeleteMediaCommand` — публикация при этом молча теряет изображение без ошибок, предупреждений и следов в логах. Строка `post_media` остаётся висеть, указывая на несуществующий `media_id`; при чтении поста `FindMediaUrlsHandler`/`MediaContract->urlsByIds()` по архитектуре «best-effort» просто пропускает недоступное медиа, так что автор и читатели не узнают, что случилось. Ровно этот эффект осознанно закреплён новым тестом волны C: `testDeletingReferencedMediaKeepsAttachmentRow` прямо удаляет медиа, привязанное к посту, и утверждает, что строка вложения осталась с исходным (уже несуществующим) `media_id` — то есть поведение задокументировано в тесте, но не поднято на уровень архитектурного документа.

Технически сегодня `DeleteMediaCommand` не вызывается ни из одного HTTP-контроллера, консольной команды или Job — у модуля Media вообще нет `Infrastructure/Spiral/Http`, так что путь пока недостижим внешним пользователем. Это снижает немедленный риск, но не снимает проблему: обработчик — рабочий, протестированный публичный сценарий модуля, и он будет использован, как только появится маршрут удаления медиа (что для медиатеки — ожидаемая и вероятная следующая возможность). На момент ревью гарантия, которую `docs/arch.md` объявляет обязательной после снятия FK, просто отсутствует для этой связи, и раздел «Осознанные отступления» об этом не говорит: там перечислены семь других сознательных компромиссов (UserId в Shared, невключённый Access, историческая миграция Posts/tags, пары add()/save(), DomainTranslatableException, четыре неиспользуемых исключения, три подавления PHPStan MediaMapper), но не этот.

Для сравнения: симметричный случай (`users.avatar_media_id -> media.id`) обработан правильно — миграция и код `GetUserPublicProfileHandler::resolveAvatars()` явно рассчитаны на «переживание» отсутствующего media_id, поведение осознанное и безопасное (просто нет аватара). Для `post_media` эквивалентной защиты или обоснования отсутствия защиты на уровне архитектурного документа нет.

#### Технические детали

- **Тип:** architecture
- **Тяжесть:** high
- **Файлы:**
  - `app/src/Modules/Media/Application/Command/DeleteMedia/DeleteMediaHandler.php:27-45` — удаление без проверки использования медиа другими модулями
  - `app/src/Modules/Posts/Infrastructure/Persistence/Cycle/Migration/20260915.234500_0_drop_post_media_media_foreign_key.php:1-38` — снятие `ON DELETE RESTRICT` без замены другим механизмом согласованности
  - `app/src/Modules/Posts/Tests/Integration/Cycle/PostsRepositoryTest.php:438-459` (`testDeletingReferencedMediaKeepsAttachmentRow`) — тест фиксирует осиротевшую строку `post_media` как ожидаемый результат
  - `app/src/Modules/Media/Application/Query/FindMediaUrls/FindMediaUrlsHandler.php:1-53` и `docs/arch.md:230-236` («Владение данными») — заявленная, но не выполненная для этой связи гарантия
- **Что подтверждает проблему:** `DeleteMediaHandler::handle()` не содержит обращения к `Public` Posts и не публикует событие; `MediaCannotBeMadePermanentException`/список исключений Media (`app/src/Modules/Media/Domain/Exception/`) не содержит исключения вида «медиа используется»; журнал волны C (`docs/artifacts/executions/2026-09-15_23-20_volna-c-dostup-i-ssylki-bez-navigacii.md:70`) прямо признаёт замену теста на новое поведение как «отклонение исполнителя (принято)», не поднятое в `docs/arch.md`.
- **Чем воспроизводится:** интеграционный тест `testDeletingReferencedMediaKeepsAttachmentRow` (уже в репозитории) — вызывает удаление медиа, вложенного в пост, и подтверждает, что вложение осталось указывать на несуществующий `media_id`.
- **Как исправить:** выбрать и реализовать один из двух путей, которые `docs/arch.md` считает легитимными: (а) синхронная проверка использования — `DeleteMediaHandler` дочитывает через `Posts/Public` (или через новый контракт «кто ссылается на это медиа», если Posts не должен знать о Media заранее) и отклоняет удаление, пока есть активные ссылки; либо (б) асинхронная — `DeleteMedia` публикует интеграционное событие `MediaDeletedEvent`, на которое Posts подписывается идемпотентным потребителем и снимает/заменяет вложение. Выбранное решение задокументировать либо как реализованную гарантию в «Владение данными», либо — если решено оставить как есть осознанно — как новый пункт «Осознанные отступления» с явным обоснованием, а не оставлять только в журнале волны и тесте.
- **Тесты:** для варианта (а) — тест на отказ `DeleteMediaHandler`, когда медиа привязано к публикации; для варианта (б) — тест идемпотентного потребителя события в Posts, включая повторную доставку.

## Отклонено

- **`PostMapper::toDomain()` всегда возвращает пустые `media`/`tags` у домена `Post`** (`app/src/Modules/Posts/Infrastructure/Persistence/Cycle/Mapper/PostMapper.php:70-71`) — проверено проверкой correctness: grep по `$post->media`/`$post->tags` показывает, что домен `Post` эти поля нигде за пределами конструктора `Entity/Post.php` не читает, `PostResource` работает с `Application\Result\PostResult`, а не с доменной сущностью. Утверждение докблока подтвердилось, находкой не является.
- **Пары `add()`/`save()` в `RequestLoginCodeHandler`, `ResolveLoginCodeHandler`, `CompleteRegistrationHandler`, `StoreIntegrationEventHandler`, `DeleteCommentHandler::mirrorCounter()`** — проверкой architecture: паттерн опирается на общий request-scoped `EntityManager`, явно описан докблоками на месте и совпадает с зафиксированным в `docs/arch.md` отступлением «Пара add()/save() в пяти доменных Repository»; во всех проверенных местах `add()` рано или поздно сопровождается `save()`/`saveAll()` в пределах того же прогона. Находкой не является.
- **Снятие FK `users.avatar_media_id -> media.id`** — проверкой architecture: в отличие от `post_media`, здесь согласованность действительно обеспечена — `GetUserPublicProfileHandler::resolveAvatars()` через `MediaContract->urlsByIds()` штатно переживает отсутствующий идентификатор, и это явно описано в докблоке миграции. Находкой не является.
- **Добавление `excludePaths: app/src/Modules/*/Tests/*` в `phpstan.neon`** — проверено на признак ослабления проверки ради зелёного `make qa`: область анализа не расширялась и не сужалась относительно того, что проект проверял до переезда (`paths` и раньше не включал каталог тестов), решение совпадает с текстом `docs/arch.md`, раздел «Проверка границ» → «Чего проверка не покрывает». Ослаблением не является.

## Применённые фиксы
Отчёт: `docs/artifacts/review-fixes/2026-09-17_14-07_arch-modular-migration-branch.md`
