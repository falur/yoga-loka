<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\Exception;

use App\Shared\Domain\Exception\DomainTranslatableException;

/**
 * У медиа нет запрошенного преобразования.
 */
final class MediaConversionNotFoundException extends DomainTranslatableException
{
    public function __construct()
    {
        parent::__construct(translationKey: 'app.media.conversion_not_found');
    }

    #[\Override]
    protected function statusCode(): int
    {
        return 404;
    }
}
