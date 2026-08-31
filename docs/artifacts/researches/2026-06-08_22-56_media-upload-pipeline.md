---
title: Медиа — рабочий путь загрузки (presigned + конверсии) для аватара и других файлов
date: 2026-06-08 22:56
mode: normal
decision_mode: recommend_and_ask
status: draft
reviewer: none
sources:
  rules: docs/rules.md
  arch: docs/arch.md
---

# Медиа — рабочий путь загрузки (presigned + конверсии)

## Суть

Модуль `Media` собран только «внутри»: есть `Domain` (богатая модель со статусами
`waitingUpload → uploaded → processing → ready`, multipart-загрузка, конверсии),
`Repository`, `Infrastructure/Cycle` (typecast) и миграция таблиц. У модуля нет ни
одного публичного входа и нет ни одной реализации работы с файлами: отсутствуют
`Application` (сценарии), `Application/Contract`, `Infrastructure/FileService`,
`Infrastructure/Bootloader`, `Presentation`.

Задача исследования — выбрать конкретные решения, чтобы загрузка файла работала от
начала до конца (это нужно модулю `User` для загрузки аватара), по двум развилкам:
(1) библиотека и драйвер обработки изображений под PHP 8.5; (2) техника presigned
PUT и multipart поверх уже имеющихся `aws/aws-sdk-php` + flysystem, и как запускать
асинхронный шаг обработки.

Принятые ранее рамки (вход): загрузка прямая, клиент льёт байты в хранилище по
временной ссылке (presigned/multipart); обработка изображений (ресайз, превью
разных размеров) нужна сразу в MVP.

## Решение

### Выбранный вариант — кратко

- Обработка изображений: **Intervention Image v4 + драйвер Imagick** (в Docker
  добавляется PHP-расширение imagick).
- presigned PUT, multipart create/complete, presigned GET, серверные операции
  (проверка наличия, copy между бакетами, удаление): **через сырой
  `Aws\S3\S3Client`**, построенный из уже существующего типизированного
  `StorageConfig`. flysystem/Spiral Storage для presigned/multipart не используем.
- Асинхронная обработка после подтверждения загрузки: **через transactional
  outbox** (гарантированная постановка задачи в RabbitMQ в той же транзакции).

### Что уже готово (не нужно делать заново)

| Факт | Источник |
|---|---|
| `aws/aws-sdk-php` 3.381.2 уже в зависимостях — есть presigned и multipart | composer.lock |
| `league/flysystem` и `league/flysystem-aws-s3-v3` 3.34 — есть, но без presigned PUT / multipart | composer.lock |
| Бакеты `media-upload` / `media-private` / `media-public` настроены | app/config/storage.php:84-101 |
| Бакеты автоматически создаются в dev | docker/docker-compose.dev.yml:148; .env.sample:70,73,76 |
| `StorageServerConfig` уже отдаёт S3-доступ (region, key, secret, endpoint, bucket, options) | app/src/Shared/Infrastructure/Configuration/Storage/StorageServerConfig.php:9-23 |
| Доменная модель загрузки готова (статусы, multipart, конверсии, миграция) | Media/Domain/**; app/database/migrations/20260521.184100_0_create_media_domain_tables.php |
| `MediaStorage` enum уже знает бакеты `media-upload/private/public` | Media/Domain/Enum/MediaStorage.php |

### Развилка 1 — обработка изображений

| Кандидат | Версия | PHP | Драйвер/расширение | Замечание |
|---|---|---|---|---|
| Intervention Image v4 | 4.1.3 (03.06.2026) | ^8.3 → покрывает 8.5 | GD / Imagick / libvips (абстракция) | нужен mbstring (есть), современный, активно поддерживается |
| Imagine | 1.5.4 (04.06.2026) | >=7.1 | GD / Imagick / Gmagick | старее, без строгой типизации, без преимуществ для задачи |
| Сырой GD / Imagick | — | — | прямое расширение | без абстракции, больше ручного кода |

Источник версий: packagist (repo.packagist.org/p2/intervention/image.json,
.../imagine/imagine.json). Требования Intervention: контекст7 `/intervention/image`
(GD/Imagick/libvips, mbstring).

Выбрано: **Intervention Image v4 + Imagick**. Библиотека абстрагирует драйвер,
поэтому код обработки не зависит от конкретного расширения. Imagick даёт лучшее
качество масштабирования и больше форматов (включая AVIF/HEIC).

Риск рядом с решением: в текущем `docker/Dockerfile:22-34` **нет ни одного
графического расширения** (только curl, mbstring, pgsql, redis, sockets, xml,
xdebug, zip, bcmath, intl). `imagick` — стороннее PECL-расширение, и его готовность
под PHP 8.5 на Ubuntu 26.04 (`php8.5-imagick` в apt либо сборка через PECL +
`libmagickwand-dev`) **надо проверить при сборке образа**. Закрытие риска: проверяем
доступность на этапе реализации; если пакет/сборка не готовы под 8.5 — временно
переключаем драйвер Intervention на GD (`php8.5-gd`, ядерное расширение) **без
изменения кода обработки**, потому что драйвер абстрагирован. То есть риск не
блокирует архитектуру, а только влияет на содержимое Dockerfile.

### Развилка 2 — presigned / multipart и запуск обработки

Технический вывод по presigned: `league/flysystem-aws-s3-v3` 3.34 не предоставляет
publicAPI для presigned PUT и для multipart (create/upload-part/complete);
`temporaryUrl()` покрывает только presigned GET. Поэтому presigned PUT, multipart и
серверные операции выполняем через сырой `Aws\S3\S3Client`
(`createPresignedRequest()`, `createMultipartUpload()`/`uploadPart` presigned/
`completeMultipartUpload()`, `headObject`, `copyObject`, `deleteObject`). Клиент
строим из значений S3-сервера типизированного `StorageConfig` — отдельный
config-файл не нужен (`StorageServerConfig` уже содержит endpoint/key/secret/region/
options с `use_path_style_endpoint` для MinIO).

Обычный путь аватара — одиночный presigned PUT. Multipart остаётся для крупных
файлов (видео); порог выбирается по заявленному размеру файла на шаге запроса
загрузки (S3 требует часть ≥ 5 MB).

Поток загрузки:

```text
1. RequestUpload (Command)
   - создаёт Media (waitingUpload), выбирает single|multipart по размеру
   - просит S3MediaFileService presigned PUT-ссылку(и) на бакет media-upload
   - возвращает ссылку(и) клиенту
2. Клиент льёт байты напрямую в MinIO по presigned-ссылке
3. CompleteUpload (Command, #[Transactional])
   - headObject: проверяет, что объект на месте в media-upload
   - Media->markUploaded() (uploaded)
   - OutboxEventStoreContract::add(MediaUploaded ...)  ← в той же транзакции
   - entityManager->run()
4. Outbox relay → Job в RabbitMQ → ProcessMedia (Command)
   - copyObject из media-upload в media-public/private
   - Intervention(Imagick): конверсии/превью, заливка результатов
   - Media->markReady() (ready)
5. User/Application/SetAvatar → Media/Application (CheckMediaIsImage, GetMediaUrl,
   MakePermanent) → User/Domain/User::setAvatar
```

Запуск обработки выбран **через outbox**. `CompleteUpload` помечает `uploaded` и в
той же транзакции кладёт интеграционное сообщение (реализация
`App\Modules\Outbox\Application\Message\OutboxMessage`) в
`OutboxEventStoreContract::add(...)`; relay ставит `ProcessMediaJob` в RabbitMQ через
`OutboxJobRegistry` (message → Job), Job диспатчит `ProcessMediaCommand`. Это даёт
транзакционную гарантию (нет окна потери Job между commit и push) и переиспользует
уже готовую инфраструктуру outbox (`OutboxEventStoreContract`, relay,
`OutboxJobRegistry` — Outbox/Application/Contract/**, Outbox/Infrastructure/**).

Замечание о согласованности с документами рядом с решением: `docs/arch.md:482-486` и
`docs/rules.md:86` сейчас описывают outbox как механизм для **внешних** побочных
эффектов (Centrifugo, email, push, webhooks). Обработка медиа — внутренний
технический шаг. Решение принято пользователем сознательно ради транзакционной
гарантии; при планировании это стоит отразить мелкой правкой формулировки (outbox
допускается и для внутренних шагов, которым нужна гарантированная доставка после
commit), чтобы код не противоречил правилам. Это не блокер, а пункт для `eda-plan`.

### Архитектура (какие слои затрагиваются)

```text
Media/
  Application/
    Contract/        MediaFileServiceContract (+ контракт обработки изображений)
    Command/Media/   RequestUpload, CompleteUpload (+ CompleteMultipart), ProcessMedia,
                     DeleteMedia, MakePermanent
    Query/Media/     GetMediaUrl, CheckMediaIsImage / CheckMediaExists
    Message/         MediaUploaded (реализует Outbox OutboxMessage)
  Infrastructure/
    FileService/     S3MediaFileService (Aws\S3\S3Client из StorageConfig),
                     ImagickMediaImageProcessor (Intervention Image v4)
    Bootloader/      MediaBootloader (контракт → реализация)
  Presentation/
    Http/            контроллеры запроса/подтверждения загрузки (Filter, Resource, Response)
    Job/             ProcessMediaJob (адаптер очереди → ProcessMediaCommand)
docker/Dockerfile    + расширение imagick (с проверкой и GD-фолбэком)
```

Слой `Domain` и `Repository` не меняются — модель и доменные методы статусов уже
есть (Media/Domain/Entity/Media.php). `StorageConfig` переиспользуется, новый
config-файл не вводится.

## Ответы на вопросы

| Вопрос | Ответ пользователя |
|---|---|
| Нужны ли конверсии (ресайз/превью) в MVP аватара? | Сразу с конверсиями |
| Как клиент грузит файл — presigned-прямая или через API? | Прямая загрузка (presigned/multipart) |
| Библиотека и драйвер обработки изображений? | Intervention Image v4 + Imagick |
| Как запускать асинхронный шаг обработки? | Через outbox |

## Итог

Подход к реализации (без пошагового плана):

- Добавить `intervention/image` ^4 и расширение `imagick` в Docker-образ; при сборке
  проверить готовность под PHP 8.5, при проблеме временно использовать драйвер GD
  без изменения кода обработки.
- Завести `MediaFileServiceContract` + контракт обработки изображений в
  `Application/Contract`; реализовать `S3MediaFileService` (presigned PUT, multipart,
  presigned GET, headObject/copyObject/deleteObject) поверх `Aws\S3\S3Client`,
  построенного из `StorageConfig`, и `ImagickMediaImageProcessor` на Intervention v4.
- Зарегистрировать связки в `MediaBootloader`.
- Реализовать сценарии `Application` (RequestUpload, CompleteUpload/CompleteMultipart,
  ProcessMedia, GetMediaUrl, CheckMediaIsImage, DeleteMedia, MakePermanent) и
  HTTP-контроллеры запроса/подтверждения загрузки.
- Обработку запускать через outbox: сообщение `MediaUploaded` в `CompleteUpload` →
  `ProcessMediaJob` через `OutboxJobRegistry` → `ProcessMediaCommand`. При
  планировании уточнить формулировку про outbox в arch.md/rules.md (разрешить
  внутренние гарантированные события).
- После рабочего пути загрузки модуль `User` цепляет готовый media через
  `Media/Application` (SetAvatar → CheckMediaIsImage/GetMediaUrl/MakePermanent).

Следующий шаг — `eda-plan` на модуль `Media` (presigned-загрузка + конверсии через
outbox) с разбивкой по слоям выше.
