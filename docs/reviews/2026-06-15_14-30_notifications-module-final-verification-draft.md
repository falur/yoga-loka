---
title: Модуль уведомлений (Notifications) — заключительная верификация после 6 кругов доводки
date: 2026-06-15 14:30
target: git diff HEAD + untracked
plan: docs/plans/2026-06-13_13-55_notifications_module.md
mode: draft
score: 97
status: meta-reviewed
meta_reviewers: [plan-check (sonnet), architecture-check (sonnet), rules-check (sonnet), quality-check (sonnet)]
---

# Ревью: Модуль уведомлений (Notifications) — заключительная верификация после 6 кругов доводки

## Оценка

**97/100.** Заключительное черновое ревью после шести кругов доводки. Все обязательные и optional
пункты прошлых кругов закрыты и подтверждаются прямо в коде: оба доменных исключения доставки
(`CentrifugoPublishException`, `FcmPushFailedException`) перенесены в `Application/Exception`
(директории `Infrastructure/Exception` в модуле больше нет, остаточных импортов
`Notifications\Infrastructure\Exception` ни в коде, ни в тестах нет, доставочные Job импортируют их из
`Application/Exception` — зависимость `Presentation → Infrastructure` устранена); `MarkAllNotificationsReadHandler`
использует `foreach` вместо `->each()`; одноразовые `*_FAILURE_MESSAGE`-константы в Job инлайнены в
`RetryException(reason: ...)`; stray-папка `tools/` из рабочего дерева удалена (в `git status` её нет).

Подтверждено независимой проверкой кода: невалидный path-`id` и битый `cursor` отбиваются на границе
как 422 (`#[Assert\Uuid]`), удаление токена скоупится по владельцу (нет IDOR), Centrifugo ходит с
`Authorization: apikey`, разбирает тело при 2xx и считает `error` сбоем, рассылка идемпотентна по
`outbox_id`, классификация временных/терминальных сбоев в доставочных Job соответствует образцу
`ProcessMediaJob`, рефактор `OutboxEventDate` под уточнённое `rules.md:21` консистентен. Механический
скан модуля чистый: везде `declare(strict_types=1)`, нет `switch`, нет loose-сравнений, нет `assert()`,
нет `env()` в коде модуля.

Найдена одна новая мелочь уровня «на усмотрение автора» — избыточный `key: 'id'` в `#[Route]`-атрибуте
одного фильтра. Это косметика, не влияет на поведение и проходит зелёный гейт. Балл снижен с 100 чисто
символически за это единственное расхождение со стилем; обязательных пунктов нет.

## Проблемы сверки с планом

Явных проблем по плану не найдено. Все 7 фаз выполнены и совпадают с разделами «Данные и БД» и «API и
внешние контракты»: 3 таблицы с нужными индексами и unique (включая `notifications.outbox_id unique`),
8 роутов (подтверждено `#[Route]`-атрибутами на трёх контроллерах), 3 пары message→Job
зарегистрированы и в `NotificationsBootloader.boot()`, и в `app/config/queue.php`, OpenApi-конфиг
расширен на список `sourcePaths` + `apiNamespace: App\Modules`, `openapi.yml` перегенерирован.
Размещение доставочных исключений в `Application/Exception` приведено в соответствие с `arch.md` и
`rules.md:46` (в прошлом круге это был единственный обязательный пункт — теперь закрыт).

Единственное осознанное отклонение от буквы плана — Centrifugo-заголовок: план (строка 168)
предписывал `X-API-Key`, реализация использует `Authorization: apikey <key>` (актуальный протокол
Centrifugo v6). Это не дефект, а намеренное решение, закрытое в 4-м круге; зафиксировано ниже в
«Заметках верификации», проблемой не является.

## Замечания

### 1. Избыточный `key: 'id'` в `#[Route]`-атрибуте `MarkNotificationReadFilter`

Фильтр отметки уведомления прочитанным читает идентификатор из сегмента пути через
`#[Route(key: 'id')]` на свойстве `$id`. Атрибут ввода `Spiral\Filters\Attribute\Input\Route` по
умолчанию берёт ключ из имени свойства (`$this->key ?? $property->getName()`), поэтому при свойстве
`$id` явный `key: 'id'` ничего не добавляет — это та же избыточность, что `rules.md:59` описывает для
`#[Post]` («`key` указывать только когда имя в JSON отличается от имени свойства»). Принцип
распространяется на все input-атрибуты Spiral Filter.

Практического риска нет: маршрутный сегмент называется `<id>`, свойство — `$id`, поведение при
удалении аргумента не меняется. Это чистая гигиена стиля и единственное расхождение фильтров модуля с
правилом о ключах ввода. Уровень — «на усмотрение автора»: убрать `key: 'id'` или оставить как явную
пометку привязки к сегменту `<id>` — на усмотрение, но по букве `rules.md:59` ключ здесь лишний.

Технические детали:

- **Тип:** `quality` (стиль Filter, `rules.md:59`)
- **Рекомендация:** `на усмотрение автора`
- **Где:** `app/src/Modules/Notifications/Presentation/Http/Filter/Notification/MarkNotificationReadFilter.php:23`
- **Что подтверждает проблему:** `Spiral\Filters\Attribute\Input\AbstractInput::getKey()` —
  `return $this->key ?? $property->getName();`; свойство называется `$id`, ключ задан `'id'`, значения
  совпадают. Для сравнения, `NotificationRecipientFilter` и поле `authUserId` в этом же фильтре `key`
  задают оправданно (имя request-attribute `authUserId` фиксировано контрактом `rules.md:57`, не
  выводится из свойства иначе).
- **Как исправить:** заменить `#[Route(key: 'id')]` на `#[Route]`; `#[Assert\NotBlank]`/`#[Assert\Uuid]`
  и поведение остаются прежними.
- **Тесты:** не требуется — существующий `testMarkNotificationReadReturns422ForInvalidId` продолжит
  ловить битый формат `id`, маршрутная привязка не меняется.

## Рекомендации

- **Править обязательно:** нет
- **На усмотрение автора:** 1 (замечание №1 — избыточный `key: 'id'`)

## Заметки верификации (проблемами не являются — зафиксированы, чтобы не поднимать повторно)

- **`NotificationReadState` и форма null-object.** Класс `final readonly`; `value(): ?\DateTimeImmutable`
  — это документированный мост к nullable-колонке `read_at` (паттерн `MediaExpiration`, `rules.md:21,
  rules.md:85`), наружу домена `?T` не виден (используются `isRead()`/`markedAt()`). Не нарушение
  (агент-эксплорер ошибочно посчитал класс не-readonly).
- **PHPDoc-типы `list<NotificationChannel>`, `list<string>`, `array<string, string>`,
  `array<string, string|NotificationActionPayload|null>`** в `NotificationChannelDefaults`,
  `NotificationAction`, `RealtimeNotificationPayload` — это простые `list<T>` / `array<int|string, T>`,
  где `T` не массив/shape/tuple. Разрешены `rules.md:105`; запрет `rules.md:103` касается shape-ов
  (`array{foo: string}`) и вложенных массивов. PHPStan level max это пропускает. Не нарушение.
- **`catch (\Throwable $exception)` в трёх доставочных Job** (`DispatchNotificationJob`,
  `SendPushNotificationJob`, `PublishRealtimeNotificationJob`). `rules.md:51` разрешает try-catch на
  границе системы (Job); запрет касается только catch-rethrow без реальной обработки. Здесь обработка
  есть: логирование + классификация временный/терминальный + конверсия в `RetryException`. Образец
  в проекте — `ProcessMediaJob` ловит ровно `\Throwable` так же. Не нарушение, не воскрешать.
- **Нормализация порядка в `NotificationChannelDefaults::equals()` через `\sort()` локальных копий** —
  ранее отклонено; копии берутся из `jsonSerialize()`, состояние VO не мутируется. Новых аргументов нет.
- **Centrifugo-заголовок `Authorization: apikey` вместо плановского `X-API-Key`** — осознанное
  отклонение, закрыто в 4-м круге (актуальный протокол Centrifugo v6). Не воскрешать.
- **`findAllForUser` вместо `findActiveForUser`** в push-доставке — ранее отклонённое переименование
  (у `notification_device_tokens` нет колонки статуса). Новых аргументов нет.
- **`resolveType()` через `all()->first()`, N→1 выборка настроек, батчинг mark-all (MVP)** — ранее
  зафиксированы/отклонены, новых аргументов нет.

## Изменения после мета-ревью

Запущены `plan-check`, `architecture-check`, `rules-check`, `quality-check` (все на `sonnet`, один
batch). Все четыре независимо подтвердили адекватность ревью по коду; новых обязательных нарушений
не найдено, score 97 признан корректным (без завышения/занижения), ложных срабатываний нет.

### После plan-check
- **+ Добавлено:** в раздел «Проблемы сверки с планом» — явная пометка единственного осознанного
  отклонения от буквы плана (Centrifugo `Authorization: apikey` вместо плановского `X-API-Key`,
  Centrifugo v6), со ссылкой на «Заметки верификации». Раздел сверки ранее это умалчивал; теперь
  он полный. Содержательно проблемой не является — отклонение закрыто в 4-м круге.
- **~ Изменено:** —
- **− Убрано:** —
- **Отклонено:** поднимать `Authorization: apikey` как замечание — нет, это намеренное решение с
  обоснованием, не дефект (подтверждено самим plan-check: «существенного значения не имеет»).

### После architecture-check
- **+ Добавлено:** —
- **~ Изменено:** —
- **− Убрано:** —
- **Отклонено:** ничего не предложено. Перенос `CentrifugoPublishException`/`FcmPushFailedException`
  в `Application/Exception` подтверждён прямо в коде (директории `Infrastructure/Exception` нет,
  остаточных импортов нет, Job импортируют из `Application/Exception`); зависимость
  `Presentation → Infrastructure` устранена. Границы слоёв и модулей соблюдены. Новых архитектурных
  нарушений нет.

### После rules-check
- **+ Добавлено:** —
- **~ Изменено:** —
- **− Убрано:** —
- **Отклонено:** ничего не предложено. Механический скан подтверждён (везде `declare(strict_types=1)`,
  нет `switch`/loose/`assert()`/`env()` в коде модуля). Optional про `key: 'id'` описан корректно;
  аналогичной избыточности в других фильтрах нет; `key: 'authUserId'` оправдан `rules.md:57`.
  Пропущенных нарушений правил и ложных срабатываний не найдено; понижение со 100 до 97 в норме.

### После quality-check
- **+ Добавлено:** —
- **~ Изменено:** —
- **− Убрано:** —
- **Отклонено:** ничего не предложено. Три доставочных Job намеренно различны (общий базовый класс
  добавил бы хрупкость без выгоды); все `handle()` укладываются в ~40 строк и здраво декомпозированы;
  `CentrifugoService` как тонкая обёртка над `CentrifugoClient` предписана `rules.md:89`;
  `resolveType()` через `all()->first()` оправдан семантикой 422. Score 97 адекватен качеству.

Итог: статус `draft` → `meta-reviewed`. Обязательных пунктов нет; единственное применимое
optional — замечание №1 (избыточный `key: 'id'`). Кросс-CLI не запускался (режим `normal`).
