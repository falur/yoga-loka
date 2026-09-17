---
plan: docs/artifacts/plans/2026-09-15_20-08_volna-b-publichnyj-yazyk-modulej.md
started: 2026-09-15 20:12
status: done
mode: subagents
current_phase: 9
finished: 2026-09-16 02:20
---

# Журнал выполнения: Волна B переезда на целевую архитектуру — публичный язык модулей

Режим задан явно в вызове: `субагенты`. Место выполнения передано оркестратором: текущая ветка `arch-modular-migration`, новая ветка и worktree не создаются. Запуск автономный: вопросы пользователю не задаются, развилки закрываются по `docs/arch.md`, `docs/rules.md` и карточкам `docs/references/`, решение с причиной пишется в этот журнал.

## Фазы

| Фаза | Результат | Проверка | Статус |
|---|---|---|---|
| 1. Публичные контракты Outbox и конверт очереди | Auth, Media, Notifications не импортируют `Outbox\Application` | `make qa`, grep по `Outbox\Application` | завершена, проверка passed |
| 2. Интеграционные события в Public/Event | Нет папок `Application/Message`, доставка работает | `make qa` | завершена, проверка passed |
| 3. Публичные DTO медиа и пакетные ссылки для User и Notifications | User и Notifications не импортируют `Media\Application` | `make qa`, неизменная OpenAPI | завершена, проверка passed |
| 4. Posts переходит на публичные ссылки медиа | Posts не импортирует `Media\Application` и `Media\Infrastructure` | `make qa`, неизменная OpenAPI | завершена, проверка passed |
| 5. Пакетное вложение медиа | Нет вызовов Media в цикле | `make qa` | завершена, проверка passed |
| 6. Публичный контракт User | Auth и Posts не импортируют `User\Application` | `make qa` | завершена, проверка passed |
| 7. Публичный контракт Tags | Posts не импортирует `Tags` мимо `Public` | `make qa` | завершена, проверка passed |
| 8. Публичный контракт Notifications | Posts не импортирует `Notifications\{Application,Domain}` | `make qa` | завершена, проверка passed |
| 9. Граница волны подтверждена | Межмодульные импорты только через `Public` плюс два отложенных исключения | `make qa`, grep, route:list, OpenAPI | завершена, проверка passed |

## Запуски

| Время | Исполнитель | Фаза | Результат | Файлы | Проверки |
|---|---|---|---|---|---|
| 2026-09-15 20:12 | субагент-исполнитель фазы 1 | 1 | completed | 80 файлов: `Outbox/Public/*`, сценарии `StoreIntegrationEvent`/`LoadIntegrationEvent`, три провайдера `PublicApi`, очередь и шесть Job, bootloader-ы и handler-ы Auth, Media, Notifications, `docs/arch.md`, тесты | `make qa`: cs-fixer 0, PHPStan No errors, тесты 1303/4301, падает только известный S3-тест, покрытие 100.00% (база: 1304/4302, то же падение, 100.00%); grep по `Outbox\Application` вне модуля — пусто |

| 2026-09-15 20:50 | субагент-исполнитель фазы 2 | 2 | completed | 88 файлов: шесть событий в `Public/Event` своих модулей с суффиксом `Event`, payload-DTO Notifications и Media в `Public/Dto`, три публичных enum конверсий Media, маршруты в четырёх bootloader-ах, шесть Job, README Media/Notifications/Outbox, тесты | `make qa`: cs-fixer 0 из 994, PHPStan No errors, тесты 1306/4304, падает только известный S3-тест, покрытие 100.00%; `find app/src/Modules -type d -name Message` — пусто; `Public` импортирует только `Public` |

| 2026-09-15 21:35 | субагент-исполнитель фазы 3 | 3 | completed | 41 файл: `Media/Public/{Contract/MediaContract, Dto/Media*Dto, Dto/MediaDtoCollection, Enum/MediaConversionKind}`, `Media/Infrastructure/Spiral/PublicApi/MediaProvider`, пакетная сборка профилей User, Notifications (инбокс, push, realtime, ресурсы-копии), вынужденный минимум в Posts, `app/config/openapi.php`, тесты | `make qa`: cs-fixer 0 из 1009, PHPStan No errors, тесты 1311/4334, падает только известный S3-тест, покрытие 100.00%; OpenAPI после правок побайтово совпала с базовым снимком; `Media\Application` в User и Notifications не импортируется |

| 2026-09-15 22:35 | субагент-исполнитель фазы 4 | 4 | completed | 17 файлов: `PostMediaView`, `PostViewAssembler` на `MediaContract::urlsByIds` одним вызовом на ответ, `PostResource` и копия `MediaResource` Posts, удалены три представления медиа и три HTTP-ресурса Media вместе с проекцией `toView()`, README Media, три новых теста | `make qa`: cs-fixer 0 из 1004, PHPStan No errors, тесты 1314/4358, падает только известный S3-тест, покрытие 100.00%; OpenAPI побайтово равна доволновому снимку; `Media\Infrastructure` в Posts не импортируется |

| 2026-09-15 23:10 | субагент-исполнитель фазы 5 | 5 | completed | 16 файлов: `MediaContract` получил `ensureAttachable` и `makePermanent` на набор, сценарии `CheckMediaAttachable` и `MakeMediaPermanent` переведены на набор (у команды свой минимальный Result), `MediaProvider`, `PostContentComposer::attachMedia` без вызовов в цикле, README Media, тесты | `make qa`: cs-fixer 0 из 1005, PHPStan No errors, тесты 1323/4377, падает только известный S3-тест, покрытие 100.00% |

| 2026-09-15 23:50 | субагент-исполнитель фазы 6 | 6 | completed | 24 файла: `User/Public/{Contract/UserContract, Dto/CreatedUserDto, Dto/UserSignInDto, Dto/UserProfileDto, Dto/UserProfileDtoCollection}`, `UserProvider` и привязка в `UserBootloader`, два handler-а Auth, семь файлов Posts, собственный ключ `app.posts.author_not_found` в двух локалях, тесты | `make qa`: cs-fixer 0 из 1013, PHPStan No errors, тесты 1334/4407, падает только известный S3-тест, покрытие 100.00%; вне модуля User импортируется только `User\Public` |

| 2026-09-16 00:25 | субагент-исполнитель фазы 7 | 7 | completed | 18 файлов: `Tags/Public/{Contract/TagsContract, Dto/TagDto, Dto/TagDtoCollection, Dto/ResolvedTagsDto}`, `TagsProvider` и привязка в `TagsBootloader`, `Posts/Domain/ValueObject/PostTagReference` вместо чужого `TagId` (сущность, typecast колонки, выборка), `PostContentComposer`, `PostViewAssembler`, тесты | `make qa`: cs-fixer 0 из 1021, PHPStan No errors, тесты 1341/4427, падает только известный S3-тест, покрытие 100.00%; из Tags в Posts импортируется только `Tags\Public` |

| 2026-09-16 01:10 | субагент-исполнитель фазы 8 | 8 | completed | 42 файла: `Notifications/Public/{Enum/NotificationChannel, Dto/NotificationChannelCollection, Dto/NotificationContentDto, Contract/NotificationContract, Contract/NotificationTypeDefinition, Contract/NotificationTypeRegistryContract}`, сценарий `RequestNotification`, два провайдера и привязки в bootloader, внутренний контракт каталога видов, три внутренних потребителя, четыре файла Posts, README, тесты | `make qa`: cs-fixer 0 из 1029, PHPStan No errors, тесты 1345/4452, падает только известный S3-тест, покрытие 100.00%; OpenAPI побайтово равна снимку; в Posts из Notifications только `Public` |

| 2026-09-16 01:55 | субагент-исполнитель фазы 9 | 9 | completed | 27 файлов: удалён осиротевший сценарий `FindMediaUrl` с репозиторным методом и переносом девяти проверок на пакетный сценарий, поправлены докблоки с удалённым `MediaView` (10 файлов и 2 теста), README Outbox и Media, `docs/arch.md` (`Public -> Public других модулей`), возвращён fail-fast на дубль канала с тестом | `make qa`: cs-fixer 0 из 1026, PHPStan No errors, тесты 1344/4452, падает только известный S3-тест, покрытие 100.00%; межмодульные импорты — только `Public` и два отложенных исключения (5 строк в 3 файлах); Shared — только bootloader-ы в Kernel; `route:list` 33 маршрута, 31 совпадает с операциями снимка; OpenAPI побайтово равна снимку |

## Запуски проверок

| Время | Проверяющий | Фаза | Результат | Проверки |
|---|---|---|---|---|
| 2026-09-16 02:20 | субагент-проверяющий фазы 9 и финала волны | 9 | passed | `make qa` (cs-fixer 0 из 1026, PHPStan level max No errors, тесты 1344/4452, падает только известный S3-тест), покрытие 100.00%; перенос проверок `MediaUrlService` сверен построчно и покрытие полное; правки докблоков не затронули код; `docs/arch.md` изменён одной строкой; fail-fast возвращён дословно; межмодульные импорты проверены собственным скриптом — только `Public` и два отложенных исключения; `Public` не тянет внутренние слои; `route:list` 33 маршрута, спецификация побайтово равна снимку; подавлений за волну ноль |
| 2026-09-16 01:30 | субагент-проверяющий фазы 8 | 8 | passed | `make qa` (cs-fixer 0 из 1029, PHPStan No errors, тесты 1345/4452, падает только известный S3-тест), покрытие 100.00%, в Posts из Notifications только `Public` (10 импортов), тело отправки и обе записи журнала перенесены дословно, каналы по видам и ключи переводов прежние, payload события идентичен, Kernel не менялся, OpenAPI побайтово равна снимку |
| 2026-09-16 00:45 | субагент-проверяющий фазы 7 | 7 | passed | `make qa` (cs-fixer 0 из 1021, PHPStan No errors, тесты 1341/4427, падает только известный S3-тест), покрытие 100.00%, из Tags в Posts только `Public`, миграции не тронуты и колонка `post_tags.tag_id` прежняя, состав меток гарантирован unique-индексом и внешним ключом, порядок меток и раньше не был определён, OpenAPI побайтово равна снимку |
| 2026-09-16 00:05 | субагент-проверяющий фазы 6 | 6 | passed | `make qa` (cs-fixer 0 из 1013, PHPStan No errors, тесты 1334/4407, падает только известный S3-тест), покрытие 100.00%, вне модуля User импортируется только `User\Public`, отклонение по форме коллекции проверено по трём сценариям и поведения не меняет, тексты нового ключа дословно совпадают с ключом владельца и статус 404 сохранён, вложенная транзакция Auth прежняя, OpenAPI побайтово равна снимку |
| 2026-09-15 23:25 | субагент-проверяющий фазы 5 | 5 | passed | `make qa` (cs-fixer 0 из 1005, PHPStan No errors, тесты 1323/4377, падает только известный S3-тест), покрытие 100.00%, ошибки и их порядок дословно прежние (проверка готовности строго сильнее guard перевода, поэтому первая ошибка та же), вызовов Media в цикле нет, дедупликация и позиции сохранены, пустой набор к соседу не идёт, транзакция и финальный flush прежние, OpenAPI побайтово равна снимку |
| 2026-09-15 22:50 | субагент-проверяющий фазы 4 | 4 | passed | `make qa` (cs-fixer 0 из 1004, PHPStan No errors, тесты 1314/4358, падает только известный S3-тест), покрытие 100.00%, мягкая деградация вложений дословно прежняя (сверено с `git show HEAD:`), один вызов `urlsByIds` на ответ включая оригиналы репостов, пустой набор к соседу не ходит, OpenAPI побайтово равна снимку, у удалённых классов нет потребителей, ORM-связь и eager-load на месте, подавлений не добавлено |
| 2026-09-15 22:05 | субагент-проверяющий фазы 3 | 3 | passed | `make qa` (cs-fixer 0 из 1009, PHPStan No errors, тесты 1311/4334, падает только известный S3-тест), покрытие 100.00%, `Media\Application` в User и Notifications не импортируется, провайдер без бизнес-правил и связан в bootloader, один вызов Media на ответ и пустой набор к соседу не ходит, поведение «аватара нет» дословно прежнее, спецификация побайтово равна доволновому снимку, все ветви `match` в провайдере покрыты |
| 2026-09-15 21:05 | субагент-проверяющий фазы 2 | 2 | passed | `make qa` (cs-fixer 0 из 995, PHPStan No errors, тесты 1306/4304, падает только известный S3-тест), покрытие 100.00%, папок `Message` в `app/src` нет, все шесть событий `final readonly` с promoted-свойствами и маркером `IntegrationEvent`, `Public` импортирует только `Public`, публичные enum — точные дубликаты доменных, конфигурации проверок и `app/config/queue.php` не менялись |
| 2026-09-15 20:40 | субагент-проверяющий фазы 1 | 1 | passed | `make qa` (cs-fixer 0 из 991, PHPStan No errors, тесты 1303/4301, падает только известный S3-тест), покрытие 100.00%, 0 обращений к Outbox мимо `Public`, `Public` без единого `use`, провайдеры без ветвлений, конфигурации проверок не менялись, формат очереди прежний |

## Решения и блокеры

- Базовый результат `make qa` до волны: cs-fixer 0 файлов, PHPStan level max — No errors, тесты 1304, assertions 4302, 1 падение (`S3MediaFileServiceTest::testCopyObjectToPublicBucketIsAnonymouslyReadable`), покрытие 100.00%. Из-за этого падения `make qa` и на базе возвращает ненулевой код, поэтому шаг покрытия внутри qa не достигается и покрытие считается тем же `docker/test/assert-coverage.php` на сгенерированном clover.
- Фаза 1, отклонение исполнителя с причиной: проверка «сериализатор вернул не тот класс» размещена в `LoadIntegrationEventResult::eventOf()`, а не в теле handler. Причина — PHPStan level max не протаскивает дженерик через `dispatch()` пакета spiral-cqrs, и провайдер иначе не может выполнить свой `@return`. Правило осталось в Application в одном экземпляре, провайдер без ветвлений, игноров и baseline не добавлено. Принято.
- Фаза 1, замечание проверяющего (не блокирует): карточки `integration-event.md` и `job-consumer.md` описывают контракты outbox как приходящие из пакета `GianTiaga\SpiralOutbox`, который подключён в composer, но в `app/src` не используется ни строкой. Это расхождение зафиксировано картой расхождений (п. 9 закрытых развилок) до начала волны: roadmap выносит изменения в собственных пакетах за рамки, поэтому модуль Outbox остаётся собственной реализацией, а расхождение карточек с кодом закрывается отдельной задачей (30). Волна B его не трогает.
- Фаза 1, замечание проверяющего (не блокирует): маркер назван `IntegrationEvent` без суффикса `Contract` — это маркер события, а не операция; имя взято из таблицы контрактов плана. Принято.
- Фаза 1: тест удалённого `StoredOutboxEventId` удалён вместе с классом, поэтому число тестов уменьшилось с 1304 до 1303.

- Фаза 2, отклонения исполнителя (приняты): `SerializedOutboxMessage` перенесён в `Outbox/Application/Dto` — событием он не является, но критерий «папок `Message` не осталось» требовал переезда; имя события Outbox выбрано `OutboxDebugLogRequestedEvent` по образцу остальных пяти; текст записи журнала `NotificationRequested застейджен.` оставлен дословно по разделу «Логирование» плана; в тестах, где нужны оба enum одного имени, публичный импортируется под алиасом.

- Критерий проверки OpenAPI уточнён (решение оркестратора, автономно): в плане фазы 3, 4 и 9 записан `git diff --exit-code public/openapi/openapi.yml` после генерации, но проверяющий фазы 2 показал, что закоммиченный `public/openapi/openapi.yml` устарел относительно генератора ещё до волны (последний коммит файла — `66ae827`; сгенерированный сейчас файл побайтово совпадает со сгенерированным до фазы 1). Поэтому критерий читается так: спецификация, сгенерированная после фазы, совпадает со спецификацией, сгенерированной до волны. Базовый снимок сохраняется исполнителем фазы 3 в `/tmp/yoga-loka-wave-b/openapi-baseline.yml` и используется фазами 4 и 9. Перегенерация закоммиченного файла в волну не входит: публичный HTTP API не меняется, а расхождение существовало до начала работ; оно выносится в незакрытые пункты.
- Фаза 2, замечание проверяющего: в `app/src/Modules/Outbox/README.md` в примере «как добавить новое outbox-сообщение» остался старый namespace `Application\Message`. Закрывается в фазе 9 вместе с остальной документацией.

- Фаза 3, отклонения исполнителя (приняты): минимально затронут Posts (`AuthorView`, `AuthorResource` и три копии ресурсов медиа) — смена типа аватара в профиле User ломала компиляцию Posts, изменения совпадают с тем, что и так предписано фазе 4; перевод доменного вида конверсии в публичный сделан исчерпывающим `match` по виду (все три ветви покрыты новым `MediaProviderTest`), потому что цепочка `tryFrom` держалась бы на непроверяемой непересекаемости значений; одиночный сценарий `FindMediaUrl` остался без внешних потребителей и удаляется в фазе 9 (сейчас его тест держит покрытие `MediaUrlService`); HTTP-тест Notifications расширен конверсией аватара, иначе новая копия ресурса конверсии осталась бы непокрытой.
- Фаза 4: критерий «в Posts нет импортов `Media\Application`» достигнут частично — четыре импорта остались в `PostContentComposer` (проверка вложения и перевод в постоянное состояние). Это ровно предмет фазы 5, которой они назначены планом; формулировка результата фазы 4 опережала события. Принято, закрывается фазой 5.
- Фаза 8, пункт для фазы 9 (замечание проверяющего): вместе с удалённым доменным `NotificationChannelDefaults` исчез fail-fast «канал указан в определении дважды»; наблюдаемого поведения это не меняет, но защиту от ошибки разработчика стоит вернуть проверкой в `NotificationChannelCollection::of()`.
- Фаза 8, пункт для фазы 9 (замечание проверяющего): тест `tests/Kernel/Modules/Posts/Notification/PostNotificationTypesTest.php` (тест Posts) тянет внутренние типы Notifications — граница прощупывается из чужого теста.
- Фаза 6, пункт для фазы 9 (замечание проверяющего): строка направлений зависимостей `Public` в `docs/arch.md` не называет межмодульный импорт `Public -> чужой Public`, хотя он предписан планом (`UserProfileDto` с публичным аватаром Media) и уже применён с фазы 1 (маркер `IntegrationEvent` Outbox). Это пробел документации — дополнить строку в фазе 9 тем же точечным способом.
- Фаза 4, пункт для фазы 9 (замечание проверяющего): имя удалённого класса `MediaView` осталось в тексте докблоков 11 файлов Notifications и Posts и двух тестов — документация ссылается на несуществующий тип, поправить в фазе 9.
- Фаза 3, обязательный пункт для фазы 9 (подтверждён проверяющим): удалить осиротевший сценарий `Media/Application/Query/FindMediaUrl` вместе с его тестом, перенеся покрытие `MediaUrlService` (private/presigned, валидация TTL, `readyOriginalRemoved`) на пакетный сценарий.
- Фаза 3: базовый снимок спецификации сохранён в `/tmp/yoga-loka-wave-b/openapi-baseline.yml`; рабочее дерево `public/openapi/openapi.yml` возвращено к закоммиченному состоянию.

## Изменения в документации

- `docs/arch.md`: строка направления зависимостей `Public` дополнена общими примитивами `Shared/Domain` (предписано первой фазой плана, нужно для типизированных списков в `Public`).
- `app/src/Modules/Outbox/README.md`: описание межмодульной поверхности переведено на публичные контракты; в фазе 2 обновлён пример регистрации маршрута события.
- `app/src/Modules/Media/README.md` и `app/src/Modules/Notifications/README.md` (фазы 2 и 3): публичный контракт ссылок и публичные DTO, пакетное чтение;
- те же файлы (фаза 2): таблицы сценариев, разделы DTO и интеграционных событий, предусловие выката про разбор outbox.

## Финальная проверка

Выполнена отдельным проверяющим субагентом после всех фаз, вместе с проверкой фазы 9. Результат: `wave_acceptance: passed`.

```text
make qa (Docker)
  php-cs-fixer      0 из 1026 файлов к исправлению
  PHPStan level max [OK] No errors
  тесты             1344 теста, 4452 assertions, 1 падение —
                    только известный до волны
                    Tests\Feature\Modules\Media\Infrastructure\S3MediaFileServiceTest
                    ::testCopyObjectToPublicBucketIsAnonymouslyReadable
  покрытие          100.00% при пороге 100.00% (отдельной командой на clover того же прогона,
                    потому что из-за известного падения qa не доходит до шага покрытия)
База до волны       1304 теста, 4302 assertions, то же одно падение, покрытие 100.00%
маршруты            33 (php app.php route:list), 31 из них — операции спецификации
OpenAPI             31 операция, 50 схем; побайтово равна доволновому снимку
                    /tmp/yoga-loka-wave-b/openapi-baseline.yml (md5 fd8d4a16c3994dddcfbf915caa85157b)
```

Публичные контракты волны и их провайдеры:

```text
Модуль        Контракт                          Операции                                   Провайдер
Outbox        IntegrationEvent                  маркер интеграционного события             — (маркер)
Outbox        IntegrationEventStoreContract     add                                        IntegrationEventStoreProvider
Outbox        IntegrationEventLoaderContract    load                                       IntegrationEventLoaderProvider
Outbox        IntegrationEventRoutingContract   register                                   IntegrationEventRoutingProvider
Media         MediaContract                     urlsByIds, ensureAttachable, makePermanent MediaProvider
User          UserContract                      createUser, findForSignIn, existsAll,      UserProvider
                                                profile, profilesByIds
Tags          TagsContract                      resolve, textsByIds                        TagsProvider
Notifications NotificationContract              send                                       NotificationProvider
Notifications NotificationTypeDefinition        code, defaultChannels                      — (реализует модуль-источник)
Notifications NotificationTypeRegistryContract  register                                   NotificationTypeRegistryProvider
```

Межмодульные импорты в `app/src`: кроме `{Module}\Public\...` остались ровно два прямо отложенных исключения — middleware Auth в `Posts/Infrastructure/Spiral/Http/Controller/{PostController,CommentController}.php` (задача 8 roadmap) и `Media\Domain\Entity\Media` в `Posts/Domain/Entity/PostMedia.php` (задача 9 roadmap). `Shared` импортирует только девять bootloader-ов в `Kernel`. `Public` любого модуля импортирует только `Public` соседей и три примитива `Shared/Domain`.

Незакрытое (не блокирует приёмку волны):

- закоммиченный `public/openapi/openapi.yml` устарел относительно генератора ещё до волны (последний коммит файла — `66ae827`, различия чисто форматные, следов волны B нет); перегенерация вынесена за рамки решением оркестратора;
- `tests/Kernel/Modules/Posts/Notification/PostNotificationTypesTest.php` читает внутренний контракт каталога видов Notifications: публичного чтения реестра нет намеренно, а файл лежит в сквозном `tests/`, а не в коде модуля;
- `FindMediaUrlsQuery::$presignedTtlSeconds` остался без внешних потребителей (публичный контракт срок не принимает) — кандидат на снос в следующей волне;
- репозитории девяти модулей по-прежнему лежат в `{Module}/Repository` вместо целевого `Infrastructure/Persistence/Cycle/Repository` — доволновое расхождение, задачи 11–12 roadmap;
- значение колонки `outbox_events.type` изменилось вместе с переездом классов событий: перед выкатом outbox и RabbitMQ должны быть разобраны до конца.
