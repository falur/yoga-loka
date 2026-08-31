---
title: Поддержка документов в модуле Media
date: 2026-06-22 17:22
mode: strict
decision_mode: recommend_and_ask
status: reviewed
reviewer: codex
sources:
  rules: docs/rules.md
  arch: docs/arch.md
---

# Поддержка документов в модуле Media

## Суть

В модуле `Media` нужно включить загрузку и выдачу документов (PDF, DOC, DOCX, DJVU,
TXT и другие популярные форматы). Сейчас `MediaType::Document` объявлен в enum, но это
заглушка: документы сознательно отклоняются во всех точках пайплайна. Задача — снять эту
заглушку так, чтобы документ можно было загрузить, переложить в постоянное хранилище и
отдать по URL, не ломая уже работающий поток image/video/audio.

Что именно отклоняется сейчас (точки проверены по коду):

```text
MediaTypeResolver::resolve()           app/.../Application/Service/MediaTypeResolver.php:22-37
  — распознаёт только image/*, video/*, audio/*; остальное → 422 unsupported_file_type
MediaPath::assertValid() (regex)       app/.../Domain/ValueObject/MediaPath.php:154
  — путь допускает лишь префиксы uploads|images|videos|audios
MediaPath::originalReady()             app/.../Domain/ValueObject/MediaPath.php:83
  — MediaType::Document => throw InvalidDomainValueException
ProcessMediaHandler::handle()          app/.../Application/Command/ProcessMedia/ProcessMediaHandler.php:90
  — MediaType::Document => throw «Обработка документов не поддержана»
CompleteMediaUploadHandler             app/.../Application/Command/CompleteMediaUpload/CompleteMediaUploadHandler.php:123
  — MediaType::Document => throw ValidationException в assertPlanValid()
```

## Решение

### Что выбрано

- **Поведение**: документ только хранится. После подтверждения загрузки оригинал
  перекладывается в постоянный бакет по `visibility` и отдаётся через `GetMediaUrl`.
  Конверсий, превью первой страницы и извлечения метаданных нет.
- **Распознавание типа**: явный whitelist MIME-типов документов в `MediaTypeResolver`.
  Неизвестный `application/*` по-прежнему отклоняется как 422.
- **Набор форматов**: все четыре согласованные категории (полный список ниже).

Документ в этой модели — это «image без конверсий»: тот же путь
`waitingUpload → uploaded → перекладка оригинала → ready`, но `MediaConversionPlan`
полностью пустой, и обработчик ничего не транскодирует.

### Почему так, а не иначе

| Развилка | Выбор | Почему |
|---|---|---|
| Глубина обработки | Только хранение | Совпадает с природой Media («храним и отдаём URL»). Превью потребовало бы Ghostscript + LibreOffice headless в образе, новую таблицу конверсий, новый процессор и контракт — отдельная большая задача. Превью можно добавить позже по образцу видео-постера. |
| Распознавание | Явный whitelist MIME | Сохраняет «намеренно сужающую» философию резолвера: модуль принимает только то, что знает. Предсказуемо и тестируемо. Альтернатива «всё, что не image/video/audio — документ» приняла бы любой `application/octet-stream`. |
| Набор форматов | Все 4 категории | Покрывает названные пользователем PDF/DOC/DOCX/DJVU/TXT и ходовые офисные/книжные форматы. |

### Архитектура: какие слои затронуты

Доменная и архитектурная модель НЕ меняется — нет новых Entity, контрактов, репозиториев,
зависимостей, миграций, конфигов, очередей или outbox-сообщений. Меняются ровно те 5 точек,
где `MediaType::Document` сейчас бросает исключение. Поток данных остаётся прежним:

```text
RequestMediaUpload  -> presigned PUT, media = waitingUpload   (тип резолвится в Document)
   клиент PUT-ит байты в staging-бакет
CompleteMediaUpload -> headObject, media = uploaded, MediaUploaded в outbox  (план пустой)
outbox:relay -> ProcessMediaJob -> ProcessMedia
   match(type): Document -> конверсий нет ([]) -> перекладка оригинала -> ready
GetMediaUrl -> public: прямой URL; private: presignGet
```

Точки правок:

| Точка | Сейчас | Станет |
|---|---|---|
| `MediaTypeResolver::resolve()` | 3 префиксных правила, остальное 422 | нормализовать MIME (нижний регистр, без параметров); whitelist MIME → `Document` проверяется **первым**, затем префиксы image/video/audio, затем 422 |
| `MediaPath::assertValid()` regex | `(uploads\|images\|videos\|audios)` | добавить префикс `documents` |
| `MediaPath::originalReady()` | `Document => throw` | `Document => 'documents'` |
| `ProcessMediaHandler::handle()` match | `Document => throw` | `Document => []` (пустой набор конверсий, дальше штатная перекладка `persistReady`) |
| `CompleteMediaUploadHandler::assertPlanValid()` | `Document => throw` | `Document =>` проверка, что `image`/`video`/`audio` пусты; иначе 422 `conversion_plan_type_mismatch` |

`originalUpload()` не трогаем: staging-путь `uploads/...` от типа не зависит.
`MediaConversionPlan` и `MediaUploaded` не трогаем: для документа все три списка пусты.
Перевод `app.media.conversion_plan_type_mismatch` уже есть (`app/locale/ru/media.php:18`) —
подходит для непустого плана у документа; `unsupported_file_type` остаётся для реально
неизвестных MIME.

### Whitelist MIME → расширение (формат)

`MediaPath::sanitizeExtension()` допускает `[a-z0-9]+` — все расширения ниже проходят.

```text
Базовые текстовые
  application/pdf                                                              -> pdf
  application/msword                                                           -> doc
  application/vnd.openxmlformats-officedocument.wordprocessingml.document      -> docx
  text/plain                                                                   -> txt
  application/rtf, text/rtf                                                    -> rtf   (встречаются оба MIME)
OpenDocument
  application/vnd.oasis.opendocument.text                                      -> odt
  application/vnd.oasis.opendocument.spreadsheet                               -> ods
  application/vnd.oasis.opendocument.presentation                              -> odp
MS Office таблицы/презентации
  application/vnd.ms-excel                                                     -> xls
  application/vnd.openxmlformats-officedocument.spreadsheetml.sheet            -> xlsx
  application/vnd.ms-powerpoint                                                -> ppt
  application/vnd.openxmlformats-officedocument.presentationml.presentation    -> pptx
Книги и сканы
  application/epub+zip                                                         -> epub
  application/x-fictionbook+xml                                                -> fb2   (MIME не стандартизирован IANA)
  image/vnd.djvu                                                               -> djvu  (внимание: префикс image/)
  text/csv                                                                     -> csv   (параметры charset/header нормализуются)
  text/markdown                                                                -> md    (charset нормализуется до базового MIME)

Явно НЕ поддержаны (не в whitelist): macro-enabled Office (docm/xlsm/pptm,
MIME с суффиксом …macroEnabled.12) — активное содержимое.
```

Сравнение с whitelist — по нормализованному MIME: базовый тип в нижнем регистре, без
параметров после `;` (нужно для `text/markdown;charset=…` и `text/csv;charset=…`).

### Риски — закрытие рядом с решением

- **DJVU ломает порядок проверок в резолвере.** MIME DJVU — `image/vnd.djvu`, он начинается
  с `image/`. Текущий резолвер проверяет `str_starts_with('image/')` первым
  (`MediaTypeResolver.php:22`), поэтому без изменений DJVU стал бы `Image` и пайплайн отправил
  бы его в Imagick-ресайз. **Снимаем**: whitelist документов (точное сравнение MIME)
  проверяется ДО префиксных правил. Это обязательное требование к реализации резолвера.
- **Whitelist резолвера — единственный источник набора, потребитель его не расширяет.**
  `MediaTypeResolver::resolve()` вызывается ДО проверки `allowedMimeTypes`
  (`RequestMediaUploadHandler.php:40-41`): сначала тип резолвится, и неизвестный резолверу MIME
  падает в 422 раньше, чем дойдёт до спецификации потребителя. Значит `allowedMimeTypes` может
  только сузить набор, заданный whitelist'ом, но не добавить новый MIME. Это влияет на
  формулировки ниже (FB2/CSV/RTF) и означает: любой принимаемый MIME должен быть в whitelist кода.
- **Модуль доверяет заявленному MIME и не проверяет содержимое.** Клиент PUT-ит байты прямо в
  S3, MIME приходит из `MediaFileMeta`. Это свойство существующей архитектуры (так же для
  image/video/audio). Для документов добавляется поверхность (вредоносный PDF; офисные файлы с
  активным содержимым), но Media документ не парсит и не исполняет — только хранит и отдаёт.
  **Принимаем** в рамках текущего дизайна; антивирус/проверка magic bytes — отдельная задача вне
  этих рамок. Отдельно про макросы: macro-enabled форматы (`docm`/`xlsm`/`pptm`, MIME с суффиксом
  `…macroEnabled.12`) в whitelist НЕ входят и остаются неподдержанными. Старый `doc`
  (`application/msword`) включён по запросу и теоретически может нести макросы, но риск нивелируется
  тем, что модуль контент не исполняет.
- **Параметры в MIME (charset/header).** По IANA у `text/markdown` параметр `charset` обязателен,
  у `text/csv` допустимы `charset` и `header`; клиент реально присылает, например,
  `text/markdown;charset=utf-8`. `MediaMimeType` хранит строку целиком и параметры не отделяет, а
  whitelist — точное сравнение, поэтому `text/markdown;charset=utf-8` не совпало бы с
  `text/markdown` и markdown/csv фактически не прошли бы. **Снимаем**: резолвер перед сравнением с
  whitelist нормализует MIME — отбрасывает параметры (часть после `;`) и приводит к нижнему
  регистру (`text/markdown;charset=UTF-8` → `text/markdown`). Это требование к реализации
  резолвера; нормализацию покрыть тестом.
- **MIME FB2 не стандартизирован.** `application/x-fictionbook+xml` — самый частый, но встречаются
  `application/fb2` и сырой `application/xml`. **Снимаем выбором**: в whitelist берём канонический
  `application/x-fictionbook+xml`; остальные MIME для FB2 считаются неподдержанными и при
  необходимости добавляются в whitelist правкой кода (потребитель их не включит — см. пункт о
  порядке resolve выше).
- **CSV/RTF имеют несколько MIME.** RTF — `application/rtf` и `text/rtf`; CSV — `text/csv` (Excel
  иногда помечает иначе). **Снимаем**: для RTF включаем в whitelist оба MIME; для CSV берём
  канонический `text/csv`. Прочие варианты — не «на усмотрение потребителя» (он whitelist не
  расширяет), а неподдержанные до явного добавления в whitelist.
- **Осиротевшие staging-объекты** — без изменений: документ, как и другие типы, чистится
  staging-expiry задачей; терминальные сироты — на будущий storage-sweep.

### Версии ПО

Проверка актуальных версий пакетов/ПО **не требуется**: выбран вариант «только хранение»,
он не вводит новых зависимостей (Composer-пакетов, бинарей, системных пакетов). Imagick и
ffmpeg для документов не задействуются; Ghostscript/LibreOffice не нужны, потому что превью
отклонено. Используются только уже имеющиеся S3-операции (`copyObject`, `headObject`,
`presignPut`, `presignGet`).

## Ответы на вопросы

| Вопрос (развилка) | Ответ пользователя |
|---|---|
| Что Media делает с документом после загрузки? | **Только хранить оригинал** (без превью и без метаданных) |
| Как определять, что файл — документ? | **Явный список MIME-типов** (whitelist) |
| Какие форматы включить? | **Все четыре категории**: базовые текстовые (PDF, DOC, DOCX, TXT, RTF), OpenDocument (ODT, ODS, ODP), MS Office таблицы/презентации (XLS, XLSX, PPT, PPTX), книги и сканы (EPUB, FB2, DJVU, CSV, MD) |

## Итог

Подход: снять заглушку `MediaType::Document` в 5 уже найденных точках, не вводя новых
сущностей, зависимостей, миграций и конфигов. Документ обрабатывается как «медиа без
конверсий»: резолвер нормализует MIME (нижний регистр, без параметров) и классифицирует его
по whitelist MIME (с приоритетом над префиксом
`image/` ради DJVU), `MediaPath` получает префикс `documents`, `ProcessMediaHandler`
возвращает пустой набор конверсий и делает штатную перекладку оригинала в ready, а
`CompleteMediaUpload` требует, чтобы план конверсий для документа был пустым. Полный whitelist
MIME → расширение зафиксирован выше. Тесты: дополнить `MediaTypeResolverTest` (включая
DJVU → Document, а не Image, и MIME с параметром `;charset=…`), `MediaValueObjectTest` (путь
`documents`), `ProcessMediaHandlerTest` (перекладка без конверсий), `CompleteMediaUploadHandlerTest`
(пустой план для документа), `RequestMediaUploadHandlerTest` (запрос загрузки документа) —
требуется 100% покрытие. Следующий шаг — `eda-plan` для пошаговой реализации.

## Реакция на ревью

Кросс-ревью выполнено Codex CLI (`codex-cli 0.141.0`); полный лог —
`2026-06-22_17-22_media-document-support_review.md`. Codex дал 3 содержательных замечания.

- **Принято [архитектура] — порядок resolve vs allowedMimeTypes.** Формулировка «другие MIME
  (FB2/CSV/RTF) — ответственность потребителя через `allowedMimeTypes`» была неверной: `resolve()`
  вызывается до проверки `allowedMimeTypes` (`RequestMediaUploadHandler.php:40-41`), поэтому
  потребитель whitelist только сужает. Добавлен отдельный пункт-риск про порядок, переписаны
  пункты FB2 и CSV/RTF.
- **Принято [MIME] — параметры MIME (charset/header).** Точное сравнение не пропустило бы
  `text/markdown;charset=utf-8` / `text/csv;charset=…`. Добавлено решение: резолвер нормализует
  MIME (нижний регистр, отбрасывание параметров после `;`) перед сравнением с whitelist; учтено в
  таблице правок, whitelist-блоке, Итоге и тестах.
- **Принято [риск] — macro-enabled форматы.** Уточнена формулировка: macro-enabled
  `docm`/`xlsm`/`pptm` (MIME `…macroEnabled.12`) в whitelist НЕ входят; старый `doc` включён по
  запросу, риск нивелируется тем, что Media контент не исполняет.
