<?php

declare (strict_types=1);

namespace GianTiaga\SpiralOpenApi\Response;

/**
 * @template T of object
 */
final class DataResponse extends AbstractJsonResponse
{
    /**
     * @param T $data
     */
    public function __construct(public readonly object $data)
    {
    }
}
