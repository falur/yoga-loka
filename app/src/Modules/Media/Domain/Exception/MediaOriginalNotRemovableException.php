<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\Exception;

use App\Shared\Domain\Exception\DomainTranslatableException;

/**
 * Удалить оригинал можно только у готового медиа.
 */
final class MediaOriginalNotRemovableException extends DomainTranslatableException
{
    public function __construct()
    {
        parent::__construct(translationKey: 'app.media.original_not_removable');
    }

    #[\Override]
    protected function statusCode(): int
    {
        return 422;
    }
}
