<?php

declare(strict_types=1);

namespace Tools\ApiError\Filter;

use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Spiral\Filters\ErrorsRendererInterface;
use Tools\OpenApi\Response\Enum\HttpStatus;
use Tools\OpenApi\Response\ValidationErrorItemResponse;
use Tools\OpenApi\Response\ValidationErrorResponse;

final readonly class ApiValidationErrorsRenderer implements ErrorsRendererInterface
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    /**
     * @param array<string, string> $errors
     */
    public function render(array $errors, mixed $context = null): ResponseInterface
    {
        $this->logger->debug('API вернул ошибки валидации Filter.', [
            'fields' => \array_keys($errors),
        ]);

        return new ValidationErrorResponse(
            message: 'Ошибка валидации',
            code: HttpStatus::UnprocessableEntity->value,
            errors: $this->validationErrors($errors),
        )
            ->withStatus(HttpStatus::UnprocessableEntity)
            ->toResponse();
    }

    /**
     * @param array<string, string> $errors
     * @return list<ValidationErrorItemResponse>
     */
    private function validationErrors(array $errors): array
    {
        $validationErrors = [];

        foreach ($errors as $field => $message) {
            $validationErrors[] = new ValidationErrorItemResponse(
                field: $field,
                message: $message,
            );
        }

        return $validationErrors;
    }
}
