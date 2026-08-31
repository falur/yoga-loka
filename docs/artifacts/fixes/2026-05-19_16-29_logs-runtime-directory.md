---
date: 2026-05-19 16:29
source: text
status: done
---

# Фикс: перенести логи в runtime

## Контекст

Пользователь сообщил, что логи записались в корень проекта, а не в `runtime`.

Учтены `docs/rules.md` и `docs/arch.md`. Изменение ограничено конфигурацией Monolog handlers.

## Что изменено

| # | Файл | Что изменено | Зачем |
|---|------|--------------|-------|
| 1 | `app/src/Infrastructure/Framework/Bootloader/LoggingBootloader.php` | Пути HTTP, error и debug логов теперь строятся от `DirectoryAlias::Runtime` вместо `DirectoryAlias::Root` | Логи должны попадать в `runtime/logs`, а не в корневой `logs` |

## Тесты и проверки

| Команда | Результат | Заметки |
|---------|-----------|---------|
| `composer phpstan` | ✓ | Ошибок нет |
| `composer cs` | ✓ | Dry-run без изменений |
| `vendor/bin/phpunit -c phpunit.xml --filter 'OpenApiHttpTest::testSwaggerUiUsesLocalAssets'` | ✗ | Тест ожидаемо падает на текущей проблеме `OpenApiConfig`, но после ошибки корневой `logs/` не создан |
| `test ! -d logs` | ✓ | Корневой каталог `logs` отсутствует |
| `find runtime/logs -maxdepth 1 -type f -name '*2026-05-19.log'` | ✓ | Найден `runtime/logs/error-2026-05-19.log` |

## Открытые вопросы

Нет.
