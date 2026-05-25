---
title: CQRS tools-пакет
date: 2026-05-22 17:56
mode: normal
plan_size: normal
decision_mode: recommend_and_ask
status: meta-reviewed
reviewer: none
meta_reviewers:
  - gpt-5.4-mini
  - gpt-5.3-codex
  - gpt-5.5
test_strategy: after_each_phase
logging_strategy: debug_precise
sources:
  rules: docs/rules.md
  arch: docs/arch.md
  research: docs/researches/2026-05-22_17-46_cqrs-tools-package.md
---

# План реализации

## Задача

Коротко: вынести инфраструктуру CQRS-шины в отдельный локальный Composer-пакет
`tools/cqrs`, подключить его к приложению и обновить примеры кода на публичные
интерфейсы `Tools\Cqrs\CommandBusInterface` и
`Tools\Cqrs\QueryBusInterface`.

Готовый результат: пакет `yoga-loka/cqrs-tools` устанавливается через path
repository как `dev-main`, сам проходит свои тесты и PHPStan, приложение получает
`CommandBusInterface` и `QueryBusInterface` из DI-контейнера Spiral, а примеры
кода больше не ссылаются на несуществующий `App\Shared\Infrastructure\Bus`.

## Контекст

В `docs/arch.md` уже описана CQRS-схема приложения: команды меняют состояние,
запросы читают данные, а шина принимает `callable`, чтобы сохранить точный тип
результата `Handler::handle()` для PHPStan и IDE.

В текущем проекте ещё нет своей реализации `CommandBusInterface` и
`QueryBusInterface`. Единственное найденное упоминание старого namespace
`App\Shared\Infrastructure\Bus\QueryBusInterface` находится в
`docs/code-examples.md`.

В проекте уже есть переносимые локальные пакеты `tools/openapi` и
`tools/api-error`. Они имеют собственный `composer.json`, локальный
`vendor`, `bootstrap.php`, `phpunit.xml`, `phpstan.neon`, namespace
`Tools\...` и отдельные команды проверки через `composer -d tools/<package>`.
Новый пакет CQRS должен повторять этот формат.

Исследование зафиксировало источник переноса:
`../yoga-loka-spiral/app/src/Infrastructure/Bus`. Там уже есть
`CommandBus`, `QueryBus`, интерфейсы, middleware логирования, middleware
транзакций, отложенные действия после commit и rollback, а также Spiral
bootloader.

Проверка версий внешним поиском не нужна: версии нужных зависимостей уже
зафиксированы в корневом `composer.lock`: `cycle/database` 2.16.0, `psr/log`
3.0.2, `spiral/framework` 3.16.2. PHP-версия проекта зафиксирована в корневом
`composer.json`: `>=8.5 <8.6`.

Соседние tools-пакеты хранят свои `composer.lock` рядом с package-local
`composer.json`. Для `tools/cqrs` lock-файл пакета тоже считается ожидаемым
артефактом.

## Принятые решения

- Размер плана: `normal`. Источник: ответ пользователя `1`.
- Режим принятия решений: `recommend_and_ask`. Существенное решение о составе
  пакета уже подтверждено в research ответом пользователя `вариант 1`.
- Создать локальный Composer-пакет `tools/cqrs` с именем
  `yoga-loka/cqrs-tools` и namespace `Tools\Cqrs`. Источник: research с
  подтверждённым ответом пользователя.
- Приложение использует интерфейсы пакета напрямую:
  `Tools\Cqrs\CommandBusInterface` и `Tools\Cqrs\QueryBusInterface`.
  Промежуточные обёртки в `App\Shared` не создаются. Источник: research с
  подтверждённым ответом пользователя.
- В пакет входят только инфраструктурные части шины: `CommandBus`,
  `QueryBus`, `BusMiddlewareInterface`, `LoggingMiddleware`,
  `TransactionalMiddleware`, `AfterCommitActions`, `AfterCommitFrame` и
  `CqrsBootloader`.
- Command и Query DTO, Handler-ы, репозитории, доменные результаты,
  `PaginatedResult<T>` и правила папок остаются в приложении. Пакет не ищет
  Handler-ы автоматически и не вводит marker-интерфейсы для Command или Query.
- `dispatch()` принимает `callable`, а не Command или Query объект. Native
  return type не указывается, а точный тип результата задаётся через generic
  PHPDoc. Причина: так сохраняется точный тип результата и при этом не
  используется явный `mixed`, запрещённый правилами проекта.
- `CommandBus` строится с цепочкой `LoggingMiddleware ->
  TransactionalMiddleware`. `QueryBus` строится с цепочкой
  `LoggingMiddleware`.
- Транзакции применяются только к Command. Query не открывает транзакцию по
  умолчанию и не меняет состояние.
- `AfterCommitActions` поддержан только внутри выполнения Command через
  `CommandBus`. Вызов вне активного frame не выполняет callback; это поведение
  фиксируется тестами и README, чтобы пакет не воспринимали как общий
  dispatcher событий.
- Для вложенного `CommandBus::dispatch()` сохраняется поведение старой
  реализации: callbacks внутреннего dispatch выполняются после успешного
  завершения его транзакции или savepoint, а не после финального commit внешней
  транзакции. Источник: перенос подтверждённого поведения из research. В README
  и тестах это фиксируется явно; внешние побочные эффекты приложения должны идти
  через transactional outbox, а не через вложенный `afterCommit`.
- `CqrsBootloader` регистрирует `AfterCommitActions` как singleton,
  привязывает интерфейсы шин к реализациям и добавляет finalizer, который
  очищает stack отложенных действий между запросами RoadRunner.
- Новый пакет зависит от уже используемых в проекте библиотек:
  `cycle/database:^2.16`, `psr/log:^3.0`, `spiral/framework:^3.16` и
  `php >=8.5 <8.6`. Источник версий: текущие `composer.json` и
  `composer.lock`.
- Логирование выполняется на уровне debug для старта и времени выполнения
  операции. Warning пишется только для ошибки внутри afterCommit или
  afterRollback callback. В логах нет пользовательских значений, секретов и
  персональных данных.
- Чтобы `LoggingMiddleware` видел имя операции, Command или Query DTO в
  примерах создаётся до closure и захватывается closure. Если DTO создаётся
  внутри closure, в лог попадает `Unknown`; это допустимое поведение и оно
  фиксируется тестом.
- База данных, миграции, HTTP-маршруты и публичный JSON API не меняются.

## Целевой алгоритм

1. HTTP-контроллер, консольная команда, задача очереди или Temporal-adapter
   создаёт Command или Query DTO приложения.
2. Входной слой вызывает `CommandBusInterface::dispatch()` или
   `QueryBusInterface::dispatch()` и передаёт `callable`, внутри которого
   вызывается нужный `Handler::handle(...)`.
3. Для читаемых логов входной слой создаёт Command или Query DTO до closure, а
   closure захватывает этот объект.
4. `LoggingMiddleware` извлекает имя Command или Query из захваченных объектов
   closure. Если имя извлечь нельзя, используется `Unknown`.
5. `LoggingMiddleware` пишет debug-лог начала операции без данных пользователя.
6. Для Command управление переходит в `TransactionalMiddleware`.
7. `TransactionalMiddleware` открывает frame в `AfterCommitActions` и запускает
   operation внутри транзакции `Cycle\Database\DatabaseInterface`.
8. Handler выполняет бизнес-сценарий приложения, работает с доменными типами,
   репозиториями и сам вызывает `EntityManager::run()`, как требует архитектура.
9. После успешного завершения транзакции или savepoint `TransactionalMiddleware`
   извлекает frame и выполняет
   callbacks, зарегистрированные через `afterCommit()`.
10. Если внутри транзакции возникла ошибка, `TransactionalMiddleware` извлекает
   frame, выполняет callbacks `afterRollback()` и пробрасывает исходное
   исключение дальше.
11. Если callback после commit или rollback сам выбросил ошибку, middleware
    пишет warning-лог и продолжает выполнение остальных callbacks.
12. Для Query после `LoggingMiddleware` сразу выполняется operation без
    `TransactionalMiddleware`.
13. `LoggingMiddleware` пишет debug-лог времени выполнения операции в
    миллисекундах.
14. `CqrsBootloader` очищает `AfterCommitActions` через Spiral finalizer между
    запросами RoadRunner, чтобы в long-running runtime не оставались callbacks
    от предыдущего запроса.

## Контракты реализации

### Данные и БД

Не затрагивается.

### API и внешние контракты

HTTP API, JSON-ответы, маршруты, права доступа, события очередей, webhooks и
внешние сервисы не меняются.

Меняется внутренний PHP-контракт приложения:

- новый пакет: `yoga-loka/cqrs-tools`;
- namespace пакета: `Tools\Cqrs`;
- публичный интерфейс для команд: `Tools\Cqrs\CommandBusInterface`;
- публичный интерфейс для запросов: `Tools\Cqrs\QueryBusInterface`;
- метод обеих шин:

```php
/**
 * @template TResult
 * @param callable(): TResult $operation
 * @return TResult
 */
public function dispatch(callable $operation);
```

- public-сервис отложенных действий:
  `Tools\Cqrs\AfterCommitActions`;
- методы отложенных действий:
  `begin()`, `afterCommit(\Closure $callback)`,
  `afterRollback(\Closure $callback)`, `pop()`, `reset()`;
- Spiral bootloader: `Tools\Cqrs\Bootloader\CqrsBootloader`.

## Фазы выполнения

### 1. Создать пакет `tools/cqrs`

Цель: перенести CQRS-инфраструктуру в отдельный пакет без зависимости от
namespace приложения.

Что сделать:

- Создать каталог `tools/cqrs` со структурой локального Composer-пакета по
  образцу `tools/openapi` и `tools/api-error`.
- Добавить `tools/cqrs/composer.json` с именем `yoga-loka/cqrs-tools`,
  namespace `Tools\Cqrs\`, scripts `test` и `phpstan`.
- В `tools/cqrs/composer.json` добавить `autoload-dev` для namespace
  `Tools\Cqrs\Tests\` и каталога `tests`.
- Добавить зависимости пакета: `php >=8.5 <8.6`, `cycle/database:^2.16`,
  `psr/log:^3.0`, `spiral/framework:^3.16`.
- Добавить dev-зависимости пакета: `phpunit/phpunit:^13.1` и
  `phpstan/phpstan:^2.1.54`.
- Добавить `tools/cqrs/bootstrap.php`, `tools/cqrs/phpunit.xml`,
  `tools/cqrs/phpstan.neon`, `tools/cqrs/README.md` и runtime-каталоги по
  локальному шаблону tools-пакетов.
- После `composer -d tools/cqrs install` сохранить `tools/cqrs/composer.lock`
  как часть пакета.
- Перенести код из старого проекта в namespace `Tools\Cqrs`: интерфейсы шин,
  реализации шин, `BusMiddlewareInterface`, `LoggingMiddleware`,
  `TransactionalMiddleware`, `AfterCommitActions` и `AfterCommitFrame`.
- В перенесённом коде убрать ссылки на `App\` и заменить их на
  `Tools\Cqrs\`.
- Сохранить generic PHPDoc у `dispatch()` и `BusMiddlewareInterface`, чтобы
  точный тип результата не терялся.
- Не добавлять native return type `mixed` в `dispatch()` и
  `BusMiddlewareInterface::handle()`. Возврат описать generic PHPDoc.
- Добавить `Tools\Cqrs\Bootloader\CqrsBootloader`, который регистрирует
  singleton `AfterCommitActions`, создаёт `CommandBus` и `QueryBus` с нужными
  middleware и добавляет finalizer на `AfterCommitActions::reset()`.
- В README пакета описать назначение пакета, пример вызова `dispatch()`,
  правило про `callable`, состав middleware, поведение `AfterCommitActions` и
  команды проверки.
- В README пакета явно указать, что `CqrsBootloader` ожидает уже
  зарегистрированные `Cycle\Database\DatabaseInterface` и
  `Psr\Log\LoggerInterface` в контейнере приложения.
- В README пакета явно указать поведение вложенного `CommandBus::dispatch()`:
  inner `afterCommit` выполняется после успешного завершения внутренней
  транзакции или savepoint; внешние побочные эффекты приложения идут через
  outbox.
- Написать unit-тесты пакета после переноса кода.
- Покрыть тестами `CommandBus`: middleware вызываются в заданном порядке, а
  результат operation возвращается без приведения типа.
- Покрыть тестами `QueryBus`: используется pipeline middleware и возвращается
  результат operation.
- Покрыть тестами `LoggingMiddleware`: пишет debug-лог старта, debug-лог
  времени выполнения, извлекает имя Command или Query из closure и возвращает
  `Unknown` для не-closure, closure без Command/Query объекта и closure, где DTO
  создаётся внутри тела closure.
- Покрыть тестами `AfterCommitActions`: callbacks добавляются в текущий frame,
  `pop()` возвращает верхний frame, вложенные frame изолированы, `reset()`
  очищает stack.
- Покрыть тестами `TransactionalMiddleware`: успешная операция выполняется
  внутри транзакции, после успеха выполняются commit callbacks, после ошибки
  выполняются rollback callbacks, исходное исключение пробрасывается дальше, а
  ошибка callback пишет warning-лог и не ломает выполнение остальных callbacks.
- Покрыть тестом `CqrsBootloader` в пакете: через fake `FinalizerInterface`
  проверить, что `init()` добавляет finalizer и вызов сохранённого finalizer
  очищает stack `AfterCommitActions`.
- Покрыть тестом `CqrsBootloader` в пакете: через fake
  `Cycle\Database\DatabaseInterface` и fake `Psr\Log\LoggerInterface`
  проверить, что фабрики bootloader-а собирают `CommandBus` и `QueryBus` с
  ожидаемыми middleware.
- Добавить portability-тест: production-код `tools/cqrs/src` не содержит
  `namespace App\` и `use App\`.

Результат: `tools/cqrs` является самостоятельным локальным пакетом, доступным
для установки, проверки и использования без кода приложения.

Сценарии тестирования:

- `CommandBus` вызывает middleware в порядке `LoggingMiddleware`,
  `TransactionalMiddleware`, затем operation.
- `QueryBus` вызывает `LoggingMiddleware`, затем operation.
- `dispatch()` возвращает строку, объект или `void` без ручного приведения.
- В сигнатурах `dispatch()` и `BusMiddlewareInterface::handle()` нет явного
  native return type `mixed`.
- `AfterCommitActions` выполняет callbacks только из текущего frame.
- Вложенные dispatch-вызовы не смешивают callbacks.
- Вложенный command выполняет inner `afterCommit` после успешного завершения
  внутренней транзакции или savepoint; этот контракт подтверждён тестом.
- Callback, добавленный вне активного frame, не выполняется.
- Finalizer из `CqrsBootloader` очищает stack `AfterCommitActions`.
- Ошибка внутри operation запускает rollback callbacks и не запускает commit
  callbacks.
- Ошибка внутри callback логируется как warning и не подменяет основной
  результат успешной операции.
- В production-коде пакета нет зависимости от `App\`.

Проверка:

- `composer validate --strict --no-interaction tools/cqrs/composer.json`
- `composer -d tools/cqrs install --no-interaction`
- `composer -d tools/cqrs test`
- `composer -d tools/cqrs phpstan`
- `! grep -RIn "namespace App\\\\\\|use App\\\\" tools/cqrs/src`

### 2. Подключить пакет к приложению

Цель: сделать CQRS-шину доступной из DI-контейнера приложения и убрать
документированные ссылки на старый namespace.

Что сделать:

- Добавить path repository `tools/cqrs` в корневой `composer.json` рядом с
  `tools/openapi` и `tools/api-error`.
- Добавить `"yoga-loka/cqrs-tools": "dev-main"` в корневой `require` как
  production-зависимость приложения.
- Обновить `composer.lock` командой Composer, чтобы root autoload увидел
  `Tools\Cqrs`.
- Зарегистрировать `Tools\Cqrs\Bootloader\CqrsBootloader` в
  `app/src/Shared/Infrastructure/Framework/Kernel.php` рядом с другими
  tools bootloader-ами.
- Обновить `docs/code-examples.md`: заменить
  `App\Shared\Infrastructure\Bus\QueryBusInterface` на
  `Tools\Cqrs\QueryBusInterface`.
- В примере `docs/code-examples.md` создавать Query DTO до closure, чтобы
  `LoggingMiddleware` получил имя операции из захваченного объекта.
- Обновить `docs/arch.md`: явно указать, что инфраструктура шины живёт в
  `tools/cqrs`, а прикладные Command, Query и Handler остаются в модулях.
- Добавить интеграционный тест приложения в `tests/Feature`, который поднимает
  тестовый kernel и проверяет, что `CommandBusInterface`, `QueryBusInterface` и
  `AfterCommitActions` доступны из контейнера.
- В интеграционном тесте проверить, что `AfterCommitActions` является
  singleton в контейнере.
- В интеграционном тесте выполнить простой `CommandBusInterface::dispatch()` с
  operation, который возвращает значение, внутри штатной тестовой БД из Docker
  окружения и проверить, что результат дошёл до вызывающего кода.
- В интеграционном тесте выполнить простой `QueryBusInterface::dispatch()` и
  проверить возврат значения без транзакционной логики приложения.
- В интеграционном тесте приложения не проверять finalizer повторно: этот
  контракт покрывается package-local тестом bootloader-а через fake finalizer.

Результат: приложение подключает новый пакет, получает CQRS-сервисы из
контейнера и документация больше не показывает старый namespace.

Сценарии тестирования:

- Composer устанавливает `yoga-loka/cqrs-tools` из path repository
  `tools/cqrs` по constraint `dev-main`.
- Kernel регистрирует `CqrsBootloader`.
- DI-контейнер приложения отдаёт `Tools\Cqrs\CommandBusInterface`.
- DI-контейнер приложения отдаёт `Tools\Cqrs\QueryBusInterface`.
- DI-контейнер приложения отдаёт один и тот же экземпляр
  `Tools\Cqrs\AfterCommitActions` при повторном запросе.
- Пример в `docs/code-examples.md` использует `Tools\Cqrs\QueryBusInterface`.
- Пример в `docs/code-examples.md` захватывает Query DTO в closure, поэтому
  debug-лог получает имя операции.
- Архитектурное описание не оставляет впечатление, что bus живёт в `App\Shared`.

Проверка:

- `composer update yoga-loka/cqrs-tools --with-dependencies --no-interaction`
- `make test`
- `make phpstan`

### 3. Проверить полный контур пакета и приложения

Цель: убедиться, что изолированный пакет и приложение проходят проверки вместе,
а изменения не ломают уже существующие tools-пакеты.

Что сделать:

- Запустить тесты и PHPStan нового пакета после подключения его к root-проекту.
- Запустить root-проверки через Docker, потому что тесты приложения используют
  сервисы Docker-сети.
- Проверить, что существующие пакеты `tools/openapi` и `tools/api-error` не
  требуют изменения своих composer-файлов из-за нового пакета.
- Проверить, что `composer.lock` содержит path-пакет `yoga-loka/cqrs-tools`,
  а diff lock-файлов не обновил сторонние зависимости сверх нужного
  Composer-изменения.
- Проверить `docs/code-examples.md` и `docs/arch.md` поиском по старому
  namespace `App\Shared\Infrastructure\Bus`.
- Проверить production-код и тесты поиском по старому namespace
  `App\Shared\Infrastructure\Bus` и по старому namespace источника
  `App\Infrastructure\Bus`, исключая `vendor`.

Результат: изменения готовы к ревью одним набором, а package-local проверки и
проверки приложения имеют понятный зелёный результат.

Сценарии тестирования:

- Новый package-local test suite проходит полностью.
- Новый package-local PHPStan проходит на максимальном уровне.
- Root test suite проходит через `make test`.
- Root PHPStan проходит через `make phpstan`.
- Старые namespace bus не встречаются в production-коде и документации, кроме
  ссылок на research как исторический источник.
- Composer lock содержит только ожидаемые изменения для нового path-пакета.
- `tools/cqrs/composer.lock` сохранён и относится только к зависимостям нового
  tools-пакета.

Проверка:

- `composer -d tools/cqrs test`
- `composer -d tools/cqrs phpstan`
- `make test`
- `make phpstan`
- `! grep -RIn "App\\\\Shared\\\\Infrastructure\\\\Bus\\|App\\\\Infrastructure\\\\Bus" app tests tools --exclude-dir=vendor`
- `! grep -RIn "App\\\\Shared\\\\Infrastructure\\\\Bus" docs/code-examples.md docs/arch.md`

## Тесты

Стратегия: `after_each_phase`. После каждой фазы нужно писать или обновлять
тесты для изменённого поведения и запускать проверки этой фазы.

В первой фазе тесты живут внутри `tools/cqrs/tests`, потому что пакет должен
проверяться отдельно от приложения. Основной упор: порядок middleware,
generic-контракт `dispatch()`, транзакционное поведение, callbacks после
commit/rollback, логирование и переносимость без `App\`.

Во второй фазе тесты живут в root `tests`, потому что проверяется интеграция
пакета со Spiral Kernel и DI-контейнером приложения. Основной упор: bootloader
зарегистрирован, интерфейсы доступны, dispatch работает через контейнер.
Интеграционные тесты размещаются в `tests/Feature`, потому что `tests/App`
используется как поддерживающий код тестового kernel и сам по себе не является
test suite.

В третьей фазе отдельный новый код не добавляется. Эта фаза закрывает полный
набор проверок: package-local tests, package-local PHPStan, `make test` и
`make phpstan`. Root PHPStan проверяет production-код приложения; типовые
ошибки в root-тестах закрываются запуском PHPUnit через `make test`.

## Логирование

Стратегия: `debug_precise`. Пакет должен давать подробные debug-логи по
важным шагам шины без пользовательских значений и секретов.

`LoggingMiddleware` пишет два debug-сообщения на каждую операцию: старт
операции и время выполнения в миллисекундах. В сообщении используется только
имя Command или Query класса, а если имя не найдено, строка `Unknown`.
Документация и примеры показывают создание DTO до closure, чтобы нормальный
прикладной код получал имя операции в debug-логе.

`TransactionalMiddleware` не пишет info-логи для обычного успешного flow, чтобы
не дублировать debug-логи шины. Warning пишется только для ошибки внутри
afterCommit или afterRollback callback, потому что это уже проблема фонового
побочного действия, а не нормальный пользовательский сценарий.

## Документация и эксплуатация

- Обновить `tools/cqrs/README.md` с назначением пакета, примером использования,
  командами проверки и правилом про `AfterCommitActions`.
- Обновить `docs/code-examples.md`, чтобы новые контроллеры импортировали
  `Tools\Cqrs\QueryBusInterface`.
- Обновить `docs/arch.md`, чтобы CQRS-раздел прямо называл пакет `tools/cqrs`
  как место инфраструктуры шины.
- Для релиза не нужны миграции, backfill, новые переменные окружения и ручные
  эксплуатационные действия.
- После реализации сохранить изменения `composer.json` и `composer.lock`
  вместе с кодом пакета, потому что без lock-файла приложение не увидит новый
  path-пакет стабильно.
- Сохранить `tools/cqrs/composer.lock` вместе с пакетом, как это сделано у
  соседних tools-пакетов.

## Изменения после мета-ревью

### После моделей

- **+ Добавлено:** точный root constraint `"yoga-loka/cqrs-tools": "dev-main"`.
- **+ Добавлено:** `autoload-dev` для `Tools\Cqrs\Tests\` и ожидаемый
  `tools/cqrs/composer.lock`.
- **+ Добавлено:** проверки finalizer в package-local тесте `CqrsBootloader`.
- **+ Добавлено:** явный контракт вложенного `CommandBus::dispatch()` и
  предупреждение, что внешние побочные эффекты идут через outbox.
- **+ Добавлено:** правило для примеров: Command или Query DTO создаётся до
  closure, чтобы debug-лог получил имя операции.
- **~ Изменено:** контракт `dispatch()` и `BusMiddlewareInterface::handle()`
  теперь описан без native `: mixed`; тип результата задаётся generic PHPDoc.
- **~ Изменено:** проверки старых namespace разделены для production-кода и
  документации, чтобы research с историческими ссылками не ломал проверку.
- **~ Изменено:** root-интеграционные тесты уточнены как `tests/Feature`, а
  package-local bootloader test использует fake finalizer, fake database и fake
  logger.
- **Отклонено:** менять поведение вложенного `afterCommit` на ожидание
  внешнего commit. Причина: research подтвердил перенос старой реализации, а
  внешние побочные эффекты по архитектуре должны идти через transactional
  outbox.

## Прогресс выполнения

Журнал: `docs/executions/2026-05-22_18-10_cqrs-tools-package.md`

- [x] Шаг 1: Создать пакет `tools/cqrs`
- [x] Шаг 2: Подключить пакет к приложению
- [x] Шаг 3: Проверить полный контур пакета и приложения
