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
            $validationErrors[] = new ValidationErrorItemResponse(field: $field, messages: $this->messages($message));
        }
        return $validationErrors;
    }
    /**
     * Symfony-валидатор отдаёт сообщения поля списком, а casting-ошибки — строкой. Возвращаем
     * все сообщения поля списком, не теряя дополнительные нарушения. Контракт остаётся
     * array<string, string> (как у vendor-интерфейса) — список разворачивается здесь, без
     * вложенного массива в типе.
     *
     * @param string|list<string> $message
     * @return list<string>
     */
    private function messages(string|array $message): array
    {
        return \is_array($message) ? $message : [$message];
    }
}
