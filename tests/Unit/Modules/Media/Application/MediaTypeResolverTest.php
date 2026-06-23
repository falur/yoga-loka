<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Media\Application;

use App\Modules\Media\Application\Service\MediaTypeResolver;
use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Shared\Domain\Exception\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MediaTypeResolverTest extends TestCase
{
    public function testResolvesImageVideoAndAudio(): void
    {
        $resolver = new MediaTypeResolver();

        self::assertSame(MediaType::Image, $resolver->resolve(MediaMimeType::fromString('image/jpeg')));
        self::assertSame(MediaType::Video, $resolver->resolve(MediaMimeType::fromString('video/mp4')));
        self::assertSame(MediaType::Audio, $resolver->resolve(MediaMimeType::fromString('audio/mpeg')));
        self::assertSame(MediaType::Audio, $resolver->resolve(MediaMimeType::fromString('audio/mp4')));
    }

    #[DataProvider('documentMimeTypeProvider')]
    public function testResolvesDocumentByAllowedMimeTypes(string $mimeType): void
    {
        self::assertSame(
            MediaType::Document,
            new MediaTypeResolver()->resolve(MediaMimeType::fromString($mimeType)),
        );
    }

    /**
     * Каждая строка списка разрешённых `DOCUMENT_MIME_TYPES` проверяется отдельно: опечатка в любой из них
     * молча превратила бы формат в 422, и узкое покрытие нескольких MIME этого бы не заметило.
     *
     * @return list<array{string}>
     */
    public static function documentMimeTypeProvider(): array
    {
        return [
            ['application/pdf'],
            ['application/msword'],
            ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            ['text/plain'],
            ['application/rtf'],
            ['text/rtf'],
            ['application/vnd.oasis.opendocument.text'],
            ['application/vnd.oasis.opendocument.spreadsheet'],
            ['application/vnd.oasis.opendocument.presentation'],
            ['application/vnd.ms-excel'],
            ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            ['application/vnd.ms-powerpoint'],
            ['application/vnd.openxmlformats-officedocument.presentationml.presentation'],
            ['application/epub+zip'],
            ['application/x-fictionbook+xml'],
            ['image/vnd.djvu'],
            ['text/csv'],
            ['text/markdown'],
        ];
    }

    public function testResolvesDjvuAsDocumentNotImage(): void
    {
        // У DJVU MIME image/vnd.djvu — список документов проверяется раньше префикса image/,
        // поэтому он должен стать Document, а не Image.
        self::assertSame(
            MediaType::Document,
            new MediaTypeResolver()->resolve(MediaMimeType::fromString('image/vnd.djvu')),
        );
    }

    public function testNormalizesMimeBeforeClassifyingDocument(): void
    {
        $resolver = new MediaTypeResolver();

        self::assertSame(
            MediaType::Document,
            $resolver->resolve(MediaMimeType::fromString('text/markdown;charset=utf-8')),
        );
        self::assertSame(MediaType::Document, $resolver->resolve(MediaMimeType::fromString('APPLICATION/PDF')));
        self::assertSame(MediaType::Document, $resolver->resolve(MediaMimeType::fromString('TEXT/CSV')));
    }

    public function testResolvesOtherImageMimeAsImage(): void
    {
        // Список документов перехватывает только image/vnd.djvu; прочие image/* остаются Image.
        self::assertSame(MediaType::Image, new MediaTypeResolver()->resolve(MediaMimeType::fromString('image/png')));
    }

    #[DataProvider('unsupportedMimeTypeProvider')]
    public function testRejectsUnsupportedMimeTypes(string $mimeType): void
    {
        $this->expectException(ValidationException::class);

        new MediaTypeResolver()->resolve(MediaMimeType::fromString($mimeType));
    }

    /**
     * @return list<array{string}>
     */
    public static function unsupportedMimeTypeProvider(): array
    {
        return [
            ['font/woff2'],
            ['application/zip'],
            ['application/octet-stream'],
            // Office-формат с макросами намеренно не входит в список документов.
            ['application/vnd.ms-word.document.macroEnabled.12'],
        ];
    }
}
