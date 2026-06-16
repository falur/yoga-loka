---
plan: docs/plans/2026-06-16_13-41_empty-success-response-204.md
started: 2026-06-16 14:21
finished: 2026-06-16 14:21
status: done
---

# Журнал: EmptySuccessResponse и 204 No Content для командных эндпоинтов Auth

## Шаги
| # | Шаг | Файлы | Тесты | Статус |
|---|-----|-------|-------|--------|
| 1 | Класс `EmptySuccessResponse`, поле `emptyResponseClass` в маппинге, call-sites теста, README | `src/Response/EmptySuccessResponse.php` (new), `src/Config/ResponseWrapperMapping.php`, `tests/Generator/OpenApiGeneratorTest.php`, `README.md`, `tests/Response/EmptySuccessResponseTest.php` (new) | пакет phpstan ✓, пакет test 28 ✓ | done |
| 2 | Ветка 204 в `SpecBuilder` (метод `successResponses` + debug-лог), fixture `CommandController`, тест генератора | `src/Spec/SpecBuilder.php`, `tests/Fixtures/Endpoint/NoContent/Api/V1/Controller/CommandController.php` (new), `tests/Generator/OpenApiGeneratorTest.php` | пакет phpstan ✓, пакет test 29 ✓ (existing operationCount===4 не тронуты) | done |
| 3 | `emptyResponseClass` в `OpenApiConfig`, `requestCode`/`logout` → `EmptySuccessResponse` (204), удаление двух ресурсов-заглушек, правки Auth-тестов | `OpenApiConfig.php`, `AuthController.php`, удалены `RequestCodeResultResource.php` + `LogoutResultResource.php`, `AuthHttpTest.php` (3 теста → `assertNoContent()`), `AuthOpenApiGenerationTest.php` (новый метод) | целевые Auth-тесты 24 ✓ | done |
| 4 | Регенерация `public/openapi/openapi.yml` + полный гейт | `public/openapi/openapi.yml` (регенерирован: добавлен весь Auth-блок, стандартные описания на ru) | `make phpstan` ✓, `make test` 600 ✓, пакет phpstan+test 29 ✓ | done |
| 4а | Внепланово (с согласия пользователя): чужая пре-существующая поломка `make test` — 8 ошибок `Unknown named parameter $payload` | `AuthTokenViewTest.php`, `UserActorProviderTest.php`, `AuthContextAttributeMiddlewareTest.php` переписаны под новый конструктор `AuthTokenView(id,userId,type,sessionId,expiresAt)` | 3 Unit-файла 9 ✓ | done |

## Заметки
- Проверки пакета `spiral-openapi` требуют PHP 8.5 — запускаются в Docker (`app-http`), не на хосте (PHP 8.4).
  Команда: `docker compose ... run --rm --no-deps app-http bash -lc 'composer -d packages/spiral-openapi <phpstan|test>'`.
- Отклонение от буквы плана (по сути — тот же план): план предлагал собрать `responses` через spread
  `[...$successResponses, 'default' => $errorResponse]`. Проверено `php -r`: spread переиндексирует числовой
  ключ кода ответа (`'204'`/`'200'` → PHP-int) в `0`, что сломало бы спецификацию. Реализована та же структура
  (успешная запись отдельной переменной, `default` вне ветки) через union `+`, который ключ сохраняет. Это
  правка корректности в рамках намерения плана (rules.md «код проходит проверки по сути»), а не обходной путь;
  fixture-тест фазы 2 (ключ `204` есть, `200` нет) подтверждает.
- PHPStan: ключи `'204'`/`'200'` — числовые, поэтому `successResponses()` имеет PHPDoc `@return array<int, mixed>`.
- Язык регенерированной спецификации: `openapi:generate` берёт текущую локаль translator (`LOCALE=ru` в `.env`),
  поэтому стандартные описания вышли на русском (`Успешный ответ.`/`Ошибка API.`). Закоммиченный HEAD-файл был
  на английском — устаревший снимок. Русский соответствует контракту в самом плане (раздел «API и внешние
  контракты» показывает `Успешный ответ.`/`Ошибка API.`), поэтому это ожидаемая часть большого diff, а не регресс.
- Внеплановая правка 8 чужих тестов согласована с пользователем через AskUserQuestion (вариант «Починить»).
  Для двух кейсов «без userID» использован `createStub(TokenInterface::class)` + `willReturn` (паттерн rules.md):
  типизированный `AuthTokenView` всегда несёт userID, поэтому defensive-гард провайдера/middleware покрывается
  только чужой реализацией `TokenInterface`.

## Изменения в docs
Изменений в `docs/rules.md` / `docs/arch.md` не потребовалось: расширение семейства базовых Response через
`ResponseWrapperMapping` уже описано в arch.md как точка расширения, новых правил решение не вводит.

## Финальная проверка
| Проверка | Команда | Результат |
|---|---|---|
| PHPStan приложения | `make phpstan` | ✓ No errors |
| Полный сьют приложения | `make test` | ✓ 600 тестов OK (1 пре-существующий PHPUnit Notice, не падение) |
| PHPStan пакета | `composer -d packages/spiral-openapi phpstan` | ✓ No errors |
| Тесты пакета | `composer -d packages/spiral-openapi test` | ✓ 29 тестов OK |

Спецификация `public/openapi/openapi.yml` сверена: `/auth/code/request` и `/auth/logout` → `204` без `content`,
без `200`; `/auth/code/verify`, `/auth/register`, `/auth/refresh`, `/health` → `200` с телом.

Git: изменения НЕ закоммичены.
