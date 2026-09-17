<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\Exception;

use App\Shared\Domain\Exception\DomainTranslatableException;

/**
 * Для медиа не найдена multipart-загрузка.
 */
final class MediaMultipartUploadNotFoundException extends DomainTranslatableException
{
    public function __construct()
    {
        parent::__construct(translationKey: 'app.media.multipart_upload_not_found');
    }

    #[\Override]
    protected function statusCode(): int
    {
        return 422;
    }
}
