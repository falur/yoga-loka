---
date: 2026-05-19 12:43
source: text
status: done
---

# Фикс: дублирование префикса OpenAPI routes

## Контекст

Swagger UI строил запрос `http://127.0.0.1:60080/api/v1/api/v1/health`, потому что OpenAPI YAML одновременно содержал `servers.url: /api/v1` и path `/api/v1/health`.

Учтены `docs/rules.md` и `docs/arch.md`: генератор OpenAPI остаётся в `tools/openapi`, приложение только отдаёт сгенерированный YAML через `/api/docs/openapi.yml`.

## Что изменено

| # | Файл | Что изменено | Зачем |
|---|------|--------------|-------|
| 1 | `tools/openapi/src/Spec/SpecBuilder.php` | При сборке `paths` route prefix удаляется из route path, если route уже начинается с `routePrefix`. | OpenAPI должен описывать `servers.url: /api/v1` + `paths./health`, чтобы Swagger вызывал `/api/v1/health`. |
| 2 | `tools/openapi/tests/Generator/OpenApiGeneratorTest.php` | Ожидания генератора обновлены на paths без `/api/v1` и добавлена проверка server URL. | Зафиксировать отсутствие двойного base path в генерируемой спецификации. |
| 3 | `tests/Feature/Endpoint/Api/OpenApiHttpTest.php` | HTTP-тест Swagger YAML теперь проверяет `url: /api/v1` и `/health:`. | Тест должен соответствовать корректной OpenAPI-модели server + relative path. |
| 4 | `public/openapi/openapi.yml` | Спецификация перегенерирована через `composer openapi:generate` в Docker. | Swagger UI читает актуальный YAML. |

## Тесты и проверки

| Команда | Результат | Заметки |
|---------|-----------|---------|
| `composer openapi:test -- --filter OpenApiGeneratorTest` | ✓ | 1 тест, 68 assertions. |
| `docker compose -f docker/docker-compose.dev.yml --env-file .env -p yoga-loka-spiral-2 exec -T app-http composer openapi:generate` | ✓ | Сгенерирована 1 operation и 3 schemas. |
| `composer openapi:test` | ✓ | 12 тестов, 141 assertion. |
| `curl -fsS http://127.0.0.1:60080/api/docs/openapi.yml` | ✓ | YAML содержит `servers.url: /api/v1` и `paths./health`. |
| `curl -fsS http://127.0.0.1:60080/api/v1/health` | ✓ | Возвращает `{"data":{"status":"ok"}}`. |
| `composer openapi:phpstan` | ✓ | No errors. |
| `docker compose -f docker/docker-compose.dev.yml --env-file .env -p yoga-loka-spiral-2 exec -T app-http composer phpstan` | ✓ | No errors. |
| `docker compose -f docker/docker-compose.dev.yml --env-file .env -p yoga-loka-spiral-2 exec -T app-http vendor/bin/phpunit tests/Feature/Endpoint/Api/OpenApiHttpTest.php` | ✓ | 5 тестов, 25 assertions. |
| `make test` | ✓ | PHPUnit: OK с 3 deprecations; PHPStan rules tests и OpenAPI tests прошли. |
| `vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php --dry-run --diff tools/openapi/src/Spec/SpecBuilder.php tools/openapi/tests/Generator/OpenApiGeneratorTest.php tests/Feature/Endpoint/Api/OpenApiHttpTest.php` | ✓ | Замечаний по изменённым PHP-файлам нет. |
| `docker compose -f docker/docker-compose.dev.yml --env-file .env -p yoga-loka-spiral-2 exec -T app-http composer test` | ✗ | Запуск в dev-контейнере использует dev env: cache `redis` вместо `local`, БД `yoga_loka` вместо `yoga_loka_test`; корректный профиль `make test` прошёл. |

## Открытые вопросы

Нет
