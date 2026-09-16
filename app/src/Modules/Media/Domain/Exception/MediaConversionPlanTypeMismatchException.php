<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\Exception;

use App\Shared\Domain\Exception\DomainTranslatableException;

/**
 * Профили преобразования не соответствуют типу медиа.
 */
final class MediaConversionPlanTypeMismatchException extends DomainTranslatableException
{
    public function __construct()
    {
        parent::__construct(translationKey: 'app.media.conversion_plan_type_mismatch');
    }

    #[\Override]
    protected function statusCode(): int
    {
        return 422;
    }
}
