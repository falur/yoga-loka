---
plan: docs/artifacts/plans/2026-09-23_16-04_pereezd-outbox-na-paket.md
started: 2026-09-24 09:45
status: done
mode: subagents
current_phase: 3
finished: 2026-09-24 22:40
---

# Журнал выполнения: Переезд обмена событиями с модуля Outbox на пакет gian-tiaga/spiral-outbox

## Фазы
| Фаза | Результат | Проверка | Статус |
|---|---|---|---|
| 1. Объявить целевую форму в документах | `docs/arch.md` и карточки `integration-event`, `job-consumer` описывают обмен через пакет | Нет модуля `Outbox` в составе модулей и границ arch.md; обе карточки показывают идентификатор доставки и маршрут в bootloader | done |
| 2. Развести очереди по назначению | Три pipeline `mail`, `media`, `notifications` в конфиге и RoadRunner, обмен через модуль работает | `make up`, `make test`, `make test-feature`; три очереди с потребителями в RabbitMQ | done |
| 3. Перевести код на пакет и удалить модуль | Обмен на пакете, каталога модуля нет, границы и статанализ зелёные | `make qa` с покрытием 100%; grep без `Modules\Outbox`; `outbox:relay` и `outbox:status`; сквозной сценарий кода входа | done |

## Запуски
| Время | Исполнитель | Фаза | Результат | Файлы | Проверки |
|---|---|---|---|---|---|
| 2026-09-24 09:52 | субагент фазы 1 | 1 | completed | docs/arch.md, docs/references/integration-event.md, docs/references/job-consumer.md | критерии фазы: состав модулей без Outbox — passed; карточки с идентификатором доставки и маршрутом в bootloader — passed |
| 2026-09-24 09:57 | субагент-проверяющий фазы 1 | 1 | passed | нет (read-only) | arch.md:92 восемь модулей; arch.md:95,348 границы отданы пакету; arch.md:212 контракты в нейтральном слое, runtime только в Infrastructure/Spiral; «Осознанные отступления» без добавлений; обе карточки с `$outboxDeliveryId` и маршрутом в bootloader; scope — только три файла документации, коммитов нет |

| 2026-09-24 10:35 | субагент фазы 2 | 2 | completed | app/src/Shared/Infrastructure/Spiral/Queue/QueueName.php, app/config/queue.php, docker/rr/http-jobs.yaml, .env.sample, .env, tests/Kernel/.../ComplexConfigBindingTest.php, tests/Unit/.../RoadRunnerRabbitMqConfigTest.php, app/src/Modules/Outbox/Tests/Integration/Spiral/OutboxRelayPublishTest.php, docker/README.md, app/src/Modules/Outbox/README.md | `make up` — три pipeline стартовали; `rabbitmqctl list_queues` — три очереди по одному потребителю; `make test` OK 1512; `make test-feature` OK 219; `make qa` зелёный, покрытие 100% |
| 2026-09-24 11:05 | субагент-проверяющий фазы 2 | 2 | passed | нет (read-only) | `make up` exit 0, три amqp-pipeline стартовали; `make test` OK 1512/5194; `make test-feature` OK 219/794; `make qa` зелёный, покрытие 100.00%; три очереди с consumers=1; сквозной обмен через модуль в dev прошёл; конфиги queue.php и http-jobs.yaml согласованы, `requeue_on_fail` = false; модуль Outbox и composer не тронуты |
| 2026-09-24 13:20 | субагент фазы 3 | 3 | completed | composer.json/lock, phpstan.neon, phpunit.xml, deptrac.yaml, .env, .env.sample, app/config/queue.php, Kernel.php, Shared/.../OutboxRelayBootloader.php, QueueInterceptorsConfig.php, удалён app/src/Modules/Outbox (103 файла), 6 событий и 5 handler и 6 Job модулей Auth/Media/Notifications/Posts, их bootloader, общие тестовые помощники, тесты модулей, docs/arch.md, docs/references/console-command.md, docker/README.md | `make qa` зелёный: cs, PHPStan No errors, deptrac 0, 1423 теста, покрытие 100.00%; grep `Modules\Outbox` пусто; `outbox:relay` и `outbox:status` отработали на пустых таблицах; сквозной сценарий кода входа дошёл до `completed` |
| 2026-09-24 15:10 | субагент-проверяющий фазы 3 | 3 | failed | нет (read-only) | passed: `make qa` (cs 0, PHPStan No errors, deptrac 0, 1423 теста, покрытие 100.00%), `make test-unit` OK 524, grep пусто, `outbox:relay` и `outbox:status`, сквозной сценарий до `completed`, шесть маршрутов совпали с таблицей, проверки не ослаблены, deptrac-слои корректны, тесты и логирование по плану, arch.md сходится с кодом. failed: два README модулей и докблок `FcmPushFailedException` описывают удалённые классы |
| 2026-09-24 15:40 | субагент фазы 3 (исправление) | 3 | completed | Notifications/README.md, Media/README.md, FcmPushFailedException.php, четыре докблока Posts, MediaProcessingFlowTest, LoginCodeOutboxFlowTest, NotificationOutboxFlowTest, DispatchNotificationJobTest, DetachDeletedMediaJobTest | `make qa` зелёный (1423 теста, покрытие 100.00%); grep по удалённым классам пуст; прогон пяти flow-тестов OK 20/74 |
| 2026-09-24 16:30 | субагент-проверяющий фазы 3 (повторный) | 3 | failed | нет (read-only) | passed: `make qa` два прогона подряд с одинаковым результатом (1423 теста, покрытие 100.00%), критерии фазы, шесть маршрутов, проверки не ослаблены, отклонения A и B честные, семь мест документации закрыты. failed: корневой `README.md`, раздел «Outbox и очередь» — три ложных утверждения |
| 2026-09-24 17:05 | субагент фазы 3 (исправление 2) | 3 | completed | README.md, docker/README.md, tests/NonTransactionalDatabaseTestCase.php, MediaProcessingFlowTest.php | `make qa` зелёный (1423 теста, покрытие 100.00%); команды relay сверены с `OutboxRelayCommand` (только опции `--loop`, `--sleep`); выборки пакета — `FOR UPDATE SKIP LOCKED`; grep по `outbox:relay 100`, `outboxId`, старому имени теста пуст |
| 2026-09-24 18:00 | субагент-проверяющий фазы 3 (третий) | 3 | failed | нет (read-only) | passed: `make qa` 1423 теста и покрытие 100.00%, критерии фазы, шесть маршрутов, проверки не ослаблены, отклонения B и C честные, arch.md сходится с кодом. failed: `tests/DatabaseTestCase.php:21`, `CentrifugoClient.php:143`, `DispatchNotificationHandler.php:87` и неточная формулировка про `SKIP LOCKED` в двух README |
| 2026-09-24 18:40 | субагент фазы 3 (исправление 3) | 3 | completed | tests/DatabaseTestCase.php, CentrifugoClient.php, DispatchNotificationHandler.php, README.md, docker/README.md, Media/README.md | `make qa` зелёный (1423 теста, покрытие 100.00%); механический проход: 120 удалённых имён из `git show HEAD:` прогреплены по границе слова — вхождений нет, кроме одноимённого `OutboxBootloader` пакета; словесные формы, `registry.*`, `envelope`, статусы удалённого enum, корневые конфиги — чисто |
| 2026-09-24 19:30 | субагент-проверяющий фазы 3 (четвёртый) | 3 | failed | нет (read-only) | passed: `make qa` 1423 теста и покрытие 100.00%, критерии фазы, независимо воспроизведённый механический проход (122 имени + 5 case, остатков нет), шесть маршрутов, формулировка про `SKIP LOCKED` точна, проверки не ослаблены. failed: пять мест описывают удалённый механизм записи события «постановкой в единицу работы» |
| 2026-09-24 20:15 | субагент фазы 3 (исправление 4) | 3 | completed | RequestLoginCodeHandler.php, RecordingOutboxEventStore.php, DispatchNotificationHandler.php, RequestNotificationHandler.php, NotificationContract.php, Notifications/README.md, PostNotifier.php, docker/README.md | `make qa` зелёный (1423 теста, покрытие 100.00%); механизм сверен с vendor и `git show HEAD:`; греп по формулировкам о единице работы дал 16 строк до правок и 5 после, из них 2 верных про откат транзакции |
| 2026-09-24 21:00 | субагент-проверяющий фазы 3 (пятый) | 3 | failed | нет (read-only) | passed: `make qa` 1423 теста и покрытие 100.00%, критерии фазы, сквозной сценарий до `completed`, шесть маршрутов, проверки не ослаблены, механизм записи проверен независимо, 8 из 11 переписанных мест верны. failed: три утверждения «вне открытой транзакции запись падает», введённые исправлением 4 |
| 2026-09-24 21:30 | субагент фазы 3 (исправление 5, точечное) | 3 | completed | NotificationContract.php, Notifications/README.md | `make qa` зелёный (1423 теста, покрытие 100.00%); цепочка `send()` -> CommandBus -> `#[Transactional]` сверена по коду и vendor; grep по ложному обещанию исключения пуст; изменены ровно три предложения в двух файлах |
| 2026-09-24 21:55 | субагент-проверяющий фазы 3 (шестой, заключительный) | 3 | passed | нет (read-only) | `make qa` exit 0: cs 0/1121, PHPStan No errors, deptrac 0, 1423 теста / 4863 утверждения, покрытие 100.00%; grep пусто; `outbox:relay` и `outbox:status` отработали; сквозной сценарий довёл доставку до `completed`; три новые формулировки сверены по цепочке до `OutboxEventStore::add()`; круг не вышел за два файла; проверки не ослаблены; коммитов нет |
| 2026-09-24 22:40 | субагент финальной проверки | все | passed | нет (read-only) | полный набор проверок проекта зелёный; критерии готовности плана выполнены; сквозной обмен и постоянный `outbox:relay --loop` проверены вживую |
## Решения и блокеры
- 2026-09-24 21:30 — решение пользователя: точечный круг только по трём фразам. Выполнен без отклонений. Остаточный риск, зафиксированный исполнителем: защиты от вызова `NotificationContract::send()` вне транзакции источника в коде нет — она держится только требованием в докблоке и README.
- 2026-09-24 21:00 — пятая проверка фазы 3: исправление 4 закрыло восемь мест верно, но ввело три новых ложных утверждения того же класса — «вне открытой транзакции `send()` падает» (`NotificationContract.php:15`, `Notifications/README.md:81`, `:515-518`). Фактически `NotificationProvider::send()` идёт через `RequestNotificationHandler` с `#[Transactional]`, транзакция всегда открыта, `MissingTransactionException` не бросается.
- 2026-09-24 21:00 — причина провала повторилась (неверное утверждение о механизме записи), причём внесена самим исправлением предыдущего круга. По правилу сходимости выполнение остановлено, решение вынесено пользователю.
- 2026-09-24 20:15 — исправление 4 фазы 3: описание механизма записи приведено к поведению пакета (немедленный `insert()->run()` в уже открытой транзакции, вне транзакции — `MissingTransactionException`). Сверх восьми мест закрыты ещё три того же класса: `NotificationContract.php`, `PostNotifier.php`, `Notifications/README.md:513-515`. Термин «стейджит» (20 мест) оставлен как название бизнес-действия — механизма записи он не описывает.
- 2026-09-24 19:30 — четвёртая проверка фазы 3: механический проход по именам воспроизведён независимо и подтверждён, остатков нет. Новый класс дефекта: пакетный `OutboxEventStore::add()` делает немедленный `insert()->run()` внутри открытой транзакции, а удалённый `CycleStoredOutboxEventRepository::add()` делал `persist()`. Атомарность сохранена, но докблоки и README в пяти местах по-прежнему описывают постановку события в единицу работы и его флаш прогоном.
- 2026-09-24 19:30 — долг вне scope фазы (зафиксирован, не чинится): карточка `docs/references/integration-event.md` требует запись события после сохранения агрегата, а `RequestLoginCodeHandler` и `CompleteMediaUploadHandler` вызывают `add()` до `save()`. Расхождение существовало до фазы 3 и атомарности не ломает — вынести отдельной задачей.
- 2026-09-24 18:40 — класс дефекта закрыт механически: список из 120 имён, удалённых вместе с модулем, собран из `git show HEAD:` и прогреплен по `app`, `tests`, `docker`, `docs` (кроме artifacts) и корневым `*.md`. Дополнительно исправлено `Media/README.md:184` («outbox-сообщение» -> «событие outbox»); три прозаических упоминания слова «Outbox» оставлены как название механизма обмена.
- 2026-09-24 18:00 — третья проверка фазы 3: содержательная часть подтверждена, провал снова по документации об удалённом поведении, но места новые и объём убывает (три докблока и одна неточность формулировки). Причина не повторяется дословно, выполнение сходится; исполнителю поручен механический сплошной проход по списку удалённых имён.
- 2026-09-24 17:05 — отклонение исправления фазы 3: сверх трёх пунктов поправлены два места того же класса — термин «queue status» удалённого `OutboxQueueStatusInterceptor` в корневом README и докблоке `NonTransactionalDatabaseTestCase`, и не подтверждённая кодом фраза «пользы от второго процесса нет» в `docker/README.md`.
- 2026-09-24 16:30 — повторная проверка фазы 3: отклонения A (докблоки Posts) и B (изоляция тестов) признаны честными, утверждения тестов не ослаблены. Новый провал в другом файле: корневой `README.md` документирует `outbox:relay 100` (аргументов у команды нет), утверждает `FOR UPDATE` без `SKIP LOCKED` (пакет использует `SKIP LOCKED`) и называет ключом идемпотентности `outboxId` вместо `outboxDeliveryId`. Передано исполнителю.
- 2026-09-24 15:40 — отклонение исправления фазы 3: сверх семи пунктов поправлены четыре докблока модуля Posts, ссылавшиеся на удалённый `StoredOutboxEventRepository` (то же расхождение «документация называет удалённый класс»).
- 2026-09-24 15:40 — отклонение исправления фазы 3: исправлена изоляция тестов обмена — `MediaProcessingFlowTest` нетранзакционен и оставлял строки соседям по worker ParaTest; добавлена очистка таблиц обмена в `tearDown()` и в `setUp()` тестов, считающих события абсолютно. Утверждения тестов не менялись.
- 2026-09-24 15:10 — проверяющий фазы 3 признал все шесть отклонений приемлемыми, но вернул `failed` по расхождению документации с кодом: `Notifications/README.md` (`OutboxJobRegistryContract`, `OutboxQueueSerializer`, ссылка на удалённый README модуля, `RetryException` ×2), `Media/README.md` (`IntegrationEvent`, `ValinorOutboxMessageSerializer`, `OutboxQueueSerializer`, `RetryException` ×4), докблок `FcmPushFailedException.php`. Передано исполнителю фазы 3 на исправление.
- 2026-09-24 13:20 — отклонение фазы 3: размер пачки relay задан общим `App\Shared\Infrastructure\Spiral\Bootloader\OutboxRelayBootloader`, а не файлом `app/config/outbox.php`: `ConfigShapeTest::testRootTypedConfigsMatchConfigFiles` требует типизированный Config на каждую секцию из `app/config`, а план прямо запрещает заводить его на секцию пакета. Тест не ослаблялся.
- 2026-09-24 13:20 — отклонение фазы 3: `QUEUE_CONNECTION` переведён на `in-memory` (маршруты задают очередь явно через `Options::onQueue()`).
- 2026-09-24 13:20 — отклонение фазы 3 (выход за scope, вынужденный): `MAILER_QUEUE_CONNECTION` переведён с `null` на `sync` в `.env` и `.env.sample` — `Environment::normalize()` не превращает строку `null` в `null`, из-за чего Mailer искал подключение с именем `null` и Job падал; дефект существовал до фазы. Без этого сквозная проверка фазы недостижима.
- 2026-09-24 13:20 — отклонение фазы 3: в `QueueInterceptorsConfig` оставлена одна ветвь `Autowire` — две структурно одинаковые ветви объединения были неразличимы для Valinor после того, как bootloader пакета дописал интерсептор объектом `Autowire`.
- 2026-09-24 13:20 — отклонение фазы 3: обновлена карточка `docs/references/console-command.md` — её пример был построен на удалённой команде модуля.
- 2026-09-24 11:05 — проверяющий фазы 2 признал все три отклонения приемлемыми; в фазу 3 перенесено: пересмотреть `QUEUE_CONNECTION`, убрать осиротевший dev-exchange `yoga_loka_jobs` при пересоздании окружения.
- 2026-09-24 10:35 — отклонение фазы 2: план не называл очередь по умолчанию после удаления pipeline `rabbitmq`; исполнитель выбрал `QUEUE_CONNECTION=notifications` как временное значение. Проверить и при необходимости пересмотреть в фазе 3, когда маршруты задают очередь явно.
- 2026-09-24 10:35 — отклонение фазы 2: списки переменных окружения обновлены в `docker/README.md` и `app/src/Modules/Outbox/README.md` (план отводил `docker/README.md` фазе 3) — оба файла называли удалённые переменные `RABBITMQ_QUEUE_NAME`, `RABBITMQ_EXCHANGE_NAME`, `RABBITMQ_ROUTING_KEY`.
- 2026-09-24 10:35 — побочное действие в dev-окружении: удалена оставшаяся очередь `yoga_loka_jobs` (0 сообщений, 0 потребителей); репозитория не касается.
- 2026-09-24 09:45 — режим `auto` разрешён в `subagents`: фаза 3 — крупный рефакторинг шести событий, пяти handler и шести Job в четырёх модулях с удалением модуля, правкой deptrac, phpstan, конфигурации очередей и тестов; объём и число областей кода не помещаются в один контекст.
- 2026-09-24 09:45 — место выполнения: текущая ветка `main` (ответ пользователя).
- 2026-09-24 09:52 — перенесено в фазу 3 (отмечено исполнителем фазы 1): в `docs/arch.md` после удаления модуля надо поправить «Осознанные отступления» (пять Repository с `add()`/`save()` -> четыре, список технических исключений без `Outbox`) и «Проверка границ» (девять `{Module}Bootloader` -> восемь).

## Изменения в документации
- Фаза 1: `docs/arch.md` — восемь модулей без `Outbox`, обмен через пакет в разделах «Взаимодействие модулей» и «Runtime», контракты `GianTiaga\SpiralOutbox` в нейтральном слое, runtime пакета — только в `Infrastructure/Spiral`, `Shared/Infrastructure` и Kernel.
- Фаза 1: карточки `integration-event.md` и `job-consumer.md` — маршрут объявляется патчем секции `outbox` в bootloader модуля-потребителя; повторами владеет outbox.

## Финальная проверка

Выполнена отдельным субагентом 2026-09-24 22:40, результат `passed`.

```text
make qa          cs 0 of 1121 fixable; PHPStan level max No errors; deptrac violations 0,
                 skipped 0, allowed 7089; PHPUnit OK (1423 tests, 4863 assertions);
                 покрытие 100.00% при пороге 100.00%
make test        OK (1423 tests, 4863 assertions)
make test-unit   OK (524 tests, 1778 assertions), guard lightweight-набора пройден
make test-feature OK (219 tests, 794 assertions)
make test-kernel OK (680 tests, 2291 assertions)
make phpstan     [OK] No errors
make deptrac     Violations 0, Skipped violations 0, Errors 0
make up, migrate контейнеры Started/Healthy; No outstanding migrations were found
rabbitmqctl      yoga_loka_mail 0 1, yoga_loka_media 0 1, yoga_loka_notifications 0 1
```

Критерии готовности плана: каталога `app/src/Modules/Outbox` нет, модулей восемь; четыре модуля
работают через контракты пакета; шесть маршрутов совпали с таблицей плана точь-в-точь и закреплены
интеграционными тестами; `outbox:status`, `outbox:relay` и постоянный `outbox:relay --loop`
проверены вживую — при поднятом loop новый запрос кода входа за ~8 секунд дал доставку
`mail / completed`.

Подавлений и ослаблений не найдено: `Makefile` и конфиг стиля не менялись, baseline и
`skip_violations` отсутствуют, `markTestSkipped` в проекте нет, порог покрытия 100% на месте,
`phpstan.neon` только усилен параметром `integrationEventInterface`.

Обещанного планом и не сделанного не найдено.

## Остаточные долги вне scope плана
- Карточка `docs/references/integration-event.md` требует записи события после сохранения агрегата,
  а `RequestLoginCodeHandler` и `CompleteMediaUploadHandler` вызывают `add()` до `save()`.
  Расхождение существовало до этой работы, атомарности не ломает.
- Автоматической защиты от вызова `NotificationContract::send()` вне транзакции источника в коде
  нет: у сценария отправки свой `#[Transactional]`, поэтому такой вызов тихо успешен, а событие
  закрепляется отдельно от бизнес-данных. Держится требованием в докблоке контракта и в README.
- `RequestLoginCodeHandler` кладёт адрес почты в контекст DEBUG-записи (существовало до работы).
