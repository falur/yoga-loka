<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

final class ValidationException extends DomainTranslatableException
{
    #[\Override]
    protected function statusCode(): int
    {
        return 422;
    }
}
