---
title: Избавиться от deprecated в тестах (PHPUnit 13 + PHP 8.5)
date: 2026-06-11 14:42
mode: normal
decision_mode: recommend_and_ask
status: draft
reviewer: none
sources:
  rules: docs/rules.md
  arch: docs/arch.md
---

# Избавиться от deprecated в тестах (PHPUnit 13 + PHP 8.5)

## Суть

Пользователь хочет убрать deprecated, особенно те, что вылезают в тестах. При прогоне тесты завершаются как `OK, but there were issues!` с маркерами `D` и сводкой деприкейшенов. Нужно понять, **что именно** деприкейтнуто, **где** и **как чинить**, чтобы прогон стал чистым.

Прогон в Docker (`PHP 8.5.4`, `PHPUnit 13.1.8`) дал такую картину по сьютам:

| Сьют | Tests | Deprecations (PHP) | PHPUnit Deprecations | Прочее |
|---|---|---|---|---|
| Unit | 211 | 1 (на 4 кейсах) | 27 | — |
| Kernel | 39 | 0 | 2 | — |
| Feature | 155 | 0 | 0 | 1 PHPUnit Notice (вне темы) |

Все деприкейшены сводятся к **двум классам**, оба — в тестовом коде, не в `app/src`.

## Решение

### Класс A — PHPUnit: `Using with*() without expects()` (29 шт.)

Сообщение: «Using with*() without expects() is deprecated and will no longer be possible in PHPUnit 14.»

Все 29 деприкейшенов порождены **одним паттерном** мока конфигуратора:

```php
$configurator = $this->createMock(ConfiguratorInterface::class);
$configurator
    ->method('getConfig')
    ->with($section)        // ← with() без expects() — deprecated
    ->willReturn($config);
```

PHPUnit 13 разделяет stub (только данные, без `with()`/`expects()`) и mock (проверка вызова через `expects()`). Аргументный матчер `with()` на дублёре без `expects()` объявлен устаревшим и станет жёсткой ошибкой в PHPUnit 14.

27 деприкейшенов в Unit идут не из 27 разных мест, а из **3 helper-методов**, которые вызываются многими тест-методами. Плюс 2 инлайновых места в Kernel. Итого правок — **5 точек** закрывают все 29:

| Файл | Строки | Тип места |
|---|---|---|
| `tests/Unit/Shared/Infrastructure/Configuration/ConfigMapperTest.php` | 212–215 | helper `mapperForSection()` |
| `tests/Unit/Shared/Infrastructure/Configuration/ComplexConfigMapperTest.php` | 406–409 | helper |
| `tests/Unit/Shared/Infrastructure/Configuration/SimpleConfigMapperTest.php` | 284–287 | helper |
| `tests/Kernel/Shared/Infrastructure/Configuration/MediaConfigTest.php` | 63–66 | инлайн в тесте |
| `tests/Kernel/Shared/Infrastructure/Configuration/MediaStorageConfigTest.php` | 63–66 | инлайн в тесте |

**Выбранный фикс (подтверждён пользователем):** добавить `->expects($this->any())` перед `->method('getConfig')`, оставив `->with($section)`:

```php
$configurator = $this->createMock(ConfiguratorInterface::class);
$configurator
    ->expects($this->any())
    ->method('getConfig')
    ->with($section)
    ->willReturn($config);
```

Почему этот вариант, а не альтернативы:
- `expects($this->any())` снимает деприкейшен и **сохраняет** проверку аргумента секции (`with($section)`), не навязывая счётчик вызовов.
- Счётчик `expects($this->once())` опасен: часть тестов вызывает только `normalize()`, который `getConfig` не дёргает, — `once()` уронит их на неудовлетворённом ожидании. Это реальный риск, поэтому вариант отклонён.
- `createStub()` + удалить `with()` — чище идеологически, но теряет проверку «маппер запросил правильную секцию». Отклонено в пользу минимальной правки, сохраняющей текущие гарантии теста.

Замечание: `tests/Unit/Modules/Outbox/Infrastructure/OutboxRelayWorkerTest.php` тоже использует `->with(...)`, но уже корректно — через `->expects(self::exactly(N))`, поэтому деприкейшена не даёт и правки не требует.

### Класс B — PHP 8.5: `imagedestroy() is deprecated` (1 шт., 4 кейса)

Сообщение: «Function imagedestroy() is deprecated since 8.5, as it has no effect since PHP 8.0».

С PHP 8.0 GD возвращает объект `GdImage`, который освобождается сборщиком мусора, а `imagedestroy()` ничего не делает; в 8.5 он помечен deprecated. Вызов лежит в двух тестовых helper-ах последней строкой перед `return $bytes;`, переменная `$image` дальше не используется:

| Файл | Строка | Действие |
|---|---|---|
| `tests/Unit/Modules/Media/Infrastructure/ImagickMediaImageProcessorTest.php` | 93 | удалить строку `\imagedestroy($image);` |
| `tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php` | 216 | удалить строку `\imagedestroy($image);` |

Фикс — просто удалить обе строки (мёртвый код, бизнес-смысла нет; противоречий с `docs/rules.md` «Нет мёртвого кода» при этом не возникает, наоборот — устраняем его).

В `app/src` функции `imagedestroy` нет — продакшен-код деприкейшенов не порождает.

### Архитектурный слой

Изменения **не затрагивают** архитектуру приложения (`Domain/Application/Infrastructure/Presentation`): правки только в тестовом коде `tests/**`, поведение продакшена и контракты не меняются.

### Проверка версий

| ПО | В проекте | Актуальное стабильное | Источник / дата |
|---|---|---|---|
| PHPUnit | 13.1.8 | 13.2 (релиз 2026-06-05), следующий мажор — 14 | [phpunit.de/announcements](https://phpunit.de/announcements/index.html), [supported-versions](https://phpunit.de/supported-versions.html) — проверено 2026-06-11 |
| PHP | 8.5.4 (в Docker) | 8.5.x | runtime прогона тестов |

Вывод по версиям: апгрейд PHPUnit для фикса **не нужен** — обе проблемы решаются правкой тестов на текущей 13.x. Фикс при этом делает сьют совместимым с грядущим PHPUnit 14, где `with*()` без `expects()` перестанет работать (риск «отложенной поломки» при будущем апгрейде — снимаем сейчас).

### Защита от регресса (рекомендация в рамках задачи)

Сейчас в `phpunit.xml` нет атрибутов `failOn*`, поэтому деприкейшены показываются, но не валят гейт — из-за этого и накопились. Чтобы «избавиться» было устойчивым, **рекомендую** после зачистки включить в `phpunit.xml`:

```xml
failOnDeprecation="true"
failOnPhpunitDeprecation="true"
```

Тогда любой новый deprecated сразу станет красным в `make qa`/`make test`, и проблема не вернётся. Включать строго **после** зелёного прогона, иначе гейт упадёт на текущих 30 деприкейшенах. Это отдельная правка конфига; финальное «включаем/нет» — на этапе `eda-plan`, но настоятельно советую включить.

## Ответы на вопросы

**Вопрос (значимая развилка):** как чинить 29 деприкейшенов `Using with*() without expects()`, порождённых паттерном `createMock(...)->method('getConfig')->with($section)->willReturn($config)` в 5 файлах?

**Ответ пользователя:** выбран вариант **`expects($this->any())`** — добавить `->expects($this->any())` перед `->method('getConfig')`, оставив `->with($section)`. Минимальная правка, сохраняет проверку секции, не навязывает счётчик вызовов.

Развилка по защите от регресса (`failOnDeprecation`/`failOnPhpunitDeprecation`) пользователю не выносилась отдельным блокирующим вопросом: это необязательное упрочнение гейта, решение по нему оставлено на `eda-plan` с моей рекомендацией «включить после зелёного прогона».

## Итог

Подход к зачистке (только тесты, `app/src` не трогаем):

1. **Класс A (29 шт.):** в 5 местах (3 helper-метода Unit + 2 инлайна Kernel) добавить `->expects($this->any())` перед `->method('getConfig')`, не убирая `->with($section)`.
2. **Класс B (1 шт.):** удалить строки `\imagedestroy($image);` в `ImagickMediaImageProcessorTest.php:93` и `MediaProcessingFlowTest.php:216`.
3. Прогнать `make test` (Unit+Kernel+Feature) — сводки деприкейшенов должны исчезнуть, остаётся только 1 PHPUnit Notice в Feature (вне темы текущей задачи).
4. После зелёного прогона включить `failOnDeprecation="true"` и `failOnPhpunitDeprecation="true"` в `phpunit.xml`, чтобы регресс ловился автоматически.

Апгрейд пакетов не требуется. Дальше — передать в `eda-plan` для пошагового плана правок.
