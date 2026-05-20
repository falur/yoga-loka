# OpenAPI Tools

`yoga-loka/openapi-tools` генерирует OpenAPI `3.1.0` из типизированного HTTP-слоя Spiral-приложения: controller route attributes, Filter DTO, Resource DTO, enum-ов и PHPDoc `@return` с generic response wrappers.

## Подключение

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "tools/openapi",
      "options": {
        "symlink": true
      }
    }
  ],
  "require": {
    "yoga-loka/openapi-tools": "dev-main"
  }
}
```

## Минимальный конфиг генерации

```php
use Tools\OpenApi\Config\OpenApiGeneratorConfig;
use Tools\OpenApi\Config\ResponseWrapperMapping;
use Tools\OpenApi\OpenApiGenerator;

/** @var \Spiral\Translator\TranslatorInterface $translator */
(new OpenApiGenerator(translator: $translator))->generate(new OpenApiGeneratorConfig(
    projectRoot: __DIR__,
    sourcePaths: [__DIR__ . '/app/src/Endpoint/Api/V1'],
    apiNamespace: 'App\\Endpoint\\Api\\V1',
    routePrefix: '/api/v1',
    outputFile: __DIR__ . '/public/openapi/openapi.yml',
    title: 'API',
    version: '1.0.0',
    responseWrapperMapping: new ResponseWrapperMapping(
        dataResponseClass: DataResponse::class,
        collectionResponseClass: CollectionResponse::class,
        paginationResponseClass: PaginationResponse::class,
        errorResponseClass: ErrorResponse::class,
    ),
));
```

## Bootloader и переводы

`OpenApiToolsBootloader` подключает каталог переводов `tools/openapi/locale` и создаёт `OpenApiGenerator` с текущим `Spiral\Translator\TranslatorInterface`.

```php
use Tools\OpenApi\Bootloader\OpenApiToolsBootloader;

return [
    OpenApiToolsBootloader::class,
    OpenApiBootloader::class,
];
```

Стандартные описания response переводятся на языке генерации YAML:

| Ключ | Locale `en` | Locale `ru` |
|---|---|---|
| `yoga_loka.openapi.successful_response` | `Successful response.` | `Успешный ответ.` |
| `yoga_loka.openapi.api_error` | `API error.` | `Ошибка API.` |

OpenAPI YAML — статический файл, поэтому для другого языка его нужно сгенерировать при другом текущем locale.

## Метаданные операции

`#[OpenApi]` не обязателен. Если он есть, генератор берёт из него `operationId` и описание.

```php
#[OpenApi(id: 'health', description: 'Проверка работоспособности API')]
```

Если атрибута нет, `operationId` строится из route name, а описание берётся из PHPDoc summary метода.

Служебные route methods, которые не должны попадать в OpenAPI-спецификацию, помечаются так:

```php
#[OpenApi(ignore: true)]
```

## Требования к controller response

Метод controller-а должен возвращать базовый response wrapper и иметь PHPDoc generic:

```php
/**
 * @return DataResponse<HealthResource>
 */
public function show(): DataResponse
```

Поддерживаются `DataResponse<T>`, `CollectionResponse<T>` и `PaginationResponse<T>`. Конкретные FQCN этих классов передаются через `ResponseWrapperMapping`, поэтому пакет не зависит от namespace приложения.

## Response helpers

Пакет содержит готовые response wrappers:

- `DataResponse<T>` — один ресурс.
- `CollectionResponse<T>` — список без пагинации.
- `PaginationResponse<T>` — список с `PaginationMetaResponse`.
- `ErrorResponse` — JSON-ошибка с `message` и `code`.
- `ValidationErrorResponse` — JSON-ошибка валидации с `message`, `code` и списком `errors`.
- `HtmlResponse` — HTML-ответ.
- `FileContentResponse` — готовый файловый/текстовый content в body, отдаётся inline.
- `FileResponse` — локальный файл по path, отдаётся как download.

Все response DTO реализуют `ConvertsToHttpResponse`. `DataResponse`, `CollectionResponse`, `PaginationResponse` и `ErrorResponse` являются JSON response DTO: они сериализуют только public payload-поля, а status и headers настраиваются fluent-методами и не попадают в JSON body.

```php
return (new PaginationResponse(data: $items, meta: $meta))
    ->withHeader(new HttpHeaderValue(name: HttpHeader::XRequestId, value: $requestId))
    ->withStatus(HttpStatus::Created);
```

`withHeader()` заменяет один header, `withAddedHeader()` добавляет значение к header-у, `setHeaders()` полностью заменяет набор headers. По умолчанию JSON response содержит `Content-Type: application/json; charset=utf-8`; `setHeaders()` может убрать этот default, если ответу нужен полностью свой набор header-ов.

```php
return (new DataResponse(data: $resource))
    ->setHeaders(
        new HttpHeaderValue(name: HttpHeader::CacheControl, value: 'no-store'),
        new HttpHeaderValue(name: HttpHeader::XRequestId, value: $requestId),
    );
```

Filter-валидация может использовать `ValidationErrorResponse`, чтобы сохранить общий формат ошибки и список найденных ошибок:

```json
{
  "message": "Ошибка валидации",
  "code": 422,
  "errors": [
    {
      "field": "email",
      "message": "Некорректный email"
    }
  ]
}
```

`FileContentResponse` по умолчанию выставляет `Content-Disposition: inline`; `FileResponse` по умолчанию выставляет `Content-Disposition: attachment; filename="..."` и `Content-Length`, если размер файла доступен.
Генератор OpenAPI берёт media type из аргумента `contentType: ContentType::*`: для `FileContentResponse` схема ответа — `type: string`, для `FileResponse` — `type: string, format: binary`.

```php
return new FileContentResponse(content: $yaml, contentType: ContentType::Yaml);
```

```php
return new FileResponse(path: $path, contentType: ContentType::Pdf, filename: 'invoice.pdf');
```

Чтобы Spiral отдавал response DTO как HTTP-ответы, зарегистрируйте `HttpResponseInterceptor` в domain pipeline. Если подключён `tools/api-error`, `HttpResponseInterceptor` должен стоять перед `ApiExceptionInterceptor`:

```php
use Tools\ApiError\Interceptor\ApiExceptionInterceptor;
use Tools\OpenApi\Response\Interceptor\HttpResponseInterceptor;

protected const array INTERCEPTORS = [
    CycleInterceptor::class,
    GridInterceptor::class,
    GuardInterceptor::class,
    HttpResponseInterceptor::class,
    ApiExceptionInterceptor::class,
];
```

### HttpStatus

`HttpStatus` описывает стандартные HTTP-коды:

- `Continue` = `100`
- `SwitchingProtocols` = `101`
- `Processing` = `102`
- `EarlyHints` = `103`
- `Ok` = `200`
- `Created` = `201`
- `Accepted` = `202`
- `NonAuthoritativeInformation` = `203`
- `NoContent` = `204`
- `ResetContent` = `205`
- `PartialContent` = `206`
- `MultiStatus` = `207`
- `AlreadyReported` = `208`
- `ImUsed` = `226`
- `MultipleChoices` = `300`
- `MovedPermanently` = `301`
- `Found` = `302`
- `SeeOther` = `303`
- `NotModified` = `304`
- `UseProxy` = `305`
- `TemporaryRedirect` = `307`
- `PermanentRedirect` = `308`
- `BadRequest` = `400`
- `Unauthorized` = `401`
- `PaymentRequired` = `402`
- `Forbidden` = `403`
- `NotFound` = `404`
- `MethodNotAllowed` = `405`
- `NotAcceptable` = `406`
- `ProxyAuthenticationRequired` = `407`
- `RequestTimeout` = `408`
- `Conflict` = `409`
- `Gone` = `410`
- `LengthRequired` = `411`
- `PreconditionFailed` = `412`
- `PayloadTooLarge` = `413`
- `UriTooLong` = `414`
- `UnsupportedMediaType` = `415`
- `RangeNotSatisfiable` = `416`
- `ExpectationFailed` = `417`
- `ImATeapot` = `418`
- `MisdirectedRequest` = `421`
- `UnprocessableEntity` = `422`
- `Locked` = `423`
- `FailedDependency` = `424`
- `TooEarly` = `425`
- `UpgradeRequired` = `426`
- `PreconditionRequired` = `428`
- `TooManyRequests` = `429`
- `RequestHeaderFieldsTooLarge` = `431`
- `UnavailableForLegalReasons` = `451`
- `InternalServerError` = `500`
- `NotImplemented` = `501`
- `BadGateway` = `502`
- `ServiceUnavailable` = `503`
- `GatewayTimeout` = `504`
- `HttpVersionNotSupported` = `505`
- `VariantAlsoNegotiates` = `506`
- `InsufficientStorage` = `507`
- `LoopDetected` = `508`
- `NotExtended` = `510`
- `NetworkAuthenticationRequired` = `511`

### HttpHeader

`HttpHeader` описывает стандартные и де-факто распространённые HTTP header names. Vendor/custom headers технически не ограничены стандартом, поэтому при появлении проектного header-а его нужно добавить отдельным enum case.

- `Accept` = `Accept`
- `AcceptCharset` = `Accept-Charset`
- `AcceptEncoding` = `Accept-Encoding`
- `AcceptLanguage` = `Accept-Language`
- `AcceptPatch` = `Accept-Patch`
- `AcceptPost` = `Accept-Post`
- `AcceptRanges` = `Accept-Ranges`
- `AccessControlAllowCredentials` = `Access-Control-Allow-Credentials`
- `AccessControlAllowHeaders` = `Access-Control-Allow-Headers`
- `AccessControlAllowMethods` = `Access-Control-Allow-Methods`
- `AccessControlAllowOrigin` = `Access-Control-Allow-Origin`
- `AccessControlExposeHeaders` = `Access-Control-Expose-Headers`
- `AccessControlMaxAge` = `Access-Control-Max-Age`
- `AccessControlRequestHeaders` = `Access-Control-Request-Headers`
- `AccessControlRequestMethod` = `Access-Control-Request-Method`
- `Age` = `Age`
- `Allow` = `Allow`
- `AltSvc` = `Alt-Svc`
- `Authorization` = `Authorization`
- `CacheControl` = `Cache-Control`
- `ClearSiteData` = `Clear-Site-Data`
- `Connection` = `Connection`
- `ContentDisposition` = `Content-Disposition`
- `ContentEncoding` = `Content-Encoding`
- `ContentLanguage` = `Content-Language`
- `ContentLength` = `Content-Length`
- `ContentLocation` = `Content-Location`
- `ContentRange` = `Content-Range`
- `ContentSecurityPolicy` = `Content-Security-Policy`
- `ContentSecurityPolicyReportOnly` = `Content-Security-Policy-Report-Only`
- `ContentType` = `Content-Type`
- `Cookie` = `Cookie`
- `CrossOriginEmbedderPolicy` = `Cross-Origin-Embedder-Policy`
- `CrossOriginOpenerPolicy` = `Cross-Origin-Opener-Policy`
- `CrossOriginResourcePolicy` = `Cross-Origin-Resource-Policy`
- `Date` = `Date`
- `DeviceMemory` = `Device-Memory`
- `Digest` = `Digest`
- `Dnt` = `DNT`
- `ETag` = `ETag`
- `Expect` = `Expect`
- `Expires` = `Expires`
- `Forwarded` = `Forwarded`
- `From` = `From`
- `Host` = `Host`
- `IfMatch` = `If-Match`
- `IfModifiedSince` = `If-Modified-Since`
- `IfNoneMatch` = `If-None-Match`
- `IfRange` = `If-Range`
- `IfUnmodifiedSince` = `If-Unmodified-Since`
- `KeepAlive` = `Keep-Alive`
- `LastModified` = `Last-Modified`
- `Link` = `Link`
- `Location` = `Location`
- `MaxForwards` = `Max-Forwards`
- `Origin` = `Origin`
- `PermissionsPolicy` = `Permissions-Policy`
- `Pragma` = `Pragma`
- `ProxyAuthenticate` = `Proxy-Authenticate`
- `ProxyAuthorization` = `Proxy-Authorization`
- `Range` = `Range`
- `Referer` = `Referer`
- `ReferrerPolicy` = `Referrer-Policy`
- `RetryAfter` = `Retry-After`
- `SecFetchDest` = `Sec-Fetch-Dest`
- `SecFetchMode` = `Sec-Fetch-Mode`
- `SecFetchSite` = `Sec-Fetch-Site`
- `SecFetchUser` = `Sec-Fetch-User`
- `SecWebSocketAccept` = `Sec-WebSocket-Accept`
- `SecWebSocketExtensions` = `Sec-WebSocket-Extensions`
- `SecWebSocketKey` = `Sec-WebSocket-Key`
- `SecWebSocketProtocol` = `Sec-WebSocket-Protocol`
- `SecWebSocketVersion` = `Sec-WebSocket-Version`
- `Server` = `Server`
- `ServerTiming` = `Server-Timing`
- `ServiceWorkerAllowed` = `Service-Worker-Allowed`
- `SetCookie` = `Set-Cookie`
- `SourceMap` = `SourceMap`
- `StrictTransportSecurity` = `Strict-Transport-Security`
- `Te` = `TE`
- `TimingAllowOrigin` = `Timing-Allow-Origin`
- `Trailer` = `Trailer`
- `TransferEncoding` = `Transfer-Encoding`
- `Upgrade` = `Upgrade`
- `UpgradeInsecureRequests` = `Upgrade-Insecure-Requests`
- `UserAgent` = `User-Agent`
- `Vary` = `Vary`
- `Via` = `Via`
- `WantDigest` = `Want-Digest`
- `Warning` = `Warning`
- `WwwAuthenticate` = `WWW-Authenticate`
- `XContentTypeOptions` = `X-Content-Type-Options`
- `XForwardedFor` = `X-Forwarded-For`
- `XForwardedHost` = `X-Forwarded-Host`
- `XForwardedProto` = `X-Forwarded-Proto`
- `XFrameOptions` = `X-Frame-Options`
- `XRealIp` = `X-Real-IP`
- `XRequestId` = `X-Request-ID`
- `XXssProtection` = `X-XSS-Protection`

### ContentType

`ContentType` описывает MIME-типы, которые нужны response wrappers и типовым HTTP-ответам. Полный IANA registry шире и пополняется, поэтому редкий vendor/custom MIME-type добавляется отдельным enum case.

- `AtomXml` = `application/atom+xml; charset=utf-8`
- `Avif` = `image/avif`
- `Binary` = `application/octet-stream`
- `Bmp` = `image/bmp`
- `Brotli` = `application/x-brotli`
- `Css` = `text/css; charset=utf-8`
- `Csv` = `text/csv; charset=utf-8`
- `Doc` = `application/msword`
- `Docx` = `application/vnd.openxmlformats-officedocument.wordprocessingml.document`
- `Epub` = `application/epub+zip`
- `EventStream` = `text/event-stream; charset=utf-8`
- `FormUrlEncoded` = `application/x-www-form-urlencoded`
- `Gif` = `image/gif`
- `Gzip` = `application/gzip`
- `Html` = `text/html; charset=utf-8`
- `Ics` = `text/calendar; charset=utf-8`
- `Ico` = `image/vnd.microsoft.icon`
- `JavaArchive` = `application/java-archive`
- `JavaScript` = `text/javascript; charset=utf-8`
- `Jpeg` = `image/jpeg`
- `Json` = `application/json; charset=utf-8`
- `JsonApi` = `application/vnd.api+json; charset=utf-8`
- `JsonPatch` = `application/json-patch+json; charset=utf-8`
- `JsonSeq` = `application/json-seq; charset=utf-8`
- `LdJson` = `application/ld+json; charset=utf-8`
- `Markdown` = `text/markdown; charset=utf-8`
- `Mp3` = `audio/mpeg`
- `Mp4` = `video/mp4`
- `Mpeg` = `video/mpeg`
- `MultipartFormData` = `multipart/form-data`
- `OggAudio` = `audio/ogg`
- `OpenDocumentPresentation` = `application/vnd.oasis.opendocument.presentation`
- `OpenDocumentSpreadsheet` = `application/vnd.oasis.opendocument.spreadsheet`
- `OpenDocumentText` = `application/vnd.oasis.opendocument.text`
- `Otf` = `font/otf`
- `Pdf` = `application/pdf`
- `PlainText` = `text/plain; charset=utf-8`
- `Png` = `image/png`
- `Ppt` = `application/vnd.ms-powerpoint`
- `Pptx` = `application/vnd.openxmlformats-officedocument.presentationml.presentation`
- `ProblemJson` = `application/problem+json; charset=utf-8`
- `Rar` = `application/vnd.rar`
- `RssXml` = `application/rss+xml; charset=utf-8`
- `SevenZip` = `application/x-7z-compressed`
- `Svg` = `image/svg+xml`
- `Tar` = `application/x-tar`
- `Tiff` = `image/tiff`
- `Ttf` = `font/ttf`
- `Wasm` = `application/wasm`
- `WebManifest` = `application/manifest+json; charset=utf-8`
- `WebmAudio` = `audio/webm`
- `WebmVideo` = `video/webm`
- `Webp` = `image/webp`
- `Woff` = `font/woff`
- `Woff2` = `font/woff2`
- `XHtml` = `application/xhtml+xml; charset=utf-8`
- `Xls` = `application/vnd.ms-excel`
- `Xlsx` = `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`
- `Xml` = `application/xml; charset=utf-8`
- `Yaml` = `application/yaml; charset=utf-8`
- `Zip` = `application/zip`

## Проверка пакета в YogaLoka

```bash
composer tools:openapi:qa
```
