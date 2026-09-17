<?php

declare(strict_types=1);

namespace App\Modules\Media\Tests\Integration\Spiral;

use App\Modules\Media\Application\Query\CheckMediaAttachable\CheckMediaAttachableHandler;
use App\Modules\Media\Application\Query\CheckMediaAttachable\CheckMediaAttachableQuery;
use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Modules\Media\Domain\Exception\MediaAccessDeniedException;
use App\Modules\Media\Domain\Exception\MediaNotFoundException;
use App\Modules\Media\Domain\Exception\MediaNotReadyForAttachmentException;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Shared\Domain\ValueObject\UserId;

final class CheckMediaAttachableHandlerTest extends MediaApplicationTestCase
{
    public function testAllowsReadyMediaOwnedByUser(): void
    {
        $owner = UserId::generate();
        $media = $this->readyMediaOwnedBy($owner);
        $this->persist($media);

        $result = $this->handler()->handle(new CheckMediaAttachableQuery(
            mediaIds: [$media->id->value()],
            ownerUserId: $owner->value(),
        ));

        self::assertSame([$media->id->value()], $result->mediaIds);
    }

    public function testAllowsSeveralReadyMediaInGivenOrder(): void
    {
        $owner = UserId::generate();
        $first = $this->readyMediaOwnedBy($owner);
        $second = $this->readyMediaOwnedBy($owner);
        $this->persist($first, $second);

        $result = $this->handler()->handle(new CheckMediaAttachableQuery(
            mediaIds: [$second->id->value(), $first->id->value()],
            ownerUserId: $owner->value(),
        ));

        self::assertSame([$second->id->value(), $first->id->value()], $result->mediaIds);
    }

    public function testAllowsEmptySet(): void
    {
        $result = $this->handler()->handle(new CheckMediaAttachableQuery(
            mediaIds: [],
            ownerUserId: UserId::generate()->value(),
        ));

        self::assertSame([], $result->mediaIds);
    }

    public function testRejectsMissingMedia(): void
    {
        $this->expectException(MediaNotFoundException::class);

        $this->handler()->handle(new CheckMediaAttachableQuery(
            mediaIds: [UserId::generate()->value()],
            ownerUserId: UserId::generate()->value(),
        ));
    }

    public function testRejectsForeignMedia(): void
    {
        $media = $this->readyMediaOwnedBy(UserId::generate());
        $this->persist($media);

        $this->expectException(MediaAccessDeniedException::class);

        $this->handler()->handle(new CheckMediaAttachableQuery(
            mediaIds: [$media->id->value()],
            ownerUserId: UserId::generate()->value(),
        ));
    }

    public function testRejectsUnsuitableMediaInTheMiddleOfSet(): void
    {
        // Набор обходится в порядке передачи, поэтому ошибку даёт первое непригодное медиа — ровно
        // как поштучный цикл до пакетной проверки.
        $owner = UserId::generate();
        $first = $this->readyMediaOwnedBy($owner);
        $foreign = $this->readyMediaOwnedBy(UserId::generate());
        $last = $this->readyMediaOwnedBy($owner);
        $this->persist($first, $foreign, $last);

        $this->expectException(MediaAccessDeniedException::class);
        $this->expectExceptionMessage('app.media.access_denied');

        $this->handler()->handle(new CheckMediaAttachableQuery(
            mediaIds: [$first->id->value(), $foreign->id->value(), $last->id->value()],
            ownerUserId: $owner->value(),
        ));
    }

    public function testRejectsMediaThatIsNotReady(): void
    {
        $owner = UserId::generate();
        $media = $this->createMedia(userId: $owner);
        $this->persist($media);

        $this->expectException(MediaNotReadyForAttachmentException::class);

        $this->handler()->handle(new CheckMediaAttachableQuery(
            mediaIds: [$media->id->value()],
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

        $this->expectException(MediaNotReadyForAttachmentException::class);
        $this->expectExceptionMessage('app.media.not_ready');

        $this->handler()->handle(new CheckMediaAttachableQuery(
            mediaIds: [$media->id->value()],
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
