<?php

declare(strict_types=1);

namespace App\Modules\Auth\Infrastructure\Spiral\Http\Middleware;

use GianTiaga\SpiralOpenApi\Response\Enum\HttpStatus;
use GianTiaga\SpiralOpenApi\Response\ErrorResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Spiral\Translator\TranslatorInterface;

/**
 * Требует аутентифицированного пользователя: если authUserId не выставлен (нет/невалидный
 * access-токен), отдаёт 401 JSON напрямую (middleware вне цепочки controller-интерсепторов,
 * поэтому исключение тут не сконвертируется).
 */
final readonly class RequireAuthenticatedMiddleware implements MiddlewareInterface
{
    public function __construct(
        private TranslatorInterface $translator,
    ) {}

    #[\Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getAttribute(AuthContextAttributeMiddleware::ATTRIBUTE_USER_ID) === null) {
            $errorResponse = new ErrorResponse(
                message: $this->translator->trans(id: 'app.auth.unauthenticated', parameters: [], domain: 'auth'),
                code: HttpStatus::Unauthorized->value,
            );

            return $errorResponse->withStatus(HttpStatus::Unauthorized)->toResponse();
        }

        return $handler->handle($request);
    }
}
