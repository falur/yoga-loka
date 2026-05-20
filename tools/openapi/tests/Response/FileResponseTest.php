<?php

declare(strict_types=1);

namespace Tools\OpenApi\Tests\Response;

use PHPUnit\Framework\TestCase;
use Tools\OpenApi\Exception\FileResponseException;
use Tools\OpenApi\Response\Enum\ContentType;
use Tools\OpenApi\Response\Enum\HttpHeader;
use Tools\OpenApi\Response\Enum\HttpStatus;
use Tools\OpenApi\Response\FileContentResponse;
use Tools\OpenApi\Response\FileResponse;
use Tools\OpenApi\Response\HtmlResponse;
use Tools\OpenApi\Response\HttpHeaderValue;

final class FileResponseTest extends TestCase
{
    public function testFileContentResponseBuildsInlineResponse(): void
    {
        $httpResponse = new FileContentResponse(content: 'file body', contentType: ContentType::PlainText)
            ->withStatus(HttpStatus::Created)
            ->toResponse();

        self::assertSame(HttpStatus::Created->value, $httpResponse->getStatusCode());
        self::assertSame(ContentType::PlainText->value, $httpResponse->getHeaderLine(HttpHeader::ContentType->value));
        self::assertSame('inline', $httpResponse->getHeaderLine(HttpHeader::ContentDisposition->value));
        self::assertSame('file body', (string) $httpResponse->getBody());
    }

    public function testFileContentResponseCanReplaceDefaultHeaders(): void
    {
        $httpResponse = new FileContentResponse(content: 'file body', contentType: ContentType::PlainText)
            ->setHeaders(new HttpHeaderValue(name: HttpHeader::CacheControl, value: 'no-store'))
            ->toResponse();

        self::assertFalse($httpResponse->hasHeader(HttpHeader::ContentType->value));
        self::assertFalse($httpResponse->hasHeader(HttpHeader::ContentDisposition->value));
        self::assertSame('no-store', $httpResponse->getHeaderLine(HttpHeader::CacheControl->value));
    }

    public function testFileResponseBuildsDownloadResponse(): void
    {
        $temporaryFilePath = $this->createTemporaryFile();

        try {
            $httpResponse = new FileResponse(
                path: $temporaryFilePath,
                contentType: ContentType::PlainText,
                filename: 'export.txt',
            )
                ->withStatus(HttpStatus::Accepted)
                ->toResponse();

            self::assertSame(HttpStatus::Accepted->value, $httpResponse->getStatusCode());
            self::assertSame(ContentType::PlainText->value, $httpResponse->getHeaderLine(HttpHeader::ContentType->value));
            self::assertSame('attachment; filename="export.txt"', $httpResponse->getHeaderLine(HttpHeader::ContentDisposition->value));
            self::assertSame((string) \strlen('file body'), $httpResponse->getHeaderLine(HttpHeader::ContentLength->value));
            self::assertSame('file body', (string) $httpResponse->getBody());
        } finally {
            \unlink($temporaryFilePath);
        }
    }

    public function testFileResponseFailsWhenFileDoesNotExist(): void
    {
        $this->expectException(FileResponseException::class);

        new FileResponse(
            path: __DIR__ . '/../Fixtures/missing-file.txt',
            contentType: ContentType::PlainText,
            filename: 'export.txt',
        )->toResponse();
    }

    public function testHtmlResponseBuildsInlineHtmlResponse(): void
    {
        $httpResponse = new HtmlResponse(html: '<html></html>')->toResponse();

        self::assertSame(ContentType::Html->value, $httpResponse->getHeaderLine(HttpHeader::ContentType->value));
        self::assertSame('inline', $httpResponse->getHeaderLine(HttpHeader::ContentDisposition->value));
        self::assertSame('<html></html>', (string) $httpResponse->getBody());
    }

    private function createTemporaryFile(): string
    {
        $temporaryFilePath = \tempnam(\sys_get_temp_dir(), 'openapi-file-response-');

        if ($temporaryFilePath === false) {
            self::fail('Не удалось создать временный файл.');
        }

        if (\file_put_contents($temporaryFilePath, 'file body') === false) {
            self::fail('Не удалось записать временный файл.');
        }

        return $temporaryFilePath;
    }
}
