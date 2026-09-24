<?php

declare(strict_types=1);

namespace App\Modules\Media\Tests\Unit\Application;

use App\Modules\Media\Public\Dto\MediaAudioConversionSpecDto;
use App\Modules\Media\Public\Dto\MediaConversionPlanDto;
use App\Modules\Media\Public\Dto\MediaImageConversionSpecDto;
use App\Modules\Media\Public\Dto\MediaVideoConversionSpecDto;
use App\Modules\Media\Public\Event\MediaUploadedEvent;
use App\Modules\Media\Public\Enum\MediaAudioConversionType;
use App\Modules\Media\Public\Enum\MediaImageConversionType;
use App\Modules\Media\Public\Enum\MediaVideoConversionType;
use GianTiaga\SpiralOutbox\Store\JsonOutboxEventSerializer;
use PHPUnit\Framework\TestCase;

final class MediaUploadedSerializationTest extends TestCase
{
    public function testRestoresNestedConversionPlanThroughPackageSerializer(): void
    {
        $serializer = new JsonOutboxEventSerializer();

        $serialized = $serializer->serialize(new MediaUploadedEvent(
            mediaId: '0192f2a0-0000-7000-8000-000000000001',
            plan: new MediaConversionPlanDto(
                image: [new MediaImageConversionSpecDto(type: MediaImageConversionType::Thumbnail, width: 100, height: 80)],
                video: [new MediaVideoConversionSpecDto(
                    type: MediaVideoConversionType::NormalizedMp4H264,
                    width: 1280,
                    height: 720,
                    videoBitrate: 1_000_000,
                    audioBitrate: 128_000,
                )],
                audio: [new MediaAudioConversionSpecDto(
                    type: MediaAudioConversionType::NormalizedAacM4a,
                    bitrate: 128_000,
                    sampleRate: 44_100,
                    waveformPeaks: 64,
                )],
            ),
        ));
        $restored = $serializer->deserialize(
            eventClass: MediaUploadedEvent::class,
            payload: $serialized,
        );

        self::assertJson($serialized);
        self::assertInstanceOf(MediaUploadedEvent::class, $restored);
        self::assertSame('0192f2a0-0000-7000-8000-000000000001', $restored->mediaId);

        self::assertCount(1, $restored->plan->image);
        self::assertSame(MediaImageConversionType::Thumbnail, $restored->plan->image[0]->type);
        self::assertSame(100, $restored->plan->image[0]->width);
        self::assertSame(80, $restored->plan->image[0]->height);

        self::assertCount(1, $restored->plan->video);
        self::assertSame(MediaVideoConversionType::NormalizedMp4H264, $restored->plan->video[0]->type);
        self::assertSame(1280, $restored->plan->video[0]->width);
        self::assertSame(1_000_000, $restored->plan->video[0]->videoBitrate);

        self::assertCount(1, $restored->plan->audio);
        self::assertSame(MediaAudioConversionType::NormalizedAacM4a, $restored->plan->audio[0]->type);
        self::assertSame(44_100, $restored->plan->audio[0]->sampleRate);
        self::assertSame(64, $restored->plan->audio[0]->waveformPeaks);
    }
}
