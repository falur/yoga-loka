---
date: 2026-05-21 19:27
source: text
status: done
---

# Фикс: убрать лишний фабричный метод пустой коллекции

## Контекст

Пользователь указал, что `MediaMultipartPartCollection::createEmpty()` просто
дублирует `new MediaMultipartPartCollection()` и не добавляет смысла.

Учтены `docs/rules.md` и `docs/arch.md`.

## Что изменено

| # | Файл | Что изменено | Зачем |
|---|------|--------------|-------|
| 1 | `app/src/Domain/Collection/MediaMultipartPartCollection.php` | Удалён `createEmpty()` | Не держать лишний метод, который повторяет конструктор |
| 2 | `app/src/Domain/Entity/MediaMultipartUpload.php` | В `create()` используется `new MediaMultipartPartCollection()` | Создание пустой коллекции стало прямым и очевидным |

## Тесты и проверки

| Команда | Результат | Заметки |
|---------|-----------|---------|
| `make test` | ✓ | 115 тестов, 452 assertion, 26 PHPUnit deprecations |
| `make phpstan` | ✓ | Ошибок нет |

## Открытые вопросы

Нет.
