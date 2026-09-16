<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\Exception;

use App\Shared\Domain\Exception\DomainTranslatableException;

/**
 * Медиа загружено другим пользователем.
 */
final class MediaAccessDeniedException extends DomainTranslatableException
{
    public function __construct()
    {
        parent::__construct(translationKey: 'app.media.access_denied');
    }

    #[\Override]
    protected function statusCode(): int
    {
        return 403;
    }
}
