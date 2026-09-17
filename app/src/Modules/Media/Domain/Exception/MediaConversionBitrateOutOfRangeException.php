<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\Exception;

use App\Shared\Domain\Exception\DomainTranslatableException;

/**
 * Битрейт преобразования вне допустимого диапазона.
 */
final class MediaConversionBitrateOutOfRangeException extends DomainTranslatableException
{
    public function __construct()
    {
        parent::__construct(translationKey: 'app.media.conversion_bitrate_out_of_range');
    }

    #[\Override]
    protected function statusCode(): int
    {
        return 422;
    }
}
