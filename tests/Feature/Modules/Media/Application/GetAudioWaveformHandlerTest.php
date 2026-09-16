<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Media\Application;

use App\Modules\Media\Application\Query\GetAudioWaveform\GetAudioWaveformHandler;
use App\Modules\Media\Application\Query\GetAudioWaveform\GetAudioWaveformQuery;
use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Modules\Media\Domain\Exception\MediaConversionNotFoundException;
use App\Modules\Media\Domain\Exception\MediaNotFinalizedException;
use App\Modules\Media\Domain\Exception\MediaNotFoundException;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Shared\Domain\ValueObject\UserId;

final class GetAudioWaveformHandlerTest extends MediaApplicationTestCase
{
    public function testReturnsWaveformForReadyAudioMedia(): void
    {
        $media = $this->readyAudioMedia();
        $this->persist($media, $this->audioConversion($media));

        $waveform = $this->handler()->handle(new GetAudioWaveformQuery(mediaId: $media->id->value()));

        self::assertSame([0, 64, 128, 255], $waveform->peaks());
    }

    public function testReturnsWaveformForReadyOriginalRemovedAudioMedia(): void
    {
        // Волна — конверсия: переживает удаление оригинала.
        $media = $this->readyAudioMedia();
        $media->markReadyOriginalRemoved();
        $this->persist($media, $this->audioConversion($media));

        $waveform = $this->handler()->handle(new GetAudioWaveformQuery(mediaId: $media->id->value()));

        self::assertSame([0, 64, 128, 255], $waveform->peaks());
    }

    public function testRejectsNonReadyMedia(): void
    {
        $media = $this->createMedia(userId: UserId::generate(), type: MediaType::Audio, extension: 'mp3', mimeType: 'audio/mpeg');
        $this->persist($media);

        $this->expectException(MediaNotFinalizedException::class);

        $this->handler()->handle(new GetAudioWaveformQuery(mediaId: $media->id->value()));
    }

    public function testRejectsNonAudioMedia(): void
    {
        $media = $this->createMedia(userId: UserId::generate());
        $media->markUploaded();
        $media->markReadyMovedTo(
            MediaStorage::Public,
            MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Image, extension: 'jpg'),
        );
        $this->persist($media);

        $this->expectException(MediaConversionNotFoundException::class);

        $this->handler()->handle(new GetAudioWaveformQuery(mediaId: $media->id->value()));
    }

    public function testRejectsMissingConversion(): void
    {
        $media = $this->readyAudioMedia();
        $this->persist($media);

        $this->expectException(MediaConversionNotFoundException::class);

        $this->handler()->handle(new GetAudioWaveformQuery(mediaId: $media->id->value()));
    }

    public function testRejectsMissingMedia(): void
    {
        $this->expectException(MediaNotFoundException::class);

        $this->handler()->handle(new GetAudioWaveformQuery(mediaId: UserId::generate()->value()));
    }

    private function handler(): GetAudioWaveformHandler
    {
        return new GetAudioWaveformHandler(
            mediaRepository: $this->mediaRepository(),
        );
    }

    private function readyAudioMedia(): Media
    {
        $media = $this->createMedia(
            userId: UserId::generate(),
            visibility: MediaVisibility::Public,
            type: MediaType::Audio,
            extension: 'mp3',
            mimeType: 'audio/mpeg',
        );
        $media->markUploaded();
        $media->markReadyMovedTo(
            MediaStorage::Public,
            MediaPath::originalReady(storageKey: $media->storageKey, type: MediaType::Audio, extension: 'mp3'),
        );

        return $media;
    }
}
