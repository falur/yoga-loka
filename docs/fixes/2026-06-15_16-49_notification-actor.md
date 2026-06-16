---
date: 2026-06-15 16:49
source: text — «в уведомлениях не хватает, от кого пришло (например, Вася подписался на вас), чтобы фронт показал аватарку автора»
status: done
---

# Фикс: «от кого» в уведомлениях (автор уведомления)

## Контекст
Запрос: в уведомлениях не хватает информации об авторе-инициаторе (например, «Пользователь Вася
подписался на вас»), чтобы клиент мог показать аватар автора.

Учтены `docs/rules.md`, `docs/arch.md`, `docs/code-examples.md` и `README.md` модуля Notifications.

**Продуктовое решение:** храним только `userId` автора (`actorId`), а не снимок имени/аватара. Это
соответствует архитектуре модуля: ядро уже хранит получателя как голый `UserId` и сознательно не
знает деталей профиля; у медиа в проекте есть срок жизни, поэтому снимок URL аватара устарел бы.
Имя и аватар автора фронт берёт из профиля пользователя по `actorId` сам.

Реализация повторяет существующий паттерн опционального `action`: null-object VO + отдельный
nullable-typecast (`rules.md:21` — «явные типы вместо `null`»). Автор проброшен сквозь все три
канала доставки (inbox, realtime, push) и наружу в API.

## Что изменено

| # | Файл | Что изменено | Зачем |
|---|------|--------------|-------|
| 1 | `Domain/ValueObject/NotificationActor.php` | Новый null-object VO с приватным `UserId|null`: `none()`/`of()`, `isPresent()`, `value()`, `equals()` | Явный тип автора вместо `?UserId` |
| 2 | `Infrastructure/Cycle/NotificationActorTypecast.php` | Новый typecast nullable `uuid` ↔ `NotificationActor` | Гидрация `actor_id`: NULL→none, uuid→of(UserId) |
| 3 | `database/migrations/...create_notification_domain_tables.php` | Добавлена nullable-колонка `actor_id` (uuid) в `notifications` | Хранение автора (миграция ещё не релизилась) |
| 4 | `Domain/Entity/Notification.php` | Свойство `actor` (колонка `actor_id`) + параметр `create()` | Хранение автора в inbox |
| 5 | `Application/Dto/NotificationContent.php` | Параметр `NotificationActor $actor` | Источник передаёт автора в `send()` |
| 6 | `Application/NotificationSender.php` | `actorId: $content->actor->value()` в `NotificationRequested` | Проброс автора в outbox |
| 7 | `Application/Message/NotificationRequested.php`, `NotificationPushRequested.php`, `NotificationRealtimeRequested.php` | Поле `string|null $actorId` | Примитив автора в payload |
| 8 | `Application/Command/.../DispatchNotificationCommand.php`, `PublishRealtimeNotificationCommand.php`, `SendPushNotificationCommand.php` | Поле `string|null $actorId` | Примитив на границе команд |
| 9 | `Application/Command/.../DispatchNotificationHandler.php` | Строит `NotificationActor` из `actorId` для `create()`, пробрасывает `actorId` в push/realtime | Рассылка автора по каналам |
| 10 | `Presentation/Job/DispatchNotificationJob.php`, `SendPushNotificationJob.php`, `PublishRealtimeNotificationJob.php` | Проброс `actorId` из сообщения в команду | Сквозной проброс через очередь |
| 11 | `Application/Dto/NotificationPush.php` + `Infrastructure/Push/KreaitFcmPushSender.php` | `actorId` в DTO и в FCM `data` | Автор в данных push |
| 12 | `Application/Dto/RealtimeNotificationPayload.php` + `PublishRealtimeNotificationHandler.php` | `actorId` в payload и его JSON | Автор в realtime-сообщении |
| 13 | `Presentation/Http/Resource/NotificationResource.php` | Поле `actorId` (`string|null`) из `actor->value()` | Автор в ответе API |
| 14 | `README.md` модуля | Документация: пример `send()`, JSON-ресурс, payload push/realtime | Контракт модуля задокументирован |
| 15 | `public/openapi/openapi.yml` | Регенерация (`openapi:generate`): `actorId` в схеме `NotificationResource` | Спецификация в синхроне |
| 16 | Тесты (11 файлов) | Обновлены конструкторы изменённых типов + добавлено покрытие автора (VO, typecast, entity, sender, serializer, dispatch, realtime, push, HTTP) | 100% coverage, проверка нового поведения |

## Тесты и проверки

| Команда | Результат | Заметки |
|---------|-----------|---------|
| `php app.php openapi:generate` | ✓ | `actorId` добавлен в `NotificationResource` |
| `make qa` (стиль + PHPStan + coverage) | ✓ | PHPStan: No errors; покрытие 100.00%; 589 тестов, 1729 ассертов |
| `vendor/bin/phpunit --testsuite Unit --filter Notification --display-notices` | ✓ | 94 теста, 0 notices — единственный notice в полном прогоне предсуществующий и не связан с задачей |

## Открытые вопросы
Нет. Если позже понадобится показывать аватар автора без отдельного запроса к профилю
(денормализованный снимок имени/URL прямо в уведомлении) — это отдельная задача со своими
компромиссами (устаревание снимка, связность с модулем User).
