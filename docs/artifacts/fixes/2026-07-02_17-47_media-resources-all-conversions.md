---
date: 2026-07-02 17:47
source: text — «в ресурсах, которые используют медиа, отдавать медиа со всеми преобразованиями, чтобы фронт сам решил что показать»
status: done
---

# Фикс: ресурсы отдают медиа со всеми конверсиями

## Контекст

На входе — короткий текст: ресурсы, использующие медиа, должны отдавать не только оригинал, а
полный набор ссылок (оригинал + все готовые конверсии), чтобы фронт сам выбирал профиль показа.

Прочитаны `AGENTS.md`, `docs/arch.md`, `docs/rules.md`, `docs/code-examples.md` и учтены:
- модульный монолит + CQRS + тактический DDD, границы слоёв (Presentation → Application своего
  модуля; межмодульные вызовы через Application; Media — foundational-модуль);
- Application не тянет `*Config`; ресурсы — `final readonly extends AbstractResource` только со
  свойствами и `fromEntity/fromView`; camelCase в JSON; типизированные списки вместо сырых массивов;
- 100% покрытие тестами, каждый роут покрыт интеграционным тестом.

Ключевое: медиа-слой уже строит полный набор (`MediaUrlService::getUrls` → `MediaUrlsResult` =
`original` + `conversions`), но потребители брали только `original`, а конверсии выбрасывали (в
коде это было помечено как «показ превью — на будущее»).

**Уточнение охвата** (задан вопрос пользователю): выбрано «посты + аватары». Аватар денормализован
как одиночная `avatarUrl` в уведомлениях/пуше/websocket, поэтому `avatarUrl` **сохранён** (его берут
потребители одной ссылки), а конверсии **добавлены** отдельным полем. Модуля `User/Presentation` нет —
аватар доходит до клиента только через `AuthorResource` (автор поста/комментария), поэтому
уведомления/пуш/websocket не затронуты.

Форма ответа (additive, обратно совместимо):
- вложение записи: `{ mediaId, url, expiresAt, position, conversions: [{kind,type,url,expiresAt}] }`;
- автор: `{ userId, name, avatarUrl, avatarConversions: [{kind,type,url,expiresAt}] }`.

`kind` и `type` конверсии — **enum-ы** (доменные `MediaConversionKind` и union
`MediaImageConversionType|MediaVideoConversionType|MediaAudioConversionType`), а не свободные строки:
контракт несёт закрытый набор значений. В JSON они по-прежнему сериализуются в свои строковые значения
(`"image"`, `"thumbnail"`), а в OpenAPI дают enum-схемы (`kind` → `$ref`, `type` → `oneOf` трёх enum-ов).

Оригинал вложения может быть удалён (`readyOriginalRemoved`), а конверсии — остаться. Поэтому:
- `url` (и `expiresAt`) вложения **nullable**: null, когда оригинал удалён;
- вложение **сохраняется** в ответе, пока есть что показать (оригинал ИЛИ хотя бы одна конверсия) —
  фронт покажет конверсию; исключается только когда показывать нечего (медиа не готово или нет ни
  оригинала, ни конверсий), тогда `attachmentType` мягко деградирует в none.

Аватар — отдельный случай: контракт требует непустую `avatarUrl` (дефолт), поэтому при удалённом
оригинале — fallback на дефолт «всё или ничего», без подмешивания конверсий удалённого оригинала.

## Что изменено

| # | Файл | Что изменено | Зачем |
|---|------|--------------|-------|
| 1 | `Modules/User/Application/Dto/MediaConversionView.php` | Новый DTO: kind/type — enum-ы Media, url, expiresAt | Конверсии аватара с закрытым набором значений |
| 2 | `Modules/Posts/Application/View/MediaConversionView.php` | Новый DTO: kind/type — enum-ы Media | То же для read-model записей/авторов |
| 3 | `Modules/Posts/Presentation/Http/Resource/MediaConversionResource.php` | Новый ресурс: kind — `MediaConversionKind`, type — union трёх enum-ов | Одна форма для вложений и аватара, enum-ы вместо строк |
| 4 | `Modules/User/Application/Dto/UserPublicProfileView.php` | + `avatarConversions: list<MediaConversionView>` | Профиль отдаёт набор конверсий аватара |
| 5 | `Modules/User/Application/Profile/UserPublicProfileAssembler.php` | Строит `avatarConversions` из `MediaUrlsResult`; fallback к дефолту → пустой набор; guard-clause | Разрешение конверсий аватара; сохранён `avatarUrl` для уведомлений |
| 6 | `Modules/Posts/Application/View/AuthorView.php` | + `avatarConversions`; фабрика `fromProfile()` | Централизует маппинг профиля → автора для обоих ассемблеров |
| 7 | `Modules/Posts/Application/View/PostViewAssembler.php` | `AuthorView::fromProfile()` вместо ручного `new`; в `mediaItem` — `expiresAt` + `conversions`; вложение сохраняется по конверсиям при удалённом оригинале | Автор с конверсиями; вложение отдаёт полный набор и не исчезает, если жив хоть один рендер |
| 8 | `Modules/Posts/Application/View/CommentViewAssembler.php` | `AuthorView::fromProfile()` в двух местах | Автор комментария с конверсиями |
| 9 | `Modules/Posts/Application/View/PostMediaItemView.php` | `url` → nullable; + `expiresAt`, + `conversions` | url = null при удалённом оригинале; полный набор во view |
| 10 | `Modules/Posts/Presentation/Http/Resource/PostMediaItemResource.php` | `url` → nullable; + `expiresAt`, + `conversions` | Полный набор в API вложения, url nullable |
| 11 | `Modules/Posts/Presentation/Http/Resource/AuthorResource.php` | + `avatarConversions` | Полный набор аватара в API |
| 12 | `Modules/Posts/Repository/PostMediaRepository.php` | Обновлён док-комментарий (eager-load теперь реально используется) | Убрал устаревшее «конверсии не читаются» |
| 13 | `Modules/Media/Application/Query/FindMediaUrl/FindMediaUrlHandler.php` | Обновлён док-комментарий | Полный набор теперь рендерится (аватар/лента) |
| 14 | `Modules/Media/Application/Contract/MediaUrlServiceContract.php` | Обновлён док-комментарий | То же |
| 15 | `packages/spiral-openapi` (PropertyMetadata, PhpAstParser, SchemaBuilder) | Поддержка union-типов: свойство-объединение классов/enum-ов выражается через `oneOf` (раньше схлопывалось до первого) | Чтобы `type` (union трёх enum-ов) давал корректный контракт, а не только image-тип |
| 16 | `app/config/openapi.php` | В `sourcePaths` добавлен `app/src/Modules/*/Domain/Enum` | Ресурсы ссылаются на доменные enum-ы напрямую (DRY, без дублей в Presentation); генератору нужно их резолвить. Схемы строятся только для реально используемых |
| 17 | `public/openapi/openapi.yml` | Перегенерирован `openapi:generate` | Публичный контракт: новые поля, `MediaConversionResource`, enum-схемы (`kind` → `$ref`, `type` → `oneOf`) |

Тесты:

| # | Файл | Что |
|---|------|-----|
| 18 | `tests/.../User/Application/UserApplicationTestCase.php` | Хелпер `persistThumbnailConversion()` |
| 19 | `tests/.../User/Application/GetUserPublicProfileHandlerTest.php` | Тест конверсий аватара (enum kind/type); пустой набор для дефолта/готового без конверсий; «всё или ничего» при удалённом оригинале |
| 20 | `tests/.../Posts/Http/PostsHttpTestCase.php` | Хелпер `attachThumbnailConversion()` |
| 21 | `tests/.../Posts/Http/GetPostHttpTest.php` | Вложение с полным набором; автор с конверсиями аватара; поле-контракт при пустом наборе; удалённый оригинал с конверсиями — вложение остаётся, url = null |
| 22 | `tests/.../Posts/Http/PostsOpenApiGenerationTest.php` | Контракт OpenAPI: `conversions`, `avatarConversions`, `MediaConversionResource`; `kind` → enum-`$ref`, `type` → `oneOf` трёх enum-ов |
| 23 | `packages/spiral-openapi` (fixtures `AccountStatus`, `UserResource`; `OpenApiGeneratorTest`) | Тест: union enum-ов в свойстве ресурса даёт `oneOf` ссылок на enum-схемы |

## Тесты и проверки

| Команда | Результат | Заметки |
|---------|-----------|---------|
| `make phpstan` | ✓ | app, level max, No errors |
| `composer -d packages/spiral-openapi test` / `phpstan` | ✓ | пакет генератора: 32 теста, PHPStan без ошибок |
| `php app.php openapi:generate` | ✓ | 31 операция, 26 схем (+enum-схемы конверсий и полей группы A) |
| `make qa` | ✓ | стиль + PHPStan + coverage: 1290 тестов, 4211 assert; покрытие 100.00% |

## Аудит остальных слабо типизированных отдаваемых полей + фикс

После правки конверсий прошёлся по всему презентационному слою (ресурсы, read-model view/DTO, payload
очередей/ws/push) тремя параллельными агентами по модулям — искал закрытые наборы, отдаваемые плоской
строкой при существующем enum/VO. Найдено и **исправлено 5 полей (группа A)** тем же способом (enum в
resource/view, убрать `->value`); в JSON значения не изменились, в OpenAPI появились enum-схемы.

| # | Поле | Файлы | Было → стало |
|---|------|-------|--------------|
| A1 | `status` | `Posts/Application/View/PostView.php`, `Posts/Presentation/Http/Resource/PostResource.php`, `PostViewAssembler.php` | `string` → `PostStatus` (убран `->value`) |
| A2 | `attachmentType` | те же файлы Posts | `string` → `AttachmentType` (убран `->value`) |
| A3 | `channel` | `Notifications/Presentation/Http/Resource/NotificationSettingResource.php` | `string` → `NotificationChannel` |
| A4 | `platform` | `Notifications/Presentation/Http/Resource/NotificationDeviceTokenResource.php` | `string` → `DevicePlatform` |
| A5 | `locale` | `User/Application/Dto/UserPublicProfileView.php`, `UserPublicProfileAssembler.php`, ripple → `Posts/Application/Notification/NotificationContentBuilder.php` | `string` → `Locale`; `build(Locale $recipientLocale)`, `->value` только на границе translator |

Тесты обновлены: `GetUserPublicProfileHandlerTest` (locale → `Locale::En/Ru`), `NotificationContentBuilderTest`
(`recipientLocale` → `Locale::En/Ru`). OpenAPI перегенерирован: 26 схем (+4 enum-схемы: `PostStatus`,
`AttachmentType`, `NotificationChannel`, `DevicePlatform`). `make qa` — 1290 тестов, покрытие 100%.

**Группа B — намеренно НЕ трогали.** Открытые (не enum) наборы, отдаваемые строкой: `NotificationResource.type`
/ `NotificationSettingResource.type` (VO `NotificationTypeCode` — открытый реестровый код) и `actionType`
(VO `NotificationActionType` — явно задокументирован как открытый deep-link код). Для открытого набора строка
на проводе уместна, а VO в HTTP-ресурсе генератор OpenAPI ошибочно сделал бы object-схемой. Payload-DTO
уведомлений намеренно примитивны под Valinor/outbox. Пограничное `Auth/TokenPairResource.tokenType` (`'Bearer'`)
— enum отсутствует, значение де-факто константа; оставлено. Media — образцово (всё уже на enum), Tags/Auth/System
— чисто.

## Унификация: «медиа с преобразованиями» одним типом и одним свойством

По правке-замечанию: медиа с конверсиями было размазано на 2–3 плоских свойства
(`AuthorView.avatarUrl` + `avatarConversions`; `PostMediaItemView.url` + `expiresAt` + `conversions`),
да ещё `MediaConversionView` дублировался в двух модулях, а `AuthorView::fromProfile` перемаппил один
в другой. Введён **один тип** «медиа с преобразованиями» (оригинал + конверсии) и он передаётся **одним
свойством** и в Application, и в ресурсах.

Ключевое уточнение по ходу ревью: отдельные read-model-типы `MediaView`/`MediaOriginalView`/
`MediaConversionView` оказались почти тождественными копиями `MediaUrlsResult`/`MediaUrlResult`/
`MediaConversionUrl` — отличие ровно одно: `expiresAt` (`DateTimeImmutable` → ISO-строка). То есть
`fromUrls` перегонял тип сам в себя ради форматирования одного поля. Поэтому проекция свёрнута:
**переиспользуется `MediaUrlsResult` напрямую**, а даты форматируются в ресурсах (прецедент есть —
`SessionResource`/`NotificationResource` так и делают). Форма контракта — `original` (вложенный объект)
+ `conversions`, «оригинал удалён» = `original: null`.

Итоговый набор:
- Удалены `Media/Application/Dto/MediaView.php`, `MediaOriginalView.php`, `MediaConversionView.php` (и два
  прежних дубля `MediaConversionView` в Posts/User) — ни одного нового Application-типа.
- `PostMediaItemView`/`AuthorView`/`UserPublicProfileView` несут `MediaUrlsResult` одним свойством
  (`media`/`avatar`); `AuthorView::fromProfile` просто копирует `avatar`.
- `UserPublicProfileAssembler` для дефолтного аватара собирает синтетический
  `MediaUrlsResult(original: MediaUrlResult(default, null), conversions: пустая коллекция)`; для реального —
  отдаёт результат `FindMediaUrl` как есть. `PostViewAssembler` кладёт `getUrls()` в view без проекции.
- Ресурсы-зеркала форматируют даты в ISO: `MediaResource::fromUrls(MediaUrlsResult)`,
  `MediaOriginalResource::fromUrl(MediaUrlResult)`, `MediaConversionResource::fromUrl(MediaConversionUrl)`.

Инвариант аватара: `MediaUrlsResult.original` nullable (ради вложений с удалённым оригиналом), но аватар
профиля всегда непустой (assembler подставляет дефолт). Единственный потребитель одной ссылки —
`PostNotifier` (снимок `NotificationActor`, требует непустой url); мост — `$actor->avatar->original->url
?? throw` (`??` в isset-семантике корректно ловит и null-original — PHPStan подтвердил, что nullsafe
здесь лишний).

**Изменение контракта API (ломающее для фронта).** Медиа/аватар — вложенный объект, форма как у
`MediaUrlsResult`:
`media[i] = {mediaId, position, media: {original: {url, expiresAt}|null, conversions:[…]}}`,
`author = {userId, name, avatar: {original: {url, expiresAt}|null, conversions:[…]}}`. OpenAPI: 28 схем
(`MediaResource`, `MediaOriginalResource`, `MediaConversionResource`; `media`/`avatar` → `$ref
MediaResource`, `MediaResource.original` → `oneOf[MediaOriginalResource, null]`). JSON и схема при сворачивании
проекции не изменились (форму задают ресурсы). Тесты: `GetPostHttpTest` и `PostsOpenApiGenerationTest` — без
изменений; `GetUserPublicProfileHandlerTest` — `avatar->original->url`, а пустые конверсии проверяются
`assertCount(0, …)` (теперь это коллекция, а не список). `make qa` — 1290 тестов, 4214 assert, покрытие 100%.

## Открытые вопросы

Нет. Уведомления/пуш/websocket сознательно оставлены на одиночной `NotificationActor.avatarUrl`
(денормализованный хранимый снимок; presigned-конверсии там протухали бы и раздували payload) — это
отдельный тип, не участвует в унификации `MediaView`.
