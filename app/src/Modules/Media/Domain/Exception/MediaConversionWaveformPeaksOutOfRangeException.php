<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\Exception;

use App\Shared\Domain\Exception\DomainTranslatableException;

/**
 * Количество пиков волны вне допустимого диапазона.
 */
final class MediaConversionWaveformPeaksOutOfRangeException extends DomainTranslatableException
{
    public function __construct()
    {
        parent::__construct(translationKey: 'app.media.conversion_waveform_peaks_out_of_range');
    }

    #[\Override]
    protected function statusCode(): int
    {
        return 422;
    }
}
