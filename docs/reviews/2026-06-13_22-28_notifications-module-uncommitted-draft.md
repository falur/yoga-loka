---
title: Модуль уведомлений (Notifications) — незакоммиченный diff
date: 2026-06-13 22:28
target: git diff HEAD + untracked
plan: docs/plans/2026-06-13_13-55_notifications_module.md
mode: draft
score: 81
status: meta-reviewed
meta_reviewers: [architecture-check (sonnet), rules-check (sonnet), plan-check (sonnet), quality-check (sonnet)]
---

# Ревью: Модуль уведомлений (Notifications) — незакоммиченный diff

## Оценка

**81/100.** Реализация близко следует плану и правилам проекта: чистый DDD, корректный
outbox-поток, аккуратные null-object VO и typecast, тонкие контроллеры. Снижение — за
авторизационную дыру в удалении push-токена (можно удалить чужой токен) и за то, что
невалидный `cursor` в списке уведомлений отдаётся как 500 вместо 422. Дополнительно мета-ревью
выявило два мелких отступления от правил/плана (ручной каст enum в Handler вместо Filter;
pure-трансформации через `array_map` вместо collection-пайплайна) — оба на усмотрение автора.
Раздел сверки с планом уточнён: удаление токена без проверки владельца — это пробел самого
плана, а не отклонение реализации от него. Остальные пункты — на усмотрение автора.

## Проблемы сверки с планом

### 1. План не предусмотрел проверку владельца при удалении токена — пробел плана, унаследованный реализацией

Реализация буквально следует алгоритму плана: целевой алгоритм (план, «DELETE → findByToken
(нет → NotFoundException) → delete») не упоминает `authUserId`, и удаление действительно
ищет/удаляет строку только по значению токена. То есть это **не отклонение реализации от
плана** — реализация выполнила алгоритм точно. Сам алгоритм плана упустил требование изоляции
по пользователю: фраза «все 8 роутов под `authUserId`» в плане означает лишь, что роут
принимает `authUserId` через `#[Attribute]`, а не что бизнес-логика удаления скоупится по
пользователю. Практический риск (IDOR на удаление чужого токена) описан в разделе «Замечания»,
пункт 1 — там же исправление.

Технические детали:

- **Статус:** пробел плана (реализация плану соответствует)
- **Где:** `app/src/Modules/Notifications/Application/Command/DeviceToken/RemoveNotificationDeviceToken/RemoveNotificationDeviceTokenCommand.php` (поле только `token`),
  `RemoveNotificationDeviceTokenHandler.php:30-43`,
  `app/src/Modules/Notifications/Presentation/Http/Filter/DeviceToken/RemoveNotificationDeviceTokenFilter.php` (нет `authUserId`).
- **Что подтверждает проблему:** в отличие от остальных контроллеров, `NotificationDeviceTokenController::remove`
  не читает `authUserId`; команда и фильтр про пользователя не знают; `NotificationDeviceTokenRepository::findByToken`
  фильтрует только по `token`. Это ровно тот алгоритм, что задан планом.
- **Как исправить:** см. «Замечания» пункт 1 (добавить `authUserId` в фильтр/команду и
  скоупить выборку по пользователю). Это исправление выходит за рамки плана — план эту проверку
  не закладывал.

### 2. `platform`/`channel` приходят как `string` с ручным `tryFrom()` в Handler, хотя план задал enum в Filter

План (фаза 7, «Filters»: `RegisterNotificationDeviceTokenFilter` — «`#[Post]` token, **platform
enum**») предписывает типизировать enum прямо в Filter. По факту `platform` и `channel`
объявлены как `string`, а каст в enum делается вручную в Handler-е через `::tryFrom()` →
`ValidationException`. Поведение корректное (невалидное значение даёт 422), но это и расхождение
с планом, и нарушение `rules.md` (см. «Замечания», пункт 6) — каст enum должен быть в Filter, а
не в Handler.

Технические детали:

- **Статус:** отклонение от плана (поведение пользователя корректно)
- **Где:** `RegisterNotificationDeviceTokenFilter.php:28` (`public string $platform`),
  `RegisterNotificationDeviceTokenHandler.php:36-37` (`DevicePlatform::tryFrom(...)`),
  `Filter/Setting/NotificationSettingUpdateInput.php:22` (`public string $channel`),
  `UpdateNotificationSettingsHandler` (ручной каст канала).
- **Что подтверждает проблему:** оба фильтра расширяют `AttributesFilter` и держат строковые
  поля; enum-каст вынесен в Application-слой вместо HTTP-границы.
- **Как исправить:** см. «Замечания», пункт 6.

## Замечания

### 1. Удаление push-токена не проверяет владельца (авторизационная дыра)

Эндпоинт удаления push-токена находит строку только по значению токена и удаляет её, не
сверяя, что токен принадлежит текущему пользователю. Пока реального auth-middleware нет,
это не выстреливает, но как только он появится (модуль Auth), любой аутентифицированный
клиент сможет удалить push-токен другого пользователя, передав его значение в теле
запроса. Push-токены не являются секретом уровня пароля и вполне могут утечь между
устройствами/логами, поэтому полагаться на их «неугадываемость» нельзя.

Дополнительный нюанс: при регистрации токен сознательно переустанавливается на нового
владельца (устройство сменило аккаунт) — это нормально. Но удаление должно затрагивать
только токены текущего пользователя, иначе появляется односторонний IDOR на удаление.

Технические детали:

- **Тип:** `security`
- **Рекомендация:** `править обязательно`
- **Где:** `RemoveNotificationDeviceTokenHandler.php:32-36`, `RemoveNotificationDeviceTokenCommand.php`,
  `RemoveNotificationDeviceTokenFilter.php`, `NotificationDeviceTokenRepository.php:28-31`.
- **Что подтверждает проблему:** команда несёт только `token`; handler делает
  `findByToken(...)` → `delete`; ни handler, ни repository не используют `userId`; фильтр
  не объявляет `#[Attribute(key: 'authUserId')]`.
- **Как исправить:** добавить `authUserId` в `RemoveNotificationDeviceTokenFilter` и в
  команду; в репозитории завести доменный метод вида `findByTokenForUser(DeviceToken, UserId)`
  (или передавать `UserId` в существующий поиск) и удалять строку, только если она
  принадлежит пользователю; отсутствие/чужой токен → `NotFoundException` (404), как сейчас
  для отсутствующего.
- **Тесты:** добавить интеграционный тест: пользователь A регистрирует токен, пользователь B
  пытается его удалить → 404, токен остаётся. Существующий happy-path и 404-тест сохранить.

### 2. Невалидный `cursor` в списке уведомлений отдаётся как 500, а не 422

Фильтр списка уведомлений валидирует `limit`, но не `cursor`: строка курсора уходит в
Query-handler как есть, где из неё пытаются собрать `NotificationId` (UUID v7). Если клиент
прислал курсор не в формате UUID v7 (опечатка, обрезанное значение, чужой формат),
доменный VO бросает внутреннюю доменную ошибку, которую интерцептор превращает в 500.
Для входных данных клиента это неверный класс ответа: некорректный query-параметр — это
ошибка запроса (422/4xx), а не сбой сервера. На практике это зашумит мониторинг 5xx и
запутает клиент при пагинации.

Технические детали:

- **Тип:** `bug`
- **Рекомендация:** `править обязательно`
- **Где:** `app/src/Modules/Notifications/Presentation/Http/Filter/Notification/ListNotificationsFilter.php:21-22`
  (cursor без проверки формата), `ListNotificationsHandler.php:24` (`NotificationId::fromString($query->cursor)`),
  `AbstractUuidV7Id::fromString` → `InvalidDomainValueException` (код 500).
- **Что подтверждает проблему:** `InvalidDomainValueException` имеет статус 500
  (`app/src/Shared/Domain/Exception/InvalidDomainValueException.php`), а `ApiExceptionInterceptor`
  отдаёт для неё обычную 500 без сообщения. В HTTP-тесте
  (`tests/Feature/Modules/Notifications/Http/NotificationHttpTest.php`) кейс невалидного
  курсора не покрыт.
- **Как исправить:** провалидировать формат курсора на границе — например, `#[Assert\Uuid]`
  на свойстве `cursor` фильтра (тогда плохой курсор даст 422 от валидатора), либо явно
  ловить невалидный курсор в presentation-границе и бросать `ValidationException`. Не
  тащить решение в домен.
- **Тесты:** добавить интеграционный тест `GET /api/v1/notifications?cursor=not-a-uuid` →
  422 (не 500).

### 3. Репозиторий токенов называется `findActiveForUser`, но «активность» нигде не выражена

Метод выборки токенов называется так, будто отбирает активные/живые токены, но фактически
возвращает все токены пользователя без какого-либо признака активности (в схеме нет
`revoked`/`disabled`/`last_seen`). Имя обещает фильтрацию, которой нет. Это не баг сейчас,
но вводит в заблуждение при чтении и провоцирует будущую ошибку: кто-то решит, что
«неактивные» уже отфильтрованы, и не добавит нужное условие, когда появится отзыв токенов.

Технические детали:

- **Тип:** `quality`
- **Рекомендация:** `на усмотрение автора`
- **Где:** `app/src/Modules/Notifications/Repository/NotificationDeviceTokenRepository.php:18-26`,
  использование в `SendPushNotificationHandler.php:34`.
- **Что подтверждает проблему:** тело метода — `where('user_id', ...)->orderBy('id','DESC')->fetchAll()`,
  никакого фильтра «активности»; в миграции у `notification_device_tokens` нет колонки состояния.
- **Как исправить:** переименовать в `findForUser`/`findAllForUser`, либо явно ввести
  понятие активности (колонка + условие), если оно планируется. По текущему MVP достаточно
  переименования.
- **Тесты:** правка имени — обновить вызовы и тесты репозитория; новых сценариев не требуется.

### 4. `NotificationActionType::__toString()` возвращает пустую строку для none(), мимо контракта «нет значения»

VO типа цели перехода моделирует «нет перехода» как пустую строку внутри. `value()`,
`jsonSerialize()` и `presentValue()` аккуратно различают присутствие/отсутствие, но
`__toString()` для отсутствующего значения вернёт `''`, тогда как `presentValue()` для того
же состояния бросает исключение — два разных контракта на одно состояние. В текущем коде риск
гипотетический: наружу VO ходит только через `value()`/`presentValue()`, в строковую
интерполяцию `(string)` нигде не попадает. Замечание — на будущее: если VO случайно уйдёт в
строковой контекст, отсутствие перехода станет неотличимо от пустого кода.

Технические детали:

- **Тип:** `quality`
- **Рекомендация:** `на усмотрение автора`
- **Где:** `app/src/Modules/Notifications/Domain/ValueObject/NotificationActionType.php:62-66`
  (аналогично `NotificationActionId`).
- **Что подтверждает проблему:** `__toString()` возвращает `$this->value`, который для
  `none()` равен `''`; при этом `presentValue()` для того же состояния бросает исключение —
  два разных контракта на одно состояние.
- **Как исправить:** согласовать `__toString()` с остальным контрактом VO (например, для
  `none()` тоже сигнализировать отсутствие, а не отдавать тихий `''`). Убирать `\Stringable`
  не следует: `rules.md` («ValueObject для доменных примитивов») требует у простого скалярного
  VO наличия `Stringable` — это часть контракта таких VO. Поведение `NotificationAction`/Entity
  при правке не меняется.
- **Тесты:** при изменении `__toString()` — добавить/поправить юнит-проверку на `none()`.

### 5. `MarkAllNotificationsRead` грузит все непрочитанные в память — известное ограничение MVP без защиты

Отметка «прочитать всё» загружает все непрочитанные уведомления пользователя в память,
проходит по ним и сохраняет одним flush. План явно фиксирует отказ от батчинга как
осознанное ограничение MVP, поэтому это не нарушение. Но стоит зафиксировать риск: у
пользователя с большим накопленным инбоксом (тысячи непрочитанных) операция тянет всю
коллекцию сущностей и весь набор в один unit of work, что даст всплеск памяти/времени и
потенциально длинную транзакцию. Проявится только при нетипично большом инбоксе.

Технические детали:

- **Тип:** `performance`
- **Рекомендация:** `на усмотрение автора`
- **Где:** `app/src/Modules/Notifications/Application/Command/Notification/MarkAllNotificationsRead/MarkAllNotificationsReadHandler.php:31-40`,
  `NotificationRepository::findUnreadForRecipient`.
- **Что подтверждает проблему:** `findUnreadForRecipient` возвращает полную коллекцию без
  лимита; handler `persist`-ит каждую и делает один `run()`.
- **Как исправить:** для MVP — оставить как есть (соответствует плану), но при росте данных
  ввести батч-обработку (пакетами по N) или массовое обновление через доменный сценарий.
  Сейчас достаточно явного комментария-ограничения (он уже есть).
- **Тесты:** дополнительных не требуется для MVP.

### 6. Enum `platform`/`channel` кастится вручную в Handler, а не типизируется в Filter

Фильтры держат `platform` и `channel` как `string`, а превращение в `DevicePlatform`/
`NotificationChannel` делает Handler через `::tryFrom()` с выбросом `ValidationException` на
неизвестном значении. Это работает (невалидное значение → 422), но противоречит правилу
`rules.md`: «Enum-ы типизируются прямо в Filter-е — Spiral автоматически кастит строку в
BackedEnum. Контроллер не делает `::from()`/`::tryFrom()` вручную». Каст enum должен жить на
HTTP-границе (Filter), а не протекать в Application-слой. Это первый Filter-модуль в проекте,
поэтому прецедента нет — стоит сразу выбрать каноничную форму. Если выбран ручной каст из-за
особенностей `AttributesFilter` (Symfony-валидатор), это решение нужно зафиксировать явно.

Технические детали:

- **Тип:** `quality`
- **Рекомендация:** `на усмотрение автора`
- **Где:** `app/src/Modules/Notifications/Presentation/Http/Filter/DeviceToken/RegisterNotificationDeviceTokenFilter.php:28`
  (`public string $platform`), `RegisterNotificationDeviceTokenHandler.php:36-37`
  (`DevicePlatform::tryFrom(...)`),
  `app/src/Modules/Notifications/Presentation/Http/Filter/Setting/NotificationSettingUpdateInput.php:22`
  (`public string $channel`), `UpdateNotificationSettingsHandler` (ручной каст канала).
- **Что подтверждает проблему:** оба свойства объявлены `string`; enum-каст вынесен в Handler;
  план (фаза 7) явно требовал «platform enum» в Filter.
- **Как исправить:** типизировать `public DevicePlatform $platform` / `public NotificationChannel
  $channel` в Filter (если `AttributesFilter` кастит BackedEnum) и убрать ручной `tryFrom()` из
  Handler; либо явно задокументировать сознательное отступление с причиной.
- **Тесты:** существующие 422-тесты на неизвестный platform/channel сохранить; при переносе
  каста — убедиться, что 422 по-прежнему отдаётся валидатором.

### 7. Pure-трансформации через `array_map`/`array_values` поверх типизированной коллекции вместо collection-пайплайна

В двух местах чистое преобразование набора сущностей делается через `array_map` +
`array_values` поверх `Collection->all()`, хотя коллекции наследуют `Illuminate\Support\Collection`
и поддерживают `->map()->values()->all()`. Это нарушает `rules.md`: «Collection-пайплайны:
`->map()`, `->filter()` для чистых трансформаций; `foreach` — только при побочных эффектах».
Функционально верно, но расходится с принятым стилем работы с типизированными коллекциями.

Технические детали:

- **Тип:** `quality`
- **Рекомендация:** `на усмотрение автора`
- **Где:** `app/src/Modules/Notifications/Application/Command/Push/SendPushNotification/SendPushNotificationHandler.php:82-88`
  (`tokenValues()`), `app/src/Modules/Notifications/Presentation/Http/Controller/NotificationController.php:47-50`
  (`list()`).
- **Что подтверждает проблему:** обе функции вызывают `\array_values(\array_map(..., $collection->all()))`
  для чистого `map`, тогда как коллекция предоставляет пайплайн напрямую.
- **Как исправить:** заменить на `$collection->map(...)->values()->all()` (или эквивалент,
  возвращающий `list<...>`). `removeInvalidTokens()` оставить на `foreach` — там побочный
  эффект (`delete`).
- **Тесты:** поведение не меняется, новых сценариев не требуется.

## Рекомендации

- **Править обязательно:** 1, 2
- **На усмотрение автора:** 3, 4, 5, 6, 7

## Изменения после мета-ревью

Режим `normal` (`defaults.strict: false`). Запущены `architecture-check`, `rules-check`,
`plan-check`, `quality-check` (все на `sonnet`). Кросс-CLI не запускался.

### После architecture-check / rules-check / plan-check / quality-check

- **+ Добавлено:**
  - «Проблемы сверки с планом», пункт 2: `platform`/`channel` приходят как `string` с ручным
    `tryFrom()` в Handler — отклонение от плана («platform enum» в Filter) и нарушение
    `rules.md` (предложили plan-check и rules-check, подтверждено кодом).
  - «Замечания», пункт 6 (`quality`, на усмотрение): тот же enum-в-Filter — каст enum должен
    жить на HTTP-границе, а не в Handler.
  - «Замечания», пункт 7 (`quality`, на усмотрение): pure-трансформации через
    `array_map`/`array_values` поверх `Collection->all()` вместо `->map()->values()->all()` в
    `SendPushNotificationHandler::tokenValues()` и `NotificationController::list()` — нарушение
    правила collection-пайплайнов (quality-check).
- **~ Изменено:**
  - «Проблемы сверки с планом», пункт 1: переформулирован из «расхождение с планом» в «пробел
    плана». plan-check и architecture-check показали, что реализация буквально следует алгоритму
    плана (`findByToken → delete`), а проверку владельца сам план не закладывал. Security-риск
    остаётся в «Замечаниях», пункт 1.
  - «Замечания», пункт 4: убран вариант «убрать `\Stringable`» — он противоречит `rules.md`
    («простой скалярный VO: `readonly`, `Stringable`, ...»); оставлен только вариант согласовать
    `__toString()` с контрактом. Формулировка риска смягчена до «гипотетический» (rules-check,
    quality-check).
- **− Убрано:** ничего из существующих пунктов — все пять подтверждены кодом всеми агентами.
- **Отклонено:**
  - architecture-check `+` про размещение `NotificationSender` в корне `Application/` — `arch.md`
    не запрещает сервис в корне `Application`, а это публичный entry-point сервис модуля
    (реализация `NotificationSenderContract`); расхождение слишком слабое для пункта ревью.
  - quality-check `+` про `readAll` хардкодит `count: 0` — внутри транзакции корректно, риск
    спекулятивный; сам агент рекомендовал не добавлять обязательным.
  - quality-check замечания про инвертированный `foreach` в `removeInvalidTokens()` и инлайн
    `$definition` в `UpdateNotificationSettingsHandler::resolveType()` — агент сам предложил не
    добавлять (риск минимален, `foreach` оправдан побочным эффектом `delete`).
  - plan-check `?` про «точный HTTP-код невалидного cursor без прогона теста» — код доказывает
    путь `InvalidDomainValueException` (500), пункт 2 остаётся; запуск тестов вне роли мета-ревью.

Оценка скорректирована 82 → 81 (два дополнительных подтверждённых мелких отступления от
правил/плана; набор обязательных пунктов не изменился).

## Применённые фиксы

Отчёт: `docs/review-fixes/2026-06-13_22-51_notifications-module-uncommitted-draft.md`

Обязательные (1, 2) исправлены полностью. Из optional применён пункт 3 (rename
`findActiveForUser` → `findAllForUser`).

Отклонённые optional-решения (режим `apply-optional`, не повторять без новых аргументов):

- **4** — согласование `__toString()`: риск гипотетический (VO не попадает в строковый контекст),
  бросающий `__toString()` на `none()` — антипаттерн; польза спорная при нулевом реальном вызове.
- **5** — батчинг `MarkAllNotificationsRead`: осознанное ограничение MVP по плану, ревью само
  рекомендует «оставить как есть».
- **6** — enum прямо в Filter: ломает `openapi:generate` (`packages/spiral-openapi` не строит схему
  для enum-свойства фильтра и бросает исключение). Оставлен ручной `tryFrom()` в Handler (422
  сохранён), причина зафиксирована в PHPDoc фильтров. Повторять только после добавления
  enum-поддержки в генератор OpenAPI.
- **7** — collection-пайплайн вместо `array_map`: ломает PHPStan level max (типизированная доменная
  коллекция фиксирует generic-тип элемента, `->map()` к другому типу не сводится к `list<U>`).
  Оставлен `array_values(array_map(...))` с комментарием. Повторять только после решения по
  generic-`map` доменных коллекций.
