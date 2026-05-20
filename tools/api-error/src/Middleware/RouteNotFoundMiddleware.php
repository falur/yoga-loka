<?php

declare(strict_types=1);

namespace Tools\ApiError\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Spiral\Router\Exception\RouteNotFoundException;
use Tools\OpenApi\Response\Enum\HttpStatus;
use Tools\OpenApi\Response\ErrorResponse;

final readonly class RouteNotFoundMiddleware implements MiddlewareInterface
{
    private const string MESSAGE = 'Маршрут не найден.';

    public function __construct(
        private LoggerInterface $logger,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (RouteNotFoundException $exception) {
            $this->logger->debug('HTTP-маршрут не найден.', [
                'method' => $request->getMethod(),
                'path' => $request->getUri()->getPath(),
                'status' => HttpStatus::NotFound->value,
                'exceptionClass' => $exception::class,
            ]);

            return new ErrorResponse(
                message: self::MESSAGE,
                code: HttpStatus::NotFound->value,
            )->withStatus(HttpStatus::NotFound)->toResponse();
        }
    }
}
