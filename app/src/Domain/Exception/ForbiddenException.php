<?php

declare(strict_types=1);

namespace App\Domain\Exception;

final class ForbiddenException extends \DomainException
{
    private const int STATUS_CODE = 403;

    public function __construct(string $message)
    {
        parent::__construct(message: $message, code: self::STATUS_CODE);
    }
}
