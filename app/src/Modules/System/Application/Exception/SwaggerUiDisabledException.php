<?php

declare(strict_types=1);

namespace App\Modules\System\Application\Exception;

use App\Shared\Domain\Exception\DomainTranslatableException;

/**
 * Swagger UI выключен настройкой приложения.
 */
final class SwaggerUiDisabledException extends DomainTranslatableException
{
    public function __construct()
    {
        parent::__construct(translationKey: 'app.system.swagger_ui_disabled');
    }

    #[\Override]
    protected function statusCode(): int
    {
        return 404;
    }
}
