<?php

declare (strict_types=1);

namespace GianTiaga\SpiralApiErrors\Filter;

use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Spiral\Filters\ErrorsRendererInterface;
use Spiral\Translator\TranslatorInterface;
use GianTiaga\SpiralOpenApi\Response\Enum\HttpStatus;
use GianTiaga\SpiralOpenApi\Response\ValidationErrorItemResponse;
use GianTiaga\SpiralOpenApi\Response\ValidationErrorResponse;

final readonly class ApiValidationErrorsRenderer implements ErrorsRendererInterface
{
    public function __construct(private LoggerInterface $logger, private TranslatorInterface $translator) {}
    /**
     * Spiral-валидатор отдаёт `array<string, string>`, а Symfony-валидатор — список сообщений на поле.
     * Рендерер поддерживает обе формы (см. messageText()).
     *
     * @param array<string, string> $errors
     */
    public function render(array $errors, mixed $context = null): ResponseInterface
    {
        $this->logger->debug(message: 'API вернул ошибки валидации Filter.', context: ['fields' => \array_keys($errors)]);
        return (new ValidationErrorResponse(message: $this->translator->trans(id: 'gian_tiaga.spiral_api_errors.validation_error'), code: HttpStatus::UnprocessableEntity->value, errors: $this->validationErrors($errors)))->withStatus(HttpStatus::UnprocessableEntity)->toResponse();
    }
    /**
     * @param array<string, string> $errors
     * @return list<ValidationErrorItemResponse>
     */
    private function validationErrors(array $errors): array
    {
        $validationErrors = [];
        foreach ($errors as $field => $message) {
            $validationErrors[] = new ValidationErrorItemResponse(field: $field, message: $this->messageText($message));
        }
        return $validationErrors;
    }
    /**
     * Symfony-валидатор кладёт на каждое поле список сообщений; сводим его к одной строке.
     *
     * @param string|list<string> $message
     */
    private function messageText(string|array $message): string
    {
        if (\is_array($message)) {
            return \implode(separator: ' ', array: $message);
        }
        return $message;
    }
}
