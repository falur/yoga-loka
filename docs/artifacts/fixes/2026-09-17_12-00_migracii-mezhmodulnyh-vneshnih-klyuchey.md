---
date: 2026-09-17 12:00
source: text (задача от оркестратора через eda-fix)
status: done
---

# Фикс: миграции модуля-владельца больше не создают межмодульные внешние ключи

## Контекст

Проект в стадии разработки (`AGENTS.md`, «Стадия проекта») — продакшена и рабочих данных нет, базу можно пересоздавать с нуля. До этого фикса шесть create-миграций создавали 14 межмодульных внешних ключей (нарушая `docs/arch.md`, «Владение данными»: «Межмодульные внешние ключи не используются как основа согласованности»), которые затем снимались пятью отдельными миграциями-заплатками волны G/F. Историческая миграция Posts создавала также таблицу `tags`, принадлежащую модулю Tags.

Прочитаны и применены: `AGENTS.md` («Стадия проекта»), `docs/arch.md` («Владение данными», «Самодостаточность модуля», «Осознанные отступления»), `docs/rules.md` (правило о миграциях), `docs/references/migration.md`, журнал волны G `docs/artifacts/executions/2026-09-16_20-52_volna-g-migracii-perevody-konfiguraciya.md`.

## Что изменено

| # | Файл | Что изменено | Зачем |
|---|------|--------------|-------|
| 1 | `app/src/Modules/Tags/Infrastructure/Persistence/Cycle/Migration/20260917.090000_0_create_tags_table.php` | Создан. Новая миграция модуля Tags создаёт таблицу `tags` (id, text(64), created_by_id, timestamps, PK id, unique index text), без FK | Владелец таблицы `tags` — Tags; создание переехало от Posts к владельцу |
| 2 | `app/src/Modules/Tags/Tests/Integration/Cycle/CreateTagsTableMigrationTest.php` | Создан. Реплей `down()/up()` новой миграции внутри транзакции `DatabaseTestCase` (покрытие тела, как у прочих миграций) | Тело миграции не покрывается `migrate-test-databases.sh` (применяется до PCOV) |
| 3 | `app/src/Modules/Posts/Infrastructure/Persistence/Cycle/Migration/20260617.160942_0_create_posts_domain_tables.php` | Убрано создание таблицы `tags`; убраны 11 межмодульных FK: `posts.user_id`, `post_media.media_id`, `post_likes.user_id`, `post_mentions.user_id`, `post_tags.tag_id`, `post_blocks.blocked_by_id`, `post_blocks.unblocked_by_id`, `comments.user_id`, `comments.deleted_by_id`, `comment_likes.user_id`, `comment_mentions.user_id`. Внутримодульные FK (self-FK `posts.parent_post_id`, `comments.parent_comment_id`, `*.post_id -> posts`, `*.comment_id -> comments`) не тронуты | Миграция-владелец меняет только свои таблицы; межмодульных FK быть не должно |
| 4 | `app/src/Modules/User/Infrastructure/Persistence/Cycle/Migration/20260613.143901_0_create_user_domain_tables.php` | Убран межмодульный FK `users.avatar_media_id -> media.id` | То же правило |
| 5 | `app/src/Modules/Access/Infrastructure/Persistence/Cycle/Migration/20260613.143902_0_create_access_domain_tables.php` | Убран межмодульный FK `user_roles.user_id -> users.id` | То же правило |
| 6 | `app/src/Modules/Posts/Infrastructure/Persistence/Cycle/Migration/20260915.234500_0_drop_post_media_media_foreign_key.php` | Удалён | Заплатка стала не нужна — FK не создаётся |
| 7 | `app/src/Modules/User/Infrastructure/Persistence/Cycle/Migration/20260916.090000_0_drop_users_avatar_media_foreign_key.php` | Удалён | То же |
| 8 | `app/src/Modules/Access/Infrastructure/Persistence/Cycle/Migration/20260916.090100_0_drop_user_roles_user_foreign_key.php` | Удалён | То же |
| 9 | `app/src/Modules/Tags/Infrastructure/Persistence/Cycle/Migration/20260916.090200_0_drop_tags_created_by_foreign_key.php` | Удалён | То же |
| 10 | `app/src/Modules/Posts/Infrastructure/Persistence/Cycle/Migration/20260916.090300_0_drop_posts_module_user_foreign_keys.php` | Удалён | То же |
| 11-15 | `app/src/Modules/{Posts,User,Access,Tags}/Tests/Integration/Cycle/Drop*MigrationTest.php` (5 файлов) | Удалены вместе со снятыми миграциями | Тесты удалённых заплаток больше не нужны |
| 16 | `app/src/Modules/Posts/Tests/Integration/Cycle/CreatePostsDomainTablesMigrationTest.php` | `TABLES` без `tags`; ассерты FK заменены на `assertNull` для 11 снятых межмодульных FK, внутримодульные FK по-прежнему проверяются `assertNotNull` | Тест реплея приведён к новому содержимому миграции |
| 17 | `app/src/Modules/User/Tests/Integration/Cycle/CreateUserDomainTablesMigrationTest.php` | Ассерт `avatar_media_id` FK заменён на `assertNull` | То же |
| 18 | `app/src/Modules/Access/Tests/Integration/Cycle/CreateAccessDomainTablesMigrationTest.php` | Ассерт `user_id` FK на `user_roles` заменён на `assertNull` | То же |
| 19 | `app/src/Modules/Posts/Infrastructure/Spiral/Bootloader/PostsBootloader.php` | Убран докблок про историческое исключение (создание `tags` в файле Posts) | Исключение закрыто по существу |
| 20 | `app/src/Modules/Tags/Infrastructure/Spiral/Bootloader/TagsBootloader.php` | Докблок обновлён: `tags` создаёт миграция этого модуля | То же |
| 21 | `tests/Support/Migration/ReplaysMigration.php` | Ссылка на удалённый `DropPostsUserForeignKeysMigrationTest` заменена на `CreatePostsDomainTablesMigrationTest` | Актуализация комментария после удаления файла |
| 22 | `docs/arch.md` | Из «Осознанные отступления» убран пункт «Историческая миграция Posts создаёт таблицу `tags`» | Отступление закрыто по существу, а не задокументировано |
| 23 | `docs/rules.md` | Правило о запрете редактирования применённой миграции дополнено исключением на стадию разработки со ссылкой на `AGENTS.md`, «Стадия проекта» | Правило приведено в соответствие со стадией проекта, снова станет строгим при появлении продакшена |
| 24 | `phpstan.neon` | Уточнён комментарий у блока `namedArgumentsRequired` для 9 исторических миграций: убрано устаревшее «без изменения содержимого» / «нельзя менять», описан факт точечной правки Access/Posts/User без смены позиционного стиля вызовов | Комментарий перестал вводить в заблуждение после разрешённой правки этих файлов |

Итого: 5 миграций удалено, 3 миграции отредактированы (создание, без структурных изменений остального), 1 миграция создана; 5 тестов удалено, 3 теста отредактированы, 1 тест создан.

## Тесты и проверки

| Команда | Результат | Заметки |
|---------|-----------|---------|
| Ручная проверка: `php app.php migrate --force` на чистой временной базе `yoga_loka_fix_after` (Docker, реальный `postgres`) | ✓ | Все 10 миграций (9 create + новая Tags) применились без ошибок |
| Нормализованный дамп схемы (`information_schema`/`pg_catalog`: таблицы, колонки+типы+длины+nullable, PK, FK с правилами update/delete, индексы с уникальностью — без учёта авто-хэшей имён constraint/index, которые Cycle генерирует заново при каждом применении) `yoga_loka_fix_after` vs истинный baseline `yoga_loka_fix_baseline` (тот же набор миграций **до** правки, применённый с нуля на отдельной базе) | ✓ идентично (`diff` exit 0) | `pg_dump --schema-only` не годится напрямую — имена FK/индексов недетерминированы между независимыми применениями (проверено: два независимых применения ДО правки уже давали разные хэши при идентичной структуре); нормализованный SQL-запрос это устраняет |
| `migrate:rollback --all --force` на `yoga_loka_fix_after` | ✓ | Все 10 миграций откатились в обратном порядке зависимостей без ошибок, остался только `migrations` |
| Повторный `php app.php migrate --force` и нормализованный дамп | ✓ идентично baseline (`diff` exit 0) | Откат и повторное применение дают ту же схему |
| Временные базы `yoga_loka_fix_baseline`, `yoga_loka_fix_after` | удалены | А также орфанные `yoga_loka_wave_g_before/clean/data`, оставшиеся от предыдущей сессии |
| `make test-unit` | ✓ 540 тестов, 1837 assertions | Suite Unit не затронут (миграционные тесты — Integration/Feature) |
| `make phpstan` | ✓ [OK] No errors | Level max, весь `app/src` |
| `make qa` (Docker, `test-runner`) | ✓ exit 0 | cs-fixer чисто; deptrac: Violations 0, Skipped 0, Errors 0; 1511 тестов / 5152 assertions, 0 failures/errors; покрытие 100.00% (порог 100%) |

### Изменение числа тестов: 1570 → 1511 (−59)

- −3 `DropPostMediaMediaForeignKeyMigrationTest`
- −3 `DropUserAvatarMediaForeignKeyMigrationTest`
- −3 `DropUserRolesUserForeignKeyMigrationTest`
- −3 `DropTagsCreatedByForeignKeyMigrationTest`
- −48 `DropPostsUserForeignKeysMigrationTest` (5 методов с `DataProvider`: 10+10+10+9+9)
- Итого удалено: 60
- +1 `CreateTagsTableMigrationTest::testDownDropsTableAndUpRecreatesItWithoutForeignKey`
- Итого: 1570 − 60 + 1 = 1511 — совпадает с фактическим результатом `make qa`.

Покрытие осталось 100%: тело новой миграции и `down()`/`up()` остальных create-миграций по-прежнему прогоняются реплей-тестами (как раньше).

## Текст изменённого правила `docs/rules.md`

> Не редактируй уже применённую миграцию — добавляй новую. Пока проект в стадии разработки и база пересоздаётся с нуля (`AGENTS.md`, «Стадия проекта»), применённую миграцию допустимо переписать вместо заплатки; с появлением продакшена это исключение снимается, и правило снова обязательно без оговорок. В миграции имена таблиц и колонок пиши строковым литералом.

## Открытые вопросы

Нет.
