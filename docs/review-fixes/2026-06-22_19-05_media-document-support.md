---
review: docs/reviews/2026-06-22_18-37_media-document-support.md
date: 2026-06-22 19:05
status: done
---

# Фиксы по ревью: Поддержка документов в модуле Media

Режим: `apply-optional`. Пунктов «править обязательно» в ревью нет. Применён один пункт
«рекомендуется исправить» (замечание 1) и по каждому пункту «на усмотрение автора» принято
собственное решение.

## Применённые правки

| # | Замечание | Файлы | Тесты | Статус |
|---|-----------|-------|-------|--------|
| Зам. 1 | Докстринг `baseValue()` обещал нормализацию «при всех сравнениях», хотя префиксы image/video/audio и `equals()` идут по сырому `value()` | `app/src/Modules/Media/Domain/ValueObject/MediaMimeType.php` | — (правка только докстринга) | ✓ применено (рекомендуется исправить) |
| Зам. 2 | Докстринг не оговаривал, что `baseValue()` — только для сопоставления MIME, а не готовый Content-Type | `app/src/Modules/Media/Domain/ValueObject/MediaMimeType.php` (тот же докстринг), `tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php` | `MediaValueObjectTest::testMimeTypeBaseValueNormalizesWithoutChangingRawValue` (+1 кейс `text/csv ; charset=utf-8` → `text/csv`) | ✓ применено (optional) |
| Зам. 4 | Тесты резолвера покрывали лишь 5 из 18 MIME из `DOCUMENT_MIME_TYPES`; опечатка в непокрытой строке молча дала бы 422 | `tests/Unit/Modules/Media/Application/MediaTypeResolverTest.php` | `MediaTypeResolverTest::testResolvesDocumentByAllowedMimeTypes` переведён на `#[DataProvider]` по всем 18 MIME | ✓ применено (optional) |
| Зам. 5 | Комментарий про DJVU не соответствовал создаваемому `application/pdf`; англицизмы в комментариях тестов | `tests/Feature/Modules/Media/Application/CheckMediaQueriesTest.php`, `tests/Feature/Modules/Media/Flow/MediaProcessingFlowTest.php`, `tests/Feature/Modules/Media/Application/RequestMediaUploadHandlerTest.php` | — (правка только комментариев) | ✓ применено (optional) |
| Сверка 1 | В рабочем дереве смешаны задачи Media и round-trip коллекций | — | — | ✗ отклонено (optional, риск момента коммита, передано в eda-commit) |
| Сверка 2 | Untracked-тест `MediaMimeTypeCollectionTest` рискует выпасть при `git add -u` | — | — | ✗ отклонено (optional, риск момента коммита, передано в eda-commit) |
| Зам. 3 | Дублирование guard «чужие списки пусты» в `assertDocumentPlan` и соседях | — | — | ✗ отклонено (optional, стиль зафиксирован планом) |

## Детали правок

- **Замечание 1 + 2 (один докстринг):** докстринг `MediaMimeType::baseValue()` переписан под
  фактическое поведение. Теперь явно сказано, что значение нормализуется только для сопоставления
  MIME (список документов в `MediaTypeResolver`, `MediaMimeTypeCollection::containsMimeType()`) и не
  является готовым Content-Type, а префиксная классификация image/video/audio и `equals()`
  намеренно остаются на исходном `value()`. Ложное обещание «при всех сравнениях» убрано. Оба
  замечания касаются одной и той же фразы, поэтому объединены в одну правку.
- **Замечание 2 (тест):** в существующий `testMimeTypeBaseValueNormalizesWithoutChangingRawValue`
  добавлен кейс `text/csv ; charset=utf-8` → `text/csv`, фиксирующий обрезку пробелов вокруг
  параметра. Отдельный тест не заводился — поведение `baseValue()` уже проверяется здесь.
- **Замечание 4:** `testResolvesDocumentByAllowedMimeTypes` переведён на `#[DataProvider]`
  `documentMimeTypeProvider()`, перечисляющий все 18 MIME из `DOCUMENT_MIME_TYPES`. Любая опечатка в
  строке whitelist теперь падает в тесте. Отдельный `testResolvesDjvuAsDocumentNotImage` оставлен —
  он проверяет приоритет списка документов над префиксом `image/`, а не просто принадлежность к
  списку. Код резолвера не менялся.
- **Замечание 5:** комментарий в `CheckMediaQueriesTest` приведён в соответствие с реальным кейсом
  (документ `application/pdf`, проверка по типу медиа, а не по MIME DJVU); англицизмы заменены
  русскими словами: «пиннит» → «фиксирует», «image-only сценарий» → «сценарий только для картинок»,
  «ready-пути» → «путь готового оригинала», «flow-теста» → «сквозного теста», «staging» (проза в
  `RequestMediaUploadHandlerTest`) → «временное хранилище». Идентификаторы в коде не трогались.

## Решения по optional

- **Принято:**
  - Замечание 2 — дёшево, усиливает контракт докстринга и фиксирует поведение `trim` вокруг
    параметра тестом; совмещено с обязательной правкой докстринга по замечанию 1.
  - Замечание 4 — data-provider по всему whitelist закрывает реальный пробел прочности тестов
    (опечатка в любой из 18 строк), кода не меняет, риск регрессии нулевой.
  - Замечание 5 — прямое нарушение правила `docs/rules.md` «без англицизмов» плюс вводящий в
    заблуждение комментарий; правка дешёвая, только текст комментариев.
- **Отклонено (чтобы следующие ревьюеры не открывали повторно без новых аргументов):**
  - **Проблема сверки 1** (разведение задач Media и round-trip коллокций по коммитам) — это
    организационный риск момента коммита, а не дефект кода Media. Скилл `eda-fix-by-review` не
    коммитит; разведение коммитов выполняет `eda-commit`. В коде/тестах исправлять нечего. Передано
    на этап `eda-commit`.
  - **Проблема сверки 2** (untracked-тест `MediaMimeTypeCollectionTest` рискует выпасть при
    `git add -u`) — файл проверен: существует по плановому пути
    `tests/Unit/Modules/Media/Domain/Collection/MediaMimeTypeCollectionTest.php`, содержит плановые
    кейсы, входит в зелёный `make test`. Риск чисто про способ добавления в индекс при коммите.
    Содержательной правки кода нет. Передано на этап `eda-commit`: при коммите явно добавить файл
    (`git add <путь>`), не полагаясь на `git add -u`.
  - **Замечание 3** (дублирование guard «чужие списки пусты» в `assertDocumentPlan` и соседних
    assert-методах) — ревью само пометило как чистую необязательную стилистику, а план явно
    зафиксировал текущий стиль («как у существующих assert-методов»). Рефакторинг расширил бы scope
    и противоречил бы решению плана при нулевом текущем риске. Оставлено как есть.

## Финальная проверка

- **Тесты:** `make test` — ✓ (1242 теста, 4021 ассерт, OK)
- **Линтер/статический анализ:** `make phpstan` — ✓ (No errors)
- **Заметки:** правки затронули только докстринг `MediaMimeType` и тесты Media. Поведение
  резолвера, коллекции и пайплайна не менялось. Untracked-тест коллекции и смешение задач остаются
  открытыми только как организационные пункты для `eda-commit`, не как дефекты кода.
