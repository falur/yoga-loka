<?php

declare (strict_types=1);

namespace GianTiaga\SpiralOpenApi\Response;

final readonly class ValidationErrorItemResponse
{
    /**
     * @param list<string> $messages
     */
    public function __construct(public string $field, public array $messages) {}
}
