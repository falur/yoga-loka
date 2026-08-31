---
title: Мультиязычность пакетов tools
date: 2026-05-20 14:11
mode: normal
decision_mode: recommend_and_ask
status: draft
reviewer: none
sources:
  rules: docs/rules.md
  arch: docs/arch.md
---

# Мультиязычность пакетов tools

## Суть

Исследовали, как перевести локальные Composer-пакеты из `tools/` на мультиязычность: `tools/openapi`, `tools/api-error` и `tools/phpstan`. Эти пакеты подключены к корневому приложению через path repositories (`composer.json:57`) и используются как отдельные переносимые библиотеки (`docs/arch.md:157`, `docs/arch.md:166`, `docs/rules.md:90`).

Проблема сейчас в том, что пользовательские тексты внутри части `tools` зашиты прямо в коде. Это видно в HTTP-ошибках `tools/api-error` (`tools/api-error/src/Middleware/RouteNotFoundMiddleware.php:18`, `tools/api-error/src/Filter/ApiValidationErrorsRenderer.php:30`) и описаниях OpenAPI по умолчанию (`tools/openapi/src/Spec/SpecBuilder.php:138`, `tools/openapi/src/Spec/SpecBuilder.php:168`). Сообщения PHPStan-правил тоже зашиты в коде (`tools/phpstan/src/Rules/RequireStrictTypesRule.php:21`, `tools/phpstan/src/TypeContracts/TypeContractInspector.php:46`), но пользователь уточнил, что это приемлемо: PHPStan — dev-инструмент, и англоязычные diagnostics там считаются стандартом. Правила проекта требуют писать ошибки для пользователя на языке пользователя, а логи и исключения сейчас должны быть на русском (`docs/rules.md:6`, `docs/rules.md:7`, `docs/rules.md:9`).

Критерий готовности исследования: выбрать подход для `tools`-пакетов с пользовательскими текстами, понять границы архитектуры, не добавить лишнюю общую библиотеку, зафиксировать риски и дать результат, который можно передать в `eda-plan`.

## Решение

Выбранный вариант: перевести только внешние тексты `tools/api-error` и `tools/openapi` через встроенные средства Spiral, а `tools/phpstan` не переводить. Сначала пользователь выбрал вариант без общего слоя, затем уточнил, что PHPStan можно убрать из будущего плана, потому что англоязычные сообщения ошибок для PHPStan приемлемы и по сути являются стандартом. Отдельный пакет вроде `tools/i18n`, общий `Tools\I18n\Translator`, внутренние словари и ручной выбор языка в `tools` не вводятся.

Итоговый список строк, которые входят в перевод:

| Пакет | Строка | Где используется | Источник |
|---|---|---|---|
| `tools/api-error` | `Маршрут не найден.` | JSON 404 для ненайденного HTTP-маршрута | `tools/api-error/src/Middleware/RouteNotFoundMiddleware.php:18` |
| `tools/api-error` | `Ошибка валидации` | JSON 422-обёртка для ошибок Spiral Filter | `tools/api-error/src/Filter/ApiValidationErrorsRenderer.php:30` |
| `tools/api-error` | `Внутренняя ошибка сервера` | JSON 500 для непредвиденной ошибки API | `tools/api-error/src/Interceptor/ApiExceptionInterceptor.php:19` |
| `tools/openapi` | `Ошибка API.` | Описание default error response в сгенерированном OpenAPI YAML | `tools/openapi/src/Spec/SpecBuilder.php:138` |
| `tools/openapi` | `Успешный ответ.` | Описание success response в сгенерированном OpenAPI YAML | `tools/openapi/src/Spec/SpecBuilder.php:168`, `tools/openapi/src/Spec/SpecBuilder.php:188` |

Что не входит в перевод:

| Не переводим | Почему |
|---|---|
| Логи `tools/api-error` и `tools/openapi` | Это внутренние сообщения для разработчика/сервера; правила проекта сейчас требуют логи на русском (`docs/rules.md:7`) |
| Технические исключения генератора OpenAPI, например `Не удалось разобрать PHP-файл` | Это ошибки CLI/dev-инструмента, а не пользовательский API-текст |
| `#[OpenApi(description: ...)]` и PHPDoc summary | Эти тексты принадлежат приложению, а не пакету `tools/openapi` (`tools/openapi/src/Parser/PhpAstParser.php:202`, `tools/openapi/src/Parser/PhpAstParser.php:377`) |
| PHPStan-сообщения `tools/phpstan` и `tools/openapi/src/PHPStan` | Пользователь уточнил, что англоязычные PHPStan diagnostics приемлемы |

Архитектурная граница такая:

```text
Приложение YogaLoka
  -> задаёт текущий язык через свою конфигурацию или request-слой
  -> вызывает локальные пакеты tools
      -> tools/api-error переводит только свои JSON-сообщения API-ошибок
      -> tools/openapi переводит только дефолтные описания response в YAML
      -> tools/phpstan оставляет диагностические сообщения на английском
```

Пакеты не должны переводить доменную модель приложения. Например, `tools/api-error` может перевести собственные сообщения `Маршрут не найден.`, `Ошибка валидации`, `Внутренняя ошибка сервера`, но произвольный текст из `\DomainException` остаётся ответственностью приложения, потому что доменные исключения живут в `App\Domain\Exception`, а не в `tools/api-error` (`docs/arch.md:171`, `docs/plans/2026-05-19_17-11_api-error-handling-tools-package.md:57`).

| Пакет | Что переводить | Как переводить без общего слоя | Что не переводить внутри пакета |
|---|---|---|---|
| `tools/api-error` | Только JSON-сообщения, которые пакет сам отдаёт наружу: 404, 422 и 500 | Использовать `Spiral\Translator\TranslatorInterface`, который Spiral регистрирует в `I18nBootloader` (`vendor/spiral/framework/src/Framework/Bootloader/I18nBootloader.php:34`). Каталоги пакета подключить к Spiral через `I18nBootloader::addDirectory()` (`vendor/spiral/framework/src/Framework/Bootloader/I18nBootloader.php:84`) | Логи пакета, тексты доменных исключений приложения, field-level сообщения валидации из `$errors` |
| `tools/openapi` | Только дефолтные описания response в YAML: `Ошибка API.` и `Успешный ответ.` | Использовать тот же `Spiral\Translator\TranslatorInterface` и locale-каталоги Spiral. Генерация YAML берёт текущий locale из Spiral translator, который уже настроен через `LOCALE` (`app/config/translator.php:11`) | Debug-логи, технические исключения генератора, описания из `#[OpenApi(description: ...)]` и PHPDoc summary |
| `tools/phpstan` | Не переводить | Оставить англоязычные сообщения правил как developer-facing diagnostics. Пакет запускается как dev-инструмент через `extension.neon`, а не через HTTP runtime (`tools/phpstan/extension.neon:5`, `phpstan.neon:1`) | Идентификаторы ошибок точно не менять (`tools/phpstan/src/Rules/RequireStrictTypesRule.php:22`, `tools/phpstan/src/Rules/RequireNamedArgumentsRule.php:31`) |

`tools/api-error` отличается от остальных, потому что работает внутри Spiral runtime. В проекте уже включены `I18nBootloader` и `TranslatedCacheBootloader` (`app/src/Infrastructure/Framework/Kernel.php:126`), а конфиг translator берёт язык и fallback из `LOCALE` (`app/config/translator.php:11`). Поэтому `api-error` не должен придумывать свой runtime-переводчик. Он должен пользоваться уже привязанным контрактом переводчика и только добавить свои файлы переводов. Риск здесь в том, что если приложение не выставит locale перед обработкой запроса, будет использован конфиг `LOCALE`; тесты сейчас явно сбрасывают translator в `en` (`tests/TestCase.php:55`). Этот риск закрывается границей задачи: `tools` должны читать текущий язык, а выбор языка пользователя остаётся отдельной задачей HTTP-слоя приложения.

`tools/openapi` нужно переводить на языке генерации, а не на языке каждого запроса. OpenAPI YAML записывается в статический файл (`docs/arch.md:160`, `app/src/Endpoint/Console/OpenApiGenerateCommand.php:31`). Поэтому выбранный язык должен браться из текущего locale Spiral translator. Если позже понадобятся две спецификации на разных языках, их нужно генерировать как два отдельных файла при разных значениях locale, а не пытаться менять один YAML на лету.

`tools/phpstan` остаётся без мультиязычности. PHPStan подключает правила через `extension.neon` (`tools/phpstan/extension.neon:5`), а корневой проект включает этот extension в `phpstan.neon` (`phpstan.neon:1`). Это developer-facing diagnostics, поэтому англоязычный текст не нарушает выбранную цель мультиязычности пользовательских API-ошибок и OpenAPI-текстов. Идентификаторы ошибок менять нельзя, иначе сломаются baseline, ignore rules и документация по правилам (`tools/phpstan/src/Rules/RequireStrictTypesRule.php:22`, `tools/phpstan/src/TypeContracts/TypeContractViolation.php:9`).

Проверка версий и зависимостей на 2026-05-20:

| Пакет или компонент | Текущая версия в проекте | Актуальная стабильная версия по источнику | Решение |
|---|---:|---:|---|
| `spiral/framework` | 3.16.2 (`composer.lock:5897`) | 3.16.2, Packagist: https://packagist.org/packages/spiral/framework | Не обновлять, уже содержит `spiral/translator` как replace (`composer.lock:5988`) |
| `spiral/translator` | отдельного lock-записа нет, используется через `spiral/framework` replace (`composer.lock:5988`) | 3.16.2 по Composer metadata, Packagist: https://packagist.org/packages/spiral/translator | Не добавлять отдельную зависимость |
| `symfony/translation` | v8.0.10 (`composer.lock:9867`) | v8.0.10 stable, Packagist также показывает 8.1.0-BETA1 как prerelease: https://packagist.org/packages/symfony/translation | Не обновлять и не выбирать beta |
| `phpstan/phpstan` | 2.1.54 (`composer.lock:11215`) | 2.1.55, Packagist: https://packagist.org/packages/phpstan/phpstan | Обновление и перевод сообщений PHPStan не входят в решение |

Новые внешние библиотеки не нужны. Для `api-error` и `openapi` уже доступен контракт переводчика через Spiral (`vendor/spiral/framework/src/Framework/Bootloader/I18nBootloader.php:35`). Основной риск этого выбора — `tools/openapi` становится сильнее завязан на Spiral runtime при генерации YAML. Риск принимается осознанно: пакет уже требует `spiral/framework` (`tools/openapi/composer.json:11`), а пользователь явно выбрал встроенные средства Spiral для всех переводов.

## Ответы на вопросы

Вопрос: какой объём мультиязычности зафиксировать?

Варианты:

1. Все `tools` через общий слой переводов.
2. Все `tools`, но без общего слоя.
3. Только API.

Ответ пользователя: вариант 2.

Дополнительное уточнение пользователя: сообщения ошибок PHPStan на английском приемлемы, потому что это стандарт для PHPStan. Решение: убрать `tools/phpstan` из будущего плана мультиязычности и оставить его diagnostics на английском.

Дополнительное архитектурное уточнение пользователя: все переводы нужно делать через встроенные средства Spiral. Решение: не использовать внутренние словари и ручные переводчики в `tools`; `tools/api-error` и `tools/openapi` должны брать переводы через `Spiral\Translator\TranslatorInterface` и locale-каталоги Spiral.

## Итог

Дальше нужно планировать перевод двух `tools`-пакетов через встроенный Spiral translator: `api-error` подключает свои каталоги к Spiral translator, `openapi` тоже использует Spiral translator при генерации YAML. `tools/phpstan` не входит в план мультиязычности. В каждом переводимом пакете переводятся только тексты, которыми владеет сам пакет; доменные сообщения приложения, OpenAPI-описания из кода приложения и стабильные PHPStan identifiers не меняются.

Выбранный вариант одной строкой: мультиязычными становятся `tools/api-error` и `tools/openapi`, оба через встроенный Spiral translator; `tools/phpstan` остаётся с англоязычными diagnostics.
