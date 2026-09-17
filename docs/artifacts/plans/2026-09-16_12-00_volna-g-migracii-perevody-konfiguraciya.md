---
title: Волна G — миграции, переводы и конфигурация внутри модулей
date: 2026-09-16 12:00
mode: normal
plan_size: normal
decision_mode: autonomous
status: draft
reviewer: none
plan_review: none
plan_review_fix: none
test_strategy: after_each_phase
logging_strategy: debug_precise
sources:
  rules: docs/rules.md
  arch: docs/arch.md
  business: []
  references:
    - docs/references/migration.md
    - docs/references/bootloader.md
    - docs/references/typed-config.md
    - docs/references/entity-columns.md
  research: docs/artifacts/researches/2026-09-15_17-25_karta-rashozhdenij-s-celevoj-arhitekturoj.md
---

# План реализации

## Задача

Волна G переезда на целевую архитектуру закрывает задачи roadmap 20, 21 и 23: миграции, пользовательские переводы и типизированная конфигурация модулей физически переезжают из общих папок (`app/database/migrations`, `app/locale`, `app/config`) внутрь модулей-владельцев и подключаются их bootloader-ами. Задача 24 (тесты внутрь модулей) и задача 25 (переименование классов) в волну не входят.

Готово, когда: каталога `app/database/migrations` больше нет; в `app/locale` и `app/config` остались только элементы без владельца среди модулей, для каждого зафиксирована причина; 13 межмодульных внешних ключей сняты новыми миграциями владельцев затронутых таблиц; дублирующая регистрация пары «outbox-сообщение → Job» схлопнута до одного источника истины; `make qa` зелёный (кроме известного до-волнового падения `S3MediaFileServiceTest::testCopyObjectToPublicBucketIsAnonymouslyReadable`); PHPStan level max чист; `route:list` — 33 маршрута; md5 сгенерированной OpenAPI — `fd8d4a16c3994dddcfbf915caa85157b`; миграции фактически проверены на временных базах (создание с нуля, применение на базе с данными, откат/повтор, `pg_dump --schema-only`, отсутствие повторного применения на уже мигрированной базе); ответы на ru/en проверены живыми запросами.

Схема базы не меняется, кроме явного снятия 13 межмодульных внешних ключей (имена таблиц, колонок, типы, индексы — прежние). Внешнее поведение (маршруты, формы запросов/ответов, тексты ошибок, переводы) не меняется.

## Целевой алгоритм

Механизм не меняет наблюдаемое поведение приложения — меняется только физическое расположение файлов и точка их регистрации. Единственное наблюдаемое отличие — отсутствие 13 внешних ключей в схеме БД, при этом ссылочная целостность на момент чтения обеспечивается уже существующим способом (публичный контракт соседа), а не новым кодом.

| Механизм | Было | Стало | Кто регистрирует |
|---|---|---|---|
| Миграции | Один каталог `app/database/migrations`, `MigrationConfig.directory` | Каждая миграция в `Infrastructure/Persistence/Cycle/Migration` модуля-владельца её таблиц | `vendorDirectories` пополняет bootloader модуля (`Spiral\Config\Patch\Append`, см. `docs/references/migration.md`) — механизм уже встроен в `cycle/migrations` (`Cycle\Migrations\FileRepository`), ничего своего строить не нужно |
| Переводы | Один каталог `app/locale`, `TranslatorConfig.directory` | Файл перевода модуля в `Infrastructure/Spiral/Resources/locale/{ru,en}/{domain}.php` | `Spiral\Bootloader\I18nBootloader::addDirectory()` — публичный метод фреймворка, уже существует, ничего своего строить не нужно |
| Конфигурация | `ConfigBootloader` сканирует один каталог `Shared/Infrastructure/Spiral/Configuration` через `Symfony\Finder` внутри `defineSingletons()` | `ConfigBootloader` сканирует Shared-каталог и реестр директорий, которые модули добавляют в фазе `init()`; связывание типизированных конфигов с контейнером переносится в фазу `boot()` | Bootloader модуля передаёт свою директорию `Infrastructure/Spiral/Configuration` в реестр в своём `init()` |

Обоснование переноса связывания конфигов в `boot()`: `Spiral\Boot\BootloadManager\DefaultInvokerStrategy::invokeBootloaders()` сначала вызывает `defineSingletons()`/`defineBindings()` и `init()` у **всех** bootloader-ов приложения (в порядке `Kernel::defineBootloaders()`), и только после этого — `boot()` у всех. Если карту типизированных конфигов вычислять в `defineSingletons()` (как сейчас), она считается раньше, чем модульные bootloader-ы (которые в `Kernel` идут позже `ConfigBootloader`) успеют зарегистрировать свою директорию в `init()`, и модульные конфиги не попадут в контейнер. Перенос вычисления карты в `boot()` убирает эту гонку без изменения порядка bootloader-ов в `Kernel`, потому что `init()`-фаза всех bootloader-ов гарантированно завершается раньше `boot()`-фазы любого из них.

Для секции конфигурации, чей файл-массив физически переезжает в модуль (а не читается общим `Spiral\Config\Loader\DirectoryLoader` из `app/config`), модуль передаёт свой массив в `ConfiguratorInterface::setDefaults(section, data)` — тот же метод, которым уже пользуется `I18nBootloader::init()` и `Spiral\Storage\Bootloader\StorageBootloader::init()` для своих defaults. `setDefaults()` бросает исключение, только если секция уже прочитана (`ConfigManager::getConfig()`), а типизированные конфиги читаются лениво (в момент, когда что-то запрашивает объект из контейнера, то есть на реальном запросе, а не во время bootload) — поэтому и это не зависит от порядка bootloader-ов.

## Контракты реализации

### Данные и БД

**Перенос девяти существующих файлов (10 физических файлов, один уже добавлен волной F):**

| Файл (имя не меняется) | Таблицы | Новый модуль-владелец |
|---|---|---|
| `20260521.184100_0_create_media_domain_tables.php` | media, media_image_conversions, media_video_conversions, media_multipart_uploads | Media |
| `20260525.153700_0_create_outbox_events_table.php` | outbox_events | Outbox |
| `20260613.130000_0_create_notification_domain_tables.php` | notifications, notification_settings, notification_device_tokens | Notifications |
| `20260613.143901_0_create_user_domain_tables.php` | users, user_bans, reserved_nicknames | User |
| `20260613.143902_0_create_access_domain_tables.php` | roles, permissions, role_permissions, user_roles | Access |
| `20260615.141700_0_create_auth_domain_tables.php` | auth_tokens, auth_login_codes, auth_registration_tickets | Auth |
| `20260616.180010_0_add_device_to_auth_tokens.php` | auth_tokens | Auth |
| `20260617.160942_0_create_posts_domain_tables.php` | posts, comments, post_likes, comment_likes, post_mentions, comment_mentions, post_media, post_tags, post_blocks, **tags** | **Tags** (см. ниже) |
| `20260620.222100_0_create_media_audio_conversions_table.php` | media_audio_conversions | Media |
| `20260915.234500_0_drop_post_media_media_foreign_key.php` | post_media (снимает FK на media) | Posts |

Принятое решение: `20260617.160942_0_create_posts_domain_tables.php` создаёт 9 таблиц Posts и 1 таблицу `tags`. Файл уже применён, редактировать и разрезать его нельзя (`docs/rules.md`: «не редактируй уже применённую миграцию»). Задача явно требует отдать создание `tags` модулю Tags — единственный способ сделать это без правки содержимого — перенести файл целиком в `Tags/Infrastructure/Persistence/Cycle/Migration` (владелец таблицы, которую задача требует отдать), не разбивая его. Это фиксируется как документированное историческое исключение из правила «миграция меняет только свои таблицы»: комментарий в шапке файла (без изменения остальной части файла — комментарий тоже часть уже применённого содержимого, поэтому вместо правки самого файла исключение фиксируется в bootloader-е Tags рядом с регистрацией пути и в журнале волны) объясняет, что файл исторически мигрировал целиком из-за запрета редактировать применённые миграции.

Каждый файл переносится **без изменения содержимого** (namespace `Migration`, имя класса, тело `up()`/`down()` — как есть). Cycle сортирует миграции по timestamp, разобранному из имени файла (`Cycle\Migrations\FileRepository::getFiles()`/`getMigrations()`), а не по каталогу — перенос в другой каталог не меняет порядок применения и не меняет то, что считается «применённой» миграцией (таблица `migrations` хранит `name`/`created`, извлечённые из имени файла, а не путь).

`app/config/migration.php`: ключ `'directory'` перестаёт указывать на `app/database/migrations/` (каталога больше нет) и указывает на несуществующий путь без единого файла в нём, например `\directory('runtime') . 'migrations/'` — `Cycle\Migrations\FileRepository` при отсутствии каталога просто не находит файлов (`Spiral\Files\Files::getFiles()` использует `GlobIterator`, который на несуществующем пути не бросает исключение и не сканирует текущую директорию). Значение `env()`/остальные ключи (`table`, `safe`) не меняются.

**13 межмодульных внешних ключей, которые снимаются новыми миграциями (согласованное изменение схемы, фиксируется явно):**

| # | Колонка с FK | Ссылается на | Правило удаления | Модуль-владелец колонки (миграция) |
|---|---|---|---|---|
| 1 | `users.avatar_media_id` | `media.id` | RESTRICT | User |
| 2 | `user_roles.user_id` | `users.id` | CASCADE | Access |
| 3 | `tags.created_by_id` | `users.id` | RESTRICT | Tags |
| 4 | `post_tags.tag_id` | `tags.id` | RESTRICT | Posts |
| 5 | `posts.user_id` | `users.id` | RESTRICT | Posts |
| 6 | `post_likes.user_id` | `users.id` | CASCADE | Posts |
| 7 | `post_mentions.user_id` | `users.id` | CASCADE | Posts |
| 8 | `post_blocks.blocked_by_id` | `users.id` | RESTRICT | Posts |
| 9 | `post_blocks.unblocked_by_id` | `users.id` | SET NULL | Posts |
| 10 | `comments.user_id` | `users.id` | RESTRICT | Posts |
| 11 | `comments.deleted_by_id` | `users.id` | SET NULL | Posts |
| 12 | `comment_likes.user_id` | `users.id` | CASCADE | Posts |
| 13 | `comment_mentions.user_id` | `users.id` | CASCADE | Posts |

Это ровно 13 ключей из задачи (ключ `post_media.media_id -> media.id` уже снят миграцией `20260915.234500`, волной F, и в счёт не входит). Каждая из 4 затронутых модулей-владельцев (User, Access, Tags, Posts) получает одну новую миграцию, которая снимает **все свои** внешние ключи из таблицы выше `dropForeignKey()`-вызовами по одной на колонку — несколько FK одного модуля можно снимать в одном файле по образцу «несколько таблиц одного модуля — одна миграция» (`docs/references/migration.md`, «Допустимые варианты»). Посл файла: Posts — 10 снятий (строки 4–13), User — 1 (строка 1), Access — 1 (строка 2), Tags — 1 (строка 3). Файлы следуют образцу уже применённой `20260915.234500_0_drop_post_media_media_foreign_key.php` (docblock с обоснованием, `up()` только `dropForeignKey()`, `down()` восстанавливает прежние правила). Новые файлы используют namespace модуля из `docs/references/migration.md` (`App\Modules\{Module}\Infrastructure\Persistence\Cycle\Migration`), а не общий `Migration` — они новые и следуют текущей карточке. Timestamp новых файлов — позже `20260915.234500` (например `20260916.HHMMSS`), формат имени как у существующих.

Обоснование, что снятие не ломает сценарии удаления/каскада (принятое решение, autonomous): в кодовой базе нет ни одного сценария, который жёстко удаляет строку `users` или `tags` (`UserRepository`/`ReservedNicknameRepository`/`UserBanRepository` не имеют метода `delete`, в `Application/Command` модуля User есть только `CreateUser`; в модуле Tags нет ни одной команды удаления — только `ResolveTags`) — то есть правила RESTRICT/CASCADE/SET NULL этих 13 ключей на практике никогда не срабатывали. Единственный реально работающий сценарий жёсткого удаления — `Media\DeleteMediaHandler` (`$mediaRepository->delete($media)`), но он не участвует ни в одном из 13 ключей: ключ `users.avatar_media_id -> media.id`, который эту ситуацию мог бы касаться, уже сегодня без страховки на уровне сценария — чтение аватара (`GetUserPublicProfileHandler::resolveAvatars()`) идёт через `MediaContract->urlsByIds()`, который переживает отсутствующий идентификатор (тот же приём уже принят волной F для `post_media.media_id`). Поэтому снятие ключей не требует нового кода сценариев — только миграции и тесты схемы.

### API и внешние контракты

Не затрагивается: маршруты, тела запросов/ответов, коды ошибок и переводы прежние. Единственная проверяемая величина — неизменный md5 сгенерированной OpenAPI и прежние 33 маршрута (раздел «Приёмка»).

## Фазы выполнения

### 1. Механизм миграций модулей и перенос десяти файлов

Цель: каждая существующая миграция лежит у модуля-владельца её таблиц, порядок применения и содержимое файлов не меняются.

Что сделать: восемь модулей (Access, Auth, Media, Notifications, Outbox, Posts, Tags, User; у System таблиц нет) получают каталог `Infrastructure/Persistence/Cycle/Migration` и регистрируют его в своём bootloader через `Append` в `vendorDirectories` секции `migration`, по образцу `docs/references/migration.md`. Десять файлов из таблицы раздела «Данные и БД» переезжают в свои каталоги без изменения содержимого; `app/database/migrations` и его `.gitignore` удаляются. `app/config/migration.php` получает новое значение `directory` (несуществующий путь без файлов), остальные ключи не меняются. Обнови три существующих теста, которые проверяют старый путь: `tests/Feature/Modules/Posts/Migration/DropPostMediaMediaForeignKeyMigrationTest.php` (константа пути к файлу — теперь путь внутри Posts), `tests/Kernel/Shared/Infrastructure/Spiral/Configuration/SimpleConfigBindingTest.php` и `tests/Unit/Shared/Infrastructure/Spiral/Configuration/SimpleConfigMapperTest.php` (ожидание значения `directory`).

Результат: `app/database/migrations` не существует; в каждом из 8 модулей есть заполненный `Infrastructure/Persistence/Cycle/Migration`; `MigrationConfig::CONFIG` собирает все 10 файлов через `vendorDirectories`.

Проверка:
- чтением кода: каждый файл лежит у модуля из таблицы владения, класс/namespace/тело не изменились (`git diff` по каждому перенесённому файлу показывает только путь, не содержимое);
- чтением кода: bootloader каждого из 8 модулей регистрирует свой путь миграций по образцу `docs/references/migration.md`;
- чтением кода: три указанных теста ссылаются на новые пути/значения.

### 2. Снятие 13 межмодульных внешних ключей

Цель: схема базы перестаёт полагаться на межмодульные внешние ключи (`docs/arch.md`, «Владение данными»), при этом ссылочная целостность на чтении не меняется.

Что сделать: в модулях User, Access, Tags и Posts появляется по одной новой миграции (таблица «13 межмодульных внешних ключей» раздела «Данные и БД»), каждая снимает FK только со своих таблиц через `dropForeignKey()`, `down()` восстанавливает прежние правила `delete`/`update`. Каждая новая миграция получает feature-тест по образцу `tests/Feature/Modules/Posts/Migration/DropPostMediaMediaForeignKeyMigrationTest.php`: проверяет схему после применения (FK отсутствует, соседние FK/индексы целы), откат (`down()` восстанавливает FK с прежними правилами) и повторное применение.

Результат: в схеме БД нет ни одного из 13 перечисленных внешних ключей; остальные внешние ключи, индексы и колонки — без изменений.

Проверка:
- чтением кода: каждая новая миграция трогает только таблицы своего модуля-владельца и снимает ровно свои строки из таблицы 13 ключей;
- чтением кода: `down()` каждой миграции восстанавливает исходные `delete`/`update` правила (значения из текущих файлов создания таблиц);
- чтением кода: новый тест на каждую миграцию покрывает up/down/повторное применение по образцу существующего.

### 3. Переводы модулей

Цель: пользовательские сообщения модуля лежат внутри модуля, в `app/locale` остаются только тексты без владельца.

Что сделать: шесть файлов (`auth.php` → Auth, `media.php` → Media, `notifications.php` → Notifications, `posts.php` → Posts, `system.php` → System, `user.php` → User; для `ru` и `en`) переезжают в `Infrastructure/Spiral/Resources/locale/{ru,en}/{domain}.php` своих модулей без изменения содержимого (ключи, тексты, язык — прежние). Bootloader каждого из шести модулей получает зависимость от `Spiral\Bootloader\I18nBootloader` и вызывает его `addDirectory()` со своим каталогом `Resources/locale` в `init()`. `shared.php` остаётся в `app/locale`: он не принадлежит ни одному модулю (перевод `app.shared.rate_limit_exceeded` использует общий `RateLimitMiddleware`, который по принятому решению карты расхождений остаётся в `Shared` — closed-decision №3 карты).

Результат: `app/locale` содержит только `shared.php` (ru/en); каждый из шести модулей содержит свои переводы и подключает их в своём bootloader.

Проверка:
- чтением кода: `app/locale/{ru,en}` содержит только `shared.php`;
- чтением кода: каждый из шести файлов лежит у модуля из карты владения без изменения содержимого;
- чтением кода: bootloader каждого модуля вызывает `I18nBootloader::addDirectory()` со своим каталогом.

### 4. Реестр директорий конфигурации и перенос шести полностью-владеемых секций

Цель: `ConfigBootloader` перестаёт жёстко сканировать один каталог; media, outbox, push, centrifugo, mailer и openapi переезжают к своим модулям.

Что сделать: `ConfigBootloader` получает реестр директорий (Shared-каталог остаётся зарегистрирован по умолчанию) и метод, которым модуль добавляет свою директорию `Infrastructure/Spiral/Configuration` в фазе `init()`; сканирование каталогов и связывание найденных `TypedConfig`-классов с контейнером переносится из `defineSingletons()` в `boot()` — обоснование см. в «Целевом алгоритме». Шесть секций переезжают целиком, каждая — файл-массив (`{name}.php`, `env()`-вызовы без изменений) и типизированный класс `{Name}Config`, реализующий `TypedConfig`, — в `Infrastructure/Spiral/Configuration` своего модуля: `media.php`/`MediaConfig` → Media, `outbox.php`/`OutboxConfig` → Outbox, `push.php`/`PushConfig` и `centrifugo.php`/`CentrifugoConfig` → Notifications, `mailer.php`/`MailerConfig` → Auth, `openapi.php`/`OpenApiConfig` → System. Каждый модуль передаёт свой массив в `ConfiguratorInterface::setDefaults(section, data)` в `init()` (по образцу `I18nBootloader::init()`/`StorageBootloader::init()`) — файл больше не читается общим `Spiral\Config\Loader\DirectoryLoader` из `app/config`, а `env()` по-прежнему вызывается только в этом файле. Импорты потребителей (классы, которые сейчас читают эти шесть `Shared\...\Configuration\*` классов) обновляются на новое пространство имён. Тесты преобразования шести конфигов переезжают вместе с классами по import-путям (сами тестовые файлы остаются в `tests/Kernel/...` — перенос тестов в модули вне этой волны, меняются только импортируемые FQCN).

Результат: `app/config` не содержит `media.php`, `outbox.php`, `push.php`, `centrifugo.php`, `mailer.php`, `openapi.php`; шесть типизированных конфигов резолвятся из контейнера по прежним значениям (env/defaults не изменились).

Проверка:
- чтением кода: `ConfigBootloader` не хранит захардкоженный единственный каталог как единственный источник — есть реестр, пополняемый в `init()`, и связывание в `boot()`;
- чтением кода: шесть файлов-массивов и шесть классов лежат в модулях-владельцах, `env()` встречается только в файле-массиве;
- чтением кода: каждый модуль вызывает `setDefaults()` со своим массивом;
- чтением кода: потребители шести конфигов используют новые FQCN, тесты преобразования ссылаются на новые классы.

### 5. Разделение storage и единый источник истины outbox-сообщение → Job

Цель: бакеты Media отделяются от общего `storage`, а пара «outbox-событие → Job» перестаёт дублироваться в `app/config/queue.php`.

Что сделать: `app/config/storage.php` теряет ключи `buckets.media-upload`, `buckets.media-private`, `buckets.media-public` (общими остаются `default`, `servers.local`, `servers.s3`, `buckets.default`, `buckets.s3`, `buckets.s3-test` — `local`-сервер и `default`-бакет использует и тестовое окружение). Media получает файл-массив с этими тремя бакетами и добавляет их в секцию `storage` через `ConfiguratorInterface::modify()` с `Append` в `init()` MediaBootloader — по образцу `Cycle\Migrations`/`Translator`: `Spiral\Storage\Bootloader\StorageBootloader::init()` читает `StorageConfig` лениво через параметр замыкания, привязанного к `StorageInterface`, поэтому добавление бакетов не зависит от порядка bootloader-ов в `Kernel`. Общий `Shared\...\Configuration\Storage\StorageConfig` теряет поля трёх media-бакетов; Media получает свой типизированный `MediaStorageConfig` с этими тремя полями (потребитель — `S3MediaFileService`, импорт обновляется). Дублирующая регистрация Job: сейчас каждый производящий событие модуль (Outbox, Media, Auth, Notifications) уже вызывает `IntegrationEventRoutingContract->register(eventClass, jobClass)` в своём `boot()` — единственное необходимое место регистрации. `OutboxJobRegistry::register()` дополнительно патчит секцию `queue` (`Spiral\Queue\Config\QueueConfig` — тоже ленивый `InjectableConfig`) через `Append` в `registry.handlers[$jobClass] = $jobClass` и `registry.serializers[$jobClass] = OutboxQueueSerializer::class`, так что `app/config/queue.php` больше не перечисляет вручную шесть Job-классов в `registry.handlers`/`registry.serializers` — они добавляются автоматически при регистрации события. Статичный список из `queue.php` удаляется, остальные ключи (`default`, `connections`, `pipelines`, `interceptors`, `driverAliases`) не меняются.

Результат: `app/config/storage.php` не содержит media-бакетов; `MediaStorageConfig` резолвится из Media со старыми значениями; `app/config/queue.php` не перечисляет Job-классы вручную; доставка задач (handler + сериализатор) для всех шести существующих outbox-Job работает как раньше — единственный источник истины — вызов `IntegrationEventRoutingContract->register()`.

Проверка:
- чтением кода: `storage.php` не содержит трёх media-ключей, Media регистрирует их через `Append` в `init()`;
- чтением кода: `MediaStorageConfig` в Media, `StorageConfig` в Shared без media-полей, `S3MediaFileService` использует новый класс;
- чтением кода: `OutboxJobRegistry::register()` патчит секцию `queue`, `queue.php` не содержит статичного списка Job-классов, поведение сериализации (`OutboxQueueSerializer`) для каждого зарегистрированного Job сохранено.

### 6. Приёмка волны G

Цель: подтвердить, что волна не изменила наблюдаемое поведение, и провести обязательную фактическую проверку миграций, которую нельзя закрыть чтением кода.

Что сделать: прогони `make qa` в Docker; при красных находках исправляй точечно и повторяй `make qa` — заложи несколько циклов (опыт волн E и F). Затем в Docker (`make shell` или прямой `docker compose run`) фактически проверь миграции: (1) применение на чистой временной базе с нуля (`php app.php migrate --force`), сравнение `pg_dump --schema-only` результата с ожидаемой схемой (те же таблицы/колонки/индексы/внешние ключи, кроме 13 снятых); (2) применение на копии базы с данными; (3) откат последних применённых миграций (`migrate:rollback`) и повторное применение — без ошибок, финальная схема совпадает с (1); (4) на базе, где все миграции уже были применены **до** переезда (снимок до начала волны или уже работающая dev-база), убедиться, что `php app.php migrate --force` не применяет ни одной миграции повторно и не считает перенесённые файлы новыми (таблица `migrations` матчит имена/классы как раньше). Затем сверь `route:list` (33 маршрута) и md5 сгенерированной OpenAPI (`fd8d4a16c3994dddcfbf915caa85157b`); живыми HTTP-запросами (с заголовком `Accept-Language`/локалью) проверь по одному ru- и en-ответу для каждого из шести перенесённых доменов переводов (auth, media, notifications, posts, system, user) — тексты совпадают с текущими файлами.

Результат: `make qa` зелёный (кроме заранее известного `S3MediaFileServiceTest::testCopyObjectToPublicBucketIsAnonymouslyReadable`); миграции фактически подтверждены на временных базах; маршруты и OpenAPI не изменились; переводы не изменились.

Проверка:
- `make qa` — 0 ошибок PHPStan, 0 замечаний php-cs-fixer, 100% покрытие, все тесты зелёные кроме известного падения;
- протокол фактической проверки миграций (4 пункта выше) с зафиксированным результатом каждого шага;
- `route:list` — 33 маршрута, md5 OpenAPI совпадает;
- зафиксированные примеры ru/en-ответов по каждому из 6 доменов переводов.

## Тесты

`test_strategy: after_each_phase` — каждая фаза добавляет/обновляет тесты сразу при реализации, не откладывая на конец плана:

- Фаза 1: обновляются три существующих теста, ссылающихся на старый путь миграций (пути/значения, не логика).
- Фаза 2: на каждую из 4 новых миграций — feature-тест по образцу `DropPostMediaMediaForeignKeyMigrationTest` (up снимает только целевые FK, down восстанавливает прежние правила, повторное up после down не оставляет ключ).
- Фаза 3: перенос без изменения содержимого — новых тестов не требуется; существующие интеграционные/feature-тесты, зависящие от переводов (сообщения об ошибках на ru/en), должны остаться зелёными без правок ожиданий.
- Фаза 4: существующие тесты преобразования шести конфигов (`*ConfigTest.php` в `tests/Kernel/.../Configuration`) обновляют импортируемые FQCN; логика тестов и ожидаемые значения не меняются.
- Фаза 5: `MediaStorageConfigTest`/`StorageConfig`-тесты обновляются под разделённые классы; тест дублирующей регистрации Job (если такого нет — не создаётся отдельно, поведение покрыто существующими feature-тестами доставки outbox-событий, которые не должны измениться).
- Фаза 6: не добавляет тестов — только запуск `make qa` и ручную/фактическую проверку по протоколу.

Ни одна фаза не запускает `make qa`/`make test`/`make phpstan`/`make test-unit` — только фаза 6.

## Логирование

Волна не добавляет нового бизнес-поведения и не меняет пользовательские сценарии — новых точек логирования не требуется. Существующие `debug`-записи (например, в `DeleteMediaHandler`) не меняются. Если реализация фазы 4/5 логирует регистрацию конфигурации/директорий/Job при bootload — это техническая инициализация, а не бизнес-событие, отдельного лога не заводится (соответствует «не логируем то, что не несёт диагностической ценности при инциденте»).

## Документация и эксплуатация

- `docs/arch.md` уже фиксирует целевое расположение миграций/переводов/конфигов — правок документа не требуется.
- Журнал волны фиксирует все 13 снятых внешних ключей поимённо и перечень оставшихся элементов `app/locale`/`app/config` с причиной.
- `.env`/`.env.example` не меняются.

## Принятые решения

1. Файл `20260617.160942_0_create_posts_domain_tables.php` переезжает целиком в Tags (не разрезается) — единственный способ отдать создание `tags` модулю Tags без правки уже применённой миграции. Autonomous, источник — прямое требование задачи и правило «не редактируй применённую миграцию» (`docs/rules.md`).
2. `migration.php`: `directory` указывает на несуществующий путь без файлов вместо `app/database/migrations` — обоснование в «Данные и БД». Autonomous.
3. Связывание типизированных конфигов переносится из `defineSingletons()` в `boot()` — обоснование в «Целевом алгоритме». Autonomous.
4. Снятие 13 FK не требует нового защитного кода в сценариях — обоснование в «Данные и БД» (grep: нет жёсткого удаления `users`/`tags`; прецедент Media уже устойчив к отсутствию). Autonomous.
5. `shared.php` остаётся в `app/locale` — уже зафиксированное решение карты расхождений (closed-decision №3): `RateLimitMiddleware` общий и без домена. Не пересматривается в этой волне.
6. Дублирующая регистрация outbox-Job схлопывается в сторону существующего `IntegrationEventRoutingContract->register()` — обоснование в фазе 5. Autonomous, сохраняет ровно текущее поведение доставки.

## Прогресс выполнения

Журнал: `docs/artifacts/executions/2026-09-16_20-52_volna-g-migracii-perevody-konfiguraciya.md`

| Фаза | Статус |
|---|---|
| 1. Механизм миграций модулей и перенос десяти файлов | done |
| 2. Снятие 13 межмодульных внешних ключей | done |
| 3. Переводы модулей | done |
| 4. Реестр директорий конфигурации и перенос шести секций | done |
| 5. Разделение storage и единый источник истины outbox-Job | done |
| 6. Приёмка волны G | pending |
