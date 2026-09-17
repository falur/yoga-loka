<?php

declare(strict_types=1);

namespace App\Modules\System\Application\Exception;

use App\Shared\Domain\Exception\DomainTranslatableException;

/**
 * Файл спецификации OpenAPI ещё не сгенерирован.
 */
final class OpenApiSpecificationNotGeneratedException extends DomainTranslatableException
{
    public function __construct()
    {
        parent::__construct(translationKey: 'app.system.openapi_yaml_not_generated');
    }

    #[\Override]
    protected function statusCode(): int
    {
        return 404;
    }
}
