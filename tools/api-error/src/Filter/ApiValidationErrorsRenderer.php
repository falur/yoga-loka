<?php

declare(strict_types=1);

namespace Tools\ApiError\Filter;

use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Spiral\Filters\ErrorsRendererInterface;
use Spiral\Translator\TranslatorInterface;
use Tools\OpenApi\Response\Enum\HttpStatus;
use Tools\OpenApi\Response\ValidationErrorItemResponse;
use Tools\OpenApi\Response\ValidationErrorResponse;

final readonly class ApiValidationErrorsRenderer implements ErrorsRendererInterface
{
    public function __construct(
        private LoggerInterface $logger,
        private TranslatorInterface $translator,
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
            message: $this->translator->trans(id: 'yoga_loka.api_error.validation_error'),
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
