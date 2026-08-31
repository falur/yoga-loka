---
review: docs/reviews/2026-06-30_17-50_uncommitted-diff.md
date: 2026-06-30 18:29
status: done
---

# Фиксы по ревью: Незакоммиченный diff — второй круг (Media URL-сервис, RemoveMediaOriginal, LocaleResolver)

Режим скилла: `apply-optional`. Обязательные пункты (3, 4) исправлены. По каждому optional
(1, 2, 5, 6, 7) принято решение самостоятельно — все пять применены (для пунктов с вариантами выбран
наименее рискованный вариант).

## Применённые правки

| # | Замечание | Файлы | Тесты | Статус |
|---|-----------|-------|-------|--------|
| 3 | `FindMediaOriginalUrl` (handler+query) и его тест — untracked, вне `git diff HEAD` | `git add` сценария и теста (НЕ коммит) | `FindMediaOriginalUrlHandlerTest` (проиндексирован) | ✓ применено |
| 4 | PHPUnit 13: `method()` без `expects()` на `createMock` — 3 места | `tests/Feature/Modules/Media/Application/FindMediaUrlHandlerTest.php` | весь Media-suite зелёный | ✓ применено |
| 1 | Комментарии описывают удалённый способ получения URL медиа | `Posts/Application/View/PostViewAssembler.php`, `Posts/Domain/Entity/PostMedia.php`, `app/config/media.php` | — (только текст) | ✓ применено (optional) |
| 2 | Лента eager-грузит конверсии, которые сейчас не читает | `Posts/Repository/PostMediaRepository.php` | — (только комментарий, вариант «а») | ✓ применено (optional) |
| 5 | `UserPublicProfileAssembler` обходит `QueryBus` — `#[LogOperation]` мёртв, обоснование неверно | `User/Application/Profile/UserPublicProfileAssembler.php`, `User/Infrastructure/Bootloader/UserBootloader.php`, `tests/Feature/Modules/User/Application/UserApplicationTestCase.php` | существующие feature-тесты профиля зелёные | ✓ применено (optional, вариант «а») |
| 6 | `docs/arch.md` рассинхронен с кодом (потребитель ленты + список сценариев) | `docs/arch.md` | — (документация) | ✓ применено (optional) |
| 7 | `MediaUploadPlannerContract` не фиксирует инвариант `partSize()`/`partsCount()` | `Media/Application/Contract/MediaUploadPlannerContract.php` | — (docblock, вариант «а») | ✓ применено (optional, вариант «а»; «б» отклонён) |

### Детали по обязательным

- **№3.** Выполнен `git add app/src/Modules/Media/Application/Query/FindMediaOriginalUrl/ tests/Feature/Modules/Media/Application/FindMediaOriginalUrlHandlerTest.php`. Файлы проиндексированы (статус `A`), теперь входят в один changeset с зависящими tracked `UserPublicProfileAssembler`/`UserBootloader`. Коммит НЕ делался.
- **№4.** В `FindMediaUrlHandlerTest`: `:71` `publicUrl` → `expects(self::atLeastOnce())`, `:113` `presignGet` → `expects(self::atLeastOnce())`, `:198` `publicUrl` → `expects(self::once())`. `createMock` сохранён (на сиблинг-методе стоит `expects(self::never())`, заменить на `createStub` нельзя). По охвату проверены соседние новые тесты Media: `FindMediaOriginalUrlHandlerTest` (все `createMock` уже с `expects`, единственный `->method()` без expects — на `createStub`, корректно), `RemoveMediaOriginalHandlerTest` (все с `expects`), `MediaUploadPlannerTest` (мок-дублёров с `->method()` нет). Новых нарушений паттерна нет.

## Решения по optional

- **Принято №1:** дёшево, расхождение «комментарий против кода» — реальный дефект сопровождения; правка только текста. Классовый docblock `PostViewAssembler` приведён к фактическому потоку (eager media.*, `getOriginalUrl`, для private — только подпись). `PostMedia` уточнён (лента — `getOriginalUrl`, `getUrls` — полный набор). `media.php` упоминает оба Query.
- **Принято №2 (вариант «а»):** добавлен комментарий в `PostMediaRepository`, фиксирующий, что eager-load конверсий держится осознанно под планируемый показ превью (по `docs/arch.md`) и убирать его можно только синхронно с правкой arch.md. Кода не трогал — ровно как предписывает ревью (вариант «убрать `->load(...)`» недопустим как обычная правка).
- **Принято №5 (вариант «а»):** перевёл вызов на `QueryBus::dispatch(query, handler)` — это вернуло к работе `#[LogOperation]` обработчика и привело ассемблер к единому паттерну с `PostViewAssembler`; ошибочный абзац про «потерю null» из docblock убран (шина generic `@return TResult`, `MediaUrlResult|null` сохраняется). Конструктор пополнен `QueryBusInterface`, обновлены фабрика бутлоадера и тестовый конструктор (`UserApplicationTestCase`). PHPStan зелёный — null не теряется, подавление статанализа не понадобилось.
- **Принято №6:** `arch.md:168` — потребитель ленты исправлен на `getOriginalUrl`, `getUrls` оставлен как путь полного набора (`FindMediaUrl`); формулировка сохраняет согласованность с №2 (eager-load конверсий — под будущее превью). В список сценариев Media добавлены `RemoveMediaOriginal` и `FindMediaOriginalUrl`.
- **Принято №7 (вариант «а»):** в `MediaUploadPlannerContract` добавлен docblock с инвариантом «partsCount делит на то же значение, что возвращает partSize()».
- **Отклонён вариант «б» п.7** (объединить `partSize()`/`partsCount()` в один result-DTO `{partsCount, partSize}`): расширяет scope (новый DTO + правка `RequestMediaUploadHandler` + правка тестов планировщика) ради латентного риска, который вариант «а» уже закрывает дешёвым docblock-инвариантом. Конструктивная защита от рассогласования не оправдывает регрессионный риск на этом этапе — при росте числа реализаций контракта вопрос можно пересмотреть.

## Финальная проверка

- **Тесты:** `make test` — ✓ OK (1284 теста, 4155 assertions), через Docker.
- **PHPStan:** `make phpstan` — ✓ No errors, через Docker.
- **Заметки:** изменения не закоммичены. Untracked-файлы `FindMediaOriginalUrl` проиндексированы (`git add`) согласно п.3, без коммита.
