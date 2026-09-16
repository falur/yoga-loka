<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\Exception;

use App\Shared\Domain\Exception\DomainTranslatableException;

/**
 * Тип файла не поддерживается конвейером загрузки.
 */
final class MediaUnsupportedFileTypeException extends DomainTranslatableException
{
    public function __construct(string $type)
    {
        parent::__construct(
            translationKey: 'app.media.unsupported_file_type',
            translationParameters: ['type' => $type],
        );
    }

    #[\Override]
    protected function statusCode(): int
    {
        return 422;
    }
}
