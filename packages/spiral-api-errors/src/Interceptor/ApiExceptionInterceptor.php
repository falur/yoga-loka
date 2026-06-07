<?php

declare (strict_types=1);

namespace GianTiaga\SpiralApiErrors\Interceptor;

use Psr\Log\LoggerInterface;
use Spiral\Filters\Exception\ValidationException as FilterValidationException;
use Spiral\Interceptors\Context\CallContextInterface;
use Spiral\Interceptors\HandlerInterface;
use Spiral\Interceptors\InterceptorInterface;
use Spiral\Translator\TranslatorInterface;
use GianTiaga\SpiralOpenApi\Response\Enum\HttpStatus;
use GianTiaga\SpiralOpenApi\Response\ErrorResponse;

final readonly class ApiExceptionInterceptor implements InterceptorInterface
{
    private const int CLIENT_ERROR_MIN = 400;
    private const int CLIENT_ERROR_MAX = 499;
    public function __construct(private LoggerInterface $logger, private TranslatorInterface $translator) {}
    public function intercept(CallContextInterface $context, HandlerInterface $handler): mixed
    {
        try {
            return $handler->handle($context);
        } catch (\DomainException $exception) {
            return $this->domainExceptionResponse($exception);
        } catch (FilterValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            return $this->unexpectedExceptionResponse($exception);
        }
    }
    private function domainExceptionResponse(\DomainException $exception): ErrorResponse
    {
        $status = $this->supportedClientStatus($exception);
        if ($status === null) {
            return $this->unexpectedExceptionResponse($exception);
        }
        return $this->errorResponse(message: $exception->getMessage(), status: $status);
    }
    private function unexpectedExceptionResponse(\Throwable $exception): ErrorResponse
    {
        $this->logger->error(message: 'Непредвиденная ошибка API.', context: ['exceptionClass' => $exception::class, 'message' => $exception->getMessage(), 'file' => $exception->getFile(), 'line' => $exception->getLine()]);
        return $this->errorResponse(message: $this->translator->trans(id: 'gian_tiaga.spiral_api_errors.internal_server_error'), status: HttpStatus::InternalServerError);
    }
    private function supportedClientStatus(\DomainException $exception): HttpStatus|null
    {
        $status = HttpStatus::tryFrom($exception->getCode());
        if ($status === null) {
            return null;
        }
        if ($status->value < self::CLIENT_ERROR_MIN || $status->value > self::CLIENT_ERROR_MAX) {
            return null;
        }
        return $status;
    }
    private function errorResponse(string $message, HttpStatus $status): ErrorResponse
    {
        return (new ErrorResponse(message: $message, code: $status->value))->withStatus($status);
    }
}
