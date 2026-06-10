<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Media\Application;

use App\Modules\Media\Application\Query\Media\CheckMediaExists\CheckMediaExistsHandler;
use App\Modules\Media\Application\Query\Media\CheckMediaExists\CheckMediaExistsQuery;
use App\Modules\Media\Application\Query\Media\CheckMediaIsImage\CheckMediaIsImageHandler;
use App\Modules\Media\Application\Query\Media\CheckMediaIsImage\CheckMediaIsImageQuery;
use App\Modules\Media\Domain\Enum\MediaType;
use App\Shared\Domain\ValueObject\UserId;

final class CheckMediaQueriesTest extends MediaApplicationTestCase
{
    public function testCheckMediaIsImageReturnsTrueForImage(): void
    {
        $media = $this->createMedia(userId: UserId::generate(), type: MediaType::Image);
        $this->persist($media);

        $handler = new CheckMediaIsImageHandler(mediaRepository: $this->mediaRepository());

        self::assertTrue($handler->handle(new CheckMediaIsImageQuery(mediaId: $media->id->value())));
    }

    public function testCheckMediaIsImageReturnsFalseForVideo(): void
    {
        $media = $this->createMedia(userId: UserId::generate(), type: MediaType::Video, extension: 'mp4', mimeType: 'video/mp4');
        $this->persist($media);

        $handler = new CheckMediaIsImageHandler(mediaRepository: $this->mediaRepository());

        self::assertFalse($handler->handle(new CheckMediaIsImageQuery(mediaId: $media->id->value())));
    }

    public function testCheckMediaIsImageReturnsFalseForMissingMedia(): void
    {
        $handler = new CheckMediaIsImageHandler(mediaRepository: $this->mediaRepository());

        self::assertFalse($handler->handle(new CheckMediaIsImageQuery(mediaId: UserId::generate()->value())));
    }

    public function testCheckMediaExistsReturnsTrueForExistingMedia(): void
    {
        $media = $this->createMedia(userId: UserId::generate());
        $this->persist($media);

        $handler = new CheckMediaExistsHandler(mediaRepository: $this->mediaRepository());

        self::assertTrue($handler->handle(new CheckMediaExistsQuery(mediaId: $media->id->value())));
    }

    public function testCheckMediaExistsReturnsFalseForMissingMedia(): void
    {
        $handler = new CheckMediaExistsHandler(mediaRepository: $this->mediaRepository());

        self::assertFalse($handler->handle(new CheckMediaExistsQuery(mediaId: UserId::generate()->value())));
    }
}
