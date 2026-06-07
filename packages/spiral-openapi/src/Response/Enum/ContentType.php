<?php

declare (strict_types=1);

namespace GianTiaga\SpiralOpenApi\Response\Enum;

enum ContentType: string
{
    case AtomXml = 'application/atom+xml; charset=utf-8';
    case Avif = 'image/avif';
    case Binary = 'application/octet-stream';
    case Bmp = 'image/bmp';
    case Brotli = 'application/x-brotli';
    case Css = 'text/css; charset=utf-8';
    case Csv = 'text/csv; charset=utf-8';
    case Doc = 'application/msword';
    case Docx = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    case Epub = 'application/epub+zip';
    case EventStream = 'text/event-stream; charset=utf-8';
    case FormUrlEncoded = 'application/x-www-form-urlencoded';
    case Gif = 'image/gif';
    case Gzip = 'application/gzip';
    case Html = 'text/html; charset=utf-8';
    case Ics = 'text/calendar; charset=utf-8';
    case Ico = 'image/vnd.microsoft.icon';
    case JavaArchive = 'application/java-archive';
    case JavaScript = 'text/javascript; charset=utf-8';
    case Jpeg = 'image/jpeg';
    case Json = 'application/json; charset=utf-8';
    case JsonApi = 'application/vnd.api+json; charset=utf-8';
    case JsonPatch = 'application/json-patch+json; charset=utf-8';
    case JsonSeq = 'application/json-seq; charset=utf-8';
    case LdJson = 'application/ld+json; charset=utf-8';
    case Markdown = 'text/markdown; charset=utf-8';
    case Mp3 = 'audio/mpeg';
    case Mp4 = 'video/mp4';
    case Mpeg = 'video/mpeg';
    case MultipartFormData = 'multipart/form-data';
    case OggAudio = 'audio/ogg';
    case OpenDocumentPresentation = 'application/vnd.oasis.opendocument.presentation';
    case OpenDocumentSpreadsheet = 'application/vnd.oasis.opendocument.spreadsheet';
    case OpenDocumentText = 'application/vnd.oasis.opendocument.text';
    case Otf = 'font/otf';
    case Pdf = 'application/pdf';
    case PlainText = 'text/plain; charset=utf-8';
    case Png = 'image/png';
    case Ppt = 'application/vnd.ms-powerpoint';
    case Pptx = 'application/vnd.openxmlformats-officedocument.presentationml.presentation';
    case ProblemJson = 'application/problem+json; charset=utf-8';
    case Rar = 'application/vnd.rar';
    case RssXml = 'application/rss+xml; charset=utf-8';
    case SevenZip = 'application/x-7z-compressed';
    case Svg = 'image/svg+xml';
    case Tar = 'application/x-tar';
    case Tiff = 'image/tiff';
    case Ttf = 'font/ttf';
    case Wasm = 'application/wasm';
    case WebManifest = 'application/manifest+json; charset=utf-8';
    case WebmAudio = 'audio/webm';
    case WebmVideo = 'video/webm';
    case Webp = 'image/webp';
    case Woff = 'font/woff';
    case Woff2 = 'font/woff2';
    case XHtml = 'application/xhtml+xml; charset=utf-8';
    case Xls = 'application/vnd.ms-excel';
    case Xlsx = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    case Xml = 'application/xml; charset=utf-8';
    case Yaml = 'application/yaml; charset=utf-8';
    case Zip = 'application/zip';
}
