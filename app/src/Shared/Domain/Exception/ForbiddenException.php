<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

final class ForbiddenException extends DomainTranslatableException
{
    #[\Override]
    protected function statusCode(): int
    {
        return 403;
    }
}
