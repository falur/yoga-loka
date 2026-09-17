---
review: docs/artifacts/reviews/2026-09-15_17-17_docs-references.md
date: 2026-09-15 17:36
status: done
---

# Фиксы по ревью: карточки-эталоны docs/references против целевой архитектуры

## Принятые решения

- Путь общих Cycle-примитивов в Shared зафиксирован как `App\Shared\Infrastructure\Persistence\Cycle` — зеркало структуры модуля. Папка добавлена в дерево `docs/arch.md` (раздел `Shared/Infrastructure`), карточки приведены к этому пути.
- Имя базового класса репозитория согласовано по факту кода: `AbstractRepository`, а не `AbstractCycleRepository`.
- Общий базовый TestCase модульных тестов остаётся в корневом `tests/` как часть без владельца среди модулей (`arch.md`, «Самодостаточность модуля»); сами тесты модуля лежат в `Modules/{Module}/Tests/...`.
- Карточки описывают целевое состояние, а не текущий код `app/src`: слоя `Presentation` нет нигде.

## Применённые правки

| # | Замечание | Файлы | Тесты | Статус |
|---|-----------|-------|-------|--------|
| 1 | Карточки HTTP-слоя показывают слой `Presentation`, которого в целевой архитектуре нет | `docs/references/http-filter.md`, `docs/references/http-controller.md`, `docs/references/api-resource.md` | не требуются (документация) | ✓ применено |
| 2 | Bootloader, публичный контракт и typed config пропускают уровень `Spiral` в Infrastructure | `docs/references/bootloader.md`, `docs/references/public-contract.md`, `docs/references/typed-config.md` | не требуются | ✓ применено |
| 3 | Reader опирается на несуществующий `WhenQuery` и обходит `WhenSelect::cursorById()` и `CursorSlice::fromOverfetched()` | `docs/references/reader.md`, `docs/references/data.md` | не требуются | ✓ применено |
| 4 | Три карточки указывают разные и несуществующие namespace общих классов Cycle в Shared | `docs/arch.md`, `docs/references/cycle-repository.md`, `docs/references/reader.md`, `docs/references/typecast.md` (уже соответствовал) | не требуются | ✓ применено |
| 5 | Reader объявлен единственным способом чтения и отменяет роль Repository в Query | `docs/references/reader.md` | не требуются | ✓ применено |
| 6 | В трёх карточках сохранился слой View, отменённый целевой архитектурой | `docs/references/query-handler.md`, `docs/references/api-resource.md`, `docs/references/repository.md` | не требуются | ✓ применено |
| 7 | Карточки разрешают выпускать доменную Entity в Query-результат и HTTP-ответ | `docs/references/query-handler.md`, `docs/references/result-dto.md`, `docs/references/api-resource.md` | не требуются | ✓ применено |
| 8 | Result DTO запрещает каталог `Application/Result`, который есть в целевой структуре | `docs/references/result-dto.md` | не требуются | ✓ применено |
| 9 | Карточки Repository предлагают read-контракт внутри Query вместо Reader в `Application/Contract` | `docs/references/repository.md`, `docs/references/cycle-repository.md` | не требуются | ✓ применено |
| 10 | Интеграционный тест указывает два взаимно несовместимых и оба неверных расположения | `docs/references/integration-test.md` | не требуются | ✓ применено |
| 11 | Typed config указывает каталог тестов, которого нет в целевом дереве | `docs/references/typed-config.md` | не требуются | ✓ применено |
| 12 | Индекс `references.md` не покрывает обязательные механизмы целевой архитектуры | `docs/references.md`, `docs/references/command-handler.md` и 12 новых карточек (см. ниже) | не требуются | ✓ применено |

### Новые карточки по пункту 12

Критичные (названы в ревью как минимум необходимые):

- `docs/references/integration-event.md` — `Public/Event` с записью в outbox в одной транзакции.
- `docs/references/migration.md` — миграция в `Infrastructure/Persistence/Cycle/Migration` и регистрация каталога bootloader-ом.
- `docs/references/domain-collection.md` — типизированная доменная коллекция и `{Name}DataCollection`.
- `docs/references/public-attribute.md` — публичный атрибут доступа в `Public/Attribute`.
- `docs/references/job-consumer.md` — Job-потребитель в `Infrastructure/Spiral/Job` с идемпотентностью по идентификатору доставки.

Желательные (в ревью отмечены как «следующим шагом», сделаны сразу):

- `docs/references/console-command.md` — консольная команда в `Infrastructure/Spiral/Console`.
- `docs/references/application-contract.md` — технический порт в `Application/Contract`.
- `docs/references/http-middleware.md` — Middleware в `Infrastructure/Spiral/Http/Middleware`.
- `docs/references/http-response.md` — Response в `Infrastructure/Spiral/Http/Response` и общие ответы пакета.
- `docs/references/unit-test.md` — unit-тест доменной логики в `Tests/Unit/{Domain,Application}`.
- `docs/references/domain-service.md` — `Domain/Service`.
- `docs/references/domain-event.md` — `Domain/Event` и его отличие от `Public/Event`.

Все двенадцать внесены в таблицу `docs/references.md` в её формате.

## Отклонённые находки

Отклонённых находок нет: все двенадцать пунктов раздела «Проблемы в коде» применены. Раздел «Отклонено» самого ревью не трогали.

## Финальная проверка

- **Тесты:** `make qa` — ✗ (1 падение из 1304: `Tests\Feature\Modules\Media\Infrastructure\S3MediaFileServiceTest::testCopyObjectToPublicBucketIsAnonymouslyReadable`, ожидается префикс `test/` в пути объекта MinIO)
- **Линтер:** `make qa` (стиль + PHPStan внутри `composer qa`) — ✓, оба этапа пройдены до запуска тестов
- **Синтаксис примеров:** все 47 PHP-фрагментов карточек извлечены и проверены `php -l` — ✓
- **Заметки:** падение теста не связано с изменением: правки затронули только `docs/`, код `app/` и `tests/` не менялся (`git status` показывает изменения исключительно в `docs/`). Это расхождение тестового окружения MinIO (префикс параллельного набора), а не последствие фиксов.
