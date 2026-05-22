---
date: 2026-05-21 19:22
source: text
status: done
---

# Фикс: доменное исключение для VO и пустые конструкторы Entity

## Контекст

Пользователь указал, что `ValidationException` нельзя использовать внутри домена:
это HTTP-исключение для ответа пользователю, такие исключения не планируется
логировать. Для невалидных доменных значений нужен отдельный тип исключения,
который отдаёт HTTP 500.

Дополнительно пользователь попросил убрать пустые `public function __construct() {}`
из Entity.

Учтены `docs/rules.md` и `docs/arch.md`.

## Что изменено

| # | Файл | Что изменено | Зачем |
|---|------|--------------|-------|
| 1 | `app/src/Domain/Exception/InvalidDomainValueException.php` | Добавлен тип исключения с кодом 500 | Не смешивать доменную ошибку значения с HTTP 422 |
| 2 | `app/src/Domain/ValueObject/*`, `app/src/Domain/Collection/MediaMultipartPartCollection.php` | `ValidationException` заменён на `InvalidDomainValueException` | VO и доменные коллекции больше не бросают HTTP-исключение |
| 3 | `tools/api-error/src/Interceptor/ApiExceptionInterceptor.php` | 4xx-доменные исключения больше не логируются; исключения без поддерживаемого 4xx-кода идут как 500 | HTTP-ошибки для пользователя не попадают в лог, а внутренние доменные ошибки не раскрывают сообщение |
| 4 | `app/src/Domain/Entity/*` | Убраны пустые конструкторы из медиа-Entity | Не держать бессмысленный код |
| 5 | `docs/rules.md`, `docs/arch.md`, `docs/code-examples.md`, `docs/plans/2026-05-21_17-59_media-domain-entities-vo-migrations.md` | Обновлены правила, архитектура, пример и план | Документы больше не требуют старое поведение |
| 6 | Тесты API-ошибок и VO | Обновлены ожидания исключений, 500-ответа и отсутствия логирования 4xx | Зафиксировать новое поведение |

## Тесты и проверки

| Команда | Результат | Заметки |
|---------|-----------|---------|
| `composer -d tools/api-error test` | ✓ | 20 тестов, 92 assertion |
| `composer -d tools/api-error phpstan` | ✓ | Ошибок нет |
| `make test` | ✓ | 115 тестов, 452 assertion, 26 PHPUnit deprecations |
| `make phpstan` | ✓ | Ошибок нет |

## Открытые вопросы

Нет.
