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
    public function testResolvesImageAndVideo(): void
    {
        $resolver = new MediaTypeResolver();

        self::assertSame(MediaType::Image, $resolver->resolve(MediaMimeType::fromString('image/jpeg')));
        self::assertSame(MediaType::Video, $resolver->resolve(MediaMimeType::fromString('video/mp4')));
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
        return [['audio/mpeg'], ['application/pdf'], ['text/plain']];
    }
}
