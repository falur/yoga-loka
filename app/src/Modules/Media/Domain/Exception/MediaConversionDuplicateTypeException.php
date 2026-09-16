<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\Exception;

use App\Shared\Domain\Exception\DomainTranslatableException;

/**
 * Профили преобразования содержат повторяющийся тип.
 */
final class MediaConversionDuplicateTypeException extends DomainTranslatableException
{
    public function __construct()
    {
        parent::__construct(translationKey: 'app.media.conversion_duplicate_type');
    }

    #[\Override]
    protected function statusCode(): int
    {
        return 422;
    }
}
