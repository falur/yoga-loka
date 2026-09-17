<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\Exception;

use App\Shared\Domain\Exception\DomainTranslatableException;

/**
 * Постоянным можно сделать только загруженное или готовое медиа.
 */
final class MediaCannotBeMadePermanentException extends DomainTranslatableException
{
    public function __construct()
    {
        parent::__construct(translationKey: 'app.media.cannot_make_permanent');
    }

    #[\Override]
    protected function statusCode(): int
    {
        return 422;
    }
}
