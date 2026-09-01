---
title: Границы Spiral и структура тестов модулей
date: 2026-09-01 10:41
mode: normal
decision_mode: ask_each_time
status: draft
reviewer: none
sources:
  rules: docs/rules.md
  arch: docs/arch.md
  business: []
  references: [docs/references/bootloader.md, docs/references/integration-test.md]
---

# Границы Spiral и структура тестов модулей

## Суть

Исследовали, как изменить целевую структуру модулей, чтобы код конкретного framework был собран в явной границе и его можно было заменить без изменения Domain и Application. Отдельно определили структуру тестов, которая показывает, какие проверки независимы от Spiral, а какие проверяют его адаптеры.

Текущая целевая структура оставляет `Presentation` отдельным верхнеуровневым слоем, а `Bootloader` — непосредственно в `Infrastructure` (`docs/arch.md:80`, `docs/arch.md:96`, `docs/arch.md:121`). Это не соответствует выбранной границе: HTTP-контроллеры, Filter, middleware, console-команды и Job являются адаптерами Spiral и должны находиться вместе с остальным кодом этого framework. Само требование не меняет бизнес-поведение, поэтому применимых бизнес-карточек нет.

## Решение

### Структура рабочего кода

В каждом модуле весь код, который зависит от Spiral, находится в `Infrastructure/Spiral`. Отдельного верхнеуровневого `Presentation` и дополнительной папки `Infrastructure/Spiral/Presentation` нет.

```text
Modules/
  {Module}/
    Public/
    Domain/
    Application/
    Infrastructure/
      Spiral/
        Bootloader/
        Configuration/
        Http/
          Controller/
          Filter/
          Middleware/
          Resource/
          Response/
        Console/
        Job/
        Temporal/
        Auth/
        Mail/
        Resources/
          locale/
          views/
      Persistence/
        Cycle/
          Migration/
          Columns/
          Entity/
          Mapper/
          Repository/
          Typecast/
      PublicApi/
      Cache/
      Client/
      Storage/
    Tests/
```

Папки внутри `Infrastructure/Spiral` создаются только при наличии соответствующего адаптера. Например, `Mail` находится там, если реализация использует `Spiral\\Mailer`; независимый SMTP-адаптер или адаптер другого поставщика получает собственную явно названную границу.

Ресурсы, которые Spiral регистрирует и загружает вместе с модулем, находятся в `Infrastructure/Spiral/Resources`. К ним относятся переводы и шаблоны представления. Это делает границу полной: при замене Spiral рядом с его PHP-адаптерами находятся связанные пути загрузки и файлы. В текущем `Auth` шаблон `login-code.twig` лежит в `Presentation/views`, а `AuthBootloader` регистрирует этот путь через `Spiral\\Views` (`app/src/Modules/Auth/Presentation/views/login-code.twig:1`, `app/src/Modules/Auth/Infrastructure/Bootloader/AuthBootloader.php:23`, `app/src/Modules/Auth/Infrastructure/Bootloader/AuthBootloader.php:54`). В целевой структуре такой шаблон находится в `Infrastructure/Spiral/Resources/views`.

Входные адаптеры остаются отдельной архитектурной ролью, но не отдельным верхнеуровневым слоем. `Http`, `Console`, `Job` и `Temporal` только преобразуют внешний ввод в Command или Query, вызывают Application и преобразуют результат обратно. Бизнес-правила в них не размещаются. Это сохраняет ответственность, ранее описанную для `Presentation` (`docs/arch.md:177`), но делает технологическую зависимость видимой по пути файла.

`Domain` зависит только от PHP, своего домена и общих доменных типов, как уже требует архитектура (`docs/arch.md:149`). `Application` обращается к техническим возможностям через собственные интерфейсы в `Application/Contract` (`docs/arch.md:159`, `docs/arch.md:165`). Прямой импорт `Spiral\\...` в `Public`, `Domain` и `Application` запрещён. Это обязательная часть решения: один перенос файлов без такого правила не обеспечивает заменяемость framework.

Bootloader переносится в `Infrastructure/Spiral/Bootloader`, потому что он наследуется от класса Spiral и связывает модуль с его runtime (`docs/references/bootloader.md:5`, `docs/references/bootloader.md:22`). Общая композиция приложения следует тому же соглашению: вместо нейтрального имени `Shared/Infrastructure/Framework` используется явная граница `Shared/Infrastructure/Spiral`. Иначе конкретная реализация продолжит выглядеть как универсальная основа; сейчас Kernel прямо назван глобальной framework-композицией (`docs/arch.md:221`, `docs/arch.md:226`).

Cycle не переносится внутрь `Spiral`: это отдельная технология хранения и независимая причина изменения. Вся его реализация находится в `Infrastructure/Persistence/Cycle`, включая `Migration`, `Columns`, `Entity`, `Mapper`, `Repository` и `Typecast`. Это подтверждённое изменение относительно текущего варианта `docs/arch.md`, где `Migration` стоит рядом с `Cycle` (`docs/arch.md:84`, `docs/arch.md:85`). Текущие миграции наследуются от `Cycle\\Migrations\\Migration`, поэтому относятся именно к реализации Cycle (`app/database/migrations/20260615.141700_0_create_auth_domain_tables.php:7`). Если один класс одновременно реализует контракт Cycle и Spiral, его ответственности следует разделить на два адаптера; иначе замена Spiral всё равно потребует изменения Cycle-части.

### Структура тестов

Тесты одного модуля остаются внутри модуля, а глобальная папка `tests` предназначена только для сквозных и межмодульных сценариев (`docs/rules.md:67`, `docs/arch.md:133`). Выбранная структура:

```text
Modules/
  Auth/
    Tests/
      Unit/
        Domain/
        Application/
      Integration/
        Cycle/
        Spiral/
      Feature/
        Spiral/
```

- `Unit` проверяет Domain, Application и другую изолированную логику без запуска Spiral, базы, очередей и сети; это соответствует правилу быстрого набора (`docs/rules.md:71`).
- `Integration/Cycle` проверяет модели хранения, mapper, typecast и Repository через настоящие Cycle и PostgreSQL.
- `Integration/Spiral` проверяет bootloader, DI, middleware, mailer и другие отдельные адаптеры Spiral.
- `Feature/Spiral` проверяет входной сценарий через настоящий runtime: HTTP, console, Job или Temporal. Эталон HTTP-теста требует настоящий Spiral kernel и размещение теста внутри `Modules/Auth/Tests/Feature/Http` (`docs/references/integration-test.md:5`, `docs/references/integration-test.md:43`); после принятого решения framework уточняется дополнительным уровнем `Feature/Spiral/Http`.

Папка `Common` не используется. Она смешивает разные основания классификации и со временем перестаёт объяснять, требуется ли тесту только PHP, Cycle, PostgreSQL или другой адаптер. Вид проверки остаётся первым уровнем, технология — вторым только там, где она действительно участвует.

Тестовые namespace и автозагрузка должны настраиваться как dev-only: архитектура уже требует, чтобы тесты модуля не попадали в рабочую автозагрузку (`docs/arch.md:133`). Это нужно сохранить при физическом переносе тестов; выбор конкретной настройки Composer и PHPUnit относится к будущему плану реализации.

Новые пакеты и версии ПО не выбирались, поэтому проверка актуальных версий не требуется.

## Ответы на вопросы

1. Где размещать входные адаптеры: пользователь выбрал перенести `Presentation` внутрь `Infrastructure`.
2. Нужна ли промежуточная папка `Infrastructure/Spiral/Presentation`: окончательный ответ пользователя — нет. Выбрана прямая структура `Infrastructure/Spiral/Http`, `Console`, `Job` и далее.
3. Как делить тесты: пользователь согласовал структуру, где первый уровень — `Unit`, `Integration`, `Feature`, а `Cycle` и `Spiral` являются вторым уровнем для зависимых от технологии проверок.
4. Где хранить переводы и шаблоны модуля: пользователь выбрал `Infrastructure/Spiral/Resources`, потому что эти файлы загружает адаптер Spiral. Отдельного верхнеуровневого `Resources` у модуля нет.
5. Где хранить миграции: пользователь выбрал `Infrastructure/Persistence/Cycle/Migration`. `Migration` является частью конкретной реализации хранения, а не соседом `Cycle`.

## Итог

Целевая архитектура должна собирать все прямые зависимости от Spiral в `Modules/{Module}/Infrastructure/Spiral` и `Shared/Infrastructure/Spiral`. Верхнеуровневый `Presentation` удаляется как отдельный слой, но его роль входного адаптера сохраняется в папках `Http`, `Console`, `Job` и `Temporal`. Связанные переводы и шаблоны находятся в `Infrastructure/Spiral/Resources`. `Public`, `Domain` и `Application` не импортируют Spiral.

Cycle остаётся независимой технической границей в `Infrastructure/Persistence/Cycle`; его миграции находятся в `Infrastructure/Persistence/Cycle/Migration`.

Тесты модуля размещаются в `Tests/Unit`, `Tests/Integration/{Cycle,Spiral}` и `Tests/Feature/Spiral`; папка `Common` не вводится.
