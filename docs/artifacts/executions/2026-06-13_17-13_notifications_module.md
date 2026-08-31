---
plan: docs/plans/2026-06-13_13-55_notifications_module.md
started: 2026-06-13 17:13
finished: 2026-06-13 17:13
status: done
---

# Журнал: Модуль уведомлений (Notifications)

## Шаги
| # | Шаг | Файлы | Тесты | Статус |
|---|-----|-------|-------|--------|
| Ф1 | Unit-тесты домена (VO, Entity, Enum) | tests/Unit/Modules/Notifications/Domain/{ValueObject,Entity,Enum}/*Test.php | make test-unit 262 ok, make phpstan ok | done |
| Ф2 | Миграция 3 таблиц + Cycle-аннотации + 4 typecast + 3 репозитория | app/database/migrations/20260613.130000_0_create_notification_domain_tables.php; Domain/Entity/*; Infrastructure/Cycle/*Typecast.php; Repository/*Repository.php; tests Unit typecast + Feature repo | make test-feature 173 ok, make test-unit 275 ok, make phpstan ok | done |
| Ф3 | Контракты + DTO + реестр + NotificationSender + бутлоадер + фикстура | Application/Contract/*, Application/Dto/*, Application/Message/NotificationRequested.php, Application/Exception/*, Application/NotificationSender.php, Infrastructure/Registry/NotificationTypeRegistry.php, Infrastructure/Bootloader/NotificationsBootloader.php, Kernel.php; tests Unit (registry/sender/serializer) + tests/Support/Notifications/FixtureNotificationTypeDefinition.php | make test-unit 283 ok, make test-kernel 42 ok, make phpstan ok | done |
| Ф4 | Push/Realtime messages + DispatchNotification Command/Handler + Job + регистрация пары | Application/Message/Notification{Push,Realtime}Requested.php, Application/Command/Notification/DispatchNotification/*, Presentation/Job/DispatchNotificationJob.php, NotificationsBootloader.boot(), app/config/queue.php; tests Feature (handler 7 + job 3) + tests/Support/Notifications/RecordingOutboxEventStore.php | make test-feature 183 ok, make phpstan ok | done |
| Ф5 | CQRS Commands+Queries + view-фабрика + locale | Application/Command/{Notification,Setting,DeviceToken}/*, Application/Query/{Notification,Setting}/*, Application/Service/NotificationSettingsViewFactory.php, Application/Dto/NotificationSettingView*.php, app/locale/{ru,en}/notifications.php; tests Feature NotificationUseCaseTest (13) | make test-feature 196 ok, make phpstan ok | done |
| Ф6 | FCM-пакет + конфиги + Centrifugo + FCM + доставочные Command/Job + регистрация | composer kreait/firebase-php:^8.2; app/config/{centrifugo,push}.php; Shared/Infrastructure/Configuration/{Centrifugo,Push}/*; Application/Contract/{Centrifugo,Fcm}*; Application/Dto/{RealtimeNotificationPayload,NotificationPush,FcmPushResult}.php; Infrastructure/{Centrifugo,Push,Exception}/*; Application/Command/{Push,Realtime}/*; Presentation/Job/{SendPush,PublishRealtime}*.php; NotificationsBootloader; queue.php; .env(.sample); tests Unit+Kernel+Feature; ConfigShapeTest обновлён | make test-unit 291, test-kernel 46, test-feature 203, phpstan ok | done |
| Ф7 | 3 контроллера/8 роутов + Filters + Resources + OpenAPI multi-source + интеграционные тесты | Presentation/Http/{Controller,Filter,Resource}/*; app/config/openapi.php + Shared/.../OpenApi/OpenApiConfig.php (sourcePaths list); packages/spiral-api-errors ApiValidationErrorsRenderer (фикс формы Symfony-ошибок); tests Feature NotificationHttpTest + покрытие-добивки (bootloader/centrifugo-service/push-handler/settings/action-resource); public/openapi/openapi.yml | make qa: cs ok, phpstan ok, coverage 100%, 553 теста; openapi:generate 8 роутов | done |

## Финальная проверка

`make qa` (полный гейт: php-cs-fixer + PHPStan + один coverage-run на Unit+Kernel+Feature, PCOV):
- **php-cs-fixer** — чисто (0 правок).
- **PHPStan** (level max, app/src) — **No errors**.
- **Покрытие** — **100.00%** (порог 100%), 553 теста зелёных (1 пред-существующий PHPUnit Notice, не падение).
- **`php app.php openapi:generate`** — без ошибок, 8 роутов Notifications + health в `public/openapi/openapi.yml` (9 операций, 8 schemas).
- Пакет `packages/spiral-api-errors` после правки рендерера: его изолированные `phpstan` ok + `test` 24 зелёных.

Состояние git: **не закоммичено**.

## Проверки

- Доустановлены зависимости (`make composer-install`): локальные path-пакеты `packages/*` не были
  слинкованы в `vendor/gian-tiaga` (свежий worktree). Без этого не запускался PHPStan.
- `make phpstan` — **зелёный** (рефактор `OutboxEventDate` + все новые VO/сущности Notifications).
- Первый прогон `make test` — 421/424; 3 падения — пред-существующие locale-тесты
  (`LocaleConfigTest`, `SimpleConfigBindingTest`), завязанные на `env(LOCALE)` (в `.env` стоит
  `LOCALE=en`, тесты жёстко ждали `'ru'`).
- По указанию пользователя сделал эти тесты независимыми от env: ассерты заменены на инвариант
  (`default ∈ supported`) и сверку единого источника (`translator.locale === locale.default`,
  нативный translator-конфиг === typed). Конкретное значение локали больше не зашито.
- Повторный `make test` — **424/424 зелёных** (1 PHPUnit Notice — пред-существующий, не падение).
- `make phpstan` — **зелёный**.

## Заметки

- Место выполнения: остаёмся в ветке `work-1` (ответ пользователя).
- Docker и сервисы (postgres, minio, centrifugo, rabbitmq, redis) подняты — проверки идут через `make`.
- Решение 11 плана сознательно отходит от `rules.md:34` (property hooks) в пользу доменного метода `action()` — обосновано мета-ревью и образцами в коде (`Media::isReady()`). Конфликта нет.
- Ф3: `NotificationTypeDefinitionCollection` размещён в `Application/Dto`, а не `Domain/Collection` (как указано в Фазе 1 плана). Причина: коллекция держит `NotificationTypeDefinition` — это контракт Application-слоя, а Domain по arch.md не зависит от Application. Поведение не меняется.
- Ф3: исключения реестра объединены в один `NotificationTypeRegistryException` (фабрики `unknownType`/`duplicateType`) по образцу `OutboxMessageLoadingException`.
- Ф6: `CentrifugoClient` строит запрос конструктором Guzzle `Psr7\Request` (заголовки массивом), а не через `RequestFactoryInterface::createRequest()->withHeader()`. Причина: правило namedArguments требует имена параметров, но у конкретной реализации Nyholm `withHeader($header, ...)` имена отличаются от PSR-интерфейса (`$name`) — именованный вызов падал бы в рантайме. Guzzle уже зависимость и реализует PSR-18 клиент.
- Ф6: `composer require kreait/firebase-php:^8.2` поставился без downgrade существующих пакетов; composer сообщил о 24 security advisories в транзитивных google/* зависимостях kreait — выбор пакета зафиксирован планом (решение 3), на гейт не влияет.
- Ф6: `ConfigShapeTest` (фикстура списка конфигов) обновлён — добавлены `centrifugo` и `push`.
- Ф7: `OpenApiConfig` расширен с одного `sourcePath: string` до `sourcePaths: list<string>` (+ `apiNamespace = App\Modules`), чтобы генератор сканировал и System, и Notifications. Генератор пакета уже поддерживает список источников. Обновлён `OpenApiGenerateCommandTest` (`#[Config('openapi.sourcePaths', [...])]`).
- Ф7: починен `packages/spiral-api-errors` `ApiValidationErrorsRenderer` — Symfony-валидатор отдаёт ошибки как `array<string, list<string>>` (список сообщений на поле), а рендерер ждал `array<string, string>` и падал с 500. Это первый Filter-контроллер в проекте, поэтому путь рендеринга ошибок валидации раньше не задействовался. Рендерер теперь сводит список к строке; его изолированные phpstan+тесты зелёные.
- Ф7: `platform` в `RegisterNotificationDeviceTokenFilter` принимается строкой (валидность проверяет Handler → 422), а не enum-типом фильтра — чтобы не зависеть от способа каста enum во фрейме и иметь стабильный 422.
- Ф7: HTTP route с параметром записан как `/notifications/<id>/read` (синтаксис Spiral); генератор OpenAPI выводит путь как есть (`<id>`), это поведение пакета, не блокер.

## Изменения в docs

- `rules.md:21` («Явные типы вместо null») — добавлен явный критерий выбора формы null-object VO: одно опциональное значение → один VO (стиль `MediaExpiration`); несколько состояний с разными данными/поведением → абстракция + наследники (`Ip`→`KnownIp`/`UnknownIp`). Согласовано с пользователем.
- Следствие для плана: решение 13 (`NotificationReadState`) → один класс; решение 11 (`NotificationAction`) — на согласовании; `OutboxEventDate` помечается как будущий рефактор под новый дефолт.
