---
plan: docs/plans/2026-06-29_20-08_media-url-service-to-infrastructure.md
started: 2026-06-29 21:42
finished: 2026-06-29 21:58
status: done
---

# Журнал: MediaUrlService → Infrastructure за контрактом MediaUrlServiceContract

## Шаги
| # | Шаг | Файлы | Тесты | Статус |
|---|-----|-------|-------|--------|
| 1 | Контракт + перенос реализации в Infrastructure/FileService + биндинг + потребители | +Application/Contract/MediaUrlServiceContract; +Infrastructure/FileService/{MediaUrlService,MediaUrlResolver,PublicMediaUrlResolver,PresignedMediaUrlResolver}; −Application/Service/{те же 4}; MediaBootloader; FindMediaUrlHandler; PostViewAssembler | `make phpstan` зелёный | done |
| 2 | Тесты под перенос: конструктор `MediaUrlService(MediaFileServiceContract, MediaConfig)`; биндинг через контракт | FindMediaUrlHandlerTest; UserApplicationTestCase; MediaBootloaderTest | `make test` зелёный (1268 тестов) | done |
| 3 | Документация: README Media + докблок MediaFileServiceContract под новое размещение | README.md; MediaFileServiceContract | грепы чистые; `make qa` зелёный | done |

## Финальная проверка

`make qa` зелёный: стиль OK, PHPStan (level max, `app/src`) — No errors, 1268 тестов / 4121 ассершн, покрытие 100.00% при пороге 100.00%.

Грепы переноса:
- старый namespace `Application\Service\{MediaUrlService,резолверы}` — нет ссылок;
- `MediaPresignedTtl` в `MediaBootloader` — отсутствует;
- потребители (`FindMediaUrlHandler`, `PostViewAssembler`) зависят от `MediaUrlServiceContract`;
- биндинг `MediaUrlServiceContract::class => MediaUrlService::class` в `const BINDINGS`.

## Заметки

- `MediaTypeResolver` в `Application/Service` оставлен на месте — не входит в охват (переезжают только `MediaUrlService` и три URL-резолвера).
- Старые файлы `Application/Service` были untracked (часть незакоммиченной работы) — удалены через `rm`.
- PHPStan анализирует только `app/src` (paths в `phpstan.neon`), поэтому фаза 1 прошла независимо от ещё не правленных тестов.
- Семантика TTL по умолчанию: `MediaPresignedTtl::fromInt($presignedTtlSeconds ?? $this->mediaConfig->presignedTtlSeconds)` — `??` ловит только `null`, явный `0` остаётся `0` → `fromInt(0)` бросает `InvalidDomainValueException`.

## Изменения в docs

## Изменения в docs
</content>
</invoke>
