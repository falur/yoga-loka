<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\Exception;

use App\Shared\Domain\Exception\DomainTranslatableException;

/**
 * Загруженный объект отсутствует в хранилище или его размер не совпадает с заявленным.
 */
final class MediaUploadedObjectMismatchException extends DomainTranslatableException
{
    public function __construct()
    {
        parent::__construct(translationKey: 'app.media.uploaded_object_mismatch');
    }

    #[\Override]
    protected function statusCode(): int
    {
        return 422;
    }
}
