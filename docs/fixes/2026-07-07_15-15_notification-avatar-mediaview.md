---
date: 2026-07-07 15:15
source: text (PostNotifier / NotificationActor::of avatarUrl → «всегда отдавать MediaView»)
status: done
---

# Фикс: аватар автора в уведомлениях отдаётся как MediaView, а не строка-ссылка

## Контекст

Вход (рядом с вызовом): `PostNotifier`, строка `avatarUrl: $actor->avatar?->original?->url`,
«мы всегда должны отдавать MediaView вне зависимости, это уведомление или что-то ещё».

Проблема: снимок автора уведомления схлопывал аватар в одну строку-ссылку. Это:
1. теряло полный `MediaView` (оригинал + конверсии), который у `PostNotifier` уже есть в `$actor->avatar`;
2. протухало для private-медиа (presigned-ссылки временные), т.к. снимок хранится в БД и открывается позже;
3. `NotificationActor` — доменный VO, и по `docs/arch.md` Domain не может держать `MediaView`
   (`Shared/Application`) и enum-ы конверсий (`Media/Domain`).

Решение (согласовано с пользователем): в снимке хранить **id медиа-аватара** (`avatarMediaId`), а полный
`MediaView` собирать заново на границе показа через модуль Media — как это уже делает
`UserPublicProfileAssembler`. Так ссылка всегда валидна и работает и для public-, и для private-медиа.
Учтены `docs/rules.md`, `docs/arch.md`, `docs/code-examples.md`.

Охват каналов: HTTP-инбокс и realtime (Centrifugo) отдают полный `MediaView`; push (FCM) оставляет одну
ссылку — FCM `data` это плоская строковая карта, вложенный объект туда не кладётся.

## Что изменено

| # | Файл | Что изменено | Зачем |
|---|------|--------------|-------|
| 1 | `Notifications/Domain/ValueObject/NotificationActor.php` | `avatarUrl` → `avatarMediaId` (валидация UUID v7, `presentAvatarMediaId()`), JSON `{id,name,avatarMediaId}` | Домен хранит ссылку на медиа, а не отрисованный URL |
| 2 | `Notifications/Infrastructure/Cycle/NotificationActorTypecast.php` | JSON-ключ `avatarMediaId` | Гидрация нового формата снимка |
| 3 | `Notifications/Application/Dto/NotificationActorPayload.php` | `avatarUrl` → `avatarMediaId` | Пайплайн несёт id медиа |
| 4 | `NotificationRequested`, `DispatchNotificationCommand`, `NotificationSender`, `DispatchNotificationHandler` | Проброс `avatarMediaId` | Согласование пайплайна |
| 5 | `Posts/Application/Notification/PostNotifier.php` | `avatarMediaId: $actor->avatar?->id` | Исходная точка входа фикса |
| 6 | `Media/Repository/MediaRepository.php` | `findByIdsWithConversions(MediaId ...)` | Пакетная загрузка медиа с конверсиями |
| 7 | `Media/Application/Query/FindMediaUrls/*` + `Application/Dto/MediaUrlsResultCollection.php` (новое) | Пакетный резолв URL по списку id | Список инбокса резолвит аватары одним запросом (без N+1) |
| 8 | `Notifications/Application/View/*` (5 новых: View/ActorView/ActionView/Collection/Assembler) | Read-model инбокса с аватаром-MediaView | Обогащение при чтении по паттерну Assembler |
| 9 | `ListNotifications{Result,Handler}`, `MarkNotificationReadHandler` | Возвращают `NotificationView` через ассемблер | Аватар резолвится на чтении |
| 10 | `Notifications/Presentation/Http/Resource/{Notification,NotificationActor,NotificationAction}Resource.php` + `NotificationController.php` | `fromView(...)`, actor несёт `MediaResource|null $avatar` | HTTP-ответ отдаёт MediaView |
| 11 | `RealtimeNotificationPayload` + `PublishRealtimeNotificationHandler` + `Realtime{Actor,Media,MediaOriginal,MediaConversion}Payload` (4 новых) | Realtime резолвит аватар в MediaView (даты ATOM) | Живое уведомление отдаёт ту же форму MediaView |
| 12 | `NotificationPush` + `NotificationPushActorPayload` (новое) + `SendPushNotificationHandler` | Push резолвит id медиа → одна ссылка (original url) | FCM data плоская — MediaView не кладётся |
| 13 | `Notifications/README.md`, `docs/arch.md` | Документация под новую модель, `FindMediaUrls` в списке сценариев Media | Точность документации |

Исходники правились только в рамках задачи; логика вне неё не менялась.

## Тесты и проверки

Тесты приведены к новому API и добавлено покрытие на новый код (ассемблер, пакетный резолв, realtime-
payload, push-резолв). Ключевые: `FindMediaUrlsHandlerTest` и `NotificationViewAssemblerTest` (новые),
`PublishRealtimeNotificationHandlerTest` перенесён из Unit в Feature (теперь резолвит через Media),
общий media-хелпер вынесен в `tests/Support/Media/`. Обновлены тесты VO/typecast/entity/sender/
serializer/http/dispatch/push/centrifugo/content-builder.

| Команда | Результат | Заметки |
|---------|-----------|---------|
| `make phpstan` | ✓ | No errors (level max) |
| `make test` | ✓ | OK, 1302 tests, 4297 assertions |
| `make test-coverage` | ✓ | Покрытие 100.00% при пороге 100.00% |

## Открытые вопросы

Нет. Замечание на будущее (не в рамках задачи): логика «id медиа → MediaView, `null` если медиа
недоступно/оригинал удалён» теперь повторяется в `UserPublicProfileAssembler`, `NotificationViewAssembler`,
`PublishRealtimeNotificationHandler` и (в виде url) в `SendPushNotificationHandler` — кандидат на вынос в
общий Media-Application резолвер аватара, если появится четвёртый потребитель.
