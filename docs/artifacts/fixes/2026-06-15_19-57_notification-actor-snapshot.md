---
date: 2026-06-15 19:57
source: text — «показывать аватар без отдельного запроса к профилю - это про уведомления»
status: done
---

# Фикс: снимок автора в уведомлениях (аватар без запроса к профилю)

## Контекст
Запрос: клиент должен показывать аватар автора уведомления **без отдельного запроса к профилю**.

Учтены `AGENTS.md`, `docs/rules.md`, `docs/arch.md`, `docs/code-examples.md` и `README.md` модуля
Notifications.

**Смена продуктового решения.** Предыдущий фикс
(`2026-06-15_16-49_notification-actor.md`) сознательно хранил только `userId` автора (`actorId`),
а имя и аватар клиент должен был брать из профиля сам. Текущая задача прямо требует обратного —
показать аватар без обращения к профилю, — поэтому решение меняется на **снимок автора**
`actor {id, name, avatarUrl}`, который ядро хранит целиком и отдаёт в inbox/push/realtime.

**Почему именно снимок, а не обогащение на чтении.** Модуля `User`/профиля и понятия «аватар» в
проекте сейчас нет вообще (модули: `Media`, `Notifications`, `Outbox`, `System`). Обогащать данные
автора «на чтении» нечем, поэтому единственный реализуемый путь — снимок в момент отправки: источник
передаёт `name`/`avatarUrl` так же, как уже передаёт готовый (переведённый) текст уведомления.

**Компромисс (озвучен явно).** Снимок берётся на момент отправки: если автор позже сменит аватар или
имя, в старом уведомлении останется прежний снимок. Это принято осознанно ради требования «без
запроса к профилю»; ровно этот риск устаревания упоминал и предыдущий фикс как причину НЕ делать
снимок — теперь приоритет у требования задачи.

**Подход.** Повторяет существующий паттерн опционального `action`: доменный составной VO на краях +
примитивный payload-DTO в командах/сообщениях outbox. Снимок хранится в одной nullable json-колонке
`actor` (образец — `MediaMultipartUpload.parts`), а не в трёх колонках, потому что это цельный объект
и по нему ничего не фильтруется. Имя и URL валидируются внутри `NotificationActor`
(длина, непустота). Отдельных суб-VO не заводил: автоматического PHPStan-правила против примитивов в
VO нет, а валидация и так инкапсулирована в VO.

## Что изменено

| # | Файл | Что изменено | Зачем |
|---|------|--------------|-------|
| 1 | `Domain/ValueObject/NotificationActor.php` | Составной null-object VO `{UserId|null, name, avatarUrl}`: `none()`/`of(userId,name,avatarUrl)`, `isPresent()`, `presentId/Name/AvatarUrl()`, `equals()`, `jsonSerialize()`; валидация длины/непустоты | Снимок автора как явный тип вместо набора `?T` |
| 2 | `Application/Dto/NotificationActorPayload.php` | Новый примитивный DTO `{id, name, avatarUrl}` | Снимок в payload команд и outbox-сообщений (восстановление Valinor без фабрик) |
| 3 | `Infrastructure/Cycle/NotificationActorTypecast.php` | Переписан на json-колонку: NULL↔none, `{id,name,avatarUrl}`↔of() | Хранение цельного снимка в одной колонке |
| 4 | `Domain/Entity/Notification.php` | Колонка `actor_id` (uuid) → `actor` (json) | Хранение снимка |
| 5 | `database/migrations/...create_notification_domain_tables.php` | `actor_id` (uuid) → `actor` (json) | Миграция ещё не релизилась — правка на месте |
| 6 | `Application/Dto/NotificationContent.php` | Без изменений сигнатуры (поле `actor` теперь несёт снимок) | — |
| 7 | `Application/NotificationSender.php` | Строит `NotificationActorPayload` из VO | Снимок едет в `NotificationRequested` |
| 8 | `Application/Message/Notification{,Push,Realtime}Requested.php` | `actorId: ?string` → `actor: ?NotificationActorPayload` | Снимок во всех трёх каналах |
| 9 | `Application/Command/.../DispatchNotificationCommand.php`, `SendPushNotificationCommand.php`, `PublishRealtimeNotificationCommand.php` | `actorId` → `actor` | Проброс снимка |
| 10 | `Application/Command/.../DispatchNotificationHandler.php` | Строит `NotificationActor` из payload, стейджит `actor` в push/realtime | Снимок в inbox и доставку |
| 11 | `Presentation/Job/{Dispatch,SendPush,PublishRealtime}NotificationJob.php` | Маппинг `actor` сообщения → команда | Проброс снимка |
| 12 | `Application/Dto/NotificationPush.php`, `SendPushNotificationHandler.php` | `actorId` → `actor` | Снимок в push |
| 13 | `Infrastructure/Push/KreaitFcmPushSender.php` | FCM `data`: `actorId`/`actorName`/`actorAvatarUrl` (плоская карта) | Снимок в push-данные |
| 14 | `Application/Dto/RealtimeNotificationPayload.php`, `PublishRealtimeNotificationHandler.php` | `actorId` → вложенный `actor` объект | Снимок в realtime payload |
| 15 | `Presentation/Http/Resource/NotificationActorResource.php` | Новый вложенный ресурс `{id, name, avatarUrl}` | Снимок в API-ответе |
| 16 | `Presentation/Http/Resource/NotificationResource.php` | `actorId: ?string` → вложенный `actor: ?NotificationActorResource` | Аватар без запроса к профилю |
| 17 | `README.md` модуля | Описание actor-снимка, пример отправки, JSON ответа, заметки push/realtime | Документация контракта |
| 18 | `public/openapi/openapi.yml` | Перегенерирован (`openapi:generate`): схема `NotificationActorResource`, `actor` в `NotificationResource` | Публичный контракт = код |
| 19 | Тесты Notifications + Centrifugo (VO, typecast, sender, serializer, entity, dispatch, http, push, realtime, jobs) | Обновлены под снимок, добавлены ветки валидации/typecast | 100% покрытие |

## Тесты и проверки

| Команда | Результат | Заметки |
|---------|-----------|---------|
| `php app.php openapi:generate` (Docker) | ✓ | 9 операций, 9 schemas; `actor`-снимок в спеке |
| `make qa` (стиль + PHPStan + coverage, Docker) | ✓ | PHPStan: No errors; 599 тестов, 1773 ассерта; покрытие 100.00% |
| `phpunit tests/.../Notifications --display-notices` (Docker) | ✓ | 171 тест, без нотисов |

## Открытые вопросы
В полном прогоне `make qa` есть 1 «PHPUnit Notice» (жёлтый `OK, but there were issues!`, exit 0). В
тестах модуля Notifications нотисов нет — он пре-существующий и не относится к этой правке, поэтому не
трогал (правило «чужие поломки фиксировать, но не чинить без необходимости»).
