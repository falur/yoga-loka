---
date: 2026-05-22 16:07
source: text
status: done
---

# Фикс: общие ValueObject и AbstractResource в Shared

## Контекст

Пользователь уточнил, что `AbstractIntegerValue`, `AbstractUuidV7Id` и `AbstractResource`
лучше держать в `Shared`, а `UserId` не должен принадлежать `Media`.

Учтены `docs/rules.md` и `docs/arch.md`: изменения точечные, без нового модуля
`User`, потому что такого модуля в коде сейчас нет.

## Что изменено

| # | Файл | Что изменено | Зачем |
|---|------|--------------|-------|
| 1 | `app/src/Shared/Domain/ValueObject/AbstractIntegerValue.php` | Перенесён базовый integer VO | Базовый класс используется не только медиа-доменом |
| 2 | `app/src/Shared/Domain/ValueObject/AbstractUuidV7Id.php` | Перенесён базовый UUID v7 VO | Базовый класс используется не только медиа-доменом |
| 3 | `app/src/Shared/Domain/ValueObject/UserId.php` | `UserId` вынесен из `Media` в `Shared` | Не объявлять чужую сущность частью медиа-модуля |
| 4 | `app/src/Shared/Presentation/Http/Resource/AbstractResource.php` | Перенесён базовый HTTP resource | Базовый resource общий для API |
| 5 | `app/src/Modules/Media/**`, `tests/**` | Обновлены imports на `App\Shared\Domain\ValueObject\UserId` и базовые VO | Сохранить рабочие ссылки после переноса |
| 6 | `app/src/Modules/System/Presentation/Http/Resource/HealthResource.php` | Обновлён import `AbstractResource` | Использовать общий базовый resource |
| 7 | `docs/arch.md`, `docs/rules.md`, `docs/code-examples.md` | Обновлены правила, архитектура и примеры | Документация теперь совпадает с новой структурой |

## Тесты и проверки

| Команда | Результат | Заметки |
|---------|-----------|---------|
| `grep -RInF "App\\Modules\\Media\\Domain\\ValueObject\\UserId" app/src tests docs --include='*.php' --include='*.md'` | ✓ | Старых ссылок нет |
| `grep -RInF "App\\Modules\\Media\\Domain\\ValueObject\\AbstractIntegerValue" app/src tests docs --include='*.php' --include='*.md'` | ✓ | Старых ссылок нет |
| `grep -RInF "App\\Modules\\Media\\Domain\\ValueObject\\AbstractUuidV7Id" app/src tests docs --include='*.php' --include='*.md'` | ✓ | Старых ссылок нет |
| `grep -RInF "App\\Modules\\System\\Presentation\\Http\\Resource\\AbstractResource" app/src tests docs --include='*.php' --include='*.md'` | ✓ | Старых ссылок нет |
| `make test` | ✓ | 122 tests, 469 assertions, 26 PHPUnit deprecations |
| `make phpstan` | ✓ | No errors |

## Открытые вопросы

Нет. Если позже появится полноценный `User`-модуль, владение `UserId` можно
пересмотреть отдельным решением.
