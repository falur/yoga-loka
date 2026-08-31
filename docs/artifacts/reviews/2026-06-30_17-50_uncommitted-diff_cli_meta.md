# Кросс-CLI мета-ревью (codex exec) — итог

Запуск: `codex exec` (codex-cli 0.141.0), strict-режим, по ревью
`docs/reviews/2026-06-30_17-50_uncommitted-diff.md`. Завершился `CODEX_EXIT=0`.
(Полный потоковый транскрипт вычищен — ниже только итоговые пункты Codex и решение редактора.)

## Итоговые пункты Codex

- **+** `UserPublicProfileAssembler.php:7,52` используют `FindMediaOriginalUrl`, но
  `app/src/Modules/Media/Application/Query/FindMediaOriginalUrl/*` и
  `tests/Feature/Modules/Media/Application/FindMediaOriginalUrlHandlerTest.php` — untracked-файлы, в
  `git diff HEAD` не входят. Ревью опирается на них как на часть changeset, но целевой diff без них не
  соберётся.
- **+** `tests/Unit/Modules/Media/Infrastructure/MediaUploadPlannerTest.php:41`: `@return list<array{int, bool}>`
  содержит array shape — формально нарушает правило «без сложных массивов в PHPDoc».
- **+** `docs/arch.md:165-169` устарел относительно кода: сказано, что лента грузит конверсии ради
  `MediaUrlService::getUrls(Media $media)`, а `PostViewAssembler.php:277` вызывает `getOriginalUrl()`.
- **~** Пункт 2 ревью: eager-load конверсий в `PostMediaRepository` прямо требуется `docs/arch.md:165-169`,
  поэтому вариант «убрать `->load('media.*Conversions')`» нельзя предлагать как обычную правку без
  одновременного изменения архитектурного решения.
- **~** Оценка ревью: фраза «явных багов, нарушений правил или архитектуры не нашёл» неверна
  (untracked `FindMediaOriginalUrl`, спорный PHPDoc в тесте, рассинхрон `docs/arch.md`).
- **−** Убрать из Оценки утверждение, что `FindMediaOriginalUrl` «появился» в целевом changeset, если
  ревью строго по `git diff HEAD` (в текущем diff этого сценария нет).

## Решение редактора (применено к файлу ревью)

- Принято **+** про untracked `FindMediaOriginalUrl` → новый пункт 3 (`править обязательно`). Проверено:
  `git status --porcelain` даёт `??` для каталога сценария и его теста; tracked `UserPublicProfileAssembler`/
  `UserBootloader` на них опираются.
- Принято **+** про `docs/arch.md` → новый пункт 6; усилено вторым расхождением (неполный список сценариев
  Media). Проверено: `git diff HEAD -- docs/arch.md` показывает добавленную строку с `getUrls` и `+FindMediaUrl`.
- Принято **~** про пункт 2 → вариант «убрать eager-load» переформулирован в недопустимую обычную правку.
- Принято **~** про Оценку → Оценка переписана, фраза об отсутствии нарушений снята; `score` 93 → 84.
- **−** про удаление упоминания `FindMediaOriginalUrl` из Оценки применено как переформулировка, а не
  удаление: путь реально существует в рабочем дереве, его untracked-статус зафиксирован пунктом 3.
- **Отклонено** **+** про `@return list<array{int, bool}>`: `array{...}`-shape в data-провайдерах PHPUnit —
  устоявшаяся практика проекта (21 вхождение в уже закоммиченных тестах: `AuthValueObjectTest`,
  `ProcessMediaJobTest`, `S3MediaFileServiceErrorTest` и др.), `make phpstan` на ней зелёный. Флаг
  противоречил бы существующему коду и был бы шумом.
