---
review: `docs/artifacts/reviews/2026-09-17_11-25_arch-modular-migration-branch.md`
date: 2026-09-17 14:07
status: done
---

# Фиксы по ревью: переезд на целевую архитектуру (ветка arch-modular-migration)

## Решение

Находка №1 (HIGH) допускала два легитимных пути: синхронный запрет удаления или интеграционное
событие. Пользователь выбрал путь (б) — интеграционное событие — и явно отклонил путь (а): проверка
использования медиа из `DeleteMediaHandler` потребовала бы обращения Media к `Posts/Public`
(«кто ссылается на это медиа»), а `docs/arch.md` не допускает межмодульный цикл зависимостей
(Media уже используется Posts через `Media/Public`, обратное направление создало бы цикл).

## Применённые правки

| # | Замечание | Файлы | Тесты | Статус |
|---|-----------|-------|-------|--------|
| 1 | `post_media.media_id -> media.id`: FK снят волной C, замены согласованности нет | см. «Что изменено» ниже | 11 новых тестов (см. ниже) | ✓ применено |

## Что изменено

### Media публикует интеграционное событие при фактическом удалении

Единственное место фактического удаления строки `media` — `MediaRepository::delete()`,
вызываемое из `DeleteMediaHandler::handle()` (`app/src/Modules/Media/Application/Command/DeleteMedia/DeleteMediaHandler.php`).
Других мест не нашлось: `findExpired()` объявлен в `MediaRepository`, но не имеет ни одного
вызывающего сценария (зарезервирован на будущее, сегодня не используется), поэтому событие
достаточно публиковать из одного места.

- `app/src/Modules/Media/Public/Event/MediaDeletedEvent.php` (новый) — интеграционное событие,
  одно поле `mediaId: string`, форма повторяет `MediaUploadedEvent`.
- `app/src/Modules/Media/Application/Command/DeleteMedia/DeleteMediaHandler.php` — добавлен
  `#[Transactional]` (транзакция теперь охватывает удаление строки медиа и запись события в outbox
  — откат уносит оба эффекта), внедрён `IntegrationEventStoreContract`, после
  `$mediaRepository->delete($media)` вызывается `$integrationEventStore->add(new MediaDeletedEvent(...))`.

### Posts подписан идемпотентным потребителем

Порт массовой записи (по образцу единственного прежде такого порта проекта,
`MarkAllNotificationsReadContract`/`CycleMarkAllNotificationsRead`): удаление `post_media` по
`media_id` не проходит через доменный переход `Post` (агрегат не хранит вложения в памяти) и набор
затронутых строк не ограничен доменом сверху.

- `app/src/Modules/Posts/Application/Contract/DetachMediaAttachmentsContract.php` (новый) —
  `detachByMediaId(PostMediaReference $mediaId): int`.
- `app/src/Modules/Posts/Infrastructure/Persistence/Cycle/CycleDetachMediaAttachments.php` (новый) —
  реализация, один `DELETE ... WHERE media_id = ?`, маркер `SetBasedWrite`.
- `app/src/Modules/Posts/Application/Command/DetachDeletedMedia/{DetachDeletedMediaCommand,DetachDeletedMediaHandler}.php`
  (новые) — сценарий-потребитель, `#[Transactional]`. Идемпотентен естественно (DELETE по mediaId
  находит уже пустой набор строк при повторе) — отдельного журнала доставок не заведено, причина
  описана в докблоке `DetachDeletedMediaHandler` (разрешено карточкой `docs/references/job-consumer.md`,
  раздел «Допустимые варианты»).
- `app/src/Modules/Posts/Infrastructure/Spiral/Job/DetachDeletedMediaJob.php` (новый) — входной
  адаптер очереди, форма повторяет `OutboxDebugLogJob`/`ProcessMediaJob`: грузит `MediaDeletedEvent`
  через `IntegrationEventLoaderContract`, диспатчит `DetachDeletedMediaCommand`, бизнес-правил и
  `try-catch` не содержит.
- `app/src/Modules/Posts/Infrastructure/Spiral/Bootloader/PostsBootloader.php` — добавлен биндинг
  `DetachMediaAttachmentsContract -> CycleDetachMediaAttachments` и в `boot()` регистрация маршрута
  `MediaDeletedEvent::class -> DetachDeletedMediaJob::class` через `IntegrationEventRoutingContract`.
  Регистрация — в bootloader'е Posts (потребителя), а не Media (издателя): deptrac запрещает
  `Infrastructure/Spiral` одного модуля зависеть от `Infrastructure/Spiral` (в т.ч. Job) другого,
  тогда как `Public/Event` соседа открыт всем слоям. Во всех прежних маршрутах события и Job лежали
  в одном модуле, поэтому этот нюанс раньше не проявлялся; карточки `docs/references/integration-event.md`
  и `docs/references/job-consumer.md` уточнены этим же изменением (см. ниже).

### Другие модули с идентификаторами медиа

Кроме `Posts.post_media.media_id`, идентификатор медиа хранит только `User.users.avatar_media_id`
(проверено grep по `Infrastructure/Persistence/Cycle/Columns` и `Domain/Entity` всех модулей, кроме
самого Media). Второй потребитель не заведён: `GetUserPublicProfileHandler::resolveAvatars()` уже
дочитывает аватар через `MediaContract->urlsByIds()`, которая best-effort пропускает недоступный
`mediaId` — отсутствующее медиа не бросает исключение и не оставляет мусора в `User`, деградация до
«нет аватара» безопасна и корректна без какого-либо потребителя. Это же зафиксировано в докблоке
миграции `20260916.090000_0_drop_users_avatar_media_foreign_key.php` и подтверждено разделом
«Отклонено» исходного ревью.

### docs/arch.md

Раздел «Владение данными» уже формулировал общее правило корректно («…либо интеграционное событие
с идемпотентным потребителем») — правка не текста документа, а кода, реализующего это правило для
связи `post_media -> media`. Новый пункт в «Осознанные отступления» не добавлен: гарантия теперь
реализована, а не осознанно оставлена незакрытой.

Уточнены (не переписаны) `docs/references/integration-event.md` и `docs/references/job-consumer.md`:
единственная фраза про то, что маршрут «событие -> Job» регистрируется «в конфигурации
модуля-издателя», была верна только пока публикующий и потребляющий модуль совпадали (все примеры
в карточках — самоподписка). Для межмодульного случая (Media издаёт, Posts потребляет) это
архитектурно невозможно под deptrac, поэтому карточки уточнены: регистрация — в bootloader'е
модуля-потребителя, когда событие и Job лежат в разных модулях.

Также уточнён докблок `MarkAllNotificationsReadContract` — он называл себя «портом единственной
массовой записи проекта»; после добавления `DetachMediaAttachmentsContract` это перестало быть
точным, докблок поправлен одной фразой.

## Тест `testDeletingReferencedMediaKeepsAttachmentRow`

Переименован в `testDeletingReferencedMediaLeavesAttachmentRowUntilConsumerDetachesIt`
(`app/src/Modules/Posts/Tests/Integration/Cycle/PostsRepositoryTest.php`). Исходная проверка не
удалена: шаг «медиа исчезает из базы в обход прикладного сценария (`$this->delete($media)`) — строка
`post_media` не пропадает сама по себе» сохранён как есть (у `post_media` по-прежнему нет FK и
ограничения базы). Добавлен второй шаг: вызов того же порта (`DetachMediaAttachmentsContract`),
которым пользуется асинхронный потребитель, — после него строка вложения снята. Тест теперь
фиксирует полную гарантию: без потребителя вложение осиротело бы, а с потребителем перестаёт.

## Новые тесты (11)

Позитив/негатив/граница по `docs/rules.md`, идемпотентность повторной доставки — по `docs/arch.md`
(«Взаимодействие модулей», at-least-once):

- `app/src/Modules/Media/Tests/Integration/Spiral/DeleteMediaHandlerTest.php` — 1 новый:
  `testPublishesMediaDeletedEventOnSuccessfulDeletion` (позитив: событие с верным `mediaId`
  публикуется). Существующие `testRejectsMissingMedia`/`testRejectsForeignOwner` дополнены
  проверкой `outboxStore->expects(self::never())->method('add')` (негатив: событие не публикуется
  при отказе).
- `app/src/Modules/Posts/Tests/Integration/Cycle/CycleDetachMediaAttachmentsTest.php` (новый файл,
  4 теста): позитив (`testDetachesMatchingAttachment`), негатив
  (`testDetachingMediaWithoutAttachmentsIsNoOp`), идемпотентность прямого вызова порта
  (`testDetachingIsSafeToRepeat`), граница — одно медиа вложено в несколько записей одновременно,
  снимаются все и только они (`testDetachesSameMediaAcrossMultiplePostsWithoutTouchingOtherAttachments`).
- `app/src/Modules/Posts/Tests/Integration/Spiral/DetachDeletedMediaHandlerTest.php` (новый файл,
  3 теста): позитив, негатив (нет вложений на этот `mediaId` — чужая строка не тронута), граница —
  повторный вызов `handle()` с той же командой не ломает состояние
  (`testRepeatedDeliveryOfSameEventStaysIdempotent`).
- `app/src/Modules/Posts/Tests/Integration/Spiral/DetachDeletedMediaJobTest.php` (новый файл,
  3 теста) — полный асинхронный путь через настоящий `IntegrationEventStoreContract`/
  `IntegrationEventLoaderContract` (без стабов, по образцу `DispatchNotificationJobTest`): позитив
  (`testDetachesAttachmentThroughFullOutboxPipeline`), повторная доставка того же outbox-события
  двумя отдельными вызовами `invoke()` не ломает состояние
  (`testRedeliveryOfSameEventStaysIdempotent`), негатив — событие про медиа без вложений не бросает
  исключение и не трогает чужую строку.

## Отклонённые находки

Нет — находка №1 принята полностью и исправлена без отклонений.

## Финальная проверка

- **Тесты + PHPStan + deptrac + покрытие:** `make qa` в Docker — ✓ зелёный.
  1570 тестов, 5349 assertions, 0 failures/errors (было 1559 до правки, +11 новых). PHPStan level
  max — без ошибок. Deptrac — Violations 0, Skipped violations 0, Errors 0. Покрытие —
  100.00% (порог 100.00%, `assert-coverage.php` на clover того же прогона).
- **Отдельно:** `make phpstan` и `make deptrac` также прогнаны точечно до полного `make qa` — оба
  зелёные.
- **Осиротевшие контейнеры:** `docker ps -a --filter name=test-runner-run-` — пусто, чистить нечего.
- **Заметки:** ничего не осталось не зелёным.
