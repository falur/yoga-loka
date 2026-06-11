<?php

declare(strict_types=1);

namespace Tests\Kernel\Shared\Infrastructure\Cycle;

use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Modules\Media\Domain\ValueObject\MediaExpiration;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaStorageKey;
use App\Shared\Domain\ValueObject\UserId;
use App\Shared\Infrastructure\Cycle\LazyGhostMapper;
use Cycle\ORM\ORMInterface;
use Tests\TestCase;

final class LazyGhostMapperExtractTest extends TestCase
{
    public function testExtractReturnsColumnsAndRelations(): void
    {
        $orm = $this->getContainer()->get(ORMInterface::class);
        $mapper = $orm->getMapper('media');

        self::assertInstanceOf(LazyGhostMapper::class, $mapper);

        $storageKey = MediaStorageKey::generate();
        $media = Media::create(
            storageKey: $storageKey,
            type: MediaType::Image,
            visibility: MediaVisibility::Private,
            path: MediaPath::originalUpload(storageKey: $storageKey, extension: 'jpg'),
            mimeType: MediaMimeType::fromString('image/jpeg'),
            size: MediaFileSize::fromInt(1024),
            uploadedById: UserId::generate(),
            expiration: MediaExpiration::temporaryUntil(new \DateTimeImmutable('+1 hour')),
        );

        $extracted = $mapper->extract($media);

        self::assertArrayHasKey('id', $extracted);
        self::assertArrayHasKey('imageConversions', $extracted);
        self::assertArrayHasKey('videoConversions', $extracted);
    }
}
