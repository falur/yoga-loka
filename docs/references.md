# Эталоны кода

Название карточки повторяет имя элемента в коде: `Entity`, `Cycle Entity`, `Repository`, `Cycle Repository`, `Command handler`, `HTTP Controller`. Имя файла записывается в `kebab-case`, а объяснение остаётся на русском языке.

| Компонент | Когда применять | Карточка |
|---|---|---|
| Публичный контракт | При синхронном обращении одного модуля к другому | [Публичный контракт](references/public-contract.md) |
| Публичный атрибут доступа | Когда маршрут объявляет требуемый доступ | [Публичный атрибут доступа](references/public-attribute.md) |
| Интеграционное событие | Когда факт уходит соседям асинхронно через outbox | [Интеграционное событие](references/integration-event.md) |
| Domain Enum | При добавлении закрытого набора доменных значений | [Domain Enum](references/domain-enum.md) |
| Доменное исключение | При ожидаемой ошибке бизнес-сценария | [Доменное исключение](references/domain-exception.md) |
| Domain Service | Для правила, которое не принадлежит ни одной сущности | [Domain Service](references/domain-service.md) |
| Domain Event | Для факта, который остаётся внутри модуля | [Domain Event](references/domain-event.md) |
| Типизированная коллекция | Для набора однородных значений вместо массива | [Типизированная коллекция](references/domain-collection.md) |
| Command handler | Для сценария, изменяющего состояние | [Command handler](references/command-handler.md) |
| Query handler | Для сценария чтения без изменения состояния | [Query handler](references/query-handler.md) |
| Result DTO | Когда результат Query отличается от Entity | [Result DTO](references/result-dto.md) |
| Технический порт Application | Для часов, хеширования, токенов, почты и внешних клиентов | [Технический порт Application](references/application-contract.md) |
| Data | Для данных, которые Reader читает из своих таблиц | [Data](references/data.md) |
| Reader | Для чтения данных модуля без загрузки агрегатов | [Reader](references/reader.md) |
| HTTP Filter | Для типизированного приёма и проверки HTTP-данных | [HTTP Filter](references/http-filter.md) |
| HTTP Controller | Для передачи HTTP-запроса в Command или Query | [HTTP Controller](references/http-controller.md) |
| API Resource | Для типизированной формы ответа и OpenAPI | [API Resource](references/api-resource.md) |
| HTTP Response | Для оболочки ответа: статуса, заголовков и верхнего уровня JSON | [HTTP Response](references/http-response.md) |
| HTTP Middleware | Для технической задачи HTTP-границы до и после Controller | [HTTP Middleware](references/http-middleware.md) |
| Консольная команда | Для регулярной или служебной операции из CLI | [Консольная команда](references/console-command.md) |
| Job-потребитель | Для асинхронной реакции на интеграционное событие | [Job-потребитель](references/job-consumer.md) |
| Entity | Для сущности или корня агрегата без зависимости от Cycle | [Entity](references/entity.md) |
| ValueObject | Для доменного значения с проверкой и поведением | [ValueObject](references/value-object.md) |
| Repository | Для чтения и сохранения агрегата через доменный интерфейс | [Repository](references/repository.md) |
| Cycle Entity | Для описания хранения Domain Entity через Cycle | [Cycle Entity](references/cycle-entity.md) |
| Mapper | Для преобразования Domain Entity и Cycle Entity | [Mapper](references/mapper.md) |
| Entity columns | Для имён колонок Cycle Entity в запросах | [Entity columns](references/entity-columns.md) |
| Typecast | Когда встроенного преобразования Cycle недостаточно | [Typecast](references/typecast.md) |
| Cycle Repository | Для реализации Domain Repository через Cycle | [Cycle Repository](references/cycle-repository.md) |
| Миграция | При любом изменении схемы своих таблиц | [Миграция](references/migration.md) |
| Bootloader модуля | Для регистрации реализаций контрактов в Spiral | [Bootloader модуля](references/bootloader.md) |
| Typed config | Для новой секции конфигурации приложения или модуля | [Typed config](references/typed-config.md) |
| Интеграционный тест | Для проверки маршрута через настоящий Spiral runtime | [Интеграционный тест](references/integration-test.md) |
| Unit-тест доменной логики | Для правила домена и сценария без Spiral и базы данных | [Unit-тест доменной логики](references/unit-test.md) |
