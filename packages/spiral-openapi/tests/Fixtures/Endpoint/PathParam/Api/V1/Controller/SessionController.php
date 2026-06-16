<?php

declare (strict_types=1);

namespace GianTiaga\SpiralOpenApi\Tests\Fixtures\Endpoint\PathParam\Api\V1\Controller;

use Spiral\Router\Annotation\Route;
use GianTiaga\SpiralOpenApi\Response\EmptySuccessResponse;

final class SessionController
{
    /**
     * Удалить сессию по идентификатору из пути.
     */
    #[Route(route: '/api/v1/sessions/<sessionId>', name: 'api.v1.sessions.revoke', methods: ['DELETE'], group: 'api')]
    public function revoke(string $sessionId): EmptySuccessResponse
    {
        return new EmptySuccessResponse();
    }
}
