<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\Exception;

use App\Shared\Domain\Exception\DomainTranslatableException;

/**
 * Медиа ещё не готово, поэтому вложить его в запись нельзя.
 */
final class MediaNotReadyForAttachmentException extends DomainTranslatableException
{
    public function __construct()
    {
        parent::__construct(translationKey: 'app.media.not_ready');
    }

    #[\Override]
    protected function statusCode(): int
    {
        return 422;
    }
}
