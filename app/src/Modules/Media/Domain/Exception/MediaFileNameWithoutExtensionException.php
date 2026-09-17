<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\Exception;

use App\Shared\Domain\Exception\DomainTranslatableException;

/**
 * Имя файла не содержит расширения.
 */
final class MediaFileNameWithoutExtensionException extends DomainTranslatableException
{
    public function __construct()
    {
        parent::__construct(translationKey: 'app.media.file_name_without_extension');
    }

    #[\Override]
    protected function statusCode(): int
    {
        return 422;
    }
}
