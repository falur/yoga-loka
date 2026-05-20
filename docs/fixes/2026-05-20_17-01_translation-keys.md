---
date: 2026-05-20 17:01
source: text
status: done
---

# Фикс: стабильные ключи переводов

## Контекст
Пользователь утвердил формат ключей `yoga_loka.api_error.*` вместо английских фраз в роли ключей переводов. Правила проекта и архитектура учтены: пользовательские сообщения остаются на языке текущего locale, пакеты только читают Spiral translator.

## Что изменено
| # | Файл | Что изменено | Зачем |
|---|------|--------------|-------|
| 1 | `tools/api-error/src/*` | Вызовы translator переведены на ключи `yoga_loka.api_error.route_not_found`, `yoga_loka.api_error.validation_error`, `yoga_loka.api_error.internal_server_error` | Убрать пересечения с обычными пользовательскими строками |
| 2 | `tools/api-error/locale/*/messages.php` | Словари переведены на новые ключи | Сохранить прежние тексты ответов при новых ключах |
| 3 | `tools/openapi/src/Spec/SpecBuilder.php` и `tools/openapi/locale/*/messages.php` | Для стандартных описаний OpenAPI добавлены ключи `yoga_loka.openapi.successful_response` и `yoga_loka.openapi.api_error` | Применить тот же принцип к второму пакету с переводами |
| 4 | `tools/*/tests` и `tests/Feature/Endpoint/*` | Обновлены тестовые словари; в тесте генерации OpenAPI locale выставляется через `TranslatorInterface` | Проверить новые ключи и убрать вызов несуществующего `withLocale()` |
| 5 | `tools/*/README.md`, `docs/arch.md`, `docs/plans/2026-05-20_14-39_tools-multilingual.md` | Документация обновлена под стабильные ключи | Не оставлять противоречие между кодом и описанием |

## Тесты и проверки
| Команда | Результат | Заметки |
|---------|-----------|---------|
| `composer -d tools/api-error test` | ✓ | 18 тестов, 69 проверок |
| `composer -d tools/openapi test` | ✓ | 16 тестов, 287 проверок |
| `composer test -- --filter ApiErrorHttpTest` | ✓ | 9 тестов, 32 проверки |
| `composer test -- --filter OpenApiGenerateCommandTest` | ✓ | 1 тест, 3 проверки |
| `composer -d tools/api-error phpstan` | ✓ | Ошибок нет |
| `composer -d tools/openapi phpstan` | ✓ | Ошибок нет |
| `composer phpstan` | ✓ | Ошибок нет |
| `composer cs` | ✓ | Замечаний нет |
| `composer test` | ✗ | Не прошёл только `DockerRuntimeSmokeTest::testStorageCanUseTestBucket`: локально не резолвится host `minio` |

## Открытые вопросы
Нет
