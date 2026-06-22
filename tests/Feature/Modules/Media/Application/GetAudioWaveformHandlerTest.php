<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Media\Application;

use App\Modules\Media\Application\Query\GetAudioWaveform\GetAudioWaveformHandler;
use App\Modules\Media\Application\Query\GetAudioWaveform\GetAudioWaveformQuery;
use App\Modules\Media\Domain\Entity\Media;
use App\Modules\Media\Domain\Entity\MediaAudioConversion;
use App\Modules\Media\Domain\Enum\MediaAudioConversionType;
use App\Modules\Media\Domain\Enum\MediaConversionStatus;
use App\Modules\Media\Domain\Enum\MediaStorage;
use App\Modules\Media\Domain\Enum\MediaType;
use App\Modules\Media\Domain\Enum\MediaVisibility;
use App\Modules\Media\Domain\ValueObject\MediaBitrate;
use App\Modules\Media\Domain\ValueObject\MediaDuration;
use App\Modules\Media\Domain\ValueObject\MediaFileSize;
use App\Modules\Media\Domain\ValueObject\MediaMimeType;
use App\Modules\Media\Domain\ValueObject\MediaPath;
use App\Modules\Media\Domain\ValueObject\MediaSampleRate;
use App\Modules\Media\Domain\ValueObject\MediaWaveform;
use App\Shared\Domain\Exception\NotFoundException;
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

    public function testRejectsNonReadyMedia(): void
    {
        $media = $this->createMedia(userId: UserId::generate(), type: MediaType::Audio, extension: 'mp3', mimeType: 'audio/mpeg');
        $this->persist($media);

        $this->expectException(NotFoundException::class);

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

        $this->expectException(NotFoundException::class);

        $this->handler()->handle(new GetAudioWaveformQuery(mediaId: $media->id->value()));
    }

    public function testRejectsMissingConversion(): void
    {
        $media = $this->readyAudioMedia();
        $this->persist($media);

        $this->expectException(NotFoundException::class);

        $this->handler()->handle(new GetAudioWaveformQuery(mediaId: $media->id->value()));
    }

    public function testRejectsMissingMedia(): void
    {
        $this->expectException(NotFoundException::class);

        $this->handler()->handle(new GetAudioWaveformQuery(mediaId: UserId::generate()->value()));
    }

    private function handler(): GetAudioWaveformHandler
    {
        return new GetAudioWaveformHandler(
            mediaRepository: $this->mediaRepository(),
            mediaAudioConversionRepository: $this->audioConversionRepository(),
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

    private function audioConversion(Media $media): MediaAudioConversion
    {
        return MediaAudioConversion::create(
            media: $media,
            type: MediaAudioConversionType::NormalizedAacM4a,
            status: MediaConversionStatus::Ready,
            storage: MediaStorage::Public,
            path: MediaPath::audioConversion(
                storageKey: $media->storageKey,
                type: MediaAudioConversionType::NormalizedAacM4a,
                extension: 'm4a',
            ),
            mimeType: MediaMimeType::fromString('audio/mp4'),
            size: MediaFileSize::fromInt(2048),
            duration: MediaDuration::fromInt(3000),
            bitrate: MediaBitrate::fromInt(128_000),
            sampleRate: MediaSampleRate::fromInt(44_100),
            waveform: MediaWaveform::fromPeaks([0, 64, 128, 255]),
        );
    }
}
