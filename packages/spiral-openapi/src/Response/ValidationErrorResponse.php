<?php

declare (strict_types=1);

namespace GianTiaga\SpiralOpenApi\Response;

final class ValidationErrorResponse extends AbstractJsonResponse
{
    /**
     * @param list<ValidationErrorItemResponse> $errors
     */
    public function __construct(public readonly string $message, public readonly int $code, public readonly array $errors)
    {
    }
}
