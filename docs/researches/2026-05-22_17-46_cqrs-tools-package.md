---
title: CQRS как tools-пакет
date: 2026-05-22 17:46
mode: normal
decision_mode: recommend_and_ask
status: draft
reviewer: none
sources:
  rules: docs/rules.md
  arch: docs/arch.md
  old_impl: ../yoga-loka-spiral/app/src/Infrastructure/Bus
  tools_pattern: tools/openapi, tools/api-error
---

# CQRS как tools-пакет

## Суть

Исследовали, как вынести инфраструктуру CQRS в отдельный Composer-пакет внутри
`tools/cqrs`, опираясь на рабочую реализацию из соседнего проекта
`../yoga-loka-spiral`.

CQRS здесь означает простое разделение сценариев записи и чтения:
Command меняет состояние, Query читает данные. В текущей архитектуре это уже
зафиксировано как правило приложения: сценарии живут в
`Modules/{Module}/Application/Command/...` и
`Modules/{Module}/Application/Query/...`, а шина принимает `callable`, чтобы
сохранять точный тип результата без ручного приведения
(`docs/arch.md:372`, `docs/arch.md:411`).

Готовность исследования: выбран состав пакета, публичный API, границы с
приложением, зависимости, риски и способ интеграции, чтобы дальше можно было
переходить к `eda-plan`.

## Решение

Выбран вариант: создать пакет `tools/cqrs` с Composer name
`yoga-loka/cqrs-tools` и namespace `Tools\Cqrs`. Приложение должно использовать
интерфейсы пакета напрямую: `Tools\Cqrs\CommandBusInterface` и
`Tools\Cqrs\QueryBusInterface`, без дублирующих обёрток в `App\Shared`.

Причина: другие переносимые части проекта уже оформлены как tools-пакеты с
собственным namespace `Tools\...`, своим `composer.json`, локальным `vendor`,
`bootstrap.php`, `phpunit.xml` и `phpstan.neon`
(`docs/rules.md:94`, `composer.json:57`, `tools/openapi/composer.json:19`,
`tools/api-error/composer.json:18`). Прямой импорт `Tools\Cqrs` убирает лишний
слой, который не добавляет поведения.

```text
+----------------------+------------------------------+-----------------------------+
| Часть                | Где живёт                     | Источник решения            |
+----------------------+------------------------------+-----------------------------+
| Command DTO          | app/src/Modules/...           | docs/rules.md:64,77         |
| Query DTO            | app/src/Modules/...           | docs/rules.md:64,77         |
| Handler              | app/src/Modules/...           | docs/arch.md:379,391        |
| Repository           | app/src/Modules/...           | docs/rules.md:65,68,69      |
| CommandBus/QueryBus  | tools/cqrs                    | old CommandBus/QueryBus     |
| Middleware шины      | tools/cqrs                    | old middleware              |
| Spiral Bootloader    | tools/cqrs                    | old BusBootloader           |
+----------------------+------------------------------+-----------------------------+
```

В пакет входит полный перенос инфраструктуры шины:

- `CommandBusInterface`, `QueryBusInterface`;
- `CommandBus`, `QueryBus`;
- `BusMiddlewareInterface`;
- `Middleware\LoggingMiddleware`;
- `Middleware\TransactionalMiddleware`;
- `AfterCommitActions`, `AfterCommitFrame`;
- `Bootloader\CqrsBootloader`.

CommandBus и QueryBus оставляют текущий подход: `dispatch()` принимает
`callable`, а не сам Command/Query объект. Это совпадает с архитектурой проекта
(`docs/arch.md:413`) и старой реализацией, где pipeline строится из middleware
и в конце вызывает переданную операцию
(`../yoga-loka-spiral/app/src/Infrastructure/Bus/CommandBus.php:16`,
`../yoga-loka-spiral/app/src/Infrastructure/Bus/QueryBus.php:16`).

```text
HTTP / Console / Job / Temporal
  -> App Command или Query DTO
  -> Tools\Cqrs\CommandBusInterface или Tools\Cqrs\QueryBusInterface
  -> Tools\Cqrs middleware
  -> App Handler::handle(...)
  -> App Domain / Repository / Result DTO
```

`CqrsBootloader` должен регистрировать:

- `AfterCommitActions` как singleton;
- `CommandBusInterface` как `CommandBus` с цепочкой
  `LoggingMiddleware -> TransactionalMiddleware`;
- `QueryBusInterface` как `QueryBus` с цепочкой `LoggingMiddleware`;
- finalizer, который вызывает `AfterCommitActions::reset()` между запросами
  RoadRunner, как в старом `BusBootloader`
  (`../yoga-loka-spiral/app/src/Application/Bootloader/BusBootloader.php:19`,
  `../yoga-loka-spiral/app/src/Application/Bootloader/BusBootloader.php:28`).

Транзакции остаются только у Command. Это уже описано в архитектуре проекта:
CommandBus использует `TransactionalMiddleware`, QueryBus его не использует
(`docs/arch.md:426`). Старый `TransactionalMiddleware` открывает frame для
отложенных действий, выполняет database transaction, на ошибке запускает
rollback callbacks и пробрасывает исключение дальше
(`../yoga-loka-spiral/app/src/Infrastructure/Bus/Middleware/TransactionalMiddleware.php:20`).

`AfterCommitActions` переносится вместе с транзакционным middleware, потому что
это часть одного поведения: код приложения может поставить действие после commit
или после rollback, а middleware выполнит его в нужный момент
(`../yoga-loka-spiral/app/src/Infrastructure/Bus/AfterCommitActions.php:24`,
`../yoga-loka-spiral/app/src/Infrastructure/Bus/AfterCommitFrame.php:21`).
Риск: вызов `afterCommit()` вне CommandBus dispatch не имеет активного frame и
может скрыть ошибку сценария. Этот риск закрывается правилом пакета: публичная
документация и тесты фиксируют, что `AfterCommitActions` поддержан только внутри
CommandBus operation; приложение не использует его как общий event dispatcher.

Логирование переносится как простая middleware. Старый код логирует начало и
время выполнения, а имя операции пытается извлечь из захваченных объектов
Command/Query в closure через reflection
(`../yoga-loka-spiral/app/src/Infrastructure/Bus/Middleware/LoggingMiddleware.php:16`,
`../yoga-loka-spiral/app/src/Infrastructure/Bus/Middleware/LoggingMiddleware.php:31`).
Риск: если closure не захватывает Command/Query объект, в лог попадёт
`Unknown`. Это не ломает выполнение, поэтому риск принимается. Улучшать это
через обязательные marker-интерфейсы сейчас не нужно: текущая архитектура
специально выбрала `callable`, чтобы не терять тип результата
(`docs/arch.md:413`).

Пакет не должен содержать:

- базовые `Command` или `Query` интерфейсы-маркеры;
- автоматический поиск Handler-ов;
- сервис-локатор для Handler-ов;
- доменные DTO, `PaginatedResult<T>`, репозитории или Entity;
- правила именования папок Command/Query.

Эти вещи остаются правилами приложения и документацией, потому что завязаны на
модульную структуру YogaLoka (`docs/rules.md:77`, `docs/rules.md:78`).

Проверка версий не требует внешнего поиска: нужные версии уже зафиксированы в
lock-файле проекта. Для нового `tools/cqrs` достаточно брать версии,
совместимые с текущим lock:

```text
+----------------------+------------------+-----------------------------------+
| Пакет                | Версия в проекте | Зачем нужен                       |
+----------------------+------------------+-----------------------------------+
| php                  | >=8.5 <8.6       | единый runtime проекта            |
| psr/log              | 3.0.2            | LoggingMiddleware                 |
| cycle/database       | 2.16.0           | TransactionalMiddleware           |
| spiral/framework     | 3.16.2           | Bootloader и Finalizer integration|
+----------------------+------------------+-----------------------------------+
```

Источники версий: `composer.json:12`, `composer.lock:764`,
`composer.lock:4627`, `composer.lock:5896`.

Рекомендуемый `composer.json` пакета на уровне подхода:

```json
{
  "name": "yoga-loka/cqrs-tools",
  "type": "library",
  "require": {
    "php": ">=8.5 <8.6",
    "cycle/database": "^2.16",
    "psr/log": "^3.0",
    "spiral/framework": "^3.16"
  },
  "autoload": {
    "psr-4": {
      "Tools\\Cqrs\\": "src"
    }
  }
}
```

`spiral/framework` выбран вместо более мелких Spiral-пакетов, потому что уже
так подключены соседние tools-пакеты (`tools/openapi/composer.json:11`,
`tools/api-error/composer.json:11`). Это чуть шире минимальной зависимости, но
сохраняет единый стиль локальных packages и снижает риск несовместимых
подверсий Spiral.

Интеграция в приложение должна быть прямой:

- добавить path repository `tools/cqrs` в корневой `composer.json` рядом с
  `tools/openapi` и `tools/api-error` (`composer.json:57`);
- добавить production-зависимость `yoga-loka/cqrs-tools`;
- подключить `Tools\Cqrs\Bootloader\CqrsBootloader` в Kernel рядом с другими
  tools bootloader-ами (`app/src/Shared/Infrastructure/Framework/Kernel.php:36`,
  `app/src/Shared/Infrastructure/Framework/Kernel.php:129`);
- обновить импорты в примерах и будущих контроллерах с
  `App\Shared\Infrastructure\Bus\...` на `Tools\Cqrs\...`
  (`docs/code-examples.md:183`).

## Ответы на вопросы

Вопрос: какой объём CQRS-пакета фиксируем и какой публичный API использовать?

Ответ пользователя: вариант 1.

Зафиксированное решение: полный перенос в `tools/cqrs` с публичным API
`Tools\Cqrs`, без промежуточных `App`-обёрток. В пакет входят Bus, логирование,
транзакции, `afterCommit/afterRollback` и Spiral bootloader.

## Итог

Дальше нужно планировать не новый CQRS-фреймворк, а перенос уже найденной
инфраструктуры в изолированный package `tools/cqrs`. Command/Query/Handler
остаются в модулях приложения, а пакет даёт только шину, middleware,
транзакционную обвязку, отложенные действия после commit/rollback и Spiral
bootloader. Основной публичный контракт приложения после переноса:
`Tools\Cqrs\CommandBusInterface` и `Tools\Cqrs\QueryBusInterface`.
