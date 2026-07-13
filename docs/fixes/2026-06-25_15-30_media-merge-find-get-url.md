---
date: 2026-06-26 18:31
source: text (eda-fix) — переработка разрешения URL медиа: единый набор ссылок + универсальный резолвер + foundational-Media с relation против N+1
status: done
---

# Фикс: разрешение URL медиа — единый набор, резолвер, foundational-Media (relation против N+1)

## Контекст

Старт: «не нравится логика GetMediaUrlHandler — всегда отдавать все преобразования и оригинал,
никаких 404 для readyOriginalRemoved». Дальше дизайн дорабатывался в диалоге и вырос до изменения
архитектуры. Итоговые решения пользователя:

1. **Слить GetMediaUrl и FindMediaUrl** в один сценарий «полный набор URL» (у GetMediaUrl не было
   потребителей). Результат расширен.
2. **Универсальный резолвер** вместо протаскивания visibility/ttl в каждый вызов: полиморфный
   `MediaUrlResolver` (Public/Presigned) + `MediaUrlResolverFactory` (контекст фиксируется один раз).
3. В `MediaConversionUrl` добавлен **`kind`** (image/video/audio — чем рендерить на клиенте;
   переиспользован `MediaType`).
4. **Media — foundational-модуль**: другим модулям разрешено держать ORM-relation на сущности Media
   (на чтение) и передавать загруженную сущность в Application-сервис Media. Это убирает N+1 в ленте:
   `Posts` eager-грузит медиа + конверсии вместе с выборкой и строит URL без обращений в БД.

## Контракт и сервисы

- `FindMediaUrl(mediaId, presignedTtlSeconds): MediaUrlsResult|null` — грузит медиа с конверсиями
  одним набором запросов (`MediaRepository::findByIdWithConversions`) и делегирует в `MediaUrlService`.
  null — медиа нет или не финализировано. Для User-аватара.
- `MediaUrlService::getUrls(Media $media, int $ttl): MediaUrlsResult|null` — строит набор из **уже
  загруженной** сущности (читает связи imageConversions/videoConversions/audioConversions), без БД.
  Прямой путь для модулей, держащих сущность Media (лента Posts).
- `MediaUrlsResult{ original: MediaUrlResult?, conversions: MediaConversionUrlCollection }`;
  `MediaConversionUrl{ kind, type, url, expiresAt? }`; `conversions->ofType($type)` — выбор по типу.
- URL: public → прямой без срока (ttl игнорируется/не валидируется); private → presigned, единый
  срок на весь набор. Способ фиксируется один раз в `MediaUrlResolverFactory::forMedia`.

## N+1 в ленте

`PostMedia` получил `#[BelongsTo(target: Media::class, innerKey: 'media_id', cascade: false,
fkCreate: false, indexCreate: false)]` (id-VO переименован `media` → `mediaId`; FK media_id уже
создан миграцией post_media — поэтому fkCreate:false, миграции не трогаем). `PostMediaRepository`
eager-грузит `media.imageConversions/videoConversions/audioConversions` — на странице ленты
константа запросов вместо ~4×N. `PostViewAssembler` строит URL через `getUrls($postMedia->media)`.

## Что изменено

| # | Файл | Что |
|---|------|-----|
| 1 | `Media/Application/Service/MediaUrlResolver.php` (+ Public/Presigned + Factory) | Полиморфный резолвер URL: контекст один раз |
| 2 | `Media/Application/Service/MediaUrlService.php` | `getUrls(Media)` — набор URL из загруженной сущности, без БД |
| 3 | `Media/Application/Dto/` (MediaUrlsResult, MediaConversionUrl с `kind`, MediaConversionUrlCollection с `ofType`) | Расширенный результат |
| 4 | `Media/Repository/MediaRepository.php` | `findByIdWithConversions` (eager-load конверсий) |
| 5 | `Media/Application/Query/FindMediaUrl/FindMediaUrlHandler.php` | Грузит с конверсиями и делегирует в `MediaUrlService` |
| 6 | `Media/Application/Query/GetMediaUrl/` + тест | Удалены (нет потребителей) |
| 7 | `Posts/Domain/Entity/PostMedia.php` | id-VO `media` → `mediaId`; добавлена relation `media: Media` |
| 8 | `Posts/Repository/PostMediaRepository.php` | eager-load `media.*Conversions` в обоих методах |
| 9 | `Posts/Application/View/PostViewAssembler.php` | `MediaUrlService::getUrls($postMedia->media)` вместо FindMediaUrl |
| 10 | `Posts/Application/Post/PostContentComposer.php` | `PostMedia::create(mediaId: ...)` |
| 11 | `User/Application/Profile/UserPublicProfileAssembler.php` | Читает `->original?->url` |
| 12 | `docs/arch.md` | Исключение «Media — foundational»: relation на чтение разрешён |
| 13 | `Media/README.md`, `MediaFileServiceContract`, `app/config/media.php` | Доки/комментарии под новый контракт |
| 14 | `app/locale/{ru,en}/media.php` | Удалён осиротевший `app.media.original_removed` |
| 15 | тесты | FindMediaUrlHandlerTest, UserApplicationTestCase, MediaApplicationTestCase (`cleanOrmHeap`), JoinEntityTest, PostMediaRepositoryTest, PostRepositoryTest |

## Заметки

- ORM-связь кросс-модульная, но без нового FK (`fkCreate: false` — FK media_id уже есть) и без записи
  в Media (`cascade: false`). Запись/изменение медиа — только через Command-сценарии Media.
- `MediaUrlService::getUrls` читает конверсии из связей сущности — вызывающий обязан передавать медиа
  с eager-загруженными связями (иначе ленивая подгрузка вернёт N+1). В тестах с persist+чтение в одном
  heap добавлен `cleanOrmHeap()`, чтобы выборка читала из БД с eager-load.
- Потребители (Posts/User) используют только `original`; конверсии/`kind` доступны, но подключение в
  ленту (постер видео, превью аватара) — отдельная задача.

## Тесты и проверки

| Команда | Результат | Заметки |
|---------|-----------|---------|
| `make qa` | ✓ | Стиль + PHPStan (level max) + coverage; 1259 тестов, 4102 проверки; покрытие 100.00% |

## Открытые вопросы

Подключение потребителей к конверсиям (аватар → миниатюра, видео-пост → нормализованный mp4 +
постер) — отдельная задача.
