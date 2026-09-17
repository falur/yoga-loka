<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\Exception;

use App\Shared\Domain\Exception\DomainTranslatableException;

/**
 * Размер файла превышает предел спецификации загрузки.
 */
final class MediaFileSizeExceededException extends DomainTranslatableException
{
    public function __construct()
    {
        parent::__construct(translationKey: 'app.media.file_size_exceeded');
    }

    #[\Override]
    protected function statusCode(): int
    {
        return 422;
    }
}
