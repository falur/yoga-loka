# Удаление лишней фабрики fromParts

Дата: 2026-05-22 17:09

## Контекст

Пользователь указал, что `MediaMultipartPartCollection::fromParts()` не добавляет смысла: метод только повторяет поведение конструктора коллекции.

## Изменения

- Удалён статический метод `MediaMultipartPartCollection::fromParts()`.
- Рабочие вызовы переведены на прямой конструктор `new MediaMultipartPartCollection([...])`.
- В `docs/rules.md` добавлено правило: статические фабрики запрещены, если они принимают те же данные, что и конструктор, и просто вызывают `new self(...)` без отдельного доменного смысла.
- Историческое упоминание `fromParts()` в старом плане `docs/plans/2026-05-22_14-49_valueobject-modular-monolith.md` оставлено без изменений.

## Проверки

- `grep -RIn "static function fromParts\|::fromParts" app/src tests --exclude-dir=vendor --exclude-dir=.git` — рабочих упоминаний нет.
- `make test` — успешно: 122 теста, 469 assertions, 26 PHPUnit deprecations.
- `make phpstan` — успешно, ошибок нет.
