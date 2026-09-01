# Эталоны кода

Название карточки повторяет имя элемента в коде: `Entity`, `Cycle Entity`, `Repository`, `Cycle Repository`, `Command handler`, `HTTP Controller`. Имя файла записывается в `kebab-case`, а объяснение остаётся на русском языке.

| Компонент | Когда применять | Карточка |
|---|---|---|
| Публичный контракт | При синхронном обращении одного модуля к другому | [Публичный контракт](references/public-contract.md) |
| Domain Enum | При добавлении закрытого набора доменных значений | [Domain Enum](references/domain-enum.md) |
| Доменное исключение | При ожидаемой ошибке бизнес-сценария | [Доменное исключение](references/domain-exception.md) |
| Command handler | Для сценария, изменяющего состояние | [Command handler](references/command-handler.md) |
| Query handler | Для сценария чтения без изменения состояния | [Query handler](references/query-handler.md) |
| Result DTO | Когда результат Query отличается от Entity | [Result DTO](references/result-dto.md) |
| HTTP Filter | Для типизированного приёма и проверки HTTP-данных | [HTTP Filter](references/http-filter.md) |
| HTTP Controller | Для передачи HTTP-запроса в Command или Query | [HTTP Controller](references/http-controller.md) |
| API Resource | Для типизированной формы ответа и OpenAPI | [API Resource](references/api-resource.md) |
| Entity | Для сущности или корня агрегата без зависимости от Cycle | [Entity](references/entity.md) |
| ValueObject | Для доменного значения с проверкой и поведением | [ValueObject](references/value-object.md) |
| Repository | Для чтения и сохранения агрегата через доменный интерфейс | [Repository](references/repository.md) |
| Cycle Entity | Для описания хранения Domain Entity через Cycle | [Cycle Entity](references/cycle-entity.md) |
| Mapper | Для преобразования Domain Entity и Cycle Entity | [Mapper](references/mapper.md) |
| Entity columns | Для имён колонок Cycle Entity в запросах | [Entity columns](references/entity-columns.md) |
| Typecast | Когда встроенного преобразования Cycle недостаточно | [Typecast](references/typecast.md) |
| Cycle Repository | Для реализации Domain Repository через Cycle | [Cycle Repository](references/cycle-repository.md) |
| Bootloader модуля | Для регистрации реализаций контрактов в Spiral | [Bootloader модуля](references/bootloader.md) |
| Typed config | Для новой секции конфигурации приложения или модуля | [Typed config](references/typed-config.md) |
| Интеграционный тест | Для проверки маршрута через настоящий Spiral runtime | [Интеграционный тест](references/integration-test.md) |
