---
title: Ревью незакоммиченных изменений медиа-домена и модульного монолита
date: 2026-05-22 16:39
target: git diff HEAD + untracked files
mode: normal
score: 88
status: meta-reviewed
meta_reviewers:
  - plan-check: gpt-5.3-codex
  - architecture-check: gpt-5.3-codex
  - rules-check: gpt-5.3-codex
  - quality-check: gpt-5.3-codex
plan:
  - docs/plans/2026-05-21_17-59_media-domain-entities-vo-migrations.md
  - docs/plans/2026-05-22_14-49_valueobject-modular-monolith.md
---

# Ревью: незакоммиченные изменения медиа-домена и модульного монолита

## Оценка

**88/100.** Код проходит проверки, но в доменном слое остался payload-класс, который лежит рядом с Value Object и не выглядит как доменный объект. Ещё есть документационная неточность в README изменённого tools-пакета.

## Проблемы сверки с планом

### 1. Multipart payload не соответствует месту в доменном слое

Статус: частично

Контекст:
Планы вводят медиа-домен через Value Object и затем очищают доменную модель от инфраструктурных зависимостей. По правилам проекта вспомогательные payload/DTO для JSON не должны лежать в `Domain/ValueObject`, если они сами не являются полноценными VO.

Проблема:
`MediaMultipartPartPayload` лежит в `Domain/ValueObject`, но выглядит как DTO для JSON-формы, а не как доменный объект.

Риск:
В доменном слое появляется технический payload. Следующий разработчик может использовать этот класс как обычный Value Object, хотя у него нет ожидаемых методов и гарантий.

Где:

- `app/src/Modules/Media/Domain/ValueObject/MediaMultipartPartPayload.php:7` — payload лежит в папке `ValueObject`, но выглядит как DTO с публичными примитивами

## Замечания

### 1. Multipart payload оформлен как Value Object, но не выполняет контракт VO

Тип: `architecture`

Рекомендация: `править обязательно`

Контекст:
В проекте доменные значения должны быть единообразными. Если класс лежит в `Domain/ValueObject`, от него ожидается поведение Value Object: создание через фабрику, сравнение, сериализация и отсутствие публичного конструктора с голыми примитивами.

Риск:
Сейчас `MediaMultipartPartPayload` может начать использоваться как доменный объект, хотя он не валидирует значения и не имеет `equals()`. Это размывает правило про Value Object и усложняет поддержку typecast-логики для multipart parts.

Где:

- `app/src/Modules/Media/Domain/ValueObject/MediaMultipartPartPayload.php:7` — класс лежит в `Domain/ValueObject`
- `app/src/Modules/Media/Domain/ValueObject/MediaMultipartPartPayload.php:9` — публичный конструктор принимает `int` и `string`
- `app/src/Modules/Media/Domain/Collection/MediaMultipartPartCollection.php:31` — коллекция возвращает payload из `jsonSerialize()`

Детали:
Payload нужен только как форма JSON для typecast/сериализации, но размещён рядом с доменными Value Object. При этом он не реализует общий контракт VO из `docs/rules.md`.

Как исправить:
Нужно либо сделать это полноценным доменным Value Object, либо убрать payload из доменного слоя и возвращать из сериализации простую структуру только на инфраструктурной границе.

Конкретные шаги:

- Если payload остаётся доменным объектом, добавить приватный конструктор, фабрику, валидацию, `equals()` и `JsonSerializable`.
- Если payload нужен только для JSON, перенести формирование JSON-структуры в `MediaMultipartPartCollectionTypecast` и не держать отдельный класс в `Domain/ValueObject`.
- Обновить тест `MediaMultipartPartCollectionTypecast` или `MediaValueObjectTest`, чтобы зафиксировать выбранный вариант.

### 2. README tools/api-error ссылается на старый namespace доменных исключений

Тип: `docs`

Рекомендация: `править обязательно`

Контекст:
После переноса общего доменного кода доменные исключения приложения находятся в `Shared`. Это теперь зафиксировано в архитектуре, а пакет `tools/api-error` тоже изменён под новое поведение доменных исключений.

Риск:
Если README оставить как есть, следующий разработчик будет брать пример со старым путём и создавать или искать исключения в несуществующем месте. Это особенно легко пропустить, потому что сам код и тесты уже перешли на новую структуру.

Где:

- `tools/api-error/README.md:121` — пример всё ещё указывает `App\Domain\Exception\NotFoundException`
- `docs/arch.md:305` — актуальная архитектура указывает `App\Shared\Domain\Exception`

Детали:
README пакета говорит, что доменные исключения остаются в приложении, и приводит пример `App\Domain\Exception\NotFoundException`. В текущих изменениях этот слой перенесён в `App\Shared\Domain\Exception`, а старый namespace в коде приложения уже удалён.

Как исправить:
Нужно привести README пакета к новой структуре приложения и новому поведению API-ошибок.

Конкретные шаги:

- В `tools/api-error/README.md` заменить пример namespace на `App\Shared\Domain\Exception\NotFoundException`.
- Рядом коротко уточнить, что доменные исключения без поддерживаемого 4xx-кода возвращаются как обычная 500-ошибка без внутреннего сообщения.

## Рекомендации

- **Править обязательно:** 1, 2
- **На усмотрение автора:** нет

## Изменения после мета-ревью

### После plan-check / architecture-check / rules-check / quality-check

- **+ Добавлено:** замечание 1 про payload-класс в `Domain/ValueObject`.
- **~ Изменено:** оценка снижена с 92 до 88; раздел сверки с планом теперь фиксирует только проблему с multipart payload.
- **− Убрано:** фраза «явных проблем в коде не найдено».
- **Отклонено:** замечание про отсутствие `app/src/Modules/Media/Application/Contract`, потому что план не добавляет файловый сервис, а пустой каталог без файлов не коммитится; замечание про внеплановые правки `tools/api-error`, потому что правила и фиксы по `InvalidDomainValueException` требуют поведения 500; замечание про зависимости Cycle-атрибутов Entity на repository/typecast, потому что это прямо зафиксировано в плане; замечание про отсутствие `Stringable` у `MediaMultipartPart`, потому что правило уточнено для составных JSON VO; замечание про дублирование `recordTemporaryProcessingError()` и `recordPermanentProcessingError()`, потому что план требует два предметных метода; замечание про `ValueObjectCast` как слишком центральный класс, потому что текущий объём ответственности прямо описан в плане; сомнение по JSON typecast, потому что интеграционный `make test` прошёл.

## Применённые фиксы

Отчёт: `docs/review-fixes/2026-05-22_16-59_uncommitted-media-modular-monolith.md`
