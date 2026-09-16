<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\Exception;

use App\Shared\Domain\Exception\DomainTranslatableException;

/**
 * Ширина или высота преобразования вне допустимого диапазона.
 */
final class MediaConversionDimensionsOutOfRangeException extends DomainTranslatableException
{
    public function __construct()
    {
        parent::__construct(translationKey: 'app.media.conversion_dimensions_out_of_range');
    }

    #[\Override]
    protected function statusCode(): int
    {
        return 422;
    }
}
