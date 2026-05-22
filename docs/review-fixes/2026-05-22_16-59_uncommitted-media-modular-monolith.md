---
review: docs/reviews/2026-05-22_16-39_uncommitted-media-modular-monolith.md
date: 2026-05-22 16:59
status: done
---

# Фиксы по ревью: незакоммиченные изменения медиа-домена и модульного монолита

## Применённые правки

| # | Замечание | Файлы | Тесты | Статус |
|---|-----------|-------|-------|--------|
| 1 | Убрать лишний `MediaMultipartPartPayload` из `Domain/ValueObject` | `app/src/Modules/Media/Domain/ValueObject/MediaMultipartPart.php`, `app/src/Modules/Media/Domain/ValueObject/MediaMultipartPartPayload.php`, `app/src/Modules/Media/Domain/Collection/MediaMultipartPartCollection.php` | `make test`, `make phpstan` | ✓ применено |
| 2 | README `tools/api-error` ссылается на старый namespace | `tools/api-error/README.md` | Документация, без запуска тестов | ✓ применено |

## Финальная проверка

- **Тесты:** `make test` — ✓, 122 теста, 469 assertions, есть 26 PHPUnit deprecations
- **PHPStan:** `make phpstan` — ✓, ошибок нет
- **Заметки:** первый запуск `make phpstan` указал на нетипизированный массив в `MediaMultipartPart::jsonSerialize()`; после добавления PHPDoc проверка прошла.
