---
plan: docs/plans/2026-06-29_16-38_application-config-independence.md
started: 2026-06-29 17:50
finished: 2026-06-29 18:25
status: done
---

# Журнал: Application не зависит от конфига (+ presigned TTL)

## Шаги
| # | Шаг | Файлы | Тесты | Статус |
|---|-----|-------|-------|--------|
| 1 | arch.md + rules.md: строгое правило | docs/arch.md, docs/rules.md | — (доки), phpstan baseline зелёный | done |
| 2 | LocaleResolver + убрать LocaleConfig из 2 хендлеров | LocaleResolver, AppBootloader, SendLoginCodeHandler, CreateUserHandler, +4 теста-сайта, unit LocaleResolverTest, kernel AppBootloaderTest | phpstan OK, test 1263 OK | done |
| 3 | UserBootloader + убрать UserConfig из ассемблера | UserBootloader (new), Kernel, UserPublicProfileAssembler, UserApplicationTestCase, kernel UserBootloaderTest | phpstan OK, test 1264 OK | done |
| 4 | MediaUploadSettings + убрать MediaConfig из RequestMediaUploadHandler | MediaUploadSettings (new), MediaBootloader, RequestMediaUploadHandler, RequestMediaUploadHandlerTest, kernel MediaBootloaderTest | phpstan OK, test 1265 OK | done |
| 5 | presigned TTL default + удалить MediaUrlResolverFactory | media.php, MediaConfig, MediaUrlService (+resolverFor), MediaBootloader (+mediaUrlService), FindMediaUrlQuery/Handler, UserPublicProfileAssembler, PostViewAssembler, MediaFileServiceContract, README, .env.sample, phpunit.xml, удалён MediaUrlResolverFactory; тесты MediaConfigTest, 3 unit Infra, FindMediaUrlHandlerTest (+default/+0), UserApplicationTestCase, kernel MediaBootloaderTest | phpstan OK, test 1268 OK | done |
| 6 | Финальная проверка строгости правила | грепы (чистые) | make qa зелёный, покрытие 100% | done |

## Заметки

- Старт: ветка `main`, в рабочей копии незакоммиченные изменения предыдущей задачи (media URL-сервисы),
  на которые опирается план. План и код согласованы — исполняем поверх.
- Контекст прочитан: план, docs/rules.md, docs/arch.md, docs/code-examples.md, AGENTS.md.
- Конфликтов плана с rules.md/arch.md нет; фаза 1 сама делает правило строгим (решение пользователя).
- Подтверждено грепом: ровно 4 Application-нарушителя (UserPublicProfileAssembler, SendLoginCodeHandler,
  CreateUserHandler, RequestMediaUploadHandler).
- `domainCore` в Spiral `DomainBootloader` — `protected static`, фабрики бутлоадеров делаем по образцу.
- Pure-unit тесты расширяют `PHPUnit\Framework\TestCase`; Kernel-тесты — `Tests\TestCase`.

## Изменения в docs

- `docs/arch.md` (раздел про `TypedConfig`): «осознанное исключение» заменено строгим правилом —
  Domain и Application не зависят от `Shared/Infrastructure/Configuration` и не импортируют `*Config`;
  конфиг читает Infrastructure (бутлоадеры/инфра-сервисы/middleware) и отдаёт готовые значения через DI.
  Добавлена оговорка про Presentation-адаптеры (вне охвата).
- `docs/rules.md` («`env()` только в конфигах»): средняя фраза переформулирована — `*Config` читаются
  только в Infrastructure-коде, Domain и Application получают готовые значения через DI.
- Архитектурных решений «по ходу», расходящихся с зафиксированной архитектурой, не было — план уже
  делал правило строгим, доки приведены в соответствие в рамках фазы 1.

## Финальная проверка

- `make qa` (включает code style php-cs-fixer, PHPStan level max и один coverage-run PCOV со 100%-гейтом)
  — **зелёный**. Тесты: 1268, assertions: 4121. Покрытие: **100.00%** (порог 100%).
- В процессе `make qa` поймал стилевое правило (`?int` → `int|null`) в `MediaUrlService` и
  `FindMediaUrlQuery` — исправлено по сути (не подавление), повторный `make qa` зелёный.
- Проверочные грепы фазы 6 чистые: нет `use ...Configuration` в `Application`, нет ссылок на
  `MediaUrlResolverFactory`, `AVATAR_URL_TTL_SECONDS`, `POST_MEDIA_URL_TTL_SECONDS`, «TTL в конфиге нет».
  Два легитимных упоминания `Config` в Application (докблоки `MediaUploadSettings`, `MediaFileServiceContract`)
  — не импорты, описывают границу/реализацию.
- Поэтапно: phpstan+test зелёные после фаз 2–5 (1263→1264→1265→1268 тестов).
