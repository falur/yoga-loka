<?php

declare (strict_types=1);

namespace GianTiaga\SpiralOpenApi\Tests\Fixtures\Endpoint\Api\V1\Resource;

use GianTiaga\SpiralOpenApi\Tests\Fixtures\Endpoint\Api\V1\Enum\AccountStatus;
use GianTiaga\SpiralOpenApi\Tests\Fixtures\Endpoint\Api\V1\Enum\HealthStatus;

final readonly class UserResource extends AbstractResource
{
    /** @var list<string>|null */
    public array|null $tags;

    /**
     * @param list<HealthResource> $healthChecks
     */
    public function __construct(public string $id, public string $email, public array $healthChecks, public HealthStatus|AccountStatus $primaryStatus, public \DateTimeImmutable $createdAt, public string|null $nickname = null, public HealthResource|null $health = null, public \DateTimeImmutable|null $deletedAt = null)
    {
        $this->tags = null;
    }
}
