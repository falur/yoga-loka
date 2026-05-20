<?php

declare(strict_types=1);

namespace Tools\ApiError\Interceptor;

use Psr\Log\LoggerInterface;
use Spiral\Filters\Exception\ValidationException as FilterValidationException;
use Spiral\Interceptors\Context\CallContextInterface;
use Spiral\Interceptors\HandlerInterface;
use Spiral\Interceptors\InterceptorInterface;
use Spiral\Translator\TranslatorInterface;
use Tools\OpenApi\Response\Enum\HttpStatus;
use Tools\OpenApi\Response\ErrorResponse;

final readonly class ApiExceptionInterceptor implements InterceptorInterface
{
    private const int CLIENT_ERROR_MIN = 400;
    private const int CLIENT_ERROR_MAX = 499;

    public function __construct(
        private LoggerInterface $logger,
        private TranslatorInterface $translator,
    ) {}

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
            $this->logger->warning('Доменная ошибка без корректного HTTP-кода.', [
                'exceptionClass' => $exception::class,
                'exceptionCode' => $exception->getCode(),
                'message' => $exception->getMessage(),
            ]);

            return $this->errorResponse(
                message: $exception->getMessage(),
                status: HttpStatus::BadRequest,
            );
        }

        $this->logger->debug('API вернул ожидаемую клиентскую ошибку.', [
            'exceptionClass' => $exception::class,
            'exceptionCode' => $exception->getCode(),
            'status' => $status->value,
            'message' => $exception->getMessage(),
        ]);

        return $this->errorResponse(
            message: $exception->getMessage(),
            status: $status,
        );
    }

    private function unexpectedExceptionResponse(\Throwable $exception): ErrorResponse
    {
        $this->logger->error('Непредвиденная ошибка API.', [
            'exceptionClass' => $exception::class,
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ]);

        return $this->errorResponse(
            message: $this->translator->trans(id: 'yoga_loka.api_error.internal_server_error'),
            status: HttpStatus::InternalServerError,
        );
    }

    private function supportedClientStatus(\DomainException $exception): ?HttpStatus
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
        return new ErrorResponse(
            message: $message,
            code: $status->value,
        )->withStatus($status);
    }
}
