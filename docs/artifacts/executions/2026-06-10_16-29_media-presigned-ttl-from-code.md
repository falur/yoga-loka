---
plan: docs/plans/2026-06-10_16-17_media-presigned-ttl-from-code.md
started: 2026-06-10 16:29
finished: 2026-06-10 18:05
status: done
---

# Журнал: Управляемый из кода TTL presigned-ссылок Media (upload и download)

## Шаги
| # | Шаг | Файлы | Тесты | Статус |
|---|-----|-------|-------|--------|
| 1 | VO `MediaPresignedTtl` + строка в data-provider VO-теста | `Domain/ValueObject/MediaPresignedTtl.php`, `tests/Unit/.../MediaValueObjectTest.php` | `MediaValueObjectTest` локально: OK (22 теста, 81 ассерт) | done |
| 2 | Upload-TTL из `MediaUploadSpec` + ключ в debug-логе | `Dto/MediaUploadSpec.php`, `RequestMediaUploadHandler.php`, `tests/Feature/.../RequestMediaUploadHandlerTest.php`, `tests/Feature/.../Flow/Fixture/RecordingMediaLogger.php`, `README.md` | Зелёный в полном `make test` (332 теста) | done |
| 3 | Download-TTL из `GetMediaUrlQuery` + полное удаление `presignedTtlSeconds` | `GetMediaUrlQuery.php`, `GetMediaUrlHandler.php`, `MediaFileServiceContract.php`, `MediaConfig.php`, `app/config/media.php`, `.env.sample`, `phpunit.xml`, `MediaConfigTest.php`, `ImagickMediaImageProcessorTest.php`, `GetMediaUrlHandlerTest.php`, `RequestMediaUploadHandlerTest.php`, `README.md`, `docs/arch.md` | Зелёный в полном `make test` + `make phpstan` | done |

## Заметки
- Место выполнения: текущая ветка `main` (выбор пользователя), поверх уже имеющихся незакоммиченных изменений media-модуля.
- Docker: первая сборка образа `test-runner` идёт очень долго (~20+ мин, PHP-расширения). Фаза 1 — чистый Unit-тест VO без БД/imagick — проверена локально через `vendor/bin/phpunit` (PHP 8.5). Feature-тесты фаз 2–3 требуют docker-стек (postgres/minio по docker-хостам) и прогоняются вместе в финальном `make test`.
- Для проверки источника TTL в тестах загрузки добавлен захват `expiresAt` из `presignPut`/`presignUploadParts` через `willReturnCallback` и характерные TTL (300 single, 600 multipart) ≠ конфиг-дефолта 900. Фикстура `RecordingMediaLogger` расширена захватом `context` (+`contextFor()`), чтобы проверить ключ `presignedTtlSeconds` в debug-логе.

## Изменения в docs
- `docs/arch.md` (строка ~267): из примера инъекции `TypedConfig` в Application-Handler убран
  `GetMediaUrlHandler` — после выноса download-TTL он больше не зависит от `MediaConfig`. Остался
  `RequestMediaUploadHandler`. Уточнено «TTL» → «staging-TTL» (presigned-TTL в конфиге нет).
  Архитектурное правило (TypedConfig можно инжектить в Application-Handler) не менялось — поправлен
  только устаревший пример, чтобы doc не противоречил коду.

## Финальная проверка
- **PHPStan**: локально `php -d memory_limit=1G vendor/bin/phpstan analyse` → **[OK] No errors**.
  Тот же `phpstan.neon` (level max, strict-rules из vendor, `phpVersion: 80506`), что и `make phpstan`.
  Ключевой сигнал: обращений к удалённому `MediaConfig->presignedTtlSeconds` нигде не осталось.
- **Unit (фаза 1)**: локально `vendor/bin/phpunit --testsuite Unit --filter MediaValueObjectTest`
  → OK (22 теста, 81 ассерт).
- **Полный `make test` (Feature + Unit, в Docker)**: **OK — 332 теста, 1129 ассертов** (exit 0,
  «Тестовый запуск завершён успешно»). Deprecations/Notices PHPUnit — преждевременный шум, не падения.
  Прогнан после перезапуска Docker пользователем (демон ранее завис на сборке образа).
- **`make phpstan` (Docker, канонический)**: **[OK] No errors** (exit 0) — подтверждает локальный прогон.
- Итог: обе обязательные проверки зелёные. Покрытие сохранено (Feature-сценарии расширены, не урезаны).
