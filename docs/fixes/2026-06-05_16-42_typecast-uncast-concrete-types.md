---
date: 2026-06-05 16:42
source: text
status: done
---

# Фикс: конкретные типы в typecast uncastValue

## Контекст

Пользователь указал, что `uncastValue()` в отдельных typecast-классах должен принимать конкретный доменный тип вместо `object|null`, чтобы не дублировать проверку `instanceof` внутри метода.

Учтены `docs/rules.md`, `docs/arch.md` и справка `cycle-entities` по Cycle ORM typecast.

## Что изменено

| # | Файл | Что изменено | Зачем |
|---|------|--------------|-------|
| 1 | `docs/rules.md` | Добавлено универсальное правило: не заменять типизацию ручными проверками `instanceof`, если ожидаемый тип известен. | Чтобы новый код не повторял лишние проверки. |
| 2 | `app/src/Modules/Media/Infrastructure/Cycle/*Typecast.php` | `uncastValue()` принимает `MediaProcessingError|null`, `MediaExpiration|null` и `MediaMultipartPartCollection`; лишние проверки удалены. | Тип проверяет PHP, метод содержит только преобразование значения. |
| 3 | `app/src/Modules/Outbox/Infrastructure/Cycle/*Typecast.php` | `uncastValue()` принимает `OutboxLastError|null`, `OutboxEventDate|null` и `OutboxEventPayload`; лишние проверки удалены. | То же правило применено ко всем текущим typecast-классам. |
| 4 | `tests/Unit/Shared/Infrastructure/Cycle/ValueObjectCastTest.php` | Тестовый typecast-класс переведён на конкретный тип аргумента. | Тестовый пример соответствует правилу. |
| 5 | `tests/Unit/Modules/Media/Infrastructure/Cycle/MediaTypecastTest.php` | Удалён тест ручной проверки `stdClass` для `MediaMultipartPartCollectionTypecast`. | Такой сценарий теперь закрыт сигнатурой метода, а не пользовательской проверкой. |
| 6 | `docs/plans/2026-05-22_14-49_valueobject-modular-monolith.md` | Старый пример `uncastValue(object|null $value)` заменён на пример с конкретным VO. | Чтобы документация не подсказывала старый шаблон. |
| 7 | `app/src/Modules/Outbox/Infrastructure/Outbox/OutboxQueueStatusInterceptor.php` | Проверка результата `findById()` заменена с `instanceof StoredOutboxEvent` на `=== null`. | Метод уже возвращает `StoredOutboxEvent|null`, поэтому достаточно проверки на отсутствие значения. |

## Тесты и проверки

| Команда | Результат | Заметки |
|---------|-----------|---------|
| `git diff --check -- ...` | Успешно | Пробеловых ошибок в изменённых файлах нет. |
| `make qa` | Неуспешно | PHP CS Fixer прошёл, PHPStan сообщил `[OK] No errors`, но завершился с кодом 1 из-за предупреждения о result cache для пользовательских правил из `tools/*`. |
| `make test` | Успешно | Запущено повторно после дополнительной правки: 183 теста, 733 assertions. Остались 28 PHPUnit deprecations. |

## Открытые вопросы

Нет.
