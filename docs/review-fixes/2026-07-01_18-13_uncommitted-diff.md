---
review: docs/reviews/2026-07-01_17-38_uncommitted-diff.md
date: 2026-07-01 18:13
status: done
---

# Фиксы по ревью: Рефакторинг Media URL-сервисов, RemoveMediaOriginal и config-independence (3-й круг)

Обязательных замечаний в ревью нет. Все три замечания — `на усмотрение автора`, касаются только
тест-кода. Режим `apply-optional`: каждое разобрано без вопроса; все три приняты — дёшево, симметрично,
снижают дублирование, не меняют продакшн-логику и не противоречат `docs/rules.md` / `docs/arch.md`.

## Применённые правки
| # | Замечание | Файлы | Тесты | Статус |
|---|-----------|-------|-------|--------|
| 1 | Audio happy-path удаления оригинала не проверял возвращённый `MediaResult` (асимметрия с image/video) | `tests/Feature/Modules/Media/Application/RemoveMediaOriginalHandlerTest.php` | `testRemovesOriginalOfAudioMediaKeepingConversion` (+2 ассерта: `status`, `mediaId`) | ✓ применено |
| 2 | Билдеры конверсий image/video/audio дублировались между тремя тест-классами при наличии общего базового кейса | `tests/Feature/Modules/Media/Application/MediaApplicationTestCase.php`, `FindMediaUrlHandlerTest.php`, `RemoveMediaOriginalHandlerTest.php`, `GetAudioWaveformHandlerTest.php` | весь набор Media-тестов (зелёный) | ✓ применено |
| 3 | `FindMediaOriginalUrl` не фиксировал отклонение явного/невалидного TTL для private (асимметрия с `FindMediaUrl`) | `tests/Feature/Modules/Media/Application/FindMediaOriginalUrlHandlerTest.php` | `testRejectsExplicitZeroTtlForPrivateMedia` (новый) | ✓ применено |

## Решения по optional
- **Принято: 1, 2, 3.**
  - **№1** — в audio-тесте захвачен результат сценария; добавлены те же два утверждения, что у image/video
    (`assertSame(MediaStatus::ReadyOriginalRemoved, $result->status)` и `assertSame($media->id->value(), $result->mediaId)`).
    Тройка позитивных тестов снова симметрична. Имя `$result` — сквозная предсуществующая конвенция всего
    сьюта (это зафиксировал rules-check в самом ревью), поэтому его использование правилам не противоречит.
  - **№2** — общие билдеры `imageConversion(type, status)`, `videoConversion(status)` и `audioConversion()`
    подняты в базовый `MediaApplicationTestCase` рядом с уже вынесенным `thumbnailConversion`; сам
    `thumbnailConversion` переделан в делегат над `imageConversion` (устранён внутренний дубль). Локальные
    копии убраны из `FindMediaUrlHandlerTest`, `RemoveMediaOriginalHandlerTest` (три `readyXxxMediaWithConversion`
    и инлайновый non-ready image-конверт) и `GetAudioWaveformHandlerTest`; неиспользуемые импорты вычищены.
    Тела билдеров побайтово совпадали с прежними инлайнами (video/audio) и с `thumbnailConversion` (image),
    поэтому смысл ассертов не изменился. Для audio использован `$media->storage` — у всех трёх потребителей
    storage у медиа = `Public`, отдельный параметр не нужен и нового дубля не создаёт (рекомендация ревью
    «параметризовать, если нужен фиксированный Public» неактуальна: значение уже совпадает).
  - **№3** — добавлен тест, что явный `presignedTtlSeconds: 0` на private-медиа бросает
    `InvalidDomainValueException`, симметрично `FindMediaUrl::testRejectsExplicitZeroTtlForPrivateMedia`.
    Путь общий: `MediaUrlService::presignedResolverFor()` → `MediaPresignedTtl::fromInt()`.
- **Отклонено:** — (нет).

## Финальная проверка
- **`make qa`:** ✓ зелёный (фоновый прогон, exit code 0).
  - Стиль (`php-cs-fixer --dry-run`): чисто, 0 файлов к правке из 1108.
  - PHPStan (`level max`): `[OK] No errors`.
  - Тесты (`paratest`, PCOV): `OK (1293 tests, 4182 assertions)`.
  - Покрытие: `100.00% соответствует порогу 100.00%`.
- **Заметки:** правки только тест-кода; продакшн-код не менялся, покрытие не упало (порог 100% держится).
