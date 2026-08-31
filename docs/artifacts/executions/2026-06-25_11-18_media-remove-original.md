---
plan: docs/plans/2026-06-24_23-26_media-remove-original.md
started: 2026-06-25 11:18
finished: 2026-06-25 11:40
status: done
---

# Журнал: Удаление оригинала медиа с сохранением конверсий

## Шаги
| # | Шаг | Файлы | Тесты | Статус |
|---|-----|-------|-------|--------|
| 1 | Команда RemoveMediaOriginal, предикат isFinalized(), защита финализации, локали | Media.php, ProcessMediaHandler.php, RemoveMediaOriginalCommand.php, RemoveMediaOriginalHandler.php, ru/en media.php | MediaEntityTest, RemoveMediaOriginalHandlerTest, DeleteMediaHandlerTest, ProcessMediaHandlerTest | done — make test (1263 OK), make phpstan (OK) |
| 2 | Семантика конверсионных и оригинал-запросов | GetMediaUrlHandler.php, GetAudioWaveformHandler.php | GetMediaUrlHandlerTest, GetAudioWaveformHandlerTest, FindMediaUrlHandlerTest, CheckMediaAttachableHandlerTest | done — make test (1269 OK), make phpstan (OK), Posts-тест зелёный |
| 3 | Документация модуля (README) | app/src/Modules/Media/README.md | — (документная фаза, своего gate нет) | done — финальный gate make qa |

## Заметки
- Фаза 1: `isFinalized()` добавлен рядом с `isReady()`; guard-ы record*ProcessingError и early-skip
  ProcessMediaHandler переведены на `isFinalized()`. Handler без `#[Transactional]`, `#[LogOperation]`,
  ленивая проверка конверсий через три `findByMediaId(...)->isNotEmpty()` с ранним выходом.
- Типизированные коллекции наследуют `Illuminate\Support\Collection` → `isNotEmpty()` доступен.
- Фаза 2: `GetMediaUrlHandler` рефакторен — ветка конверсии вынесена в приватный `conversionUrl()`
  (плоская структура без вложенности 2+); оригинал-ветка: сначала `original_removed`, затем `not_ready`.
  `GetAudioWaveformHandler` переведён на `isFinalized()`. `FindMediaUrl`/`CheckMediaAttachable` без
  изменений — зафиксированы регрессиями (null / 422 на removed-original).

## Финальная проверка
`make qa` (стиль + PHPStan level max + один PCOV-coverage-run) — зелёный:
- Стиль: ошибок нет.
- PHPStan: [OK] No errors.
- Тесты: OK (1269 tests, 4088 assertions).
- Покрытие: 100.00% соответствует порогу 100.00%.

## Изменения в docs
`docs/rules.md` и `docs/arch.md` правок не требуют — задача уложилась в существующую архитектуру
(новый Application-сценарий + изменение поведения Query внутри модуля). Документация модуля обновлена
в `app/src/Modules/Media/README.md` (фаза 3).
</content>
</invoke>
