---
plan: docs/plans/2026-06-11_15-04_izbavitsya-ot-deprecated-v-testah.md
started: 2026-06-11 15:17
finished: 2026-06-11 15:55
status: done
---

# Журнал: Избавиться от deprecated в тестах (PHPUnit 13 + PHP 8.5)

## Шаги
| # | Шаг | Файлы | Тесты | Статус |
|---|-----|-------|-------|--------|
| 1 | Класс A: 5 точек `createStub + willReturnMap` (отклонение от плана, см. Заметки) | ConfigMapperTest, ComplexConfigMapperTest, SimpleConfigMapperTest (Unit); MediaConfigTest, MediaStorageConfigTest (Kernel) | `make test` — 405 тестов, 0 deprecated | ✅ |
| 1 | Класс B: удалить `\imagedestroy($image);` (мёртвый код) | ImagickMediaImageProcessorTest (Unit), MediaProcessingFlowTest (Feature) | `make test` — PHP-deprecated `imagedestroy` исчез | ✅ |
| 1 | Прогон `make test` (Unit+Kernel+Feature) | — | OK: 405 тестов, 1246 assertions, **0 Deprecations**, ровно 1 несвязанный Notice | ✅ |
| 2 | Включить гейт `failOnDeprecation` + `failOnPhpunitDeprecation` | phpunit.xml | — | ✅ |
| 2 | Negative-контроль гейта (временный deprecated → откат) | ConfigMapperTest (временно) | exit code 1 при возвращённом deprecated, после отката зелёный | ✅ |
| 2 | Финальный прогон `make test` + `make qa` | — | `make test` зелёный с активным гейтом; `make qa` — стиль OK, PHPStan OK, coverage 100% | ✅ |

Оставшийся Notice (вне темы, гейтом не ловится): `ProcessMediaHandlerTest::testStoresConversionDimensionsFromProcessorResult`
(`tests/Feature/Modules/Media/Application/ProcessMediaHandlerTest.php:50`) — «No expectations
were configured for the mock object for MediaFileServiceContract». Это PHPUnit Notice про мок
без ожиданий, не deprecation.

## Заметки

### Отклонение от плана (Класс A) — подтверждено пользователем
План предписывал `->expects($this->any())` перед `->method('getConfig')`. На PHPUnit
13.1.8 это **не убирает** деприкейшен, а заменяет его новым: «The any() invoked count
expectation is deprecated and will be removed in PHPUnit 14. Use a test stub instead or
configure a real invocation count expectation». Деприкейшенов оставалось столько же (27
Unit + 2 Kernel).

Решение (AskUserQuestion → выбран `createStub + willReturnMap`): во всех 5 точках Класса A
заменить `createMock(...)->expects(...)->method('getConfig')->with($section)->willReturn($config)`
на `createStub(...)->method('getConfig')->willReturnMap([[$section, $config]])`. Это:
- убирает деприкейшен (проверено на ConfigMapperTest — зелёный, 0 deprecated);
- при неверной секции стаб вернёт `[]` (дефолт по типу возврата `getConfig(): array`, не
  `null`); маппинг падает, потому что у целевого конфига есть обязательные поля. Для текущих
  5 конфигов с обязательными полями зелёный/красный исход совпадает со старым поведением
  `with()`, но проверка секции теперь косвенная (зависит от обязательных полей DTO), а не
  явная (assertion дублёра);
- совпадает с уже используемым в `ConfigMapperTest` паттерном `createStub()` (строки 142, 193).

## Финальная проверка

- **`make test`** (Unit + Kernel + Feature, ParaTest×4, активный гейт `failOn*`): зелёный, exit 0.
  405 тестов, 1246 assertions, **0 PHPUnit/PHP Deprecations**, 1 несвязанный PHPUnit Notice
  (не под гейтом).
- **`make qa`** (стиль + PHPStan + coverage через тот же `phpunit.xml`): зелёный.
  - Стиль: без ошибок.
  - PHPStan: `[OK] No errors`.
  - Coverage: `Покрытие 100.00% соответствует порогу 100.00%`.
- **Negative-контроль гейта:** при временном возврате deprecated-паттерна в ConfigMapperTest
  прогон дал `PHPUnit Deprecations: 5` и exit code 1 (красный). После отката — снова зелёный.
  Гейт `failOnPhpunitDeprecation` подтверждённо валит прогон.

Запрещённых приёмов (подавление ошибок, отключение правил, ослабление конфигов) не
применялось — наоборот, добавлен гейт. Чужих/несвязанных поломок не правил.

## Изменения в docs

Правок `docs/rules.md` / `docs/arch.md` не требуется: изменения только в тестовом коде и
конфиге PHPUnit, архитектура и контракты приложения не затронуты. Отклонение по фиксу
Класса A — деталь реализации тестов (PHPUnit-версия), а не проектное правило. При желании
закрепить паттерн «`expects($this->any())` deprecated в PHPUnit 13 → стаб» можно через
`eda-docs`/`eda-automate`.
