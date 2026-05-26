---
title: Незакоммиченные изменения публичных пакетов
date: 2026-05-25 21:07
target: git diff HEAD + untracked files
mode: normal
score: 88
status: meta-reviewed
meta_reviewers:
  - plan-check:gpt-5.3-codex
  - architecture-check:gpt-5.3-codex
  - rules-check:gpt-5.3-codex
  - quality-check:gpt-5.3-codex
---

# Ревью: Незакоммиченные изменения публичных пакетов

## Оценка

**88/100.** После перепроверки оставлены только подтверждённые проблемы: повреждённые URL в lock-файлах и рассинхрон README с PHPStan-правилом `OpenApi id`.

## Проблемы сверки с планом

### 1. В lock-файлах повреждены ссылки Doctrine

Статус: частично

Контекст:
План требовал обновить lock-файлы пакетов после переименования через Composer и подготовить пакеты к публичной публикации. Lock-файл в публичном пакете должен оставаться корректным описанием зависимостей.

Проблема:
В трёх package-local `composer.lock` ссылки `doctrine-project.org` превратились в несуществующий домен `doctrine-gian_tiaga.phpstan_strict_rules.org`.

Риск:
Публичные артефакты пакетов содержат повреждённые метаданные сторонних зависимостей. Это ухудшает доверие к package-артефактам и показывает, что массовые замены задели нейтральный текст, который не относится к переименованию пакетов.

Где:

- `packages/spiral-openapi/composer.lock:315` — повреждён `homepage` для `doctrine/inflector`
- `packages/spiral-openapi/composer.lock:334` — повреждён funding URL для `doctrine/inflector`
- `packages/spiral-openapi/composer.lock:397` — повреждён `homepage` для `doctrine/lexer`
- `packages/spiral-openapi/composer.lock:411` — повреждён funding URL для `doctrine/lexer`
- `packages/spiral-api-errors/composer.lock:315` — повреждён `homepage` для `doctrine/inflector`
- `packages/spiral-api-errors/composer.lock:334` — повреждён funding URL для `doctrine/inflector`
- `packages/spiral-api-errors/composer.lock:397` — повреждён `homepage` для `doctrine/lexer`
- `packages/spiral-api-errors/composer.lock:411` — повреждён funding URL для `doctrine/lexer`
- `packages/spiral-cqrs/composer.lock:409` — повреждён `homepage` для `doctrine/inflector`
- `packages/spiral-cqrs/composer.lock:428` — повреждён funding URL для `doctrine/inflector`
- `packages/spiral-cqrs/composer.lock:491` — повреждён `homepage` для `doctrine/lexer`
- `packages/spiral-cqrs/composer.lock:505` — повреждён funding URL для `doctrine/lexer`

## Замечания

### 1. README описывает не тот формат `OpenApi id`, который проверяет код

Тип: `bug`

Рекомендация: `править обязательно`

Контекст:
README публичного пакета должен совпадать с фактическим поведением. Здесь документация говорит пользователю, какие значения можно писать в `#[OpenApi(id: ...)]`, а PHPStan-правило затем проверяет эти значения.

Риск:
Пользователь может взять допустимый по README идентификатор с точкой, дефисом или двоеточием, например `api.v1.health`, а PHPStan отклонит его. Это делает публичный контракт пакета ненадёжным.

Где:

- `packages/spiral-openapi/README.md:157` — README перечисляет разрешённые символы
- `packages/spiral-openapi/README.md:160` — README разрешает `.`, `-`, `:`
- `packages/spiral-openapi/src/PHPStan/Rules/OpenApiAttributeRule.php:43` — фактическое правило разрешает только первый символ `a-z`, далее `a-zA-Z0-9_`
- `packages/spiral-openapi/tests/PHPStan/Fixtures/OpenApiAttributeInvalid.fixture:13` — тестовый пример `Invalid-Id` не проверяет отдельно допустимость или запрет `.`, `-`, `:`

Детали:
Регулярное выражение `/^[a-z][a-zA-Z0-9_]*$/` не допускает точку, дефис и двоеточие. README при этом прямо говорит, что эти символы допустимы.

Как исправить:
Нужно выбрать один публичный контракт: либо расширить правило до формата из README, либо сузить README до текущего поведения правила.

Конкретные шаги:

- Если точки, дефисы и двоеточия действительно должны быть разрешены, обновить регулярное выражение в `OpenApiAttributeRule`.
- Добавить PHPStan-тест на допустимый `id` с символами, которые заявлены в README.
- Если текущая строгая форма правильная, переписать пункт README и добавить тест, который явно подтверждает выбранный формат.

## Рекомендации

- **Править обязательно:** проблема сверки с планом 1, замечание 1
- **На усмотрение автора:** нет

## Изменения после мета-ревью

### После plan-check / architecture-check / rules-check / quality-check

- **+ Добавлено:** полный список повреждённых URL в lock-файлах; уточнение про тест `OpenApi id`.
- **~ Изменено:** lock-файлы описаны как повреждение package-артефактов; `OpenApi id` описан как рассинхрон публичного контракта README и PHPStan-правила.
- **− Убрано:** недоказанная привязка проблемы lock-файлов к `composer validate`; спорные замечания про `mixed` и форматирование после ручной перепроверки.
- **Отклонено:** пункт plan-check о том, что нельзя проверить изменения вне `packages/*`, потому что цель ревью — все незакоммиченные изменения через `git diff HEAD` плюс untracked-файлы; предложения о выполненных пунктах не добавлялись, так как ревью содержит только проблемы.

## Применённые фиксы

Отчёт: `docs/review-fixes/2026-05-25_21-27_uncommitted-public-packages.md`
