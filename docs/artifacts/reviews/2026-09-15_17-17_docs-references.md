---
target: docs/references/ и индекс docs/references.md (соответствие docs/arch.md и docs/rules.md)
plan: none
execution: main
score: 45
status: reviewed
result: changes-required
date: 2026-09-15 17:17
checks:
  - name: correctness
    model: current
    status: completed
    reason: карточки содержат исполняемые примеры кода; проверены имена классов и API, на которые они ссылаются
  - name: architecture
    model: current
    status: completed
    reason: цель ревью — соответствие карточек слоям и путям docs/arch.md
  - name: rules
    model: current
    status: completed
    reason: базовая проверка; сверка карточек с docs/rules.md
  - name: references
    model: current
    status: skipped
    reason: карточки references сами являются target; отдельная сверка «код против карточки» неприменима
  - name: business
    model: current
    status: skipped
    reason: пользовательское и доменное поведение не менялось, изменений в коде нет
  - name: plan_alignment
    model: current
    status: skipped
    reason: план не указан и однозначно не определяется
  - name: code_quality
    model: current
    status: completed
    reason: примеры карточек копируются в код дословно, читаемость и согласованность примеров важны
  - name: tests
    model: current
    status: completed
    reason: в наборе есть карточка теста, а rules.md задаёт требования к тестам и покрытию
  - name: security
    model: current
    status: skipped
    reason: карточки не меняют границы доверия, обработку секретов и внешнего ввода
  - name: performance
    model: current
    status: skipped
    reason: горячие пути и запросы не менялись; пакетное чтение в карточках описано корректно
  - name: frontend
    model: current
    status: skipped
    reason: проект API-only, UI-части нет
  - name: api
    model: current
    status: completed
    reason: карточки описывают публичные контракты модулей и HTTP-форму ответа
  - name: database
    model: current
    status: completed
    reason: карточки описывают Cycle Entity, Columns, Repository, Reader и работу с таблицами
  - name: documentation
    model: current
    status: completed
    reason: проверяется полнота индекса docs/references.md относительно обязательных элементов arch.md
  - name: previous_reviews
    model: current
    status: skipped
    reason: target локальный, удалённого PR/MR с обсуждениями нет
---

# Ревью: карточки-эталоны docs/references против целевой архитектуры

## Оценка

**45/100.** Карточки в основном описывают текущее, ещё не перенесённое состояние кода, а не целевую архитектуру. Шесть из двадцати двух карточек указывают папки и namespace, которых в целевой структуре не существует (`Presentation`, `Infrastructure/Bootloader`, `Infrastructure/PublicApi`, `Infrastructure/Configuration`), а карточка Reader ссылается на несуществующий класс и обходит обязательные примитивы курсорной страницы из `docs/rules.md`. Поскольку эти файлы задуманы как образец для копирования при переезде, каждая такая ошибка тиражируется на все модули сразу. Отдельно снижает оценку то, что несколько карточек прямо противоречат ключевым правилам `arch.md` о ролях Repository, Reader, Data и Result, а обязательные механизмы (интеграционное событие с outbox, миграции, типизированные коллекции, публичный атрибут доступа) не покрыты ни одной карточкой.

## Покрытие

- **Execution:** main
- **Completed:** correctness, architecture, rules, code_quality, tests, api, database, documentation (все — текущей моделью агента)
- **Skipped:** references (карточки сами являются target), business (поведение не менялось), plan_alignment (плана нет), security (границы доверия не затронуты), performance (горячие пути не менялись), frontend (проект API-only), previous_reviews (локальный target)
- **Warnings:** нет
- **Previous reviews:** не проверялись, удалённых обсуждений у target нет

## Проблемы сверки с планом

Проверка плана пропущена: план не указан и не найден.

## Проблемы бизнес-логики

Проверка не запускалась: изменений пользовательского или доменного поведения нет, target — документация.

## Проблемы в коде

### 1. Карточки HTTP-слоя показывают слой Presentation, которого в целевой архитектуре нет

Три карточки HTTP-слоя целиком построены вокруг папки `Presentation`. В целевой структуре такой папки нет вообще: `arch.md` явно пишет, что «Отдельного верхнеуровневого `Presentation` и папки `Infrastructure/Spiral/Presentation` нет», а дерево модуля размещает контроллеры, фильтры и ресурсы в `Infrastructure/Spiral/Http/{Controller,Filter,Resource}`.

Сейчас в коде `app/src/Modules/*/Presentation` действительно существует — карточки просто зафиксировали дореформенное состояние. Именно поэтому они опасны: при переезде исполнитель, следуя эталону, воссоздаст удаляемый слой в новых модулях, и расхождение придётся вычищать повторно.

Отдельно в `api-resource.md` неверен и общий базовый класс: `App\Shared\Presentation\Http\Resource\AbstractResource`. В целевой структуре `Shared` состоит из `Domain`, `Application` и `Infrastructure/Spiral`, поэтому `Shared\Presentation` существовать не может.

#### Технические детали

- **Тип:** architecture
- **Тяжесть:** high
- **Файлы:** `docs/references/http-filter.md:16`, `docs/references/http-controller.md:16`, `docs/references/http-controller.md:21`, `docs/references/api-resource.md:16`, `docs/references/api-resource.md:19`
- **Что подтверждает проблему:** `docs/arch.md` (раздел «Структура», строка «Папки внутри `Infrastructure/Spiral` создаются только при наличии соответствующего адаптера. Отдельного верхнеуровневого `Presentation` и папки `Infrastructure/Spiral/Presentation` нет.») и дерево `Infrastructure/Spiral/Http/{Controller,Filter,Middleware,Resource,Response}`
- **Чем воспроизводится:** `grep -n "Presentation" docs/references/*.md` возвращает namespace `App\Modules\User\Presentation\Http\Filter`, `App\Modules\User\Presentation\Http\Controller`, `App\Modules\User\Presentation\Http\Resource`, `App\Shared\Presentation\Http\Resource`
- **Как исправить:** заменить в трёх карточках namespace на `App\Modules\User\Infrastructure\Spiral\Http\{Filter,Controller,Resource}`, а базовый класс Resource — на `App\Shared\Infrastructure\Spiral\Http\Resource\AbstractResource`; поправить импорт Filter внутри примера контроллера
- **Тесты:** не требуются, изменение документации; после переезда добавить в `make qa` проверку запрета namespace `*\Presentation\*` (например, правило PHPStan или grep-шаг)

### 2. Карточки Bootloader, публичного контракта и typed config пропускают уровень `Spiral` в Infrastructure

В целевом дереве все зависимости модуля от фреймворка собраны в `Infrastructure/Spiral`: `Infrastructure/Spiral/Bootloader`, `Infrastructure/Spiral/PublicApi`, `Infrastructure/Spiral/Configuration`. Карточки же показывают пути на уровень выше — `Infrastructure/Bootloader`, `Infrastructure/PublicApi`, `Infrastructure/Configuration`, — то есть снова текущее состояние кода.

Это не косметика: `arch.md` отдельно оговаривает, что `Infrastructure/Spiral` — граница, отделяющая Spiral от `Persistence`, `Cache`, `Client` и `Storage`, и что «Класс, реализующий одновременно контракт Cycle и Spiral, разделяется на два адаптера». Без уровня `Spiral` это разделение в новых модулях не возникнет.

Ошибка есть и в тексте карточек, а не только в примерах: `public-contract.md` в разделе «Что повторять» пишет «Реализация заканчивается на `Provider` и находится в `Infrastructure/PublicApi`», `bootloader.md` — «Публичный интерфейс связан с адаптером из `Infrastructure/PublicApi`», `typed-config.md` — «Конфиг модуля лежит в его `Infrastructure/Configuration`».

#### Технические детали

- **Тип:** architecture
- **Тяжесть:** high
- **Файлы:** `docs/references/public-contract.md:47`, `docs/references/public-contract.md:82`, `docs/references/bootloader.md:16`, `docs/references/bootloader.md:20`, `docs/references/bootloader.md:36`, `docs/references/typed-config.md:9`, `docs/references/typed-config.md:16`, `docs/references/typed-config.md:18`, `docs/references/typed-config.md:40`
- **Что подтверждает проблему:** дерево `docs/arch.md`: `Infrastructure/Spiral/{Bootloader,Configuration,PublicApi,Http,Console,Job,Temporal,Auth,Mail,Resources}`; раздел «Public»: «Реализация контракта живёт в `Infrastructure/Spiral/PublicApi`»
- **Чем воспроизводится:** `grep -rn "Infrastructure.PublicApi\|Infrastructure.Bootloader\|Infrastructure.Configuration" docs/references/` — ни одно вхождение не содержит сегмента `Spiral`
- **Как исправить:** во всех трёх карточках добавить сегмент `Spiral` в namespace примеров и в текст разделов «Что повторять» и «Допустимые варианты»; в `typed-config.md` общий конфиг указать как `Shared/Infrastructure/Spiral/Configuration`
- **Тесты:** не требуются; после переезда покрыть правилом статического анализа «класс, импортирующий `Spiral\...`, лежит в `Infrastructure/Spiral`»

### 3. Карточка Reader опирается на несуществующий класс и игнорирует обязательные примитивы курсорной страницы

Пример в `reader.md` внедряет `App\Shared\Infrastructure\Cycle\WhenQuery` и вызывает у него `select()`, `from()`, `where()`, `cursorById()`, `fetchAll()`. Класса `WhenQuery` в проекте нет ни в `app/src`, ни в `vendor`. Есть `App\Shared\Infrastructure\Cycle\WhenSelect`, и это не query builder, а наследник Cycle `Select` над Entity — методов `select()` со списком колонок и `from()` с именем таблицы у него нет. Скопированный из карточки Reader не заработает.

Вторая часть проблемы серьёзнее для архитектуры. `docs/rules.md` в разделе «Коллекции» требует: «Для курсорной страницы используй `WhenSelect::cursorById()` и `CursorSlice::fromOverfetched()`, пока эти общие примитивы остаются в проекте». Карточка делает выборку с запасом (`limit + 1`) и передаёт лимит в `PostPageData::fromDatabaseRows()`, то есть перекладывает нарезку страницы и вычисление курсора в фабрику Data. `CursorSlice` не упоминается ни в `reader.md`, ни в `data.md`. Эталон, обязательный к повторению, прямо расходится с обязательным правилом, и каждый новый Reader получит собственную реализацию пагинации.

#### Технические детали

- **Тип:** correctness
- **Тяжесть:** high
- **Файлы:** `docs/references/reader.md:39`, `docs/references/reader.md:48`, `docs/references/reader.md:53-62`, `docs/references/reader.md:99`
- **Что подтверждает проблему:** `app/src/Shared/Infrastructure/Cycle/WhenSelect.php:17` — `class WhenSelect extends Select`, метод `cursorById(string|null $cursor, int $limit): static`; `app/src/Shared/Domain/Pagination/CursorSlice.php:44` — `fromOverfetched(Collection $overfetched, int $limit, callable $cursorOf)`; `docs/rules.md`, раздел «Коллекции»
- **Чем воспроизводится:** `grep -rn "WhenQuery" app/src vendor` не даёт ни одного определения класса; `grep -rn "CursorSlice" docs/references/` не даёт ни одного вхождения
- **Как исправить:** переписать пример на фактически существующие примитивы: `WhenSelect` для условной части запроса и `cursorById()`, `CursorSlice::fromOverfetched()` для нарезки страницы и курсора; в разделе «Что повторять» назвать оба класса явно. Если для Reader нужен именно построитель запроса поверх сырых таблиц, а не `Select` над Entity, то такой общий примитив нужно сначала завести в `Shared` и только потом показывать в карточке
- **Тесты:** после появления первого Reader покрыть пагинацию тестом на границе страницы (полная страница, неполная страница, пустая выборка)

### 4. Три карточки указывают разные и несуществующие namespace для общих классов Cycle в Shared

Карточки расходятся между собой в том, где в `Shared` лежит инфраструктура Cycle. `reader.md` использует `App\Shared\Infrastructure\Cycle`, `cycle-repository.md` — `App\Shared\Infrastructure\Persistence\Cycle\AbstractCycleRepository`, `typecast.md` — `App\Shared\Infrastructure\Persistence\Cycle\Typecast`. В проекте существует только первый вариант, и базовый класс репозитория там называется иначе.

`arch.md` эту область не закрывает: в дереве `Shared/Infrastructure` перечислен только подраздел `Spiral/`, поэтому ни один из трёх вариантов не подтверждается архитектурой. Пока это не решено, исполнитель переезда выберет путь произвольно, и общие классы разъедутся по разным неймспейсам.

#### Технические детали

- **Тип:** architecture
- **Тяжесть:** medium
- **Файлы:** `docs/references/reader.md:39`, `docs/references/cycle-repository.md:24`, `docs/references/typecast.md:16`, `docs/references/typecast.md:103`
- **Что подтверждает проблему:** `app/src/Shared/Infrastructure/Cycle/` содержит `AbstractRepository.php`, `WhenSelect.php`, `ColumnValueTypecast.php`, `ValueObjectCast.php`; каталога `app/src/Shared/Infrastructure/Persistence` не существует; в `docs/arch.md` под `Shared/Infrastructure` указан только `Spiral/`
- **Чем воспроизводится:** `ls app/src/Shared/Infrastructure` даёт `Cache Configuration Cycle Database Exception Framework` — подкаталога `Persistence` нет; `grep -rn "AbstractCycleRepository" app/src` пусто, фактическое имя — `AbstractRepository`
- **Как исправить:** зафиксировать целевой путь общих классов Cycle в `docs/arch.md` (например, `Shared/Infrastructure/Persistence/Cycle` по аналогии с модулем) и привести все три карточки к нему, одновременно согласовав имя базового класса репозитория
- **Тесты:** не требуются

### 5. Карточка Reader объявляет себя единственным способом чтения и отменяет роль Repository в Query

`reader.md` в разделе «Назначение» утверждает: «Это единственный способ получить данные для показа: доменные агрегаты ради ответа не загружаются». `arch.md` говорит обратное и описывает выбор из двух вариантов: «Свою часть handler берёт через Repository, когда ответу хватает полей агрегата, и через Reader, когда ответу нужен признак, которого в агрегате нет: флаг по зрителю, счётчик из соседней таблицы, склейка нескольких таблиц».

Формулировка карточки противоречит и соседним карточкам: пример в `query-handler.md` и интерфейс в `repository.md` как раз показывают чтение через Repository. Исполнитель, следующий Reader-карточке буквально, заведёт Reader и Data под каждый простой Query и продублирует выборку, которая уже есть у Repository агрегата.

#### Технические детали

- **Тип:** architecture
- **Тяжесть:** medium
- **Файлы:** `docs/references/reader.md:5`, `docs/references/reader.md:9`
- **Что подтверждает проблему:** `docs/arch.md`, раздел «Application» (выбор между Repository и Reader) и раздел «Чтение и сборка ответа» («Query handler берёт Entity от Repository или Data от Reader — по форме ответа»)
- **Чем воспроизводится:** сравнение текста `reader.md` с примером `docs/references/query-handler.md:22-40`, где handler читает через `UserRepository`
- **Как исправить:** переписать «Назначение» и «Когда применять» так, чтобы Reader выбирался по критерию arch.md — когда ответу нужен признак, которого нет в агрегате; добавить явное указание, что для ответа из полей агрегата используется Repository
- **Тесты:** не требуются

### 6. В трёх карточках сохранился слой View, отменённый целевой архитектурой

`query-handler.md`, `api-resource.md` и `repository.md` продолжают называть View допустимой формой чтения: «получения сущности, списка, Result или View», «Resource может строиться из View или Entity», «Он возвращает Result или View». В целевой архитектуре такой роли нет. `arch.md` перечисляет ровно три формы — Entity от Repository, Data от Reader, Result от Handler — и прямо запрещает промежуточную модель: «Промежуточной read-модели между Entity или Data и Result нет».

View — термин текущего кода (`app/src/Modules/*/Application/View`, `app/src/Shared/Application/View`), который переезд как раз должен заменить на Data и Result. Оставаясь в карточках, он легализует перенос старой структуры в новые модули.

#### Технические детали

- **Тип:** architecture
- **Тяжесть:** medium
- **Файлы:** `docs/references/query-handler.md:9`, `docs/references/query-handler.md:50`, `docs/references/api-resource.md:9`, `docs/references/api-resource.md:47`, `docs/references/repository.md:38`
- **Что подтверждает проблему:** `docs/arch.md`, раздел «Чтение и сборка ответа»: схема `Repository -> Entity`, `Reader -> Data`, `Handler -> Result` и фраза «Промежуточной read-модели между Entity или Data и Result нет»; в «Именах ролей» роли View тоже нет
- **Чем воспроизводится:** `grep -rn "View" docs/references/` возвращает пять вхождений; `grep -rn "View" docs/arch.md` — ни одного
- **Как исправить:** убрать упоминания View из трёх карточек, заменив их на Data (когда речь о чтении Reader) или Result (когда о результате сценария)
- **Тесты:** не требуются

### 7. Карточки разрешают выпускать доменную Entity в Query-результат и в HTTP-ответ

Сразу три карточки допускают, что доменная сущность уходит за пределы Application: «Для простой внутренней выборки Query может вернуть доменную сущность» (`query-handler.md`), «Простой Query может вернуть Domain Entity» (`result-dto.md`), «Resource может строиться из View или Entity, если обогащение не требуется» (`api-resource.md`).

`arch.md` закрывает такую возможность дважды: «Оба возвращают Result» и «Возвращаемый тип определяет слой, поэтому граница видна в сигнатуре», а в разделе про HTTP — «Публичная HTTP-форма, публичный DTO, Application Result и доменная Entity — разные формы и не подменяют друг друга».

Практический риск конкретный: Resource, построенный из Entity, публикует в JSON любое поле, которое позже добавят в агрегат, — включая приватные. Это ровно тот класс утечек, который карточка интеграционного теста предлагает ловить («проверяется точное JSON-содержимое и отсутствие приватных полей»).

#### Технические детали

- **Тип:** architecture
- **Тяжесть:** medium
- **Файлы:** `docs/references/query-handler.md:50`, `docs/references/result-dto.md:39`, `docs/references/api-resource.md:47`
- **Что подтверждает проблему:** `docs/arch.md`, разделы «Чтение и сборка ответа» и «HTTP API, ошибки и доступ»
- **Чем воспроизводится:** сравнение указанных строк с правилом arch.md «Оба возвращают Result»
- **Как исправить:** в трёх карточках заменить допущение на явный запрет: Query handler возвращает Result, Resource строится из Result; для межмодульной границы — публичный DTO
- **Тесты:** для каждого маршрута чтения фиксировать точный JSON-контракт в интеграционном тесте (уже предписано `integration-test.md`)

### 8. Карточка Result DTO запрещает каталог `Application/Result`, который есть в целевой структуре

`result-dto.md` заканчивается фразой «общий каталог результатов не создаётся». В дереве `arch.md` каталог `Application/Result/` присутствует явно, с пояснением «переиспользуемые части ответа», и это повторено в тексте: «Форма ответа сценария лежит рядом со своим Command или Query, переиспользуемые части ответа — в `Application/Result`».

Карточка не просто умалчивает о `Application/Result` — она его отменяет. В результате переиспользуемый кусок ответа (например, краткая карточка автора, нужная и в ленте, и в детальном просмотре) будет продублирован в каждом сценарии.

#### Технические детали

- **Тип:** architecture
- **Тяжесть:** medium
- **Файлы:** `docs/references/result-dto.md:39`
- **Что подтверждает проблему:** `docs/arch.md`, дерево `Application/Result/  переиспользуемые части ответа` и раздел «Чтение и сборка ответа»
- **Чем воспроизводится:** `grep -n "Application/Result" docs/references/*.md` — ни одного вхождения при наличии каталога в arch.md
- **Как исправить:** заменить фразу на правило arch.md: форма ответа сценария лежит рядом со своим Command или Query, переиспользуемая часть — в `Application/Result`; добавить короткий пример переиспользуемой части
- **Тесты:** не требуются

### 9. Карточки Repository предлагают read-контракт внутри Query вместо Reader в `Application/Contract`

`repository.md` в «Допустимых вариантах» пишет: «Для сложной проекции чтения допускается отдельный read-контракт в конкретном Query. Он возвращает Result или View и не подменяет Repository агрегата». `cycle-repository.md` повторяет: «Отдельный read-контракт допустим для тяжёлой проекции».

В целевой архитектуре у этой потребности есть ровно одно решение с фиксированным местом и фиксированным типом: порт чтения объявляется как `{Name}Reader` в `Application/Contract`, реализуется как `Cycle{Name}Reader` в `Infrastructure/Persistence/Cycle/Read` и возвращает Data. Карточки же отправляют такой контракт в папку сценария и разрешают возвращать Result, то есть стирают границу между ролью Reader и ролью Handler, которую `arch.md` описывает как несмешиваемую.

#### Технические детали

- **Тип:** architecture
- **Тяжесть:** medium
- **Файлы:** `docs/references/repository.md:38`, `docs/references/cycle-repository.md:86`
- **Что подтверждает проблему:** `docs/arch.md`, раздел «Чтение и сборка ответа» («К базе обращаются два вида классов, и их роли не пересекаются», `Reader -> Data`, `Handler -> Result`) и раздел «Infrastructure» («Reader реализует объявленный в `Application/Contract` порт чтения»)
- **Чем воспроизводится:** сравнение «Допустимых вариантов» обеих карточек с разделом `reader.md` «Что повторять», где то же самое описано как Reader в `Application/Contract`
- **Как исправить:** в обеих карточках заменить «read-контракт в конкретном Query» ссылкой на карточку Reader: порт в `Application/Contract`, реализация в `Infrastructure/Persistence/Cycle/Read`, возвращаемый тип — Data
- **Тесты:** не требуются

### 10. Карточка интеграционного теста указывает сразу два взаимно несовместимых и оба неверных расположения

В примере namespace теста — `Tests\Modules\Auth\Feature\Http`, то есть корневой каталог `tests/`. В разделе «Что повторять» тот же файл описан иначе: «Файл теста находится в `Modules/Auth/Tests/Feature/Http`», то есть внутри модуля. Одновременно верным быть не может ни то, ни другое: в дереве `arch.md` у модуля есть `Tests/Feature/Spiral/`, а не `Tests/Feature/Http`, а корневой `tests/` оставлен только для «сквозных и межмодульных проверок».

Базовый класс `Tests\NonTransactionalDatabaseTestCase` из примера принадлежит корневому `tests/` — для теста внутри модуля он недоступен без отдельного решения, которого карточка не описывает. При этом `arch.md` требует самодостаточности модуля: «Удаление модуля не оставляет его файлов в других папках», — а `rules.md` требует интеграционный тест на каждый HTTP-маршрут, поэтому именно эта карточка будет размножена по всем модулям.

#### Технические детали

- **Тип:** tests
- **Тяжесть:** medium
- **Файлы:** `docs/references/integration-test.md:16`, `docs/references/integration-test.md:20`, `docs/references/integration-test.md:43`
- **Что подтверждает проблему:** `docs/arch.md`, дерево `Tests/Unit/{Domain,Application}/`, `Tests/Integration/{Cycle,Spiral}/`, `Tests/Feature/Spiral/` и раздел «Самодостаточность модуля»; фактическое расположение базового класса — `tests/NonTransactionalDatabaseTestCase.php`
- **Чем воспроизводится:** `find app/src -type d -name Tests` не находит ни одного модульного каталога тестов; все тесты лежат в корневом `tests/`
- **Как исправить:** привести namespace примера и текст «Что повторять» к одному целевому пути `Modules/{Module}/Tests/Feature/Spiral`; отдельно решить и записать, где живёт общий базовый TestCase при модульных тестах (кандидат — `Shared` или корневой `tests/Support`), и сослаться на него из карточки
- **Тесты:** после переезда — прогон `make test` на перенесённом маршруте, подтверждающий, что модульный тест находится автозагрузчиком и PHPUnit

### 11. Карточка typed config указывает каталог тестов, которого нет в целевом дереве

`typed-config.md` требует класть тест преобразования конфига в `Tests/Unit/Infrastructure/Configuration`. В дереве `arch.md` у модуля есть только `Tests/Unit/{Domain,Application}/`, `Tests/Integration/{Cycle,Spiral}/` и `Tests/Feature/Spiral/`. Подкаталога `Tests/Unit/Infrastructure` там нет.

Дело не только в пути: `rules.md` требует, чтобы тест из unit-набора «не должен поднимать Spiral, базу данных, Redis, MinIO, очередь или сеть», а проверка преобразования секции конфигурации в типизированный объект как раз поднимает конфигурацию Spiral. По целевому дереву такому тесту место в `Tests/Integration/Spiral`.

#### Технические детали

- **Тип:** tests
- **Тяжесть:** low
- **Файлы:** `docs/references/typed-config.md:40`
- **Что подтверждает проблему:** `docs/arch.md`, дерево `Tests/Unit/{Domain,Application}/` и `Tests/Integration/{Cycle,Spiral}/`; `docs/rules.md`, раздел «Тесты и проверки» про `make test-unit`
- **Чем воспроизводится:** сравнение строки карточки с деревом arch.md — каталога `Tests/Unit/Infrastructure` в целевой структуре нет
- **Как исправить:** указать в карточке `Tests/Integration/Spiral` для теста преобразования конфига либо, если тест действительно не поднимает Spiral, явно описать его как чистую проверку конструктора в `Tests/Unit/Application`
- **Тесты:** не требуются

### 12. Индекс references.md не покрывает обязательные механизмы целевой архитектуры

`arch.md` описывает ряд элементов как обязательные части целевой модели, но ни один из них не имеет карточки, а значит переносить их будут «по памяти».

Самый заметный пробел — интеграционное событие и outbox. Раздел «Взаимодействие модулей» задаёт цепочку «Command handler меняет агрегат, создаёт `Public/Event`, сохраняет событие в outbox в той же транзакции, commit», требует семантику at-least-once и идемпотентного потребителя, а раздел «Public» отделяет `Public/Event` от `Domain/Event`. Карточка `command-handler.md` в «Что повторять» сама ссылается на этот механизм («Транзакция охватывает загрузку, изменение, сохранение и outbox-событие»), но нигде его не показывает, и отдельной карточки нет.

Остальные непокрытые элементы: миграция в `Infrastructure/Persistence/Cycle/Migration` (при трёх отдельных правилах в `rules.md` — только вскользь упомянута в `entity-columns.md`); типизированная доменная коллекция `Domain/Collection` и `{Name}DataCollection` (обязательны по разделу «Коллекции» в `rules.md`, упомянуты в `data.md` без эталона); публичный атрибут доступа `Public/Attribute` (в `http-controller.md` используется `AuthenticatedRoute`, но карточки нет и класса в коде тоже нет); Job-потребитель `Infrastructure/Spiral/Job` с идемпотентностью; консольная команда `Infrastructure/Spiral/Console`; технический порт в `Application/Contract`, отличный от Reader (часы, хеширование, токены, почта, внешние клиенты); Middleware и Response в `Infrastructure/Spiral/Http` (в примере контроллера возвращается `EmptySuccessResponse` без эталона); unit-тест доменной логики при требовании 100% покрытия; `Domain/Service` и `Domain/Event`.

#### Технические детали

- **Тип:** documentation
- **Тяжесть:** medium
- **Файлы:** `docs/references.md:7-28`, `docs/references/command-handler.md:48`
- **Что подтверждает проблему:** `docs/arch.md` — разделы «Взаимодействие модулей», «Владение данными» (миграция у модуля-владельца), «HTTP API, ошибки и доступ» (маршрут объявляет доступ публичным атрибутом), «Application» (технические порты в `Application/Contract`), дерево модуля с `Domain/Collection`, `Domain/Service`, `Domain/Event`, `Infrastructure/Spiral/{Job,Console,Http/Middleware,Http/Response}`; `docs/rules.md` — разделы «Коллекции», «Данные и безопасность» (миграции), «Тесты и проверки» (100% покрытия, `make test-unit`)
- **Чем воспроизводится:** `grep -n "Event\|Миграция\|Коллекц\|Job\|Console\|Middleware\|Attribute" docs/references.md` — ни одной строки; `grep -rn "AuthenticatedRoute" app/src` — класса нет, при этом он используется в эталоне контроллера
- **Как исправить:** добавить карточки и строки индекса как минимум для интеграционного события с записью в outbox, миграции, типизированной коллекции, публичного атрибута доступа и Job-потребителя; остальные (Console, Middleware, Response, технический порт, unit-тест, Domain Service, Domain Event) завести следующим шагом
- **Тесты:** не требуются

## Отклонено

- **`domain-exception.md`: HTTP-код внутри доменного исключения.** Проверка architecture. Отклонено: `statusCode()` — часть контракта уже существующего общего класса `App\Shared\Domain\Exception\DomainTranslatableException` (`app/src/Shared/Domain/Exception/DomainTranslatableException.php:14`), само преобразование в ответ выполняет `spiral-api-errors` на HTTP-границе, как и предписывает arch.md. Карточка соответствует действующему общему механизму.
- **`value-object.md`: готовый русский текст вместо ключа перевода.** Проверка rules. Отклонено: это соответствует действующей конвенции проекта — `InvalidDomainValueException` (код 500) используется как страховка инварианта после проверки во Filter, а пользовательские ошибки идут через `ValidationException` с ключом перевода. Подтверждение: `app/src/Modules/Media/Domain/ValueObject/MediaPath.php:139`, `app/src/Shared/Domain/Exception/InvalidDomainValueException.php:11`, `docs/references/http-filter.md:42`.
- **`command-handler.md`: пример возвращает `void`, а arch.md пишет «Command … возвращает Result своего сценария».** Проверка architecture. Отклонено: у сценария переименования нет данных для возврата, карточка явно допускает минимальный Result в «Допустимых вариантах», практического риска нет.
- **`references.md`: название карточки «Result DTO» не совпадает с ролью `{Action}Result`.** Проверка documentation. Отклонено: косметика, карточка внутри явно предписывает «Имя заканчивается на `Result`», риска неверного имени класса нет.
- **`domain-enum.md`: backed value в `snake_case` против правила о `camelCase`.** Проверка rules. Отклонено: правило `rules.md` касается сериализуемых ключей, а не значений enum; конфликта нет.
- **`cycle-repository.md`: `findOne()` получает имена колонок, а Cycle ожидает имена полей.** Проверка correctness. Отклонено: в показанном примере имя поля и имя колонки совпадают, а поведение Cycle при их расхождении в рамках этого ревью не подтверждено; находка без доказательства.
- **`bootloader.md`: константа `BINDINGS` вместо метода определения биндингов.** Проверка rules. Отклонено: это действующая конвенция проекта на Spiral 3.15 (`app/src/Modules/Auth/Infrastructure/Bootloader/AuthBootloader.php:39` и ещё три бутлоадера), правилами не запрещена.

## Применённые фиксы
Отчёт: `docs/artifacts/review-fixes/2026-09-15_17-36_docs-references.md`
