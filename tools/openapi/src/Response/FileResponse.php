<?php

declare(strict_types=1);

namespace Tools\OpenApi\Response;

use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Tools\OpenApi\Exception\FileResponseException;
use Tools\OpenApi\Response\Enum\ContentType;
use Tools\OpenApi\Response\Enum\HttpHeader;

final class FileResponse implements ConvertsToHttpResponse
{
    use HasHttpResponseMetadata;

    private const string CONTENT_DISPOSITION_ATTACHMENT_TEMPLATE = 'attachment; filename="%s"';

    public function __construct(
        private readonly string $path,
        private readonly ContentType $contentType,
        private readonly string $filename,
    ) {
        $this->ensureReadableFile();
    }

    public function toResponse(): ResponseInterface
    {
        $this->ensureReadableFile();

        $fileStream = \fopen($this->path, 'r');

        if ($fileStream === false) {
            throw new FileResponseException(\sprintf('Не удалось открыть файл для HTTP-ответа: %s.', $this->path));
        }

        return new Response(
            status: $this->responseStatus()->value,
            headers: $this->responseHeaders(),
            body: $fileStream,
        );
    }

    /**
     * @return array<string, list<string>>
     */
    protected function defaultHeaders(): array
    {
        $headers = [
            HttpHeader::ContentType->value => [$this->contentType->value],
            HttpHeader::ContentDisposition->value => [\sprintf(
                self::CONTENT_DISPOSITION_ATTACHMENT_TEMPLATE,
                \addcslashes($this->filename, '\\"'),
            )],
        ];
        $fileSize = \is_file($this->path) ? \filesize($this->path) : false;

        if ($fileSize !== false) {
            $headers[HttpHeader::ContentLength->value] = [(string) $fileSize];
        }

        return $headers;
    }

    private function ensureReadableFile(): void
    {
        if (!\is_file($this->path)) {
            throw new FileResponseException(\sprintf('Файл для HTTP-ответа не найден: %s.', $this->path));
        }

        if (!\is_readable($this->path)) {
            throw new FileResponseException(\sprintf('Файл для HTTP-ответа недоступен для чтения: %s.', $this->path));
        }
    }
}
