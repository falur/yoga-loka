<?php

declare (strict_types=1);

namespace GianTiaga\SpiralApiErrors\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Spiral\Router\Exception\RouteNotFoundException;
use Spiral\Translator\TranslatorInterface;
use GianTiaga\SpiralOpenApi\Response\Enum\HttpStatus;
use GianTiaga\SpiralOpenApi\Response\ErrorResponse;

final readonly class RouteNotFoundMiddleware implements MiddlewareInterface
{
    public function __construct(private LoggerInterface $logger, private TranslatorInterface $translator) {}
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (RouteNotFoundException $exception) {
            $this->logger->debug(message: 'HTTP-маршрут не найден.', context: ['method' => $request->getMethod(), 'path' => $request->getUri()->getPath(), 'status' => HttpStatus::NotFound->value, 'exceptionClass' => $exception::class]);
            return (new ErrorResponse(message: $this->translator->trans(id: 'gian_tiaga.spiral_api_errors.route_not_found'), code: HttpStatus::NotFound->value))->withStatus(HttpStatus::NotFound)->toResponse();
        }
    }
}
