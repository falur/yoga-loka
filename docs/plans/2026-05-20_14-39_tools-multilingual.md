---
title: Мультиязычность tools-пакетов
date: 2026-05-20 14:39
mode: normal
plan_size: normal
decision_mode: recommend_and_ask
status: meta-reviewed
reviewer: none
meta_reviewers:
  - gpt-5.4-mini
  - gpt-5.3-codex
  - gpt-5.5
test_strategy: after_each_phase
logging_strategy: debug_precise
sources:
  rules: docs/rules.md
  arch: docs/arch.md
  research: docs/researches/2026-05-20_14-11_tools-multilingual.md
---

# План реализации

## Задача

Коротко: перевести пользовательские тексты локальных пакетов `tools/api-error` и `tools/openapi` через штатный переводчик Spiral.

Готовый результат: `tools/api-error` отдаёт свои JSON-сообщения на текущем языке Spiral translator, `tools/openapi` генерирует стандартные описания ответов OpenAPI на языке генерации, а `tools/phpstan` остаётся без изменений.

## Контекст

В проекте уже включены `Spiral\Bootloader\I18nBootloader` и `Spiral\Bootloader\Views\TranslatedCacheBootloader` в `app/src/Infrastructure/Framework/Kernel.php`. Конфиг `app/config/translator.php` берёт основной язык и fallback из `LOCALE`, по умолчанию это `en`.

`tools/api-error` сейчас владеет тремя пользовательскими сообщениями: `Маршрут не найден.`, `Ошибка валидации`, `Внутренняя ошибка сервера`. Эти сообщения возвращаются наружу в JSON-ответах 404, 422 и 500.

`tools/openapi` сейчас владеет двумя стандартными описаниями response в YAML: `Ошибка API.` и `Успешный ответ.`. YAML является статическим файлом, поэтому язык выбирается во время команды генерации, а не на каждый HTTP-запрос.

`tools/phpstan` не входит в реализацию. Пользователь подтвердил, что англоязычные сообщения PHPStan приемлемы для инструмента разработки.

Новые библиотеки не нужны. В проекте уже есть `spiral/framework` 3.16.2 и `spiral/translator`; исследование подтвердило, что отдельную зависимость или общий пакет переводов добавлять не нужно.

## Принятые решения

- Размер плана: `normal`. Источник: ответ пользователя `1`.
- Переводить только `tools/api-error` и `tools/openapi`. Источник: research с ответом пользователя.
- `tools/phpstan` не переводить. Источник: research с уточнением пользователя.
- Использовать только встроенный `Spiral\Translator\TranslatorInterface` и locale-каталоги Spiral. Источник: research с архитектурным уточнением пользователя.
- Не добавлять `tools/i18n`, общий переводчик, свои словари в коде или ручной выбор языка внутри tools-пакетов. Источник: research с ответом пользователя.
- Каталоги переводов хранить рядом с пакетами: `tools/api-error/locale/` и `tools/openapi/locale/`.
- Для каждого пакета добавить каталоги `en` и `ru` с доменом `messages`, потому что `LOCALE` по умолчанию равен `en`, а текущие русские тексты нужно сохранить для `ru`.
- Ключами переводов использовать стабильные имена с префиксом владельца и пакета, например `yoga_loka.api_error.route_not_found`. Это снижает риск пересечения с обычными пользовательскими строками.
- Конструкторы `tools/api-error` расширять обратимо: добавить необязательный `?TranslatorInterface $translator = null` после существующих аргументов, чтобы текущие `new ...(logger: ...)` в тестах и внешнем коде не сломались.
- В `tools/api-error` всё равно настроить DI через `ApiErrorBootloader`, чтобы приложение получало реальные экземпляры с `TranslatorInterface`, а не fallback.
- Конструктор `OpenApiGenerator` сохранить совместимым с текущим `new OpenApiGenerator()`: добавить необязательный переводчик и использовать английский fallback, если генератор создан вне Spiral DI.
- Новый `OpenApiToolsBootloader` должен явно зависеть от `I18nBootloader` и создавать `OpenApiGenerator` через factory с текущим `TranslatorInterface`.
- Эта задача не внедряет выбор языка пользователя по HTTP-заголовкам или профилю. `tools` читают тот locale, который приложение уже выставило в Spiral translator; если приложение ничего не выставило, используется `LOCALE`.
- Доменные сообщения приложения не переводить внутри `tools/api-error`: текст из `\DomainException` остаётся ответственностью приложения.
- Сообщения конкретных полей валидации из массива `$errors` не переводить внутри `tools/api-error`: пакет переводит только общую обёртку `Ошибка валидации`.
- Описания из `#[OpenApi(description: ...)]` и PHPDoc summary не переводить внутри `tools/openapi`: они принадлежат приложению.
- Логи и технические исключения генератора OpenAPI не переводить на английский: по правилам проекта логи и исключения остаются на русском.

## Целевой алгоритм

1. При старте приложения `I18nBootloader` настраивает Spiral translator.
2. Bootloader пакета `tools/api-error` через `init(I18nBootloader $i18n)` вызывает `$i18n->addDirectory(__DIR__ . '/../../locale')`.
3. `ApiErrorBootloader` через factory создаёт `RouteNotFoundMiddleware`, `ApiValidationErrorsRenderer` и `ApiExceptionInterceptor` с текущим `TranslatorInterface`.
4. Новый bootloader пакета `tools/openapi` объявляет зависимость от `I18nBootloader`, через `init(I18nBootloader $i18n)` добавляет каталог `tools/openapi/locale/` и через factory создаёт `OpenApiGenerator` с текущим `TranslatorInterface`.
5. Когда `RouteNotFoundMiddleware` формирует 404, оно переводит ключ `yoga_loka.api_error.route_not_found` через текущий locale переводчика.
6. Когда `ApiValidationErrorsRenderer` формирует 422, он переводит ключ `yoga_loka.api_error.validation_error` через текущий locale переводчика.
7. Когда `ApiExceptionInterceptor` формирует 500 для непредвиденной ошибки, он переводит ключ `yoga_loka.api_error.internal_server_error` через текущий locale переводчика.
8. Для доменных исключений `ApiExceptionInterceptor` по-прежнему возвращает сообщение самого исключения без перевода внутри пакета.
9. При запуске `openapi:generate` генератор получает текущий locale Spiral translator.
10. `SpecBuilder` переводит ключи `yoga_loka.openapi.successful_response` и `yoga_loka.openapi.api_error` и записывает переведённые описания в YAML.
11. Debug-логи остаются на русском и не содержат секретов, персональных данных, query-строк, тела запроса или пользовательских значений.

## Контракты реализации

### Данные и БД

Не затрагивается.

### API и внешние контракты

Новые маршруты не добавляются. Формат JSON-ответов не меняется.

Меняется язык сообщений, которыми владеет `tools/api-error`.

| Сценарий | Locale `en` | Locale `ru` |
|---|---|---|
| Ненайденный маршрут | `{"message":"Route not found.","code":404}` | `{"message":"Маршрут не найден.","code":404}` |
| Ошибка Spiral Filter | `{"message":"Validation error","code":422,"errors":[...]}` | `{"message":"Ошибка валидации","code":422,"errors":[...]}` |
| Непредвиденная ошибка API | `{"message":"Internal server error","code":500}` | `{"message":"Внутренняя ошибка сервера","code":500}` |

Доменные ошибки остаются без перевода внутри пакета:

| Сценарий | Поведение |
|---|---|
| `\DomainException` с поддерживаемым HTTP-кодом | В JSON попадает сообщение исключения |
| `\DomainException` без корректного HTTP-кода | В JSON попадает сообщение исключения и код 400 |

OpenAPI YAML меняет только стандартные описания response:

| Response | Locale `en` | Locale `ru` |
|---|---|---|
| Успешный ответ | `Successful response.` | `Успешный ответ.` |
| Ошибка API | `API error.` | `Ошибка API.` |

## Фазы выполнения

### 1. Подключить переводы в `tools/api-error`

Цель: сделать пользовательские сообщения `tools/api-error` зависимыми от текущего языка Spiral translator.

Что сделать:
- Добавить locale-каталоги `tools/api-error/locale/en/` и `tools/api-error/locale/ru/`.
- В файлах `messages.php` добавить ключи `yoga_loka.api_error.route_not_found`, `yoga_loka.api_error.validation_error`, `yoga_loka.api_error.internal_server_error`.
- В `ApiErrorBootloader` добавить `init(I18nBootloader $i18n): void` и вызвать `$i18n->addDirectory(__DIR__ . '/../../locale')`.
- В `ApiErrorBootloader` добавить factory-привязки для `RouteNotFoundMiddleware`, `ApiValidationErrorsRenderer` и `ApiExceptionInterceptor`, чтобы DI передавал `Spiral\Translator\TranslatorInterface`.
- В `RouteNotFoundMiddleware`, `ApiValidationErrorsRenderer` и `ApiExceptionInterceptor` добавить необязательный `?TranslatorInterface $translator = null` после существующих аргументов.
- Заменить захардкоженные пользовательские сообщения пакета на приватный метод перевода: при наличии `$translator` вызвать `$translator->trans(...)`, при `null` вернуть английский ключ как готовый fallback-текст.
- Оставить debug-, warning- и error-логи на русском.
- Оставить сообщения доменных исключений без перевода.
- Оставить field-level сообщения Filter-валидации без перевода внутри пакета.
- Обновить unit-тесты `tools/api-error`: передавать тестовый переводчик и проверять английские и русские сообщения.
- Обновить feature-тесты приложения: проверить ответы `tools/api-error` при `locale=en` и `locale=ru`.
- В ru feature-тестах явно вызвать `$this->getContainer()->get(TranslatorInterface::class)->setLocale('ru')` после `setUp`, потому что базовый `tests/TestCase.php` сбрасывает locale в `en`.
- Для 500 добавить unit-проверку `ApiExceptionInterceptor`, которая доказывает, что наружу уходит переведённое общее сообщение, а внутреннее сообщение исключения не раскрывается.
- Проверить, что `tools/api-error/src` по-прежнему не содержит ссылок на `App\`.

Результат: `tools/api-error` отдаёт свои собственные пользовательские сообщения на текущем языке переводчика.

Сценарии тестирования:
- При locale `en` ненайденный route возвращает `Route not found.`.
- При locale `ru` ненайденный route возвращает `Маршрут не найден.`.
- При locale `en` ошибка Filter-валидации возвращает `Validation error`, но сообщения конкретных полей остаются теми, что пришли от Spiral validator.
- При locale `ru` ошибка Filter-валидации возвращает `Ошибка валидации`.
- При locale `en` непредвиденная ошибка возвращает `Internal server error` и не раскрывает внутреннее сообщение исключения.
- При locale `ru` непредвиденная ошибка возвращает `Внутренняя ошибка сервера`.
- Доменная ошибка `Тестовый ресурс не найден.` остаётся с сообщением исключения.
- Debug-логи не содержат query-строку, тело запроса, cookie, authorization headers или пользовательские значения.

Проверка:
- `composer -d tools/api-error test`
- `composer -d tools/api-error phpstan`
- `composer test -- --filter ApiErrorHttpTest`
- `composer phpstan`

### 2. Подключить переводы в `tools/openapi`

Цель: сделать стандартные описания response в OpenAPI YAML зависимыми от языка генерации.

Что сделать:
- Добавить locale-каталоги `tools/openapi/locale/en/` и `tools/openapi/locale/ru/`.
- В файлах `messages.php` добавить ключи `yoga_loka.openapi.successful_response` и `yoga_loka.openapi.api_error`.
- Добавить bootloader пакета `Tools\OpenApi\Bootloader\OpenApiToolsBootloader`.
- В bootloader объявить зависимость от `I18nBootloader`.
- В bootloader добавить `init(I18nBootloader $i18n): void` и вызвать `$i18n->addDirectory(__DIR__ . '/../../locale')`.
- В bootloader настроить factory для создания `OpenApiGenerator` с текущим `Spiral\Translator\TranslatorInterface`.
- Зарегистрировать `OpenApiToolsBootloader` в `app/src/Infrastructure/Framework/Kernel.php` рядом с интернационализацией и до bootloader-а консольной команды OpenAPI.
- Добавить в `OpenApiGenerator` необязательный `?TranslatorInterface $translator = null`, не ломая текущий `new OpenApiGenerator()`.
- Передать `TranslatorInterface` из `OpenApiGenerator` в `SpecBuilder`.
- В `SpecBuilder` заменить строки `Ошибка API.` и `Успешный ответ.` на переводы по ключам.
- Если переводчик не передан, `SpecBuilder` возвращает английский ключ как готовый fallback-текст.
- Сохранить возможность запускать unit-тесты `tools/openapi` без полного приложения: в тестах передавать тестовый переводчик явно для ru-сценария и проверять fallback для `new OpenApiGenerator()`.
- Обновить `tools/openapi/tests/Generator/OpenApiGeneratorTest.php`: проверить английские описания по умолчанию и русские описания при locale `ru`.
- Добавить интеграционный тест команды `openapi:generate`, чтобы доказать, что генератор в приложении использует текущий Spiral translator.
- В интеграционном тесте команды явно выставить `TranslatorInterface::setLocale('ru')` после boot.
- В интеграционном тесте команды использовать `#[Config('openapi.outputFile', 'runtime/openapi-i18n-test.yml')]`, чтобы тест не перезаписывал `public/openapi/openapi.yml`.
- В интеграционном тесте команды прочитать сгенерированный YAML и проверить, что в нём есть `Успешный ответ.` и `Ошибка API.`.
- Не переводить описания из атрибутов, PHPDoc и технические исключения генератора.

Результат: статический OpenAPI YAML получает стандартные описания response на языке текущего locale во время генерации.

Сценарии тестирования:
- При locale `en` в YAML есть `Successful response.` и `API error.`.
- При locale `ru` в YAML есть `Успешный ответ.` и `Ошибка API.`.
- `new OpenApiGenerator()` без DI продолжает работать и отдаёт английские описания.
- Команда `openapi:generate` в приложении при locale `ru` пишет русские стандартные описания в тестовый YAML-файл внутри `runtime/`.
- Описания из `#[OpenApi(description: ...)]` и PHPDoc summary остаются как в коде приложения.
- Генерация YAML по-прежнему находит те же операции и схемы.
- Пакет `tools/openapi` по-прежнему не зависит от namespace `App\`.

Проверка:
- `composer -d tools/openapi test`
- `composer -d tools/openapi phpstan`
- `composer test -- --filter OpenApiHttpTest`
- `composer phpstan`

### 3. Обновить документацию и выполнить общую проверку

Цель: зафиксировать новую границу мультиязычности и проверить, что изменение не сломало приложение.

Что сделать:
- Обновить `tools/api-error/README.md`: описать зависимость от Spiral translator, каталог переводов и список сообщений, которыми владеет пакет.
- Обновить `tools/openapi/README.md`: описать, что стандартные описания response переводятся на языке генерации YAML.
- Обновить `docs/arch.md`: уточнить, что `tools/api-error` и `tools/openapi` используют Spiral translator, а выбор языка пользователя остаётся задачей request-слоя приложения.
- Обновить упоминания старых русских сообщений в документации, если они теперь зависят от locale.
- Проверить, что `tools/phpstan` и его тесты не менялись.
- Перегенерировать `public/openapi/openapi.yml` после изменения генератора и проверить, что diff содержит только ожидаемые описания response.

Результат: документация совпадает с новым поведением, а все затронутые пакеты и приложение проходят проверки.

Сценарии тестирования:
- Документация не обещает всегда русские сообщения для текстов, которые теперь зависят от locale.
- Полный набор тестов приложения проходит.
- Статический анализ проходит для приложения и двух затронутых tools-пакетов.
- Проверка стиля проходит.

Проверка:
- `composer -d tools/api-error test`
- `composer -d tools/openapi test`
- `composer test`
- `composer -d tools/api-error phpstan`
- `composer -d tools/openapi phpstan`
- `composer phpstan`
- `composer cs`

## Тесты

Стратегия: `after_each_phase`.

После каждой фазы нужно писать или обновлять тесты для изменённого поведения и сразу запускать проверки этой фазы. В конце нужно запустить тесты приложения и статический анализ для приложения и двух затронутых packages.

Ключевые сценарии:
- `tools/api-error` возвращает разные сообщения для `en` и `ru`.
- `tools/api-error` не переводит доменные сообщения приложения.
- `tools/api-error` не раскрывает внутреннее сообщение непредвиденного исключения.
- ru feature-тесты вручную выставляют locale после базового `setUp`.
- `tools/openapi` генерирует разные стандартные описания response для `en` и `ru`.
- command-тест `openapi:generate` пишет YAML в `runtime/`, а не в `public/openapi/openapi.yml`.
- `tools/openapi` не переводит описания, которые пришли из кода приложения.
- `tools/phpstan` не затронут.

## Логирование

Стратегия: `debug_precise`.

Новые debug-логи не добавлять. При изменении существующего кода нужно сохранить текущие debug-, warning- и error-логи на русском.

В логах нельзя добавлять:
- пользовательский ввод;
- тело запроса;
- query-параметры;
- cookie;
- authorization headers;
- секреты и персональные данные.

Для OpenAPI-генерации существующие debug-сообщения остаются на русском и продолжают показывать старт генерации, число найденных файлов, число операций, число schemas и путь результата.

## Документация и эксплуатация

Обновить `tools/api-error/README.md`, `tools/openapi/README.md` и `docs/arch.md`.

Новые переменные окружения не нужны. Используется существующая `LOCALE`.

Миграции и изменения БД не нужны.

При релизе важно помнить: OpenAPI YAML является статическим файлом. Если нужен YAML на другом языке, его нужно сгенерировать при заранее выставленном нужном locale переводчика.

## Изменения после мета-ревью

### После моделей
- **+ Добавлено:** точное подключение каталогов через `init(I18nBootloader $i18n)` и `$i18n->addDirectory(...)`.
- **+ Добавлено:** factory-привязки в bootloader-ах, чтобы приложение получало экземпляры с `TranslatorInterface`.
- **+ Добавлено:** обратимая совместимость конструкторов `tools/api-error` и `OpenApiGenerator` через необязательный переводчик и английский fallback.
- **+ Добавлено:** явное ручное переключение locale в ru feature- и command-тестах после базового `setUp`.
- **+ Добавлено:** безопасный output-файл `runtime/openapi-i18n-test.yml` для интеграционного теста `openapi:generate`.
- **~ Изменено после уточнения пользователя:** ключи переводов стали стабильными именами вида `yoga_loka.<package>.<message>`, чтобы они не пересекались с обычными пользовательскими строками.
- **~ Изменено:** план явно отделяет чтение текущего locale от выбора языка пользователя на уровне HTTP-запроса; такой выбор языка не входит в эту реализацию.
- **Отклонено:** не добавлять перевод технических `OpenApiException` и CLI-ошибок генератора, потому что research ограничил перевод стандартными описаниями response.
- **Отклонено:** не добавлять отдельный тест locale `de`, потому что Spiral `setLocale()` требует существующий locale, а задача фиксирует только `en` и `ru`.

## Прогресс выполнения
Журнал: `docs/executions/2026-05-20_16-23_tools-multilingual.md`

- [x] Шаг 1: Подключить переводы в `tools/api-error`
- [x] Шаг 2: Подключить переводы в `tools/openapi`
- [x] Шаг 3: Обновить документацию и выполнить общую проверку
