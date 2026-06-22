<?php

declare(strict_types=1);

namespace App\Modules\Media\Application\Query\GetAudioWaveform;

final readonly class GetAudioWaveformQuery
{
    public function __construct(
        public string $mediaId,
    ) {}
}
