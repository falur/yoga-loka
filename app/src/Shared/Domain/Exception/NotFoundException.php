<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

final class NotFoundException extends \DomainException
{
    private const int STATUS_CODE = 404;

    public function __construct(string $message)
    {
        parent::__construct(message: $message, code: self::STATUS_CODE);
    }
}
