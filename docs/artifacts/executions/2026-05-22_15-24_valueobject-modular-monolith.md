---
plan: docs/plans/2026-05-22_14-49_valueobject-modular-monolith.md
started: 2026-05-22 15:24
finished: 2026-05-22 15:54
status: done
---

# Журнал: Перенос ValueObject на чистую доменную модель и модульный монолит

## Шаги

| # | Шаг | Файлы | Тесты | Статус |
|---|-----|-------|-------|--------|
| 1 | Перенести общий код в Shared | `app.php`, `app/config/cache.php`, `app/src/Shared/**`, `tests/**` | `make test` прошёл после правки пути в `ConfigShapeTest`; `make phpstan` прошёл; `grep` по старым imports в коде без совпадений | done |
| 2 | Перенести код в модули | `app/src/Modules/Media/**`, `app/src/Modules/System/**`, `app/config/openapi.php`, `app/config/scaffolder.php`, `tests/**` | `make test` прошёл, 116 тестов; `make phpstan` прошёл; `grep` по старым `App\Domain`, `App\Repository`, `App\Endpoint`, `App\Infrastructure` в коде без совпадений | done |
| 3 | Сделать ValueObject независимыми от Cycle ORM | `app/src/Modules/Media/Domain/**`, `app/src/Modules/Media/Infrastructure/Cycle/**`, `app/src/Shared/Infrastructure/Cycle/**`, `tests/**` | `make test` прошёл, 122 теста; `make phpstan` прошёл; `grep` по `fromDatabase`, `toDatabase`, `Castable`, `JsonCastable`, `Shared\Infrastructure` в `Modules/Media/Domain` без совпадений | done |
| 4 | Обновить документацию и финальные проверки | `docs/arch.md`, `docs/rules.md`, `docs/code-examples.md` | `make test` прошёл, 122 теста; `make phpstan` прошёл; `grep` по старым namespace и старым VO-методам в актуальных docs без совпадений | done |

## Заметки

- Место выполнения: текущая ветка `main`, выбран вариант 1 по ответу пользователя.
- `rg` недоступен в окружении, для поисковых проверок используется `grep`.
- В фазе 1 первый `make test` упал из-за старого пути `app/src/Infrastructure/Configuration` в `ConfigShapeTest`. Путь исправлен на `app/src/Shared/Infrastructure/Configuration`, повторный запуск прошёл.
- В фазе 3 первый `make test` упал из-за недопустимого union type `object|\DateTimeInterface`; типы сужены. Первый `make phpstan` после этого нашёл динамические возвраты в `ValueObjectCast`; добавлено явное сужение через reflection и проверки типа.
- Исторические документы в `docs/fixes`, `docs/plans`, `docs/reviews`, `docs/researches` не переписывались: они фиксируют прошлое состояние проекта. Поисковая проверка документации выполнена по актуальным `docs/arch.md`, `docs/rules.md`, `docs/code-examples.md`.

## Изменения в docs

- `docs/arch.md`: целевая структура заменена на `Modules` и `Shared`; описаны `Shared\Domain\Exception`, `Shared\Domain\Trait`, модульные `Repository`, `Application/Contract`, `Infrastructure/Cycle` и `Infrastructure/FileService`.
- `docs/rules.md`: обновлены пути typed config, Filter, CQRS, команды проверок `make test` и `make phpstan`; исправлена опечатка про интеграционные тесты роутов.
- `docs/code-examples.md`: примеры переведены на модульные namespace и чистые `ValueObject` без `fromDatabase()`.

## Финальная проверка

| Команда | Результат |
|---------|-----------|
| `make test` | OK, 122 теста, 469 assertions, есть 26 PHPUnit deprecations |
| `make phpstan` | OK, ошибок нет |
| `grep` по старым namespace и VO-методам в `app/src/Modules/Media/Domain` | OK, совпадений нет |
| `grep` по старым namespace и VO-методам в `docs/arch.md`, `docs/rules.md`, `docs/code-examples.md` | OK, совпадений нет |
