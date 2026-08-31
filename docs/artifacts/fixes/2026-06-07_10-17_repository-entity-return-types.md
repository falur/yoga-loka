---
date: 2026-06-07 10:17
source: text
status: done
---

# Фикс: возврат сущностей из репозиториев

## Контекст

Пользователь попросил добавить правило, что в репозиториях не нужно писать `return $entity instanceof Entity ? $entity : null;`, потому что Cycle ORM через `@extends Repository<Entity>` уже возвращает нужный тип, и поправить все репозитории.

Учтены `docs/rules.md` и `docs/arch.md`.

## Что изменено

| # | Файл | Что изменено | Зачем |
|---|------|--------------|-------|
| 1 | `docs/rules.md` | Добавлено правило против лишнего `instanceof` после `findByPK()`, `findOne()`, `findAll()` и `select()->fetchOne()` в типизированных репозиториях Cycle ORM. | Зафиксировать проектное соглашение. |
| 2 | `app/src/Modules/Media/Repository/MediaRepository.php` | Методы `findById()` и `findByStorageKey()` возвращают результат Cycle напрямую. | Убрать лишнюю проверку типа. |
| 3 | `app/src/Modules/Media/Repository/MediaMultipartUploadRepository.php` | Метод `findByMediaId()` возвращает результат Cycle напрямую. | Убрать лишнюю проверку типа. |

## Тесты и проверки

| Команда | Результат | Заметки |
|---------|-----------|---------|
| `rg "instanceof .* \? .* : null" app/src/Modules -g '*Repository.php'` | ✓ | Совпадений не найдено. |
| `make phpstan` | ✓ | Ошибок нет. |
| `make test` | ✓ | 123 теста, 473 проверки. Есть 26 PHPUnit deprecations, запуск успешный. |

## Открытые вопросы

Нет
