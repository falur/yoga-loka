# Кросс-CLI мета-ревью (codex) — round 4

Соседний CLI: codex (codex-cli 0.139.0). Strict-режим задан пользователем.
Файл логов очищен, ниже сохранён только итоговый вывод codex.

+ Добавить в ревью пропущенное замечание по `rules.md`: правило «Конкретные имена переменных»
  прямо запрещает `$handler` и `$result`.
- `tests/Feature/Modules/Auth/Application/RequestLoginCodeHandlerTest.php:38` — `$handler`
  (строки 40-41 используют это имя).
- `tests/Feature/Modules/Auth/Application/LoginCodeVerificationTest.php:26` — `$result`
  (также 38 и 45).
- `tests/Feature/Modules/User/Application/CreateUserHandlerTest.php:30` — `$result` (также 51).
  Severity: `править обязательно`, риск низкий — это не runtime-баг, а нарушение обязательного
  правила проекта в новых тестах. PHPStan не поймает: тесты не сканируются.

− Убрать/считать неверным утверждение ревью «ни одного must-fix нет»: после подтверждённого
  нарушения правила имён must-fix есть.

− Не возвращать старые отклонённые пункты — новых фактов нет:
  - `ApiValidationErrorsRenderer` всё ещё на границе vendor-контракта `array<string,string>`
    (`packages/spiral-api-errors/src/Filter/ApiValidationErrorsRenderer.php:19`; список сообщений
    сужается локально в `firstMessage()` на 44-49).
  - `RegisterFilter` осознанно строже без полной нормализации VO
    (`app/src/Modules/Auth/Presentation/Http/Filter/RegisterFilter.php:17`).

~ Переформулировать nullable-замечание: по фактам верно, но уточнить достижимость 3.0-ветки.
  `NullableSchema` выбирает ветку по версии (`NullableSchema.php:26`, 3.0-ветка 60-68),
  `SpecBuilder` передаёт `OpenApiGeneratorConfig::openApiVersion` (`SpecBuilder.php:29`). Но из
  приложения 3.0 недостижима: `OpenApiConfig` (`OpenApiConfig.php:22`) не имеет поля версии и
  `toGeneratorConfig()` не передаёт `openApiVersion`; `app/config/openapi.php:5` версию не задаёт.
  Риск касается пакета `spiral-openapi` как переиспользуемого генератора, а не текущего приложения.

~ Severity nullable-пункта корректная: `на усмотрение автора`. Текущий YAML уже 3.1
  (`public/openapi/openapi.yml:1`), `VerifyResultResource` выражен через `oneOf` и
  `type: [string, null]` (154-157), ключей `nullable` нет.

~ Score `96` завышен из-за пропущенного rules must-fix. При текущих фактах ~`94-95`: один
  обязательный неповеденческий пункт по именам переменных + один optional по OpenAPI 3.0.

? Полную зелёность `make test` / `make phpstan` не подтверждаю: в мета-проверке команды не
  запускались.
