<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\Exception;

use App\Shared\Domain\Exception\DomainTranslatableException;

/**
 * Загрузка медиа не ожидает подтверждения.
 */
final class MediaUploadNotPendingException extends DomainTranslatableException
{
    public function __construct()
    {
        parent::__construct(translationKey: 'app.media.upload_not_pending');
    }

    #[\Override]
    protected function statusCode(): int
    {
        return 422;
    }
}
