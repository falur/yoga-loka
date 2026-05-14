---
title: Заменить Psalm на PHPStan max level
date: 2026-05-11 15:37
mode: normal
plan_size: short
decision_mode: recommend_and_ask
status: meta-reviewed
reviewer: pending
meta_reviewers:
  - gpt-5.4-mini
  - gpt-5.3-codex
  - gpt-5.5
test_strategy: after_each_phase
logging_strategy: debug_precise
sources:
  rules: —
  arch: —
  research: —
---

# План реализации

## Задача

Заменить Psalm на PHPStan в проекте, настроить анализ на максимальный уровень строгости и убрать старые Psalm-артефакты. Готовый результат: `composer phpstan` запускает PHPStan на `level: max`, проект проходит статический анализ без baseline, тесты проходят, Psalm больше не используется в зависимостях, скриптах и конфигурации.

## Контекст

- В проекте нет `docs/rules.md` и `docs/arch.md`; доступна только настройка планирования в `docs/settings.yaml`.
- Проект должен работать на PHP 8.5 последней стабильной ветки, Spiral Framework, Composer и PHPUnit.
- Сейчас Psalm подключён через `vimeo/psalm` в `require-dev`, `psalm.xml`, `psalm-baseline.xml`, скрипты `psalm` и `psalm:baseline` в `composer.json`.
- Psalm анализирует только `app/src`; в `app/src` 8 PHP-файлов, в `tests` 3 PHP-файла.
- В коде есть две Psalm-аннотации: `@psalm-suppress ClassMustBeFinal` в `app/src/Application/Kernel.php` и `tests/App/TestKernel.php`.
- Проверка Composer dry-run показала, что `phpstan/phpstan` версии `2.1.54` устанавливается без конфликта.
- Packagist показывает `phpstan/phpstan 2.1.54` как актуальную стабильную версию на 2026-05-11: https://packagist.org/packages/phpstan/phpstan
- Официальная документация PHPStan описывает `--level max` как alias максимального уровня и позволяет фиксировать уровень через параметр `level` в config: https://phpstan.org/user-guide/rule-levels и https://phpstan.org/config-reference
- Пользователь уточнил, что проект работает на последней PHP 8.5. Официальный сайт PHP показывает PHP `8.5.6` от 2026-05-07 как актуальный релиз ветки 8.5: https://www.php.net/
- Локальный PHP сейчас `8.5.2`, а целевая версия проекта — PHP `8.5.6`; исполнитель должен обновить локальный PHP перед финальной проверкой или явно записать расхождение в журнал выполнения.
- Текущий `composer test` завершается с кодом 0, но содержит risky test и vendor deprecation. Эта задача не исправляет существующее тестовое предупреждение, но исполнитель должен явно записать его в журнал, если оно сохранится после миграции.
- Текущий `composer cs:fix -- --dry-run --diff` в этой среде падает из-за parallel runner; для проверки стиля нужен последовательный fallback.

## Принятые решения

- Использовать `phpstan/phpstan` версии `2.1.54`; источник версии — Packagist и проверка `composer show phpstan/phpstan --all`.
- Настроить PHPStan через `phpstan.neon` с `level: max`, `paths: [app/src]` и `phpVersion: 80506`, чтобы сохранить текущую область анализа Psalm и проверять код под целевую PHP 8.5.6.
- Подключить Composer autoload в PHPStan config. Если PHPStan не видит глобальный Spiral helper `directory()`, добавить точечный `scanFiles` на файл vendor, где объявлен этот helper; новые PHPStan extension-пакеты не добавлять в рамках этого short-плана.
- Не создавать PHPStan baseline. Решение подтверждено пользователем: выбран вариант «без baseline».
- Удалить `psalm.xml`, `psalm-baseline.xml`, зависимость `vimeo/psalm`, скрипты `psalm` и `psalm:baseline`.
- Удалить Psalm-специфичные inline-аннотации. Если PHPStan выдаёт подтверждённое ложное срабатывание, использовать узкое PHPStan-подавление с идентификатором ошибки и причиной.
- Не включать `tests` в `paths` PHPStan в этом плане: сохраняется текущая область анализа Psalm. Очистка `@psalm` в `tests` проверяется отдельным `rg`-контролем.
- Синхронизировать `composer.json` с фактической целевой платформой PHP 8.5, чтобы Composer и PHPStan проверяли один и тот же runtime-контракт.
- Ожидаемый объём для `short`: 2 фазы, около 5-7 изменённых файлов с учётом `composer.lock`, до 400 строк осмысленного diff без учёта lock-файла.
- Стратегия тестов — `after_each_phase`: после каждой фазы запускаются проверки, относящиеся к сделанным изменениям.
- Стратегия логирования — `debug_precise`: runtime-логи приложения не добавляются, потому что меняется tooling; исполнитель фиксирует точные команды, версии и вывод ошибок анализатора в журнале выполнения.

## Целевой алгоритм

1. Composer перестаёт устанавливать Psalm и начинает устанавливать PHPStan версии `2.1.54`.
2. В корне появляется `phpstan.neon`, где PHPStan анализирует `app/src` на `level: max` под PHP `8.5.6`.
3. Команда `composer phpstan` запускает `phpstan analyse` с проектным конфигом.
4. Старые Psalm-файлы и Psalm-скрипты удаляются, чтобы в проекте остался один статический анализатор.
5. Исполнитель запускает PHPStan, исправляет найденные ошибки в коде или PHPDoc без baseline и без широких подавлений.
6. Если PHPStan не видит глобальные функции Spiral, исполнитель подключает Composer autoload или точечный `scanFiles` для нужного helper-файла.
7. После исправлений `composer phpstan` завершается без ошибок, затем проходят `composer test` и доступная проверка стиля с последовательным fallback.
8. В журнале выполнения фиксируются точные команды, версия PHPStan, найденные ошибки до исправления, существующие PHPUnit-issues и финальный чистый прогон PHPStan.

## Фазы выполнения

### 1. Переключить tooling с Psalm на PHPStan

Цель: заменить зависимость, конфиг и Composer-скрипты без изменения поведения приложения.

Что сделать:
- В `composer.json` удалить `vimeo/psalm` из `require-dev`.
- В `composer.json` добавить `phpstan/phpstan` версии `2.1.54` в `require-dev`.
- В `composer.json` обновить PHP platform requirement с текущего `>=8.4` до контракта PHP 8.5, согласованного с проектом.
- В `composer.json` заменить скрипты `psalm` и `psalm:baseline` на `phpstan`, который запускает `phpstan analyse`.
- Создать `phpstan.neon` с `level: max`, `paths: [app/src]`, `phpVersion: 80506` и подключением Composer autoload.
- Удалить `psalm.xml` и `psalm-baseline.xml`.
- Обновить `composer.lock` через Composer.
- Если PHPStan не видит глобальный helper `directory()`, добавить в `phpstan.neon` точечный `scanFiles` на vendor-файл с этим helper.
- Зафиксировать в журнале выполнения команды Composer, установленную версию PHPStan и выбранный способ загрузки helper-функций.

Результат: PHPStan установлен и настроен, Psalm удалён из конфигурации проекта.

Сценарии тестирования:
- Composer dependency graph собирается с `phpstan/phpstan 2.1.54`.
- Composer dependency graph собирается под PHP 8.5.
- `composer phpstan` стартует и читает `phpstan.neon`.
- В проекте нет активных Psalm-команд и Psalm-конфигов.

Проверка:
- `composer validate --strict`
- `composer phpstan`
- `test ! -e psalm.xml && test ! -e psalm-baseline.xml`
- `rg -n "@psalm|vimeo/psalm|psalm-baseline|\"psalm\"|\"psalm:baseline\"" app tests composer.json README.md docs/settings.yaml`

### 2. Довести код до чистого PHPStan max без baseline

Цель: добиться чистого статического анализа на максимальном уровне без переноса долгов в baseline.

Что сделать:
- Прочитать все ошибки `composer phpstan` после фазы 1.
- Исправить типы, сигнатуры, PHPDoc и явные преобразования значений там, где PHPStan показывает реальные проблемы.
- Удалить `@psalm-suppress ClassMustBeFinal` в `app/src/Application/Kernel.php` и `tests/App/TestKernel.php`.
- Использовать PHPStan-подавления только для подтверждённых ложных срабатываний, с узким идентификатором ошибки и короткой причиной.
- Повторять `composer phpstan` до чистого результата.
- После чистого PHPStan запустить тесты и проверку стиля.
- Если обычная проверка стиля падает из-за parallel runner, запустить `composer cs:fix -- --dry-run --diff --sequential`.
- Зафиксировать в журнале выполнения список исправленных категорий ошибок, текущий статус PHPUnit risky/deprecation и финальные команды.

Результат: код проходит `level: max`, тесты проходят, Psalm-специфичных аннотаций не осталось.

Сценарии тестирования:
- Статический анализ покрывает `app/src` на максимальном уровне.
- Изменения типизации не ломают существующие PHPUnit-сценарии.
- Проверка стиля проходит обычной командой или последовательным fallback без форматирующих правок.
- Существующие PHPUnit risky/deprecation зафиксированы отдельно от результата миграции.

Проверка:
- `composer phpstan`
- `composer test`
- `composer cs:fix -- --dry-run --diff`
- `composer cs:fix -- --dry-run --diff --sequential`
- `test ! -e psalm.xml && test ! -e psalm-baseline.xml`
- `rg -n "@psalm|vimeo/psalm|psalm-baseline|\"psalm\"|\"psalm:baseline\"" app tests composer.json README.md docs/settings.yaml`

## Тесты

Стратегия: `after_each_phase`. После первой фазы запускается Composer validation и старт PHPStan, чтобы проверить корректность tooling. После второй фазы запускаются полный PHPStan max, PHPUnit и dry-run проверки стиля. Для стиля основной вариант — `composer cs:fix -- --dry-run --diff`, fallback в текущей среде — `composer cs:fix -- --dry-run --diff --sequential`. Новые unit-тесты не планируются, потому что задача меняет инструменты анализа, а не runtime-поведение; если исправление PHPStan затронет бизнес-логику, исполнитель добавляет точечный тест на изменённый сценарий в той же фазе. Текущий risky/deprecation в PHPUnit не относится к замене анализатора и фиксируется в журнале как существующее состояние, если сохраняется.

## Логирование

Стратегия: `debug_precise`. Runtime-логи приложения не добавляются. В журнале выполнения нужно точно записать команды, версии, первичный вывод PHPStan, категории исправленных ошибок и финальные чистые прогоны. В лог не попадают секреты, `.env`-значения и персональные данные.

## Документация и эксплуатация

- Обновить README и внутренние docs только если там есть ссылки на Psalm-команды; предварительно найти их через `rg`. CI-конфигов в текущем дереве нет.
- Для релиза важно, что quality gate меняется с Psalm на PHPStan `level: max` без baseline.
- После внедрения команда для локальной проверки статического анализа: `composer phpstan`.

## Изменения после мета-ревью

### После моделей

- **+ Добавлено:** фиксация `phpVersion: 80506`, чтобы PHPStan проверял код под целевую версию проекта PHP 8.5.6.
- **+ Добавлено:** fallback `--sequential` для проверки стиля, потому что текущий parallel runner php-cs-fixer падает в среде выполнения.
- **+ Добавлено:** явное правило для глобального Spiral helper `directory()` через Composer autoload или точечный `scanFiles` без новых extension-пакетов.
- **+ Добавлено:** фиксация текущих PHPUnit risky/deprecation в журнале выполнения как существующего состояния.
- **~ Изменено:** `rg`-проверки больше не ищут по всему репозиторию и не передают удалённые `psalm.xml`/`psalm-baseline.xml` как обязательные пути.
- **~ Изменено:** отдельно зафиксировано, что `tests` не входят в область PHPStan, но очищаются от `@psalm` через grep-контроль.
- **Отклонено:** добавление PHPStan extension-пакетов для Spiral не включено в short-план, потому что для текущей маленькой кодовой базы сначала достаточно autoload/scanFiles и точечных исправлений.

### После уточнения пользователя

- **~ Изменено:** целевая версия PHP в плане изменена с PHP 8.4 на PHP 8.5.6.
- **+ Добавлено:** шаг синхронизации `composer.json` с контрактом PHP 8.5.

## Прогресс выполнения
Журнал: `docs/executions/2026-05-11_15-46_replace-psalm-with-phpstan-max.md`

- [x] Шаг 1: Переключить tooling с Psalm на PHPStan
- [x] Шаг 2: Довести код до чистого PHPStan max без baseline
- [x] Шаг 3: Финальная проверка
