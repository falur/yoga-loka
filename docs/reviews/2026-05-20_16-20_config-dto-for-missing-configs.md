---
title: DTO для всех недостающих конфигов
date: 2026-05-20 16:20
target: git diff HEAD + untracked
mode: normal
score: 84
status: meta-reviewed
meta_reviewers:
  - plan-check:gpt-5.3-codex
  - architecture-check:gpt-5.3-codex
  - rules-check:gpt-5.3-codex
  - quality-check:gpt-5.3-codex
---

# Ревью: DTO для всех недостающих конфигов

## Оценка

**84/100.** Основной объём плана выполнен, но есть неполное соответствие по `cycle.schema.generators` и слишком широкий тип для фабрик коллекций. Ещё не хватает явной проверки, что ошибки маппинга не раскрывают `key` и `token` из storage-конфига.

## Проблемы сверки с планом

### 1. Не покрыта форма `cycle.schema.generators` с группами генераторов

Статус: частично

Контекст:
План требовал описать `cycle.schema.generators` не только как простой список классов или `null`, но и как карту групп, где у каждой группы свой список классов.

Проблема:
DTO и тесты покрывают только `list<class-string>|null`. Вариант `array<string, list<class-string>>` из плана не описан и не проверен.

Риск:
Если Spiral или проектная настройка задаст генераторы группами, typed config может отклонить рабочий framework-конфиг или дать неверную подсказку при ошибке.

Где:

- `app/src/Infrastructure/Configuration/Cycle/CycleSchemaConfig.php:11` — PHPDoc для `generators`
- `tests/Unit/Infrastructure/Configuration/ComplexConfigMapperTest.php:75` — тест покрывает только простой список

### 2. Не проверено скрытие всех секретных storage-полей

Статус: частично

Контекст:
План требовал проверить, что ошибка маппинга не показывает DSN, S3 key, S3 secret, token и password.

Проблема:
Тесты проверяют DSN и `secret-password`, но нет явной проверки, что сообщение ошибки не содержит значения `key` и `token` из storage-конфига.

Риск:
Общая логика сейчас выглядит безопасной, но плановый сценарий про конкретные S3-поля не закреплён тестом. При будущей правке исключения можно случайно вернуть утечку части storage-секретов.

Где:

- `tests/Unit/Infrastructure/Configuration/ConfigMapperTest.php:75` — проверка DSN
- `tests/Unit/Infrastructure/Configuration/ComplexConfigMapperTest.php:275` — проверка storage secret без `key` и `token`
- `tests/Unit/Infrastructure/Configuration/ConfigShapeTest.php:46` — shape-helper проверяет типы `key` и `token`, но не сообщение ошибки маппинга

## Замечания

### 1. Фабрика коллекций описана слишком широким типом

Тип: `architecture`

Рекомендация: `править обязательно`

Контекст:
Typed config должен сужать форму framework-конфига до ожидаемых типов. Для фабрик коллекций Cycle ожидается объект, который реализует интерфейс фабрики коллекций.

Риск:
Сейчас любой объект может пройти маппинг как фабрика коллекций. Ошибка проявится позже, когда Cycle попробует использовать значение как фабрику, и причина будет менее очевидной.

Где:

- `app/src/Infrastructure/Configuration/Cycle/CycleCollectionFactoryConfig.php:10` — поле `factory` объявлено как `object`
- `vendor/spiral/cycle-bridge/src/Config/CycleConfig.php:21` — framework-конфиг ожидает `CollectionFactoryInterface`

Детали:
План требовал `array<string, CollectionFactoryInterface>` для `cycle.schema.collections.factories`. Реализация добавила wrapper DTO с `object $factory`, поэтому контракт typed config стал шире фактического контракта Cycle.

Как исправить:
Описать фабрики коллекций через интерфейс Cycle или другой узкий тип, который не принимает произвольные объекты.

Конкретные шаги:

- Заменить `object $factory` на тип, совместимый с `Cycle\ORM\Collection\CollectionFactoryInterface`.
- Обновить тест маппинга так, чтобы некорректный объект в `factories` давал безопасную ошибку.

## Рекомендации

- **Править обязательно:** проблема плана 1, замечание 1
- **На усмотрение автора:** проблема плана 2

## Изменения после мета-ревью

### После plan-check / architecture-check / rules-check / quality-check

- **+ Добавлено:** неполное покрытие `cycle.schema.generators`; неполная проверка скрытия `key` и `token`; слишком широкий тип `object` для фабрики коллекций.
- **~ Изменено:** оценка снижена со 100 до 84, вывод заменён с «проблем нет» на список подтверждённых рисков.
- **− Убрано:** формулировка «явных проблем не найдено».
- **Отклонено:** замечание про `allowPermissiveTypes()` и `allowScalarValueCasting()`, потому что это осознанный компромисс из журнала выполнения для vendor-объектов и env-значений; замечание про `mixed` в PHPDoc тестовых helper-ов, потому что это не публичный проектный контракт и PHPStan его принимает; замечание про недоказанные проверки, потому что в ходе ревью заново запущены `composer test -- --filter Configuration`, `composer phpstan`, `PROJECT_NAME=yoga-loka-spiral-2-work-1 make test` и `PROJECT_NAME=yoga-loka-spiral-2-work-1 make phpstan`.

## Применённые фиксы

Отчёт: `docs/review-fixes/2026-05-20_16-41_config-dto-for-missing-configs.md`
