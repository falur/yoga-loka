---
plan: docs/plans/2026-06-30_15-04_remove-media-upload-settings.md
started: 2026-06-30 15:47
finished: 2026-06-30 15:47
status: done
---

# Журнал: Убрать MediaUploadSettings — конфиг-зависимое поведение за Application-контрактом

## Шаги
| # | Шаг | Файлы | Тесты | Статус |
|---|-----|-------|-------|--------|
| 1 | Контракт планнера + реализация + unit-тест | +`MediaUploadPlannerContract.php`, +`MediaUploadPlanner.php`, +`tests/Unit/Modules/Media/Infrastructure/MediaUploadPlannerTest.php` | `make test-unit` OK (610), `make phpstan` OK | done |
| 2 | Handler на контракт + биндинг планнера | `RequestMediaUploadHandler.php`, `MediaBootloader.php` (BINDINGS), `RequestMediaUploadHandlerTest.php` | `make test-feature` OK (603), `make phpstan` OK | done |
| 3 | Удалить MediaUploadSettings + фабрику; kernel-тест; README | −`MediaUploadSettings.php`, `MediaBootloader.php` (фабрика удалена), `MediaBootloaderTest.php`, `Media/README.md` | `make test-kernel` OK (63), `make phpstan` OK, grep пусто | done |
| 4 | Обновить rules.md и arch.md (запрет settings-DTO, паттерн контракта) | `docs/rules.md`, `docs/arch.md` | grep «settings-объект» — только запрещающие; закрывающий `make qa` | done |

## Заметки

- Для `isMultipart` сделан data provider (3 кейса: равен/выше/ниже порога) по идиоме `MediaTypeResolverTest`.
- VO-валидация размера части покрыта тестом `partSize()` ниже минимума → `InvalidDomainValueException`; `partsCount()` делит на `partSize()->value()`.

## Изменения в docs

- `docs/rules.md` (правило «`env()` только в конфигах») и `docs/arch.md` («Правила зависимостей») —
  обновлены в фазе 4 как часть задачи: убрано разрешение settings-объектов, зафиксирован паттерн
  «конфиг-зависимое поведение → Infrastructure-сервис за `Application/Contract`», явный запрет
  settings-DTO «конфиг от конфига». Образцы в arch.md проверены в коде: `UserBootloader`
  (`defaultAvatarUrl`), `LocaleResolver` (`LocaleConfig`), `MediaUrlService`, `MediaUploadPlanner`.
- Зафиксированных архитектурных решений «по ходу» вне плана не было.

## Финальная проверка

- `make qa` (стиль php-cs-fixer + PHPStan level max + полный сьют с покрытием PCOV): **зелёный**.
  - PHPStan: No errors.
  - Тесты: OK (1276 тестов, 4131 assertions).
  - Покрытие: 100.00% при пороге 100.00%.
- Промежуточно по фазам: `make test-unit` OK (610), `make test-feature` OK (603), `make test-kernel` OK (63).
- grep-гейты: `MediaUploadSettings`/`mediaUploadSettings` в `app`/`tests` и живых доках — пусто;
  «settings-объект» в живых доках — только запрещающие формулировки.

## Память агента

- Память `application-config-independence.md` обновлена: settings-DTO больше не согласованный
  паттерн; конфиг-зависимое поведение идёт через Infrastructure-сервис за `Application/Contract`
  (образец `MediaUploadPlanner`).
