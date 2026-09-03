---
title: Размещение PublicApi внутри границы Spiral
date: 2026-09-03 13:33
mode: normal
decision_mode: ask_each_time
status: draft
reviewer: none
sources:
  rules: docs/rules.md
  arch: docs/arch.md
  business: []
  references: [docs/references/public-contract.md]
---

# Размещение PublicApi внутри границы Spiral

## Суть

Исследовали, должна ли реализация синхронного публичного контракта модуля оставаться в `Infrastructure/PublicApi` или находиться внутри `Infrastructure/Spiral`.

Публичный контракт является входом в модуль для соседнего модуля и передаёт вызов сценарию Application (`docs/arch.md:130`, `docs/arch.md:164`). При этом целевая архитектура уже собирает входные адаптеры текущего runtime — HTTP, Console, Job и Temporal — в `Infrastructure/Spiral` (`docs/arch.md:43`, `docs/arch.md:166`). Вопрос не затрагивает бизнес-поведение, поэтому применимых бизнес-карточек нет.

## Решение

Реализацию собственного `Public/Contract` размещать в `Infrastructure/Spiral/PublicApi`:

```text
Modules/{Module}/
  Public/
    Contract/
    Dto/
  Application/
  Infrastructure/
    Spiral/
      PublicApi/
        {Name}Provider.php
```

Provider остаётся отдельной ролью входного адаптера: преобразует аргументы публичного контракта в Command или Query, вызывает сценарий через шину и преобразует Result в `Public/Dto`. Бизнес-правила, Repository, Reader и обращения к соседним модулям в нём запрещены (`docs/arch.md:164`). Перенос меняет технологическую группировку, но не ответственность Provider.

Этот вариант выбран по следующим причинам:

- эталонный Provider вызывает Query через `GianTiaga\SpiralCqrs\QueryBusInterface`, то есть участвует в принятом Spiral CQRS runtime (`docs/references/public-contract.md:47`, `docs/references/public-contract.md:53`, `docs/references/public-contract.md:65`);
- `Infrastructure/Spiral` уже определён как место прямых зависимостей модуля от Spiral и его входных адаптеров (`docs/arch.md:166`);
- единая папка показывает общее основание изменения: при замене Spiral вместе рассматриваются HTTP, Console, Job, Temporal, Bootloader и реализация межмодульного входа;
- ранее согласованное исследование уже выбрало правило собирать весь зависящий от Spiral код в `Infrastructure/Spiral`, но оставило `PublicApi` соседней папкой (`docs/artifacts/researches/2026-09-01_10-41_framework-boundaries-and-tests.md:27`, `docs/artifacts/researches/2026-09-01_10-41_framework-boundaries-and-tests.md:61`). Текущее решение устраняет это исключение.

Вариант `Infrastructure/PublicApi` отклонён: он был бы оправдан для реализации, независимой от Spiral runtime и его CQRS-шины. Такой вариант противоречит выбранному эталону Provider, который вызывает сценарий через `SpiralCqrs` (`docs/references/public-contract.md:53`, `docs/references/public-contract.md:65`).

Риск названия `PublicApi` состоит в возможной путанице с HTTP API. Он принимается, потому что архитектура уже закрепляет термин `Public` за опубликованным языком модуля, а карточка называет реализацию `Provider` (`docs/arch.md:20`, `docs/arch.md:105`, `docs/references/public-contract.md:55`). Контекст полного пути `Infrastructure/Spiral/PublicApi` отделяет этот адаптер от `Spiral/Http`.

Новые зависимости и версии ПО не выбирались, поэтому проверка актуальных версий не требуется.

## Ответы на вопросы

1. Пользователь выбрал перенести реализации публичных контрактов в `Infrastructure/Spiral/PublicApi`.

## Итог

Целевая структура должна размещать `{Name}Provider`, реализующий собственный `Public/Contract`, в `Modules/{Module}/Infrastructure/Spiral/PublicApi`. `Infrastructure/PublicApi` как отдельная соседняя граница больше не используется. Сам контракт и DTO остаются в `Public`, а ответственность Provider не меняется.
