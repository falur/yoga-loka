---
plan: docs/plans/2026-06-17_19-02_extract-tags-module.md
started: 2026-06-17 19:29
finished: 2026-06-17 19:45
status: done
---

# Журнал: Выделение тегов в отдельный модуль Tags

## Шаги
| # | Шаг | Файлы | Тесты | Статус |
|---|-----|-------|-------|--------|
| 1 | Создан `TagId` в Shared, словарь тегов перенесён в модуль `Tags` (Tag, TagText, TagCollection, TagRepository) со сменой namespace 1:1 | `app/src/Shared/Domain/ValueObject/TagId.php`, `app/src/Modules/Tags/**` | — | done |
| 1 | Обновлён импорт `TagId` → Shared в связи PostTag (Entity + Repository) | `Posts/Domain/Entity/PostTag.php`, `Posts/Repository/PostTagRepository.php` | — | done |
| 1 | Удалены старые tag-файлы из Posts | 5 файлов Posts (Tag, TagText, TagId, TagCollection, TagRepository) | — | done |
| 1 | Обновлены импорты во всех тестах, ссылающихся на перемещённые классы (раскладка файлов прежняя) | 4 unit + 3 feature теста Posts | — | done |
| 1 | Проверка фазы 1: grep остатков пуст, PHPStan зелёный, `make test` зелёный | — | 957 тестов OK (1 предзалежавшийся PHPUnit Notice, не связан с переносом) | done |
| 2 | Созданы unit-тесты модуля Tags (Entity, TagText VO, Collection) | `tests/Unit/Modules/Tags/**` | TagEntityTest, TagTextValueObjectTest, TagCollectionTest | done |
| 2 | Очищены unit-тесты Posts: удалён `testTagCreate` (JoinEntityTest), `TagId` из idClasses (PostsIdentifierTest), tag-методы TagText (PostsTextValueObjectTest), проверка TagCollection (PostsCollectionTest) + их импорты | 4 unit-теста Posts | — | done |
| 2 | Создана feature-база `TagsRepositoryTestCase` (createUser со своим счётчиком `tag.user%d`, persist, entityManager, tagRepository) + `TagRepositoryTest` (4 теста тега) | `tests/Feature/Modules/Tags/**` | TagRepositoryTest | done |
| 2 | Создан `PostTagRepositoryTest` (3 теста связи + createPostFor, кросс-модульные фикстуры Tag/TagText легальны), удалён `TagAndPostTagRepositoryTest` | `Posts/Repository/PostTagRepositoryTest.php` | PostTagRepositoryTest | done |
| 2 | Удалён метод `tagRepository()` и импорт TagRepository из `PostsRepositoryTestCase` (потребителей вне удалённого теста не было), `postTagRepository()` оставлен | `Posts/PostsRepositoryTestCase.php` | — | done |
| 2 | Проверка фазы 2: find/grep границ модулей чистый; `make qa` (стиль + PHPStan + покрытие) | — | 958 тестов OK, покрытие 100.00% | done |

## Заметки
- Импорты приводил к алфавитному порядку FQCN (как в существующем коде), чтобы пройти стиль-чек `make qa`.
- `make test` сам сбрасывает и прогревает кэш схемы Cycle (reset-test → migrate → warmup), поэтому смена FQCN сущностей не оставляет устаревший кэш.
- Конфиги не трогал: сущности авто-сканируются по `app/src`, тест-suite использует рекурсивные директории `tests/Unit`/`tests/Feature`, coverage включает весь `app/src` — новый модуль Tags попадает автоматически.

## Изменения в docs
(нет — архитектура и правила уже описывают размещение общих идентификаторов в `Shared/Domain/ValueObject` и словарей в модулях; новых решений по ходу не появилось)

## Финальная проверка
Команда: `make qa` (через Docker) — стиль + PHPStan level max + один прогон с покрытием (PCOV).
Результат:
- Стиль: OK.
- PHPStan: `No errors`.
- Тесты: 958 пройдено, Assertions 3160. Один PHPUnit Notice — предзалежавшийся, к задаче не относится (виден и до правок).
- Покрытие: `Покрытие 100.00% соответствует порогу 100.00%`.
