<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\Exception;

use App\Shared\Domain\Exception\DomainTranslatableException;

/**
 * Медиа ещё не финализировано, поэтому его преобразований пока нет.
 */
final class MediaNotFinalizedException extends DomainTranslatableException
{
    public function __construct()
    {
        parent::__construct(translationKey: 'app.media.not_ready');
    }

    #[\Override]
    protected function statusCode(): int
    {
        return 404;
    }
}
