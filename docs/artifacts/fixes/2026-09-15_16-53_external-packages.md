---
date: 2026-09-15 16:53
source: text — переход на собственные пакеты из GitHub (falur/*) вместо локальных packages/*
status: done
---

# Фикс: переход на собственные пакеты из GitHub вместо локальных

## Контекст

Вход — сообщение пользователя со списком собственных репозиториев: `falur/spiral-cqrs`,
`falur/spiral-api-errors`, `falur/spiral-outbox`, `falur/spiral-openapi`,
`falur/phpstan-strict-rules` и указание использовать их вместо локальных.

Проверено: все пять пакетов опубликованы на Packagist под именами `gian-tiaga/*` с тегом
`v0.1.0`, поэтому раздел `repositories` в `composer.json` больше не нужен.

Подтверждённые решения пользователя:

- `spiral-outbox` — только подключить пакет, локальный модуль `app/src/Modules/Outbox` оставить
  работающим; перевод модуля на пакет — отдельная задача;
- версии пакетов — теги `^0.1.0`;
- каталог `packages/` удалить;
- остальные зависимости можно обновить до последних версий.

Учтены `docs/rules.md` (объём изменений, точечное гашение PHPStan, проверки только в Docker) и
`docs/arch.md` (раздел «Структура» и «Направления зависимостей» упоминали `packages/`).
Business-карточки не применимы: поведение продукта не меняется. Reference-карточки не применимы:
новых компонентов кода не появилось.

## Что изменено

| # | Файл | Что изменено | Зачем |
|---|------|--------------|-------|
| 1 | `composer.json` | Версии `gian-tiaga/spiral-api-errors`, `spiral-cqrs`, `spiral-openapi` и `phpstan-strict-rules` заменены с `dev-main`/`*` на `^0.1.0`; добавлен `gian-tiaga/spiral-outbox: ^0.1.0`; удалён блок `repositories` с путями `packages/*` | Пакеты берутся с Packagist, а не из локального каталога |
| 2 | `composer.json` | `brianium/paratest` поднят с `7.22.4` до `7.24.1` | PHPUnit 13.3 удалил `NullResultCache`, старый ParaTest падал с fatal error |
| 3 | `composer.lock` | Полное обновление зависимостей (`composer update`) | Явное разрешение пользователя обновить всё до последних версий; попутно закрыты 29 предупреждений `composer audit` |
| 4 | `packages/` (182 файла) | Каталог локальных пакетов удалён | Код пакетов живёт в своих репозиториях |
| 5 | `phpstan.neon` | Добавлен параметр `setBasedWriteMarkerInterface: 'App\Shared\Infrastructure\Database\SetBasedWrite'` | В выпущенной версии правил маркер задаёт проект, а не сам пакет; без него правило перестало бы проверять массовую запись |
| 6 | `phpstan.neon` | Добавлено точечное исключение `function.alreadyNarrowedType` для `LazyGhostEntityFactory.php` с причиной | Cycle объявляет ключ `getRelations()` как `non-empty-string`, но PHP приводит числовое имя к `int`; проверка нужна и покрыта тестом |
| 7 | `app/src/Shared/Infrastructure/Cycle/LazyGhostEntityFactory.php` | К проверке `is_string($name)` добавлен комментарий о расхождении PHPDoc Cycle и рантайма | Проверка выглядит лишней по типам; без пояснения её удаляют — это уже ломало тест |
| 8 | `app/src/Modules/Media/Infrastructure/FileService/S3MediaFileService.php` | `->values()->all()` заменено на `\array_values(...->all())` | Обновлённый aws-sdk требует `list` для `MultipartUpload.Parts`; PHPDoc Illuminate `list` не гарантирует |
| 9 | `.gitignore` | Удалены строки `/packages/*/vendor` и `/packages/*/runtime/` | Каталога больше нет |
| 10 | `docs/arch.md` | Из схемы структуры убрана строка `packages/`; в направлениях зависимостей «внутренних packages» заменено на «собственных Composer-пакетов» | Документ описывал каталог, которого больше нет |
| 11 | `docs/rules.md` | Правило о запуске тестов и PHPStan внутри `packages/<package>` заменено правилом о правке пакетов в их репозиториях | Прежний способ проверки недоступен |
| 12 | Два фильтра модуля `Notifications` | В комментариях `packages/spiral-openapi` заменено на `gian-tiaga/spiral-openapi` | Ссылка на несуществующий путь |

Тесты не добавлялись: поведение приложения не менялось. Правки в `S3MediaFileService` и
`LazyGhostEntityFactory` сохраняют прежний результат и покрыты существующими тестами — один из них
(`LazyGhostEntityFactoryTest::testExtractRelationsSkipsNonStringRelationName`) как раз поймал
ошибочную попытку убрать проверку `is_string()`.

## Тесты и проверки

| Команда | Результат | Заметки |
|---------|-----------|---------|
| `make phpstan` | ✓ | No errors, level max |
| `make qa` | ✓ | php-cs-fixer + PHPStan + 1304 теста, 4304 утверждения, покрытие 100.00% |
| `composer update` (в Docker) | ✓ | `No security vulnerability advisories found` (было 29 предупреждений по 4 пакетам) |

Промежуточные падения и их устранение:

- ParaTest 7.22.4 + PHPUnit 13.3.4 — fatal error `NullResultCache not found`; поднята версия ParaTest;
- 2 ошибки PHPStan после обновления aws-sdk и cycle/orm — исправлены в коде;
- 1 упавший тест после ошибочного удаления проверки `is_string()` — проверка возвращена.

## Открытые вопросы

1. `gian-tiaga/spiral-outbox` установлен, но его `OutboxBootloader` намеренно не зарегистрирован в
   `Kernel`. Пакет занимает те же имена, что и локальный модуль: секцию конфигурации `outbox`,
   таблицу `outbox_events` и команду `outbox:relay`, плюс добавляет свой каталог миграций с
   `outbox_events`/`outbox_deliveries`. Включение bootloader сейчас сломало бы миграции и конфиг.
   Перевод модуля `app/src/Modules/Outbox` на пакет — отдельная задача (схема пакета другая:
   события и доставки разделены, есть маршруты и `outbox:status`).
2. Тест `packages/spiral-api-errors/tests/ConstructorContractTest.php` существовал только локально и
   в репозитории `falur/spiral-api-errors` отсутствует. После удаления `packages/` он остался лишь в
   истории git — при желании его нужно перенести в репозиторий пакета.
3. `composer.json` сохраняет `minimum-stability: dev`; после перехода на теги dev-зависимостей не
   осталось, но значение не менялось, чтобы не расширять объём фикса.
