---
plan: docs/plans/2026-06-16_14-22_when-select-repository.md
started: 2026-06-16 16:45
finished: 2026-06-16 17:35
status: done
---

# Журнал: Метод when() в репозиториях через WhenSelect и AbstractRepository

## Шаги
| # | Шаг | Файлы | Тесты | Статус |
|---|-----|-------|-------|--------|
| 1 | `WhenSelect` (наследник `Select`, метод `when()`) | app/src/Shared/Infrastructure/Cycle/WhenSelect.php | NotificationRepositoryTest | done |
| 2 | `AbstractRepository` (строит `WhenSelect`, подменяет базовый select) | app/src/Shared/Infrastructure/Cycle/AbstractRepository.php | NotificationRepositoryTest | done |
| 3 | `NotificationRepository` на `AbstractRepository` + `findPageForRecipient` через `when()` | app/src/Modules/Notifications/Repository/NotificationRepository.php | NotificationRepositoryTest (171 тест) | done |
| 4 | Бутлоадер: `@attention` из докблоков Cycle в игнор-список Doctrine | AnnotationsBootloader.php, Kernel.php | прогрев schema | done |
| 5 | Миграция 7 репозиториев на `AbstractRepository` (`use`/`@extends`/`extends`) | MediaRepository, MediaImageConversionRepository, MediaMultipartUploadRepository, MediaVideoConversionRepository, NotificationDeviceTokenRepository, NotificationSettingRepository, OutboxEventRepository | весь сьют | done |
| 6 | Пример «Репозиторий» в docs на `AbstractRepository` | docs/code-examples.md | — | done |

## Заметки
- **Подход (design F):** конструктор `AbstractRepository(Select $select, ORM $orm, string $role)` отдаёт инжектированный `$select` в родителя, затем строит `WhenSelect` из `orm`+`role` и перезаписывает `$this->select`. Так `$this->select` становится `WhenSelect` (как просил пользователь), а все три параметра фабрики использованы.
- **Решение пользователя по PHPStan:** перезапись родительского `@readonly`-свойства `$select` ловится правилом `property.readOnlyByPhpDocAssignOutOfClass`. Доказано: `@readonly` у Cycle — только PHPDoc, в рантайме PHP 8.5 перезапись валидна (проба запускалась). Вариант B (строить `WhenSelect` в `select()`, без подавлений) пользователь отклонил. По явному решению пользователя поставлен один видимый `@phpstan-ignore` с комментарием. Подмена порождателя репозиториев рассмотрена и отклонена: `ORM` — `final`, `RepositoryProvider` создаётся в нём жёстко, seam нет; декоратор всего `ORMInterface` вышел бы крупнее и хрупче.
- **Побочный эффект наследования `Select`:** при старте токенайзер сканирует классы `app/src` и читает докблоки методов; унаследованный `Select::__clone()` несёт тег `@attention`, на котором Doctrine-ридер падает. Закрыто бутлоадером `addGlobalIgnoredName('attention')` — тем же механизмом, что spiral/attributes уже применяет к `mixin/yield/note/type`.
- **PHPUnit notice (1 шт.)** — пред­существующий, в `ProcessMediaHandlerTest` модуля Media (`createMock` без `expects`), не связан с правками, сборку не валит.

## Изменения в docs
- `docs/code-examples.md` — пример `UserRepository` переведён на `AbstractRepository` (часть плана).
- Архитектурного отступления, требующего правок `docs/arch.md`/`docs/rules.md`, нет: `AbstractRepository`/`WhenSelect` лежат в `Shared/Infrastructure/Cycle` (как и предписывает arch.md для Cycle-инфраструктуры), репозитории по-прежнему наследуют Cycle-базу (теперь транзитивно), правило «без лишнего instanceof в репозиториях» сохраняется.

## Финальная проверка
`make qa` (стиль + PHPStan + один прогон с покрытием PCOV):
- стиль — ок;
- PHPStan level max — `No errors`;
- тесты — `599/599` passed, Assertions 1771;
- покрытие — `100.00%`, порог 100% соблюдён;
- 1 PHPUnit notice — пред­существующий в `ProcessMediaHandlerTest` (Media), не связан с правками, сборку не валит.

Статус: done.
