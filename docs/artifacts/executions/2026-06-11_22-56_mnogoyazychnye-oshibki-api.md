---
plan: docs/plans/2026-06-11_22-30_mnogoyazychnye-oshibki-api.md
started: 2026-06-11 22:56
finished: 2026-06-11 23:40
status: done
---

# Журнал: Многоязычные ошибки API — перевод на границе + выбор языка по Accept-Language

## Шаги
| # | Шаг | Файлы | Тесты | Статус |
|---|-----|-------|-------|--------|
| 1 | Фаза 1: интерфейс `TranslatableException` + перевод 4xx на границе | `packages/spiral-api-errors/src/Exception/TranslatableException.php` (новый), `.../Interceptor/ApiExceptionInterceptor.php`, `.../tests/Interceptor/ApiExceptionInterceptorTest.php` | `composer -d packages/spiral-api-errors test` — 23 теста зелёные; `phpstan` — OK; `PackagePortabilityTest` зелёный | ✅ |
| 2 | Фаза 2: абстрактный `DomainTranslatableException`, 4 подкласса, миграция 23 точек выброса, каталоги `ru`/`en`, тесты | `app/src/Shared/Domain/Exception/{DomainTranslatableException(новый),NotFoundException,ForbiddenException,ValidationException,AuthenticationException}.php`, 8 хендлеров + `MediaTypeResolver` + `SwaggerController`, `app/locale/{ru,en}/messages.php` (новые), `tests/Unit/Shared/Domain/Exception/ApiDomainExceptionTest.php`, `tests/App/.../ApiErrorTestController.php`, `tests/Feature/.../ApiErrorHttpTest.php`, `tests/Feature/.../OpenApiHttpTest.php` | `make phpstan` — OK; `make test` — 405 тестов зелёные; grep-контроль миграции — русских строк в аргументах 4xx нет | ✅ |
| 3 | Фаза 3: `LocaleConfig`, `app/config/locale.php`, `LocaleMiddleware`, регистрация на index 1, фикс `translator.php` fallback, env, тесты на `Accept-Language` | `app/config/locale.php` (новый), `app/config/translator.php`, `app/src/Shared/Infrastructure/Configuration/Locale/LocaleConfig.php` (новый), `app/src/Shared/Infrastructure/Framework/Middleware/LocaleMiddleware.php` (новый), `RoutesBootloader.php`, `.env`/`.env.sample`, `tests/Kernel/.../ConfigShapeTest.php`, `tests/Kernel/.../LocaleConfigTest.php` (новый), `tests/Unit/.../Middleware/LocaleMiddlewareTest.php` (новый), `ApiErrorHttpTest.php`, `OpenApiHttpTest.php` | `make phpstan` — OK; `make test` — 416 тестов зелёные (+11 новых) | ✅ |
| 4 | Фаза 4: сквозной интеграционный тест локали (en/ru/fallback + параметризованный роут), queue-контекст, финальный гейт | `tests/App/.../ApiErrorTestController.php` (+`parametrizedDomain`), `tests/App/Bootloader/ApiErrorTestRoutesBootloader.php` (+роут), `tests/Feature/.../LocaleHttpTest.php` (новый, 5 кейсов), `tests/Feature/Modules/Media/Flow/ProcessMediaJobTest.php` (+queue-тест), `tests/Unit/.../LocaleMiddlewareTest.php` (+кейс `en;q=0` для покрытия `continue`) | `make qa` — cs + phpstan + **coverage 100.00%** зелёные (423 теста); пакет `spiral-api-errors` — 23 теста + phpstan OK | ✅ |
| 5 | Дополнение по запросу пользователя: разбиение переводов на доменные файлы по модулям | пакет: `TranslatableException` (+`translationDomain()`), `ApiExceptionInterceptor` (передаёт `domain`), `tests/Support/FakeTranslator.php` (+`lastDomain`), `tests/Interceptor/ApiExceptionInterceptorTest.php`; app: `DomainTranslatableException` (+`translationDomain()` — авто-вывод из ключа), `app/locale/{ru,en}/{media,system}.php` (новые, вместо `messages.php`), `ApiDomainExceptionTest.php`, `LocaleHttpTest.php` | `make qa` — **coverage 100.00%** (424 теста); пакет — 24 теста + phpstan OK | ✅ |
| 6 | Дополнение по запросу пользователя: сделать основной язык русским (`LOCALE=ru`) | `.env`, `.env.sample` (`LOCALE=en` → `ru`), `tests/Kernel/.../LocaleConfigTest.php` (default → `ru`), `tests/Kernel/.../SimpleConfigBindingTest.php` (translator locale + Spiral `getDefaultLocale` → `ru`) | `make qa` — **coverage 100.00%** (424 теста), cs + phpstan OK | ✅ |
| 7 | Дополнение по запросу пользователя: заменить кальку «конверсия» на «преобразование» в русских сообщениях | `app/locale/ru/media.php` (`conversion_not_found`, `conversion_dimensions_out_of_range`) | `php -l` чисто; правка только русских строк перевода — тестами/PHPStan/покрытием не затрагивается (locale вне `pcov.directory=app/src`), доменная терминология кода/README не менялась | ✅ |

## Заметки

- Локальный PHP 8.4, проект требует 8.5 → все проверки запускаются через Docker (`docker compose ... run --rm --no-deps app-http ...`).
- Пакет `spiral-api-errors` подключён в `vendor/` приложения симлинком — приложение сразу видит изменения пакета.
- Полный прогон `make test`/`make phpstan` приложения отложен на фазу 2: в фазе 1 `app/src` не менялся, поведение приложения не изменилось (исключения интерфейс пока не реализуют).
- При первой записи `TranslatableException.php` через Write в файл попала мусорная строка `</content>` — перезаписан корректно, синтаксис проверен.
- **Отклонение от плана (согласовано с пользователем):** вместо 4 самостоятельных классов с дублированием введён абстрактный `DomainTranslatableException implements TranslatableException` (конструктор + `translationKey()`/`translationParameters()`), подклассы реализуют только `abstract protected statusCode(): int`. `InvalidDomainValueException` (500) в иерархию не входит. Подклассы расширяют `\DomainException` транзитивно — интерсептор по-прежнему их ловит.
- Media handler/resolver-тесты ассертят только тип через `expectException(...::class)` без текста — миграция их не ломает, правок не требуют.
- **Предсуществующий нотис (не связан с задачей, не блокирует гейт):** `ProcessMediaHandlerTest::testStoresConversionDimensionsFromProcessorResult` триггерит PHPUnit Notice «No expectations were configured for the mock object for MediaFileServiceContract» — мок без expectations (rules.md:106 советует stub). Файл теста мной не менялся, `make test` зелёный (exit 0). Вынести в отдельную задачу.
- Правило `RequireNamedArgumentsRule` исключает только **variadic** вызовы (`sprintf` — variadic, исключён), а `implode(separator, array)` — нет. Конвенция кодовой базы: `\implode(separator: ..., array: ...)` с именованными аргументами (как в `ConfigMappingException`/`OutboxQueueHeaders`). `LocaleConfig` приведён к ней.
- `Spiral\Translator\Translator` — `final` и `#[Singleton]`; `TranslatorInterface::class => Translator::class`. Один инстанс делится между `LocaleMiddleware` (конкретный `Translator`) и интерсептором (через `TranslatorInterface`), поэтому `setLocale` виден переводу ошибок. Для unit-теста middleware (final нельзя мокать) собран реальный `Translator` со stub `CatalogueManagerInterface(has()=true)`.

## Изменения в docs

- **Кандидат для `eda-docs`:** `arch.md` (раздел «API-ошибки») пишет «Общие доменные исключения… явно расширяют `\DomainException`». После рефакторинга 4 translatable-исключения расширяют `\DomainException` транзитивно через `DomainTranslatableException`. Формулировку стоит уточнить. — **Внесено** минимальное уточнение в `arch.md` (см. ниже).
- **Из плана (раздел «Документация»), делегировано `eda-docs`:** правило-задел про переводимые сообщения Spiral Filter-валидаторов (домен `spiral-packages/symfony-validator`). В этой задаче Filter-ов с `#[Assert\...]` нет — правило вне кода плана, не вносилось.

## Финальная проверка

- Сводка отражает состояние после всех шагов (включая 5–6): `make qa` (cs + phpstan + один coverage-run PCOV): **зелёный**. Покрытие **100.00%** при пороге 100%. Тесты: 424.
- Пакет `spiral-api-errors`: `composer test` — 24 теста зелёные, `composer phpstan` — No errors.
- **PHPUnit Notices: 1** — предсуществующий нотис в `ProcessMediaHandlerTest::testStoresConversionDimensionsFromProcessorResult` (мок `MediaFileServiceContract` без expectations). Не связан с задачей, файл теста не менялся, гейт `make qa` зелёный (exit 0). Рекомендуется отдельная задача (перевести мок на stub по rules.md:106).
- Состояние git: изменения **не закоммичены**.
