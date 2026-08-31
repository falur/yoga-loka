Проверил план по указанным файлам и связанным тестам. План в целом реализуем, но в текущем виде есть несколько серьёзных дыр.

- [блокер] Критерий «онлайн-получатель push не получает» сейчас нельзя реально подтвердить в этом репозитории: нет выдачи Centrifugo connection token и нет клиентской подписки на `personal:#user_{userId}` — вынести это в отдельную обязательную зависимость с конкретным критерием готовности или добавить эту часть в план

- [блокер] План и текущий `CentrifugoClient` используют заголовок `Authorization: apikey ...`, а документация Centrifugo v6 для HTTP API показывает `X-API-Key`; если сервер не примет текущий заголовок, `presence_stats` всегда будет падать, а из-за fail-open push всегда уйдёт — проверить на живом `centrifugo:v6.7.2`, затем поправить заголовок и тесты

- [bug] В плане не прописан перехват битого JSON-ответа Centrifugo; `json_decode(... JSON_THROW_ON_ERROR)` может выбросить исключение мимо `CentrifugoPresenceException`, тогда Job упадёт и push не отправится, хотя нужен fail-open — явно обернуть невалидный JSON и неверную структуру ответа в `CentrifugoPresenceException::malformedResponse()`

- [реализуемость] В тестах `CentrifugoOnlinePresence` план предлагает мокать `CentrifugoClient`, но класс `final`, обычный PHPUnit его не замокает — тестировать через настоящий `CentrifugoClient` с фейковым `ClientInterface` или ввести маленький внутренний интерфейс для клиента presence

- [тесты] План упоминает обновление helper только в `SendPushNotificationHandlerTest`, но прямое создание `SendPushNotificationHandler` есть ещё в `DeliveryJobTest` — добавить туда offline-стаб `OnlinePresenceContract`, иначе тесты сломаются после изменения конструктора

- [эксплуатация] `make test` не поднимает Centrifugo: в `Makefile` для reset-test стартуют только postgres, redis, minio и mailpit, значит изменение `docker/centrifugo/config.json` не проверится — добавить отдельную проверку `centrifugo checkconfig` или smoke-команду для dev-конфига

- [производительность] План ставит presence-проверку до загрузки push-токенов, из-за этого Centrifugo будет вызываться даже когда push всё равно невозможен из-за отсутствия токенов — лучше оставить текущий ранний выход «нет токенов», а presence проверять уже перед FCM

- [скрытый риск] `SendPushNotificationHandler` не знает, были ли реально включены database или realtime для этого уведомления; если тип уведомления push-only, онлайн-пользователь вообще ничего не получит — либо явно принять это как продуктовый сценарий, либо передавать в push-событие признак доступного in-app/realtime канала и покрыть push-only тестом

- [тесты] Нет проверки DI-биндинга `OnlinePresenceContract -> CentrifugoOnlinePresence` — добавить kernel/unit тест bootloader-а или тест резолва из контейнера, иначе ошибка биндинга может не пойматься прямыми unit-тестами

- [тесты] План требует 100% покрытия, но `make test` покрытие не измеряет — добавить в критерии `make test-coverage` или `make qa`

- [документация] План говорит про production/staging Centrifugo config, но не указывает конкретный файл в репозитории; README модуля тоже надо обновить новым правилом «онлайн suppress push» — добавить конкретный docs/README шаг, иначе эксплуатационная часть останется устной

Оценка готовности плана: **68/100**.

Главная проблема не в самой идее, а в том, что план смешивает «реализовать backend guard» и «фича реально работает в проде». Backend guard реализуем, но без клиентской подписки, проверки заголовка API и отдельной проверки Centrifugo config готовность неполная.

Источник по Centrifugo HTTP API: https://centrifugal.dev/docs/server/server_api
