<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Media\Application;

use App\Modules\Media\Application\Dto\MediaAudioConversionSpec;
use App\Modules\Media\Application\Dto\MediaConversionPlan;
use App\Modules\Media\Application\Dto\MediaImageConversionSpec;
use App\Modules\Media\Application\Dto\MediaVideoConversionSpec;
use App\Modules\Media\Application\Message\MediaUploaded;
use App\Modules\Media\Domain\Enum\MediaAudioConversionType;
use App\Modules\Media\Domain\Enum\MediaImageConversionType;
use App\Modules\Media\Domain\Enum\MediaVideoConversionType;
use App\Modules\Outbox\Infrastructure\Serializer\ValinorOutboxMessageSerializer;
use PHPUnit\Framework\TestCase;

final class MediaUploadedSerializationTest extends TestCase
{
    public function testRestoresNestedConversionPlanThroughValinor(): void
    {
        $serializer = new ValinorOutboxMessageSerializer();

        $serialized = $serializer->serialize(new MediaUploaded(
            mediaId: '0192f2a0-0000-7000-8000-000000000001',
            plan: new MediaConversionPlan(
                image: [new MediaImageConversionSpec(type: MediaImageConversionType::Thumbnail, width: 100, height: 80)],
                video: [new MediaVideoConversionSpec(
                    type: MediaVideoConversionType::NormalizedMp4H264,
                    width: 1280,
                    height: 720,
                    videoBitrate: 1_000_000,
                    audioBitrate: 128_000,
                )],
                audio: [new MediaAudioConversionSpec(
                    type: MediaAudioConversionType::NormalizedAacM4a,
                    bitrate: 128_000,
                    sampleRate: 44_100,
                    waveformPeaks: 64,
                )],
            ),
        ));
        $restored = $serializer->deserialize(serializedOutboxMessage: $serialized);

        self::assertSame(MediaUploaded::class, $serialized->type);
        self::assertInstanceOf(MediaUploaded::class, $restored);
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
