---
title: CQRS через атрибуты Handler без stateful hooks
date: 2026-05-24 14:19
mode: normal
decision_mode: recommend_and_ask
status: draft
reviewer: none
sources:
  rules: docs/rules.md
  arch: docs/arch.md
  php_attributes: https://www.php.net/manual/en/language.attributes.syntax.php
  php_reflection_attributes: https://www.php.net/manual/en/reflectionfunctionabstract.getattributes.php
  php_closures_const: https://wiki.php.net/rfc/closures_in_const_expr
---

# CQRS через атрибуты Handler без stateful hooks

## Суть

Исследовали, как заменить текущий CQRS-подход с middleware и singleton
`AfterCommitActions` на более явную модель через PHP-атрибуты.

Проблема текущего варианта: `AfterCommitActions` хранит стек callbacks в
объекте, зарегистрированном как singleton в `CqrsBootloader`
(`tools/cqrs/src/AfterCommitActions.php:7`,
`tools/cqrs/src/Bootloader/CqrsBootloader.php:22`). В RoadRunner это может
работать при обычном синхронном выполнении, но архитектурно это mutable state в
долгоживущем процессе, который требует `reset()` через finalizer
(`tools/cqrs/src/Bootloader/CqrsBootloader.php:32`). Такой механизм легко начать
использовать как скрытую замену outbox.

Цель исследования: выбрать новый подход, где транзакционность и логирование
задаются явно, без stateful singleton и без lifecycle callbacks.

## Решение

Выбранный вариант: атрибуты вешаются на метод `Handler::handle()`, а не на
Command DTO.

```php
final readonly class UpdateUserProfileHandler
{
    #[Transactional]
    #[LogOperation]
    public function handle(UpdateUserProfileCommand $command): UpdateUserProfileResult
    {
        // доменная логика
    }
}
```

Причина: Command в архитектуре проекта является DTO сценария, а логика
исполнения находится в Handler. Правила проекта уже закрепляют, что Command и
Query сценарии живут в `Application`, а каждый use-case группируется вокруг
действия и `Handler` (`docs/rules.md:77`, `docs/rules.md:78`). Архитектура
описывает поток как Controller -> Command DTO -> CommandBus -> Handler
(`docs/arch.md:247`).

Новый контракт bus на уровне подхода:

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

Использование из контроллера:

```php
$command = new UpdateUserProfileCommand(
    userId: $filter->userId,
    name: $filter->name,
);

$result = $commandBus->dispatch(
    command: $command,
    handler: $updateUserProfileHandler->handle(...),
);
```

Так bus получает не безымянную closure вида `fn() => $handler->handle($command)`,
а сам callable Handler-а. Это нужно, чтобы читать атрибуты именно с метода
`handle()`, а не угадывать команду через захваченные переменные. Текущий
`LoggingMiddleware` сейчас вынужден угадывать имя операции через reflection по
closure и захваченным объектам (`tools/cqrs/src/Middleware/LoggingMiddleware.php:37`).
PHP Reflection API умеет получать атрибуты функции или метода через
`ReflectionFunctionAbstract::getAttributes()`:
https://www.php.net/manual/en/reflectionfunctionabstract.getattributes.php.

Атрибуты:

```php
#[\Attribute(\Attribute::TARGET_METHOD)]
final readonly class Transactional
{
    public function __construct(
        public ?string $isolationLevel = null,
    ) {}
}

#[\Attribute(\Attribute::TARGET_METHOD)]
final readonly class LogOperation
{
    public function __construct(
        public ?string $name = null,
    ) {}
}
```

`#[Transactional]` означает: выполнить Handler внутри
`Cycle\Database\DatabaseInterface::transaction()`. `EntityManager::run()` при
этом остаётся внутри Handler, как требует проект (`docs/rules.md:74`,
`docs/arch.md:437`).

`#[LogOperation]` означает: писать debug-лог начала и времени выполнения.
Логирование остаётся opt-in: если атрибута нет, bus не пишет operation-log.
Это соответствует текущему правилу про DEBUG по умолчанию для бизнес-логов
(`docs/rules.md:70`), но убирает неявное угадывание имени операции.

Lifecycle hooks не добавляются:

```text
beforeCommit  - не добавляем
afterCommit   - не добавляем
afterRollback - не добавляем
```

Причина: внешние побочные эффекты в проекте уже должны идти через
transactional outbox (`docs/rules.md:72`, `docs/arch.md:443`). К таким действиям
относятся email, push, Centrifugo, webhooks, внешние API, очереди вне текущей
транзакции, S3/MinIO-файлы и поисковые индексы. Outbox сохраняет намерение
выполнить действие в БД вместе с бизнес-изменением, а отдельный worker делает
повторы и хранит статус доставки.

Почему hooks отклонены:

```text
+----------------+----------------------------------------------------------+
| Hook           | Почему не подходит как общий механизм                    |
+----------------+----------------------------------------------------------+
| beforeCommit   | Внешнее действие может выполниться, а commit потом упадёт |
| afterCommit    | Commit прошёл, но PHP-процесс может умереть до callback   |
| afterRollback  | Компенсация во внешнем мире тоже требует retry и статуса  |
+----------------+----------------------------------------------------------+
```

PHP 8.5 технически позволяет closures в constant expressions, включая параметры
атрибутов, но только static closures без `use(...)` и без `$this`
(официальный PHP RFC, target PHP 8.5: https://wiki.php.net/rfc/closures_in_const_expr).
Проект зафиксирован на PHP `>=8.5 <8.6` (`composer.json:12`). Даже с этой
возможностью callbacks в атрибутах остаются плохим местом для бизнес-эффектов,
потому что они не дают retry, статуса доставки и надёжного восстановления после
падения процесса. Поэтому callbacks в атрибутах не используются.

Регистрация в DI:

```text
CommandBusInterface -> обычный binding/factory, не singleton
QueryBusInterface   -> обычный binding/factory, не singleton
AfterCommitActions  -> удалить
AfterCommitFrame    -> удалить
```

Stateless-сервис сам по себе может быть singleton, но в этом решении CQRS-bus не
нужно регистрировать как singleton. Это снимает архитектурный спор и не создаёт
значимой стоимости: bus лёгкий, хранить request-state ему больше не нужно.

Архитектурный поток после изменения:

```text
HTTP / Console / Job / Temporal
  -> создаёт Command или Query DTO
  -> CommandBus::dispatch(command, handler)
    -> читает атрибуты Handler::handle()
    -> если #[LogOperation], пишет debug-лог
    -> если #[Transactional], открывает DB transaction
    -> вызывает Handler::handle(Command)
      -> Handler меняет доменную модель
      -> Handler вызывает EntityManager::run()
      -> Handler пишет outbox-событие для внешних эффектов
  -> Response / result
```

Для Query используется тот же принцип, но без транзакции по умолчанию. Query
Handler не меняет состояние (`docs/arch.md:404`). Если когда-нибудь появится
исключительный read-сценарий, которому нужна транзакция чтения, это должно быть
отдельным осознанным решением, а не поведением по умолчанию.

Чтобы не забывать атрибуты, нужен явный контроль правилом или тестом:

```text
Command Handler должен иметь один из маркеров:
- #[Transactional]
- #[NonTransactional]
```

Это важное ограничение. Иначе легко случайно оставить пишущий Handler без
транзакции. Для Query Handler `#[Transactional]` по умолчанию запрещён, потому
что Query не должен менять состояние (`docs/arch.md:406`).

Версии и источники:

```text
+--------------------+----------------------+-----------------------------------+
| Что                | Зафиксировано        | Источник                          |
+--------------------+----------------------+-----------------------------------+
| PHP проекта        | >=8.5 <8.6           | composer.json:12                  |
| Cycle Database     | 2.16.0 root lock     | composer.lock:764                 |
| Spiral Framework   | 3.16.2 root lock     | composer.lock:5896                |
| PSR Log            | 3.0.2 root lock      | composer.lock:4627                |
| PHP attributes     | args = constants     | php.net attribute syntax          |
| Closures in attrs  | PHP 8.5, static only | PHP RFC closures_in_const_expr    |
+--------------------+----------------------+-----------------------------------+
```

## Ответы на вопросы

Вопрос: куда вешать атрибуты - на Command или на Handler?

Ответ пользователя: атрибуты вешаем на Handler.

Принятое решение: `#[Transactional]` и `#[LogOperation]` ставятся на
`Handler::handle()`. Command и Query остаются простыми DTO.

Вопрос: добавлять ли `beforeCommit`, `afterCommit`, `afterRollback` hooks?

Ответ пользователя: hooks не добавляем, только транзакции.

Принятое решение: `AfterCommitActions` и `AfterCommitFrame` удаляются из
подхода. Внешние побочные эффекты идут только через outbox.

## Итог

Дальше планировать нужно не доработку текущего middleware-stack, а замену CQRS
пакета на stateless attribute-driven executor:

- `CommandBus::dispatch(command, handler)` и `QueryBus::dispatch(query, handler)`;
- атрибуты `#[Transactional]`, `#[NonTransactional]` и `#[LogOperation]` на
  `Handler::handle()`;
- без `AfterCommitActions`, `AfterCommitFrame`, `beforeCommit`, `afterCommit` и
  `afterRollback`;
- регистрация bus-сервисов как обычных bindings, не singleton;
- внешние эффекты только через transactional outbox.
