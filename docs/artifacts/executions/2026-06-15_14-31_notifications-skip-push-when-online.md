---
plan: docs/plans/2026-06-15_13-36_notifications-skip-push-when-online.md
started: 2026-06-15 14:31
finished: 2026-06-15 14:55
status: done
---

# Журнал: Не отправлять push, если получатель онлайн (Notifications)

## Шаги
| # | Шаг | Файлы | Тесты | Статус |
|---|-----|-------|-------|--------|
| 1 | Фаза 1: presence на уровне клиента и инфраструктуры | `docker/centrifugo/config.json`, `Infrastructure/Centrifugo/CentrifugoPresenceStats.php`, `Infrastructure/Exception/CentrifugoPresenceException.php`, `Infrastructure/Centrifugo/CentrifugoClient.php` | `CentrifugoClientTest` (14/14, +7 presence-сценариев); `make phpstan` OK; `centrifugo checkconfig` EXIT=0 | done |
| 2 | Фаза 2: контракт онлайн-статуса и реализация | `Application/Contract/OnlinePresenceContract.php`, `Infrastructure/Centrifugo/CentrifugoOnlinePresence.php`, `Infrastructure/Bootloader/NotificationsBootloader.php` | `CentrifugoOnlinePresenceTest` (3, online/offline/fail-open+WARN), `NotificationsBootloaderTest` (+резолв биндинга через `new Container()` + `defineBindings()`); Unit 20/20; `make phpstan` OK | done |
| 3 | Фаза 3: пропуск push для онлайн-получателя | `Application/Command/Push/SendPushNotification/SendPushNotificationHandler.php`, тесты `SendPushNotificationHandlerTest.php`, `DeliveryJobTest.php` | helper с офлайн-стабом в обоих call-site; +тесты «онлайн → push пропущен» и «офлайн → push уходит»; `make phpstan` OK (Feature-прогон — в финальном `make qa`) | done |

## Заметки
- Место выполнения: ветка `work-1` (остаёмся, изменения дополняют незакоммиченный модуль Notifications).
- Phase 2 binding-тест: проверка резолва биндинга сделана без подъёма kernel — через `new Container()` + публичный `NotificationsBootloader::defineBindings()`. Это соблюдает конвенцию «kernel-тесты живут в tests/Kernel» и при этом честно резолвит `OnlinePresenceContract` через контейнер, ловя ошибку биндинга. Остаётся в suite Unit.
- CS Fixer: в helper-е теста потребовалась форма `OnlinePresenceContract|null` вместо `?OnlinePresenceContract` (правило `nullable_type_declaration`).
- Покрытие: первый `make qa` дал 99.97% — не достигалась ветка `throw malformedResponse()` в `parsePresenceStats` при валидном JSON-не-массиве (битый JSON ловится как `\JsonException`). Добавлен тест `testPresenceNonArrayJsonIsWrapped` (тело `42`) → 100%.
- PHPUnit Notice: 1 — предсуществующий, не из затронутых файлов (Unit/Feature-тесты Notifications с `--display-notices` чисты); сборку не валит (config не падает на notices). Не трогал (точечные изменения).

## Финальная проверка
- `make qa` (стиль PHP CS Fixer + PHPStan level max + один coverage-run PCOV по Unit/Kernel/Feature): **зелёный**. Tests: 581, Assertions: 1691. Покрытие **100.00%** (порог 100%). PHPStan: No errors. Стиль: чисто.
- `centrifugo checkconfig` для нового `docker/centrifugo/config.json`: EXIT=0 (Фаза 1).

## Изменения в docs
- Нет изменений в `docs/rules.md`/`docs/arch.md`: реализация уложилась в существующие правила и архитектуру. Эксплуатационное требование к prod/staging-конфигу Centrifugo (`presence` + `allow_user_limited_channels` у namespace `personal`) уже зафиксировано в самом плане (раздел «Документация и эксплуатация»); отдельного runbook-файла в репозитории нет.
