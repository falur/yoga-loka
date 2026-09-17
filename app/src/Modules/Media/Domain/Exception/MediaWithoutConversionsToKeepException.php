<?php

declare(strict_types=1);

namespace App\Modules\Media\Domain\Exception;

use App\Shared\Domain\Exception\DomainTranslatableException;

/**
 * Оригинал нельзя удалить: у медиа нет ни одного готового преобразования.
 */
final class MediaWithoutConversionsToKeepException extends DomainTranslatableException
{
    public function __construct()
    {
        parent::__construct(translationKey: 'app.media.no_conversions_to_keep');
    }

    #[\Override]
    protected function statusCode(): int
    {
        return 422;
    }
}
