---
date: 2026-06-15 20-07
source: text (замечание 1 из docs/reviews/2026-06-15_22-10_auth-module.md + указание вернуть все сообщения)
status: done
---

# Фикс: рендерер ошибок валидации возвращает все сообщения поля

## Контекст

Отправная точка — замечание 1 ревью `docs/reviews/2026-06-15_22-10_auth-module.md`: PHPDoc
`render()` объявлял `array<string, string>`, тогда как Symfony-валидатор отдаёт несколько
сообщений на поле списком; рендерер брал только первое (`firstMessage`).

В ходе обсуждения задача уточнена: `firstMessage` убрать, **возвращать все сообщения** поля.

Проверены `docs/rules.md` и `docs/arch.md`. Ключевое ограничение — правило «Без сложных
массивов в PHPDoc» (PHPStan `gianTiaga.phpstanStrictRules.noNestedArrayType`): тип
`array<string, string|list<string>>` запрещён (вложенный массив в value). Эмпирически
подтверждено прогоном PHPStan пакета — такой PHPDoc даёт ошибку `noNestedArrayType`.

Поэтому «честный» keyed-тип не вводился. Контракт остаётся `array<string, string>` (как у
vendor-интерфейса `Spiral\Filters\ErrorsRendererInterface`, который сам объявляет именно
этот тип), а список сообщений разворачивается в приватном хелпере с нативным типом
`string|array` и PHPDoc `@param string|list<string>` — union `list<string>` не вложен в
массив и правило не нарушает (тот же приём, что был у `firstMessage`). Baseline и
подавления правил не понадобились.

Форма JSON-ответа изменена (вариант A — список сообщений на поле):
`{"field":"email","message":"..."}` → `{"field":"email","messages":["...","..."]}`.

## Что изменено

| # | Файл | Что изменено | Зачем |
|---|------|--------------|-------|
| 1 | `packages/spiral-openapi/src/Response/ValidationErrorItemResponse.php` | `public string $message` → `public array $messages` с `@param list<string>` | Хранить все сообщения поля, а не одно |
| 2 | `packages/spiral-api-errors/src/Filter/ApiValidationErrorsRenderer.php` | Удалён `firstMessage()`, добавлен `messages(string\|array): list<string>` (`@param string\|list<string>`); `validationErrors()` строит item с `messages:` | Возвращать все сообщения; контракт keyed-массива остаётся `array<string,string>` без вложенного типа |
| 3 | `packages/spiral-api-errors/tests/Filter/ApiValidationErrorsRendererTest.php` | Ожидаемые тела под `messages:[...]`; кейс списка переименован в `testRendererReturnsAllMessagesWhenFieldErrorsAreList` (теперь проверяет **оба** сообщения); пустой список → `testRendererReturnsEmptyListWhenFieldErrorListIsEmpty` (`messages:[]`) | Покрыть новое поведение |
| 4 | `packages/spiral-openapi/tests/Response/ValidationErrorResponseTest.php` | Конструктор `messages: [...]`, ожидаемый JSON под `messages` | Под новый shape DTO |
| 5 | `tests/Feature/Modules/System/Http/ApiErrorHttpTest.php` | Два ассерта (en/ru) под `"messages":["Возраст должен быть числом"]` | Под новый shape ответа |

OpenAPI не трогался: схемы валидации в спеке нет (рендерится только рантайм Filter-ом),
поэтому правка не даёт diff в `public/openapi/openapi.yml`. Случайная регенерация,
подтянувшая несвязанные маршруты Auth, откатана к HEAD.

## Тесты и проверки

| Команда | Результат | Заметки |
|---------|-----------|---------|
| `packages/spiral-openapi` phpstan (Docker, PHP 8.5) | ✓ | No errors |
| `packages/spiral-openapi` phpunit | ✓ | 27 tests, 543 assertions |
| `packages/spiral-api-errors` phpstan | ✓ | No errors (после удаления избыточного `array_values`) |
| `packages/spiral-api-errors` phpunit | ✓ | 26 tests, 138 assertions |
| `composer phpstan` (app) | ✓ | No errors |
| `vendor/bin/phpunit --filter ApiErrorHttpTest` (Feature) | ✓ | 10 tests, 37 assertions |

## Открытые вопросы

Нет. Изменение формы ответа валидации (`message` → `messages`) — публичный контракт API;
подтверждён пользователем (вариант A). Полный прогон `make qa` и регенерацию OpenAPI для
всего незакоммиченного набора (Auth) разумно сделать в рамках основной работы по модулю.
