<?php

declare (strict_types=1);

namespace GianTiaga\SpiralOpenApi\Tests\Fixtures\Endpoint\Api\V1\Resource;

final readonly class UserResource extends AbstractResource
{
    /** @var list<string>|null */
    public array|null $tags;

    public function __construct(public string $id, public string $email, public string|null $nickname = null, public HealthResource|null $health = null)
    {
        $this->tags = null;
    }
}
