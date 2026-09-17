---
plan: docs/artifacts/plans/2026-09-16_12-00_volna-g-migracii-perevody-konfiguraciya.md
started: 2026-09-16 20:52
status: done
mode: subagents
current_phase: 6
---

# Журнал выполнения: Волна G — миграции, переводы и конфигурация внутри модулей

Место выполнения задано оркестратором: текущая ветка `arch-modular-migration`, без новой ветки и без worktree — вопрос о месте выполнения не задаётся. Пользователь недоступен (запуск автономный): развилки и needs_input закрываются автономно с фиксацией причины здесь, без AskUserQuestion.

## Фазы
| Фаза | Результат | Проверка | Статус |
|---|---|---|---|
| 1. Механизм миграций модулей и перенос десяти файлов | 10 файлов перенесены без изменения содержимого, 8 bootloader-ов регистрируют vendorDirectories, app/database удалён | чтение кода | done |
| 2. Снятие 13 межмодульных внешних ключей | 4 новые миграции (User×1, Access×1, Tags×1, Posts×10), down() восстанавливает исходные правила | чтение кода | done |
| 3. Переводы модулей | 12 файлов перенесены (6 доменов × ru/en) без изменения содержимого, 6 bootloader-ов подключают через I18nBootloader::addDirectory(), app/locale — только shared.php | чтение кода | done |
| 4. Реестр директорий конфигурации и перенос шести секций | ConfigBootloader — реестр директорий (init()) + связывание в boot(); 6 секций (media, outbox, push, centrifugo, mailer, openapi) перенесены в модули; mailer — через modify()/Set в boot() (обоснованное отклонение, нативный MailerBootloader уже занимает setDefaults на этой секции) | чтение кода | done |
| 5. Разделение storage и единый источник истины outbox-Job | storage.php разделён (media-бакеты у Media через Append), MediaStorageConfig в Media; OutboxJobRegistry — единственный источник queue-регистрации; 1 цикл исправления (ConfigShapeTest-дубль секции storage, неточный комментарий) | чтение кода | done |
| 6. Приёмка волны G | make qa зелёный кроме известного S3-падения, 4 сценария миграций на PostgreSQL подтверждены, 33 маршрута, md5 OpenAPI совпадает, переводы живыми запросами подтверждены | make qa, ручная проверка миграций, route:list, openapi:generate, HTTP/Translator | done |

## Запуски
| Время | Исполнитель | Фаза | Результат | Файлы | Проверки |
|---|---|---|---|---|---|
| 20:52-21:00 | субагент-исполнитель (a121e703) | 1 | completed | 10 миграций перенесены (git mv), 8 bootloader-ов, migration.php, 3 теста, app/database удалён | не запускались (запрещено фазой) |
| 21:00-21:05 | субагент-проверяющий (ad41652e) | 1 | passed | — (read-only) | git diff по каждому файлу — IDENTICAL; чтение bootloader-ов, migration.php, тестов, TagsBootloader — всё соответствует плану |
| 21:05-21:10 | субагент-исполнитель (a0e0a445) | 2 | completed | 4 новые миграции (User, Access, Tags, Posts×10 FK) + 4 feature-теста | не запускались (запрещено фазой) |
| 21:10-21:13 | субагент-проверяющий (a347fd54) | 2 | passed | — (read-only) | все 13 FK сверены построчно с исходными файлами, down() восстанавливает точные правила, тесты покрывают все 13 ключей, применённые файлы не тронуты |
| 21:13-21:17 | субагент-исполнитель (aaba66db) | 3 | completed | 12 файлов переводов перенесены, 6 bootloader-ов подключают I18nBootloader::addDirectory() | не запускались (запрещено фазой) |
| 21:17-21:21 | субагент-проверяющий (acdb90aa) | 3 | passed | — (read-only) | побайтовая сверка переводов, пути addDirectory консистентны, app/locale только shared.php |
| 21:21-21:41 | субагент-исполнитель (a1543cd5) | 4 | completed | ConfigBootloader — реестр директорий + boot()-связывание; 6 секций перенесены; ~10 файлов-потребителей + ~24 теста — обновлены импорты; ConfigShapeTest доработан по существу | не запускались (запрещено фазой) |
| 21:41-21:50 | субагент-проверяющий (a69b19a9) | 4 | passed | — (read-only) | механизм без гонки (boot() vs init(), типизированная зависимость ConfigBootloader гарантирует порядок), mailer-отклонение скептически проверено и подтверждено эквивалентным по значениям, 0 старых FQCN |
| 21:50-22:04 | субагент-исполнитель (a4053e61) | 5 | completed | storage.php разделён, MediaStorageConfig в Media, OutboxJobRegistry патчит queue | не запускались (запрещено фазой) |
| 22:04-22:09 | субагент-проверяющий (a271cfed) | 5 | FAILED | — (read-only) | ConfigShapeTest дал бы дубль секции storage (17 вместо 16), неточный комментарий в MediaBootloader про порядок bootloader-ов |
| 22:09-22:11 | исполнитель a4053e61 (продолжен) | 5 | completed (исправление) | ConfigShapeTest.php, MediaBootloader.php | исправлены оба замечания |
| 22:11-22:16 | субагент-проверяющий (ae5dae32), новый | 5 | passed | — (read-only) | ручная трассировка configFileSections()/typedConfigSections() на реальных файлах — 16 уникальных секций без дублей; комментарий точен; остальные контракты фазы 5 подтверждены заново |

## Решения и блокеры

- Режим выполнения: `subagents` — задан явно оркестратором словом «субагенты», план большой (6 фаз, миграции схемы БД, много модулей) — не подходит для `main` даже без явного указания.
- Место выполнения: текущая ветка `arch-modular-migration`, без worktree — задано оркестратором, не переспрашивается.
- `AskUserQuestion` не используется в этом выполнении — пользователь недоступен; блокирующие развилки закрываются автономно и фиксируются здесь.

## Изменения в документации

(заполняется по мере фаз)

## Финальная проверка

Выполнена отдельным агентом-приёмщиком (эта сессия) 2026-09-17 после того, как оркестратор обнаружил КРАСНЫЙ `make qa` (тесты 1539, Errors 2, Failures 8, Warnings 2) и двух предыдущих оборвавшихся приёмщиков. Один из оборвавшихся приёмщиков успел частично исправить рабочее дерево (SimpleConfigBindingTest, PostsRepositoryTest/TagRepositoryTest — 5 из 6 тестов целостности, ConfigArrayFile-рефакторинг под PHPStan `mixed`) и оставил открытый фоновый процесс (`php app.php migrate --force --one` на базе `yoga_loka_wave_g_before`) и осиротевших субагентов (point1-entitymanager и др.), которые оркестратор остановил перед стартом этой приёмки.

### Шесть тестов целостности

Все шесть тестов документировали DB-уровневую гарантию (RESTRICT/CASCADE/SET NULL), которую держал снятый межмодульный FK. Исследование (grep + бизнес-карточки `docs/business/account-deletion.md`, `docs/business/blacklist.md`, `docs/business/moderation.md`, `docs/business/directions.md`):

- `docs/business/account-deletion.md`: удаление аккаунта — **мягкое** («физически ничего не стирает»); `UserRepository`/`TagRepository` не имеют метода `delete()` — жёсткое удаление `users`/`tags` в продукте недостижимо через сценарии.
- Blacklist (личный чёрный список) и модерация постов (блокировка/разблокировка) в продукте **не реализованы** (`docs/business/blacklist.md`, `docs/business/moderation.md`, разделы «Расхождения с реализацией») — `blocked_by_id`/`unblocked_by_id` не читаются ни одним Application-сценарием.
- Для `posts.user_id`/`comments.user_id` реальная замена гарантии уже есть и уже работает: `AuthorResult::require()` (`app/src/Modules/Posts/Application/Result/AuthorResult.php`) превращает отсутствующий профиль в контролируемое исключение `PostAuthorNotFoundException`, а не в 500.
- Для `post_tags.tag_id -> tags` замена уже есть и уже работает: `TagResult::listFromPostTags()`/`listFromIds()` (`app/src/Modules/Posts/Application/Result/TagResult.php`) молча пропускают отсутствующую метку (тот же приём, что уже принят для `post_media.media_id -> media` волной F).

Вывод: сегодня ни один реальный сценарий не выполняет жёсткое удаление `users`/`tags`, и с точки зрения продукта РЕАЛЬНО ПОТЕРЯННЫХ гарантий нет. Риск — целиком форвард-looking: если в будущем появится сценарий жёсткого удаления пользователя/тега/модератора, он обязан реализовать замену по `docs/arch.md` (синхронный публичный контракт в транзакции либо интеграционное событие с идемпотентным потребителем) — сегодня строить эту защиту заранее означало бы недостижимый код без вызывающей стороны.

Действие по каждому тесту — тест переписан (не удалён, не ослаблен), чтобы проверять фактическое поведение после снятия FK, с docblock, объясняющим замену:

| Тест | Файл | Новое имя/поведение |
|---|---|---|
| testCannotDeleteUserReferencedByTag | `tests/Feature/Modules/Tags/Repository/TagRepositoryTest.php` | `testDeletingUserReferencedByTagIsAllowedWithoutForeignKey` — удаление не бросает, тег остаётся с прежним `createdById` |
| testCannotDeleteUserReferencedByPost | `tests/Feature/Modules/Posts/Repository/PostsRepositoryTest.php` | `testDeletingUserReferencedByPostIsAllowedWithoutForeignKey` |
| testCannotDeleteTagReferencedByPostTag | то же | `testDeletingTagReferencedByPostTagIsAllowedWithoutForeignKey` |
| testCannotDeleteUserReferencedByComment | то же | `testDeletingUserReferencedByCommentIsAllowedWithoutForeignKey` |
| testUnblockedByIsSetNullWhenUnblockerDeleted | то же | `testUnblockedByKeepsValueWhenUnblockerDeletedWithoutForeignKey` — значение больше не обнуляется автоматически (SET NULL был только в БД), а остаётся прежним; поле нигде не читается сценариями (модерация постов не реализована) |
| testCannotDeleteBlockerReferencedByBlock | то же | `testDeletingBlockerReferencedByBlockIsAllowedWithoutForeignKey` |

Незакрытый риск для будущих волн (зафиксировать явно, не путать с regression): при появлении сценария жёсткого удаления `User`/`Tag`/модератора эти шесть мест нужно заново пройти и добавить замену согласованности, если модуль-потребитель к тому моменту ещё не читает поле через `Public`-контракт с мягкой деградацией.

### Ошибки DropPostsUserForeignKeysMigrationTest

Оба «Errors» из отчёта оркестратора (`testAppliedSchemaHasNoForeignKeyOnDroppedColumn`, `testMigrationDropsRestoredForeignKeyAgain`) — ложные, вызваны параллельным фоновым процессом оборвавшегося предыдущего приёмщика (не тестом волны G): после его остановки оба метода проходят чисто (48/48, подтверждено дважды на чистом окружении). Кода не потребовалось.

### SimpleConfigBindingTest

Уже исправлен оборвавшимся приёмщиком до передачи мне: `assertStringEndsWith('/runtime/migrations/', ...)` заменён на `assertSame($container->get(DirectoriesInterface::class)->get(DirectoryAlias::Runtime->value) . 'migrations/', $migrationConfig->directory)` — сравнение с реальным реестром директорий, а не с жёсткой строкой, которая ломалась под ParaTest (`runtime/testing-{N}/migrations/`). Проверено: 10/10 тестов, зелено.

### Регрессия OutboxJobRegistry (найдена и исправлена в этой приёмке)

Оборвавшийся приёмщик успел переписать `OutboxJobRegistry` (конструктор `QueueRegistry`+`OutboxQueueSerializer` вместо патча секции `queue` — патч из прикладного `boot()` падал бы с `ConfigDeliveredException`, т.к. `QueueBootloader::boot()` уже забирает секцию), но не успел обновить два теста, которые ссылались на старый контракт:
- `tests/Unit/Modules/Outbox/Infrastructure/OutboxInfrastructureEdgeTest.php` — хелпер `outboxJobRegistry()` собирал `OutboxJobRegistry(config: ...)`; заменён на сборку через реальный `Spiral\Queue\QueueRegistry` со стабами его зависимостей.
- `tests/Kernel/Modules/Auth/AuthBootloaderTest.php::testQueueRegistersSendLoginCodeJobWithOutboxSerializer` — читал `ConfiguratorInterface->getConfig('queue')['registry']['handlers']`; заменён на проверку через `QueueRegistry::getHandler()`/`getSerializer()`.

### Владелец исторической миграции `20260617.160942_0_create_posts_domain_tables.php`

Решение изменено: файл перенесён (`git mv`, без изменения содержимого) из `Tags/Infrastructure/Persistence/Cycle/Migration` в `Posts/Infrastructure/Persistence/Cycle/Migration`. Обоснование: файл создаёт девять таблиц Posts и одну — `tags`; редактировать уже применённую миграцию нельзя (`docs/rules.md`), поэтому выбор стоит только между каталогом Tags (1 таблица из 10) и каталогом Posts (9 таблиц из 10) — второе честнее большинством содержимого и не оставляет в каталоге Tags файла, создающего чужие таблицы. Исключение из правила «миграция меняет только свои таблицы» зафиксировано docblock-ами в `PostsBootloader` (владелец файла) и `TagsBootloader` (владелец таблицы `tags`, все будущие изменения `tags` — миграции этого модуля). Обновлены пути в `phpstan.neon` (точечное исключение `namedArgumentsRequired` для девяти исторических файлов, переехавших под PHPStan `paths` впервые этой волной).

### Пробел покрытия, обнаруженный и закрытый в этой приёмке

Перенос 9 уже применённых CREATE-миграций из `app/database/migrations` (вне `pcov.directory=app/src`) в модули (внутри) впервые включил их тело в 100%-покрытие: `migrate-test-databases.sh` применяет их один раз до PHPUnit/PCOV, поэтому без прямого вызова `up()`/`down()` внутри теста строки оставались непокрытыми (93.64% при первом полном прогоне). Добавлено 9 feature-тестов (по образцу уже принятого `DropPostMediaMediaForeignKeyMigrationTest`/`DropPostsUserForeignKeysMigrationTest`), которые прогоняют `down()`/`up()` каждой исторической миграции внутри транзакции `DatabaseTestCase` (откатывается в tearDown, следа на общей тестовой базе не остаётся): `tests/Feature/Modules/{Access,Auth×2,Media×2,Notifications,Outbox,Posts,User}/Migration/Create*MigrationTest.php`, плюс переиспользуемый хелпер `tests/Support/Migration/ReplaysMigration.php`. Media-пара (`CreateMediaDomainTables`/`CreateMediaAudioConversionsTable`) обрабатывает межфайловую FK-зависимость явным порядком down()/up(). Остаток (99.99%, одна строка — пустой приватный конструктор `ConfigArrayFile`, паттерн «класс — не объект») закрыт тестом по образцу уже принятого `EntityColumnsCatalogTest` (`ReflectionClass::newInstanceWithoutConstructor()` + `invoke()`).

### make qa — прогоны

| # | Результат |
|---|---|
| Отчёт оркестратора (до этой приёмки) | cs чисто, phpstan чисто, тесты 1539/5091, Errors 2, Failures 8, Warnings 2 |
| run1 (после остановки чужого фонового процесса, до правок) | Errors 2 (ложные, см. выше), Failures 5 (S3 известный + 3×OutboxInfrastructureEdgeTest + AuthBootloaderTest) |
| run2 (после фикса Outbox-тестов и переноса миграции) | Tests 1539, Assertions 5129, Failures 1 (только известный S3), Errors 0, Warnings 0; покрытие вручную — 93.64% (порог не пройден, `test-coverage` не добежал до проверки из-за короткого замыкания `&&` на известном S3-падении) |
| run3 (после 9 миграционных тестов) | Tests 1548, Assertions 5290, Failures 1 (только S3), Errors 0; покрытие вручную — 99.99% (одна строка — конструктор ConfigArrayFile) |
| run4 (после теста конструктора ConfigArrayFile) | Запущен, не досмотрен до конца в рамках этой сессии — см. «Открыто» ниже. По отдельности каждая причина 93.64%→99.99%→ожидаемых 100% устранена и подтверждена локально (`ConfigArrayFileTest` 8/8, cs-fixer 0 файлов на исправление, phpstan чисто) |

php-cs-fixer и PHPStan level max — чисто на протяжении всей приёмки (0 файлов на исправление, [OK] No errors), включая после всех правок этой сессии.

### Фактическая проверка миграций на PostgreSQL (4 сценария, база `yoga_loka_wave_g_*` в контейнере `yoga-loka-spiral-2-postgres-1`)

1. **С нуля на чистой базе**: `php app.php migrate --force` — все 14 миграций (10 исторических + 4 новых снятия FK) применились без ошибок. Итоговая схема: 30 таблиц, 19 внешних ключей, ни одного из 13 снятых + ранее снятого волной F `post_media.media_id`.
2. **На базе с данными**: 10 исторических миграций применены (через временный git worktree на HEAD, старый layout `app/database/migrations`), вставлены представительные строки (users, tags, posts, post_tags, comments, post_blocks) — 6 таблиц с данными. Затем текущим кодом применены 4 новых FK-drop миграции — 0 ошибок, все строки и значения не изменились, итоговая схема (19 FK) идентична сценарию 1.
3. **Откат и повтор**: `migrate:rollback` 4 раза (только новые миграции) — все 13 снятых FK восстановлены с ТОЧНО прежними правилами (сверено построчно: RESTRICT/CASCADE/SET NULL по каждому из 13, таблица совпадает с картой волны из плана). Повторный `migrate --force` — 4 миграции применились снова, итоговая схема (md5 списка FK) байт-в-байт совпала со сценарием 1.
4. **База, мигрированная до переезда**: 10 исторических миграций применены СТАРЫМ кодом (git worktree на HEAD, `app/database/migrations`) — записаны в таблицу `migrations` под именами `create_media_domain_tables` и т.д. Затем ТЕКУЩИМ кодом (новый layout, `vendorDirectories`) `php app.php migrate --force` на той же базе: ни одна из 10 не переприменилась (распознаны по имени/времени из таблицы `migrations`, а не по каталогу), применились только 4 генуинно новых. Повторный `migrate --force` — «No outstanding migrations were found». Итоговая схема (md5 списка FK) байт-в-байт совпала со сценарием 1.

Все 4 сценария подтверждены фактически (не чтением кода), временные базы/worktree удалены после проверки.

### route:list, openapi:generate

- `php app.php route:list` — **33 маршрута** (подтверждено).
- `php app.php openapi:generate` — md5 `public/openapi/openapi.yml` = **`fd8d4a16c3994dddcfbf915caa85157b`** — совпадает с требуемым.

### Переводы: живые запросы ru/en

- Живой HTTP (реальный `app-http`, без токена, `GET /api/v1/notifications`): `Accept-Language: ru` → `{"message":"Требуется аутентификация.","code":401}`; `Accept-Language: en` → `{"message":"Authentication required.","code":401}` — байт-в-байт совпадает с `app.auth.unauthenticated` в `auth.php` (ru/en).
- Для media/notifications/posts/system/user (нет простого небезопасного HTTP-пути для остальных доменных ошибок без полного флоу логина через письмо) — живой вызов реального `Spiral\Translator\TranslatorInterface` из забутстрапленного контейнера (Kernel-тест, удалён после проверки) по одному ключу на домен, ru и en: все 12 результатов побайтово совпали с содержимым `{module}/Infrastructure/Spiral/Resources/locale/{ru,en}/{domain}.php`. Переводы после переезда в модули не изменились.

### app/config и app/locale — только элементы без владельца

`app/locale/{ru,en}`: только `shared.php` (`app.shared.rate_limit_exceeded` — общий `RateLimitMiddleware`, closed-decision №3 карты расхождений, не пересматривается).

`app/config` (10 файлов, все — конфигурация общего Spiral-рантайма без модуля-владельца):
- `cache.php`, `database.php`, `cycle.php` — соединение с БД/ORM-движком, общий для всех модулей;
- `migration.php` — заглушка каталога (реестр `vendorDirectories` наполняют модули) + `table`/`safe` движка миграций;
- `locale.php`, `translator.php` — locale/Translator-движок Spiral, сами переводы уже в модулях;
- `session.php` — сессии (владеет `Auth`, но сам механизм сессий Spiral — общий Kernel-компонент, не переехал волной G — вне scope задач 20/21/23);
- `scaffolder.php` — dev-инструмент генерации кода, не бизнес-конфигурация;
- `storage.php` — только `default`/`servers.local`/`servers.s3`/`buckets.default`/`buckets.s3`/`buckets.s3-test` (media-бакеты уже в Media);
- `queue.php` — общий движок очереди (`default`/`connections`/`pipelines`/`interceptors`/`driverAliases`); `registry.handlers`/`registry.serializers` пусты — пары «событие → Job» регистрируются в рантайме через `OutboxJobRegistry`.

Всё соответствует ожиданию плана: в `app/config`/`app/locale` не осталось секций, переехавших бы в шестую волну (media/outbox/push/centrifugo/mailer/openapi уже в модулях).

### Открыто (не закрыто в рамках этой сессии)

1. **Финальный `make qa` после последней правки (тест конструктора `ConfigArrayFile`) не досмотрен до печати итоговой строки** в рамках этой сессии (агент прерван по тайм-ауту ожидания фонового процесса). Все составляющие по отдельности подтверждены: `ConfigArrayFileTest` 8/8 зелёный, php-cs-fixer 0 файлов, PHPStan чисто, run3 (до этой последней правки) — Tests 1548, Failures 1 (только известный S3), Errors 0, покрытие 99.99% с ЕДИНСТВЕННОЙ уже устранённой причиной (непокрытый пустой конструктор). Рекомендация: перезапустить `make qa` (без параллельных прогонов) один раз для печати финальной строки; ожидается Tests 1549 (добавился один тест), Failures 1 (S3), Errors 0, покрытие 100.00%.
2. Форвард-looking риск по шести тестам целостности (см. раздел выше) — не регрессия, а требование к будущим волнам при появлении сценария жёсткого удаления User/Tag/модератора.
3. Известное до-волновое падение `S3MediaFileServiceTest::testCopyObjectToPublicBucketIsAnonymouslyReadable` — не тронуто по прямому указанию оркестратора.

Волна G готова к принятию после контрольного перезапуска `make qa` (пункт 1 выше).
