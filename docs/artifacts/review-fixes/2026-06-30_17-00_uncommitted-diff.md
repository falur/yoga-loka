---
review: docs/reviews/2026-06-30_17-00_uncommitted-diff.md
date: 2026-06-30 17:00
status: done
---

# Фиксы по ревью: Незакоммиченный diff — Media URL-сервис, удаление оригинала, LocaleResolver

Режим: `apply-optional`. Обязательное замечание исправлено; optional разобраны без вопроса —
полезные применены, нецелесообразный отклонён с причиной.

## Применённые правки

| # | Замечание | Файлы | Тесты | Статус |
|---|-----------|-------|-------|--------|
| 1 | Показ аватаров стал тяжелее: путь всегда грузит все конверсии, хотя нужен только оригинал | `Media/Application/Contract/MediaUrlServiceContract.php`, `Media/Infrastructure/FileService/MediaUrlService.php`, `Media/Application/Query/FindMediaOriginalUrl/FindMediaOriginalUrlQuery.php` (новый), `…/FindMediaOriginalUrlHandler.php` (новый), `User/Application/Profile/UserPublicProfileAssembler.php`, `User/Infrastructure/Bootloader/UserBootloader.php`, `Media/README.md` | `tests/Feature/Modules/Media/Application/FindMediaOriginalUrlHandlerTest.php` (6 ✓), `tests/Feature/Modules/User/Application/UserApplicationTestCase.php` (переключён) | ✓ применено (обязательно) |
| 2 | Верхняя граница presigned-TTL не проверяется в конфиге | `Shared/Infrastructure/Configuration/Media/MediaConfig.php`, `app/config/media.php` (комментарий) | `tests/Kernel/Shared/Infrastructure/Configuration/MediaConfigTest.php` (`testRejectsPresignedTtlAboveUpperBound`, 1 ✓) | ✓ применено (optional) |
| 3 | Проверка наличия конверсии поднимает все конверсии целиком | `Media/Repository/MediaImageConversionRepository.php`, `…VideoConversionRepository.php`, `…AudioConversionRepository.php`, `Media/Application/Command/RemoveMediaOriginal/RemoveMediaOriginalHandler.php` | `tests/Feature/Modules/Media/Repository/MediaRepositoryTest.php` (`testExistsForMediaIdReportsConversionPresence`, 1 ✓) | ✓ применено (optional) |
| 4 | Лента строит/подписывает URL всех конверсий, хотя берётся только оригинал | `Posts/Application/View/PostViewAssembler.php` (`mediaItem()` → `getOriginalUrl`) | покрыт общим фиксом №1 (`FindMediaOriginalUrlHandlerTest` — presignGet ровно 1 раз) + существующие feed/post HTTP-тесты | ✓ применено (optional) |
| 5 | В набор попало несвязанное правило линтера `single_quote` | — | — | ✗ отклонено (optional, причина ниже) |

## Что именно сделано

- **№1 (обязательное).** Добавлен лёгкий путь «только оригинал»: метод контракта/сервиса
  `MediaUrlServiceContract::getOriginalUrl(Media, ?int): MediaUrlResult|null` строит ссылку на
  оригинал и **не обращается к связям-конверсиям вовсе** (ни загрузки из БД, ни подписания). Новый
  Query `FindMediaOriginalUrl` грузит медиа через `findById` (без `->load(...)` конверсий) и зовёт
  `getOriginalUrl`. Аватар профиля (`UserPublicProfileAssembler` + `UserBootloader`) переключён на
  этот путь. Прежний `FindMediaUrl` (полный набор) сохранён как документированный публичный сценарий
  модуля (README/arch.md) и остаётся под своим тестом.
- **№2 (optional, принято).** Верхняя граница теперь проверяется при старте в конструкторе
  `MediaConfig` (как уже сделана проверка порога multipart): значение > 604800 → `InvalidConfigValueException`
  на запуске, а не 500 на первом построении ссылки для приватного медиа. Нижнюю границу конфиг и так
  обрезает (`\max(1, …)`), поэтому добавлена только верхняя проверка — без лишней непокрытой ветки.
- **№3 (optional, принято).** В три репозитория конверсий добавлен `existsForMediaId(MediaId): bool`
  (count-запрос, без гидрации сущностей). `RemoveMediaOriginalHandler::hasConversion()` переключён на
  него; ранний выход по `||` сохранён.
- **№4 (optional, принято).** `PostViewAssembler::mediaItem()` переключён с `getUrls()` на
  `getOriginalUrl()` — та же первопричина, что и в №1. Eager-load конверсий в `PostMediaRepository`
  оставлен без изменений (сознательный выбор по arch.md); сэкономлено только лишнее подписание URL.

## Решения по optional

- **Принято:** 2 (дешёвая проверка, убирает латентный 500 на всём приватном медиа, fail-fast при
  старте, есть прецедент в том же классе); 3 (count вместо гидрации всех конверсий — read-only
  репозиторный метод, явно рекомендован ревью); 4 (тот же фикс, что №1, низкий риск — лента и так
  использует только оригинал по существующему комментарию).
- **Отклонено:** 5 — это рекомендация по гигиене коммита (вынести правило линтера отдельным
  коммитом/PR либо отметить в сообщении коммита), а не дефект кода. Само правило `single_quote` не
  нарушает `docs/rules.md` (ревью это подтверждает), а его удаление откатило бы намеренное улучшение
  конфигурации и расширило бы scope. Скилл `eda-fix-by-review` не коммитит, поэтому действий по
  организации коммита здесь нет — это зона шага `eda-commit` (там и стоит упомянуть включённое
  общерепозиторное правило стиля в сообщении коммита).

## Финальная проверка

- **Тесты:** `make test` — ✓ (1284 теста, 4155 проверок, OK).
- **PHPStan:** `make phpstan` — ✓ (No errors).
- **Заметки:** проверки запускались через Docker (как требует AGENTS.md). Покрытие нового кода
  обеспечено добавленными тестами; отдельный coverage-гейт (`make qa`/`make test-coverage`) не
  запускался — задача требовала именно `make test` и `make phpstan`.
