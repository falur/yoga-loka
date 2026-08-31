---
review: docs/reviews/2026-06-14_00-05_notifications-module-uncommitted-draft.md
date: 2026-06-15 12:15
status: done
---

# Фиксы по ревью: Модуль уведомлений (Notifications) — незакоммиченный diff (четвёртый круг)

Режим: `apply-optional`. Это **восстановление** после обрыва предыдущего прогона по сетевой
ошибке: обязательная правка уже была применена оборвавшимся прогоном. Моя задача — сверить
фактическое состояние по коду, доделать недостающее и оформить канонический отчёт.

В ревью один обязательный пункт и **ноль** «на усмотрение автора».

## Применённые правки

| # | Замечание | Файлы | Тесты | Статус |
|---|-----------|-------|-------|--------|
| 1 | `POST /api/v1/notifications/<id>/read` отдавал 500 на некорректный `id` в пути вместо клиентской 4xx | `app/src/Modules/Notifications/Presentation/Http/Filter/Notification/MarkNotificationReadFilter.php` | `tests/Feature/Modules/Notifications/Http/NotificationHttpTest.php::testMarkNotificationReadReturns422ForInvalidId` (1 ✓) | ✓ применено (применено оборвавшимся прогоном, мной сверено по коду) |

## Что сделано в этом восстановлении

Сверил фактическое состояние по коду — обязательный пункт закрыт ровно по предпочтённому
ревью варианту (б): валидация формата на HTTP-границе через Filter, симметрично уже
исправленному `cursor`.

- **Валидация на границе.** В `MarkNotificationReadFilter` на поле `$id` (читается из сегмента
  пути через `#[Route(key: 'id')]`) стоят `#[Assert\NotBlank]` и `#[Assert\Uuid]`. Битый формат
  `id` теперь отбивается Spiral Filter-ом как 422 (`ApiValidationErrorsRenderer`), не доходя до
  доменного VO `NotificationId::fromString()`, который на невалидный UUID бросает
  `InvalidDomainValueException` (500). Решение симметрично `ListNotificationsFilter::cursor`
  (`#[Assert\Uuid]`). Docblock фильтра поясняет причину.
- **Тест.** `testMarkNotificationReadReturns422ForInvalidId`: `POST /api/v1/notifications/not-a-uuid/read`
  → `assertUnprocessable()` (422). Симметричен существующему `testListNotificationsReturns422ForInvalidCursor`.
  Прогон зелёный, общее число тестов выросло с 565 (третий круг) до 566.
- **Контракт 422 vs 404 сохранён.** «Битый формат» → 422 (фильтр), «нет/чужое» (валидный UUID,
  но не найдено/чужой получатель) → 404 (`NotFoundException` в `MarkNotificationReadHandler`).
  Существующие `testMarkNotificationRead` (200) и `testMarkNotificationReadReturns404ForForeignNotification`
  (404) остались зелёными.

Доделывать ничего не потребовалось: правка кода и тест из оборвавшегося прогона на месте,
корректны и проходят полный гейт.

## Проверенная и отклонённая микро-правка вне scope ревью

В ходе сверки я рассмотрел чистку кажущегося «мёртвым» параметра `string $id` в
`NotificationController::read()` (тело метода использует `$markNotificationReadFilter->id`).
**Отклонено.** Параметр не мёртвый: генератор OpenAPI
(`packages/spiral-openapi` → `SpecBuilder::parameters()`) выводит path-параметр операции из
параметров сигнатуры метода контроллера, чьё имя совпадает с сегментом `<id>` маршрута, а
свойства фильтра с `SOURCE_PATH` в path-параметры **не** добавляет. Удаление `string $id`
выкинуло бы `id` из `public/openapi/openapi.yml` и сломало бы `OpenApiGenerateCommandTest`.
Поэтому `string $id` оставлен — он load-bearing для генерации API-документации, и подход
оборвавшегося прогона (оставить параметр + валидировать через фильтр) корректен. Пробную
правку откатил, состояние кода идентично результату оборвавшегося прогона.

## Решения по optional

В ревью нет пунктов «На усмотрение автора» (раздел «Рекомендации»: `Править обязательно: 1`,
`На усмотрение автора: —`). Незакрытых optional-пунктов также не найдено. Принимать/отклонять
по `apply-optional` нечего.

## Финальная проверка

- **Гейт:** `make qa` (стиль + PHPStan level max + один coverage-run на PCOV, ParaTest 4 процесса
  в Docker) — ✓ зелёный, exit 0.
- **Стиль (php-cs-fixer 3.95.1):** ✓ `Found 0 of 605 files that can be fixed`.
- **PHPStan (level max):** ✓ `[OK] No errors`.
- **Тесты:** ✓ 566 тестов, 1663 ассерта, все зелёные.
- **Покрытие:** ✓ `Покрытие 100.00% соответствует порогу 100.00%`.
- **Заметки:** 1 PHPUnit Notice (`OK, but there were issues!` / `PHPUnit Notices: 1`) — это
  notice, не падение; присутствовал и в прошлом (третьем) круге, не связан с правкой по этому
  ревью (правка — два атрибута валидации в фильтре + один feature-тест). Гейт зелёный.
