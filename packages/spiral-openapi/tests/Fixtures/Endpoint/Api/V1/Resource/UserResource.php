<?php

declare (strict_types=1);

namespace GianTiaga\SpiralOpenApi\Tests\Fixtures\Endpoint\Api\V1\Resource;

final readonly class UserResource extends AbstractResource
{
    public function __construct(public string $id, public string $email)
    {
    }
}
