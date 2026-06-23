<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Media\Domain\Collection;

use App\Modules\Media\Domain\Collection\MediaMimeTypeCollection;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use PHPUnit\Framework\TestCase;

final class MediaMimeTypeCollectionTest extends TestCase
{
    public function testContainsMimeTypeMatchesByBaseValueIgnoringParameters(): void
    {
        $allowed = new MediaMimeTypeCollection([MediaMimeType::fromString('text/markdown')]);

        self::assertTrue($allowed->containsMimeType(MediaMimeType::fromString('text/markdown;charset=utf-8')));
    }

    public function testContainsMimeTypeRejectsDifferentMimeType(): void
    {
        $allowed = new MediaMimeTypeCollection([MediaMimeType::fromString('image/jpeg')]);

        self::assertFalse($allowed->containsMimeType(MediaMimeType::fromString('image/png')));
    }

    public function testContainsMimeTypeMatchesParametrizedImageByBaseValue(): void
    {
        // Нормализация по базовому MIME умышленно общая: image-тип с параметром тоже проходит
        // спецификацию, заданную базовым image/jpeg. Это осознанное поведение, а не только
        // документный кейс. Хранимое значение остаётся исходным (с параметром).
        $allowed = new MediaMimeTypeCollection([MediaMimeType::fromString('image/jpeg')]);

        self::assertTrue($allowed->containsMimeType(MediaMimeType::fromString('image/jpeg;charset=utf-8')));
    }
}
