---
title: Избавиться от deprecated в тестах (PHPUnit 13 + PHP 8.5)
date: 2026-06-11 15:04
mode: normal
plan_size: short
decision_mode: recommend_and_ask
status: meta-reviewed
reviewer: none
meta_reviewers: [haiku, sonnet, opus]
test_strategy: after_each_phase
logging_strategy: debug_precise
sources:
  rules: docs/rules.md
  arch: docs/arch.md
  research: docs/researches/2026-06-11_14-42_izbavitsya-ot-deprecated-v-testah.md
---

# План реализации

## Задача

Сделать прогон тестов чистым: убрать все 30 деприкейшенов, из-за которых сьюты
завершаются как `OK, but there were issues!` с маркерами `D`. Все деприкейшены —
в тестовом коде, `app/src` чистый. После зачистки закрепить результат гейтом, чтобы
deprecated не накапливались снова незаметно.

Готово, когда: `make test` (Unit + Kernel + Feature) зелёный без сводок
`Deprecations` / `PHPUnit Deprecations`, а в `phpunit.xml` включены атрибуты
`failOnDeprecation` и `failOnPhpunitDeprecation`.

## Контекст

Деприкейшены сводятся к двум классам (источник:
`docs/researches/2026-06-11_14-42_izbavitsya-ot-deprecated-v-testah.md`, все 7 точек
правок и единый паттерн подтверждены чтением кода):

- **Класс A (29 шт.)** — `Using with*() without expects() is deprecated` (станет
  жёсткой ошибкой в PHPUnit 14). PHPUnit 13 разделяет stub (только данные) и mock
  (проверка вызова через `expects()`); аргументный матчер `with()` на дублёре без
  `expects()` объявлен устаревшим. Источник — один паттерн мока конфигуратора
  `createMock(ConfiguratorInterface)->method('getConfig')->with($section)->willReturn($config)`
  в **5 helper/инлайн-местах** (27 деприкейшенов в Unit идут из 3 helper-методов,
  + 2 инлайна в Kernel).
- **Класс B (1 шт., 4 кейса)** — `imagedestroy() is deprecated since 8.5`. Функция
  no-op с PHP 8.0 (GD возвращает `GdImage`, освобождаемый сборщиком мусора). Два
  мёртвых вызова в тестовых фикстурах последней строкой перед `return`, переменная
  `$image` дальше не используется. Деприкейшен всплывает потому, что `phpunit.xml`
  задаёт `<ini name="error_reporting" value="-1"/>` (`E_DEPRECATED` активен); после
  удаления вызовов источник PHP-deprecation исчезает полностью.

Всего **7 точек правок** (5 в Класса A + 2 в Класса B) устраняют все **30
deprecation-событий** (29 PHPUnit + 1 PHP на 4 кейсах). 27 событий в Unit идут из
3 helper-методов — поэтому точек меньше, чем событий, это ожидаемо.

`tests/Unit/Modules/Outbox/Infrastructure/OutboxRelayWorkerTest.php` тоже использует
`->with(...)`, но уже корректно — через `->expects(self::exactly(N))`, деприкейшена
не даёт и правки не требует.

Единственный конфиг приложения — корневой `phpunit.xml`, в нём сейчас нет атрибутов
`failOn*`, поэтому деприкейшены показываются, но не валят гейт — из-за этого и
накопились. Тесты в `packages/*` изолированы своими `phpunit.xml` (rules.md
«Packages-пакеты изолированы»), `make test`/`make qa` их не прогоняет; deprecated-
паттернов (`with()` без `expects()`, `imagedestroy`) там нет — их не трогаем.

Апгрейд PHPUnit/PHP не нужен: обе проблемы решаются правкой тестов на текущих
PHPUnit 13.1.8 / PHP 8.5.4. Фикс заодно делает сьют совместимым с грядущим PHPUnit 14.

Изменения **не затрагивают** архитектуру (`Domain/Application/Infrastructure/
Presentation`): правки только в `tests/**` и `phpunit.xml`, продакшен-поведение и
контракты не меняются.

## Принятые решения

- **Класс A** — добавить `->expects($this->any())` перед `->method('getConfig')`,
  оставив `->with(...)`. _Источник: ответ пользователя в research._ Снимает
  деприкейшен и сохраняет проверку запрошенной секции, не навязывая счётчик вызовов.
  Отклонено: `expects($this->once())` (уронит тесты, вызывающие только `normalize()`
  без `getConfig`); `createStub()` + удалить `with()` (теряет проверку секции).
- **Класс B** — удалить строки `\imagedestroy($image);`. Мёртвый код, согласуется с
  `docs/rules.md` «Нет мёртвого кода».
- **Гейт** — после зелёного прогона включить `failOnDeprecation="true"` и
  `failOnPhpunitDeprecation="true"` в `phpunit.xml`. _Источник: ответ пользователя._
  Включать строго **после** зачистки, иначе гейт упадёт на текущих 30 деприкейшенах.
- **Версии** — апгрейд не требуется (PHPUnit 13.1.8, PHP 8.5.4 — обе проблемы
  решаются на них).
- **plan_size: short** — 2 фазы, ожидаемый объём: 7 точечных правок в 7 файлах
  `tests/**` (5 однострочных вставок + 2 удаления строк) + 2 атрибута в `phpunit.xml`.

## Целевой алгоритм

1. **Зачистка.** В 5 местах Класса A вставляется `->expects($this->any())`; в 2
   местах Класса B удаляется `\imagedestroy(...)`. Прогон `make test` → сводки
   `PHPUnit Deprecations` и `Deprecations` исчезают (остаётся только 1 несвязанный
   PHPUnit Notice в Feature — вне темы задачи).
2. **Закрепление.** В корневой тег `<phpunit>` добавляются `failOnDeprecation` и
   `failOnPhpunitDeprecation`. Повторный `make test` остаётся зелёным; с этого момента
   любой новый deprecated даёт ненулевой exit code и валит гейт.

## Контракты реализации

### Данные и БД
Не затрагивается.

### API и внешние контракты
Не затрагивается. Правки только в `tests/**` и `phpunit.xml`.

## Фазы выполнения

### 1. Зачистить deprecated в тестах
Цель: убрать все 30 деприкейшенов правкой тестового кода.

Что сделать:

**Класс A (5 точек)** — вставить строку `->expects($this->any())` непосредственно
выше `->method('getConfig')`, не трогая `->with(...)` и `->willReturn(...)`:

- `tests/Unit/Shared/Infrastructure/Configuration/ConfigMapperTest.php` — helper `mapperForSection()` (~стр. 213)
- `tests/Unit/Shared/Infrastructure/Configuration/ComplexConfigMapperTest.php` — helper `mapperFor()` (~стр. 407)
- `tests/Unit/Shared/Infrastructure/Configuration/SimpleConfigMapperTest.php` — helper `mapperFor()` (~стр. 285)
- `tests/Kernel/Shared/Infrastructure/Configuration/MediaConfigTest.php` — helper `mapperFor()` (~стр. 64)
- `tests/Kernel/Shared/Infrastructure/Configuration/MediaStorageConfigTest.php` — helper `mapperFor()` (~стр. 64)

Итоговый паттерн во всех пяти местах:

```php
$configurator = $this->createMock(ConfiguratorInterface::class);
$configurator
    ->expects($this->any())
    ->method('getConfig')
    ->with($section)
    ->willReturn($config);
```

**Класс B (2 точки)** — удалить строку `\imagedestroy($image);` целиком:

- `tests/Unit/Modules/Media/Infrastructure/ImagickMediaImageProcessorTest.php` — в `encodeFixture()` (~стр. 93)
- `tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php` — в `jpegBytes()` (~стр. 216)

Не трогать `tests/Unit/Modules/Outbox/Infrastructure/OutboxRelayWorkerTest.php`: там
`->with(...)` уже корректен через `->expects(self::exactly(N))`.

Результат: тестовый код без deprecated-паттернов.

Сценарии тестирования:
- ConfigMapper / ComplexConfigMapper / SimpleConfigMapper тесты проходят, проверка
  секции через `with()` сохранена.
- Тесты, вызывающие только `normalize()` (без `getConfig`), не падают на
  неудовлетворённом ожидании — `any()` не навязывает вызов.
- Media image processor / processing flow тесты проходят, фикстуры возвращают те же байты.

Проверка: `make test` — все три сьюта зелёные (exit code 0), сводки
`PHPUnit Deprecations` и `Deprecations` отсутствуют. В выводе зафиксировать
конкретный тест-источник оставшегося PHPUnit Notice в Feature (имя класса/метода из
сводки `PHPUnit Notices`) — это нужно, чтобы критерий «остался ровно 1 несвязанный
Notice» был проверяемым: если Notice больше одного или это замаскированная
deprecation, фаза не считается зелёной.

### 2. Включить гейт на deprecated
Цель: не дать deprecated вернуться незаметно.

Что сделать: в корневой тег `<phpunit ...>` в `phpunit.xml` (рядом с уже имеющимися
`stopOnFailure`, `stopOnError` и т.п.) добавить два атрибута:

```xml
failOnDeprecation="true"
failOnPhpunitDeprecation="true"
```

Добавляются **только** эти два атрибута. `failOnNotice` и `failOnPhpunitNotice` не
включаются — иначе оставшийся несвязанный Notice в Feature уронит прогон (он не
deprecation и в тему задачи не входит).

Результат: новый PHP- или PHPUnit-deprecated даёт ненулевой exit code и валит
`make test` / `make qa`. `failOn*` влияет только на exit code — прогон не
останавливается на первом deprecated (`stopOnFailure`/`stopOnError` остаются
`false`), сводка деприкейшенов по-прежнему печатается полностью.

Важно: после включения гейт распространяется и на отдельные цели `make test-unit`,
`make test-kernel`, `make test-feature` (голый `vendor/bin/phpunit` / `paratest`
читает тот же корневой `phpunit.xml`) — они тоже станут красными при любом
deprecated. Это ожидаемое поведение.

Сценарии тестирования:
- Прогон на уже зачищенном коде остаётся зелёным (`make test` exit code 0).
- (Обязательный negative-контроль) разово подтвердить, что гейт активен: временно
  добавить заведомый deprecated в один тест (например, вызов `\imagedestroy(null)`
  или `with()` без `expects()`), прогнать сьют — он должен стать красным, затем
  откатить временную правку через git. Без этого «гейт активен» — лишь декларация.

Проверка: `make test` зелёный с активными `failOn*`; `make qa` (стиль + PHPStan +
coverage через тот же ParaTest) зелёный.

## Тесты
Стратегия `after_each_phase`: новых тестов не пишем — задача правит существующие.
После каждой фазы прогоняется полный `make test` (Unit + Kernel + Feature) как
критерий готовности фазы. Финальная проверка фазы 2 — `make qa`.

## Логирование
Стратегия `debug_precise` неприменима: изменения только в тестовом коде и конфиге
PHPUnit, рантайм-логирование приложения не затрагивается. Новых лог-точек нет.

## Документация и эксплуатация
- Кода `app/src`, ENV и зависимостей изменения не касаются — обновлять docs/runbook
  не требуется.
- Включение `failOn*` меняет поведение гейта: с этого момента deprecated блокирует
  локальные прогоны `make test`, `make qa`, а также отдельные `make test-unit`/
  `test-kernel`/`test-feature`. Отдельного CI-пайплайна в проекте нет
  (`.github/workflows` и аналоги отсутствуют), поэтому влияние ограничено локальным
  гейтом. Зафиксировать смену поведения в сообщении коммита.
- Порог покрытия не затрагивается: правки только в `tests/**`, а `source`-include в
  `phpunit.xml` — `app/src`; тестовый код в coverage не входит, требование «100%
  покрытие» по `app/src` остаётся выполнимым, `make qa` не падает по coverage.

## Изменения после мета-ревью

### После моделей
- **+ Добавлено:** явное указание, что 7 точек правок устраняют 30 deprecation-
  событий (устранена путаница «29+1 vs 30 vs 5/2 точки»).
- **+ Добавлено:** обязательный negative-контроль гейта в фазе 2 (временный
  deprecated → красный прогон → откат), чтобы «гейт активен» был проверяем.
- **+ Добавлено:** шаг локализации источника оставшегося PHPUnit Notice в фазе 1 —
  делает критерий «остался ровно 1 Notice» фальсифицируемым.
- **+ Добавлено:** заметка, что `make test-unit`/`test-kernel`/`test-feature` тоже
  подпадут под гейт (читают тот же корневой `phpunit.xml`).
- **+ Добавлено:** изоляция `packages/*` (свои `phpunit.xml`, deprecated там нет, не
  трогаем) и связь правок с coverage-порогом 100% (не затрагивается).
- **~ Изменено:** исправлена фактическая неточность — в проекте нет CI-пайплайна;
  формулировка «блокирует в CI» заменена на локальный гейт.
- **~ Изменено:** уточнено, что добавляются только `failOnDeprecation`/
  `failOnPhpunitDeprecation`, без `failOn*Notice`, и что `failOn*` влияет лишь на
  exit code, не останавливая прогон; добавлен контекст `error_reporting=-1` для
  Класса B.
- **Отклонено:** отдельный раздел отката (haiku) — для git-проекта тривиально, в
  short-плане избыточно. Подтверждение всех 7 точек ±1 строка и поведения ParaTest
  (наследование `failOn*` через `ShellExitCodeCalculator`) тремя ревьюерами вошло как
  обоснование, отдельных правок не потребовало.

## Прогресс выполнения
Журнал: `docs/executions/2026-06-11_15-17_izbavitsya-ot-deprecated-v-testah.md`

- [x] Фаза 1: Зачистить deprecated в тестах (5 точек Класса A + 2 точки Класса B), прогон `make test`
- [x] Фаза 2: Включить гейт `failOnDeprecation`/`failOnPhpunitDeprecation`, negative-контроль, `make qa`
