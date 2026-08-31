---
date: 2026-05-18 18:40
source: text
status: done
---

# Фикс: путь runtime-файла OpenAPI-пакета

## Контекст
Пользователь указал, что `tools/runtime/openapi-fixture.yml` создаётся вне публичного пакета `tools/openapi`. Учтены правила проекта из `docs/rules.md` и архитектура из `docs/arch.md`.

## Что изменено
| # | Файл | Что изменено | Зачем |
|---|------|--------------|-------|
| 1 | `tools/openapi/tests/Generator/OpenApiGeneratorTest.php` | Путь `outputFile` изменён с `../../../runtime/openapi-fixture.yml` на `../../runtime/openapi-fixture.yml`; `projectRoot` изменён с `../../..` на `../..` | Runtime-артефакт и корень fixture-проекта теперь остаются внутри публичного пакета `tools/openapi` |
| 2 | `tools/openapi/.gitignore` | Добавлен ignore для `/runtime/` | Тестовый YAML не попадает в git как артефакт запуска тестов |

## Тесты и проверки
| Команда | Результат | Заметки |
|---------|-----------|---------|
| `composer openapi:test` | ✓ | 7 тестов, 83 assertions |
| `composer openapi:phpstan` | ✓ | Ошибок нет |
| `composer phpstan` | ✓ | Приложение, `tools/phpstan` и `tools/openapi` без ошибок |

## Открытые вопросы
Нет
