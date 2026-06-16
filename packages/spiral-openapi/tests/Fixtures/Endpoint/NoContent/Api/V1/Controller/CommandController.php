<?php

declare (strict_types=1);

namespace GianTiaga\SpiralOpenApi\Tests\Fixtures\Endpoint\NoContent\Api\V1\Controller;

use Spiral\Router\Annotation\Route;
use GianTiaga\SpiralOpenApi\Response\EmptySuccessResponse;

final class CommandController
{
    /**
     * Выполнить команду без тела ответа.
     */
    #[Route(route: '/api/v1/commands/run', name: 'api.v1.commands.run', methods: ['POST'], group: 'api')]
    public function run(): EmptySuccessResponse
    {
        return new EmptySuccessResponse();
    }
}
