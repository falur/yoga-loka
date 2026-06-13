<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

final class NotFoundException extends DomainTranslatableException
{
    #[\Override]
    protected function statusCode(): int
    {
        return 404;
    }
}
