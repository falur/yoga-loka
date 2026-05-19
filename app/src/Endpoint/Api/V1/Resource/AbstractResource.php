<?php

declare(strict_types=1);

namespace App\Endpoint\Api\V1\Resource;

abstract readonly class AbstractResource implements \JsonSerializable
{
    public function jsonSerialize(): mixed
    {
        return \get_object_vars($this);
    }
}
