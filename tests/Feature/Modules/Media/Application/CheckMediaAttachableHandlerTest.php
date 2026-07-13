<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Media\Application;

use App\Modules\Media\Application\Query\CheckMediaAttachable\CheckMediaAttachableHandler;
use App\Modules\Media\Application\Query\CheckMediaAttachable\CheckMediaAttachableQuery;
use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Shared\Domain\Exception\ForbiddenException;
use App\Shared\Domain\Exception\NotFoundException;
use App\Shared\Domain\Exception\ValidationException;
use App\Shared\Domain\ValueObject\UserId;

final class CheckMediaAttachableHandlerTest extends MediaApplicationTestCase
{
    public function testAllowsReadyMediaOwnedByUser(): void
    {
        $owner = UserId::generate();
        $media = $this->readyMediaOwnedBy($owner);
        $this->persist($media);

        $result = $this->handler()->handle(new CheckMediaAttachableQuery(
            mediaId: $media->id->value(),
            ownerUserId: $owner->value(),
        ));

        self::assertSame($media->id->value(), $result->mediaId);
    }

    public function testRejectsMissingMedia(): void
    {
        $this->expectException(NotFoundException::class);

        $this->handler()->handle(new CheckMediaAttachableQuery(
            mediaId: UserId::generate()->value(),
            ownerUserId: UserId::generate()->value(),
        ));
    }

    public function testRejectsForeignMedia(): void
    {
        $media = $this->readyMediaOwnedBy(UserId::generate());
        $this->persist($media);

        $this->expectException(ForbiddenException::class);

        $this->handler()->handle(new CheckMediaAttachableQuery(
            mediaId: $media->id->value(),
            ownerUserId: UserId::generate()->value(),
        ));
    }

    public function testRejectsMediaThatIsNotReady(): void
    {
        $owner = UserId::generate();
        $media = $this->createMedia(userId: $owner);
        $this->persist($media);

        $this->expectException(ValidationException::class);

        $this->handler()->handle(new CheckMediaAttachableQuery(
            mediaId: $media->id->value(),
            ownerUserId: $owner->value(),
        ));
    }

    public function testRejectsReadyOriginalRemovedMedia(): void
    {
        // attach-семантика осознанно остаётся строгой (только ready): после удаления оригинала
        // вложить медиа нельзя — 422.
        $owner = UserId::generate();
        $media = $this->readyMediaOwnedBy($owner);
        $media->markReadyOriginalRemoved();
        $this->persist($media);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('app.media.not_ready');

        $this->handler()->handle(new CheckMediaAttachableQuery(
            mediaId: $media->id->value(),
            ownerUserId: $owner->value(),
        ));
    }

    private function handler(): CheckMediaAttachableHandler
    {
        return new CheckMediaAttachableHandler(mediaRepository: $this->mediaRepository());
    }

    private function readyMediaOwnedBy(UserId $owner): Media
    {
        $media = $this->createMedia(userId: $owner, visibility: MediaVisibility::Public);
        $media->markUploaded();
        $media->markReadyMovedTo(
            MediaStorage::Public,
            MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Image, extension: 'jpg'),
        );

        return $media;
    }
}
