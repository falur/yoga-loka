<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\Exception;

use App\Shared\Domain\Exception\DomainTranslatableException;

/**
 * Медиа не найдено.
 */
final class MediaNotFoundException extends DomainTranslatableException
{
    public function __construct()
    {
        parent::__construct(translationKey: 'app.media.not_found');
    }

    #[\Override]
    protected function statusCode(): int
    {
        return 404;
    }
}
