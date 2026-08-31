---
plan: docs/plans/2026-06-22_18-46_typed-collection-base.md
started: 2026-06-23 11:46
finished: 2026-06-23 12:30
status: done
---

# Журнал: Базовая коллекция TypedCollection и устранение round-trip

## Шаги
| # | Шаг | Файлы | Тесты | Статус |
|---|-----|-------|-------|--------|
| 1 | Базовый класс `TypedCollection` + unit-тест | `app/src/Shared/Domain/Collection/TypedCollection.php`, `tests/Unit/Shared/Domain/Collection/TypedCollectionTest.php` | `make phpstan` зелёный (No errors); запасной вариант с двухаргументной лямбдой не понадобился | ✅ |
| 2 | Перевод 34 коллекций на `TypedCollection` (замена `use`/`extends`/`@extends` по всем `app/src/Modules/**/*Collection.php`) | 34 файла коллекций | `make phpstan` зелёный — обобщённые типы не сломаны; grep: 34/34 на `extends`+`@extends`+`use TypedCollection`, голого `Collection` не осталось | ✅ |
| 3 | Чистка мест вызова на `mapToList()` + устранение round-trip (`GetTagsHandler`) и generic-break (`PostContentComposer`) | `PostController`, `CommentController`, `AuthController`, `NotificationController`, `NotificationSettingController`, `SendPushNotificationHandler`, `PostViewAssembler`, `CommentViewAssembler`, `GetTagsHandler`, `PostContentComposer` | `make phpstan` зелёный; финальный grep `array_values`/`array_map`/`->all()`/`toBase()` — все остатки классифицированы как задокументированные исключения или конвейеры коллекция→коллекция | ✅ |
| 4 | Правило про `mapToList()` в `docs/rules.md` + три места в скилле `laravel-collections` (п.4, п.5, «Ключевые правила» п.5) | `docs/rules.md`, `.claude/skills/laravel-collections/SKILL.md` | Ручная сверка: `code-examples.md` без устаревшего шаблона; в `SKILL.md` старый `\array_values(...->toBase()->map(...)->all())` остался только как описание внутренностей `mapToList()` и в списке исключений; текст без англицизмов (нет «дженерик»/«callback») | ✅ |

## Заметки

- Стартовое состояние: `TypedCollection` ещё нет, все 34 коллекции на прямом `extends Collection`. В рабочей копии уже лежат связанные незакоммиченные изменения (cursor-пагинация, новые view-коллекции `PostViewCollection`/`CommentViewCollection`). Выбрано: остаться в текущей ветке main.
- `make phpstan` анализирует только `app/src` (см. `phpstan.neon` → `paths: app/src`). Тесты в `tests/` под PHPStan не попадают — фикстура теста свободна от ограничений анализатора.
- Unit-тесты используют `PHPUnit\Framework\TestCase`, namespace `Tests\Unit\...` (образец `tests/Unit/Shared/Domain/Pagination/CursorSliceTest.php`).
- **Эквивалентность ответов API (критерий «форма/порядок/значения не меняются»):** `mapToList($fn)` — буквальное извлечение прежнего выражения `\array_values($coll->toBase()->map($fn)->all())` (и эквивалентной формы `\array_values(\array_map($fn, $coll->all()))`). Тело метода **есть** старое выражение, поэтому байт-в-байт идентичность JSON гарантирована по построению, а не только тестом. Существующие интеграционные тесты всех затронутых маршрутов (лента, комментарии, ответы, сессии, уведомления, настройки) остаются зелёными и служат сетью безопасности. Согласно стратегии плана (`after_each_phase` + «новых тест-сценариев на поведение не добавляется, только покрытие `mapToList()`») новые feature-проверки `data` не добавлялись — эквивалентность зафиксирована здесь.
- **Чужой/несвязанный пробел покрытия (по решению пользователя — закрыт тестом):** гейт `make test-coverage` сначала дал 99.98% из-за непокрытого `MediaMimeType::equals()` (строки 48,50). Файл я не менял (`git status` чист), метод введён предыдущим коммитом `dd5926e feat(media): поддержка документов` и нигде в `app/src` не вызывается — пробел предсуществующий, не связан с задачей коллекций. По решению пользователя добавлен минимальный unit-тест `testMimeTypeEqualsComparesByRawValue` в `tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php` (только тест, продакшен-код не тронут) — покрытие вернулось к 100%.

## Финальная проверка

| Проверка | Результат |
|---|---|
| `make phpstan` | ✅ No errors (level max, `app/src`) |
| `make test` | ✅ OK 1248 тестов, 4028 проверок |
| `make test-coverage` | ✅ Покрытие 100.00% (порог 100%) |

| # | Шаг | Файлы | Тесты | Статус |
|---|-----|-------|-------|--------|
| 5 | Закрытие несвязанного пробела покрытия `MediaMimeType::equals()` (по решению пользователя) | `tests/Unit/Modules/Media/Domain/ValueObject/MediaValueObjectTest.php` | `make test-coverage` 100.00% | ✅ |

## Изменения в docs

- `docs/rules.md` — добавлен буллет про `mapToList()` в раздел «Качество кода» рядом с буллетами про коллекции (фаза 4).
- `.claude/skills/laravel-collections/SKILL.md` — обновлены п.4, п.5 и «Ключевые правила» п.5 под `mapToList()` (фаза 4).
- `docs/arch.md` правок не требует: `Shared/Domain/Collection/` укладывается в «общий доменный код в `Shared/Domain`».
