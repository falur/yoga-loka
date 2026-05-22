<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

final class InvalidDomainValueException extends \DomainException
{
    private const int STATUS_CODE = 500;

    public function __construct(string $message)
    {
        parent::__construct(message: $message, code: self::STATUS_CODE);
    }
}
