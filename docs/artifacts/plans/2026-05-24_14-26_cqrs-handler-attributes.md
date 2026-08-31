---
title: CQRS через атрибуты Handler
date: 2026-05-24 14:26
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
  research: docs/researches/2026-05-24_14-19_cqrs-attributes-no-stateful-hooks.md
---

# План реализации

## Задача

Коротко: заменить текущую CQRS-шину на исполнение через атрибуты на
`Handler::handle()`: `#[Transactional]` включает транзакцию, `#[LogOperation]`
включает debug-логирование операции, а отсутствие атрибута означает выполнение
без транзакции и без operation-лога.

Готовый результат: пакет `tools/cqrs` больше не содержит stateful
`AfterCommitActions`, lifecycle hooks и `TransactionalMiddleware`;
`CommandBusInterface` и `QueryBusInterface` принимают DTO и callable Handler-а;
транзакции и логи включаются только атрибутами на методе `handle()`; форма
вызова `handler: $handler->handle(...)` проверяется PHPStan-правилом внутри
`tools/cqrs`; приложение, тесты и документация используют новый контракт.

## Контекст

Текущий пакет `tools/cqrs` уже подключён как локальный Composer-пакет
`yoga-loka/cqrs-tools`. Он содержит `CommandBus`, `QueryBus`, middleware,
`AfterCommitActions`, `AfterCommitFrame` и `CqrsBootloader`.

Сейчас `CommandBus::dispatch()` и `QueryBus::dispatch()` принимают только
`callable`. `CommandBus` всегда запускает цепочку
`LoggingMiddleware -> TransactionalMiddleware -> callable`, поэтому любая
команда всегда транзакционная. `QueryBus` запускает `LoggingMiddleware ->
callable`.

`LoggingMiddleware` сейчас угадывает имя операции по объектам, захваченным
closure. Если DTO создан внутри closure или передан не closure-callable, имя
становится `Unknown`.

`TransactionalMiddleware` хранит callbacks через singleton `AfterCommitActions`.
В RoadRunner это долгоживущий объект со сбрасываемым состоянием. Исследование
зафиксировало риск: такой механизм легко начать использовать как скрытую
замену outbox.

Архитектура проекта уже требует: внешние побочные эффекты вроде email, push,
Centrifugo, webhooks и внешних API идут через transactional outbox, а не через
callbacks после commit.

В текущем приложении почти нет прикладных Command/Query Handler-ов. Найденные
места, которые точно нужно адаптировать: `tests/Feature/CqrsContainerTest.php`,
`tools/cqrs` tests, `tools/cqrs/README.md`, `docs/arch.md`,
`docs/rules.md` и `docs/code-examples.md`.

Новые пакеты устанавливать не нужно. PHP `>=8.5 <8.6`, `cycle/database`,
`spiral/framework` и `psr/log` уже есть в `composer.json` и lock-файлах.

В проекте уже есть локальный пакет `tools/phpstan` с именем
`yoga-loka/phpstan-rules`. В нём живут проектные PHPStan-правила:
строгие типы, именованные аргументы, запрет loose comparison и правила
контрактов типов. Новое CQRS-правило относится к контракту конкретного пакета
`tools/cqrs`, а не к универсальным правилам, поэтому оно должно жить внутри
`tools/cqrs`. При этом `tools/cqrs` должен подключать
`yoga-loka/phpstan-rules` как dev-зависимость, чтобы универсальные правила
тоже прогонялись внутри пакета.

## Принятые решения

- Размер плана: `normal`. Источник: ответ пользователя `1`.
- Режим принятия решений: `recommend_and_ask`. Все существенные решения
  подтверждены в research или текущим сообщением пользователя.
- Атрибуты ставятся на метод `Handler::handle()`, а не на Command или Query DTO.
  Источник: research с ответом пользователя.
- Добавляются только два атрибута: `#[Transactional]` и `#[LogOperation]`.
  Источник: research и текущее уточнение пользователя.
- `#[NonTransactional]` не добавляется. Если на `handle()` нет
  `#[Transactional]`, операция по умолчанию выполняется без транзакции.
  Источник: текущее сообщение пользователя.
- Защита от случайной записи без транзакции в этой задаче не делается через
  `#[NonTransactional]`. Для будущих write Handler-ов правило фиксируется в
  документации: если сценарий пишет в БД и должен быть атомарным, на
  `handle()` ставится `#[Transactional]`.
- Если на `handle()` нет `#[LogOperation]`, operation-log по умолчанию не
  пишется. Это сохраняет логирование как явное действие.
- Lifecycle hooks `beforeCommit`, `afterCommit`, `afterRollback`,
  `AfterCommitActions` и `AfterCommitFrame` удаляются из подхода. Источник:
  research с ответом пользователя.
- Внешние побочные эффекты не выполняются из CQRS-bus callbacks. Для таких
  эффектов остаётся только transactional outbox, как уже требует архитектура.
- Контракт `dispatch()` меняется на передачу DTO и callable Handler-а:

```php
/**
 * @template TInput of object
 * @template TResult
 * @param TInput $input
 * @param callable(TInput): TResult $handler
 * @return TResult
 */
public function dispatch(object $input, callable $handler);
```

- `EntityManager::run()` остаётся внутри Handler-а. Если Handler помечен
  `#[Transactional]`, весь dispatch оборачивается в
  `Cycle\Database\DatabaseInterface::transaction()`, а `run()` остаётся
  явным flush внутри сценария.
- `CommandBusInterface` и `QueryBusInterface` используют один и тот же принцип:
  атрибуты читаются с переданного callable Handler-а.
- `#[Transactional]` поддерживается только в `CommandBus`. Если
  `QueryBus` получает Query Handler с `#[Transactional]`, это ошибка
  контракта, которую должно поймать PHPStan-правило: Query не должен менять
  состояние, а транзакционный read-сценарий нужно планировать отдельным
  решением.
- CQRS-bus не регистрируется как singleton. `CqrsBootloader` должен перейти на
  обычные bindings для `CommandBusInterface` и `QueryBusInterface`, потому что
  bus больше не хранит состояние запроса.
- Форму второго аргумента `dispatch()` проверяет отдельное PHPStan-правило в
  `tools/cqrs`: `handler` должен быть first-class callable на метод
  `handle(...)`. Runtime guard для этой формы не добавляется.
- Новое PHPStan-правило не проверяет именованные аргументы, потому что в
  проекте уже есть отдельное правило `RequireNamedArgumentsRule`.
- Пакет `tools/cqrs` должен подключить `yoga-loka/phpstan-rules` как
  dev-зависимость через path repository на `../phpstan`, а его
  `phpstan.neon` должен включать
  `vendor/yoga-loka/phpstan-rules/extension.neon`.
- База данных, миграции, HTTP API, JSON-контракты, очереди, webhooks и внешние
  сервисы не меняются.

## Целевой алгоритм

1. Контроллер, консольная команда, задача очереди или Temporal-adapter создаёт
   Command или Query DTO.
2. Входной слой вызывает `dispatch(command, handler)` или
   `dispatch(query, handler)`.
3. В `handler` передаётся callable метода `handle()`, например
   `$createPostHandler->handle(...)`.
4. PHPStan до запуска проверяет, что в `handler` передан именно first-class
   callable на метод `handle(...)`, а не closure, array-callable или объект.
5. Bus определяет реальный метод Handler-а через reflection по callable.
6. Bus читает атрибуты с метода `handle()`.
7. Если найден `#[LogOperation]`, bus пишет debug-лог начала операции. Имя лога
   берётся из атрибута `name`, а если имя не задано, из короткого имени класса
   Handler-а.
8. Если `#[LogOperation]` не найден, bus не пишет operation-log.
9. Если `CommandBus` нашёл `#[Transactional]`, bus выполняет Handler внутри
   `Cycle\Database\DatabaseInterface::transaction()`.
10. Если `CommandBus` не нашёл `#[Transactional]`, bus сразу вызывает Handler
   без транзакции.
11. PHPStan запрещает `#[Transactional]` на Query Handler-е. При выполнении
    `QueryBus` не открывает транзакцию.
12. Handler выполняет сценарий: создаёт value object, работает с Entity и
    Repository, сам вызывает `EntityManager::run()` для записи.
13. Если Handler создаёт внешний побочный эффект, он сохраняет намерение в
    outbox в той же бизнес-транзакции. Bus не выполняет callbacks после commit.
14. Если Handler выбрасывает исключение, bus не ловит его ради бизнес-логики:
    исключение уходит дальше в существующий слой обработки ошибок.
15. Если был `#[LogOperation]`, bus через `finally` пишет debug-лог времени
    выполнения после успешного завершения или после ошибки. В логах нет
    пользовательских значений, секретов и персональных данных.

## Контракты реализации

### Данные и БД

Не затрагивается.

### API и внешние контракты

HTTP API, JSON-ответы, маршруты, права доступа, события очередей, webhooks и
внешние сервисы не меняются.

Меняется внутренний PHP-контракт локального пакета `tools/cqrs`:

- добавить `Tools\Cqrs\Attribute\Transactional`;
- добавить `Tools\Cqrs\Attribute\LogOperation`;
- удалить публичные классы `Tools\Cqrs\AfterCommitActions` и
  `Tools\Cqrs\AfterCommitFrame`;
- удалить публичный интерфейс `Tools\Cqrs\BusMiddlewareInterface`;
- удалить middleware `Tools\Cqrs\Middleware\LoggingMiddleware` и
  `Tools\Cqrs\Middleware\TransactionalMiddleware`;
- добавить внутренний stateless executor без публичного middleware API;
- изменить `Tools\Cqrs\CommandBusInterface::dispatch()`:

```php
/**
 * @template TCommand of object
 * @template TResult
 * @param TCommand $command
 * @param callable(TCommand): TResult $handler
 * @return TResult
 */
public function dispatch(object $command, callable $handler);
```

- изменить `Tools\Cqrs\QueryBusInterface::dispatch()`:

```php
/**
 * @template TQuery of object
 * @template TResult
 * @param TQuery $query
 * @param callable(TQuery): TResult $handler
 * @return TResult
 */
public function dispatch(object $query, callable $handler);
```

Меняется PHPStan-контракт проекта:

- добавить правило `Tools\Cqrs\PHPStan\Rules\RequireCqrsHandlerCallableRule` в
  `tools/cqrs`;
- зарегистрировать правило в package-local PHPStan-конфигурации `tools/cqrs`;
- правило проверяет вызовы `dispatch()` у
  `Tools\Cqrs\CommandBusInterface` и `Tools\Cqrs\QueryBusInterface`;
- правило не проверяет именованные аргументы, потому что это уже делает
  `RequireNamedArgumentsRule`;
- второй аргумент `handler` должен быть first-class callable на метод
  `handle(...)`;
- запрещены closure, array-callable, объект Handler-а без `->handle(...)` и
  любые другие формы, где bus не видит метод `handle()` заранее;
- для `QueryBusInterface::dispatch()` правило запрещает `#[Transactional]` на
  переданном `handle()`;
- PHPStan generic PHPDoc у `dispatch()` продолжает отвечать за совместимость
  типа DTO и return type Handler-а.

- `Transactional`:

```php
#[\Attribute(\Attribute::TARGET_METHOD)]
final readonly class Transactional {}
```

- `LogOperation`:

```php
#[\Attribute(\Attribute::TARGET_METHOD)]
final readonly class LogOperation
{
    public function __construct(
        public ?string $name = null,
    ) {}
}
```

## Фазы выполнения

### 1. Добавить CQRS PHPStan-правило внутри `tools/cqrs`

Цель: проверять форму вызова CQRS-bus до запуска кода и не добавлять тяжёлые
runtime-проверки в `tools/cqrs`.

Что сделать:

- В `tools/cqrs/src/PHPStan/Rules` добавить
  `RequireCqrsHandlerCallableRule`.
- Зарегистрировать правило в package-local PHPStan-конфигурации `tools/cqrs`.
- В правиле обрабатывать вызовы `dispatch()` только когда тип объекта —
  `Tools\Cqrs\CommandBusInterface` или `Tools\Cqrs\QueryBusInterface`.
- Не добавлять проверку именованных аргументов: существующий
  `RequireNamedArgumentsRule` уже отвечает за это.
- Проверять, что аргумент `handler` является first-class callable на метод
  `handle(...)`.
- Запрещать `handler` в виде closure, array-callable и объекта Handler-а.
- Для `QueryBusInterface::dispatch()` проверять атрибуты метода `handle()` и
  запрещать `#[Transactional]`.
- Не дублировать в новом правиле проверку совместимости DTO и return type:
  это остаётся задачей generic PHPDoc на `dispatch()`.
- Добавить unit-тесты правила в `tools/cqrs/tests` через
  `PHPStan\Testing\RuleTestCase`.
- В fixtures покрыть разрешённый вызов:
  `dispatch(command: $command, handler: $handler->handle(...))`.
- В fixtures покрыть запрещённые вызовы: closure, array-callable, объект
  Handler-а и `#[Transactional]` на Query Handler-е.

Результат: неправильная форма CQRS dispatch ловится командой PHPStan до
запуска приложения.

Сценарии тестирования:

- First-class callable `handler: $handler->handle(...)` разрешён.
- Closure вместо first-class callable запрещена.
- Array-callable вместо first-class callable запрещён.
- Передача объекта Handler-а вместо first-class callable запрещена.
- `#[Transactional]` на Query Handler-е запрещён.

Проверка:

- `composer -d tools/cqrs test`
- `composer -d tools/cqrs phpstan`

### 2. Перестроить CQRS-пакет на атрибуты

Цель: убрать stateful callbacks и сделать транзакции с логированием явными на
`Handler::handle()`.

Что сделать:

- В `tools/cqrs/src/Attribute` добавить `Transactional` и `LogOperation`.
- В `CommandBusInterface` и `QueryBusInterface` заменить контракт `dispatch()`
  на передачу DTO и callable Handler-а.
- В `CommandBus` и `QueryBus` убрать старый pipeline с middleware.
- Добавить один внутренний stateless executor, который читает атрибуты и
  вызывает Handler. `CommandBus` и `QueryBus` должны только передавать ему
  параметры своего сценария.
- В executor добавить чтение атрибутов с callable Handler-а.
- Поддержать чтение атрибутов для `$handler->handle(...)`.
- Основной поддерживаемый пользовательский вызов — first-class callable
  `$handler->handle(...)`; форму вызова контролирует PHPStan-правило из
  первой фазы.
- Runtime-проверку неправильной формы callable не добавлять.
- При `#[Transactional]` выполнять Handler внутри
  `DatabaseInterface::transaction()`.
- При отсутствии `#[Transactional]` вызывать Handler напрямую.
- В `QueryBus` не открывать транзакции. Запрет `#[Transactional]` на Query
  Handler-е обеспечивает PHPStan-правило из первой фазы.
- При `#[LogOperation]` писать debug-лог начала и debug-лог времени выполнения.
  Имя брать из `LogOperation::$name`; если имя не задано, брать короткое имя
  класса Handler-а.
- Не логировать Command/Query payload и пользовательские значения.
- Удалить `AfterCommitActions`, `AfterCommitFrame`,
  `BusMiddlewareInterface`, `TransactionalMiddleware`, `LoggingMiddleware` и
  связанные тесты старого поведения.
- Удалить тестовые helpers, которые станут мёртвым кодом:
  `tools/cqrs/tests/Support/RecordingMiddleware.php` и
  `tools/cqrs/tests/Support/TestFinalizer.php`.
- Удалить неиспользуемую зависимость `nyholm/psr7` из
  `tools/cqrs/composer.json`, потому что CQRS-пакет не работает с PSR-7.
- В `tools/cqrs/composer.json` добавить dev-зависимость
  `yoga-loka/phpstan-rules` и path repository на `../phpstan`.
- В `tools/cqrs/phpstan.neon` подключить
  `vendor/yoga-loka/phpstan-rules/extension.neon`, чтобы
  `composer -d tools/cqrs phpstan` запускал универсальные проектные правила.
- В `tools/cqrs/phpstan.neon` также подключить package-local CQRS-правило из
  первой фазы.
- Обновить `CqrsBootloader`: убрать `FinalizerInterface`,
  `AfterCommitActions` и singleton-регистрацию; зарегистрировать
  `CommandBusInterface` и `QueryBusInterface` через `defineBindings()`.
- Сохранить package-local формат `tools/cqrs`: отдельные `composer.json`,
  `phpunit.xml`, `phpstan.neon`, `bootstrap.php`, `vendor` и runtime-каталоги.
- После реализации фазы написать и обновить unit-тесты пакета.
- Покрыть тестами: `#[Transactional]` открывает транзакцию; отсутствие
  `#[Transactional]` не открывает транзакцию; при исключении Handler-а
  транзакция проходит через rollback внутри `DatabaseInterface::transaction()`;
  `#[LogOperation]` пишет два debug-лога; отсутствие `#[LogOperation]` не пишет
  operation-log.
- Покрыть тестами чтение атрибутов с `$handler->handle(...)`.
- Покрыть тестами имя операции для `#[LogOperation(name: '...')]` и fallback
  на короткое имя Handler-а, когда `name` не задан.
- Покрыть тестом, что при `#[LogOperation]` ошибка Handler-а пробрасывается, а
  debug-лог времени всё равно пишется через `finally`.
- Покрыть тестами: `dispatch()` передаёт DTO в Handler и возвращает строку,
  объект и `void` без ручного приведения типа.
- Покрыть тестами `CqrsBootloader`: bindings возвращают доступные
  `CommandBusInterface` и `QueryBusInterface`, а `AfterCommitActions` больше не
  регистрируется.
- Покрыть тестом `CqrsBootloader`, что bus зарегистрированы через
  `defineBindings()`, а не через `defineSingletons()`, и метод `init()` с
  `FinalizerInterface` удалён.
- Сохранить portability-тест: production-код `tools/cqrs/src` не содержит
  `namespace App\` и `use App\`.

Результат: пакет `tools/cqrs` работает без stateful callbacks, читает атрибуты
с Handler-а и сам проходит свои unit-тесты.

Сценарии тестирования:

- Handler с `#[Transactional]` выполняется внутри transaction.
- Handler без `#[Transactional]` выполняется без transaction.
- Handler с `#[LogOperation]` пишет debug-лог начала и времени.
- Handler без `#[LogOperation]` не пишет operation-log.
- Ошибка Handler-а пробрасывается без подмены.
- `AfterCommitActions` недоступен через bootloader и не используется в пакете.
- `composer -d tools/cqrs phpstan` запускает правила из
  `yoga-loka/phpstan-rules`.

Проверка:

- `composer -d tools/cqrs test`
- `composer -d tools/cqrs phpstan`

### 3. Адаптировать приложение и документацию

Цель: привести места использования и проектные правила к новому контракту CQRS.

Что сделать:

- Обновить `tests/Feature/CqrsContainerTest.php`: больше не получать
  `AfterCommitActions` из контейнера; проверять новый вызов `dispatch()` с DTO
  и callable Handler-а.
- Обновить `docs/arch.md`: заменить описание старого middleware pipeline на
  attribute-driven flow через `Handler::handle()`.
- В `docs/arch.md` явно записать, что отсутствие `#[Transactional]` означает
  выполнение без транзакции, а `#[NonTransactional]` не используется.
- В `docs/arch.md` явно записать, что hooks и after-commit callbacks не входят
  в CQRS-bus, а внешние эффекты идут через outbox.
- Обновить `docs/rules.md`: правило про `entityManager->run()` должно говорить,
  что `run()` остаётся в Handler-е, а транзакцию вокруг dispatch включает
  `#[Transactional]` на `handle()`.
- Обновить `docs/code-examples.md`: примеры вызова bus перевести на
  `dispatch(query: $query, handler: $getUserProfileHandler->handle(...))`.
- Обновить `tools/cqrs/README.md`: описать атрибуты, новый контракт
  `dispatch()`, отсутствие `AfterCommitActions`, отсутствие hooks и команды
  проверки.
- Проверить по проекту, что больше нет ссылок на `AfterCommitActions`,
  `AfterCommitFrame`, старые middleware и старый однопараметрический
  `dispatch(callable)`.
- Проверить по проекту, что не осталось `BusMiddlewareInterface`,
  `RecordingMiddleware`, `TestFinalizer` и неиспользуемой зависимости
  `nyholm/psr7` в `tools/cqrs`.
- После изменений фазы запустить приложение в Docker-проверках.

Результат: приложение и документация описывают один и тот же CQRS-подход, а
контейнерный тест подтверждает доступность новых bus-сервисов.

Сценарии тестирования:

- Контейнер Spiral отдаёт `CommandBusInterface` и `QueryBusInterface`.
- Вызов
  `CommandBusInterface::dispatch(command: $command, handler: $handler->handle(...))`
  возвращает результат Handler-а.
- Вызов
  `QueryBusInterface::dispatch(query: $query, handler: $handler->handle(...))`
  возвращает результат Handler-а.
- В проекте не осталось ссылок на удалённые after-commit классы.
- В проекте не осталось старых вызовов `dispatch(callable)`.

Проверка:

- `make test`
- `make phpstan`
- `composer -d tools/cqrs test`
- `composer -d tools/cqrs phpstan`

## Тесты

Стратегия: тесты писать и запускать после каждой фазы.

После каждой фазы проверяются unit-тесты и PHPStan пакета `tools/cqrs`.
При этом `tools/cqrs` должен запускать и своё CQRS-правило, и универсальные
правила из `yoga-loka/phpstan-rules`. После третьей фазы дополнительно
проверяется всё приложение через Docker-команды проекта.

## Логирование

Стратегия: `debug_precise`.

В новой CQRS-шине debug-логи пишутся только при `#[LogOperation]`.
Логи должны фиксировать старт операции и время выполнения. В логах нельзя
писать DTO payload, секреты, персональные данные, email, токены, имена файлов
пользователя и другие пользовательские значения.

Ошибки Handler-а не превращаются bus-ом в warning или error: они уходят в
существующий слой обработки ошибок. Это сохраняет единое место обработки ошибок
и не создаёт дублей в логах.

## Документация и эксплуатация

- Обновить `tools/cqrs/README.md`, `docs/arch.md`, `docs/rules.md` и
  `docs/code-examples.md`.
- Миграции, env-переменные и новые сервисы инфраструктуры не нужны.
- Перед релизом убедиться, что long-running RoadRunner runtime больше не
  держит stateful `AfterCommitActions` в контейнере.
- Для будущих write Handler-ов явно ставить `#[Transactional]`, если сценарий
  должен быть атомарным. Отсутствие атрибута технически означает выполнение
  без транзакции.

## Изменения после мета-ревью

### После моделей

- **+ Добавлено:** запрет `#[Transactional]` на Query Handler-е через
  PHPStan-правило и тест для этого правила.
- **+ Добавлено:** PHPStan-правило внутри `tools/cqrs`, которое разрешает
  только `handler: $handler->handle(...)` и запрещает closure, array-callable и
  передачу объекта Handler-а.
- **+ Добавлено:** dev-зависимость `tools/cqrs` от `yoga-loka/phpstan-rules`,
  чтобы универсальные правила из `tools/phpstan` прогонялись внутри CQRS-пакета.
- **+ Добавлено:** тесты чтения атрибутов с `$handler->handle(...)` в
  `tools/cqrs`.
- **+ Добавлено:** тесты rollback при исключении в транзакционном Handler-е и
  запись финального debug-лога через `finally`.
- **+ Добавлено:** удаление `BusMiddlewareInterface`, мёртвых test helper-ов и
  неиспользуемой зависимости `nyholm/psr7`.
- **~ Изменено:** `Transactional` упрощён до marker-атрибута без
  `isolationLevel`, потому что уровень изоляции не нужен для первой итерации.
- **~ Изменено:** план теперь однозначно удаляет middleware-слой и заменяет его
  одним внутренним stateless executor.
- **~ Изменено:** новое CQRS PHPStan-правило не проверяет именованные
  аргументы, потому что это уже делает существующий `RequireNamedArgumentsRule`.
- **Отклонено:** предложение вернуть `#[NonTransactional]` отклонено, потому
  что пользователь прямо уточнил: `NonTransactional` не нужен, отсутствие
  `#[Transactional]` означает нетранзакционное выполнение.
- **Отклонено:** автоматическая проверка всех будущих write Handler-ов без
  `#[Transactional]` не добавлена в этот план, потому что это отдельное правило
  статического анализа и оно не было подтверждено пользователем.

## Прогресс выполнения

Журнал: `docs/executions/2026-05-24_16-57_cqrs-handler-attributes.md`

- [x] Шаг 1: Добавить CQRS PHPStan-правило внутри `tools/cqrs`
- [x] Шаг 2: Перестроить CQRS-пакет на атрибуты
- [x] Шаг 3: Адаптировать приложение и документацию
- [x] Шаг 4: Финальная проверка
