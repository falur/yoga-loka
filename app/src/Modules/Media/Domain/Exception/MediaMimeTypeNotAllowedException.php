<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\Exception;

use App\Shared\Domain\Exception\DomainTranslatableException;

/**
 * MIME-тип файла не разрешён спецификацией загрузки.
 */
final class MediaMimeTypeNotAllowedException extends DomainTranslatableException
{
    public function __construct(string $mimeType)
    {
        parent::__construct(
            translationKey: 'app.media.mime_not_allowed',
            translationParameters: ['mimeType' => $mimeType],
        );
    }

    #[\Override]
    protected function statusCode(): int
    {
        return 422;
    }
}
