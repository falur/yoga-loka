---
plan: docs/plans/2026-06-17_14-16_posts-comments-db-layer.md
started: 2026-06-17 15:41
finished: 2026-06-17 17:35
status: done
---

# Журнал: Модуль Posts — слой базы данных

## Шаги
| # | Шаг | Файлы | Тесты | Статус |
|---|-----|-------|-------|--------|
| 1 | VO и Enum: 10 id, 6 текст/причина, 5 счётчиков, 5 ссылок, 5 null-object, 2 enum | `app/src/Modules/Posts/Domain/ValueObject/*` (31), `.../Domain/Enum/*` (2) | `tests/Unit/Modules/Posts/Domain/{ValueObject,Enum}/*` (6 файлов) | make phpstan ✓, make test-unit ✓ (480 тестов) |
| 2 | 12 typecast-классов (nullable↔null-object, datetime, uuid, reason) | `app/src/Modules/Posts/Infrastructure/Cycle/*` (12) | `tests/Unit/Modules/Posts/Infrastructure/Cycle/PostsTypecastTest.php` | make phpstan ✓, make test-unit ✓ (493 теста) |
| 3 | 10 коллекций + 10 сущностей (Post, Comment, Tag, PostMedia, PostBlock, 5 join) + 10 read-only репозиториев | `.../Domain/Collection/*` (10), `.../Domain/Entity/*` (10), `.../Repository/*` (10) | `tests/Unit/Modules/Posts/Domain/{Entity,Collection}/*` (5 файлов) | make phpstan ✓, make test-unit ✓ (519 тестов) |
| 4 | Миграция на 10 таблиц + 2 self-FK через `->update()` | `app/database/migrations/20260617.160942_0_create_posts_domain_tables.php` | ручная приёмка в Docker | migrate ✓, migrate:rollback ✓ (self-FK дропается чисто), cache:clean ✓ |
| 5 | Feature-тесты раунд-трипа, связей, FK-поведения, пагинации | `tests/Feature/Modules/Posts/PostsRepositoryTestCase.php` + `Repository/*Test.php` (6 файлов) | 41 Posts feature-тест ✓; покрытие Posts 100% (526/526) | phpstan ✓, Posts unit+feature ✓ (136), coverage Posts 100% |

## Изменения в порядке относительно плана

- **Коллекции** (план — фаза 2) созданы в фазе 3 вместе с сущностями: коллекция в PHPDoc
  `@extends Collection<int, Entity>` ссылается на сущность, а сущность использует коллекцию в
  `#[HasMany(collection: ...)]` — взаимная зависимость, иначе PHPStan на границе фазы красный.
- **Репозитории** (план — фаза 5) созданы в фазе 3: `#[Entity(repository: XxxRepository::class)]`
  требует существования класса репозитория, иначе PHPStan красный. В фазе 5 остались только
  feature-тесты (интеграционная часть фазы 5). Конечный результат идентичен плану.

## Заметки

- `PostMediaReference` сделан как `extends AbstractUuidV7Id` (non-null id-VO с `fromString`),
  по эталону `MediaId`. В плане фаза 5 упоминает `PostMediaReference::pointingTo()`, но
  фаза 1 задаёт «образец MediaId с fromString без none()», а общий `ValueObjectCast` для
  non-nullable VO гидрирует именно через `fromString` — поэтому фабрика `fromString`, а в
  feature-тестах фазы 5 ссылка на медиа создаётся через `PostMediaReference::fromString(...)`.
- Счётчики (`LikesCount/RepostsCount/CommentsCount/RepliesCount`) переопределяют
  `MAX = PHP_INT_MAX` и добавляют `decrement()` (на `MIN` → `InvalidDomainValueException`),
  `fromString` не объявляют — `ValueObjectCast` гидрирует их через `fromInt`. `MediaPosition`
  без inc/dec/zero (создаётся из реальной позиции через `fromInt`).

## Изменения в docs

## Финальная проверка

- **make qa** — зелёный целиком: code style ✓, PHPStan ✓, 957 тестов проходят, покрытие
  **100.00%** при пороге 100% (`PHPUnit Notices: 1` — лишь нотис о symfony deprecation, не падение).
- **make phpstan** — зелёный (анализирует и код, и тесты).
- **make test-unit** — зелёный (519 тестов; suite Unit лёгкий, без kernel).
- **Покрытие нового кода Posts** — 100% (526/526 statements по 75 файлам модуля).
- **Миграция** — `migrate`/`migrate:rollback` в Docker без ошибок (self-FK через `->update()`).

### Диагностика и фикс падений Auth (по решению пользователя)

При первом полном прогоне `make test-feature` падали 14 тестов модуля Auth (ни один Posts-тест
не падал). Диагностика показала, что причина — **конфигурация окружения `.env`**, не код:

1. **HTTP 500 в `AuthHttpTest`** — `ENCRYPTER_KEY={encrypt-key}` (плейсхолдер, не валидный hex):
   Auth-флоу шифрует данные через Spiral Encrypter, `Encoding::hexToBin()` падал →
   `EncrypterException` → 500. (Трейлинговый symfony/validator 7.4 deprecation в логе — шум, не
   причина 500.)
2. **`SendLoginCodeHandlerTest::testFallsBackToDefaultLocaleForUnsupportedValue`** — `LOCALE=en`,
   тест ждёт русский дефолт.

Пользователь выбрал исправить окружение. Сделано в `.env`: сгенерирован валидный `ENCRYPTER_KEY`
(`php app.php encrypt:key`) и выставлен `LOCALE=ru`. После фикса все 35 Auth-тестов и полный гейт
`make qa` зелёные. Код Auth не менялся (он корректен).
